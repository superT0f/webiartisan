<?php

function slugifyWebsiteText(string $text): string {
    $text = trim($text);
    if ($text === '') return 'site';
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) $text = $converted;
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? 'site';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'site';
}

function getWebsiteTemplateCatalog(): array {
    return [
        [
            'key' => 'artisan-classic',
            'name' => 'Artisan Classique',
            'description' => 'Pour artisans du bâtiment, dépannage et rénovation.',
            'activity_keys' => ['artisan', 'plomberie', 'electricite', 'maconnerie', 'menuiserie', 'peinture'],
            'default_components' => ['hero', 'services', 'service_area', 'before_after', 'testimonials', 'contact'],
        ],
        [
            'key' => 'restaurant-menu',
            'name' => 'Restaurant & Carte',
            'description' => 'Pour restaurants, cafés et métiers de bouche.',
            'activity_keys' => ['restaurant', 'cafe', 'boulangerie', 'traiteur'],
            'default_components' => ['hero', 'services', 'opening_hours', 'gallery', 'testimonials', 'contact'],
        ],
        [
            'key' => 'commerce-local',
            'name' => 'Commerce Local',
            'description' => 'Pour boutiques, magasins et commerces de proximité.',
            'activity_keys' => ['commerce', 'boutique', 'beaute', 'fleuriste'],
            'default_components' => ['hero', 'services', 'featured_products', 'gallery', 'faq', 'contact'],
        ],
        [
            'key' => 'consulting-pro',
            'name' => 'Conseil & Services',
            'description' => 'Pour consultants, agences et services B2B.',
            'activity_keys' => ['service', 'consulting', 'agence', 'comptabilite'],
            'default_components' => ['hero', 'services', 'team', 'faq', 'testimonials', 'contact'],
        ],
        [
            'key' => 'portfolio-creator',
            'name' => 'Portfolio Créatif',
            'description' => 'Pour indépendants, designers et métiers créatifs.',
            'activity_keys' => ['portfolio', 'photographe', 'designer', 'blog'],
            'default_components' => ['hero', 'gallery', 'team', 'testimonials', 'contact'],
        ],
    ];
}

function getWebsiteComponentCatalog(): array {
    return [
        ['key' => 'hero', 'name' => 'Hero', 'category' => 'core', 'tier' => 'basic', 'default_enabled' => true],
        ['key' => 'services', 'name' => 'Services', 'category' => 'content', 'tier' => 'basic', 'default_enabled' => true],
        ['key' => 'gallery', 'name' => 'Galerie', 'category' => 'media', 'tier' => 'basic', 'default_enabled' => false],
        ['key' => 'testimonials', 'name' => 'Avis clients', 'category' => 'trust', 'tier' => 'basic', 'default_enabled' => false],
        ['key' => 'faq', 'name' => 'FAQ', 'category' => 'conversion', 'tier' => 'basic', 'default_enabled' => false],
        ['key' => 'contact', 'name' => 'Contact', 'category' => 'conversion', 'tier' => 'basic', 'default_enabled' => true],
        ['key' => 'map', 'name' => 'Carte', 'category' => 'local', 'tier' => 'basic', 'default_enabled' => false],
        ['key' => 'service_area', 'name' => 'Zone d’intervention', 'category' => 'local', 'tier' => 'basic', 'default_enabled' => false],
        ['key' => 'opening_hours', 'name' => 'Horaires', 'category' => 'local', 'tier' => 'basic', 'default_enabled' => false],
        ['key' => 'featured_products', 'name' => 'Produits phares', 'category' => 'commerce', 'tier' => 'basic', 'default_enabled' => false],
        ['key' => 'before_after', 'name' => 'Avant / Après', 'category' => 'showcase', 'tier' => 'basic', 'default_enabled' => false],
        ['key' => 'recent_stories', 'name' => 'Stories récentes de projet', 'category' => 'showcase', 'tier' => 'basic', 'default_enabled' => false],
        ['key' => 'story_showcase', 'name' => 'Mise en avant d’une Story', 'category' => 'showcase', 'tier' => 'basic', 'default_enabled' => false],
        ['key' => 'team', 'name' => 'Équipe', 'category' => 'trust', 'tier' => 'basic', 'default_enabled' => false],
        ['key' => 'online_booking', 'name' => 'Réservation en ligne', 'category' => 'plus', 'tier' => 'basic', 'default_enabled' => false],
    ];
}

