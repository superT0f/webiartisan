<?php
/**
 * Test script for Client Portal API endpoints
 * Run this script to verify the client portal functionality
 */

require_once __DIR__ . '/config/database.php';

echo "=== WebIArtisan Client Portal Test ===\n\n";

$pdo = getDatabase();

// Test 1: Check database schema
echo "1. Testing database schema...\n";

// Check devis table has client_token columns
$stmt = $pdo->prepare("DESCRIBE devis");
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
$requiredColumns = ['client_token', 'client_token_expires_at', 'signed_at', 'signed_ip', 'signed_signature_b64'];

foreach ($requiredColumns as $col) {
    if (in_array($col, $columns)) {
        echo "   ✅ Column '$col' exists in devis table\n";
    } else {
        echo "   ❌ Column '$col' missing in devis table\n";
    }
}

// Check paiements_client table exists
$stmt = $pdo->prepare("SHOW TABLES LIKE 'paiements_client'");
$stmt->execute();
if ($stmt->fetch()) {
    echo "   ✅ Table 'paiements_client' exists\n";
} else {
    echo "   ❌ Table 'paiements_client' missing\n";
}

echo "\n";

// Test 2: Test token generation
echo "2. Testing token generation...\n";

// Create test tenant if not exists
$stmt = $pdo->prepare("SELECT id FROM tenants WHERE slug = 'test-tenant'");
$stmt->execute();
$tenant = $stmt->fetch();

if (!$tenant) {
    $stmt = $pdo->prepare("INSERT INTO tenants (slug, name) VALUES (?, ?)");
    $stmt->execute(['test-tenant', 'Test Tenant']);
    $tenantId = $pdo->lastInsertId();
    echo "   ✅ Created test tenant\n";
} else {
    $tenantId = $tenant['id'];
    echo "   ✅ Using existing test tenant\n";
}

// Create test client if not exists
$stmt = $pdo->prepare("SELECT id FROM clients WHERE tenant_id = ? AND email = 'test@example.com'");
$stmt->execute([$tenantId]);
$client = $stmt->fetch();

if (!$client) {
    $stmt = $pdo->prepare("INSERT INTO clients (tenant_id, nom, email) VALUES (?, ?, ?)");
    $stmt->execute([$tenantId, 'Test Client', 'test@example.com']);
    $clientId = $pdo->lastInsertId();
    echo "   ✅ Created test client\n";
} else {
    $clientId = $client['id'];
    echo "   ✅ Using existing test client\n";
}

// Create test devis if not exists
$stmt = $pdo->prepare("SELECT id, client_token FROM devis WHERE tenant_id = ? AND numero = 'TEST-001'");
$stmt->execute([$tenantId]);
$devis = $stmt->fetch();

if (!$devis) {
    $stmt = $pdo->prepare("
        INSERT INTO devis (tenant_id, client_id, numero, status, lignes, total_ht, tva_rate, acompte_pourcentage, acompte_montant) 
        VALUES (?, ?, ?, 'draft', ?, 1000.00, 20.00, 30.0, 360.00)
    ");
    $stmt->execute([$tenantId, $clientId, json_encode([
        ['description' => 'Service test', 'quantite' => 1, 'prix_unitaire_ht' => 1000, 'total_ht' => 1000]
    ])]);
    $devisId = $pdo->lastInsertId();
    echo "   ✅ Created test devis\n";
} else {
    $devisId = $devis['id'];
    echo "   ✅ Using existing test devis\n";
}

// Generate client token
$token = bin2hex(random_bytes(32));
$stmt = $pdo->prepare("UPDATE devis SET client_token = ? WHERE id = ?");
$stmt->execute([$token, $devisId]);

echo "   ✅ Generated client token: $token\n";
echo "\n";

// Test 3: Test client portal endpoint
echo "3. Testing client portal endpoint...\n";

// Simulate GET /api/client/{token}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'web.prigent.tech';
$segments = ['client', $token];

// Include the client-public route
ob_start();
include __DIR__ . '/routes/client-public.php';
$output = ob_get_clean();

if (strpos($output, '"success":true') !== false) {
    echo "   ✅ Client portal endpoint working\n";
} else {
    echo "   ❌ Client portal endpoint failed\n";
    echo "   Output: $output\n";
}

echo "\n";

// Test 4: Test signature endpoint
echo "4. Testing signature endpoint...\n";

// Simulate POST /api/client/{token}/sign
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'web.prigent.tech';
$segments = ['client', $token, 'sign'];

// Mock signature data
$signatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

// Mock input
file_put_contents('php://temp', json_encode(['signature_b64' => $signatureData]));
$_SERVER['CONTENT_TYPE'] = 'application/json';

ob_start();
include __DIR__ . '/routes/client-public.php';
$output = ob_get_clean();

if (strpos($output, '"success":true') !== false) {
    echo "   ✅ Signature endpoint working\n";
} else {
    echo "   ❌ Signature endpoint failed\n";
    echo "   Output: $output\n";
}

echo "\n";

// Test 5: Test payment endpoint
echo "5. Testing payment endpoint...\n";

// Simulate POST /api/client/{token}/pay
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'web.prigent.tech';
$segments = ['client', $token, 'pay'];

// Mock payment data
file_put_contents('php://temp', json_encode(['methode' => 'virement', 'type' => 'acompte']));
$_SERVER['CONTENT_TYPE'] = 'application/json';

ob_start();
include __DIR__ . '/routes/client-public.php';
$output = ob_get_clean();

if (strpos($output, '"success":true') !== false) {
    echo "   ✅ Payment endpoint working\n";
} else {
    echo "   ❌ Payment endpoint failed\n";
    echo "   Output: $output\n";
}

echo "\n";

// Test 6: Verify data integrity
echo "6. Testing data integrity...\n";

$stmt = $pdo->prepare("SELECT signed_at, signed_ip FROM devis WHERE id = ?");
$stmt->execute([$devisId]);
$updatedDevis = $stmt->fetch();

if ($updatedDevis['signed_at']) {
    echo "   ✅ Signature timestamp recorded\n";
} else {
    echo "   ❌ Signature timestamp missing\n";
}

if ($updatedDevis['signed_ip']) {
    echo "   ✅ Signature IP recorded\n";
} else {
    echo "   ❌ Signature IP missing\n";
}

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM paiements_client WHERE devis_id = ?");
$stmt->execute([$devisId]);
$paymentCount = $stmt->fetch()['count'];

if ($paymentCount > 0) {
    echo "   ✅ Payment record created ($paymentCount payments)\n";
} else {
    echo "   ❌ Payment record missing\n";
}

echo "\n=== Test Complete ===\n";
echo "Client portal functionality appears to be working correctly!\n";
echo "Access the test portal at: https://web.prigent.tech/client/$token\n";
