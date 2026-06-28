-- ============================================
-- Migration 020: Replace Matomo with Micro-Analytics
-- Drop matomo columns, create lightweight analytics tables
-- ============================================

-- 1. Drop Matomo indexes
DROP INDEX idx_sites_matomo ON sites;
DROP INDEX idx_tenants_matomo ON tenants;

-- 2. Drop Matomo columns
ALTER TABLE sites DROP COLUMN matomo_site_id;
ALTER TABLE tenants DROP COLUMN matomo_site_id;
ALTER TABLE ai_generated_sites DROP COLUMN matomo_site_id;

-- 3. Create micro-analytics daily aggregation table
-- 1 row per site per day = very controlled growth
CREATE TABLE site_analytics (
    site_token VARCHAR(32) NOT NULL,
    day DATE NOT NULL,
    pageviews INT UNSIGNED DEFAULT 0,
    visitors INT UNSIGNED DEFAULT 0,
    mobile INT UNSIGNED DEFAULT 0,
    desktop INT UNSIGNED DEFAULT 0,
    referrer_direct INT UNSIGNED DEFAULT 0,
    referrer_search INT UNSIGNED DEFAULT 0,
    referrer_social INT UNSIGNED DEFAULT 0,
    referrer_other INT UNSIGNED DEFAULT 0,
    PRIMARY KEY (site_token, day),
    INDEX idx_day (day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Visitor dedup table (hash of IP+UA+day, no PII stored)
-- Purge rows older than 90 days via cron
CREATE TABLE site_visitor_hashes (
    site_token VARCHAR(32) NOT NULL,
    day DATE NOT NULL,
    visitor_hash BINARY(16) NOT NULL,
    PRIMARY KEY (site_token, day, visitor_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
