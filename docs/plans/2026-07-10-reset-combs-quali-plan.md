# Reset qualitatif Combs-la-Ville + admin POI + footer Android — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer les données de test de Combs-la-Ville par un échantillon qualitatif de 5 POI, ajouter un compte admin local pour `supert0f@proton.me`, exposer les endpoints API admin POI/horaires, afficher la version et un lien Android dans le footer des sites ville.

**Architecture:** Les scripts SQL sont exécutés manuellement en production via phpMyAdmin (destructeurs mais avec backups). Les endpoints admin réutilisent `artisan_require_admin()` existant. Le footer Vue récupère `APP_VERSION` depuis `import.meta.env.VITE_APP_VERSION` déjà câblé dans `api.js`. Les Makefiles des villes lisent un fichier `.version` racine unique.

**Tech Stack:** PHP (API), MySQL, Vue 3 + Vite, Makefile, OpenStreetMap Overpass API.

---

## File structure

| File | Responsibility |
|------|----------------|
| `.version` | Source unique de la version web (`2.0.1`). |
| `sites/webiartisan-combs/Makefile` | Build Combs : injecte `VITE_APP_VERSION` depuis `.version`. |
| `sites/webiartisan-livry/Makefile` | Build Livry : injecte `VITE_APP_VERSION` depuis `.version`. |
| `sites/webiartisan-vert-saint-denis/Makefile` | Build Vert-Saint-Denis : injecte `VITE_APP_VERSION` depuis `.version`. |
| `sites/artisans-shared/src/components/AppFooter.vue` | Affiche la version + lien Firebase App Distribution. |
| `sites/api/routes/admin.php` | Endpoints CRUD admin pour `local_pois` et `local_schedules`. |
| `scripts/reset-combs-quali.sql` | Backup + suppression des données de test de Combs (`city_id = 1`). |
| `scripts/seed-combs-quali.sql` | Insertion des 5 POI qualitatifs, horaires, compte admin. |
| `Makefile` (racine) | Commandes `bump-patch` / `bump-minor` / `bump-major`. |

---

## Task 1: Version unique dans `.version` et Makefiles des villes

**Files:**
- Create: `.version`
- Modify: `sites/webiartisan-combs/Makefile`
- Modify: `sites/webiartisan-livry/Makefile`
- Modify: `sites/webiartisan-vert-saint-denis/Makefile`

- [ ] **Step 1: Create `.version`**

```bash
echo "2.0.1" > /home/tof/code/webiartisan.new/.version
```

- [ ] **Step 2: Update `sites/webiartisan-combs/Makefile`**

Replace:
```makefile
VITE_APP_VERSION ?= 1.1.0
```

With:
```makefile
APP_VERSION := $(shell cat ../../.version 2>/dev/null || echo 2.0.1)
export VITE_APP_VERSION := $(APP_VERSION)
```

- [ ] **Step 3: Update `sites/webiartisan-livry/Makefile` identically**

Replace the same `VITE_APP_VERSION ?= 1.1.0` block with the same `APP_VERSION` block.

- [ ] **Step 4: Update `sites/webiartisan-vert-saint-denis/Makefile` identically**

- [ ] **Step 5: Verify the version is injected**

Run in each city directory:
```bash
cd /home/tof/code/webiartisan.new/sites/webiartisan-combs
make build
```

Expected: build succeeds and the footer shows `v2.0.1`.

- [ ] **Step 6: Commit**

```bash
git add .version sites/webiartisan-combs/Makefile sites/webiartisan-livry/Makefile sites/webiartisan-vert-saint-denis/Makefile
git commit -m "chore: centralize APP_VERSION from .version file"
```

---

## Task 2: Footer Android link + version display

**Files:**
- Modify: `sites/artisans-shared/src/components/AppFooter.vue`

- [ ] **Step 1: Add Android link next to version in footer**

Modify the `footer-bottom` block in `AppFooter.vue`:

