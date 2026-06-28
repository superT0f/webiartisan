-- ============================================
-- WebIArtisan Platform — Multi-User Migration
-- Adds role-based access control (KISS approach)
-- ============================================

-- Step 1: Add new columns to existing users table
ALTER TABLE users 
ADD COLUMN name VARCHAR(100) NULL AFTER email,
ADD COLUMN fonction VARCHAR(100) NULL AFTER name,
ADD COLUMN phone VARCHAR(20) NULL AFTER fonction,
ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER phone,
ADD COLUMN created_by INT NULL AFTER is_active,
ADD COLUMN last_login_at TIMESTAMP NULL AFTER created_by,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER last_login_at;

-- Step 2: Modify role enum to include new roles
-- Note: We need to handle existing data carefully
ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'admin', 'commercial', 'comptable') DEFAULT 'commercial';

-- Step 3: Add foreign key for created_by (self-referencing)
ALTER TABLE users 
ADD CONSTRAINT fk_user_created_by 
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- Step 4: Update existing users - set name from email (before @)
UPDATE users SET name = SUBSTRING_INDEX(email, '@', 1) WHERE name IS NULL;

-- Step 5: Set first user of each tenant as admin
-- This is a one-time fix for existing data
UPDATE users u1
JOIN (
    SELECT tenant_id, MIN(id) as first_user_id
    FROM users
    GROUP BY tenant_id
) u2 ON u1.tenant_id = u2.tenant_id AND u1.id = u2.first_user_id
SET u1.role = 'admin';

-- Step 6: Set remaining users as commercial (default role)
UPDATE users SET role = 'commercial' WHERE role NOT IN ('superadmin', 'admin');

-- Step 7: Add indexes for performance
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_active ON users(tenant_id, is_active);
CREATE INDEX idx_users_created_by ON users(created_by);

-- ============================================
-- Permissions Reference (enforced in API, not DB)
-- ============================================
-- superadmin:  All tenants, impersonation, user management across tenants
-- admin:       Full CRUD on own tenant, user management within tenant
-- commercial:  CRUD clients/devis, read factures, no user management
-- comptable:   CRUD factures, read clients/devis, no user management
-- ============================================

-- ============================================
-- Super Admin Setup Instructions
-- ============================================
-- After migration, manually promote your account to superadmin:
-- 
-- UPDATE users SET role = 'superadmin' WHERE email = 'your-email@example.com';
-- 
-- Only superadmins can:
-- - View all users across all tenants
-- - Impersonate any user for support
-- - Create/manage users in any tenant
-- ============================================
