# Gamification consommateur Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transformer le compte consommateur existant en personnage ludique avec niveaux, XP, badges, avatars et cooldowns sur les actions quotidiennes.

**Architecture:** Une migration SQL ajoute les colonnes/tables nécessaires. Un module PHP `lib/Gamification.php` centralise la logique XP/cooldowns/badges. De nouvelles routes API exposent le profil, l’upload d’avatar et l’enregistrement des actions. Le frontend ajoute une icône de profil dans `AppNav`, les pages `/profil` et `/personnage`, et déclenche des toasts d’XP sur les actions existantes.

**Tech Stack:** PHP 8.4, MySQL 8, Vue 3, Vite, Docker Compose, Makefile.

---

### Task 1 : Migration SQL gamification

**Files:**
- Create: `sites/api/migrations/028_gamification.sql`

- [ ] **Step 1 : Créer la migration**

```sql
-- ============================================================
-- WebiArtisan — Migration 028 : Gamification consommateur
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE local_users
    ADD COLUMN display_name VARCHAR(80) NULL AFTER email,
    ADD COLUMN avatar_type ENUM('default','upload','custom') NOT NULL DEFAULT 'default' AFTER display_name,
    ADD COLUMN avatar_url VARCHAR(255) NULL AFTER avatar_type,
    ADD COLUMN avatar_gender ENUM('male','female','neutral') NOT NULL DEFAULT 'neutral' AFTER avatar_url,
    ADD COLUMN level INT NOT NULL DEFAULT 1 AFTER avatar_gender,
    ADD COLUMN xp INT NOT NULL DEFAULT 0 AFTER level,
    ADD COLUMN title VARCHAR(80) NULL AFTER xp;

CREATE TABLE IF NOT EXISTS local_user_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_key VARCHAR(50) NOT NULL,
    xp_amount INT NOT NULL DEFAULT 0,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_action_key (action_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_user_cooldowns (
    user_id INT NOT NULL,
    action_key VARCHAR(50) NOT NULL,
    period VARCHAR(20) NOT NULL,
    resource_key VARCHAR(255) NULL,
    last_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, action_key, resource_key),
    INDEX idx_last_at (last_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_user_badges (
    user_id INT NOT NULL,
    badge_key VARCHAR(50) NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, badge_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_user_streaks (
    user_id INT PRIMARY KEY,
    current_streak INT NOT NULL DEFAULT 0,
    last_visit_date DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2 : Commit**

```bash
git add sites/api/migrations/028_gamification.sql
git commit -m "feat(db): add gamification migration"
```

---

### Task 2 : Module PHP Gamification

**Files:**
- Create: `sites/api/lib/Gamification.php`

- [ ] **Step 1 : Créer le module**

```php
<?php
/**
 * WebIArtisan — Gamification engine
 */

require_once __DIR__ . '/UserAuth.php';

const XP_ACTIONS = [
    'artisan_view'      => ['xp' => 5,  'cooldown' => 'hourly', 'limit' => null],
    'spin_play'         => ['xp' => 10, 'cooldown' => 'daily',  'limit' => null],
    'qr_validate'       => ['xp' => 25, 'cooldown' => 'once_per_resource', 'limit' => null],
    'recipe_view'       => ['xp' => 3,  'cooldown' => 'daily',  'limit' => null],
    'share'             => ['xp' => 15, 'cooldown' => 'daily',  'limit' => 3],
    'review'            => ['xp' => 20, 'cooldown' => 'once_per_resource', 'limit' => null],
    'recipe_suggest'    => ['xp' => 10, 'cooldown' => 'once_per_resource', 'limit' => null],
    'daily_visit'       => ['xp' => 0,  'cooldown' => 'daily',  'limit' => 1],
    'streak_3days'      => ['xp' => 30, 'cooldown' => 'daily',  'limit' => 1],
];

const LEVEL_TITLES = [
    1  => 'Nouveau dans le quartier',
    3  => 'Explorateur local',
    5  => 'Habitulé du marché',
    10 => 'Ambassadeur du terroir',
    20 => 'Légende du village',
];

const BADGES = [
    'first_visit'   => ['name' => 'Première visite', 'condition' => 'Visiter une fiche artisan.', 'target' => 1, 'action' => 'artisan_view'],
    'gourmand'      => ['name' => 'Gourmand',        'condition' => 'Consulter 10 recettes.',      'target' => 10, 'action' => 'recipe_view'],
    'lucky'         => ['name' => 'Chanceux',        'condition' => 'Gagner 5 offres à la roue.',  'target' => 5, 'action' => 'spin_play'],
    'benefactor'    => ['name' => 'Bienfaiteur',     'condition' => 'Laisser 3 avis.',             'target' => 3, 'action' => 'review'],
    'generous'      => ['name' => 'Généreux',        'condition' => 'Partager 5 pages.',           'target' => 5, 'action' => 'share'],
    'faithful'      => ['name' => 'Fidèle',          'condition' => '7 jours de connexion.',       'target' => 7, 'action' => 'daily_visit'],
];

