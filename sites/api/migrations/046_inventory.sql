-- ============================================================
-- WebiArtisan — Migration 046 : Inventaire joueur
-- (leurres à boss + réserves d'énergie ramassables)
-- Appliquée automatiquement par lib/Migrations.php au déploiement.
-- ============================================================
SET NAMES utf8mb4;

ALTER TABLE local_world_objects
    MODIFY object_type enum(
        'dechet','canette','papier','tresor','cadeau_artisan','big_brother',
        'boss_spawner','energy_store'
    ) COLLATE utf8mb4_unicode_ci NOT NULL;

CREATE TABLE IF NOT EXISTS local_user_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    object_type VARCHAR(32) NOT NULL,
    source_object_id INT NULL,
    status ENUM('active','used') NOT NULL DEFAULT 'active',
    acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
