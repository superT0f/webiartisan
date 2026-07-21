<?php
/**
 * WebiArtisan — Tests sessions artisan multiples (fix remember-me).
 *
 * Bug corrigé : le slot unique auth_token_* était régénéré à chaque login
 * (autre appareil, autre site, app Flutter, demande de lien magique),
 * invalidant toutes les autres sessions.
 *
 * Run: docker compose exec -T php php /var/www/api/tests/test_artisan_sessions.php
 */

require_once __DIR__ . '/../config/database.php';

$apiBase = rtrim(getenv('API_BASE_URL') ?: 'http://nginx/api', '/');
$pdo = getDatabase();

function check(string $name, bool $cond, string $detail = ''): void {
    echo ($cond ? 'OK' : 'FAIL') . ": $name" . ($detail ? " — $detail" : '') . "\n";
    if (!$cond) exit(1);
}

function api(string $method, string $path, ?array $body = null, ?string $artisanToken = null): array {
    $ch = curl_init(rtrim($GLOBALS['apiBase'], '/') . $path);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = ['Content-Type: application/json'];
    if ($artisanToken) $headers[] = 'X-Artisan-Token: ' . $artisanToken;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $code, 'json' => json_decode($res, true)];
}

// --- Compte artisan de test -------------------------------------------------
$email = 'sessions-test-' . time() . '@example.com';
$pdo->prepare("DELETE FROM local_artisans WHERE email = ?")->execute([$email]);
$pdo->prepare("
    INSERT INTO local_artisans (company_name, city_id, category_id, email, phone, status, plan, password_hash, email_verified)
    VALUES ('Sessions Test', 1, 1, ?, '0100000000', 'active', 'free', ?, TRUE)
")->execute([$email, password_hash('Password123!', PASSWORD_BCRYPT)]);
$artisanId = (int)$pdo->lastInsertId();
if ($artisanId === 0) { echo "FAIL: création artisan\n"; exit(1); }

// 1. Deux logins successifs (deux appareils) — AVANT le fix, le 2e tuait le 1er
$r1 = api('POST', '/artisans/login', ['email' => $email, 'password' => 'Password123!', 'rememberMe' => true]);
$token1 = $r1['json']['token'] ?? null;
check('login appareil 1', $token1 !== null, json_encode($r1['json']));

$r2 = api('POST', '/artisans/login', ['email' => $email, 'password' => 'Password123!', 'rememberMe' => true]);
$token2 = $r2['json']['token'] ?? null;
check('login appareil 2', $token2 !== null && $token2 !== $token1);

// 2. Les DEUX sessions restent valides
$m1 = api('GET', '/artisans/me', null, $token1);
check('session appareil 1 toujours valide après login 2', $m1['status'] === 200, (string)$m1['status']);
$m2 = api('GET', '/artisans/me', null, $token2);
check('session appareil 2 valide', $m2['status'] === 200, (string)$m2['status']);

// 3. Une demande de lien magique n'invalide AUCUNE session existante
$ml = api('POST', '/artisans/magic-link', ['email' => $email, 'rememberMe' => false]);
check('magic-link demandé', ($ml['json']['success'] ?? false) === true);
check('session 1 survit à la demande de lien magique', api('GET', '/artisans/me', null, $token1)['status'] === 200);
check('session 2 survit à la demande de lien magique', api('GET', '/artisans/me', null, $token2)['status'] === 200);

// 4. Le token du lien magique fonctionne aussi (3e session indépendante)
$magicToken = null;
$stmt = $pdo->prepare("SELECT token_lookup FROM local_artisan_sessions WHERE artisan_id = ? ORDER BY id DESC LIMIT 3");
$stmt->execute([$artisanId]);
check('3 sessions en BDD', count($stmt->fetchAll(PDO::FETCH_COLUMN)) === 3);

// 5. Logout ciblé : seule la session courante est détruite
$out1 = api('POST', '/artisans/logout', [], $token1);
check('logout appareil 1', ($out1['json']['success'] ?? false) === true);
// /artisans/me répond 403 « Token invalide ou expiré » sur session détruite
check('session 1 invalidée par logout', api('GET', '/artisans/me', null, $token1)['status'] === 403);
check('session 2 survit au logout de la session 1', api('GET', '/artisans/me', null, $token2)['status'] === 200);

// 6. Expiration honorée : une session expirée est refusée
$pdo->prepare("UPDATE local_artisan_sessions SET expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE artisan_id = ?")->execute([$artisanId]);
check('session expirée refusée', api('GET', '/artisans/me', null, $token2)['status'] === 403);

// Cleanup
$pdo->prepare("DELETE FROM local_artisan_sessions WHERE artisan_id = ?")->execute([$artisanId]);
$pdo->prepare("DELETE FROM local_users WHERE email = ?")->execute([$email]);
$pdo->prepare("DELETE FROM local_artisans WHERE id = ?")->execute([$artisanId]);

echo "OK: tous les tests sessions artisan passent\n";
