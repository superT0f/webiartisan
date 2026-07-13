<?php
/**
 * WebIArtisan API — Route : Artisans
 * Endpoints publics et privés pour les artisans locaux
 *
 * GET  /artisans                   — Liste publique (filtres: city, category, search)
 * GET  /artisans/{id}              — Fiche publique d'un artisan
 * GET  /artisans/{id}/services     — Services d'un artisan
 * GET  /artisans/{id}/reviews      — Avis approuvés d'un artisan
 * POST /artisans/register          — Inscription artisan (public)
 * POST /artisans/{id}/contact      — Envoyer un message à l'artisan (public)
 * POST /artisans/{id}/review       — Déposer un avis (public, modéré)
 * PUT  /artisans/{id}              — Mise à jour profil (artisan authentifié)
 * POST /artisans/login             — Connexion artisan (email + password)
 * POST /artisans/magic-link        — Connexion par lien magique
 */

require_once __DIR__ . '/../lib/Mailer.php';
require_once __DIR__ . '/../lib/Games.php';
require_once __DIR__ . '/../lib/Gamification.php';
require_once __DIR__ . '/../lib/ArtisanAuth.php';
require_once __DIR__ . '/../lib/UserAuth.php';

switch ($method) {

    case 'GET':
        if ($action === '' || $action === 'list') {
            artisan_list($pdo);
        } elseif ($action === 'me' && $param === 'prospects') {
            artisan_my_prospects($pdo);
        } elseif ($action === 'me' && $param === 'admin-recipes') {
            artisan_admin_recipes($pdo);
        } elseif ($action === 'me' && $param === 'spin-offers') {
            artisan_my_spin_offers($pdo);
        } elseif ($action === 'me' && $param === 'spin-wins') {
            artisan_my_spin_wins($pdo);
        } elseif ($action === 'me' && $param === 'games') {
            artisan_games_list($pdo);
        } elseif ($action === 'me' && $param === 'services') {
            artisan_my_services($pdo);
        } elseif ($action === 'me') {
            artisan_me($pdo);
        } elseif (is_numeric($action) && !$param) {
            artisan_get($pdo, (int)$action);
        } elseif (is_numeric($action) && $param === 'services') {
            artisan_services($pdo, (int)$action);
        } elseif (is_numeric($action) && $param === 'reviews') {
            artisan_reviews($pdo, (int)$action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if ($action === 'register') {
            artisan_register($pdo, $body);
        } elseif ($action === 'login') {
            artisan_login($pdo, $body);
        } elseif ($action === 'magic-link') {
            artisan_magic_link($pdo, $body);
        } elseif ($action === 'logout') {
            artisan_logout($pdo);
        } elseif (is_numeric($action) && $param === 'contact') {
            artisan_contact($pdo, (int)$action, $body);
        } elseif (is_numeric($action) && $param === 'review') {
            artisan_add_review($pdo, (int)$action, $body);
        } elseif ($action === 'me' && $param === 'services') {
            artisan_create_service($pdo, $body);
        } elseif ($action === 'me' && $param === 'prospects' && is_numeric($segments[3] ?? '')) {
            artisan_follow_prospect($pdo, (int)$segments[3], $body);
        } elseif ($action === 'me' && $param === 'spin-offers') {
            artisan_create_spin_offer($pdo, $body);
        } elseif ($action === 'me' && $param === 'games') {
            artisan_create_game($pdo, $body);
        } elseif ($action === 'me' && $param === 'spin-wins' && !empty($segments[3]) && ($segments[4] ?? '') === 'validate') {
            artisan_validate_spin_win($pdo, $segments[3]);
        } elseif ($action === 'me' && $param === 'consumer-token') {
            artisan_consumer_token($pdo);
        } elseif ($action === 'me' && $param === 'change-password') {
            artisan_change_password($pdo, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'DELETE':
        if ($action === 'me' && $param === 'prospects' && is_numeric($segments[3] ?? '')) {
            artisan_unfollow_prospect($pdo, (int)$segments[3]);
        } elseif ($action === 'me' && $param === 'spin-offers' && is_numeric($segments[3] ?? '')) {
            artisan_delete_spin_offer($pdo, (int)$segments[3]);
        } elseif ($action === 'me' && $param === 'games' && filter_var($segments[3] ?? '', FILTER_VALIDATE_INT) !== false) {
            artisan_delete_game($pdo, (int)$segments[3]);
        } elseif ($action === 'me' && $param === 'services' && filter_var($segments[3] ?? '', FILTER_VALIDATE_INT) !== false) {
            artisan_delete_service($pdo, (int)$segments[3]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'PUT':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($action === 'me' && $param === 'admin-recipes' && is_numeric($segments[3] ?? '')) {
            artisan_archive_recipe($pdo, (int)$segments[3]);
        } elseif ($action === 'me' && $param === 'services' && filter_var($segments[3] ?? '', FILTER_VALIDATE_INT) !== false) {
            artisan_update_service($pdo, (int)$segments[3], $body);
        } elseif ($action === 'me' && $param === 'spin-offers' && is_numeric($segments[3] ?? '')) {
            artisan_update_spin_offer($pdo, (int)$segments[3], $body);
        } elseif ($action === 'me' && $param === 'games' && filter_var($segments[3] ?? '', FILTER_VALIDATE_INT) !== false) {
            artisan_update_game($pdo, (int)$segments[3], $body);
        } elseif ($action === 'me') {
            artisan_update_me($pdo, $body);
        } elseif (is_numeric($action)) {
            artisan_update($pdo, (int)$action, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

// ===================================================================
// Fonctions
// ===================================================================

/**
 * Récupère ou crée le compte consommateur lié à un artisan.
 * Met à jour local_artisans.user_id si nécessaire.
 * Retourne l'ID utilisateur ou null en cas d'erreur.
 */
function artisan_ensure_user(PDO $pdo, int $artisanId, string $email, string $displayName): ?int
{
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $email = strtolower($email);

    // Utilisateur déjà lié ?
    $stmt = $pdo->prepare("SELECT user_id FROM local_artisans WHERE id = ?");
    $stmt->execute([$artisanId]);
    $existingUserId = $stmt->fetchColumn();

    if ($existingUserId) {
        return (int)$existingUserId;
    }

    // Rechercher un utilisateur existant avec le même email
    $stmt = $pdo->prepare("SELECT id FROM local_users WHERE email = ?");
    $stmt->execute([$email]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        $insert = $pdo->prepare("INSERT INTO local_users (email, display_name) VALUES (?, ?)");
        $insert->execute([$email, $displayName]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $userId = (int)$userId;
    }

    // Lier l'artisan
    $pdo->prepare("UPDATE local_artisans SET user_id = ? WHERE id = ?")
        ->execute([$userId, $artisanId]);

    return $userId;
}

/**
 * GET /artisans — Liste publique
 * Paramètres: city (slug), category (slug), search, featured, limit, offset
 */
function artisan_list(PDO $pdo): void
{
    $city     = $_GET['city']     ?? null;
    $category = $_GET['category'] ?? null;
    $search   = $_GET['search']   ?? null;
    $featured = isset($_GET['featured']) ? filter_var($_GET['featured'], FILTER_VALIDATE_BOOLEAN) : null;
    $limit    = min((int)($_GET['limit']  ?? 20), 100);
    $offset   = max((int)($_GET['offset'] ?? 0), 0);

    $sql = "
        SELECT
            a.id, a.company_name, a.description,
            a.phone, a.email, a.website, a.address,
            a.latitude, a.longitude,
            a.logo_url, a.cover_url,
            a.is_verified, a.is_featured, a.view_count,
            a.created_at,
            c.slug AS city_slug, c.name AS city_name, c.postal_code,
            cat.slug AS category_slug, cat.name AS category_name,
            cat.icon AS category_icon, cat.color AS category_color,
            COALESCE(ROUND(AVG(r.rating), 1), 0) AS rating_avg,
            COUNT(DISTINCT r.id)                  AS rating_count
        FROM local_artisans a
        JOIN local_cities c    ON a.city_id     = c.id
        LEFT JOIN local_categories cat ON a.category_id = cat.id
        LEFT JOIN local_reviews r      ON r.artisan_id  = a.id AND r.is_approved = 1
        WHERE a.status = 'active' AND c.is_active = 1
    ";
    $params = [];

    if ($city) {
        $sql .= " AND c.slug = ?";
        $params[] = $city;
    }
    if ($category) {
        $sql .= " AND cat.slug = ?";
        $params[] = $category;
    }
    if ($search) {
        $sql .= " AND (a.company_name LIKE ? OR a.description LIKE ? OR cat.name LIKE ?)";
        $s = "%{$search}%";
        $params[] = $s; $params[] = $s; $params[] = $s;
    }
    if ($featured !== null) {
        $sql .= " AND a.is_featured = ?";
        $params[] = $featured ? 1 : 0;
    }

    $sql .= "
        GROUP BY a.id
        ORDER BY a.is_featured DESC, rating_avg DESC, a.company_name ASC
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $artisans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $artisanIds = array_map(fn($a) => (int)$a['id'], $artisans);
    $couponArtisans = [];
    $wheelArtisans = [];
    if ($artisanIds) {
        $in = implode(',', array_fill(0, count($artisanIds), '?'));

        $couponStmt = $pdo->prepare("
            SELECT DISTINCT i.artisan_id
            FROM local_game_instances i
            JOIN local_game_types gt ON gt.id = i.game_type_id
            WHERE i.artisan_id IN ($in)
              AND i.is_active = 1 AND gt.is_active = 1
              AND (i.starts_at IS NULL OR i.starts_at <= NOW())
              AND (i.ends_at IS NULL OR i.ends_at >= NOW())
        ");
        $couponStmt->execute($artisanIds);
        $couponArtisans = array_map('intval', $couponStmt->fetchAll(PDO::FETCH_COLUMN));

        $wheelStmt = $pdo->prepare("
            SELECT DISTINCT so.artisan_id
            FROM local_spin_offers so
            JOIN local_artisans a2 ON a2.id = so.artisan_id
            WHERE so.artisan_id IN ($in)
              AND a2.plan = 'premium'
              AND so.is_active = 1 AND so.stock_remaining > 0
        ");
        $wheelStmt->execute($artisanIds);
        $wheelArtisans = array_map('intval', $wheelStmt->fetchAll(PDO::FETCH_COLUMN));
    }

    foreach ($artisans as &$a) {
        $a['is_featured']  = (bool)$a['is_featured'];
        $a['is_verified']  = (bool)$a['is_verified'];
        $a['rating_avg']   = (float)$a['rating_avg'];
        $a['rating_count'] = (int)$a['rating_count'];
        $a['view_count']   = (int)$a['view_count'];
        $a['has_coupon']   = in_array((int)$a['id'], $couponArtisans, true);
        $a['has_wheel']    = in_array((int)$a['id'], $wheelArtisans, true);
        $a['has_active_game'] = $a['has_coupon'] || $a['has_wheel'];
    }

    echo json_encode([
        'success' => true,
        'data'    => $artisans,
        'total'   => count($artisans),
        'limit'   => $limit,
        'offset'  => $offset,
    ]);
}

/**
 * GET /artisans/{id} — Fiche complète d'un artisan
 */
function artisan_get(PDO $pdo, int $id): void
{
    // Incrémenter le compteur de vues
    $pdo->prepare("UPDATE local_artisans SET view_count = view_count + 1 WHERE id = ?")
        ->execute([$id]);

    $stmt = $pdo->prepare("
        SELECT
            a.*,
            c.slug AS city_slug, c.name AS city_name, c.postal_code,
            cat.slug AS category_slug, cat.name AS category_name,
            cat.icon AS category_icon, cat.color AS category_color,
            COALESCE(ROUND(AVG(r.rating), 1), 0) AS rating_avg,
            COUNT(DISTINCT r.id)                  AS rating_count
        FROM local_artisans a
        JOIN local_cities c    ON a.city_id     = c.id
        LEFT JOIN local_categories cat ON a.category_id = cat.id
        LEFT JOIN local_reviews r      ON r.artisan_id  = a.id AND r.is_approved = 1
        WHERE a.id = ? AND a.status = 'active'
        GROUP BY a.id
    ");
    $stmt->execute([$id]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$artisan) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Artisan non trouvé']);
        return;
    }

    // Retirer les champs sensibles
    unset(
        $artisan['password_hash'],
        $artisan['auth_token'],
        $artisan['auth_token_exp'],
        $artisan['auth_token_hash'],
        $artisan['auth_token_lookup'],
        $artisan['stripe_customer_id'],
        $artisan['stripe_subscription_id'],
        $artisan['user_id']
    );

    $artisan['is_featured']  = (bool)$artisan['is_featured'];
    $artisan['is_verified']  = (bool)$artisan['is_verified'];
    $artisan['email_verified'] = (bool)$artisan['email_verified'];
    $artisan['rating_avg']   = (float)$artisan['rating_avg'];
    $artisan['rating_count'] = (int)$artisan['rating_count'];

    // Services
    $stmt2 = $pdo->prepare("
        SELECT
            s.id,
            s.name,
            s.description,
            s.price_range,
            s.duration,
            s.is_custom,
            s.is_active,
            s.sort_order,
            s.service_catalog_id,
            sc.`key` AS catalog_key,
            sc.label_fr AS catalog_label,
            sc.icon AS catalog_icon
        FROM local_services s
        LEFT JOIN local_service_catalog sc ON sc.id = s.service_catalog_id
        WHERE s.artisan_id = ? AND s.is_active = 1
        ORDER BY s.sort_order ASC, s.id ASC
    ");
    $stmt2->execute([$id]);
    $artisan['services'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($artisan['services'] as &$svc) {
        $svc['id'] = (int)$svc['id'];
        $svc['service_catalog_id'] = $svc['service_catalog_id'] !== null ? (int)$svc['service_catalog_id'] : null;
        $svc['is_custom'] = (bool)$svc['is_custom'];
        $svc['is_active'] = (bool)$svc['is_active'];
        $svc['sort_order'] = (int)$svc['sort_order'];
    }

    $artisan['recipes'] = artisan_recipes($pdo, $artisan['id'], $artisan['email'] ?? '');
    $artisan['nearby'] = artisan_nearby(
        $pdo,
        (float)$artisan['latitude'],
        (float)$artisan['longitude'],
        (int)$artisan['city_id'],
        (int)$artisan['id']
    );

    echo json_encode(['success' => true, 'data' => $artisan]);
}

function artisan_recipes(PDO $pdo, int $artisanId, string $artisanEmail): array
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            r.id, r.title, r.slug, r.description, r.image_url,
            r.servings, r.prep_time_minutes, r.cook_time_minutes,
            r.submitted_by, r.submitter_email, r.created_at,
            (ra.artisan_id IS NOT NULL) AS is_product_recipe
        FROM local_recipes r
        LEFT JOIN local_recipe_artisans ra ON ra.recipe_id = r.id AND ra.artisan_id = ?
        WHERE r.status = 'published'
          AND (ra.artisan_id IS NOT NULL OR r.submitter_email = ?)
        ORDER BY r.created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$artisanId, $artisanEmail]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function artisan_nearby(PDO $pdo, float $lat, float $lng, int $cityId, int $artisanId): array
{
    $sql = "
        SELECT 'prospect' AS kind, id, name, type, address, latitude, longitude,
               (6371000 * acos(
                   cos(radians(?)) * cos(radians(latitude)) *
                   cos(radians(longitude) - radians(?)) +
                   sin(radians(?)) * sin(radians(latitude))
               )) AS distance_meters
        FROM local_prospects
        WHERE city_id = ? AND is_active = 1
        HAVING distance_meters <= 2000

        UNION ALL

        SELECT 'poi' AS kind, id, name, type, address, latitude, longitude,
               (6371000 * acos(
                   cos(radians(?)) * cos(radians(latitude)) *
                   cos(radians(longitude) - radians(?)) +
                   sin(radians(?)) * sin(radians(latitude))
               )) AS distance_meters
        FROM local_pois
        WHERE city_id = ? AND is_active = 1
        HAVING distance_meters <= 2000

        ORDER BY distance_meters ASC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $lat, $lng, $lat,
        $cityId,
        $lat, $lng, $lat,
        $cityId,
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * GET /artisans/{id}/services
 */
function artisan_services(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.name,
            s.description,
            s.price_range,
            s.duration,
            s.is_custom,
            s.is_active,
            s.sort_order,
            s.service_catalog_id,
            sc.`key` AS catalog_key,
            sc.label_fr AS catalog_label,
            sc.icon AS catalog_icon
        FROM local_services s
        LEFT JOIN local_service_catalog sc ON sc.id = s.service_catalog_id
        WHERE s.artisan_id = ? AND s.is_active = 1
        ORDER BY s.sort_order ASC, s.id ASC
    ");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['service_catalog_id'] = $item['service_catalog_id'] !== null ? (int)$item['service_catalog_id'] : null;
        $item['is_custom'] = (bool)$item['is_custom'];
        $item['is_active'] = (bool)$item['is_active'];
        $item['sort_order'] = (int)$item['sort_order'];
    }

    echo json_encode(['success' => true, 'data' => $items]);
}

/**
 * GET /artisans/{id}/reviews
 */
function artisan_reviews(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("
        SELECT id, reviewer_name, rating, comment, created_at
        FROM local_reviews
        WHERE artisan_id = ? AND is_approved = 1
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reviews as &$r) {
        $r['rating'] = (int)$r['rating'];
    }

    $avg = count($reviews) > 0
        ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1)
        : 0;

    echo json_encode([
        'success'    => true,
        'data'       => $reviews,
        'total'      => count($reviews),
        'rating_avg' => $avg,
    ]);
}

/**
 * POST /artisans/register — Inscription artisan
 */
function artisan_register(PDO $pdo, array $body): void
{
    // Validation
    $required = ['company_name', 'city_slug', 'category_slug', 'email', 'phone'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Champ obligatoire manquant : {$field}"]);
            return;
        }
    }

    $email = strtolower(trim($body['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email invalide']);
        return;
    }

    // Vérifier email déjà existant
    $check = $pdo->prepare("SELECT id FROM local_artisans WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Un compte existe déjà avec cet email']);
        return;
    }

    // Récupérer city_id
    $cityStmt = $pdo->prepare("SELECT id FROM local_cities WHERE slug = ? AND is_active = 1");
    $cityStmt->execute([$body['city_slug']]);
    $city = $cityStmt->fetch();
    if (!$city) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ville inconnue']);
        return;
    }

    // Récupérer category_id
    $catStmt = $pdo->prepare("SELECT id FROM local_categories WHERE slug = ?");
    $catStmt->execute([$body['category_slug']]);
    $cat = $catStmt->fetch();

    // Mot de passe optionnel
    $passwordHash = null;
    if (!empty($body['password'])) {
        if (strlen($body['password']) < 8) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Mot de passe trop court (min 8 caractères)']);
            return;
        }
        $passwordHash = password_hash($body['password'], PASSWORD_BCRYPT);
    }

    // Insérer
    $stmt = $pdo->prepare("
        INSERT INTO local_artisans
            (city_id, category_id, company_name, description,
             phone, email, website, address,
             password_hash, status, email_verified)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0)
    ");
    $stmt->execute([
        $city['id'],
        $cat ? $cat['id'] : null,
        trim($body['company_name']),
        trim($body['description'] ?? ''),
        trim($body['phone']),
        $email,
        trim($body['website'] ?? ''),
        trim($body['address'] ?? ''),
        $passwordHash,
    ]);
    $artisanId = (int)$pdo->lastInsertId();

    // Créer automatiquement le compte consommateur lié
    artisan_ensure_user($pdo, $artisanId, $email, trim($body['company_name']));

    // Services optionnels
    if (!empty($body['services']) && is_array($body['services'])) {
        $svcStmt = $pdo->prepare("
            INSERT INTO local_services (artisan_id, name, description, price_range, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach (array_slice($body['services'], 0, 10) as $i => $svc) {
            if (!empty($svc['name'])) {
                $svcStmt->execute([
                    $artisanId,
                    trim($svc['name']),
                    trim($svc['description'] ?? ''),
                    trim($svc['price_range'] ?? ''),
                    $i,
                ]);
            }
        }
    }

    // TODO: Envoyer email de confirmation

    echo json_encode([
        'success' => true,
        'message' => 'Inscription reçue ! Votre profil sera validé sous 24h.',
        'data'    => ['id' => $artisanId, 'status' => 'pending'],
    ]);
}

/**
 * POST /artisans/login
 */
function artisan_login(PDO $pdo, array $body): void
{
    $email    = strtolower(trim($body['email'] ?? ''));
    $password = $body['password'] ?? '';

    error_log("[AUTH-LOGIN] email=$email provided=" . ($email && $password ? 'yes' : 'no'));

    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email et mot de passe requis']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT a.id, a.company_name, a.password_hash, a.status, a.email_verified,
               c.slug AS city_slug
        FROM local_artisans a
        JOIN local_cities c ON a.city_id = c.id
        WHERE a.email = ?
    ");
    $stmt->execute([$email]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

    $found = $artisan ? 'yes' : 'no';
    $hashPresent = !empty($artisan['password_hash']) ? 'yes' : 'no';
    $passwordOk = $artisan && !empty($artisan['password_hash']) && password_verify($password, $artisan['password_hash']);
    error_log("[AUTH-LOGIN] email=$email found=$found hash_present=$hashPresent password_ok=" . ($passwordOk ? 'yes' : 'no') . " status=" . ($artisan['status'] ?? 'n/a'));

    if (!$artisan || !password_verify($password, $artisan['password_hash'] ?? '')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Email ou mot de passe incorrect']);
        return;
    }

    if ($artisan['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Compte non actif. En attente de validation.']);
        return;
    }

    $rememberMe = !empty($body['rememberMe']);

    // Générer un token simple (JWT serait mieux — à améliorer)
    $token = bin2hex(random_bytes(32));
    $tokenHash = password_hash($token, PASSWORD_DEFAULT);
    $tokenLookup = hash('sha256', $token);
    $exp   = date('Y-m-d H:i:s', $rememberMe ? strtotime('+365 days') : strtotime('+30 days'));

    $pdo->prepare("UPDATE local_artisans SET auth_token_hash = ?, auth_token_lookup = ?, auth_token_exp = ?, auth_token = NULL WHERE id = ?")
        ->execute([$tokenHash, $tokenLookup, $exp, $artisan['id']]);

    // Garantir un compte consommateur lié et créer sa session
    $userId = artisan_ensure_user($pdo, (int)$artisan['id'], $email, $artisan['company_name']);
    $userToken = $userId ? user_create_session($pdo, $userId, $rememberMe) : null;

    echo json_encode([
        'success' => true,
        'token'   => $token,
        'userToken' => $userToken,
        'data'    => [
            'id'           => $artisan['id'],
            'company_name' => $artisan['company_name'],
            'city_slug'    => $artisan['city_slug'],
        ],
    ]);
}

/**
 * POST /artisans/magic-link — Envoyer lien magique par email
 */
function artisan_magic_link(PDO $pdo, array $body): void
{
    $email = strtolower(trim($body['email'] ?? ''));
    $rememberMe = !empty($body['rememberMe']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email invalide']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id, company_name, status FROM local_artisans WHERE email = ?");
    $stmt->execute([$email]);
    $artisan = $stmt->fetch();

    // Toujours répondre OK pour ne pas exposer les emails
    if (!$artisan || $artisan['status'] !== 'active') {
        error_log(sprintf(
            "[MAGIC-LINK] email=%s found=%s status=%s rememberMe=%s reason=no_active_artisan",
            $email,
            $artisan ? 'yes' : 'no',
            $artisan['status'] ?? 'n/a',
            $rememberMe ? '1' : '0'
        ));
        echo json_encode(['success' => true, 'data' => ['message' => 'Si votre email est valide, vous recevrez un lien de connexion.']]);
        return;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = password_hash($token, PASSWORD_DEFAULT);
    $tokenLookup = hash('sha256', $token);
    $exp   = date('Y-m-d H:i:s', $rememberMe ? strtotime('+365 days') : strtotime('+1 hour'));

    $pdo->prepare("UPDATE local_artisans SET auth_token_hash = ?, auth_token_lookup = ?, auth_token_exp = ?, auth_token = NULL WHERE id = ?")
        ->execute([$tokenHash, $tokenLookup, $exp, $artisan['id']]);

    $portalUrl = artisan_portal_url();
    $link      = rtrim($portalUrl, '/') . '/espace?token=' . urlencode($token) . ($rememberMe ? '&rememberMe=1' : '');

    $config    = getAppConfig();
    $fromEmail = $config['mail_from'] ?? 'noreply@webiartisan.prigent.tech';

    $safeCompany = htmlspecialchars($artisan['company_name'], ENT_QUOTES, 'UTF-8');
    $safeLink    = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

    $subject = 'Votre lien de connexion WebIArtisan';
    $expiryText = $rememberMe ? '365 jours' : '1 heure';
    $html    = <<<HTML
<!DOCTYPE html>
<html><body style="font-family: -apple-system, sans-serif; max-width: 480px; margin: 0 auto; padding: 20px;">
  <h2 style="color: #1a1a2e;">Bonjour {$safeCompany},</h2>
  <p>Voici votre lien de connexion sécurisé à votre espace artisan :</p>
  <div style="text-align: center; margin: 24px 0;">
    <a href="{$safeLink}" style="display: inline-block; background: #1a1a2e; color: #fff; padding: 14px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;">Me connecter</a>
  </div>
  <p style="color: #888; font-size: 13px;">Ce lien est valable {$expiryText}. Si vous n'avez pas demandé ce lien, ignorez cet email.</p>
  <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
  <p style="color: #aaa; font-size: 12px;">WebIArtisan</p>
</body></html>
HTML;

    $queued = queueEmail(
        $email,
        $subject,
        $html,
        $fromEmail,
        'WebIArtisan',
        null,
        ['type' => 'artisan_magic_link', 'artisan_id' => (int)$artisan['id']]
    );

    $redactedLink = preg_replace('/token=[^&]+/', 'token=REDACTED', $link);
    $tokenFingerprint = substr(hash('sha256', $token), 0, 16);
    error_log(sprintf(
        "[MAGIC-LINK] email=%s artisan_id=%s status=%s rememberMe=%s exp=%s origin=%s portalUrl=%s from=%s queued=%s token_fp=%s link=%s",
        $email,
        $artisan['id'],
        $artisan['status'],
        $rememberMe ? '1' : '0',
        $exp,
        $_SERVER['HTTP_ORIGIN'] ?? 'none',
        $portalUrl,
        $fromEmail,
        $queued ? '1' : '0',
        $tokenFingerprint,
        $redactedLink
    ));

    echo json_encode(['success' => true, 'data' => ['message' => 'Lien de connexion envoyé par email.']]);
}

/**
 * POST /artisans/me/consumer-token — Récupère ou crée un compte visiteur lié à l'artisan.
 */
function artisan_consumer_token(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);

    $stmt = $pdo->prepare("SELECT email, company_name FROM local_artisans WHERE id = ?");
    $stmt->execute([$artisan['id']]);
    $artisanData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$artisanData) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Artisan non trouvé']);
        return;
    }

    $email = strtolower($artisanData['email']);

    $userStmt = $pdo->prepare("SELECT id FROM local_users WHERE email = ?");
    $userStmt->execute([$email]);
    $userId = $userStmt->fetchColumn();

    if (!$userId) {
        $insert = $pdo->prepare("INSERT INTO local_users (email, display_name) VALUES (?, ?)");
        $insert->execute([$email, $artisanData['company_name']]);
        $userId = (int)$pdo->lastInsertId();
    }

    $sessionToken = user_create_session($pdo, $userId, true);

    echo json_encode([
        'success' => true,
        'data'    => ['id' => (int)$userId, 'email' => $email, 'token' => $sessionToken],
    ]);
}

function artisan_logout(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);
    $pdo->prepare("UPDATE local_artisans SET auth_token_hash = NULL, auth_token_lookup = NULL, auth_token = NULL, auth_token_exp = NULL WHERE id = ?")
        ->execute([$artisan['id']]);
    echo json_encode(['success' => true, 'data' => ['message' => 'Déconnecté']]);
}

/**
 * POST /artisans/me/change-password — Change le mot de passe artisan
 */
function artisan_change_password(PDO $pdo, array $body): void
{
    $artisan = artisan_require_auth($pdo);

    $current = $body['current_password'] ?? '';
    $new     = $body['new_password'] ?? '';
    $confirm = $body['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tous les champs sont requis']);
        return;
    }

    if ($new !== $confirm) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Les nouveaux mots de passe ne correspondent pas']);
        return;
    }

    if (strlen($new) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Le nouveau mot de passe doit faire au moins 8 caractères']);
        return;
    }

    $stmt = $pdo->prepare("SELECT password_hash FROM local_artisans WHERE id = ?");
    $stmt->execute([$artisan['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['password_hash'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucun mot de passe défini. Utilisez la connexion par lien magique.']);
        return;
    }

    if (!password_verify($current, $row['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Mot de passe actuel incorrect']);
        return;
    }

    $hash = password_hash($new, PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE local_artisans SET password_hash = ? WHERE id = ?")
        ->execute([$hash, $artisan['id']]);

    echo json_encode(['success' => true, 'message' => 'Mot de passe mis à jour']);
}

/**
 * Détermine l'URL du portail artisan à partir de l'origine de la requête.
 */
function artisan_portal_url(): string {
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
    if ($origin && in_array($origin, $allowedOrigins, true)) {
        return $origin;
    }

    return 'https://artisans-livry.prigent.tech';
}

/**
 * POST /artisans/{id}/contact
 */
function artisan_contact(PDO $pdo, int $id, array $body): void
{
    $name    = trim($body['name']    ?? '');
    $email   = trim($body['email']   ?? '');
    $message = trim($body['message'] ?? '');

    if (!$name || !$email || !$message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nom, email et message requis']);
        return;
    }

    // Récupérer l'artisan
    $stmt = $pdo->prepare("SELECT email, company_name FROM local_artisans WHERE id = ? AND status = 'active'");
    $stmt->execute([$id]);
    $artisan = $stmt->fetch();

    if (!$artisan) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Artisan non trouvé']);
        return;
    }

    // Incrémenter le compteur de contacts
    $pdo->prepare("UPDATE local_artisans SET contact_count = contact_count + 1 WHERE id = ?")
        ->execute([$id]);

    // Envoyer l'email à l'artisan
    $plainCompany = $artisan['company_name'];
    $safeCompany  = htmlspecialchars($artisan['company_name'], ENT_QUOTES, 'UTF-8');
    $safeName     = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeEmail    = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safeMessage  = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

    $contactSubject = "Nouveau message depuis WebIArtisan — {$plainCompany}";
    $contactHtml    = <<<HTML
<!DOCTYPE html>
<html><body style="font-family: -apple-system, sans-serif; max-width: 520px; margin: 0 auto; padding: 20px;">
  <h2 style="color: #1a1a2e;">Nouveau message pour {$safeCompany}</h2>
  <p><strong>De :</strong> {$safeName} &lt;{$safeEmail}&gt;</p>
  <p><strong>Message :</strong></p>
  <div style="background: #f8f8f8; padding: 16px; border-radius: 8px; border-left: 4px solid #1a1a2e;">
    {$safeMessage}
  </div>
  <p style="margin-top: 24px; color: #888; font-size: 13px;">Répondez directement à cet email pour contacter {$safeName}.</p>
  <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
  <p style="color: #aaa; font-size: 12px;">WebIArtisan</p>
</body></html>
HTML;

    $queued = queueEmail(
        $artisan['email'],
        $contactSubject,
        $contactHtml,
        null,
        'WebIArtisan',
        $email,
        ['type' => 'artisan_contact', 'artisan_id' => $id, 'from_email' => $email]
    );

    if (!$queued) {
        error_log("[CONTACT] Échec mise en file email à {$artisan['email']} depuis {$email}");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Votre message a été envoyé à ' . $artisan['company_name'],
    ]);
}

/**
 * POST /artisans/{id}/review — Déposer un avis
 */
function artisan_add_review(PDO $pdo, int $id, array $body): void
{
    $name    = trim($body['reviewer_name'] ?? '');
    $email   = trim($body['reviewer_email'] ?? '');
    $rating  = (int)($body['rating'] ?? 0);
    $comment = trim($body['comment'] ?? '');

    if (!$name || $rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nom et note (1-5) requis']);
        return;
    }

    // Vérifier que l'artisan existe
    $stmt = $pdo->prepare("SELECT id FROM local_artisans WHERE id = ? AND status = 'active'");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Artisan non trouvé']);
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO local_reviews (artisan_id, reviewer_name, reviewer_email, rating, comment, is_approved)
        VALUES (?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([$id, $name, $email ?: null, $rating, $comment]);

    $artisanId = $id;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
    if ($token) {
        $usr = $pdo->prepare("SELECT id FROM local_users WHERE session_token = ? AND session_exp > NOW() LIMIT 1");
        $usr->execute([$token]);
        $uid = $usr->fetchColumn();
        if ($uid) {
            gamificationRecordAction($pdo, (int)$uid, 'testimonial_post', "artisan:$artisanId", ['artisan_id' => $artisanId]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Merci pour votre avis ! Il sera publié après modération.',
    ]);
}

/**
 * Vérifie que l'artisan authentifié est administrateur.
 */
function artisan_require_admin(PDO $pdo): array
{
    $artisan = artisan_require_auth($pdo);
    if (empty($artisan['is_admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Accès réservé aux administrateurs']);
        exit;
    }
    return $artisan;
}

/**
 * GET /artisans/me — Profil de l'artisan authentifié
 */
function artisan_me(PDO $pdo): void
{
    $token = artisan_get_token();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token requis']);
        return;
    }

    $tokenLookup = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT
            a.id, a.company_name, a.description,
            a.phone, a.email, a.website, a.address,
            a.latitude, a.longitude,
            a.logo_url, a.cover_url,
            a.is_verified, a.is_featured, a.status,
            a.auth_token_hash,
            c.slug AS city_slug, c.name AS city_name, c.postal_code,
            cat.slug AS category_slug, cat.name AS category_name,
            cat.icon AS category_icon, cat.color AS category_color,
            COALESCE(ROUND(AVG(r.rating), 1), 0) AS rating_avg,
            COUNT(DISTINCT r.id)                  AS rating_count
        FROM local_artisans a
        JOIN local_cities c            ON a.city_id = c.id
        LEFT JOIN local_categories cat ON a.category_id = cat.id
        LEFT JOIN local_reviews r      ON r.artisan_id = a.id AND r.is_approved = 1
        WHERE a.auth_token_lookup = ?
          AND a.auth_token_hash IS NOT NULL
          AND a.auth_token_exp > NOW()
        GROUP BY a.id
        LIMIT 1
    ");
    $stmt->execute([$tokenLookup]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($artisan && !password_verify($token, $artisan['auth_token_hash'])) {
        $artisan = null;
    }

    if (!$artisan) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token invalide ou expiré']);
        return;
    }

    unset($artisan['password_hash'], $artisan['auth_token'], $artisan['auth_token_exp'], $artisan['auth_token_hash']);
    $artisan['is_featured']    = (bool)$artisan['is_featured'];
    $artisan['is_verified']    = (bool)$artisan['is_verified'];
    $artisan['email_verified'] = (bool)($artisan['email_verified'] ?? 0);
    $artisan['rating_avg']     = (float)$artisan['rating_avg'];
    $artisan['rating_count']   = (int)$artisan['rating_count'];

    // Garantir un compte consommateur lié et créer sa session
    $userId = artisan_ensure_user($pdo, (int)$artisan['id'], $artisan['email'], $artisan['company_name']);
    $userToken = $userId ? user_create_session($pdo, $userId, true) : null;

    echo json_encode([
        'success' => true,
        'data' => $artisan,
        'userToken' => $userToken,
    ]);
}

/**
 * PUT /artisans/me — Mise à jour profil artisan authentifié
 */
function artisan_update_me(PDO $pdo, array $body): void
{
    $token = artisan_get_token();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token requis']);
        return;
    }

    $tokenLookup = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT id, auth_token_hash FROM local_artisans
        WHERE auth_token_lookup = ?
          AND auth_token_hash IS NOT NULL
          AND auth_token_exp > NOW() AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$tokenLookup]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($artisan && !password_verify($token, $artisan['auth_token_hash'])) {
        $artisan = null;
    }

    if (!$artisan) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token invalide ou expiré']);
        return;
    }

    $allowed = ['company_name', 'description', 'phone', 'website', 'address', 'logo_url', 'cover_url'];
    $updates = [];
    $params  = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $updates[] = "{$field} = ?";
            $params[]  = trim($body[$field]);
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucun champ à mettre à jour']);
        return;
    }

    $params[] = $artisan['id'];
    $pdo->prepare("UPDATE local_artisans SET " . implode(', ', $updates) . " WHERE id = ?")
        ->execute($params);

    echo json_encode(['success' => true, 'message' => 'Profil mis à jour']);
}

/**
 * PUT /artisans/{id} — Mise à jour profil artisan (authentifié)
 */
function artisan_update(PDO $pdo, int $id, array $body): void
{
    // Vérification token artisan
    $token = artisan_get_token();

    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token requis']);
        return;
    }

    $tokenLookup = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT id, auth_token_hash FROM local_artisans
        WHERE id = ? AND auth_token_lookup = ?
          AND auth_token_hash IS NOT NULL
          AND auth_token_exp > NOW() AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$id, $tokenLookup]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($artisan && !password_verify($token, $artisan['auth_token_hash'])) {
        $artisan = null;
    }

    if (!$artisan) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token invalide ou expiré']);
        return;
    }

    // Champs modifiables
    $allowed = ['company_name', 'description', 'phone', 'website', 'address', 'logo_url', 'cover_url'];
    $updates = [];
    $params  = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $updates[] = "{$field} = ?";
            $params[]  = trim($body[$field]);
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucun champ à mettre à jour']);
        return;
    }

    $params[] = $id;
    $pdo->prepare("UPDATE local_artisans SET " . implode(', ', $updates) . " WHERE id = ?")
        ->execute($params);

    echo json_encode(['success' => true, 'message' => 'Profil mis à jour']);
}

