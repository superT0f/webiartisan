-- ============================================================
-- WebiArtisan — Migration 032 : Consumer password auth
-- ============================================================
SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS webiartisan_add_column_if_not_exists;
DROP PROCEDURE IF EXISTS webiartisan_drop_index_if_exists;
DROP PROCEDURE IF EXISTS webiartisan_add_index_if_not_exists;

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

CREATE PROCEDURE webiartisan_drop_index_if_exists(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP INDEX `', p_index, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //

CREATE PROCEDURE webiartisan_add_index_if_not_exists(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //

DELIMITER ;

CALL webiartisan_add_column_if_not_exists('local_users', 'password_hash', 'VARCHAR(255) NULL AFTER magic_token_exp');
CALL webiartisan_add_column_if_not_exists('local_users', 'email_verified', 'BOOLEAN NOT NULL DEFAULT FALSE AFTER password_hash');

CREATE TABLE IF NOT EXISTS local_user_password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(255) NOT NULL,
    token_hash VARCHAR(64)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_hash (token_hash),
    INDEX idx_email (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate any existing plaintext-token schema to hashed tokens.
CALL webiartisan_drop_index_if_exists('local_user_password_resets', 'idx_token');
CALL webiartisan_drop_index_if_exists('local_user_password_resets', 'uk_token');
CALL webiartisan_drop_index_if_exists('local_user_password_resets', 'idx_expires');

CALL webiartisan_add_column_if_not_exists('local_user_password_resets', 'token_hash', 'VARCHAR(64) NOT NULL DEFAULT \'\' AFTER email');

DROP PROCEDURE IF EXISTS webiartisan_drop_column_if_exists;
DELIMITER //
CREATE PROCEDURE webiartisan_drop_column_if_exists(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP COLUMN `', p_column, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

CALL webiartisan_drop_column_if_exists('local_user_password_resets', 'token');

CALL webiartisan_add_index_if_not_exists('local_user_password_resets', 'idx_token_hash', 'INDEX `idx_token_hash` (`token_hash`)');
CALL webiartisan_add_index_if_not_exists('local_user_password_resets', 'idx_email', 'INDEX `idx_email` (`email`, `created_at`)');

DROP PROCEDURE IF EXISTS webiartisan_drop_column_if_exists;

DROP PROCEDURE IF EXISTS webiartisan_add_column_if_not_exists;
DROP PROCEDURE IF EXISTS webiartisan_drop_index_if_exists;
DROP PROCEDURE IF EXISTS webiartisan_add_index_if_not_exists;
