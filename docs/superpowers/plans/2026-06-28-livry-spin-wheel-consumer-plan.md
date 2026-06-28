# Livry — Roue consommateur géolocalisée (Spin Wheel)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre à un utilisateur authentifié de tourner une roue **une fois par jour** pour gagner une offre d’un artisan de Livry, dans la limite des stocks définis par l’artisan. L’offre gagnée est matérialisée par un **code unique** que l’artisan peut valider.

**Architecture:** Back PHP/MySQL avec tables `local_users`, `local_spin_offers`, `local_spin_wins`, `local_spin_daily_limits` ; routes API publiques + privées ; front Vue 3 partagé sous `sites/artisans-shared/` avec de nouvelles vues et un `api.js` enrichi.

**Tech Stack:** PHP 8.4, MySQL 8, Vue 3, Vue Router, Vite, qrcode (npm).

---

## File structure

| File | Responsibility |
|------|----------------|
| `sites/api/migrations/027_spin_wheel.sql` | Création des tables users, offres, gains, limites journalières |
| `sites/api/lib/UserAuth.php` | Helpers d’authentification consommateur (token session) |
| `sites/api/routes/users.php` | API auth consommateur (magic-link, auth, me) |
| `sites/api/routes/spin.php` | API publique/privée de la roue (offers, spin, wins) |
| `sites/api/routes/artisans.php` | Endpoints artisans : CRUD offres + validation gains |
| `sites/api/index.php` | Routage des modules `users`, `spin` |
| `docker-compose.yml` | Monte la migration 027 en initdb |
| `Makefile` | Commande `make migrate` exécute 025, 026, 027 |
| `sites/artisans-shared/src/api.js` | Appels API front |
| `sites/artisans-shared/src/main.js` | Déclaration des routes `/roue`, `/espace/spin-offers`, `/espace/spin-wins` |
| `sites/artisans-shared/src/components/AppNav.vue` | Liens vers la roue |
| `sites/artisans-shared/src/views/Dashboard.vue` | Liens espace artisan vers offres / gains |
| `sites/artisans-shared/src/views/SpinWheel.vue` | Page roue + auth + affichage gain |
| `sites/artisans-shared/src/views/SpinOffers.vue` | CRUD offres par l’artisan |
| `sites/artisans-shared/src/views/SpinWins.vue` | Liste et validation des gains par l’artisan |
| `sites/artisans-shared/package.json` | Dépendance `qrcode` |
| `scripts/test-api.sh` | Tests du flux magic-link → spin → validation |

---

## Phase 1 — Auth utilisateur + tables SQL

### Task 1: Migration SQL spin wheel

**Files:**
- Create: `sites/api/migrations/027_spin_wheel.sql`

- [ ] **Step 1: Write migration**

```sql
-- ============================================================
-- WebIArtisan — Migration 027 : Roue consommateur (Spin Wheel)
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS local_users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) UNIQUE NOT NULL,
    magic_token     VARCHAR(64) DEFAULT NULL,
    magic_token_exp DATETIME DEFAULT NULL,
    session_token   VARCHAR(64) DEFAULT NULL,
    session_exp     DATETIME DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_session (session_token, session_exp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Utilisateurs consommateurs de la roue';

CREATE TABLE IF NOT EXISTS local_spin_offers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    artisan_id      INT NOT NULL,
    label           VARCHAR(200) NOT NULL COMMENT 'Texte affiche sur la roue',
    description     TEXT,
    stock_total     INT NOT NULL DEFAULT 0,
    stock_remaining INT NOT NULL DEFAULT 0,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (artisan_id) REFERENCES local_artisans(id) ON DELETE CASCADE,
    INDEX idx_artisan (artisan_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Offres promotionnelles pour la roue';

CREATE TABLE IF NOT EXISTS local_spin_wins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    offer_id    INT NOT NULL,
    artisan_id  INT NOT NULL,
    code        VARCHAR(32) UNIQUE NOT NULL,
    status      ENUM('pending','claimed','expired') DEFAULT 'pending',
    spin_date   DATE NOT NULL,
    claimed_at  TIMESTAMP NULL,
    expires_at  TIMESTAMP NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES local_users(id)    ON DELETE CASCADE,
    FOREIGN KEY (offer_id)   REFERENCES local_spin_offers(id) ON DELETE RESTRICT,
    FOREIGN KEY (artisan_id) REFERENCES local_artisans(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_artisan_status (artisan_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Gains des utilisateurs';

CREATE TABLE IF NOT EXISTS local_spin_daily_limits (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    city_id     INT NOT NULL,
    spin_date   DATE NOT NULL,
    count       INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_daily (user_id, city_id, spin_date),
    FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    FOREIGN KEY (city_id) REFERENCES local_cities(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, spin_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Limite de spins par utilisateur, ville et jour';
```

- [ ] **Step 2: Commit**

```bash
cd /mnt/c/Users/user/code/webiartisan.new
git add sites/api/migrations/027_spin_wheel.sql
git commit -m "feat(db): add spin wheel tables"
```

### Task 2: Docker compose + Makefile

**Files:**
- Modify: `docker-compose.yml`
- Modify: `Makefile`

- [ ] **Step 1: Add migration 027 to mysql volumes**

Replace the existing `mysql` volumes block with:

```yaml
    volumes:
      - mysql_data:/var/lib/mysql
      - ./sites/api/migrations/025_artisans_local.sql:/docker-entrypoint-initdb.d/025_artisans_local.sql:ro
      - ./sites/api/migrations/026_b2b_recipes.sql:/docker-entrypoint-initdb.d/026_b2b_recipes.sql:ro
      - ./sites/api/migrations/027_spin_wheel.sql:/docker-entrypoint-initdb.d/027_spin_wheel.sql:ro
      - ./data/seeds/livry.sql:/docker-entrypoint-initdb.d/livry.sql:ro
      - ./data/seeds/livry_prospects.sql:/docker-entrypoint-initdb.d/livry_prospects.sql:ro
      - ./data/seeds/livry_recipes.sql:/docker-entrypoint-initdb.d/livry_recipes.sql:ro
```

- [ ] **Step 2: Update Makefile migrate target**

Replace the `migrate` target with:

```makefile
migrate:
	@docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < sites/api/migrations/025_artisans_local.sql
	@docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < sites/api/migrations/026_b2b_recipes.sql
	@docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < sites/api/migrations/027_spin_wheel.sql
	@echo "✅ Migrations applied"
```

- [ ] **Step 3: Apply migration**

```bash
cd /mnt/c/Users/user/code/webiartisan.new
make migrate
```

Expected output:
```
✅ Migrations applied
```

- [ ] **Step 4: Verify tables**

```bash
docker compose exec -T mysql mysql -uwebiartisan -pwebiartisan_dev webiartisan -e "
  SHOW TABLES LIKE 'local_users';
  SHOW TABLES LIKE 'local_spin_offers';
  SHOW TABLES LIKE 'local_spin_wins';
  SHOW TABLES LIKE 'local_spin_daily_limits';
"
```

Expected output: each query returns one table row.

- [ ] **Step 5: Commit**

```bash
git add docker-compose.yml Makefile
git commit -m "chore(docker,make): apply spin wheel migration"
```

