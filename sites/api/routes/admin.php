<?php
/**
 * WebIArtisan API — Route : Admin
 * Endpoints réservés aux administrateurs pour gérer les artisans,
 * les POI locaux et leurs horaires.
 *
 * GET  /admin/artisans              — Liste des artisans
 * POST /admin/artisans/{id}/activate — Activer un artisan
 * POST /admin/artisans/{id}/suspend  — Suspendre un artisan
 * POST /admin/artisans/{id}/set-plan — Définir le plan free/premium
 *
 * GET    /admin/pois                — Liste des POI de la ville admin
 * GET    /admin/pois/{id}           — Détail d'un POI
 * POST   /admin/pois                — Créer un POI
 * PUT    /admin/pois/{id}           — Modifier un POI
 * DELETE /admin/pois/{id}           — Supprimer un POI
 *
 * POST   /admin/pois/{id}/schedules — Ajouter un horaire à un POI
 * PUT    /admin/schedules/{id}      — Modifier un horaire
 * DELETE /admin/schedules/{id}      — Supprimer un horaire
 */

require_once __DIR__ . '/../lib/ArtisanAuth.php';

$artisan = artisan_require_auth($pdo);

if (empty($artisan['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès réservé']);
    exit;
}

$subAction = $segments[3] ?? '';

if ($method === 'GET' && $action === 'artisans' && $param === null) {
    admin_list_artisans($pdo);
} elseif ($method === 'PUT' && $action === 'artisans' && is_numeric($param) && $subAction === '') {
    admin_update_artisan($pdo, (int)$param);
} elseif ($method === 'POST' && $action === 'artisans' && is_numeric($param) && $subAction === 'activate') {
    admin_activate_artisan($pdo, (int)$param);
} elseif ($method === 'POST' && $action === 'artisans' && is_numeric($param) && $subAction === 'suspend') {
    admin_suspend_artisan($pdo, (int)$param);
} elseif ($method === 'POST' && $action === 'artisans' && is_numeric($param) && $subAction === 'set-plan') {
    admin_set_artisan_plan($pdo, (int)$param);
} elseif ($method === 'POST' && $action === 'artisans' && is_numeric($param) && $subAction === 'reset-password') {
    admin_reset_artisan_password($pdo, (int)$param);
} elseif ($method === 'POST' && $action === 'artisans' && is_numeric($param) && $subAction === 'force-password') {
    admin_force_artisan_password($pdo, (int)$param);
} elseif ($method === 'POST' && $action === 'artisans' && is_numeric($param) && $subAction === 'set-subscription-status') {
    admin_set_artisan_subscription_status($pdo, (int)$param);
} elseif ($method === 'POST' && $action === 'artisans' && is_numeric($param) && $subAction === 'set-admin') {
    admin_set_artisan_admin($pdo, (int)$param);
} elseif ($action === 'pois') {
    admin_pois_router($pdo, $method, $param);
} elseif ($action === 'schedules') {
    admin_schedules_router($pdo, $method, $param);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
}

function admin_list_artisans(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT
            a.id,
            a.company_name,
            a.email,
            a.phone,
            a.status,
            a.plan,
            a.is_admin,
            a.subscription_status,
            a.created_at,
            a.updated_at,
            c.name AS city_name,
            c.slug AS city_slug
        FROM local_artisans a
        LEFT JOIN local_cities c ON a.city_id = c.id
        ORDER BY a.id DESC
    ");

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function admin_activate_artisan(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("UPDATE local_artisans SET status = 'active' WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Artisan activé',
        'affected' => $stmt->rowCount(),
    ]);
}

function admin_suspend_artisan(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("UPDATE local_artisans SET status = 'suspended' WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Artisan suspendu',
        'affected' => $stmt->rowCount(),
    ]);
}

function admin_set_artisan_plan(PDO $pdo, int $id): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $plan = in_array($body['plan'] ?? '', ['free', 'premium'], true) ? $body['plan'] : null;

    if (!$plan) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Plan invalide']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE local_artisans SET plan = ? WHERE id = ?");
    $stmt->execute([$plan, $id]);

    echo json_encode([
        'success' => true,
        'message' => "Plan mis à jour : $plan",
        'affected' => $stmt->rowCount(),
    ]);
}

/**
 * POST /admin/artisans/{id}/reset-password — Envoie un lien de connexion magique
 */