/**
 * GET /artisans/me/prospects — Prospects suivis par l'artisan
 */
function artisan_my_prospects(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);

    $stmt = $pdo->prepare("
        SELECT p.*, f.status AS follow_status, f.notes AS follow_notes, f.updated_at AS follow_updated_at
        FROM local_prospects p
        LEFT JOIN local_prospect_follow_ups f ON f.prospect_id = p.id AND f.artisan_id = ?
        WHERE p.is_active = 1
        ORDER BY FIELD(f.status, 'tocontact', 'contacted', 'meeting', 'converted', 'declined'), p.name ASC
    ");
    $stmt->execute([$artisan['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $items,
        'total'   => count($items),
    ]);
}

/**
 * POST /artisans/me/prospects/:id — Suivre / mettre à jour un prospect
 */
function artisan_follow_prospect(PDO $pdo, int $prospectId, array $body): void
{
    $artisan = artisan_require_auth($pdo);

    $status = $body['status'] ?? 'tocontact';
    $allowed = ['tocontact', 'contacted', 'meeting', 'converted', 'declined'];
    if (!in_array($status, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Statut invalide']);
        return;
    }

    $notes = trim($body['notes'] ?? '');

    // verify prospect exists
    $check = $pdo->prepare("SELECT id FROM local_prospects WHERE id = ? AND is_active = 1");
    $check->execute([$prospectId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Prospect non trouvé']);
        return;
    }

    $pdo->prepare("
        INSERT INTO local_prospect_follow_ups (prospect_id, artisan_id, status, notes)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes)
    ")->execute([$prospectId, $artisan['id'], $status, $notes]);

    echo json_encode(['success' => true, 'message' => 'Suivi mis à jour']);
}

/**
 * DELETE /artisans/me/prospects/:id — Ne plus suivre un prospect
 */
function artisan_unfollow_prospect(PDO $pdo, int $prospectId): void
{
    $artisan = artisan_require_auth($pdo);

    $pdo->prepare("DELETE FROM local_prospect_follow_ups WHERE prospect_id = ? AND artisan_id = ?")
        ->execute([$prospectId, $artisan['id']]);

    echo json_encode(['success' => true, 'message' => 'Suivi supprimé']);
}

/**
 * GET /artisans/me/admin-recipes — Recettes à modérer (admin)
 */
function artisan_admin_recipes(PDO $pdo): void
{
    artisan_require_admin($pdo);

    $status = $_GET['status'] ?? '';
    $allowedStatuses = ['published', 'reported', 'archived'];

    $sql = "
        SELECT r.id, r.title, r.slug, r.description, r.image_url,
               r.prep_time_minutes, r.cook_time_minutes, r.servings,
               r.difficulty, r.season, r.is_premium, r.is_incomplete,
               r.status, r.submitted_by, r.submitter_email, r.created_at,
               c.slug AS city_slug, c.name AS city_name
        FROM local_recipes r
        JOIN local_cities c ON c.id = r.city_id
    ";
    $params = [];

    if ($status && in_array($status, $allowedStatuses, true)) {
        $sql .= " WHERE r.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY r.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['is_premium']    = (bool)$item['is_premium'];
        $item['is_incomplete'] = (bool)$item['is_incomplete'];
    }

    echo json_encode([
        'success' => true,
        'data'    => $items,
        'total'   => count($items),
    ]);
}

/**
 * PUT /artisans/me/admin-recipes/:id/archive — Archiver une recette
 */
function artisan_archive_recipe(PDO $pdo, int $recipeId): void
{
    artisan_require_admin($pdo);

    $check = $pdo->prepare("SELECT id FROM local_recipes WHERE id = ?");
    $check->execute([$recipeId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Recette non trouvée']);
        return;
    }

    $pdo->prepare("UPDATE local_recipes SET status = 'archived' WHERE id = ?")
        ->execute([$recipeId]);

    echo json_encode(['success' => true, 'message' => 'Recette archivée']);
}


/**
 * GET /artisans/me/spin-offers
 */
function artisan_my_spin_offers(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);

    $stmt = $pdo->prepare("
        SELECT id, label, description, stock_total, stock_remaining,
               is_active, created_at, updated_at
        FROM local_spin_offers
        WHERE artisan_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$artisan['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['stock_total']     = (int)$item['stock_total'];
        $item['stock_remaining'] = (int)$item['stock_remaining'];
        $item['is_active']       = (bool)$item['is_active'];
    }

    echo json_encode(['success' => true, 'data' => $items]);
}

/**
 * POST /artisans/me/spin-offers
 */
function artisan_create_spin_offer(PDO $pdo, array $body): void
{
    $artisan = artisan_require_auth($pdo);

    if (!artisanIsPremium($pdo, (int)$artisan['id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cette fonctionnalité nécessite l\'abonnement Premium']);
        return;
    }

    $label       = trim($body['label'] ?? '');
    $description = trim($body['description'] ?? '');
    $stockTotal  = (int)($body['stock_total'] ?? 0);

    if (!$label || $stockTotal < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Libellé et stock initial requis']);
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO local_spin_offers
            (artisan_id, label, description, stock_total, stock_remaining)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$artisan['id'], $label, $description, $stockTotal, $stockTotal]);

    $id = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'data'    => [
            'id'              => $id,
            'label'           => $label,
            'stock_remaining' => $stockTotal,
        ],
    ]);
}

/**
 * PUT /artisans/me/spin-offers/:id
 */
function artisan_update_spin_offer(PDO $pdo, int $id, array $body): void
{
    $artisan = artisan_require_auth($pdo);

    $allowed = ['label', 'description', 'is_active'];
    $updates = [];
    $params  = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $updates[] = "{$field} = ?";
            $params[]  = $body[$field];
        }
    }

    if (isset($body['stock_total'])) {
        $newTotal = (int)$body['stock_total'];
        if ($newTotal < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Stock total invalide']);
            return;
        }
        $updates[] = "stock_total = ?";
        $params[]  = $newTotal;
        $updates[] = "stock_remaining = GREATEST(? - (stock_total - stock_remaining), 0)";
        $params[]  = $newTotal;
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucun champ à mettre à jour']);
        return;
    }

    $params[] = $id;
    $params[] = $artisan['id'];

    $stmt = $pdo->prepare("
        UPDATE local_spin_offers
        SET " . implode(', ', $updates) . "
        WHERE id = ? AND artisan_id = ?
    ");
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Offre mise à jour']);
}