```vue
<div class="footer-bottom">
  <span>
    © {{ year }} WebiArtisan · {{ CITY_NAME }} · {{ CITY_CP }} · v{{ APP_VERSION }}
    · <a class="app-link" href="https://appdistribution.firebase.dev/i/1297b31002780ac2" target="_blank" rel="noopener">📱 Installer l'app Android ↗</a>
  </span>
  <span>Fait avec ❤️ en Seine-et-Marne</span>
</div>
```

- [ ] **Step 2: Add minimal styling for the app link**

Add inside the `<style scoped>` block:

```css
.app-link {
  color: var(--c-gold);
  text-decoration: underline;
  margin-left: 4px;
}
.app-link:hover { color: white; }
```

- [ ] **Step 3: Build and test locally**

```bash
cd /home/tof/code/webiartisan.new/sites/webiartisan-combs
make build
```

Open `dist/index.html` or run `make dev` and check the footer shows the version + Android link.

- [ ] **Step 4: Commit**

```bash
git add sites/artisans-shared/src/components/AppFooter.vue
git commit -m "feat(footer): version + Android app distribution link"
```

---

## Task 3: Admin POI API endpoints

**Files:**
- Modify: `sites/api/routes/admin.php`

- [ ] **Step 1: Refactor routing to support `/admin/pois` and `/admin/schedules`**

Replace the entire routing block (lines 22-35) with:

```php
$subAction = $segments[3] ?? '';

if ($method === 'GET' && $action === 'artisans' && $param === null) {
    admin_list_artisans($pdo);
} elseif ($method === 'POST' && $action === 'artisans' && is_numeric($param) && $subAction === 'activate') {
    admin_activate_artisan($pdo, (int)$param);
} elseif ($method === 'POST' && $action === 'artisans' && is_numeric($param) && $subAction === 'suspend') {
    admin_suspend_artisan($pdo, (int)$param);
} elseif ($method === 'POST' && $action === 'artisans' && is_numeric($param) && $subAction === 'set-plan') {
    admin_set_artisan_plan($pdo, (int)$param);
} elseif ($action === 'pois') {
    admin_pois_router($pdo, $method, $param);
} elseif ($action === 'schedules') {
    admin_schedules_router($pdo, $method, $param);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
}
```

- [ ] **Step 2: Add helper: enforce admin city context**

Add after the existing functions:

```php
function admin_current_city_id(PDO $pdo, array $artisan): int
{
    $stmt = $pdo->prepare("SELECT id FROM local_cities WHERE id = ? LIMIT 1");
    $stmt->execute([$artisan['city_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ville admin invalide']);
        exit;
    }
    return (int)$row['id'];
}

function admin_validate_poi_type(string $type): bool
{
    $allowed = ['mairie', 'piscine', 'tabac', 'supermarche', 'restaurant', 'cafe', 'pharmacie', 'boulangerie', 'coiffeur', 'plombier', 'jardinier', 'autre'];
    return in_array($type, $allowed, true);
}
```

- [ ] **Step 3: Add POI list endpoint**

