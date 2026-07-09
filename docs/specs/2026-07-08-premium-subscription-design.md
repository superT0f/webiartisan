# WebiArtisan Premium Subscription — Design Spec

> Date: 2026-07-08  
> Scope: artisan premium subscription via Stripe, open-directory claim flow, enriched Flutter app.

---

## 1. Goal

Allow local artisans/merchants to subscribe for **2.99 €/month** to unlock premium mini-games and gamification. The platform remains open: all listings are public, artisans can claim an existing imported listing, and visitors play for free.

---

## 2. Guiding Principles

- **Visitor/consumer stays free.** Revenue comes from artisans, like turning on a “pocket-stop.”
- **Phased rollout.** Validate monetisation before investing in mass data import.
- **One Stripe config for all cities.** Return URLs are dynamic; no per-city Stripe setup.
- **Keep it simple.** Reuse existing `local_artisans`, `local_game_types`, and Stripe service scaffolding.

---

## 3. Phases

| Phase | Focus | Deployable Alone |
|-------|-------|------------------|
| 1 | Stripe subscription + premium gating + boosted admin | Yes |
| 2 | Mass import (OSM/SIRENE/manual) + claim flow + QR-code claim | Yes |
| 3 | Flutter wrapper enriched (QR-code bridge + fixed geolocation) | Yes |

---

## 4. Data Model

### 4.1 `local_artisans` additions

```sql
ALTER TABLE local_artisans
    ADD COLUMN plan ENUM('free','premium') DEFAULT 'free',
    ADD COLUMN stripe_customer_id VARCHAR(255) NULL,
    ADD COLUMN stripe_subscription_id VARCHAR(255) NULL,
    ADD COLUMN subscription_status ENUM('active','past_due','canceled','unpaid','incomplete') NULL,
    ADD COLUMN subscription_period_end DATETIME NULL,
    ADD COLUMN subscription_canceled_at DATETIME NULL,
    ADD COLUMN claimed_at DATETIME NULL,
    ADD COLUMN claimed_by_artisan_id INT NULL,
    ADD COLUMN source ENUM('manual','osm','sirene') DEFAULT 'manual',
    ADD COLUMN is_imported TINYINT(1) NOT NULL DEFAULT 0;
```

### 4.2 New `artisan_subscription_events`

```sql
CREATE TABLE artisan_subscription_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    artisan_id INT NOT NULL,
    stripe_event_id VARCHAR(255) NOT NULL UNIQUE,
    event_type VARCHAR(64) NOT NULL,
    payload JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 4.3 New `artisan_claim_requests`

```sql
CREATE TABLE artisan_claim_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    artisan_id INT NOT NULL,
    requested_by_artisan_id INT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    proof_type ENUM('email','siret','document','qr_code') NOT NULL,
    proof_value TEXT,
    claim_token VARCHAR(64) NULL,
    claim_token_expires_at DATETIME NULL,
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 4.4 Reusing legacy tables

The legacy `subscriptions`/`plan_usage` tables are tied to `tenant_id` and the old CRM. **Do not reuse them** for local artisans.

---

## 5. Stripe Checkout Flow

### 5.1 API endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/artisans/me/subscription/checkout` | Create Stripe Checkout session for monthly subscription |
| GET | `/artisans/me/subscription` | Current plan and subscription status |
| POST | `/artisans/me/subscription/portal` | Create Stripe Billing Portal session |
| POST | `/webhooks/stripe` | Receive Stripe webhooks |

### 5.2 Checkout request/response

Request:
```json
{ "return_url": "https://artisans-livry.prigent.tech/espace" }
```

Response:
```json
{ "success": true, "url": "https://checkout.stripe.com/..." }
```

The `return_url` is validated against an allow-list:
- `https://artisans-livry.prigent.tech/espace`
- `https://artisans-combs.prigent.tech/espace`
- `https://artisans-vert-saint-denis.prigent.tech/espace`

### 5.3 Webhook events

| Event | Action |
|-------|--------|
| `checkout.session.completed` | Link `customer`/`subscription` to artisan; set `plan = 'premium'`, `subscription_status = 'active'` |
| `customer.subscription.updated` | Update `subscription_status` and `subscription_period_end` |
| `customer.subscription.deleted` | Set `plan = 'free'`, `subscription_status = 'canceled'` |

