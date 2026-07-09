# Premium Subscription Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Stripe subscription payments (2.99 €/month) for artisans, gate premium mini-games behind the subscription, and boost the admin dashboard.

**Architecture:** Extend `local_artisans` with Stripe subscription state. Add a dedicated `StripeSubscriptionService` and new API routes under `/artisans/me/subscription`. Webhooks update the artisan plan. Existing premium checks on `local_game_types` are replaced with a real plan check.

**Tech Stack:** PHP 8.4, PDO, MySQL, Stripe PHP SDK, Vue 3, Vite.

---

## File map

| File | Responsibility |
|------|----------------|
| `sites/api/migrations/033_artisan_premium.sql` | Adds subscription columns to `local_artisans`; creates `artisan_subscription_events`. |
| `sites/api/lib/StripeSubscriptionService.php` | Creates Checkout sessions, Billing Portal sessions, validates webhooks. |
| `sites/api/config/app.php` or `.env` | Holds Stripe price ID and secrets. |
| `sites/api/routes/subscriptions.php` | New route file: checkout, status, portal endpoints. |
| `sites/api/routes/webhooks.php` | Stripe webhook handler. |
| `sites/api/routes/games.php` | Update premium gating to check artisan plan. |
| `sites/api/routes/artisans.php` | Update service/spin-offer limits; add admin endpoints. |
| `sites/api/lib/Games.php` | Replace hardcoded premium check with plan check. |
| `sites/artisans-shared/src/api.js` | Add subscription helpers. |
| `sites/artisans-shared/src/views/Dashboard.vue` | Premium CTA, status display. |
| `sites/artisans-shared/src/views/GamesConfig.vue` | Gate premium game types. |
| `sites/artisans-shared/src/views/FreemiumLimitBanner.vue` | Upgrade banner. |
| `sites/artisans-shared/src/views/SubscriptionSuccess.vue` | Success page after Stripe. |
| `sites/artisans-shared/src/views/SubscriptionCancel.vue` | Cancel page after Stripe. |
| `sites/artisans-shared/src/main.js` | Register success/cancel routes. |
| `sites/artisans-shared/src/views/AdminDashboard.vue` | New admin tab for subscriptions/artisans. |
| `scripts/test-subscriptions.php` | Smoke test for checkout + webhook handling. |
| `Makefile` | Add migration 033 to `make migrate`. |
| `docker-compose.yml` | Add migration 033 to initdb. |

---

### Task 1: Database migration

**Files:**
- Create: `sites/api/migrations/033_artisan_premium.sql`

- [ ] **Step 1: Write the migration**

