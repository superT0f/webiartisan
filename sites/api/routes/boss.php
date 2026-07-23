<?php
/**
 * WebiArtisan API — Route : Arène Big Brother
 *
 * POST /boss/:id/fight           — engager un combat (500 m max, 1 fight/spawn/joueur)
 * POST /boss/fights/:fightId/round  {game}  — démarrer une manche (quiz|mate|cards)
 * POST /boss/fights/:fightId/answer {...}   — résoudre la manche
 * GET  /boss/fights/:fightId     — état courant (reconnexion)
 */

require_once __DIR__ . '/../lib/UserAuth.php';
require_once __DIR__ . '/../lib/Gamification.php';
require_once __DIR__ . '/../lib/WorldObjects.php';
require_once __DIR__ . '/../lib/AppLogger.php';

const BOSS_ENGAGE_RANGE_M = 500.0;
const BOSS_MAX_HP = 3;
const BOSS_ROUND_XP = 25;
const BOSS_WIN_XP = 150;
const BOSS_LOSS_XP = 5;
const BOSS_LOST_ROUND_ENERGY = 5;
const BOSS_CARD_ELEMENTS = ['feu', 'eau', 'plante']; // feu > plante > eau > feu

switch ($method) {
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (filter_var($action, FILTER_VALIDATE_INT) !== false && $param === 'fight') {
            boss_fight_start($pdo, (int)$action, $body);
        } elseif ($action === 'fights' && filter_var($param, FILTER_VALIDATE_INT) !== false && ($segments[3] ?? '') === 'round') {
            boss_round_start($pdo, (int)$param, $body);
        } elseif ($action === 'fights' && filter_var($param, FILTER_VALIDATE_INT) !== false && ($segments[3] ?? '') === 'answer') {
            boss_round_answer($pdo, (int)$param, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'GET':
        if ($action === 'fights' && filter_var($param, FILTER_VALIDATE_INT) !== false) {
            boss_fight_state($pdo, (int)$param);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function boss_fight_start(PDO $pdo, int $objectId, array $body): void
{
    $user = user_require_auth($pdo);
    $userId = (int)$user['id'];

    $lat = $body['lat'] ?? null;
    $lng = $body['lng'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Position requise']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM local_world_objects WHERE id = ? AND object_type = 'big_brother'");
    $stmt->execute([$objectId]);
    $boss = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$boss || $boss['status'] !== 'active' || strtotime($boss['expires_at']) < time()) {
        http_response_code(410);
        echo json_encode(['success' => false, 'error' => 'gone', 'message' => 'Le Big Brother a fui !']);
        return;
    }

    $distance = worldobjects_distance_m((float)$lat, (float)$lng, (float)$boss['lat'], (float)$boss['lng']);
    if ($distance > BOSS_ENGAGE_RANGE_M) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'distance', 'message' => 'Trop loin du Big Brother (500 m max)', 'data' => ['distance_m' => (int)round($distance)]]);
        return;
    }

    // Fight déjà terminée ?
    $existing = $pdo->prepare("SELECT id FROM local_boss_fights WHERE user_id = ? AND object_id = ?");
    $existing->execute([$userId, $objectId]);
    if ($existing->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'already_fought', 'message' => 'Tu as déjà affronté ce Big Brother.']);
        return;
    }
    // Fight en cours ?
    $live = $pdo->prepare("SELECT id FROM local_boss_fights_live WHERE user_id = ? AND object_id = ? AND status = 'ongoing'");
    $live->execute([$userId, $objectId]);
    $liveId = $live->fetchColumn();
    if ($liveId) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'already_fighting', 'message' => 'Combat déjà en cours', 'data' => ['fight_id' => (int)$liveId]]);
        return;
    }

    $pdo->prepare("INSERT INTO local_boss_fights_live (object_id, user_id) VALUES (?, ?)")
        ->execute([$objectId, $userId]);
    $fightId = (int)$pdo->lastInsertId();

    if (function_exists('app_log')) {
        app_log('info', '[BOSS] fight start', ['user_id' => $userId, 'object_id' => $objectId, 'fight_id' => $fightId]);
    }

    echo json_encode(['success' => true, 'data' => [
        'fight_id' => $fightId,
        'boss_hp' => BOSS_MAX_HP,
        'player_hp' => BOSS_MAX_HP,
        'rounds_won' => 0,
        'rounds_lost' => 0,
        'status' => 'ongoing',
    ]]);
}

