<?php
/**
 * WebIArtisan API — Route : E2E debug/cleanup endpoints
 *
 * DELETE /e2e/cleanup/:id       — delete a test account
 * GET    /e2e/magic-link/:email — generate a magic login code for a test email
 *
 * Both endpoints are gated by E2E_ALLOWED and an X-E2E-Token header.
 * The magic-link code is stored in local_users.magic_token (the existing
 * consumer magic-link column) because the brief assumed a local_magic_codes
 * table that does not exist in the current schema.
 */

require_once __DIR__ . '/../lib/AppLogger.php';

$e2eAllowed = ($_ENV['E2E_ALLOWED'] ?? 'false') === 'true';
$e2eToken = $_ENV['E2E_API_TOKEN'] ?? '';

function requireE2EAuth(): void
{
    global $e2eAllowed, $e2eToken;

    if (!$e2eAllowed) {
        http_response_code(403);
        echo json_encode(['error' => 'E2E not allowed']);
        exit;
    }

    $header = $_SERVER['HTTP_X_E2E_TOKEN'] ?? '';
    if (!$e2eToken || $header !== $e2eToken) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid E2E token']);
        exit;
    }
}

function isTestEmail(string $email): bool
{
    return str_starts_with($email, 'e2e-') && str_ends_with($email, '@prigent.tech');
}

switch ($method) {
    case 'DELETE':
        if ($action === 'cleanup' && $param !== null && is_numeric($param)) {
            requireE2EAuth();
            $id = (int) $param;

            try {
                $pdo->prepare('DELETE la FROM local_artisans la INNER JOIN local_users lu ON la.user_id = lu.id WHERE la.user_id = ? AND lu.email LIKE "e2e-%@prigent.tech"')
                    ->execute([$id]);
                $pdo->prepare('DELETE FROM local_users WHERE id = ? AND email LIKE "e2e-%@prigent.tech"')
                    ->execute([$id]);

                app_log('info', '[E2E-CLEANUP] test account cleaned', ['id' => $id]);
                echo json_encode(['ok' => true]);
            } catch (Throwable $e) {
                app_log('error', '[E2E-CLEANUP] database error', ['id' => $id, 'error' => $e->getMessage()]);
                http_response_code(500);
                echo json_encode(['error' => 'Server error']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        if ($action === 'prepare-artisan' && $param !== null && is_numeric($param)) {
            requireE2EAuth();
            $id = (int) $param;
            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            try {
                $stmt = $pdo->prepare('SELECT email FROM local_artisans WHERE id = ?');
                $stmt->execute([$id]);
                $email = $stmt->fetchColumn();

                if (!$email || !isTestEmail($email)) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Test artisan not found']);
                    exit;
                }

                $plan = ($body['plan'] ?? '') === 'premium' ? 'premium' : 'free';
                $lat = is_numeric($body['lat'] ?? null) ? (float)$body['lat'] : null;
                $lng = is_numeric($body['lng'] ?? null) ? (float)$body['lng'] : null;

                $pdo->prepare("UPDATE local_artisans SET status = 'active', email_verified = 1, plan = ?, latitude = COALESCE(?, latitude), longitude = COALESCE(?, longitude) WHERE id = ?")
                    ->execute([$plan, $lat, $lng, $id]);

                app_log('info', '[E2E-PREPARE-ARTISAN] test artisan prepared', ['id' => $id, 'plan' => $plan]);
                echo json_encode(['ok' => true]);
            } catch (Throwable $e) {
                app_log('error', '[E2E-PREPARE-ARTISAN] database error', ['id' => $id, 'error' => $e->getMessage()]);
                http_response_code(500);
                echo json_encode(['error' => 'Server error']);
            }
        } elseif ($action === 'activate-artisan' && $param !== null && is_numeric($param)) {
            requireE2EAuth();
            $id = (int) $param;

            try {
                $stmt = $pdo->prepare('SELECT email FROM local_artisans WHERE id = ?');
                $stmt->execute([$id]);
                $email = $stmt->fetchColumn();

                if (!$email || !isTestEmail($email)) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Test artisan not found']);
                    exit;
                }

                $pdo->prepare("UPDATE local_artisans SET status = 'active', email_verified = 1 WHERE id = ?")
                    ->execute([$id]);

                app_log('info', '[E2E-ACTIVATE-ARTISAN] test artisan activated', ['id' => $id]);
                echo json_encode(['ok' => true]);
            } catch (Throwable $e) {
                app_log('error', '[E2E-ACTIVATE-ARTISAN] database error', ['id' => $id, 'error' => $e->getMessage()]);
                http_response_code(500);
                echo json_encode(['error' => 'Server error']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint inconnu']);
        }
        break;

    case 'GET':
        if ($action === 'magic-link' && $param !== null) {
            requireE2EAuth();
            $email = urldecode($param);

            if (!isTestEmail($email)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid test email']);
                exit;
            }

            $code = bin2hex(random_bytes(16));
            $codeHash = hash('sha256', $code);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            try {
                $stmt = $pdo->prepare('SELECT id FROM local_users WHERE email = ?');
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Test user not found']);
                    exit;
                }

                $userId = (int) $user['id'];

                $pdo->prepare('
                    UPDATE local_users
                    SET magic_token = ?, magic_token_exp = ?
                    WHERE id = ?
                ')->execute([$codeHash, $expiresAt, $userId]);

                app_log('info', '[E2E-MAGIC-LINK] code generated', ['email' => $email, 'user_id' => $userId]);
                echo json_encode([
                    'code' => $code,
                    'url'  => "https://artisans-livry.prigent.tech/login?token={$code}",
                ]);
            } catch (Throwable $e) {
                app_log('error', '[E2E-MAGIC-LINK] database error', ['email' => $email, 'error' => $e->getMessage()]);
                http_response_code(500);
                echo json_encode(['error' => 'Server error']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Méthode non autorisée']);
}
