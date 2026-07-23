<?php
/**
 * WebIArtisan — Objets du monde (déchets, trésors, cadeaux artisans)
 * Spawn paresseux par densité, expiration paresseuse, score ville propre.
 */

require_once __DIR__ . '/Gamification.php';

const PICKUP_RANGE_M = 150.0;
const SPAWN_DENSITY = 10;
const SPAWN_RADIUS_M = 500;
const SPAWN_MIN_RADIUS_M = 100;
const SPAWN_DAILY_CAP = 200;
const OBJECT_TTL_HOURS = 48;
const TRESOR_TTL_HOURS = 24;
const ARTISAN_GIFT_MAX = 3;
const ARTISAN_GIFT_RANGE_M = 100.0;

const OBJECT_TYPES = [
    'dechet'         => ['xp' => 10, 'energy' => 5,  'weight' => 60, 'category' => 'dechet', 'label' => 'Déchet'],
    'canette'        => ['xp' => 10, 'energy' => 5,  'weight' => 20, 'category' => 'dechet', 'label' => 'Canette'],
    'papier'         => ['xp' => 10, 'energy' => 5,  'weight' => 14, 'category' => 'dechet', 'label' => 'Papier'],
    'tresor'         => ['xp' => 50, 'energy' => 10, 'weight' => 6,  'category' => 'tresor', 'label' => 'Trésor'],
    'cadeau_artisan' => ['xp' => 15, 'energy' => 0,  'weight' => 0,  'category' => 'cadeau', 'label' => 'Cadeau'],
    'big_brother'    => ['xp' => 150, 'energy' => 0, 'weight' => 5,  'category' => 'boss',   'label' => 'Big Brother'],
];

/** Fait apparaître un Big Brother dans l'anneau 100–500 m (TTL 2 h). */
function worldobjects_spawn_boss(PDO $pdo, string $city, float $lat, float $lng): int
{
    $dist = mt_rand(SPAWN_MIN_RADIUS_M, SPAWN_RADIUS_M);
    $bearing = deg2rad(mt_rand(0, 359));
    $dLat = ($dist * cos($bearing)) / 111320.0;
    $dLng = ($dist * sin($bearing)) / (111320.0 * cos(deg2rad($lat)));
    $pdo->prepare("
        INSERT INTO local_world_objects (city, object_type, lat, lng, xp_value, energy_cost, expires_at)
        VALUES (?, 'big_brother', ?, ?, 150, 0, DATE_ADD(NOW(), INTERVAL 2 HOUR))
    ")->execute([$city, round($lat + $dLat, 7), round($lng + $dLng, 7)]);
    return (int)$pdo->lastInsertId();
}

function worldobjects_distance_m(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($a)));
}

function worldobjects_expire_stale(PDO $pdo): void
{
    $pdo->exec("UPDATE local_world_objects SET status = 'expired' WHERE status = 'active' AND expires_at < NOW()");
}

function worldobjects_cleanliness(PDO $pdo, string $city): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM local_world_objects
        WHERE city = ? AND status = 'active' AND object_type IN ('dechet','canette','papier')
    ");
    $stmt->execute([$city]);
    return max(0, 100 - ((int)$stmt->fetchColumn()) * 2);
}

/** Total d'objets ramassés dans la ville (toutes périodes, tous types). */
function worldobjects_collected_total(PDO $pdo, string $city): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM local_object_pickups p
        JOIN local_world_objects o ON o.id = p.object_id
        WHERE o.city = ?
    ");
    $stmt->execute([$city]);
    return (int)$stmt->fetchColumn();
}

/**
 * Podium public des 3 meilleurs nettoyeurs de la ville.
 * Anonymisation identique à /gamification/:id/xp : un display_name vide ou
 * égal au préfixe email devient « Utilisateur ».
 */
function worldobjects_top_cleaners(PDO $pdo, string $city): array
{
    $stmt = $pdo->prepare("
        SELECT u.display_name, u.email, COUNT(*) AS n
        FROM local_object_pickups p
        JOIN local_world_objects o ON o.id = p.object_id
        JOIN local_users u ON u.id = p.user_id
        WHERE o.city = ?
        GROUP BY p.user_id
        ORDER BY n DESC, p.user_id ASC
        LIMIT 3
    ");
    $stmt->execute([$city]);
    $top = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = $row['display_name'];
        $emailLocal = strstr((string)$row['email'], '@', true);
        if (empty($name) || ($emailLocal !== false && $name === $emailLocal)) {
            $name = 'Utilisateur';
        }
        $top[] = ['display_name' => $name, 'count' => (int)$row['n']];
    }
    return $top;
}

function worldobjects_random_type(): string
{
    $total = 0;
    foreach (OBJECT_TYPES as $cfg) {
        $total += $cfg['weight'];
    }
    $roll = mt_rand(1, $total);
    foreach (OBJECT_TYPES as $type => $cfg) {
        $roll -= $cfg['weight'];
        if ($roll <= 0) {
            return $type;
        }
    }
    return 'dechet';
}

/**
 * Garantit SPAWN_DENSITY objets actifs dans SPAWN_RADIUS_M autour du point,
 * borné par SPAWN_DAILY_CAP créations système par ville et par jour.
 */
function worldobjects_ensure_density(PDO $pdo, string $city, float $lat, float $lng): void
{
    // Bounding-box ~500 m (0.005° lat, 0.007° lng à ~49°)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM local_world_objects
        WHERE city = ? AND status = 'active'
          AND lat BETWEEN ? AND ? AND lng BETWEEN ? AND ?
    ");
    $stmt->execute([$city, $lat - 0.005, $lat + 0.005, $lng - 0.007, $lng + 0.007]);
    $missing = SPAWN_DENSITY - (int)$stmt->fetchColumn();
    if ($missing <= 0) {
        return;
    }

    $capStmt = $pdo->prepare("
        SELECT COUNT(*) FROM local_world_objects
        WHERE city = ? AND spawned_by = 'system' AND created_at >= CURDATE()
    ");
    $capStmt->execute([$city]);
    $missing = min($missing, max(0, SPAWN_DAILY_CAP - (int)$capStmt->fetchColumn()));
    if ($missing <= 0) {
        return;
    }

    $insert = $pdo->prepare("
        INSERT INTO local_world_objects (city, object_type, lat, lng, xp_value, energy_cost, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    for ($i = 0; $i < $missing; $i++) {
        $type = worldobjects_random_type();
        $cfg = OBJECT_TYPES[$type];
        // Anneau 100–500 m autour du joueur
        $dist = mt_rand(SPAWN_MIN_RADIUS_M, SPAWN_RADIUS_M);
        $bearing = deg2rad(mt_rand(0, 359));
        $dLat = ($dist * cos($bearing)) / 111320.0;
        $dLng = ($dist * sin($bearing)) / (111320.0 * cos(deg2rad($lat)));
        $ttl = ($type === 'tresor' || $type === 'big_brother') ? TRESOR_TTL_HOURS : OBJECT_TTL_HOURS;
        $insert->execute([
            $city, $type,
            round($lat + $dLat, 7), round($lng + $dLng, 7),
            $cfg['xp'], $cfg['energy'],
            date('Y-m-d H:i:s', time() + $ttl * 3600),
        ]);
    }
}
