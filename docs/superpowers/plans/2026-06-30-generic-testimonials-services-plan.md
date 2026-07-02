# Generic Testimonials & Artisan Services Implementation Plan

> **For agentic workers:** REQUIRED SUB-GRADE: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the recipe-specific review/feedback system with generic artisan testimonials and allow artisans to define/configure their services from a shared catalog.

**Architecture:** Add a global service catalog table, enrich the existing `local_services` table to link catalog entries or custom services, create a new `local_testimonials` table with reporting/media, expose REST endpoints under `/api/testimonials` and `/api/artisans/{id}/services`, and build Vue views/components for listing, composing, and managing testimonials and services.

**Tech Stack:** PHP 8.4 (custom front-controller), MySQL 8, Vue 3 + Vite, PDO, JWT/session-token auth.

---

## File Structure

| File | Responsibility |
|------|----------------|
| `sites/api/migrations/029_testimonials_services.sql` | Database schema for service catalog, enriched artisan services, testimonials, media, reports |
| `sites/api/routes/testimonials.php` | Public and authenticated endpoints for testimonials |
| `sites/api/routes/artisans.php` | Adds `/artisans/{id}/services` management endpoints |
| `sites/api/lib/Testimonials.php` | Shared helpers: can testify, moderation helpers, templates |
| `sites/artisans-shared/src/api.js` | API client functions for testimonials and services |
| `sites/artisans-shared/src/views/Testimonials.vue` | Public testimonials feed |
| `sites/artisans-shared/src/views/Artisan.vue` | Adds Services and Testimonials tabs |
| `sites/artisans-shared/src/components/TestimonialCard.vue` | Reusable testimonial card |
| `sites/artisans-shared/src/components/TestimonialComposer.vue` | Compose testimonial modal/form |
| `sites/artisans-shared/src/components/ServiceTag.vue` | Clickable service tag |
| `sites/artisans-shared/src/views/artisan/ServicesConfig.vue` | Artisan dashboard service management |
| `scripts/test-api.sh` | Smoke tests for new endpoints |

---

### Task 1: Create migration for service catalog, services, and testimonials

**Files:**
- Create: `sites/api/migrations/029_testimonials_services.sql`

- [ ] **Step 1: Write the migration SQL**

```sql
-- ============================================================
-- WebiArtisan — Migration 029 : Témoignages génériques & catalogue de services
-- ============================================================

SET NAMES utf8mb4;

-- Catalogue global de types de services
CREATE TABLE IF NOT EXISTS local_service_catalog (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    `key`       VARCHAR(50) UNIQUE NOT NULL COMMENT 'food_recipe, haircut, gardening...',
    label_fr    VARCHAR(100) NOT NULL,
    icon        VARCHAR(100) DEFAULT NULL,
    category    VARCHAR(50) DEFAULT NULL,
    is_active   BOOLEAN DEFAULT TRUE,
    testimonial_templates JSON NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (`key`),
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enrich existing local_services with catalog link and custom flag
SET @dbname = DATABASE();
SET @tablename = 'local_services';
SET @colname = 'service_catalog_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE local_services ADD COLUMN service_catalog_id INT NULL AFTER artisan_id'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @colname = 'is_custom';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE local_services ADD COLUMN is_custom BOOLEAN NOT NULL DEFAULT FALSE AFTER service_catalog_id'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @colname = 'is_active';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE local_services ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE AFTER duration'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

ALTER TABLE local_services
    ADD CONSTRAINT fk_service_catalog
        FOREIGN KEY (service_catalog_id) REFERENCES local_service_catalog(id) ON DELETE SET NULL;

-- Témoignages / recommandations génériques
CREATE TABLE IF NOT EXISTS local_testimonials (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    artisan_id      INT NOT NULL,
    user_id         INT NOT NULL,
    artisan_service_id INT NULL,
    service_type    VARCHAR(50) NULL COMMENT 'denormalized catalog key',
    rating          TINYINT NULL,
    title           VARCHAR(150) NULL,
    content         TEXT NOT NULL,
    status          ENUM('pending','approved','rejected','flagged') NOT NULL DEFAULT 'pending',
    helpful_count   INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (artisan_id) REFERENCES local_artisans(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    FOREIGN KEY (artisan_service_id) REFERENCES local_services(id) ON DELETE SET NULL,
    INDEX idx_artisan_status (artisan_id, status),
    INDEX idx_user (user_id),
    INDEX idx_service_type (service_type),
    INDEX idx_created (created_at),
    CONSTRAINT chk_rating CHECK (rating IS NULL OR rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Médias associés aux témoignages
CREATE TABLE IF NOT EXISTS local_testimonial_media (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    testimonial_id  INT NOT NULL,
    media_url       VARCHAR(255) NOT NULL,
    media_type      ENUM('image','video') NOT NULL DEFAULT 'image',
    display_order   INT NOT NULL DEFAULT 0,
    FOREIGN KEY (testimonial_id) REFERENCES local_testimonials(id) ON DELETE CASCADE,
    INDEX idx_testimonial (testimonial_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Signalements de témoignages
CREATE TABLE IF NOT EXISTS local_testimonial_reports (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    testimonial_id      INT NOT NULL,
    reporter_user_id    INT NOT NULL,
    reason              VARCHAR(100) NOT NULL,
    details             TEXT NULL,
    status              ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at         TIMESTAMP NULL,
    FOREIGN KEY (testimonial_id) REFERENCES local_testimonials(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    INDEX idx_testimonial (testimonial_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed sample catalog entries
INSERT INTO local_service_catalog (`key`, label_fr, icon, category, testimonial_templates) VALUES
('food_recipe', 'Recette locale', '🍽️', 'alimentation', '["J\'ai utilisé des ingrédients locaux dans une recette de {{dish}}.","{{artisan}} m\'a fourni les produits pour ma recette."]'),
('haircut', 'Coiffure', '✂️', 'beauté', '["Super coupe chez {{artisan}}, je recommande !","{{artisan}} a su m\'écouter et me conseiller."]'),
('gardening', 'Jardinage', '🌱', 'maison', '["{{artisan}} a transformé mon jardin.","Travail soigné et conseils avisés."]'),
('plumbing', 'Plomberie', '🚿', 'maison', '["Intervention rapide et efficace.","{{artisan}} a résolu mon problème en un temps record."]'),
('sewing', 'Couture', '🧵', 'mode', '["Retouche parfaite, merci {{artisan}} !","{{artisan}} a réalisé une pièce sur mesure."]')
ON DUPLICATE KEY UPDATE label_fr = VALUES(label_fr);
```

