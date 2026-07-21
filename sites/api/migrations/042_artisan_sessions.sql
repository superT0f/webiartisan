-- ============================================================
-- WebiArtisan — Migration 042 : Sessions artisan multiples
-- Une ligne par appareil/session (miroir de 040_user_sessions).
-- Corrige : « rester connecté » cassé dès qu'on se connecte ailleurs
-- (le slot unique auth_token_* était régénéré à chaque login/lien).
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS local_artisan_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artisan_id INT NOT NULL,
    token_lookup CHAR(64) NOT NULL,   -- sha256(token), indexé
    token_hash VARCHAR(255) NOT NULL, -- bcrypt(token), vérifié à l'auth
    device_label VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    UNIQUE KEY uq_token_lookup (token_lookup),
    INDEX idx_artisan_exp (artisan_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill : préserver les sessions existantes (personne n'est déconnecté)
INSERT IGNORE INTO local_artisan_sessions (artisan_id, token_lookup, token_hash, device_label, created_at, expires_at)
SELECT id, auth_token_lookup, auth_token_hash, 'legacy', NOW(), auth_token_exp
FROM local_artisans
WHERE auth_token_lookup IS NOT NULL
  AND auth_token_hash IS NOT NULL
  AND auth_token_exp > NOW();