### Task 3: UserAuth helper library

**Files:**
- Create: `sites/api/lib/UserAuth.php`

- [ ] **Step 1: Write helper**

```php
<?php
/**
 * UserAuth — Authentification consommateur par session token
 */

function user_get_session_token(): ?string
{
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    return $token ?: null;
}

function user_require_auth(PDO $pdo): array
{
    $token = user_get_session_token();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentification requise']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, email
        FROM local_users
        WHERE session_token = ? AND session_exp > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session invalide']);
        exit;
    }

    return $user;
}
```

- [ ] **Step 2: Commit**

```bash
git add sites/api/lib/UserAuth.php
git commit -m "feat(api): add consumer auth helper"
```

### Task 4: Create users route

**Files:**
- Create: `sites/api/routes/users.php`

- [ ] **Step 1: Write route file**

```php
<?php
/**
 * WebIArtisan API — Route : Utilisateurs (consommateurs)
 *
 * POST /users/magic-link        — envoie un lien magique
 * POST /users/auth?token=...    — valide le token et crée une session
 * GET  /users/me                — infos utilisateur connecté
 */

require_once __DIR__ . '/../lib/Mailer.php';
require_once __DIR__ . '/../lib/UserAuth.php';

switch ($method) {
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($action === 'magic-link') {
            user_magic_link($pdo, $body);
        } elseif ($action === 'auth') {
            user_auth($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'GET':
        if ($action === 'me') {
            user_me($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function user_magic_link(PDO $pdo, array $body): void
{
    $email = strtolower(trim($body['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email invalide']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM local_users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $pdo->prepare("INSERT INTO local_users (email) VALUES (?)")->execute([$email]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $userId = (int)$user['id'];
    }

    $token = bin2hex(random_bytes(32));
    $exp = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $pdo->prepare("
        UPDATE local_users
        SET magic_token = ?, magic_token_exp = ?
        WHERE id = ?
    ")->execute([$token, $exp, $userId]);

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $base = ($origin && filter_var($origin, FILTER_VALIDATE_URL))
        ? $origin
        : 'https://artisans-livry.prigent.tech';
    $link = rtrim($base, '/') . '/roue?token=' . urlencode($token);

    $subject = 'Votre lien pour tourner la roue des artisans';
    $html = <<<HTML
<!DOCTYPE html>
<html><body style="font-family: -apple-system, sans-serif; max-width: 480px; margin: 0 auto; padding: 20px;">
  <h2 style="color: #1a1a2e;">Bonjour,</h2>
  <p>Voici votre lien sécurisé pour tourner la roue des artisans de Livry :</p>
  <div style="text-align: center; margin: 24px 0;">
    <a href="{$link}" style="display: inline-block; background: #1a1a2e; color: #fff; padding: 14px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;">Tourner la roue</a>
  </div>
  <p style="color: #888; font-size: 13px;">Ce lien est valable 1 heure. Si vous ne l'avez pas demandé, ignorez cet email.</p>
</body></html>
HTML;

    $sent = send_html_email($email, $subject, $html, null, 'WebIArtisan');
    if (!$sent) {
        error_log("[USER-MAGIC-LINK] Échec envoi email à {$email}");
    }
    error_log("[USER-MAGIC-LINK] {$link}");

    echo json_encode([
        'success' => true,
        'message' => 'Si votre email est valide, vous recevrez un lien de connexion.',
    ]);
}

function user_auth(PDO $pdo): void
{
    $token = $_GET['token'] ?? '';
    if (!$token) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token manquant']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id, email
        FROM local_users
        WHERE magic_token = ? AND magic_token_exp > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Lien invalide ou expiré']);
        return;
    }

    $sessionToken = bin2hex(random_bytes(32));
    $sessionExp = date('Y-m-d H:i:s', strtotime('+30 days'));

    $pdo->prepare("
        UPDATE local_users
        SET session_token = ?, session_exp = ?,
            magic_token = NULL, magic_token_exp = NULL
        WHERE id = ?
    ")->execute([$sessionToken, $sessionExp, $user['id']]);

    echo json_encode([
        'success' => true,
        'token'   => $sessionToken,
        'data'    => ['id' => (int)$user['id'], 'email' => $user['email']],
    ]);
}

function user_me(PDO $pdo): void
{
    $user = user_require_auth($pdo);
    echo json_encode(['success' => true, 'data' => $user]);
}
```

- [ ] **Step 2: Wire module in index.php**

Modify `sites/api/index.php`, after the `artisans` block add:

```php
if ($module === 'users') {
    applyRateLimit($pdo, 'login');
    require_once __DIR__ . '/routes/users.php';
    exit;
}
```

- [ ] **Step 3: Test magic link**

```bash
curl -s -X POST "http://localhost:8080/api/users/magic-link" \
  -H "Content-Type: application/json" \
  -d '{"email":"spin-tester@example.com"}' | python3 -m json.tool
```

Expected output:
```json
{
  "success": true,
  "message": "Si votre email est valide, vous recevrez un lien de connexion."
}
```

Check Docker logs for the magic link URL.

- [ ] **Step 4: Test auth**

```bash
LINK_TOKEN="..." # from logs
curl -s -X POST "http://localhost:8080/api/users/auth?token=$LINK_TOKEN" | python3 -m json.tool
```

Expected output:
```json
{
  "success": true,
  "token": "...",
  "data": { "id": 1, "email": "spin-tester@example.com" }
}
```

- [ ] **Step 5: Test /users/me**

```bash
USER_TOKEN="..." # from previous response
curl -s -H "Authorization: Bearer $USER_TOKEN" "http://localhost:8080/api/users/me" | python3 -m json.tool
```

Expected output:
```json
{ "success": true, "data": { "id": 1, "email": "spin-tester@example.com" } }
```

- [ ] **Step 6: Commit**

```bash
git add sites/api/routes/users.php sites/api/index.php
git commit -m "feat(api): consumer magic-link auth"
```

---

## Phase 2 — Gestion des offres par l'artisan

### Task 5: Artisan spin-offers endpoints

**Files:**
- Modify: `sites/api/routes/artisans.php`

- [ ] **Step 1: Add switch branches**

In `case 'GET'`, after existing branches add:

```php
        } elseif ($action === 'me' && $param === 'spin-offers') {
            artisan_my_spin_offers($pdo);
        } elseif ($action === 'me' && $param === 'spin-wins') {
            artisan_my_spin_wins($pdo);
```

In `case 'POST'`, after existing branches add:

```php
        } elseif ($action === 'me' && $param === 'spin-offers') {
            artisan_create_spin_offer($pdo, $body);
```

In `case 'PUT'`, after existing branches add:

```php
        } elseif ($action === 'me' && $param === 'spin-offers' && is_numeric($segments[3] ?? '')) {
            artisan_update_spin_offer($pdo, (int)$segments[3], $body);
```

In `case 'DELETE'`, after existing branches add:

```php
        } elseif ($action === 'me' && $param === 'spin-offers' && is_numeric($segments[3] ?? '')) {
            artisan_delete_spin_offer($pdo, (int)$segments[3]);
```

- [ ] **Step 2: Append handler functions**

Append to `sites/api/routes/artisans.php`:

