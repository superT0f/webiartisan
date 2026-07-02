# Mini-Games Hub Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the standalone spin wheel with a generic mini-games hub supporting coupon, poll, vote/battle (free) and wheel/quiz/bingo/rebus (premium), with per-instance configuration and freemium limits.

**Architecture:** Add game type and instance tables, expose generic game engine endpoints under `/api/games`, build a dynamic `GameRenderer` Vue component that switches engines by `game_type`, and create city + artisan hub pages. Keep existing spin tables untouched for now; premium wheel uses the new engine with a migrated config later.

**Tech Stack:** PHP 8.4 (custom front-controller), MySQL 8, Vue 3 + Vite, PDO.

---

## File Structure

| File | Responsibility |
|------|----------------|
| `sites/api/migrations/030_mini_games.sql` | Tables for game types, instances, plays, rewards |
| `sites/api/routes/games.php` | Public and authenticated game endpoints |
| `sites/api/lib/Games.php` | Shared helpers: limits, engine dispatch, reward resolution |
| `sites/artisans-shared/src/api.js` | API client functions for games |
| `sites/artisans-shared/src/views/GamesHub.vue` | City mini-games hub |
| `sites/artisans-shared/src/views/GamePlay.vue` | Play a specific game instance |
| `sites/artisans-shared/src/components/GameCard.vue` | Game instance card |
| `sites/artisans-shared/src/components/GameCardGrid.vue` | Grid of game cards |
| `sites/artisans-shared/src/components/GameRenderer.vue` | Dynamic engine switcher |
| `sites/artisans-shared/src/components/games/CouponGame.vue` | Coupon reveal engine |
| `sites/artisans-shared/src/components/games/PollGame.vue` | Poll engine |
| `sites/artisans-shared/src/components/games/VoteBattleGame.vue` | Vote / battle engine |
| `sites/artisans-shared/src/components/FreemiumLimitBanner.vue` | Premium upgrade CTA |
| `sites/artisans-shared/src/views/artisan/GamesConfig.vue` | Artisan game management |
| `scripts/test-api.sh` | Smoke tests for game endpoints |

---

### Task 1: Create mini-games database migration

**Files:**
- Create: `sites/api/migrations/030_mini_games.sql`

- [ ] **Step 1: Write migration SQL**

```sql
-- ============================================================
-- WebiArtisan — Migration 030 : Hub de mini-jeux
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS local_game_types (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    `key`           VARCHAR(50) UNIQUE NOT NULL COMMENT 'coupon, poll, vote, wheel, quiz, bingo, rebus',
    label_fr        VARCHAR(100) NOT NULL,
    description     TEXT,
    is_premium      BOOLEAN NOT NULL DEFAULT FALSE,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    default_config  JSON NOT NULL,
    engine_component VARCHAR(50) NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (`key`),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_game_instances (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    game_type_id        INT NOT NULL,
    artisan_id          INT NOT NULL,
    city_id             INT NOT NULL,
    title               VARCHAR(150) NOT NULL,
    description         TEXT,
    config              JSON NOT NULL,
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    starts_at           TIMESTAMP NULL,
    ends_at             TIMESTAMP NULL,
    max_plays_per_user  INT NOT NULL DEFAULT 1,
    play_cooldown_hours INT NOT NULL DEFAULT 24,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (game_type_id) REFERENCES local_game_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (artisan_id)  REFERENCES local_artisans(id)  ON DELETE CASCADE,
    FOREIGN KEY (city_id)     REFERENCES local_cities(id)    ON DELETE CASCADE,
    INDEX idx_city_active (city_id, is_active),
    INDEX idx_artisan (artisan_id),
    INDEX idx_type (game_type_id),
    INDEX idx_dates (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_game_rewards (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    game_instance_id INT NOT NULL,
    label           VARCHAR(150) NOT NULL,
    reward_type     ENUM('coupon','points','badge','nothing') NOT NULL DEFAULT 'nothing',
    reward_value    JSON NULL,
    probability     DECIMAL(5,4) NULL COMMENT 'for probabilistic games',
    stock           INT NULL,
    claimed_count   INT NOT NULL DEFAULT 0,
    FOREIGN KEY (game_instance_id) REFERENCES local_game_instances(id) ON DELETE CASCADE,
    INDEX idx_instance (game_instance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_game_plays (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    game_instance_id INT NOT NULL,
    user_id         INT NOT NULL,
    result          JSON NULL,
    xp_awarded      INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_instance_id) REFERENCES local_game_instances(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    INDEX idx_instance_user (game_instance_id, user_id),
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed game types
INSERT INTO local_game_types (`key`, label_fr, description, is_premium, default_config, engine_component) VALUES
('coupon', 'Coupon de réduction', 'Révéler un coupon ou une offre.', FALSE, '{"reveal_text":"Découvrez votre offre !"}', 'CouponGame'),
('poll', 'Sondage', 'Répondre à une question.', FALSE, '{"question":"Votre avis nous intéresse","options":["Oui","Non"]}', 'PollGame'),
('vote', 'Vote / Battle', 'Voter pour votre préféré.', FALSE, '{"question":"Lequel préférez-vous ?","options":["Option A","Option B"]}', 'VoteBattleGame'),
('wheel', 'Roue de la chance', 'Tourner la roue pour gagner.', TRUE, '{"segments":[]}', 'WheelGame'),
('quiz', 'Quiz', 'Répondre à des questions.', TRUE, '{"questions":[]}', 'QuizGame'),
('bingo', 'Bingo', 'Carte de bingo locale.', TRUE, '{"grid_size":3}', 'BingoGame'),
('rebus', 'Rébus', 'Résoudre un rébus.', TRUE, '{"puzzle":""}', 'RebusGame')
ON DUPLICATE KEY UPDATE label_fr = VALUES(label_fr), is_premium = VALUES(is_premium);
```