- [ ] **Step 2: Run migration against local database**

Run:
```bash
cd /mnt/c/Users/user/code/webiartisan.new
make migrate
# OR, if Makefile target differs:
docker compose exec -T db mysql -u root -p$DB_ROOT_PASSWORD webiartisan < sites/api/migrations/029_testimonials_services.sql
```
Expected: no errors, tables created successfully.

- [ ] **Step 3: Verify tables exist**

Run:
```bash
docker compose exec db mysql -u root -p$DB_ROOT_PASSWORD webiartisan -e "SHOW TABLES LIKE 'local_service_catalog'; SHOW TABLES LIKE 'local_testimonials'; SHOW TABLES LIKE 'local_testimonial_media'; SHOW TABLES LIKE 'local_testimonial_reports';"
```
Expected: each query returns 1 row.

- [ ] **Step 4: Commit**

```bash
git add sites/api/migrations/029_testimonials_services.sql
git commit -m "feat(db): add service catalog, testimonials, media and reports tables"
```

---

### Task 2: Add shared Testimonials helpers

**Files:**
- Create: `sites/api/lib/Testimonials.php`

- [ ] **Step 1: Write helper library**

```php
<?php
/**
 * WebiArtisan — Testimonials helpers
 */

require_once __DIR__ . '/UserAuth.php';

const TESTIMONIAL_STATUSES = ['pending', 'approved', 'rejected', 'flagged'];

function testimonials_can_user_testify(PDO $pdo, int $userId, int $artisanId): bool
{
    // User must exist and artisan must be active
    $stmt = $pdo->prepare("
        SELECT 1 FROM local_artisans
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$artisanId]);
    return $stmt->fetch() !== false;
}

function testimonials_get_templates(PDO $pdo, ?string $serviceKey = null): array
{
    $sql = "SELECT `key`, label_fr, icon, testimonial_templates FROM local_service_catalog WHERE is_active = 1";
    $params = [];
    if ($serviceKey) {
        $sql .= " AND `key` = ?";
        $params[] = $serviceKey;
    }
    $sql .= " ORDER BY label_fr ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_map(function ($row) {
        $templates = json_decode($row['testimonial_templates'] ?? '[]', true);
        return [
            'key' => $row['key'],
            'label' => $row['label_fr'],
            'icon' => $row['icon'],
            'templates' => is_array($templates) ? $templates : [],
        ];
    }, $rows);
}

function testimonials_enrich_with_user(PDO $pdo, array $testimonial): array
{
    $stmt = $pdo->prepare("
        SELECT id, display_name, avatar_type, avatar_url, avatar_gender
        FROM local_users WHERE id = ?
    ");
    $stmt->execute([(int)$testimonial['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $testimonial['author'] = [
        'id' => (int)($user['id'] ?? $testimonial['user_id']),
        'display_name' => $user['display_name'] ?? null,
        'avatar_url' => $user['avatar_url'] ?? null,
    ];
    return $testimonial;
}
```

- [ ] **Step 2: Commit**

```bash
git add sites/api/lib/Testimonials.php
git commit -m "feat(api): add testimonials helper functions"
```

---

### Task 3: Create `/api/testimonials` route

**Files:**
- Create: `sites/api/routes/testimonials.php`
- Modify: `sites/api/index.php` (register route)

- [ ] **Step 1: Write the testimonials route file**