```sql
-- ============================================================
-- WebiArtisan — Migration 033 : Artisan premium subscription
-- ============================================================
SET NAMES utf8mb4;

ALTER TABLE local_artisans
    ADD COLUMN plan ENUM('free','premium') NOT NULL DEFAULT 'free'
        AFTER status,
    ADD COLUMN stripe_customer_id VARCHAR(255) NULL
        AFTER plan,
    ADD COLUMN stripe_subscription_id VARCHAR(255) NULL
        AFTER stripe_customer_id,
    ADD COLUMN subscription_status ENUM('active','past_due','canceled','unpaid','incomplete') NULL
        AFTER stripe_subscription_id,
    ADD COLUMN subscription_period_end DATETIME NULL
        AFTER subscription_status,
    ADD COLUMN subscription_canceled_at DATETIME NULL
        AFTER subscription_period_end,
    ADD COLUMN claimed_at DATETIME NULL
        AFTER subscription_canceled_at,
    ADD COLUMN claimed_by_artisan_id INT NULL
        AFTER claimed_at,
    ADD COLUMN source ENUM('manual','osm','sirene') NOT NULL DEFAULT 'manual'
        AFTER claimed_by_artisan_id,
    ADD COLUMN is_imported TINYINT(1) NOT NULL DEFAULT 0
        AFTER source;

CREATE TABLE IF NOT EXISTS artisan_subscription_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    artisan_id INT NOT NULL,
    stripe_event_id VARCHAR(255) NOT NULL UNIQUE,
    event_type VARCHAR(64) NOT NULL,
    payload JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_artisan_id (artisan_id),
    INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Run migration locally**

Run:
```bash
docker compose up -d --build
make migrate
```

Expected: migration applies without error.

- [ ] **Step 3: Verify columns**

Run:
```bash
docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan -e "SHOW COLUMNS FROM local_artisans;"
```

Expected: columns `plan`, `stripe_customer_id`, `stripe_subscription_id`, `subscription_status`, `subscription_period_end`, `subscription_canceled_at`, `claimed_at`, `claimed_by_artisan_id`, `source`, `is_imported` exist.

- [ ] **Step 4: Commit**

```bash
git add sites/api/migrations/033_artisan_premium.sql Makefile docker-compose.yml
git commit -m "feat(db): add artisan premium subscription columns and events table"
```

---

### Task 2: Stripe configuration and service

**Files:**
- Modify: `sites/api/.env.example`
- Create: `sites/api/lib/StripeSubscriptionService.php`

- [ ] **Step 1: Add Stripe env vars to `.env.example`**

```bash
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PREMIUM_MONTHLY_PRICE_ID=price_...
```

- [ ] **Step 2: Create `StripeSubscriptionService.php`**

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

class StripeSubscriptionService
{
    private string $secretKey;
    private string $webhookSecret;
    private string $monthlyPriceId;

    public function __construct()
    {
        $this->secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
        $this->webhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
        $this->monthlyPriceId = $_ENV['STRIPE_PREMIUM_MONTHLY_PRICE_ID'] ?? '';

        if (!$this->secretKey || !$this->monthlyPriceId) {
            throw new RuntimeException('Stripe configuration missing');
        }

        \Stripe\Stripe::setApiKey($this->secretKey);
    }

    public function createCheckoutSession(array $artisan, string $returnUrl): string
    {
        $allowed = [
            'https://artisans-livry.prigent.tech/espace',
            'https://artisans-combs.prigent.tech/espace',
            'https://artisans-vert-saint-denis.prigent.tech/espace',
        ];
        if (!in_array($returnUrl, $allowed, true)) {
            throw new InvalidArgumentException('Invalid return URL');
        }

        $successUrl = rtrim($returnUrl, '/') . '?subscription=success&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = rtrim($returnUrl, '/') . '?subscription=cancel';

        $session = \Stripe\Checkout\Session::create([
            'mode' => 'subscription',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $this->monthlyPriceId,
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $artisan['email'] ?? null,
            'metadata' => [
                'artisan_id' => (string)$artisan['id'],
            ],
        ]);

        return $session->url;
    }

    public function createPortalSession(string $customerId, string $returnUrl): string
    {
        $allowed = [
            'https://artisans-livry.prigent.tech/espace',
            'https://artisans-combs.prigent.tech/espace',
            'https://artisans-vert-saint-denis.prigent.tech/espace',
        ];
        if (!in_array($returnUrl, $allowed, true)) {
            throw new InvalidArgumentException('Invalid return URL');
        }

        $session = \Stripe\BillingPortal\Session::create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        return $session->url;
    }

    public function constructEvent(string $payload, string $signature): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent($payload, $signature, $this->webhookSecret);
    }

    public function getMonthlyPriceId(): string
    {
        return $this->monthlyPriceId;
    }
}
```

- [ ] **Step 3: Verify syntax**

Run:
```bash
php -l sites/api/lib/StripeSubscriptionService.php
```

Expected: `No syntax errors detected in sites/api/lib/StripeSubscriptionService.php`

- [ ] **Step 4: Commit**

```bash
git add sites/api/lib/StripeSubscriptionService.php sites/api/.env.example
git commit -m "feat(stripe): add subscription service for checkout and portal"
```

---

### Task 3: Subscription API routes

**Files:**
- Create: `sites/api/routes/subscriptions.php`
- Modify: `sites/api/index.php` (router dispatch)

- [ ] **Step 1: Create `subscriptions.php`**

