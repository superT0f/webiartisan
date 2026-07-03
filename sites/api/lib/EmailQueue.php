<?php
/**
 * Email queue processing shared between the CLI worker and the HTTP trigger.
 */

require_once __DIR__ . '/Mailer.php';

/**
 * Process pending/retrying emails from the queue.
 *
 * @param PDO $pdo    Database connection.
 * @param int $limit  Maximum number of emails to process in one run.
 * @return array      Stats: processed, sent, failed, retrying.
 * @throws Throwable  When the batch transaction fails.
 */
function processEmailQueue(PDO $pdo, int $limit = 50): array
{
    $stats = [
        'processed' => 0,
        'sent'      => 0,
        'failed'    => 0,
        'retrying'  => 0,
    ];

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

                $upd = $pdo->prepare("
                    UPDATE email_queue
                    SET status      = ?,
                        attempts    = attempts + 1,
                        sent_at     = ?,
                        error_log   = NULL
                    WHERE id = ?
                ");
                $upd->execute([$status, $sentAt, $email['id']]);

                $stats['processed']++;
                if ($status === 'sent') {
                    $stats['sent']++;
                } else {
                    $stats['retrying']++;
                }

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

                $stats['processed']++;
                if ($newStatus === 'failed') {
                    $stats['failed']++;
                } else {
                    $stats['retrying']++;
                }

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
        throw $e;
    }

    return $stats;
}