function gamificationUserProfile(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT id, email, display_name, avatar_type, avatar_url, avatar_gender, level, xp, title
        FROM local_users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $badgeStmt = $pdo->prepare("
        SELECT badge_key, unlocked_at FROM local_user_badges WHERE user_id = ?
    ");
    $badgeStmt->execute([$userId]);
    $badges = $badgeStmt->fetchAll(PDO::FETCH_ASSOC);

    $xpNeeded = ((int)$user['level']) * 100;

    return [
        'id' => (int)$user['id'],
        'email' => $user['email'],
        'display_name' => $user['display_name'] ?? substr($user['email'], 0, strpos($user['email'], '@')),
        'avatar_type' => $user['avatar_type'],
        'avatar_url' => $user['avatar_url'],
        'avatar_gender' => $user['avatar_gender'],
        'level' => (int)$user['level'],
        'xp' => (int)$user['xp'],
        'xp_needed' => $xpNeeded,
        'title' => $user['title'] ?? LEVEL_TITLES[1],
        'badges' => array_map(fn($b) => ['key' => $b['badge_key'], 'name' => BADGES[$b['badge_key']]['name'] ?? $b['badge_key'], 'unlocked_at' => $b['unlocked_at']], $badges),
    ];
}

function gamificationRecordAction(PDO $pdo, int $userId, string $actionKey, ?string $resourceKey = null, ?array $metadata = null): ?array
{
    if (!isset(XP_ACTIONS[$actionKey])) {
        return null;
    }

    $config = XP_ACTIONS[$actionKey];
    $now = new DateTimeImmutable();
    $resourceKey = $resourceKey ?? '';

    // Vérifier cooldown
    if ($config['cooldown'] !== 'none') {
        $stmt = $pdo->prepare("
            SELECT last_at FROM local_user_cooldowns
            WHERE user_id = ? AND action_key = ? AND resource_key = ?
        ");
        $stmt->execute([$userId, $actionKey, $resourceKey]);
        $last = $stmt->fetchColumn();

        if ($last) {
            $lastAt = new DateTimeImmutable($last);
            $canAfter = match ($config['cooldown']) {
                'hourly' => $lastAt->modify('+1 hour'),
                'daily' => $lastAt->modify('+1 day')->setTime(0, 0),
                'once_per_resource' => false,
                default => $lastAt,
            };

            if ($canAfter === false || $now < $canAfter) {
                return null;
            }
        }
    }

    // Vérifier limite globale quotidienne
    if ($config['limit'] !== null && $config['cooldown'] === 'daily') {
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM local_user_actions
            WHERE user_id = ? AND action_key = ? AND DATE(created_at) = CURDATE()
        ");
        $countStmt->execute([$userId, $actionKey]);
        if ((int)$countStmt->fetchColumn() >= $config['limit']) {
            return null;
        }
    }

    $pdo->beginTransaction();
    try {
        // Mettre à jour cooldown
        $pdo->prepare("
            INSERT INTO local_user_cooldowns (user_id, action_key, period, resource_key, last_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_at = NOW()
        ")->execute([$userId, $actionKey, $config['cooldown'], $resourceKey]);

        // Log action
        $pdo->prepare("
            INSERT INTO local_user_actions (user_id, action_key, xp_amount, metadata, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ")->execute([$userId, $actionKey, $config['xp'], $metadata ? json_encode($metadata) : null]);

        // Ajouter XP et gérer level up
        $pdo->prepare("
            UPDATE local_users SET xp = xp + ? WHERE id = ?
        ")->execute([$config['xp'], $userId]);

        $leveledUp = gamificationCheckLevelUp($pdo, $userId);

        // Vérifier badges
        $newBadges = gamificationCheckBadges($pdo, $userId, $actionKey);

        $pdo->commit();

        return [
            'xp_gained' => $config['xp'],
            'level_up' => $leveledUp,
            'new_badges' => $newBadges,
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[GAMIFICATION] ' . $e->getMessage());
        return null;
    }
}

function gamificationCheckLevelUp(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT level, xp FROM local_users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $level = (int)$user['level'];
    $xp = (int)$user['xp'];
    $leveledUp = false;

    while ($xp >= $level * 100) {
        $xp -= $level * 100;
        $level++;
        $leveledUp = true;
    }

    if ($leveledUp) {
        $title = null;
        krsort(LEVEL_TITLES);
        foreach (LEVEL_TITLES as $lvl => $t) {
            if ($level >= $lvl) {
                $title = $t;
                break;
            }
        }

        $pdo->prepare("
            UPDATE local_users SET level = ?, xp = ?, title = ? WHERE id = ?
        ")->execute([$level, $xp, $title, $userId]);
    }

    return $leveledUp;
}

function gamificationCheckBadges(PDO $pdo, int $userId, string $actionKey): array
{
    $newBadges = [];

    foreach (BADGES as $key => $badge) {
        if ($badge['action'] !== $actionKey) {
            continue;
        }

        $stmt = $pdo->prepare("
            SELECT 1 FROM local_user_badges WHERE user_id = ? AND badge_key = ?
        ");
        $stmt->execute([$userId, $key]);
        if ($stmt->fetch()) continue;

        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM local_user_actions
            WHERE user_id = ? AND action_key = ?
        ");
        $countStmt->execute([$userId, $badge['action']]);
        $count = (int)$countStmt->fetchColumn();

        if ($count >= $badge['target']) {
            $pdo->prepare("
                INSERT INTO local_user_badges (user_id, badge_key) VALUES (?, ?)
            ")->execute([$userId, $key]);
            $newBadges[] = ['key' => $key, 'name' => $badge['name']];
        }
    }

    return $newBadges;
}

function gamificationUpdateStreak(PDO $pdo, int $userId): void
{
    $today = date('Y-m-d');

    // Vérifier si on a déjà une visite aujourd'hui
    $checkStmt = $pdo->prepare("
        SELECT last_visit_date FROM local_user_streaks WHERE user_id = ?
    ");
    $checkStmt->execute([$userId]);
    $lastDate = $checkStmt->fetchColumn();

    $isNewDay = $lastDate !== $today;

    $stmt = $pdo->prepare("
        INSERT INTO local_user_streaks (user_id, current_streak, last_visit_date)
        VALUES (?, 1, ?)
        ON DUPLICATE KEY UPDATE
            current_streak = CASE
                WHEN last_visit_date = DATE_SUB(?, INTERVAL 1 DAY) THEN current_streak + 1
                WHEN last_visit_date = ? THEN current_streak
                ELSE 1
            END,
            last_visit_date = ?
    ");
    $stmt->execute([$userId, $today, $today, $today, $today]);

    // Loguer la visite quotidienne une seule fois par jour
    if ($isNewDay) {
        gamificationRecordAction($pdo, $userId, 'daily_visit');
    }

    $streakStmt = $pdo->prepare("SELECT current_streak FROM local_user_streaks WHERE user_id = ?");
    $streakStmt->execute([$userId]);
    $streak = (int)$streakStmt->fetchColumn();

    if ($streak >= 3) {
        gamificationRecordAction($pdo, $userId, 'streak_3days');
    }
}
```

- [ ] **Step 2 : Commit**

```bash
git add sites/api/lib/Gamification.php
git commit -m "feat(api): add gamification engine"
```

---

### Task 3 : Enrichir `/users/me`

**Files:**
- Modify: `sites/api/routes/users.php:140-144`

- [ ] **Step 1 : Modifier la fonction `user_me`**

```php
function user_me(PDO $pdo): void
{
    $user = user_require_auth($pdo);
    require_once __DIR__ . '/../lib/Gamification.php';
    $profile = gamificationUserProfile($pdo, (int)$user['id']);
    echo json_encode(['success' => true, 'data' => $profile]);
}
```

- [ ] **Step 2 : Commit**

```bash
git add sites/api/routes/users.php
git commit -m "feat(api): enrich /users/me with gamification profile"
```

---

### Task 4 : Route `/actions` pour enregistrer les actions

**Files:**
- Create: `sites/api/routes/actions.php`

- [ ] **Step 1 : Créer le fichier**

```php
<?php
/**
 * WebIArtisan API — Route : Actions gamifiées
 *
 * POST /actions
 */

require_once __DIR__ . '/../lib/UserAuth.php';
require_once __DIR__ . '/../lib/Gamification.php';

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$user = user_require_auth($pdo);
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$actionKey = $body['action'] ?? '';
$resourceKey = $body['resource_key'] ?? null;
$metadata = $body['metadata'] ?? null;

if (!$actionKey || !isset(XP_ACTIONS[$actionKey])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Action inconnue']);
    exit;
}

$result = gamificationRecordAction($pdo, (int)$user['id'], $actionKey, $resourceKey, $metadata);

if ($result === null) {
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => ['xp_gained' => 0, 'cooldown' => true]]);
    exit;
}

echo json_encode(['success' => true, 'data' => $result]);
```

- [ ] **Step 2 : Brancher la route dans `sites/api/index.php`**

Ajouter avant `// Route to the correct module`:

```php
if ($module === 'actions') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/actions.php';
    exit;
}
```

- [ ] **Step 3 : Commit**

```bash
git add sites/api/routes/actions.php sites/api/index.php
git commit -m "feat(api): add /actions endpoint for gamification"
```

---

### Task 5 : Route `/avatars` et upload d’avatar

**Files:**
- Create: `sites/api/routes/avatars.php`
- Create: `sites/api/public/avatars/male/.gitkeep`
- Create: `sites/api/public/avatars/female/.gitkeep`
- Create: `sites/api/public/avatars/neutral/.gitkeep`
- Create: `sites/api/public/avatars/neutral/default.png`
- Create: `sites/api/public/avatars/neutral/default.png.json`
- Create: `sites/api/uploads/.gitkeep`
- Create: `sites/api/.gitignore`

- [ ] **Step 1 : Créer les répertoires d’avatars et ignorer les uploads**

```bash
mkdir -p sites/api/public/avatars/{male,female,neutral}
mkdir -p sites/api/uploads/avatars
```

Créer `sites/api/.gitignore` :

```gitignore
# Uploads utilisateurs
uploads/avatars/*
!uploads/avatars/.gitkeep
```

Placer ensuite les fichiers PNG/SVG fournis par l’équipe dans `sites/api/public/avatars/{male,female}/`. Chaque avatar doit avoir un fichier `.json` associé, par exemple `sites/api/public/avatars/neutral/default.png.json` :

```json
{
  "id": "default",
  "gender": "neutral",
  "name": "Explorateur",
  "unlock_level": 1,
  "unlock_badge": null
}
```

- [ ] **Step 2 : Créer `/avatars` (GET)**

```php
<?php
/**
 * WebIArtisan API — Route : Avatars
 *
 * GET /avatars?gender=male|female
 */

$gender = $_GET['gender'] ?? 'neutral';
$allowedGenders = ['male', 'female', 'neutral'];
if (!in_array($gender, $allowedGenders, true)) {
    $gender = 'neutral';
}

$basePath = __DIR__ . '/../public/avatars';
$dirs = [];
if ($gender === 'neutral') {
    $dirs = ['neutral', 'male', 'female'];
} else {
    $dirs = [$gender, 'neutral'];
}

$avatars = [];
foreach ($dirs as $dir) {
    $path = $basePath . '/' . $dir;
    if (!is_dir($path)) continue;
    foreach (glob($path . '/*.{png,svg,jpg,jpeg}', GLOB_BRACE) as $file) {
        if (str_ends_with($file, '.json')) continue;
        $metaFile = $file . '.json';
        $meta = [];
        if (file_exists($metaFile)) {
            $meta = json_decode(file_get_contents($metaFile), true) ?: [];
        }
        $avatars[] = [
            'id' => basename($file),
            'gender' => $dir,
            'url' => '/avatars/' . $dir . '/' . basename($file),
            'name' => $meta['name'] ?? basename($file),
            'unlock_level' => $meta['unlock_level'] ?? 1,
            'unlock_badge' => $meta['unlock_badge'] ?? null,
        ];
    }
}

usort($avatars, fn($a, $b) => $a['unlock_level'] <=> $b['unlock_level']);

echo json_encode(['success' => true, 'data' => $avatars]);
```

- [ ] **Step 2 : Ajouter PUT `/users/me/profile` et POST `/users/me/avatar` dans `users.php`**

Dans le `switch ($method)` de `users.php`, ajouter:

```php
case 'PUT':
    if ($action === 'me') {
        user_update_profile($pdo);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
    }
    break;
```

Et les fonctions:

```php
function user_update_profile(PDO $pdo): void
{
    $user = user_require_auth($pdo);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $displayName = isset($body['display_name']) ? trim($body['display_name']) : null;
    $avatarGender = $body['avatar_gender'] ?? null;
    $title = $body['title'] ?? null;

    $fields = [];
    $values = [];

    if ($displayName !== null) {
        $fields[] = 'display_name = ?';
        $values[] = substr($displayName, 0, 80);
    }
    if ($avatarGender !== null && in_array($avatarGender, ['male', 'female', 'neutral'], true)) {
        $fields[] = 'avatar_gender = ?';
        $values[] = $avatarGender;
    }
    if ($title !== null) {
        $fields[] = 'title = ?';
        $values[] = substr($title, 0, 80);
    }

    if ($fields) {
        $values[] = $user['id'];
        $pdo->prepare("UPDATE local_users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
    }

    require_once __DIR__ . '/../lib/Gamification.php';
    echo json_encode(['success' => true, 'data' => gamificationUserProfile($pdo, (int)$user['id'])]);
}
```

Pour l’upload, on va le gérer via une nouvelle route séparée plus robuste. Créer `sites/api/routes/avatar-upload.php`?

En fait, intégrons dans `users.php` en POST avec `action === 'me/avatar'`:

```php
case 'POST':
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if ($action === 'magic-link') {
        user_magic_link($pdo, $body);
    } elseif ($action === 'auth') {
        user_auth($pdo);
    } elseif ($action === 'me/avatar') {
        user_update_avatar($pdo, $body);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
    }
    break;
```

Fonction `user_update_avatar`:

```php
function user_update_avatar(PDO $pdo, array $body): void
{
    $user = user_require_auth($pdo);

    if (!empty($body['avatar_id']) && !empty($body['avatar_url'])) {
        // Choix dans la bibliothèque
        $avatarType = $body['avatar_type'] ?? 'custom';
        $pdo->prepare("
            UPDATE local_users
            SET avatar_type = ?, avatar_url = ?
            WHERE id = ?
        ")->execute([$avatarType, $body['avatar_url'], $user['id']]);
    } elseif (!empty($body['base64_image'])) {
        // Upload base64
        $uploadDir = __DIR__ . '/../uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $base64 = $body['base64_image'];
        if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $base64, $matches)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Format invalide']);
            return;
        }
        $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $data = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $base64));
        if (!$data || strlen($data) > 2 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Image trop lourde ou invalide']);
            return;
        }

        $fileName = $user['id'] . '_' . time() . '.' . $ext;
        $filePath = $uploadDir . $fileName;
        file_put_contents($filePath, $data);

        // Redimensionnement simple avec GD si dispo
        if (extension_loaded('gd')) {
            $src = imagecreatefromstring($data);
            if ($src) {
                $size = 256;
                $origW = imagesx($src);
                $origH = imagesy($src);
                $min = min($origW, $origH);
                $cropX = ($origW - $min) / 2;
                $cropY = ($origH - $min) / 2;
                $dst = imagecreatetruecolor($size, $size);
                imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $size, $size, $min, $min);
                imagejpeg($dst, $filePath, 85);
                imagedestroy($src);
                imagedestroy($dst);
            }
        }

        $publicUrl = '/api/uploads/avatars/' . $fileName;
        $pdo->prepare("
            UPDATE local_users
            SET avatar_type = 'upload', avatar_url = ?
            WHERE id = ?
        ")->execute([$publicUrl, $user['id']]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucun avatar fourni']);
        return;
    }

    require_once __DIR__ . '/../lib/Gamification.php';
    echo json_encode(['success' => true, 'data' => gamificationUserProfile($pdo, (int)$user['id'])]);
}
```

- [ ] **Step 3 : Commit**

```bash
git add sites/api/routes/users.php sites/api/routes/avatars.php sites/api/public/avatars sites/api/uploads/.gitkeep sites/api/.gitignore sites/api/index.php
git commit -m "feat(api): add avatar selection, upload and profile update"
```

---

### Task 6 : Icône de profil dans `AppNav.vue`

**Files:**
- Modify: `sites/artisans-shared/src/components/AppNav.vue`

- [ ] **Step 1 : Ajouter le hook utilisateur**

Ajouter dans `<script setup>`:

```js
import { ref, computed, onMounted } from 'vue'
import { API_BASE, getUserToken, fetchUserMe } from '../api.js'
import { useRouter } from 'vue-router'

const router = useRouter()
const user = ref(null)

const avatarUrl = computed(() => user.value?.avatar_url ? API_BASE + user.value.avatar_url : null)

onMounted(async () => {
  const token = getUserToken()
  if (token) {
    try {
      const res = await fetchUserMe(token)
      if (res.success) user.value = res.data
    } catch (e) {
      user.value = null
    }
  }
})

function goToProfile() {
  router.push('/profil')
}
```

- [ ] **Step 2 : Ajouter l’icône dans le template**

Dans le `<nav>`, ajouter à côté des liens existants :

```vue
<div v-if="user" class="nav-profile" @click="goToProfile">
  <img v-if="avatarUrl" :src="avatarUrl" class="nav-avatar" alt="Avatar" />
  <span v-else class="nav-avatar-placeholder">🙂</span>
  <span class="nav-level">Lv.{{ user.level }}</span>
</div>
```

- [ ] **Step 3 : Commit**

```bash
git add sites/artisans-shared/src/components/AppNav.vue
git commit -m "feat(ui): add user profile icon to nav"
```

---

### Task 7 : Page `/profil`

**Files:**
- Create: `sites/artisans-shared/src/views/UserProfile.vue`
- Modify: `sites/artisans-shared/src/main.js:6-24`

- [ ] **Step 1 : Créer la page**

```vue
<template>
  <div class="user-profile">
    <div class="profile-card">
      <img v-if="user?.avatar_url" :src="avatarUrl" class="profile-avatar" />
      <div v-else class="profile-avatar-placeholder">🙂</div>
      <h1>{{ displayName }}</h1>
      <p class="profile-title">{{ user?.title }}</p>
      <div class="profile-level">
        <span>Niveau {{ user?.level }}</span>
        <div class="xp-bar"><div class="xp-fill" :style="{ width: xpPercent + '%' }"></div></div>
        <span>{{ user?.xp }} / {{ user?.xp_needed }} XP</span>
      </div>
      <button @click="$router.push('/personnage')">Modifier mon personnage</button>
    </div>

    <h2>Badges</h2>
    <div class="badges-list">
      <span v-for="b in user?.badges" :key="b.key" class="badge">{{ b.name }}</span>
      <p v-if="!user?.badges?.length">Aucun badge pour l’instant. Continuez à explorer !</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { API_BASE, getUserToken, fetchUserMe } from '../api.js'

const user = ref(null)

const displayName = computed(() => user.value?.display_name || user.value?.email?.split('@')[0] || 'Explorateur')
const xpPercent = computed(() => user.value ? (user.value.xp / user.value.xp_needed) * 100 : 0)
const avatarUrl = computed(() => user.value?.avatar_url ? API_BASE + user.value.avatar_url : null)

onMounted(async () => {
  const token = getUserToken()
  if (token) {
    const res = await fetchUserMe(token)
    if (res.success) user.value = res.data
  }
})
</script>

<style scoped>
.user-profile { max-width: 640px; margin: 0 auto; padding: 24px; }
.profile-card { text-align: center; background: #f8fafc; border-radius: 16px; padding: 32px; margin-bottom: 24px; }
.profile-avatar, .profile-avatar-placeholder { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 48px; background: #e2e8f0; }
.profile-title { color: #64748b; }
.profile-level { margin: 16px 0; }
.xp-bar { width: 100%; height: 12px; background: #e2e8f0; border-radius: 6px; overflow: hidden; margin: 8px 0; }
.xp-fill { height: 100%; background: #10b981; transition: width 0.3s; }
.badges-list { display: flex; flex-wrap: wrap; gap: 8px; }
.badge { background: #1a1a2e; color: #fff; padding: 6px 12px; border-radius: 20px; font-size: 13px; }
</style>
```

- [ ] **Step 2 : Ajouter la route**

Dans `sites/artisans-shared/src/main.js`:

```js
{ path: '/profil', component: () => import('./views/UserProfile.vue'), meta: { title: 'Mon profil' } },
```

- [ ] **Step 3 : Commit**

```bash
git add sites/artisans-shared/src/views/UserProfile.vue sites/artisans-shared/src/main.js
git commit -m "feat(ui): add /profil page"
```

---

### Task 8 : Page `/personnage`

**Files:**
- Create: `sites/artisans-shared/src/views/CharacterEdit.vue`
- Modify: `sites/artisans-shared/src/main.js`
- Modify: `sites/artisans-shared/src/api.js`

- [ ] **Step 1 : Ajouter les fonctions API dans `api.js`**

```js
export async function fetchAvatars(gender = 'neutral') {
  const res = await fetch(`${API_BASE}/avatars?gender=${encodeURIComponent(gender)}`)
  if (!res.ok) throw new Error('Erreur chargement avatars')
  return res.json()
}

export async function updateUserProfile(token, data) {
  const res = await fetch(`${API_BASE}/users/me`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}

export async function updateUserAvatar(token, data) {
  const res = await fetch(`${API_BASE}/users/me/avatar`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(data),
  })
  return res.json()
}
```

- [ ] **Step 2 : Créer `CharacterEdit.vue`**

```vue
<template>
  <div class="character-edit">
    <h1>Personnaliser mon personnage</h1>

    <label>Pseudo public</label>
    <input v-model="form.display_name" maxlength="80" />

    <label>Genre</label>
    <select v-model="form.avatar_gender">
      <option value="male">Homme</option>
      <option value="female">Femme</option>
      <option value="neutral">Neutre</option>
    </select>

    <label>Avatar</label>
    <div class="avatar-grid">
      <div
        v-for="a in avatars"
        :key="a.id"
        class="avatar-item"
        :class="{ locked: a.unlock_level > (user?.level || 1), selected: selectedAvatar?.id === a.id }"
        @click="selectAvatar(a)"
      >
        <img :src="avatarUrl(a.url)" :alt="a.name" />
        <span>{{ a.name }}</span>
        <small v-if="a.unlock_level > 1">Niv. {{ a.unlock_level }}</small>
      </div>
    </div>

    <label>Avatar personnel</label>
    <input type="file" accept="image/png,image/jpeg" @change="onFileChange" />

    <button @click="save" :disabled="saving">Enregistrer</button>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { API_BASE, getUserToken, fetchUserMe, fetchAvatars, updateUserProfile, updateUserAvatar } from '../api.js'

const avatarUrl = (url) => API_BASE + url

const router = useRouter()
const user = ref(null)
const avatars = ref([])
const form = ref({ display_name: '', avatar_gender: 'neutral' })
const selectedAvatar = ref(null)
const uploadedBase64 = ref(null)
const saving = ref(false)

onMounted(async () => {
  const token = getUserToken()
  if (!token) return router.push('/roue')
  const res = await fetchUserMe(token)
  if (!res.success) return router.push('/roue')
  user.value = res.data
  form.value.display_name = res.data.display_name || ''
  form.value.avatar_gender = res.data.avatar_gender || 'neutral'
  await loadAvatars()
})

watch(() => form.value.avatar_gender, loadAvatars)

async function loadAvatars() {
  const res = await fetchAvatars(form.value.avatar_gender)
  avatars.value = res.data || []
}

function selectAvatar(a) {
  if (a.unlock_level > (user.value?.level || 1)) return
  selectedAvatar.value = a
  uploadedBase64.value = null
}

function onFileChange(e) {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    uploadedBase64.value = reader.result
    selectedAvatar.value = null
  }
  reader.readAsDataURL(file)
}

async function save() {
  saving.value = true
  const token = getUserToken()
  try {
    await updateUserProfile(token, {
      display_name: form.value.display_name,
      avatar_gender: form.value.avatar_gender,
    })

    if (selectedAvatar.value) {
      await updateUserAvatar(token, {
        avatar_id: selectedAvatar.value.id,
        avatar_url: selectedAvatar.value.url,
        avatar_type: 'custom',
      })
    } else if (uploadedBase64.value) {
      await updateUserAvatar(token, { base64_image: uploadedBase64.value })
    }

    router.push('/profil')
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.character-edit { max-width: 640px; margin: 0 auto; padding: 24px; }
label { display: block; margin-top: 16px; font-weight: 600; }
input, select { width: 100%; padding: 8px; margin-top: 4px; }
.avatar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 12px; margin-top: 8px; }
.avatar-item { text-align: center; padding: 8px; border: 2px solid transparent; border-radius: 8px; cursor: pointer; }
.avatar-item.selected { border-color: #10b981; background: #ecfdf5; }
.avatar-item.locked { opacity: 0.4; cursor: not-allowed; }
.avatar-item img { width: 64px; height: 64px; object-fit: cover; border-radius: 50%; }
button { margin-top: 24px; padding: 12px 24px; background: #1a1a2e; color: #fff; border: none; border-radius: 8px; cursor: pointer; }
button:disabled { opacity: 0.6; }
</style>
```

- [ ] **Step 3 : Ajouter la route**

Dans `main.js`:

```js
{ path: '/personnage', component: () => import('./views/CharacterEdit.vue'), meta: { title: 'Mon personnage' } },
```

- [ ] **Step 4 : Commit**

```bash
git add sites/artisans-shared/src/views/CharacterEdit.vue sites/artisans-shared/src/main.js sites/artisans-shared/src/api.js
git commit -m "feat(ui): add /personnage avatar and profile editor"
```

---

### Task 9 : Toasts XP et service frontend

**Files:**
- Create: `sites/artisans-shared/src/composables/useGamification.js`
- Modify: `sites/artisans-shared/src/App.vue`

- [ ] **Step 1 : Créer le composable**

```js
import { ref } from 'vue'
import { API_BASE, getUserToken } from '../api.js'

const toasts = ref([])

export function useGamification() {
  async function recordAction(action, resourceKey = null, metadata = null) {
    const token = getUserToken()
    if (!token) return null
    try {
      const res = await fetch(`${API_BASE}/actions`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({ action, resource_key: resourceKey, metadata }),
      })
      const data = await res.json()
      if (data.success && data.data && data.data.xp_gained > 0) {
        showToast(`+${data.data.xp_gained} XP`)
        if (data.data.level_up) showToast('🎉 Niveau supérieur !')
        if (data.data.new_badges?.length) {
          for (const b of data.data.new_badges) showToast(`🏅 Badge débloqué : ${b.name}`)
        }
      }
      return data
    } catch (e) {
      return null
    }
  }

  function showToast(message) {
    const id = Date.now() + Math.random()
    toasts.value.push({ id, message })
    setTimeout(() => {
      toasts.value = toasts.value.filter(t => t.id !== id)
    }, 3000)
  }

  return { toasts, recordAction }
}
```

- [ ] **Step 2 : Afficher les toasts dans `App.vue`**

```vue
<template>
  <div id="layout">
    <AppNav />
    <main>
      <RouterView v-slot="{ Component }">
        <Transition name="fade" mode="out-in">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </main>
    <AppFooter />
    <div class="toast-container">
      <TransitionGroup name="toast">
        <div v-for="t in toasts" :key="t.id" class="toast">{{ t.message }}</div>
      </TransitionGroup>
    </div>
  </div>
</template>

<script setup>
import AppNav    from './components/AppNav.vue'
import AppFooter from './components/AppFooter.vue'
import { useGamification } from './composables/useGamification.js'

const { toasts } = useGamification()
</script>

<style>
#layout { display: flex; flex-direction: column; min-height: 100vh; }
main { flex: 1; }
.toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 1000; display: flex; flex-direction: column; gap: 8px; }
.toast { background: #1a1a2e; color: #fff; padding: 10px 16px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(20px); }
</style>
```

- [ ] **Step 3 : Commit**

```bash
git add sites/artisans-shared/src/composables/useGamification.js sites/artisans-shared/src/App.vue
git commit -m "feat(ui): add XP toast system"
```

---

### Task 10 : Déclencher les actions dans la roue

**Files:**
- Modify: `sites/artisans-shared/src/views/SpinWheel.vue`

- [ ] **Step 1 : Importer et utiliser le composable**

Dans `<script setup>`:

```js
import { useGamification } from '../composables/useGamification.js'
const { recordAction } = useGamification()
```

Lorsque le spin réussit (après l’appel API `postSpin`), appeler:

```js
await recordAction('spin_play')
```

- [ ] **Step 2 : Commit**

```bash
git add sites/artisans-shared/src/views/SpinWheel.vue
git commit -m "feat(gamification): award XP on spin"
```

---

### Task 11 : Déclencher les actions sur les fiches artisans

**Files:**
- Modify: `sites/artisans-shared/src/views/Artisan.vue`

- [ ] **Step 1 : Ajouter `artisan_view` au montage**

Dans `<script setup>`:

```js
import { useGamification } from '../composables/useGamification.js'
const { recordAction } = useGamification()

onMounted(async () => {
  // ... chargement existant ...
  await recordAction('artisan_view', `artisan:${artisanId.value}`)
})
```

- [ ] **Step 2 : Commit**

```bash
git add sites/artisans-shared/src/views/Artisan.vue
git commit -m "feat(gamification): award XP on artisan view"
```

---

### Task 12 : Déclencher les actions sur les recettes

**Files:**
- Modify: `sites/artisans-shared/src/views/RecipeDetail.vue`

- [ ] **Step 1 : Ajouter `recipe_view` au montage**

Dans `<script setup>`:

```js
import { useGamification } from '../composables/useGamification.js'
const { recordAction } = useGamification()

onMounted(async () => {
  // ... chargement existant ...
  if (recipe.value?.slug) {
    await recordAction('recipe_view', `recipe:${recipe.value.slug}`)
  }
})
```

- [ ] **Step 2 : Commit**

```bash
git add sites/artisans-shared/src/views/RecipeDetail.vue
git commit -m "feat(gamification): award XP on recipe view"
```

---

### Task 13 : Série de connexion et visite quotidienne

**Files:**
- Modify: `sites/api/routes/users.php:100-138`

- [ ] **Step 1 : Mettre à jour `user_auth` pour tracker la streak**

Après validation du token, ajouter avant le `echo json_encode`:

```php
require_once __DIR__ . '/../lib/Gamification.php';
gamificationUpdateStreak($pdo, (int)$user['id']);
```

- [ ] **Step 2 : Commit**

```bash
git add sites/api/routes/users.php
git commit -m "feat(gamification): track daily streak on auth"
```

---

### Task 14 : Tests API

**Files:**
- Modify: `scripts/test-api.sh`

- [ ] **Step 1 : Ajouter des tests gamification**

Ajouter à la fin du script:

```bash
echo "--- Test /users/me gamification ---"
USER_ME=$(curl -s -H "Authorization: Bearer $USER_TOKEN" "$BASE/users/me")
echo "$USER_ME" | jq -e '.data.level == 1' || fail "user level should be 1"
echo "$USER_ME" | jq -e '.data.xp >= 0' || fail "user xp should exist"

echo "--- Test /actions artisan_view ---"
ACTION=$(curl -s -X POST -H "Authorization: Bearer $USER_TOKEN" -H "Content-Type: application/json" \
  -d '{"action":"artisan_view","resource_key":"artisan:1"}' "$BASE/actions")
echo "$ACTION" | jq -e '.success == true' || fail "action should succeed"
echo "$ACTION" | jq -e '.data.xp_gained == 5' || fail "artisan_view should give 5 XP"

echo "--- Test cooldown ---"
ACTION2=$(curl -s -X POST -H "Authorization: Bearer $USER_TOKEN" -H "Content-Type: application/json" \
  -d '{"action":"artisan_view","resource_key":"artisan:1"}' "$BASE/actions")
echo "$ACTION2" | jq -e '.data.xp_gained == 0' || fail "duplicate action should give 0 XP"

echo "--- Test /avatars ---"
AVATARS=$(curl -s "$BASE/avatars?gender=male")
echo "$AVATARS" | jq -e '.success == true' || fail "avatars endpoint should succeed"
```

- [ ] **Step 2 : Commit**

```bash
git add scripts/test-api.sh
git commit -m "test(api): add gamification smoke tests"
```

---

### Task 15 : Migrations locales et validation

- [ ] **Step 1 : Appliquer la migration**

```bash
make migrate
```

- [ ] **Step 2 : Lancer les tests**

```bash
make test-api
```

Expected: all tests pass.

---

### Task 16 : Build et déploiement

- [ ] **Step 1 : Build Livry, Combs, VSD**

```bash
make deploy-all
```

- [ ] **Step 2 : Déployer l’API en prod**

```bash
cd sites/api && make push
```

- [ ] **Step 3 : Appliquer la migration en prod**

Via phpMyAdmin ou runner SQL temporaire (voir méthode utilisée précédemment).

- [ ] **Step 4 : Smoke tests prod**

```bash
curl -s https://artisans-combs.prigent.tech/profil
curl -s -H "Authorization: Bearer <token>" https://api.prigent.tech/users/me | jq '.data.level'
```

---

### Task 17 : Commit final

```bash
git status
git add -A
git commit -m "feat: gamification consumer v1.1.0" || true
```

---

## Self-Review Checklist

- [ ] Spec coverage : chaque section du design est couverte.
- [ ] Placeholder scan : aucun `TBD`, `TODO` ou code non fourni.
- [ ] Type consistency : `avatar_type`, `avatar_gender`, `level`, `xp` cohérents entre DB, API et frontend.
- [ ] Sécurité : l’upload est limité en taille et format, le dossier uploads isolé.
- [ ] Pas de conflit avec l’auth artisan existante.