function getWebsiteQuotaConfig(): array {
    $plan = PlanQuota::getCurrentPlan();
    $limits = PlanQuota::getLimits($plan);
    
    return [
        'max_sites' => $limits['sites'] ?? 999,
        'max_assets_bytes' => $limits['assets_size'] ?? (1024 * 1024 * 1024 * 10), // 10GB if unlimited
    ];
}

function getWebsiteBuilderFeatures(): array {
    return [
        'plus_components' => PlanQuota::hasFeature('website_plus_components'),
    ];
}

function getWebsiteTemplateByKey(string $key): ?array {
    foreach (getWebsiteTemplateCatalog() as $template) {
        if ($template['key'] === $key) {
            return $template;
        }
    }
    return null;
}

function getWebsiteComponentByKey(string $key): ?array {
    foreach (getWebsiteComponentCatalog() as $component) {
        if ($component['key'] === $key) {
            return $component;
        }
    }
    return null;
}

function getWebsiteDefaultTemplateKey(?string $activityKey = null): string {
    $activityKey = trim((string) $activityKey);
    foreach (getWebsiteTemplateCatalog() as $template) {
        if ($activityKey !== '' && in_array($activityKey, $template['activity_keys'], true)) {
            return $template['key'];
        }
    }
    return 'artisan-classic';
}

function loadWebsiteBrand(PDO $pdo, int $tenantId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$brand) {
        return null;
    }

    foreach (['colors', 'style', 'contact', 'social', 'legal'] as $field) {
        if (isset($brand[$field]) && is_string($brand[$field])) {
            $brand[$field] = json_decode($brand[$field], true) ?: [];
        }
    }

    if (!empty($brand['logo_image_url']) && strpos($brand['logo_image_url'], '/uploads/') === 0) {
        $appConfig = getAppConfig();
        $brand['logo_image_url'] = rtrim($appConfig['api_url'], '/') . $brand['logo_image_url'];
    }

    return $brand;
}

function normalizeWebsiteConfig(array $config, ?array $brand): array {
    $brandStyle = $brand['style'] ?? [];
    $brandContact = $brand['contact'] ?? [];
    $brandSocial = $brand['social'] ?? [];
    $activityKey = $config['activityKey'] ?? $config['purpose'] ?? $brandStyle['activity_category'] ?? 'artisan';
    $templateKey = $config['templateKey'] ?? getWebsiteDefaultTemplateKey($activityKey);
    $template = getWebsiteTemplateByKey($templateKey) ?? getWebsiteTemplateByKey(getWebsiteDefaultTemplateKey($activityKey));

    $enabledComponents = $config['enabledComponents'] ?? [];
    if (!is_array($enabledComponents) || !$enabledComponents) {
        $enabledComponents = $template['default_components'] ?? ['hero', 'services', 'contact'];
    }

    $enabledComponents = array_values(array_unique(array_filter(array_map('strval', $enabledComponents))));
    $recentStoriesFeed = array_merge([
        'maxItems' => 15,
        'status' => 'published',
        'projet_id' => null,
        'chantierId' => null,
    ], is_array($config['recentStoriesFeed'] ?? null) ? $config['recentStoriesFeed'] : []);

    return array_merge($config, [
        'siteTitle' => trim((string) ($config['siteTitle'] ?? $brand['company_name'] ?? 'Site sans titre')),
        'slug' => slugifyWebsiteText((string) ($config['slug'] ?? $config['siteTitle'] ?? $brand['company_name'] ?? 'site')),
        'activityKey' => $activityKey,
        'templateKey' => $template['key'] ?? 'artisan-classic',
        'enabledComponents' => $enabledComponents,
        'recentStoriesFeed' => [
            'maxItems' => max(1, min(15, (int) ($recentStoriesFeed['maxItems'] ?? 15))),
            'status' => in_array(($recentStoriesFeed['status'] ?? 'published'), ['published', 'draft', 'all'], true) ? $recentStoriesFeed['status'] : 'published',
            'projet_id' => !empty($recentStoriesFeed['projet_id']) ? (int) $recentStoriesFeed['projet_id'] : (!empty($recentStoriesFeed['chantierId']) ? (int) $recentStoriesFeed['chantierId'] : null),
        ],
        'brandSnapshot' => [
            'company_name' => $brand['company_name'] ?? '',
            'slogan' => $brand['slogan'] ?? '',
            'colors' => $brand['colors'] ?? [],
            'style' => $brandStyle,
            'contact' => $brandContact,
            'social' => $brandSocial,
            'legal' => $brand['legal'] ?? [],
        ],
        'builder' => array_merge([
            'source' => 'website-backend-v1',
            'version' => 2,
        ], is_array($config['builder'] ?? null) ? $config['builder'] : []),
        'assetBytes' => computeWebsiteAssetBytes($config),
    ]);
}

