-- ============================================================
-- WebiArtisan — Migration 033 : Artisan premium subscription
-- ============================================================
SET NAMES utf8mb4;

ALTER TABLE local_artisans
    ADD COLUMN plan ENUM('free','premium') NOT NULL DEFAULT 'free'
        AFTER status,
    ADD COLUMN stripe_customer_id VARCHAR(255) NULL
        AFTER plan,
    ADD COLUMN stripe_subscription_id VARCHAR(255) NULL
        AFTER stripe_customer_id,
    ADD COLUMN subscription_status ENUM('active','past_due','canceled','unpaid','incomplete') NULL
        AFTER stripe_subscription_id,
    ADD COLUMN subscription_period_end DATETIME NULL
        AFTER subscription_status,
    ADD COLUMN subscription_canceled_at DATETIME NULL
        AFTER subscription_period_end,
    ADD COLUMN claimed_at DATETIME NULL
        AFTER subscription_canceled_at,
    ADD COLUMN claimed_by_artisan_id INT NULL
        AFTER claimed_at,
    ADD COLUMN source ENUM('manual','osm','sirene') NOT NULL DEFAULT 'manual'
        AFTER claimed_by_artisan_id,
    ADD COLUMN is_imported TINYINT(1) NOT NULL DEFAULT 0
        AFTER source;

CREATE TABLE IF NOT EXISTS artisan_subscription_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    artisan_id INT NOT NULL,
    stripe_event_id VARCHAR(255) NOT NULL UNIQUE,
    event_type VARCHAR(64) NOT NULL,
    payload JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_artisan_id (artisan_id),
    INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
