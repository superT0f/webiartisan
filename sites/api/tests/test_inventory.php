<?php
/**
 * WebiArtisan — Tests inventaire joueur (migration 046).
 * Leurre à boss + réserve d'énergie : ramassage → inventaire → activation.
 * Run: make test-php FILE=test_inventory.php
 */

require_once __DIR__ . '/../config/database.php';

$apiBase = rtrim(getenv('API_BASE_URL') ?: 'http://nginx/api', '/');
$pdo = getDatabase();

function check(string $name, bool $cond, string $detail = ''): void {
    echo ($cond ? 'OK' : 'FAIL') . ": $name" . ($detail ? " — $detail" : '') . "\n";
    if (!$cond) exit(1);
}

function api(string $method, string $path, ?array $body = null, ?string $token = null): array {
    $ch = curl_init(rtrim($GLOBALS['apiBase'], '/') . $path);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $code, 'json' => json_decode($res, true)];
}

$LAT = 49.1081; $LNG = -0.7658; // Livry (ville de test)

// Joueurs de test
$email = 'inv-test-' . time() . '@example.com';
api('POST', '/users/register', ['email' => $email, 'password' => 'Password123!']);
$r = api('POST', '/users/login', ['email' => $email, 'password' => 'Password123!']);
$token = $r['json']['token'] ?? null;
check('login joueur', $token !== null);
$userId = (int)$pdo->query("SELECT id FROM local_users WHERE email = '$email'")->fetchColumn();

$email2 = 'inv-test-bis-' . time() . '@example.com';
api('POST', '/users/register', ['email' => $email2, 'password' => 'Password123!']);
$r = api('POST', '/users/login', ['email' => $email2, 'password' => 'Password123!']);
$token2 = $r['json']['token'] ?? null;
check('login joueur 2', $token2 !== null);

// Objets spéciaux à la position du joueur
$pdo->prepare("INSERT INTO local_world_objects (city, object_type, lat, lng, xp_value, energy_cost, expires_at) VALUES ('livry', 'boss_spawner', ?, ?, 25, 5, DATE_ADD(NOW(), INTERVAL 48 HOUR))")->execute([$LAT, $LNG]);
$spawnerObjId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO local_world_objects (city, object_type, lat, lng, xp_value, energy_cost, expires_at) VALUES ('livry', 'energy_store', ?, ?, 10, 5, DATE_ADD(NOW(), INTERVAL 48 HOUR))")->execute([$LAT, $LNG]);
$storeObjId = (int)$pdo->lastInsertId();

// 1. Inventaire requiert l'auth
$r = api('GET', '/inventory');
check('GET /inventory sans token → 401', $r['status'] === 401);

// 2. Inventaire vide au départ
$r = api('GET', '/inventory', null, $token);
check('inventaire vide', $r['status'] === 200 && count($r['json']['data'] ?? []) === 0, json_encode($r['json']));

// 3. Ramassage du leurre → inventaire
$r = api('POST', "/objects/$spawnerObjId/pickup", ['lat' => $LAT, 'lng' => $LNG], $token);
check('pickup leurre 200', $r['status'] === 200, json_encode($r['json']));
check('pickup déclare inventory_item=boss_spawner', ($r['json']['data']['inventory_item'] ?? null) === 'boss_spawner');

$r = api('GET', '/inventory', null, $token);
$items = $r['json']['data'] ?? [];
check('1 objet dans l inventaire', count($items) === 1 && $items[0]['type'] === 'boss_spawner', json_encode($items));
$spawnerItemId = (int)$items[0]['id'];

// 4. Activation du leurre → boss spawné à proximité (anneau 100–500 m)
$r = api('POST', "/inventory/$spawnerItemId/activate", ['lat' => $LAT, 'lng' => $LNG, 'city' => 'livry'], $token);
check('activation leurre 200', $r['status'] === 200, json_encode($r['json']));
$bossId = (int)($r['json']['data']['boss_id'] ?? 0);
check('boss_id retourné', $bossId > 0);
$boss = $pdo->query("SELECT object_type, city, lat, lng, status FROM local_world_objects WHERE id = $bossId")->fetch(PDO::FETCH_ASSOC);
check('boss big_brother actif à livry', $boss && $boss['object_type'] === 'big_brother' && $boss['city'] === 'livry' && $boss['status'] === 'active', json_encode($boss));
$dist = $pdo->query("SELECT ST_Distance_Sphere(POINT($LNG, $LAT), POINT({$boss['lng']}, {$boss['lat']}))")->fetchColumn();
check('boss dans l anneau 100–500 m', $dist !== null && $dist >= 90 && $dist <= 520, "dist={$dist}m");

// 5. Double activation → 404
$r = api('POST', "/inventory/$spawnerItemId/activate", ['lat' => $LAT, 'lng' => $LNG], $token);
check('double activation → 404', $r['status'] === 404);

// 6. Activation par un autre joueur → 404
$pdo->prepare("INSERT INTO local_user_inventory (user_id, object_type) VALUES (?, 'energy_store')")->execute([$userId]);
$otherItemId = (int)$pdo->lastInsertId();
$r = api('POST', "/inventory/$otherItemId/activate", ['lat' => $LAT, 'lng' => $LNG], $token2);
check('activation par un tiers → 404', $r['status'] === 404);

// 7. Réserve d'énergie : pickup puis activation → +30 ⚡
$r = api('POST', "/objects/$storeObjId/pickup", ['lat' => $LAT, 'lng' => $LNG], $token);
check('pickup réserve 200', $r['status'] === 200 && ($r['json']['data']['inventory_item'] ?? null) === 'energy_store', json_encode($r['json']));
$storeItemId = (int)$pdo->query("SELECT id FROM local_user_inventory WHERE user_id = $userId AND object_type = 'energy_store' AND status = 'active' ORDER BY id DESC LIMIT 1")->fetchColumn();

// Énergie à 10 pour mesurer le gain
$pdo->prepare("UPDATE local_users SET energy = 10, energy_updated_at = NOW() WHERE id = ?")->execute([$userId]);
$r = api('POST', "/inventory/$storeItemId/activate", ['lat' => $LAT, 'lng' => $LNG], $token);
check('activation réserve 200', $r['status'] === 200, json_encode($r['json']));
check('+30 ⚡ appliqué', ($r['json']['data']['amount'] ?? 0) === 30 && ($r['json']['data']['energy']['current'] ?? 0) === 40, json_encode($r['json']['data'] ?? []));

echo "OK: tous les tests inventaire passent\n";
