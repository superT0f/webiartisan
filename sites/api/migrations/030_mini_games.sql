-- ============================================================
-- WebiArtisan — Migration 030 : Hub de mini-jeux
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS local_game_types (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    `key`           VARCHAR(50) UNIQUE NOT NULL COMMENT 'coupon, poll, vote, wheel, quiz, bingo, rebus',
    label_fr        VARCHAR(100) NOT NULL,
    description     TEXT,
    is_premium      BOOLEAN NOT NULL DEFAULT FALSE,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    default_config  JSON NOT NULL,
    engine_component VARCHAR(50) NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (`key`),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_game_instances (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    game_type_id        INT NOT NULL,
    artisan_id          INT NOT NULL,
    city_id             INT NOT NULL,
    title               VARCHAR(150) NOT NULL,
    description         TEXT,
    config              JSON NOT NULL,
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    starts_at           TIMESTAMP NULL,
    ends_at             TIMESTAMP NULL,
    max_plays_per_user  INT NOT NULL DEFAULT 1,
    play_cooldown_hours INT NOT NULL DEFAULT 24,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (game_type_id) REFERENCES local_game_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (artisan_id)  REFERENCES local_artisans(id)  ON DELETE CASCADE,
    FOREIGN KEY (city_id)     REFERENCES local_cities(id)    ON DELETE CASCADE,
    INDEX idx_city_active (city_id, is_active),
    INDEX idx_artisan (artisan_id),
    INDEX idx_type (game_type_id),
    INDEX idx_dates (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_game_rewards (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    game_instance_id INT NOT NULL,
    label           VARCHAR(150) NOT NULL,
    reward_type     ENUM('coupon','points','badge','nothing') NOT NULL DEFAULT 'nothing',
    reward_value    JSON NULL,
    probability     DECIMAL(5,4) NULL COMMENT 'for probabilistic games',
    stock           INT NULL,
    claimed_count   INT NOT NULL DEFAULT 0,
    FOREIGN KEY (game_instance_id) REFERENCES local_game_instances(id) ON DELETE CASCADE,
    INDEX idx_instance (game_instance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_game_plays (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    game_instance_id INT NOT NULL,
    user_id         INT NOT NULL,
    result          JSON NULL,
    xp_awarded      INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_instance_id) REFERENCES local_game_instances(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    INDEX idx_instance_user (game_instance_id, user_id),
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed game types
INSERT INTO local_game_types (`key`, label_fr, description, is_premium, default_config, engine_component) VALUES
('coupon', 'Coupon de réduction', 'Révéler un coupon ou une offre.', FALSE, '{"reveal_text":"Découvrez votre offre !"}', 'CouponGame'),
('poll', 'Sondage', 'Répondre à une question.', FALSE, '{"question":"Votre avis nous intéresse","options":["Oui","Non"]}', 'PollGame'),
('vote', 'Vote / Battle', 'Voter pour votre préféré.', FALSE, '{"question":"Lequel préférez-vous ?","options":["Option A","Option B"]}', 'VoteBattleGame'),
('wheel', 'Roue de la chance', 'Tourner la roue pour gagner.', TRUE, '{"segments":[]}', 'WheelGame'),
('quiz', 'Quiz', 'Répondre à des questions.', TRUE, '{"questions":[]}', 'QuizGame'),
('bingo', 'Bingo', 'Carte de bingo locale.', TRUE, '{"grid_size":3}', 'BingoGame'),
('rebus', 'Rébus', 'Résoudre un rébus.', TRUE, '{"puzzle":""}', 'RebusGame')
ON DUPLICATE KEY UPDATE label_fr = VALUES(label_fr), is_premium = VALUES(is_premium);
