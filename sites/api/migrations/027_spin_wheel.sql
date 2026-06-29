-- ============================================================
-- WebIArtisan — Migration 027 : Roue consommateur (Spin Wheel)
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS local_users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) UNIQUE NOT NULL,
    magic_token     VARCHAR(64) DEFAULT NULL,
    magic_token_exp DATETIME DEFAULT NULL,
    session_token   VARCHAR(64) DEFAULT NULL,
    session_exp     DATETIME DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_session (session_token, session_exp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Utilisateurs consommateurs de la roue';

CREATE TABLE IF NOT EXISTS local_spin_offers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    artisan_id      INT NOT NULL,
    label           VARCHAR(200) NOT NULL COMMENT 'Texte affiche sur la roue',
    description     TEXT,
    stock_total     INT NOT NULL DEFAULT 0,
    stock_remaining INT NOT NULL DEFAULT 0,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (artisan_id) REFERENCES local_artisans(id) ON DELETE CASCADE,
    INDEX idx_artisan (artisan_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Offres promotionnelles pour la roue';

CREATE TABLE IF NOT EXISTS local_spin_wins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    offer_id    INT NOT NULL,
    artisan_id  INT NOT NULL,
    code        VARCHAR(32) UNIQUE NOT NULL,
    status      ENUM('pending','claimed','expired') DEFAULT 'pending',
    spin_date   DATE NOT NULL,
    claimed_at  TIMESTAMP NULL,
    expires_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES local_users(id)    ON DELETE CASCADE,
    FOREIGN KEY (offer_id)   REFERENCES local_spin_offers(id) ON DELETE RESTRICT,
    FOREIGN KEY (artisan_id) REFERENCES local_artisans(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_artisan_status (artisan_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Gains des utilisateurs';

CREATE TABLE IF NOT EXISTS local_spin_daily_limits (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    city_id     INT NOT NULL,
    spin_date   DATE NOT NULL,
    count       INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_daily (user_id, city_id, spin_date),
    FOREIGN KEY (user_id) REFERENCES local_users(id) ON DELETE CASCADE,
    FOREIGN KEY (city_id) REFERENCES local_cities(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, spin_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Limite de spins par utilisateur, ville et jour';
