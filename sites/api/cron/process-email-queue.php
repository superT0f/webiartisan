<?php
/**
 * Email queue worker — meant to be run from cron, not from the web.
 * Processes pending/retrying emails up to 5 attempts.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/EmailQueue.php';

// Make sure .env credentials are available when running outside the FPM request
$env = loadEnv(__DIR__ . '/../.env');
foreach ($env as $key => $value) {
    if (!isset($_ENV[$key])) {
        $_ENV[$key] = $value;
    }
}

$pdo = getDatabase();
$limit = 50;

try {
    $stats = processEmailQueue($pdo, $limit);
    error_log('[EMAIL-WORKER] Done stats=' . json_encode($stats));
} catch (Throwable $e) {
    error_log('[EMAIL-WORKER] Fatal: ' . $e->getMessage());
    exit(1);
}
