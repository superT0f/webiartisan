-- ============================================================
-- WebiArtisan — Seed data : Combs-la-Ville (Seine-et-Marne, 77380)
-- ============================================================

SET NAMES utf8mb4;

SET @combs_id = (SELECT id FROM local_cities WHERE slug = 'combs-la-ville' LIMIT 1);

-- S'assurer que les catégories utiles existent
INSERT IGNORE INTO local_categories (slug, name, icon, color, sort_order) VALUES
('boulanger', 'Boulanger', '🥖', '#D84315', 5),
('coiffeur',  'Coiffeur',  '💈', '#C2185B', 6),
('peintre',   'Peintre',   '🎨', '#6A1B9A', 3),
('menuisier', 'Menuisier', '🪚', '#558B2F', 4),
('jardinage', 'Jardinage / Espaces verts', '🌿', '#2E7D32', 7);

-- Catégories déjà créées par migration 025 ; on récupère les IDs utiles
SET @cat_boulanger = (SELECT id FROM local_categories WHERE slug = 'boulanger' LIMIT 1);
SET @cat_coiffeur  = (SELECT id FROM local_categories WHERE slug = 'coiffeur' LIMIT 1);
SET @cat_peintre   = (SELECT id FROM local_categories WHERE slug = 'peintre' LIMIT 1);
SET @cat_menuisier = (SELECT id FROM local_categories WHERE slug = 'menuisier' LIMIT 1);
SET @cat_jardinage = (SELECT id FROM local_categories WHERE slug = 'jardinage' LIMIT 1);

-- Artisans de démonstration
INSERT IGNORE INTO local_artisans
    (city_id, category_id, company_name, description, phone, email, website, address,
     latitude, longitude, status, is_verified, is_featured, email_verified)
VALUES
(@combs_id, @cat_boulanger, 'Boulangerie Combs Centre',
 'Pain artisanal, viennoiseries et pâtisseries maison au centre de Combs-la-Ville.',
 '01 60 60 11 22', 'contact@boulangerie-combs.fr', NULL,
 '10 place Charles de Gaulle, 77380 Combs-la-Ville',
 48.6614, 2.5628, 'active', TRUE, TRUE, TRUE),

(@combs_id, @cat_coiffeur, 'Salon L\'Hair Combs',
 'Coiffure mixte, coupes, couleurs et soins capillaires sans rendez-vous.',
 '01 60 60 33 44', 'salon.lhair@orange.fr', NULL,
 '22 avenue du Général de Gaulle, 77380 Combs-la-Ville',
 48.6602, 2.5641, 'active', TRUE, FALSE, TRUE),

(@combs_id, @cat_peintre, 'Peintures & Décors 77',
 'Peinture intérieure, extérieure, papier peint et conseil déco pour Combs et environs.',
 '01 60 60 55 66', 'contact@peintures77.fr', 'https://peintures77.fr',
 '5 rue du Bois de la Grange, 77380 Combs-la-Ville',
 48.6621, 2.5610, 'active', TRUE, FALSE, TRUE),

(@combs_id, @cat_menuisier, 'Menuiserie Combs Bois',
 'Fabrication et pose de menuiseries, portes, fenêtres et meubles sur mesure.',
 '01 60 60 77 88', 'menuiserie@combsbois.fr', NULL,
 '8 rue de la Garenne, 77380 Combs-la-Ville',
 48.6595, 2.5655, 'active', TRUE, TRUE, TRUE),

(@combs_id, @cat_jardinage, 'Verts & Jardins Combs',
 'Entretien de jardins, taille de haies, débroussaillage et aménagement paysager.',
 '01 60 60 99 00', 'contact@verts-jardins-combs.fr', NULL,
 '15 route de Lieusaint, 77380 Combs-la-Ville',
 48.6580, 2.5680, 'active', TRUE, FALSE, TRUE);

-- Services
SET @boulangerie_combs_id = (SELECT id FROM local_artisans WHERE company_name = 'Boulangerie Combs Centre' AND city_id = @combs_id LIMIT 1);
SET @coiffeur_combs_id    = (SELECT id FROM local_artisans WHERE company_name = 'Salon L\'Hair Combs' AND city_id = @combs_id LIMIT 1);
SET @peintre_combs_id     = (SELECT id FROM local_artisans WHERE company_name = 'Peintures & Décors 77' AND city_id = @combs_id LIMIT 1);
SET @menuisier_combs_id   = (SELECT id FROM local_artisans WHERE company_name = 'Menuiserie Combs Bois' AND city_id = @combs_id LIMIT 1);

INSERT IGNORE INTO local_services (artisan_id, name, description, price_range, duration, sort_order) VALUES
(@boulangerie_combs_id, 'Pain traditionnel', 'Pain au levain et baguettes tradition', '1€-5€', 'Tous les jours', 1),
(@boulangerie_combs_id, 'Pâtisseries maison', 'Tartes, éclairs et gâteaux sur commande', '3€-25€', 'Sur commande', 2),
(@coiffeur_combs_id, 'Coupe homme', 'Coupe et shampoing', '15€', '30 min', 1),
(@coiffeur_combs_id, 'Coupe femme', 'Coupe, brushing et conseil', '35€', '1h', 2),
(@peintre_combs_id, 'Peinture intérieure', 'Murs et plafonds', 'Sur devis', '1j-3j', 1),
(@menuisier_combs_id, 'Pose de fenêtres', 'Fenêtres PVC, bois ou alu', 'Sur devis', '1j-2j', 1);

