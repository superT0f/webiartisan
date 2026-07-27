-- ============================================================
-- WebiArtisan — Migration 047 : Réglages clé-valeur
-- (flag maintenance, futurs réglages ops)
-- Appliquée automatiquement par lib/Migrations.php au déploiement.
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS local_settings (
    setting_key VARCHAR(64) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
