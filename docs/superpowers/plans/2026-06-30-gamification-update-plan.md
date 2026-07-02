# Gamification Update Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Update the existing gamification system to reward generic testimonial and game actions instead of recipes and spin, expose an HTTP XP event API, and refresh badges/titles.

**Architecture:** Modify `sites/api/lib/Gamification.php` constants and logic, replace old action calls in route files with new ones, add a `/api/gamification/xp` endpoint, and update the Vue gamification composable/profile components.

**Tech Stack:** PHP 8.4, MySQL 8, Vue 3 + Vite.

---

## File Structure

| File | Responsibility |
|------|----------------|
| `sites/api/lib/Gamification.php` | XP actions, badges, level/title logic |
| `sites/api/routes/gamification.php` | New `/api/gamification/*` endpoints |
| `sites/api/routes/artisans.php` | Replace review XP call with testimonial_post |
| `sites/api/routes/testimonials.php` | Add testimonial_view XP call |
| `sites/api/routes/games.php` | Add game_play and game_win XP calls |
| `sites/api/index.php` | Register gamification route |
| `sites/artisans-shared/src/composables/useGamification.js` | Frontend gamification helpers |
| `sites/artisans-shared/src/api.js` | API client for gamification endpoints |
| `sites/artisans-shared/src/views/UserProfile.vue` | Display updated badges/titles |
| `scripts/test-api.sh` | Smoke tests for gamification endpoints |

---

### Task 1: Update Gamification constants

**Files:**
- Modify: `sites/api/lib/Gamification.php`

- [ ] **Step 1: Replace XP_ACTIONS, BADGES, LEVEL_TITLES**

Replace the top constants in `Gamification.php` with:

```php
const XP_ACTIONS = [
    'artisan_view'         => ['xp' => 5,  'cooldown' => 'hourly', 'limit' => null],
    'testimonial_view'     => ['xp' => 3,  'cooldown' => 'daily',  'limit' => null],
    'testimonial_post'     => ['xp' => 25, 'cooldown' => 'once_per_resource', 'limit' => null],
    'game_play'            => ['xp' => 10, 'cooldown' => 'daily',  'limit' => null],
    'game_win'             => ['xp' => 20, 'cooldown' => 'daily',  'limit' => null],
    'share'                => ['xp' => 15, 'cooldown' => 'daily',  'limit' => 3],
    'daily_visit'          => ['xp' => 10, 'cooldown' => 'daily',  'limit' => 1, 'internal' => true],
    'streak_3days'         => ['xp' => 30, 'cooldown' => 'daily',  'limit' => 1, 'internal' => true],
];

const LEVEL_TITLES = [
    1  => 'Nouveau dans le quartier',
    3  => 'Explorateur local',
    5  => 'Habitué du marché',
    10 => 'Ambassadeur du terroir',
    20 => 'Légende du village',
];

const BADGES = [
    'first_visit'    => ['name' => 'Première visite',  'condition' => 'Visiter une fiche artisan.',        'target' => 1,   'action' => 'artisan_view'],
    'curieux'        => ['name' => 'Curieux',          'condition' => 'Lire 10 témoignages.',              'target' => 10,  'action' => 'testimonial_view'],
    'ambassadeur'    => ['name' => 'Ambassadeur',      'condition' => 'Publier 3 témoignages.',            'target' => 3,   'action' => 'testimonial_post'],
    'joueur'         => ['name' => 'Joueur',           'condition' => 'Jouer 10 fois.',                    'target' => 10,  'action' => 'game_play'],
    'vainqueur'      => ['name' => 'Vainqueur',        'condition' => 'Gagner 5 récompenses.',             'target' => 5,   'action' => 'game_win'],
    'chanceux'       => ['name' => 'Chanceux',         'condition' => 'Gagner 3 fois à la suite.',         'target' => 3,   'action' => 'game_win'],
    'generous'       => ['name' => 'Généreux',         'condition' => 'Partager 5 pages.',                 'target' => 5,   'action' => 'share'],
    'faithful'       => ['name' => 'Fidèle',           'condition' => '7 jours de connexion.',             'target' => 7,   'action' => 'daily_visit'],
];
```

- [ ] **Step 2: Run existing gamification tests**