- [ ] **Step 2: Run migration**

```bash
cd /mnt/c/Users/user/code/webiartisan.new
docker compose exec -T db mysql -u root -p$DB_ROOT_PASSWORD webiartisan < sites/api/migrations/030_mini_games.sql
```

- [ ] **Step 3: Verify tables**

```bash
docker compose exec db mysql -u root -p$DB_ROOT_PASSWORD webiartisan -e "SHOW TABLES LIKE 'local_game_%';"
```
Expected: 4 tables listed.

- [ ] **Step 4: Commit**

```bash
git add sites/api/migrations/030_mini_games.sql
git commit -m "feat(db): add mini-games hub tables"
```

---

### Task 2: Add shared Games helpers

**Files:**
- Create: `sites/api/lib/Games.php`

- [ ] **Step 1: Write helper library**

```php
<?php
/**
 * WebiArtisan — Mini-games helpers
 */

require_once __DIR__ . '/UserAuth.php';

function games_can_artisan_create(PDO $pdo, int $artisanId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM local_game_instances
        WHERE artisan_id = ? AND is_active = 1
    ");
    $stmt->execute([$artisanId]);
    return (int)$stmt->fetchColumn() < 2;
}

function games_count_user_plays(PDO $pdo, int $instanceId, int $userId): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM local_game_plays
        WHERE game_instance_id = ? AND user_id = ?
    ");
    $stmt->execute([$instanceId, $userId]);
    return (int)$stmt->fetchColumn();
}

function games_last_play_at(PDO $pdo, int $instanceId, int $userId): ?string
{
    $stmt = $pdo->prepare("
        SELECT created_at FROM local_game_plays
        WHERE game_instance_id = ? AND user_id = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$instanceId, $userId]);
    $val = $stmt->fetchColumn();
    return $val ? (string)$val : null;
}

function games_resolve_reward(PDO $pdo, int $instanceId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, label, reward_type, reward_value, probability, stock, claimed_count
        FROM local_game_rewards
        WHERE game_instance_id = ? AND (stock IS NULL OR stock > claimed_count)
        ORDER BY id ASC
    ");
    $stmt->execute([$instanceId]);
    $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rewards)) return null;

    // For now deterministic: pick first available coupon-like reward.
    // Probabilistic selection can be added later for wheel.
    foreach ($rewards as $r) {
        if ($r['reward_type'] === 'coupon') {
            return $r;
        }
    }
    return $rewards[0];
}

function games_record_play(PDO $pdo, int $instanceId, int $userId, array $result, int $xp = 0): void
{
    $pdo->prepare("
        INSERT INTO local_game_plays (game_instance_id, user_id, result, xp_awarded)
        VALUES (?, ?, ?, ?)
    ")->execute([$instanceId, $userId, json_encode($result, JSON_THROW_ON_ERROR), $xp]);
}

function games_instance_is_playable(array $instance): bool
{
    if (!$instance['is_active']) return false;
    $now = time();
    if ($instance['starts_at'] && strtotime($instance['starts_at']) > $now) return false;
    if ($instance['ends_at'] && strtotime($instance['ends_at']) < $now) return false;
    return true;
}
```

- [ ] **Step 2: Commit**

```bash
git add sites/api/lib/Games.php
git commit -m "feat(api): add mini-games helper functions"
```

---

### Task 3: Create `/api/games` route

**Files:**
- Create: `sites/api/routes/games.php`
- Modify: `sites/api/index.php`

- [ ] **Step 1: Write games route**