/**
 * DELETE /artisans/me/spin-offers/:id
 */
function artisan_delete_spin_offer(PDO $pdo, int $id): void
{
    $artisan = artisan_require_auth($pdo);

    $pdo->prepare("DELETE FROM local_spin_offers WHERE id = ? AND artisan_id = ?")
        ->execute([$id, $artisan['id']]);

    echo json_encode(['success' => true, 'message' => 'Offre supprimée']);
}

/**
 * GET /artisans/me/spin-wins?status=pending
 */
function artisan_my_spin_wins(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);

    $status = $_GET['status'] ?? '';
    $allowed = ['pending', 'claimed', 'expired'];

    $sql = "
        SELECT w.id, w.code, w.status, w.spin_date, w.claimed_at, w.expires_at,
               o.label, o.description, u.email AS user_email
        FROM local_spin_wins w
        JOIN local_spin_offers o ON o.id = w.offer_id
        JOIN local_users u       ON u.id = w.user_id
        WHERE w.artisan_id = ?
    ";
    $params = [$artisan['id']];

    if ($status && in_array($status, $allowed, true)) {
        $sql .= " AND w.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY w.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $items]);
}

/**
 * POST /artisans/me/spin-wins/:code/validate
 */
function artisan_validate_spin_win(PDO $pdo, string $code): void
{
    $artisan = artisan_require_auth($pdo);

    $stmt = $pdo->prepare("
        SELECT id, status, expires_at
        FROM local_spin_wins
        WHERE code = ? AND artisan_id = ?
    ");
    $stmt->execute([$code, $artisan['id']]);
    $win = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$win) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Gain non trouvé']);
        return;
    }

    if ($win['status'] !== 'pending') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Ce gain a déjà été utilisé ou est expiré']);
        return;
    }

    if (strtotime($win['expires_at']) < time()) {
        $upd = $pdo->prepare("
            UPDATE local_spin_wins
            SET status = 'expired'
            WHERE id = ? AND status = 'pending'
        ");
        $upd->execute([$win['id']]);
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Ce gain a expiré']);
        return;
    }

    $upd = $pdo->prepare("
        UPDATE local_spin_wins
        SET status = 'claimed', claimed_at = NOW()
        WHERE id = ? AND status = 'pending' AND expires_at > NOW()
    ");
    $upd->execute([$win['id']]);

    if ($upd->rowCount() === 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Ce gain a déjà été utilisé ou est expiré']);
        return;
    }

    echo json_encode(['success' => true, 'message' => 'Gain validé']);
}

/**
 * GET /artisans/me/services
 */
function artisan_my_services(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.description, s.price_range, s.duration,
               s.is_custom, s.is_active, s.sort_order, s.service_catalog_id,
               sc.`key` AS catalog_key, sc.label_fr AS catalog_label, sc.icon AS catalog_icon
        FROM local_services s
        LEFT JOIN local_service_catalog sc ON sc.id = s.service_catalog_id
        WHERE s.artisan_id = ?
        ORDER BY s.sort_order ASC, s.id ASC
    ");
    $stmt->execute([$artisan['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['service_catalog_id'] = $item['service_catalog_id'] !== null ? (int)$item['service_catalog_id'] : null;
        $item['is_custom'] = (bool)$item['is_custom'];
        $item['is_active'] = (bool)$item['is_active'];
        $item['sort_order'] = (int)$item['sort_order'];
    }
    echo json_encode(['success' => true, 'data' => $items]);
}

/**
 * POST /artisans/me/services
 */
function artisan_create_service(PDO $pdo, array $body): void
{
    $artisan = artisan_require_auth($pdo);
    $artisanId = (int)$artisan['id'];

    $catalogId = !empty($body['service_catalog_id']) ? (int)$body['service_catalog_id'] : null;
    $name = trim($body['name'] ?? '');
    $description = trim($body['description'] ?? '');
    $priceRange = trim($body['price_range'] ?? '');
    $duration = trim($body['duration'] ?? '');
    $isCustom = !empty($body['is_custom']);

    if (!$name) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nom du service requis']);
        return;
    }

    if (mb_strlen($name) > 200) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nom du service trop long (200 caractères max)']);
        return;
    }

    if ($catalogId !== null) {
        $catCheck = $pdo->prepare("SELECT id FROM local_service_catalog WHERE id = ? AND is_active = 1");
        $catCheck->execute([$catalogId]);
        if (!$catCheck->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Catalogue de service invalide']);
            return;
        }
    }

    // Enforce free-tier limit (5 active services)
    if (!artisanIsPremium($pdo, $artisanId)) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM local_services WHERE artisan_id = ? AND is_active = 1");
        $countStmt->execute([$artisanId]);
        if ((int)$countStmt->fetchColumn() >= FREE_TIER_MAX_ACTIVE_SERVICES) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Limite de 5 services atteinte']);
            return;
        }
    }

    $pdo->prepare("
        INSERT INTO local_services
            (artisan_id, service_catalog_id, name, description, price_range, duration, is_custom, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, 99)
    ")->execute([$artisanId, $catalogId, $name, $description, $priceRange, $duration, $isCustom ? 1 : 0]);

    echo json_encode(['success' => true, 'data' => ['id' => (int)$pdo->lastInsertId()]]);
}

