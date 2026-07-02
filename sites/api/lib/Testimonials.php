<?php
/**
 * WebiArtisan — Testimonials helpers
 */

require_once __DIR__ . '/UserAuth.php';

function testimonials_can_user_testify(PDO $pdo, int $userId, int $artisanId): bool
{
    $userStmt = $pdo->prepare("SELECT 1 FROM local_users WHERE id = ?");
    $userStmt->execute([$userId]);
    if ($userStmt->fetch() === false) {
        return false;
    }

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
            'label' => htmlspecialchars($row['label_fr'] ?? '', ENT_QUOTES, 'UTF-8'),
            'icon' => $row['icon'],
            'templates' => is_array($templates) ? $templates : [],
        ];
    }, $rows);
}

function testimonials_enrich_with_user(PDO $pdo, array $testimonial): array
{
    if (empty($testimonial['user_id'])) {
        $testimonial['author'] = null;
        return $testimonial;
    }

    $stmt = $pdo->prepare("
        SELECT id, display_name, avatar_url
        FROM local_users WHERE id = ?
    ");
    $stmt->execute([(int) $testimonial['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $testimonial['author'] = [
        'id' => (int) ($user['id'] ?? $testimonial['user_id']),
        'display_name' => isset($user['display_name'])
            ? htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8')
            : null,
        'avatar_url' => $user['avatar_url'] ?? null,
    ];
    return $testimonial;
}
