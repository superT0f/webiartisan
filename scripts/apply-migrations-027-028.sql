-- ============================================================
-- WebIArtisan — Migrations 027 & 028 : Roue consommateur + Gamification
-- ============================================================
-- NOTE: This file now also applies migration 028 so the consumer
-- schema is fully up to date. The canonical migrations live in
-- sites/api/migrations/027_spin_wheel.sql and
-- sites/api/migrations/028_gamification.sql.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS local_users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) UNIQUE NOT NULL,
    magic_token     VARCHAR(64) DEFAULT NULL,
    magic_token_exp DATETIME DEFAULT NULL,
    session_token   VARCHAR(64) DEFAULT NULL,
    session_exp     DATETIME DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_session (session_token, session_exp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Utilisateurs consommateurs de la roue';

CREATE TABLE IF NOT EXISTS local_spin_offers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    artisan_id      INT NOT NULL,
    label           VARCHAR(200) NOT NULL COMMENT 'Texte affiche sur la roue',
    description     TEXT,
    stock_total     INT NOT NULL DEFAULT 0,
    stock_remaining INT NOT NULL DEFAULT 0,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (artisan_id) REFERENCES local_artisans(id) ON DELETE CASCADE,
    INDEX idx_artisan (artisan_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Offres promotionnelles pour la roue';

CREATE TABLE IF NOT EXISTS local_spin_wins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    offer_id    INT NOT NULL,
    artisan_id  INT NOT NULL,
    code        VARCHAR(32) UNIQUE NOT NULL,
    status      ENUM('pending','claimed','expired') DEFAULT 'pending',
    spin_date   DATE NOT NULL,
    claimed_at  TIMESTAMP NULL,
    expires_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES local_users(id)    ON DELETE CASCADE,
    FOREIGN KEY (offer_id)   REFERENCES local_spin_offers(id) ON DELETE RESTRICT,
    FOREIGN KEY (artisan_id) REFERENCES local_artisans(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_artisan_status (artisan_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Gains des utilisateurs';

CREATE TABLE IF NOT EXISTS local_spin_daily_limits (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    city_id     INT NOT NULL,
    spin_date   DATE NOT NULL,
    count       INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_daily (user_id, city_id, spin_date),
    FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    FOREIGN KEY (city_id) REFERENCES local_cities(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, spin_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Limite de spins par utilisateur, ville et jour';

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
