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
require_once __DIR__ . '/../lib/Mailer.php';

// Make sure .env credentials are available when running outside the FPM request
$env = loadEnv(__DIR__ . '/../.env');
foreach ($env as $key => $value) {
    if (!isset($_ENV[$key])) {
        $_ENV[$key] = $value;
    }
}

$pdo = getDatabase();
$limit = 50;

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM email_queue
        WHERE status IN ('pending', 'retrying')
          AND attempts < 5
        ORDER BY created_at ASC
        LIMIT ?
        FOR UPDATE SKIP LOCKED
    ");
    $stmt->execute([$limit]);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($emails as $email) {
        try {
            $sent = send_html_email(
                $email['to_email'],
                $email['subject'],
                $email['html_body'],
                $email['from_email'],
                $email['from_name'],
                $email['reply_to']
            );

            $status  = $sent ? 'sent' : 'retrying';
            $sentAt  = $sent ? date('Y-m-d H:i:s') : null;
            $error   = null;

            $upd = $pdo->prepare("
                UPDATE email_queue
                SET status      = ?,
                    attempts    = attempts + 1,
                    sent_at     = ?,
                    error_log   = ?
                WHERE id = ?
            ");
            $upd->execute([$status, $sentAt, $error, $email['id']]);

            error_log(sprintf(
                "[EMAIL-WORKER] id=%d to=%s status=%s attempts=%d",
                $email['id'],
                $email['to_email'],
                $status,
                $email['attempts'] + 1
            ));
        } catch (Throwable $e) {
            $newStatus = $email['attempts'] >= 4 ? 'failed' : 'retrying';

            $upd = $pdo->prepare("
                UPDATE email_queue
                SET status    = ?,
                    attempts  = attempts + 1,
                    error_log = ?
                WHERE id = ?
            ");
            $upd->execute([
                $newStatus,
                substr($e->getMessage(), 0, 500),
                $email['id']
            ]);

            error_log(sprintf(
                "[EMAIL-WORKER] id=%d to=%s status=%s attempts=%d error=%s",
                $email['id'],
                $email['to_email'],
                $newStatus,
                $email['attempts'] + 1,
                $e->getMessage()
            ));
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[EMAIL-WORKER] Batch failed: ' . $e->getMessage());
    exit(1);
}