```php
/**
 * GET /artisans/me/spin-offers
 */
function artisan_my_spin_offers(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);

    $stmt = $pdo->prepare("
        SELECT id, label, description, stock_total, stock_remaining,
               is_active, created_at, updated_at
        FROM local_spin_offers
        WHERE artisan_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$artisan['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['stock_total']     = (int)$item['stock_total'];
        $item['stock_remaining'] = (int)$item['stock_remaining'];
        $item['is_active']       = (bool)$item['is_active'];
    }

    echo json_encode(['success' => true, 'data' => $items]);
}

/**
 * POST /artisans/me/spin-offers
 */
function artisan_create_spin_offer(PDO $pdo, array $body): void
{
    $artisan = artisan_require_auth($pdo);

    $label       = trim($body['label'] ?? '');
    $description = trim($body['description'] ?? '');
    $stockTotal  = (int)($body['stock_total'] ?? 0);

    if (!$label || $stockTotal < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Libellé et stock initial requis']);
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO local_spin_offers
            (artisan_id, label, description, stock_total, stock_remaining)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$artisan['id'], $label, $description, $stockTotal, $stockTotal]);

    $id = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'data'    => [
            'id'              => $id,
            'label'           => $label,
            'stock_remaining' => $stockTotal,
        ],
    ]);
}

/**
 * PUT /artisans/me/spin-offers/:id
 */
function artisan_update_spin_offer(PDO $pdo, int $id, array $body): void
{
    $artisan = artisan_require_auth($pdo);

    $allowed = ['label', 'description', 'is_active'];
    $updates = [];
    $params  = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $updates[] = "{$field} = ?";
            $params[]  = $body[$field];
        }
    }

    if (isset($body['stock_total'])) {
        $newTotal = (int)$body['stock_total'];
        if ($newTotal < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Stock total invalide']);
            return;
        }
        $updates[] = "stock_total = ?";
        $params[]  = $newTotal;
        $updates[] = "stock_remaining = GREATEST(? - (stock_total - stock_remaining), 0)";
        $params[]  = $newTotal;
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucun champ à mettre à jour']);
        return;
    }

    $params[] = $id;
    $params[] = $artisan['id'];

    $stmt = $pdo->prepare("
        UPDATE local_spin_offers
        SET " . implode(', ', $updates) . "
        WHERE id = ? AND artisan_id = ?
    ");
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Offre mise à jour']);
}

/**
 * DELETE /artisans/me/spin-offers/:id
 */
function artisan_delete_spin_offer(PDO $pdo, int $id): void
{
    $artisan = artisan_require_auth($pdo);

    $pdo->prepare("DELETE FROM local_spin_offers WHERE id = ? AND artisan_id = ?")
        ->execute([$id, $artisan['id']]);

    echo json_encode(['success' => true, 'message' => 'Offre supprimée']);
}
```

- [ ] **Step 3: Test with artisan token**

Get an artisan token (from logs after `/artisans/magic-link`, or from DB).

```bash
ARTISAN_TOKEN="..."

curl -s -X POST "http://localhost:8080/api/artisans/me/spin-offers" \
  -H "Content-Type: application/json" \
  -H "X-Artisan-Token: $ARTISAN_TOKEN" \
  -d '{"label":"-10% sur votre prochain achat","description":"Remise immédiate en magasin.","stock_total":20}' | python3 -m json.tool

curl -s -H "X-Artisan-Token: $ARTISAN_TOKEN" "http://localhost:8080/api/artisans/me/spin-offers" | python3 -m json.tool
```

Expected output: success true with created offer and list containing it.

- [ ] **Step 4: Commit**

```bash
git add sites/api/routes/artisans.php
git commit -m "feat(api): artisan spin offers CRUD"
```

---

## Phase 3 — Endpoint spin + logique stock/journalière

### Task 6: Create spin route

**Files:**
- Create: `sites/api/routes/spin.php`

- [ ] **Step 1: Write route file**

```php
<?php
/**
 * WebIArtisan API — Route : Spin Wheel
 *
 * GET /spin/offers?city=livry
 * POST /spin
 * GET /spin/wins
 */

require_once __DIR__ . '/../lib/UserAuth.php';

switch ($method) {
    case 'GET':
        if ($action === 'offers' || $action === '') {
            spin_offers_list($pdo);
        } elseif ($action === 'wins') {
            spin_wins_list($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        if ($action === '' || $action === 'spin') {
            spin_play($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

/**
 * GET /spin/offers?city=livry
 */
function spin_offers_list(PDO $pdo): void
{
    $citySlug = $_GET['city'] ?? '';
    if (!$citySlug) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ville requise']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT o.id, o.artisan_id, o.label, o.description, o.stock_remaining,
               a.company_name AS artisan_name, c.slug AS city_slug
        FROM local_spin_offers o
        JOIN local_artisans a ON a.id = o.artisan_id
        JOIN local_cities c   ON c.id = a.city_id
        WHERE c.slug = ?
          AND o.is_active = 1
          AND o.stock_remaining > 0
          AND a.status = 'active'
        ORDER BY o.created_at ASC
    ");
    $stmt->execute([$citySlug]);
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($offers as &$o) {
        $o['stock_remaining'] = (int)$o['stock_remaining'];
    }

    echo json_encode(['success' => true, 'data' => $offers]);
}

/**
 * POST /spin
 */
function spin_play(PDO $pdo): void
{
    $user = user_require_auth($pdo);

    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $citySlug = $body['city_slug'] ?? '';

    if (!$citySlug) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ville requise']);
        return;
    }

    $cityStmt = $pdo->prepare("
        SELECT id, name
        FROM local_cities
        WHERE slug = ? AND is_active = 1
    ");
    $cityStmt->execute([$citySlug]);
    $city = $cityStmt->fetch(PDO::FETCH_ASSOC);

    if (!$city) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ville inconnue']);
        return;
    }

    $today = date('Y-m-d');

    // 1 spin/jour
    $limitStmt = $pdo->prepare("
        SELECT count
        FROM local_spin_daily_limits
        WHERE user_id = ? AND city_id = ? AND spin_date = ?
    ");
    $limitStmt->execute([$user['id'], $city['id'], $today]);
    $limit = $limitStmt->fetch(PDO::FETCH_ASSOC);

    if ($limit && (int)$limit['count'] >= 1) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Vous avez déjà tourné la roue aujourd\'hui']);
        return;
    }

    // Offres actives avec stock
    $stmt = $pdo->prepare("
        SELECT o.id, o.artisan_id, o.label, o.description, o.stock_remaining
        FROM local_spin_offers o
        JOIN local_artisans a ON a.id = o.artisan_id
        WHERE a.city_id = ?
          AND o.is_active = 1
          AND o.stock_remaining > 0
          AND a.status = 'active'
        ORDER BY o.stock_remaining DESC, o.created_at ASC
    ");
    $stmt->execute([$city['id']]);
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($offers)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Aucune offre disponible']);
        return;
    }

    // Pondération par stock restant
    $totalWeight = array_sum(array_column($offers, 'stock_remaining'));
    $rand = mt_rand(1, $totalWeight);
    $chosen = null;
    $cumul = 0;

    foreach ($offers as $offer) {
        $cumul += (int)$offer['stock_remaining'];
        if ($rand <= $cumul) {
            $chosen = $offer;
            break;
        }
    }

    if (!$chosen) {
        $chosen = $offers[0];
    }

    $code = 'LIV-' . strtoupper(bin2hex(random_bytes(4)));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

    $pdo->beginTransaction();

    try {
        $upd = $pdo->prepare("
            UPDATE local_spin_offers
            SET stock_remaining = stock_remaining - 1
            WHERE id = ? AND stock_remaining > 0
        ");
        $upd->execute([$chosen['id']]);

        if ($upd->rowCount() === 0) {
            throw new Exception('Stock épuisé');
        }

        $winStmt = $pdo->prepare("
            INSERT INTO local_spin_wins
                (user_id, offer_id, artisan_id, code, status, spin_date, expires_at)
            VALUES (?, ?, ?, ?, 'pending', ?, ?)
        ");
        $winStmt->execute([
            $user['id'],
            $chosen['id'],
            $chosen['artisan_id'],
            $code,
            $today,
            $expiresAt,
        ]);

        $pdo->prepare("
            INSERT INTO local_spin_daily_limits (user_id, city_id, spin_date, count)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE count = count + 1
        ")->execute([$user['id'], $city['id'], $today]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Impossible d\'enregistrer le gain']);
        return;
    }

    $artisanStmt = $pdo->prepare("SELECT company_name FROM local_artisans WHERE id = ?");
    $artisanStmt->execute([$chosen['artisan_id']]);
    $artisanName = $artisanStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data'    => [
            'offer_id'      => (int)$chosen['id'],
            'label'         => $chosen['label'],
            'description'   => $chosen['description'],
            'artisan_id'    => (int)$chosen['artisan_id'],
            'artisan_name'  => $artisanName,
            'code'          => $code,
            'expires_at'    => $expiresAt,
        ],
    ]);
}

/**
 * GET /spin/wins
 */
function spin_wins_list(PDO $pdo): void
{
    $user = user_require_auth($pdo);

    $stmt = $pdo->prepare("
        SELECT w.id, w.code, w.status, w.spin_date, w.claimed_at, w.expires_at,
               o.label, o.description, a.company_name AS artisan_name
        FROM local_spin_wins w
        JOIN local_spin_offers o ON o.id = w.offer_id
        JOIN local_artisans a    ON a.id = w.artisan_id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $wins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $wins]);
}
```

