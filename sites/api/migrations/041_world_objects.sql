-- ============================================================
-- WebiArtisan — Migration 041 : Objets du monde (gamification carte)
-- Déchets à ramasser, trésors, cadeaux artisans, énergie, quêtes.
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS local_world_objects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    city VARCHAR(64) NOT NULL,
    object_type ENUM('dechet','canette','papier','tresor','cadeau_artisan') NOT NULL,
    lat DECIMAL(10,7) NOT NULL,
    lng DECIMAL(11,7) NOT NULL,
    xp_value SMALLINT NOT NULL,
    energy_cost TINYINT NOT NULL,
    status ENUM('active','collected','expired') NOT NULL DEFAULT 'active',
    spawned_by ENUM('system','artisan') NOT NULL DEFAULT 'system',
    artisan_id INT NULL,
    collected_by INT NULL,
    collected_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_city_status (city, status),
    INDEX idx_status_expires (status, expires_at),
    INDEX idx_geo (lat, lng)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_object_pickups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    object_id INT NOT NULL,
    object_type VARCHAR(32) NOT NULL,
    xp_awarded INT NOT NULL,
    picked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_object (object_id),
    INDEX idx_user_date (user_id, picked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_daily_quests (
    code VARCHAR(32) PRIMARY KEY,
    label VARCHAR(128) NOT NULL,
    target_count SMALLINT NOT NULL,
    reward_xp SMALLINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO local_daily_quests (code, label, target_count, reward_xp) VALUES
('collect_5_dechets', 'Ramasser 5 déchets', 5, 30),
('visit_2_artisans', 'Visiter 2 artisans (check-in)', 2, 30),
('find_1_tresor', 'Trouver 1 trésor', 1, 40),
('collect_10_total', 'Ramasser 10 objets', 10, 50),
('clean_streak', 'Ramasser 3 jours de suite', 3, 60)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    target_count = VALUES(target_count),
    reward_xp = VALUES(reward_xp);

CREATE TABLE IF NOT EXISTS local_user_quests (
    user_id INT NOT NULL,
    quest_code VARCHAR(32) NOT NULL,
    quest_date DATE NOT NULL,
    progress SMALLINT NOT NULL DEFAULT 0,
    completed TINYINT(1) NOT NULL DEFAULT 0,
    claimed TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, quest_code, quest_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exécution unique (comme 035) : ne pas rejouer ce ALTER.
ALTER TABLE local_users
    ADD COLUMN energy INT NOT NULL DEFAULT 100 AFTER xp,
    ADD COLUMN energy_updated_at DATETIME NULL AFTER energy;
