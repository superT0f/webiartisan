-- ============================================================
-- Seed qualitatif Combs-la-Ville (city_id = 1)
-- ============================================================
-- Données géo issues d'OpenStreetMap / Nominatim.
-- Horaires à vérifier / affiner selon la réalité.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Admin local (mot de passe à remplacer par le hash généré)
-- On supprime d'abord les éventuels doublons pour cette ville/email
DELETE FROM local_artisans WHERE city_id = 1 AND email = 'supert0f@proton.me';

INSERT INTO local_artisans
    (city_id, category_id, company_name, email, status, email_verified, is_admin, plan, is_verified, created_at, updated_at)
VALUES
    (1, NULL, 'Admin Combs-la-Ville', 'supert0f@proton.me', 'active', 1, 1, 'premium', 1, NOW(), NOW());

-- Mise à jour du password_hash (à jouer séparément après génération du hash)
-- UPDATE local_artisans SET password_hash = '<HASH_BCRYPT>' WHERE email = 'supert0f@proton.me' AND city_id = 1;

-- POI
-- Sources OSM/Nominatim :
-- Mairie de Combs-la-Ville : way 168058004, lat 48.6649311, lon 2.5653851, Rue Sommeville
-- Piscine (Rue Baptiste Marcet) : way 112340438, lat 48.6585085, lon 2.5660060
-- Lamotte Tabac Librairie : node 11358214476, lat 48.6592914, lon 2.5645944, Passage des Artistes
-- Lidl : node 1125144523, lat 48.6593660, lon 2.5641628, 1 Rue Pablo Picasso
-- Intermarché Super : way 437854421, lat 48.6480093, lon 2.5627704, 2 Rue Charles Fabry
INSERT INTO local_pois
    (city_id, type, name, address, phone, website, email, latitude, longitude, description, meta, is_active, sort_order)
VALUES
(1, 'mairie', 'Mairie de Combs-la-Ville', 'Rue Sommeville, 77380 Combs-la-Ville', '01 64 14 77 00', 'https://www.combs-la-ville.fr', 'mairie@combs-la-ville.fr', 48.6649311, 2.5653851, 'Hôtel de ville de Combs-la-Ville.', NULL, 1, 1),
(1, 'piscine', 'Centre Aquatique Camille Muffat', 'Rue Baptiste Marcet, 77380 Combs-la-Ville', NULL, 'https://www.combs-la-ville.fr', NULL, 48.6585085, 2.5660060, 'Piscine municipale avec bassin sportif et ludique.', NULL, 1, 2),
(1, 'tabac', 'Tabac La Motte', 'Passage des Artistes, 77380 Combs-la-Ville', NULL, NULL, NULL, 48.6592914, 2.5645944, 'Bureau de tabac, presse et librairie.', NULL, 1, 3),
(1, 'supermarche', 'Lidl Combs-la-Ville', '1 Rue Pablo Picasso, 77380 Combs-la-Ville', NULL, 'https://www.lidl.fr', NULL, 48.6593660, 2.5641628, 'Supermarché discount.', '{"opening_hours":"Mo-Sa 08:30-20:00; Su off"}', 1, 4),
(1, 'supermarche', 'Intermarché Combs-la-Ville', '2 Rue Charles Fabry, 77380 Combs-la-Ville', NULL, 'https://www.intermarche.com', NULL, 48.6480093, 2.5627704, 'Supermarché avec pharmacie, opticien, vapoteuse, boulangerie et station essence sur place.', '{"sub_shops":["Pharmacie","Opticien","Vapoteuse","Boulangerie","Station essence"]}', 1, 5);

-- Horaires Lidl (à vérifier)
INSERT INTO local_schedules (poi_id, day_of_week, open_time, close_time, is_closed)
SELECT id, 0, '08:30', '20:00', 0 FROM local_pois WHERE name = 'Lidl Combs-la-Ville'
UNION ALL SELECT id, 1, '08:30', '20:00', 0 FROM local_pois WHERE name = 'Lidl Combs-la-Ville'
UNION ALL SELECT id, 2, '08:30', '20:00', 0 FROM local_pois WHERE name = 'Lidl Combs-la-Ville'
UNION ALL SELECT id, 3, '08:30', '20:00', 0 FROM local_pois WHERE name = 'Lidl Combs-la-Ville'
UNION ALL SELECT id, 4, '08:30', '20:00', 0 FROM local_pois WHERE name = 'Lidl Combs-la-Ville'
UNION ALL SELECT id, 5, '08:30', '20:00', 0 FROM local_pois WHERE name = 'Lidl Combs-la-Ville'
UNION ALL SELECT id, 6, NULL, NULL, 1 FROM local_pois WHERE name = 'Lidl Combs-la-Ville';

-- Horaires Mairie (à vérifier)
INSERT INTO local_schedules (poi_id, day_of_week, open_time, close_time, is_closed)
SELECT id, 1, '08:30', '12:00', 0 FROM local_pois WHERE name = 'Mairie de Combs-la-Ville'
UNION ALL SELECT id, 1, '13:30', '17:30', 0 FROM local_pois WHERE name = 'Mairie de Combs-la-Ville'
UNION ALL SELECT id, 2, '08:30', '12:00', 0 FROM local_pois WHERE name = 'Mairie de Combs-la-Ville'
UNION ALL SELECT id, 2, '13:30', '17:30', 0 FROM local_pois WHERE name = 'Mairie de Combs-la-Ville'
UNION ALL SELECT id, 3, '08:30', '12:00', 0 FROM local_pois WHERE name = 'Mairie de Combs-la-Ville'
UNION ALL SELECT id, 3, '13:30', '17:30', 0 FROM local_pois WHERE name = 'Mairie de Combs-la-Ville'
UNION ALL SELECT id, 4, '08:30', '12:00', 0 FROM local_pois WHERE name = 'Mairie de Combs-la-Ville'
UNION ALL SELECT id, 4, '13:30', '17:30', 0 FROM local_pois WHERE name = 'Mairie de Combs-la-Ville'
UNION ALL SELECT id, 5, '08:30', '12:00', 0 FROM local_pois WHERE name = 'Mairie de Combs-la-Ville'
UNION ALL SELECT id, 5, '13:30', '17:30', 0 FROM local_pois WHERE name = 'Mairie de Combs-la-Ville'
UNION ALL SELECT id, 0, NULL, NULL, 1 FROM local_pois WHERE name = 'Mairie de Combs-la-Ville'
UNION ALL SELECT id, 6, NULL, NULL, 1 FROM local_pois WHERE name = 'Mairie de Combs-la-Ville';

SET FOREIGN_KEY_CHECKS = 1;
