<?php
/**
 * WebiArtisan API — Route : Objets du monde (déchets, trésors, cadeaux)
 *
 * GET    /objects?lat=&lng=&city=   — objets actifs + énergie + propreté (auth)
 * POST   /objects/:id/pickup        — ramasser un objet (50 m max, coût énergie)
 * GET    /objects/mine              — cadeaux de l'artisan connecté
 * POST   /objects                   — placer un cadeau (artisan premium)
 * DELETE /objects/:id               — retirer un cadeau (artisan propriétaire)
 */

require_once __DIR__ . '/../lib/UserAuth.php';
require_once __DIR__ . '/../lib/ArtisanAuth.php';
require_once __DIR__ . '/../lib/Gamification.php';
require_once __DIR__ . '/../lib/WorldObjects.php';
require_once __DIR__ . '/../lib/Quests.php';
require_once __DIR__ . '/../lib/Games.php';
require_once __DIR__ . '/../lib/AppLogger.php';

switch ($method) {
    case 'GET':
        if ($action === 'mine') {
            objects_mine($pdo);
        } elseif ($action === '') {
            objects_list($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (filter_var($action, FILTER_VALIDATE_INT) !== false && $param === 'pickup') {
            objects_pickup($pdo, (int)$action, $body);
        } elseif ($action === '') {
            objects_create_gift($pdo, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'DELETE':
        if (filter_var($action, FILTER_VALIDATE_INT) !== false) {
            objects_delete_gift($pdo, (int)$action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function objects_list(PDO $pdo): void
{
    $user = user_require_auth($pdo);
    $lat = $_GET['lat'] ?? null;
    $lng = $_GET['lng'] ?? null;
    $city = trim((string)($_GET['city'] ?? ''));
    if (!is_numeric($lat) || !is_numeric($lng) || $city === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Position et ville requises']);
        return;
    }
    $lat = (float)$lat;
    $lng = (float)$lng;

    $cityStmt = $pdo->prepare("SELECT 1 FROM local_cities WHERE slug = ? AND is_active = 1");
    $cityStmt->execute([$city]);
    if (!$cityStmt->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ville inconnue']);
        return;
    }

    worldobjects_expire_stale($pdo);
    worldobjects_ensure_density($pdo, $city, $lat, $lng);

    // Garantie admin : toujours un Big Brother à proximité (debug/démo)
    $adminStmt = $pdo->prepare("SELECT 1 FROM local_artisans WHERE user_id = ? AND is_admin = 1 AND status = 'active' LIMIT 1");
    $adminStmt->execute([(int)$user['id']]);
    if ($adminStmt->fetchColumn()) {
        $bossStmt = $pdo->prepare("
            SELECT 1 FROM local_world_objects
            WHERE city = ? AND status = 'active' AND object_type = 'big_brother'
              AND lat BETWEEN ? AND ? AND lng BETWEEN ? AND ?
            LIMIT 1
        ");
        $bossStmt->execute([$city, $lat - 0.01, $lat + 0.01, $lng - 0.014, $lng + 0.014]);
        if (!$bossStmt->fetchColumn()) {
            worldobjects_spawn_boss($pdo, $city, $lat, $lng);
        }
    }

    // Bounding-box ~1 km (0.01° lat, 0.014° lng à ~49°)
    // Les boss déjà combattus par le joueur sont exclus.
    $stmt = $pdo->prepare("
        SELECT o.id, o.object_type, o.lat, o.lng, o.xp_value, o.energy_cost
        FROM local_world_objects o
        WHERE o.city = ? AND o.status = 'active'
          AND o.lat BETWEEN ? AND ? AND o.lng BETWEEN ? AND ?
          AND (o.object_type != 'big_brother' OR NOT EXISTS (
              SELECT 1 FROM local_boss_fights f
              WHERE f.object_id = o.id AND f.user_id = ?
          ))
        ORDER BY o.id DESC
        LIMIT 50
    ");
    $stmt->execute([$city, $lat - 0.01, $lat + 0.01, $lng - 0.014, $lng + 0.014, (int)$user['id']]);

    $objects = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $distance = worldobjects_distance_m($lat, $lng, (float)$row['lat'], (float)$row['lng']);
        $objects[] = [
            'id'          => (int)$row['id'],
            'type'        => $row['object_type'],
            'label'       => OBJECT_TYPES[$row['object_type']]['label'] ?? $row['object_type'],
            'lat'         => (float)$row['lat'],
            'lng'         => (float)$row['lng'],
            'xp'          => (int)$row['xp_value'],
            'energy_cost' => (int)$row['energy_cost'],
            'distance_m'  => (int)round($distance),
        ];
    }
    usort($objects, fn($a, $b) => $a['distance_m'] <=> $b['distance_m']);

    echo json_encode([
        'success' => true,
        'data'    => [
            'objects'              => $objects,
            'energy'               => energyGet($pdo, (int)$user['id']),
            'city_cleanliness'     => worldobjects_cleanliness($pdo, $city),
            'city_collected_total' => worldobjects_collected_total($pdo, $city),
            'top_cleaners'         => worldobjects_top_cleaners($pdo, $city),
        ],
    ]);
}

function objects_pickup(PDO $pdo, int $objectId, array $body): void
{
    $user = user_require_auth($pdo);
    $userId = (int)$user['id'];

    $lat = $body['lat'] ?? null;
    $lng = $body['lng'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng)
        || (float)$lat < -90 || (float)$lat > 90
        || (float)$lng < -180 || (float)$lng > 180) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Position invalide']);
        return;
    }
    $lat = (float)$lat;
    $lng = (float)$lng;

    $questsCompleted = [];
    $energy = null;
    $xp = 0;
    $result = null;

    $pdo->beginTransaction();
    try {
        // Verrou user (pattern gamification) puis verrou objet
        $pdo->prepare("SELECT 1 FROM local_users WHERE id = ? FOR UPDATE")->execute([$userId]);
        $stmt = $pdo->prepare("SELECT * FROM local_world_objects WHERE id = ? FOR UPDATE");
        $stmt->execute([$objectId]);
        $obj = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$obj || $obj['status'] !== 'active' || strtotime($obj['expires_at']) < time()) {
            if ($obj && $obj['status'] === 'active') {
                $pdo->prepare("UPDATE local_world_objects SET status = 'expired' WHERE id = ?")->execute([$objectId]);
            }
            $pdo->rollBack();
            http_response_code(410);
            echo json_encode(['success' => false, 'error' => 'gone', 'message' => 'Trop tard, un voisin l\'a eu !']);
            return;
        }

        if ($obj['object_type'] === 'big_brother') {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'not_pickable', 'message' => 'Le Big Brother ne se ramasse pas — affronte-le en duel !']);
            return;
        }

        $distance = worldobjects_distance_m($lat, $lng, (float)$obj['lat'], (float)$obj['lng']);
        if ($distance > PICKUP_RANGE_M) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error'   => 'distance',
                'message' => 'Trop loin de l\'objet (50 m maximum)',
                'data'    => ['distance_m' => (int)round($distance)],
            ]);
            return;
        }

        $cost = (int)$obj['energy_cost'];
        if (!energySpend($pdo, $userId, $cost)) {
            $energy = energyGet($pdo, $userId, true);
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error'   => 'energy',
                'message' => 'Plus d\'énergie — reviens plus tard ou fais un check-in chez un artisan pour en récupérer.',
                'data'    => ['energy' => $energy],
            ]);
            return;
        }

        $xp = (int)$obj['xp_value'];
        $type = $obj['object_type'];
        $category = OBJECT_TYPES[$type]['category'] ?? $type;

        $pdo->prepare("
            UPDATE local_world_objects
            SET status = 'collected', collected_by = ?, collected_at = NOW()
            WHERE id = ?
        ")->execute([$userId, $objectId]);

        $pdo->prepare("
            INSERT INTO local_object_pickups (user_id, object_id, object_type, xp_awarded)
            VALUES (?, ?, ?, ?)
        ")->execute([$userId, $objectId, $type, $xp]);

        $result = gamificationRecordAction(
            $pdo, $userId, 'object_pickup', "object:$objectId",
            ['city' => $obj['city'], 'object_type' => $type, 'object_category' => $category, 'object_id' => $objectId],
            true, true, $xp
        );

        // Progression des quêtes du jour
        foreach ([
            'collect_10_total' => 1,
            'collect_5_dechets' => $category === 'dechet' ? 1 : 0,
            'find_1_tresor' => $type === 'tresor' ? 1 : 0,
        ] as $code => $delta) {
            if ($delta > 0) {
                $q = questsProgress($pdo, $userId, $code, $delta);
                if ($q) $questsCompleted[] = $q;
            }
        }
        $streak = questsPickupStreak($pdo, $userId);
        $q = questsSetProgress($pdo, $userId, 'clean_streak', $streak);
        if ($q) $questsCompleted[] = $q;

        $energy = energyGet($pdo, $userId, true);
        $pdo->commit();

        if (function_exists('app_log')) {
            app_log('info', '[OBJECTS] pickup', ['user_id' => $userId, 'object_id' => $objectId, 'type' => $type, 'xp' => $xp]);
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[OBJECTS] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    echo json_encode([
        'success' => true,
        'data'    => [
            'xp_awarded'       => $xp,
            'energy'           => $energy,
            'level_up'         => (bool)($result['level_up'] ?? false),
            'new_badges'       => $result['new_badges'] ?? [],
            'quests_completed' => $questsCompleted,
        ],
    ]);
}

function objects_mine(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);
    $stmt = $pdo->prepare("
        SELECT id, object_type, lat, lng, status, expires_at, created_at
        FROM local_world_objects
        WHERE artisan_id = ? AND spawned_by = 'artisan'
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([(int)$artisan['id']]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function objects_create_gift(PDO $pdo, array $body): void
{
    $artisan = artisan_require_auth($pdo);
    $artisanId = (int)$artisan['id'];

    if (!artisanIsPremium($pdo, $artisanId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cadeaux réservés au plan premium']);
        return;
    }

    $lat = $body['lat'] ?? null;
    $lng = $body['lng'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng)
        || (float)$lat < -90 || (float)$lat > 90
        || (float)$lng < -180 || (float)$lng > 180) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Position invalide']);
        return;
    }
    $lat = (float)$lat;
    $lng = (float)$lng;

    // artisan_require_auth ne sélectionne pas les coordonnées : requête dédiée
    $posStmt = $pdo->prepare("SELECT latitude, longitude FROM local_artisans WHERE id = ?");
    $posStmt->execute([$artisanId]);
    $pos = $posStmt->fetch(PDO::FETCH_ASSOC);
    if (!$pos || $pos['latitude'] === null || $pos['longitude'] === null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Boutique sans coordonnées GPS']);
        return;
    }
    $distance = worldobjects_distance_m($lat, $lng, (float)$pos['latitude'], (float)$pos['longitude']);
    if ($distance > ARTISAN_GIFT_RANGE_M) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Le cadeau doit être à moins de 100 m de la boutique']);
        return;
    }

    worldobjects_expire_stale($pdo);
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM local_world_objects
        WHERE artisan_id = ? AND spawned_by = 'artisan' AND status = 'active'
    ");
    $countStmt->execute([$artisanId]);
    if ((int)$countStmt->fetchColumn() >= ARTISAN_GIFT_MAX) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Maximum 3 cadeaux actifs']);
        return;
    }

    $cityStmt = $pdo->prepare("SELECT slug FROM local_cities WHERE id = ?");
    $cityStmt->execute([(int)$artisan['city_id']]);
    $city = $cityStmt->fetchColumn() ?: 'livry';

    $cfg = OBJECT_TYPES['cadeau_artisan'];
    $pdo->prepare("
        INSERT INTO local_world_objects
            (city, object_type, lat, lng, xp_value, energy_cost, spawned_by, artisan_id, expires_at)
        VALUES (?, 'cadeau_artisan', ?, ?, ?, ?, 'artisan', ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))
    ")->execute([$city, $lat, $lng, $cfg['xp'], $cfg['energy'], $artisanId]);

    http_response_code(201);
    echo json_encode(['success' => true, 'data' => ['id' => (int)$pdo->lastInsertId()]]);
}

function objects_delete_gift(PDO $pdo, int $objectId): void
{
    $artisan = artisan_require_auth($pdo);
    $stmt = $pdo->prepare("
        DELETE FROM local_world_objects
        WHERE id = ? AND artisan_id = ? AND spawned_by = 'artisan'
    ");
    $stmt->execute([$objectId, (int)$artisan['id']]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Cadeau introuvable']);
        return;
    }
    echo json_encode(['success' => true, 'data' => ['deleted' => $objectId]]);
}
