<?php
/**
 * WebIArtisan API — Route : Utilisateurs (consommateurs)
 *
 * POST /users/magic-link        — envoie un lien magique
 * POST /users/auth?token=...    — valide le token et crée une session
 * GET  /users/me                — infos utilisateur connecté
 */

require_once __DIR__ . '/../lib/Mailer.php';
require_once __DIR__ . '/../lib/UserAuth.php';

switch ($method) {
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($action === 'magic-link') {
            user_magic_link($pdo, $body);
        } elseif ($action === 'auth') {
            user_auth($pdo);
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
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email invalide']);
        return;
    }

    $rememberMe = !empty($body['rememberMe']);

    $redirect = $body['redirect'] ?? '/roue';
    if (!is_string($redirect)) {
        $redirect = '/roue';
    } else {
        $redirect = trim($redirect);
        if ($redirect === '' || $redirect[0] !== '/' || preg_match('#^//#', $redirect) || strpbrk($redirect, "\r\n#") !== false || strpos($redirect, '..') !== false) {
            $redirect = '/roue';
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM local_users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $pdo->prepare("INSERT INTO local_users (email) VALUES (?)")->execute([$email]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $userId = (int)$user['id'];
    }

    $token = bin2hex(random_bytes(32));
    $exp = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $pdo->prepare("
        UPDATE local_users
        SET magic_token = ?, magic_token_exp = ?
        WHERE id = ?
    ")->execute([$token, $exp, $userId]);

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $base = 'https://artisans-livry.prigent.tech';
    if ($origin && filter_var($origin, FILTER_VALIDATE_URL)) {
        $parsed = parse_url($origin);
        if (in_array($parsed['scheme'] ?? '', ['http', 'https'], true)) {
            $base = $origin;
        }
    }
    $link = rtrim($base, '/') . $redirect
        . (strpos($redirect, '?') !== false ? '&' : '?')
        . 'token=' . urlencode($token)
        . ($rememberMe ? '&rememberMe=1' : '');

    $subject = 'Votre lien pour tourner la roue des artisans';
    $html = <<<HTML
<!DOCTYPE html>
<html><body style="font-family: -apple-system, sans-serif; max-width: 480px; margin: 0 auto; padding: 20px;">
  <h2 style="color: #1a1a2e;">Bonjour,</h2>
  <p>Voici votre lien sécurisé pour tourner la roue des artisans de Livry :</p>
  <div style="text-align: center; margin: 24px 0;">
    <a href="{$link}" style="display: inline-block; background: #1a1a2e; color: #fff; padding: 14px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;">Tourner la roue</a>
  </div>
  <p style="color: #888; font-size: 13px;">Ce lien est valable 1 heure. Si vous ne l'avez pas demandé, ignorez cet email.</p>
</body></html>
HTML;

    $config = getAppConfig();
    $fromEmail = $config['mail_from'] ?? 'noreply@webiartisan.prigent.tech';

    $sent = send_html_email($email, $subject, $html, $fromEmail, 'WebIArtisan');
    if (!$sent) {
        error_log("[USER-MAGIC-LINK] Échec envoi email à {$email}");
    }
    error_log(sprintf(
        "[USER-MAGIC-LINK] email=%s user_id=%s rememberMe=%s redirect=%s origin=%s from=%s sent=%s link=%s",
        $email,
        $userId,
        $rememberMe ? '1' : '0',
        $redirect,
        $_SERVER['HTTP_ORIGIN'] ?? 'none',
        $fromEmail,
        $sent ? '1' : '0',
        $link
    ));

    echo json_encode([
        'success' => true,
        'message' => 'Si votre email est valide, vous recevrez un lien de connexion.',
    ]);
}

function user_auth(PDO $pdo): void
{
    $token = $_GET['token'] ?? '';
    if (!$token) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token manquant']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id, email
        FROM local_users
        WHERE magic_token = ? AND magic_token_exp > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Lien invalide ou expiré']);
        return;
    }

    $sessionToken = bin2hex(random_bytes(32));
    $sessionExp = date('Y-m-d H:i:s', strtotime('+30 days'));

    $pdo->prepare("
        UPDATE local_users
        SET session_token = ?, session_exp = ?,
            magic_token = NULL, magic_token_exp = NULL
        WHERE id = ?
    ")->execute([$sessionToken, $sessionExp, $user['id']]);

    try {
        require_once __DIR__ . '/../lib/Gamification.php';
        gamificationUpdateStreak($pdo, (int)$user['id']);
    } catch (Throwable $e) {
        error_log('[AUTH-STREAK] ' . $e->getMessage());
    }

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