- [ ] **Step 2: Wire module in index.php**

Modify `sites/api/index.php`, after the `users` block add:

```php
if ($module === 'spin') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/spin.php';
    exit;
}
```

- [ ] **Step 3: Test public offers list**

```bash
curl -s "http://localhost:8080/api/spin/offers?city=livry" | python3 -m json.tool
```

Expected output:
```json
{
  "success": true,
  "data": [
    { "id": 1, "artisan_id": 1, "label": "...", "stock_remaining": 19, ... }
  ]
}
```

- [ ] **Step 4: Test spin endpoint**

Use user token from Phase 1.

```bash
USER_TOKEN="..."
curl -s -X POST "http://localhost:8080/api/spin" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $USER_TOKEN" \
  -d '{"city_slug":"livry"}' | python3 -m json.tool
```

Expected output:
```json
{
  "success": true,
  "data": {
    "offer_id": 1,
    "label": "-10% sur votre prochain achat",
    "artisan_name": "...",
    "code": "LIV-...",
    "expires_at": "..."
  }
}
```

- [ ] **Step 5: Test daily limit**

Run the same spin request again.

```bash
curl -s -X POST "http://localhost:8080/api/spin" \
  -H "Authorization: Bearer $USER_TOKEN" \
  -d '{"city_slug":"livry"}' -w "\n%{http_code}\n"
```

Expected output: HTTP 429 with message "Vous avez déjà tourné la roue aujourd'hui".

- [ ] **Step 6: Commit**

```bash
git add sites/api/routes/spin.php sites/api/index.php
git commit -m "feat(api): spin wheel endpoints"
```

---

## Phase 4 — Frontend roue + affichage gain

### Task 7: Enrich api.js

**Files:**
- Modify: `sites/artisans-shared/src/api.js`

- [ ] **Step 1: Append consumer and spin methods**

Append to `sites/artisans-shared/src/api.js` before the final `weatherInfo` function:

```javascript
// --- Authentification consommateur ------------------------------

const USER_TOKEN_KEY = 'spin_user_token'

export function getUserToken() {
  return localStorage.getItem(USER_TOKEN_KEY)
}

export function setUserToken(token) {
  localStorage.setItem(USER_TOKEN_KEY, token)
}

export function removeUserToken() {
  localStorage.removeItem(USER_TOKEN_KEY)
}

export async function requestUserMagicLink(email) {
  const res = await fetch(`${API_BASE}/users/magic-link`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email }),
  })
  return res.json()
}

export async function authUser(token) {
  const res = await fetch(`${API_BASE}/users/auth?token=${encodeURIComponent(token)}`, {
    method: 'POST',
  })
  return res.json()
}

export async function fetchUserMe(token) {
  const res = await fetch(`${API_BASE}/users/me`, {
    headers: { 'Authorization': `Bearer ${token}` },
  })
  return res.json()
}

// --- Spin wheel -------------------------------------------------

export async function getSpinOffers() {
  const res = await fetch(`${API_BASE}/spin/offers?city=${CITY_SLUG}`)
  if (!res.ok) throw new Error('Erreur chargement offres')
  return res.json()
}

export async function postSpin(token, payload = {}) {
  const res = await fetch(`${API_BASE}/spin`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({ city_slug: CITY_SLUG, ...payload }),
  })
  return res.json()
}

export async function getSpinWins(token) {
  const res = await fetch(`${API_BASE}/spin/wins`, {
    headers: { 'Authorization': `Bearer ${token}` },
  })
  if (!res.ok) throw new Error('Erreur chargement gains')
  return res.json()
}
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/api.js
git commit -m "feat(front): consumer auth and spin API methods"
```

### Task 8: SpinWheel view

**Files:**
- Create: `sites/artisans-shared/src/views/SpinWheel.vue`

- [ ] **Step 1: Write component**