```php
<?php
/**
 * WebiArtisan API — Route : Témoignages / recommandations
 *
 * GET  /testimonials                — liste publique
 * GET  /testimonials/templates      — modèles par type de service
 * GET  /testimonials/:id            — détail
 * POST /testimonials                — créer (authentifié)
 * PATCH /testimonials/:id           — modifier (auteur/admin)
 * DELETE /testimonials/:id          — supprimer (auteur/admin)
 * POST /testimonials/:id/report     — signaler (authentifié)
 * POST /testimonials/:id/helpful    — marquer utile (authentifié)
 */

require_once __DIR__ . '/../lib/Testimonials.php';

switch ($method) {
    case 'GET':
        if ($action === '' || $action === 'list') {
            testimonials_list($pdo);
        } elseif ($action === 'templates') {
            testimonials_templates($pdo);
        } elseif (is_numeric($action) && !$param) {
            testimonials_get($pdo, (int)$action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (is_numeric($action) && $param === 'report') {
            testimonials_report($pdo, (int)$action, $body);
        } elseif (is_numeric($action) && $param === 'helpful') {
            testimonials_helpful($pdo, (int)$action);
        } elseif ($action === '' || $action === 'list') {
            testimonials_create($pdo, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'PATCH':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (is_numeric($action) && !$param) {
            testimonials_update($pdo, (int)$action, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'DELETE':
        if (is_numeric($action) && !$param) {
            testimonials_delete($pdo, (int)$action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function testimonials_list(PDO $pdo): void
{
    $artisanId = isset($_GET['artisan_id']) ? (int)$_GET['artisan_id'] : null;
    $citySlug = $_GET['city'] ?? null;
    $serviceType = $_GET['service_type'] ?? null;
    $rating = isset($_GET['rating']) ? (int)$_GET['rating'] : null;
    $sort = in_array($_GET['sort'] ?? '', ['newest', 'oldest', 'helpful', 'rating'], true)
        ? $_GET['sort']
        : 'newest';
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $offset = max((int)($_GET['offset'] ?? 0), 0);

    $sql = "
        SELECT t.*, u.display_name, u.avatar_url
        FROM local_testimonials t
        JOIN local_users u ON u.id = t.user_id
        JOIN local_artisans a ON a.id = t.artisan_id
        JOIN local_cities c ON c.id = a.city_id
        WHERE t.status = 'approved'
    ";
    $params = [];

    if ($artisanId) {
        $sql .= " AND t.artisan_id = ?";
        $params[] = $artisanId;
    }
    if ($citySlug) {
        $sql .= " AND c.slug = ?";
        $params[] = $citySlug;
    }
    if ($serviceType) {
        $sql .= " AND t.service_type = ?";
        $params[] = $serviceType;
    }
    if ($rating !== null && $rating >= 1 && $rating <= 5) {
        $sql .= " AND t.rating = ?";
        $params[] = $rating;
    }

    $orderBy = match ($sort) {
        'oldest' => 't.created_at ASC',
        'helpful' => 't.helpful_count DESC, t.created_at DESC',
        'rating' => 't.rating DESC, t.created_at DESC',
        default => 't.created_at DESC',
    };
    $sql .= " ORDER BY $orderBy LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['artisan_id'] = (int)$item['artisan_id'];
        $item['user_id'] = (int)$item['user_id'];
        $item['rating'] = $item['rating'] !== null ? (int)$item['rating'] : null;
        $item['helpful_count'] = (int)$item['helpful_count'];
        $item['media'] = testimonials_get_media($pdo, (int)$item['id']);
    }

    echo json_encode([
        'success' => true,
        'data' => $items,
        'total' => count($items),
        'limit' => $limit,
        'offset' => $offset,
    ]);
}

function testimonials_get(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("
        SELECT t.*, u.display_name, u.avatar_url
        FROM local_testimonials t
        JOIN local_users u ON u.id = t.user_id
        WHERE t.id = ? AND t.status = 'approved'
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Témoignage non trouvé']);
        return;
    }

    $item['id'] = (int)$item['id'];
    $item['rating'] = $item['rating'] !== null ? (int)$item['rating'] : null;
    $item['helpful_count'] = (int)$item['helpful_count'];
    $item['media'] = testimonials_get_media($pdo, $id);

    echo json_encode(['success' => true, 'data' => $item]);
}

function testimonials_create(PDO $pdo, array $body): void
{
    $user = user_require_auth($pdo);

    $artisanId = (int)($body['artisan_id'] ?? 0);
    $artisanServiceId = !empty($body['artisan_service_id']) ? (int)$body['artisan_service_id'] : null;
    $serviceType = !empty($body['service_type']) ? trim($body['service_type']) : null;
    $rating = isset($body['rating']) ? (int)$body['rating'] : null;
    $title = trim($body['title'] ?? '');
    $content = trim($body['content'] ?? '');

    if (!$artisanId || !$content) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Artisan et contenu requis']);
        return;
    }

    if (!testimonials_can_user_testify($pdo, (int)$user['id'], $artisanId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Impossible de témoigner pour cet artisan']);
        return;
    }

    if ($rating !== null && ($rating < 1 || $rating > 5)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Note invalide']);
        return;
    }

    // Resolve service_type from artisan_service_id if not provided
    if (!$serviceType && $artisanServiceId) {
        $stmt = $pdo->prepare("
            SELECT sc.`key` FROM local_services s
            LEFT JOIN local_service_catalog sc ON sc.id = s.service_catalog_id
            WHERE s.id = ?
        ");
        $stmt->execute([$artisanServiceId]);
        $serviceType = $stmt->fetchColumn() ?: null;
    }

    $pdo->prepare("
        INSERT INTO local_testimonials
            (artisan_id, user_id, artisan_service_id, service_type, rating, title, content, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'approved')
    ")->execute([$artisanId, $user['id'], $artisanServiceId, $serviceType, $rating, $title, $content]);

    $id = (int)$pdo->lastInsertId();

    // Insert media URLs if provided
    $media = $body['media'] ?? [];
    if (is_array($media)) {
        $order = 0;
        foreach (array_slice($media, 0, 5) as $m) {
            $url = is_string($m) ? $m : ($m['url'] ?? null);
            $type = is_string($m) ? 'image' : ($m['type'] ?? 'image');
            if (!$url) continue;
            $pdo->prepare("
                INSERT INTO local_testimonial_media (testimonial_id, media_url, media_type, display_order)
                VALUES (?, ?, ?, ?)
            ")->execute([$id, $url, $type, $order++]);
        }
    }

    echo json_encode(['success' => true, 'data' => ['id' => $id]]);
}

function testimonials_update(PDO $pdo, int $id, array $body): void
{
    // TODO in plan: implement author/admin update with 24h window
    http_response_code(501);
    echo json_encode(['success' => false, 'error' => 'Non implémenté']);
}

function testimonials_delete(PDO $pdo, int $id): void
{
    // TODO in plan: implement author/admin soft delete
    http_response_code(501);
    echo json_encode(['success' => false, 'error' => 'Non implémenté']);
}

function testimonials_report(PDO $pdo, int $id, array $body): void
{
    $user = user_require_auth($pdo);
    $reason = trim($body['reason'] ?? '');
    if (!$reason) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Motif requis']);
        return;
    }
    $pdo->prepare("
        INSERT INTO local_testimonial_reports (testimonial_id, reporter_user_id, reason, details)
        VALUES (?, ?, ?, ?)
    ")->execute([$id, $user['id'], $reason, trim($body['details'] ?? '')]);
    $pdo->prepare("UPDATE local_testimonials SET status = 'flagged' WHERE id = ? AND status = 'approved'")
        ->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Signalement enregistré']);
}

function testimonials_helpful(PDO $pdo, int $id): void
{
    user_require_auth($pdo);
    // Simple increment; duplicate clicks accepted but could be rate-limited later
    $pdo->prepare("UPDATE local_testimonials SET helpful_count = helpful_count + 1 WHERE id = ? AND status = 'approved'")
        ->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Merci pour votre retour']);
}

function testimonials_templates(PDO $pdo): void
{
    $serviceKey = $_GET['service'] ?? null;
    echo json_encode(['success' => true, 'data' => testimonials_get_templates($pdo, $serviceKey)]);
}

function testimonials_get_media(PDO $pdo, int $testimonialId): array
{
    $stmt = $pdo->prepare("
        SELECT id, media_url, media_type, display_order
        FROM local_testimonial_media
        WHERE testimonial_id = ?
        ORDER BY display_order ASC, id ASC
    ");
    $stmt->execute([$testimonialId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

- [ ] **Step 2: Register route in `sites/api/index.php`**

Locate the existing route dispatch block (e.g. `case 'recipes':`) and add:

```php
case 'testimonials':
    require_once __DIR__ . '/routes/testimonials.php';
    break;