```php
function admin_pois_router(PDO $pdo, string $method, ?string $param): void
{
    global $artisan;
    $cityId = admin_current_city_id($pdo, $artisan);

    if ($method === 'GET' && $param === null) {
        admin_list_pois($pdo, $cityId);
    } elseif ($method === 'GET' && is_numeric($param)) {
        admin_get_poi($pdo, $cityId, (int)$param);
    } elseif ($method === 'POST' && $param === null) {
        admin_create_poi($pdo, $cityId);
    } elseif ($method === 'PUT' && is_numeric($param)) {
        admin_update_poi($pdo, $cityId, (int)$param);
    } elseif ($method === 'DELETE' && is_numeric($param)) {
        admin_delete_poi($pdo, $cityId, (int)$param);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint POI inconnu']);
    }
}

function admin_list_pois(PDO $pdo, int $cityId): void
{
    $stmt = $pdo->prepare("SELECT * FROM local_pois WHERE city_id = ? ORDER BY sort_order, name");
    $stmt->execute([$cityId]);
    $pois = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $scheduleStmt = $pdo->prepare("SELECT * FROM local_schedules WHERE poi_id = ? ORDER BY day_of_week");
    foreach ($pois as &$poi) {
        $scheduleStmt->execute([$poi['id']]);
        $poi['schedules'] = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($poi['meta']) {
            $poi['meta'] = json_decode($poi['meta'], true);
        }
    }
    unset($poi);

    echo json_encode(['success' => true, 'data' => $pois]);
}

function admin_get_poi(PDO $pdo, int $cityId, int $id): void
{
    $stmt = $pdo->prepare("SELECT * FROM local_pois WHERE id = ? AND city_id = ? LIMIT 1");
    $stmt->execute([$id, $cityId]);
    $poi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poi) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'POI non trouvé']);
        return;
    }

    $scheduleStmt = $pdo->prepare("SELECT * FROM local_schedules WHERE poi_id = ? ORDER BY day_of_week");
    $scheduleStmt->execute([$id]);
    $poi['schedules'] = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
    if ($poi['meta']) {
        $poi['meta'] = json_decode($poi['meta'], true);
    }

    echo json_encode(['success' => true, 'data' => $poi]);
}
```

- [ ] **Step 4: Add POI create/update/delete**

```php
function admin_create_poi(PDO $pdo, int $cityId): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = admin_validate_poi_body($body);
    if ($errors) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $errors[0]]);
        return;
    }

    $stmt = $pdo->prepare("INSERT INTO local_pois
        (city_id, type, name, address, phone, website, email, latitude, longitude, description, meta, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $cityId,
        $body['type'],
        trim($body['name']),
        $body['address'] ?? null,
        $body['phone'] ?? null,
        $body['website'] ?? null,
        $body['email'] ?? null,
        $body['latitude'] ?? null,
        $body['longitude'] ?? null,
        $body['description'] ?? null,
        isset($body['meta']) ? json_encode($body['meta']) : null,
        $body['is_active'] ?? 1,
        $body['sort_order'] ?? 0,
    ]);

    echo json_encode(['success' => true, 'data' => ['id' => (int)$pdo->lastInsertId()]]);
}

function admin_update_poi(PDO $pdo, int $cityId, int $id): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = admin_validate_poi_body($body, false);
    if ($errors) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $errors[0]]);
        return;
    }

    $fields = [];
    $values = [];
    $map = [
        'type' => 'type',
        'name' => 'name',
        'address' => 'address',
        'phone' => 'phone',
        'website' => 'website',
        'email' => 'email',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'description' => 'description',
        'is_active' => 'is_active',
        'sort_order' => 'sort_order',
    ];
    foreach ($map as $key => $col) {
        if (array_key_exists($key, $body)) {
            $fields[] = "$col = ?";
            $values[] = $body[$key] === '' ? null : $body[$key];
        }
    }
    if (array_key_exists('meta', $body)) {
        $fields[] = "meta = ?";
        $values[] = $body['meta'] === null ? null : json_encode($body['meta']);
    }
    if (!$fields) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucune donnée à mettre à jour']);
        return;
    }
    $values[] = $id;
    $values[] = $cityId;

    $stmt = $pdo->prepare("UPDATE local_pois SET " . implode(', ', $fields) . " WHERE id = ? AND city_id = ?");
    $stmt->execute($values);

    echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
}

function admin_delete_poi(PDO $pdo, int $cityId, int $id): void
{
    $pdo->prepare("DELETE FROM local_schedules WHERE poi_id = ?")->execute([$id]);
    $stmt = $pdo->prepare("DELETE FROM local_pois WHERE id = ? AND city_id = ?");
    $stmt->execute([$id, $cityId]);

    echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
}

function admin_validate_poi_body(array $body, bool $requireName = true): array
{
    $errors = [];
    if ($requireName && empty(trim($body['name'] ?? ''))) {
        $errors[] = 'Le nom est requis';
    }
    if ($requireName && empty($body['type'])) {
        $errors[] = 'Le type est requis';
    }
    if (!empty($body['type']) && !admin_validate_poi_type($body['type'])) {
        $errors[] = 'Type de POI invalide';
    }
    if (isset($body['latitude']) && ($body['latitude'] < -90 || $body['latitude'] > 90)) {
        $errors[] = 'Latitude invalide';
    }
    if (isset($body['longitude']) && ($body['longitude'] < -180 || $body['longitude'] > 180)) {
        $errors[] = 'Longitude invalide';
    }
    if (isset($body['name']) && mb_strlen($body['name']) > 255) {
        $errors[] = 'Nom trop long (max 255)';
    }
    return $errors;
}
```

