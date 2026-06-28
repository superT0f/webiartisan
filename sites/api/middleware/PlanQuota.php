<?php
/**
 * PlanQuota Middleware — Enforces freemium plan limits.
 * Checks resource counts against plan quotas before allowing creation.
 */

class PlanQuota {

    /**
     * Plan limits configuration.
     * null = unlimited
     * assets_size is in bytes (100MB = 104857600 bytes)
     */
    private static array $limits = [
        'demo' => [
            'clients'       => 5,
            'devis_month'   => 3,
            'factures_month' => 2,
            'sites'         => 1,
            'users'         => 1,
            'assets_size'   => 20971520, // 20 MB
        ],
        'free' => [
            'clients'       => 30,
            'devis_month'   => 15,
            'factures_month' => 10,
            'sites'         => 3,
            'users'         => 1,
            'assets_size'   => 104857600, // 100 MB
        ],
        'pro' => [
            'clients'       => null,
            'devis_month'   => null,
            'factures_month' => null,
            'sites'         => 10,
            'users'         => 5,
            'assets_size'   => 524288000, // 500 MB
        ],
        'business' => [
            'clients'       => null,
            'devis_month'   => null,
            'factures_month' => null,
            'sites'         => null,
            'users'         => null,
            'assets_size'   => null, // Unlimited
        ],
    ];

    /**
     * Get the current tenant's plan (defaults to 'free').
     */
    public static function getCurrentPlan(): string {
        try {
            $tenant = TenantContext::get();
            return $tenant['plan'] ?? 'free';
        } catch (Exception $e) {
            return 'free';
        }
    }

    /**
     * Get limits for a given plan.
     */
    public static function getLimits(string $plan): array {
        return self::$limits[$plan] ?? self::$limits['free'];
    }

    /**
     * Get full plan info (plan name + limits + current usage).
     */
    public static function getPlanInfo(): array {
        $plan = self::getCurrentPlan();
        $limits = self::getLimits($plan);
        $usage = self::getCurrentUsage();

        return [
            'plan'   => $plan,
            'limits' => $limits,
            'usage'  => $usage,
        ];
    }

    /**
     * Calculate total assets size for a tenant in bytes.
     */
    public static function getAssetsSize(): int {
        $tenantId = TenantContext::id();
        if (!$tenantId) return 0;

        $totalSize = 0;
        $baseUploadDir = __DIR__ . '/../../uploads';
        
        error_log("🔍 [PlanQuota] Calculating assets size for tenant $tenantId");
        error_log("📁 [PlanQuota] Base upload dir: $baseUploadDir");

        // 1. Logos
        $logoDir = $baseUploadDir . '/logos';
        if (is_dir($logoDir)) {
            $pattern = $logoDir . "/logo_{$tenantId}_*";
            $logoFiles = glob($pattern);
            $logoSize = 0;
            foreach ($logoFiles as $filename) {
                $size = filesize($filename);
                $logoSize += $size;
                error_log("📄 [PlanQuota] Logo file: " . basename($filename) . " = " . $size . " bytes");
            }
            $totalSize += $logoSize;
            error_log("📊 [PlanQuota] Logos total: $logoSize bytes (" . count($logoFiles) . " files)");
        } else {
            error_log("⚠️ [PlanQuota] Logo directory not found: $logoDir");
        }

        // 2. Projets media (formerly chantiers)
        $projetDir = $baseUploadDir . "/projets/{$tenantId}";
        if (!is_dir($projetDir)) {
            // Fallback for transition
            $projetDir = $baseUploadDir . "/chantiers/{$tenantId}";
        }
        
        if (is_dir($projetDir)) {
            $projetSize = self::getDirSize($projetDir);
            $totalSize += $projetSize;
            error_log("📊 [PlanQuota] Projets total: $projetSize bytes");
        } else {
            error_log("⚠️ [PlanQuota] Projet directory not found: $projetDir");
        }

        // 3. Website assets (if any stored separately)
        $siteDir = $baseUploadDir . "/sites/{$tenantId}";
        if (is_dir($siteDir)) {
            $siteSize = self::getDirSize($siteDir);
            $totalSize += $siteSize;
            error_log("📊 [PlanQuota] Website assets total: $siteSize bytes");
        } else {
            error_log("⚠️ [PlanQuota] Website directory not found: $siteDir");
        }

        error_log("✅ [PlanQuota] Final assets size for tenant $tenantId: $totalSize bytes (" . round($totalSize/1024/1024, 2) . " MB)");
        return $totalSize;
    }

