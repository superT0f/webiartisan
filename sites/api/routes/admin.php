<?php
/**
 * WebIArtisan API — Route : Admin
 * Endpoints réservés aux administrateurs pour gérer les artisans.
 *
 * GET  /admin/artisans              — Liste des artisans
 * POST /admin/artisans/{id}/activate — Activer un artisan
 * POST /admin/artisans/{id}/suspend  — Suspendre un artisan
 * POST /admin/artisans/{id}/set-plan — Définir le plan free/premium
 */

require_once __DIR__ . '/../lib/ArtisanAuth.php';

$artisan = artisan_require_auth($pdo);

if (empty($artisan['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès réservé']);
    exit;
}

$subAction = $segments[3] ?? '';

if ($method === 'GET' && $action === 'artisans' && $param === null) {
    admin_list_artisans($pdo);
} elseif ($method === 'POST' && $action === 'artisans' && is_numeric($param) && $subAction === 'activate') {
    admin_activate_artisan($pdo, (int)$param);
} elseif ($method === 'POST' && $action === 'artisans' && is_numeric($param) && $subAction === 'suspend') {
    admin_suspend_artisan($pdo, (int)$param);
} elseif ($method === 'POST' && $action === 'artisans' && is_numeric($param) && $subAction === 'set-plan') {
    admin_set_artisan_plan($pdo, (int)$param);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
}

function admin_list_artisans(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT
            a.id,
            a.company_name,
            a.email,
            a.phone,
            a.status,
            a.plan,
            a.subscription_status,
            a.created_at,
            a.updated_at,
            c.name AS city_name,
            c.slug AS city_slug
        FROM local_artisans a
        LEFT JOIN local_cities c ON a.city_id = c.id
        ORDER BY a.id DESC
    ");

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function admin_activate_artisan(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("UPDATE local_artisans SET status = 'active' WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Artisan activé',
        'affected' => $stmt->rowCount(),
    ]);
}

function admin_suspend_artisan(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("UPDATE local_artisans SET status = 'suspended' WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Artisan suspendu',
        'affected' => $stmt->rowCount(),
    ]);
}

function admin_set_artisan_plan(PDO $pdo, int $id): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $plan = in_array($body['plan'] ?? '', ['free', 'premium'], true) ? $body['plan'] : null;

    if (!$plan) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Plan invalide']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE local_artisans SET plan = ? WHERE id = ?");
    $stmt->execute([$plan, $id]);

    echo json_encode([
        'success' => true,
        'message' => "Plan mis à jour : $plan",
        'affected' => $stmt->rowCount(),
    ]);
}