```vue
<template>
  <div class="spin-view section">
    <div class="container narrow">
      <h1>🎰 La Roue des Artisans</h1>
      <p class="text-muted">Tournez la roue une fois par jour et gagnez une offre locale.</p>

      <!-- Auth -->
      <div v-if="!token" class="auth-card card">
        <h2>Connexion</h2>
        <p class="text-muted">Recevez un lien magique par email pour participer.</p>
        <form @submit.prevent="sendMagicLink" class="auth-form">
          <input
            v-model="email"
            type="email"
            class="form-input"
            placeholder="votre@email.fr"
            required
            :disabled="sending"
          />
          <button type="submit" class="btn btn-primary" :disabled="sending || !email">
            {{ sending ? 'Envoi…' : 'Recevoir mon lien' }}
          </button>
        </form>
        <div v-if="message" class="auth-message" :class="messageType">{{ message }}</div>
      </div>

      <!-- Connected -->
      <template v-else>
        <div v-if="loading" class="skeleton" style="height: 360px; border-radius: 12px;"></div>

        <template v-else>
          <div v-if="alreadySpun" class="card result-card">
            <h2>Vous avez déjà tourné aujourd'hui 🎉</h2>
            <p>Revenez demain pour une nouvelle chance.</p>
            <RouterLink to="/" class="btn btn-outline">Retour à l'annuaire</RouterLink>
          </div>

          <div v-else class="wheel-wrap">
            <div class="wheel-container" :class="{ spinning: spinning }">
              <canvas ref="wheelCanvas" width="360" height="360"></canvas>
              <div class="wheel-pointer"></div>
            </div>
            <button class="btn btn-primary btn-lg" @click="spin" :disabled="spinning || offers.length < 2">
              {{ spinning ? 'La roue tourne…' : 'Tourner la roue' }}
            </button>
            <p v-if="offers.length < 2" class="text-muted small">Offres insuffisantes pour tourner.</p>
          </div>

          <div v-if="result" class="card result-card">
            <h2>🎁 Vous avez gagné</h2>
            <div class="offer-label">{{ result.label }}</div>
            <p class="text-muted">{{ result.description }}</p>
            <p><strong>Artisan :</strong> {{ result.artisan_name }}</p>
            <div class="qr-wrap">
              <canvas ref="qrCanvas"></canvas>
              <div class="code">{{ result.code }}</div>
            </div>
            <p class="text-muted small">Valide jusqu'au {{ formatDate(result.expires_at) }}</p>
          </div>

          <div v-if="wins.length" class="card wins-card">
            <h2>Mes gains</h2>
            <div v-for="w in wins" :key="w.id" class="win-row">
              <div>
                <strong>{{ w.label }}</strong>
                <span class="badge" :class="'status-' + w.status">{{ statusLabel[w.status] }}</span>
              </div>
              <div class="code">{{ w.code }}</div>
              <div class="text-muted small">Expire le {{ formatDate(w.expires_at) }}</div>
            </div>
          </div>
        </template>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import QRCode from 'qrcode'
import {
  requestUserMagicLink,
  authUser,
  fetchUserMe,
  getUserToken,
  setUserToken,
  removeUserToken,
  getSpinOffers,
  postSpin,
  getSpinWins,
} from '../api.js'

const route = useRoute()
const router = useRouter()

const email = ref('')
const token = ref(getUserToken() || '')
const user = ref(null)
const offers = ref([])
const wins = ref([])
const loading = ref(false)
const sending = ref(false)
const spinning = ref(false)
const result = ref(null)
const message = ref('')
const messageType = ref('')
const wheelCanvas = ref(null)
const qrCanvas = ref(null)

const alreadySpun = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  return wins.value.some(w => w.spin_date === today && w.status !== 'expired')
})

const statusLabel = {
  pending: 'En attente',
  claimed: 'Utilisé',
  expired: 'Expiré',
}

function setMessage(text, type = 'info') {
  message.value = text
  messageType.value = type
}

if (route.query.token) {
  authUser(route.query.token).then(res => {
    if (res.success && res.token) {
      setUserToken(res.token)
      token.value = res.token
      router.replace('/roue')
    } else {
      setMessage(res.error || 'Lien invalide', 'error')
    }
  })
}

async function sendMagicLink() {
  sending.value = true
  message.value = ''
  try {
    const res = await requestUserMagicLink(email.value)
    setMessage(res.message || 'Si votre email est valide, vous recevrez un lien.', 'success')
  } catch (e) {
    setMessage('Erreur lors de l\'envoi.', 'error')
  } finally {
    sending.value = false
  }
}

async function loadUser() {
  if (!token.value) return
  loading.value = true
  try {
    const res = await fetchUserMe(token.value)
    if (res.success) {
      user.value = res.data
    } else {
      logout()
      setMessage('Session expirée.', 'error')
    }
  } catch (e) {
    setMessage('Impossible de charger le profil.', 'error')
  } finally {
    loading.value = false
  }
}

async function loadOffers() {
  try {
    const res = await getSpinOffers()
    offers.value = res.data || []
    drawWheel()
  } catch (e) {
    console.error('Erreur chargement offres', e)
  }
}

async function loadWins() {
  if (!token.value) return
  try {
    const res = await getSpinWins(token.value)
    wins.value = res.data || []
  } catch (e) {
    console.error('Erreur chargement gains', e)
  }
}

async function spin() {
  if (spinning.value || alreadySpun.value || offers.value.length < 2) return
  spinning.value = true
  result.value = null
  try {
    const res = await postSpin(token.value, {})
    if (res.success) {
      result.value = res.data
      await loadWins()
      await nextTick()
      drawQr()
    } else {
      setMessage(res.error || 'Erreur lors du spin', 'error')
    }
  } catch (e) {
    setMessage('Erreur réseau.', 'error')
  } finally {
    spinning.value = false
  }
}

function drawWheel() {
  if (!wheelCanvas.value || offers.value.length < 2) return
  const ctx = wheelCanvas.value.getContext('2d')
  const w = wheelCanvas.value.width
  const h = wheelCanvas.value.height
  const cx = w / 2
  const cy = h / 2
  const r = Math.min(w, h) / 2 - 8
  const slice = (Math.PI * 2) / offers.value.length
  const colors = ['#2D6A4F', '#40916C', '#52B788', '#74C69D', '#95D5B2', '#B7E4C7']

  ctx.clearRect(0, 0, w, h)
  offers.value.forEach((offer, i) => {
    const start = i * slice - Math.PI / 2
    const end = start + slice
    ctx.beginPath()
    ctx.moveTo(cx, cy)
    ctx.arc(cx, cy, r, start, end)
    ctx.fillStyle = colors[i % colors.length]
    ctx.fill()
    ctx.stroke()
    ctx.save()
    ctx.translate(cx, cy)
    ctx.rotate(start + slice / 2)
    ctx.fillStyle = '#fff'
    ctx.font = 'bold 13px sans-serif'
    ctx.textAlign = 'right'
    const text = offer.label.length > 18 ? offer.label.slice(0, 18) + '…' : offer.label
    ctx.fillText(text, r - 16, 5)
    ctx.restore()
  })
}

async function drawQr() {
  if (!qrCanvas.value || !result.value) return
  await QRCode.toCanvas(qrCanvas.value, result.value.code, { width: 180, margin: 2 })
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR')
}

function logout() {
  token.value = ''
  user.value = null
  removeUserToken()
}

onMounted(() => {
  loadUser()
  loadOffers()
  loadWins()
})
</script>

<style scoped>
.spin-view { min-height: 60vh; }
.narrow { max-width: 720px; }
.auth-card, .result-card, .wins-card { padding: 28px; margin-top: 24px; }
.auth-form { display: flex; flex-direction: column; gap: 14px; margin-top: 16px; }
.wheel-wrap { text-align: center; margin: 32px 0; }
.wheel-container { position: relative; display: inline-block; margin-bottom: 24px; }
.wheel-container.spinning canvas {
  animation: spin-anim 3s cubic-bezier(0.25, 0.1, 0.25, 1) forwards;
}
@keyframes spin-anim {
  from { transform: rotate(0deg); }
  to { transform: rotate(1800deg); }
}
.wheel-pointer {
  position: absolute;
  top: -10px;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 0;
  border-left: 14px solid transparent;
  border-right: 14px solid transparent;
  border-top: 22px solid #B71C1C;
}
.offer-label { font-size: 1.3rem; font-weight: 700; color: var(--c-green-dark); margin: 12px 0; }
.qr-wrap { margin: 20px 0; }
.code { font-family: monospace; font-size: 1.1rem; letter-spacing: 1px; margin-top: 8px; }
.win-row { padding: 14px 0; border-bottom: 1px solid var(--c-border); }
.win-row:last-child { border-bottom: none; }
.status-pending { background: #FFF3E0; color: #E65100; }
.status-claimed { background: #E8F5E9; color: #2E7D32; }
.status-expired { background: #FFEBEE; color: #C62828; }
</style>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/views/SpinWheel.vue
git commit -m "feat(front): spin wheel page"
```

