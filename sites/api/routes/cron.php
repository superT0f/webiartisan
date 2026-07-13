<?php
/**
 * WebIArtisan API — Route : Cron triggers
 *
 * GET /cron/process-email-queue?token=... — trigger email queue worker
 */

require_once __DIR__ . '/../lib/EmailQueue.php';

$cronAction = $action ?: 'process-email-queue';

if ($cronAction !== 'process-email-queue') {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
    exit;
}

$secret   = $_ENV['CRON_SECRET'] ?? '';
$provided = $_GET['token'] ?? '';

if (!$secret || !hash_equals($secret, $provided)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$limit = min((int)($_GET['limit'] ?? 50), 100);

try {
    $stats = processEmailQueue($pdo, $limit);
    echo json_encode([
        'success' => true,
        'stats'   => $stats,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[CRON-TRIGGER] ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => 'Worker failed',
    ]);
}
