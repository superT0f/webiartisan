<?php
/**
 * WebIArtisan API — Route : Utilisateurs (consommateurs)
 *
 * POST /users/magic-link        — envoie un lien magique
 * POST /users/auth?token=...    — valide le token et crée une session
 * POST /users/register          — crée un compte avec mot de passe
 * POST /users/login             — connexion avec mot de passe
 * POST /users/logout            — déconnexion
 * POST /users/forgot-password   — demande de réinitialisation
 * POST /users/reset-password    — réinitialise le mot de passe
 * POST /users/change-password   — change le mot de passe (connecté)
 * GET  /users/me                — infos utilisateur connecté
 */

require_once __DIR__ . '/../lib/Mailer.php';
require_once __DIR__ . '/../lib/UserAuth.php';
require_once __DIR__ . '/../lib/AppLogger.php';

const USER_AUTH_TIMING_TARGET_MS = 400;

function pad_user_auth_response(float $startTime): void
{
    $elapsedMs = (int) ((microtime(true) - $startTime) * 1000);
    if ($elapsedMs < USER_AUTH_TIMING_TARGET_MS) {
        usleep((USER_AUTH_TIMING_TARGET_MS - $elapsedMs) * 1000);
    }
}

$authActions = ['magic-link', 'auth', 'register', 'login', 'forgot-password', 'reset-password', 'change-password'];
if (in_array($action, $authActions, true)) {
    applyRateLimit($pdo, 'login');
} else {
    applyRateLimit($pdo, 'public');
}

