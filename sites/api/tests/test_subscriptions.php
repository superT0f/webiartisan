<?php
/**
 * WebiArtisan — Smoke tests for subscription endpoints and plan helper.
 *
 * Run inside the PHP container (Docker stack up):
 *   docker compose exec -T php php /var/www/api/tests/test_subscriptions.php
 *
 * Run locally against a custom API base URL:
 *   API_BASE_URL=http://localhost:8081/api DB_HOST=127.0.0.1 DB_PORT=3307 php sites/api/tests/test_subscriptions.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Games.php';

$apiBase = rtrim(getenv('API_BASE_URL') ?: 'http://nginx/api', '/');
$statusUrl = "$apiBase/subscription/status";

$pdo = getDatabase();

// ------------------------------------------------------------------
// 1. Status endpoint requires auth
// ------------------------------------------------------------------
echo "Test /subscription/status without auth...\n";
$ch = curl_init($statusUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 401) {
    echo "FAIL: expected 401, got $code\n";
    if ($res) echo "Response: $res\n";
    exit(1);
}
echo "OK: /subscription/status requires auth (401)\n";

// ------------------------------------------------------------------
// 2. artisanIsPremium() helper
// ------------------------------------------------------------------
echo "Test artisanIsPremium() helper...\n";

$email = 'premium-test-' . time() . '@example.com';
$phone = '0100000000';

// Defensive cleanup in case a previous run left the test account behind.
$pdo->prepare("DELETE FROM local_artisans WHERE email = ?")
    ->execute([$email]);

$stmt = $pdo->prepare("
    INSERT INTO local_artisans
        (company_name, city_id, category_id, email, phone, status, plan)
    VALUES (?, ?, ?, ?, ?, 'active', 'free')
");
$stmt->execute(['Premium Test', 1, 1, $email, $phone]);
$artisanId = (int)$pdo->lastInsertId();

if ($artisanId === 0) {
    echo "FAIL: could not create test artisan\n";
    exit(1);
}

if (artisanIsPremium($pdo, $artisanId)) {
    echo "FAIL: artisan should be free\n";
    cleanup($pdo, $artisanId, $email);
    exit(1);
}

$pdo->prepare("UPDATE local_artisans SET plan = 'premium' WHERE id = ?")
    ->execute([$artisanId]);

if (!artisanIsPremium($pdo, $artisanId)) {
    echo "FAIL: artisan should be premium\n";
    cleanup($pdo, $artisanId, $email);
    exit(1);
}
echo "OK: artisanIsPremium() correctly distinguishes free and premium\n";

// ------------------------------------------------------------------
// 3. Authenticated status endpoint returns current plan
// ------------------------------------------------------------------
echo "Test /subscription/status with auth...\n";

$token = bin2hex(random_bytes(32));
$tokenHash = password_hash($token, PASSWORD_DEFAULT);
$tokenExp = date('Y-m-d H:i:s', strtotime('+1 hour'));
$pdo->prepare("
    UPDATE local_artisans
    SET plan = 'premium',
        subscription_status = 'active',
        auth_token_hash = ?,
        auth_token_exp = ?,
        auth_token = NULL
    WHERE id = ?
")->execute([$tokenHash, $tokenExp, $artisanId]);

$ch = curl_init($statusUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Artisan-Token: $token"]);
$res = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) {
    echo "FAIL: expected 200 for authenticated status, got $code\n";
    if ($res) echo "Response: $res\n";
    cleanup($pdo, $artisanId, $email);
    exit(1);
}

$json = json_decode($res, true);
if (empty($json['success']) || ($json['data']['plan'] ?? '') !== 'premium') {
    echo "FAIL: authenticated status did not return premium plan\n";
    if ($res) echo "Response: $res\n";
    cleanup($pdo, $artisanId, $email);
    exit(1);
}
echo "OK: /subscription/status returns premium plan for authenticated artisan\n";

// ------------------------------------------------------------------
// Cleanup
// ------------------------------------------------------------------
cleanup($pdo, $artisanId, $email);
echo "OK: cleanup\n";
echo "\nAll subscription smoke tests passed.\n";

function cleanup(PDO $pdo, int $artisanId, string $email): void
{
    $pdo->prepare("DELETE FROM local_artisans WHERE id = ?")
        ->execute([$artisanId]);
    $pdo->prepare("DELETE FROM local_artisans WHERE email = ?")
        ->execute([$email]);
}