```php
<?php
/**
 * WebiArtisan API — Route : Mini-jeux
 *
 * GET  /games/types
 * GET  /games?city=livry&artisan_id=...
 * GET  /games/:id
 * POST /games/:id/play
 * POST /games/:id/claim
 */

require_once __DIR__ . '/../lib/Games.php';

switch ($method) {
    case 'GET':
        if ($action === 'types') {
            games_types_list($pdo);
        } elseif ($action === '' || $action === 'list') {
            games_list($pdo);
        } elseif (is_numeric($action) && !$param) {
            games_get($pdo, (int)$action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (is_numeric($action) && $param === 'play') {
            games_play($pdo, (int)$action, $body);
        } elseif (is_numeric($action) && $param === 'claim') {
            games_claim($pdo, (int)$action, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function games_types_list(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        SELECT id, `key`, label_fr, description, is_premium, is_active, default_config, engine_component
        FROM local_game_types
        WHERE is_active = 1
        ORDER BY is_premium ASC, label_fr ASC
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['is_premium'] = (bool)$item['is_premium'];
        $item['is_active'] = (bool)$item['is_active'];
        $item['default_config'] = json_decode($item['default_config'], true);
    }
    echo json_encode(['success' => true, 'data' => $items]);
}

function games_list(PDO $pdo): void
{
    $citySlug = $_GET['city'] ?? null;
    $artisanId = isset($_GET['artisan_id']) ? (int)$_GET['artisan_id'] : null;

    $sql = "
        SELECT i.*, gt.`key` AS game_type_key, gt.label_fr AS game_type_label,
               gt.is_premium, gt.engine_component,
               a.company_name AS artisan_name, c.slug AS city_slug
        FROM local_game_instances i
        JOIN local_game_types gt ON gt.id = i.game_type_id
        JOIN local_artisans a ON a.id = i.artisan_id
        JOIN local_cities c ON c.id = i.city_id
        WHERE i.is_active = 1 AND a.status = 'active'
          AND (i.starts_at IS NULL OR i.starts_at <= NOW())
          AND (i.ends_at IS NULL OR i.ends_at >= NOW())
    ";
    $params = [];

    if ($citySlug) {
        $sql .= " AND c.slug = ?";
        $params[] = $citySlug;
    }
    if ($artisanId) {
        $sql .= " AND i.artisan_id = ?";
        $params[] = $artisanId;
    }

    $sql .= " ORDER BY gt.is_premium ASC, i.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['is_premium'] = (bool)$item['is_premium'];
        $item['config'] = json_decode($item['config'], true);
        unset($item['game_type_id']);
    }

    echo json_encode(['success' => true, 'data' => $items]);
}

function games_get(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("
        SELECT i.*, gt.`key` AS game_type_key, gt.label_fr AS game_type_label,
               gt.is_premium, gt.engine_component,
               a.company_name AS artisan_name, c.slug AS city_slug
        FROM local_game_instances i
        JOIN local_game_types gt ON gt.id = i.game_type_id
        JOIN local_artisans a ON a.id = i.artisan_id
        JOIN local_cities c ON c.id = i.city_id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Jeu non trouvé']);
        return;
    }

    $item['id'] = (int)$item['id'];
    $item['is_premium'] = (bool)$item['is_premium'];
    $item['config'] = json_decode($item['config'], true);
    $item['is_playable'] = games_instance_is_playable($item);

    // User play state
    $userId = null;
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    if ($token) {
        $usr = $pdo->prepare("SELECT id FROM local_users WHERE session_token = ? AND session_exp > NOW() LIMIT 1");
        $usr->execute([$token]);
        $userId = $usr->fetchColumn();
    }

    $item['user_plays_count'] = $userId ? games_count_user_plays($pdo, $id, (int)$userId) : 0;
    $item['can_play'] = $item['is_playable'] && ($item['max_plays_per_user'] === 0 || $item['user_plays_count'] < $item['max_plays_per_user']);

    echo json_encode(['success' => true, 'data' => $item]);
}

function games_play(PDO $pdo, int $id, array $body): void
{
    $user = user_require_auth($pdo);

    $stmt = $pdo->prepare("
        SELECT i.*, gt.`key` AS game_type_key, gt.is_premium
        FROM local_game_instances i
        JOIN local_game_types gt ON gt.id = i.game_type_id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $instance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$instance) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Jeu non trouvé']);
        return;
    }

    if ((bool)$instance['is_premium']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Ce jeu est réservé aux artisans premium']);
        return;
    }

    if (!games_instance_is_playable($instance)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ce jeu n\'est pas actif']);
        return;
    }

    $plays = games_count_user_plays($pdo, $id, (int)$user['id']);
    if ($instance['max_plays_per_user'] > 0 && $plays >= $instance['max_plays_per_user']) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Limite de participations atteinte']);
        return;
    }

    $lastPlay = games_last_play_at($pdo, $id, (int)$user['id']);
    if ($lastPlay && $instance['play_cooldown_hours'] > 0) {
        $next = (new DateTimeImmutable($lastPlay))->modify("+{$instance['play_cooldown_hours']} hours");
        if (new DateTimeImmutable() < $next) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Veuillez attendre avant de rejouer']);
            return;
        }
    }

    $result = match ($instance['game_type_key']) {
        'coupon' => ['reward' => games_resolve_reward($pdo, $id)],
        'poll' => ['choice' => $body['choice'] ?? null],
        'vote' => ['choice' => $body['choice'] ?? null],
        default => [],
    };

    games_record_play($pdo, $id, (int)$user['id'], $result, 10);

    echo json_encode(['success' => true, 'data' => $result]);
}

function games_claim(PDO $pdo, int $id, array $body): void
{
    user_require_auth($pdo);
    // Claim logic: update reward stock and return coupon code.
    // Minimal implementation: return the reward label from the last play.
    http_response_code(501);
    echo json_encode(['success' => false, 'error' => 'Non implémenté'});
}
```

- [ ] **Step 2: Register route**

In `sites/api/index.php`:
```php
case 'games':
    require_once __DIR__ . '/routes/games.php';
    break;
```

- [ ] **Step 3: Test**

```bash
curl -s "http://localhost:8080/api/games/types" | jq .
curl -s "http://localhost:8080/api/games?city=livry" | jq .
```
Expected: JSON lists.

- [ ] **Step 4: Commit**

```bash
git add sites/api/routes/games.php sites/api/index.php
git commit -m "feat(api): add games hub endpoints"
```

---

### Task 4: Add artisan game management endpoints

**Files:**
- Modify: `sites/api/routes/artisans.php`

- [ ] **Step 1: Add route handlers**

Add functions:

```php
function artisan_games_list(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);
    $stmt = $pdo->prepare("
        SELECT i.*, gt.`key` AS game_type_key, gt.label_fr AS game_type_label, gt.is_premium
        FROM local_game_instances i
        JOIN local_game_types gt ON gt.id = i.game_type_id
        WHERE i.artisan_id = ?
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$artisan['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['is_premium'] = (bool)$item['is_premium'];
        $item['config'] = json_decode($item['config'], true);
    }
    echo json_encode(['success' => true, 'data' => $items]);
}

function artisan_create_game(PDO $pdo, array $body): void
{
    $artisan = artisan_require_auth($pdo);
    $artisanId = (int)$artisan['id'];

    $gameTypeKey = $body['game_type_key'] ?? '';
    $title = trim($body['title'] ?? '');
    $description = trim($body['description'] ?? '');
    $config = $body['config'] ?? [];

    if (!$gameTypeKey || !$title) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Type de jeu et titre requis']);
        return;
    }

    $typeStmt = $pdo->prepare("SELECT id, is_premium FROM local_game_types WHERE `key` = ? AND is_active = 1");
    $typeStmt->execute([$gameTypeKey]);
    $type = $typeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Type de jeu inconnu']);
        return;
    }

    if ((bool)$type['is_premium']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Type de jeu premium']);
        return;
    }

    if (!games_can_artisan_create($pdo, $artisanId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Limite de 2 jeux actifs atteinte']);
        return;
    }

    $cityStmt = $pdo->prepare("SELECT city_id FROM local_artisans WHERE id = ?");
    $cityStmt->execute([$artisanId]);
    $cityId = (int)$cityStmt->fetchColumn();

    $pdo->prepare("
        INSERT INTO local_game_instances
            (game_type_id, artisan_id, city_id, title, description, config)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([
        $type['id'], $artisanId, $cityId, $title, $description,
        json_encode($config, JSON_THROW_ON_ERROR),
    ]);

    echo json_encode(['success' => true, 'data' => ['id' => (int)$pdo->lastInsertId()]]);
}

function artisan_update_game(PDO $pdo, int $gameId, array $body): void
{
    $artisan = artisan_require_auth($pdo);
    $allowed = ['title', 'description', 'config', 'is_active', 'starts_at', 'ends_at', 'max_plays_per_user', 'play_cooldown_hours'];
    $sets = [];
    $params = [];
    foreach ($allowed as $col) {
        if (array_key_exists($col, $body)) {
            $sets[] = "$col = ?";
            $params[] = is_array($body[$col]) ? json_encode($body[$col], JSON_THROW_ON_ERROR) : $body[$col];
        }
    }
    if (empty($sets)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucune donnée à mettre à jour']);
        return;
    }
    $params[] = $gameId;
    $params[] = $artisan['id'];

    $stmt = $pdo->prepare("UPDATE local_game_instances SET " . implode(', ', $sets) . " WHERE id = ? AND artisan_id = ?");
    $stmt->execute($params);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Jeu non trouvé']);
        return;
    }
    echo json_encode(['success' => true, 'message' => 'Jeu mis à jour']);
}

function artisan_delete_game(PDO $pdo, int $gameId): void
{
    $artisan = artisan_require_auth($pdo);
    $stmt = $pdo->prepare("DELETE FROM local_game_instances WHERE id = ? AND artisan_id = ?");
    $stmt->execute([$gameId, $artisan['id']]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Jeu non trouvé']);
        return;
    }
    echo json_encode(['success' => true, 'message' => 'Jeu supprimé']);
}
```

- [ ] **Step 2: Wire endpoints**

In `case 'GET'` block add:
```php
} elseif ($action === 'me' && $param === 'games') {
    artisan_games_list($pdo);
```

In `case 'POST'` block add:
```php
} elseif ($action === 'me' && $param === 'games') {
    artisan_create_game($pdo, $body);
```

In `case 'PUT'` block add:
```php
} elseif ($action === 'me' && $param === 'games' && is_numeric($segments[3] ?? '')) {
    artisan_update_game($pdo, (int)$segments[3], $body);
```

In `case 'DELETE'` block add:
```php
} elseif ($action === 'me' && $param === 'games' && is_numeric($segments[3] ?? '')) {
    artisan_delete_game($pdo, (int)$segments[3]);
```

- [ ] **Step 3: Commit**

```bash
git add sites/api/routes/artisans.php
git commit -m "feat(api): add artisan game CRUD endpoints"
```

---

### Task 5: Add frontend API helpers for games

**Files:**
- Modify: `sites/artisans-shared/src/api.js`

- [ ] **Step 1: Add functions**

Append:

