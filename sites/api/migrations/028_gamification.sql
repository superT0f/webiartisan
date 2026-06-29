-- ============================================================
-- WebiArtisan — Migration 028 : Gamification consommateur
-- ============================================================

SET NAMES utf8mb4;

-- MySQL 8.0 does not support ALTER TABLE ... ADD COLUMN IF NOT EXISTS,
-- so we use a small helper procedure to make each addition idempotent.
DROP PROCEDURE IF EXISTS webiartisan_add_column_if_not_exists;

DELIMITER //

CREATE PROCEDURE webiartisan_add_column_if_not_exists(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //

DELIMITER ;

CALL webiartisan_add_column_if_not_exists('local_users', 'display_name', 'VARCHAR(80) NULL AFTER email');
CALL webiartisan_add_column_if_not_exists('local_users', 'avatar_type', "ENUM('default','upload','custom') NOT NULL DEFAULT 'default' AFTER display_name");
CALL webiartisan_add_column_if_not_exists('local_users', 'avatar_url', 'VARCHAR(255) NULL AFTER avatar_type');
CALL webiartisan_add_column_if_not_exists('local_users', 'avatar_gender', "ENUM('male','female','neutral') NOT NULL DEFAULT 'neutral' AFTER avatar_url");
CALL webiartisan_add_column_if_not_exists('local_users', 'level', 'INT NOT NULL DEFAULT 1 AFTER avatar_gender');
CALL webiartisan_add_column_if_not_exists('local_users', 'xp', 'INT NOT NULL DEFAULT 0 AFTER level');
CALL webiartisan_add_column_if_not_exists('local_users', 'title', 'VARCHAR(80) NULL AFTER xp');

DROP PROCEDURE IF EXISTS webiartisan_add_column_if_not_exists;

CREATE TABLE IF NOT EXISTS local_user_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_key VARCHAR(50) NOT NULL,
    xp_amount INT NOT NULL DEFAULT 0,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_action_key (action_key),
    FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_user_cooldowns (
    user_id INT NOT NULL,
    action_key VARCHAR(50) NOT NULL,
    period VARCHAR(20) NOT NULL,
    resource_key VARCHAR(255) NULL,
    last_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_action_resource (user_id, action_key, resource_key),
    INDEX idx_last_at (last_at),
    FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_user_badges (
    user_id INT NOT NULL,
    badge_key VARCHAR(50) NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, badge_key),
    FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_user_streaks (
    user_id INT PRIMARY KEY,
    current_streak INT NOT NULL DEFAULT 0,
    last_visit_date DATE NULL,
    FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
