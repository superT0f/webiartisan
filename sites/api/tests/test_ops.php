<?php
/**
 * WebiArtisan — Tests endpoints ops (/ops/*) + maintenance publique.
 * Run: make test-php FILE=test_ops.php
 */

require_once __DIR__ . '/../config/database.php';

$apiBase = rtrim(getenv('API_BASE_URL') ?: 'http://nginx/api', '/');
$pdo = getDatabase();
$token = 'ops-local-test-token-0123456789abcdef';

function check(string $name, bool $cond, string $detail = ''): void {
    echo ($cond ? 'OK' : 'FAIL') . ": $name" . ($detail ? " — $detail" : '') . "\n";
    if (!$cond) exit(1);
}

function api(string $method, string $path, ?array $body = null, ?string $token = null, bool $raw = false): array {
    $ch = curl_init(rtrim($GLOBALS['apiBase'], '/') . $path);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $code, 'json' => $raw ? $res : json_decode($res, true)];
}

// 1. Sans token → 401
$r = api('GET', '/ops/health');
check('ops sans token → 401', $r['status'] === 401, (string)$r['status']);

// 2. Mauvais token → 401
$r = api('GET', '/ops/health', null, 'mauvais-token');
check('ops mauvais token → 401', $r['status'] === 401);

// 3. Health : migrations + maintenance présents
$r = api('GET', '/ops/health', null, $token);
check('health 200', $r['status'] === 200, json_encode($r['json']));
check('health migrations 046+047', isset($r['json']['data']['migrations']['046_inventory'], $r['json']['data']['migrations']['047_settings']), json_encode($r['json']['data']['migrations'] ?? []));
check('health maintenance off par défaut', ($r['json']['data']['maintenance']['enabled'] ?? null) === false);

// 4. Maintenance : set puis get (ops + public)
$r = api('POST', '/ops/maintenance', ['enabled' => true, 'message' => 'Test maintenance'], $token);
check('maintenance set 200', $r['status'] === 200 && ($r['json']['data']['enabled'] ?? null) === true, json_encode($r['json']));
$r = api('GET', '/maintenance');
check('maintenance publique 200', $r['status'] === 200 && ($r['json']['data']['enabled'] ?? null) === true && $r['json']['data']['message'] === 'Test maintenance', json_encode($r['json']));
$r = api('POST', '/ops/maintenance', ['enabled' => false], $token);
check('maintenance off', $r['status'] === 200 && ($r['json']['data']['enabled'] ?? null) === false);

// 5. Logs : le set ci-dessus a dû écrire une ligne [OPS]
$r = api('GET', '/ops/logs?lines=50', null, $token);
check('logs 200', $r['status'] === 200, json_encode(array_slice($r['json']['data']['lines'] ?? [], -1)));
$foundOps = false;
foreach ($r['json']['data']['lines'] ?? [] as $l) { if (str_contains($l, '[OPS]')) $foundOps = true; }
check('logs contiennent [OPS]', $foundOps);

// 6. db/tables : contient local_users
$r = api('GET', '/ops/db/tables', null, $token);
$names = array_column($r['json']['data'] ?? [], 'table_name');
check('db/tables 200 + local_users', $r['status'] === 200 && in_array('local_users', $names, true), implode(',', array_slice($names, 0, 5)));

// 7. Export : table invalide refusée
$r = api('GET', '/ops/db/export?table=users', null, $token);
check('export table non local_ → 400', $r['status'] === 400);

// 8. Export CSV anonymisé : pas d'email en clair
$pdo->exec("DELETE FROM local_users WHERE email LIKE 'ops-export-%'");
api('POST', '/users/register', ['email' => 'ops-export-' . time() . '@example.com', 'password' => 'Password123!']);
$r = api('GET', '/ops/db/export?table=local_users', null, $token, true);
check('export local_users CSV 200', $r['status'] === 200 && str_contains($r['json'], 'email'), substr((string)$r['json'], 0, 80));
check('export anonymisé (pas d email en clair)', !str_contains($r['json'], 'ops-export-'));

echo "OK: tous les tests ops passent\n";
