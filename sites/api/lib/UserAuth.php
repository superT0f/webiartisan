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

function user_generate_session_token(): string
{
    return bin2hex(random_bytes(32));
}

function user_create_session(PDO $pdo, int $userId, bool $rememberMe): string
{
    $token = user_generate_session_token();
    $expiryDays = $rememberMe ? 365 : 30;
    $stmt = $pdo->prepare("
        UPDATE local_users
        SET session_token = ?, session_exp = DATE_ADD(NOW(), INTERVAL ? DAY)
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$token, $expiryDays, $userId]);
    return $token;
}

function user_logout(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare("
        UPDATE local_users
        SET session_token = NULL, session_exp = NULL
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
}
