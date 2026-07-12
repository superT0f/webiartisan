<?php
/**
 * UserAuth — Authentification consommateur par session token
 */

function user_get_session_token(): ?string
{
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = preg_replace('/^Bearer\s+/i', '', $token);
    return $token ?: null;
}

function user_require_auth(PDO $pdo): array
{
    $token = user_get_session_token();
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!$token) {
        error_log("[USER-AUTH] missing bearer token, ip={$ip}");
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentification requise']);
        exit;
    }

    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare("
        SELECT id, email
        FROM local_users
        WHERE session_token = ? AND session_exp > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("[USER-AUTH] invalid or expired session, ip={$ip}");
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
    $tokenHash = hash('sha256', $token);
    $expiryDays = $rememberMe ? 365 : 30;
    $stmt = $pdo->prepare("
        UPDATE local_users
        SET session_token = ?, session_exp = DATE_ADD(NOW(), INTERVAL ? DAY)
        WHERE id = ?
    ");
    $stmt->execute([$tokenHash, (int)$expiryDays, $userId]);
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('User not found');
    }
    return $token;
}

function user_logout(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare("
        UPDATE local_users
        SET session_token = NULL, session_exp = NULL
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
}
