<?php
/**
 * Auth routes — Magic link authentication (style Supercell ID).
 * Flux : lookup → request-code → verify-code
 * Public routes (no JWT required except GET:me, POST:avatar, POST:logout).
 */

// Rate-limit sensitive auth endpoints
$sensitiveActions = ['lookup', 'request-code', 'verify-code', 'biometric-login', 'register'];
if (in_array($action, $sensitiveActions, true)) {
    applyRateLimit($pdo, 'login');
}

// Helper: format user response
function formatUserResponse(array $user, Auth $auth): array {
    return [
        'id'          => (int) $user['id'],
        'email'       => $user['email'],
        'login'       => $user['login'] ?? null,
        'name'        => $user['name'] ?? null,
        'fonction'    => $user['fonction'] ?? null,
        'phone'       => $user['phone'] ?? null,
        'role'        => $user['role'],
        'tenant_id'   => (int) $user['tenant_id'],
        'tenant_slug' => $user['tenant_slug'] ?? null,
        'tenant_name' => $user['tenant_name'] ?? null,
        'avatar_url'  => $auth->getAvatarUrl($user['avatar_path'] ?? null),
    ];
}

switch ("$method:$action") {

    // ============================================
    // STEP 1: LOOKUP — find user by email or login, return avatar + name
    // ============================================
    case 'POST:lookup':
        $input = json_decode(file_get_contents('php://input'), true);
        $identifier = trim($input['identifier'] ?? '');
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!$identifier) {
            $logger->warning('[AUTH-LOOKUP] missing identifier', ['ip' => $ip]);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Email ou identifiant requis']);
            break;
        }

        try {
            $pdo = getDatabase();
            $user = $auth->findUserByIdentifier($pdo, $identifier);

            if (!$user) {
                $logger->info('[AUTH-LOOKUP] account not found', ['identifier' => $identifier, 'ip' => $ip]);
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Aucun compte trouvé']);
                break;
            }

            $logger->info('[AUTH-LOOKUP] account found', ['user_id' => (int)$user['id'], 'identifier' => $identifier, 'ip' => $ip]);
            echo json_encode([
                'success'    => true,
                'user_id'    => (int) $user['id'],
                'name'       => $user['name'],
                'email'      => $user['email'],
                'avatar_url' => $auth->getAvatarUrl($user['avatar_path'] ?? null),
            ]);
        } catch (Exception $e) {
            $logger->error('[AUTH-LOOKUP] server error', ['identifier' => $identifier, 'ip' => $ip, 'error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        }
        break;

    // ============================================
    // STEP 2: REQUEST-CODE — generate and send magic code by email
    // ============================================
    case 'POST:request-code':
        $input = json_decode(file_get_contents('php://input'), true);
        $identifier = trim($input['identifier'] ?? '');
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!$identifier) {
            $logger->warning('[AUTH-REQUEST-CODE] missing identifier', ['ip' => $ip]);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Email ou identifiant requis']);
            break;
        }

        try {
            $pdo = getDatabase();
            $user = $auth->findUserByIdentifier($pdo, $identifier);

            if (!$user) {
                // Don't reveal if user exists or not
                $logger->info('[AUTH-REQUEST-CODE] no active user', ['identifier' => $identifier, 'ip' => $ip]);
                echo json_encode(['success' => true, 'message' => 'Si ce compte existe, un code a été envoyé']);
                break;
            }

            $code = $auth->generateCode();
            $auth->storeCode($pdo, (int)$user['id'], $code);
            $sent = $auth->sendCode($user['email'], $code, $user['name'] ?? '');

            $logger->info('[AUTH-REQUEST-CODE] code generated', [
                'user_id' => (int)$user['id'],
                'identifier' => $identifier,
                'ip' => $ip,
                'sent' => $sent ? 'yes' : 'no',
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Code envoyé par email',
                'email_hint' => preg_replace('/^(.).+(@.+)$/', '$1***$2', $user['email']),
            ]);
        } catch (Exception $e) {
            $logger->error('[AUTH-REQUEST-CODE] server error', ['identifier' => $identifier, 'ip' => $ip, 'error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        }
        break;

    // ============================================
    // STEP 3: VERIFY-CODE — verify code, return JWT + set remember cookie
    // ============================================
    case 'POST:verify-code':
        $input = json_decode(file_get_contents('php://input'), true);
        $identifier  = trim($input['identifier'] ?? '');
        $code        = trim($input['code'] ?? '');
        $rememberMe  = (bool)($input['remember_me'] ?? true);
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!$identifier) {
            $logger->warning('[AUTH-VERIFY-CODE] missing identifier', ['ip' => $ip]);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Identifiant requis']);
            break;
        }

        if (!$code) {
            $logger->warning('[AUTH-VERIFY-CODE] missing code', ['identifier' => $identifier, 'ip' => $ip]);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Code requis']);
            break;
        }

        try {
            $pdo = getDatabase();
            $user = $auth->findUserByIdentifier($pdo, $identifier);

            if (!$user) {
                $logger->warning('[AUTH-VERIFY-CODE] user not found', ['identifier' => $identifier, 'ip' => $ip]);
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Code invalide ou expiré']);
                break;
            }

            if (!$auth->verifyCode($pdo, (int)$user['id'], $code)) {
                $logger->warning('[AUTH-VERIFY-CODE] invalid or expired code', [
                    'user_id' => (int)$user['id'],
                    'identifier' => $identifier,
                    'ip' => $ip,
                ]);
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Code invalide ou expiré']);
                break;
            }

            // Generate JWT
            $token = $auth->generateToken($user);

            // Update last_login_at
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

            // Set remember me cookie if requested
            if ($rememberMe) {
                $auth->createRememberToken($pdo, $user);
            }

            $logger->info('[AUTH-VERIFY-CODE] login success', [
                'user_id' => (int)$user['id'],
                'identifier' => $identifier,
                'ip' => $ip,
                'remember_me' => $rememberMe ? 'yes' : 'no',
            ]);

            echo json_encode([
                'success' => true,
                'token'   => $token,
                'user'    => formatUserResponse($user, $auth),
            ]);
        } catch (Exception $e) {
            $logger->error('[AUTH-VERIFY-CODE] server error', [
                'identifier' => $identifier,
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        }
        break;

    // ============================================
    // BIOMETRIC-ENABLE — register a device for biometric login (authenticated)
    // ============================================
    case 'POST:biometric-enable':
        $authUser = $auth->requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $deviceId = trim($input['device_id'] ?? '');
        $secret   = trim($input['secret'] ?? ''); // Unique secret generated by the app
        $deviceName = isset($input['device_name']) && $input['device_name'] !== null ? trim($input['device_name']) : null;

        if (!$deviceId || !$secret) {
            $logger->warning('[AUTH-BIOMETRIC-ENABLE] missing fields', ['user_id' => (int)$authUser['sub'], 'ip' => $ip]);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'device_id et secret requis']);
            break;
        }

        try {
            $pdo = getDatabase();
            $auth->createBiometricKey($pdo, (int)$authUser['sub'], $deviceId, $secret, $deviceName);

            $logger->info('[AUTH-BIOMETRIC-ENABLE] biometric key created', [
                'user_id' => (int)$authUser['sub'],
                'device_id' => substr(hash('sha256', $deviceId), 0, 16),
                'ip' => $ip,
            ]);
            echo json_encode(['success' => true, 'message' => 'Biométrie activée pour cet appareil']);
        } catch (Exception $e) {
            $logger->error('[AUTH-BIOMETRIC-ENABLE] server error', [
                'user_id' => (int)$authUser['sub'],
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        }
        break;

    // ============================================
    // BIOMETRIC-LOGIN — login using biometric secret (public)
    // ============================================
    case 'POST:biometric-login':
        $input = json_decode(file_get_contents('php://input'), true);
        $deviceId = trim($input['device_id'] ?? '');
        $secret   = trim($input['secret'] ?? '');
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!$deviceId || !$secret) {
            $logger->warning('[AUTH-BIOMETRIC-LOGIN] missing fields', ['ip' => $ip]);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'device_id et secret requis']);
            break;
        }

        try {
            $pdo = getDatabase();
            $user = $auth->verifyBiometric($pdo, $deviceId, $secret);

            if (!$user) {
                $logger->warning('[AUTH-BIOMETRIC-LOGIN] invalid credentials', [
                    'device_id' => substr(hash('sha256', $deviceId), 0, 16),
                    'ip' => $ip,
                ]);
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentification biométrique invalide ou expirée']);
                break;
            }

            // Generate JWT
            // Map the flat user structure back to what generateToken expects
            $tokenUser = [
                'id'        => $user['id'],
                'tenant_id' => $user['tenant_id'],
                'email'     => $user['email'],
                'role'      => $user['role']
            ];
            $token = $auth->generateToken($tokenUser);

            // Update last_login_at
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

            $logger->info('[AUTH-BIOMETRIC-LOGIN] login success', [
                'user_id' => (int)$user['id'],
                'device_id' => substr(hash('sha256', $deviceId), 0, 16),
                'ip' => $ip,
            ]);

            echo json_encode([
                'success' => true,
                'token'   => $token,
                'user'    => formatUserResponse($user, $auth),
            ]);
        } catch (Exception $e) {
            $logger->error('[AUTH-BIOMETRIC-LOGIN] server error', [
                'device_id' => substr(hash('sha256', $deviceId), 0, 16),
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        }
        break;

    // ============================================
    // BIOMETRIC-DISABLE — remove biometric key for a device (authenticated)
    // ============================================
    case 'POST:biometric-disable':
        $authUser = $auth->requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $deviceId = trim($input['device_id'] ?? '');
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!$deviceId) {
            $logger->warning('[AUTH-BIOMETRIC-DISABLE] missing device_id', ['user_id' => (int)$authUser['sub'], 'ip' => $ip]);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'device_id requis']);
            break;
        }

        try {
            $pdo = getDatabase();
            $auth->clearBiometricKey($pdo, (int)$authUser['sub'], $deviceId);
            $logger->info('[AUTH-BIOMETRIC-DISABLE] biometric key cleared', [
                'user_id' => (int)$authUser['sub'],
                'device_id' => substr(hash('sha256', $deviceId), 0, 16),
                'ip' => $ip,
            ]);
            echo json_encode(['success' => true, 'message' => 'Biométrie désactivée pour cet appareil']);
        } catch (Exception $e) {
            $logger->error('[AUTH-BIOMETRIC-DISABLE] server error', [
                'user_id' => (int)$authUser['sub'],
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        }
        break;

    // ============================================
    // REGISTER — create account (magic link, no password)
    // ============================================
    case 'POST:register':
        $input = json_decode(file_get_contents('php://input'), true);
        $email       = trim($input['email'] ?? '');
        $companyName = trim($input['company_name'] ?? '');
        $slug        = trim($input['slug'] ?? '');
        $login       = trim($input['login'] ?? '');
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!$email || !$companyName) {
            $logger->warning('[AUTH-REGISTER] missing fields', ['ip' => $ip]);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Email et nom d\'entreprise requis']);
            break;
        }

        if (!$slug) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $companyName));
            $slug = trim($slug, '-');
        }

        if (!$login) {
            $login = strtolower(preg_replace('/[^a-z0-9._-]+/i', '', explode('@', $email)[0]));
        }

        try {
            $pdo = getDatabase();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                $logger->warning('[AUTH-REGISTER] email already used', ['email' => $email, 'ip' => $ip]);
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Email déjà utilisé']);
                break;
            }

            if ($login) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ? LIMIT 1");
                $stmt->execute([$login]);
                if ($stmt->fetch()) {
                    $pdo->rollBack();
                    $logger->warning('[AUTH-REGISTER] login already taken', ['login' => $login, 'ip' => $ip]);
                    http_response_code(409);
                    echo json_encode(['success' => false, 'error' => 'Identifiant déjà pris']);
                    break;
                }
            }

            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE slug = ? LIMIT 1");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                $logger->warning('[AUTH-REGISTER] slug already taken', ['slug' => $slug, 'ip' => $ip]);
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Slug entreprise déjà pris']);
                break;
            }

            $subdomain = "$slug.webiartisan.prigent.tech";
            $stmt = $pdo->prepare("INSERT INTO tenants (slug, name, subdomain) VALUES (?, ?, ?)");
            $stmt->execute([$slug, $companyName, $subdomain]);
            $tenantId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "INSERT INTO users (tenant_id, email, login, role, name) VALUES (?, ?, ?, 'admin', ?)"
            );
            $stmt->execute([$tenantId, $email, $login ?: null, explode('@', $email)[0]]);
            $userId = (int) $pdo->lastInsertId();

            $pdo->commit();

            // Send magic code to verify email
            $code = $auth->generateCode();
            $auth->storeCode($pdo, $userId, $code);
            $sent = $auth->sendCode($email, $code);

            $logger->info('[AUTH-REGISTER] account created', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'email' => $email,
                'ip' => $ip,
                'code_sent' => $sent ? 'yes' : 'no',
            ]);

            echo json_encode([
                'success'    => true,
                'message'    => 'Compte créé. Un code de vérification a été envoyé.',
                'user_id'    => $userId,
                'email_hint' => preg_replace('/^(.).+(@.+)$/', '$1***$2', $email),
            ]);
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $logger->error('[AUTH-REGISTER] server error', ['email' => $email, 'ip' => $ip, 'error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        }
        break;

    // ============================================
    // GET ME — current user info (authenticated)
    // ============================================
    case 'GET:me':
        $authUser = $auth->requireAuth();
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        try {
            $pdo = getDatabase();
            $stmt = $pdo->prepare(
                "SELECT u.id, u.email, u.login, u.name, u.fonction, u.phone, u.role,
                        u.tenant_id, u.last_login_at, u.avatar_path,
                        t.slug as tenant_slug, t.name as tenant_name
                 FROM users u JOIN tenants t ON t.id = u.tenant_id
                 WHERE u.id = ? AND u.is_active = TRUE
                 LIMIT 1"
            );
            $stmt->execute([$authUser['sub']]);
            $user = $stmt->fetch();

            if (!$user) {
                $logger->warning('[AUTH-ME] user not found', ['user_id' => (int)$authUser['sub'], 'ip' => $ip]);
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                break;
            }

            echo json_encode(['success' => true, 'user' => formatUserResponse($user, $auth)]);
        } catch (Exception $e) {
            $logger->error('[AUTH-ME] server error', ['user_id' => (int)$authUser['sub'], 'ip' => $ip, 'error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        }
        break;

    // ============================================
    // LOGOUT — clear remember cookie
    // ============================================
    case 'POST:logout':
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        try {
            $pdo = getDatabase();
            $auth->clearRememberToken($pdo);
            $logger->info('[AUTH-LOGOUT] remember token cleared', ['ip' => $ip]);
        } catch (Exception $e) {
            $logger->warning('[AUTH-LOGOUT] failed to clear remember token', ['ip' => $ip, 'error' => $e->getMessage()]);
        }
        echo json_encode(['success' => true, 'message' => 'Déconnecté']);
        break;

    // ============================================
    // AVATAR UPLOAD (authenticated)
    // ============================================
    case 'POST:avatar':
        $authUser = $auth->requireAuth();

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Fichier requis']);
            break;
        }

        $file = $_FILES['avatar'];
        $maxSize = 2 * 1024 * 1024; // 2 MB
        if ($file['size'] > $maxSize) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 2 Mo)']);
            break;
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Format non supporté (jpg, png, webp, gif)']);
            break;
        }

        $ext = match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default      => 'jpg',
        };

        $uploadDir = __DIR__ . '/../uploads/avatars';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'avatar_' . $authUser['sub'] . '_' . time() . '.' . $ext;
        $filepath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'upload']);
            break;
        }

        try {
            $pdo = getDatabase();

            // Delete old avatar file
            $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ?");
            $stmt->execute([$authUser['sub']]);
            $old = $stmt->fetchColumn();
            if ($old) {
                $oldFile = $uploadDir . '/' . basename($old);
                if (file_exists($oldFile)) unlink($oldFile);
            }

            // Update DB
            $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?")
                ->execute([$filename, $authUser['sub']]);

            echo json_encode([
                'success'    => true,
                'avatar_url' => $auth->getAvatarUrl($filename),
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        }
        break;

    // ============================================
    // SERVE AVATAR (public)
    // ============================================
    case 'GET:avatar':
        $avatarFile = $param ?? '';
        $avatarFile = basename($avatarFile); // security
        $filepath = __DIR__ . '/../uploads/avatars/' . $avatarFile;

        if (!$avatarFile || !file_exists($filepath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Avatar not found']);
            break;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filepath);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;

    // ============================================
    // SSO-TOKEN — issue a short-lived SSO token (authenticated)
    // Used by app.prigent.tech to hand off session to web.prigent.tech
    // ============================================
    case 'POST:sso-token':
        $authUser = $auth->requireAuth();
        try {
            $pdo = getDatabase();
            $stmt = $pdo->prepare(
                "SELECT u.id, u.email, u.login, u.name, u.role, u.tenant_id, u.avatar_path,
                        t.slug as tenant_slug, t.name as tenant_name
                 FROM users u JOIN tenants t ON t.id = u.tenant_id
                 WHERE u.id = ? AND u.is_active = TRUE LIMIT 1"
            );
            $stmt->execute([$authUser['sub']]);
            $user = $stmt->fetch();

            if (!$user) {
                $logger->warning('SSO token request: user not found', ['user_id' => $authUser['sub']]);
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                break;
            }

            $ssoToken = $auth->generateSsoToken($user);
            $logger->info('SSO token issued', [
                'user_id'   => $user['id'],
                'email'     => $user['email'],
                'origin'    => $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? 'unknown',
            ]);
            echo json_encode(['success' => true, 'sso_token' => $ssoToken]);
        } catch (Exception $e) {
            $logger->error('SSO token error', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        }
        break;

    // ============================================
    // SSO-VERIFY — verify an SSO token, return full JWT + user (public)
    // Used by web.prigent.tech to establish a session from app.prigent.tech
    // ============================================
    case 'POST:sso-verify':
        $input = json_decode(file_get_contents('php://input'), true);
        $ssoToken = trim($input['sso_token'] ?? '');

        if (!$ssoToken) {
            $logger->warning('SSO verify: missing sso_token', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'sso_token requis']);
            break;
        }

        try {
            $payload = $auth->verifySsoToken($ssoToken);

            if (!$payload) {
                $logger->warning('SSO verify: invalid or expired token', [
                    'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
                    'origin' => $_SERVER['HTTP_ORIGIN'] ?? '',
                ]);
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'SSO token invalide ou expiré']);
                break;
            }

            $pdo = getDatabase();
            $stmt = $pdo->prepare(
                "SELECT u.id, u.email, u.login, u.name, u.role, u.tenant_id, u.avatar_path,
                        t.slug as tenant_slug, t.name as tenant_name
                 FROM users u JOIN tenants t ON t.id = u.tenant_id
                 WHERE u.id = ? AND u.is_active = TRUE LIMIT 1"
            );
            $stmt->execute([$payload['sub']]);
            $user = $stmt->fetch();

            if (!$user) {
                $logger->warning('SSO verify: user not found after valid token', ['sub' => $payload['sub']]);
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Compte introuvable']);
                break;
            }

            $token = $auth->generateToken($user);
            $logger->info('SSO verify: session established', [
                'user_id'   => $user['id'],
                'email'     => $user['email'],
                'origin'    => $_SERVER['HTTP_ORIGIN'] ?? '',
            ]);

            echo json_encode([
                'success' => true,
                'token'   => $token,
                'user'    => formatUserResponse($user, $auth),
            ]);
        } catch (Exception $e) {
            $logger->error('SSO verify error', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Unknown auth action: $action"]);
}
