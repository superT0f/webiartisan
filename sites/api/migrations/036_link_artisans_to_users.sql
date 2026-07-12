-- ============================================================
-- WebiArtisan — Migration 036 : Lier chaque artisan à un compte consommateur
-- ============================================================
SET NAMES utf8mb4;

-- Colonne de liaison (si elle n'existe pas déjà)
SET @add_user_id = (
    SELECT IF(
        NOT EXISTS(
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'local_artisans'
              AND COLUMN_NAME = 'user_id'
        ),
        'ALTER TABLE local_artisans ADD COLUMN user_id INT NULL AFTER email',
        'SELECT 1'
    )
);
PREPARE stmt FROM @add_user_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index (s'il n'existe pas déjà)
SET @add_idx = (
    SELECT IF(
        NOT EXISTS(
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'local_artisans'
              AND INDEX_NAME = 'idx_user_id'
        ),
        'ALTER TABLE local_artisans ADD INDEX idx_user_id (user_id)',
        'SELECT 1'
    )
);
PREPARE stmt FROM @add_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Créer un compte local_users pour chaque artisan qui n'en a pas
INSERT INTO local_users (email, display_name)
SELECT a.email, a.company_name
FROM local_artisans a
LEFT JOIN local_users u ON u.email = a.email
WHERE a.email IS NOT NULL
  AND a.email != ''
  AND u.id IS NULL;

-- Mettre à jour local_artisans.user_id pour tous les artisans
UPDATE local_artisans a
JOIN local_users u ON u.email = a.email
SET a.user_id = u.id
WHERE a.user_id IS NULL;