function computeWebsiteAssetBytes(array $config): int {
    $total = 0;
    
    // 1. Assets array (nouveau format)
    $assets = $config['assets'] ?? [];
    if (is_array($assets)) {
        foreach ($assets as $i => $asset) {
            if (is_array($asset) && isset($asset['size']) && is_numeric($asset['size'])) {
                $size = max(0, (int) $asset['size']);
                $total += $size;
            }
        }
    }
    
    // 2. Gallery images (ancien format - compatibilité) 
    $galleryImages = $config['galleryImages'] ?? [];
    if (is_array($galleryImages)) {
        foreach ($galleryImages as $i => $image) {
            if (is_array($image) && isset($image['url'])) {
                // Estimation taille image de galerie : ~200KB par image
                $total += 200000; // ~200KB par image de galerie
            }
        }
    }
    
    // 3. Hero images
    if (isset($config['heroImageUrl']) && $config['heroImageUrl']) {
        // Estimation taille hero image : ~150KB
        $total += 150000;
    }
    
    // 4. Logo images
    if (isset($config['logoUrl']) && $config['logoUrl']) {
        // Estimation taille logo : ~50KB
        $total += 50000;
    }
    
    return $total;
}

function validateWebsiteComponents(array $enabledComponents): array {
    $allowed = [];
    $premiumLocked = [];
    foreach ($enabledComponents as $componentKey) {
        $component = getWebsiteComponentByKey((string) $componentKey);
        if (!$component) {
            continue;
        }
        if (($component['tier'] ?? 'basic') === 'plus' && !PlanQuota::hasFeature('website_plus_components')) {
            $premiumLocked[] = $component['key'];
            continue;
        }
        $allowed[] = $component['key'];
    }

    return [
        'allowed' => array_values(array_unique($allowed)),
        'premium_locked' => array_values(array_unique($premiumLocked)),
    ];
}

function getWebsiteQuotaUsage(PDO $pdo, int $tenantId): array {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sites WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $siteCount = (int) $stmt->fetchColumn();

    // Calculer les assets des sites web
    $siteAssetsBytes = 0;
    $stmt = $pdo->prepare("SELECT token, config FROM sites WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sites as $site) {
        $config = json_decode($site['config'] ?? '[]', true) ?: [];
        $assetBytes = computeWebsiteAssetBytes($config);
        $siteAssetsBytes += $assetBytes;
    }

    // Ajouter les autres assets (logos, chantiers, etc.)
    $otherAssetsBytes = PlanQuota::getAssetsSize();
    $totalAssetsBytes = $siteAssetsBytes + $otherAssetsBytes;

    return [
        'sites' => $siteCount,
        'assets_bytes' => $totalAssetsBytes,
    ];
}

