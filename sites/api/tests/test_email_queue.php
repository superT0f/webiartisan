<?php
/**
 * Smoke test for the async email queue.
 * Run inside the PHP container:
 *   docker compose exec -T php php /var/www/api/tests/test_email_queue.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Mailer.php';

$testEmail = 'test-queue-' . time() . '@example.com';
$pdo = getDatabase();

// Cleanup any stale test rows
$pdo->prepare("DELETE FROM email_queue WHERE to_email = ?")
    ->execute([$testEmail]);

$queued = queueEmail(
    $testEmail,
    'Test subject',
    '<p>Test body</p>',
    null,
    'WebIArtisan Test',
    'reply@example.com',
    ['type' => 'test']
);

if (!$queued) {
    echo "FAIL: queueEmail() returned false\n";
    exit(1);
}

$row = $pdo->prepare("SELECT * FROM email_queue WHERE to_email = ? ORDER BY id DESC LIMIT 1");
$row->execute([$testEmail]);
$inserted = $row->fetch(PDO::FETCH_ASSOC);

if (!$inserted || $inserted['status'] !== 'pending') {
    echo "FAIL: email was not inserted with status=pending\n";
    exit(1);
}

echo "OK: queued id={$inserted['id']}\n";

$workerOutput = shell_exec('php /var/www/api/cron/process-email-queue.php 2>&1');
echo "Worker output: " . trim($workerOutput) . "\n";

$row2 = $pdo->prepare("SELECT status, attempts FROM email_queue WHERE id = ?");
$row2->execute([$inserted['id']]);
$processed = $row2->fetch(PDO::FETCH_ASSOC);

if (!$processed || $processed['status'] === 'pending') {
    echo "FAIL: email is still pending after worker run\n";
    exit(1);
}

if ((int)$processed['attempts'] !== 1) {
    echo "FAIL: attempts={$processed['attempts']}, expected 1\n";
    exit(1);
}

echo "OK: worker processed email to status={$processed['status']} attempts={$processed['attempts']}\n";

// Cleanup
$pdo->prepare("DELETE FROM email_queue WHERE id = ?")
    ->execute([$inserted['id']]);

echo "OK: cleanup done\n";
