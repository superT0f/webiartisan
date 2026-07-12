<?php
/**
 * WebIArtisan API — Artisan authentication helpers
 */

function artisan_get_token(): ?string
{
    $token = $_SERVER['HTTP_X_ARTISAN_TOKEN'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $token = str_replace('Bearer ', '', $token);
    return $token ?: null;
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

    $stmt = $pdo->prepare("
        SELECT id, city_id, company_name, email, is_admin, plan, subscription_status, subscription_period_end, stripe_customer_id
        FROM local_artisans
        WHERE auth_token = ? AND auth_token_exp > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$artisan) {
        $tokenFingerprint = substr(hash('sha256', $token), 0, 16);
        error_log("[ARTISAN-AUTH] invalid or expired session, ip={$ip}, token_fp={$tokenFingerprint}");
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session invalide']);
        exit;
    }

    return $artisan;
}
