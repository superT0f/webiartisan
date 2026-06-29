<?php
/**
 * WebIArtisan — Gamification engine
 */

require_once __DIR__ . '/UserAuth.php';

const XP_ACTIONS = [
    'artisan_view'      => ['xp' => 5,  'cooldown' => 'hourly', 'limit' => null],
    'spin_play'         => ['xp' => 10, 'cooldown' => 'daily',  'limit' => null],
    'qr_validate'       => ['xp' => 25, 'cooldown' => 'once_per_resource', 'limit' => null],
    'recipe_view'       => ['xp' => 3,  'cooldown' => 'daily',  'limit' => null],
    'share'             => ['xp' => 15, 'cooldown' => 'daily',  'limit' => 3],
    'review'            => ['xp' => 20, 'cooldown' => 'once_per_resource', 'limit' => null],
    'recipe_suggest'    => ['xp' => 10, 'cooldown' => 'once_per_resource', 'limit' => null],
    'daily_visit'       => ['xp' => 0,  'cooldown' => 'daily',  'limit' => 1],
    'streak_3days'      => ['xp' => 30, 'cooldown' => 'daily',  'limit' => 1],
];

const LEVEL_TITLES = [
    1  => 'Nouveau dans le quartier',
    3  => 'Explorateur local',
    5  => 'Habitulé du marché',
    10 => 'Ambassadeur du terroir',
    20 => 'Légende du village',
];

const BADGES = [
    'first_visit'   => ['name' => 'Première visite', 'condition' => 'Visiter une fiche artisan.', 'target' => 1, 'action' => 'artisan_view'],
    'gourmand'      => ['name' => 'Gourmand',        'condition' => 'Consulter 10 recettes.',      'target' => 10, 'action' => 'recipe_view'],
    'lucky'         => ['name' => 'Chanceux',        'condition' => 'Gagner 5 offres à la roue.',  'target' => 5, 'action' => 'spin_play'],
    'benefactor'    => ['name' => 'Bienfaiteur',     'condition' => 'Laisser 3 avis.',             'target' => 3, 'action' => 'review'],
    'generous'      => ['name' => 'Généreux',        'condition' => 'Partager 5 pages.',           'target' => 5, 'action' => 'share'],
    'faithful'      => ['name' => 'Fidèle',          'condition' => '7 jours de connexion.',       'target' => 7, 'action' => 'daily_visit'],
];

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
    ];
}

function gamificationRecordAction(PDO $pdo, int $userId, string $actionKey, ?string $resourceKey = null, ?array $metadata = null): ?array
{
    if (!isset(XP_ACTIONS[$actionKey])) {
        return null;
    }

    $config = XP_ACTIONS[$actionKey];
    $now = new DateTimeImmutable();
    $resourceKey = $resourceKey ?? '';

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

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO local_user_cooldowns (user_id, action_key, period, resource_key, last_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_at = NOW()
        ")->execute([$userId, $actionKey, $config['cooldown'], $resourceKey]);

        $pdo->prepare("
            INSERT INTO local_user_actions (user_id, action_key, xp_amount, metadata, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ")->execute([$userId, $actionKey, $config['xp'], $metadata ? json_encode($metadata) : null]);

        $pdo->prepare("
            UPDATE local_users SET xp = xp + ? WHERE id = ?
        ")->execute([$config['xp'], $userId]);

        $leveledUp = gamificationCheckLevelUp($pdo, $userId);
        $newBadges = gamificationCheckBadges($pdo, $userId, $actionKey);

        $pdo->commit();

        return [
            'xp_gained' => $config['xp'],
            'level_up' => $leveledUp,
            'new_badges' => $newBadges,
        ];
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

    $level = (int)$user['level'];
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
    $today = date('Y-m-d');

    $checkStmt = $pdo->prepare("
        SELECT last_visit_date FROM local_user_streaks WHERE user_id = ?
    ");
    $checkStmt->execute([$userId]);
    $lastDate = $checkStmt->fetchColumn();

    $isNewDay = $lastDate !== $today;

    $stmt = $pdo->prepare("
        INSERT INTO local_user_streaks (user_id, current_streak, last_visit_date)
        VALUES (?, 1, ?)
        ON DUPLICATE KEY UPDATE
            current_streak = CASE
                WHEN last_visit_date = DATE_SUB(?, INTERVAL 1 DAY) THEN current_streak + 1
                WHEN last_visit_date = ? THEN current_streak
                ELSE 1
            END,
            last_visit_date = ?
    ");
    $stmt->execute([$userId, $today, $today, $today, $today]);

    if ($isNewDay) {
        gamificationRecordAction($pdo, $userId, 'daily_visit');
    }

    $streakStmt = $pdo->prepare("SELECT current_streak FROM local_user_streaks WHERE user_id = ?");
    $streakStmt->execute([$userId]);
    $streak = (int)$streakStmt->fetchColumn();

    if ($streak >= 3) {
        gamificationRecordAction($pdo, $userId, 'streak_3days');
    }
}
