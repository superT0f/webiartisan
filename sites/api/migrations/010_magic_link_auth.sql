-- ============================================
-- WebIArtisan Platform — Magic Link Auth Migration
-- Removes password-based auth, adds:
--   - login (username) field
--   - avatar_path for profile photos
--   - auth_tokens table (email magic codes)
--   - remember_tokens table (per-device cookies)
-- ============================================

-- Step 1: Add login (username) and avatar_path to users
ALTER TABLE users
ADD COLUMN login VARCHAR(50) NULL AFTER email,
ADD COLUMN avatar_path VARCHAR(500) NULL AFTER login;

-- Step 2: Add unique index on login (allow NULL, unique when set)
CREATE UNIQUE INDEX idx_users_login ON users(login);

-- Step 3: Make password_hash nullable (no longer required)
ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL;

-- Step 4: Default login = part before @ in email for existing users
UPDATE users SET login = SUBSTRING_INDEX(email, '@', 1) WHERE login IS NULL;

-- Step 5: Auth tokens (magic link codes sent by email)
CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_code (user_id, code),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 6: Remember tokens (per-device persistent sessions)
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    device_name VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    last_used_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 7: Cleanup old expired tokens (run periodically)
-- DELETE FROM auth_tokens WHERE expires_at < NOW();
-- DELETE FROM remember_tokens WHERE expires_at < NOW();
