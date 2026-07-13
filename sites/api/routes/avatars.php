<?php
/**
 * Avatar routes — upload user avatars.
 *
 * Endpoint:
 *   POST /api/avatars/upload     (authenticated)
 *
 * Uploaded files are served statically by nginx from /uploads/avatars/.
 */

$auth = new Auth();
$uploadDir = __DIR__ . '/../uploads/avatars';
$maxSize = 2 * 1024 * 1024; // 2 MB
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

function getAvatarUploadDir(): string {
    $dir = __DIR__ . '/../uploads/avatars';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function validateAvatarFile(array $file, int $maxSize, array $allowedTypes): ?string {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'Fichier trop volumineux (limite serveur)',
            UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux (limite formulaire)',
            UPLOAD_ERR_PARTIAL    => 'Fichier partiellement uploadé',
            UPLOAD_ERR_NO_FILE    => 'Fichier requis',
            UPLOAD_ERR_NO_TMP_DIR => 'Répertoire temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Écriture impossible',
            UPLOAD_ERR_EXTENSION  => 'Extension PHP a bloqué l\'upload',
        ];
        return $messages[$code] ?? 'Erreur lors de l\'upload';
    }

    if ($file['size'] > $maxSize) {
        return 'Fichier trop volumineux (max 2 Mo)';
    }

    $tmpName = $file['tmp_name'];
    if (!is_uploaded_file($tmpName)) {
        return 'Fichier invalide';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes, true)) {
        return 'Format non supporté (jpg, png, webp, gif)';
    }

    // Reject files that claim to be images but are not valid
    if (!getimagesize($tmpName)) {
        return 'Image invalide';
    }

    return null;
}

function extensionFromMime(string $mimeType): string {
    return match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'jpg',
    };
}

if ($method === 'GET' && ($action === '' || $action === 'list')) {
    // ============================================
    // LIST — bibliothèque d'avatars par genre
    // ============================================
    // Endpoint public : les métadonnées (unlock_level, unlock_badge) sont
    // renvoyées brutes, le verrouillage est calculé côté client.
    $gender = $_GET['gender'] ?? 'neutral';
    if (!in_array($gender, ['male', 'female', 'neutral'], true)) {
        $gender = 'neutral';
    }

    $avatars = [];
    // Même logique que getAvailableAvatar (users.php) : un genre non-neutre
    // inclut aussi les avatars neutres, et retombe sur eux si son dossier
    // est vide.
    $genders = $gender === 'neutral' ? ['neutral'] : [$gender, 'neutral'];
    foreach ($genders as $dirGender) {
        $dir = __DIR__ . '/../public/avatars/' . $dirGender;
        if (!is_dir($dir)) {
            continue;
        }
        foreach (glob($dir . '/*.{png,svg,jpg,jpeg}', GLOB_BRACE) as $file) {
            $metaFile = $file . '.json';
            $meta = file_exists($metaFile) ? (json_decode(file_get_contents($metaFile), true) ?: []) : [];
            $fallbackId = pathinfo($file, PATHINFO_FILENAME);
            $avatars[] = [
                'id' => $meta['id'] ?? $fallbackId,
                'gender' => $meta['gender'] ?? $dirGender,
                'name' => $meta['name'] ?? ($meta['id'] ?? $fallbackId),
                'type' => $meta['type'] ?? 'custom',
                'url' => '/avatars/' . $dirGender . '/' . basename($file),
                'unlock_level' => (int) ($meta['unlock_level'] ?? 1),
                'unlock_badge' => $meta['unlock_badge'] ?? null,
            ];
        }
    }

    echo json_encode(['success' => true, 'data' => $avatars]);
    return;
}

if ($method === 'POST' && $action === 'upload') {
    // ============================================
    // UPLOAD — replace current user's avatar
    // ============================================
    $authUser = $auth->requireAuth();

    if (!isset($_FILES['avatar'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Fichier requis']);
        return;
    }

    $error = validateAvatarFile($_FILES['avatar'], $maxSize, $allowedTypes);
    if ($error !== null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $error]);
        return;
    }

    $file = $_FILES['avatar'];
    $uploadDir = getAvatarUploadDir();

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $ext = extensionFromMime($mimeType);
    $filename = 'avatar_' . (int) $authUser['sub'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filepath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'upload']);
        return;
    }

    try {
        $pdo = getDatabase();

        // Delete old avatar file
        $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ?");
        $stmt->execute([(int) $authUser['sub']]);
        $old = $stmt->fetchColumn();
        if ($old) {
            $oldFile = $uploadDir . '/' . basename($old);
            if (file_exists($oldFile) && is_file($oldFile)) {
                unlink($oldFile);
            }
        }

        $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?")
            ->execute([$filename, (int) $authUser['sub']]);

        $logger->info('[AVATAR] uploaded', [
            'user_id' => (int) $authUser['sub'],
            'filename' => $filename,
        ]);

        echo json_encode([
            'success'    => true,
            'avatar_url' => $auth->getAvatarUrl($filename),
        ]);
    } catch (Exception $e) {
        // Clean up uploaded file on DB failure
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        $logger->error('[AVATAR] upload failed', [
            'user_id' => (int) $authUser['sub'],
            'error' => $e->getMessage(),
        ]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
    }
    return;
}

http_response_code(404);
echo json_encode(['success' => false, 'error' => "Unknown avatar action: $action"]);