### Task 9: Register routes and navigation

**Files:**
- Modify: `sites/artisans-shared/src/main.js`
- Modify: `sites/artisans-shared/src/components/AppNav.vue`

- [ ] **Step 1: Add route in main.js**

Add to the `routes` array:

```javascript
  { path: '/roue', component: () => import('./views/SpinWheel.vue'), meta: { title: 'La roue des artisans' } },
```

- [ ] **Step 2: Add nav links in AppNav.vue**

In desktop nav (`<div class="nav-links">`), add:

```html
<RouterLink to="/roue" class="nav-link">🎰 Roue</RouterLink>
```

In mobile nav (`<div class="nav-mobile">`), add:

```html
<RouterLink to="/roue" class="nav-mobile-link">🎰 La roue</RouterLink>
```

- [ ] **Step 3: Commit**

```bash
git add sites/artisans-shared/src/main.js sites/artisans-shared/src/components/AppNav.vue
git commit -m "feat(front): spin wheel route and navigation"
```

### Task 10: Build verification

**Command:**

```bash
cd /mnt/c/Users/user/code/webiartisan.new
make build
```

Expected output: Vite build completes with no errors.

---

## Phase 5 — Validation artisan + QR code

### Task 11: Artisan spin-wins endpoints

**Files:**
- Modify: `sites/api/routes/artisans.php`

- [ ] **Step 1: Add switch branches**

In `case 'POST'`, after existing branches add:

```php
        } elseif ($action === 'me' && $param === 'spin-wins' && !empty($segments[3]) && ($segments[4] ?? '') === 'validate') {
            artisan_validate_spin_win($pdo, $segments[3]);
```

- [ ] **Step 2: Append handler functions**

Append to `sites/api/routes/artisans.php`:

```php
/**
 * GET /artisans/me/spin-wins?status=pending
 */
function artisan_my_spin_wins(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);

    $status = $_GET['status'] ?? '';
    $allowed = ['pending', 'claimed', 'expired'];

    $sql = "
        SELECT w.id, w.code, w.status, w.spin_date, w.claimed_at, w.expires_at,
               o.label, o.description, u.email AS user_email
        FROM local_spin_wins w
        JOIN local_spin_offers o ON o.id = w.offer_id
        JOIN local_users u       ON u.id = w.user_id
        WHERE w.artisan_id = ?
    ";
    $params = [$artisan['id']];

    if ($status && in_array($status, $allowed, true)) {
        $sql .= " AND w.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY w.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $items]);
}

/**
 * POST /artisans/me/spin-wins/:code/validate
 */
function artisan_validate_spin_win(PDO $pdo, string $code): void
{
    $artisan = artisan_require_auth($pdo);

    $stmt = $pdo->prepare("
        SELECT id, status, expires_at
        FROM local_spin_wins
        WHERE code = ? AND artisan_id = ?
    ");
    $stmt->execute([$code, $artisan['id']]);
    $win = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$win) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Gain non trouvé']);
        return;
    }

    if ($win['status'] !== 'pending') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Ce gain a déjà été utilisé ou est expiré']);
        return;
    }

    if (strtotime($win['expires_at']) < time()) {
        $pdo->prepare("UPDATE local_spin_wins SET status = 'expired' WHERE id = ?")
            ->execute([$win['id']]);
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Ce gain a expiré']);
        return;
    }

    $pdo->prepare("
        UPDATE local_spin_wins
        SET status = 'claimed', claimed_at = NOW()
        WHERE id = ?
    ")->execute([$win['id']]);

    echo json_encode(['success' => true, 'message' => 'Gain validé']);
}
```

- [ ] **Step 3: Test validation**

```bash
ARTISAN_TOKEN="..."
USER_CODE="LIV-..." # from spin response

curl -s -X POST "http://localhost:8080/api/artisans/me/spin-wins/$USER_CODE/validate" \
  -H "X-Artisan-Token: $ARTISAN_TOKEN" | python3 -m json.tool
```

Expected output:
```json
{ "success": true, "message": "Gain validé" }
```

- [ ] **Step 4: Commit**

```bash
git add sites/api/routes/artisans.php
git commit -m "feat(api): artisan spin wins validation"
```

### Task 12: Install QR code library

**Files:**
- Modify: `sites/artisans-shared/package.json`

- [ ] **Step 1: Install package**

```bash
cd /mnt/c/Users/user/code/webiartisan.new/sites/artisans-shared
npm install qrcode
```

Expected output: `qrcode` added to `package.json` dependencies and `node_modules/`.

- [ ] **Step 2: Commit**

```bash
cd /mnt/c/Users/user/code/webiartisan.new
git add sites/artisans-shared/package.json sites/artisans-shared/package-lock.json
git commit -m "chore(front): add qrcode dependency"
```

### Task 13: Artisan spin offers view

**Files:**
- Create: `sites/artisans-shared/src/views/SpinOffers.vue`

- [ ] **Step 1: Write component**