```

- [ ] **Step 3: Smoke test public endpoints**

Run:
```bash
./scripts/test-api.sh testimonials
# If no target exists, test manually:
curl -s "http://localhost:8080/api/testimonials/templates" | jq .
```
Expected: JSON with `success: true` and catalog templates.

- [ ] **Step 4: Commit**

```bash
git add sites/api/routes/testimonials.php sites/api/index.php
git commit -m "feat(api): add testimonials CRUD, report and helpful endpoints"
```

---

### Task 4: Add artisan services management endpoints

**Files:**
- Modify: `sites/api/routes/artisans.php`

- [ ] **Step 1: Add route handlers for services**

In the `case 'GET'` block, the route `/artisans/{id}/services` already exists via `artisan_services($pdo, (int)$action)`. We need to enrich `artisan_services` and add POST/PATCH/DELETE handlers.

Replace the existing `artisan_services` function (search for it) with:

```php
function artisan_services(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.name,
            s.description,
            s.price_range,
            s.duration,
            s.is_custom,
            s.is_active,
            s.sort_order,
            s.service_catalog_id,
            sc.`key` AS catalog_key,
            sc.label_fr AS catalog_label,
            sc.icon AS catalog_icon
        FROM local_services s
        LEFT JOIN local_service_catalog sc ON sc.id = s.service_catalog_id
        WHERE s.artisan_id = ? AND s.is_active = 1
        ORDER BY s.sort_order ASC, s.id ASC
    ");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['service_catalog_id'] = $item['service_catalog_id'] !== null ? (int)$item['service_catalog_id'] : null;
        $item['is_custom'] = (bool)$item['is_custom'];
        $item['is_active'] = (bool)$item['is_active'];
        $item['sort_order'] = (int)$item['sort_order'];
    }

    echo json_encode(['success' => true, 'data' => $items]);
}
```

Add the following new functions at the bottom of `artisans.php`:

```php
function artisan_create_service(PDO $pdo, array $body): void
{
    $artisan = artisan_require_auth($pdo);
    $artisanId = (int)$artisan['id'];

    $catalogId = !empty($body['service_catalog_id']) ? (int)$body['service_catalog_id'] : null;
    $name = trim($body['name'] ?? '');
    $description = trim($body['description'] ?? '');
    $priceRange = trim($body['price_range'] ?? '');
    $duration = trim($body['duration'] ?? '');
    $isCustom = !empty($body['is_custom']);

    if (!$name) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nom du service requis']);
        return;
    }

    // Enforce free-tier limit (5 active services)
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM local_services WHERE artisan_id = ? AND is_active = 1");
    $countStmt->execute([$artisanId]);
    if ((int)$countStmt->fetchColumn() >= 5) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Limite de 5 services atteinte']);
        return;
    }

    $pdo->prepare("
        INSERT INTO local_services
            (artisan_id, service_catalog_id, name, description, price_range, duration, is_custom, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, 99)
    ")->execute([$artisanId, $catalogId, $name, $description, $priceRange, $duration, $isCustom ? 1 : 0]);

    echo json_encode(['success' => true, 'data' => ['id' => (int)$pdo->lastInsertId()]]);
}

function artisan_update_service(PDO $pdo, int $serviceId, array $body): void
{
    $artisan = artisan_require_auth($pdo);
    $artisanId = (int)$artisan['id'];

    $allowed = ['name', 'description', 'price_range', 'duration', 'is_active', 'sort_order'];
    $sets = [];
    $params = [];
    foreach ($allowed as $col) {
        if (array_key_exists($col, $body)) {
            $sets[] = "$col = ?";
            $params[] = $body[$col];
        }
    }
    if (empty($sets)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucune donnée à mettre à jour']);
        return;
    }
    $params[] = $serviceId;
    $params[] = $artisanId;

    $sql = "UPDATE local_services SET " . implode(', ', $sets) . " WHERE id = ? AND artisan_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Service non trouvé']);
        return;
    }
    echo json_encode(['success' => true, 'message' => 'Service mis à jour']);
}

function artisan_delete_service(PDO $pdo, int $serviceId): void
{
    $artisan = artisan_require_auth($pdo);
    $artisanId = (int)$artisan['id'];

    $stmt = $pdo->prepare("DELETE FROM local_services WHERE id = ? AND artisan_id = ?");
    $stmt->execute([$serviceId, $artisanId]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Service non trouvé']);
        return;
    }
    echo json_encode(['success' => true, 'message' => 'Service supprimé']);
}
```

- [ ] **Step 2: Wire new endpoints in `artisans.php` switch blocks**

In the `case 'POST'` block, add before the default:
```php
} elseif ($action === 'me' && $param === 'services') {
    artisan_create_service($pdo, $body);
```

In the `case 'PUT'` block, add before the default:
```php
} elseif ($action === 'me' && $param === 'services' && is_numeric($segments[3] ?? '')) {
    artisan_update_service($pdo, (int)$segments[3], $body);
```

In the `case 'DELETE'` block, add before the default:
```php
} elseif ($action === 'me' && $param === 'services' && is_numeric($segments[3] ?? '')) {
    artisan_delete_service($pdo, (int)$segments[3]);
```

- [ ] **Step 3: Add `/api/service-catalog` public route**

Create a new route file `sites/api/routes/services.php`:

```php
<?php
/**
 * WebiArtisan API — Route : Catalogue de services
 *
 * GET /service-catalog — liste publique
 */

switch ($method) {
    case 'GET':
        if ($action === '' || $action === 'list') {
            service_catalog_list($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function service_catalog_list(PDO $pdo): void
{
    $category = $_GET['category'] ?? null;
    $sql = "SELECT id, `key`, label_fr, icon, category, testimonial_templates FROM local_service_catalog WHERE is_active = 1";
    $params = [];
    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    $sql .= " ORDER BY category ASC, label_fr ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['testimonial_templates'] = json_decode($item['testimonial_templates'] ?? '[]', true);
    }
    echo json_encode(['success' => true, 'data' => $items]);
}
```

Register it in `sites/api/index.php`:
```php
case 'service-catalog':
    require_once __DIR__ . '/routes/services.php';
    break;
```

- [ ] **Step 4: Test endpoints**

Run:
```bash
curl -s "http://localhost:8080/api/service-catalog" | jq .
curl -s "http://localhost:8080/api/artisans/1/services" | jq .
```
Expected: JSON arrays with services and catalog.

- [ ] **Step 5: Commit**

```bash
git add sites/api/routes/artisans.php sites/api/routes/services.php sites/api/index.php
git commit -m "feat(api): add artisan service catalog and CRUD endpoints"
```

---

### Task 5: Add frontend API helpers

**Files:**
- Modify: `sites/artisans-shared/src/api.js`

- [ ] **Step 1: Add testimonial and service client functions**

Append to `api.js`:

```javascript
// --- Authentification consommateur ---------------------------------

export async function requestUserMagicLink(email) {
  const res = await fetch(`${API_BASE}/users/magic-link`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email }),
  })
  return res.json()
}

