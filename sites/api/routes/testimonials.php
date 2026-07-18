<?php
/**
 * WebIArtisan API — Route : Témoignages / recommandations
 *
 * GET  /testimonials                — liste publique
 * GET  /testimonials/templates      — modèles par type de service
 * GET  /testimonials/:id            — détail
 * POST /testimonials                — créer (authentifié)
 * PATCH /testimonials/:id           — modifier (auteur/admin)
 * DELETE /testimonials/:id          — supprimer (auteur/admin)
 * POST /testimonials/:id/report     — signaler (authentifié)
 * POST /testimonials/:id/helpful    — marquer utile (authentifié)
 */

require_once __DIR__ . '/../lib/Testimonials.php';
require_once __DIR__ . '/../lib/Gamification.php';

function is_valid_id(mixed $value): bool
{
    return filter_var($value, FILTER_VALIDATE_INT) !== false && (int)$value > 0;
}

function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function is_valid_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?: '');
    return in_array($scheme, ['http', 'https'], true);
}

switch ($method) {
    case 'GET':
        if ($action === '' || $action === 'list') {
            testimonials_list($pdo);
        } elseif ($action === 'templates') {
            testimonials_templates($pdo);
        } elseif (is_valid_id($action) && !$param) {
            testimonials_get($pdo, (int)$action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (is_valid_id($action) && $param === 'report') {
            testimonials_report($pdo, (int)$action, $body);
        } elseif (is_valid_id($action) && $param === 'helpful') {
            testimonials_helpful($pdo, (int)$action);
        } elseif ($action === '' || $action === 'list') {
            testimonials_create($pdo, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'PATCH':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (is_valid_id($action) && !$param) {
            testimonials_update($pdo, (int)$action, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'DELETE':
        if (is_valid_id($action) && !$param) {
            testimonials_delete($pdo, (int)$action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function testimonials_list(PDO $pdo): void
{
    $artisanId = isset($_GET['artisan_id']) ? (int)$_GET['artisan_id'] : null;
    $citySlug = $_GET['city'] ?? null;
    $serviceType = $_GET['service_type'] ?? null;
    $rating = isset($_GET['rating']) ? (int)$_GET['rating'] : null;
    $sort = in_array($_GET['sort'] ?? '', ['newest', 'oldest', 'helpful', 'rating'], true)
        ? $_GET['sort']
        : 'newest';
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $offset = max((int)($_GET['offset'] ?? 0), 0);

    $sql = "
        SELECT t.*, u.display_name, u.avatar_url
        FROM local_testimonials t
        JOIN local_users u ON u.id = t.user_id
        JOIN local_artisans a ON a.id = t.artisan_id
        JOIN local_cities c ON c.id = a.city_id
        WHERE t.status = 'approved'
    ";
    $params = [];

    if ($artisanId) {
        $sql .= " AND t.artisan_id = ?";
        $params[] = $artisanId;
    }
    if ($citySlug) {
        $sql .= " AND c.slug = ?";
        $params[] = $citySlug;
    }
    if ($serviceType) {
        $sql .= " AND t.service_type = ?";
        $params[] = $serviceType;
    }
    if ($rating !== null && $rating >= 1 && $rating <= 5) {
        $sql .= " AND t.rating = ?";
        $params[] = $rating;
    }

    $orderBy = match ($sort) {
        'oldest' => 't.created_at ASC',
        'helpful' => 't.helpful_count DESC, t.created_at DESC',
        'rating' => 't.rating DESC, t.created_at DESC',
        default => 't.created_at DESC',
    };
    $sql .= " ORDER BY $orderBy LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['artisan_id'] = (int)$item['artisan_id'];
        $item['user_id'] = (int)$item['user_id'];
        $item['artisan_service_id'] = $item['artisan_service_id'] !== null ? (int)$item['artisan_service_id'] : null;
        $item['rating'] = $item['rating'] !== null ? (int)$item['rating'] : null;
        $item['helpful_count'] = (int)$item['helpful_count'];
        $item['display_name'] = e((string)($item['display_name'] ?? ''));
        $item['title'] = $item['title'] !== null ? e((string)$item['title']) : null;
        $item['content'] = e((string)($item['content'] ?? ''));
        if (!empty($item['avatar_url']) && !is_valid_url($item['avatar_url'])) {
            $item['avatar_url'] = null;
        }
        $item['media'] = testimonials_get_media($pdo, (int)$item['id']);
    }

    echo json_encode([
        'success' => true,
        'data' => $items,
        'total' => count($items),
        'limit' => $limit,
        'offset' => $offset,
    ]);
}

function testimonials_get(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("
        SELECT t.*, u.display_name, u.avatar_url
        FROM local_testimonials t
        JOIN local_users u ON u.id = t.user_id
        WHERE t.id = ? AND t.status = 'approved'
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Témoignage non trouvé']);
        return;
    }

    $item['id'] = (int)$item['id'];
    $item['artisan_id'] = (int)$item['artisan_id'];
    $item['user_id'] = (int)$item['user_id'];
    $item['artisan_service_id'] = $item['artisan_service_id'] !== null ? (int)$item['artisan_service_id'] : null;
    $item['rating'] = $item['rating'] !== null ? (int)$item['rating'] : null;
    $item['helpful_count'] = (int)$item['helpful_count'];
    $item['display_name'] = e((string)($item['display_name'] ?? ''));
    $item['title'] = $item['title'] !== null ? e((string)$item['title']) : null;
    $item['content'] = e((string)($item['content'] ?? ''));
    if (!empty($item['avatar_url']) && !is_valid_url($item['avatar_url'])) {
        $item['avatar_url'] = null;
    }
    $item['media'] = testimonials_get_media($pdo, $id);

    $uid = user_optional_auth($pdo);
    if ($uid) {
        gamificationRecordAction($pdo, (int)$uid, 'testimonial_view', "testimonial:$id", ['testimonial_id' => $id, 'artisan_id' => $item['artisan_id']]);
    }

    echo json_encode(['success' => true, 'data' => $item]);
}

function testimonials_create(PDO $pdo, array $body): void
{
    $user = user_require_auth($pdo);

    $artisanId = (int)($body['artisan_id'] ?? 0);
    $artisanServiceId = !empty($body['artisan_service_id']) ? (int)$body['artisan_service_id'] : null;
    $serviceType = !empty($body['service_type']) ? trim($body['service_type']) : null;
    $rating = isset($body['rating']) ? (int)$body['rating'] : null;
    $title = trim($body['title'] ?? '');
    $content = trim($body['content'] ?? '');

    if (!$artisanId || !$content) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Artisan et contenu requis']);
        return;
    }

    if (!testimonials_can_user_testify($pdo, (int)$user['id'], $artisanId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Impossible de témoigner pour cet artisan']);
        return;
    }

    if ($rating !== null && ($rating < 1 || $rating > 5)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Note invalide']);
        return;
    }

    // Verify artisan_service_id ownership
    if ($artisanServiceId) {
        $svcOwner = $pdo->prepare("SELECT id FROM local_services WHERE id = ? AND artisan_id = ?");
        $svcOwner->execute([$artisanServiceId, $artisanId]);
        if (!$svcOwner->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Le service sélectionné n\'appartient pas à cet artisan']);
            return;
        }
    }

    // Resolve service_type from artisan_service_id if not provided
    if (!$serviceType && $artisanServiceId) {
        $stmt = $pdo->prepare("
            SELECT sc.`key` FROM local_services s
            LEFT JOIN local_service_catalog sc ON sc.id = s.service_catalog_id
            WHERE s.id = ?
        ");
        $stmt->execute([$artisanServiceId]);
        $serviceType = $stmt->fetchColumn() ?: null;
    }

    $pdo->prepare("
        INSERT INTO local_testimonials
            (artisan_id, user_id, artisan_service_id, service_type, rating, title, content, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ")->execute([$artisanId, $user['id'], $artisanServiceId, $serviceType, $rating, $title, $content]);

    $id = (int)$pdo->lastInsertId();

    // Insert media URLs if provided
    $media = $body['media'] ?? [];
    if (is_array($media)) {
        $order = 0;
        foreach (array_slice($media, 0, 5) as $m) {
            $url = is_string($m) ? $m : ($m['url'] ?? null);
            if (!is_string($url) || !$url || !is_valid_url($url)) {
                continue;
            }
            $type = is_string($m) ? 'image' : ($m['type'] ?? 'image');
            if (!in_array($type, ['image', 'video'], true)) {
                $type = 'image';
            }
            $pdo->prepare("
                INSERT INTO local_testimonial_media (testimonial_id, media_url, media_type, display_order)
                VALUES (?, ?, ?, ?)
            ")->execute([$id, $url, $type, $order++]);
        }
    }

    echo json_encode(['success' => true, 'data' => ['id' => $id, 'message' => 'Témoignage envoyé, il sera visible après validation.']]);
}

function testimonials_update(PDO $pdo, int $id, array $body): void
{
    // TODO in plan: implement author/admin update with 24h window
    http_response_code(501);
    echo json_encode(['success' => false, 'error' => 'Non implémenté']);
}

function testimonials_delete(PDO $pdo, int $id): void
{
    // TODO in plan: implement author/admin soft delete
    http_response_code(501);
    echo json_encode(['success' => false, 'error' => 'Non implémenté']);
}

function testimonials_report(PDO $pdo, int $id, array $body): void
{
    $user = user_require_auth($pdo);

    $check = $pdo->prepare("SELECT id FROM local_testimonials WHERE id = ? AND status = 'approved'");
    $check->execute([$id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Témoignage non trouvé']);
        return;
    }

    $dup = $pdo->prepare("
        SELECT id FROM local_testimonial_reports
        WHERE testimonial_id = ? AND reporter_user_id = ?
        LIMIT 1
    ");
    $dup->execute([$id, $user['id']]);
    if ($dup->fetch()) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Vous avez déjà signalé ce témoignage']);
        return;
    }

    $reason = trim($body['reason'] ?? '');
    if (!$reason) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Motif requis']);
        return;
    }
    $pdo->prepare("
        INSERT INTO local_testimonial_reports (testimonial_id, reporter_user_id, reason, details)
        VALUES (?, ?, ?, ?)
    ")->execute([$id, $user['id'], $reason, trim($body['details'] ?? '')]);
    $pdo->prepare("UPDATE local_testimonials SET status = 'flagged' WHERE id = ? AND status = 'approved'")
        ->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Signalement enregistré']);
}

function testimonials_helpful(PDO $pdo, int $id): void
{
    user_require_auth($pdo);

    $check = $pdo->prepare("SELECT id FROM local_testimonials WHERE id = ? AND status = 'approved'");
    $check->execute([$id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Témoignage non trouvé']);
        return;
    }

    // Simple increment; duplicate clicks accepted but could be rate-limited later
    $pdo->prepare("UPDATE local_testimonials SET helpful_count = helpful_count + 1 WHERE id = ? AND status = 'approved'")
        ->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Merci pour votre retour']);
}

function testimonials_templates(PDO $pdo): void
{
    $serviceKey = $_GET['service'] ?? null;
    echo json_encode(['success' => true, 'data' => testimonials_get_templates($pdo, $serviceKey)]);
}

function testimonials_get_media(PDO $pdo, int $testimonialId): array
{
    $stmt = $pdo->prepare("
        SELECT id, media_url, media_type, display_order
        FROM local_testimonial_media
        WHERE testimonial_id = ?
        ORDER BY display_order ASC, id ASC
    ");
    $stmt->execute([$testimonialId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['display_order'] = (int)$item['display_order'];
        if (!empty($item['media_url']) && !is_valid_url($item['media_url'])) {
            $item['media_url'] = null;
        }
    }
    return $items;
}