function admin_reset_artisan_password(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("SELECT id, company_name, email, status FROM local_artisans WHERE id = ?");
    $stmt->execute([$id]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$artisan) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Artisan non trouvé']);
        return;
    }

    if ($artisan['status'] !== 'active') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Le compte doit être actif pour recevoir un lien']);
        return;
    }

    $token = bin2hex(random_bytes(32));
    $exp = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $pdo->prepare("UPDATE local_artisans SET auth_token = ?, auth_token_exp = ? WHERE id = ?")
        ->execute([$token, $exp, $id]);

    $config = getAppConfig();
    $fromEmail = $config['mail_from'] ?? 'noreply@webiartisan.prigent.tech';
    $portalUrl = artisan_portal_url();
    $link = rtrim($portalUrl, '/') . '/espace?token=' . urlencode($token);

    $safeCompany = htmlspecialchars($artisan['company_name'], ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

    $subject = 'Votre lien de connexion WebIArtisan';
    $html = <<<HTML
<!DOCTYPE html>
<html><body style="font-family: -apple-system, sans-serif; max-width: 480px; margin: 0 auto; padding: 20px;">
  <h2 style="color: #1a1a2e;">Bonjour {$safeCompany},</h2>
  <p>Un administrateur vous a envoyé un lien pour accéder à votre espace artisan :</p>
  <div style="text-align: center; margin: 24px 0;">
    <a href="{$safeLink}" style="display: inline-block; background: #1a1a2e; color: #fff; padding: 14px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;">Me connecter</a>
  </div>
  <p style="color: #888; font-size: 13px;">Ce lien est valable 1 heure. Si vous ne l'avez pas demandé, contactez-nous.</p>
</body></html>
HTML;

    $queued = queueEmail(
        $artisan['email'],
        $subject,
        $html,
        $fromEmail,
        'WebIArtisan',
        null,
        ['type' => 'admin_reset_password', 'artisan_id' => $id]
    );

    echo json_encode([
        'success' => true,
        'message' => $queued ? 'Lien de connexion envoyé par email' : 'Lien généré mais échec de l\'envoi email',
        'queued' => $queued,
    ]);
}

/**
 * POST /admin/artisans/{id}/force-password — Force un nouveau mot de passe
 */
function admin_force_artisan_password(PDO $pdo, int $id): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $password = $body['password'] ?? '';

    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Mot de passe trop court (min 8 caractères)']);
        return;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE local_artisans SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hash, $id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Artisan non trouvé']);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Mot de passe mis à jour',
    ]);
}

/**
 * POST /admin/artisans/{id}/set-subscription-status — Met à jour le statut d'abonnement
 */
function admin_set_artisan_subscription_status(PDO $pdo, int $id): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $allowedStatuses = ['active', 'canceled', 'past_due', 'unpaid', 'trialing', 'incomplete', null];
    $status = in_array($body['status'] ?? null, $allowedStatuses, true) ? $body['status'] : null;

    if ($status === null && !array_key_exists('status', $body)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Statut invalide']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE local_artisans SET subscription_status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    echo json_encode([
        'success' => true,
        'message' => 'Statut d\'abonnement mis à jour',
        'affected' => $stmt->rowCount(),
    ]);
}

/**
 * POST /admin/artisans/{id}/set-admin — Accorde ou révoque les droits admin
 */
function admin_set_artisan_admin(PDO $pdo, int $id): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!array_key_exists('is_admin', $body)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Paramètre is_admin requis']);
        return;
    }

    $isAdmin = filter_var($body['is_admin'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE local_artisans SET is_admin = ? WHERE id = ?");
    $stmt->execute([$isAdmin, $id]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare("SELECT id FROM local_artisans WHERE id = ? LIMIT 1");
        $check->execute([$id]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Artisan non trouvé']);
            return;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $isAdmin ? 'Droits admin accordés' : 'Droits admin retirés',
        'affected' => $stmt->rowCount(),
    ]);
}

function admin_update_artisan(PDO $pdo, int $id): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

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
    $stmt = $pdo->prepare("UPDATE local_artisans SET " . implode(', ', $updates) . " WHERE id = ?");
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Artisan mis à jour',
        'affected' => $stmt->rowCount(),
    ]);
}

/* =============================================================
 * POI Admin
 * ============================================================= */

function admin_current_city_id(PDO $pdo, array $artisan): int
{
    $stmt = $pdo->prepare("SELECT id FROM local_cities WHERE id = ? LIMIT 1");
    $stmt->execute([$artisan['city_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ville admin invalide']);
        exit;
    }
    return (int)$row['id'];
}