    private static function getDirSize($dir): int {
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    /**
     * Check if a resource creation is allowed under the current plan.
     * Returns true if allowed, or sends 403 + JSON error and exits.
     *
     * @param string $resource  'clients', 'devis', 'factures', 'sites', 'users', 'assets'
     * @param int $additionalSize Size to add for assets check
     * @return bool
     */
    public static function checkCanCreate(string $resource, int $additionalSize = 0): bool {
        $plan = self::getCurrentPlan();
        $limits = self::getLimits($plan);
        $tenantId = TenantContext::id();
        $pdo = getDatabase();

        $limitKey = $resource;
        $currentValue = 0;

        switch ($resource) {
            case 'clients':
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE tenant_id = ?");
                $stmt->execute([$tenantId]);
                $currentValue = (int) $stmt->fetchColumn();
                break;

            case 'devis':
                $limitKey = 'devis_month';
                $period = date('Y-m');
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM devis WHERE tenant_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?"
                );
                $stmt->execute([$tenantId, $period]);
                $currentValue = (int) $stmt->fetchColumn();
                break;

            case 'factures':
                $limitKey = 'factures_month';
                $period = date('Y-m');
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM factures WHERE tenant_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?"
                );
                $stmt->execute([$tenantId, $period]);
                $currentValue = (int) $stmt->fetchColumn();
                break;

            case 'sites':
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM sites WHERE tenant_id = ?");
                $stmt->execute([$tenantId]);
                $currentValue = (int) $stmt->fetchColumn();
                break;

            case 'users':
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND is_active = TRUE");
                $stmt->execute([$tenantId]);
                $currentValue = (int) $stmt->fetchColumn();
                break;

            case 'assets':
                $limitKey = 'assets_size';
                $currentValue = self::getAssetsSize() + $additionalSize;
                break;

            default:
                return true;
        }

        $limit = $limits[$limitKey] ?? null;

        // null = unlimited
        if ($limit === null) {
            return true;
        }

        if ($currentValue >= $limit) {
            http_response_code(403);
            $message = "Limite du plan $plan atteinte pour $resource.";
            if ($resource === 'assets') {
                $message = "Espace de stockage insuffisant (" . round($currentValue/1024/1024, 2) . "Mo / " . round($limit/1024/1024, 2) . "Mo).";
            }
            
            echo json_encode([
                'success'  => false,
                'error'    => 'plan_limit_reached',
                'message'  => $message,
                'plan'     => $plan,
                'resource' => $resource,
                'current'  => $currentValue,
                'limit'    => $limit,
            ]);
            exit;
        }

        return true;
    }

    /**
     * Check if a premium feature is available on the current plan.
     *
     * @param string $feature Feature name
     * @return bool
     */
    public static function hasFeature(string $feature): bool {
        $plan = self::getCurrentPlan();

        $features = [
            'custom_pdf'          => ['pro', 'business'],
            'stripe_payments'     => ['pro', 'business'],
            'export_comptable'    => ['pro', 'business'],
            'custom_domain'       => ['pro', 'business'],
            'advanced_analytics'  => ['pro', 'business'],
            'signatures'          => ['business'],
            'auto_relance'        => ['business'],
            'portail_client'      => ['business'],
            'integrations'        => ['business'],
            'api_access'          => ['business'],
            'website_plus_components' => ['pro', 'business'],
        ];

        $allowedPlans = $features[$feature] ?? [];
        return in_array($plan, $allowedPlans);
    }

    /**
     * Require a specific feature or abort with 403.
     */
    public static function requireFeature(string $feature): void {
        if (!self::hasFeature($feature)) {
            $plan = self::getCurrentPlan();
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => 'premium_feature',
                'message' => "Cette fonctionnalité nécessite un plan supérieur.",
                'feature' => $feature,
                'plan'    => $plan,
            ]);
            exit;
        }
    }

    /**
     * Get current usage stats for the tenant.
     */
    public static function getCurrentUsage(): array {
        $tenantId = TenantContext::id();
        if (!$tenantId) return [];

        $pdo = getDatabase();
        $period = date('Y-m');

        $clients = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE tenant_id = ?");
        $clients->execute([$tenantId]);

        $devisMonth = $pdo->prepare(
            "SELECT COUNT(*) FROM devis WHERE tenant_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?"
        );
        $devisMonth->execute([$tenantId, $period]);

        $facturesMonth = $pdo->prepare(
            "SELECT COUNT(*) FROM factures WHERE tenant_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?"
        );
        $facturesMonth->execute([$tenantId, $period]);

        $sites = $pdo->prepare("SELECT COUNT(*) FROM sites WHERE tenant_id = ?");
        $sites->execute([$tenantId]);

        $users = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND is_active = TRUE");
        $users->execute([$tenantId]);

        return [
            'clients'        => (int) $clients->fetchColumn(),
            'devis_month'    => (int) $devisMonth->fetchColumn(),
            'factures_month' => (int) $facturesMonth->fetchColumn(),
            'sites'          => (int) $sites->fetchColumn(),
            'users'          => (int) $users->fetchColumn(),
            'assets_size'    => self::getAssetsSize(),
        ];
    }
}
