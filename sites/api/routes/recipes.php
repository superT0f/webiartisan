<?php
/**
 * WebIArtisan API — Route : Recettes locales
 *
 * GET  /recipes?city=livry&difficulty=&season=&search= — liste
 * GET  /recipes/:slug                                 — détail
 * POST /recipes                                       — proposer
 * POST /recipes/:id/report                            — signaler
 * POST /recipes/:id/suggest                           — complément / variante
 */

require_once __DIR__ . '/../lib/Gamification.php';

switch ($method) {
    case 'GET':
        if ($action === '' || $action === 'list') {
            recipes_list($pdo);
        } elseif ($action && !$param) {
            recipes_get($pdo, $action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if ($action === '' || $action === 'list') {
            recipes_create($pdo, $body);
        } elseif (is_numeric($action) && $param === 'report') {
            recipes_report($pdo, (int)$action, $body);
        } elseif (is_numeric($action) && $param === 'suggest') {
            recipes_suggest($pdo, (int)$action, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function recipes_list(PDO $pdo): void
{
    $citySlug   = $_GET['city']       ?? '';
    $difficulty = $_GET['difficulty'] ?? '';
    $season     = $_GET['season']     ?? '';
    $search     = trim($_GET['search'] ?? '');

    $sql = "
        SELECT r.id, r.title, r.slug, r.description, r.image_url,
               r.prep_time_minutes, r.cook_time_minutes, r.servings,
               r.difficulty, r.season, r.is_premium, r.is_incomplete,
               r.submitted_by, r.created_at,
               c.slug AS city_slug, c.name AS city_name
        FROM local_recipes r
        JOIN local_cities c ON c.id = r.city_id
        WHERE r.status = 'published'
    ";
    $params = [];

    if ($citySlug) {
        $sql .= " AND c.slug = ?";
        $params[] = $citySlug;
    }
    if ($difficulty) {
        $sql .= " AND r.difficulty = ?";
        $params[] = $difficulty;
    }
    if ($season) {
        $sql .= " AND r.season = ?";
        $params[] = $season;
    }
    if ($search) {
        $sql .= " AND (r.title LIKE ? OR r.description LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY r.is_premium DESC, r.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $items,
        'total'   => count($items),
    ]);
}

function recipes_get(PDO $pdo, string $slug): void
{
    $stmt = $pdo->prepare("
        SELECT r.*, c.slug AS city_slug, c.name AS city_name
        FROM local_recipes r
        JOIN local_cities c ON c.id = r.city_id
        WHERE r.slug = ? AND r.status = 'published'
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Recette non trouvée']);
        return;
    }

    // ingredients
    $stmt = $pdo->prepare("
        SELECT id, name, quantity, unit, is_local, is_optional, sort_order
        FROM local_recipe_ingredients
        WHERE recipe_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$recipe['id']]);
    $recipe['ingredients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // steps
    $stmt = $pdo->prepare("
        SELECT id, step_number, instruction
        FROM local_recipe_steps
        WHERE recipe_id = ?
        ORDER BY step_number ASC
    ");
    $stmt->execute([$recipe['id']]);
    $recipe['steps'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // linked artisans
    $stmt = $pdo->prepare("
        SELECT a.id, a.company_name, a.email, a.website, a.phone
        FROM local_artisans a
        JOIN local_recipe_artisans ra ON ra.artisan_id = a.id
        WHERE ra.recipe_id = ? AND a.status = 'active'
    ");
    $stmt->execute([$recipe['id']]);
    $recipe['artisans'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // variants
    $stmt = $pdo->prepare("
        SELECT id, title, slug, description, is_incomplete, submitted_by, created_at
        FROM local_recipes
        WHERE parent_recipe_id = ? AND status = 'published'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$recipe['id']]);
    $recipe['variants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        $usr = $pdo->prepare("SELECT id FROM local_users WHERE session_token = ? AND session_exp > NOW() LIMIT 1");
        $usr->execute([$token]);
        $uid = $usr->fetchColumn();
        if ($uid) {
            gamificationRecordAction($pdo, (int)$uid, 'testimonial_view', "recipe:{$recipe['id']}", ['recipe_id' => $recipe['id']]);
        }
    }

    echo json_encode(['success' => true, 'data' => $recipe]);
}

function recipes_create(PDO $pdo, array $body): void
{
    $citySlug = $body['city_slug'] ?? 'livry';
    $title    = trim($body['title'] ?? '');
    $description = trim($body['description'] ?? '');
    $ingredients = $body['ingredients'] ?? [];
    $steps    = $body['steps'] ?? [];

    if (!$title || !$description || empty($ingredients) || empty($steps)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Titre, description, ingrédients et étapes requis']);
        return;
    }

    $cityStmt = $pdo->prepare("SELECT id FROM local_cities WHERE slug = ? LIMIT 1");
    $cityStmt->execute([$citySlug]);
    $city = $cityStmt->fetch(PDO::FETCH_ASSOC);
    if (!$city) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Ville inconnue']);
        return;
    }
    $cityId = (int)$city['id'];

    $baseSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $title)));
    $baseSlug = trim($baseSlug, '-');
    $slug = $baseSlug;
    $counter = 1;
    while (true) {
        $check = $pdo->prepare("SELECT id FROM local_recipes WHERE slug = ? LIMIT 1");
        $check->execute([$slug]);
        if (!$check->fetch()) break;
        $slug = $baseSlug . '-' . $counter++;
    }

    $difficulty = in_array($body['difficulty'] ?? '', ['very_easy','easy','medium','hard'], true)
        ? $body['difficulty']
        : 'easy';
    $season = in_array($body['season'] ?? '', ['spring','summer','autumn','winter','all'], true)
        ? $body['season']
        : 'all';

    $parentRecipeId = !empty($body['parent_recipe_id']) ? (int)$body['parent_recipe_id'] : null;

    $pdo->prepare("
        INSERT INTO local_recipes
            (city_id, title, slug, description, image_url, prep_time_minutes, cook_time_minutes,
             servings, difficulty, season, is_premium, is_incomplete, parent_recipe_id, submitted_by, submitter_email)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $cityId,
        $title,
        $slug,
        $description,
        $body['image_url'] ?? null,
        (int)($body['prep_time_minutes'] ?? 0),
        (int)($body['cook_time_minutes'] ?? 0),
        (int)($body['servings'] ?? 1),
        $difficulty,
        $season,
        !empty($body['is_premium']) ? 1 : 0,
        !empty($body['is_incomplete']) ? 1 : 0,
        $parentRecipeId,
        trim($body['submitted_by'] ?? 'Anonyme'),
        trim($body['submitter_email'] ?? '') ?: null,
    ]);

    $recipeId = (int)$pdo->lastInsertId();

    // ingredients
    $ingStmt = $pdo->prepare("
        INSERT INTO local_recipe_ingredients
            (recipe_id, name, quantity, unit, is_local, is_optional, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($ingredients as $idx => $ing) {
        $ingStmt->execute([
            $recipeId,
            trim($ing['name'] ?? ''),
            isset($ing['quantity']) ? (float)$ing['quantity'] : null,
            trim($ing['unit'] ?? '') ?: null,
            !empty($ing['is_local']) ? 1 : 0,
            !empty($ing['is_optional']) ? 1 : 0,
            $idx,
        ]);
    }

    // steps
    $stepStmt = $pdo->prepare("
        INSERT INTO local_recipe_steps (recipe_id, step_number, instruction)
        VALUES (?, ?, ?)
    ");
    foreach ($steps as $idx => $step) {
        $stepStmt->execute([
            $recipeId,
            $idx + 1,
            trim($step['instruction'] ?? ''),
        ]);
    }

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        $usr = $pdo->prepare("SELECT id FROM local_users WHERE session_token = ? AND session_exp > NOW() LIMIT 1");
        $usr->execute([$token]);
        $uid = $usr->fetchColumn();
        if ($uid) {
            gamificationRecordAction($pdo, (int)$uid, 'testimonial_post', "recipe:$recipeId", ['recipe_id' => $recipeId]);
        }
    }

    echo json_encode(['success' => true, 'slug' => $slug, 'message' => 'Recette publiée']);
}

function recipes_report(PDO $pdo, int $id, array $body): void
{
    $reason = trim($body['reason'] ?? '');
    $ip = clientIp();

    // Vérifier que la recette existe
    $check = $pdo->prepare("SELECT id FROM local_recipes WHERE id = ? LIMIT 1");
    $check->execute([$id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Recette non trouvée']);
        return;
    }

    // Limiter à un signalement par IP + recette sur 24h
    $recent = $pdo->prepare("
        SELECT id FROM local_recipe_reports
        WHERE recipe_id = ? AND reporter_ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
        LIMIT 1
    ");
    $recent->execute([$id, $ip]);
    if ($recent->fetch()) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Vous avez déjà signalé cette recette récemment.']);
        return;
    }

    $pdo->prepare("INSERT INTO local_recipe_reports (recipe_id, reason, reporter_ip) VALUES (?, ?, ?)")
        ->execute([$id, $reason, $ip]);

    $pdo->prepare("UPDATE local_recipes SET status = 'reported' WHERE id = ?")
        ->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Signalement envoyé']);
}

function recipes_suggest(PDO $pdo, int $id, array $body): void
{
    $body['parent_recipe_id'] = $id;
    $body['is_incomplete'] = false;
    $body['title'] = ($body['title'] ?? '') ?: ('Variante de recette #' . $id);
    recipes_create($pdo, $body);
}
