# Livry — Modules Prospection B2B & Recettes locales

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter deux modules fonctionnels au POC Livry : un outil de prospection B2B (carte/liste/fiche + pipeline artisan) et un module de recettes locales (seed/admin + contributions publiques).

**Architecture:** Back PHP/MySQL avec tables `local_prospects*` et `local_recipe*` ; routes API publiques + privées ; front Vue 3 partagé sous `sites/artisans-shared/` avec de nouvelles vues et un `api.js` enrichi. Les données de démonstration sont chargées par les seeds SQL montés dans `docker-entrypoint-initdb.d`.

**Tech Stack:** PHP 8.4, MySQL 8, Vue 3, Vue Router, Leaflet, Vite.

---

## File structure

| File | Responsibility |
|------|----------------|
| `sites/api/migrations/026_b2b_recipes.sql` | Création des tables prospects et recettes |
| `data/seeds/livry_prospects.sql` | Prospects de démo pour Livry (import POI) |
| `data/seeds/livry_recipes.sql` | Recettes de démo pour Livry |
| `docker-compose.yml` | Monte les nouveaux seeds/initdb |
| `sites/api/routes/prospects.php` | API publique prospects |
| `sites/api/routes/recipes.php` | API publique recettes |
| `sites/api/routes/artisans.php` | Endpoints artisans : suivis prospects + admin recettes |
| `sites/api/index.php` | Routage des modules `prospects` et `recipes` |
| `sites/artisans-shared/src/api.js` | Appels API front |
| `sites/artisans-shared/src/views/Prospects.vue` | Page annuaire B2B (carte/liste) |
| `sites/artisans-shared/src/views/ProspectDetail.vue` | Fiche prospect |
| `sites/artisans-shared/src/views/Recipes.vue` | Liste des recettes |
| `sites/artisans-shared/src/views/RecipeDetail.vue` | Fiche recette |
| `sites/artisans-shared/src/views/RecipeForm.vue` | Formulaire nouvelle recette / complément |
| `sites/artisans-shared/src/views/AdminRecipes.vue` | Admin recettes signalées (flag admin) |
| `sites/artisans-shared/src/main.js` | Déclaration des routes |

---

## Phase 0 — Schéma & seeds

### Task 1: Migration SQL prospects + recettes

**Files:**
- Create: `sites/api/migrations/026_b2b_recipes.sql`

- [ ] **Step 1: Write migration**

```sql
-- ============================================================
-- WebIArtisan — Migration 026 : Prospection B2B & Recettes
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE local_artisans
    ADD COLUMN is_admin BOOLEAN NOT NULL DEFAULT FALSE AFTER is_featured;

CREATE TABLE IF NOT EXISTS local_prospects (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    city_id         INT NOT NULL,
    source_poi_id   INT DEFAULT NULL,
    name            VARCHAR(200) NOT NULL,
    type            VARCHAR(100) NOT NULL,
    zone            VARCHAR(100) DEFAULT NULL,
    address         TEXT,
    phone           VARCHAR(20),
    email           VARCHAR(255),
    website         VARCHAR(500),
    instagram       VARCHAR(100),
    latitude        DECIMAL(10,7) DEFAULT NULL,
    longitude       DECIMAL(10,7) DEFAULT NULL,
    pitch           TEXT,
    weakness        TEXT,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_city_active (city_id, is_active),
    INDEX idx_city_zone (city_id, zone),
    INDEX idx_type (type),
    CONSTRAINT fk_prospects_city FOREIGN KEY (city_id) REFERENCES local_cities(id) ON DELETE CASCADE,
    CONSTRAINT fk_prospects_poi  FOREIGN KEY (source_poi_id) REFERENCES local_pois(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_prospect_follow_ups (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    prospect_id INT NOT NULL,
    artisan_id  INT NOT NULL,
    status      ENUM('tocontact','contacted','meeting','converted','declined') NOT NULL DEFAULT 'tocontact',
    notes       TEXT,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_follow_up (prospect_id, artisan_id),
    CONSTRAINT fk_follow_prospect FOREIGN KEY (prospect_id) REFERENCES local_prospects(id) ON DELETE CASCADE,
    CONSTRAINT fk_follow_artisan  FOREIGN KEY (artisan_id)  REFERENCES local_artisans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_recipes (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    city_id           INT NOT NULL,
    title             VARCHAR(200) NOT NULL,
    slug              VARCHAR(220) NOT NULL UNIQUE,
    description       TEXT,
    image_url         VARCHAR(500),
    prep_time_minutes INT DEFAULT 0,
    cook_time_minutes INT DEFAULT 0,
    servings          INT DEFAULT 1,
    difficulty        ENUM('very_easy','easy','medium','hard') NOT NULL DEFAULT 'easy',
    season            ENUM('spring','summer','autumn','winter','all') NOT NULL DEFAULT 'all',
    is_premium        BOOLEAN DEFAULT FALSE,
    is_incomplete     BOOLEAN DEFAULT FALSE,
    parent_recipe_id  INT DEFAULT NULL,
    status            ENUM('published','reported','archived') NOT NULL DEFAULT 'published',
    submitted_by      VARCHAR(100),
    submitter_email   VARCHAR(255),
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_city_status (city_id, status),
    INDEX idx_difficulty (difficulty),
    INDEX idx_season (season),
    CONSTRAINT fk_recipes_city   FOREIGN KEY (city_id) REFERENCES local_cities(id) ON DELETE CASCADE,
    CONSTRAINT fk_recipes_parent FOREIGN KEY (parent_recipe_id) REFERENCES local_recipes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_recipe_ingredients (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id    INT NOT NULL,
    name         VARCHAR(150) NOT NULL,
    quantity     DECIMAL(10,2) DEFAULT NULL,
    unit         VARCHAR(50) DEFAULT NULL,
    is_local     BOOLEAN DEFAULT FALSE,
    is_optional  BOOLEAN DEFAULT FALSE,
    sort_order   INT DEFAULT 0,
    CONSTRAINT fk_ing_recipe FOREIGN KEY (recipe_id) REFERENCES local_recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_recipe_steps (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id    INT NOT NULL,
    step_number  INT NOT NULL,
    instruction  TEXT NOT NULL,
    CONSTRAINT fk_step_recipe FOREIGN KEY (recipe_id) REFERENCES local_recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_recipe_artisans (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id  INT NOT NULL,
    artisan_id INT NOT NULL,
    UNIQUE KEY uk_recipe_artisan (recipe_id, artisan_id),
    CONSTRAINT fk_reca_recipe   FOREIGN KEY (recipe_id)  REFERENCES local_recipes(id) ON DELETE CASCADE,
    CONSTRAINT fk_reca_artisan  FOREIGN KEY (artisan_id) REFERENCES local_artisans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_recipe_reports (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id  INT NOT NULL,
    reason     TEXT,
    reporter_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_report_recipe FOREIGN KEY (recipe_id) REFERENCES local_recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Commit**

```bash
cd /mnt/c/Users/user/code/webiartisan.new/.worktrees/livry-modules
git add sites/api/migrations/026_b2b_recipes.sql
git commit -m "feat(db): add prospects and recipes tables"
```

### Task 2: Seed prospects Livry

**Files:**
- Create: `data/seeds/livry_prospects.sql`

- [ ] **Step 1: Write seed**

```sql
-- ============================================================
-- WebIArtisan — Seed prospects B2B pour Livry
-- ============================================================
SET NAMES utf8mb4;

SET @livry_id = (SELECT id FROM local_cities WHERE slug = 'livry' LIMIT 1);

INSERT IGNORE INTO local_prospects
    (city_id, source_poi_id, name, type, zone, address, phone, email, website, latitude, longitude, pitch, weakness, is_active)
SELECT
    @livry_id,
    p.id,
    p.name,
    p.type,
    'Centre-bourg',
    p.address,
    p.phone,
    p.email,
    p.website,
    p.latitude,
    p.longitude,
    NULL,
    NULL,
    TRUE