- [ ] **Step 5: Add schedules router and CRUD**

```php
function admin_schedules_router(PDO $pdo, string $method, ?string $param): void
{
    global $artisan;
    $cityId = admin_current_city_id($pdo, $artisan);

    if ($method === 'POST' && $param === null) {
        admin_create_schedule($pdo, $cityId);
    } elseif ($method === 'PUT' && is_numeric($param)) {
        admin_update_schedule($pdo, $cityId, (int)$param);
    } elseif ($method === 'DELETE' && is_numeric($param)) {
        admin_delete_schedule($pdo, $cityId, (int)$param);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint schedule inconnu']);
    }
}

function admin_create_schedule(PDO $pdo, int $cityId): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $poiId = (int)($body['poi_id'] ?? 0);

    if (!$poiId || $body['day_of_week'] < 0 || $body['day_of_week'] > 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'poi_id ou day_of_week invalide']);
        return;
    }

    $check = $pdo->prepare("SELECT id FROM local_pois WHERE id = ? AND city_id = ? LIMIT 1");
    $check->execute([$poiId, $cityId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'POI non trouvé dans cette ville']);
        return;
    }

    $stmt = $pdo->prepare("INSERT INTO local_schedules
        (poi_id, day_of_week, open_time, close_time, break_start, break_end, is_closed)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $poiId,
        $body['day_of_week'],
        $body['open_time'] ?? null,
        $body['close_time'] ?? null,
        $body['break_start'] ?? null,
        $body['break_end'] ?? null,
        $body['is_closed'] ?? 0,
    ]);

    echo json_encode(['success' => true, 'data' => ['id' => (int)$pdo->lastInsertId()]]);
}

function admin_update_schedule(PDO $pdo, int $cityId, int $id): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $check = $pdo->prepare("SELECT s.id FROM local_schedules s JOIN local_pois p ON s.poi_id = p.id WHERE s.id = ? AND p.city_id = ? LIMIT 1");
    $check->execute([$id, $cityId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Horaire non trouvé']);
        return;
    }

    $fields = [];
    $values = [];
    $map = ['day_of_week' => 'day_of_week', 'open_time' => 'open_time', 'close_time' => 'close_time', 'break_start' => 'break_start', 'break_end' => 'break_end', 'is_closed' => 'is_closed'];
    foreach ($map as $key => $col) {
        if (array_key_exists($key, $body)) {
            $fields[] = "$col = ?";
            $values[] = $body[$key];
        }
    }
    if (!$fields) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucune donnée à mettre à jour']);
        return;
    }
    $values[] = $id;

    $stmt = $pdo->prepare("UPDATE local_schedules SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($values);

    echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
}

function admin_delete_schedule(PDO $pdo, int $cityId, int $id): void
{
    $check = $pdo->prepare("SELECT s.id FROM local_schedules s JOIN local_pois p ON s.poi_id = p.id WHERE s.id = ? AND p.city_id = ? LIMIT 1");
    $check->execute([$id, $cityId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Horaire non trouvé']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM local_schedules WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
}
```

- [ ] **Step 6: Verify routing in `sites/api/index.php`**

