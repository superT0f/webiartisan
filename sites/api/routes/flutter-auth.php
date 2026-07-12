<?php
/**
 * WebIArtisan API — Routes d'authentification pour l'application Flutter
 *
 * Ces endpoints sont destinés au conteneur natif Flutter :
 *   POST /auth/login        — email + mot de passe
 *   POST /auth/send-code    — demande d'un code à 6 chiffres par email
 *   POST /auth/verify-code  — validation du code → session
 *   GET  /auth/villes       — villes actives avec URL complète
 *
 * Ils réutilisent la table local_users et les sessions existantes.
 */

require_once __DIR__ . '/../lib/Mailer.php';
require_once __DIR__ . '/../lib/UserAuth.php';
require_once __DIR__ . '/../lib/AppLogger.php';

const FLUTTER_AUTH_TIMING_TARGET_MS = 400;

function pad_flutter_auth_response(float $startTime): void
{
    $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
    if ($elapsedMs < FLUTTER_AUTH_TIMING_TARGET_MS) {
        usleep((FLUTTER_AUTH_TIMING_TARGET_MS - $elapsedMs) * 1000);
    }
}

function format_flutter_user_response(array $user): array
{
    return [
        'id'           => (int) $user['id'],
        'email'        => $user['email'],
        'display_name' => $user['display_name'] ?? null,
    ];
}

