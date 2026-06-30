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

if ($metadata !== null) {
    if (!is_array($metadata)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Métadonnées invalides']);
        exit;
    }
    try {
        $metadataJson = json_encode($metadata, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Métadonnées invalides']);
        exit;
    }
    if (strlen($metadataJson) > 4096) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Métadonnées trop volumineuses']);
        exit;
    }
}

if (!$actionKey || !isset(XP_ACTIONS[$actionKey])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Action inconnue']);
    exit;
}

if (!empty(XP_ACTIONS[$actionKey]['internal'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Action réservée']);
    exit;
}

$result = gamificationRecordAction($pdo, (int)$user['id'], $actionKey, $resourceKey, $metadata);

if ($result === null) {
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => ['xp_gained' => 0, 'cooldown' => true]]);
    exit;
}

echo json_encode(['success' => true, 'data' => $result]);
