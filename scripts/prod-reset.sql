-- ============================================================
-- WebIArtisan — Production reset for Livry POC
-- Run this file in phpMyAdmin (or mysql CLI) on the prod DB.
-- WARNING: drops all local_* tables and recreates them.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS local_recipe_reports;
DROP TABLE IF EXISTS local_recipe_artisans;
DROP TABLE IF EXISTS local_recipe_steps;
DROP TABLE IF EXISTS local_recipe_ingredients;
DROP TABLE IF EXISTS local_recipes;
DROP TABLE IF EXISTS local_prospect_follow_ups;
DROP TABLE IF EXISTS local_prospects;
DROP TABLE IF EXISTS local_schedules;
DROP TABLE IF EXISTS local_pois;
DROP TABLE IF EXISTS local_reviews;
DROP TABLE IF EXISTS local_services;
DROP TABLE IF EXISTS local_artisans;
DROP TABLE IF EXISTS local_categories;
DROP TABLE IF EXISTS local_cities;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- WebIArtisan — Artisans Locaux par Ville
-- Migration 025 : Nouveau modèle données artisans locaux
-- Compatible avec la base existante (aucune table modifiée)
-- ============================================================

SET NAMES utf8mb4;

-- --------------------------------------------------------
-- TABLE : local_cities — Villes / Points d'intérêt principal
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS local_cities (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    slug         VARCHAR(100) UNIQUE NOT NULL COMMENT 'combs-la-ville',
    name         VARCHAR(150) NOT NULL COMMENT 'Combs-la-Ville',
    postal_code  VARCHAR(10) NOT NULL COMMENT '77380',
    department   VARCHAR(3)  NOT NULL COMMENT '77',
    region       VARCHAR(100) DEFAULT 'Île-de-France',
    country      CHAR(2) DEFAULT 'FR',
    latitude     DECIMAL(10,7),
    longitude    DECIMAL(10,7),
    population   INT UNSIGNED,
    description  TEXT COMMENT 'Courte description de la ville',
    is_active    BOOLEAN DEFAULT TRUE,
    subdomain    VARCHAR(200) COMMENT 'artisans-combs.prigent.tech',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_postal (postal_code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Villes couvertes par la plateforme';

-- --------------------------------------------------------
-- TABLE : local_categories — Catégories de métiers
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS local_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(100) UNIQUE NOT NULL COMMENT 'plombier',
    name        VARCHAR(150) NOT NULL COMMENT 'Plombier',
    icon        VARCHAR(10) DEFAULT '🔧' COMMENT 'Emoji ou nom icône',
    color       VARCHAR(7) DEFAULT '#2D6A4F' COMMENT 'Couleur hex',
    parent_id   INT DEFAULT NULL COMMENT 'Catégorie parente (hiérarchie)',
    sort_order  SMALLINT DEFAULT 0,
    is_active   BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (parent_id) REFERENCES local_categories(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_parent (parent_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catégories de métiers artisans';

-- --------------------------------------------------------
-- TABLE : local_artisans — Profils artisans inscrits
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS local_artisans (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    city_id         INT NOT NULL,
    category_id     INT,
    -- Informations professionnelles
    company_name    VARCHAR(200) NOT NULL,
    siret           VARCHAR(14),
    description     TEXT,
    -- Contact
    phone           VARCHAR(20),
    email           VARCHAR(255),
    website         VARCHAR(500),
    address         TEXT,
    -- Géolocalisation
    latitude        DECIMAL(10,7),
    longitude       DECIMAL(10,7),
    -- Médias
    logo_url        VARCHAR(500),
    cover_url       VARCHAR(500),
    -- Statut
    status          ENUM('pending','active','suspended') DEFAULT 'pending'
                    COMMENT 'pending=en attente validation',
    is_verified     BOOLEAN DEFAULT FALSE COMMENT 'SIRET vérifié',
    is_featured     BOOLEAN DEFAULT FALSE COMMENT 'Mis en avant sur la page ville',
    -- Authentification artisan
    email_verified  BOOLEAN DEFAULT FALSE,
    password_hash   VARCHAR(255),
    auth_token      VARCHAR(64) COMMENT 'Token magic link',
    auth_token_exp  TIMESTAMP NULL,
    -- Statistiques
    view_count      INT UNSIGNED DEFAULT 0,
    contact_count   INT UNSIGNED DEFAULT 0,
    -- Méta
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (city_id) REFERENCES local_cities(id) ON DELETE RESTRICT,
    FOREIGN KEY (category_id) REFERENCES local_categories(id) ON DELETE SET NULL,
    INDEX idx_city (city_id),
    INDEX idx_status (status),
    INDEX idx_category (category_id),
    INDEX idx_featured (city_id, is_featured),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Artisans locaux inscrits sur la plateforme';

-- --------------------------------------------------------
-- TABLE : local_services — Services proposés
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS local_services (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    artisan_id  INT NOT NULL,
    name        VARCHAR(200) NOT NULL,
    description TEXT,
    price_range VARCHAR(100) COMMENT 'Ex: 50€-200€ ou Sur devis',
    duration    VARCHAR(100) COMMENT 'Ex: 1h à 3h',
    sort_order  SMALLINT DEFAULT 0,
    FOREIGN KEY (artisan_id) REFERENCES local_artisans(id) ON DELETE CASCADE,
    INDEX idx_artisan (artisan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Services proposés par chaque artisan';

-- --------------------------------------------------------
-- TABLE : local_reviews — Avis clients
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS local_reviews (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    artisan_id      INT NOT NULL,
    reviewer_name   VARCHAR(100) NOT NULL,
    reviewer_email  VARCHAR(255) COMMENT 'Non affiché publiquement',
    rating          TINYINT NOT NULL COMMENT '1 à 5 étoiles',
    comment         TEXT,
    is_approved     BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (artisan_id) REFERENCES local_artisans(id) ON DELETE CASCADE,
    INDEX idx_artisan (artisan_id),
    INDEX idx_approved (artisan_id, is_approved),
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Avis clients sur les artisans';

-- --------------------------------------------------------
-- TABLE : local_pois — Points d'intérêt de la ville
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS local_pois (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    city_id     INT NOT NULL,
    type        ENUM(
                    'mairie','piscine','bibliotheque','mediatheque',
                    'cinema','dechetterie','poste','supermarche',
                    'transport','ecole','college','lycee',
                    'hopital','clinique','pharmacie',
                    'eglise','monument','parc','sport',
                    'autre'
                ) NOT NULL,
    name        VARCHAR(200) NOT NULL,
    address     TEXT,
    phone       VARCHAR(20),
    website     VARCHAR(500),
    email       VARCHAR(255),
    latitude    DECIMAL(10,7),
    longitude   DECIMAL(10,7),
    description TEXT,
    external_id VARCHAR(200) COMMENT 'ID externe (OSM, SNCF, etc.)',
    meta        JSON COMMENT 'Données supplémentaires flexibles',
    is_active   BOOLEAN DEFAULT TRUE,
    sort_order  SMALLINT DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (city_id) REFERENCES local_cities(id) ON DELETE CASCADE,
    INDEX idx_city_type (city_id, type),
    INDEX idx_city_active (city_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Points d intérêt de chaque ville';

-- --------------------------------------------------------
-- TABLE : local_schedules — Horaires des POI
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS local_schedules (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    poi_id       INT NOT NULL,
    day_of_week  TINYINT NOT NULL COMMENT '0=Lundi, 6=Dimanche',
    open_time    TIME,
    close_time   TIME,
    break_start  TIME COMMENT 'Début pause déjeuner',
    break_end    TIME COMMENT 'Fin pause déjeuner',
    is_closed    BOOLEAN DEFAULT FALSE,
    period_start DATE COMMENT 'Début validité (horaires saisonniers)',
    period_end   DATE COMMENT 'Fin validité',
    notes        VARCHAR(500) COMMENT 'Ex: Fermé le 14 juillet',
    FOREIGN KEY (poi_id) REFERENCES local_pois(id) ON DELETE CASCADE,
    INDEX idx_poi (poi_id),
    INDEX idx_day (poi_id, day_of_week),
    CONSTRAINT chk_day CHECK (day_of_week BETWEEN 0 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Horaires d ouverture des points d intérêt';

-- ============================================================
-- DONNÉES SEED : Combs-la-Ville (77380)
-- ============================================================

-- Ville pilote : Combs-la-Ville
INSERT IGNORE INTO local_cities (slug, name, postal_code, department, region, country,
                    latitude, longitude, population, description,
                    is_active, subdomain)
VALUES (
    'combs-la-ville',
    'Combs-la-Ville',
    '77380',
    '77',
    'Île-de-France',
    'FR',
    48.6600000,
    2.5650000,
    23000,
    'Ville dynamique de Seine-et-Marne, aux portes de Paris, avec un tissu d\'artisans locaux de qualité.',
    TRUE,
    'artisans-combs.prigent.tech'
);

-- Catégories d'artisans (principales)
INSERT IGNORE INTO local_categories (slug, name, icon, color, sort_order) VALUES
('plombier',          'Plombier',           '🔧', '#1565C0', 1),
('electricien',       'Électricien',        '⚡', '#F57F17', 2),
('peintre',           'Peintre',            '🎨', '#6A1B9A', 3),
('macon',             'Maçon',              '🧱', '#4E342E', 4),
('menuisier',         'Menuisier',          '🪚', '#558B2F', 5),
('carreleur',         'Carreleur',          '◼️', '#37474F', 6),
('chauffagiste',      'Chauffagiste',       '🔥', '#BF360C', 7),
('serrurier',         'Serrurier',          '🔑', '#455A64', 8),
('jardinage',         'Jardinage / Espaces verts', '🌿', '#2E7D32', 9),
('nettoyage',         'Nettoyage / Entretien', '🧹', '#00838F', 10),
('toiture',           'Couvreur / Toiture', '🏠', '#6D4C41', 11),
('climatisation',     'Climatisation / VMC', '❄️', '#0288D1', 12),
('domotique',         'Domotique / Alarme',  '🏡', '#283593', 13),
('renovation',        'Rénovation générale', '🏗️', '#795548', 14),
('piscine',           'Piscine / Spa',       '🏊', '#039BE5', 15),
('demenagement',      'Déménagement',        '📦', '#EF6C00', 16),
('vitrier',           'Vitrier / Miroitier', '🪟', '#26C6DA', 17),
('plaquiste',         'Plaquiste / Isolation', '🔨', '#8D6E63', 18),
('informatique',      'Informatique / Réseau', '💻', '#1E88E5', 19),
('auto',              'Réparation auto',     '🚗', '#E53935', 20);

-- POIs de Combs-la-Ville
SET @combs_id = (SELECT id FROM local_cities WHERE slug = 'combs-la-ville');

INSERT IGNORE INTO local_pois (city_id, type, name, address, phone, website, latitude, longitude, description, is_active, sort_order) VALUES
(@combs_id, 'mairie', 'Mairie de Combs-la-Ville', 'Place Charles de Gaulle, 77380 Combs-la-Ville', '01 64 13 70 00', 'https://www.combs-la-ville.fr', 48.6600, 2.5650, 'Services administratifs de la ville', TRUE, 1),
(@combs_id, 'piscine', 'Piscine Aquatis', '10 avenue du Bois de la Grange, 77380 Combs-la-Ville', '01 64 13 70 70', 'https://www.combs-la-ville.fr/piscine', 48.6580, 2.5630, 'Centre aquatique municipal', TRUE, 2),
(@combs_id, 'mediatheque', 'Médiathèque de Combs-la-Ville', 'Place Charles de Gaulle, 77380 Combs-la-Ville', '01 64 13 70 80', 'https://www.combs-la-ville.fr/mediatheque', 48.6605, 2.5655, 'Médiathèque municipale', TRUE, 3),
(@combs_id, 'cinema', 'Cinéma Les Toiles', '77380 Combs-la-Ville', '01 64 13 70 90', 'https://www.les-toiles.fr', 48.6595, 2.5660, 'Cinéma municipal de Combs-la-Ville', TRUE, 4),
(@combs_id, 'poste', 'La Poste — Combs-la-Ville', 'Centre commercial, 77380 Combs-la-Ville', '36 31', 'https://www.laposte.fr', 48.6610, 2.5640, 'Bureau de Poste', TRUE, 5),
(@combs_id, 'supermarche', 'Lidl Combs-la-Ville', 'Zone commerciale, 77380 Combs-la-Ville', NULL, 'https://www.lidl.fr', 48.6620, 2.5620, 'Supermarché Lidl', TRUE, 6),
(@combs_id, 'dechetterie', 'Déchetterie de Combs-la-Ville', 'Route de Lieusaint, 77380 Combs-la-Ville', '01 64 13 70 00', NULL, 48.6570, 2.5680, 'Déchetterie intercommunale', TRUE, 7);

-- Horaires Mairie de Combs-la-Ville
SET @combs_mairie_id = (SELECT id FROM local_pois WHERE name = 'Mairie de Combs-la-Ville' AND city_id = @combs_id LIMIT 1);
INSERT IGNORE INTO local_schedules (poi_id, day_of_week, open_time, close_time, break_start, break_end, is_closed) VALUES
(@combs_mairie_id, 0, '08:30:00', '17:00:00', '12:30:00', '13:30:00', FALSE),
(@combs_mairie_id, 1, '08:30:00', '17:00:00', '12:30:00', '13:30:00', FALSE),
(@combs_mairie_id, 2, '08:30:00', '17:00:00', '12:30:00', '13:30:00', FALSE),
(@combs_mairie_id, 3, '08:30:00', '17:00:00', '12:30:00', '13:30:00', FALSE),
(@combs_mairie_id, 4, '08:30:00', '17:00:00', '12:30:00', '13:30:00', FALSE),
(@combs_mairie_id, 5, NULL, NULL, NULL, NULL, TRUE),
(@combs_mairie_id, 6, NULL, NULL, NULL, NULL, TRUE);

-- Horaires Piscine Aquatis
SET @combs_piscine_id = (SELECT id FROM local_pois WHERE name = 'Piscine Aquatis' AND city_id = @combs_id LIMIT 1);
INSERT IGNORE INTO local_schedules (poi_id, day_of_week, open_time, close_time, is_closed) VALUES
(@combs_piscine_id, 0, '07:00:00', '21:00:00', FALSE),
(@combs_piscine_id, 1, '07:00:00', '21:00:00', FALSE),
(@combs_piscine_id, 2, '07:00:00', '21:00:00', FALSE),
(@combs_piscine_id, 3, '07:00:00', '21:00:00', FALSE),
(@combs_piscine_id, 4, '07:00:00', '21:00:00', FALSE),
(@combs_piscine_id, 5, '09:00:00', '18:00:00', FALSE),
(@combs_piscine_id, 6, '09:00:00', '13:00:00', FALSE);

-- Horaires La Poste
SET @combs_poste_id = (SELECT id FROM local_pois WHERE name = 'La Poste — Combs-la-Ville' AND city_id = @combs_id LIMIT 1);
INSERT IGNORE INTO local_schedules (poi_id, day_of_week, open_time, close_time, is_closed) VALUES
(@combs_poste_id, 0, '09:00:00', '18:00:00', FALSE),
(@combs_poste_id, 1, '09:00:00', '18:00:00', FALSE),
(@combs_poste_id, 2, '09:00:00', '18:00:00', FALSE),
(@combs_poste_id, 3, '09:00:00', '18:00:00', FALSE),
(@combs_poste_id, 4, '09:00:00', '18:00:00', FALSE),
(@combs_poste_id, 5, '09:00:00', '12:00:00', FALSE),
(@combs_poste_id, 6, NULL, NULL, TRUE);

-- ============================================================
-- DONNÉES SEED : Vert-Saint-Denis (77240)
-- ============================================================

INSERT IGNORE INTO local_cities (slug, name, postal_code, department, region, country,
                    latitude, longitude, population, description,
                    is_active, subdomain)
VALUES (
    'vert-saint-denis',
    'Vert-Saint-Denis',
    '77240',
    '77',
    'Île-de-France',
    'FR',
    48.5667000,
    2.6167000,
    8000,
    'Commune chaleureuse de Sénart, dynamique et accueillante.',
    TRUE,
    'artisans-vert-saint-denis.prigent.tech'
);

-- POIs de Vert-Saint-Denis
SET @vsd_id = (SELECT id FROM local_cities WHERE slug = 'vert-saint-denis');

INSERT IGNORE INTO local_pois (city_id, type, name, address, phone, website, latitude, longitude, description, is_active, sort_order) VALUES
(@vsd_id, 'mairie', 'Mairie de Vert-Saint-Denis', '2 rue Pasteur, 77240 Vert-Saint-Denis', '01 64 10 59 00', 'https://www.vert-saint-denis.fr', 48.5667, 2.6167, 'Services administratifs', TRUE, 1),
(@vsd_id, 'mediatheque', 'Ferme des Arts', '60 rue Pasteur, 77240 Vert-Saint-Denis', '01 64 10 59 02', 'https://www.vert-saint-denis.fr', 48.5670, 2.6170, 'Médiathèque et centre culturel', TRUE, 2);

-- Horaires Mairie de Vert-Saint-Denis
SET @vsd_mairie_id = (SELECT id FROM local_pois WHERE name = 'Mairie de Vert-Saint-Denis' AND city_id = @vsd_id LIMIT 1);
INSERT IGNORE INTO local_schedules (poi_id, day_of_week, open_time, close_time, break_start, break_end, is_closed) VALUES
(@vsd_mairie_id, 0, '09:00:00', '17:30:00', '12:00:00', '13:30:00', FALSE),
(@vsd_mairie_id, 1, '09:00:00', '17:30:00', '12:00:00', '13:30:00', FALSE),
(@vsd_mairie_id, 2, '09:00:00', '17:30:00', '12:00:00', '13:30:00', FALSE),
(@vsd_mairie_id, 3, '09:00:00', '17:30:00', '12:00:00', '13:30:00', FALSE),
(@vsd_mairie_id, 4, '09:00:00', '17:00:00', '12:00:00', '13:30:00', FALSE),
(@vsd_mairie_id, 5, '09:00:00', '12:00:00', NULL, NULL, FALSE),
(@vsd_mairie_id, 6, NULL, NULL, NULL, NULL, TRUE);
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
-- ============================================================
-- WebIArtisan — Seed data : Livry (Calvados, 14240)
-- ============================================================

SET NAMES utf8mb4;

-- Ville : Livry
INSERT IGNORE INTO local_cities (slug, name, postal_code, department, region, country,
                    latitude, longitude, population, description,
                    is_active, subdomain)
VALUES (
    'livry',
    'Livry',
    '14240',
    '14',
    'Normandie',
    'FR',
    49.1081000,
    -0.7658000,
    752,
    'Petite commune du Calvados en Normandie, au cœur du bocage et de la vie rurale.',
    TRUE,
    'artisans-livry.prigent.tech'
);

SET @livry_id = (SELECT id FROM local_cities WHERE slug = 'livry');

-- Catégories d'artisans
INSERT IGNORE INTO local_categories (slug, name, icon, color, sort_order) VALUES
('plombier',  'Plombier',  '🔧', '#1565C0', 1),
('electricien','Électricien','⚡', '#F57F17', 2),
('peintre',   'Peintre',   '🎨', '#6A1B9A', 3),
('menuisier', 'Menuisier', '🪚', '#558B2F', 4),
('boulanger', 'Boulanger', '🥖', '#D84315', 5),
('coiffeur',  'Coiffeur',  '💈', '#C2185B', 6),
('jardinage', 'Jardinage / Espaces verts', '🌿', '#2E7D32', 7);

SET @cat_plombier  = (SELECT id FROM local_categories WHERE slug = 'plombier');
SET @cat_elec      = (SELECT id FROM local_categories WHERE slug = 'electricien');
SET @cat_peintre   = (SELECT id FROM local_categories WHERE slug = 'peintre');
SET @cat_menuisier = (SELECT id FROM local_categories WHERE slug = 'menuisier');
SET @cat_boulanger = (SELECT id FROM local_categories WHERE slug = 'boulanger');
SET @cat_coiffeur  = (SELECT id FROM local_categories WHERE slug = 'coiffeur');
SET @cat_jardinage = (SELECT id FROM local_categories WHERE slug = 'jardinage');

-- Artisans de démonstration
INSERT IGNORE INTO local_artisans
    (city_id, category_id, company_name, description, phone, email, website, address,
     latitude, longitude, status, is_verified, is_featured, email_verified)
VALUES
(@livry_id, @cat_plombier, 'Livry Plomberie',
 'Intervention rapide en plomberie, chauffage et dépannage d\'urgence sur Livry et les communes voisines.',
 '02 31 00 11 22', 'contact@livry-plomberie.fr', 'https://livry-plomberie.fr',
 '12 rue du Bac, 14240 Livry',
 49.1085000, -0.7652000, 'active', TRUE, TRUE, TRUE),

(@livry_id, @cat_elec, 'Électricité Morel',
 'Installation électrique, rénovation et dépannage pour particuliers et professionnels.',
 '02 31 11 22 33', 'morel.elec@orange.fr', NULL,
 '8 route de Caen, 14240 Livry',
 49.1077000, -0.7663000, 'active', TRUE, FALSE, TRUE),

(@livry_id, @cat_peintre, 'Peintures Lefebvre',
 'Peinture intérieure et extérieure, papier peint et conseil en décoration. Devis gratuit.',
 '02 31 22 33 44', 'peinture.lefebvre@outlook.fr', NULL,
 '5 place de l\'Église, 14240 Livry',
 49.1082000, -0.7660000, 'active', TRUE, TRUE, TRUE),

(@livry_id, @cat_menuisier, 'Menuiserie Durand',
 'Fabrication et pose de menuiseries, portes, fenêtres, escaliers et meubles sur mesure.',
 '02 31 33 44 55', 'durand.menuiserie@live.fr', NULL,
 '3 rue des Tilleuls, 14240 Livry',
 49.1088000, -0.7649000, 'active', TRUE, FALSE, TRUE),

(@livry_id, @cat_boulanger, 'Boulangerie du Village',
 'Pain traditionnel, viennoiseries et pâtisseries artisanales au cœur de Livry.',
 '02 31 44 55 66', 'boulangerie.livry@gmail.com', NULL,
 '1 rue Principale, 14240 Livry',
 49.1075000, -0.7655000, 'active', TRUE, TRUE, TRUE),

(@livry_id, @cat_coiffeur, 'Salon Caprice',
 'Salon de coiffure mixte, coupes, couleurs et soins capillaires sur rendez-vous.',
 '02 31 55 66 77', 'salon.caprice@free.fr', NULL,
 '4 rue de la Mairie, 14240 Livry',
 49.1080000, -0.7665000, 'active', FALSE, FALSE, TRUE),

(@livry_id, @cat_jardinage, 'Vert Évasion',
 'Entretien de jardins, taille de haies, aménagement paysager et débroussaillage.',
 '02 31 66 77 88', 'vert.evasion@sfr.fr', 'https://vert-evasion.fr',
 '15 chemin de la Vallée, 14240 Livry',
 49.1092000, -0.7668000, 'active', TRUE, FALSE, TRUE);

-- Points d'intérêt de Livry
INSERT IGNORE INTO local_pois (city_id, type, name, address, phone, website, latitude, longitude, description, is_active, sort_order) VALUES
(@livry_id, 'mairie', 'Mairie de Livry', 'Le Bourg, 14240 Livry', '02 31 77 80 31', 'https://www.livry14.fr', 49.1081000, -0.7658000, 'Services administratifs de la commune', TRUE, 1),
(@livry_id, 'eglise', 'Église Saint-Martin', 'Route de Caen, 14240 Livry', NULL, NULL, 49.1083000, -0.7661000, 'Église paroissiale de Livry', TRUE, 2),
(@livry_id, 'poste', 'La Poste — Livry', '1 rue Principale, 14240 Livry', '36 31', 'https://www.laposte.fr', 49.1079000, -0.7656000, 'Bureau de poste', TRUE, 3),
(@livry_id, 'supermarche', 'Carrefour Contact Livry', 'Route de Caen, 14240 Livry', '02 31 77 80 40', 'https://www.carrefour.fr', 49.1076000, -0.7662000, 'Supermarché de proximité', TRUE, 4),
(@livry_id, 'pharmacie', 'Pharmacie de Livry', '2 rue Principale, 14240 Livry', '02 31 77 80 32', NULL, 49.1078000, -0.7654000, 'Pharmacie de la commune', TRUE, 5);

-- Horaires Mairie de Livry (lun-ven 9h-17h, pause 12h-13h30, fermé week-end)
SET @livry_mairie_id = (SELECT id FROM local_pois WHERE name = 'Mairie de Livry' AND city_id = @livry_id LIMIT 1);
INSERT IGNORE INTO local_schedules (poi_id, day_of_week, open_time, close_time, break_start, break_end, is_closed) VALUES
(@livry_mairie_id, 0, '09:00:00', '17:00:00', '12:00:00', '13:30:00', FALSE),
(@livry_mairie_id, 1, '09:00:00', '17:00:00', '12:00:00', '13:30:00', FALSE),
(@livry_mairie_id, 2, '09:00:00', '17:00:00', '12:00:00', '13:30:00', FALSE),
(@livry_mairie_id, 3, '09:00:00', '17:00:00', '12:00:00', '13:30:00', FALSE),
(@livry_mairie_id, 4, '09:00:00', '17:00:00', '12:00:00', '13:30:00', FALSE),
(@livry_mairie_id, 5, NULL, NULL, NULL, NULL, TRUE),
(@livry_mairie_id, 6, NULL, NULL, NULL, NULL, TRUE);

-- Horaires La Poste — Livry (lun-ven 9h-12h / 14h-17h, sam 9h-12h, dim fermé)
SET @livry_poste_id = (SELECT id FROM local_pois WHERE name = 'La Poste — Livry' AND city_id = @livry_id LIMIT 1);
INSERT IGNORE INTO local_schedules (poi_id, day_of_week, open_time, close_time, break_start, break_end, is_closed) VALUES
(@livry_poste_id, 0, '09:00:00', '17:00:00', '12:00:00', '14:00:00', FALSE),
(@livry_poste_id, 1, '09:00:00', '17:00:00', '12:00:00', '14:00:00', FALSE),
(@livry_poste_id, 2, '09:00:00', '17:00:00', '12:00:00', '14:00:00', FALSE),
(@livry_poste_id, 3, '09:00:00', '17:00:00', '12:00:00', '14:00:00', FALSE),
(@livry_poste_id, 4, '09:00:00', '17:00:00', '12:00:00', '14:00:00', FALSE),
(@livry_poste_id, 5, '09:00:00', '12:00:00', NULL, NULL, FALSE),
(@livry_poste_id, 6, NULL, NULL, NULL, NULL, TRUE);
-- ============================================================
-- WebIArtisan — Seed prospects B2B pour Livry
-- ============================================================
SET NAMES utf8mb4;

SET @livry_id = (SELECT id FROM local_cities WHERE slug = 'livry' LIMIT 1);

INSERT IGNORE INTO local_prospects
    (city_id, source_poi_id, name, type, zone, address, phone, email, website, latitude, longitude, pitch, weakness, is_active)
SELECT
    @livry_id,
    p.id,
    p.name,
    p.type,
    'Centre-bourg',
    p.address,
    p.phone,
    p.email,
    p.website,
    p.latitude,
    p.longitude,
    NULL,
    NULL,
    TRUE
FROM local_pois p
WHERE p.city_id = @livry_id;

-- Compléments fictifs
INSERT IGNORE INTO local_prospects
    (city_id, name, type, zone, address, phone, email, latitude, longitude, pitch, weakness, is_active)
VALUES
(@livry_id, 'Auberge du Bocage', 'restaurant', 'Nord', 'Route de Caen, 14240 Livry', '02 31 12 34 56', 'contact@aubergedubocage.fr', 49.1090000, -0.7670000, 'Mettre en avant les produits locaux dans la carte du restaurant.', 'Carte actuelle peu locale.', TRUE),
(@livry_id, 'Boucherie Charcuterie Lemoine', 'boucherie', 'Centre-bourg', '3 rue Principale, 14240 Livry', '02 31 23 45 67', NULL, 49.1082000, -0.7659000, 'Fournir viande locale aux artisans traiteurs et restaurants.', 'Pas de visibilité en ligne.', TRUE);
-- ============================================================
-- WebIArtisan — Seed recettes pour Livry
-- ============================================================
SET NAMES utf8mb4;

SET @livry_id = (SELECT id FROM local_cities WHERE slug = 'livry' LIMIT 1);

INSERT IGNORE INTO local_recipes
    (city_id, title, slug, description, image_url, prep_time_minutes, cook_time_minutes, servings, difficulty, season, is_premium, is_incomplete, status, submitted_by)
VALUES
(@livry_id, 'Tarte aux pommes normandes', 'tarte-aux-pommes-normandes', 'Une tarte simple et gourmande avec les pommes du bocage.', 'https://images.unsplash.com/photo-1568571780765-9276ac8b75a2?w=800', 20, 35, 6, 'easy', 'autumn', FALSE, FALSE, 'published', 'Mairie de Livry'),
(@livry_id, 'Pain perdu à la brioche', 'pain-perdu-brioche', 'Idéal pour utiliser la brioche de la boulangerie du village.', 'https://images.unsplash.com/photo-1484723091739-30a097e8f929?w=800', 10, 10, 4, 'very_easy', 'winter', FALSE, TRUE, 'published', 'Boulangerie du Village');

SET @tarte_id = (SELECT id FROM local_recipes WHERE slug = 'tarte-aux-pommes-normandes' LIMIT 1);
SET @pain_id  = (SELECT id FROM local_recipes WHERE slug = 'pain-perdu-brioche' LIMIT 1);

INSERT IGNORE INTO local_recipe_ingredients (recipe_id, name, quantity, unit, is_local, is_optional, sort_order) VALUES
(@tarte_id, 'Pommes', 4, 'pièce', TRUE, FALSE, 1),
(@tarte_id, 'Pâte brisée', 1, 'pièce', FALSE, FALSE, 2),
(@tarte_id, 'Sucre', 50, 'g', FALSE, FALSE, 3),
(@tarte_id, 'Beurre', 30, 'g', TRUE, FALSE, 4),
(@pain_id, 'Brioche', 6, 'tranche', TRUE, FALSE, 1),
(@pain_id, 'Œufs', 2, 'pièce', TRUE, FALSE, 2),
(@pain_id, 'Lait', 250, 'ml', TRUE, FALSE, 3),
(@pain_id, 'Sucre vanillé', 1, 'sachet', FALSE, TRUE, 4);

INSERT IGNORE INTO local_recipe_steps (recipe_id, step_number, instruction) VALUES
(@tarte_id, 1, 'Éplucher et couper les pommes en lamelles.'),
(@tarte_id, 2, 'Étaler la pâte dans un moule à tarte.'),
(@tarte_id, 3, 'Disposer les pommes, saupoudrer de sucre et parsemer de beurre.'),
(@tarte_id, 4, 'Cuire 35 minutes à 180°C.'),
(@pain_id, 1, 'Battre les œufs avec le lait et le sucre.'),
(@pain_id, 2, 'Tremper les tranches de brioche dans le mélange.'),
(@pain_id, 3, 'Faire dorer 2-3 minutes de chaque côté dans une poêle beurrée.');

-- Lien recettes ↔ artisans producteurs
SET @boulangerie_id = (SELECT id FROM local_artisans WHERE company_name = 'Boulangerie du Village' AND city_id = @livry_id LIMIT 1);

INSERT IGNORE INTO local_recipe_artisans (recipe_id, artisan_id) VALUES
(@tarte_id, @boulangerie_id),
(@pain_id, @boulangerie_id);
-- Reset complete