/**
 * PUT /artisans/me/services/:id
 */
function artisan_update_service(PDO $pdo, int $serviceId, array $body): void
{
    $artisan = artisan_require_auth($pdo);
    $artisanId = (int)$artisan['id'];

    $check = $pdo->prepare("SELECT id, is_active FROM local_services WHERE id = ? AND artisan_id = ?");
    $check->execute([$serviceId, $artisanId]);
    $service = $check->fetch(PDO::FETCH_ASSOC);
    if (!$service) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Service non trouvé']);
        return;
    }

    $allowed = ['name', 'description', 'price_range', 'duration', 'is_active', 'sort_order', 'service_catalog_id'];

    // Enforce free-tier active limit when activating a service
    if (!artisanIsPremium($pdo, $artisanId) && array_key_exists('is_active', $body) && (int)$body['is_active'] === 1 && !(bool)$service['is_active']) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM local_services WHERE artisan_id = ? AND is_active = 1 AND id != ?");
        $countStmt->execute([$artisanId, $serviceId]);
        if ((int)$countStmt->fetchColumn() >= FREE_TIER_MAX_ACTIVE_SERVICES) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Limite de 5 services actifs atteinte']);
            return;
        }
    }
    $sets = [];
    $params = [];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $body)) {
            continue;
        }
        if ($col === 'name') {
            $value = trim((string)$body[$col]);
            if (mb_strlen($value) > 200) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nom du service trop long (200 caractères max)']);
                return;
            }
        } elseif ($col === 'service_catalog_id') {
            $catalogId = (int)$body[$col];
            $catCheck = $pdo->prepare("SELECT id FROM local_service_catalog WHERE id = ? AND is_active = 1");
            $catCheck->execute([$catalogId]);
            if (!$catCheck->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Catalogue de service invalide']);
                return;
            }
            $value = $catalogId;
        } else {
            $value = $body[$col];
            if ($col === 'is_active' || $col === 'sort_order') {
                $value = (int)$value;
            } elseif (in_array($col, ['description', 'price_range', 'duration'], true)) {
                $value = trim((string)$value);
            }
        }
        $sets[] = "$col = ?";
        $params[] = $value;
    }
    if (empty($sets)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucune donnée à mettre à jour']);
        return;
    }
    $params[] = $serviceId;
    $params[] = $artisanId;

    $sql = "UPDATE local_services SET " . implode(', ', $sets) . " WHERE id = ? AND artisan_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Service mis à jour']);
}

