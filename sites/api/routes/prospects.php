<?php
/**
 * WebIArtisan API — Route : Prospects B2B
 *
 * GET /prospects?city=livry&zone=&type=&search=   — liste publique
 * GET /prospects/:id                              — fiche publique
 */

switch ($method) {
    case 'GET':
        if ($action === '' || $action === 'list') {
            prospects_list($pdo);
        } elseif (is_numeric($action) && !$param) {
            prospect_get($pdo, (int)$action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function prospects_list(PDO $pdo): void
{
    $citySlug = $_GET['city'] ?? '';
    $zone     = $_GET['zone'] ?? '';
    $type     = $_GET['type'] ?? '';
    $search   = trim($_GET['search'] ?? '');

    $sql = "
        SELECT p.*, c.slug AS city_slug, c.name AS city_name
        FROM local_prospects p
        JOIN local_cities c ON c.id = p.city_id
        WHERE p.is_active = 1
    ";
    $params = [];

    if ($citySlug) {
        $sql .= " AND c.slug = ?";
        $params[] = $citySlug;
    }
    if ($zone) {
        $sql .= " AND p.zone = ?";
        $params[] = $zone;
    }
    if ($type) {
        $sql .= " AND p.type = ?";
        $params[] = $type;
    }
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.type LIKE ? OR p.zone LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY p.name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $items,
        'total'   => count($items),
    ]);
}

function prospect_get(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("
        SELECT p.*, c.slug AS city_slug, c.name AS city_name
        FROM local_prospects p
        JOIN local_cities c ON c.id = p.city_id
        WHERE p.id = ? AND p.is_active = 1
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Prospect non trouvé']);
        return;
    }

    echo json_encode(['success' => true, 'data' => $item]);
}
