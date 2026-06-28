<?php

function websiteTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $key = 'table:' . $table;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        $cache[$key] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function websiteColumnExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE " . $pdo->quote($column));
        $cache[$key] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function websiteSupportsStructuredTables(PDO $pdo): bool
{
    static $supported = null;
    if ($supported !== null) {
        return $supported;
    }
    try {
        $supported = websiteTableExists($pdo, 'site_components') && websiteTableExists($pdo, 'site_component_items');
    } catch (Throwable $e) {
        $supported = false;
    }
    return $supported;
}

function websiteSupportsExtendedSiteColumns(PDO $pdo): bool
{
    return websiteColumnExists($pdo, 'sites', 'title')
        && websiteColumnExists($pdo, 'sites', 'slug')
        && websiteColumnExists($pdo, 'sites', 'activity_key')
        && websiteColumnExists($pdo, 'sites', 'template_key')
        && websiteColumnExists($pdo, 'sites', 'asset_bytes')
        && websiteColumnExists($pdo, 'sites', 'last_generated_at');
}

function fetchWebsiteRecentStories(PDO $pdo, int $tenantId, array $feed = []): array
{
    if (!websiteSupportsStructuredTables($pdo)) {
        return [];
    }

    $maxItems = max(1, min(15, (int) ($feed['maxItems'] ?? 15)));
    $status = (string) ($feed['status'] ?? 'published');
    $projetId = !empty($feed['projet_id']) ? (int) $feed['projet_id'] : (!empty($feed['chantierId']) ? (int) $feed['chantierId'] : null);

    $sql = "
        SELECT
            s.id,
            s.projet_id,
            s.title,
            s.story_text,
            s.summary,
            s.status,
            s.published_at,
            p.nom AS projet_name,
            p.reference AS projet_reference,
            cl.societe AS client_name,
            m.id AS cover_media_id,
            m.file_path,
            m.youtube_token,
            m.youtube_url,
            m.thumbnail_url,
            m.media_kind,
            m.source_type
        FROM projet_stories s
        JOIN projets p ON p.id = s.projet_id
        LEFT JOIN clients cl ON cl.id = s.client_id
        LEFT JOIN projet_medias m ON m.id = s.cover_media_id
        WHERE s.tenant_id = ?
    ";
    $params = [$tenantId];

    if ($status !== 'all') {
        $sql .= " AND s.status = ? ";
        $params[] = $status;
    }
    if ($projetId) {
        $sql .= " AND s.projet_id = ? ";
        $params[] = $projetId;
    }

    $sql .= " ORDER BY COALESCE(s.published_at, s.created_at) DESC LIMIT {$maxItems}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!empty($feed['includeMedias']) && !empty($results)) {
        foreach ($results as &$row) {
            $mediaStmt = $pdo->prepare("
                SELECT m.* 
                FROM projet_medias m
                JOIN projet_story_medias sm ON sm.media_id = m.id
                WHERE sm.story_id = ?
                ORDER BY sm.position ASC, m.id ASC
            ");
            $mediaStmt->execute([$row['id']]);
            $row['medias'] = $mediaStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    return $results;
}

/**
 * Wrapper that normalises recentStoriesFeed (old = array of URLs, new = config object)
 * and computes a combined maxItems for both recent_stories and story_showcase.
 */
function fetchWebsiteRecentStoriesForConfig(PDO $pdo, int $tenantId, array $config): array
{
    $feed = $config['recentStoriesFeed'] ?? [];

    // Legacy format: array of URL strings → ignore, fetch all published
    if (isset($feed[0]) && is_string($feed[0])) {
        $feed = [];
    }

    // Ensure it is an assoc array
    if (!is_array($feed)) {
        $feed = [];
    }

    // Compute combined maxItems (max of recent_stories limit and story_showcase limit)
    $recentMax   = (int) ($feed['maxItems'] ?? 6);
    $showcaseMax = (int) ($config['storyShowcaseMaxItems'] ?? 3);
    $combined    = max($recentMax, $showcaseMax, 1);

    $feed['maxItems']      = $combined;
    $feed['status']        = 'published';
    $feed['includeMedias'] = true;

    return fetchWebsiteRecentStories($pdo, $tenantId, $feed);
}

function buildWebsiteStructuredConfig(PDO $pdo, int $tenantId, array $site): array
{
    $baseConfig = json_decode($site['config'] ?? '[]', true) ?: [];
    if (!websiteSupportsStructuredTables($pdo)) {
        return $baseConfig;
    }

    $stmt = $pdo->prepare("SELECT * FROM site_components WHERE tenant_id = ? AND site_id = ? ORDER BY position ASC, id ASC");
    $stmt->execute([$tenantId, (int) $site['id']]);
    $components = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$components) {
        return $baseConfig;
    }

    $enabledComponents = [];
    foreach ($components as $component) {
        if (!empty($component['is_enabled'])) {
            $enabledComponents[] = $component['component_key'];
        }
        $settings = json_decode($component['settings_json'] ?? 'null', true);
        if (!is_array($settings)) {
            $settings = [];
        }

        $itemsStmt = $pdo->prepare("SELECT payload_json FROM site_component_items WHERE tenant_id = ? AND site_component_id = ? ORDER BY position ASC, id ASC");
        $itemsStmt->execute([$tenantId, (int) $component['id']]);
        $items = [];
        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $itemRow) {
            $payload = json_decode($itemRow['payload_json'] ?? 'null', true);
            if (is_array($payload)) {
                $items[] = $payload;
            }
        }

        switch ($component['component_key']) {
            case 'services':
                $baseConfig['services'] = $items ?: ($baseConfig['services'] ?? []);
                break;
            case 'testimonials':
                $baseConfig['testimonials'] = $items ?: ($baseConfig['testimonials'] ?? []);
                break;
            case 'faq':
                $baseConfig['faqs'] = $items ?: ($baseConfig['faqs'] ?? []);
                break;
            case 'featured_products':
                $baseConfig['featuredProducts'] = $items ?: ($baseConfig['featuredProducts'] ?? []);
                break;
            case 'team':
                $baseConfig['teamMembers'] = $items ?: ($baseConfig['teamMembers'] ?? []);
                break;
            case 'before_after':
                $baseConfig['beforeAfterItems'] = $items ?: ($baseConfig['beforeAfterItems'] ?? []);
                break;
            case 'opening_hours':
                $baseConfig['openingHours'] = $items ?: ($baseConfig['openingHours'] ?? []);
                break;
            case 'recent_stories':
                $baseConfig['recentStoriesFeed'] = array_merge($baseConfig['recentStoriesFeed'] ?? [], $settings);
                // Backwards compatibility for chantierId -> projet_id
                if (isset($baseConfig['recentStoriesFeed']['chantierId'])) {
                    $baseConfig['recentStoriesFeed']['projet_id'] = $baseConfig['recentStoriesFeed']['chantierId'];
                }
                $baseConfig['recentStories'] = fetchWebsiteRecentStories($pdo, $tenantId, $baseConfig['recentStoriesFeed']);
                break;
        }
    }

    $baseConfig['enabledComponents'] = array_values(array_unique($enabledComponents));
    return $baseConfig;
}