function admin_validate_poi_type(string $type): bool
{
    $allowed = ['mairie', 'piscine', 'tabac', 'supermarche', 'restaurant', 'cafe', 'pharmacie', 'boulangerie', 'coiffeur', 'plombier', 'jardinier', 'autre'];
    return in_array($type, $allowed, true);
}

function admin_pois_router(PDO $pdo, string $method, ?string $param): void
{
    global $artisan;
    $cityId = admin_current_city_id($pdo, $artisan);

    if ($method === 'GET' && $param === null) {
        admin_list_pois($pdo, $cityId);
    } elseif ($method === 'GET' && is_numeric($param)) {
        admin_get_poi($pdo, $cityId, (int)$param);
    } elseif ($method === 'POST' && $param === null) {
        admin_create_poi($pdo, $cityId);
    } elseif ($method === 'PUT' && is_numeric($param)) {
        admin_update_poi($pdo, $cityId, (int)$param);
    } elseif ($method === 'DELETE' && is_numeric($param)) {
        admin_delete_poi($pdo, $cityId, (int)$param);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint POI inconnu']);
    }
}

function admin_list_pois(PDO $pdo, int $cityId): void
{
    $stmt = $pdo->prepare("SELECT * FROM local_pois WHERE city_id = ? ORDER BY sort_order, name");
    $stmt->execute([$cityId]);
    $pois = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $scheduleStmt = $pdo->prepare("SELECT * FROM local_schedules WHERE poi_id = ? ORDER BY day_of_week");
    foreach ($pois as &$poi) {
        $scheduleStmt->execute([$poi['id']]);
        $poi['schedules'] = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($poi['meta']) {
            $poi['meta'] = json_decode($poi['meta'], true);
        }
    }
    unset($poi);

    echo json_encode(['success' => true, 'data' => $pois]);
}

function admin_get_poi(PDO $pdo, int $cityId, int $id): void
{
    $stmt = $pdo->prepare("SELECT * FROM local_pois WHERE id = ? AND city_id = ? LIMIT 1");
    $stmt->execute([$id, $cityId]);
    $poi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poi) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'POI non trouvé']);
        return;
    }

    $scheduleStmt = $pdo->prepare("SELECT * FROM local_schedules WHERE poi_id = ? ORDER BY day_of_week");
    $scheduleStmt->execute([$id]);
    $poi['schedules'] = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
    if ($poi['meta']) {
        $poi['meta'] = json_decode($poi['meta'], true);
    }

    echo json_encode(['success' => true, 'data' => $poi]);
}

function admin_create_poi(PDO $pdo, int $cityId): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = admin_validate_poi_body($body);
    if ($errors) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $errors[0]]);
        return;
    }

    $stmt = $pdo->prepare("INSERT INTO local_pois
        (city_id, type, name, address, phone, website, email, latitude, longitude, description, meta, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $cityId,
        $body['type'],
        trim($body['name']),
        $body['address'] ?? null,
        $body['phone'] ?? null,
        $body['website'] ?? null,
        $body['email'] ?? null,
        $body['latitude'] ?? null,
        $body['longitude'] ?? null,
        $body['description'] ?? null,
        isset($body['meta']) ? json_encode($body['meta']) : null,
        $body['is_active'] ?? 1,
        $body['sort_order'] ?? 0,
    ]);

    echo json_encode(['success' => true, 'data' => ['id' => (int)$pdo->lastInsertId()]]);
}

function admin_update_poi(PDO $pdo, int $cityId, int $id): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $errors = admin_validate_poi_body($body, false);
    if ($errors) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $errors[0]]);
        return;
    }

    $fields = [];
    $values = [];
    $map = [
        'type' => 'type',
        'name' => 'name',
        'address' => 'address',
        'phone' => 'phone',
        'website' => 'website',
        'email' => 'email',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'description' => 'description',
        'is_active' => 'is_active',
        'sort_order' => 'sort_order',
    ];
    foreach ($map as $key => $col) {
        if (array_key_exists($key, $body)) {
            $fields[] = "$col = ?";
            $values[] = $body[$key] === '' ? null : $body[$key];
        }
    }
    if (array_key_exists('meta', $body)) {
        $fields[] = "meta = ?";
        $values[] = $body['meta'] === null ? null : json_encode($body['meta']);
    }
    if (!$fields) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucune donnée à mettre à jour']);
        return;
    }
    $values[] = $id;
    $values[] = $cityId;

    $stmt = $pdo->prepare("UPDATE local_pois SET " . implode(', ', $fields) . " WHERE id = ? AND city_id = ?");
    $stmt->execute($values);

    echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
}