```php
<?php
require_once __DIR__ . '/../lib/StripeSubscriptionService.php';

const SUBSCRIPTION_RETURN_URLS = [
    'https://artisans-livry.prigent.tech/espace',
    'https://artisans-combs.prigent.tech/espace',
    'https://artisans-vert-saint-denis.prigent.tech/espace',
];

$artisan = artisan_require_auth($pdo);

if ($method !== 'POST' && $method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    return;
}

if ($action === 'checkout' && $method === 'POST') {
    handleSubscriptionCheckout($pdo, $artisan, $body);
} elseif ($action === 'portal' && $method === 'POST') {
    handleSubscriptionPortal($pdo, $artisan, $body);
} elseif ($action === 'status' && $method === 'GET') {
    handleSubscriptionStatus($pdo, $artisan);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
}

function handleSubscriptionCheckout(PDO $pdo, array $artisan, array $body): void
{
    $returnUrl = $body['return_url'] ?? ($_SERVER['HTTP_ORIGIN'] ?? '') . '/espace';
    if (!in_array($returnUrl, SUBSCRIPTION_RETURN_URLS, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'URL de retour invalide']);
        return;
    }

    try {
        $service = new StripeSubscriptionService();
        $url = $service->createCheckoutSession($artisan, $returnUrl);
        echo json_encode(['success' => true, 'url' => $url]);
    } catch (Throwable $e) {
        error_log('[SUBSCRIPTION-CHECKOUT] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la création du paiement']);
    }
}

function handleSubscriptionPortal(PDO $pdo, array $artisan, array $body): void
{
    if (empty($artisan['stripe_customer_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucun abonnement actif']);
        return;
    }

    $returnUrl = $body['return_url'] ?? ($_SERVER['HTTP_ORIGIN'] ?? '') . '/espace';
    if (!in_array($returnUrl, SUBSCRIPTION_RETURN_URLS, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'URL de retour invalide']);
        return;
    }

    try {
        $service = new StripeSubscriptionService();
        $url = $service->createPortalSession($artisan['stripe_customer_id'], $returnUrl);
        echo json_encode(['success' => true, 'url' => $url]);
    } catch (Throwable $e) {
        error_log('[SUBSCRIPTION-PORTAL] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'ouverture du portail']);
    }
}

function handleSubscriptionStatus(PDO $pdo, array $artisan): void
{
    echo json_encode([
        'success' => true,
        'data' => [
            'plan' => $artisan['plan'],
            'subscription_status' => $artisan['subscription_status'],
            'subscription_period_end' => $artisan['subscription_period_end'],
        ],
    ]);
}
```

- [ ] **Step 2: Wire route in `index.php`**

Find the route dispatch block and add:

```php
} elseif ($route === 'subscription') {
    require __DIR__ . '/routes/subscriptions.php';
}
```

(Adapt the exact insertion point to match the current `index.php` switch/if structure.)

- [ ] **Step 3: Verify syntax**

Run:
```bash
php -l sites/api/routes/subscriptions.php
php -l sites/api/index.php
```

Expected: no syntax errors.

- [ ] **Step 4: Commit**

```bash
git add sites/api/routes/subscriptions.php sites/api/index.php
git commit -m "feat(api): add subscription checkout, portal and status endpoints"
```

---

### Task 4: Stripe webhook handler

**Files:**
- Create: `sites/api/routes/webhooks.php`
- Modify: `sites/api/index.php`

- [ ] **Step 1: Create `webhooks.php`**

```php
<?php
require_once __DIR__ . '/../lib/StripeSubscriptionService.php';

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    return;
}

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!$signature) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Signature manquante']);
    return;
}

try {
    $service = new StripeSubscriptionService();
    $event = $service->constructEvent($payload, $signature);
} catch (Throwable $e) {
    error_log('[STRIPE-WEBHOOK] Signature invalid: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Signature invalide']);
    return;
}

$eventId = $event->id;
$existing = $pdo->prepare("SELECT id FROM artisan_subscription_events WHERE stripe_event_id = ?");
$existing->execute([$eventId]);
if ($existing->fetch()) {
    echo json_encode(['received' => true, 'duplicate' => true]);
    return;
}

$pdo->prepare("
    INSERT INTO artisan_subscription_events (artisan_id, stripe_event_id, event_type, payload)
    VALUES (?, ?, ?, ?)
")->execute([
    0,
    $eventId,
    $event->type,
    json_encode($event->data->object->toArray()),
]);

switch ($event->type) {
    case 'checkout.session.completed':
        handleCheckoutSessionCompleted($pdo, $event->data->object);
        break;
    case 'customer.subscription.updated':
    case 'customer.subscription.deleted':
        handleSubscriptionUpdated($pdo, $event->data->object);
        break;
}

echo json_encode(['received' => true]);

function handleCheckoutSessionCompleted(PDO $pdo, \Stripe\Checkout\Session $session): void
{
    $metadata = $session->metadata->toArray();
    $artisanId = (int)($metadata['artisan_id'] ?? 0);
    if (!$artisanId) return;

    $customerId = $session->customer;
    $subscriptionId = $session->subscription;

    if (!$subscriptionId) return;

    $subscription = \Stripe\Subscription::retrieve($subscriptionId);

    $pdo->prepare("
        UPDATE local_artisans
        SET plan = 'premium',
            stripe_customer_id = ?,
            stripe_subscription_id = ?,
            subscription_status = ?,
            subscription_period_end = FROM_UNIXTIME(?)
        WHERE id = ?
    ")->execute([
        $customerId,
        $subscriptionId,
        $subscription->status,
        $subscription->current_period_end,
        $artisanId,
    ]);
}

function handleSubscriptionUpdated(PDO $pdo, \Stripe\Subscription $subscription): void
{
    $stmt = $pdo->prepare("SELECT id FROM local_artisans WHERE stripe_subscription_id = ?");
    $stmt->execute([$subscription->id]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$artisan) return;

    $plan = $subscription->status === 'active' ? 'premium' : 'free';

    $pdo->prepare("
        UPDATE local_artisans
        SET plan = ?,
            subscription_status = ?,
            subscription_period_end = FROM_UNIXTIME(?),
            subscription_canceled_at = ?
        WHERE id = ?
    ")->execute([
        $plan,
        $subscription->status,
        $subscription->current_period_end,
        $subscription->canceled_at ? date('Y-m-d H:i:s', $subscription->canceled_at) : null,
        $artisan['id'],
    ]);
}
```

