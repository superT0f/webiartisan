<?php
/**
 * Smoke test for consumer password auth.
 * Run inside the PHP container:
 *   docker compose exec -T php php /var/www/api/tests/test_password_auth.php
 */
require_once __DIR__ . '/../config/database.php';

$testEmail = 'auth-test-' . time() . '@example.com';
$password  = 'SecurePass123!';
$pdo = getDatabase();

// Cleanup
$pdo->prepare("DELETE FROM local_users WHERE email = ?")->execute([$testEmail]);

function httpPost($url, $payload, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Content-Type: application/json'], $headers));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $res;
}

// Register
$res = httpPost('http://nginx/api/users/register', [
    'email'       => $testEmail,
    'password'    => $password,
    'display_name'=> 'Test User',
]);

if (!$res || !$res['success']) {
    echo "FAIL: register\n";
    print_r($res);
    exit(1);
}
echo "OK: registered\n";

// Login
$res = httpPost('http://nginx/api/users/login', [
    'email'    => $testEmail,
    'password' => $password,
    'rememberMe' => true,
]);

if (!$res || !$res['success'] || empty($res['token'])) {
    echo "FAIL: login\n";
    print_r($res);
    exit(1);
}
$token = $res['token'];
echo "OK: login, token received\n";

// Logout
$res = httpPost('http://nginx/api/users/logout', [], ["Authorization: Bearer {$token}"]);

if (!$res || !$res['success']) {
    echo "FAIL: logout\n";
    print_r($res);
    exit(1);
}
echo "OK: logout\n";

// Cleanup
$pdo->prepare("DELETE FROM local_users WHERE email = ?")->execute([$testEmail]);
echo "OK: cleanup\n";
