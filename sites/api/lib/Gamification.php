<?php
/**
 * WebIArtisan — Gamification engine
 */

require_once __DIR__ . '/UserAuth.php';

const XP_ACTIONS = [
    'artisan_view'         => ['xp' => 5,  'cooldown' => 'hourly', 'limit' => null],
    'testimonial_view'     => ['xp' => 3,  'cooldown' => 'daily',  'limit' => null],
    'testimonial_post'     => ['xp' => 25, 'cooldown' => 'once_per_resource', 'limit' => null],
    'game_play'            => ['xp' => 10, 'cooldown' => 'daily',  'limit' => null],
    'game_win'             => ['xp' => 20, 'cooldown' => 'daily',  'limit' => null],
    'share'                => ['xp' => 15, 'cooldown' => 'daily',  'limit' => 3],
    'daily_visit'          => ['xp' => 10, 'cooldown' => 'daily',  'limit' => 1, 'internal' => true],
    'streak_3days'         => ['xp' => 30, 'cooldown' => 'daily',  'limit' => 1, 'internal' => true],
    'poi_checkin'          => ['xp' => 100, 'cooldown' => 'none', 'limit' => null, 'internal' => true],
    'poi_checkin_recharge' => ['xp' => 10,  'cooldown' => 'none', 'limit' => null, 'internal' => true],
];

const LEVEL_TITLES = [
    1  => 'Nouveau dans le quartier',
    3  => 'Explorateur local',
    5  => 'Habitué du marché',
    10 => 'Ambassadeur du terroir',
    20 => 'Légende du village',
];

const BADGES = [
    'first_visit'    => ['name' => 'Première visite',  'condition' => 'Visiter une fiche artisan.',        'target' => 1,   'action' => 'artisan_view'],
    'curieux'        => ['name' => 'Curieux',          'condition' => 'Lire 10 témoignages.',              'target' => 10,  'action' => 'testimonial_view'],
    'ambassadeur'    => ['name' => 'Ambassadeur',      'condition' => 'Publier 3 témoignages.',            'target' => 3,   'action' => 'testimonial_post'],
    'joueur'         => ['name' => 'Joueur',           'condition' => 'Jouer 10 fois.',                    'target' => 10,  'action' => 'game_play'],
    'vainqueur'      => ['name' => 'Vainqueur',        'condition' => 'Gagner 5 récompenses.',             'target' => 5,   'action' => 'game_win'],
    'chanceux'       => ['name' => 'Chanceux',         'condition' => 'Gagner 3 fois à la suite.',         'target' => 3,   'action' => 'game_win'],
    'generous'       => ['name' => 'Généreux',         'condition' => 'Partager 5 pages.',                 'target' => 5,   'action' => 'share'],
    'faithful'       => ['name' => 'Fidèle',           'condition' => '7 jours de connexion.',             'target' => 7,   'action' => 'daily_visit'],
];

const STREAK_MILESTONE_DAYS = 3;

