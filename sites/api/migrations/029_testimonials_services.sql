-- ============================================================
-- WebiArtisan — Migration 029 : Témoignages génériques & catalogue de services
-- ============================================================

SET NAMES utf8mb4;

-- Catalogue global de types de services
CREATE TABLE IF NOT EXISTS local_service_catalog (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    `key`       VARCHAR(50) UNIQUE NOT NULL COMMENT 'food_recipe, haircut, gardening...',
    label_fr    VARCHAR(100) NOT NULL,
    icon        VARCHAR(100) DEFAULT NULL,
    category    VARCHAR(50) DEFAULT NULL,
    is_active   BOOLEAN DEFAULT TRUE,
    testimonial_templates JSON NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (`key`),
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enrich existing local_services with catalog link and custom flag
SET @dbname = DATABASE();
SET @tablename = 'local_services';
SET @colname = 'service_catalog_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE local_services ADD COLUMN service_catalog_id INT NULL AFTER artisan_id'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @colname = 'is_custom';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE local_services ADD COLUMN is_custom BOOLEAN NOT NULL DEFAULT FALSE AFTER service_catalog_id'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @colname = 'is_active';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @colname) > 0,
  'SELECT 1',
  'ALTER TABLE local_services ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE AFTER duration'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @fkname = 'fk_service_catalog';
SET @fkStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND CONSTRAINT_NAME = @fkname) > 0,
  'SELECT 1',
  'ALTER TABLE local_services ADD CONSTRAINT fk_service_catalog FOREIGN KEY (service_catalog_id) REFERENCES local_service_catalog(id) ON DELETE SET NULL'
));
PREPARE alterFkIfNotExists FROM @fkStatement;
EXECUTE alterFkIfNotExists;
DEALLOCATE PREPARE alterFkIfNotExists;

-- Témoignages / recommandations génériques
CREATE TABLE IF NOT EXISTS local_testimonials (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    artisan_id      INT NOT NULL,
    user_id         INT NOT NULL,
    artisan_service_id INT NULL,
    service_type    VARCHAR(50) NULL COMMENT 'denormalized catalog key',
    rating          TINYINT NULL,
    title           VARCHAR(150) NULL,
    content         TEXT NOT NULL,
    status          ENUM('pending','approved','rejected','flagged') NOT NULL DEFAULT 'pending',
    helpful_count   INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (artisan_id) REFERENCES local_artisans(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    FOREIGN KEY (artisan_service_id) REFERENCES local_services(id) ON DELETE SET NULL,
    INDEX idx_artisan_status (artisan_id, status),
    INDEX idx_user (user_id),
    INDEX idx_service_type (service_type),
    INDEX idx_created (created_at),
    CONSTRAINT chk_testimonial_rating CHECK (rating IS NULL OR rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Médias associés aux témoignages
CREATE TABLE IF NOT EXISTS local_testimonial_media (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    testimonial_id  INT NOT NULL,
    media_url       VARCHAR(255) NOT NULL,
    media_type      ENUM('image','video') NOT NULL DEFAULT 'image',
    display_order   INT NOT NULL DEFAULT 0,
    FOREIGN KEY (testimonial_id) REFERENCES local_testimonials(id) ON DELETE CASCADE,
    INDEX idx_testimonial (testimonial_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Signalements de témoignages
CREATE TABLE IF NOT EXISTS local_testimonial_reports (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    testimonial_id      INT NOT NULL,
    reporter_user_id    INT NOT NULL,
    reason              VARCHAR(100) NOT NULL,
    details             TEXT NULL,
    status              ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at         TIMESTAMP NULL,
    FOREIGN KEY (testimonial_id) REFERENCES local_testimonials(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    INDEX idx_testimonial (testimonial_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed sample catalog entries
INSERT INTO local_service_catalog (`key`, label_fr, icon, category, testimonial_templates) VALUES
('food_recipe', 'Recette locale', '🍽️', 'alimentation', '["J\'ai utilisé des ingrédients locaux dans une recette de {{dish}}.","{{artisan}} m\'a fourni les produits pour ma recette."]'),
('haircut', 'Coiffure', '✂️', 'beauté', '["Super coupe chez {{artisan}}, je recommande !","{{artisan}} a su m\'écouter et me conseiller."]'),
('gardening', 'Jardinage', '🌱', 'maison', '["{{artisan}} a transformé mon jardin.","Travail soigné et conseils avisés."]'),
('plumbing', 'Plomberie', '🚿', 'maison', '["Intervention rapide et efficace.","{{artisan}} a résolu mon problème en un temps record."]'),
('sewing', 'Couture', '🧵', 'mode', '["Retouche parfaite, merci {{artisan}} !","{{artisan}} a réalisé une pièce sur mesure."]')
ON DUPLICATE KEY UPDATE label_fr = VALUES(label_fr);
