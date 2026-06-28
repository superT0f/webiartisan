-- ============================================
-- WebIArtisan Platform — Freemium Plans Migration
-- Adds plan management to tenants + subscriptions table
-- ============================================

-- Step 1: Add plan column to tenants
SET @dbname = DATABASE();
SET @tablename = 'tenants';

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'plan'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE tenants ADD COLUMN plan ENUM("free", "pro", "business") NOT NULL DEFAULT "free" AFTER custom_domain',
    'SELECT "Column plan already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Add stripe_customer_id to tenants
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'stripe_customer_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE tenants ADD COLUMN stripe_customer_id VARCHAR(255) DEFAULT NULL AFTER plan',
    'SELECT "Column stripe_customer_id already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Subscriptions table (tracks Stripe subscriptions)
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    stripe_subscription_id VARCHAR(255) NOT NULL,
    stripe_price_id VARCHAR(255) NOT NULL,
    plan ENUM('pro', 'business') NOT NULL,
    status ENUM('active', 'past_due', 'canceled', 'incomplete', 'trialing') NOT NULL DEFAULT 'active',
    current_period_start DATETIME NULL,
    current_period_end DATETIME NULL,
    cancel_at DATETIME NULL,
    canceled_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_stripe_sub (stripe_subscription_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 4: Plan usage tracking (monthly counters)
CREATE TABLE IF NOT EXISTS plan_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    period VARCHAR(7) NOT NULL,  -- YYYY-MM format
    clients_count INT DEFAULT 0,
    devis_count INT DEFAULT 0,
    factures_count INT DEFAULT 0,
    sites_count INT DEFAULT 0,
    users_count INT DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_tenant_period (tenant_id, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