- [ ] **Step 2: Wire webhook route in `index.php`**

Add:
```php
} elseif ($route === 'webhooks' && $action === 'stripe') {
    require __DIR__ . '/routes/webhooks.php';
}
```

- [ ] **Step 3: Verify syntax**

Run:
```bash
php -l sites/api/routes/webhooks.php
php -l sites/api/index.php
```

Expected: no syntax errors.

- [ ] **Step 4: Commit**

```bash
git add sites/api/routes/webhooks.php sites/api/index.php
git commit -m "feat(api): add Stripe webhook handler for subscriptions"
```

---

### Task 5: Premium gating

**Files:**
- Modify: `sites/api/lib/Games.php`
- Modify: `sites/api/routes/games.php`
- Modify: `sites/api/routes/artisans.php`

- [ ] **Step 1: Add `artisanIsPremium` helper**

In `sites/api/lib/Games.php` (or create `sites/api/lib/ArtisanPlan.php`), add:

```php
<?php
function artisanIsPremium(PDO $pdo, int $artisanId): bool
{
    $stmt = $pdo->prepare("SELECT plan FROM local_artisans WHERE id = ?");
    $stmt->execute([$artisanId]);
    return $stmt->fetchColumn() === 'premium';
}
```

- [ ] **Step 2: Replace hardcoded premium checks in `Games.php`**

Find existing checks like `$gameType['is_premium']` and replace the decision with:

```php
$isPremium = (bool)($gameType['is_premium'] ?? 0);
if ($isPremium && !artisanIsPremium($pdo, $artisanId)) {
    // reject
}
```

- [ ] **Step 3: Update `routes/games.php` consumer block**

Remove or adjust any consumer-side “premium game” block. Visitors play for free once an artisan premium has activated the game.

- [ ] **Step 4: Update spin-offer creation in `routes/artisans.php`**

Require premium:

```php
if (!artisanIsPremium($pdo, $artisan['id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Cette fonctionnalité nécessite l\'abonnement Premium']);
    return;
}
```

- [ ] **Step 5: Update service limit in `routes/artisans.php`**

Enforce 5-service limit for free plan.

- [ ] **Step 6: Verify syntax and commit**

Run:
```bash
php -l sites/api/lib/Games.php
php -l sites/api/routes/games.php
php -l sites/api/routes/artisans.php
```

Commit:
```bash
git add sites/api/lib/Games.php sites/api/routes/games.php sites/api/routes/artisans.php
git commit -m "feat(api): gate premium games, spin and services by artisan plan"
```

---

### Task 6: Frontend API helpers

**Files:**
- Modify: `sites/artisans-shared/src/api.js`

- [ ] **Step 1: Add subscription helpers**

After artisan auth helpers, add:

```javascript
export async function createSubscriptionCheckout(returnUrl) {
  const res = await fetch(`${API_BASE}/subscription/checkout`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Artisan-Token': getArtisanToken() },
    body: JSON.stringify({ return_url: returnUrl }),
  })
  return res.json()
}

export async function getSubscriptionStatus() {
  const res = await fetch(`${API_BASE}/subscription/status`, {
    headers: { 'X-Artisan-Token': getArtisanToken() },
  })
  return res.json()
}

export async function createSubscriptionPortal(returnUrl) {
  const res = await fetch(`${API_BASE}/subscription/portal`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Artisan-Token': getArtisanToken() },
    body: JSON.stringify({ return_url: returnUrl }),
  })
  return res.json()
}
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/api.js
git commit -m "feat(frontend): add subscription API helpers"
```

