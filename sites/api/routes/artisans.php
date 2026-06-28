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

switch ($method) {

    case 'GET':
        if ($action === '' || $action === 'list') {
            artisan_list($pdo);
        } elseif ($action === 'me' && $param === 'prospects') {
            artisan_my_prospects($pdo);
        } elseif ($action === 'me' && $param === 'admin-recipes') {
            artisan_admin_recipes($pdo);
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
        } elseif (is_numeric($action) && $param === 'contact') {
            artisan_contact($pdo, (int)$action, $body);
        } elseif (is_numeric($action) && $param === 'review') {
            artisan_add_review($pdo, (int)$action, $body);
        } elseif ($action === 'me' && $param === 'prospects' && is_numeric($segments[3] ?? '')) {
            artisan_follow_prospect($pdo, (int)$segments[3], $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'DELETE':
        if ($action === 'me' && $param === 'prospects' && is_numeric($segments[3] ?? '')) {
            artisan_unfollow_prospect($pdo, (int)$segments[3]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'PUT':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($action === 'me' && $param === 'admin-recipes' && is_numeric($segments[3] ?? '')) {
            artisan_archive_recipe($pdo, (int)$segments[3]);
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
 * GET /artisans — Liste publique
 * Paramètres: city (slug), category (slug), search, featured, limit, offset
 */
function artisan_list(PDO $pdo): void
{
    $city     = $_GET['city']     ?? null;
    $category = $_GET['category'] ?? null;
    $search   = $_GET['search']   ?? null;
    $featured = isset($_GET['featured']) ? (bool)$_GET['featured'] : null;
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

    foreach ($artisans as &$a) {
        $a['is_featured']  = (bool)$a['is_featured'];
        $a['is_verified']  = (bool)$a['is_verified'];
        $a['rating_avg']   = (float)$a['rating_avg'];
        $a['rating_count'] = (int)$a['rating_count'];
        $a['view_count']   = (int)$a['view_count'];
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
    unset($artisan['password_hash'], $artisan['auth_token'], $artisan['auth_token_exp']);

    $artisan['is_featured']  = (bool)$artisan['is_featured'];
    $artisan['is_verified']  = (bool)$artisan['is_verified'];
    $artisan['email_verified'] = (bool)$artisan['email_verified'];
    $artisan['rating_avg']   = (float)$artisan['rating_avg'];
    $artisan['rating_count'] = (int)$artisan['rating_count'];

    // Services
    $stmt2 = $pdo->prepare("
        SELECT id, name, description, price_range, duration
        FROM local_services
        WHERE artisan_id = ?
        ORDER BY sort_order ASC, name ASC
    ");
    $stmt2->execute([$id]);
    $artisan['services'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $artisan]);
}

/**
 * GET /artisans/{id}/services
 */
function artisan_services(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("
        SELECT id, name, description, price_range, duration, sort_order
        FROM local_services
        WHERE artisan_id = ?
        ORDER BY sort_order ASC, name ASC
    ");
    $stmt->execute([$id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $services]);
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

    // Générer un token simple (JWT serait mieux — à améliorer)
    $token = bin2hex(random_bytes(32));
    $exp   = date('Y-m-d H:i:s', strtotime('+30 days'));

    $pdo->prepare("UPDATE local_artisans SET auth_token = ?, auth_token_exp = ? WHERE id = ?")
        ->execute([$token, $exp, $artisan['id']]);

    echo json_encode([
        'success' => true,
        'token'   => $token,
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
        echo json_encode(['success' => true, 'message' => 'Si votre email est valide, vous recevrez un lien de connexion.']);
        return;
    }

    $token = bin2hex(random_bytes(32));
    $exp   = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $pdo->prepare("UPDATE local_artisans SET auth_token = ?, auth_token_exp = ? WHERE id = ?")
        ->execute([$token, $exp, $artisan['id']]);

    $portalUrl = artisan_portal_url();
    $link      = rtrim($portalUrl, '/') . '/espace?token=' . urlencode($token);

    $subject = 'Votre lien de connexion WebIArtisan';
    $html    = <<<HTML
<!DOCTYPE html>
<html><body style="font-family: -apple-system, sans-serif; max-width: 480px; margin: 0 auto; padding: 20px;">
  <h2 style="color: #1a1a2e;">Bonjour {$artisan['company_name']},</h2>
  <p>Voici votre lien de connexion sécurisé à votre espace artisan :</p>
  <div style="text-align: center; margin: 24px 0;">
    <a href="{$link}" style="display: inline-block; background: #1a1a2e; color: #fff; padding: 14px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;">Me connecter</a>
  </div>
  <p style="color: #888; font-size: 13px;">Ce lien est valable 1 heure. Si vous n'avez pas demandé ce lien, ignorez cet email.</p>
  <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
  <p style="color: #aaa; font-size: 12px;">WebIArtisan</p>
</body></html>
HTML;

    $sent = send_html_email($email, $subject, $html, null, 'WebIArtisan');
    if (!$sent) {
        error_log("[MAGIC-LINK] Échec envoi email à {$email}");
    }

    // Toujours logger en dev / secours
    error_log("[MAGIC-LINK] {$link}");

    echo json_encode(['success' => true, 'message' => 'Lien de connexion envoyé par email.']);
}

/**
 * Détermine l'URL du portail artisan à partir de l'origine de la requête.
 */
function artisan_portal_url(): string {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin && filter_var($origin, FILTER_VALIDATE_URL)) {
        return $origin;
    }
    $config = getAppConfig();
    return $config['url'] ?? 'https://artisans-livry.prigent.tech';
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
    $safeCompany = htmlspecialchars($artisan['company_name']);
    $safeName    = htmlspecialchars($name);
    $safeEmail   = htmlspecialchars($email);
    $safeMessage = nl2br(htmlspecialchars($message));

    $contactSubject = "Nouveau message depuis WebIArtisan — {$safeCompany}";
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

    $contactSent = send_html_email(
        $artisan['email'],
        $contactSubject,
        $contactHtml,
        null,
        'WebIArtisan',
        $email
    );

    if (!$contactSent) {
        error_log("[CONTACT] Échec envoi email à {$artisan['email']} depuis {$email}");
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

    echo json_encode([
        'success' => true,
        'message' => 'Merci pour votre avis ! Il sera publié après modération.',
    ]);
}

/**
 * Récupère le token artisan depuis les en-têtes.
 */
function artisan_get_token(): ?string
{
    $token = $_SERVER['HTTP_X_ARTISAN_TOKEN'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $token = str_replace('Bearer ', '', $token);
    return $token ?: null;
}

/**
 * Résout l'artisan authentifié depuis son token.
 */
function artisan_require_auth(PDO $pdo): array
{
    $token = artisan_get_token();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentification requise']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, company_name, email, is_admin
        FROM local_artisans
        WHERE auth_token = ? AND auth_token_exp > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$artisan) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session invalide']);
        exit;
    }

    return $artisan;
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

    $stmt = $pdo->prepare("
        SELECT
            a.id, a.company_name, a.description,
            a.phone, a.email, a.website, a.address,
            a.latitude, a.longitude,
            a.logo_url, a.cover_url,
            a.is_verified, a.is_featured, a.status,
            c.slug AS city_slug, c.name AS city_name, c.postal_code,
            cat.slug AS category_slug, cat.name AS category_name,
            cat.icon AS category_icon, cat.color AS category_color,
            COALESCE(ROUND(AVG(r.rating), 1), 0) AS rating_avg,
            COUNT(DISTINCT r.id)                  AS rating_count
        FROM local_artisans a
        JOIN local_cities c            ON a.city_id = c.id
        LEFT JOIN local_categories cat ON a.category_id = cat.id
        LEFT JOIN local_reviews r      ON r.artisan_id = a.id AND r.is_approved = 1
        WHERE a.auth_token = ? AND a.auth_token_exp > NOW()
        GROUP BY a.id
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$artisan) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token invalide ou expiré']);
        return;
    }

    unset($artisan['password_hash'], $artisan['auth_token'], $artisan['auth_token_exp']);
    $artisan['is_featured']    = (bool)$artisan['is_featured'];
    $artisan['is_verified']    = (bool)$artisan['is_verified'];
    $artisan['email_verified'] = (bool)($artisan['email_verified'] ?? 0);
    $artisan['rating_avg']     = (float)$artisan['rating_avg'];
    $artisan['rating_count']   = (int)$artisan['rating_count'];

    echo json_encode(['success' => true, 'data' => $artisan]);
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

    $stmt = $pdo->prepare("
        SELECT id FROM local_artisans
        WHERE auth_token = ? AND auth_token_exp > NOW() AND status = 'active'
    ");
    $stmt->execute([$token]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

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

    $stmt = $pdo->prepare("
        SELECT id FROM local_artisans
        WHERE id = ? AND auth_token = ? AND auth_token_exp > NOW() AND status = 'active'
    ");
    $stmt->execute([$id, $token]);
    if (!$stmt->fetch()) {
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
