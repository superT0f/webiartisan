-- ============================================================
-- WebiArtisan — Migration 044 : Arène Big Brother
-- ============================================================
SET NAMES utf8mb4;

-- Nouveau type d'objet : le boss (exécution unique, style 035)
ALTER TABLE local_world_objects
MODIFY COLUMN object_type ENUM('dechet','canette','papier','tresor','cadeau_artisan','big_brother') NOT NULL;

CREATE TABLE IF NOT EXISTS local_boss_fights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    object_id INT NOT NULL,
    result ENUM('win','loss') NOT NULL,
    rounds_won TINYINT NOT NULL DEFAULT 0,
    rounds_lost TINYINT NOT NULL DEFAULT 0,
    xp_awarded INT NOT NULL DEFAULT 0,
    fought_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_object (user_id, object_id),
    INDEX idx_object (object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_boss_fights_live (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    user_id INT NOT NULL,
    boss_hp TINYINT NOT NULL DEFAULT 3,
    player_hp TINYINT NOT NULL DEFAULT 3,
    rounds_won TINYINT NOT NULL DEFAULT 0,
    rounds_lost TINYINT NOT NULL DEFAULT 0,
    current_game VARCHAR(16) NULL,
    current_payload JSON NULL,
    status ENUM('ongoing','won','lost') NOT NULL DEFAULT 'ongoing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    theme VARCHAR(32) NOT NULL DEFAULT 'savoir-faire',
    question VARCHAR(255) NOT NULL,
    choices JSON NOT NULL,
    answer_index TINYINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_mate_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fen VARCHAR(128) NOT NULL,
    solution_uci VARCHAR(8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO local_quiz_questions (theme, question, choices, answer_index) VALUES
('metiers', 'Quel artisan fabrique le pain et les viennoiseries ?', '["Le boulanger","Le pâtissier exclusif","Le traiteur","Le fromager"]', 0),
('metiers', 'Quel artisan répare les toitures ?', '["Le maçon","Le couvreur","Le charpentier","Le plâtrier"]', 1),
('metiers', 'Quel artisan taille la pierre ?', '["Le sculpteur sur bois","Le marbrier","Le tailleur de pierre","Le graveur"]', 2),
('metiers', 'Quel artisan pose et répare les serrures ?', '["Le forgeron","Le serrurier","Le métallier","Le plombier"]', 1),
('metiers', 'Quel artisan répare les chaussures ?', '["Le bottier","Le maroquinier","Le cordonnier","Le sellier"]', 2),
('metiers', 'Quel métier fabrique des vitraux ?', '["Le vitrier","Le maître-verrier","Le souffleur de verre","Le miroitier"]', 1),
('metiers', 'Quel artisan entretient les chaudières ?', '["Le plombier","L''électricien","Le chauffagiste","Le ramoneur"]', 2),
('metiers', 'Quel artisan fabrique les escaliers sur mesure ?', '["Le menuisier","L''ébéniste","Le charpentier","L''agenceur"]', 0),
('savoir-faire', 'L''ébéniste travaille principalement :', '["Le métal","Le bois et l''ameublement fin","La pierre","Le cuir"]', 1),
('savoir-faire', 'La maroquinerie française est réputée pour travailler :', '["Le textile","Le cuir","Le bois","Le métal"]', 1),
('savoir-faire', 'Le « compagnonnage » est :', '["Une école publique","Une tradition de formation des artisans","Un syndicat","Une marque d''outils"]', 1),
('savoir-faire', 'Le titre « Meilleur Ouvrier de France » (MOF) récompense :', '["Les plus grandes entreprises","Les meilleurs artisans du savoir-faire","Les écoles de commerce","Les inventeurs"]', 1),
('savoir-faire', 'La coutellerie française célèbre est liée à quelle ville ?', '["Lyon","Thiers","Limoges","Annecy"]', 1),
('savoir-faire', 'Quel outil le plombier utilise pour couper un tuyau en cuivre ?', '["La scie à métaux uniquement","Le coupe-tube","La meuleuse","Le ciseau à froid"]', 1),
('terroir', 'Le camembert est un fromage originaire de :', '["Bretagne","Normandie","Savoie","Alsace"]', 1),
('terroir', 'La « haute couture » est née dans quelle ville ?', '["Lyon","Milan","Paris","Londres"]', 2),
('terroir', 'Le boucher-charcutier français transforme principalement :', '["Le bœuf","Le porc","L''agneau","La volaille"]', 1),
('terroir', 'Quel artisan restaure les meubles anciens ?', '["Le menuisier","Le tapissier-ébéniste","Le charpentier","Le tourneur"]', 1),
('metiers', 'Quel artisan s''occupe des installations électriques ?', '["L''électricien","Le plombier","Le chauffagiste","L''antenniste"]', 0),
('terroir', 'Le fleuriste compose principalement :', '["Des graines","Des bouquets","Des arbres fruitiers","Des potagers"]', 1);

INSERT INTO local_mate_positions (fen, solution_uci) VALUES
('6k1/5ppp/8/8/8/8/8/R5K1 w - - 0 1', 'a1a8'),
('6k1/5ppp/8/8/8/8/8/3Q2K1 w - - 0 1', 'd1d8'),
('r1bqkb1r/pppp1ppp/2n2n2/4p2Q/2B1P3/8/PPPP1PPP/RNB1K1NR w KQkq - 4 4', 'h5f7'),
('3r2k1/5ppp/8/8/8/8/8/3R2K1 w - - 0 1', 'd1d8'),
('6k1/6p1/6Pp/8/8/8/8/3Q2K1 w - - 0 1', 'd1d8'),
('6k1/pp4pp/8/8/8/8/PP4P1/2KR3R w - - 0 1', 'h1h8'),
('6k1/6pp/8/8/8/8/6PP/3Q2K1 w - - 0 1', 'd1d8'),
('6k1/6p1/5pPp/8/8/8/8/3Q2K1 w - - 0 1', 'd1d8'),
('6k1/6p1/6P1/7p/8/8/8/3Q2K1 w - - 0 1', 'd1d8'),
('6k1/5p2/6Pp/7P/8/8/8/3Q2K1 w - - 0 1', 'd1d8'),
('6rk/6pp/8/6N1/8/8/8/7K w - - 0 1', 'g5f7'),
('6k1/6pp/8/8/8/8/8/2R3K1 w - - 0 1', 'c1c8');