```javascript
// --- Mini-jeux -----------------------------------------------------

export async function fetchGameTypes() {
  const res = await fetch(`${API_BASE}/games/types`)
  if (!res.ok) throw new Error('Erreur chargement types de jeux')
  return res.json()
}

export async function fetchGames(filters = {}) {
  const qs = new URLSearchParams({ city: CITY_SLUG, ...filters }).toString()
  const res = await fetch(`${API_BASE}/games?${qs}`)
  if (!res.ok) throw new Error('Erreur chargement jeux')
  return res.json()
}

export async function fetchGame(id) {
  const res = await fetch(`${API_BASE}/games/${id}`, {
    headers: { ...userHeaders() },
  })
  if (!res.ok) throw new Error('Jeu non trouvé')
  return res.json()
}

export async function playGame(id, data = {}) {
  const res = await fetch(`${API_BASE}/games/${id}/play`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...userHeaders() },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function fetchMyGames(token) {
  const res = await fetch(`${API_BASE}/artisans/me/games`, {
    headers: { 'X-Artisan-Token': token },
  })
  if (!res.ok) throw new Error('Erreur chargement de mes jeux')
  return res.json()
}

export async function createArtisanGame(token, data) {
  const res = await fetch(`${API_BASE}/artisans/me/games`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function updateArtisanGame(token, gameId, data) {
  const res = await fetch(`${API_BASE}/artisans/me/games/${gameId}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-Artisan-Token': token,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function deleteArtisanGame(token, gameId) {
  const res = await fetch(`${API_BASE}/artisans/me/games/${gameId}`, {
    method: 'DELETE',
    headers: { 'X-Artisan-Token': token },
  })
  return res.json()
}
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/api.js
git commit -m "feat(front): add game hub API helpers"
```

---

### Task 6: Build `FreemiumLimitBanner` component

**Files:**
- Create: `sites/artisans-shared/src/components/FreemiumLimitBanner.vue`

- [ ] **Step 1: Write component**

```vue
<template>
  <div class="freemium-banner" role="status">
    <span class="freemium-banner__badge">Gratuit</span>
    <span class="freemium-banner__message">{{ message }}</span>
    <button type="button" class="freemium-banner__cta" @click="$emit('upgrade')">
      Passer premium
    </button>
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: 'Passez à la version premium pour débloquer plus de fonctionnalités.' },
})
defineEmits(['upgrade'])
</script>

<style scoped>
.freemium-banner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  background: #e8f5e9;
  border: 1px solid #c8e6c9;
  border-radius: 0.5rem;
  font-size: 0.9rem;
}
.freemium-banner__badge {
  background: #2d6a4f;
  color: #fff;
  padding: 0.2rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: bold;
}
.freemium-banner__cta {
  margin-left: auto;
  background: #f5a623;
  color: #fff;
  border: none;
  padding: 0.4rem 0.75rem;
  border-radius: 0.5rem;
  cursor: pointer;
}
</style>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/components/FreemiumLimitBanner.vue
git commit -m "feat(front): add FreemiumLimitBanner component"
```

---

### Task 7: Build `GameCard` and `GameCardGrid`

**Files:**
- Create: `sites/artisans-shared/src/components/GameCard.vue`
- Create: `sites/artisans-shared/src/components/GameCardGrid.vue`

- [ ] **Step 1: Write GameCard**

```vue
<template>
  <article class="game-card" :class="{ premium: game.is_premium }">
    <div class="game-card__badge">
      {{ game.is_premium ? 'Premium' : 'Gratuit' }}
    </div>
    <h3 class="game-card__title">{{ game.title }}</h3>
    <p class="game-card__type">{{ game.game_type_label }}</p>
    <p v-if="game.description" class="game-card__desc">{{ game.description }}</p>
    <p class="game-card__artisan">par {{ game.artisan_name }}</p>
    <router-link :to="`/jeu/${game.id}`" class="game-card__cta">
      {{ game.is_premium ? 'Voir' : 'Jouer' }}
    </router-link>
  </article>
</template>

<script setup>
defineProps({
  game: { type: Object, required: true },
})
</script>

<style scoped>
.game-card {
  border: 1px solid #eee;
  border-radius: 0.75rem;
  padding: 1rem;
  background: #fff;
  display: flex;
  flex-direction: column;
}
.game-card.premium {
  border-color: #f5a623;
  background: #fffbf2;
}
.game-card__badge {
  align-self: flex-start;
  font-size: 0.7rem;
  font-weight: bold;
  padding: 0.15rem 0.4rem;
  border-radius: 0.25rem;
  background: #2d6a4f;
  color: #fff;
  margin-bottom: 0.5rem;
}
.game-card.premium .game-card__badge {
  background: #f5a623;
}
.game-card__title {
  margin: 0 0 0.25rem;
  font-size: 1.1rem;
}
.game-card__type {
  color: #666;
  font-size: 0.85rem;
  margin: 0 0 0.5rem;
}
.game-card__desc {
  flex: 1;
  font-size: 0.9rem;
}
.game-card__artisan {
  font-size: 0.8rem;
  color: #888;
  margin: 0.5rem 0;
}
.game-card__cta {
  display: inline-block;
  text-align: center;
  padding: 0.5rem;
  background: #2d6a4f;
  color: #fff;
  border-radius: 0.5rem;
  text-decoration: none;
}
</style>
```

- [ ] **Step 2: Write GameCardGrid**

```vue
<template>
  <div class="game-card-grid">
    <GameCard v-for="g in games" :key="g.id" :game="g" />
  </div>
</template>

<script setup>
import GameCard from './GameCard.vue'

defineProps({
  games: { type: Array, required: true },
})
</script>

<style scoped>
.game-card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1rem;
}
</style>
```

- [ ] **Step 3: Commit**

```bash
git add sites/artisans-shared/src/components/GameCard.vue sites/artisans-shared/src/components/GameCardGrid.vue
git commit -m "feat(front): add GameCard and GameCardGrid components"
```

---

### Task 8: Build free game engines

**Files:**
- Create: `sites/artisans-shared/src/components/games/CouponGame.vue`
- Create: `sites/artisans-shared/src/components/games/PollGame.vue`
- Create: `sites/artisans-shared/src/components/games/VoteBattleGame.vue`

- [ ] **Step 1: CouponGame**

```vue
<template>
  <div class="coupon-game">
    <p class="coupon-game__intro">{{ config.reveal_text || 'Cliquez pour révéler votre offre' }}</p>
    <button v-if="!result" type="button" class="coupon-game__btn" @click="play">
      Révéler
    </button>
    <div v-else class="coupon-game__result">
      <h4>{{ result.reward?.label || 'Merci d\'avoir joué !' }}</h4>
      <p v-if="result.reward?.reward_value?.code">Code : <strong>{{ result.reward.reward_value.code }}</strong></p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { playGame } from '../../api.js'