function getWebsiteQuotaSummary(PDO $pdo, int $tenantId): array {
    $limits = getWebsiteQuotaConfig();
    $usage = getWebsiteQuotaUsage($pdo, $tenantId);

    return [
        'limits' => $limits,
        'usage' => $usage,
        'remaining' => [
            'sites' => max(0, $limits['max_sites'] - $usage['sites']),
            'assets_bytes' => max(0, $limits['max_assets_bytes'] - $usage['assets_bytes']),
        ],
    ];
}

function enforceWebsiteCreationQuota(PDO $pdo, int $tenantId): void {
    $summary = getWebsiteQuotaSummary($pdo, $tenantId);
    if ($summary['usage']['sites'] >= $summary['limits']['max_sites']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'website_site_quota_reached',
            'message' => 'Limite de 3 sites atteinte pour ce compte.',
            'quota' => $summary,
        ]);
        exit;
    }
}

function enforceWebsiteAssetQuota(PDO $pdo, int $tenantId, int $incomingBytes, ?string $excludeToken = null): void {
    $stmt = $pdo->prepare("SELECT token, config FROM sites WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $existingBytes = 0;
    foreach ($rows as $row) {
        if ($excludeToken && $row['token'] === $excludeToken) {
            continue;
        }
        $config = json_decode($row['config'] ?? '[]', true) ?: [];
        $existingBytes += computeWebsiteAssetBytes($config);
    }

    $limit = getWebsiteQuotaConfig()['max_assets_bytes'];
    if ($existingBytes + $incomingBytes > $limit) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'website_asset_quota_reached',
            'message' => 'Le quota total d’assets website (100 Mo) serait dépassé.',
            'limit_bytes' => $limit,
            'current_bytes' => $existingBytes,
            'incoming_bytes' => $incomingBytes,
        ]);
        exit;
    }
}

