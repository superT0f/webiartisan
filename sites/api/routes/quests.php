<?php
/**
 * WebiArtisan API — Route : Quêtes quotidiennes
 *
 * GET  /quests/today       — 3 quêtes du jour (assignation paresseuse)
 * POST /quests/:code/claim — récupérer la récompense d'une quête complétée
 */

require_once __DIR__ . '/../lib/UserAuth.php';
require_once __DIR__ . '/../lib/Gamification.php';
require_once __DIR__ . '/../lib/Quests.php';

switch ($method) {
    case 'GET':
        if ($action === 'today' || $action === '') {
            quests_today_endpoint($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        if ($action !== '' && $param === 'claim') {
            quests_claim($pdo, $action);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function quests_today_endpoint(PDO $pdo): void
{
    $user = user_require_auth($pdo);
    echo json_encode(['success' => true, 'data' => questsEnsureToday($pdo, (int)$user['id'])]);
}

function quests_claim(PDO $pdo, string $code): void
{
    $user = user_require_auth($pdo);
    $userId = (int)$user['id'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("SELECT 1 FROM local_users WHERE id = ? FOR UPDATE")->execute([$userId]);
        $stmt = $pdo->prepare("
            SELECT q.completed, q.claimed, d.reward_xp, d.label
            FROM local_user_quests q
            JOIN local_daily_quests d ON d.code = q.quest_code
            WHERE q.user_id = ? AND q.quest_code = ? AND q.quest_date = CURDATE()
            FOR UPDATE
        ");
        $stmt->execute([$userId, $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'not_found', 'message' => 'Quête non assignée aujourd\'hui']);
            return;
        }
        if (!(bool)$row['completed']) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'not_completed', 'message' => 'Quête pas encore terminée']);
            return;
        }
        if ((bool)$row['claimed']) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'already_claimed', 'message' => 'Récompense déjà récupérée']);
            return;
        }

        $xp = (int)$row['reward_xp'];
        $pdo->prepare("
            UPDATE local_user_quests SET claimed = 1
            WHERE user_id = ? AND quest_code = ? AND quest_date = CURDATE()
        ")->execute([$userId, $code]);

        $result = gamificationRecordAction(
            $pdo, $userId, 'quest_complete', 'quest:' . $code . ':' . date('Y-m-d'),
            ['quest_code' => $code, 'label' => $row['label']],
            true, true, $xp
        );

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[QUESTS] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    echo json_encode([
        'success' => true,
        'data'    => [
            'xp_awarded' => $xp,
            'level_up'   => (bool)($result['level_up'] ?? false),
            'new_badges' => $result['new_badges'] ?? [],
        ],
    ]);
}
