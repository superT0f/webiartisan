# Galerie photo communautaire POI — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Galerie photo communautaire dans les POI (upload joueur + conversion GD, signalement + modération admin, onglets Photos/Détails dans la fiche POI, cadeau inventaire à la validation).

**Architecture:** Table `local_poi_photos` + `local_poi_photo_reports` (migration 049 auto-appliquée), endpoints dans `routes/pois.php` (joueur) et `routes/admin.php` (modération, pattern `moderation/recipes`), front : onglets dans `PoiSheet.vue`, section signalements dans `AdminPois.vue`.

**Tech Stack:** PHP 8.4 + PDO/MySQL (GD), Vue 3 + Vite, Vitest, tests PHP via `make test-php`.

## Global Constraints

- Tests PHP : `export COMPOSE_PROJECT_NAME=webiartisanfix && make test-php FILE=<fichier>` depuis `webiartisan.new/`. Purger le rate limit `login` avant les suites HTTP : `docker compose exec -T php php -r '$pdo = new PDO("mysql:host=mysql;dbname=".getenv("DB_NAME"), getenv("DB_USER"), getenv("DB_PASS")); $pdo->exec("DELETE FROM api_rate_limits WHERE endpoint = \"login\"");'`
- Vitest : `npm test` dans `sites/artisans-shared` (56 tests au vert actuellement).
- Build : `npm run build` dans `sites/webiartisan-livry`.
- Deploy : `make -C sites/api push` puis `make -C sites/<ville> push` pour les 4 villes (`webiartisan-livry`, `webiartisan-combs`, `webiartisan-vert-saint-denis`, `webiartisan-lieusaint`). **Vérifier `curl -s -o /dev/null -w "%{http_code}" https://api.prigent.tech/api/health` = 200 après chaque push API.**
- Migrations : uniquement additives, dans `lib/Migrations.php` (jamais de DROP).
- Auth joueur : `user_require_auth($pdo)` (lib/UserAuth.php) ; admin : `artisan_require_auth` + `is_admin` (routes/admin.php le fait déjà en tête de fichier).
- Spec : `docs/superpowers/specs/2026-07-27-poi-photo-gallery-design.md`.

---

### Task 1: Migration 049 (tables + colonne inventaire)

**Files:**
- Modify: `sites/api/lib/Migrations.php` (array `$migrations`)
- Create: `sites/api/migrations/049_poi_photos.sql`

**Interfaces:**
- Produces: tables `local_poi_photos(id, poi_id, user_id, file_path, status ENUM('active','validated','hidden','deleted'), gifted_at, created_at)`, `local_poi_photo_reports(id, photo_id, user_id, created_at, UNIQUE(photo_id,user_id))`, colonne `local_user_inventory.source_label VARCHAR(120) NULL`.

- [ ] **Step 1: Ajouter la migration à l'auto-migrate**

Dans `sites/api/lib/Migrations.php`, ajouter après l'entrée `'047_settings'` :

```php
        '049_poi_photos' => [
            "CREATE TABLE IF NOT EXISTS local_poi_photos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                poi_id INT NOT NULL,
                user_id INT NOT NULL,
                file_path VARCHAR(190) NOT NULL,
                status ENUM('active','validated','hidden','deleted') NOT NULL DEFAULT 'active',
                gifted_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_poi_status (poi_id, status),
                INDEX idx_user_created (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS local_poi_photo_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                photo_id INT NOT NULL,
                user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_report (photo_id, user_id),
                INDEX idx_photo (photo_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "ALTER TABLE local_user_inventory
                ADD COLUMN source_label VARCHAR(120) NULL AFTER source_object_id",
        ],
```

- [ ] **Step 2: Fichier SQL de référence**