```vue
<template>
  <div class="section">
    <div class="container narrow">
      <h1>Mes offres roue</h1>
      <RouterLink to="/espace" class="btn btn-outline btn-sm">← Retour à l'espace</RouterLink>

      <form @submit.prevent="save" class="card form-card">
        <h2>{{ editingId ? 'Modifier' : 'Nouvelle' }} offre</h2>
        <div class="form-group">
          <label>Libellé (affiché sur la roue)</label>
          <input v-model="form.label" class="form-input" required maxlength="200" />
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea v-model="form.description" class="form-textarea" rows="3"></textarea>
        </div>
        <div class="form-row grid-2">
          <div class="form-group">
            <label>Stock total</label>
            <input type="number" min="1" v-model.number="form.stock_total" class="form-input" required />
          </div>
          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.is_active" />
              Visible sur la roue
            </label>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Enregistrement…' : (editingId ? 'Mettre à jour' : 'Créer') }}
          </button>
          <button type="button" class="btn btn-outline" @click="reset" v-if="editingId">Annuler</button>
        </div>
        <div v-if="message" class="auth-message" :class="messageType">{{ message }}</div>
      </form>

      <div v-if="loading" class="skeleton" style="height: 120px;"></div>
      <div v-else-if="offers.length" class="card offers-card">
        <div v-for="o in offers" :key="o.id" class="offer-row">
          <div>
            <strong>{{ o.label }}</strong>
            <span class="badge" :class="o.is_active ? 'badge-green' : 'badge-grey'">
              {{ o.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <div class="text-muted small">Stock : {{ o.stock_remaining }} / {{ o.stock_total }}</div>
          <div class="row-actions">
            <button class="btn btn-outline btn-sm" @click="edit(o)">Modifier</button>
            <button class="btn btn-danger btn-sm" @click="remove(o.id)">Supprimer</button>
          </div>
        </div>
      </div>
      <div v-else class="empty-state">
        <p>Aucune offre créée.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import {
  getArtisanSpinOffers,
  createArtisanSpinOffer,
  updateArtisanSpinOffer,
  deleteArtisanSpinOffer,
} from '../api.js'

const STORAGE_KEY = 'artisan_token'
const token = ref(localStorage.getItem(STORAGE_KEY) || '')

const offers = ref([])
const loading = ref(false)
const saving = ref(false)
const editingId = ref(null)
const message = ref('')
const messageType = ref('')

const form = reactive({
  label: '',
  description: '',
  stock_total: 10,
  is_active: true,
})

function setMessage(text, type = 'info') {
  message.value = text
  messageType.value = type
}

async function load() {
  if (!token.value) return
  loading.value = true
  try {
    const res = await getArtisanSpinOffers(token.value)
    offers.value = res.data || []
  } catch (e) {
    setMessage('Erreur chargement offres.', 'error')
  } finally {
    loading.value = false
  }
}

function edit(offer) {
  editingId.value = offer.id
  Object.assign(form, {
    label: offer.label,
    description: offer.description,
    stock_total: offer.stock_total,
    is_active: offer.is_active,
  })
}

function reset() {
  editingId.value = null
  Object.assign(form, { label: '', description: '', stock_total: 10, is_active: true })
}

async function save() {
  saving.value = true
  message.value = ''
  try {
    const res = editingId.value
      ? await updateArtisanSpinOffer(token.value, editingId.value, form)
      : await createArtisanSpinOffer(token.value, form)
    if (res.success) {
      setMessage(editingId.value ? 'Offre mise à jour.' : 'Offre créée.', 'success')
      reset()
      await load()
    } else {
      setMessage(res.error || 'Erreur.', 'error')
    }
  } catch (e) {
    setMessage('Erreur réseau.', 'error')
  } finally {
    saving.value = false
  }
}

async function remove(id) {
  if (!confirm('Supprimer cette offre ?')) return
  try {
    await deleteArtisanSpinOffer(token.value, id)
    await load()
  } catch (e) {
    setMessage('Erreur suppression.', 'error')
  }
}

onMounted(load)
</script>

<style scoped>
.narrow { max-width: 760px; }
.form-card, .offers-card { padding: 28px; margin-top: 24px; }
.offer-row { padding: 16px 0; border-bottom: 1px solid var(--c-border); }
.offer-row:last-child { border-bottom: none; }
.row-actions { display: flex; gap: 8px; margin-top: 12px; }
.checkbox-label { display: flex; align-items: center; gap: 8px; margin-top: 8px; cursor: pointer; }
.checkbox-label input { width: auto; }
</style>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/views/SpinOffers.vue
git commit -m "feat(front): artisan spin offers CRUD page"
```

### Task 14: Artisan spin wins view

**Files:**
- Create: `sites/artisans-shared/src/views/SpinWins.vue`

- [ ] **Step 1: Write component**

```vue
<template>
  <div class="section">
    <div class="container narrow">
      <h1>Validation des gains</h1>
      <RouterLink to="/espace" class="btn btn-outline btn-sm">← Retour à l'espace</RouterLink>

      <form @submit.prevent="validate" class="card form-card">
        <h2>Saisir un code</h2>
        <div class="form-group">
          <label>Code du gain</label>
          <input v-model="code" class="form-input" placeholder="LIV-XXXXXX" required />
        </div>
        <button type="submit" class="btn btn-primary" :disabled="validating">
          {{ validating ? 'Validation…' : 'Valider' }}
        </button>
        <div v-if="message" class="auth-message" :class="messageType">{{ message }}</div>
      </form>

      <div v-if="loading" class="skeleton" style="height: 120px;"></div>
      <div v-else-if="wins.length" class="card wins-card">
        <h2>Gains en attente</h2>
        <div v-for="w in wins" :key="w.id" class="win-row">
          <div>
            <strong>{{ w.label }}</strong>
            <span class="code">{{ w.code }}</span>
          </div>
          <div class="text-muted small">{{ w.user_email }} — expire le {{ formatDate(w.expires_at) }}</div>
          <button class="btn btn-primary btn-sm" @click="validateCode(w.code)">Valider</button>
        </div>
      </div>
      <div v-else class="empty-state">
        <p>Aucun gain en attente.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getArtisanSpinWins, validateArtisanSpinWin } from '../api.js'

const STORAGE_KEY = 'artisan_token'
const token = ref(localStorage.getItem(STORAGE_KEY) || '')

const code = ref('')
const wins = ref([])
const loading = ref(false)
const validating = ref(false)
const message = ref('')
const messageType = ref('')

function setMessage(text, type = 'info') {
  message.value = text
  messageType.value = type
}

async function load() {
  if (!token.value) return
  loading.value = true
  try {
    const res = await getArtisanSpinWins(token.value, 'pending')
    wins.value = res.data || []
  } catch (e) {
    setMessage('Erreur chargement.', 'error')
  } finally {
    loading.value = false
  }
}

async function validate() {
  await validateCode(code.value)
}

async function validateCode(c) {
  validating.value = true
  message.value = ''
  try {
    const res = await validateArtisanSpinWin(token.value, c)
    if (res.success) {
      setMessage('Gain validé avec succès.', 'success')
      code.value = ''
      await load()
    } else {
      setMessage(res.error || 'Erreur de validation.', 'error')
    }
  } catch (e) {
    setMessage('Erreur réseau.', 'error')
  } finally {
    validating.value = false
  }
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR')
}

onMounted(load)
</script>

<style scoped>
.narrow { max-width: 760px; }
.form-card, .wins-card { padding: 28px; margin-top: 24px; }
.win-row { padding: 14px 0; border-bottom: 1px solid var(--c-border); }
.win-row:last-child { border-bottom: none; }
.code { font-family: monospace; margin-left: 12px; }
</style>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/views/SpinWins.vue
git commit -m "feat(front): artisan spin wins validation page"
```

### Task 15: Enrich api.js with artisan spin management

**Files:**
- Modify: `sites/artisans-shared/src/api.js`

- [ ] **Step 1: Append artisan spin methods**

Append to `sites/artisans-shared/src/api.js` after the spin methods:

```javascript
// --- Gestion artisan spin ---------------------------------------

export async function getArtisanSpinOffers(token) {
  const res = await fetch(`${API_BASE}/artisans/me/spin-offers`, {
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}

export async function createArtisanSpinOffer(token, data) {
  const res = await fetch(`${API_BASE}/artisans/me/spin-offers`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function updateArtisanSpinOffer(token, id, data) {
  const res = await fetch(`${API_BASE}/artisans/me/spin-offers/${id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function deleteArtisanSpinOffer(token, id) {
  const res = await fetch(`${API_BASE}/artisans/me/spin-offers/${id}`, {
    method: 'DELETE',
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}

export async function getArtisanSpinWins(token, status = '') {
  const qs = status ? `?status=${encodeURIComponent(status)}` : ''
  const res = await fetch(`${API_BASE}/artisans/me/spin-wins${qs}`, {
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}

export async function validateArtisanSpinWin(token, code) {
  const res = await fetch(`${API_BASE}/artisans/me/spin-wins/${encodeURIComponent(code)}/validate`, {
    method: 'POST',
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/api.js
git commit -m "feat(front): artisan spin management API methods"
```

### Task 16: Register artisan routes and dashboard links

**Files:**
- Modify: `sites/artisans-shared/src/main.js`
- Modify: `sites/artisans-shared/src/views/Dashboard.vue`

- [ ] **Step 1: Add routes in main.js**

Add to the `routes` array:

```javascript
  { path: '/espace/spin-offers', component: () => import('./views/SpinOffers.vue'), meta: { title: 'Mes offres roue' } },
  { path: '/espace/spin-wins',   component: () => import('./views/SpinWins.vue'),   meta: { title: 'Validation des gains' } },
```

- [ ] **Step 2: Add dashboard links**

In `sites/artisans-shared/src/views/Dashboard.vue`, inside the connected template (after the dashboard header), add:

```html
      <section class="dashboard-section card">
        <div class="section-title">
          <h2>Roue des artisans</h2>
        </div>
        <div class="prospect-list">
          <RouterLink to="/espace/spin-offers" class="prospect-mini">
            <div><strong>Mes offres</strong><span class="text-muted small">Créer et gérer les lots</span></div>
            <span class="badge badge-green">Gérer</span>
          </RouterLink>
          <RouterLink to="/espace/spin-wins" class="prospect-mini">
            <div><strong>Valider un gain</strong><span class="text-muted small">Saisir un code gagnant</span></div>
            <span class="badge badge-green">Valider</span>
          </RouterLink>
        </div>
      </section>
```

- [ ] **Step 3: Commit**

```bash
git add sites/artisans-shared/src/main.js sites/artisans-shared/src/views/Dashboard.vue
git commit -m "feat(front): artisan spin routes and dashboard links"
```

### Task 17: Final build verification

**Command:**

```bash
cd /mnt/c/Users/user/code/webiartisan.new
make build
```

Expected output: Vite build completes with no errors.

---

## Tests & Validation

### Task 18: Extend test-api.sh

**Files:**
- Modify: `scripts/test-api.sh`

- [ ] **Step 1: Append spin wheel tests**

Append before the final `if [[ "$FAILED" -ne 0 ]]` block:

```bash
echo ""
echo "== Spin wheel =="

# Create active test artisan if not exists
curl -s "${BASE_URL}/artisans/register" -X POST -H 'Content-Type: application/json' \
  -d '{"company_name":"Spin Artisan","city_slug":"livry","category_slug":"boulangerie","email":"spin-artisan@example.com","phone":"02 00 00 00 20","password":"spinpass123"}' >/dev/null || true

if command -v docker >/dev/null 2>&1 && docker compose ps | grep -q mysql; then
  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
    -e "UPDATE local_artisans SET status='active' WHERE email='spin-artisan@example.com';" >/dev/null 2>&1 || true)
fi

SPIN_ARTISAN_TOKEN=$(curl -s -X POST "${BASE_URL}/artisans/login" -H 'Content-Type: application/json' \
  -d '{"email":"spin-artisan@example.com","password":"spinpass123"}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('token',''))" || true)

if [[ -n "$SPIN_ARTISAN_TOKEN" ]]; then
  # Create offer
  curl -s -X POST "${BASE_URL}/artisans/me/spin-offers" \
    -H "Content-Type: application/json" \
    -H "X-Artisan-Token: $SPIN_ARTISAN_TOKEN" \
    -d '{"label":"-10% en magasin","description":"Remise immédiate","stock_total":10}' >/dev/null

  code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/artisans/me/spin-offers" -H "X-Artisan-Token: $SPIN_ARTISAN_TOKEN")
  check "$code" "Artisan spin offers list" "200"

  # Create user and session directly in DB
  if command -v docker >/dev/null 2>&1 && docker compose ps | grep -q mysql; then
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
      -e "INSERT IGNORE INTO local_users (id, email, session_token, session_exp) VALUES (99999, 'spin-user@example.com', 'test-session-token-12345', DATE_ADD(NOW(), INTERVAL 1 DAY));" >/dev/null 2>&1 || true)
  fi

  code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/spin/offers?city=livry")
  check "$code" "Public spin offers" "200"

  SPIN_RESPONSE=$(curl -s -X POST "${BASE_URL}/spin" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer test-session-token-12345" \
    -d '{"city_slug":"livry"}")
  if echo "$SPIN_RESPONSE" | python3 -c "import sys,json; d=json.load(sys.stdin); sys.exit(0 if d.get('success') and d.get('data',{}).get('code','').startswith('LIV-') else 1)" 2>/dev/null; then
    echo "✅ POST /spin returns a winning code"
  else
    echo "❌ POST /spin did not return a winning code: $SPIN_RESPONSE"
    FAILED=1
  fi

  WIN_CODE=$(echo "$SPIN_RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('code',''))" || true)

  if [[ -n "$WIN_CODE" ]]; then
    code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "${BASE_URL}/artisans/me/spin-wins/${WIN_CODE}/validate" -H "X-Artisan-Token: $SPIN_ARTISAN_TOKEN")
    check "$code" "Validate spin win" "200"
  fi
fi
```

- [ ] **Step 2: Run tests**

```bash
cd /mnt/c/Users/user/code/webiartisan.new
make test-api
```

Expected output:
```
✅ All API integration tests passed.
```

- [ ] **Step 3: Commit**

```bash
git add scripts/test-api.sh
git commit -m "test(api): add spin wheel integration tests"
```

---

## Self-Review Checklist

Before considering this feature complete, verify:

- [ ] All 4 tables (`local_users`, `local_spin_offers`, `local_spin_wins`, `local_spin_daily_limits`) exist and have correct indexes/constraints.
- [ ] `sites/api/index.php` routes `users` and `spin` modules.
- [ ] `sites/api/lib/UserAuth.php` is required by `users.php` and `spin.php`.
- [ ] Magic-link emails are logged and rate-limited under `login`.
- [ ] Spin endpoint enforces 1 spin/day via `local_spin_daily_limits`.
- [ ] Stock is decremented atomically inside a transaction.
- [ ] No offer with `stock_remaining <= 0` can be selected.
- [ ] Artisan CRUD only touches offers owned by the authenticated artisan.
- [ ] Validation endpoint only accepts codes belonging to the artisan.
- [ ] Expired wins are rejected at validation time.
- [ ] Frontend uses `localStorage` keys `spin_user_token` and `artisan_token`.
- [ ] `/roue` handles `?token=` from magic link and stores session token.
- [ ] `qrcode` is installed and used only after a successful spin.
- [ ] `make build` succeeds without Vite errors.
- [ ] `make test-api` passes including new spin wheel tests.
- [ ] No new non-standard libraries are introduced except `qrcode`.