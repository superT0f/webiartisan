<?php
/**
 * WebiArtisan — Mini-games helpers
 */

require_once __DIR__ . '/UserAuth.php';

function games_can_artisan_create(PDO $pdo, int $artisanId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM local_game_instances
        WHERE artisan_id = ? AND is_active = 1
    ");
    $stmt->execute([$artisanId]);
    return (int)$stmt->fetchColumn() < 2;
}

function games_count_user_plays(PDO $pdo, int $instanceId, int $userId): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM local_game_plays
        WHERE game_instance_id = ? AND user_id = ?
    ");
    $stmt->execute([$instanceId, $userId]);
    return (int)$stmt->fetchColumn();
}

function games_last_play_at(PDO $pdo, int $instanceId, int $userId): ?string
{
    $stmt = $pdo->prepare("
        SELECT created_at FROM local_game_plays
        WHERE game_instance_id = ? AND user_id = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$instanceId, $userId]);
    $val = $stmt->fetchColumn();
    return $val ? (string)$val : null;
}

function games_resolve_reward(PDO $pdo, int $instanceId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, label, reward_type, reward_value, probability, stock, claimed_count
        FROM local_game_rewards
        WHERE game_instance_id = ? AND (stock IS NULL OR stock > claimed_count)
        ORDER BY id ASC
    ");
    $stmt->execute([$instanceId]);
    $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rewards)) return null;

    // For now deterministic: pick first available coupon-like reward.
    // Probabilistic selection can be added later for wheel.
    foreach ($rewards as $r) {
        if ($r['reward_type'] === 'coupon') {
            return $r;
        }
    }
    return $rewards[0];
}

function games_record_play(PDO $pdo, int $instanceId, int $userId, array $result, int $xp = 0): void
{
    $pdo->prepare("
        INSERT INTO local_game_plays (game_instance_id, user_id, result, xp_awarded)
        VALUES (?, ?, ?, ?)
    ")->execute([$instanceId, $userId, json_encode($result, JSON_THROW_ON_ERROR), $xp]);
}

function games_instance_is_playable(array $instance): bool
{
    if (!(bool)$instance['is_active']) return false;
    $now = time();
    if ($instance['starts_at'] && strtotime($instance['starts_at']) > $now) return false;
    if ($instance['ends_at'] && strtotime($instance['ends_at']) < $now) return false;
    return true;
}