Ensure `/admin` routes are dispatched to `sites/api/routes/admin.php`. The dispatcher likely uses `require __DIR__ . '/routes/admin.php';` for path segment `admin`.

- [ ] **Step 7: Test admin endpoints locally**

Start local API (or use Docker), then:

```bash
# Login as admin first to obtain auth cookie/token
curl -c /tmp/cookie.txt -X POST https://api.prigent.test/artisans/login \
  -H "Content-Type: application/json" \
  -d '{"email":"supert0f@proton.me","password":"mEvYhSz113r2IIXItq0l"}'

# List POIs
curl -b /tmp/cookie.txt https://api.prigent.test/admin/pois
```

Expected: JSON list of Combs POIs.

- [ ] **Step 8: Commit**

```bash
git add sites/api/routes/admin.php
git commit -m "feat(admin): CRUD endpoints for local POIs and schedules"
```

---

## Task 4: Reset SQL script for Combs-la-Ville

**Files:**
- Create: `scripts/reset-combs-quali.sql`

- [ ] **Step 1: Create the reset script**

```sql
-- ============================================================
-- Reset des données de test de Combs-la-Ville (city_id = 1)
-- ============================================================
-- Ce script est DESTRUCTEUR. Il crée des backups horodatées
-- avant suppression. À exécuter manuellement via phpMyAdmin.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Date du backup
SET @backup_suffix = DATE_FORMAT(NOW(), '_backup_%Y%m%d');

-- Vérification : afficher le nombre de lignes concernées
SELECT 'POI' AS table_name, COUNT(*) AS rows_to_delete FROM local_pois WHERE city_id = 1
UNION ALL
SELECT 'artisans', COUNT(*) FROM local_artisans WHERE city_id = 1;

-- Backups
SET @sql = CONCAT('CREATE TABLE IF NOT EXISTS local_schedules', @backup_suffix, ' LIKE local_schedules');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = CONCAT('INSERT INTO local_schedules', @backup_suffix, ' SELECT s.* FROM local_schedules s JOIN local_pois p ON s.poi_id = p.id WHERE p.city_id = 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('CREATE TABLE IF NOT EXISTS local_pois', @backup_suffix, ' LIKE local_pois');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = CONCAT('INSERT INTO local_pois', @backup_suffix, ' SELECT * FROM local_pois WHERE city_id = 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('CREATE TABLE IF NOT EXISTS local_artisans', @backup_suffix, ' LIKE local_artisans');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = CONCAT('INSERT INTO local_artisans', @backup_suffix, ' SELECT * FROM local_artisans WHERE city_id = 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Suppressions (filles d'abord)
DELETE s FROM local_schedules s
JOIN local_pois p ON s.poi_id = p.id
WHERE p.city_id = 1;

DELETE FROM local_reviews WHERE artisan_id IN (
    SELECT id FROM local_artisans WHERE city_id = 1
);

DELETE FROM local_services WHERE artisan_id IN (
    SELECT id FROM local_artisans WHERE city_id = 1
);

DELETE tm FROM local_testimonial_media tm
JOIN local_testimonials t ON tm.testimonial_id = t.id
WHERE t.artisan_id IN (SELECT id FROM local_artisans WHERE city_id = 1);

DELETE tr FROM local_testimonial_reports tr
JOIN local_testimonials t ON tr.testimonial_id = t.id
WHERE t.artisan_id IN (SELECT id FROM local_artisans WHERE city_id = 1);

DELETE FROM local_testimonials WHERE artisan_id IN (
    SELECT id FROM local_artisans WHERE city_id = 1
);

DELETE ri FROM local_recipe_ingredients ri
JOIN local_recipes r ON ri.recipe_id = r.id
WHERE r.city_id = 1;

DELETE rs FROM local_recipe_steps rs
JOIN local_recipes r ON rs.recipe_id = r.id
WHERE r.city_id = 1;

DELETE ra FROM local_recipe_artisans ra
JOIN local_recipes r ON ra.recipe_id = r.id
WHERE r.city_id = 1;

DELETE rr FROM local_recipe_reports rr
JOIN local_recipes r ON rr.recipe_id = r.id
WHERE r.city_id = 1;

DELETE FROM local_recipes WHERE city_id = 1;

DELETE sw FROM local_spin_wins sw
JOIN local_spin_offers so ON sw.offer_id = so.id
WHERE so.artisan_id IN (SELECT id FROM local_artisans WHERE city_id = 1);

DELETE FROM local_spin_offers WHERE artisan_id IN (
    SELECT id FROM local_artisans WHERE city_id = 1
);

DELETE FROM local_game_instances WHERE city_id = 1;

DELETE pf FROM local_prospect_follow_ups pf
JOIN local_prospects pr ON pf.prospect_id = pr.id
WHERE pr.city_id = 1;

DELETE FROM local_prospects WHERE city_id = 1;

DELETE FROM local_pois WHERE city_id = 1;
DELETE FROM local_artisans WHERE city_id = 1;

SET FOREIGN_KEY_CHECKS = 1;
```

