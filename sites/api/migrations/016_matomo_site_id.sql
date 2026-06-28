-- ============================================
-- Migration 016: Matomo Multi-Tenant Analytics
-- Ajout des colonnes matomo_site_id
-- ============================================

-- Stocke l'idSite Matomo pour chaque site généré
-- NULL = pas encore provisionné
ALTER TABLE sites
    ADD COLUMN matomo_site_id INT DEFAULT NULL COMMENT 'Matomo site ID for per-site analytics tracking';

-- Stocke l'idSite Matomo principal du tenant (pour son tableau de bord)
ALTER TABLE tenants
    ADD COLUMN matomo_site_id INT DEFAULT NULL COMMENT 'Matomo site ID for tenant dashboard stats view';

-- Stocke l'idSite Matomo pour les sites legacy (ai_generated_sites)
ALTER TABLE ai_generated_sites
    ADD COLUMN matomo_site_id INT DEFAULT NULL COMMENT 'Matomo site ID for legacy per-site analytics tracking';

-- Index optionnel pour recherche rapide
CREATE INDEX idx_sites_matomo ON sites (matomo_site_id);
CREATE INDEX idx_tenants_matomo ON tenants (matomo_site_id);
