<?php
/**
 * WebiArtisan — Tests claims POI + upload image (Tasks 2-3).
 * Run: make test-php FILE=test_poi_images.php
 */

require_once __DIR__ . '/../config/database.php';

$apiBase = rtrim(getenv('API_BASE_URL') ?: 'http://nginx/api', '/');
$pdo = getDatabase();

function check(string $name, bool $cond, string $detail = ''): void {
    echo ($cond ? 'OK' : 'FAIL') . ": $name" . ($detail ? " — $detail" : '') . "\n";
    if (!$cond) exit(1);
}

function api(string $method, string $path, $body = null, ?string $artisanToken = null, bool $rawBody = false): array {
    $ch = curl_init(rtrim($GLOBALS['apiBase'], '/') . $path);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = [];
    if (!$rawBody) $headers[] = 'Content-Type: application/json';
    if ($artisanToken) $headers[] = 'X-Artisan-Token: ' . $artisanToken;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody ? $body : json_encode($body));
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $code, 'json' => json_decode($res, true)];
}

function makeArtisan(PDO $pdo, string $email, int $cityId, bool $admin = false): array {
    $pdo->prepare("DELETE FROM local_artisans WHERE email = ?")->execute([$email]);
    $pdo->prepare("
        INSERT INTO local_artisans (company_name, city_id, category_id, email, phone, status, plan, password_hash, email_verified, is_admin)
        VALUES ('POI Test', ?, 1, ?, '0100000000', 'active', 'free', ?, TRUE, ?)
    ")->execute([$cityId, $email, password_hash('Password123!', PASSWORD_BCRYPT), $admin ? 1 : 0]);
    $id = (int)$pdo->lastInsertId();
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE local_artisans SET auth_token_lookup = ?, auth_token_hash = ?, auth_token_exp = DATE_ADD(NOW(), INTERVAL 1 DAY) WHERE id = ?")
        ->execute([hash('sha256', $token), password_hash($token, PASSWORD_BCRYPT), $id]);
    return ['id' => $id, 'token' => $token];
}

// Artisan de test (ville 1) + admin + POI de test
$art = makeArtisan($pdo, 'poi-test-' . time() . '@example.com', 1);
$adm = makeArtisan($pdo, 'poi-admin-' . time() . '@example.com', 1, true);
$pdo->prepare("INSERT INTO local_pois (city_id, type, name, latitude, longitude, is_active) VALUES (1, 'parc', 'Parc E2E Image', 49.1081, -0.7658, 1)")->execute();
$poiId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO local_pois (city_id, type, name, latitude, longitude, is_active) VALUES (2, 'parc', 'Parc Autre Ville', 48.66, 2.56, 1)")->execute();
$otherCityPoiId = (int)$pdo->lastInsertId();

// 1. Claimable : le POI de ma ville est listé, pas celui de l'autre ville
$r = api('GET', '/pois/claimable', null, $art['token']);
check('claimable 200', $r['status'] === 200, (string)$r['status']);
$ids = array_column($r['json']['data'] ?? [], 'id');
check('POI ma ville claimable', in_array($poiId, $ids, true));
check('POI autre ville absent', !in_array($otherCityPoiId, $ids, true));

// 2. Claim OK
$r = api('POST', "/pois/$poiId/claim", [], $art['token']);
check('claim 201', $r['status'] === 201, json_encode($r['json']));

// 3. Double claim → 409
$r = api('POST', "/pois/$poiId/claim", [], $art['token']);
check('double claim → 409', $r['status'] === 409, (string)$r['status']);

// 4. Claim POI autre ville → 422
$r = api('POST', "/pois/$otherCityPoiId/claim", [], $art['token']);
check('claim autre ville → 422', $r['status'] === 422, (string)$r['status']);

// 5. Admin liste et approuve
$r = api('GET', '/admin/poi-claims?status=pending', null, $adm['token']);
check('admin liste pending', $r['status'] === 200 && count($r['json']['data'] ?? []) >= 1, (string)$r['status']);
$claimId = null;
foreach ($r['json']['data'] ?? [] as $c) { if ((int)$c['poi_id'] === $poiId) $claimId = (int)$c['id']; }
check('claim trouvée', $claimId !== null);
$r = api('POST', "/admin/poi-claims/$claimId/approve", [], $adm['token']);
check('approve 200', $r['status'] === 200, json_encode($r['json']));
$owner = (int)$pdo->query("SELECT owner_artisan_id FROM local_pois WHERE id = $poiId")->fetchColumn();
check('owner assigné', $owner === $art['id'], "owner=$owner");

// 6. Claim sur POI déjà possédé → 409
$r = api('POST', "/pois/$poiId/claim", [], $art['token']);
check('claim POI possédé → 409', $r['status'] === 409, (string)$r['status']);

// 7. Upload : non-owner (autre artisan) → 403
$other = makeArtisan($pdo, 'poi-other-' . time() . '@example.com', 1);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$tmpImg = tempnam(sys_get_temp_dir(), 'poi') . '.png';
file_put_contents($tmpImg, $png);
$post = ['image' => new CURLFile($tmpImg, 'image/png', 'test.png')];
$r = api('POST', "/pois/$poiId/image", $post, $other['token'], true);
check('upload non-owner → 403', $r['status'] === 403, (string)$r['status']);

// 8. Upload owner OK
$r = api('POST', "/pois/$poiId/image", $post, $art['token'], true);
check('upload owner 200', $r['status'] === 200, json_encode($r['json']));
$imageUrl = $r['json']['data']['image_url'] ?? '';
check('image_url retournée', str_starts_with($imageUrl, '/uploads/pois/poi_' . $poiId . '_'), $imageUrl);

// 9. Upload admin OK (remplace)
$r = api('POST', "/pois/$poiId/image", $post, $adm['token'], true);
check('upload admin 200', $r['status'] === 200);

// 10. Faux MIME → 422
$tmpTxt = tempnam(sys_get_temp_dir(), 'poi') . '.txt';
file_put_contents($tmpTxt, 'pas une image');
$post2 = ['image' => new CURLFile($tmpTxt, 'image/png', 'fake.png')];
$r = api('POST', "/pois/$poiId/image", $post2, $art['token'], true);
check('faux MIME → 422', $r['status'] === 422, (string)$r['status']);

// 11. DELETE image
$r = api('DELETE', "/pois/$poiId/image", null, $art['token']);
check('delete image 200', $r['status'] === 200, json_encode($r['json']));

// 12. Revoke owner (admin)
$r = api('POST', "/admin/pois/$poiId/revoke-owner", [], $adm['token']);
check('revoke 200', $r['status'] === 200, json_encode($r['json']));
$owner = $pdo->query("SELECT owner_artisan_id FROM local_pois WHERE id = $poiId")->fetchColumn();
check('owner révoqué', $owner === null || $owner === 0 || $owner === '0');

// Cleanup
$pdo->prepare("DELETE FROM local_poi_claims WHERE poi_id IN (?, ?)")->execute([$poiId, $otherCityPoiId]);
$pdo->prepare("DELETE FROM local_pois WHERE id IN (?, ?)")->execute([$poiId, $otherCityPoiId]);
foreach ([$art, $adm, $other] as $a) {
    $pdo->prepare("DELETE FROM local_artisan_sessions WHERE artisan_id = ?")->execute([$a['id']]);
    $pdo->prepare("DELETE FROM local_artisans WHERE id = ?")->execute([$a['id']]);
}
@unlink($tmpImg); @unlink($tmpTxt);

echo "OK: tous les tests POI images passent\n";
