<?php
/**
 * WebiArtisan — Tests lib WorldObjects + Quests (Task 3).
 * Run: docker compose exec -T php php /var/www/api/tests/test_world_lib.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/WorldObjects.php';
require_once __DIR__ . '/../lib/Quests.php';

$pdo = getDatabase();

function check(string $name, bool $cond, string $detail = ''): void {
    echo ($cond ? 'OK' : 'FAIL') . ": $name" . ($detail ? " — $detail" : '') . "\n";
    if (!$cond) exit(1);
}

$city = 'livry';
$lat = 49.1081; $lng = -0.7658;

// 1. Spawn paresseux : densité portée à SPAWN_DENSITY autour du point
$pdo->prepare("DELETE FROM local_world_objects WHERE city = ?")->execute([$city]);
worldobjects_ensure_density($pdo, $city, $lat, $lng);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM local_world_objects WHERE city = ? AND status = 'active'");
$stmt->execute([$city]);
check('spawn densité = ' . SPAWN_DENSITY, (int)$stmt->fetchColumn() === SPAWN_DENSITY);

// 2. Pas de double spawn si densité suffisante
worldobjects_ensure_density($pdo, $city, $lat, $lng);
$stmt->execute([$city]);
check('pas de double spawn', (int)$stmt->fetchColumn() === SPAWN_DENSITY);

// 3. Cap journalier
$pdo->prepare("UPDATE local_world_objects SET created_at = NOW() WHERE city = ?")->execute([$city]);
for ($i = 0; $i < SPAWN_DAILY_CAP; $i++) {
    $pdo->prepare("INSERT INTO local_world_objects (city, object_type, lat, lng, xp_value, energy_cost, expires_at) VALUES (?, 'papier', ?, ?, 10, 5, DATE_ADD(NOW(), INTERVAL 48 HOUR))")
        ->execute([$city, $lat + $i * 0.00001, $lng + $i * 0.00001]);
}
// Tout est expiré pour forcer un besoin de spawn, mais le cap est atteint
$pdo->prepare("UPDATE local_world_objects SET status = 'expired' WHERE city = ?")->execute([$city]);
worldobjects_ensure_density($pdo, $city, $lat, $lng);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM local_world_objects WHERE city = ? AND status = 'active'");
$stmt->execute([$city]);
check('cap journalier respecté (0 nouveau)', (int)$stmt->fetchColumn() === 0);

// 4. Cleanliness
$pdo->prepare("DELETE FROM local_world_objects WHERE city = ?")->execute([$city]);
check('ville propre à 100 sans déchets', worldobjects_cleanliness($pdo, $city) === 100);
for ($i = 0; $i < 5; $i++) {
    $pdo->prepare("INSERT INTO local_world_objects (city, object_type, lat, lng, xp_value, energy_cost, expires_at) VALUES (?, 'dechet', ?, ?, 10, 5, DATE_ADD(NOW(), INTERVAL 48 HOUR))")
        ->execute([$city, $lat, $lng]);
}
check('ville propre 90 avec 5 déchets', worldobjects_cleanliness($pdo, $city) === 90);

// 5. Expiration paresseuse
$pdo->prepare("INSERT INTO local_world_objects (city, object_type, lat, lng, xp_value, energy_cost, expires_at) VALUES (?, 'tresor', ?, ?, 50, 10, DATE_SUB(NOW(), INTERVAL 1 HOUR))")
    ->execute([$city, $lat, $lng]);
worldobjects_expire_stale($pdo);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM local_world_objects WHERE city = ? AND object_type = 'tresor' AND status = 'expired'");
$stmt->execute([$city]);
check('objet périmé marqué expired', (int)$stmt->fetchColumn() === 1);

// 6. Quêtes : assignation paresseuse de 3 quêtes/jour
$email = 'quest-test-' . time() . '@example.com';
$pdo->prepare("INSERT INTO local_users (email, password_hash, created_at) VALUES (?, '', NOW())")->execute([$email]);
$userId = (int)$pdo->lastInsertId();
$quests = questsEnsureToday($pdo, $userId);
check('3 quêtes assignées', count($quests) === 3, (string)count($quests));
$quests2 = questsEnsureToday($pdo, $userId);
check('assignation stable', array_column($quests2, 'quest_code') === array_column($quests, 'quest_code'));

// 7. Progression + complétion (une seule fois)
$code = $quests[0]['quest_code'];
$target = (int)$quests[0]['target_count'];
$done = null;
for ($i = 0; $i < $target; $i++) {
    $done = questsProgress($pdo, $userId, $code, 1);
}
check('quête complétée à la cible', $done !== null && (bool)$done['completed'] === true);
check('pas de double complétion', questsProgress($pdo, $userId, $code, 1) === null);

// 8. Streak de ramassage : pickups hier + avant-hier + aujourd'hui = 3
$pdo->prepare("INSERT INTO local_world_objects (city, object_type, lat, lng, xp_value, energy_cost, expires_at) VALUES (?, 'dechet', ?, ?, 10, 5, DATE_ADD(NOW(), INTERVAL 48 HOUR))")
    ->execute([$city, $lat, $lng]);
$oid = (int)$pdo->lastInsertId();
foreach (['NOW()', 'DATE_SUB(NOW(), INTERVAL 1 DAY)', 'DATE_SUB(NOW(), INTERVAL 2 DAY)'] as $when) {
    $pdo->prepare("INSERT INTO local_object_pickups (user_id, object_id, object_type, xp_awarded, picked_at) VALUES (?, ?, 'dechet', 10, $when)")
        ->execute([$userId, $oid]);
    $oid++;
}
check('streak ramassage = 3', questsPickupStreak($pdo, $userId) === 3);

// Cleanup
$pdo->prepare("DELETE FROM local_object_pickups WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_quests WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_actions WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_users WHERE id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_world_objects WHERE city = ?")->execute([$city]);

echo "OK: tous les tests WorldObjects/Quests passent\n";
