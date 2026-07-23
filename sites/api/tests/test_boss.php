<?php
/**
 * WebiArtisan — Tests arène Big Brother (Task 3).
 * Run: make test-php FILE=test_boss.php
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

// Joueur de test
$email = 'boss-test-' . time() . '@example.com';
api('POST', '/users/register', ['email' => $email, 'password' => 'Password123!']);
$r = api('POST', '/users/login', ['email' => $email, 'password' => 'Password123!']);
$token = $r['json']['token'] ?? null;
check('login joueur', $token !== null);
$userId = (int)$pdo->query("SELECT id FROM local_users WHERE email = '$email'")->fetchColumn();

// Boss de test à la position du joueur
$pdo->prepare("INSERT INTO local_world_objects (city, object_type, lat, lng, xp_value, energy_cost, expires_at) VALUES ('livry', 'big_brother', 49.1081, -0.7658, 150, 0, DATE_ADD(NOW(), INTERVAL 2 HOUR))")->execute();
$bossId = (int)$pdo->lastInsertId();

// 1. Engager le combat
$xpBaseline = $pdo->query("SELECT level, xp FROM local_users WHERE id = $userId")->fetch(PDO::FETCH_ASSOC);
$r = api('POST', "/boss/$bossId/fight", ['lat' => 49.1081, 'lng' => -0.7658], $token);
check('fight 200', $r['status'] === 200, json_encode($r['json']));
$fightId = (int)($r['json']['data']['fight_id'] ?? 0);
check('fight_id + 3/3 PV', $fightId > 0 && $r['json']['data']['boss_hp'] === 3 && $r['json']['data']['player_hp'] === 3);

// 2. Double engagement → 409 avec fight_id
$r = api('POST', "/boss/$bossId/fight", ['lat' => 49.1081, 'lng' => -0.7658], $token);
check('double fight → 409', $r['status'] === 409 && (int)($r['json']['data']['fight_id'] ?? 0) === $fightId, json_encode($r['json']));

// 3. Manche quiz : contenu sans la réponse
$r = api('POST', "/boss/fights/$fightId/round", ['game' => 'quiz'], $token);
check('round quiz 200', $r['status'] === 200, json_encode($r['json']));
check('question + 4 choix, pas d answer_index', isset($r['json']['data']['content']['question'], $r['json']['data']['content']['choices']) && !isset($r['json']['data']['content']['answer_index']) && count($r['json']['data']['content']['choices']) === 4);

// 4. Répondre : on force la bonne réponse en lisant le payload serveur (test)
$payload = json_decode($pdo->query("SELECT current_payload FROM local_boss_fights_live WHERE id = $fightId")->fetchColumn(), true);
$goodIdx = $payload['answer_index'];
$hpBefore = (int)$pdo->query("SELECT boss_hp FROM local_boss_fights_live WHERE id = $fightId")->fetchColumn();
$xpBefore = (int)$pdo->query("SELECT xp FROM local_users WHERE id = $userId")->fetchColumn(); // inclut daily_visit du login
$r = api('POST', "/boss/fights/$fightId/answer", ['answer_index' => $goodIdx], $token);
check('bonne réponse → round_won', ($r['json']['data']['round_won'] ?? null) === true, json_encode($r['json']));
check('boss -1 PV', $r['json']['data']['boss_hp'] === $hpBefore - 1);
$xpAfter = (int)$pdo->query("SELECT xp FROM local_users WHERE id = $userId")->fetchColumn();
check('+25 XP de manche', $xpAfter - $xpBefore === 25, "$xpBefore → $xpAfter");

// 5. Manche cartes : 3 cartes joueur, pas de carte boss visible
$r = api('POST', "/boss/fights/$fightId/round", ['game' => 'cards'], $token);
check('round cards 200', $r['status'] === 200);
check('3 cartes joueur, boss caché', count($r['json']['data']['content']['cards'] ?? []) === 3 && !isset($r['json']['data']['content']['boss_card']));
$energyBefore = (int)$pdo->query("SELECT energy FROM local_users WHERE id = $userId")->fetchColumn();

// 6. Jouer une carte : résolution immédiate, énergie -5 si perdu (ou boss -1 si gagné)
$r = api('POST', "/boss/fights/$fightId/answer", ['card_index' => 0], $token);
check('résolution cartes', isset($r['json']['data']['round_won'], $r['json']['data']['reveal']['boss_card']), json_encode($r['json']));
$energyAfter = (int)$pdo->query("SELECT energy FROM local_users WHERE id = $userId")->fetchColumn();
if ($r['json']['data']['round_won'] === false) {
    check('manche perdue → -5 énergie', $energyAfter === $energyBefore - 5, "$energyBefore → $energyAfter");
}

// 7. Manche mat : FEN sans solution, coup solution → gagné
$r = api('POST', "/boss/fights/$fightId/round", ['game' => 'mate'], $token);
check('round mate 200', $r['status'] === 200 && isset($r['json']['data']['content']['fen']) && !isset($r['json']['data']['content']['solution']));
$payload = json_decode($pdo->query("SELECT current_payload FROM local_boss_fights_live WHERE id = $fightId")->fetchColumn(), true);
$r = api('POST', "/boss/fights/$fightId/answer", ['move' => $payload['solution_uci']], $token);
check('mat solution → round_won', ($r['json']['data']['round_won'] ?? null) === true, json_encode($r['json']));

// 8. Forcer la victoire (boss à 0) : rejouer des quiz jusqu'à la fin
for ($i = 0; $i < 4; $i++) {
    $state = api('GET', "/boss/fights/$fightId", null, $token)['json']['data'];
    if (($state['status'] ?? '') !== 'ongoing') break;
    api('POST', "/boss/fights/$fightId/round", ['game' => 'quiz'], $token);
    $payload = json_decode($pdo->query("SELECT current_payload FROM local_boss_fights_live WHERE id = $fightId")->fetchColumn(), true);
    api('POST', "/boss/fights/$fightId/answer", ['answer_index' => $payload['answer_index']], $token);
}
$state = api('GET', "/boss/fights/$fightId", null, $token)['json']['data'];
check('victoire', ($state['status'] ?? '') === 'won', json_encode($state));
// XP cumulatif (les niveaux consomment de l'XP : level-up = level*100)
// Victoire = 3 manches gagnées (75 XP) + bonus (150 XP) quelle que soit l'issue des cartes
$userFinal = $pdo->query("SELECT level, xp FROM local_users WHERE id = $userId")->fetch(PDO::FETCH_ASSOC);
$cumul = fn(int $level, int $xp): int => (int)(($level - 1) * $level / 2 * 100) + $xp;
$gained = $cumul((int)$userFinal['level'], (int)$userFinal['xp']) - $cumul((int)$xpBaseline['level'], (int)$xpBaseline['xp']);
check('XP combat cumulés (>= 225)', $gained >= 225, "gained=$gained");

// 9. Badge brother_slayer débloqué
$badge = $pdo->query("SELECT 1 FROM local_user_badges WHERE user_id = $userId AND badge_key = 'brother_slayer'")->fetchColumn();
check('badge brother_slayer', (bool)$badge);

// 10. Le boss a disparu du radar du joueur
$r = api('GET', '/objects?lat=49.1081&lng=-0.7658&city=livry', null, $token);
$bossVisible = false;
foreach ($r['json']['data']['objects'] ?? [] as $o) { if ($o['type'] === 'big_brother' && (int)$o['id'] === $bossId) $bossVisible = true; }
check('boss combattu exclu de /objects', !$bossVisible);

// 11. Rematch impossible → 409
$r = api('POST', "/boss/$bossId/fight", ['lat' => 49.1081, 'lng' => -0.7658], $token);
check('rematch → 409', $r['status'] === 409, (string)$r['status']);

// 12. Pickup d'un boss → 422 not_pickable
$pdo->prepare("INSERT INTO local_world_objects (city, object_type, lat, lng, xp_value, energy_cost, expires_at) VALUES ('livry', 'big_brother', 49.1081, -0.7658, 150, 0, DATE_ADD(NOW(), INTERVAL 2 HOUR))")->execute();
$boss2Id = (int)$pdo->lastInsertId();
$r = api('POST', "/objects/$boss2Id/pickup", ['lat' => 49.1081, 'lng' => -0.7658], $token);
check('pickup boss → 422 not_pickable', $r['status'] === 422 && ($r['json']['error'] ?? '') === 'not_pickable', json_encode($r['json']));

// 13. Trop loin → 422 distance
$r = api('POST', "/boss/$boss2Id/fight", ['lat' => 49.15, 'lng' => -0.76], $token);
check('fight trop loin → 422', $r['status'] === 422 && ($r['json']['error'] ?? '') === 'distance', json_encode($r['json']));

// 14. Garantie admin : un admin voit toujours un boss à proximité
$adminEmail = 'boss-admin-' . time() . '@example.com';
api('POST', '/users/register', ['email' => $adminEmail, 'password' => 'Password123!']);
$r = api('POST', '/users/login', ['email' => $adminEmail, 'password' => 'Password123!']);
$adminToken = $r['json']['token'] ?? null;
$adminUserId = (int)$pdo->query("SELECT id FROM local_users WHERE email = '$adminEmail'")->fetchColumn();
// Lie un compte artisan admin à ce joueur
$pdo->prepare("INSERT INTO local_artisans (company_name, city_id, category_id, email, phone, status, plan, is_admin, user_id) VALUES ('Admin Boss', 1, 1, ?, '0100000000', 'active', 'free', 1, ?)")
    ->execute([$adminEmail, $adminUserId]);
$adminArtisanId = (int)$pdo->lastInsertId();
// Zone vierge de boss
$pdo->exec("UPDATE local_world_objects SET status = 'expired' WHERE object_type = 'big_brother'");
$r = api('GET', '/objects?lat=49.1081&lng=-0.7658&city=livry', null, $adminToken);
$adminBoss = false;
foreach ($r['json']['data']['objects'] ?? [] as $o) { if ($o['type'] === 'big_brother') $adminBoss = true; }
check('admin : un boss apparaît à proximité', $adminBoss === true, json_encode(array_column($r['json']['data']['objects'] ?? [], 'type')));

// Cleanup
$pdo->prepare("DELETE FROM local_artisans WHERE id = ?")->execute([$adminArtisanId]);
$pdo->prepare("DELETE FROM local_user_actions WHERE user_id = ?")->execute([$adminUserId]);
$pdo->prepare("DELETE FROM local_user_cooldowns WHERE user_id = ?")->execute([$adminUserId]);
$pdo->prepare("DELETE FROM local_users WHERE id = ?")->execute([$adminUserId]);
$pdo->prepare("DELETE FROM local_boss_fights WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_boss_fights_live WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_object_pickups WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_actions WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_badges WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_cooldowns WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_users WHERE id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_world_objects WHERE object_type = 'big_brother'")->execute();

echo "OK: tous les tests boss arena passent\n";
