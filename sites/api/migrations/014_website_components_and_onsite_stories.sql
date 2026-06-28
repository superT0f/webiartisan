-- Migration: normalize website components and enrich on-site chantier/story media model
-- Created: 2026-03-10

SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'sites' AND COLUMN_NAME = 'title'),
    'SELECT 1',
    'ALTER TABLE sites ADD COLUMN title VARCHAR(190) NULL AFTER token'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'sites' AND COLUMN_NAME = 'slug'),
    'SELECT 1',
    'ALTER TABLE sites ADD COLUMN slug VARCHAR(190) NULL AFTER title'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'sites' AND COLUMN_NAME = 'activity_key'),
    'SELECT 1',
    'ALTER TABLE sites ADD COLUMN activity_key VARCHAR(80) NULL AFTER slug'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'sites' AND COLUMN_NAME = 'template_key'),
    'SELECT 1',
    'ALTER TABLE sites ADD COLUMN template_key VARCHAR(120) NULL AFTER activity_key'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'sites' AND COLUMN_NAME = 'asset_bytes'),
    'SELECT 1',
    'ALTER TABLE sites ADD COLUMN asset_bytes BIGINT NOT NULL DEFAULT 0 AFTER config'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'sites' AND COLUMN_NAME = 'last_generated_at'),
    'SELECT 1',
    'ALTER TABLE sites ADD COLUMN last_generated_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'sites' AND INDEX_NAME = 'idx_sites_tenant_updated'),
    'SELECT 1',
    'ALTER TABLE sites ADD INDEX idx_sites_tenant_updated (tenant_id, updated_at)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'sites' AND INDEX_NAME = 'idx_sites_slug'),
    'SELECT 1',
    'ALTER TABLE sites ADD INDEX idx_sites_slug (slug)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS site_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    tenant_id INT NOT NULL,
    component_key VARCHAR(80) NOT NULL,
    position INT NOT NULL DEFAULT 0,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    settings_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_site_component (site_id, component_key),
    KEY idx_site_components_tenant_site (tenant_id, site_id),
    KEY idx_site_components_key (component_key),
    CONSTRAINT fk_site_components_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    CONSTRAINT fk_site_components_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_component_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_component_id INT NOT NULL,
    tenant_id INT NOT NULL,
    item_key VARCHAR(80) NULL,
    position INT NOT NULL DEFAULT 0,
    payload_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_component_items_component (site_component_id, position),
    KEY idx_component_items_tenant (tenant_id),
    CONSTRAINT fk_site_component_items_component FOREIGN KEY (site_component_id) REFERENCES site_components(id) ON DELETE CASCADE,
    CONSTRAINT fk_site_component_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_story_feeds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    tenant_id INT NOT NULL,
    component_key VARCHAR(80) NOT NULL DEFAULT 'recent_stories',
    chantier_id INT NULL,
    max_items INT NOT NULL DEFAULT 15,
    story_status ENUM('published','draft','all') NOT NULL DEFAULT 'published',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_site_story_feed (site_id, component_key),
    KEY idx_site_story_feed_tenant (tenant_id),
    CONSTRAINT fk_site_story_feed_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    CONSTRAINT fk_site_story_feed_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'devis' AND COLUMN_NAME = 'chantier_id'),
    'SELECT 1',
    'ALTER TABLE devis ADD COLUMN chantier_id INT NULL AFTER client_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'devis' AND INDEX_NAME = 'idx_devis_chantier'),
    'SELECT 1',
    'ALTER TABLE devis ADD INDEX idx_devis_chantier (tenant_id, chantier_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantiers' AND COLUMN_NAME = 'devis_id'),
    'SELECT 1',
    'ALTER TABLE chantiers ADD COLUMN devis_id INT NULL AFTER client_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantiers' AND COLUMN_NAME = 'reference'),
    'SELECT 1',
    'ALTER TABLE chantiers ADD COLUMN reference VARCHAR(80) NULL AFTER devis_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantiers' AND COLUMN_NAME = 'description'),
    'SELECT 1',
    'ALTER TABLE chantiers ADD COLUMN description TEXT NULL AFTER nom'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantiers' AND COLUMN_NAME = 'start_date'),
    'SELECT 1',
    'ALTER TABLE chantiers ADD COLUMN start_date DATE NULL AFTER description'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantiers' AND COLUMN_NAME = 'archived_at'),
    'SELECT 1',
    'ALTER TABLE chantiers ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantiers' AND COLUMN_NAME = 'is_public'),
    'SELECT 1',
    'ALTER TABLE chantiers ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantiers' AND INDEX_NAME = 'idx_chantiers_devis'),
    'SELECT 1',
    'ALTER TABLE chantiers ADD INDEX idx_chantiers_devis (tenant_id, devis_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND COLUMN_NAME = 'tenant_id'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD COLUMN tenant_id INT NULL AFTER chantier_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND COLUMN_NAME = 'media_kind'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD COLUMN media_kind ENUM(''photo'',''video'',''audio'') NOT NULL DEFAULT ''photo'' AFTER type'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND COLUMN_NAME = 'source_type'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD COLUMN source_type ENUM(''upload'',''youtube'',''external'') NOT NULL DEFAULT ''upload'' AFTER media_kind'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND COLUMN_NAME = 'title'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD COLUMN title VARCHAR(190) NULL AFTER source_type'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND COLUMN_NAME = 'caption'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD COLUMN caption TEXT NULL AFTER title'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND COLUMN_NAME = 'mime_type'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD COLUMN mime_type VARCHAR(120) NULL AFTER caption'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND COLUMN_NAME = 'file_size'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD COLUMN file_size BIGINT NOT NULL DEFAULT 0 AFTER mime_type'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND COLUMN_NAME = 'youtube_token'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD COLUMN youtube_token VARCHAR(32) NULL AFTER file_size'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND COLUMN_NAME = 'youtube_url'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD COLUMN youtube_url VARCHAR(500) NULL AFTER youtube_token'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND COLUMN_NAME = 'thumbnail_url'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD COLUMN thumbnail_url VARCHAR(500) NULL AFTER youtube_url'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND COLUMN_NAME = 'duration_seconds'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD COLUMN duration_seconds INT NULL AFTER thumbnail_url'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND COLUMN_NAME = 'sort_order'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER duration_seconds'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND COLUMN_NAME = 'is_public'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_order'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND INDEX_NAME = 'idx_chantier_medias_tenant'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD INDEX idx_chantier_medias_tenant (tenant_id, chantier_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'chantier_medias' AND INDEX_NAME = 'idx_chantier_medias_story'),
    'SELECT 1',
    'ALTER TABLE chantier_medias ADD INDEX idx_chantier_medias_story (chantier_id, is_public, created_at)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE chantier_medias SET tenant_id = (
    SELECT c.tenant_id FROM chantiers c WHERE c.id = chantier_medias.chantier_id LIMIT 1
) WHERE tenant_id IS NULL;

CREATE TABLE IF NOT EXISTS chantier_stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    chantier_id INT NOT NULL,
    client_id INT NULL,
    devis_id INT NULL,
    title VARCHAR(190) NOT NULL,
    story_text TEXT NOT NULL,
    summary VARCHAR(500) NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    cover_media_id INT NULL,
    published_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_chantier_stories_tenant (tenant_id, chantier_id, status, published_at),
    KEY idx_chantier_stories_client (tenant_id, client_id),
    KEY idx_chantier_stories_devis (tenant_id, devis_id),
    CONSTRAINT fk_chantier_stories_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_chantier_stories_chantier FOREIGN KEY (chantier_id) REFERENCES chantiers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chantier_story_medias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    story_id INT NOT NULL,
    media_id INT NOT NULL,
    position INT NOT NULL DEFAULT 0,
    is_cover TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_story_media (story_id, media_id),
    KEY idx_story_media_story (story_id, position),
    KEY idx_story_media_media (media_id),
    CONSTRAINT fk_story_media_story FOREIGN KEY (story_id) REFERENCES chantier_stories(id) ON DELETE CASCADE,
    CONSTRAINT fk_story_media_media FOREIGN KEY (media_id) REFERENCES chantier_medias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