function generateWebsiteSuggestions(string $activityKey, string $activity, string $companyName): array {
    $catalog = [
        'artisan' => [
            'siteTitle' => 'Artisan Expert',
            'welcomeMessage' => 'Artisan qualifié avec plus de 10 ans d\'expérience, j\'interviens rapidement pour tous vos travaux. Devis gratuit sous 24h.',
            'services' => [
                ['title' => 'Diagnostic & Devis', 'description' => 'Intervention rapide sur site, devis détaillé offert sans engagement.'],
                ['title' => 'Travaux & Réalisation', 'description' => 'Exécution soignée dans les règles de l\'art, garantie décennale.'],
                ['title' => 'Dépannage Urgence', 'description' => 'Disponible 7j/7 pour interventions urgentes dans un rayon de 30 km.'],
            ],
        ],
        'restaurant' => [
            'siteTitle' => 'Notre Restaurant',
            'welcomeMessage' => 'Bienvenue dans notre établissement ! Cuisine faite maison, produits locaux et accueil chaleureux vous attendent.',
            'services' => [
                ['title' => 'Déjeuner du Midi', 'description' => 'Formule complète entrée-plat-dessert renouvelée chaque semaine selon le marché.'],
                ['title' => 'Carte & Spécialités', 'description' => 'Découvrez notre carte de saison élaborée avec des produits frais du terroir.'],
                ['title' => 'Privatisation & Événements', 'description' => 'Réservez notre salle pour vos fêtes, anniversaires ou repas d\'entreprise.'],
            ],
        ],
        'commerce' => [
            'siteTitle' => 'Notre Boutique',
            'welcomeMessage' => 'Retrouvez toute notre sélection en ligne. Commerce local, service personnalisé et conseils d\'experts.',
            'services' => [
                ['title' => 'Sélection du Moment', 'description' => 'Découvrez nos produits phares et les dernières nouveautés.'],
                ['title' => 'Click & Collect', 'description' => 'Commandez en ligne et retirez votre commande directement en boutique.'],
                ['title' => 'Conseil Personnalisé', 'description' => 'Notre équipe vous guide pour trouver exactement ce qu\'il vous faut.'],
            ],
        ],
        'service' => [
            'siteTitle' => 'Mon Cabinet Conseil',
            'welcomeMessage' => 'Expert dans mon domaine, j\'accompagne particuliers et entreprises pour atteindre leurs objectifs. Prenez rendez-vous.',
            'services' => [
                ['title' => 'Audit & Diagnostic', 'description' => 'Analyse approfondie de votre situation et recommandations sur mesure.'],
                ['title' => 'Accompagnement Stratégique', 'description' => 'Suivi personnalisé pour piloter vos projets de A à Z.'],
                ['title' => 'Formation & Coaching', 'description' => 'Sessions adaptées à vos équipes pour monter en compétences rapidement.'],
            ],
        ],
        'portfolio' => [
            'siteTitle' => 'Mon Portfolio',
            'welcomeMessage' => 'Créatif passionné, je transforme vos idées en réalisations visuelles uniques. Découvrez mes projets et contactez-moi.',
            'services' => [
                ['title' => 'Design & Identité Visuelle', 'description' => 'Création de logos, chartes graphiques et supports de communication.'],
                ['title' => 'Photographie', 'description' => 'Reportages professionnels, portraits et couverture d\'événements.'],
                ['title' => 'Direction Artistique', 'description' => 'Conception visuelle globale de vos projets créatifs et marketing.'],
            ],
        ],
        'blog' => [
            'siteTitle' => 'Mon Blog',
            'welcomeMessage' => 'Bienvenue sur mon espace de partage ! Retrouvez ici mes articles, analyses et guides pratiques sur mes sujets de prédilection.',
            'services' => [
                ['title' => 'Articles & Analyses', 'description' => 'Publications régulières sur mes sujets de prédilection avec un regard critique.'],
                ['title' => 'Guides Pratiques', 'description' => 'Tutoriels pas à pas et guides concrets pour vous aider au quotidien.'],
                ['title' => 'Newsletter', 'description' => 'Abonnez-vous pour recevoir mes dernières publications directement dans votre boîte mail.'],
            ],
        ],
    ];

    $base = $catalog[$activityKey] ?? $catalog['artisan'];

    if ($companyName) {
        $base['siteTitle'] = $companyName;
    }

    if ($activity) {
        $base['welcomeMessage'] = rtrim($activity, '.') . '. ' . $base['welcomeMessage'];
    }

    return $base;
}