```bash
./scripts/test-api.sh
```
Expected: existing tests may fail because old badges/XP actions are gone; this is expected until callers are updated.

- [ ] **Step 3: Commit**

```bash
git add sites/api/lib/Gamification.php
git commit -m "feat(gamification): update XP actions, badges and titles for testimonials and games"
```

---

### Task 2: Replace old XP calls in callers

**Files:**
- Modify: `sites/api/routes/artisans.php`
- Modify: `sites/api/routes/recipes.php`
- Modify: `sites/api/routes/spin.php`

- [ ] **Step 1: Update artisans.php review function**

Find `artisan_add_review` and replace the XP call:

```php
// After inserting review:
gamificationRecordAction($pdo, (int)$user['id'], 'testimonial_post', "artisan:$artisanId", ['artisan_id' => $artisanId]);
```

If the old review endpoint is kept for backward compatibility, map it to `testimonial_post` as well. If removed, skip.

- [ ] **Step 2: Update recipes.php view/create functions**

In `recipes_get`, replace:
```php
// Add at end of successful fetch, before echo:
if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    $usr = $pdo->prepare("SELECT id FROM local_users WHERE session_token = ? AND session_exp > NOW() LIMIT 1");
    $usr->execute([$token]);
    $uid = $usr->fetchColumn();
    if ($uid) {
        gamificationRecordAction($pdo, (int)$uid, 'testimonial_view', "recipe:{$recipe['id']}", ['recipe_id' => $recipe['id']]);
    }
}
```

In `recipes_create`, replace `recipe_suggest` or `recipe_create` XP with `testimonial_post` (only if user is authenticated; recipe create is currently anonymous).

- [ ] **Step 3: Update spin.php**

In `spin_play`, after successful win, replace:
```php
gamificationRecordAction($pdo, (int)$user['id'], 'game_play', "city:{$city['id']}", ['city_id' => $city['id']]);
```

- [ ] **Step 4: Commit**

```bash
git add sites/api/routes/artisans.php sites/api/routes/recipes.php sites/api/routes/spin.php
git commit -m "feat(gamification): wire testimonial and game XP actions in existing routes"
```

---

### Task 3: Add `/api/gamification` route

**Files:**
- Create: `sites/api/routes/gamification.php`
- Modify: `sites/api/index.php`

- [ ] **Step 1: Write route**

```php
<?php
/**
 * WebiArtisan API — Route : Gamification
 *
 * GET  /gamification/events
 * POST /gamification/xp
 * GET  /users/:id/xp
 * GET  /leaderboards/city/:city_id
 */

require_once __DIR__ . '/../lib/Gamification.php';

switch ($method) {
    case 'GET':
        if ($action === 'events' || $action === '') {
            gamification_events_list();
        } elseif (is_numeric($action) && $param === 'xp') {
            gamification_user_profile_endpoint($pdo, (int)$action);
        } elseif ($action === 'leaderboards' && $param === 'city' && is_numeric($segments[3] ?? '')) {
            gamification_city_leaderboard($pdo, (int)$segments[3]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($action === 'xp' || $action === '') {
            gamification_record_xp($pdo, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function gamification_events_list(): void
{
    $events = [];
    foreach (XP_ACTIONS as $key => $cfg) {
        $events[] = ['key' => $key, 'xp' => $cfg['xp'], 'cooldown' => $cfg['cooldown']];
    }
    echo json_encode(['success' => true, 'data' => $events]);
}

function gamification_user_profile_endpoint(PDO $pdo, int $userId): void
{
    $profile = gamificationUserProfile($pdo, $userId);
    if (!$profile) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Utilisateur non trouvé']);
        return;
    }
    echo json_encode(['success' => true, 'data' => $profile]);
}

function gamification_record_xp(PDO $pdo, array $body): void
{
    $user = user_require_auth($pdo);
    $actionKey = $body['action'] ?? '';
    $resourceKey = !empty($body['resource_key']) ? $body['resource_key'] : null;
    $metadata = !empty($body['metadata']) ? $body['metadata'] : null;

    if (!isset(XP_ACTIONS[$actionKey])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action inconnue']);
        return;
    }

    $result = gamificationRecordAction($pdo, (int)$user['id'], $actionKey, $resourceKey, $metadata);

    if ($result === null) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Action en cooldown ou limite atteinte']);
        return;
    }

    echo json_encode(['success' => true, 'data' => $result]);
}

function gamification_city_leaderboard(PDO $pdo, int $cityId): void
{
    $stmt = $pdo->prepare("
        SELECT u.id, u.display_name, u.avatar_url, u.level, u.xp
        FROM local_users u
        JOIN local_user_actions a ON a.user_id = u.id
        JOIN local_artisans ar ON ar.city_id = ?
        WHERE a.metadata->>'$.artisan_id' = ar.id OR a.metadata->>'$.city_id' = ?
        GROUP BY u.id
        ORDER BY u.level DESC, u.xp DESC
        LIMIT 50
    ");
    $stmt->execute([$cityId, $cityId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $items]);
}
```

