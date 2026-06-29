<?php
/**
 * WebIArtisan API — Route : Actions gamifiées
 *
 * POST /actions
 */

require_once __DIR__ . '/../lib/UserAuth.php';
require_once __DIR__ . '/../lib/Gamification.php';

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$user = user_require_auth($pdo);
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$actionKey = $body['action'] ?? '';
$resourceKey = $body['resource_key'] ?? null;
$metadata = $body['metadata'] ?? null;

if (!$actionKey || !isset(XP_ACTIONS[$actionKey])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Action inconnue']);
    exit;
}

$result = gamificationRecordAction($pdo, (int)$user['id'], $actionKey, $resourceKey, $metadata);

if ($result === null) {
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => ['xp_gained' => 0, 'cooldown' => true]]);
    exit;
}

echo json_encode(['success' => true, 'data' => $result]);
