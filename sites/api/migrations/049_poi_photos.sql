-- ============================================================
-- WebiArtisan — Migration 049 : Galerie photo communautaire POI
-- (photos communautaires + signalements + provenance inventaire)
-- Appliquée automatiquement par lib/Migrations.php au déploiement.
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS local_poi_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poi_id INT NOT NULL,
    user_id INT NOT NULL,
    file_path VARCHAR(190) NOT NULL,
    status ENUM('active','validated','hidden','deleted') NOT NULL DEFAULT 'active',
    gifted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_poi_status (poi_id, status),
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_poi_photo_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    photo_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_report (photo_id, user_id),
    INDEX idx_photo (photo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE local_user_inventory
    ADD COLUMN source_label VARCHAR(120) NULL AFTER source_object_id;
