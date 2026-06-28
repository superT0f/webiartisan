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
