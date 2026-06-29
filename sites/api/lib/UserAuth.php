<?php
/**
 * UserAuth — Authentification consommateur par session token
 */

function user_get_session_token(): ?string
{
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    return $token ?: null;
}

function user_require_auth(PDO $pdo): array
{
    $token = user_get_session_token();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentification requise']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, email
        FROM local_users
        WHERE session_token = ? AND session_exp > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session invalide']);
        exit;
    }

    return $user;
}