export async function authenticateUser(token) {
  const res = await fetch(`${API_BASE}/users/auth?token=${encodeURIComponent(token)}`)
  return res.json()
}

function userHeaders() {
  const token = localStorage.getItem('user_session_token')
  return token ? { Authorization: `Bearer ${token}` } : {}
}

// --- Témoignages ---------------------------------------------------

export async function fetchTestimonials(filters = {}) {
  const qs = new URLSearchParams({ city: CITY_SLUG, ...filters }).toString()
  const res = await fetch(`${API_BASE}/testimonials?${qs}`)
  if (!res.ok) throw new Error('Erreur chargement témoignages')
  return res.json()
}

export async function fetchTestimonial(id) {
  const res = await fetch(`${API_BASE}/testimonials/${id}`)
  if (!res.ok) throw new Error('Témoignage non trouvé')
  return res.json()
}

export async function createTestimonial(data) {
  const res = await fetch(`${API_BASE}/testimonials`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...userHeaders() },
    body: JSON.stringify({ city_slug: CITY_SLUG, ...data }),
  })
  return res.json()
}

export async function reportTestimonial(id, reason, details = '') {
  const res = await fetch(`${API_BASE}/testimonials/${id}/report`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...userHeaders() },
    body: JSON.stringify({ reason, details }),
  })
  return res.json()
}

export async function markTestimonialHelpful(id) {
  const res = await fetch(`${API_BASE}/testimonials/${id}/helpful`, {
    method: 'POST',
    headers: { ...userHeaders() },
  })
  return res.json()
}

export async function fetchTestimonialTemplates(serviceKey = null) {
  const qs = serviceKey ? `?service=${encodeURIComponent(serviceKey)}` : ''
  const res = await fetch(`${API_BASE}/testimonials/templates${qs}`)
  if (!res.ok) throw new Error('Erreur chargement modèles')
  return res.json()
}

// --- Services artisan ----------------------------------------------

export async function fetchServiceCatalog() {
  const res = await fetch(`${API_BASE}/service-catalog`)
  if (!res.ok) throw new Error('Erreur chargement catalogue')
  return res.json()
}

export async function fetchArtisanServices(artisanId) {
  const res = await fetch(`${API_BASE}/artisans/${artisanId}/services`)
  if (!res.ok) throw new Error('Erreur chargement services')
  return res.json()
}

