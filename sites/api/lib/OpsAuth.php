<?php
/**
 * WebiArtisan — Auth des endpoints ops (/ops/*).
 *
 * Token dédié (OPS_TOKEN dans .env), DISTINCT du JWT utilisateur/admin :
 * une session compromise ne doit pas ouvrir les outils d'exploitation.
 * Endpoint désactivé tant que OPS_TOKEN n'est pas configuré (503).
 */

require_once __DIR__ . '/AppLogger.php';

function ops_require_auth(): void
{
    $expected = $_ENV['OPS_TOKEN'] ?? '';
    if (strlen($expected) < 32) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Ops non configuré (OPS_TOKEN manquant)']);
        exit;
    }

    $provided = '';
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        $provided = trim($m[1]);
    }

    if ($provided === '' || !hash_equals($expected, $provided)) {
        app_log('warning', '[OPS] auth refusée', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token ops invalide']);
        exit;
    }

    app_log('info', '[OPS] accès', ['uri' => $_SERVER['REQUEST_URI'] ?? '', 'method' => $_SERVER['REQUEST_METHOD'] ?? '']);
}