-- Recettes
INSERT IGNORE INTO local_recipes
    (city_id, title, slug, description, image_url, prep_time_minutes, cook_time_minutes, servings, difficulty, season, is_premium, is_incomplete, status, submitted_by)
VALUES
(@combs_id, 'Croissants maison', 'croissants-maison-combs', 'Recette de croissants feuilletés à faire avec le beurre et la farine du boulanger local.', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=800', 30, 20, 8, 'hard', 'winter', FALSE, TRUE, 'published', 'Boulangerie Combs Centre'),
(@combs_id, 'Tarte aux mirabelles', 'tarte-mirabelles-combs', 'Tarte aux mirabelles de Seine-et-Marne, simple et fruitée.', 'https://images.unsplash.com/photo-1519915028121-7d3463d20b13?w=800', 20, 35, 6, 'easy', 'summer', FALSE, FALSE, 'published', 'Boulangerie Combs Centre');

SET @croissants_id = (SELECT id FROM local_recipes WHERE slug = 'croissants-maison-combs' LIMIT 1);
SET @mirabelles_id = (SELECT id FROM local_recipes WHERE slug = 'tarte-mirabelles-combs' LIMIT 1);

INSERT IGNORE INTO local_recipe_ingredients (recipe_id, name, quantity, unit, is_local, is_optional, sort_order) VALUES
(@croissants_id, 'Farine T55', 500, 'g', TRUE, FALSE, 1),
(@croissants_id, 'Beurre', 250, 'g', TRUE, FALSE, 2),
(@croissants_id, 'Levure fraîche', 20, 'g', FALSE, FALSE, 3),
(@mirabelles_id, 'Mirabelles', 500, 'g', TRUE, FALSE, 1),
(@mirabelles_id, 'Pâte brisée', 1, 'pièce', FALSE, FALSE, 2),
(@mirabelles_id, 'Sucre', 80, 'g', FALSE, FALSE, 3);

INSERT IGNORE INTO local_recipe_steps (recipe_id, step_number, instruction) VALUES
(@croissants_id, 1, 'Préparer la détrempe avec farine, eau, sucre, sel et levure.'),
(@croissants_id, 2, 'Incorporer le beurre de tourage en plusieurs étapes.'),
(@croissants_id, 3, 'Façonner les croissants et laisser pousser.'),
(@croissants_id, 4, 'Cuire 20 minutes à 180°C.'),
(@mirabelles_id, 1, 'Dénoyauter les mirabelles.'),
(@mirabelles_id, 2, 'Étaler la pâte dans un moule.'),
(@mirabelles_id, 3, 'Disposer les mirabelles et saupoudrer de sucre.'),
(@mirabelles_id, 4, 'Cuire 35 minutes à 180°C.');

INSERT IGNORE INTO local_recipe_artisans (recipe_id, artisan_id) VALUES
(@croissants_id, @boulangerie_combs_id),
(@mirabelles_id, @boulangerie_combs_id);

-- Prospects (à partir des POI existants + compléments)
INSERT IGNORE INTO local_prospects
    (city_id, source_poi_id, name, type, zone, address, phone, email, website, latitude, longitude, pitch, weakness, is_active)
SELECT
    @combs_id,
    p.id,
    p.name,
    p.type,
    'Centre-ville',
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
WHERE p.city_id = @combs_id;

INSERT IGNORE INTO local_prospects
    (city_id, name, type, zone, address, phone, email, latitude, longitude, pitch, weakness, is_active)
VALUES
(@combs_id, 'Brasserie Le Combsien', 'restaurant', 'Centre-ville', '3 place Charles de Gaulle, 77380 Combs-la-Ville', '01 60 60 12 34', 'contact@brasserie-combsien.fr', 48.6615, 2.5630, 'Valoriser les produits locaux dans la carte.', 'Peu de visibilité en ligne.', TRUE),
(@combs_id, 'Fleuriste Combs Fleurs', 'fleuriste', 'Centre-ville', '7 avenue de la Gare, 77380 Combs-la-Ville', '01 60 60 56 78', NULL, 48.6608, 2.5645, 'Fournir des compositions florales aux artisans et restaurants.', 'Pas de présence digitale.', TRUE);

-- Offres Spin
INSERT IGNORE INTO local_spin_offers (artisan_id, label, description, stock_total, stock_remaining, is_active) VALUES
(@boulangerie_combs_id, '1 croissant offert', 'Un croissant offert avec un café acheté.', 100, 100, TRUE),
(@coiffeur_combs_id, '-20% sur la coupe', 'Réduction de 20% sur toute coupe.', 50, 50, TRUE),
(@menuisier_combs_id, 'Devis gratuit', 'Devis gratuit pour tout projet menuiserie.', 200, 200, TRUE);