Créer `sites/api/migrations/049_poi_photos.sql` avec le même DDL (commentaire d'en-tête : appliquée automatiquement par lib/Migrations.php).

- [ ] **Step 3: Vérifier l'application automatique**

Run: `export COMPOSE_PROJECT_NAME=webiartisanfix && make test-php FILE=test_ops.php 2>&1 | grep "health migrations"`
Expected: `047_settings` ET `049_poi_photos` présents dans les migrations du health check.

- [ ] **Step 4: Commit**

```bash
git add sites/api/lib/Migrations.php sites/api/migrations/049_poi_photos.sql
git commit -m "feat(gallery): migration 049 — tables photos POI + source_label inventaire"
```

---

### Task 2: Upload joueur avec conversion GD (`POST /pois/:id/photos`) + liste publique

**Files:**
- Modify: `sites/api/routes/pois.php`
- Create: `sites/api/lib/ImageResize.php`

**Interfaces:**
- Consumes: migration 049 (Task 1), `user_require_auth` (lib/UserAuth.php), `POI_IMAGE_MAX_BYTES`/`POI_IMAGE_MIMES` (déjà dans pois.php).
- Produces: `poi_photo_resize(string $srcPath, string $mime, string $destPath): bool` (≤1600 px, JPEG q80) ; endpoints `GET /pois/:id/photos` et `POST /pois/:id/photos`. Constantes `POI_GALLERY_MAX_PER_DAY = 10`, `POI_GALLERY_MAX_PER_POI = 30`, `POI_GALLERY_MAX_WIDTH = 1600`.

- [ ] **Step 1: Créer `sites/api/lib/ImageResize.php`**

```php
<?php
/**
 * Conversion basse résolution des photos communautaires (GD) :
 * ≤ 1600 px de large, ré-encodage JPEG q80 (~200-400 Ko).
 */

function poi_photo_resize(string $srcPath, string $mime, string $destPath): bool
{
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($srcPath),
        'image/png'  => @imagecreatefrompng($srcPath),
        'image/webp' => @imagecreatefromwebp($srcPath),
        default      => false,
    };
    if (!$src) return false;

    $w = imagesx($src);
    $h = imagesy($src);
    if ($w > 1600) {
        $dst = imagescale($src, 1600);
        imagedestroy($src);
        if (!$dst) return false;
        $src = $dst;
    }
    $ok = imagejpeg($src, $destPath, 80);
    imagedestroy($src);
    return $ok;
}
```

- [ ] **Step 2: Routing dans `sites/api/routes/pois.php`**

En-tête requires, ajouter :
```php
require_once __DIR__ . '/../lib/UserAuth.php';
require_once __DIR__ . '/../lib/ImageResize.php';
```

Constantes après `POI_IMAGE_MIMES` :
```php
const POI_GALLERY_MAX_PER_DAY = 10;
const POI_GALLERY_MAX_PER_POI = 30;
```

Dans le `case 'GET':`, ajouter avant le `else` 404 :
```php
        } elseif (filter_var($action, FILTER_VALIDATE_INT) !== false && $param === 'photos') {
            pois_list_photos($pdo, (int)$action);
```

Dans le `case 'POST':`, ajouter :
```php
        } elseif (filter_var($action, FILTER_VALIDATE_INT) !== false && $param === 'photos') {
            pois_upload_photo($pdo, (int)$action);
        } elseif ($action === 'photos' && filter_var($param, FILTER_VALIDATE_INT) !== false && ($segments[3] ?? '') === 'report') {
            pois_report_photo($pdo, (int)$param);
```

Dans le `case 'DELETE':`, ajouter :
```php
        } elseif ($action === 'photos' && filter_var($param, FILTER_VALIDATE_INT) !== false) {
            pois_delete_photo($pdo, (int)$param);
```

- [ ] **Step 3: Fonctions `pois_list_photos` + `pois_upload_photo`** (à la fin de pois.php)

```php
function pois_list_photos(PDO $pdo, int $poiId): void
{
    $userId = function_exists('user_optional_auth') ? user_optional_auth($pdo) : null;
    $stmt = $pdo->prepare("
        SELECT id, file_path, user_id, created_at
        FROM local_poi_photos
        WHERE poi_id = ? AND status IN ('active','validated')
        ORDER BY id DESC
        LIMIT 60
    ");
    $stmt->execute([$poiId]);
    $photos = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $photos[] = [
            'id'         => (int)$row['id'],
            'url'        => $row['file_path'],
            'mine'       => $userId !== null && (int)$row['user_id'] === (int)$userId,
            'created_at' => $row['created_at'],
        ];
    }
    echo json_encode(['success' => true, 'data' => $photos]);
}

function pois_upload_photo(PDO $pdo, int $poiId): void
{
    $user = user_require_auth($pdo);
    $userId = (int)$user['id'];

    $poiStmt = $pdo->prepare("SELECT id FROM local_pois WHERE id = ? AND is_active = 1");
    $poiStmt->execute([$poiId]);
    if (!$poiStmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'POI introuvable']);
        return;
    }

    $dayCount = (int)$pdo->query("SELECT COUNT(*) FROM local_poi_photos WHERE user_id = $userId AND created_at >= CURDATE()")->fetchColumn();
    if ($dayCount >= POI_GALLERY_MAX_PER_DAY) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'rate_limited', 'message' => 'Maximum 10 photos par jour — reviens demain !']);
        return;
    }
    $poiCount = (int)$pdo->query("SELECT COUNT(*) FROM local_poi_photos WHERE poi_id = $poiId AND status IN ('active','validated')")->fetchColumn();
    if ($poiCount >= POI_GALLERY_MAX_PER_POI) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'poi_full', 'message' => 'Ce lieu a déjà assez de photos, merci !']);
        return;
    }

    $file = $_FILES['image'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'missing_file', 'message' => 'Aucune image reçue']);
        return;
    }
    if ($file['size'] > POI_IMAGE_MAX_BYTES) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'too_large', 'message' => 'Image trop lourde (5 Mo max)']);
        return;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(POI_IMAGE_MIMES[$mime])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'bad_mime', 'message' => 'Formats acceptés : JPEG, PNG, WebP']);
        return;
    }

    $dir = __DIR__ . '/../uploads/pois/gallery';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }
    $filename = sprintf('poi%d_u%d_%s.jpg', $poiId, $userId, bin2hex(random_bytes(6)));
    if (!poi_photo_resize($file['tmp_name'], $mime, "$dir/$filename")) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'bad_image', 'message' => 'Image illisible']);
        return;
    }

    $path = '/uploads/pois/gallery/' . $filename;
    $pdo->prepare("INSERT INTO local_poi_photos (poi_id, user_id, file_path) VALUES (?, ?, ?)")
        ->execute([$poiId, $userId, $path]);
    $id = (int)$pdo->lastInsertId();
    app_log('info', '[POI-PHOTOS] upload', ['poi_id' => $poiId, 'user_id' => $userId, 'photo_id' => $id]);
    http_response_code(201);
    echo json_encode(['success' => true, 'data' => ['id' => $id, 'url' => $path]]);
}
```

- [ ] **Step 4: Commit**

```bash
git add sites/api/lib/ImageResize.php sites/api/routes/pois.php
git commit -m "feat(gallery): upload photo POI joueur (GD 1600px, 10/j, 30/POI) + liste publique"
```

---

### Task 3: Suppression (auteur/admin) + signalement

**Files:**
- Modify: `sites/api/routes/pois.php`

**Interfaces:**
- Produces: `pois_delete_photo(PDO $pdo, int $photoId)`, `pois_report_photo(PDO $pdo, int $photoId)` ; helper `pois_is_admin(PDO $pdo, int $userId): bool`.

- [ ] **Step 1: Fonctions** (fin de pois.php)

```php
function pois_is_admin(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("
        SELECT 1 FROM local_artisans a
        WHERE a.is_admin = 1 AND a.status = 'active'
          AND (a.user_id = ? OR a.email = (SELECT email FROM local_users WHERE id = ?))
        LIMIT 1
    ");
    $stmt->execute([$userId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function pois_delete_photo(PDO $pdo, int $photoId): void
{
    $user = user_require_auth($pdo);
    $userId = (int)$user['id'];
    $stmt = $pdo->prepare("SELECT * FROM local_poi_photos WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$photo) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Photo introuvable']);
        return;
    }
    if ((int)$photo['user_id'] !== $userId && !pois_is_admin($pdo, $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Seul l\'auteur (ou un admin) peut supprimer cette photo']);
        return;
    }
    $pdo->prepare("UPDATE local_poi_photos SET status = 'deleted' WHERE id = ?")->execute([$photoId]);
    $file = __DIR__ . '/../uploads/pois/gallery/' . basename($photo['file_path']);
    if (is_file($file)) @unlink($file);
    echo json_encode(['success' => true, 'data' => ['deleted' => $photoId]]);
}

function pois_report_photo(PDO $pdo, int $photoId): void
{
    $user = user_require_auth($pdo);
    $userId = (int)$user['id'];
    $exists = $pdo->prepare("SELECT 1 FROM local_poi_photos WHERE id = ? AND status IN ('active','validated')");
    $exists->execute([$photoId]);
    if (!$exists->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Photo introuvable']);
        return;
    }
    try {
        $pdo->prepare("INSERT INTO local_poi_photo_reports (photo_id, user_id) VALUES (?, ?)")
            ->execute([$photoId, $userId]);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1062) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'already_reported', 'message' => 'Déjà signalée, merci !']);
            return;
        }
        throw $e;
    }
    app_log('info', '[POI-PHOTOS] report', ['photo_id' => $photoId, 'user_id' => $userId]);
    echo json_encode(['success' => true, 'data' => ['reported' => $photoId]]);
}
```

- [ ] **Step 2: Commit**

```bash
git add sites/api/routes/pois.php
git commit -m "feat(gallery): suppression (auteur/admin) + signalement unique"
```

---

### Task 4: Modération admin (`/admin/moderation/photos`) + cadeau de validation

**Files:**
- Modify: `sites/api/routes/admin.php`

**Interfaces:**
- Consumes: pattern dispatch existant (`$action === 'moderation'`, `$param`, `$subAction`, `$segments[4]`), `OBJECT_TYPES` (lib/WorldObjects.php).
- Produces: `GET /admin/moderation/photos`, `POST /admin/moderation/photos/{id}/{keep|hide|delete}`.

- [ ] **Step 1: Requires + dispatch**

En tête d'admin.php, ajouter `require_once __DIR__ . '/../lib/WorldObjects.php';` et `require_once __DIR__ . '/../lib/AppLogger.php';` si absents. Dans le dispatch (après le bloc testimonials), ajouter :

```php
} elseif ($action === 'moderation' && $param === 'photos' && $method === 'GET' && $subAction === '') {
    admin_moderation_photos($pdo);
} elseif ($action === 'moderation' && $param === 'photos' && $method === 'POST' && is_numeric($subAction) && in_array($segments[4] ?? '', ['keep','hide','delete'], true)) {
    admin_moderation_photo_review($pdo, (int)$subAction, $segments[4]);
```

- [ ] **Step 2: Fonctions** (fin d'admin.php)

```php
function admin_moderation_photos(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT ph.id, ph.file_path, ph.status, ph.created_at, ph.poi_id,
               p.name AS poi_name,
               (SELECT COUNT(*) FROM local_poi_photo_reports r WHERE r.photo_id = ph.id) AS reports
        FROM local_poi_photos ph
        JOIN local_pois p ON p.id = ph.poi_id
        WHERE ph.status IN ('active','validated')
          AND EXISTS (SELECT 1 FROM local_poi_photo_reports r WHERE r.photo_id = ph.id)
        ORDER BY reports DESC, ph.created_at ASC
        LIMIT 100
    ");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function admin_moderation_photo_review(PDO $pdo, int $id, string $action): void
{
    $stmt = $pdo->prepare("SELECT * FROM local_poi_photos WHERE id = ? AND status IN ('active','validated')");
    $stmt->execute([$id]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$photo) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Photo introuvable']);
        return;
    }

    if ($action === 'keep') {
        $pdo->prepare("UPDATE local_poi_photos SET status = 'validated' WHERE id = ?")->execute([$id]);
        // Cadeau pour l'uploadeur, une seule fois par photo
        $gifted = false;
        if ($photo['gifted_at'] === null) {
            $pdo->prepare("
                INSERT INTO local_user_inventory (user_id, object_type, source_object_id, source_label)
                VALUES (?, 'energy_store', NULL, 'Merci pour la photo, ami artisan')
            ")->execute([(int)$photo['user_id']]);
            $pdo->prepare("UPDATE local_poi_photos SET gifted_at = NOW() WHERE id = ?")->execute([$id]);
            $gifted = true;
        }
        app_log('info', '[POI-PHOTOS] keep', ['photo_id' => $id, 'gifted' => $gifted]);
        echo json_encode(['success' => true, 'data' => ['validated' => $id, 'gifted' => $gifted]]);
        return;
    }

    if ($action === 'hide') {
        $pdo->prepare("UPDATE local_poi_photos SET status = 'hidden' WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'data' => ['hidden' => $id]]);
        return;
    }

    // delete
    $pdo->prepare("UPDATE local_poi_photos SET status = 'deleted' WHERE id = ?")->execute([$id]);
    $file = __DIR__ . '/../uploads/pois/gallery/' . basename($photo['file_path']);
    if (is_file($file)) @unlink($file);
    app_log('info', '[POI-PHOTOS] delete', ['photo_id' => $id]);
    echo json_encode(['success' => true, 'data' => ['deleted' => $id]]);
}
```

- [ ] **Step 3: Commit**

```bash
git add sites/api/routes/admin.php
git commit -m "feat(gallery): modération admin (keep+hide+delete) + cadeau inventaire à la validation"
```

---

### Task 5: Tests API `test_poi_photos.php`

**Files:**
- Create: `sites/api/tests/test_poi_photos.php`

**Interfaces:**
- Consumes: tasks 1-4, pattern `api()`/`check()` de test_boss.php, création d'image de test GD.

- [ ] **Step 1: Écrire le test**

```php
<?php
/**
 * WebiArtisan — Tests galerie photo POI (migration 049).
 * Run: make test-php FILE=test_poi_photos.php
 */

require_once __DIR__ . '/../config/database.php';

$apiBase = rtrim(getenv('API_BASE_URL') ?: 'http://nginx/api', '/');
$pdo = getDatabase();

function check(string $name, bool $cond, string $detail = ''): void {
    echo ($cond ? 'OK' : 'FAIL') . ": $name" . ($detail ? " — $detail" : '') . "\n";
    if (!$cond) exit(1);
}

function api(string $method, string $path, $body = null, ?string $token = null, bool $artisan = false): array {
    $ch = curl_init(rtrim($GLOBALS['apiBase'], '/') . $path);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = [];
    if (is_array($body)) $headers[] = 'Content-Type: application/json';
    if ($token) $headers[] = $artisan ? 'X-Artisan-Token: ' . $token : 'Authorization: Bearer ' . $token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $code, 'json' => json_decode($res, true)];
}

// Image JPEG 2000x1200 générée en GD
$img = imagecreatetruecolor(2000, 1200);
imagefill($img, 0, 0, imagecolorallocate($img, 200, 100, 50));
$tmp = tempnam(sys_get_temp_dir(), 'gal') . '.jpg';
imagejpeg($img, $tmp, 90);
imagedestroy($img);

function upload(string $path, string $filePath, string $token): array {
    $ch = curl_init(rtrim($GLOBALS['apiBase'], '/') . $path);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => new CURLFile($filePath, 'image/jpeg', 'photo.jpg')]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $code, 'json' => json_decode($res, true)];
}

// Joueur + POI de test
$email = 'gallery-' . time() . '@example.com';
api('POST', '/users/register', ['email' => $email, 'password' => 'Password123!']);
$r = api('POST', '/users/login', ['email' => $email, 'password' => 'Password123!']);
$token = $r['json']['token'] ?? null;
check('login joueur', $token !== null);
$userId = (int)$pdo->query("SELECT id FROM local_users WHERE email = '$email'")->fetchColumn();
$cityId = (int)$pdo->query("SELECT id FROM local_cities WHERE slug = 'livry'")->fetchColumn();
$pdo->prepare("INSERT INTO local_pois (city_id, name, type, latitude, longitude, is_active) VALUES (?, 'POI galerie test', 'autre', 49.1081, -0.7658, 1)")->execute([$cityId]);
$poiId = (int)$pdo->lastInsertId();

// 1. Auth requise
$r = upload("/pois/$poiId/photos", $tmp, 'mauvais-token');
check('upload sans token → 401', $r['status'] === 401, (string)$r['status']);

// 2. Upload OK + resize ≤1600 px
$r = upload("/pois/$poiId/photos", $tmp, $token);
check('upload 201', $r['status'] === 201, json_encode($r['json']));
$photoId = (int)($r['json']['data']['id'] ?? 0);
$stored = __DIR__ . '/../uploads/pois/gallery/' . basename($r['json']['data']['url'] ?? '');
$size = is_file($stored) ? getimagesize($stored) : null;
check('resize ≤ 1600 px', $size && $size[0] <= 1600, $size ? "{$size[0]}x{$size[1]}" : 'fichier absent');

// 3. Liste publique
$r = api('GET', "/pois/$poiId/photos");
check('liste 200 + 1 photo', $r['status'] === 200 && count($r['json']['data'] ?? []) === 1, json_encode($r['json']));

// 4. Signalement + unicité
$r = api('POST', "/pois/photos/$photoId/report", [], $token);
check('report 200', $r['status'] === 200, json_encode($r['json']));
$r = api('POST', "/pois/photos/$photoId/report", [], $token);
check('double report → 409', $r['status'] === 409);

// 5. Modération admin : keep → validated + cadeau 1×
$adminToken = trim((string)$pdo->query("SELECT token FROM local_artisan_sessions ORDER BY id DESC LIMIT 1")->fetchColumn());
check('token admin récupéré', $adminToken !== '', 'via session artisan existante');
$r = api('POST', "/admin/moderation/photos/$photoId/keep", [], $adminToken, true);
check('keep 200 + gifted', $r['status'] === 200 && ($r['json']['data']['gifted'] ?? null) === true, json_encode($r['json']));
$gift = $pdo->query("SELECT object_type, source_label FROM local_user_inventory WHERE user_id = $userId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('cadeau energy_store + message', $gift && $gift['object_type'] === 'energy_store' && $gift['source_label'] === 'Merci pour la photo, ami artisan', json_encode($gift));
$r = api('POST', "/admin/moderation/photos/$photoId/keep", [], $adminToken, true);
check('2e keep → pas de 2e cadeau', $r['status'] === 200 && ($r['json']['data']['gifted'] ?? null) === false);

// 6. File de modération
$r = api('GET', '/admin/moderation/photos', null, $adminToken, true);
check('file modération 200', $r['status'] === 200 && count($r['json']['data'] ?? []) >= 1);

// 7. Suppression par l'auteur
$r = api('DELETE', "/pois/photos/$photoId", null, $token);
check('delete auteur 200', $r['status'] === 200);
check('photo retirée de la liste', count(api('GET', "/pois/$poiId/photos")['json']['data'] ?? []) === 0);

// 8. Rate limit 10/j (compte direct en base pour éviter 9 uploads)
$pdo->exec("INSERT INTO local_poi_photos (poi_id, user_id, file_path) SELECT $poiId, $userId, '/uploads/pois/gallery/fake.jpg' FROM dual LIMIT 0");
for ($i = 0; $i < 10; $i++) {
    $pdo->prepare("INSERT INTO local_poi_photos (poi_id, user_id, file_path, created_at) VALUES (?, ?, '/uploads/pois/gallery/fake.jpg', NOW())")->execute([$poiId, $userId]);
}
$r = upload("/pois/$poiId/photos", $tmp, $token);
check('11e photo du jour → 422 rate_limited', $r['status'] === 422 && ($r['json']['error'] ?? '') === 'rate_limited', json_encode($r['json']));

// 9. Plafond 30/POI (compte direct)
$pdo->exec("DELETE FROM local_poi_photos WHERE poi_id = $poiId");
for ($i = 0; $i < 30; $i++) {
    $pdo->prepare("INSERT INTO local_poi_photos (poi_id, user_id, file_path, created_at) VALUES (?, ?, '/uploads/pois/gallery/fake.jpg', DATE_SUB(NOW(), INTERVAL 2 DAY))")->execute([$poiId, $userId]);
}
$r = upload("/pois/$poiId/photos", $tmp, $token);
check('31e photo du POI → 422 poi_full', $r['status'] === 422 && ($r['json']['error'] ?? '') === 'poi_full');

@unlink($tmp);
echo "OK: tous les tests galerie passent\n";
```

NB pour l'exécuteur : si aucune `local_artisan_sessions` admin n'existe dans l'env de test, en créer une via PDO (artisan `is_admin=1` + session) avant l'étape 5 — pattern identique à `test_boss.php` (section admin).

- [ ] **Step 2: Run**

Run: `export COMPOSE_PROJECT_NAME=webiartisanfix && make test-php FILE=test_poi_photos.php`
Expected: toutes les lignes OK, fin `OK: tous les tests galerie passent`

- [ ] **Step 3: Régressions**

Run: purge rate limit `login` (voir Global Constraints), puis `make test-php FILE=test_poi_images.php` et `make test-php FILE=test_inventory.php`
Expected: que des OK.

- [ ] **Step 4: Commit**

```bash
git add sites/api/tests/test_poi_photos.php
git commit -m "test(gallery): 15 cas — upload/resize, rate limit, plafond, report, modération, cadeau"
```

---

### Task 6: Front — api.js + PoiSheet à onglets

**Files:**
- Modify: `sites/artisans-shared/src/api.js`
- Modify: `sites/artisans-shared/src/components/PoiSheet.vue`
- Modify: `sites/artisans-shared/src/views/MapView.vue` (passer `is-admin` à PoiSheet)

**Interfaces:**
- Consumes: `userHeaders()` (api.js), `uploadPoiImage(token, poiId, file)` existant (couverture admin), `getArtisanToken()` (api.js), `pickImage` (utils/flutterBridge.js, retourne `{base64, mimeType, name}`), `isFlutterApp` (flutterBridge.js).
- Produces: `getPoiPhotos(poiId)`, `uploadPoiPhoto(poiId, file)`, `deletePoiPhoto(photoId)`, `reportPoiPhoto(photoId)`, `fetchAdminPhotoReports(token)`, `reviewPhotoReport(token, photoId, action)`.

- [ ] **Step 1: api.js** (après `deletePoiImage`)

```js
export async function getPoiPhotos(poiId) {
  return requestJson(`${API_BASE}/pois/${poiId}/photos`, { headers: { ...userHeaders() } }, 'Impossible de charger les photos.')
}

export async function uploadPoiPhoto(poiId, file) {
  const form = new FormData()
  form.append('image', file)
  return requestJson(`${API_BASE}/pois/${poiId}/photos`, {
    method: 'POST', headers: { ...userHeaders() }, body: form,
  }, 'Envoi de la photo impossible')
}

export async function deletePoiPhoto(photoId) {
  return requestJson(`${API_BASE}/pois/photos/${photoId}`, {
    method: 'DELETE', headers: { ...userHeaders() },
  }, 'Suppression impossible')
}

export async function reportPoiPhoto(photoId) {
  return requestJson(`${API_BASE}/pois/photos/${photoId}/report`, {
    method: 'POST', headers: { 'Content-Type': 'application/json', ...userHeaders() }, body: JSON.stringify({}),
  }, 'Signalement impossible')
}

export async function fetchAdminPhotoReports(token) {
  return requestJson(`${API_BASE}/admin/moderation/photos`, { headers: artisanHeaders(token) }, 'Erreur chargement des signalements')
}

export async function reviewPhotoReport(token, photoId, action) {
  return requestJson(`${API_BASE}/admin/moderation/photos/${photoId}/${action}`, {
    method: 'POST', headers: { 'Content-Type': 'application/json', ...artisanHeaders(token) }, body: JSON.stringify({}),
  }, 'Action impossible')
}
```

- [ ] **Step 2: PoiSheet.vue — onglets Photos / Détails**

Script (remplace le bloc `<script setup>` actuel en conservant `typeLabel`, `scheduleLine`, `checkinLabel`, `checkinDisabled`) :

```js
import { ref, computed, watch } from 'vue'
import {
  getPoiPhotos, uploadPoiPhoto, deletePoiPhoto, reportPoiPhoto,
  uploadPoiImage, getArtisanToken, getUserToken,
} from '../api.js'
import { pickImage, isFlutterApp } from '../utils/flutterBridge.js'

const props = defineProps({
  poi: { type: Object, default: null },
  checkinState: { type: Object, default: null },
  authenticated: { type: Boolean, default: false },
  isAdmin: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'navigate', 'checkin', 'toast'])

const tab = ref('photos')
const photos = ref([])
const uploading = ref(false)
const reportedIds = ref(new Set())
const fileInput = ref(null)
// ... (conserver typeLabel / scheduleLine / checkinLabel / checkinDisabled existants)

watch(() => props.poi?.id, async (id) => {
  tab.value = 'photos'
  photos.value = []
  reportedIds.value = new Set()
  if (!id) return
  const res = await getPoiPhotos(id)
  if (res.success) photos.value = res.data || []
}, { immediate: true })

function base64ToFile({ base64, mimeType, name }) {
  const bin = atob(base64)
  const bytes = new Uint8Array(bin.length)
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i)
  return new File([bytes], name || 'photo.jpg', { type: mimeType || 'image/jpeg' })
}

async function onAddPhoto() {
  if (!props.authenticated) { emit('toast', 'Connecte-toi pour ajouter une photo'); return }
  let file = null
  if (isFlutterApp()) {
    try { file = base64ToFile(await pickImage({ source: 'gallery', quality: 90, maxWidth: 2000 })) }
    catch (e) { if (e?.code !== 'cancelled') emit('toast', 'Photo non récupérée'); return }
  } else {
    fileInput.value?.click()
    return
  }
  await doUpload(file)
}

async function onFilePicked(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (file) await doUpload(file)
}

async function doUpload(file) {
  if (uploading.value) return
  uploading.value = true
  const res = await uploadPoiPhoto(props.poi.id, file)
  uploading.value = false
  if (res.success) {
    photos.value.unshift({ id: res.data.id, url: res.data.url, mine: true, created_at: new Date().toISOString() })
    emit('toast', '📷 Photo publiée, merci !')
  } else {
    emit('toast', res.message || res.error || 'Envoi impossible')
  }
}

async function onDeletePhoto(photo) {
  const res = await deletePoiPhoto(photo.id)
  if (res.success) photos.value = photos.value.filter(p => p.id !== photo.id)
  else emit('toast', res.error || 'Suppression impossible')
}

async function onReportPhoto(photo) {
  const res = await reportPoiPhoto(photo.id)
  if (res.success || res.status === 409) {
    reportedIds.value = new Set([...reportedIds.value, photo.id])
    emit('toast', 'Photo signalée, merci — un admin va regarder')
  } else {
    emit('toast', res.error || 'Signalement impossible')
  }
}

async function onCoverUpload(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return
  const token = getArtisanToken()
  const res = await uploadPoiImage(token, props.poi.id, file)
  emit('toast', res.success ? '✅ Couverture mise à jour' : (res.message || res.error || 'Envoi impossible'))
}
```

Template : onglets `Photos | Détails` en haut de `.sheet` ; onglet photos = carousel `<div class="gallery">` (cover `poi.image_url` en premier, puis `photos`), chaque slide avec img + boutons 🚩 (disabled si dans `reportedIds`) et 🗑️ si `p.mine` ; boutons d'action : `📷 Ajouter une photo` (input file caché pour web + input caché couverture admin), `📷 Couverture` si `isAdmin` ; onglet détails = horaires/adresse/téléphone (markup actuel déplacé) ; la section check-in reste en bas hors onglets. États vides : « Sois le premier à photographier ce lieu ! ».

CSS ajouté :
```css
.tabs { display: flex; gap: 8px; margin-bottom: 12px; }
.tabs button { flex: 1; padding: 8px; border: none; border-radius: 999px; background: #f1f5f9; font-weight: 600; cursor: pointer; }
.tabs button.active { background: var(--c-green); color: #fff; }
.gallery { display: flex; gap: 10px; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 8px; }
.gallery-item { position: relative; flex: 0 0 78%; scroll-snap-align: center; }
.gallery-item img { width: 100%; max-height: 220px; object-fit: cover; border-radius: 12px; display: block; }
.gallery-actions { position: absolute; top: 8px; right: 8px; display: flex; gap: 6px; }
.gallery-actions button { background: rgba(255,255,255,0.9); border: none; border-radius: 999px; padding: 4px 8px; cursor: pointer; }
```

- [ ] **Step 3: MapView.vue**

Passer `:is-admin="isAdmin"` à `<PoiSheet>` et ajouter `@toast="showToast"` (showToast existe déjà via useGamification).

- [ ] **Step 4: Build + vitest**

Run: `npm run build` (sites/webiartisan-livry) → ✓ built ; `npm test` (sites/artisans-shared) → 56 passed.

- [ ] **Step 5: Commit**

```bash
git add sites/artisans-shared/src/api.js sites/artisans-shared/src/components/PoiSheet.vue sites/artisans-shared/src/views/MapView.vue
git commit -m "feat(gallery): PoiSheet à onglets — carousel photos, upload, signalement, couverture admin"
```

---

### Task 7: Front — section signalements dans AdminPois.vue

**Files:**
- Modify: `sites/artisans-shared/src/views/AdminPois.vue`

**Interfaces:**
- Consumes: `fetchAdminPhotoReports`, `reviewPhotoReport` (Task 6), `getArtisanToken`.

- [ ] **Step 1: Section « 🚩 Signalements photos » en haut de la vue**

Charger au `onMounted` via `fetchAdminPhotoReports(getArtisanToken())`. Pour chaque ligne : miniature (`file_path` via `resolveAvatarUrl` ou préfixe API), nom du POI, nb de signalements, date ; boutons **Garder** (keep), **Masquer** (hide), **Supprimer** (delete) → `reviewPhotoReport(token, id, action)` puis retrait de la liste. État vide : « Aucun signalement 🎉 ».

- [ ] **Step 2: Build + commit**

```bash
npm run build # sites/webiartisan-livry
git add sites/artisans-shared/src/views/AdminPois.vue
git commit -m "feat(gallery): file de modération des photos signalées dans /espace/admin/pois"
```

---

### Task 8: Inventaire — afficher `source_label`

**Files:**
- Modify: `sites/api/routes/inventory.php` (`inventory_list` : ajouter `source_label` au SELECT et à la réponse)
- Modify: `sites/artisans-shared/src/views/Inventory.vue` (afficher sous le label quand présent)

**Interfaces:**
- Consumes: colonne `source_label` (Task 1).

- [ ] **Step 1: API** — dans `inventory_list`, SELECT `id, object_type, source_label, acquired_at` et ajouter `'source_label' => $row['source_label']` dans `$items[]`.

- [ ] **Step 2: Vue** — sous `<strong>{{ item.label }}</strong>` :
```html
<small v-if="item.source_label" class="item-source">{{ item.source_label }}</small>
```
CSS : `.item-source { color: var(--c-green); font-style: italic; }`

- [ ] **Step 3: Tests + build + commit**

Run: `make test-php FILE=test_inventory.php` (17 OK attendus), `npm run build`.
```bash
git add sites/api/routes/inventory.php sites/artisans-shared/src/views/Inventory.vue
git commit -m "feat(gallery): source_label visible dans l'inventaire (message du cadeau photo)"
```

---

### Task 9: Déploiement + vérification prod

**Files:** aucun (ops)

- [ ] **Step 1: Deploy API** — `make -C sites/api push` puis **vérifier** `curl -s -o /dev/null -w "%{http_code}\n" https://api.prigent.tech/api/health` → 200.

- [ ] **Step 2: Migration 049 auto-appliquée** — `TOKEN=$(grep OPS_TOKEN_PROD sites/api/.env | cut -d= -f2); curl -s https://api.prigent.tech/api/ops/health -H "Authorization: Bearer $TOKEN"` → migrations contiennent `049_poi_photos`.

- [ ] **Step 3: Deploy 4 villes** — `for s in webiartisan-livry webiartisan-combs webiartisan-vert-saint-denis webiartisan-lieusaint; do make -C sites/$s push; done` puis comparer les hash `index-*.js` live vs `sites/webiartisan-$s/dist/assets/` pour chaque ville.

- [ ] **Step 4: Fumée prod** — `GET /pois/1/photos` (public) → 200 ; upload sans token → 401.

- [ ] **Step 5: TODO.md** — ajouter l'entrée galerie dans `docs/TODO.md` (racine, hors git) puis commit du suivi si pertinent.

---

## Self-Review

- **Spec coverage** : migration 049 (T1), upload GD 1600/q80 (T2), 10/j + 30/POI (T2/T5), liste publique lazy (T2/T6), delete auteur/admin (T3), report unique (T3/T5), file admin keep/hide/delete (T4/T7), cadeau + source_label 1× (T4/T5/T8), couverture accès rapide admin (T6), onglets Photos/Détails (T6), horaires déplacés (T6), tests (T5/T6/T8), déploiement (T9). Couverture complète.
- **Placeholders** : le markup détaillé du template PoiSheet est décrit mais l'implémentateur a le composant actuel sous les yeux (modification, pas création) ; le script est complet. Le fallback de création de session admin est explicité (T5).
- **Type consistency** : `pois_list_photos`/`pois_upload_photo`/`pois_delete_photo`/`pois_report_photo` (pois.php) ↔ endpoints api.js `getPoiPhotos`/`uploadPoiPhoto`/`deletePoiPhoto`/`reportPoiPhoto` ; `admin_moderation_photos`/`admin_moderation_photo_review` ↔ `fetchAdminPhotoReports`/`reviewPhotoReport` ; statuts `active|validated|hidden|deleted` cohérents partout ; `source_label` identique API/front.