function syncWebsiteStructuredData(PDO $pdo, int $tenantId, int $siteId, array $config): void
{
    if (!websiteSupportsStructuredTables($pdo)) {
        return;
    }

    $componentMap = [
        'services' => ['items' => $config['services'] ?? [], 'settings' => null],
        'testimonials' => ['items' => $config['testimonials'] ?? [], 'settings' => null],
        'faq' => ['items' => $config['faqs'] ?? [], 'settings' => null],
        'featured_products' => ['items' => $config['featuredProducts'] ?? [], 'settings' => null],
        'team' => ['items' => $config['teamMembers'] ?? [], 'settings' => null],
        'before_after' => ['items' => $config['beforeAfterItems'] ?? [], 'settings' => null],
        'opening_hours' => ['items' => $config['openingHours'] ?? [], 'settings' => null],
        'recent_stories' => ['items' => [], 'settings' => $config['recentStoriesFeed'] ?? ['maxItems' => 15, 'status' => 'published', 'projet_id' => null]],
    ];

    $deleteStmt = $pdo->prepare("DELETE FROM site_component_items WHERE tenant_id = ? AND site_component_id IN (SELECT id FROM (SELECT id FROM site_components WHERE tenant_id = ? AND site_id = ?) scoped)");
    $deleteStmt->execute([$tenantId, $tenantId, $siteId]);
    $pdo->prepare("DELETE FROM site_components WHERE tenant_id = ? AND site_id = ?")->execute([$tenantId, $siteId]);
    $pdo->prepare("DELETE FROM site_story_feeds WHERE tenant_id = ? AND site_id = ?")->execute([$tenantId, $siteId]);

    $enabledComponents = array_values(array_unique(array_map('strval', $config['enabledComponents'] ?? [])));
    $position = 0;
    foreach ($enabledComponents as $componentKey) {
        $definition = $componentMap[$componentKey] ?? ['items' => [], 'settings' => null];
        $settingsJson = $definition['settings'] !== null
            ? json_encode($definition['settings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $stmt = $pdo->prepare("INSERT INTO site_components (site_id, tenant_id, component_key, position, is_enabled, settings_json) VALUES (?, ?, ?, ?, 1, ?)");
        $stmt->execute([$siteId, $tenantId, $componentKey, $position++, $settingsJson]);
        $siteComponentId = (int) $pdo->lastInsertId();

        foreach (($definition['items'] ?? []) as $itemPosition => $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemStmt = $pdo->prepare("INSERT INTO site_component_items (site_component_id, tenant_id, item_key, position, payload_json) VALUES (?, ?, ?, ?, ?)");
            $itemStmt->execute([
                $siteComponentId,
                $tenantId,
                null,
                $itemPosition,
                json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        if ($componentKey === 'recent_stories') {
            $feed = array_merge(['maxItems' => 15, 'status' => 'published', 'projet_id' => null, 'chantierId' => null], is_array($definition['settings']) ? $definition['settings'] : []);
            $projetId = !empty($feed['projet_id']) ? (int) $feed['projet_id'] : (!empty($feed['chantierId']) ? (int) $feed['chantierId'] : null);
            $feedStmt = $pdo->prepare("INSERT INTO site_story_feeds (site_id, tenant_id, component_key, projet_id, max_items, story_status) VALUES (?, ?, 'recent_stories', ?, ?, ?)");
            $feedStmt->execute([
                $siteId,
                $tenantId,
                $projetId,
                max(1, min(15, (int) ($feed['max_items'] ?? $feed['maxItems'] ?? 15))),
                in_array(($feed['status'] ?? 'published'), ['published', 'draft', 'all'], true) ? $feed['status'] : 'published',
            ]);
        }
    }
}