function flutter_ensure_user(PDO $pdo, string $email): int
{
    $stmt = $pdo->prepare("INSERT INTO local_users (email) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $stmt->execute([$email]);
    return (int) $pdo->lastInsertId();
}

function flutter_generate_code(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function flutter_store_code(PDO $pdo, int $userId, string $code): void
{
    $pdo->prepare("DELETE FROM local_user_email_codes WHERE user_id = ?")->execute([$userId]);
    $stmt = $pdo->prepare("INSERT INTO local_user_email_codes (user_id, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $stmt->execute([$userId, $code]);
}

function flutter_validate_code(PDO $pdo, int $userId, string $code): bool
{
    $stmt = $pdo->prepare("SELECT id FROM local_user_email_codes WHERE user_id = ? AND code = ? AND expires_at > NOW() AND used_at IS NULL LIMIT 1");
    $stmt->execute([$userId, $code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    $pdo->prepare("UPDATE local_user_email_codes SET used_at = NOW() WHERE id = ?")->execute([$row['id']]);
    return true;
}

function flutter_send_code_email(string $email, string $code): bool
{
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $subject = 'Votre code de connexion WebIArtisan';
    $html = <<<HTML
<!DOCTYPE html>
<html><body style="font-family: -apple-system, sans-serif; max-width: 480px; margin: 0 auto; padding: 20px;">
  <h2 style="color: #1a1a2e;">Bonjour,</h2>
  <p>Voici votre code de connexion pour l'application WebIArtisan :</p>
  <div style="text-align: center; margin: 24px 0; padding: 20px; background: #f3f4f6; border-radius: 12px;">
    <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #1a1a2e;">{$safeCode}</span>
  </div>
  <p style="color: #888; font-size: 13px;">Ce code est valable 10 minutes. Si vous ne l'avez pas demandé, ignorez cet email.</p>
</body></html>
HTML;

    $config = getAppConfig();
    $fromEmail = $config['mail_from'] ?? 'noreply@webiartisan.prigent.tech';

    return queueEmail(
        $email,
        $subject,
        $html,
        $fromEmail,
        'WebIArtisan',
        null,
        ['type' => 'flutter_auth_code', 'email' => $email]
    );
}

function flutter_login(PDO $pdo, array $body): void
{
    $email    = strtolower(trim($body['email'] ?? ''));
    $password = $body['password'] ?? '';
    $rememberMe = !empty($body['rememberMe']);

    app_log('info', '[FLUTTER-AUTH] login start', ['email' => $email, 'rememberMe' => $rememberMe ? '1' : '0']);

    $startTime = microtime(true);

    if (!$email || !$password) {
        pad_flutter_auth_response($startTime);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email et mot de passe requis']);
        return;
    }

    $dummyHash = '$2y$10$nJE.S3ari5fK7bx/5wTzLuAqtQF2nVkanks.m5AdkvLK3s9ity/i6';
    $authenticatedUser = null;
    $authSource = null;
    $artisanData = null;

    // 1. Essayer un compte visiteur classique
    try {
        $stmt = $pdo->prepare("SELECT id, email, password_hash, display_name FROM local_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'] ?? $dummyHash)) {
            $authenticatedUser = $user;
            $authSource = 'user';
        }
    } catch (Throwable $e) {
        app_log('error', '[FLUTTER-AUTH] login user db error', ['email' => $email, 'error' => $e->getMessage()]);
        pad_flutter_auth_response($startTime);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    // 2. Sinon, essayer un compte artisan actif et lier/recréer un compte visiteur
    if (!$authenticatedUser) {
        try {
            $stmt = $pdo->prepare("
                SELECT a.id, a.email, a.password_hash, a.company_name, a.status,
                       a.email_verified, a.is_admin, c.slug AS city_slug
                FROM local_artisans a
                JOIN local_cities c ON c.id = a.city_id
                WHERE a.email = ?
            ");
            $stmt->execute([$email]);
            $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($artisan
                && !empty($artisan['password_hash'])
                && password_verify($password, $artisan['password_hash'])
                && $artisan['status'] === 'active'
            ) {
                // Générer un token artisan longue durée pour le bridge vers l'espace web
                $artisanToken = bin2hex(random_bytes(32));
                $artisanTokenHash = password_hash($artisanToken, PASSWORD_DEFAULT);
                $artisanTokenExp = date('Y-m-d H:i:s', $rememberMe ? strtotime('+365 days') : strtotime('+30 days'));
                $pdo->prepare("UPDATE local_artisans SET auth_token_hash = ?, auth_token_exp = ?, auth_token = NULL WHERE id = ?")
                    ->execute([$artisanTokenHash, $artisanTokenExp, $artisan['id']]);

                $userStmt = $pdo->prepare("SELECT id, email, display_name FROM local_users WHERE email = ?");
                $userStmt->execute([$email]);
                $existingUser = $userStmt->fetch(PDO::FETCH_ASSOC);

                if ($existingUser) {
                    if (empty($existingUser['display_name']) && !empty($artisan['company_name'])) {
                        $pdo->prepare("UPDATE local_users SET display_name = ? WHERE id = ?")
                            ->execute([$artisan['company_name'], $existingUser['id']]);
                        $existingUser['display_name'] = $artisan['company_name'];
                    }
                    $authenticatedUser = $existingUser;
                } else {
                    $insert = $pdo->prepare("INSERT INTO local_users (email, display_name, email_verified) VALUES (?, ?, ?)");
                    $insert->execute([
                        $email,
                        $artisan['company_name'],
                        (int)(bool)$artisan['email_verified'],
                    ]);
                    $newUserId = (int)$pdo->lastInsertId();
                    $authenticatedUser = [
                        'id'           => $newUserId,
                        'email'        => $email,
                        'display_name' => $artisan['company_name'],
                    ];
                }
                $authSource = 'artisan';
                $artisanData = [
                    'id'           => (int)$artisan['id'],
                    'company_name' => $artisan['company_name'],
                    'city_slug'    => $artisan['city_slug'],
                    'is_admin'     => (bool)$artisan['is_admin'],
                    'token'        => $artisanToken,
                ];
            }
        } catch (Throwable $e) {
            app_log('error', '[FLUTTER-AUTH] login artisan fallback db error', ['email' => $email, 'error' => $e->getMessage()]);
            pad_flutter_auth_response($startTime);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
            return;
        }
    }

    if (!$authenticatedUser) {
        app_log('info', '[FLUTTER-AUTH] login invalid credentials', ['email' => $email]);
        pad_flutter_auth_response($startTime);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Email ou mot de passe incorrect']);
        return;
    }

    try {
        $sessionToken = user_create_session($pdo, (int)$authenticatedUser['id'], $rememberMe);
    } catch (Throwable $e) {
        app_log('error', '[FLUTTER-AUTH] login session error', ['email' => $email, 'error' => $e->getMessage()]);
        pad_flutter_auth_response($startTime);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    app_log('info', '[FLUTTER-AUTH] login success', [
        'email'     => $email,
        'user_id'   => (int)$authenticatedUser['id'],
        'source'    => $authSource,
        'artisan_id'=> $artisanData['id'] ?? null,
    ]);
    pad_flutter_auth_response($startTime);
    echo json_encode([
        'success' => true,
        'token'   => $sessionToken,
        'data'    => format_flutter_user_response($authenticatedUser),
        'artisan' => $artisanData,
    ]);
}

function flutter_send_code(PDO $pdo, array $body): void
{
    $email = strtolower(trim($body['email'] ?? ''));

    app_log('info', '[FLUTTER-AUTH] send-code start', ['email' => $email]);

    $startTime = microtime(true);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        pad_flutter_auth_response($startTime);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email invalide']);
        return;
    }

    try {
        $pdo->beginTransaction();
        $userId = flutter_ensure_user($pdo, $email);
        $code = flutter_generate_code();
        flutter_store_code($pdo, $userId, $code);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        app_log('error', '[FLUTTER-AUTH] send-code db error', ['email' => $email, 'error' => $e->getMessage()]);
        pad_flutter_auth_response($startTime);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    $queued = flutter_send_code_email($email, $code);
    if (!$queued) {
        app_log('error', '[FLUTTER-AUTH] send-code email queue failed', ['email' => $email, 'user_id' => $userId]);
    }

    $redacted = preg_replace('/^(.).+(@.+)$/', '$1***$2', $email);
    app_log('info', '[FLUTTER-AUTH] send-code success', ['email' => $email, 'user_id' => $userId, 'queued' => $queued ? '1' : '0']);

    pad_flutter_auth_response($startTime);
    echo json_encode([
        'success' => true,
        'message' => 'Si votre email est valide, vous recevrez un code de connexion.',
        'email_hint' => $redacted,
    ]);
}

function flutter_verify_code(PDO $pdo, array $body): void
{
    $email      = strtolower(trim($body['email'] ?? ''));
    $code       = trim($body['code'] ?? '');
    $rememberMe = !empty($body['rememberMe']);

    app_log('info', '[FLUTTER-AUTH] verify-code start', ['email' => $email]);

    $startTime = microtime(true);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$code) {
        pad_flutter_auth_response($startTime);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email et code requis']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, email, password_hash, display_name FROM local_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            pad_flutter_auth_response($startTime);
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Code invalide ou expiré']);
            return;
        }

        if (!flutter_validate_code($pdo, (int)$user['id'], $code)) {
            pad_flutter_auth_response($startTime);
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Code invalide ou expiré']);
            return;
        }

        $sessionToken = user_create_session($pdo, (int)$user['id'], $rememberMe);

        // Nettoyer les anciens codes utilisés pour cet utilisateur de temps en temps
        $pdo->prepare("DELETE FROM local_user_email_codes WHERE user_id = ? AND used_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->execute([(int)$user['id']]);
    } catch (Throwable $e) {
        app_log('error', '[FLUTTER-AUTH] verify-code error', ['email' => $email, 'error' => $e->getMessage()]);
        pad_flutter_auth_response($startTime);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    app_log('info', '[FLUTTER-AUTH] verify-code success', ['email' => $email, 'user_id' => (int)$user['id']]);
    pad_flutter_auth_response($startTime);
    echo json_encode([
        'success' => true,
        'token'   => $sessionToken,
        'data'    => format_flutter_user_response($user),
    ]);
}

function flutter_villes(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.slug,
                c.name,
                c.postal_code AS cp,
                c.subdomain,
                COUNT(DISTINCT a.id) AS artisan_count
            FROM local_cities c
            LEFT JOIN local_artisans a ON a.city_id = c.id AND a.status = 'active'
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY c.name ASC
        ");
        $stmt->execute();
        $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        app_log('error', '[FLUTTER-AUTH] villes error', ['error' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    $data = array_map(function (array $city) {
        $subdomain = $city['subdomain'] ?? '';
        return [
            'id'            => (int) $city['id'],
            'slug'          => $city['slug'],
            'name'          => $city['name'],
            'cp'            => $city['cp'] ?? '',
            'subdomain'     => $subdomain,
            'url'           => $subdomain ? 'https://' . rtrim($subdomain, '/') . '/' : '',
            'artisan_count' => (int) $city['artisan_count'],
        ];
    }, $cities);

    echo json_encode([
        'success' => true,
        'data'    => $data,
        'total'   => count($data),
    ]);
}

// Dispatcher
$body = json_decode(file_get_contents('php://input'), true) ?? [];

switch ("$method:$action") {
    case 'POST:login':
        flutter_login($pdo, $body);
        break;

    case 'POST:send-code':
        flutter_send_code($pdo, $body);
        break;

    case 'POST:verify-code':
        flutter_verify_code($pdo, $body);
        break;

    case 'GET:villes':
        flutter_villes($pdo);
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
}
