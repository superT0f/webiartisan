<?php
/**
 * WebiArtisan — Testimonials helpers
 */

require_once __DIR__ . '/UserAuth.php';

const TESTIMONIAL_STATUSES = ['pending', 'approved', 'rejected', 'flagged'];

function testimonials_can_user_testify(PDO $pdo, int $userId, int $artisanId): bool
{
    // User must exist and artisan must be active
    $stmt = $pdo->prepare("
        SELECT 1 FROM local_artisans
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$artisanId]);
    return $stmt->fetch() !== false;
}

function testimonials_get_templates(PDO $pdo, ?string $serviceKey = null): array
{
    $sql = "SELECT `key`, label_fr, icon, testimonial_templates FROM local_service_catalog WHERE is_active = 1";
    $params = [];
    if ($serviceKey) {
        $sql .= " AND `key` = ?";
        $params[] = $serviceKey;
    }
    $sql .= " ORDER BY label_fr ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_map(function ($row) {
        $templates = json_decode($row['testimonial_templates'] ?? '[]', true);
        return [
            'key' => $row['key'],
            'label' => $row['label_fr'],
            'icon' => $row['icon'],
            'templates' => is_array($templates) ? $templates : [],
        ];
    }, $rows);
}

function testimonials_enrich_with_user(PDO $pdo, array $testimonial): array
{
    $stmt = $pdo->prepare("
        SELECT id, display_name, avatar_type, avatar_url, avatar_gender
        FROM local_users WHERE id = ?
    ");
    $stmt->execute([(int)$testimonial['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $testimonial['author'] = [
        'id' => (int)($user['id'] ?? $testimonial['user_id']),
        'display_name' => $user['display_name'] ?? null,
        'avatar_url' => $user['avatar_url'] ?? null,
    ];
    return $testimonial;
}
