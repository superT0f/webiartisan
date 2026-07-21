<?php
/**
 * WebIArtisan API — Route : Check-ins GPS (carte jouable)
 *
 * POST /checkin                  — enregistre un check-in (200 m max,
 *                                  100 XP / 24 h / point puis 10 XP / 10 min)
 * GET  /checkin/status?lat=&lng= — points à portée et état des cooldowns
 */

require_once __DIR__ . '/../lib/UserAuth.php';
require_once __DIR__ . '/../lib/Gamification.php';
require_once __DIR__ . '/../lib/Quests.php';
require_once __DIR__ . '/../lib/AppLogger.php';

const CHECKIN_RANGE_M = 200.0;
const CHECKIN_DAILY_SECONDS = 86400;   // 24 h
const CHECKIN_RECHARGE_SECONDS = 600;  // 10 min
const CHECKIN_DAILY_XP = 100;
const CHECKIN_RECHARGE_XP = 10;

switch ($method) {
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($action === '' || $action === 'checkin') {
            checkin_create($pdo, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'GET':
        if ($action === 'status' || $action === '') {
            checkin_status($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function checkin_distance_m(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($a)));
}

function checkin_find_target(PDO $pdo, string $type, int $id): ?array
{
    if ($type === 'artisan') {
        $stmt = $pdo->prepare("
            SELECT a.id, a.latitude, a.longitude, a.company_name AS name, c.slug AS city
            FROM local_artisans a
            JOIN local_cities c ON c.id = a.city_id
            WHERE a.id = ? AND a.status = 'active' AND c.is_active = 1
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT p.id, p.latitude, p.longitude, p.name, c.slug AS city
            FROM local_pois p
            JOIN local_cities c ON c.id = p.city_id
            WHERE p.id = ? AND p.is_active = 1 AND c.is_active = 1
        ");
    }
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['latitude'] === null || $row['longitude'] === null) {
        return null;
    }
    return $row;
}

function checkin_cooldown_state(PDO $pdo, int $userId, string $actionKey, string $resourceKey, int $ttlSeconds): array
{
    $stmt = $pdo->prepare("
        SELECT last_at FROM local_user_cooldowns
        WHERE user_id = ? AND action_key = ? AND resource_key = ?
    ");
    $stmt->execute([$userId, $actionKey, $resourceKey]);
    $last = $stmt->fetchColumn();
    if (!$last) {
        return ['available' => true, 'next_at' => null];
    }
    $next = strtotime($last) + $ttlSeconds;
    if (time() >= $next) {
        return ['available' => true, 'next_at' => null];
    }
    return ['available' => false, 'next_at' => date('c', $next)];
}

function checkin_touch_cooldown(PDO $pdo, int $userId, string $actionKey, string $period, string $resourceKey): void
{
    $pdo->prepare("
        INSERT INTO local_user_cooldowns (user_id, action_key, period, resource_key, last_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE last_at = NOW()
    ")->execute([$userId, $actionKey, $period, $resourceKey]);
}

function checkin_create(PDO $pdo, array $body): void
{
    $user = user_require_auth($pdo);
    $userId = (int)$user['id'];

    $targetType = $body['target_type'] ?? '';
    $targetId = (int)($body['target_id'] ?? 0);
    $lat = $body['lat'] ?? null;
    $lng = $body['lng'] ?? null;

    if (!in_array($targetType, ['artisan', 'poi'], true) || $targetId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cible invalide']);
        return;
    }
    if (!is_numeric($lat) || !is_numeric($lng)
        || (float)$lat < -90 || (float)$lat > 90
        || (float)$lng < -180 || (float)$lng > 180) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Position invalide']);
        return;
    }
    $lat = (float)$lat;
    $lng = (float)$lng;

    $target = checkin_find_target($pdo, $targetType, $targetId);
    if (!$target) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Point introuvable']);
        return;
    }

    $distance = checkin_distance_m($lat, $lng, (float)$target['latitude'], (float)$target['longitude']);
    if ($distance > CHECKIN_RANGE_M) {
        if (function_exists('app_log')) {
            app_log('info', '[CHECKIN] 422 trop loin', ['target' => "{$targetType}:{$targetId}", 'distance_m' => (int)round($distance)]);
        }
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error'   => 'Trop loin du point (200 m maximum)',
            'data'    => ['distance_m' => (int)round($distance)],
        ]);
        return;
    }

    $resourceKey = "{$targetType}:{$targetId}";
    $questsCompleted = [];
    $energy = null;

    $pdo->beginTransaction();
    try {
        // Serialize per-user gamification writes (same pattern as the engine)
        $pdo->prepare("SELECT 1 FROM local_users WHERE id = ? FOR UPDATE")->execute([$userId]);

        $daily = checkin_cooldown_state($pdo, $userId, 'poi_daily', $resourceKey, CHECKIN_DAILY_SECONDS);

        if ($daily['available']) {
            $xp = CHECKIN_DAILY_XP;
            $xpAction = 'poi_checkin';
            checkin_touch_cooldown($pdo, $userId, 'poi_daily', 'daily', $resourceKey);
            checkin_touch_cooldown($pdo, $userId, 'poi_spin', 'recharge', $resourceKey);
        } else {
            $spin = checkin_cooldown_state($pdo, $userId, 'poi_spin', $resourceKey, CHECKIN_RECHARGE_SECONDS);
            if (!$spin['available']) {
                $pdo->rollBack();
                if (function_exists('app_log')) {
                    app_log('info', '[CHECKIN] 429 cooldown', ['user_id' => $userId, 'target' => $resourceKey, 'next_at' => $spin['next_at']]);
                }
                http_response_code(429);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Point en recharge, réessayez plus tard',
                    'data'    => ['next_spin_at' => $spin['next_at']],
                ]);
                return;
            }
            $xp = CHECKIN_RECHARGE_XP;
            $xpAction = 'poi_checkin_recharge';
            checkin_touch_cooldown($pdo, $userId, 'poi_spin', 'recharge', $resourceKey);
        }

        $pdo->prepare("
            INSERT INTO local_checkins (user_id, city, target_type, target_id, xp_awarded)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$userId, $target['city'], $targetType, $targetId, $xp]);

        $result = gamificationRecordAction(
            $pdo,
            $userId,
            $xpAction,
            $resourceKey,
            ['city' => $target['city'], 'target_type' => $targetType, 'target_id' => $targetId, 'distance_m' => (int)round($distance)],
            true,
            true
        );

        // Bonus énergie : le check-in restaure +20 ⚡
        energyAdd($pdo, $userId, ENERGY_PER_CHECKIN);

        // Quête du jour : visite d'artisans
        if ($targetType === 'artisan') {
            $q = questsProgress($pdo, $userId, 'visit_2_artisans', 1);
            if ($q) $questsCompleted[] = $q;
        }
        $energy = energyGet($pdo, $userId, true);

        $pdo->commit();
        if (function_exists('app_log')) {
            app_log('info', '[CHECKIN] success', ['user_id' => $userId, 'target' => $resourceKey, 'xp' => $xp, 'distance_m' => (int)round($distance)]);
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[CHECKIN] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    echo json_encode([
        'success' => true,
        'data'    => [
            'xp_awarded'       => $xp,
            'next_spin_at'     => date('c', time() + CHECKIN_RECHARGE_SECONDS),
            'level_up'         => (bool)($result['level_up'] ?? false),
            'new_badges'       => $result['new_badges'] ?? [],
            'energy_bonus'     => ENERGY_PER_CHECKIN,
            'energy'           => $energy,
            'quests_completed' => $questsCompleted,
        ],
    ]);
}

function checkin_status(PDO $pdo): void
{
    $lat = $_GET['lat'] ?? null;
    $lng = $_GET['lng'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Position requise']);
        return;
    }
    $lat = (float)$lat;
    $lng = (float)$lng;

    // Optional auth: cooldown state is only computed for a connected user
    $userId = user_optional_auth($pdo);

    // Bounding-box prefilter (~250 m: 0.0025° lat, 0.0035° lng at ~49° latitude)
    $candidates = [];

    $artisanStmt = $pdo->prepare("
        SELECT a.id, a.latitude, a.longitude, a.company_name AS name
        FROM local_artisans a
        JOIN local_cities c ON c.id = a.city_id
        WHERE a.status = 'active' AND c.is_active = 1
          AND a.latitude IS NOT NULL AND a.longitude IS NOT NULL
          AND a.latitude BETWEEN ? AND ?
          AND a.longitude BETWEEN ? AND ?
    ");
    $artisanStmt->execute([$lat - 0.0025, $lat + 0.0025, $lng - 0.0035, $lng + 0.0035]);
    foreach ($artisanStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['target_type'] = 'artisan';
        $candidates[] = $row;
    }

    $poiStmt = $pdo->prepare("
        SELECT p.id, p.latitude, p.longitude, p.name
        FROM local_pois p
        JOIN local_cities c ON c.id = p.city_id
        WHERE p.is_active = 1 AND c.is_active = 1
          AND p.latitude IS NOT NULL AND p.longitude IS NOT NULL
          AND p.latitude BETWEEN ? AND ?
          AND p.longitude BETWEEN ? AND ?
    ");
    $poiStmt->execute([$lat - 0.0025, $lat + 0.0025, $lng - 0.0035, $lng + 0.0035]);
    foreach ($poiStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['target_type'] = 'poi';
        $candidates[] = $row;
    }

    $targets = [];
    foreach ($candidates as $row) {
        $distance = checkin_distance_m($lat, $lng, (float)$row['latitude'], (float)$row['longitude']);
        if ($distance > CHECKIN_RANGE_M) {
            continue;
        }

        $dailyAvailable = null;
        $nextSpinAt = null;
        if ($userId) {
            $resourceKey = "{$row['target_type']}:{$row['id']}";
            $daily = checkin_cooldown_state($pdo, (int)$userId, 'poi_daily', $resourceKey, CHECKIN_DAILY_SECONDS);
            $dailyAvailable = $daily['available'];
            if (!$dailyAvailable) {
                $spin = checkin_cooldown_state($pdo, (int)$userId, 'poi_spin', $resourceKey, CHECKIN_RECHARGE_SECONDS);
                $nextSpinAt = $spin['available'] ? null : $spin['next_at'];
            }
        }

        $targets[] = [
            'target_type'     => $row['target_type'],
            'target_id'       => (int)$row['id'],
            'name'            => $row['name'],
            'distance_m'      => (int)round($distance),
            'daily_available' => $dailyAvailable,
            'next_spin_at'    => $nextSpinAt,
        ];
    }

    usort($targets, fn($a, $b) => $a['distance_m'] <=> $b['distance_m']);
    $targets = array_slice($targets, 0, 20);

    echo json_encode(['success' => true, 'data' => $targets]);
}
