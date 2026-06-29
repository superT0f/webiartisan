-- ============================================================
-- WebiArtisan — Seed data : Vert-Saint-Denis (Seine-et-Marne, 77240)
-- ============================================================

SET NAMES utf8mb4;

SET @vsd_id = (SELECT id FROM local_cities WHERE slug = 'vert-saint-denis' LIMIT 1);

-- S'assurer que les catégories utiles existent
INSERT IGNORE INTO local_categories (slug, name, icon, color, sort_order) VALUES
('boulanger',   'Boulanger',   '🥖', '#D84315', 5),
('coiffeur',    'Coiffeur',    '💈', '#C2185B', 6),
('electricien', 'Électricien', '⚡', '#F57F17', 2),
('plombier',    'Plombier',    '🔧', '#1565C0', 1),
('jardinage',   'Jardinage / Espaces verts', '🌿', '#2E7D32', 7);

-- Catégories déjà créées par migration 025
SET @cat_boulanger = (SELECT id FROM local_categories WHERE slug = 'boulanger' LIMIT 1);
SET @cat_coiffeur  = (SELECT id FROM local_categories WHERE slug = 'coiffeur' LIMIT 1);
SET @cat_electricien = (SELECT id FROM local_categories WHERE slug = 'electricien' LIMIT 1);
SET @cat_plombier  = (SELECT id FROM local_categories WHERE slug = 'plombier' LIMIT 1);
SET @cat_jardinage = (SELECT id FROM local_categories WHERE slug = 'jardinage' LIMIT 1);

-- Artisans de démonstration
INSERT IGNORE INTO local_artisans
    (city_id, category_id, company_name, description, phone, email, website, address,
     latitude, longitude, status, is_verified, is_featured, email_verified)
VALUES
(@vsd_id, @cat_boulanger, 'Boulangerie Vert-Saint-Denis',
 'Boulangerie familiale : pain, viennoiseries et sandwiches préparés sur place.',
 '01 64 10 10 10', 'boulangerie@vsd-local.fr', NULL,
 '12 rue Pasteur, 77240 Vert-Saint-Denis',
 48.5644, 2.6186, 'active', TRUE, TRUE, TRUE),

(@vsd_id, @cat_coiffeur, 'Coiffure VSD',
 'Salon de coiffure mixte, coupes modernes et soins bio.',
 '01 64 10 20 20', 'coiffure.vsd@free.fr', NULL,
 '5 rue de la République, 77240 Vert-Saint-Denis',
 48.5650, 2.6175, 'active', TRUE, FALSE, TRUE),

(@vsd_id, @cat_electricien, 'Élec Services VSD',
 'Installation, rénovation et dépannage électrique pour particuliers et entreprises.',
 '01 64 10 30 30', 'contact@elec-services-vsd.fr', 'https://elec-services-vsd.fr',
 '18 rue Pasteur, 77240 Vert-Saint-Denis',
 48.5635, 2.6195, 'active', TRUE, FALSE, TRUE),

(@vsd_id, @cat_plombier, 'Plomberie Vert-Saint-Denis',
 'Dépannage plomberie, chauffage et sanitaires. Intervention rapide.',
 '01 64 10 40 40', 'plomberie@vsd-local.fr', NULL,
 '7 rue des Écoles, 77240 Vert-Saint-Denis',
 48.5655, 2.6165, 'active', TRUE, TRUE, TRUE),

(@vsd_id, @cat_jardinage, 'Jardins de Sénart',
 'Entretien de jardins, élagage, création de massifs et tonte.',
 '01 64 10 50 50', 'contact@jardins-senart.fr', NULL,
 '25 rue de la Garenne, 77240 Vert-Saint-Denis',
 48.5625, 2.6205, 'active', TRUE, FALSE, TRUE);

-- Services
SET @boulangerie_vsd_id = (SELECT id FROM local_artisans WHERE company_name = 'Boulangerie Vert-Saint-Denis' AND city_id = @vsd_id LIMIT 1);
SET @coiffeur_vsd_id    = (SELECT id FROM local_artisans WHERE company_name = 'Coiffure VSD' AND city_id = @vsd_id LIMIT 1);
SET @elec_vsd_id        = (SELECT id FROM local_artisans WHERE company_name = 'Élec Services VSD' AND city_id = @vsd_id LIMIT 1);
SET @plombier_vsd_id    = (SELECT id FROM local_artisans WHERE company_name = 'Plomberie Vert-Saint-Denis' AND city_id = @vsd_id LIMIT 1);

INSERT IGNORE INTO local_services (artisan_id, name, description, price_range, duration, sort_order) VALUES
(@boulangerie_vsd_id, 'Pain traditionnel', 'Baguettes et pains spéciaux', '1€-5€', 'Tous les jours', 1),
(@boulangerie_vsd_id, 'Plateau sandwich', 'Plateaux pour réceptions et entreprises', 'Sur devis', 'Sur commande', 2),
(@coiffeur_vsd_id, 'Coupe femme', 'Coupe, brushing et soin', '35€', '1h', 1),
(@elec_vsd_id, 'Mise aux normes', 'Diagnostic et mise aux normes électrique', 'Sur devis', '1j-2j', 1),
(@plombier_vsd_id, 'Dépannage urgence', 'Intervention rapide 7j/7', '80€', '1h', 1);

