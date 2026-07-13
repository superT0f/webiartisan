<?php
/**
 * WebiArtisan API — Route : Gamification
 *
 * GET  /gamification/events
 * POST /gamification/xp
 * GET  /gamification/:id/xp
 * GET  /gamification/leaderboards/city/:city_id
 */

require_once __DIR__ . '/../lib/Gamification.php';

switch ($method) {
    case 'GET':
        if ($action === 'events' || $action === '') {
            gamification_events_list();
        } elseif (filter_var($action, FILTER_VALIDATE_INT) !== false && $param === 'xp') {
            gamification_user_profile_endpoint($pdo, (int)$action);
        } elseif ($action === 'leaderboards' && $param === 'city' && filter_var($segments[3] ?? '', FILTER_VALIDATE_INT) !== false) {
            gamification_city_leaderboard($pdo, (int)$segments[3]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($action === 'xp' || $action === '') {
            gamification_record_xp($pdo, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function gamification_events_list(): void
{
    $events = [];
    foreach (XP_ACTIONS as $key => $cfg) {
        $events[] = ['key' => $key, 'xp' => $cfg['xp'], 'cooldown' => $cfg['cooldown']];
    }
    echo json_encode(['success' => true, 'data' => $events]);
}

function gamification_user_profile_endpoint(PDO $pdo, int $userId): void
{
    $profile = gamificationUserProfile($pdo, $userId);
    if (!$profile) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Utilisateur non trouvé']);
        return;
    }
    // Endpoint public : ne pas exposer l'email, même via le fallback display_name
    $emailLocal = isset($profile['email']) ? strstr($profile['email'], '@', true) : false;
    if (empty($profile['display_name']) || ($emailLocal !== false && $profile['display_name'] === $emailLocal)) {
        $profile['display_name'] = 'Utilisateur';
    }
    unset($profile['email']);
    echo json_encode(['success' => true, 'data' => $profile]);
}

function gamification_record_xp(PDO $pdo, array $body): void
{
    $user = user_require_auth($pdo);
    $actionKey = $body['action'] ?? '';
    $resourceKey = !empty($body['resource_key']) ? $body['resource_key'] : null;
    $metadata = !empty($body['metadata']) ? $body['metadata'] : null;

    if (!isset(XP_ACTIONS[$actionKey])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action inconnue']);
        return;
    }

    if (!empty(XP_ACTIONS[$actionKey]['internal'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action réservée']);
        return;
    }

    $result = gamificationRecordAction($pdo, (int)$user['id'], $actionKey, $resourceKey, $metadata);

    if ($result === null) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Action en cooldown ou limite atteinte']);
        return;
    }

    echo json_encode(['success' => true, 'data' => $result]);
}

function gamification_city_leaderboard(PDO $pdo, int $cityId): void
{
    $stmt = $pdo->prepare("
        SELECT u.id, u.display_name, u.avatar_url, u.level, u.xp
        FROM local_users u
        WHERE EXISTS (
            SELECT 1
            FROM local_user_actions a
            WHERE a.user_id = u.id
              AND (
                  a.metadata->>'$.city_id' = ?
                  OR a.metadata->>'$.artisan_id' IN (
                      SELECT id FROM local_artisans WHERE city_id = ?
                  )
              )
        )
        ORDER BY u.level DESC, u.xp DESC
        LIMIT 50
    ");
    $stmt->execute([$cityId, $cityId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['level'] = (int)$item['level'];
        $item['xp'] = (int)$item['xp'];
    }
    echo json_encode(['success' => true, 'data' => $items]);
}
