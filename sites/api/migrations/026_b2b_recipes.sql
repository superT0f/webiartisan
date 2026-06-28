-- ============================================================
-- WebIArtisan — Migration 026 : Prospection B2B & Recettes
-- ============================================================

SET NAMES utf8mb4;

-- Ensure rate limiting table exists for fresh dev environments
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45) NOT NULL,
    endpoint     VARCHAR(100) NOT NULL,
    window_start INT NOT NULL,
    count        INT NOT NULL DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_rate_limit (ip, endpoint, window_start),
    INDEX idx_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE local_artisans
    ADD COLUMN is_admin BOOLEAN NOT NULL DEFAULT FALSE AFTER is_featured;

CREATE TABLE IF NOT EXISTS local_prospects (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    city_id         INT NOT NULL,
    source_poi_id   INT DEFAULT NULL,
    name            VARCHAR(200) NOT NULL,
    type            VARCHAR(100) NOT NULL,
    zone            VARCHAR(100) DEFAULT NULL,
    address         TEXT,
    phone           VARCHAR(20),
    email           VARCHAR(255),
    website         VARCHAR(500),
    instagram       VARCHAR(100),
    latitude        DECIMAL(10,7) DEFAULT NULL,
    longitude       DECIMAL(10,7) DEFAULT NULL,
    pitch           TEXT,
    weakness        TEXT,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_city_active (city_id, is_active),
    INDEX idx_city_zone (city_id, zone),
    INDEX idx_type (type),
    CONSTRAINT fk_prospects_city FOREIGN KEY (city_id) REFERENCES local_cities(id) ON DELETE CASCADE,
    CONSTRAINT fk_prospects_poi  FOREIGN KEY (source_poi_id) REFERENCES local_pois(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_prospect_follow_ups (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    prospect_id INT NOT NULL,
    artisan_id  INT NOT NULL,
    status      ENUM('tocontact','contacted','meeting','converted','declined') NOT NULL DEFAULT 'tocontact',
    notes       TEXT,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_follow_up (prospect_id, artisan_id),
    CONSTRAINT fk_follow_prospect FOREIGN KEY (prospect_id) REFERENCES local_prospects(id) ON DELETE CASCADE,
    CONSTRAINT fk_follow_artisan  FOREIGN KEY (artisan_id)  REFERENCES local_artisans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_recipes (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    city_id           INT NOT NULL,
    title             VARCHAR(200) NOT NULL,
    slug              VARCHAR(220) NOT NULL UNIQUE,
    description       TEXT,
    image_url         VARCHAR(500),
    prep_time_minutes INT DEFAULT 0,
    cook_time_minutes INT DEFAULT 0,
    servings          INT DEFAULT 1,
    difficulty        ENUM('very_easy','easy','medium','hard') NOT NULL DEFAULT 'easy',
    season            ENUM('spring','summer','autumn','winter','all') NOT NULL DEFAULT 'all',
    is_premium        BOOLEAN DEFAULT FALSE,
    is_incomplete     BOOLEAN DEFAULT FALSE,
    parent_recipe_id  INT DEFAULT NULL,
    status            ENUM('published','reported','archived') NOT NULL DEFAULT 'published',
    submitted_by      VARCHAR(100),
    submitter_email   VARCHAR(255),
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_city_status (city_id, status),
    INDEX idx_difficulty (difficulty),
    INDEX idx_season (season),
    CONSTRAINT fk_recipes_city   FOREIGN KEY (city_id) REFERENCES local_cities(id) ON DELETE CASCADE,
    CONSTRAINT fk_recipes_parent FOREIGN KEY (parent_recipe_id) REFERENCES local_recipes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_recipe_ingredients (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id    INT NOT NULL,
    name         VARCHAR(150) NOT NULL,
    quantity     DECIMAL(10,2) DEFAULT NULL,
    unit         VARCHAR(50) DEFAULT NULL,
    is_local     BOOLEAN DEFAULT FALSE,
    is_optional  BOOLEAN DEFAULT FALSE,
    sort_order   INT DEFAULT 0,
    CONSTRAINT fk_ing_recipe FOREIGN KEY (recipe_id) REFERENCES local_recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_recipe_steps (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id    INT NOT NULL,
    step_number  INT NOT NULL,
    instruction  TEXT NOT NULL,
    CONSTRAINT fk_step_recipe FOREIGN KEY (recipe_id) REFERENCES local_recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_recipe_artisans (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id  INT NOT NULL,
    artisan_id INT NOT NULL,
    UNIQUE KEY uk_recipe_artisan (recipe_id, artisan_id),
    CONSTRAINT fk_reca_recipe   FOREIGN KEY (recipe_id)  REFERENCES local_recipes(id) ON DELETE CASCADE,
    CONSTRAINT fk_reca_artisan  FOREIGN KEY (artisan_id) REFERENCES local_artisans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_recipe_reports (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id  INT NOT NULL,
    reason     TEXT,
    reporter_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_report_recipe FOREIGN KEY (recipe_id) REFERENCES local_recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