function getCCImageLibrary(string $category): array {
    $base = 'https://images.unsplash.com/';
    $q = '?w=600&auto=format&q=72&fit=crop';
    $library = [
        'artisan' => [
            ['url' => $base . 'photo-1504307651254-35680f356dfd' . $q, 'title' => 'Chantier construction'],
            ['url' => $base . 'photo-1581092160562-40aa08e78837' . $q, 'title' => 'Outillage industriel'],
            ['url' => $base . 'photo-1530124566582-a618bc2615dc' . $q, 'title' => 'Plomberie'],
            ['url' => $base . 'photo-1621905252507-b35492cc74b4' . $q, 'title' => 'Électricité'],
            ['url' => $base . 'photo-1503387762-592deb58ef4e' . $q, 'title' => 'Architecture bâtiment'],
            ['url' => $base . 'photo-1572981779307-38b8cabb2407' . $q, 'title' => 'Maçonnerie'],
        ],
        'commerce' => [
            ['url' => $base . 'photo-1528360983277-13d401cdc186' . $q, 'title' => 'Boutique chic'],
            ['url' => $base . 'photo-1441986300917-64674bd600d8' . $q, 'title' => 'Vitrine commerce'],
            ['url' => $base . 'photo-1607082348824-0a96f2a4b9da' . $q, 'title' => 'Shopping'],
            ['url' => $base . 'photo-1600880292203-757bb62b4baf' . $q, 'title' => 'Produits locaux'],
            ['url' => $base . 'photo-1555529669-e69e7aa0ba9a' . $q, 'title' => 'Commerce de proximité'],
            ['url' => $base . 'photo-1607082349566-187342175e2f' . $q, 'title' => 'Étalage marché'],
        ],
        'restaurant' => [
            ['url' => $base . 'photo-1517248135467-4c7edcad34c4' . $q, 'title' => 'Restaurant intérieur'],
            ['url' => $base . 'photo-1414235077428-338989a2e8c0' . $q, 'title' => 'Plat gastronomique'],
            ['url' => $base . 'photo-1555396273-367ea4eb4db5' . $q, 'title' => 'Terrasse café'],
            ['url' => $base . 'photo-1498654896293-37aacf113fd9' . $q, 'title' => 'Cuisine professionnelle'],
            ['url' => $base . 'photo-1424847651672-bf20a4b0982b' . $q, 'title' => 'Bar ambiance'],
            ['url' => $base . 'photo-1466978913421-dad2ebd01d17' . $q, 'title' => 'Food styling'],
        ],
        'service' => [
            ['url' => $base . 'photo-1497366216548-37526070297c' . $q, 'title' => 'Bureau moderne'],
            ['url' => $base . 'photo-1521737604893-d14cc237f11d' . $q, 'title' => 'Réunion équipe'],
            ['url' => $base . 'photo-1454165804606-c3d57bc86b40' . $q, 'title' => 'Conseil business'],
            ['url' => $base . 'photo-1553877522-43269d4ea984' . $q, 'title' => 'Digital & tech'],
            ['url' => $base . 'photo-1556761175-b413da4baf72' . $q, 'title' => 'Coworking'],
            ['url' => $base . 'photo-1507679799987-c73779587ccf' . $q, 'title' => 'Professionnel'],
        ],
        'portfolio' => [
            ['url' => $base . 'photo-1558618666-fcd25c85cd64' . $q, 'title' => 'Design studio'],
            ['url' => $base . 'photo-1561070791-2526d30994b5' . $q, 'title' => 'Création visuelle'],
            ['url' => $base . 'photo-1513364776144-60967b0f800f' . $q, 'title' => 'Photographie'],
            ['url' => $base . 'photo-1547826039-bfc35e0f1ea8' . $q, 'title' => 'Illustration'],
            ['url' => $base . 'photo-1600132806370-bf17e65e942f' . $q, 'title' => 'Art abstrait'],
            ['url' => $base . 'photo-1507003211169-0a1dd7228f2d' . $q, 'title' => 'Portrait créatif'],
        ],
        'blog' => [
            ['url' => $base . 'photo-1499750310107-5fef28a66643' . $q, 'title' => 'Espace écriture'],
            ['url' => $base . 'photo-1542435503-956c469947f6' . $q, 'title' => 'Café & blog'],
            ['url' => $base . 'photo-1432821596592-e2c18b78144f' . $q, 'title' => 'Lecture'],
            ['url' => $base . 'photo-1486312338219-ce68d2c6f44d' . $q, 'title' => 'Ordinateur créatif'],
            ['url' => $base . 'photo-1455390582262-044cdead277a' . $q, 'title' => 'Plume & carnet'],
            ['url' => $base . 'photo-1517694712202-14dd9538aa97' . $q, 'title' => 'Code & contenu'],
        ],
        'general' => [
            ['url' => $base . 'photo-1497366754035-f200968a6e72' . $q, 'title' => 'Bureau lumineux'],
            ['url' => $base . 'photo-1486325212027-8081e485255e' . $q, 'title' => 'Architecture moderne'],
            ['url' => $base . 'photo-1444210971048-6130cf0c46cf' . $q, 'title' => 'Nature verdure'],
            ['url' => $base . 'photo-1464938050520-ef2270bb8ce8' . $q, 'title' => 'Paysage urbain'],
            ['url' => $base . 'photo-1484291470158-b8f8d608850d' . $q, 'title' => 'Lumière abstraite'],
            ['url' => $base . 'photo-1497366811353-6870744d04b2' . $q, 'title' => 'Espace de travail'],
        ],
    ];
    return $library[$category] ?? $library['general'];
}
