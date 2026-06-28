<?php
/**
 * WebIArtisan API — Route : Cities
 * Endpoints publics pour les villes et leurs POI
 *
 * GET  /cities              — Liste des villes actives
 * GET  /cities/{slug}       — Détail d'une ville
 * GET  /cities/{slug}/pois  — POI d'une ville
 * GET  /cities/{slug}/artisans — Artisans d'une ville (raccourci)
 */

// $pdo, $action, $param, $method disponibles depuis index.php

switch ($method) {
    case 'GET':
        if ($action === '') {
            // GET /cities — Liste toutes les villes actives
            cities_list($pdo);
        } elseif ($action && !$param) {
            // GET /cities/{slug} — Détail d'une ville
            city_get($pdo, $action);
        } elseif ($action && $param === 'pois') {
            // GET /cities/{slug}/pois — POI d'une ville
            city_pois_list($pdo, $action);
        } elseif ($action && $param === 'artisans') {
            // GET /cities/{slug}/artisans — Artisans d'une ville
            city_artisans_list($pdo, $action);
        } elseif ($action && $param === 'schedules') {
            // GET /cities/{slug}/schedules — Horaires d'une ville (tous POI)
            city_schedules_list($pdo, $action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

// -------------------------------------------------------------------
// Fonctions
// -------------------------------------------------------------------

function cities_list(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        SELECT
            c.id, c.slug, c.name, c.postal_code, c.department, c.region,
            c.latitude, c.longitude, c.population, c.description,
            c.subdomain,
            COUNT(DISTINCT a.id) AS artisan_count
        FROM local_cities c
        LEFT JOIN local_artisans a ON a.city_id = c.id AND a.status = 'active'
        WHERE c.is_active = 1
        GROUP BY c.id
        ORDER BY c.name ASC
    ");
    $stmt->execute();
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $cities,
        'total'   => count($cities),
    ]);
}

function city_get(PDO $pdo, string $slug): void
{
    $stmt = $pdo->prepare("
        SELECT
            c.*,
            COUNT(DISTINCT a.id) AS artisan_count,
            COUNT(DISTINCT p.id) AS poi_count
        FROM local_cities c
        LEFT JOIN local_artisans a ON a.city_id = c.id AND a.status = 'active'
        LEFT JOIN local_pois p ON p.city_id = c.id AND p.is_active = 1
        WHERE c.slug = ? AND c.is_active = 1
        GROUP BY c.id
    ");
    $stmt->execute([$slug]);
    $city = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$city) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Ville non trouvée']);
        return;
    }

    echo json_encode(['success' => true, 'data' => $city]);
}

function city_pois_list(PDO $pdo, string $slug): void
{
    // Filtre optionnel par type
    $typeFilter = $_GET['type'] ?? null;
    $allowedTypes = [
        'mairie','piscine','bibliotheque','mediatheque','cinema','dechetterie',
        'poste','supermarche','transport','ecole','college','lycee',
        'hopital','clinique','pharmacie','eglise','monument','parc','sport','autre'
    ];

    $sql = "
        SELECT
            p.*,
            JSON_ARRAYAGG(
                JSON_OBJECT(
                    'id', s.id,
                    'day_of_week', s.day_of_week,
                    'open_time', s.open_time,
                    'close_time', s.close_time,
                    'break_start', s.break_start,
                    'break_end', s.break_end,
                    'is_closed', s.is_closed,
                    'notes', s.notes
                )
            ) AS schedules
        FROM local_pois p
        JOIN local_cities c ON p.city_id = c.id
        LEFT JOIN local_schedules s ON s.poi_id = p.id
            AND (s.period_start IS NULL OR s.period_start <= CURDATE())
            AND (s.period_end IS NULL OR s.period_end >= CURDATE())
        WHERE c.slug = ? AND p.is_active = 1
    ";
    $params = [$slug];

    if ($typeFilter && in_array($typeFilter, $allowedTypes)) {
        $sql .= " AND p.type = ?";
        $params[] = $typeFilter;
    }

    $sql .= " GROUP BY p.id ORDER BY p.sort_order ASC, p.name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pois = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Décoder les schedules JSON
    foreach ($pois as &$poi) {
        $poi['schedules'] = json_decode($poi['schedules'] ?? 'null', true) ?? [];
        // Filtrer les entrées nulles
        $poi['schedules'] = array_filter($poi['schedules'], fn($s) => $s['id'] !== null);
        $poi['schedules'] = array_values($poi['schedules']);
        // Ajouter l'état ouvert/fermé actuel
        $poi['is_open_now'] = poi_is_open_now($poi['schedules']);
    }

    echo json_encode([
        'success' => true,
        'data'    => $pois,
        'total'   => count($pois),
    ]);
}