switch ($method) {
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($action === 'magic-link') {
            user_magic_link($pdo, $body);
        } elseif ($action === 'auth') {
            user_auth($pdo);
        } elseif ($action === 'register') {
            user_register($pdo, $body);
        } elseif ($action === 'login') {
            user_login($pdo, $body);
        } elseif ($action === 'logout') {
            user_logout_endpoint($pdo);
        } elseif ($action === 'forgot-password') {
            user_forgot_password($pdo, $body);
        } elseif ($action === 'reset-password') {
            user_reset_password($pdo, $body);
        } elseif ($action === 'change-password') {
            user_change_password($pdo, $body);
        } elseif ($action === 'me' && $param === 'avatar') {
            user_update_avatar($pdo, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'PUT':
        if ($action === 'me') {
            user_update_profile($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'GET':
        if ($action === 'me') {
            user_me($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function user_magic_link(PDO $pdo, array $body): void
{
    $email = strtolower(trim($body['email'] ?? ''));
    app_log('info', '[USER-MAGIC-LINK] start', ['email' => $email, 'rememberMe' => !empty($body['rememberMe'])]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        app_log('warning', '[USER-MAGIC-LINK] invalid email', ['email' => $email]);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email invalide']);
        return;
    }

    $rememberMe = !empty($body['rememberMe']);

    $rawRedirect = $body['redirect'] ?? '/roue';
    if (!is_string($rawRedirect)) {
        $rawRedirect = '/roue';
    }
    $rawRedirect = trim($rawRedirect);
    $redirect = trim(urldecode($rawRedirect));
    if ($redirect === '' || $redirect[0] !== '/' || preg_match('#^//#', $redirect) || strpbrk($redirect, "\r\n#") !== false || strpos($redirect, '..') !== false) {
        $redirect = '/roue';
        $rawRedirect = '/roue';
    }

    $startTime = microtime(true);

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare("
            INSERT INTO local_users (email) VALUES (?)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
        ");
        $insert->execute([$email]);
        $userId = (int)$pdo->lastInsertId();

        if ($userId === 0) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $exp = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $pdo->prepare("
            UPDATE local_users
            SET magic_token = ?, magic_token_exp = ?
            WHERE id = ?
        ")->execute([$tokenHash, $exp, $userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        app_log('error', '[USER-MAGIC-LINK] database error', ['email' => $email, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    $allowedOrigins = [
        'http://localhost:8080',
        'http://localhost:5173',
        'http://localhost:1313',
        'https://app.prigent.tech',
        'https://web.prigent.tech',
        'https://artisans-combs.prigent.tech',
        'https://artisans-vert-saint-denis.prigent.tech',
        'https://artisans-livry.prigent.tech',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $base = 'https://artisans-livry.prigent.tech';
    if ($origin && in_array($origin, $allowedOrigins, true)) {
        $base = $origin;
    }
    $link = rtrim($base, '/') . $redirect
        . (strpos($redirect, '?') !== false ? '&' : '?')
        . 'token=' . urlencode($token)
        . ($rememberMe ? '&rememberMe=1' : '');

    $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

    $subject = 'Votre lien pour tourner la roue des artisans';
    $html = <<<HTML
<!DOCTYPE html>
<html><body style="font-family: -apple-system, sans-serif; max-width: 480px; margin: 0 auto; padding: 20px;">
  <h2 style="color: #1a1a2e;">Bonjour,</h2>
  <p>Voici votre lien sécurisé pour tourner la roue des artisans de Livry :</p>
  <div style="text-align: center; margin: 24px 0;">
    <a href="{$safeLink}" style="display: inline-block; background: #1a1a2e; color: #fff; padding: 14px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;">Tourner la roue</a>
  </div>
  <p style="color: #888; font-size: 13px;">Ce lien est valable 1 heure. Si vous ne l'avez pas demandé, ignorez cet email.</p>
</body></html>
HTML;

    $config = getAppConfig();
    $fromEmail = $config['mail_from'] ?? 'noreply@webiartisan.prigent.tech';

    $queued = queueEmail(
        $email,
        $subject,
        $html,
        $fromEmail,
        'WebIArtisan',
        null,
        ['type' => 'user_magic_link', 'user_id' => $userId, 'redirect' => $rawRedirect]
    );
    if (!$queued) {
        error_log("[USER-MAGIC-LINK] Échec mise en file email à {$email}");
    }
    $redactedLink = preg_replace('/token=[^&]+/', 'token=REDACTED', $link);
    error_log(sprintf(
        "[USER-MAGIC-LINK] email=%s user_id=%s rememberMe=%s redirect=%s base=%s from=%s queued=%s link=%s",
        $email,
        $userId,
        $rememberMe ? '1' : '0',
        $rawRedirect,
        $base,
        $fromEmail,
        $queued ? '1' : '0',
        $redactedLink
    ));

    // Pad response time to prevent email enumeration via timing.
    pad_user_auth_response($startTime);

    app_log('info', '[USER-MAGIC-LINK] success', ['email' => $email, 'user_id' => $userId, 'queued' => $queued ? '1' : '0']);
    echo json_encode([
        'success' => true,
        'message' => 'Si votre email est valide, vous recevrez un lien de connexion.',
    ]);
}

function user_auth(PDO $pdo): void
{
    $rawToken = $_GET['token'] ?? '';
    app_log('info', '[USER-AUTH] start', ['has_token' => !empty($rawToken)]);
    if (!$rawToken) {
        app_log('warning', '[USER-AUTH] missing token');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token manquant']);
        return;
    }

    $tokenHash = hash('sha256', $rawToken);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT id, email
            FROM local_users
            WHERE magic_token = ? AND magic_token_exp > NOW()
            FOR UPDATE
        ");
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $pdo->rollBack();
            app_log('warning', '[USER-AUTH] invalid or expired token');
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Lien invalide ou expiré']);
            return;
        }

        $rememberMe = !empty($_GET['rememberMe']);
        $sessionToken = user_create_session($pdo, (int)$user['id'], $rememberMe);

        $pdo->prepare("
            UPDATE local_users
            SET magic_token = NULL, magic_token_exp = NULL, email_verified = TRUE
            WHERE id = ?
        ")->execute([$user['id']]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        app_log('error', '[USER-AUTH] database error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    try {
        require_once __DIR__ . '/../lib/Gamification.php';
        gamificationUpdateStreak($pdo, (int)$user['id']);
    } catch (Throwable $e) {
        app_log('warning', '[USER-AUTH] streak update failed', ['user_id' => (int)$user['id'], 'error' => $e->getMessage()]);
    }

    app_log('info', '[USER-AUTH] success', ['user_id' => (int)$user['id'], 'email' => $user['email']]);
    echo json_encode([
        'success' => true,
        'token'   => $sessionToken,
        'data'    => ['id' => (int)$user['id'], 'email' => $user['email']],
    ]);
}

function user_me(PDO $pdo): void
{
    $user = user_require_auth($pdo);
    require_once __DIR__ . '/../lib/Gamification.php';
    $profile = gamificationUserProfile($pdo, (int)$user['id']);
    if ($profile === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Utilisateur introuvable']);
        return;
    }
    echo json_encode(['success' => true, 'data' => $profile]);
}

function user_update_profile(PDO $pdo): void
{
    $user = user_require_auth($pdo);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $displayName = isset($body['display_name']) ? trim($body['display_name']) : null;
    $avatarGender = $body['avatar_gender'] ?? null;
    $title = $body['title'] ?? null;

    if ($displayName === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Pseudo public requis']);
        return;
    }

    $fields = [];
    $values = [];

    if ($displayName !== null) {
        $fields[] = 'display_name = ?';
        $values[] = substr($displayName, 0, 80);
    }
    if ($avatarGender !== null && in_array($avatarGender, ['male', 'female', 'neutral'], true)) {
        $fields[] = 'avatar_gender = ?';
        $values[] = $avatarGender;
    }
    if ($title !== null) {
        $fields[] = 'title = ?';
        $values[] = substr($title, 0, 80);
    }

    if ($fields) {
        $pdo->beginTransaction();
        try {
            $currentStmt = $pdo->prepare("SELECT avatar_type, avatar_url FROM local_users WHERE id = ? FOR UPDATE");
            $currentStmt->execute([$user['id']]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

            if (
                $current &&
                ($current['avatar_type'] ?? '') !== 'upload' &&
                $avatarGender !== null &&
                in_array($avatarGender, ['male', 'female', 'neutral'], true) &&
                $avatarGender !== 'neutral'
            ) {
                $currentUrl = $current['avatar_url'] ?? '';
                if (preg_match('#^/avatars/([^/]+)/#', $currentUrl, $m)) {
                    $oldGender = $m[1];
                    if ($oldGender !== $avatarGender && $oldGender !== 'neutral') {
                        $fields[] = "avatar_type = ?";
                        $values[] = 'default';
                        $fields[] = "avatar_url = NULL";
                    }
                }
            }

            $values[] = $user['id'];
            $pdo->prepare("UPDATE local_users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
            return;
        }
    }

    require_once __DIR__ . '/../lib/Gamification.php';
    $profile = gamificationUserProfile($pdo, (int)$user['id']);
    if ($profile === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Utilisateur introuvable']);
        return;
    }
    echo json_encode(['success' => true, 'data' => $profile]);
}

function getAvailableAvatar(PDO $pdo, string $avatarId, int $userLevel, array $userBadges, string $userGender = 'neutral', ?string $preferredGender = null): ?array
{
    $basePath = __DIR__ . '/../public/avatars';
    $genders = [];
    if ($preferredGender !== null && in_array($preferredGender, ['male', 'female', 'neutral'], true)) {
        $genders[] = $preferredGender;
        if ($preferredGender !== 'neutral') {
            $genders[] = 'neutral';
        }
    } elseif ($userGender === 'neutral') {
        $genders = ['neutral', 'male', 'female'];
    } else {
        $genders = [$userGender, 'neutral'];
    }
    $dirs = [];
    foreach ($genders as $gender) {
        $path = $basePath . '/' . $gender;
        if (is_dir($path)) {
            $dirs[] = $path;
        }
    }

    foreach ($dirs as $dir) {
        foreach (glob($dir . '/*.{png,svg,jpg,jpeg}', GLOB_BRACE) as $file) {
            $metaFile = $file . '.json';
            $meta = file_exists($metaFile) ? (json_decode(file_get_contents($metaFile), true) ?: []) : [];
            $id = $meta['id'] ?? pathinfo($file, PATHINFO_FILENAME);
            if ($id === $avatarId) {
                $dirName = basename($dir);
                if ($userGender !== 'neutral' && $dirName !== 'neutral' && $dirName !== $userGender) {
                    return null;
                }
                $unlockLevel = $meta['unlock_level'] ?? 1;
                $unlockBadge = $meta['unlock_badge'] ?? null;
                if ($userLevel < $unlockLevel) return null;
                if ($unlockBadge && !in_array($unlockBadge, $userBadges, true)) return null;
                return [
                    'id' => $id,
                    'url' => '/avatars/' . $dirName . '/' . basename($file),
                    'type' => $meta['type'] ?? 'custom',
                ];
            }
        }
    }
    return null;
}

function deleteOldUploadedAvatar(?string $url): void
{
    if (!$url) return;
    if (preg_match('#^/uploads/avatars/([^/]+)$#', $url, $m)) {
        $path = __DIR__ . '/../uploads/avatars/' . $m[1];
        if (file_exists($path)) @unlink($path);
    }
}

function user_update_avatar(PDO $pdo, array $body): void
{
    $user = user_require_auth($pdo);

    $uploadDir = __DIR__ . '/../uploads/avatars/';
    $oldStmt = $pdo->prepare("SELECT avatar_url FROM local_users WHERE id = ?");
    $oldStmt->execute([$user['id']]);
    $oldAvatarUrl = $oldStmt->fetchColumn();

    if (!empty($body['avatar_id'])) {
        $badgeStmt = $pdo->prepare("SELECT badge_key FROM local_user_badges WHERE user_id = ?");
        $badgeStmt->execute([$user['id']]);
        $badgeKeys = array_column($badgeStmt->fetchAll(PDO::FETCH_ASSOC), 'badge_key');
        $userStmt = $pdo->prepare("SELECT level, avatar_gender FROM local_users WHERE id = ?");
        $userStmt->execute([$user['id']]);
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
        $userLevel = (int)($userRow['level'] ?? 0);
        $userGender = in_array($userRow['avatar_gender'] ?? '', ['male', 'female', 'neutral'], true)
            ? $userRow['avatar_gender']
            : 'neutral';
        $preferredGender = $body['avatar_gender'] ?? null;
        $avatar = getAvailableAvatar($pdo, $body['avatar_id'], $userLevel, $badgeKeys, $userGender, $preferredGender);
        if (!$avatar) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Avatar non disponible']);
            return;
        }
        $pdo->prepare("UPDATE local_users SET avatar_type = ?, avatar_url = ? WHERE id = ?")
            ->execute([$avatar['type'], $avatar['url'], $user['id']]);

        deleteOldUploadedAvatar($oldAvatarUrl);
    } elseif (!empty($body['base64_image'])) {
        $base64 = $body['base64_image'];
        if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $base64, $matches)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Format invalide']);
            return;
        }

        $base64Payload = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
        $decodedSizeEstimate = (int) (strlen($base64Payload) * 3 / 4);
        if ($decodedSizeEstimate > 2 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Image trop lourde']);
            return;
        }

        $data = base64_decode($base64Payload);
        if (!$data || strlen($data) > 2 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Image trop lourde ou invalide']);
            return;
        }

        $imageInfo = getimagesizefromstring($data);
        if (!$imageInfo || !in_array($imageInfo[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Image invalide']);
            return;
        }
        $isPng = $imageInfo[2] === IMAGETYPE_PNG;

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        if (extension_loaded('gd')) {
            $src = imagecreatefromstring($data);
            if (!$src) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Image invalide']);
                return;
            }

            $size = 256;
            $origW = imagesx($src);
            $origH = imagesy($src);
            $min = min($origW, $origH);
            $cropX = (int)(($origW - $min) / 2);
            $cropY = (int)(($origH - $min) / 2);
            $dst = imagecreatetruecolor($size, $size);
            if (!$dst) {
                imagedestroy($src);
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Impossible de créer l\'image']);
                return;
            }

            if ($isPng) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                imagefill($dst, 0, 0, $transparent);
            }

            imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $size, $size, $min, $min);

            $ext = $isPng ? 'png' : 'jpg';
            $fileName = $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $filePath = $uploadDir . $fileName;
            if ($isPng) {
                imagepng($dst, $filePath, 6);
            } else {
                imagejpeg($dst, $filePath, 85);
            }
            imagedestroy($src);
            imagedestroy($dst);
        } else {
            $ext = $isPng ? 'png' : 'jpg';
            $fileName = $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $filePath = $uploadDir . $fileName;
            file_put_contents($filePath, $data);
        }

        $publicUrl = '/uploads/avatars/' . $fileName;
        $pdo->prepare("
            UPDATE local_users
            SET avatar_type = 'upload', avatar_url = ?
            WHERE id = ?
        ")->execute([$publicUrl, $user['id']]);

        deleteOldUploadedAvatar($oldAvatarUrl);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucun avatar fourni']);
        return;
    }

    require_once __DIR__ . '/../lib/Gamification.php';
    $profile = gamificationUserProfile($pdo, (int)$user['id']);
    if ($profile === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Utilisateur introuvable']);
        return;
    }
    echo json_encode(['success' => true, 'data' => $profile]);
}

function user_register(PDO $pdo, array $body): void
{
    $email       = strtolower(trim($body['email'] ?? ''));
    $password    = $body['password'] ?? '';
    $displayName = isset($body['display_name']) ? trim($body['display_name']) : null;

    app_log('info', '[USER-REGISTER] start', ['email' => $email, 'has_password' => !empty($password), 'has_display_name' => !empty($displayName)]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        app_log('warning', '[USER-REGISTER] validation failed: invalid email', ['email' => $email]);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email invalide']);
        return;
    }
    if (strlen($password) < 8) {
        app_log('warning', '[USER-REGISTER] validation failed: password too short', ['email' => $email]);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Mot de passe trop court (min 8 caractères)']);
        return;
    }

    $startTime = microtime(true);
    $genericMessage = 'Si votre email est valide, votre inscription est prise en compte.';

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $displayNameToStore = $displayName ? substr($displayName, 0, 80) : null;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id, password_hash FROM local_users WHERE email = ? FOR UPDATE");
        $stmt->execute([$email]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            app_log('info', '[USER-REGISTER] creating new user', ['email' => $email]);
            $pdo->prepare("
                INSERT INTO local_users (email, password_hash, display_name, email_verified)
                VALUES (?, ?, ?, FALSE)
            ")->execute([
                $email,
                $passwordHash,
                $displayNameToStore,
            ]);
        } elseif (empty($existing['password_hash'])) {
            app_log('info', '[USER-REGISTER] completing magic-link account', ['email' => $email, 'user_id' => (int)$existing['id']]);
            // Complete a magic-link-only account and invalidate any active magic link.
            $pdo->prepare("
                UPDATE local_users
                SET password_hash = ?,
                    display_name = COALESCE(?, display_name),
                    magic_token = NULL,
                    magic_token_exp = NULL
                WHERE email = ? AND password_hash IS NULL
            ")->execute([$passwordHash, $displayNameToStore, $email]);
        } else {
            app_log('info', '[USER-REGISTER] account already has password, silent success', ['email' => $email, 'user_id' => (int)$existing['id']]);
        }
        // If the account already has a password, do nothing (do not reveal existence).

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        app_log('error', '[USER-REGISTER] database error', ['email' => $email, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    $response = ['success' => true, 'message' => $genericMessage];

    // Pad response time to prevent email enumeration via timing.
    pad_user_auth_response($startTime);

    app_log('info', '[USER-REGISTER] success', ['email' => $email]);
    echo json_encode($response);
}

function user_login(PDO $pdo, array $body): void
{
    $email    = strtolower(trim($body['email'] ?? ''));
    $password = $body['password'] ?? '';

    app_log('info', '[USER-LOGIN] start', ['email' => $email, 'has_password' => !empty($password), 'rememberMe' => !empty($body['rememberMe'])]);

    $startTime = microtime(true);
    $error = null;
    $response = null;

    if (!$email || !$password) {
        app_log('warning', '[USER-LOGIN] validation failed: missing fields', ['email' => $email]);
        $error = ['code' => 400, 'body' => ['success' => false, 'error' => 'Email et mot de passe requis']];
    } else {
        $dummyHash = '$2y$10$nJE.S3ari5fK7bx/5wTzLuAqtQF2nVkanks.m5AdkvLK3s9ity/i6';

        try {
            $stmt = $pdo->prepare("SELECT id, email, password_hash FROM local_users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            app_log('error', '[USER-LOGIN] database query failed', ['email' => $email, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $error = ['code' => 500, 'body' => ['success' => false, 'error' => 'Erreur serveur']];
            pad_user_auth_response($startTime);
            http_response_code($error['code']);
            echo json_encode($error['body']);
            return;
        }

        if (!$user) {
            app_log('info', '[USER-LOGIN] user not found', ['email' => $email]);
            $error = ['code' => 401, 'body' => ['success' => false, 'error' => 'Email ou mot de passe incorrect']];
        } elseif (!password_verify($password, $user['password_hash'] ?? $dummyHash)) {
            app_log('info', '[USER-LOGIN] password mismatch', ['email' => $email, 'user_id' => (int)$user['id'], 'has_password_hash' => !empty($user['password_hash'])]);
            $error = ['code' => 401, 'body' => ['success' => false, 'error' => 'Email ou mot de passe incorrect']];
        } else {
            try {
                $rememberMe   = !empty($body['rememberMe']);
                $sessionToken = user_create_session($pdo, (int)$user['id'], $rememberMe);
                $response = [
                    'success' => true,
                    'token'   => $sessionToken,
                    'data'    => ['id' => (int)$user['id'], 'email' => $user['email']],
                ];
                app_log('info', '[USER-LOGIN] success', ['email' => $email, 'user_id' => (int)$user['id']]);
            } catch (Throwable $e) {
                app_log('error', '[USER-LOGIN] session creation failed', ['email' => $email, 'user_id' => (int)$user['id'], 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                $error = ['code' => 500, 'body' => ['success' => false, 'error' => 'Erreur serveur']];
            }
        }
    }

    // Pad response time to prevent email enumeration via timing.
    pad_user_auth_response($startTime);

    if ($error) {
        http_response_code($error['code']);
        echo json_encode($error['body']);
    } else {
        echo json_encode($response);
    }
}

function user_logout_endpoint(PDO $pdo): void
{
    $user = user_require_auth($pdo);
    user_logout($pdo, (int)$user['id']);
    echo json_encode(['success' => true, 'message' => 'Déconnecté']);
}

function user_forgot_password(PDO $pdo, array $body): void
{
    $email = strtolower(trim($body['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email invalide']);
        return;
    }

    $startTime = microtime(true);

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $exp = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare("SELECT id FROM local_users WHERE email = ? FOR UPDATE");
        $lock->execute([$email]);
        $user = $lock->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $pdo->prepare("
                INSERT INTO local_user_password_resets (email, token_hash, expires_at)
                VALUES (?, ?, ?)
            ")->execute([$email, $tokenHash, $exp]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    if ($user) {
        $config  = getAppConfig();
        $baseUrl = $config['url'] ?? 'https://artisans-livry.prigent.tech';
        queuePasswordResetEmail($email, $token, $baseUrl);
    }

    // Pad response time to prevent email enumeration via timing.
    pad_user_auth_response($startTime);

    echo json_encode([
        'success' => true,
        'message' => 'Si votre email est valide, vous recevrez un lien de réinitialisation.',
    ]);
}

function user_reset_password(PDO $pdo, array $body): void
{
    $rawToken    = $body['token'] ?? '';
    $newPassword = $body['password'] ?? '';

    $startTime = microtime(true);

    if (!$rawToken || strlen($newPassword) < 8) {
        pad_user_auth_response($startTime);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token ou mot de passe invalide']);
        return;
    }

    $tokenHash = hash('sha256', $rawToken);

    $pdo->beginTransaction();
    try {
        $consume = $pdo->prepare("
            UPDATE local_user_password_resets
            SET used_at = NOW()
            WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
        ");
        $consume->execute([$tokenHash]);

        if ($consume->rowCount() === 0) {
            $pdo->rollBack();
            pad_user_auth_response($startTime);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Lien invalide ou expiré']);
            return;
        }

        $stmt = $pdo->prepare("SELECT email FROM local_user_password_resets WHERE token_hash = ?");
        $stmt->execute([$tokenHash]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset) {
            $pdo->rollBack();
            pad_user_auth_response($startTime);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Lien invalide ou expiré']);
            return;
        }

        $lock = $pdo->prepare("SELECT id FROM local_users WHERE email = ? FOR UPDATE");
        $lock->execute([$reset['email']]);
        if ($lock->rowCount() === 0) {
            $pdo->rollBack();
            pad_user_auth_response($startTime);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Lien invalide ou expiré']);
            return;
        }

        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE local_users SET password_hash = ? WHERE email = ?")
            ->execute([$passwordHash, $reset['email']]);

        // Invalidate all existing sessions for this user.
        $pdo->prepare("
            UPDATE local_users
            SET session_token = NULL, session_exp = NULL
            WHERE email = ?
        ")->execute([$reset['email']]);

        // Invalidate active magic-link tokens.
        $pdo->prepare("
            UPDATE local_users
            SET magic_token = NULL, magic_token_exp = NULL
            WHERE email = ?
        ")->execute([$reset['email']]);

        $pdo->prepare("
            UPDATE local_user_password_resets
            SET used_at = NOW()
            WHERE email = ? AND used_at IS NULL
        ")->execute([$reset['email']]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        pad_user_auth_response($startTime);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    pad_user_auth_response($startTime);

    echo json_encode([
        'success' => true,
        'message' => 'Votre mot de passe a été mis à jour.',
    ]);
}

/**
 * POST /users/change-password — Changement de mot de passe pour utilisateur connecté
 */
function user_change_password(PDO $pdo, array $body): void
{
    $user = user_require_auth($pdo);
    $startTime = microtime(true);

    $currentPassword = $body['current_password'] ?? '';
    $newPassword     = $body['new_password'] ?? '';
    $confirmPassword = $body['confirm_password'] ?? '';

    if (!$currentPassword || strlen($newPassword) < 8) {
        pad_user_auth_response($startTime);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Mot de passe actuel ou nouveau mot de passe invalide']);
        return;
    }

    if ($newPassword !== $confirmPassword) {
        pad_user_auth_response($startTime);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Les mots de passe ne correspondent pas']);
        return;
    }

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare("SELECT id, password_hash FROM local_users WHERE id = ? FOR UPDATE");
        $lock->execute([(int)$user['id']]);
        $row = $lock->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['password_hash']) || !password_verify($currentPassword, $row['password_hash'])) {
            $pdo->rollBack();
            pad_user_auth_response($startTime);
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Mot de passe actuel incorrect']);
            return;
        }

        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        // Mettre à jour le mot de passe
        $pdo->prepare("UPDATE local_users SET password_hash = ? WHERE id = ?")
            ->execute([$passwordHash, (int)$user['id']]);

        // Invalider les sessions existantes (sauf la session actuelle)
        $pdo->prepare("
            UPDATE local_users
            SET session_token = NULL, session_exp = NULL
            WHERE id = ? AND session_token != ?
        ")->execute([(int)$user['id'], hash('sha256', user_get_session_token() ?? '')]);

        // Invalider les tokens magiques
        $pdo->prepare("
            UPDATE local_users
            SET magic_token = NULL, magic_token_exp = NULL
            WHERE id = ?
        ")->execute([(int)$user['id']]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        pad_user_auth_response($startTime);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    pad_user_auth_response($startTime);

    echo json_encode([
        'success' => true,
        'message' => 'Votre mot de passe a été mis à jour.',
    ]);
}
