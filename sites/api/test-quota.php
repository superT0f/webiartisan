<?php
/**
 * Endpoint temporaire pour tester le quota
 */
header('Content-Type: application/json');

require_once __DIR__ . '/lib/WebsiteBackend.php';
require_once __DIR__ . '/middleware/PlanQuota.php';
require_once __DIR__ . '/config/database.php';

// Simuler tenant 3
$tenantId = 3;

try {
    $pdo = getDatabase();
    $quota = getWebsiteQuotaSummary($pdo, $tenantId);
    
    echo json_encode([
        'success' => true,
        'tenant_id' => $tenantId,
        'quota' => $quota,
        'calculated_at' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
