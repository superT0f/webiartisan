<?php
/**
 * WebIArtisan API — Route : Catalogue de services
 *
 * GET /service-catalog — liste publique
 */

switch ($method) {
    case 'GET':
        if ($action === '' || $action === 'list') {
            service_catalog_list($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function service_catalog_list(PDO $pdo): void
{
    $category = $_GET['category'] ?? null;
    $sql = "SELECT id, `key`, label_fr, icon, category, testimonial_templates FROM local_service_catalog WHERE is_active = 1";
    $params = [];
    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    $sql .= " ORDER BY category ASC, label_fr ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['label_fr'] = htmlspecialchars((string)($item['label_fr'] ?? ''), ENT_QUOTES, 'UTF-8');
        $templates = json_decode($item['testimonial_templates'] ?? '[]', true);
        if (is_array($templates)) {
            $item['testimonial_templates'] = array_map(
                static fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'),
                $templates
            );
        } else {
            $item['testimonial_templates'] = [];
        }
    }
    echo json_encode(['success' => true, 'data' => $items]);
}
