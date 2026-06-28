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
