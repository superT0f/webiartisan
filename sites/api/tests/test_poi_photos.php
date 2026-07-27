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
clearstatcache(true, $stored);
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
// Aucune session artisan admin n'existe dans l'env de test : on en crée une via PDO
// (pattern test_poi_images.php / artisan_create_session : token_lookup sha256 + token_hash bcrypt)
$adminEmail = 'gallery-admin-' . time() . '@example.com';
$pdo->prepare("
    INSERT INTO local_artisans (company_name, city_id, category_id, email, phone, status, plan, password_hash, email_verified, is_admin)
    VALUES ('Admin Galerie', 1, 1, ?, '0100000000', 'active', 'free', ?, TRUE, 1)
")->execute([$adminEmail, password_hash('Password123!', PASSWORD_BCRYPT)]);
$adminArtisanId = (int)$pdo->lastInsertId();
$adminToken = bin2hex(random_bytes(32));
$pdo->prepare("
    INSERT INTO local_artisan_sessions (artisan_id, token_lookup, token_hash, device_label, expires_at)
    VALUES (?, ?, ?, 'test gallery', DATE_ADD(NOW(), INTERVAL 1 DAY))
")->execute([$adminArtisanId, hash('sha256', $adminToken), password_hash($adminToken, PASSWORD_BCRYPT)]);
check('token admin créé', $adminToken !== '', 'session admin via PDO');
$r = api('POST', "/admin/moderation/photos/$photoId/keep", [], $adminToken, true);
check('keep 200 + gifted', $r['status'] === 200 && ($r['json']['data']['gifted'] ?? null) === true, json_encode($r['json']));
$gift = $pdo->query("SELECT object_type, source_label FROM local_user_inventory WHERE user_id = $userId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('cadeau energy_store + message', $gift && $gift['object_type'] === 'energy_store' && $gift['source_label'] === 'Merci pour la photo, ami artisan', json_encode($gift));
$r = api('POST', "/admin/moderation/photos/$photoId/keep", [], $adminToken, true);
check('2e keep → pas de 2e cadeau', $r['status'] === 200 && ($r['json']['data']['gifted'] ?? null) === false);

// 6. File de modération
$r = api('GET', '/admin/moderation/photos', null, $adminToken, true);
check('file modération 200', $r['status'] === 200 && count($r['json']['data'] ?? []) >= 1);

// 7. Suppression par l'auteur (retire aussi le fichier uploadé)
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

// Cleanup : le fichier uploadé a été retiré par le DELETE de l'étape 7 ;
// on purge ici les lignes créées en base (dont le compte admin du test)
$pdo->prepare("DELETE FROM local_artisan_sessions WHERE artisan_id = ?")->execute([$adminArtisanId]);
$pdo->prepare("DELETE FROM local_artisans WHERE id = ?")->execute([$adminArtisanId]);
$pdo->prepare("DELETE FROM local_poi_photo_reports WHERE photo_id = ?")->execute([$photoId]);
$pdo->prepare("DELETE FROM local_poi_photos WHERE poi_id = ?")->execute([$poiId]);
$pdo->prepare("DELETE FROM local_pois WHERE id = ?")->execute([$poiId]);
$pdo->prepare("DELETE FROM local_user_inventory WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_sessions WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_users WHERE id = ?")->execute([$userId]);
@unlink($tmp);

echo "OK: tous les tests galerie passent\n";