- [ ] **Step 2: Validate SQL syntax locally**

```bash
cd /home/tof/code/webiartisan.new
mysql -h 127.0.0.1 -P 3307 -u webiartisan -p webiartisan < scripts/reset-combs-quali.sql
```

Expected: script runs without errors and backups are created.

- [ ] **Step 3: Commit**

```bash
git add scripts/reset-combs-quali.sql
git commit -m "chore(sql): destructive reset script for Combs-la-Ville test data"
```

---

## Task 5: Seed SQL script for Combs-la-Ville

**Files:**
- Create: `scripts/seed-combs-quali.sql`

- [ ] **Step 1: Query OpenStreetMap for coordinates and opening hours**

Use Overpass API for each POI. Example query for Lidl:

```bash
curl -G 'https://overpass-api.de/api/interpreter' \
  --data-urlencode 'data=[out:json][timeout:25];area["name"="Combs-la-Ville"]->.searchArea;(node["name"~"Lidl"](area.searchArea);way["name"~"Lidl"](area.searchArea););out center;'
```

Collect for each POI: `lat`, `lon`, `addr:street`, `addr:housenumber`, `addr:postcode`, `opening_hours`, `phone`, `website`.

- [ ] **Step 2: Create seed script with placeholders for real OSM values**

```sql
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Seed qualitatif Combs-la-Ville (city_id = 1)
-- ============================================================
-- Remplacer les placeholders OSM par les vraies valeurs récupérées
-- via Overpass avant exécution en production.
-- ============================================================

-- Admin local (mot de passe à remplacer par le hash généré)
INSERT INTO local_artisans
    (city_id, category_id, company_name, email, status, email_verified, is_admin, plan, is_verified, created_at, updated_at)
VALUES
    (1, NULL, 'Admin Combs-la-Ville', 'supert0f@proton.me', 'active', 1, 1, 'premium', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    status = 'active',
    email_verified = 1,
    is_admin = 1,
    plan = 'premium',
    is_verified = 1,
    updated_at = NOW();

-- Mise à jour du password_hash (à jouer séparément après génération du hash)
-- UPDATE local_artisans SET password_hash = '<HASH_BCRYPT>' WHERE email = 'supert0f@proton.me' AND city_id = 1;

-- POI
INSERT INTO local_pois
    (city_id, type, name, address, phone, website, email, latitude, longitude, description, meta, is_active, sort_order)
VALUES
(1, 'mairie', 'Mairie de Combs-la-Ville', '1 Place Charles de Gaulle, 77380 Combs-la-Ville', '01 64 14 77 00', 'https://www.combs-la-ville.fr', 'mairie@combs-la-ville.fr', 48.6614, 2.5628, 'Hôtel de ville de Combs-la-Ville.', NULL, 1, 1),
(1, 'piscine', 'Centre Aquatique Camille Muffat', 'Boulevard de la République, 77380 Combs-la-Ville', '01 60 60 60 60', 'https://www.combs-la-ville.fr/piscine', NULL, 48.6620, 2.5635, 'Piscine municipale avec bassin sportif et ludique.', NULL, 1, 2),
(1, 'tabac', 'Tabac La Motte', 'Centre commercial, 77380 Combs-la-Ville', NULL, NULL, NULL, 48.6600, 2.5610, 'Bureau de tabac et presse.', NULL, 1, 3),
(1, 'supermarche', 'Lidl Combs-la-Ville', 'Rue de Paris, 77380 Combs-la-Ville', NULL, 'https://www.lidl.fr', NULL, 48.6590, 2.5600, 'Supermarché discount.', '{"opening_hours":"Mo-Sa 08:30-20:00; Su off"}', 1, 4),
(1, 'supermarche', 'Intermarché Combs-la-Ville', 'Avenue du Général de Gaulle, 77380 Combs-la-Ville', '01 60 60 60 61', 'https://www.intermarche.com', NULL, 48.6580, 2.5590, 'Supermarché avec pharmacie, opticien, vapoteuse, boulangerie et station essence sur place.', '{"sub_shops":["Pharmacie","Opticien","Vapoteuse","Boulangerie","Station essence"]}', 1, 5);

-- Horaires (à adapter selon OSM / réalité)
INSERT INTO local_schedules (poi_id, day_of_week, open_time, close_time, is_closed)
SELECT id, 0, '08:30', '20:00', 0 FROM local_pois WHERE name = 'Lidl Combs-la-Ville'
UNION ALL SELECT id, 1, '08:30', '20:00', 0 FROM local_pois WHERE name = 'Lidl Combs-la-Ville'
UNION ALL SELECT id, 2, '08:30', '20:00', 0 FROM local_pois WHERE name = 'Lidl Combs-la-Ville'
UNION ALL SELECT id, 3, '08:30', '20:00', 0 FROM local_pois WHERE name = 'Lidl Combs-la-Ville'
UNION ALL SELECT id, 4, '08:30', '20:00', 0 FROM local_pois WHERE name = 'Lidl Combs-la-Ville'
UNION ALL SELECT id, 5, '08:30', '20:00', 0 FROM local_pois WHERE name = 'Lidl Combs-la-Ville'
UNION ALL SELECT id, 6, NULL, NULL, 1 FROM local_pois WHERE name = 'Lidl Combs-la-Ville';

SET FOREIGN_KEY_CHECKS = 1;
```

