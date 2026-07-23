-- ============================================================
-- WebiArtisan — Migration 043 : Images POI + revendication owner
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS local_poi_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poi_id INT NOT NULL,
    artisan_id INT NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    INDEX idx_poi_status (poi_id, status),
    INDEX idx_artisan_status (artisan_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exécution unique (comme 035/041) : ne pas rejouer ces ALTER.
ALTER TABLE local_pois
    ADD COLUMN image_url VARCHAR(255) NULL,
    ADD COLUMN owner_artisan_id INT NULL,
    ADD INDEX idx_owner (owner_artisan_id);
