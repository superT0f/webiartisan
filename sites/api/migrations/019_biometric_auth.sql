-- Migration: Add support for biometric authentication
-- Allows users to link a device for quick login

CREATE TABLE IF NOT EXISTS biometric_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_id VARCHAR(255) NOT NULL,
    key_hash VARCHAR(255) NOT NULL,
    device_name VARCHAR(100) NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_device_user (device_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
