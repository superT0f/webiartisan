<?php
/**
 * WebiArtisan — Tests lib énergie + badges meta_filter (Task 2).
 * Run: docker compose exec -T php php /var/www/api/tests/test_energy.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Gamification.php';

$pdo = getDatabase();

// Compte de test jetable
$email = 'energy-test-' . time() . '@example.com';
$pdo->prepare("INSERT INTO local_users (email, password_hash, created_at) VALUES (?, '', NOW())")->execute([$email]);
$userId = (int)$pdo->lastInsertId();
if ($userId === 0) { echo "FAIL: création user\n"; exit(1); }

function check(string $name, bool $cond, string $detail = ''): void {
    echo ($cond ? 'OK' : 'FAIL') . ": $name" . ($detail ? " — $detail" : '') . "\n";
    if (!$cond) exit(1);
}

// 1. Énergie initiale = 100 (défaut migration)
$e = energyGet($pdo, $userId);
check('énergie initiale 100/100', $e['current'] === 100 && $e['max'] === 100 && $e['next_energy_at'] === null);

// 2. Spend OK / insuffisant
$pdo->beginTransaction();
$pdo->prepare("SELECT 1 FROM local_users WHERE id = ? FOR UPDATE")->execute([$userId]);
check('spend 5 OK', energySpend($pdo, $userId, 5) === true);
check('spend 200 refusé', energySpend($pdo, $userId, 200) === false);
$pdo->commit();
$e = energyGet($pdo, $userId);
check('solde 95 après spend', $e['current'] === 95);

// 3. Regen paresseuse : simuler energy_updated_at il y a 30 min → +15
$pdo->prepare("UPDATE local_users SET energy = 50, energy_updated_at = DATE_SUB(NOW(), INTERVAL 30 MINUTE) WHERE id = ?")->execute([$userId]);
$e = energyGet($pdo, $userId);
check('regen paresseuse +15 (50→65)', $e['current'] === 65, "got {$e['current']}");
check('next_energy_at présent si < max', $e['next_energy_at'] !== null);

// 4. Regen plafonnée au max
$pdo->prepare("UPDATE local_users SET energy = 98, energy_updated_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = ?")->execute([$userId]);
$e = energyGet($pdo, $userId);
check('regen plafonnée à 100', $e['current'] === 100 && $e['next_energy_at'] === null);

// 5. energyAdd plafonnée
$pdo->prepare("UPDATE local_users SET energy = 90, energy_updated_at = NOW() WHERE id = ?")->execute([$userId]);
$pdo->beginTransaction();
$pdo->prepare("SELECT 1 FROM local_users WHERE id = ? FOR UPDATE")->execute([$userId]);
energyAdd($pdo, $userId, 20);
$pdo->commit();
$e = energyGet($pdo, $userId);
check('energyAdd 90+20 → 100', $e['current'] === 100);

// 6. XP override sur action interne object_pickup + badge premier_ramassage
$res = gamificationRecordAction($pdo, $userId, 'object_pickup', 'object:999999', ['object_type' => 'dechet', 'object_category' => 'dechet', 'city' => 'test'], false, true, 10);
check('xpOverride 10 crédité', ($res['xp_gained'] ?? null) === 10, json_encode($res));
check('badge premier_ramassage débloqué', in_array('premier_ramassage', array_column($res['new_badges'] ?? [], 'key'), true));

// 7. Badge eco_warrior via meta_filter : 48 canettes (49 déchets au total)
for ($i = 0; $i < 48; $i++) {
    gamificationRecordAction($pdo, $userId, 'object_pickup', "object:99$i", ['object_type' => 'canette', 'object_category' => 'dechet', 'city' => 'test'], false, true, 10);
}
// La 50e action de catégorie dechet débloque eco_warrior
$res = gamificationRecordAction($pdo, $userId, 'object_pickup', 'object:fifty', ['object_type' => 'papier', 'object_category' => 'dechet', 'city' => 'test'], false, true, 10);
check('badge eco_warrior à 50 déchets', in_array('eco_warrior', array_column($res['new_badges'] ?? [], 'key'), true));
// Un trésor seul ne débloque pas chasseur_tresor
$res = gamificationRecordAction($pdo, $userId, 'object_pickup', 'object:last', ['object_type' => 'tresor', 'object_category' => 'tresor', 'city' => 'test'], false, true, 50);
check('badge chasseur_tresor PAS à 1 trésor', !in_array('chasseur_tresor', array_column($res['new_badges'] ?? [], 'key'), true));

// Cleanup
$pdo->prepare("DELETE FROM local_user_actions WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_cooldowns WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_user_badges WHERE user_id = ?")->execute([$userId]);
$pdo->prepare("DELETE FROM local_users WHERE id = ?")->execute([$userId]);

echo "OK: tous les tests énergie/badges passent\n";
