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
$tmp = sys_get_temp_dir() . '/gal-' . uniqid() . '.jpg';
imagejpeg($img, $tmp, 90);
imagedestroy($img);

// Faux JPEG (texte) : finfo lit le contenu, pas l'extension
$tmpBad = sys_get_temp_dir() . '/gal-' . uniqid() . '.jpg';
file_put_contents($tmpBad, "ceci n'est pas une image\n");

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

// 2. Mime refusé : contenu non-image → 422 bad_mime
$r = upload("/pois/$poiId/photos", $tmpBad, $token);
check('fichier non-image → 422 bad_mime', $r['status'] === 422 && ($r['json']['error'] ?? '') === 'bad_mime', json_encode($r['json']));

// 3. Upload OK + resize ≤1600 px
$r = upload("/pois/$poiId/photos", $tmp, $token);
check('upload 201', $r['status'] === 201, json_encode($r['json']));
$photoId = (int)($r['json']['data']['id'] ?? 0);
$stored = __DIR__ . '/../uploads/pois/gallery/' . basename($r['json']['data']['url'] ?? '');
clearstatcache(true, $stored);
$size = is_file($stored) ? getimagesize($stored) : null;
check('resize ≤ 1600 px', $size && $size[0] <= 1600, $size ? "{$size[0]}x{$size[1]}" : 'fichier absent');

// 4. Liste publique
$r = api('GET', "/pois/$poiId/photos");
check('liste 200 + 1 photo', $r['status'] === 200 && count($r['json']['data'] ?? []) === 1, json_encode($r['json']));

// 5. Signalement + unicité
$r = api('POST', "/pois/photos/$photoId/report", [], $token);
check('report 200', $r['status'] === 200, json_encode($r['json']));
$r = api('POST', "/pois/photos/$photoId/report", [], $token);
check('double report → 409', $r['status'] === 409);

