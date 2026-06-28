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