const props = defineProps({ instanceId: { type: Number, required: true }, config: Object })
const emit = defineEmits(['played'])

const result = ref(null)
const loading = ref(false)

async function play() {
  loading.value = true
  const res = await playGame(props.instanceId)
  loading.value = false
  if (res.success) {
    result.value = res.data
    emit('played', res.data)
  } else {
    alert(res.error || 'Erreur')
  }
}
</script>

<style scoped>
.coupon-game {
  text-align: center;
  padding: 1rem;
}
.coupon-game__btn {
  padding: 0.75rem 1.5rem;
  background: #2d6a4f;
  color: #fff;
  border: none;
  border-radius: 0.5rem;
  font-size: 1rem;
  cursor: pointer;
}
.coupon-game__result {
  border: 2px dashed #2d6a4f;
  padding: 1rem;
  border-radius: 0.5rem;
}
</style>
```

- [ ] **Step 2: PollGame**

```vue
<template>
  <div class="poll-game">
    <h4>{{ config.question }}</h4>
    <div class="poll-game__options">
      <button
        v-for="opt in config.options"
        :key="opt"
        type="button"
        @click="choose(opt)"
      >
        {{ opt }}
      </button>
    </div>
    <p v-if="result">Merci pour votre réponse !</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { playGame } from '../../api.js'

const props = defineProps({ instanceId: { type: Number, required: true }, config: Object })
const emit = defineEmits(['played'])

const result = ref(null)

async function choose(option) {
  const res = await playGame(props.instanceId, { choice: option })
  if (res.success) {
    result.value = res.data
    emit('played', res.data)
  } else {
    alert(res.error || 'Erreur')
  }
}
</script>

<style scoped>
.poll-game__options {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: 1rem;
}
.poll-game__options button {
  padding: 0.6rem;
  border: 1px solid #ccc;
  background: #fff;
  border-radius: 0.5rem;
  cursor: pointer;
}
</style>
```

- [ ] **Step 3: VoteBattleGame**

```vue
<template>
  <div class="vote-game">
    <h4>{{ config.question }}</h4>
    <div class="vote-game__options">
      <button
        v-for="opt in config.options"
        :key="opt"
        type="button"
        @click="vote(opt)"
      >
        {{ opt }}
      </button>
    </div>
    <p v-if="result">Vote enregistré !</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { playGame } from '../../api.js'

const props = defineProps({ instanceId: { type: Number, required: true }, config: Object })
const emit = defineEmits(['played'])

const result = ref(null)

async function vote(option) {
  const res = await playGame(props.instanceId, { choice: option })
  if (res.success) {
    result.value = res.data
    emit('played', res.data)
  } else {
    alert(res.error || 'Erreur')
  }
}
</script>

<style scoped>
.vote-game__options {
  display: flex;
  gap: 0.75rem;
  margin-top: 1rem;
}
.vote-game__options button {
  flex: 1;
  padding: 0.75rem;
  border: 1px solid #ccc;
  background: #fff;
  border-radius: 0.5rem;
  cursor: pointer;
}
</style>
```

- [ ] **Step 4: Commit**

```bash
git add sites/artisans-shared/src/components/games/
git commit -m "feat(front): add coupon, poll and vote/battle game engines"
```

---

### Task 9: Build `GameRenderer` dynamic switcher

**Files:**
- Create: `sites/artisans-shared/src/components/GameRenderer.vue`

- [ ] **Step 1: Write component**

```vue
<template>
  <component
    :is="engineComponent"
    v-if="engineComponent"
    :instance-id="instanceId"
    :config="config"
    @played="$emit('played', $event)"
  />
  <p v-else>Moteur de jeu non disponible.</p>
</template>

<script setup>
import { computed } from 'vue'
import CouponGame from './games/CouponGame.vue'
import PollGame from './games/PollGame.vue'
import VoteBattleGame from './games/VoteBattleGame.vue'

const props = defineProps({
  instanceId: { type: Number, required: true },
  gameType: { type: String, required: true },
  config: { type: Object, default: () => ({}) },
})
defineEmits(['played'])

const engines = {
  CouponGame,
  PollGame,
  VoteBattleGame,
}

const engineComponent = computed(() => {
  const key = props.gameType === 'coupon' ? 'CouponGame'
    : props.gameType === 'poll' ? 'PollGame'
    : props.gameType === 'vote' ? 'VoteBattleGame'
    : null
  return engines[key]
})
</script>
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/components/GameRenderer.vue
git commit -m "feat(front): add GameRenderer dynamic engine switcher"
```

---

### Task 10: Create `GamesHub.vue` city page

**Files:**
- Create: `sites/artisans-shared/src/views/GamesHub.vue`
- Modify: `sites/artisans-shared/src/main.js`
- Modify: `sites/artisans-shared/src/App.vue` nav

- [ ] **Step 1: Write page**

```vue
<template>
  <main class="games-hub">
    <BetaBanner message="Le hub de jeux est en version bêta. De nouveaux jeux arrivent bientôt." />
    <h1>Jeux et bons plans à {{ CITY_NAME }}</h1>

    <FreemiumLimitBanner
      v-if="premiumGames.length"
      message="Passez premium pour débloquer la roue, les quiz, le bingo et les rébus."
    />

    <h2>Jeux gratuits</h2>
    <GameCardGrid :games="freeGames" />

    <h2>Jeux premium</h2>
    <GameCardGrid :games="premiumGames" />
  </main>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { fetchGames } from '../api.js'
