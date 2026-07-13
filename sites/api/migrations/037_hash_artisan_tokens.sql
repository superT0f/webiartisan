-- ============================================================
-- WebiArtisan — Migration 037 : Hash artisan auth tokens
-- ============================================================
-- Adds the auth_token_hash column and its index idempotently.
-- Data migration (one-shot hashing of legacy plaintext tokens)
-- is performed by the companion 037_hash_artisan_tokens.php CLI script.
-- ============================================================
SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS webiartisan_add_column_if_not_exists;
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

CALL webiartisan_add_column_if_not_exists('local_artisans', 'auth_token_hash', 'VARCHAR(255) NULL AFTER auth_token_exp');
CALL webiartisan_add_index_if_not_exists('local_artisans', 'idx_local_artisans_auth_token_hash', 'INDEX `idx_local_artisans_auth_token_hash` (`auth_token_hash`)');

DROP PROCEDURE IF EXISTS webiartisan_add_column_if_not_exists;
DROP PROCEDURE IF EXISTS webiartisan_add_index_if_not_exists;
