-- ============================================================
-- WebiArtisan — Migration 045 : Inscriptions bêta (campagne QR)
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS local_beta_signups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    city VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email (email),
    INDEX idx_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
