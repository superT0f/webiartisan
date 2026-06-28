<?php
/**
 * Tenant Middleware — Multi-tenant scope resolver.
 * Ensures all queries are scoped to the authenticated user's tenant.
 */

class TenantContext {
    private static ?int $currentTenantId = null;
    private static ?array $currentTenant = null;

    /**
     * Set the current tenant from the authenticated user's JWT payload.
     */
    public static function setFromAuth(array $authUser): void {
        self::$currentTenantId = (int) $authUser['tenant_id'];
    }

    /**
     * Load full tenant data from DB (lazy, cached per request).
     */
    public static function get(): ?array {
        if (self::$currentTenantId === null) return null;

        if (self::$currentTenant === null) {
            $pdo = getDatabase();
            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ? LIMIT 1");
            $stmt->execute([self::$currentTenantId]);
            self::$currentTenant = $stmt->fetch() ?: null;
        }

        return self::$currentTenant;
    }

    /**
     * Get the current tenant ID (for query scoping).
     */
    public static function id(): ?int {
        return self::$currentTenantId;
    }

    /**
     * Get the current tenant's plan (free/pro/business).
     */
    public static function getPlan(): string {
        $tenant = self::get();
        return $tenant['plan'] ?? 'free';
    }

    /**
     * Require a valid tenant context or abort.
     */
    public static function require(): int {
        if (self::$currentTenantId === null) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No tenant context']);
            exit;
        }
        return self::$currentTenantId;
    }

    /**
     * Resolve tenant from subdomain (for public site viewer).
     * e.g., "boulangerie-dupont.webiartisan.prigent.tech"
     */
    public static function resolveFromHost(string $host): ?array {
        // Extract slug from subdomain pattern: {slug}.webiartisan.prigent.tech
        if (preg_match('/^([a-z0-9\-]+)\.webiartisan\.prigent\.tech$/i', $host, $m)) {
            $slug = strtolower($m[1]);
            $pdo = getDatabase();
            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE slug = ? LIMIT 1");
            $stmt->execute([$slug]);
            $tenant = $stmt->fetch();

            if ($tenant) {
                self::$currentTenantId = (int) $tenant['id'];
                self::$currentTenant = $tenant;
                return $tenant;
            }
        }

        return null;
    }
}