/**
 * DELETE /artisans/me/services/:id
 */
function artisan_delete_service(PDO $pdo, int $serviceId): void
{
    $artisan = artisan_require_auth($pdo);
    $artisanId = (int)$artisan['id'];

    $stmt = $pdo->prepare("DELETE FROM local_services WHERE id = ? AND artisan_id = ?");
    $stmt->execute([$serviceId, $artisanId]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Service non trouvé']);
        return;
    }
    echo json_encode(['success' => true, 'message' => 'Service supprimé']);
}


/**
 * GET /artisans/me/games
 */
function artisan_games_list(PDO $pdo): void
{
    $artisan = artisan_require_auth($pdo);
    $stmt = $pdo->prepare("
        SELECT i.*, gt.`key` AS game_type_key, gt.label_fr AS game_type_label, gt.is_premium
        FROM local_game_instances i
        JOIN local_game_types gt ON gt.id = i.game_type_id
        WHERE i.artisan_id = ?
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$artisan['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['is_premium'] = (bool)$item['is_premium'];
        $item['config'] = json_decode($item['config'], true);
    }
    echo json_encode(['success' => true, 'data' => $items]);
}

/**
 * POST /artisans/me/games
 */
function artisan_create_game(PDO $pdo, array $body): void
{
    $artisan = artisan_require_auth($pdo);
    $artisanId = (int)$artisan['id'];

    $gameTypeKey = $body['game_type_key'] ?? '';
    $title = trim($body['title'] ?? '');
    $description = trim($body['description'] ?? '');
    $config = $body['config'] ?? [];

    if (!$gameTypeKey || !$title) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Type de jeu et titre requis']);
        return;
    }

    $typeStmt = $pdo->prepare("SELECT id, is_premium FROM local_game_types WHERE `key` = ? AND is_active = 1");
    $typeStmt->execute([$gameTypeKey]);
    $type = $typeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Type de jeu inconnu']);
        return;
    }

    if ((bool)$type['is_premium'] && !artisanIsPremium($pdo, $artisanId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Type de jeu premium']);
        return;
    }

    if (!games_can_artisan_create($pdo, $artisanId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Limite de 1 jeu actif atteinte']);
        return;
    }

    $cityStmt = $pdo->prepare("SELECT city_id FROM local_artisans WHERE id = ?");
    $cityStmt->execute([$artisanId]);
    $cityId = (int)$cityStmt->fetchColumn();

    $pdo->prepare("
        INSERT INTO local_game_instances
            (game_type_id, artisan_id, city_id, title, description, config)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([
        $type['id'], $artisanId, $cityId, $title, $description,
        json_encode($config, JSON_THROW_ON_ERROR),
    ]);

    echo json_encode(['success' => true, 'data' => ['id' => (int)$pdo->lastInsertId()]]);
}

/**
 * PUT /artisans/me/games/:id
 */
function artisan_update_game(PDO $pdo, int $gameId, array $body): void
{
    $artisan = artisan_require_auth($pdo);

    $check = $pdo->prepare("SELECT id, is_active FROM local_game_instances WHERE id = ? AND artisan_id = ?");
    $check->execute([$gameId, $artisan['id']]);
    $game = $check->fetch(PDO::FETCH_ASSOC);
    if (!$game) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Jeu non trouvé']);
        return;
    }

    // Enforce free-tier active limit when activating a game
    if (array_key_exists('is_active', $body) && (int)$body['is_active'] === 1 && !(bool)$game['is_active']) {
        if (!games_can_artisan_create($pdo, (int)$artisan['id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Limite de 1 jeu actif atteinte']);
            return;
        }
    }

    $allowed = ['title', 'description', 'config', 'is_active', 'starts_at', 'ends_at', 'max_plays_per_user', 'play_cooldown_hours'];
    $sets = [];
    $params = [];
    foreach ($allowed as $col) {
        if (array_key_exists($col, $body)) {
            $sets[] = "$col = ?";
            $params[] = is_array($body[$col]) ? json_encode($body[$col], JSON_THROW_ON_ERROR) : $body[$col];
        }
    }
    if (empty($sets)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucune donnée à mettre à jour']);
        return;
    }
    $params[] = $gameId;
    $params[] = $artisan['id'];

    $stmt = $pdo->prepare("UPDATE local_game_instances SET " . implode(', ', $sets) . " WHERE id = ? AND artisan_id = ?");
    $stmt->execute($params);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Jeu non trouvé']);
        return;
    }
    echo json_encode(['success' => true, 'message' => 'Jeu mis à jour']);
}

/**
 * DELETE /artisans/me/games/:id
 */
function artisan_delete_game(PDO $pdo, int $gameId): void
{
    $artisan = artisan_require_auth($pdo);
    $stmt = $pdo->prepare("DELETE FROM local_game_instances WHERE id = ? AND artisan_id = ?");
    $stmt->execute([$gameId, $artisan['id']]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Jeu non trouvé']);
        return;
    }
    echo json_encode(['success' => true, 'message' => 'Jeu supprimé']);
}
