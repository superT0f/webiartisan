<?php
/**
 * WebiArtisan API — Route : Inventaire joueur
 *
 * GET  /inventory                — objets utilisables (auth joueur)
 * POST /inventory/:id/activate   — activer un objet (leurre boss / énergie)
 */

require_once __DIR__ . '/../lib/UserAuth.php';
require_once __DIR__ . '/../lib/Gamification.php';
require_once __DIR__ . '/../lib/WorldObjects.php';
require_once __DIR__ . '/../lib/AppLogger.php';

switch ($method) {
    case 'GET':
        if ($action === '') {
            inventory_list($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (filter_var($action, FILTER_VALIDATE_INT) !== false && $param === 'activate') {
            inventory_activate($pdo, (int)$action, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function inventory_list(PDO $pdo): void
{
    $user = user_require_auth($pdo);
    $stmt = $pdo->prepare("
        SELECT id, object_type, acquired_at
        FROM local_user_inventory
        WHERE user_id = ? AND status = 'active'
        ORDER BY id DESC
        LIMIT 50
    ");
    $stmt->execute([(int)$user['id']]);

    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'id'          => (int)$row['id'],
            'type'        => $row['object_type'],
            'label'       => OBJECT_TYPES[$row['object_type']]['label'] ?? $row['object_type'],
            'acquired_at' => $row['acquired_at'],
        ];
    }
    echo json_encode(['success' => true, 'data' => $items]);
}

function inventory_activate(PDO $pdo, int $itemId, array $body): void
{
    $user = user_require_auth($pdo);
    $userId = (int)$user['id'];

    $lat = $body['lat'] ?? null;
    $lng = $body['lng'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng)
        || (float)$lat < -90 || (float)$lat > 90
        || (float)$lng < -180 || (float)$lng > 180) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Position invalide']);
        return;
    }
    $lat = (float)$lat;
    $lng = (float)$lng;

    $pdo->beginTransaction();
    try {
        $pdo->prepare("SELECT 1 FROM local_users WHERE id = ? FOR UPDATE")->execute([$userId]);
        $stmt = $pdo->prepare("SELECT * FROM local_user_inventory WHERE id = ? FOR UPDATE");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item || (int)$item['user_id'] !== $userId || $item['status'] !== 'active') {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Objet introuvable ou déjà utilisé']);
            return;
        }

        $data = [];
        if ($item['object_type'] === 'boss_spawner') {
            // Ville de l'objet d'origine (fallback : ville passée par le front, validée)
            $city = null;
            if ($item['source_object_id']) {
                $c = $pdo->prepare("SELECT city FROM local_world_objects WHERE id = ?");
                $c->execute([(int)$item['source_object_id']]);
                $city = $c->fetchColumn() ?: null;
            }
            if (!$city) {
                $bodyCity = trim((string)($body['city'] ?? ''));
                $chk = $pdo->prepare("SELECT 1 FROM local_cities WHERE slug = ? AND is_active = 1");
                $chk->execute([$bodyCity]);
                if ($chk->fetchColumn()) {
                    $city = $bodyCity;
                }
            }
            if (!$city) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'Ville introuvable pour invoquer le boss']);
                return;
            }
            $bossId = worldobjects_spawn_boss($pdo, $city, $lat, $lng);
            $data = ['activated' => 'boss_spawner', 'boss_id' => $bossId];
        } elseif ($item['object_type'] === 'energy_store') {
            energyAdd($pdo, $userId, ENERGY_STORE_AMOUNT);
            $data = ['activated' => 'energy_store', 'amount' => ENERGY_STORE_AMOUNT, 'energy' => energyGet($pdo, $userId, true)];
        } else {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Cet objet ne s\'active pas']);
            return;
        }

        $pdo->prepare("UPDATE local_user_inventory SET status = 'used', used_at = NOW() WHERE id = ?")
            ->execute([$itemId]);
        $pdo->commit();

        if (function_exists('app_log')) {
            app_log('info', '[INVENTORY] activate', ['user_id' => $userId, 'item_id' => $itemId, 'type' => $item['object_type']]);
        }
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[INVENTORY] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
    }
}