function boss_fight_state(PDO $pdo, int $fightId): void
{
    $user = user_require_auth($pdo);
    $fight = boss_load_fight($pdo, $fightId, (int)$user['id']);
    if (!$fight) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Combat introuvable']);
        return;
    }
    echo json_encode(['success' => true, 'data' => boss_public_state($fight)]);
}

function boss_load_fight(PDO $pdo, int $fightId, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM local_boss_fights_live WHERE id = ? AND user_id = ?");
    $stmt->execute([$fightId, $userId]);
    $fight = $stmt->fetch(PDO::FETCH_ASSOC);
    return $fight ?: null;
}

function boss_public_state(array $fight): array
{
    return [
        'fight_id' => (int)$fight['id'],
        'boss_hp' => (int)$fight['boss_hp'],
        'player_hp' => (int)$fight['player_hp'],
        'rounds_won' => (int)$fight['rounds_won'],
        'rounds_lost' => (int)$fight['rounds_lost'],
        'status' => $fight['status'],
    ];
}

function boss_round_start(PDO $pdo, int $fightId, array $body): void
{
    $user = user_require_auth($pdo);
    $fight = boss_load_fight($pdo, $fightId, (int)$user['id']);
    if (!$fight || $fight['status'] !== 'ongoing') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'bad_state', 'message' => 'Pas de manche à jouer']);
        return;
    }

    $game = $body['game'] ?? '';
    if (!in_array($game, ['quiz', 'mate', 'cards'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Jeu inconnu']);
        return;
    }

    $content = null;
    $payload = null;

    if ($game === 'quiz') {
        $q = $pdo->query("SELECT * FROM local_quiz_questions ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $payload = ['question_id' => (int)$q['id'], 'answer_index' => (int)$q['answer_index']];
        $content = ['question' => $q['question'], 'choices' => json_decode($q['choices'], true)];
    } elseif ($game === 'mate') {
        $m = $pdo->query("SELECT * FROM local_mate_positions ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $payload = ['position_id' => (int)$m['id'], 'solution_uci' => $m['solution_uci']];
        $content = ['fen' => $m['fen']];
    } else {
        $cards = [];
        for ($i = 0; $i < 3; $i++) {
            $cards[] = ['value' => random_int(2, 10), 'element' => BOSS_CARD_ELEMENTS[array_rand(BOSS_CARD_ELEMENTS)]];
        }
        // Le Big Brother triche : deck biaisé vers les fortes valeurs
        $bossCard = ['value' => random_int(5, 10), 'element' => BOSS_CARD_ELEMENTS[array_rand(BOSS_CARD_ELEMENTS)]];
        $payload = ['cards' => $cards, 'boss_card' => $bossCard];
        $content = ['cards' => $cards];
    }

    $pdo->prepare("UPDATE local_boss_fights_live SET current_game = ?, current_payload = ? WHERE id = ?")
        ->execute([$game, json_encode($payload, JSON_THROW_ON_ERROR), $fightId]);

    echo json_encode(['success' => true, 'data' => array_merge(boss_public_state($fight), [
        'game' => $game,
        'content' => $content,
    ])]);
}

function boss_round_answer(PDO $pdo, int $fightId, array $body): void
{
    $user = user_require_auth($pdo);
    $userId = (int)$user['id'];
    $fight = boss_load_fight($pdo, $fightId, $userId);
    if (!$fight || $fight['status'] !== 'ongoing' || !$fight['current_game']) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'bad_state', 'message' => 'Aucune manche en attente']);
        return;
    }

    $payload = json_decode($fight['current_payload'], true);
    $game = $fight['current_game'];
    $roundWon = false;
    $reveal = null;

    if ($game === 'quiz') {
        $answerIndex = $body['answer_index'] ?? -1;
        $roundWon = is_int($answerIndex) && $answerIndex === $payload['answer_index'];
        $reveal = ['answer_index' => $payload['answer_index']];
    } elseif ($game === 'mate') {
        $move = strtolower(trim((string)($body['move'] ?? '')));
        $roundWon = $move !== '' && $move === $payload['solution_uci'];
        $reveal = ['solution_uci' => $payload['solution_uci']];
    } else {
        $cardIndex = $body['card_index'] ?? -1;
        if (!is_int($cardIndex) || !isset($payload['cards'][$cardIndex])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'bad_state', 'message' => 'Carte invalide']);
            return;
        }
        $roundWon = boss_card_beats($payload['cards'][$cardIndex], $payload['boss_card']);
        $reveal = ['boss_card' => $payload['boss_card']];
    }

    $bossHp = (int)$fight['boss_hp'];
    $playerHp = (int)$fight['player_hp'];
    $roundsWon = (int)$fight['rounds_won'];
    $roundsLost = (int)$fight['rounds_lost'];
    $status = 'ongoing';
    $xpTotal = 0;

    $pdo->beginTransaction();
    try {
        $pdo->prepare("SELECT 1 FROM local_users WHERE id = ? FOR UPDATE")->execute([$userId]);

        if ($roundWon) {
            $bossHp--;
            $roundsWon++;
            gamificationRecordAction($pdo, $userId, 'boss_round_won', "boss_fight:$fightId", ['fight_id' => $fightId], true, true);
            $xpTotal += BOSS_ROUND_XP;
        } else {
            $playerHp--;
            $roundsLost++;
            energySpend($pdo, $userId, BOSS_LOST_ROUND_ENERGY);
        }

        $result = null;
        if ($bossHp <= 0) {
            $status = 'won';
            $result = 'win';
            gamificationRecordAction($pdo, $userId, 'boss_win', "boss:{$fight['object_id']}", ['fight_id' => $fightId], true, true, BOSS_WIN_XP);
            $xpTotal += BOSS_WIN_XP;
            // Sa pollution s'efface : 3 déchets de la ville nettoyés
            $cityStmt = $pdo->prepare("SELECT city FROM local_world_objects WHERE id = ?");
            $cityStmt->execute([(int)$fight['object_id']]);
            $bossCity = $cityStmt->fetchColumn();
            if ($bossCity) {
                $cleanStmt = $pdo->prepare("
                    UPDATE local_world_objects SET status = 'expired'
                    WHERE city = ? AND status = 'active' AND object_type IN ('dechet','canette','papier')
                    ORDER BY created_at ASC LIMIT 3
                ");
                $cleanStmt->execute([$bossCity]);
            }
            $pdo->prepare("INSERT INTO local_boss_fights (user_id, object_id, result, rounds_won, rounds_lost, xp_awarded) VALUES (?, ?, 'win', ?, ?, ?)")
                ->execute([$userId, (int)$fight['object_id'], $roundsWon, $roundsLost, $xpTotal]);
        } elseif ($playerHp <= 0) {
            $status = 'lost';
            $result = 'loss';
            gamificationRecordAction($pdo, $userId, 'boss_loss', "boss:{$fight['object_id']}", ['fight_id' => $fightId], true, true, BOSS_LOSS_XP);
            $xpTotal += BOSS_LOSS_XP;
            $pdo->prepare("INSERT INTO local_boss_fights (user_id, object_id, result, rounds_won, rounds_lost, xp_awarded) VALUES (?, ?, 'loss', ?, ?, ?)")
                ->execute([$userId, (int)$fight['object_id'], $roundsWon, $roundsLost, $xpTotal]);
        }

        $pdo->prepare("
            UPDATE local_boss_fights_live
            SET boss_hp = ?, player_hp = ?, rounds_won = ?, rounds_lost = ?, status = ?, current_game = NULL, current_payload = NULL
            WHERE id = ?
        ")->execute([$bossHp, $playerHp, $roundsWon, $roundsLost, $status, $fightId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[BOSS] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
        return;
    }

    if (function_exists('app_log') && $result !== null) {
        app_log('info', '[BOSS] fight end', ['user_id' => $userId, 'fight_id' => $fightId, 'result' => $result, 'xp' => $xpTotal]);
    }

    echo json_encode(['success' => true, 'data' => [
        'fight_id' => $fightId,
        'round_won' => $roundWon,
        'boss_hp' => $bossHp,
        'player_hp' => $playerHp,
        'rounds_won' => $roundsWon,
        'rounds_lost' => $roundsLost,
        'status' => $status,
        'result' => $result,
        'reveal' => $reveal,
    ]]);
}

/** feu > plante, plante > eau, eau > feu ; à élément égal, la plus forte valeur. */
function boss_card_beats(array $player, array $boss): bool
{
    $wins = ['feu' => 'plante', 'plante' => 'eau', 'eau' => 'feu'];
    if ($player['element'] !== $boss['element']) {
        return ($wins[$player['element']] ?? '') === $boss['element'];
    }
    return $player['value'] > $boss['value'];
}
