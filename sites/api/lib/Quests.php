<?php
/**
 * WebIArtisan — Quêtes quotidiennes
 * Assignation paresseuse de 3 quêtes/jour, progression, claim manuel.
 */

const DAILY_QUESTS_COUNT = 3;

function questsToday(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT q.quest_code, q.quest_date, q.progress, q.completed, q.claimed,
               d.label, d.target_count, d.reward_xp
        FROM local_user_quests q
        JOIN local_daily_quests d ON d.code = q.quest_code
        WHERE q.user_id = ? AND q.quest_date = CURDATE()
        ORDER BY q.quest_code
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['progress'] = (int)$row['progress'];
        $row['target_count'] = (int)$row['target_count'];
        $row['reward_xp'] = (int)$row['reward_xp'];
        $row['completed'] = (bool)$row['completed'];
        $row['claimed'] = (bool)$row['claimed'];
    }
    return $rows;
}

function questsEnsureToday(PDO $pdo, int $userId): array
{
    $existing = questsToday($pdo, $userId);
    if (count($existing) >= DAILY_QUESTS_COUNT) {
        return $existing;
    }

    $have = array_column($existing, 'quest_code');
    $pool = $pdo->query("SELECT code FROM local_daily_quests")->fetchAll(PDO::FETCH_COLUMN);
    $pool = array_values(array_diff($pool, $have));
    shuffle($pool);

    $insert = $pdo->prepare("
        INSERT IGNORE INTO local_user_quests (user_id, quest_code, quest_date)
        VALUES (?, ?, CURDATE())
    ");
    foreach (array_slice($pool, 0, DAILY_QUESTS_COUNT - count($existing)) as $code) {
        $insert->execute([$userId, $code]);
    }

    return questsToday($pdo, $userId);
}

/**
 * Met à jour la progression d'une quête du jour.
 * Retourne la quête si elle vient d'être complétée, sinon null.
 * À appeler dans la transaction de l'action métier (verrou FOR UPDATE).
 */
function questsApplyProgress(PDO $pdo, int $userId, string $code, int $newProgress): ?array
{
    $stmt = $pdo->prepare("
        SELECT q.progress, q.completed, d.target_count, d.label, d.reward_xp
        FROM local_user_quests q
        JOIN local_daily_quests d ON d.code = q.quest_code
        WHERE q.user_id = ? AND q.quest_code = ? AND q.quest_date = CURDATE()
        FOR UPDATE
    ");
    $stmt->execute([$userId, $code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (bool)$row['completed']) {
        return null;
    }

    $target = (int)$row['target_count'];
    $progress = min($target, $newProgress);
    $completed = $progress >= $target ? 1 : 0;
    $pdo->prepare("
        UPDATE local_user_quests SET progress = ?, completed = ?
        WHERE user_id = ? AND quest_code = ? AND quest_date = CURDATE()
    ")->execute([$progress, $completed, $userId, $code]);

    if ($completed) {
        return [
            'quest_code' => $code,
            'label' => $row['label'],
            'reward_xp' => (int)$row['reward_xp'],
            'completed' => true,
        ];
    }
    return null;
}

function questsProgress(PDO $pdo, int $userId, string $code, int $delta = 1): ?array
{
    $stmt = $pdo->prepare("
        SELECT progress FROM local_user_quests
        WHERE user_id = ? AND quest_code = ? AND quest_date = CURDATE()
    ");
    $stmt->execute([$userId, $code]);
    $current = $stmt->fetchColumn();
    if ($current === false) {
        return null; // quête non assignée aujourd'hui
    }
    return questsApplyProgress($pdo, $userId, $code, (int)$current + $delta);
}

function questsSetProgress(PDO $pdo, int $userId, string $code, int $value): ?array
{
    return questsApplyProgress($pdo, $userId, $code, $value);
}

/** Jours consécutifs avec au moins un ramassage, en finissant aujourd'hui. */
function questsPickupStreak(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(picked_at) AS d
        FROM local_object_pickups
        WHERE user_id = ? AND picked_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY d DESC
    ");
    $stmt->execute([$userId]);
    $days = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $streak = 0;
    $expected = new DateTimeImmutable('today');
    foreach ($days as $day) {
        if ($day !== $expected->format('Y-m-d')) {
            break;
        }
        $streak++;
        $expected = $expected->modify('-1 day');
    }
    return $streak;
}
