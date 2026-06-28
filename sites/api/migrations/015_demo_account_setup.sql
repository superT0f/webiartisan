-- ============================================
-- WebIArtisan Platform — Demo Account Setup
-- Adds demo plan and creates demo@prigent.tech account
-- ============================================

-- Step 1: Add 'demo' to the plan enum in tenants table
SET @dbname = DATABASE();
SET @tablename = 'tenants';

-- Get current enum definition
SET @enum_query := (
    SELECT CONCAT('ALTER TABLE ', @tablename, ' MODIFY COLUMN plan ENUM("demo", "free", "pro", "business") NOT NULL DEFAULT "free"')
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'plan'
);

-- Execute the alter if plan column exists
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'plan'
);

SET @sql = IF(@col_exists > 0, @enum_query, 'SELECT "Plan column does not exist" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Create demo tenant if it doesn't exist
INSERT IGNORE INTO tenants (slug, name, subdomain, plan, custom_domain) 
VALUES ('demo', 'Demo Account', 'demo.webiartisan.prigent.tech', 'demo', NULL);

-- Step 3: Create demo user if it doesn't exist
INSERT IGNORE INTO users (tenant_id, email, login, role, name, is_active) 
SELECT 
    t.id,
    'demo@prigent.tech',
    'demo',
    'admin',
    'Demo User',
    1
FROM tenants t 
WHERE t.slug = 'demo' AND t.plan = 'demo';

-- Step 4: Add demo-specific security flags
-- Add a metadata table for demo account security
CREATE TABLE IF NOT EXISTS demo_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bypass_sso BOOLEAN DEFAULT TRUE,
    max_login_attempts INT DEFAULT 999,
    auto_logout_minutes INT DEFAULT 480, -- 8 hours
    restricted_ips TEXT DEFAULT NULL, -- JSON array of allowed IPs, NULL = any
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 5: Insert demo metadata
INSERT IGNORE INTO demo_metadata (user_id, bypass_sso, max_login_attempts, auto_logout_minutes)
SELECT 
    u.id,
    TRUE,
    999,
    480
FROM users u 
JOIN tenants t ON u.tenant_id = t.id 
WHERE u.email = 'demo@prigent.tech' AND t.slug = 'demo';

-- Step 6: Log the demo account creation
INSERT IGNORE INTO activity_log (tenant_id, user_id, action, details, ip_address, user_agent)
SELECT 
    t.id,
    u.id,
    'demo_account_created',
    JSON_OBJECT('email', 'demo@prigent.tech', 'plan', 'demo', 'quota_sites', 1, 'quota_storage_mb', 20),
    '127.0.0.1',
    'System Migration'
FROM users u 
JOIN tenants t ON u.tenant_id = t.id 
WHERE u.email = 'demo@prigent.tech' AND t.slug = 'demo';