import { CITY_NAME } from '../api.js'
import GameCardGrid from '../components/GameCardGrid.vue'
import BetaBanner from '../components/BetaBanner.vue'
import FreemiumLimitBanner from '../components/FreemiumLimitBanner.vue'

const games = ref([])

const freeGames = computed(() => games.value.filter(g => !g.is_premium))
const premiumGames = computed(() => games.value.filter(g => g.is_premium))

async function load() {
  const res = await fetchGames()
  games.value = res.data || []
}

onMounted(load)
</script>

<style scoped>
.games-hub {
  padding: 1rem;
  max-width: 960px;
  margin: 0 auto;
}
.games-hub h2 {
  margin-top: 1.5rem;
}
</style>
```

- [ ] **Step 2: Register route and nav**

In `main.js`:
```javascript
import GamesHub from './views/GamesHub.vue'
{ path: '/jeux', name: 'GamesHub', component: GamesHub },
```

In `App.vue` nav, add:
```vue
<router-link to="/jeux">Jeux</router-link>
```

- [ ] **Step 3: Commit**

```bash
git add sites/artisans-shared/src/views/GamesHub.vue sites/artisans-shared/src/main.js sites/artisans-shared/src/App.vue
git commit -m "feat(front): add city games hub page and navigation"
```

---

### Task 11: Create `GamePlay.vue` instance page

**Files:**
- Create: `sites/artisans-shared/src/views/GamePlay.vue`
- Modify: `sites/artisans-shared/src/main.js`

- [ ] **Step 1: Write page**

```vue
<template>
  <main v-if="game" class="game-play">
    <BetaBanner message="Les jeux sont en version bêta." />
    <h1>{{ game.title }}</h1>
    <p>{{ game.description }}</p>

    <div v-if="!game.can_play" class="game-play__blocked">
      <p v-if="!userToken">
        Connectez-vous gratuitement pour jouer.
        <router-link to="/inscription">Créer un compte</router-link>
      </p>
      <p v-else>Limite de participations atteinte ou jeu inactif.</p>
    </div>

    <GameRenderer
      v-else
      :instance-id="game.id"
      :game-type="game.game_type_key"
      :config="game.config"
      @played="onPlayed"
    />
  </main>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { fetchGame } from '../api.js'
import GameRenderer from '../components/GameRenderer.vue'
import BetaBanner from '../components/BetaBanner.vue'

const route = useRoute()
const game = ref(null)
const userToken = ref(localStorage.getItem('user_session_token'))

async function load() {
  const res = await fetchGame(route.params.id)
  if (res.success) game.value = res.data
}

function onPlayed(data) {
  console.log('played', data)
}

onMounted(load)
</script>

<style scoped>
.game-play {
  padding: 1rem;
  max-width: 600px;
  margin: 0 auto;
}
.game-play__blocked {
  padding: 1rem;
  background: #f5f5f5;
  border-radius: 0.5rem;
  text-align: center;
}
</style>
```

- [ ] **Step 2: Register route**

In `main.js`:
```javascript
import GamePlay from './views/GamePlay.vue'
{ path: '/jeu/:id', name: 'GamePlay', component: GamePlay },
```

- [ ] **Step 3: Commit**

```bash
git add sites/artisans-shared/src/views/GamePlay.vue sites/artisans-shared/src/main.js
git commit -m "feat(front): add game play page"
```

---

### Task 12: Add ActiveGames tab to Artisan.vue

**Files:**
- Modify: `sites/artisans-shared/src/views/Artisan.vue`

- [ ] **Step 1: Add games tab**

Add import:
```javascript
import GameCard from '../components/GameCard.vue'
import { fetchGames } from '../api.js'
```

Add state:
```javascript
const artisanGames = ref([])
async function loadGames() {
  const res = await fetchGames({ artisan_id: props.id })
  artisanGames.value = res.data || []
}
```

Add tab button:
```vue
<button :class="{ active: activeTab === 'games' }" @click="activeTab = 'games'">Jeux actifs</button>
```

Add section:
```vue
<section v-if="activeTab === 'games'" class="artisan-section">
  <h2>Jeux actifs</h2>
  <div v-if="artisanGames.length" class="artisan-games">
    <GameCard v-for="g in artisanGames" :key="g.id" :game="g" />
  </div>
  <p v-else>Aucun jeu actif pour le moment.</p>
</section>
```

Call `loadGames()` alongside other loads.

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/views/Artisan.vue
git commit -m "feat(front): add active games tab to artisan page"
```

---

### Task 13: Create artisan `GamesConfig.vue` dashboard

**Files:**
- Create: `sites/artisans-shared/src/views/artisan/GamesConfig.vue`

- [ ] **Step 1: Write view**