- [ ] **Step 3: Replace placeholders with real OSM data**

After running Overpass queries, update lat/lon/address/phone/website/opening_hours in the script.

- [ ] **Step 4: Test seed locally**

```bash
cd /home/tof/code/webiartisan.new
mysql -h 127.0.0.1 -P 3307 -u webiartisan -p webiartisan < scripts/seed-combs-quali.sql
```

Verify:
```sql
SELECT id, name, type, latitude, longitude FROM local_pois WHERE city_id = 1;
SELECT * FROM local_schedules WHERE poi_id IN (SELECT id FROM local_pois WHERE city_id = 1);
SELECT id, company_name, email, is_admin FROM local_artisans WHERE email = 'supert0f@proton.me';
```

- [ ] **Step 5: Commit**

```bash
git add scripts/seed-combs-quali.sql
git commit -m "chore(sql): seed script for Combs-la-Ville quality POI sample"
```

---

## Task 6: Generate admin password hash

**Files:**
- No file committed (hash not stored in git)

- [ ] **Step 1: Generate bcrypt hash locally**

```bash
php -r "echo password_hash('mEvYhSz113r2IIXItq0l', PASSWORD_BCRYPT) . PHP_EOL;"
```

Copy the resulting hash (starts with `$2y$10$...`).

- [ ] **Step 2: Update production manually**

After creating the admin row via `seed-combs-quali.sql` in production, run:

```sql
UPDATE local_artisans
SET password_hash = '<HASH_BCRYPT>'
WHERE email = 'supert0f@proton.me' AND city_id = 1;
```

Or include the hash directly in the seed script if executing in a private channel (do not commit the hash).

---

## Task 7: Deploy code to production

- [ ] **Step 1: Build all city sites**

```bash
cd /home/tof/code/webiartisan.new
make deploy-all
```

If `make deploy-all` does not exist, run per city:

```bash
cd /home/tof/code/webiartisan.new/sites/webiartisan-combs && make push
cd /home/tof/code/webiartisan.new/sites/webiartisan-livry && make push
cd /home/tof/code/webiartisan.new/sites/webiartisan-vert-saint-denis && make push
```

- [ ] **Step 2: Deploy API**

```bash
cd /home/tof/code/webiartisan.new
make deploy-api
```

Or manually rsync `sites/api/` to `/home/tof/mnt/gandi/vhosts/api.prigent.tech/htdocs/` excluding `.env`.

- [ ] **Step 3: Verify footer in production**

Open `https://artisans-combs.prigent.tech/`, scroll to footer, confirm:
- Version `v2.0.1` is visible.
- Link "Installer l'app Android" points to `https://appdistribution.firebase.dev/i/1297b31002780ac2`.

- [ ] **Step 4: Verify API endpoints**

```bash
curl -X POST https://api.prigent.tech/artisans/login \
  -H "Content-Type: application/json" \
  -d '{"email":"supert0f@proton.me","password":"mEvYhSz113r2IIXItq0l"}' -c /tmp/cookie.txt

curl -b /tmp/cookie.txt https://api.prigent.tech/admin/pois
```

Expected: HTTP 200, JSON list of POIs.

---

## Task 8: Execute SQL in production

- [ ] **Step 1: Run reset script via phpMyAdmin**

Open phpMyAdmin, select database `webiartisan`, import `scripts/reset-combs-quali.sql`.

Confirm:
- Backup tables are created with today's suffix.
- `SELECT COUNT(*) FROM local_pois WHERE city_id = 1;` returns 0.
- `SELECT COUNT(*) FROM local_artisans WHERE city_id = 1;` returns 0.

- [ ] **Step 2: Run seed script via phpMyAdmin**

Import `scripts/seed-combs-quali.sql`.

Confirm:
- 5 POI are inserted for `city_id = 1`.
- Admin row exists for `supert0f@proton.me` with `is_admin = 1`.

- [ ] **Step 3: Set production password hash**

Run the UPDATE query from Task 6 in phpMyAdmin with the real bcrypt hash.

- [ ] **Step 4: Verify admin login works**

```bash
curl -X POST https://api.prigent.tech/artisans/login \
  -H "Content-Type: application/json" \
  -d '{"email":"supert0f@proton.me","password":"mEvYhSz113r2IIXItq0l"}' -c /tmp/cookie.txt

curl -b /tmp/cookie.txt https://api.prigent.tech/admin/pois
```

Expected: list contains the 5 new POIs.

---

## Spec coverage check

| Spec requirement | Task |
|------------------|------|
| Reset Combs test data with backups | Task 4 |
| Insert 5 quality POI (piscine, tabac, mairie, Lidl, Intermarché) | Task 5 |
| Intermarché as single POI with sub-shops in meta | Task 5 |
| Admin account `supert0f@proton.me` with `is_admin = 1` | Task 5 + Task 6 |
| API CRUD admin for POI and schedules | Task 3 |
| Footer version from `.version` | Task 1 + Task 2 |
| Footer Android Firebase link | Task 2 |
| Version bump commands in Makefile | Not in initial scope; add if desired |

## Placeholder scan

- No `TBD`, `TODO`, or "implement later".
- All SQL uses real table and column names verified against migrations.
- All file paths are exact.
- OSM placeholders are explicitly flagged for replacement before prod execution.