export async function createArtisanService(token, data) {
  const res = await fetch(`${API_BASE}/artisans/me/services`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function updateArtisanService(token, serviceId, data) {
  const res = await fetch(`${API_BASE}/artisans/me/services/${serviceId}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function deleteArtisanService(token, serviceId) {
  const res = await fetch(`${API_BASE}/artisans/me/services/${serviceId}`, {
    method: 'DELETE',
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/api.js
git commit -m "feat(front): add API helpers for testimonials and services"
```

---

### Task 6: Build `ServiceTag` component

**Files:**
- Create: `sites/artisans-shared/src/components/ServiceTag.vue`

- [ ] **Step 1: Write component**

```vue
<template>
  <button
    type="button"
    class="service-tag"
    :class="{ active: isActive }"
    @click="$emit('toggle', serviceKey)"
  >
    <span v-if="icon" class="service-tag__icon">{{ icon }}</span>
    <span class="service-tag__label">{{ label }}</span>
  </button>
</template>

<script setup>
defineProps({
  serviceKey: { type: String, default: '' },
  label: { type: String, required: true },
  icon: { type: String, default: '' },
  isActive: { type: Boolean, default: false },
})
defineEmits(['toggle'])
</script>

<style scoped>
.service-tag {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.75rem;
  border-radius: 999px;
  border: 1px solid #ddd;
  background: #fff;
  cursor: pointer;
  font-size: 0.85rem;
}
.service-tag.active {
  background: #2d6a4f;
  color: #fff;
  border-color: #2d6a4f;
}
.service-tag__icon {
  font-size: 0.9rem;
}
</style>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/components/ServiceTag.vue
git commit -m "feat(front): add ServiceTag component"
```

---

### Task 7: Build `TestimonialCard` component

**Files:**
- Create: `sites/artisans-shared/src/components/TestimonialCard.vue`

- [ ] **Step 1: Write component**

```vue
<template>
  <article class="testimonial-card">
    <header class="testimonial-card__header">
      <img
        v-if="testimonial.avatar_url"
        :src="testimonial.avatar_url"
        alt=""
        class="testimonial-card__avatar"
      />
      <div v-else class="testimonial-card__avatar testimonial-card__avatar--placeholder">
        {{ initials }}
      </div>
      <div class="testimonial-card__meta">
        <strong>{{ displayName }}</strong>
        <span v-if="testimonial.service_label" class="testimonial-card__service">
          {{ testimonial.service_icon }} {{ testimonial.service_label }}
        </span>
        <time :datetime="testimonial.created_at">{{ formattedDate }}</time>
      </div>
      <div v-if="testimonial.rating" class="testimonial-card__rating">
        {{ '★'.repeat(testimonial.rating) }}{{ '☆'.repeat(5 - testimonial.rating) }}
      </div>
    </header>

    <h3 v-if="testimonial.title" class="testimonial-card__title">{{ testimonial.title }}</h3>
    <p class="testimonial-card__content">{{ testimonial.content }}</p>

    <div v-if="testimonial.media?.length" class="testimonial-card__media">
      <img
        v-for="m in testimonial.media"
        :key="m.id"
        :src="m.media_url"
        alt=""
        class="testimonial-card__media-item"
      />
    </div>

    <footer class="testimonial-card__actions">
      <button type="button" @click="$emit('helpful', testimonial.id)">
        Utile ({{ testimonial.helpful_count }})
      </button>
      <button type="button" @click="$emit('report', testimonial.id)">
        Signaler
      </button>
    </footer>
  </article>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  testimonial: { type: Object, required: true },
})
defineEmits(['helpful', 'report'])

const displayName = computed(() => props.testimonial.display_name || 'Utilisateur anonyme')
const initials = computed(() => displayName.value.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase())
const formattedDate = computed(() => {
  if (!props.testimonial.created_at) return ''
  return new Date(props.testimonial.created_at).toLocaleDateString('fr-FR')
})
</script>

<style scoped>
.testimonial-card {
  border: 1px solid #eee;
  border-radius: 0.75rem;
  padding: 1rem;
  background: #fff;
}
.testimonial-card__header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}
.testimonial-card__avatar {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  object-fit: cover;
}
.testimonial-card__avatar--placeholder {
  display: grid;
  place-items: center;
  background: #2d6a4f;
  color: #fff;
  font-size: 0.75rem;
  font-weight: bold;
}
.testimonial-card__meta {
  display: flex;
  flex-direction: column;
  font-size: 0.85rem;
  flex: 1;
}
.testimonial-card__service {
  color: #666;
}
.testimonial-card__rating {
  color: #f5a623;
}
.testimonial-card__title {
  font-size: 1rem;
  margin: 0 0 0.5rem;
}
.testimonial-card__content {
  margin: 0 0 0.75rem;
  line-height: 1.5;
}
.testimonial-card__media {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}
.testimonial-card__media-item {
  width: 5rem;
  height: 5rem;
  object-fit: cover;
  border-radius: 0.5rem;
}
.testimonial-card__actions {
  display: flex;
  gap: 1rem;
}
.testimonial-card__actions button {
  background: none;
  border: none;
  color: #2d6a4f;
  cursor: pointer;
  font-size: 0.85rem;
}
</style>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/components/TestimonialCard.vue
git commit -m "feat(front): add TestimonialCard component"
```

---

### Task 8: Build `TestimonialComposer` component

**Files:**
- Create: `sites/artisans-shared/src/components/TestimonialComposer.vue`

- [ ] **Step 1: Write component**

```vue
<template>
  <div class="testimonial-composer">
    <BetaBanner message="Les témoignages sont en version beta. Merci pour votre patience." />

    <form @submit.prevent="submit">
      <label>
        Service concerné
        <select v-model="form.artisan_service_id">
          <option :value="null">Général</option>
          <option v-for="s in services" :key="s.id" :value="s.id">
            {{ s.icon || s.catalog_icon }} {{ s.name }}
          </option>
        </select>
      </label>

      <label>
        Note (optionnel)
        <select v-model.number="form.rating">
          <option :value="null">—</option>
          <option v-for="n in 5" :key="n" :value="n">{{ n }} étoile{{ n > 1 ? 's' : '' }}</option>
        </select>
      </label>

      <label>
        Titre (optionnel)
        <input v-model="form.title" type="text" maxlength="150" />
      </label>

      <label>
        Votre témoignage
        <textarea v-model="form.content" rows="4" required maxlength="2000"></textarea>
      </label>

      <div v-if="templates.length" class="testimonial-composer__templates">
        <p>Idées de formulation :</p>
        <button
          v-for="(t, i) in templates"
          :key="i"
          type="button"
          class="template-chip"
          @click="useTemplate(t)"
        >
          {{ t }}
        </button>
      </div>

      <p v-if="error" class="error">{{ error }}</p>

      <button type="submit" :disabled="submitting">
        {{ submitting ? 'Envoi...' : 'Publier mon témoignage' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { createTestimonial, fetchTestimonialTemplates } from '../api.js'
import BetaBanner from './BetaBanner.vue'

const props = defineProps({
  artisanId: { type: Number, required: true },
  services: { type: Array, default: () => [] },
})
const emit = defineEmits(['posted'])

const form = ref({
  artisan_service_id: null,
  rating: null,
  title: '',
  content: '',
})
const templates = ref([])
const submitting = ref(false)
const error = ref('')

const selectedCatalogKey = computed(() => {
  if (!form.value.artisan_service_id) return null
  const s = props.services.find(s => s.id === form.value.artisan_service_id)
  return s?.catalog_key || null
})

watch(selectedCatalogKey, async (key) => {
  if (!key) {
    templates.value = []
    return
  }
  try {
    const res = await fetchTestimonialTemplates(key)
    templates.value = res.data?.[0]?.templates || []
  } catch {
    templates.value = []
  }
})

function useTemplate(text) {
  form.value.content = text
}

async function submit() {
  error.value = ''
  submitting.value = true
  const res = await createTestimonial({
    artisan_id: props.artisanId,
    artisan_service_id: form.value.artisan_service_id,
    rating: form.value.rating,
    title: form.value.title,
    content: form.value.content,
  })
  submitting.value = false
  if (!res.success) {
    error.value = res.error || 'Erreur lors de la publication'
    return
  }
  form.value = { artisan_service_id: null, rating: null, title: '', content: '' }
  emit('posted')
}
</script>

<style scoped>
.testimonial-composer label {
  display: block;
  margin-bottom: 0.75rem;
}
.testimonial-composer input,
.testimonial-composer select,
.testimonial-composer textarea {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ccc;
  border-radius: 0.5rem;
  margin-top: 0.25rem;
}
.testimonial-composer__templates {
  margin-bottom: 0.75rem;
}
.template-chip {
  display: inline-block;
  margin: 0.25rem 0.25rem 0 0;
  padding: 0.35rem 0.6rem;
  border: 1px solid #2d6a4f;
  background: #fff;
  color: #2d6a4f;
  border-radius: 999px;
  cursor: pointer;
  font-size: 0.8rem;
}
.error {
  color: #c0392b;
}
</style>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/components/TestimonialComposer.vue
git commit -m "feat(front): add TestimonialComposer component with templates"
```

---

### Task 9: Build `BetaBanner` component

**Files:**
- Create: `sites/artisans-shared/src/components/BetaBanner.vue`

- [ ] **Step 1: Write component**

```vue
<template>
  <div class="beta-banner" role="status">
    <span class="beta-banner__badge">Bêta</span>
    <span class="beta-banner__message">{{ message }}</span>
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: 'Cette fonctionnalité est en version bêta.' },
})
</script>

<style scoped>
.beta-banner {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  margin-bottom: 1rem;
  background: #fff8e1;
  border: 1px solid #ffecb3;
  border-radius: 0.5rem;
  font-size: 0.85rem;
}
.beta-banner__badge {
  background: #f5a623;
  color: #fff;
  padding: 0.15rem 0.4rem;
  border-radius: 0.25rem;
  font-weight: bold;
  font-size: 0.75rem;
}
</style>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/components/BetaBanner.vue
git commit -m "feat(front): add BetaBanner component"
```

---

### Task 10: Update `Artisan.vue` with Services and Testimonials tabs

**Files:**
- Modify: `sites/artisans-shared/src/views/Artisan.vue`

- [ ] **Step 1: Read current `Artisan.vue` structure**

Run:
```bash
head -50 sites/artisans-shared/src/views/Artisan.vue
```
Expected: view of component imports and template structure.

- [ ] **Step 2: Add imports and tabs**

Add to `<script setup>` imports:
```javascript
import TestimonialCard from '../components/TestimonialCard.vue'
import TestimonialComposer from '../components/TestimonialComposer.vue'
import ServiceTag from '../components/ServiceTag.vue'
import { fetchArtisanServices, fetchTestimonials } from '../api.js'
```

Add reactive state:
```javascript
const services = ref([])
const testimonials = ref([])
const activeTab = ref('services') // services | testimonials | about
```

Load data in `onMounted` or watch artisan id:
```javascript
async function loadServices() {
  const res = await fetchArtisanServices(props.id)
  services.value = res.data || []
}
async function loadTestimonials() {
  const res = await fetchTestimonials({ artisan_id: props.id, limit: 50 })
  testimonials.value = res.data || []
}
```

Add tabs in template:
```vue
<nav class="artisan-tabs">
  <button :class="{ active: activeTab === 'services' }" @click="activeTab = 'services'">Services</button>
  <button :class="{ active: activeTab === 'testimonials' }" @click="activeTab = 'testimonials'">Avis</button>
  <button :class="{ active: activeTab === 'about' }" @click="activeTab = 'about'">À propos</button>
</nav>

<section v-if="activeTab === 'services'" class="artisan-section">
  <h2>Services proposés</h2>
  <div v-if="services.length" class="service-list">
    <div v-for="s in services" :key="s.id" class="service-item">
      <h3>{{ s.catalog_icon || s.icon }} {{ s.name }}</h3>
      <p v-if="s.description">{{ s.description }}</p>
      <p v-if="s.price_range || s.duration" class="service-meta">
        <span v-if="s.price_range">{{ s.price_range }}</span>
        <span v-if="s.duration">{{ s.duration }}</span>
      </p>
    </div>
  </div>
  <p v-else>Aucun service renseigné.</p>
</section>

<section v-if="activeTab === 'testimonials'" class="artisan-section">
  <h2>Avis et témoignages</h2>
  <TestimonialComposer :artisan-id="Number(id)" :services="services" @posted="loadTestimonials" />
  <div class="testimonial-list">
    <TestimonialCard
      v-for="t in testimonials"
      :key="t.id"
      :testimonial="t"
      @helpful="markHelpful"
      @report="openReport"
    />
  </div>
</section>
```

Add helpers:
```javascript
async function markHelpful(id) {
  await markTestimonialHelpful(id)
  await loadTestimonials()
}
function openReport(id) {
  const reason = prompt('Pourquoi signalez-vous ce témoignage ?')
  if (reason) reportTestimonial(id, reason)
}
```

- [ ] **Step 3: Commit**

```bash
git add sites/artisans-shared/src/views/Artisan.vue
git commit -m "feat(front): add services and testimonials tabs to artisan page"
```

---

### Task 11: Create public `Testimonials.vue` feed page

**Files:**
- Create: `sites/artisans-shared/src/views/Testimonials.vue`
- Modify: `sites/artisans-shared/src/main.js` router

- [ ] **Step 1: Write page**

```vue
<template>
  <main class="testimonials-page">
    <BetaBanner message="Les témoignages sont en version bêta. Connectez-vous gratuitement pour partager le vôtre." />
    <h1>Avis et recommandations locales</h1>

    <div class="filters">
      <ServiceTag
        v-for="cat in catalog"
        :key="cat.key"
        :service-key="cat.key"
        :label="cat.label"
        :icon="cat.icon"
        :is-active="selectedService === cat.key"
        @toggle="toggleService"
      />
    </div>

    <div v-if="testimonials.length" class="testimonial-list">
      <TestimonialCard
        v-for="t in testimonials"
        :key="t.id"
        :testimonial="t"
        @helpful="loadTestimonials"
      />
    </div>
    <p v-else>Aucun témoignage pour cette sélection.</p>
  </main>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { fetchTestimonials, fetchServiceCatalog } from '../api.js'
import TestimonialCard from '../components/TestimonialCard.vue'
import ServiceTag from '../components/ServiceTag.vue'
import BetaBanner from '../components/BetaBanner.vue'

const testimonials = ref([])
const catalog = ref([])
const selectedService = ref(null)

async function loadCatalog() {
  const res = await fetchServiceCatalog()
  catalog.value = res.data || []
}

async function loadTestimonials() {
  const filters = { limit: 50 }
  if (selectedService.value) filters.service_type = selectedService.value
  const res = await fetchTestimonials(filters)
  testimonials.value = res.data || []
}

function toggleService(key) {
  selectedService.value = selectedService.value === key ? null : key
}

watch(selectedService, loadTestimonials)
onMounted(() => { loadCatalog(); loadTestimonials() })
</script>

<style scoped>
.testimonials-page {
  padding: 1rem;
  max-width: 800px;
  margin: 0 auto;
}
.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin: 1rem 0;
}
.testimonial-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
</style>
```

- [ ] **Step 2: Register route**

In `sites/artisans-shared/src/main.js`, add:
```javascript
import Testimonials from './views/Testimonials.vue'

// inside routes array:
{ path: '/temoignages', name: 'Testimonials', component: Testimonials },
```

- [ ] **Step 3: Commit**

```bash
git add sites/artisans-shared/src/views/Testimonials.vue sites/artisans-shared/src/main.js
git commit -m "feat(front): add public testimonials feed page"
```

---

### Task 12: Build artisan dashboard `ServicesConfig.vue`

**Files:**
- Create: `sites/artisans-shared/src/views/artisan/ServicesConfig.vue`
- Modify: `sites/artisans-shared/src/views/artisan/Dashboard.vue` (or router)

- [ ] **Step 1: Write view**

```vue
<template>
  <div class="services-config">
    <h2>Mes services</h2>
    <p v-if="services.length >= 5" class="limit-warning">Limite gratuite atteinte (5 services).</p>

    <form @submit.prevent="addService">
      <label>
        Ajouter depuis le catalogue
        <select v-model="newService.catalog_id">
          <option :value="null">— Personnalisé —</option>
          <option v-for="c in catalog" :key="c.id" :value="c.id">{{ c.icon }} {{ c.label }}</option>
        </select>
      </label>
      <label>
        Nom du service
        <input v-model="newService.name" type="text" required />
      </label>
      <label>
        Description
        <textarea v-model="newService.description" rows="2"></textarea>
      </label>
      <label>
        Fourchette de prix
        <input v-model="newService.price_range" type="text" placeholder="Ex: 20€-50€" />
      </label>
      <label>
        Durée
        <input v-model="newService.duration" type="text" placeholder="Ex: 1h" />
      </label>
      <button type="submit" :disabled="services.length >= 5">Ajouter</button>
    </form>

    <ul class="service-list">
      <li v-for="s in services" :key="s.id" class="service-item">
        <div>
          <strong>{{ s.catalog_icon || s.icon }} {{ s.name }}</strong>
          <p v-if="s.description">{{ s.description }}</p>
        </div>
        <div class="service-actions">
          <button @click="toggleActive(s)">{{ s.is_active ? 'Désactiver' : 'Activer' }}</button>
          <button @click="removeService(s.id)">Supprimer</button>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import {
  fetchArtisanServices,
  fetchServiceCatalog,
  createArtisanService,
  updateArtisanService,
  deleteArtisanService,
} from '../../api.js'

const props = defineProps({ token: { type: String, required: true } })

const services = ref([])
const catalog = ref([])
const newService = ref({ catalog_id: null, name: '', description: '', price_range: '', duration: '' })

async function load() {
  const [svcRes, catRes] = await Promise.all([
    fetchArtisanServices('me'),
    fetchServiceCatalog(),
  ])
  services.value = svcRes.data || []
  catalog.value = catRes.data || []
}

async function addService() {
  const selected = catalog.value.find(c => c.id === newService.value.catalog_id)
  await createArtisanService(props.token, {
    service_catalog_id: newService.value.catalog_id,
    name: newService.value.name,
    description: newService.value.description,
    price_range: newService.value.price_range,
    duration: newService.value.duration,
    is_custom: !newService.value.catalog_id,
  })
  newService.value = { catalog_id: null, name: '', description: '', price_range: '', duration: '' }
  await load()
}

async function toggleActive(s) {
  await updateArtisanService(props.token, s.id, { is_active: !s.is_active })
  await load()
}

async function removeService(id) {
  if (!confirm('Supprimer ce service ?')) return
  await deleteArtisanService(props.token, id)
  await load()
}

onMounted(load)
</script>

<style scoped>
.services-config form label {
  display: block;
  margin-bottom: 0.5rem;
}
.services-config input,
.services-config select,
.services-config textarea {
  width: 100%;
  padding: 0.4rem;
  margin-top: 0.2rem;
}
.limit-warning {
  color: #c0392b;
}
.service-list {
  list-style: none;
  padding: 0;
  margin-top: 1rem;
}
.service-item {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem;
  border: 1px solid #eee;
  border-radius: 0.5rem;
  margin-bottom: 0.5rem;
}
.service-actions {
  display: flex;
  gap: 0.5rem;
}
</style>
```

Note: `fetchArtisanServices('me')` will not work with the public endpoint; create a new helper `fetchMyServices(token)` in `api.js` pointing to `/artisans/me/services`.

- [ ] **Step 2: Add `fetchMyServices` to `api.js`**

```javascript
export async function fetchMyServices(token) {
  const res = await fetch(`${API_BASE}/artisans/me/services`, {
    headers: { 'X-Artisan-Token': token },
  })
  if (!res.ok) throw new Error('Erreur chargement de mes services')
  return res.json()
}
```

Update `ServicesConfig.vue` to use `fetchMyServices(props.token)`.

- [ ] **Step 3: Wire into artisan dashboard**

Locate the artisan dashboard router/view and add a tab/link to `ServicesConfig`. Add route:
```javascript
{ path: '/artisan/services', component: ServicesConfig, props: true },
```

- [ ] **Step 4: Commit**

```bash
git add sites/artisans-shared/src/views/artisan/ServicesConfig.vue sites/artisans-shared/src/api.js
git commit -m "feat(front): add artisan services configuration page"
```

---

### Task 13: Update smoke tests

**Files:**
- Modify: `scripts/test-api.sh`

- [ ] **Step 1: Add testimonials/services test block**

Append a new test section to `scripts/test-api.sh`:

```bash
# ------------------------------------------------------------------
# Testimonials & Services
# ------------------------------------------------------------------
echo "== Testimonials & Services =="

# Public catalog
assert_json "$BASE/service-catalog" "GET" "{}" "success" "true"

# Public testimonials list (empty OK)
assert_json "$BASE/testimonials?city=livry" "GET" "{}" "success" "true"

# Public templates
assert_json "$BASE/testimonials/templates" "GET" "{}" "success" "true"
```

- [ ] **Step 2: Run tests**

Run:
```bash
./scripts/test-api.sh
```
Expected: all existing tests still pass, new assertions pass.

- [ ] **Step 3: Commit**

```bash
git add scripts/test-api.sh
git commit -m "test(api): add smoke tests for testimonials and service catalog"
```

---

## Self-Review Checklist

1. **Spec coverage:**
   - Témoignages CRUD + signalement + helpful → Tasks 1-3, 7-11
   - Services artisan catalogue + CRUD + limite 5 gratuits → Tasks 1, 4, 6, 12
   - Authentification stricte → Tasks 3, 5 (user auth helpers), 8 (composer)
   - Bandeau Beta → Tasks 8, 9, 11
   - Fiche artisan onglets → Task 10
   - Flux public → Task 11

2. **Placeholder scan:** no TBD/TODO remains except the explicitly deferred `testimonials_update` and `testimonials_delete` (these should be implemented before closing this plan; add tasks if needed).

3. **Type consistency:** `service_catalog_id`, `artisan_service_id`, `service_type` used consistently across SQL, PHP, and Vue.
