-- ============================================================
-- WebiArtisan — Migration 031 : File d'attente d'emails
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS email_queue (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    to_email     VARCHAR(255) NOT NULL,
    subject      VARCHAR(255) NOT NULL,
    html_body    MEDIUMTEXT NOT NULL,
    from_email   VARCHAR(255) NULL,
    from_name    VARCHAR(255) NULL,
    reply_to     VARCHAR(255) NULL,
    metadata     JSON NULL COMMENT 'contexte: type, user_id, token, redirect...',
    status       ENUM('pending','sent','failed','retrying') NOT NULL DEFAULT 'pending',
    attempts     INT NOT NULL DEFAULT 0,
    error_log    TEXT NULL,
    sent_at      DATETIME NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_created (status, created_at),
    INDEX idx_attempts (status, attempts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='File d\'attente asynchrone d\'emails';