- [ ] **Step 2: Register route**

In `sites/api/index.php`:
```php
case 'gamification':
    require_once __DIR__ . '/routes/gamification.php';
    break;
```

- [ ] **Step 3: Test**

```bash
curl -s "http://localhost:8080/api/gamification/events" | jq .
```
Expected: list includes `testimonial_view`, `testimonial_post`, `game_play`, `game_win`.

- [ ] **Step 4: Commit**

```bash
git add sites/api/routes/gamification.php sites/api/index.php
git commit -m "feat(api): add gamification HTTP endpoints"
```

---

### Task 4: Add gamification XP calls in testimonials and games routes

**Files:**
- Modify: `sites/api/routes/testimonials.php`
- Modify: `sites/api/routes/games.php`

- [ ] **Step 1: Add testimonial_view on detail/list**

In `testimonials_get`, after successful fetch and before echo, add:

```php
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);
if ($token) {
    $usr = $pdo->prepare("SELECT id FROM local_users WHERE session_token = ? AND session_exp > NOW() LIMIT 1");
    $usr->execute([$token]);
    $uid = $usr->fetchColumn();
    if ($uid) {
        gamificationRecordAction($pdo, (int)$uid, 'testimonial_view', "testimonial:$id", ['testimonial_id' => $id, 'artisan_id' => $item['artisan_id']]);
    }
}
```

- [ ] **Step 2: Add game_win in games_play**

In `games_play`, after recording play, if a coupon reward was won, add:

```php
if (!empty($result['reward'])) {
    gamificationRecordAction($pdo, (int)$user['id'], 'game_win', "game:$id", ['game_id' => $id, 'reward_id' => $result['reward']['id']]);
}
```

- [ ] **Step 3: Commit**

```bash
git add sites/api/routes/testimonials.php sites/api/routes/games.php
git commit -m "feat(gamification): record testimonial_view and game_win XP events"
```

---

### Task 5: Update frontend gamification API helpers

**Files:**
- Modify: `sites/artisans-shared/src/api.js`

- [ ] **Step 1: Add gamification functions**

Append:

```javascript
// --- Gamification --------------------------------------------------

export async function fetchGamificationEvents() {
  const res = await fetch(`${API_BASE}/gamification/events`)
  if (!res.ok) throw new Error('Erreur chargement événements XP')
  return res.json()
}

export async function fetchUserProfile(userId) {
  const res = await fetch(`${API_BASE}/users/${userId}/xp`, {
    headers: { ...userHeaders() },
  })
  if (!res.ok) throw new Error('Erreur chargement profil')
  return res.json()
}

export async function recordXpEvent(action, resourceKey = null, metadata = null) {
  const res = await fetch(`${API_BASE}/gamification/xp`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...userHeaders() },
    body: JSON.stringify({ action, resource_key: resourceKey, metadata }),
  })
  return res.json()
}

export async function fetchCityLeaderboard(cityId) {
  const res = await fetch(`${API_BASE}/leaderboards/city/${cityId}`)
  if (!res.ok) throw new Error('Erreur chargement classement')
  return res.json()
}
```

- [ ] **Step 2: Commit**

```bash
git add sites/artisans-shared/src/api.js
git commit -m "feat(front): add gamification API helpers"
```

---

### Task 6: Update useGamification composable

**Files:**
- Modify: `sites/artisans-shared/src/composables/useGamification.js`

- [ ] **Step 1: Read current file**

Run:
```bash
head -50 sites/artisans-shared/src/composables/useGamification.js
```

- [ ] **Step 2: Add record action helper**

