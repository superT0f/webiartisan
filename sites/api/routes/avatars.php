<?php
/**
 * Avatar routes — bibliothèque d'avatars.
 *
 * Endpoint:
 *   GET /api/avatars?gender=male|female|neutral   (public)
 *
 * Les fichiers avatars sont servis statiquement par nginx depuis /avatars/.
 * L'upload d'avatar consommateur passe par POST /users/me/avatar (routes/users.php).
 */

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

http_response_code(404);
echo json_encode(['success' => false, 'error' => "Unknown avatar action: $action"]);