---

### Task 7: Frontend premium UI

**Files:**
- Modify: `sites/artisans-shared/src/views/Dashboard.vue`
- Modify: `sites/artisans-shared/src/views/GamesConfig.vue`
- Modify: `sites/artisans-shared/src/views/FreemiumLimitBanner.vue`
- Create: `sites/artisans-shared/src/views/SubscriptionSuccess.vue`
- Create: `sites/artisans-shared/src/views/SubscriptionCancel.vue`
- Modify: `sites/artisans-shared/src/main.js`

- [ ] **Step 1: Add premium status to Dashboard**

On mount, call `getSubscriptionStatus()` and store in `subscriptionStatus` ref.

Show:
- If free: button “Passer Premium — 2,99 €/mois” → calls `createSubscriptionCheckout(window.location.origin + '/espace')` → redirect to Stripe.
- If premium: badge “Premium actif” + button “Gérer mon abonnement” → portal.

- [ ] **Step 2: Gate premium game types in GamesConfig**

Disable/hide premium `game_type_key` when free. Show upgrade CTA.

- [ ] **Step 3: Update FreemiumLimitBanner**

Add a “Passer Premium” button that redirects to checkout.

- [ ] **Step 4: Create success/cancel pages**

`SubscriptionSuccess.vue`:
```vue
<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getSubscriptionStatus } from '../api.js'

const route = useRoute()
const router = useRouter()
const loading = ref(true)

onMounted(async () => {
  await getSubscriptionStatus()
  setTimeout(() => router.push('/espace'), 3000)
})
</script>
<template>
  <div class="page">
    <h1>Abonnement activé !</h1>
    <p>Merci. Vous allez être redirigé vers votre espace.</p>
  </div>
</template>
```

`SubscriptionCancel.vue`: simple message “Paiement annulé. Vous pouvez réessayer quand vous voulez.” with a link to `/espace`.

- [ ] **Step 5: Register routes**

In `main.js`:
```javascript
{
  path: '/abonnement/success',
  component: () => import('./views/SubscriptionSuccess.vue'),
  meta: { title: 'Abonnement activé' }
},
{
  path: '/abonnement/cancel',
  component: () => import('./views/SubscriptionCancel.vue'),
  meta: { title: 'Paiement annulé' }
}
```

- [ ] **Step 6: Commit**

```bash
git add sites/artisans-shared/src/api.js sites/artisans-shared/src/views/Dashboard.vue sites/artisans-shared/src/views/GamesConfig.vue sites/artisans-shared/src/views/FreemiumLimitBanner.vue sites/artisans-shared/src/views/SubscriptionSuccess.vue sites/artisans-shared/src/views/SubscriptionCancel.vue sites/artisans-shared/src/main.js
git commit -m "feat(frontend): premium CTA, gating and subscription result pages"
```

---

### Task 8: Admin dashboard

**Files:**
- Create: `sites/api/routes/admin.php`
- Modify: `sites/api/index.php`
- Modify: `sites/artisans-shared/src/views/Dashboard.vue`
- Create: `sites/artisans-shared/src/views/AdminDashboard.vue`

- [ ] **Step 1: Create admin API routes**

```php
<?php
$artisan = artisan_require_auth($pdo);
if (empty($artisan['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès réservé']);
    return;
}

if ($method === 'GET' && $action === 'artisans') {
    $stmt = $pdo->query("SELECT id, company_name, email, city_id, plan, subscription_status, status FROM local_artisans ORDER BY id DESC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
} elseif ($method === 'POST' && $action === 'artisans' && $param === 'activate') {
    $pdo->prepare("UPDATE local_artisans SET status = 'active' WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
} elseif ($method === 'POST' && $action === 'artisans' && $param === 'suspend') {
    $pdo->prepare("UPDATE local_artisans SET status = 'suspended' WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
} elseif ($method === 'POST' && $action === 'artisans' && $param === 'set-plan') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $plan = in_array($body['plan'] ?? '', ['free','premium'], true) ? $body['plan'] : 'free';
    $pdo->prepare("UPDATE local_artisans SET plan = ? WHERE id = ?")->execute([$plan, $id]);
    echo json_encode(['success' => true]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
}
```

- [ ] **Step 2: Wire admin route in `index.php`**

Add:
```php
} elseif ($route === 'admin') {
    require __DIR__ . '/routes/admin.php';
}
```

