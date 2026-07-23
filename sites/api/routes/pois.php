<?php
/**
 * WebiArtisan API — Route : POI (claims owner + images)
 *
 * GET    /pois/claimable      — POI revendiquables de ma ville (artisan)
 * GET    /pois/my-claims      — mes claims + mes POI possédés (artisan)
 * POST   /pois/:id/claim      — revendiquer un POI (artisan)
 * POST   /pois/:id/image      — upload l'image (owner ou admin, multipart)
 * DELETE /pois/:id/image      — retirer l'image (owner ou admin)
 */

require_once __DIR__ . '/../lib/ArtisanAuth.php';
require_once __DIR__ . '/../lib/AppLogger.php';

const POI_IMAGE_MAX_BYTES = 5 * 1024 * 1024;
const POI_IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

switch ($method) {
    case 'GET':
        if ($action === 'claimable') {
            pois_claimable($pdo);
        } elseif ($action === 'my-claims') {
            pois_my_claims($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        if (filter_var($action, FILTER_VALIDATE_INT) !== false && $param === 'claim') {
            pois_claim($pdo, (int)$action);
        } elseif (filter_var($action, FILTER_VALIDATE_INT) !== false && $param === 'image') {
            pois_upload_image($pdo, (int)$action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'DELETE':
        if (filter_var($action, FILTER_VALIDATE_INT) !== false && $param === 'image') {
            pois_delete_image($pdo, (int)$action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function pois_claimable(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.type, p.address
        FROM local_pois p
        WHERE p.city_id = ? AND p.is_active = 1
          AND p.owner_artisan_id IS NULL
          AND NOT EXISTS (
              SELECT 1 FROM local_poi_claims c
              WHERE c.poi_id = p.id AND c.status = 'pending'
          )
        ORDER BY p.name
    ");
    $stmt->execute([(int)$artisan['city_id']]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function pois_my_claims(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);
    $artisanId = (int)$artisan['id'];

    $claimsStmt = $pdo->prepare("
        SELECT c.id, c.poi_id, c.status, c.created_at, p.name AS poi_name
        FROM local_poi_claims c
        JOIN local_pois p ON p.id = c.poi_id
        WHERE c.artisan_id = ?
        ORDER BY c.created_at DESC
        LIMIT 50
    ");
    $claimsStmt->execute([$artisanId]);

    $ownedStmt = $pdo->prepare("
        SELECT id, name, type, image_url
        FROM local_pois
        WHERE owner_artisan_id = ?
        ORDER BY name
    ");
    $ownedStmt->execute([$artisanId]);

    echo json_encode([
        'success' => true,
        'data' => [
            'claims' => $claimsStmt->fetchAll(PDO::FETCH_ASSOC),
            'owned'  => $ownedStmt->fetchAll(PDO::FETCH_ASSOC),
        ],
    ]);
}

function pois_claim(PDO $pdo, int $poiId): void
{
    $artisan = artisan_require_auth($pdo);
    $artisanId = (int)$artisan['id'];

    $stmt = $pdo->prepare("SELECT id, city_id, owner_artisan_id, is_active FROM local_pois WHERE id = ?");
    $stmt->execute([$poiId]);
    $poi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poi || !(bool)$poi['is_active']) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'not_found', 'message' => 'POI introuvable']);
        return;
    }
    if ((int)$poi['city_id'] !== (int)$artisan['city_id']) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'wrong_city', 'message' => 'Ce POI n\'est pas dans ta ville']);
        return;
    }
    if ($poi['owner_artisan_id'] !== null) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => (int)$poi['owner_artisan_id'] === $artisanId ? 'already_mine' : 'already_owned',
            'message' => 'Ce POI est déjà attribué',
        ]);
        return;
    }

    $pendingStmt = $pdo->prepare("SELECT artisan_id FROM local_poi_claims WHERE poi_id = ? AND status = 'pending'");
    $pendingStmt->execute([$poiId]);
    $pendingArtisan = $pendingStmt->fetchColumn();
    if ($pendingArtisan !== false) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => (int)$pendingArtisan === $artisanId ? 'already_pending' : 'already_owned',
            'message' => 'Une revendication est déjà en cours sur ce POI',
        ]);
        return;
    }

    $pdo->prepare("INSERT INTO local_poi_claims (poi_id, artisan_id) VALUES (?, ?)")
        ->execute([$poiId, $artisanId]);
    if (function_exists('app_log')) {
        app_log('info', '[POI] claim', ['poi_id' => $poiId, 'artisan_id' => $artisanId]);
    }
    http_response_code(201);
    echo json_encode(['success' => true, 'data' => ['id' => (int)$pdo->lastInsertId()]]);
}

function pois_require_owner_or_admin(PDO $pdo, int $poiId): array
{
    $artisan = artisan_require_auth($pdo);
    $stmt = $pdo->prepare("SELECT id, owner_artisan_id, image_url FROM local_pois WHERE id = ?");
    $stmt->execute([$poiId]);
    $poi = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$poi) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'POI introuvable']);
        exit;
    }
    $isOwner = (int)($poi['owner_artisan_id'] ?? 0) === (int)$artisan['id'];
    $isAdmin = !empty($artisan['is_admin']);
    if (!$isOwner && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Seul le propriétaire du POI (ou un admin) peut gérer son image']);
        exit;
    }
    return [$artisan, $poi];
}

function pois_upload_image(PDO $pdo, int $poiId): void
{
    [, $poi] = pois_require_owner_or_admin($pdo, $poiId);

    $file = $_FILES['image'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'missing_file', 'message' => 'Aucune image reçue']);
        return;
    }
    if ($file['size'] > POI_IMAGE_MAX_BYTES) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'too_large', 'message' => 'Image trop lourde (5 Mo max)']);
        return;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(POI_IMAGE_MIMES[$mime])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'bad_mime', 'message' => 'Formats acceptés : JPEG, PNG, WebP']);
        return;
    }

    $dir = __DIR__ . '/../uploads/pois';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        error_log('[POI] impossible de créer uploads/pois');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    $filename = sprintf('poi_%d_%s.%s', $poiId, bin2hex(random_bytes(6)), POI_IMAGE_MIMES[$mime]);
    if (!move_uploaded_file($file['tmp_name'], "$dir/$filename")) {
        error_log('[POI] move_uploaded_file a échoué');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    // Supprime l'ancienne image si différente
    if (!empty($poi['image_url'])) {
        $old = $dir . '/' . basename($poi['image_url']);
        if (is_file($old) && basename($old) !== $filename) {
            @unlink($old);
        }
    }

    $imageUrl = '/uploads/pois/' . $filename;
    $pdo->prepare("UPDATE local_pois SET image_url = ? WHERE id = ?")->execute([$imageUrl, $poiId]);

    if (function_exists('app_log')) {
        app_log('info', '[POI] image upload', ['poi_id' => $poiId]);
    }
    echo json_encode(['success' => true, 'data' => ['image_url' => $imageUrl]]);
}

function pois_delete_image(PDO $pdo, int $poiId): void
{
    [, $poi] = pois_require_owner_or_admin($pdo, $poiId);

    if (!empty($poi['image_url'])) {
        $path = __DIR__ . '/../uploads/pois/' . basename($poi['image_url']);
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $pdo->prepare("UPDATE local_pois SET image_url = NULL WHERE id = ?")->execute([$poiId]);
    echo json_encode(['success' => true, 'data' => ['deleted' => $poiId]]);
}