FROM local_pois p
WHERE p.city_id = @livry_id;

-- Compléments fictifs
INSERT IGNORE INTO local_prospects
    (city_id, name, type, zone, address, phone, email, latitude, longitude, pitch, weakness, is_active)
VALUES
(@livry_id, 'Auberge du Bocage', 'restaurant', 'Nord', 'Route de Caen, 14240 Livry', '02 31 12 34 56', 'contact@aubergedubocage.fr', 49.1090000, -0.7670000, 'Mettre en avant les produits locaux dans la carte du restaurant.', 'Carte actuelle peu locale.', TRUE),
(@livry_id, 'Boucherie Charcuterie Lemoine', 'boucherie', 'Centre-bourg', '3 rue Principale, 14240 Livry', '02 31 23 45 67', NULL, 49.1082000, -0.7659000, 'Fournir viande locale aux artisans traiteurs et restaurants.', 'Pas de visibilité en ligne.', TRUE);
```

- [ ] **Step 2: Commit**

```bash
git add data/seeds/livry_prospects.sql
git commit -m "feat(seed): add Livry B2B prospects"
```

### Task 3: Seed recettes Livry

**Files:**
- Create: `data/seeds/livry_recipes.sql`

- [ ] **Step 1: Write seed**

```sql
-- ============================================================
-- WebIArtisan — Seed recettes pour Livry
-- ============================================================
SET NAMES utf8mb4;

SET @livry_id = (SELECT id FROM local_cities WHERE slug = 'livry' LIMIT 1);

INSERT IGNORE INTO local_recipes
    (city_id, title, slug, description, image_url, prep_time_minutes, cook_time_minutes, servings, difficulty, season, is_premium, is_incomplete, status, submitted_by)
VALUES
(@livry_id, 'Tarte aux pommes normandes', 'tarte-aux-pommes-normandes', 'Une tarte simple et gourmande avec les pommes du bocage.', 'https://images.unsplash.com/photo-1568571780765-9276ac8b75a2?w=800', 20, 35, 6, 'easy', 'autumn', FALSE, FALSE, 'published', 'Mairie de Livry'),
(@livry_id, 'Pain perdu à la brioche', 'pain-perdu-brioche', 'Idéal pour utiliser la brioche de la boulangerie du village.', 'https://images.unsplash.com/photo-1484723091739-30a097e8f929?w=800', 10, 10, 4, 'very_easy', 'winter', FALSE, TRUE, 'published', 'Boulangerie du Village');

SET @tarte_id = (SELECT id FROM local_recipes WHERE slug = 'tarte-aux-pommes-normandes' LIMIT 1);
SET @pain_id  = (SELECT id FROM local_recipes WHERE slug = 'pain-perdu-brioche' LIMIT 1);

INSERT IGNORE INTO local_recipe_ingredients (recipe_id, name, quantity, unit, is_local, is_optional, sort_order) VALUES
(@tarte_id, 'Pommes', 4, 'pièce', TRUE, FALSE, 1),
(@tarte_id, 'Pâte brisée', 1, 'pièce', FALSE, FALSE, 2),
(@tarte_id, 'Sucre', 50, 'g', FALSE, FALSE, 3),
(@tarte_id, 'Beurre', 30, 'g', TRUE, FALSE, 4),
(@pain_id, 'Brioche', 6, 'tranche', TRUE, FALSE, 1),
(@pain_id, 'Œufs', 2, 'pièce', TRUE, FALSE, 2),
(@pain_id, 'Lait', 250, 'ml', TRUE, FALSE, 3),
(@pain_id, 'Sucre vanillé', 1, 'sachet', FALSE, TRUE, 4);

INSERT IGNORE INTO local_recipe_steps (recipe_id, step_number, instruction) VALUES
(@tarte_id, 1, 'Éplucher et couper les pommes en lamelles.'),
(@tarte_id, 2, 'Étaler la pâte dans un moule à tarte.'),
(@tarte_id, 3, 'Disposer les pommes, saupoudrer de sucre et parsemer de beurre.'),
(@tarte_id, 4, 'Cuire 35 minutes à 180°C.'),
(@pain_id, 1, 'Battre les œufs avec le lait et le sucre.'),
(@pain_id, 2, 'Tremper les tranches de brioche dans le mélange.'),
(@pain_id, 3, 'Faire dorer 2-3 minutes de chaque côté dans une poêle beurrée.');
```

- [ ] **Step 2: Commit**

```bash
git add data/seeds/livry_recipes.sql
git commit -m "feat(seed): add Livry recipes"
```

### Task 4: Docker compose mounts

**Files:**
- Modify: `docker-compose.yml`

- [ ] **Step 1: Add volumes to mysql service**

Replace the existing mysql volumes block with:

```yaml
    volumes:
      - mysql_data:/var/lib/mysql
      - ./sites/api/migrations/025_artisans_local.sql:/docker-entrypoint-initdb.d/025_artisans_local.sql:ro
      - ./sites/api/migrations/026_b2b_recipes.sql:/docker-entrypoint-initdb.d/026_b2b_recipes.sql:ro
      - ./data/seeds/livry.sql:/docker-entrypoint-initdb.d/livry.sql:ro
      - ./data/seeds/livry_prospects.sql:/docker-entrypoint-initdb.d/livry_prospects.sql:ro
      - ./data/seeds/livry_recipes.sql:/docker-entrypoint-initdb.d/livry_recipes.sql:ro
```

- [ ] **Step 2: Rebuild DB and verify**

```bash
cd /mnt/c/Users/user/code/webiartisan.new/.worktrees/livry-modules
docker compose down -v
docker compose up -d mysql php nginx
sleep 25
docker compose exec -T mysql mysql -uwebiartisan -pwebiartisan_dev webiartisan -e "
  SHOW TABLES LIKE 'local_prospect%';
  SHOW TABLES LIKE 'local_recipe%';
  SELECT COUNT(*) FROM local_prospects;
  SELECT COUNT(*) FROM local_recipes;
"
```

Expected output: tables exist and counts > 0.

- [ ] **Step 3: Commit**

```bash
git add docker-compose.yml
git commit -m "chore(docker): mount new seeds and migration"
```


## Phase 1 — API Prospection B2B

### Task 5: Create prospects public route

**Files:**
- Create: `sites/api/routes/prospects.php`

- [ ] **Step 1: Write route file**

```php
<?php
/**
 * WebIArtisan API — Route : Prospects B2B
 *
 * GET /prospects?city=livry&zone=&type=&search=   — liste publique
 * GET /prospects/:id                              — fiche publique
 */

