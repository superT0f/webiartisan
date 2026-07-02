<?php
/**
 * WebIArtisan API — Route : Spin Wheel
 *
 * GET /spin/offers?city=livry
 * POST /spin
 * GET /spin/wins
 */

require_once __DIR__ . '/../lib/UserAuth.php';
require_once __DIR__ . '/../lib/Gamification.php';

switch ($method) {
    case 'GET':
        if ($action === 'offers' || $action === '') {
            spin_offers_list($pdo);
        } elseif ($action === 'wins') {
            spin_wins_list($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        if ($action === '' || $action === 'spin') {
            spin_play($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

/**
 * GET /spin/offers?city=livry
 */
function spin_offers_list(PDO $pdo): void
{
    $citySlug = $_GET['city'] ?? '';
    if (!$citySlug) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ville requise']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT o.id, o.artisan_id, o.label, o.description, o.stock_remaining,
               a.company_name AS artisan_name, c.slug AS city_slug
        FROM local_spin_offers o
        JOIN local_artisans a ON a.id = o.artisan_id
        JOIN local_cities c   ON c.id = a.city_id
        WHERE c.slug = ?
          AND o.is_active = 1
          AND o.stock_remaining > 0
        ORDER BY o.created_at ASC
    ");
    $stmt->execute([$citySlug]);
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($offers as &$o) {
        $o['stock_remaining'] = (int)$o['stock_remaining'];
    }

    echo json_encode(['success' => true, 'data' => $offers]);
}

/**
 * POST /spin
 */
function spin_play(PDO $pdo): void
{
    $user = user_require_auth($pdo);

    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $citySlug = $body['city_slug'] ?? '';

    if (!$citySlug) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ville requise']);
        return;
    }

    $cityStmt = $pdo->prepare("
        SELECT id, name
        FROM local_cities
        WHERE slug = ? AND is_active = 1
    ");
    $cityStmt->execute([$citySlug]);
    $city = $cityStmt->fetch(PDO::FETCH_ASSOC);

    if (!$city) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ville inconnue']);
        return;
    }

    $today = date('Y-m-d');

    // Offres actives avec stock
    $stmt = $pdo->prepare("
        SELECT o.id, o.artisan_id, o.label, o.description, o.stock_remaining
        FROM local_spin_offers o
        JOIN local_artisans a ON a.id = o.artisan_id
        WHERE a.city_id = ?
          AND o.is_active = 1
          AND o.stock_remaining > 0
        ORDER BY o.stock_remaining DESC, o.created_at ASC
    ");
    $stmt->execute([$city['id']]);
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($offers)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Aucune offre disponible']);
        return;
    }

    // Pondération par stock restant
    $totalWeight = array_sum(array_column($offers, 'stock_remaining'));
    $rand = mt_rand(1, $totalWeight);
    $chosen = null;
    $cumul = 0;

    foreach ($offers as $offer) {
        $cumul += (int)$offer['stock_remaining'];
        if ($rand <= $cumul) {
            $chosen = $offer;
            break;
        }
    }

    if (!$chosen) {
        $chosen = $offers[0];
    }

    $code = 'LIV-' . strtoupper(bin2hex(random_bytes(4)));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

    $pdo->beginTransaction();

    try {
        // Atomically enforce 1 spin/day. The unique key serializes concurrent
        // requests; the post-update count check rejects double-spins.
        $pdo->prepare("
            INSERT INTO local_spin_daily_limits (user_id, city_id, spin_date, count)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE count = count + 1
        ")->execute([$user['id'], $city['id'], $today]);

        $limitStmt = $pdo->prepare("
            SELECT count
            FROM local_spin_daily_limits
            WHERE user_id = ? AND city_id = ? AND spin_date = ?
        ");
        $limitStmt->execute([$user['id'], $city['id'], $today]);
        $spinCount = (int)$limitStmt->fetchColumn();

        if ($spinCount > 1) {
            throw new Exception('daily_limit_exceeded');
        }

        $upd = $pdo->prepare("
            UPDATE local_spin_offers
            SET stock_remaining = stock_remaining - 1
            WHERE id = ? AND stock_remaining > 0
        ");
        $upd->execute([$chosen['id']]);

        if ($upd->rowCount() === 0) {
            throw new Exception('stock_depleted');
        }

        $winStmt = $pdo->prepare("
            INSERT INTO local_spin_wins
                (user_id, offer_id, artisan_id, code, status, spin_date, expires_at)
            VALUES (?, ?, ?, ?, 'pending', ?, ?)
        ");
        $winStmt->execute([
            $user['id'],
            $chosen['id'],
            $chosen['artisan_id'],
            $code,
            $today,
            $expiresAt,
        ]);

        $pdo->commit();

    gamificationRecordAction($pdo, (int)$user['id'], 'game_play', "city:{$city['id']}", ['city_id' => $city['id']]);
    } catch (Exception $e) {
        $pdo->rollBack();
        if ($e->getMessage() === 'daily_limit_exceeded') {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Vous avez déjà tourné la roue aujourd\'hui']);
            return;
        }
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Impossible d\'enregistrer le gain']);
        return;
    }

    $artisanStmt = $pdo->prepare("SELECT company_name FROM local_artisans WHERE id = ?");
    $artisanStmt->execute([$chosen['artisan_id']]);
    $artisanName = $artisanStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data'    => [
            'offer_id'      => (int)$chosen['id'],
            'label'         => $chosen['label'],
            'description'   => $chosen['description'],
            'artisan_id'    => (int)$chosen['artisan_id'],
            'artisan_name'  => $artisanName,
            'code'          => $code,
            'expires_at'    => $expiresAt,
        ],
    ]);
}

/**
 * GET /spin/wins
 */
function spin_wins_list(PDO $pdo): void
{
    $user = user_require_auth($pdo);

    $stmt = $pdo->prepare("
        SELECT w.id, w.code, w.status, w.spin_date, w.claimed_at, w.expires_at,
               o.label, o.description, a.company_name AS artisan_name
        FROM local_spin_wins w
        JOIN local_spin_offers o ON o.id = w.offer_id
        JOIN local_artisans a    ON a.id = w.artisan_id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $wins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $wins]);
}
