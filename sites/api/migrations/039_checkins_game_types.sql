-- ============================================================
-- WebiArtisan — Migration 039 : Check-ins carte + nettoyage types de jeux
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS local_checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    city VARCHAR(64) NOT NULL,
    target_type ENUM('artisan','poi') NOT NULL,
    target_id INT NOT NULL,
    xp_awarded INT NOT NULL,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_target (user_id, target_type, target_id, checked_at),
    INDEX idx_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remove phantom game types. Rows still referenced by an instance are kept
-- but deactivated (FK game_type_id is ON DELETE RESTRICT; production has 0
-- instances, so the DELETE removes everything in practice).
DELETE gt FROM local_game_types gt
LEFT JOIN local_game_instances i ON i.game_type_id = gt.id
WHERE gt.`key` IN ('poll','vote','quiz','bingo','rebus') AND i.id IS NULL;

UPDATE local_game_types SET is_active = 0 WHERE `key` IN ('poll','vote','quiz','bingo','rebus');

-- Reseed the two real types: coupon (free engine) and wheel (premium, legacy
-- local_spin_* system — never instantiated as a game instance).
INSERT INTO local_game_types (`key`, label_fr, description, is_premium, is_active, default_config, engine_component) VALUES
('coupon', 'Coupon de réduction', 'Révéler un coupon ou une offre.', FALSE, TRUE, '{"reveal_text":"Découvrez votre offre !"}', 'CouponGame'),
('wheel', 'Roue de la fortune', 'Roue premium liée aux offres de la boutique (système legacy).', TRUE, TRUE, '{"segments":[]}', 'LegacyWheel')
ON DUPLICATE KEY UPDATE
    label_fr = VALUES(label_fr),
    description = VALUES(description),
    is_premium = VALUES(is_premium),
    is_active = VALUES(is_active),
    engine_component = VALUES(engine_component);