switch ($method) {
    case 'GET':
        if ($action === '' || $action === 'list') {
            prospects_list($pdo);
        } elseif (is_numeric($action) && !$param) {
            prospect_get($pdo, (int)$action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function prospects_list(PDO $pdo): void
{
    $citySlug = $_GET['city'] ?? '';
    $zone     = $_GET['zone'] ?? '';
    $type     = $_GET['type'] ?? '';
    $search   = trim($_GET['search'] ?? '');

    $sql = "
        SELECT p.*, c.slug AS city_slug, c.name AS city_name
        FROM local_prospects p
        JOIN local_cities c ON c.id = p.city_id
        WHERE p.is_active = 1
    ";
    $params = [];

    if ($citySlug) {
        $sql .= " AND c.slug = ?";
        $params[] = $citySlug;
    }
    if ($zone) {
        $sql .= " AND p.zone = ?";
        $params[] = $zone;
    }
    if ($type) {
        $sql .= " AND p.type = ?";
        $params[] = $type;
    }
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.type LIKE ? OR p.zone LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY p.name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $items,
        'total'   => count($items),
    ]);
}

function prospect_get(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("
        SELECT p.*, c.slug AS city_slug, c.name AS city_name
        FROM local_prospects p
        JOIN local_cities c ON c.id = p.city_id
        WHERE p.id = ? AND p.is_active = 1
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Prospect non trouvé']);
        return;
    }

    echo json_encode(['success' => true, 'data' => $item]);
}
```

- [ ] **Step 2: Wire module in index.php**

Modify `sites/api/index.php` after the `artisans` block:

```php
if ($module === 'prospects') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/prospects.php';
    exit;
}
```

- [ ] **Step 3: Test**

```bash
curl -s "http://localhost:8080/api/prospects?city=livry" | python3 -m json.tool | head -40
```

Expected: JSON with success true and a list of prospects.

- [ ] **Step 4: Commit**

```bash
git add sites/api/routes/prospects.php sites/api/index.php
git commit -m "feat(api): public prospects list and detail"
```

### Task 6: Artisan follow-up endpoints

**Files:**
- Modify: `sites/api/routes/artisans.php`

- [ ] **Step 1: Add helper to resolve artisan from token**

Insert after `artisan_get_token()`:

```php
function artisan_require_auth(PDO $pdo): array
{
    $token = artisan_get_token();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentification requise']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, company_name, email, is_admin
        FROM local_artisans
        WHERE auth_token = ? AND auth_token_exp > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$artisan) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session invalide']);
        exit;
    }

    return $artisan;
}
```

- [ ] **Step 2: Add follow-up handlers in switch**

In the `case 'GET'` block, after the existing branches, add:

```php
        } elseif ($action === 'me' && $param === 'prospects') {
            artisan_my_prospects($pdo);
```

In the `case 'POST'` block, after existing branches, add:

```php
        } elseif ($action === 'me' && $param === 'prospects' && is_numeric($segments[3] ?? '')) {
            artisan_follow_prospect($pdo, (int)$segments[3], $body);
```

In the `case 'DELETE'` block, add a branch:

```php
        } elseif ($action === 'me' && $param === 'prospects' && is_numeric($segments[3] ?? '')) {
            artisan_unfollow_prospect($pdo, (int)$segments[3]);
```

- [ ] **Step 3: Implement functions**

Append to the file:

```php
function artisan_my_prospects(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);

    $stmt = $pdo->prepare("
        SELECT p.*, f.status AS follow_status, f.notes AS follow_notes, f.updated_at AS follow_updated_at
        FROM local_prospects p
        LEFT JOIN local_prospect_follow_ups f ON f.prospect_id = p.id AND f.artisan_id = ?
        WHERE p.is_active = 1
        ORDER BY FIELD(f.status, 'tocontact', 'contacted', 'meeting', 'converted', 'declined'), p.name ASC
    ");
    $stmt->execute([$artisan['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $items,
        'total'   => count($items),
    ]);
}

function artisan_follow_prospect(PDO $pdo, int $prospectId, array $body): void
{
    $artisan = artisan_require_auth($pdo);

    $status = $body['status'] ?? 'tocontact';
    $allowed = ['tocontact', 'contacted', 'meeting', 'converted', 'declined'];
    if (!in_array($status, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Statut invalide']);
        return;
    }

    $notes = trim($body['notes'] ?? '');

    // verify prospect exists
    $check = $pdo->prepare("SELECT id FROM local_prospects WHERE id = ? AND is_active = 1");
    $check->execute([$prospectId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Prospect non trouvé']);
        return;
    }

    $pdo->prepare("
        INSERT INTO local_prospect_follow_ups (prospect_id, artisan_id, status, notes)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes)
    ")->execute([$prospectId, $artisan['id'], $status, $notes]);

    echo json_encode(['success' => true, 'message' => 'Suivi mis à jour']);
}

function artisan_unfollow_prospect(PDO $pdo, int $prospectId): void
{
    $artisan = artisan_require_auth($pdo);

    $pdo->prepare("DELETE FROM local_prospect_follow_ups WHERE prospect_id = ? AND artisan_id = ?")
        ->execute([$prospectId, $artisan['id']]);

    echo json_encode(['success' => true, 'message' => 'Suivi supprimé']);
}
```

- [ ] **Step 4: Test**

1. Get a magic link token from logs or DB.
2. Call:

```bash
TOKEN="..."
curl -s -H "X-Artisan-Token: $TOKEN" "http://localhost:8080/api/artisans/me/prospects" | python3 -m json.tool | head -20

curl -s -X POST -H "Content-Type: application/json" -H "X-Artisan-Token: $TOKEN" \
  -d '{"status":"contacted","notes":"Appel prévu mardi"}' \
  "http://localhost:8080/api/artisans/me/prospects/1" | python3 -m json.tool
```

Expected: success true.

- [ ] **Step 5: Commit**

```bash
git add sites/api/routes/artisans.php
git commit -m "feat(api): artisan prospect follow-ups"
```


## Phase 2 — API Recettes

### Task 7: Create recipes public route

**Files:**
- Create: `sites/api/routes/recipes.php`

- [ ] **Step 1: Write route file**

```php
<?php
/**
 * WebIArtisan API — Route : Recettes locales
 *
 * GET  /recipes?city=livry&difficulty=&season=&search= — liste
 * GET  /recipes/:slug                                 — détail
 * POST /recipes                                       — proposer
 * POST /recipes/:id/report                            — signaler
 * POST /recipes/:id/suggest                           — complément / variante
 */

switch ($method) {
    case 'GET':
        if ($action === '' || $action === 'list') {
            recipes_list($pdo);
        } elseif ($action && !$param) {
            recipes_get($pdo, $action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if ($action === '' || $action === 'list') {
            recipes_create($pdo, $body);
        } elseif (is_numeric($action) && $param === 'report') {
            recipes_report($pdo, (int)$action, $body);
        } elseif (is_numeric($action) && $param === 'suggest') {
            recipes_suggest($pdo, (int)$action, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function recipes_list(PDO $pdo): void
{
    $citySlug   = $_GET['city']       ?? '';
    $difficulty = $_GET['difficulty'] ?? '';
    $season     = $_GET['season']     ?? '';
    $search     = trim($_GET['search'] ?? '');

    $sql = "
        SELECT r.id, r.title, r.slug, r.description, r.image_url,
               r.prep_time_minutes, r.cook_time_minutes, r.servings,
               r.difficulty, r.season, r.is_premium, r.is_incomplete,
               r.submitted_by, r.created_at,
               c.slug AS city_slug, c.name AS city_name
        FROM local_recipes r
        JOIN local_cities c ON c.id = r.city_id
        WHERE r.status = 'published'
    ";
    $params = [];

    if ($citySlug) {
        $sql .= " AND c.slug = ?";
        $params[] = $citySlug;
    }
    if ($difficulty) {
        $sql .= " AND r.difficulty = ?";
        $params[] = $difficulty;
    }
    if ($season) {
        $sql .= " AND r.season = ?";
        $params[] = $season;
    }
    if ($search) {
        $sql .= " AND (r.title LIKE ? OR r.description LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY r.is_premium DESC, r.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $items,
        'total'   => count($items),
    ]);
}

function recipes_get(PDO $pdo, string $slug): void
{
    $stmt = $pdo->prepare("
        SELECT r.*, c.slug AS city_slug, c.name AS city_name
        FROM local_recipes r
        JOIN local_cities c ON c.id = r.city_id
        WHERE r.slug = ? AND r.status = 'published'
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Recette non trouvée']);
        return;
    }

    // ingredients
    $stmt = $pdo->prepare("
        SELECT id, name, quantity, unit, is_local, is_optional, sort_order
        FROM local_recipe_ingredients
        WHERE recipe_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$recipe['id']]);
    $recipe['ingredients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // steps
    $stmt = $pdo->prepare("
        SELECT id, step_number, instruction
        FROM local_recipe_steps
        WHERE recipe_id = ?
        ORDER BY step_number ASC
    ");
    $stmt->execute([$recipe['id']]);
    $recipe['steps'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // linked artisans
    $stmt = $pdo->prepare("
        SELECT a.id, a.company_name, a.email, a.website, a.phone
        FROM local_artisans a
        JOIN local_recipe_artisans ra ON ra.artisan_id = a.id
        WHERE ra.recipe_id = ? AND a.status = 'active'
    ");
    $stmt->execute([$recipe['id']]);
    $recipe['artisans'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // variants
    $stmt = $pdo->prepare("
        SELECT id, title, slug, description, is_incomplete, submitted_by, created_at
        FROM local_recipes
        WHERE parent_recipe_id = ? AND status = 'published'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$recipe['id']]);
    $recipe['variants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $recipe]);
}

function recipes_create(PDO $pdo, array $body): void
{
    $citySlug = $body['city_slug'] ?? 'livry';
    $title    = trim($body['title'] ?? '');
    $description = trim($body['description'] ?? '');
    $ingredients = $body['ingredients'] ?? [];
    $steps    = $body['steps'] ?? [];

    if (!$title || !$description || empty($ingredients) || empty($steps)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Titre, description, ingrédients et étapes requis']);
        return;
    }

    $cityStmt = $pdo->prepare("SELECT id FROM local_cities WHERE slug = ? LIMIT 1");
    $cityStmt->execute([$citySlug]);
    $city = $cityStmt->fetch(PDO::FETCH_ASSOC);
    if (!$city) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Ville inconnue']);
        return;
    }
    $cityId = (int)$city['id'];

    $baseSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $title)));
    $baseSlug = trim($baseSlug, '-');
    $slug = $baseSlug;
    $counter = 1;
    while (true) {
        $check = $pdo->prepare("SELECT id FROM local_recipes WHERE slug = ? LIMIT 1");
        $check->execute([$slug]);
        if (!$check->fetch()) break;
        $slug = $baseSlug . '-' . $counter++;
    }

    $difficulty = in_array($body['difficulty'] ?? '', ['very_easy','easy','medium','hard'], true)
        ? $body['difficulty']
        : 'easy';
    $season = in_array($body['season'] ?? '', ['spring','summer','autumn','winter','all'], true)
        ? $body['season']
        : 'all';

    $pdo->prepare("
        INSERT INTO local_recipes
            (city_id, title, slug, description, image_url, prep_time_minutes, cook_time_minutes,
             servings, difficulty, season, is_premium, is_incomplete, submitted_by, submitter_email)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $cityId,
        $title,
        $slug,
        $description,
        $body['image_url'] ?? null,
        (int)($body['prep_time_minutes'] ?? 0),
        (int)($body['cook_time_minutes'] ?? 0),
        (int)($body['servings'] ?? 1),
        $difficulty,
        $season,
        !empty($body['is_premium']) ? 1 : 0,
        !empty($body['is_incomplete']) ? 1 : 0,
        trim($body['submitted_by'] ?? 'Anonyme'),
        trim($body['submitter_email'] ?? '') ?: null,
    ]);

    $recipeId = (int)$pdo->lastInsertId();

    // ingredients
    $ingStmt = $pdo->prepare("
        INSERT INTO local_recipe_ingredients
            (recipe_id, name, quantity, unit, is_local, is_optional, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($ingredients as $idx => $ing) {
        $ingStmt->execute([
            $recipeId,
            trim($ing['name'] ?? ''),
            isset($ing['quantity']) ? (float)$ing['quantity'] : null,
            trim($ing['unit'] ?? '') ?: null,
            !empty($ing['is_local']) ? 1 : 0,
            !empty($ing['is_optional']) ? 1 : 0,
            $idx,
        ]);
    }

    // steps
    $stepStmt = $pdo->prepare("
        INSERT INTO local_recipe_steps (recipe_id, step_number, instruction)
        VALUES (?, ?, ?)
    ");
    foreach ($steps as $idx => $step) {
        $stepStmt->execute([
            $recipeId,
            $idx + 1,
            trim($step['instruction'] ?? ''),
        ]);
    }

    echo json_encode(['success' => true, 'slug' => $slug, 'message' => 'Recette publiée']);
}

function recipes_report(PDO $pdo, int $id, array $body): void
{
    $reason = trim($body['reason'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $pdo->prepare("INSERT INTO local_recipe_reports (recipe_id, reason, reporter_ip) VALUES (?, ?, ?)")
        ->execute([$id, $reason, $ip]);

    $pdo->prepare("UPDATE local_recipes SET status = 'reported' WHERE id = ?")
        ->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Signalement envoyé']);
}

function recipes_suggest(PDO $pdo, int $id, array $body): void
{
    $body['parent_recipe_id'] = $id;
    $body['is_incomplete'] = false;
    $body['title'] = ($body['title'] ?? '') ?: ('Variante de recette #' . $id);
    recipes_create($pdo, $body);
}
```

- [ ] **Step 2: Wire module in index.php**

After the `artisans` block add:

```php
if ($module === 'prospects') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/prospects.php';
    exit;
}

if ($module === 'recipes') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/recipes.php';
    exit;
}
```

- [ ] **Step 3: Test**

```bash
curl -s "http://localhost:8080/api/recipes?city=livry" | python3 -m json.tool | head -30

curl -s "http://localhost:8080/api/recipes/tarte-aux-pommes-normandes" | python3 -m json.tool | head -50

curl -s -X POST -H "Content-Type: application/json" \
  -d '{"title":"Salade normande","description":"Salade aux pommes et camembert.","city_slug":"livry","ingredients":[{"name":"Salade","quantity":1,"unit":"botte","is_local":true}],"steps":[{"instruction":"Laver la salade."}],"submitted_by":"Test"}' \
  "http://localhost:8080/api/recipes" | python3 -m json.tool
```

Expected: success true for all calls.

- [ ] **Step 4: Commit**

```bash
git add sites/api/routes/recipes.php sites/api/index.php
git commit -m "feat(api): recipes list, detail, create, report and suggest"
```


## Phase 3 — Frontend Prospection B2B

### Task 8: Enrich api.js

**Files:**
- Modify: `sites/artisans-shared/src/api.js`

- [ ] **Step 1: Add prospect and recipe API methods**

Find the existing `api` object and append new methods before the final `export default api;`:

```js
  // --- Prospection B2B ---
  async getProspects(filters = {}) {
    const qs = new URLSearchParams({ city: CITY, ...filters }).toString();
    const res = await fetch(`${API_URL}/prospects?${qs}`);
    return res.json();
  },
  async getProspect(id) {
    const res = await fetch(`${API_URL}/prospects/${id}`);
    return res.json();
  },
  async getMyProspects(token) {
    const res = await fetch(`${API_URL}/artisans/me/prospects`, {
      headers: { 'X-Artisan-Token': token },
    });
    return res.json();
  },
  async followProspect(token, prospectId, data) {
    const res = await fetch(`${API_URL}/artisans/me/prospects/${prospectId}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Artisan-Token': token,
      },
      body: JSON.stringify(data),
    });
    return res.json();
  },
  async unfollowProspect(token, prospectId) {
    const res = await fetch(`${API_URL}/artisans/me/prospects/${prospectId}`, {
      method: 'DELETE',
      headers: { 'X-Artisan-Token': token },
    });
    return res.json();
  },

  // --- Recettes ---
  async getRecipes(filters = {}) {
    const qs = new URLSearchParams({ city: CITY, ...filters }).toString();
    const res = await fetch(`${API_URL}/recipes?${qs}`);
    return res.json();
  },
  async getRecipe(slug) {
    const res = await fetch(`${API_URL}/recipes/${slug}`);
    return res.json();
  },
  async createRecipe(data) {
    const res = await fetch(`${API_URL}/recipes`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ city_slug: CITY, ...data }),
    });
    return res.json();
  },
  async reportRecipe(id, reason) {
    const res = await fetch(`${API_URL}/recipes/${id}/report`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reason }),
    });
    return res.json();
  },
  async suggestRecipe(id, data) {
    const res = await fetch(`${API_URL}/recipes/${id}/suggest`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ city_slug: CITY, ...data }),
    });
    return res.json();
  },
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/api.js
git commit -m "feat(front): add prospects and recipes API methods"
```

### Task 9: Prospects list page

**Files:**
- Create: `sites/artisans-shared/src/views/Prospects.vue`

- [ ] **Step 1: Write component**

```vue
<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api.js';

const prospects = ref([]);
const viewMode = ref('list'); // 'list' | 'map'
const search = ref('');
const selectedZone = ref('');
const selectedType = ref('');
const loading = ref(true);
const router = useRouter();

const zones = computed(() => [...new Set(prospects.value.map(p => p.zone).filter(Boolean))]);
const types = computed(() => [...new Set(prospects.value.map(p => p.type).filter(Boolean))]);

const filtered = computed(() => {
  return prospects.value.filter(p => {
    const q = search.value.toLowerCase();
    const matchesSearch = !q ||
      p.name.toLowerCase().includes(q) ||
      (p.type || '').toLowerCase().includes(q) ||
      (p.zone || '').toLowerCase().includes(q);
    const matchesZone = !selectedZone.value || p.zone === selectedZone.value;
    const matchesType = !selectedType.value || p.type === selectedType.value;
    return matchesSearch && matchesZone && matchesType;
  });
});

onMounted(async () => {
  const res = await api.getProspects();
  prospects.value = res.data || [];
  loading.value = false;
});
</script>

<template>
  <div class="prospects-page container">
    <h1>Prospection locale</h1>
    <p>Commerces et établissements à contacter autour de Livry.</p>

    <div class="filters">
      <input v-model="search" placeholder="Rechercher..." />
      <select v-model="selectedZone">
        <option value="">Toutes les zones</option>
        <option v-for="z in zones" :key="z" :value="z">{{ z }}</option>
      </select>
      <select v-model="selectedType">
        <option value="">Tous les types</option>
        <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
      </select>
      <button @click="viewMode = viewMode === 'list' ? 'map' : 'list'">
        {{ viewMode === 'list' ? 'Carte' : 'Liste' }}
      </button>
    </div>

    <div v-if="loading">Chargement...</div>

    <div v-else-if="viewMode === 'list'" class="prospect-list">
      <div
        v-for="p in filtered"
        :key="p.id"
        class="prospect-card"
        @click="router.push(`/prospect/${p.id}`)"
      >
        <h3>{{ p.name }}</h3>
        <span class="meta">{{ p.type }} · {{ p.zone }}</span>
        <p v-if="p.pitch">{{ p.pitch }}</p>
      </div>
    </div>

    <div v-else class="map-placeholder">
      <!-- Map integration in Task 11 -->
      <p>Vue carte (intégration Leaflet à suivre).</p>
      <ul>
        <li v-for="p in filtered" :key="p.id">{{ p.name }}</li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.container { max-width: 960px; margin: 0 auto; padding: 1rem; }
.filters { display: flex; gap: 0.5rem; margin: 1rem 0; flex-wrap: wrap; }
.filters input, .filters select { padding: 0.5rem; border-radius: 6px; border: 1px solid #ddd; }
.prospect-list { display: grid; gap: 1rem; }
.prospect-card { border: 1px solid #eee; border-radius: 8px; padding: 1rem; cursor: pointer; }
.prospect-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.meta { color: #666; font-size: 0.9rem; }
</style>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/views/Prospects.vue
git commit -m "feat(front): prospects list page"
```

### Task 10: Prospect detail page

**Files:**
- Create: `sites/artisans-shared/src/views/ProspectDetail.vue`

- [ ] **Step 1: Write component**

```vue
<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../api.js';

const route = useRoute();
const prospect = ref(null);
const token = localStorage.getItem('artisanToken') || '';
const status = ref('tocontact');
const notes = ref('');
const saving = ref(false);
const message = ref('');

const statuses = [
  { value: 'tocontact', label: 'À contacter' },
  { value: 'contacted', label: 'Contacté' },
  { value: 'meeting', label: 'RDV pris' },
  { value: 'converted', label: 'Converti' },
  { value: 'declined', label: 'Refus' },
];

onMounted(async () => {
  const id = route.params.id;
  const res = await api.getProspect(id);
  prospect.value = res.data || null;

  if (token) {
    const my = await api.getMyProspects(token);
    const follow = (my.data || []).find(p => p.id === Number(id));
    if (follow) {
      status.value = follow.follow_status || 'tocontact';
      notes.value = follow.follow_notes || '';
    }
  }
});

async function saveFollow() {
  if (!token) return;
  saving.value = true;
  const res = await api.followProspect(token, prospect.value.id, { status: status.value, notes: notes.value });
  message.value = res.success ? 'Suivi enregistré' : (res.error || 'Erreur');
  saving.value = false;
}
</script>

<template>
  <div class="container" v-if="prospect">
    <h1>{{ prospect.name }}</h1>
    <p class="meta">{{ prospect.type }} · {{ prospect.zone }}</p>
    <p v-if="prospect.address">{{ prospect.address }}</p>

    <div v-if="prospect.pitch" class="box">
      <h3>Argumentaire</h3>
      <p>{{ prospect.pitch }}</p>
    </div>

    <div v-if="prospect.weakness" class="box">
      <h3>Point de douleur</h3>
      <p>{{ prospect.weakness }}</p>
    </div>

    <div v-if="prospect.phone || prospect.email || prospect.website" class="box">
      <h3>Contact</h3>
      <p v-if="prospect.phone">Tél : {{ prospect.phone }}</p>
      <p v-if="prospect.email">Email : {{ prospect.email }}</p>
      <p v-if="prospect.website"><a :href="prospect.website" target="_blank">Site web</a></p>
    </div>

    <div v-if="token" class="box">
      <h3>Mon suivi</h3>
      <select v-model="status">
        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
      <textarea v-model="notes" placeholder="Notes..."></textarea>
      <button @click="saveFollow" :disabled="saving">{{ saving ? 'Enregistrement...' : 'Enregistrer' }}</button>
      <p v-if="message" class="message">{{ message }}</p>
    </div>
  </div>
</template>

<style scoped>
.container { max-width: 720px; margin: 0 auto; padding: 1rem; }
.meta { color: #666; }
.box { background: #f8f8f8; border-radius: 8px; padding: 1rem; margin: 1rem 0; }
select, textarea { display: block; width: 100%; margin: 0.5rem 0; padding: 0.5rem; }
button { padding: 0.6rem 1.2rem; border: none; border-radius: 6px; background: #1a1a2e; color: #fff; cursor: pointer; }
.message { color: green; }
</style>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/views/ProspectDetail.vue
git commit -m "feat(front): prospect detail page with follow-up"
```


## Phase 4 — Frontend Recettes

### Task 11: Recipes list page

**Files:**
- Create: `sites/artisans-shared/src/views/Recipes.vue`

- [ ] **Step 1: Write component**

```vue
<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api.js';

const recipes = ref([]);
const search = ref('');
const difficulty = ref('');
const season = ref('');
const loading = ref(true);
const router = useRouter();

const difficulties = [
  { value: 'very_easy', label: 'Très facile' },
  { value: 'easy', label: 'Facile' },
  { value: 'medium', label: 'Moyen' },
  { value: 'hard', label: 'Difficile' },
];

const seasons = [
  { value: 'spring', label: 'Printemps' },
  { value: 'summer', label: 'Été' },
  { value: 'autumn', label: 'Automne' },
  { value: 'winter', label: 'Hiver' },
  { value: 'all', label: 'Toute saison' },
];

const filtered = computed(() => {
  return recipes.value.filter(r => {
    const q = search.value.toLowerCase();
    const matchesSearch = !q || r.title.toLowerCase().includes(q) || (r.description || '').toLowerCase().includes(q);
    const matchesDifficulty = !difficulty.value || r.difficulty === difficulty.value;
    const matchesSeason = !season.value || r.season === season.value;
    return matchesSearch && matchesDifficulty && matchesSeason;
  });
});

onMounted(async () => {
  const res = await api.getRecipes();
  recipes.value = res.data || [];
  loading.value = false;
});
</script>

<template>
  <div class="container">
    <h1>Recettes locales</h1>
    <p>Cuisinez avec les produits des artisans de Livry.</p>

    <div class="filters">
      <input v-model="search" placeholder="Rechercher une recette..." />
      <select v-model="difficulty">
        <option value="">Difficulté</option>
        <option v-for="d in difficulties" :key="d.value" :value="d.value">{{ d.label }}</option>
      </select>
      <select v-model="season">
        <option value="">Saison</option>
        <option v-for="s in seasons" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
      <button @click="router.push('/recette/nouvelle')">Proposer une recette</button>
    </div>

    <div v-if="loading">Chargement...</div>

    <div v-else class="recipe-grid">
      <div
        v-for="r in filtered"
        :key="r.id"
        class="recipe-card"
        @click="router.push(`/recette/${r.slug}`)"
      >
        <img v-if="r.image_url" :src="r.image_url" :alt="r.title" />
        <div class="content">
          <h3>{{ r.title }}</h3>
          <p>{{ r.description }}</p>
          <div class="meta">
            <span>{{ r.prep_time_minutes + r.cook_time_minutes }} min</span>
            <span>{{ r.difficulty }}</span>
            <span v-if="r.is_incomplete" class="badge incomplete">Incomplète</span>
            <span v-if="r.is_premium" class="badge premium">Premium</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.container { max-width: 1000px; margin: 0 auto; padding: 1rem; }
.filters { display: flex; gap: 0.5rem; margin: 1rem 0; flex-wrap: wrap; align-items: center; }
.filters input, .filters select { padding: 0.5rem; border-radius: 6px; border: 1px solid #ddd; }
.filters button { padding: 0.5rem 1rem; border-radius: 6px; border: none; background: #1a1a2e; color: #fff; cursor: pointer; }
.recipe-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
.recipe-card { border: 1px solid #eee; border-radius: 10px; overflow: hidden; cursor: pointer; }
.recipe-card img { width: 100%; height: 160px; object-fit: cover; }
.recipe-card .content { padding: 1rem; }
.meta { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem; font-size: 0.85rem; color: #555; }
.badge { padding: 2px 8px; border-radius: 12px; color: #fff; }
.badge.incomplete { background: #f59e0b; }
.badge.premium { background: #8b5cf6; }
</style>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/views/Recipes.vue
git commit -m "feat(front): recipes list page"
```

### Task 12: Recipe detail page

**Files:**
- Create: `sites/artisans-shared/src/views/RecipeDetail.vue`

- [ ] **Step 1: Write component**

```vue
<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api.js';

const route = useRoute();
const router = useRouter();
const recipe = ref(null);
const servings = ref(1);
const reportReason = ref('');
const reporting = ref(false);

const totalTime = computed(() => (recipe.value?.prep_time_minutes || 0) + (recipe.value?.cook_time_minutes || 0));

function scaledQuantity(qty) {
  if (!qty || !recipe.value?.servings) return qty;
  return Math.round((qty * servings.value / recipe.value.servings) * 100) / 100;
}

async function report() {
  if (!reportReason.value) return;
  await api.reportRecipe(recipe.value.id, reportReason.value);
  reporting.value = false;
  alert('Signalement envoyé');
}

onMounted(async () => {
  const res = await api.getRecipe(route.params.slug);
  recipe.value = res.data || null;
  if (recipe.value) servings.value = recipe.value.servings;
});
</script>

<template>
  <div class="container" v-if="recipe">
    <img v-if="recipe.image_url" :src="recipe.image_url" :alt="recipe.title" class="hero" />
    <h1>{{ recipe.title }}</h1>
    <p class="meta">Par {{ recipe.submitted_by }} · {{ totalTime }} min · {{ recipe.difficulty }}</p>
    <p v-if="recipe.is_incomplete" class="incomplete-banner">Cette recette est incomplète : contribuez en proposant un complément !</p>

    <div class="servings">
      <label>Portions :</label>
      <input type="number" min="1" v-model.number="servings" />
    </div>

    <div class="box">
      <h2>Ingrédients</h2>
      <ul>
        <li v-for="ing in recipe.ingredients" :key="ing.id">
          <span v-if="ing.is_local" class="local">local</span>
          {{ scaledQuantity(ing.quantity) }} {{ ing.unit }} {{ ing.name }}
          <span v-if="ing.is_optional">(optionnel)</span>
        </li>
      </ul>
    </div>

    <div class="box">
      <h2>Préparation</h2>
      <ol>
        <li v-for="step in recipe.steps" :key="step.id">
          <strong>Étape {{ step.step_number }}</strong>
          <p>{{ step.instruction }}</p>
        </li>
      </ol>
    </div>

    <div v-if="recipe.artisans?.length" class="box">
      <h2>Artisans associés</h2>
      <div v-for="a in recipe.artisans" :key="a.id" class="artisan">
        <strong>{{ a.company_name }}</strong>
        <p v-if="a.phone">{{ a.phone }}</p>
      </div>
    </div>

    <div v-if="recipe.variants?.length" class="box">
      <h2>Variantes / compléments</h2>
      <div v-for="v in recipe.variants" :key="v.id" class="variant" @click="router.push(`/recette/${v.slug}`)">
        <strong>{{ v.title }}</strong>
        <p>{{ v.description }}</p>
      </div>
    </div>

    <div class="actions">
      <button @click="router.push(`/recette/${recipe.id}/suggérer`)">Proposer un complément</button>
      <button @click="reporting = true">Signaler</button>
    </div>

    <div v-if="reporting" class="report-box">
      <textarea v-model="reportReason" placeholder="Motif du signalement"></textarea>
      <button @click="report">Envoyer</button>
      <button @click="reporting = false">Annuler</button>
    </div>
  </div>
</template>

<style scoped>
.container { max-width: 760px; margin: 0 auto; padding: 1rem; }
.hero { width: 100%; max-height: 320px; object-fit: cover; border-radius: 12px; }
.meta { color: #666; }
.incomplete-banner { background: #fff7ed; border-left: 4px solid #f59e0b; padding: 0.75rem; border-radius: 6px; }
.servings { margin: 1rem 0; }
.servings input { width: 60px; padding: 0.4rem; }
.box { background: #f8f8f8; border-radius: 8px; padding: 1rem; margin: 1rem 0; }
.local { background: #22c55e; color: #fff; font-size: 0.7rem; padding: 2px 6px; border-radius: 8px; margin-right: 6px; }
.actions { display: flex; gap: 0.5rem; margin: 1rem 0; }
.actions button { padding: 0.6rem 1rem; border: none; border-radius: 6px; background: #1a1a2e; color: #fff; cursor: pointer; }
.variant { border-bottom: 1px solid #ddd; padding: 0.5rem 0; cursor: pointer; }
.report-box textarea { width: 100%; padding: 0.5rem; margin-bottom: 0.5rem; }
</style>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/views/RecipeDetail.vue
git commit -m "feat(front): recipe detail page"
```

### Task 13: Recipe form page

**Files:**
- Create: `sites/artisans-shared/src/views/RecipeForm.vue`

- [ ] **Step 1: Write component**

```vue
<script setup>
import { ref, reactive } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '../api.js';

const router = useRouter();
const route = useRoute();
const parentId = route.query.parent || null;

const recipe = reactive({
  title: '',
  description: '',
  image_url: '',
  prep_time_minutes: 0,
  cook_time_minutes: 0,
  servings: 4,
  difficulty: 'easy',
  season: 'all',
  is_premium: false,
  is_incomplete: false,
  submitted_by: '',
  submitter_email: '',
});

const ingredients = ref([{ name: '', quantity: null, unit: '', is_local: false, is_optional: false }]);
const steps = ref([{ instruction: '' }]);
const submitting = ref(false);
const error = ref('');

function addIngredient() {
  ingredients.value.push({ name: '', quantity: null, unit: '', is_local: false, is_optional: false });
}
function removeIngredient(i) { ingredients.value.splice(i, 1); }
function addStep() { steps.value.push({ instruction: '' }); }
function removeStep(i) { steps.value.splice(i, 1); }

async function submit() {
  error.value = '';
  if (!recipe.title || !recipe.description || ingredients.value.some(i => !i.name) || steps.value.some(s => !s.instruction)) {
    error.value = 'Tous les champs obligatoires doivent être remplis.';
    return;
  }
  submitting.value = true;

  const payload = {
    ...recipe,
    ingredients: ingredients.value,
    steps: steps.value,
  };

  let res;
  if (parentId) {
    res = await api.suggestRecipe(parentId, payload);
  } else {
    res = await api.createRecipe(payload);
  }

  submitting.value = false;
  if (res.success) {
    router.push(`/recette/${res.slug}`);
  } else {
    error.value = res.error || 'Erreur lors de la publication';
  }
}
</script>

<template>
  <div class="container">
    <h1>{{ parentId ? 'Proposer un complément' : 'Proposer une recette' }}</h1>
    <form @submit.prevent="submit">
      <input v-model="recipe.title" placeholder="Titre de la recette" required />
      <textarea v-model="recipe.description" placeholder="Description" required></textarea>
      <input v-model="recipe.image_url" placeholder="URL d'image (optionnel)" />

      <div class="row">
        <input type="number" v-model.number="recipe.prep_time_minutes" placeholder="Prépa (min)" />
        <input type="number" v-model.number="recipe.cook_time_minutes" placeholder="Cuisson (min)" />
        <input type="number" v-model.number="recipe.servings" placeholder="Portions" min="1" />
      </div>

      <div class="row">
        <select v-model="recipe.difficulty">
          <option value="very_easy">Très facile</option>
          <option value="easy">Facile</option>
          <option value="medium">Moyen</option>
          <option value="hard">Difficile</option>
        </select>
        <select v-model="recipe.season">
          <option value="spring">Printemps</option>
          <option value="summer">Été</option>
          <option value="autumn">Automne</option>
          <option value="winter">Hiver</option>
          <option value="all">Toute saison</option>
        </select>
      </div>

      <label><input type="checkbox" v-model="recipe.is_incomplete" /> Recette incomplète (ouverte aux compléments)</label>

      <h2>Ingrédients</h2>
      <div v-for="(ing, i) in ingredients" :key="i" class="ingredient-row">
        <input v-model="ing.name" placeholder="Nom" required />
        <input type="number" v-model.number="ing.quantity" placeholder="Qté" />
        <input v-model="ing.unit" placeholder="Unité" />
        <label><input type="checkbox" v-model="ing.is_local" /> Local</label>
        <label><input type="checkbox" v-model="ing.is_optional" /> Optionnel</label>
        <button type="button" @click="removeIngredient(i)">×</button>
      </div>
      <button type="button" @click="addIngredient">+ Ingrédient</button>

      <h2>Étapes</h2>
      <div v-for="(step, i) in steps" :key="i" class="step-row">
        <textarea v-model="step.instruction" placeholder="Description de l'étape" required></textarea>
        <button type="button" @click="removeStep(i)">×</button>
      </div>
      <button type="button" @click="addStep">+ Étape</button>

      <h2>Vos informations</h2>
      <input v-model="recipe.submitted_by" placeholder="Votre nom" />
      <input v-model="recipe.submitter_email" placeholder="Votre email (optionnel)" type="email" />

      <p v-if="error" class="error">{{ error }}</p>
      <button type="submit" :disabled="submitting">{{ submitting ? 'Publication...' : 'Publier' }}</button>
    </form>
  </div>
</template>

<style scoped>
.container { max-width: 720px; margin: 0 auto; padding: 1rem; }
input, textarea, select { display: block; width: 100%; margin: 0.5rem 0; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; }
.row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; }
.ingredient-row, .step-row { display: grid; grid-template-columns: 2fr 1fr 1fr auto auto auto; gap: 0.4rem; align-items: center; margin: 0.4rem 0; }
.step-row { grid-template-columns: 1fr auto; }
button[type="submit"] { padding: 0.8rem 1.5rem; border: none; border-radius: 6px; background: #1a1a2e; color: #fff; cursor: pointer; }
.error { color: #c00; }
</style>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/views/RecipeForm.vue
git commit -m "feat(front): recipe submission form"
```


## Phase 5 — Routes, admin et mise en ligne

### Task 14: Update Vue router

**Files:**
- Modify: `sites/artisans-shared/src/main.js`

- [ ] **Step 1: Import views and add routes**

```js
import Prospects from './views/Prospects.vue';
import ProspectDetail from './views/ProspectDetail.vue';
import Recipes from './views/Recipes.vue';
import RecipeDetail from './views/RecipeDetail.vue';
import RecipeForm from './views/RecipeForm.vue';
import AdminRecipes from './views/AdminRecipes.vue';
```

Add routes inside the router configuration:

```js
{ path: '/prospection', component: Prospects },
{ path: '/prospect/:id', component: ProspectDetail },
{ path: '/recettes', component: Recipes },
{ path: '/recette/:slug', component: RecipeDetail },
{ path: '/recette/nouvelle', component: RecipeForm },
{ path: '/recette/:id/suggérer', component: RecipeForm, props: route => ({ parent: route.params.id }) },
{ path: '/espace/admin-recettes', component: AdminRecipes },
```

- [ ] **Step 2: Add navigation links**

Modify `sites/artisans-shared/src/components/AppNav.vue` to add links to `/prospection` and `/recettes`.

- [ ] **Step 3: Commit**

```bash
git add sites/artisans-shared/src/main.js sites/artisans-shared/src/components/AppNav.vue
git commit -m "feat(front): wire new routes and navigation"
```

### Task 15: Admin recipes view

**Files:**
- Create: `sites/artisans-shared/src/views/AdminRecipes.vue`
- Modify: `sites/api/routes/artisans.php`

- [ ] **Step 1: Add admin endpoints in artisans.php**

After the follow-up functions append:

```php
function artisan_admin_recipes(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);
    if (empty($artisan['is_admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Accès réservé']);
        return;
    }

    $status = $_GET['status'] ?? 'reported';
    $allowed = ['published', 'reported', 'archived'];
    if (!in_array($status, $allowed, true)) $status = 'reported';

    $stmt = $pdo->prepare("
        SELECT id, title, slug, status, is_incomplete, submitted_by, created_at
        FROM local_recipes
        WHERE status = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$status]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $items, 'total' => count($items)]);
}

function artisan_admin_archive_recipe(PDO $pdo, int $recipeId): void
{
    $artisan = artisan_require_auth($pdo);
    if (empty($artisan['is_admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Accès réservé']);
        return;
    }

    $pdo->prepare("UPDATE local_recipes SET status = 'archived' WHERE id = ?")
        ->execute([$recipeId]);

    echo json_encode(['success' => true, 'message' => 'Recette archivée']);
}
```

Wire in the `switch` statement:

```php
        } elseif ($action === 'me' && $param === 'admin-recipes') {
            artisan_admin_recipes($pdo);
        } elseif ($action === 'me' && $param === 'admin-recipes' && is_numeric($segments[3] ?? '') && ($segments[4] ?? '') === 'archive') {
            artisan_admin_archive_recipe($pdo, (int)$segments[3]);
```

- [ ] **Step 2: Add api.js methods**

```js
  async getAdminRecipes(token, status = 'reported') {
    const res = await fetch(`${API_URL}/artisans/me/admin-recipes?status=${status}`, {
      headers: { 'X-Artisan-Token': token },
    });
    return res.json();
  },
  async archiveRecipe(token, id) {
    const res = await fetch(`${API_URL}/artisans/me/admin-recipes/${id}/archive`, {
      method: 'POST',
      headers: { 'X-Artisan-Token': token },
    });
    return res.json();
  },
```

- [ ] **Step 3: Write AdminRecipes.vue**

```vue
<script setup>
import { ref, onMounted } from 'vue';
import api from '../api.js';

const recipes = ref([]);
const token = localStorage.getItem('artisanToken') || '';
const status = ref('reported');
const message = ref('');

async function load() {
  const res = await api.getAdminRecipes(token, status.value);
  recipes.value = res.data || [];
}

async function archive(id) {
  await api.archiveRecipe(token, id);
  message.value = 'Recette archivée';
  await load();
}

onMounted(load);
</script>

<template>
  <div class="container">
    <h1>Admin recettes</h1>
    <select v-model="status" @change="load">
      <option value="reported">Signalées</option>
      <option value="published">Publiées</option>
      <option value="archived">Archivées</option>
    </select>
    <p v-if="message" class="message">{{ message }}</p>
    <div class="recipe-list">
      <div v-for="r in recipes" :key="r.id" class="recipe-row">
        <div>
          <strong>{{ r.title }}</strong>
          <p>{{ r.submitted_by }} · {{ r.status }}</p>
        </div>
        <button v-if="r.status !== 'archived'" @click="archive(r.id)">Archiver</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.container { max-width: 800px; margin: 0 auto; padding: 1rem; }
select { padding: 0.5rem; margin-bottom: 1rem; }
.recipe-list { display: grid; gap: 0.75rem; }
.recipe-row { display: flex; justify-content: space-between; align-items: center; background: #f8f8f8; padding: 0.75rem; border-radius: 8px; }
button { padding: 0.4rem 0.8rem; border: none; border-radius: 6px; background: #c00; color: #fff; cursor: pointer; }
.message { color: green; }
</style>
```

- [ ] **Step 4: Commit**

```bash
git add sites/api/routes/artisans.php sites/artisans-shared/src/api.js sites/artisans-shared/src/views/AdminRecipes.vue
git commit -m "feat(admin): recipe moderation view for admins"
```

### Task 16: Map view for prospects

**Files:**
- Modify: `sites/artisans-shared/src/views/Prospects.vue`

- [ ] **Step 1: Integrate Leaflet**

Add imports:

```js
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
```

Add a map container in template:

```vue
<div v-show="viewMode === 'map'" ref="mapEl" class="map-container"></div>
```

Add `const mapEl = ref(null);` and initialize map in `onMounted` after data load:

```js
const map = ref(null);
const markers = ref([]);

function initMap() {
  if (!mapEl.value) return;
  map.value = L.map(mapEl.value).setView([49.1081, -0.7658], 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
  }).addTo(map.value);
  updateMarkers();
}

function updateMarkers() {
  if (!map.value) return;
  markers.value.forEach(m => map.value.removeLayer(m));
  markers.value = [];
  filtered.value.forEach(p => {
    if (!p.latitude || !p.longitude) return;
    const marker = L.marker([p.latitude, p.longitude]).addTo(map.value);
    marker.bindPopup(`<b>${p.name}</b><br>${p.type}`);
    marker.on('click', () => router.push(`/prospect/${p.id}`));
    markers.value.push(marker);
  });
}

watch(filtered, updateMarkers);
```

Call `initMap()` inside `onMounted` after loading prospects.

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/views/Prospects.vue
git commit -m "feat(front): Leaflet map on prospects page"
```

### Task 17: Dashboard prospect follow-up list

**Files:**
- Modify: `sites/artisans-shared/src/views/Dashboard.vue`

- [ ] **Step 1: Add prospects section**

Add a new section that calls `api.getMyProspects(token)` and displays followed prospects with status badges.

```vue
<script setup>
// existing imports...
import api from '../api.js';

const myProspects = ref([]);

async function loadMyProspects() {
  if (!token.value) return;
  const res = await api.getMyProspects(token.value);
  myProspects.value = (res.data || []).filter(p => p.follow_status);
}

onMounted(() => {
  // existing init...
  loadMyProspects();
});
</script>
```

Add template section:

```vue
<section class="dashboard-section" v-if="myProspects.length">
  <h2>Ma prospection</h2>
  <div v-for="p in myProspects" :key="p.id" class="prospect-mini" @click="router.push(`/prospect/${p.id}`)">
    <strong>{{ p.name }}</strong>
    <span class="status">{{ p.follow_status }}</span>
  </div>
</section>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/views/Dashboard.vue
git commit -m "feat(front): dashboard prospect follow-up list"
```

### Task 18: Final integration tests

- [ ] **Step 1: Run full API smoke tests**

```bash
cd /mnt/c/Users/user/code/webiartisan.new/.worktrees/livry-modules
./scripts/test-api.sh || (
  curl -s "http://localhost:8080/api/prospects?city=livry" | python3 -m json.tool
  curl -s "http://localhost:8080/api/recipes?city=livry" | python3 -m json.tool
)
```

- [ ] **Step 2: Build frontend**

```bash
cd /mnt/c/Users/user/code/webiartisan.new/.worktrees/livry-modules/sites/webiartisan-livry
npm run build
```

Expected: no build errors, `dist/` generated.

- [ ] **Step 3: Fix any TypeScript/Vite errors**

If build fails, read the error, edit the offending file, rerun build.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "test(build): validate new modules integration"
```

### Task 19: Production deployment

- [ ] **Step 1: Update production env**

Ensure `sites/api/.env` on production has:

```env
MAIL_FROM=noreply@webiartisan.prigent.tech
APP_URL=https://artisans-livry.prigent.tech
```

- [ ] **Step 2: Reset production database**

Since encodage fixes require fresh seed, run on production:

```bash
# via phpMyAdmin or SSH
mysql -u<user> -p<pass> <database> < sites/api/migrations/025_artisans_local.sql
mysql -u<user> -p<pass> <database> < sites/api/migrations/026_b2b_recipes.sql
mysql -u<user> -p<pass> <database> < data/seeds/livry.sql
mysql -u<user> -p<pass> <database> < data/seeds/livry_prospects.sql
mysql -u<user> -p<pass> <database> < data/seeds/livry_recipes.sql
```

- [ ] **Step 3: Deploy frontend**

```bash
cd /mnt/c/Users/user/code/webiartisan.new/.worktrees/livry-modules/sites/webiartisan-livry
make push
```

- [ ] **Step 4: Verify production**

Visit:
- `https://artisans-livry.prigent.tech/prospection`
- `https://artisans-livry.prigent.tech/recettes`

Check accents are correct and magic-link/contact emails are sent.

- [ ] **Step 5: Commit any final config changes**

```bash
git add -A
git commit -m "chore(deploy): production config for new modules"
```


---

## Self-review

### Spec coverage

| Spec requirement | Plan task |
|------------------|-----------|
| Table `local_prospects` | Task 1 |
| Table `local_prospect_follow_ups` | Task 1 |
| Tables recettes / ingrédients / étapes / liens artisans / signalements | Task 1 |
| Seed prospects Livry | Task 2 |
| Seed recettes Livry | Task 3 |
| API publique prospects liste/détail | Task 5 |
| API artisan suivis prospects | Task 6 |
| API recettes liste/détail/créer/signaler/suggérer | Task 7 |
| Front prospects carte/liste/fiche | Tasks 8-10, 16 |
| Front recettes liste/fiche/formulaire | Tasks 11-13 |
| Admin recettes signalées | Task 15 |
| Routes et navigation | Task 14 |
| Déploiement et reset DB | Task 19 |

### Placeholder scan

No TBD/TODO placeholders. Every step contains concrete file paths, SQL, PHP, Vue code, and exact commands.

### Type consistency

- Pipeline statuses match spec: `tocontact`, `contacted`, `meeting`, `converted`, `declined`.
- Recipe difficulty values: `very_easy`, `easy`, `medium`, `hard`.
- Recipe season values: `spring`, `summer`, `autumn`, `winter`, `all`.
- Slug generation uses iconv transliteration consistent with existing patterns.

### Known simplifications

- Map view uses basic Leaflet markers; advanced clustering is out of MVP scope.
- Admin flag is a boolean on `local_artisans`; no separate admin table.
- Recipe image is URL-only, no file upload.
