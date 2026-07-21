<?php
/**
 * WebiArtisan — Smoke tests endpoints /objects (Task 4).
 * Run: docker compose exec -T php php /var/www/api/tests/test_objects.php
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

// 0. Auth requise
$r = api('GET', '/objects?lat=49.1081&lng=-0.7658&city=livry');
check('GET /objects sans auth → 401', $r['status'] === 401, (string)$r['status']);

// 1. Créer un joueur puis login (register est silencieux, c'est login qui rend le token)
$email = 'objects-test-' . time() . '@example.com';
$r = api('POST', '/users/register', ['email' => $email, 'password' => 'Password123!']);
check('register joueur', ($r['json']['success'] ?? false) === true, json_encode($r['json']));
$r = api('POST', '/users/login', ['email' => $email, 'password' => 'Password123!']);
$token = $r['json']['token'] ?? null;
check('login joueur + token', $token !== null, json_encode($r['json']));

// 2. GET /objects : spawn paresseux + énergie + propreté
$pdo->prepare("DELETE FROM local_world_objects WHERE city = 'livry' AND spawned_by = 'system'")->execute();
$r = api('GET', '/objects?lat=49.1081&lng=-0.7658&city=livry', null, $token);
check('GET /objects 200', $r['status'] === 200, (string)$r['status']);
check('objets spawnes (>=1)', count($r['json']['data']['objects'] ?? []) >= 1);
check('énergie incluse', ($r['json']['data']['energy']['current'] ?? null) === 100);
check('propreté incluse', isset($r['json']['data']['city_cleanliness']));

// 3. Pickup trop loin → 422 (objet inséré à ~500 m)
$pdo->prepare("INSERT INTO local_world_objects (city, object_type, lat, lng, xp_value, energy_cost, expires_at) VALUES ('livry', 'dechet', 49.1126, -0.7658, 10, 5, DATE_ADD(NOW(), INTERVAL 48 HOUR))")->execute();
$farId = (int)$pdo->lastInsertId();
$r = api('POST', "/objects/$farId/pickup", ['lat' => 49.1081, 'lng' => -0.7658], $token);
check('pickup trop loin → 422', $r['status'] === 422, (string)$r['status']);

// 4. Pickup à portée → succès, XP 10, énergie 95
$pdo->prepare("INSERT INTO local_world_objects (city, object_type, lat, lng, xp_value, energy_cost, expires_at) VALUES ('livry', 'dechet', 49.1081, -0.7658, 10, 5, DATE_ADD(NOW(), INTERVAL 48 HOUR))")->execute();
$nearId = (int)$pdo->lastInsertId();
$r = api('POST', "/objects/$nearId/pickup", ['lat' => 49.1081, 'lng' => -0.7658], $token);
check('pickup 200', $r['status'] === 200, json_encode($r['json']));
check('xp 10', ($r['json']['data']['xp_awarded'] ?? null) === 10);
check('énergie 95', ($r['json']['data']['energy']['current'] ?? null) === 95);
check('quests_completed est un tableau', is_array($r['json']['data']['quests_completed'] ?? null));

// 5. Re-pickup du même objet → 410 gone
$r = api('POST', "/objects/$nearId/pickup", ['lat' => 49.1081, 'lng' => -0.7658], $token);
check('re-pickup → 410', $r['status'] === 410, (string)$r['status']);

// 6. Énergie insuffisante → 422 error energy
$userId = (int)$pdo->query("SELECT id FROM local_users WHERE email = '$email'")->fetchColumn();
$pdo->prepare("UPDATE local_users SET energy = 2, energy_updated_at = NOW() WHERE id = ?")->execute([$userId]);
$pdo->prepare("INSERT INTO local_world_objects (city, object_type, lat, lng, xp_value, energy_cost, expires_at) VALUES ('livry', 'dechet', 49.1081, -0.7658, 10, 5, DATE_ADD(NOW(), INTERVAL 48 HOUR))")->execute();
$noEnergyId = (int)$pdo->lastInsertId();
$r = api('POST', "/objects/$noEnergyId/pickup", ['lat' => 49.1081, 'lng' => -0.7658], $token);
check('énergie insuffisante → 422 energy', $r['status'] === 422 && ($r['json']['error'] ?? '') === 'energy', json_encode($r['json']));

// 7. Cadeau artisan : non premium → 403
$artEmail = 'gift-test-' . time() . '@example.com';
$pdo->prepare("DELETE FROM local_artisans WHERE email = ?")->execute([$artEmail]);
$pdo->prepare("INSERT INTO local_artisans (company_name, city_id, category_id, email, phone, status, plan, latitude, longitude) VALUES ('Gift Test', 1, 1, ?, '0100000000', 'active', 'free', 49.1081, -0.7658)")->execute([$artEmail]);
$artisanId = (int)$pdo->lastInsertId();
// Token artisan valide : auth_token_lookup = sha256(token), hash bcrypt, exp future
// (pattern de lib/ArtisanAuth.php)
$artisanToken = bin2hex(random_bytes(32));
$pdo->prepare("UPDATE local_artisans SET auth_token_lookup = ?, auth_token_hash = ?, auth_token_exp = DATE_ADD(NOW(), INTERVAL 1 DAY) WHERE id = ?")
    ->execute([hash('sha256', $artisanToken), password_hash($artisanToken, PASSWORD_BCRYPT), $artisanId]);
$ch = curl_init("$apiBase/objects");
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Artisan-Token: ' . $artisanToken],
    CURLOPT_POSTFIELDS => json_encode(['lat' => 49.1081, 'lng' => -0.7658])]);
$res = json_decode(curl_exec($ch), true); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
check('cadeau non premium → 403', $code === 403, "$code " . json_encode($res));

// 8. Cadeau artisan premium → 201, visible dans GET /objects/mine
$pdo->prepare("UPDATE local_artisans SET plan = 'premium' WHERE id = ?")->execute([$artisanId]);
$ch = curl_init("$apiBase/objects");
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Artisan-Token: ' . $artisanToken],
    CURLOPT_POSTFIELDS => json_encode(['lat' => 49.10810, 'lng' => -0.76580])]);
$res = json_decode(curl_exec($ch), true); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
check('cadeau premium → 200/201', in_array($code, [200, 201], true), "$code " . json_encode($res));
$giftId = (int)($res['data']['id'] ?? 0);
check('id cadeau retourné', $giftId > 0);

// 9. DELETE cadeau
$ch = curl_init("$apiBase/objects/$giftId");
curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['X-Artisan-Token: ' . $artisanToken]]);
curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
check('DELETE cadeau → 200', $code === 200, (string)$code);

// 10. Ville propre ++ : compteur global + podium des 3 meilleurs nettoyeurs
$r = api('GET', '/objects?lat=49.1081&lng=-0.7658&city=livry', null, $token);
$total = $r['json']['data']['city_collected_total'] ?? null;
check('compteur ville présent (>=1)', is_int($total) && $total >= 1, json_encode($total));
$top = $r['json']['data']['top_cleaners'] ?? null;
check('podium présent (1-3 entrées)', is_array($top) && count($top) >= 1 && count($top) <= 3, json_encode($top));
check('forme podium', isset($top[0]['display_name'], $top[0]['count']), json_encode($top[0] ?? null));
// Le joueur de test (display_name null → préfixe email) doit être anonymisé
$names = array_column($top, 'display_name');
check('anonymisation email-like', !in_array('objects-test-' . '', $names, true) && !str_contains(implode(',', $names), '@'), json_encode($names));

// Cleanup
$pdo->prepare("DELETE FROM local_object_pickups WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_quests WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_actions WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_cooldowns WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_users WHERE id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_world_objects WHERE city = 'livry'")->execute();
$pdo->prepare("DELETE FROM local_artisans WHERE id = ?")->execute([$artisanId]);

echo "OK: tous les tests /objects passent\n";