```vue
<template>
  <div class="games-config">
    <h2>Mes mini-jeux</h2>
    <FreemiumLimitBanner
      v-if="activeCount >= 2"
      message="Limite de 2 jeux actifs atteinte en version gratuite."
    />

    <form @submit.prevent="createGame">
      <label>
        Type de jeu
        <select v-model="newGame.game_type_key" required>
          <option v-for="t in freeTypes" :key="t.key" :value="t.key">{{ t.label_fr }}</option>
        </select>
      </label>
      <label>
        Titre
        <input v-model="newGame.title" type="text" required />
      </label>
      <label>
        Description
        <textarea v-model="newGame.description" rows="2"></textarea>
      </label>

      <div v-if="newGame.game_type_key === 'coupon'">
        <label>Texte de révélation <input v-model="newGame.config.reveal_text" type="text" /></label>
      </div>
      <div v-if="newGame.game_type_key === 'poll' || newGame.game_type_key === 'vote'">
        <label>Question <input v-model="newGame.config.question" type="text" /></label>
        <label>Options (séparées par virgule) <input v-model="optionsInput" type="text" /></label>
      </div>

      <button type="submit" :disabled="activeCount >= 2">Créer le jeu</button>
    </form>

    <ul class="game-list">
      <li v-for="g in games" :key="g.id" class="game-item">
        <div>
          <strong>{{ g.title }}</strong>
          <span class="game-type">{{ g.game_type_label }}</span>
        </div>
        <div class="game-actions">
          <button @click="toggleActive(g)">{{ g.is_active ? 'Désactiver' : 'Activer' }}</button>
          <button @click="deleteGame(g.id)">Supprimer</button>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { fetchGameTypes, fetchMyGames, createArtisanGame, updateArtisanGame, deleteArtisanGame } from '../../api.js'
import FreemiumLimitBanner from '../../components/FreemiumLimitBanner.vue'

const props = defineProps({ token: { type: String, required: true } })

const types = ref([])
const games = ref([])
const optionsInput = ref('')
const newGame = ref({
  game_type_key: 'coupon',
  title: '',
  description: '',
  config: {},
})

const freeTypes = computed(() => types.value.filter(t => !t.is_premium))
const activeCount = computed(() => games.value.filter(g => g.is_active).length)

async function load() {
  const [tRes, gRes] = await Promise.all([fetchGameTypes(), fetchMyGames(props.token)])
  types.value = tRes.data || []
  games.value = gRes.data || []
}

async function createGame() {
  const config = { ...newGame.value.config }
  if (newGame.value.game_type_key === 'poll' || newGame.value.game_type_key === 'vote') {
    config.options = optionsInput.value.split(',').map(s => s.trim()).filter(Boolean)
  }
  await createArtisanGame(props.token, {
    game_type_key: newGame.value.game_type_key,
    title: newGame.value.title,
    description: newGame.value.description,
    config,
  })
  newGame.value = { game_type_key: 'coupon', title: '', description: '', config: {} }
  optionsInput.value = ''
  await load()
}

async function toggleActive(g) {
  await updateArtisanGame(props.token, g.id, { is_active: !g.is_active })
  await load()
}

async function deleteGame(id) {
  if (!confirm('Supprimer ce jeu ?')) return
  await deleteArtisanGame(props.token, id)
  await load()
}

onMounted(load)
</script>

<style scoped>
.games-config form label {
  display: block;
  margin-bottom: 0.5rem;
}
.games-config input,
.games-config select,
.games-config textarea {
  width: 100%;
  padding: 0.4rem;
  margin-top: 0.2rem;
}
.game-list {
  list-style: none;
  padding: 0;
  margin-top: 1rem;
}
.game-item {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem;
  border: 1px solid #eee;
  border-radius: 0.5rem;
  margin-bottom: 0.5rem;
}
.game-type {
  display: block;
  font-size: 0.8rem;
  color: #666;
}
.game-actions {
  display: flex;
  gap: 0.5rem;
}
</style>
```

- [ ] **Step 2: Wire route**

Add route in artisan dashboard router:
```javascript
{ path: '/artisan/jeux', component: GamesConfig, props: true },
```

- [ ] **Step 3: Commit**

```bash
git add sites/artisans-shared/src/views/artisan/GamesConfig.vue
git commit -m "feat(front): add artisan game configuration page"
```

---

### Task 14: Update smoke tests

**Files:**
- Modify: `scripts/test-api.sh`

- [ ] **Step 1: Add games test block**

Append:

```bash
# ------------------------------------------------------------------
# Mini-games
# ------------------------------------------------------------------
echo "== Mini-games =="
assert_json "$BASE/games/types" "GET" "{}" "success" "true"
assert_json "$BASE/games?city=livry" "GET" "{}" "success" "true"
```

- [ ] **Step 2: Run tests**

```bash
./scripts/test-api.sh
```

- [ ] **Step 3: Commit**

```bash
git add scripts/test-api.sh
git commit -m "test(api): add smoke tests for mini-games hub"
```

---

## Self-Review Checklist

1. **Spec coverage:**
   - Game types table with premium flag → Task 1
   - Instances, plays, rewards → Task 1
   - Public hub + play endpoints → Tasks 2-3
   - Artisan CRUD with 2 active limit → Task 4
   - Free engines (coupon, poll, vote) → Tasks 7-9
   - Premium displayed but blocked → Tasks 3, 10
   - City + artisan hub pages → Tasks 10, 12
   - Artisan dashboard → Task 13

2. **Placeholder scan:** `games_claim` is 501 — add a task if coupon claiming is required before closing this plan.

3. **Type consistency:** `game_type_key`, `engine_component`, `is_premium`, `config` consistent across PHP and Vue.
