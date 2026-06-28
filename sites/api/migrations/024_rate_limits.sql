-- Migration 024 — Rate limiting table
-- Compteurs de requêtes par IP, par endpoint, par fenêtre de 60s
CREATE TABLE IF NOT EXISTS api_rate_limits (ip VARCHAR(45) NOT NULL, endpoint VARCHAR(50) NOT NULL, window_start INT UNSIGNED NOT NULL, count SMALLINT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY (ip, endpoint, window_start), INDEX idx_window (window_start)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