- [ ] **Step 3: Create AdminDashboard.vue**

Simple table listing artisans with actions: Activate, Suspend, Set Free/Premium.

- [ ] **Step 4: Add admin tab to Dashboard.vue**

Show “Admin” tab only when `artisan.is_admin === 1`.

- [ ] **Step 5: Commit**

```bash
git add sites/api/routes/admin.php sites/api/index.php sites/artisans-shared/src/views/AdminDashboard.vue sites/artisans-shared/src/views/Dashboard.vue
git commit -m "feat(admin): admin dashboard for artisans and subscriptions"
```

---

### Task 9: Smoke tests

**Files:**
- Create: `sites/api/tests/test_subscriptions.php`

- [ ] **Step 1: Create test**

```php
<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDatabase();

// 1. Status endpoint requires auth
echo "Test status without auth...\n";
$ch = curl_init('http://nginx/api/subscription/status');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($code !== 401) {
    echo "FAIL: expected 401, got $code\n";
    exit(1);
}
echo "OK: status requires auth\n";

// 2. Create a test artisan and verify helper
echo "Test artisanIsPremium helper...\n";
require_once __DIR__ . '/../lib/Games.php';
$email = 'premium-test-' . time() . '@example.com';
$pdo->prepare("DELETE FROM local_artisans WHERE email = ?")->execute([$email]);
$pdo->prepare("INSERT INTO local_artisans (company_name, city_id, category_id, email, phone, status, plan) VALUES (?, ?, ?, ?, ?, 'active', 'free')")->execute([
    'Premium Test', 1, 1, $email, '0100000000'
]);
$artisanId = (int)$pdo->lastInsertId();

if (artisanIsPremium($pdo, $artisanId)) {
    echo "FAIL: artisan should be free\n";
    exit(1);
}

$pdo->prepare("UPDATE local_artisans SET plan = 'premium' WHERE id = ?")->execute([$artisanId]);
if (!artisanIsPremium($pdo, $artisanId)) {
    echo "FAIL: artisan should be premium\n";
    exit(1);
}
echo "OK: premium helper works\n";

// Cleanup
$pdo->prepare("DELETE FROM local_artisans WHERE id = ?")->execute([$artisanId]);
echo "OK: cleanup\n";
```

- [ ] **Step 2: Run test**

```bash
docker compose exec -T php php /var/www/api/tests/test_subscriptions.php
```

Expected: all tests pass.

- [ ] **Step 3: Commit**

```bash
git add sites/api/tests/test_subscriptions.php
git commit -m "test(api): add subscription smoke tests"
```

---

### Task 10: Full test suite and deployment

**Files:** none

- [ ] **Step 1: Run `make test-api`**

```bash
make test-api
```

Expected: `✅ All API integration tests passed.`

- [ ] **Step 2: Run subscription smoke test**

```bash
docker compose exec -T php php /var/www/api/tests/test_subscriptions.php
```

Expected: all OK.

- [ ] **Step 3: Build frontends**

```bash
make build
make build-combs
make build-vsd
```

- [ ] **Step 4: Deploy**

```bash
make push-api
make push-livry
make push-combs
make push-vsd
```

- [ ] **Step 5: Production Stripe setup**

1. Create Stripe Product “Premium Artisan” with monthly recurring price 2.99 €.
2. Copy Price ID to `STRIPE_PREMIUM_MONTHLY_PRICE_ID` in `/home/tof/mnt/gandi/vhosts/api.prigent.tech/htdocs/.env`.
3. Add `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` to production `.env`.
4. Configure Stripe webhook endpoint: `https://api.prigent.tech/webhooks/stripe`.
5. Listen to events: `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`.

- [ ] **Step 6: Run migration in production**

Execute `sites/api/migrations/033_artisan_premium.sql` via phpMyAdmin.

- [ ] **Step 7: Production verification**

1. Register/login as artisan on production.
2. Click “Passer Premium”.
3. Complete Stripe test payment.
4. Verify webhook updates `local_artisans.plan` to `premium`.
5. Verify premium game types become available.
6. Verify admin endpoints work for an `is_admin = 1` artisan.

---

## Self-review checklist

- [ ] Spec coverage: Phase 1 (subscription, gating, admin) fully covered.
- [ ] Placeholder scan: no TBD/TODO/vague instructions.
- [ ] Type consistency: `plan` enum values match everywhere (`free`, `premium`).
- [ ] Scope: Phase 1 only; import/claim/Flutter deferred to later plans.
