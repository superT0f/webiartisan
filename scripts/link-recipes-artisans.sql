-- ============================================================
-- WebIArtisan — Lien recettes / artisans (à exécuter sur la prod)
-- Lie la Tarte aux pommes et le Pain perdu à la Boulangerie du Village.
-- ============================================================
SET NAMES utf8mb4;

INSERT IGNORE INTO local_recipe_artisans (recipe_id, artisan_id)
SELECT r.id, a.id
FROM local_recipes r
CROSS JOIN local_artisans a
WHERE r.slug IN ('tarte-aux-pommes-normandes', 'pain-perdu-brioche')
  AND a.company_name = 'Boulangerie du Village';
