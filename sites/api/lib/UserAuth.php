<?php
/**
 * UserAuth — Authentification consommateur par session token.
 *
 * Multi-sessions : chaque login crée une ligne dans local_user_sessions,
 * donc un login sur un appareil n'invalide plus les sessions des autres.
 * Les colonnes legacy local_users.session_token/session_exp continuent
 * d'être écrites (dernière session gagne) mais ne sont plus lues ici.
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
        SELECT s.user_id AS id, u.email
        FROM local_user_sessions s
        JOIN local_users u ON u.id = s.user_id
        WHERE s.token_hash = ? AND s.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("[USER-AUTH] invalid or expired session, ip={$ip}");
        if (function_exists('app_log')) {
            app_log('info', '[USER-AUTH] invalid or expired session', [
                'ip' => $ip,
                'path' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
        }
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session invalide']);
        exit;
    }

    $pdo->prepare("UPDATE local_user_sessions SET last_used_at = NOW() WHERE token_hash = ?")
        ->execute([$tokenHash]);

    return $user;
}

/**
 * Retourne l'id du user authentifié par le Bearer courant, ou null.
 * Pour les endpoints publiques qui enrichissent la réponse quand le user est connecté.
 */
function user_optional_auth(PDO $pdo): ?int
{
    $token = user_get_session_token();
    if (!$token) return null;

    $stmt = $pdo->prepare("
        SELECT user_id FROM local_user_sessions
        WHERE token_hash = ? AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([hash('sha256', $token)]);
    $userId = $stmt->fetchColumn();
    return $userId === false ? null : (int)$userId;
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

    // GC : purge des sessions expirées du compte
    $deleted = $pdo->prepare("DELETE FROM local_user_sessions WHERE user_id = ? AND expires_at <= NOW()");
    $deleted->execute([$userId]);
    if ($deleted->rowCount() > 0 && function_exists('app_log')) {
        app_log('info', '[USER-AUTH] expired sessions purged', ['user_id' => $userId, 'count' => $deleted->rowCount()]);
    }

    $deviceLabel = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 100) ?: null;
    $stmt = $pdo->prepare("
        INSERT INTO local_user_sessions (user_id, token_hash, device_label, expires_at)
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))
    ");
    $stmt->execute([$userId, $tokenHash, $deviceLabel, (int)$expiryDays]);

    // Compat legacy : dernière session gagne (plus utilisée pour l'auth)
    $pdo->prepare("
        UPDATE local_users
        SET session_token = ?, session_exp = DATE_ADD(NOW(), INTERVAL ? DAY)
        WHERE id = ?
    ")->execute([$tokenHash, (int)$expiryDays, $userId]);

    return $token;
}

function user_logout(PDO $pdo, int $userId): void
{
    $token = user_get_session_token();
    if ($token) {
        $pdo->prepare("DELETE FROM local_user_sessions WHERE user_id = ? AND token_hash = ?")
            ->execute([$userId, hash('sha256', $token)]);
    }
    $pdo->prepare("
        UPDATE local_users
        SET session_token = NULL, session_exp = NULL
        WHERE id = ?
    ")->execute([$userId]);
}
