-- 040_user_sessions.sql — Sessions consommateur multiples (une ligne par appareil/session)
CREATE TABLE IF NOT EXISTS local_user_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, -- signé, comme local_users.id (compat FK)
  token_hash CHAR(64) NOT NULL,
  device_label VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  last_used_at DATETIME NULL,
  UNIQUE KEY uq_token_hash (token_hash),
  KEY idx_user_exp (user_id, expires_at),
  CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill : préserver les sessions existantes (personne n'est déconnecté)
INSERT INTO local_user_sessions (user_id, token_hash, device_label, created_at, expires_at)
SELECT id, session_token, 'legacy', NOW(), session_exp
FROM local_users
WHERE session_token IS NOT NULL AND session_exp > NOW();
