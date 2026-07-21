<?php
/**
 * WebIArtisan API — Artisan authentication helpers
 *
 * Sessions multiples (migration 042) : une ligne par appareil/session dans
 * local_artisan_sessions. Le slot legacy auth_token_* de local_artisans reste
 * en lecture (transition) et en écriture de compat (dernière session gagne),
 * comme pour local_users en 040.
 */

function artisan_get_token(): ?string
{
    $token = $_SERVER['HTTP_X_ARTISAN_TOKEN'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $token = str_replace('Bearer ', '', $token);
    return $token ?: null;
}

/**
 * Crée une session artisan (multi-appareils) et retourne le token brut.
 * $ttlSeconds : durée de vie explicite (ex. 3600 pour un lien magique) ;
 * sinon 365 jours si $rememberMe, 30 jours sinon.
 */
function artisan_create_session(PDO $pdo, int $artisanId, bool $rememberMe, ?int $ttlSeconds = null): string
{
    $token = bin2hex(random_bytes(32));
    $lookup = hash('sha256', $token);
    $hash = password_hash($token, PASSWORD_BCRYPT);
    $ttl = $ttlSeconds ?? (($rememberMe ? 365 : 30) * 86400);

    // GC : purge des sessions expirées du compte
    $pdo->prepare("DELETE FROM local_artisan_sessions WHERE artisan_id = ? AND expires_at <= NOW()")
        ->execute([$artisanId]);

    $deviceLabel = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 100) ?: null;
    $exp = date('Y-m-d H:i:s', time() + $ttl);
    $pdo->prepare("
        INSERT INTO local_artisan_sessions (artisan_id, token_lookup, token_hash, device_label, expires_at)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$artisanId, $lookup, $hash, $deviceLabel, $exp]);

    // Compat legacy : dernière session gagne (plus utilisée pour l'auth)
    $pdo->prepare("UPDATE local_artisans SET auth_token_hash = ?, auth_token_lookup = ?, auth_token_exp = ?, auth_token = NULL WHERE id = ?")
        ->execute([$hash, $lookup, $exp, $artisanId]);

    return $token;
}

/** Détruit UNE session (celle du token courant), pas les autres appareils. */
function artisan_destroy_session(PDO $pdo, string $token): void
{
    $lookup = hash('sha256', $token);
    $pdo->prepare("DELETE FROM local_artisan_sessions WHERE token_lookup = ?")->execute([$lookup]);
    // Slot legacy : ne le purger que s'il correspond à ce token
    $pdo->prepare("UPDATE local_artisans SET auth_token_hash = NULL, auth_token_lookup = NULL, auth_token = NULL, auth_token_exp = NULL WHERE auth_token_lookup = ?")
        ->execute([$lookup]);
}

/**
 * Valide un token brut : sessions multiples d'abord, slot legacy ensuite.
 * Retourne ['artisan_id' => int, 'status' => string] ou null.
 */
function artisan_resolve_token(PDO $pdo, string $token): ?array
{
    $lookup = hash('sha256', $token);

    $stmt = $pdo->prepare("
        SELECT s.id AS session_id, s.artisan_id, s.token_hash, a.status
        FROM local_artisan_sessions s
        JOIN local_artisans a ON a.id = s.artisan_id
        WHERE s.token_lookup = ? AND s.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$lookup]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && password_verify($token, $row['token_hash'])) {
        $pdo->prepare("UPDATE local_artisan_sessions SET last_used_at = NOW() WHERE id = ?")
            ->execute([(int)$row['session_id']]);
        return ['artisan_id' => (int)$row['artisan_id'], 'status' => $row['status']];
    }

    // Slot legacy (transition d'avant la 042)
    $stmt = $pdo->prepare("
        SELECT id AS artisan_id, auth_token_hash AS token_hash, status
        FROM local_artisans
        WHERE auth_token_lookup = ?
          AND auth_token_hash IS NOT NULL
          AND auth_token_exp > NOW()
        LIMIT 1
    ");
    $stmt->execute([$lookup]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && password_verify($token, $row['token_hash'])) {
        return ['artisan_id' => (int)$row['artisan_id'], 'status' => $row['status']];
    }

    return null;
}

function artisan_require_auth(PDO $pdo): array
{
    $token = artisan_get_token();
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!$token) {
        error_log("[ARTISAN-AUTH] missing token, ip={$ip}");
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentification requise']);
        exit;
    }

    // Master admin token (emergency / support access)
    $masterToken = envOrDefault('ADMIN_MASTER_TOKEN', '');
    if ($masterToken !== '' && (strlen($masterToken) !== 300 || !ctype_xdigit($masterToken))) {
        error_log('[ARTISAN-AUTH] ADMIN_MASTER_TOKEN set but invalid (must be 300 hex chars) — ignoring');
        $masterToken = '';
    }
    if ($masterToken !== '' && hash_equals($masterToken, $token)) {
        $stmt = $pdo->prepare("
            SELECT id, city_id, company_name, email, is_admin, plan, subscription_status, subscription_period_end, stripe_customer_id
            FROM local_artisans
            WHERE is_admin = 1
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute();
        $artisan = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($artisan) {
            return $artisan;
        }
    }

    $resolved = artisan_resolve_token($pdo, $token);

    if ($resolved && $resolved['status'] !== 'active') {
        error_log("[ARTISAN-AUTH] suspended account access attempt, id={$resolved['artisan_id']}, ip={$ip}");
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Compte suspendu']);
        exit;
    }

    $artisan = null;
    if ($resolved) {
        $stmt = $pdo->prepare("
            SELECT id, city_id, company_name, email, is_admin, plan, subscription_status, subscription_period_end, stripe_customer_id
            FROM local_artisans
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$resolved['artisan_id']]);
        $artisan = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$artisan) {
        $tokenFingerprint = substr(hash('sha256', $token), 0, 16);
        error_log("[ARTISAN-AUTH] invalid or expired session, ip={$ip}, token_fp={$tokenFingerprint}");
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session invalide']);
        exit;
    }

    return $artisan;
}