-- Recettes
INSERT IGNORE INTO local_recipes
    (city_id, title, slug, description, image_url, prep_time_minutes, cook_time_minutes, servings, difficulty, season, is_premium, is_incomplete, status, submitted_by)
VALUES
(@vsd_id, 'Pain perdu à la brioche', 'pain-perdu-brioche-vsd', 'Recette familiale pour utiliser la brioche de la boulangerie locale.', 'https://images.unsplash.com/photo-1484723091739-30a097e8f929?w=800', 10, 10, 4, 'very_easy', 'winter', FALSE, FALSE, 'published', 'Boulangerie Vert-Saint-Denis'),
(@vsd_id, 'Galette des rois maison', 'galette-rois-vsd', 'Galette frangipane à partager en famille.', 'https://images.unsplash.com/photo-1517433670267-08bbd4be890f?w=800', 40, 30, 8, 'medium', 'winter', FALSE, TRUE, 'published', 'Boulangerie Vert-Saint-Denis');

SET @pain_vsd_id = (SELECT id FROM local_recipes WHERE slug = 'pain-perdu-brioche-vsd' LIMIT 1);
SET @galette_vsd_id = (SELECT id FROM local_recipes WHERE slug = 'galette-rois-vsd' LIMIT 1);

INSERT IGNORE INTO local_recipe_ingredients (recipe_id, name, quantity, unit, is_local, is_optional, sort_order) VALUES
(@pain_vsd_id, 'Brioche', 6, 'tranche', TRUE, FALSE, 1),
(@pain_vsd_id, 'Œufs', 2, 'pièce', TRUE, FALSE, 2),
(@pain_vsd_id, 'Lait', 250, 'ml', TRUE, FALSE, 3),
(@galette_vsd_id, 'Pâte feuilletée', 2, 'pièce', FALSE, FALSE, 1),
(@galette_vsd_id, 'Poudre d\'amandes', 100, 'g', FALSE, FALSE, 2),
(@galette_vsd_id, 'Sucre', 80, 'g', FALSE, FALSE, 3);

INSERT IGNORE INTO local_recipe_steps (recipe_id, step_number, instruction) VALUES
(@pain_vsd_id, 1, 'Battre les œufs avec le lait et le sucre.'),
(@pain_vsd_id, 2, 'Tremper les tranches de brioche dans le mélange.'),
(@pain_vsd_id, 3, 'Faire dorer 2-3 minutes de chaque côté dans une poêle beurrée.'),
(@galette_vsd_id, 1, 'Préparer la crème frangipane avec beurre, sucre, œufs et amandes.'),
(@galette_vsd_id, 2, 'Étaler la crème sur un disque de pâte feuilletée.'),
(@galette_vsd_id, 3, 'Recouvrir du second disque et souder les bords.'),
(@galette_vsd_id, 4, 'Dorer au jaune d\'œuf et cuire 30 minutes à 180°C.');

INSERT IGNORE INTO local_recipe_artisans (recipe_id, artisan_id) VALUES
(@pain_vsd_id, @boulangerie_vsd_id),
(@galette_vsd_id, @boulangerie_vsd_id);

-- Prospects
INSERT IGNORE INTO local_prospects
    (city_id, source_poi_id, name, type, zone, address, phone, email, website, latitude, longitude, pitch, weakness, is_active)
SELECT
    @vsd_id,
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
WHERE p.city_id = @vsd_id;

INSERT IGNORE INTO local_prospects
    (city_id, name, type, zone, address, phone, email, latitude, longitude, pitch, weakness, is_active)
VALUES
(@vsd_id, 'Restaurant Le Sénart', 'restaurant', 'Centre-bourg', '9 rue Pasteur, 77240 Vert-Saint-Denis', '01 64 10 60 60', 'contact@restaurant-senart.fr', 48.5640, 2.6190, 'Mettre en avant les produits locaux sur la carte.', 'Faible présence en ligne.', TRUE),
(@vsd_id, 'Boucherie de la Garenne', 'boucherie', 'Nord', '14 rue de la Garenne, 77240 Vert-Saint-Denis', '01 64 10 70 70', NULL, 48.5660, 2.6150, 'Proposer viandes locales aux restaurants et traiteurs.', 'Pas de site internet.', TRUE);

-- Offres Spin
INSERT IGNORE INTO local_spin_offers (artisan_id, label, description, stock_total, stock_remaining, is_active) VALUES
(@boulangerie_vsd_id, '1 pain offert', 'Un pain offert pour tout achat supérieur à 5€.', 100, 100, TRUE),
(@coiffeur_vsd_id, '-15% sur la coupe', 'Réduction de 15% sur la première coupe.', 50, 50, TRUE),
(@plombier_vsd_id, 'Diagnostic gratuit', 'Diagnostic gratuit pour tout dépannage.', 200, 200, TRUE);