function admin_delete_poi(PDO $pdo, int $cityId, int $id): void
{
    $pdo->prepare("DELETE FROM local_schedules WHERE poi_id = ?")->execute([$id]);
    $stmt = $pdo->prepare("DELETE FROM local_pois WHERE id = ? AND city_id = ?");
    $stmt->execute([$id, $cityId]);

    echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
}

function admin_validate_poi_body(array $body, bool $requireName = true): array
{
    $errors = [];
    if ($requireName && empty(trim($body['name'] ?? ''))) {
        $errors[] = 'Le nom est requis';
    }
    if ($requireName && empty($body['type'])) {
        $errors[] = 'Le type est requis';
    }
    if (!empty($body['type']) && !admin_validate_poi_type($body['type'])) {
        $errors[] = 'Type de POI invalide';
    }
    if (isset($body['latitude']) && ($body['latitude'] < -90 || $body['latitude'] > 90)) {
        $errors[] = 'Latitude invalide';
    }
    if (isset($body['longitude']) && ($body['longitude'] < -180 || $body['longitude'] > 180)) {
        $errors[] = 'Longitude invalide';
    }
    if (isset($body['name']) && mb_strlen($body['name']) > 255) {
        $errors[] = 'Nom trop long (max 255)';
    }
    return $errors;
}

/* =============================================================
 * Schedules Admin
 * ============================================================= */

function admin_schedules_router(PDO $pdo, string $method, ?string $param): void
{
    global $artisan;
    $cityId = admin_current_city_id($pdo, $artisan);

    if ($method === 'POST' && $param === null) {
        admin_create_schedule($pdo, $cityId);
    } elseif ($method === 'PUT' && is_numeric($param)) {
        admin_update_schedule($pdo, $cityId, (int)$param);
    } elseif ($method === 'DELETE' && is_numeric($param)) {
        admin_delete_schedule($pdo, $cityId, (int)$param);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint schedule inconnu']);
    }
}

function admin_create_schedule(PDO $pdo, int $cityId): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $poiId = (int)($body['poi_id'] ?? 0);

    if (!$poiId || !isset($body['day_of_week']) || $body['day_of_week'] < 0 || $body['day_of_week'] > 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'poi_id ou day_of_week invalide']);
        return;
    }

    $check = $pdo->prepare("SELECT id FROM local_pois WHERE id = ? AND city_id = ? LIMIT 1");
    $check->execute([$poiId, $cityId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'POI non trouvé dans cette ville']);
        return;
    }

    $stmt = $pdo->prepare("INSERT INTO local_schedules
        (poi_id, day_of_week, open_time, close_time, break_start, break_end, is_closed)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $poiId,
        $body['day_of_week'],
        $body['open_time'] ?? null,
        $body['close_time'] ?? null,
        $body['break_start'] ?? null,
        $body['break_end'] ?? null,
        $body['is_closed'] ?? 0,
    ]);

    echo json_encode(['success' => true, 'data' => ['id' => (int)$pdo->lastInsertId()]]);
}

function admin_update_schedule(PDO $pdo, int $cityId, int $id): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $check = $pdo->prepare("SELECT s.id FROM local_schedules s JOIN local_pois p ON s.poi_id = p.id WHERE s.id = ? AND p.city_id = ? LIMIT 1");
    $check->execute([$id, $cityId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Horaire non trouvé']);
        return;
    }

    $fields = [];
    $values = [];
    $map = ['day_of_week' => 'day_of_week', 'open_time' => 'open_time', 'close_time' => 'close_time', 'break_start' => 'break_start', 'break_end' => 'break_end', 'is_closed' => 'is_closed'];
    foreach ($map as $key => $col) {
        if (array_key_exists($key, $body)) {
            $fields[] = "$col = ?";
            $values[] = $body[$key];
        }
    }
    if (!$fields) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucune donnée à mettre à jour']);
        return;
    }
    $values[] = $id;

    $stmt = $pdo->prepare("UPDATE local_schedules SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($values);

    echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
}

function admin_delete_schedule(PDO $pdo, int $cityId, int $id): void
{
    $check = $pdo->prepare("SELECT s.id FROM local_schedules s JOIN local_pois p ON s.poi_id = p.id WHERE s.id = ? AND p.city_id = ? LIMIT 1");
    $check->execute([$id, $cityId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Horaire non trouvé']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM local_schedules WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
}
