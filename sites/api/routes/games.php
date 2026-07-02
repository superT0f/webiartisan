<?php
/**
 * WebIArtisan API — Route : Mini-jeux
 *
 * GET  /games/types
 * GET  /games?city=livry&artisan_id=...
 * GET  /games/:id
 * POST /games/:id/play
 * POST /games/:id/claim
 */

require_once __DIR__ . '/../lib/Games.php';

switch ($method) {
    case 'GET':
        if ($action === 'types') {
            games_types_list($pdo);
        } elseif ($action === '' || $action === 'list') {
            games_list($pdo);
        } elseif (filter_var($action, FILTER_VALIDATE_INT) && !$param) {
            games_get($pdo, (int)$action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (filter_var($action, FILTER_VALIDATE_INT) && $param === 'play') {
            games_play($pdo, (int)$action, $body);
        } elseif (filter_var($action, FILTER_VALIDATE_INT) && $param === 'claim') {
            games_claim($pdo, (int)$action, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function games_types_list(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        SELECT id, `key`, label_fr, description, is_premium, is_active, default_config, engine_component
        FROM local_game_types
        WHERE is_active = 1
        ORDER BY is_premium ASC, label_fr ASC
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['is_premium'] = (bool)$item['is_premium'];
        $item['is_active'] = (bool)$item['is_active'];
        $item['default_config'] = json_decode($item['default_config'], true);
    }
    echo json_encode(['success' => true, 'data' => $items]);
}

function games_list(PDO $pdo): void
{
    $citySlug = $_GET['city'] ?? null;
    $artisanId = isset($_GET['artisan_id']) ? (int)$_GET['artisan_id'] : null;

    $sql = "
        SELECT i.*, gt.`key` AS game_type_key, gt.label_fr AS game_type_label,
               gt.is_premium, gt.engine_component,
               a.company_name AS artisan_name, c.slug AS city_slug
        FROM local_game_instances i
        JOIN local_game_types gt ON gt.id = i.game_type_id
        JOIN local_artisans a ON a.id = i.artisan_id
        JOIN local_cities c ON c.id = i.city_id
        WHERE i.is_active = 1 AND a.status = 'active'
          AND (i.starts_at IS NULL OR i.starts_at <= NOW())
          AND (i.ends_at IS NULL OR i.ends_at >= NOW())
    ";
    $params = [];

    if ($citySlug) {
        $sql .= " AND c.slug = ?";
        $params[] = $citySlug;
    }
    if ($artisanId) {
        $sql .= " AND i.artisan_id = ?";
        $params[] = $artisanId;
    }

    $sql .= " ORDER BY gt.is_premium ASC, i.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['is_premium'] = (bool)$item['is_premium'];
        $item['config'] = json_decode($item['config'], true);
        unset($item['game_type_id']);
    }

    echo json_encode(['success' => true, 'data' => $items]);
}

function games_get(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("
        SELECT i.*, gt.`key` AS game_type_key, gt.label_fr AS game_type_label,
               gt.is_premium, gt.engine_component,
               a.company_name AS artisan_name, c.slug AS city_slug
        FROM local_game_instances i
        JOIN local_game_types gt ON gt.id = i.game_type_id
        JOIN local_artisans a ON a.id = i.artisan_id
        JOIN local_cities c ON c.id = i.city_id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Jeu non trouvé']);
        return;
    }

    $item['id'] = (int)$item['id'];
    $item['is_premium'] = (bool)$item['is_premium'];
    $item['config'] = json_decode($item['config'], true);
    $item['is_playable'] = games_instance_is_playable($item);

    // User play state
    $userId = null;
    $token = user_get_session_token();
    if ($token) {
        $usr = $pdo->prepare("SELECT id FROM local_users WHERE session_token = ? AND session_exp > NOW() LIMIT 1");
        $usr->execute([$token]);
        $userId = $usr->fetchColumn();
    }

    $maxPlays = (int)$item['max_plays_per_user'];
    $item['user_plays_count'] = $userId ? games_count_user_plays($pdo, $id, (int)$userId) : 0;
    $item['can_play'] = $item['is_playable'] && ($maxPlays === 0 || $item['user_plays_count'] < $maxPlays);

    echo json_encode(['success' => true, 'data' => $item]);
}

function games_play(PDO $pdo, int $id, array $body): void
{
    $user = user_require_auth($pdo);

    $stmt = $pdo->prepare("
        SELECT i.*, gt.`key` AS game_type_key, gt.is_premium
        FROM local_game_instances i
        JOIN local_game_types gt ON gt.id = i.game_type_id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $instance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$instance) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Jeu non trouvé']);
        return;
    }

    if ((bool)$instance['is_premium']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Ce jeu est réservé aux artisans premium']);
        return;
    }

    if (!games_instance_is_playable($instance)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ce jeu n\'est pas actif']);
        return;
    }

    $maxPlays = (int)$instance['max_plays_per_user'];
    $plays = games_count_user_plays($pdo, $id, (int)$user['id']);
    if ($maxPlays > 0 && $plays >= $maxPlays) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Limite de participations atteinte']);
        return;
    }

    $lastPlay = games_last_play_at($pdo, $id, (int)$user['id']);
    $cooldownHours = (int)$instance['play_cooldown_hours'];
    if ($lastPlay && $cooldownHours > 0) {
        $next = (new DateTimeImmutable($lastPlay))->modify("+{$cooldownHours} hours");
        if (new DateTimeImmutable() < $next) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Veuillez attendre avant de rejouer']);
            return;
        }
    }

    $result = match ($instance['game_type_key']) {
        'coupon' => ['reward' => games_resolve_reward($pdo, $id)],
        'poll' => ['choice' => $body['choice'] ?? null],
        'vote' => ['choice' => $body['choice'] ?? null],
        default => [],
    };

    games_record_play($pdo, $id, (int)$user['id'], $result, 10);

    echo json_encode(['success' => true, 'data' => $result]);
}

function games_claim(PDO $pdo, int $id, array $body): void
{
    user_require_auth($pdo);
    // Claim logic: update reward stock and return coupon code.
    // Minimal implementation: return the reward label from the last play.
    http_response_code(501);
    echo json_encode(['success' => false, 'error' => 'Non implémenté']);
}