// 6. Modération admin : keep → validated + cadeau 1×
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
$r = api('POST', "/admin/moderation/photos/$photoId/keep", [], $adminToken, true);
check('keep 200 + gifted', $r['status'] === 200 && ($r['json']['data']['gifted'] ?? null) === true, json_encode($r['json']));
$status = $pdo->query("SELECT status FROM local_poi_photos WHERE id = $photoId")->fetchColumn();
check('keep → status validated en base', $status === 'validated', (string)$status);
$gift = $pdo->query("SELECT object_type, source_label FROM local_user_inventory WHERE user_id = $userId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('cadeau energy_store + message', $gift && $gift['object_type'] === 'energy_store' && $gift['source_label'] === 'Merci pour la photo, ami artisan', json_encode($gift));
$r = api('POST', "/admin/moderation/photos/$photoId/keep", [], $adminToken, true);
check('2e keep → pas de 2e cadeau', $r['status'] === 200 && ($r['json']['data']['gifted'] ?? null) === false);

// 7. Suppression par un tiers → 403
$email2 = 'gallery-tiers-' . time() . '@example.com';
api('POST', '/users/register', ['email' => $email2, 'password' => 'Password123!']);
$r = api('POST', '/users/login', ['email' => $email2, 'password' => 'Password123!']);
$token2 = $r['json']['token'] ?? null;
check('login 2e joueur', $token2 !== null);
$userId2 = (int)$pdo->query("SELECT id FROM local_users WHERE email = '$email2'")->fetchColumn();
$r = api('DELETE', "/pois/photos/$photoId", null, $token2);
check('delete par un tiers → 403', $r['status'] === 403, (string)$r['status']);

// 8. Modération admin : hide → hidden + retrait de la galerie publique
$r = upload("/pois/$poiId/photos", $tmp, $token);
check('upload 2e photo 201', $r['status'] === 201, json_encode($r['json']));
$photoId2 = (int)($r['json']['data']['id'] ?? 0);
$stored2 = __DIR__ . '/../uploads/pois/gallery/' . basename($r['json']['data']['url'] ?? '');
$r = api('POST', "/pois/photos/$photoId2/report", [], $token);
check('report 2e photo 200', $r['status'] === 200, json_encode($r['json']));
$r = api('POST', "/admin/moderation/photos/$photoId2/hide", [], $adminToken, true);
check('hide 200', $r['status'] === 200, json_encode($r['json']));
$status2 = $pdo->query("SELECT status FROM local_poi_photos WHERE id = $photoId2")->fetchColumn();
check('hide → status hidden en base', $status2 === 'hidden', (string)$status2);
$ids = array_map('intval', array_column(api('GET', "/pois/$poiId/photos")['json']['data'] ?? [], 'id'));
check('photo masquée absente de la liste publique', !in_array($photoId2, $ids, true) && in_array($photoId, $ids, true), json_encode($ids));

// 9. File de modération : la photo signalée y figure
$r = api('GET', '/admin/moderation/photos', null, $adminToken, true);
$queueIds = array_map('intval', array_column($r['json']['data'] ?? [], 'id'));
check('file modération contient la photo signalée', $r['status'] === 200 && in_array($photoId, $queueIds, true), json_encode($queueIds));

// 10. Suppression par l'auteur (retire aussi le fichier uploadé)
$r = api('DELETE', "/pois/photos/$photoId", null, $token);
check('delete auteur 200', $r['status'] === 200);
check('photo retirée de la liste', count(api('GET', "/pois/$poiId/photos")['json']['data'] ?? []) === 0);

// 11. Rate limit 10/j (compte direct en base pour éviter 9 uploads)
for ($i = 0; $i < 10; $i++) {
    $pdo->prepare("INSERT INTO local_poi_photos (poi_id, user_id, file_path, created_at) VALUES (?, ?, '/uploads/pois/gallery/fake.jpg', NOW())")->execute([$poiId, $userId]);
}
$r = upload("/pois/$poiId/photos", $tmp, $token);
check('11e photo du jour → 422 rate_limited', $r['status'] === 422 && ($r['json']['error'] ?? '') === 'rate_limited', json_encode($r['json']));

// 12. Plafond 30/POI (compte direct)
$pdo->exec("DELETE FROM local_poi_photos WHERE poi_id = $poiId");
for ($i = 0; $i < 30; $i++) {
    $pdo->prepare("INSERT INTO local_poi_photos (poi_id, user_id, file_path, created_at) VALUES (?, ?, '/uploads/pois/gallery/fake.jpg', DATE_SUB(NOW(), INTERVAL 2 DAY))")->execute([$poiId, $userId]);
}
$r = upload("/pois/$poiId/photos", $tmp, $token);
check('31e photo du POI → 422 poi_full', $r['status'] === 422 && ($r['json']['error'] ?? '') === 'poi_full');

// Cleanup : le fichier de la 1re photo a été retiré par le DELETE de l'étape 10 ;
// on purge ici les lignes créées en base (dont les comptes du test) et le fichier masqué
$pdo->prepare("DELETE FROM local_artisan_sessions WHERE artisan_id = ?")->execute([$adminArtisanId]);
$pdo->prepare("DELETE FROM local_artisans WHERE id = ?")->execute([$adminArtisanId]);
$pdo->prepare("DELETE FROM local_poi_photo_reports WHERE photo_id IN (?, ?)")->execute([$photoId, $photoId2]);
$pdo->prepare("DELETE FROM local_poi_photos WHERE poi_id = ?")->execute([$poiId]);
$pdo->prepare("DELETE FROM local_pois WHERE id = ?")->execute([$poiId]);
$pdo->prepare("DELETE FROM local_user_inventory WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_sessions WHERE user_id IN (?, ?)")->execute([$userId, $userId2]);
$pdo->prepare("DELETE FROM local_users WHERE id IN (?, ?)")->execute([$userId, $userId2]);
@unlink($stored2);
@unlink($tmp);
@unlink($tmpBad);

echo "OK: tous les tests galerie passent\n";
