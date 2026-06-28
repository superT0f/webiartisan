-- Migration: Ajout du système de paiement Stripe
-- Date: 2024-03-06

-- Table des paiements Stripe
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facture_id INT NOT NULL,
    stripe_payment_intent_id VARCHAR(255) NOT NULL,
    stripe_customer_id VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'eur',
    status ENUM('pending', 'processing', 'succeeded', 'failed', 'canceled', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_method_details JSON DEFAULT NULL,
    receipt_email VARCHAR(255) DEFAULT NULL,
    receipt_url VARCHAR(500) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_facture_id (facture_id),
    INDEX idx_stripe_intent (stripe_payment_intent_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajout de colonnes à la table factures pour le paiement (avec vérification)
SET @dbname = DATABASE();
SET @tablename = 'factures';

-- Ajout payment_status si non existant
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'payment_status'
);
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE factures ADD COLUMN payment_status ENUM("unpaid", "pending", "paid", "partial", "failed") DEFAULT "unpaid" AFTER status',
    'SELECT "Column payment_status already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout payment_token si non existant
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'payment_token'
);
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE factures ADD COLUMN payment_token VARCHAR(255) DEFAULT NULL',
    'SELECT "Column payment_token already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout payment_token_expires_at si non existant
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'payment_token_expires_at'
);
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE factures ADD COLUMN payment_token_expires_at DATETIME DEFAULT NULL',
    'SELECT "Column payment_token_expires_at already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout paid_at si non existant
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'paid_at'
);
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE factures ADD COLUMN paid_at DATETIME DEFAULT NULL',
    'SELECT "Column paid_at already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout stripe_payment_intent_id si non existant
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'stripe_payment_intent_id'
);
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE factures ADD COLUMN stripe_payment_intent_id VARCHAR(255) DEFAULT NULL',
    'SELECT "Column stripe_payment_intent_id already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