### 5.4 Environment variables

```bash
STRIPE_SECRET_KEY=sk_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PREMIUM_MONTHLY_PRICE_ID=price_...
```

---

## 6. Premium Gating

### 6.1 Plan-aware helper

```php
function artisanIsPremium(PDO $pdo, int $artisanId): bool {
    $stmt = $pdo->prepare("SELECT plan FROM local_artisans WHERE id = ?");
    $stmt->execute([$artisanId]);
    return $stmt->fetchColumn() === 'premium';
}
```

### 6.2 Gated features

| Feature | Free | Premium |
|---------|------|---------|
| Public profile | Yes | Yes |
| Coupon, poll, vote games | Yes | Yes |
| Wheel, quiz, battle, bingo, rebus | No | Yes |
| Spin offers | No | Yes |
| Active games limit | 2 | Unlimited |
| Active services limit | 5 | Unlimited |

### 6.3 Enforcement points

- `POST /artisans/me/games` — reject premium `game_type_key` if free.
- `POST /artisans/me/spin-offers` — require premium.
- `POST /artisans/me/services` — enforce 5-service limit for free.
- `POST /artisans/me/games` — enforce 2-active-games limit for free.

### 6.4 Frontend changes

- `GamesConfig.vue` hides/disables premium game types for free artisans.
- `FreemiumLimitBanner.vue` shows upgrade CTA.
- `Dashboard.vue` shows “Passer Premium” button when free.

---

## 7. Open Directory & Claim Flow

### 7.1 Mass import (admin only)

| Endpoint | Description |
|----------|-------------|
| POST `/admin/imports/osm` | Import from OSM/Overpass bbox |
| POST `/admin/imports/sirene` | Import from SIRENE API |
| GET `/admin/imports/jobs/:id` | Track import job |

Imported artisans are inserted with:
- `status = 'unclaimed'`
- `source = 'osm'` or `'sirene'`
- `is_imported = 1`
- No password hash

### 7.2 Claim request

Endpoint: `POST /artisans/claim`

Request:
```json
{
  "artisan_id": 123,
  "proof_type": "email",
  "proof_value": "contact@commerce.fr"
}
```

Admin validates via:
- `POST /admin/claims/:id/approve`
- `POST /admin/claims/:id/reject`

On approval the listing is linked to the artisan and status becomes active.

### 7.3 QR-code claim (admin only)

For door-to-door use by the admin only:

1. Admin clicks “Générer QR de revendication” on an imported listing.
2. API creates a single-use token valid 7 days in `artisan_claim_requests`.
3. QR code points to `https://artisans-<city>.prigent.tech/revendiquer?token=abc123`.
4. Merchant scans, sees the business name, enters email, submits claim.
5. Admin approves/rejects in dashboard.

---

## 8. Boosted Admin

New admin-only dashboard capabilities:

- List/search artisans.
- Activate, suspend, delete artisans.
- View and approve/reject claim requests.
- Launch and track OSM/SIRENE imports.
- Force plan changes (`POST /admin/artisans/:id/set-plan`).
- View subscription event history.

All admin endpoints require `artisan_require_auth()` + `is_admin = 1`.

---

## 9. Flutter App (Phase 3)

Keep the existing WebView wrapper architecture. Add/enhance native bridges:

### 9.1 QR-code scanner bridge

Channel: `FlutterScanner`
Action: `scan`

The web app calls `scan()`, Flutter opens the camera, returns scanned text/URL.

### 9.2 Fix geolocation

Move `MethodChannel` registration from `LocationService.kt` to `MainActivity.kt` or remove the unused `LocationService`.

### 9.3 Deferred

- Push notifications
- Deep links

---

## 10. Security

- Stripe webhook signature verification mandatory.
- Store every Stripe event in `artisan_subscription_events` for idempotence.
- Return URLs validated against allow-list.
- Never expose `stripe_customer_id`/`stripe_subscription_id` to frontend.
- Admin endpoints protected by admin flag.

---

## 11. Testing

- Webhook tests with Stripe test payloads.
- Premium gating tests.
- Claim approval/rejection tests.
- Import job tests.
- Checkout session creation tests (mocked Stripe).

---

## 12. Open Questions

None remaining after validation. All sections approved by product owner.