function gamificationUserProfile(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, email, display_name, avatar_type, avatar_url, avatar_gender, level, xp, title
        FROM local_users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user === false) {
        return null;
    }

    $badgeStmt = $pdo->prepare("
        SELECT badge_key, unlocked_at FROM local_user_badges WHERE user_id = ?
    ");
    $badgeStmt->execute([$userId]);
    $badges = $badgeStmt->fetchAll(PDO::FETCH_ASSOC);

    $streakStmt = $pdo->prepare("
        SELECT current_streak, last_visit_date
        FROM local_user_streaks
        WHERE user_id = ?
    ");
    $streakStmt->execute([$userId]);
    $streak = $streakStmt->fetch(PDO::FETCH_ASSOC);

    $xpNeeded = ((int)$user['level']) * 100;

    return [
        'id' => (int)$user['id'],
        'email' => $user['email'],
        'display_name' => $user['display_name'] ?? substr($user['email'], 0, strpos($user['email'], '@')),
        'avatar_type' => $user['avatar_type'],
        'avatar_url' => $user['avatar_url'],
        'avatar_gender' => $user['avatar_gender'],
        'level' => (int)$user['level'],
        'xp' => (int)$user['xp'],
        'xp_needed' => $xpNeeded,
        'title' => $user['title'] ?? LEVEL_TITLES[1],
        'badges' => array_map(fn($b) => ['key' => $b['badge_key'], 'name' => BADGES[$b['badge_key']]['name'] ?? $b['badge_key'], 'unlocked_at' => $b['unlocked_at']], $badges),
        'current_streak' => $streak ? (int)$streak['current_streak'] : 0,
        'last_visit_date' => $streak ? $streak['last_visit_date'] : null,
    ];
}

function gamificationRecordAction(PDO $pdo, int $userId, string $actionKey, ?string $resourceKey = null, ?array $metadata = null, bool $inTransaction = false, bool $allowInternal = false): ?array
{
    if (!isset(XP_ACTIONS[$actionKey])) {
        return null;
    }

    if ($inTransaction && !$pdo->inTransaction()) {
        throw new RuntimeException('gamificationRecordAction called with inTransaction=true but no active transaction');
    }

    $config = XP_ACTIONS[$actionKey];
    $resourceKey = $resourceKey ?? '';

    if (!empty($config['internal']) && !$allowInternal) {
        return null;
    }

    $doRecord = function () use ($pdo, $userId, $actionKey, $config, $resourceKey, $metadata): array {
        $pdo->prepare("
            INSERT INTO local_user_cooldowns (user_id, action_key, period, resource_key, last_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_at = NOW()
        ")->execute([$userId, $actionKey, $config['cooldown'], $resourceKey]);

        $pdo->prepare("
            INSERT INTO local_user_actions (user_id, action_key, xp_amount, metadata, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ")->execute([$userId, $actionKey, $config['xp'], $metadata !== null ? json_encode($metadata, JSON_THROW_ON_ERROR) : null]);

        $pdo->prepare("
            UPDATE local_users SET xp = xp + ? WHERE id = ?
        ")->execute([$config['xp'], $userId]);

        $leveledUp = gamificationCheckLevelUp($pdo, $userId);
        $newBadges = gamificationCheckBadges($pdo, $userId, $actionKey);

        return [
            'xp_gained' => $config['xp'],
            'level_up' => $leveledUp,
            'new_badges' => $newBadges,
        ];
    };

    $run = function () use ($pdo, $userId, $actionKey, $config, $resourceKey, $doRecord): ?array {
        // Lock user row first to serialize per-user gamification writes
        $pdo->prepare("SELECT 1 FROM local_users WHERE id = ? FOR UPDATE")->execute([$userId]);

        $now = new DateTimeImmutable();

        // Cooldown check
        if ($config['cooldown'] !== 'none') {
            $stmt = $pdo->prepare("
                SELECT last_at FROM local_user_cooldowns
                WHERE user_id = ? AND action_key = ? AND resource_key = ?
            ");
            $stmt->execute([$userId, $actionKey, $resourceKey]);
            $last = $stmt->fetchColumn();

            if ($last) {
                $lastAt = new DateTimeImmutable($last);
                $canAfter = match ($config['cooldown']) {
                    'hourly' => $lastAt->modify('+1 hour'),
                    'daily' => $lastAt->modify('+1 day')->setTime(0, 0),
                    'once_per_resource' => false,
                    default => $lastAt,
                };

                if ($canAfter === false || $now < $canAfter) {
                    return null;
                }
            }
        }

        // Limit check
        if ($config['limit'] !== null && $config['cooldown'] === 'daily') {
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) FROM local_user_actions
                WHERE user_id = ? AND action_key = ? AND DATE(created_at) = CURDATE()
            ");
            $countStmt->execute([$userId, $actionKey]);
            if ((int)$countStmt->fetchColumn() >= $config['limit']) {
                return null;
            }
        }

        return $doRecord();
    };

    if ($inTransaction) {
        return $run();
    }

    $pdo->beginTransaction();
    try {
        $result = $run();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[GAMIFICATION] ' . $e->getMessage());
        return null;
    }
}

function gamificationCheckLevelUp(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT level, xp FROM local_users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $level = max(1, (int)$user['level']);
    $xp = (int)$user['xp'];
    $leveledUp = false;

    while ($xp >= $level * 100) {
        $xp -= $level * 100;
        $level++;
        $leveledUp = true;
    }

    if ($leveledUp) {
        $title = null;
        $titles = LEVEL_TITLES;
        krsort($titles);
        foreach ($titles as $lvl => $t) {
            if ($level >= $lvl) {
                $title = $t;
                break;
            }
        }

        $pdo->prepare("
            UPDATE local_users SET level = ?, xp = ?, title = ? WHERE id = ?
        ")->execute([$level, $xp, $title, $userId]);
    }

    return $leveledUp;
}

function gamificationCheckBadges(PDO $pdo, int $userId, string $actionKey): array
{
    $newBadges = [];

    foreach (BADGES as $key => $badge) {
        if ($badge['action'] !== $actionKey) {
            continue;
        }

        $stmt = $pdo->prepare("
            SELECT 1 FROM local_user_badges WHERE user_id = ? AND badge_key = ?
        ");
        $stmt->execute([$userId, $key]);
        if ($stmt->fetch()) continue;

        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM local_user_actions
            WHERE user_id = ? AND action_key = ?
        ");
        $countStmt->execute([$userId, $badge['action']]);
        $count = (int)$countStmt->fetchColumn();

        if ($count >= $badge['target']) {
            $pdo->prepare("
                INSERT INTO local_user_badges (user_id, badge_key) VALUES (?, ?)
            ")->execute([$userId, $key]);
            $newBadges[] = ['key' => $key, 'name' => $badge['name']];
        }
    }

    return $newBadges;
}

function gamificationUpdateStreak(PDO $pdo, int $userId): void
{
    $pdo->beginTransaction();
    try {
        $pdo->prepare("SELECT 1 FROM local_users WHERE id = ? FOR UPDATE")->execute([$userId]);

        $todayStmt = $pdo->query("SELECT CURDATE()");
        $today = $todayStmt->fetchColumn();

        $prevStmt = $pdo->prepare("
            SELECT current_streak, last_visit_date
            FROM local_user_streaks
            WHERE user_id = ?
            FOR UPDATE
        ");
        $prevStmt->execute([$userId]);
        $row = $prevStmt->fetch(PDO::FETCH_ASSOC);

        $prevStreak = $row ? (int)$row['current_streak'] : 0;
        $lastDate = $row ? $row['last_visit_date'] : null;
        $yesterday = DateTimeImmutable::createFromFormat('Y-m-d', $today)->modify('-1 day')->format('Y-m-d');

        if ($lastDate === $today) {
            $newStreak = $prevStreak;
            $isNewDay = false;
        } elseif ($lastDate === $yesterday) {
            $newStreak = $prevStreak + 1;
            $isNewDay = true;
        } else {
            $newStreak = 1;
            $isNewDay = true;
        }

        $upsertStmt = $pdo->prepare("
            INSERT INTO local_user_streaks (user_id, current_streak, last_visit_date)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                current_streak = VALUES(current_streak),
                last_visit_date = VALUES(last_visit_date)
        ");
        $upsertStmt->execute([$userId, $newStreak, $today]);

        if ($isNewDay) {
            gamificationRecordAction($pdo, $userId, 'daily_visit', null, null, true, true);
        }

        if ($newStreak >= STREAK_MILESTONE_DAYS && $prevStreak < STREAK_MILESTONE_DAYS) {
            gamificationRecordAction($pdo, $userId, 'streak_3days', null, null, true, true);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[GAMIFICATION-STREAK] ' . $e->getMessage());
    }
}