function city_artisans_list(PDO $pdo, string $slug): void
{
    $categoryFilter = $_GET['category'] ?? null;
    $limit  = min((int)($_GET['limit'] ?? 20), 100);
    $offset = max((int)($_GET['offset'] ?? 0), 0);

    $sql = "
        SELECT
            a.id, a.company_name, a.description, a.phone, a.email,
            a.website, a.address, a.latitude, a.longitude,
            a.logo_url, a.cover_url, a.is_verified, a.is_featured,
            a.view_count,
            cat.slug AS category_slug, cat.name AS category_name, cat.icon AS category_icon,
            COALESCE(AVG(r.rating), 0) AS rating_avg,
            COUNT(DISTINCT r.id)       AS rating_count
        FROM local_artisans a
        JOIN local_cities c ON a.city_id = c.id
        LEFT JOIN local_categories cat ON a.category_id = cat.id
        LEFT JOIN local_reviews r ON r.artisan_id = a.id AND r.is_approved = 1
        WHERE c.slug = ? AND a.status = 'active'
    ";
    $params = [$slug];

    if ($categoryFilter) {
        $sql .= " AND cat.slug = ?";
        $params[] = $categoryFilter;
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

    // Formater les données
    foreach ($artisans as &$art) {
        $art['rating_avg']   = round((float)$art['rating_avg'], 1);
        $art['rating_count'] = (int)$art['rating_count'];
        $art['is_featured']  = (bool)$art['is_featured'];
        $art['is_verified']  = (bool)$art['is_verified'];
    }

    echo json_encode([
        'success' => true,
        'data'    => $artisans,
        'total'   => count($artisans),
        'limit'   => $limit,
        'offset'  => $offset,
    ]);
}

function city_schedules_list(PDO $pdo, string $slug): void
{
    $stmt = $pdo->prepare("
        SELECT
            p.id AS poi_id, p.type, p.name AS poi_name,
            s.day_of_week, s.open_time, s.close_time,
            s.break_start, s.break_end, s.is_closed, s.notes
        FROM local_pois p
        JOIN local_cities c ON p.city_id = c.id
        LEFT JOIN local_schedules s ON s.poi_id = p.id
            AND (s.period_start IS NULL OR s.period_start <= CURDATE())
            AND (s.period_end IS NULL OR s.period_end >= CURDATE())
        WHERE c.slug = ? AND p.is_active = 1
        ORDER BY p.sort_order, p.name, s.day_of_week
    ");
    $stmt->execute([$slug]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Regrouper par POI
    $byPoi = [];
    foreach ($rows as $row) {
        $key = $row['poi_id'];
        if (!isset($byPoi[$key])) {
            $byPoi[$key] = [
                'poi_id'   => $row['poi_id'],
                'type'     => $row['type'],
                'name'     => $row['poi_name'],
                'is_open_now' => false,
                'schedules' => [],
            ];
        }
        if ($row['day_of_week'] !== null) {
            $byPoi[$key]['schedules'][] = [
                'day'         => (int)$row['day_of_week'],
                'open_time'   => $row['open_time'],
                'close_time'  => $row['close_time'],
                'break_start' => $row['break_start'],
                'break_end'   => $row['break_end'],
                'is_closed'   => (bool)$row['is_closed'],
                'notes'       => $row['notes'],
            ];
        }
    }

    foreach ($byPoi as &$poi) {
        $poi['is_open_now'] = poi_is_open_now($poi['schedules']);
    }

    echo json_encode([
        'success' => true,
        'data'    => array_values($byPoi),
    ]);
}

/**
 * Détermine si un POI est ouvert maintenant
 */
function poi_is_open_now(array $schedules): bool
{
    if (empty($schedules)) return false;

    // 0=Lundi en PHP: date('N')-1
    $todayIndex = (int)date('N') - 1;
    $nowTime    = date('H:i:s');

    foreach ($schedules as $s) {
        $day = isset($s['day_of_week']) ? (int)$s['day_of_week'] : (isset($s['day']) ? (int)$s['day'] : -1);
        if ($day !== $todayIndex) continue;
        if (!empty($s['is_closed'])) return false;
        if (empty($s['open_time']) || empty($s['close_time'])) return false;

        $open  = $s['open_time'];
        $close = $s['close_time'];

        if ($nowTime < $open || $nowTime > $close) return false;

        // Vérifier pause déjeuner
        if (!empty($s['break_start']) && !empty($s['break_end'])) {
            if ($nowTime >= $s['break_start'] && $nowTime <= $s['break_end']) return false;
        }

        return true;
    }

    return false;
}
