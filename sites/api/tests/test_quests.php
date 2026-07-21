<?php
/**
 * WebiArtisan — Smoke tests endpoints /quests (Task 5).
 * Run: docker compose exec -T php php /var/www/api/tests/test_quests.php
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

// Joueur de test (register silencieux → login pour obtenir le token)
$email = 'quests-test-' . time() . '@example.com';
$r = api('POST', '/users/register', ['email' => $email, 'password' => 'Password123!']);
$r = api('POST', '/users/login', ['email' => $email, 'password' => 'Password123!']);
$token = $r['json']['token'] ?? null;
check('register + login joueur', $token !== null, json_encode($r['json']));
$userId = (int)$pdo->query("SELECT id FROM local_users WHERE email = '$email'")->fetchColumn();

// 1. GET /quests/today : 3 quêtes assignées paresseusement
$r = api('GET', '/quests/today', null, $token);
check('GET /quests/today 200', $r['status'] === 200, (string)$r['status']);
check('3 quêtes', count($r['json']['data'] ?? []) === 3, json_encode($r['json']));
$quest = $r['json']['data'][0];
check('forme quête', isset($quest['quest_code'], $quest['label'], $quest['target_count'], $quest['reward_xp'], $quest['progress']));

// 2. Claim avant complétion → 422
$code = $quest['quest_code'];
$r = api('POST', "/quests/$code/claim", [], $token);
check('claim non complétée → 422', $r['status'] === 422 && ($r['json']['error'] ?? '') === 'not_completed', json_encode($r['json']));

// 3. Forcer la complétion en BDD puis claim → XP créditée
$pdo->prepare("UPDATE local_user_quests SET progress = 99, completed = 1 WHERE user_id = ? AND quest_code = ? AND quest_date = CURDATE()")
    ->execute([$userId, $code]);
$xpBefore = (int)$pdo->query("SELECT xp FROM local_users WHERE id = $userId")->fetchColumn();
$r = api('POST', "/quests/$code/claim", [], $token);
check('claim 200', $r['status'] === 200, json_encode($r['json']));
$xpAfter = (int)$pdo->query("SELECT xp FROM local_users WHERE id = $userId")->fetchColumn();
check('XP créditée', $xpAfter - $xpBefore === (int)$quest['reward_xp'], "$xpBefore → $xpAfter");

// 4. Double claim → 409
$r = api('POST', "/quests/$code/claim", [], $token);
check('double claim → 409', $r['status'] === 409 && ($r['json']['error'] ?? '') === 'already_claimed', json_encode($r['json']));

// 5. Claim d'une quête non assignée → 404
$assignedToday = array_column(api('GET', '/quests/today', null, $token)['json']['data'] ?? [], 'quest_code');
$pool = $pdo->query("SELECT code FROM local_daily_quests")->fetchAll(PDO::FETCH_COLUMN);
$notAssigned = array_values(array_diff($pool, $assignedToday))[0] ?? null;
check('il existe une quête non assignée', $notAssigned !== null);
$r = api('POST', "/quests/$notAssigned/claim", [], $token);
check('quête non assignée → 404', $r['status'] === 404 && ($r['json']['error'] ?? '') === 'not_found', json_encode($r['json']));

// Cleanup
$pdo->prepare("DELETE FROM local_user_quests WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_actions WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_cooldowns WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_users WHERE id = ?")->execute([$userId]);

echo "OK: tous les tests /quests passent\n";
