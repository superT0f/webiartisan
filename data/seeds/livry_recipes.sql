-- ============================================================
-- WebIArtisan — Seed recettes pour Livry
-- ============================================================
SET NAMES utf8mb4;

SET @livry_id = (SELECT id FROM local_cities WHERE slug = 'livry' LIMIT 1);

INSERT IGNORE INTO local_recipes
    (city_id, title, slug, description, image_url, prep_time_minutes, cook_time_minutes, servings, difficulty, season, is_premium, is_incomplete, status, submitted_by)
VALUES
(@livry_id, 'Tarte aux pommes normandes', 'tarte-aux-pommes-normandes', 'Une tarte simple et gourmande avec les pommes du bocage.', 'https://images.unsplash.com/photo-1568571780765-9276ac8b75a2?w=800', 20, 35, 6, 'easy', 'autumn', FALSE, FALSE, 'published', 'Mairie de Livry'),
(@livry_id, 'Pain perdu à la brioche', 'pain-perdu-brioche', 'Idéal pour utiliser la brioche de la boulangerie du village.', 'https://images.unsplash.com/photo-1484723091739-30a097e8f929?w=800', 10, 10, 4, 'very_easy', 'winter', FALSE, TRUE, 'published', 'Boulangerie du Village');

SET @tarte_id = (SELECT id FROM local_recipes WHERE slug = 'tarte-aux-pommes-normandes' LIMIT 1);
SET @pain_id  = (SELECT id FROM local_recipes WHERE slug = 'pain-perdu-brioche' LIMIT 1);

INSERT IGNORE INTO local_recipe_ingredients (recipe_id, name, quantity, unit, is_local, is_optional, sort_order) VALUES
(@tarte_id, 'Pommes', 4, 'pièce', TRUE, FALSE, 1),
(@tarte_id, 'Pâte brisée', 1, 'pièce', FALSE, FALSE, 2),
(@tarte_id, 'Sucre', 50, 'g', FALSE, FALSE, 3),
(@tarte_id, 'Beurre', 30, 'g', TRUE, FALSE, 4),
(@pain_id, 'Brioche', 6, 'tranche', TRUE, FALSE, 1),
(@pain_id, 'Œufs', 2, 'pièce', TRUE, FALSE, 2),
(@pain_id, 'Lait', 250, 'ml', TRUE, FALSE, 3),
(@pain_id, 'Sucre vanillé', 1, 'sachet', FALSE, TRUE, 4);

INSERT IGNORE INTO local_recipe_steps (recipe_id, step_number, instruction) VALUES
(@tarte_id, 1, 'Éplucher et couper les pommes en lamelles.'),
(@tarte_id, 2, 'Étaler la pâte dans un moule à tarte.'),
(@tarte_id, 3, 'Disposer les pommes, saupoudrer de sucre et parsemer de beurre.'),
(@tarte_id, 4, 'Cuire 35 minutes à 180°C.'),
(@pain_id, 1, 'Battre les œufs avec le lait et le sucre.'),
(@pain_id, 2, 'Tremper les tranches de brioche dans le mélange.'),
(@pain_id, 3, 'Faire dorer 2-3 minutes de chaque côté dans une poêle beurrée.');