Add to the composable:

```javascript
import { recordXpEvent } from '../api.js'

export function useGamification() {
  // existing code...

  async function recordAction(action, resourceKey, metadata) {
    try {
      const res = await recordXpEvent(action, resourceKey, metadata)
      if (res.success && res.data) {
        // Optionally show toast with xp_gained, level_up, new_badges
        return res.data
      }
    } catch (e) {
      console.error('Gamification action failed', e)
    }
    return null
  }

  return {
    // existing returns...
    recordAction,
  }
}
```

- [ ] **Step 3: Commit**

```bash
git add sites/artisans-shared/src/composables/useGamification.js
git commit -m "feat(front): add recordAction to gamification composable"
```

---

### Task 7: Update UserProfile/CharacterEdit badges display

**Files:**
- Modify: `sites/artisans-shared/src/views/UserProfile.vue`

- [ ] **Step 1: Ensure badges use keys from updated BADGES**

Verify the profile view iterates `profile.badges` and displays `badge.name`. The backend already maps `badge_key` to `BADGES[$badge_key]['name']` in `gamificationUserProfile`.

- [ ] **Step 2: Add missing badge icons (optional)**

If the view has hardcoded badge icons, update to handle new keys:

```javascript
const badgeIcons = {
  first_visit: '🏠',
  curieux: '👀',
  ambassadeur: '📣',
  joueur: '🎮',
  vainqueur: '🏆',
  chanceux: '🍀',
  generous: '🔗',
  faithful: '🔥',
}
```

- [ ] **Step 3: Commit**

```bash
git add sites/artisans-shared/src/views/UserProfile.vue
git commit -m "feat(front): update profile badge display for new gamification keys"
```

---

### Task 8: Trigger frontend XP events

**Files:**
- Modify: `sites/artisans-shared/src/views/Testimonials.vue`
- Modify: `sites/artisans-shared/src/views/GamePlay.vue`

- [ ] **Step 1: Record testimonial_view in feed**

In `Testimonials.vue`, when a testimonial card is opened/expanded or on detail view, call:

```javascript
import { useGamification } from '../composables/useGamification.js'
const { recordAction } = useGamification()

function onViewTestimonial(t) {
  recordAction('testimonial_view', `testimonial:${t.id}`, { testimonial_id: t.id, artisan_id: t.artisan_id })
}
```

- [ ] **Step 2: Record game_play in GamePlay**

In `GamePlay.vue`, in `onPlayed`:

```javascript
import { useGamification } from '../composables/useGamification.js'
const { recordAction } = useGamification()

function onPlayed(data) {
  recordAction('game_play', `game:${game.value.id}`, { game_id: game.value.id, artisan_id: game.value.artisan_id })
  if (data?.reward) {
    recordAction('game_win', `game:${game.value.id}`, { game_id: game.value.id, reward_id: data.reward.id })
  }
}
```

- [ ] **Step 3: Commit**

```bash
git add sites/artisans-shared/src/views/Testimonials.vue sites/artisans-shared/src/views/GamePlay.vue
git commit -m "feat(front): trigger testimonial_view and game_play XP events"
```

---

### Task 9: Update smoke tests

**Files:**
- Modify: `scripts/test-api.sh`

- [ ] **Step 1: Add gamification tests**

Append:

```bash
# ------------------------------------------------------------------
# Gamification
# ------------------------------------------------------------------
echo "== Gamification =="
assert_json "$BASE/gamification/events" "GET" "{}" "success" "true"
```

- [ ] **Step 2: Run tests**

```bash
./scripts/test-api.sh
```

- [ ] **Step 3: Commit**

```bash
git add scripts/test-api.sh
git commit -m "test(api): add gamification events smoke test"
```

---

## Self-Review Checklist

1. **Spec coverage:**
   - Remove recipe/spin actions, add testimonial/game actions → Task 1
   - Update callers → Task 2
   - HTTP XP API → Task 3
   - testimonial_view and game_win triggers → Task 4
   - Frontend helpers and profile → Tasks 5-7
   - Frontend event triggers → Task 8

2. **Placeholder scan:** no TBD/TODO.

3. **Type consistency:** action keys (`testimonial_view`, `testimonial_post`, `game_play`, `game_win`) match exactly in PHP constants, route calls, and Vue triggers.
