-- ============================================================
-- Reset des données de test de Combs-la-Ville (city_id = 1)
-- ============================================================
-- Ce script est DESTRUCTEUR. Il crée des backups horodatées
-- avant suppression. À exécuter manuellement via phpMyAdmin.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Date du backup (inclut l'heure pour éviter les collisions si relancé le même jour)
SET @backup_suffix = DATE_FORMAT(NOW(), '_backup_%Y%m%d_%H%i%s');

-- Vérification : afficher le nombre de lignes concernées
SELECT 'POI' AS table_name, COUNT(*) AS rows_to_delete FROM local_pois WHERE city_id = 1
UNION ALL
SELECT 'artisans', COUNT(*) FROM local_artisans WHERE city_id = 1;

-- Backups
SET @sql = CONCAT('CREATE TABLE IF NOT EXISTS local_schedules', @backup_suffix, ' LIKE local_schedules');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = CONCAT('INSERT INTO local_schedules', @backup_suffix, ' SELECT s.* FROM local_schedules s JOIN local_pois p ON s.poi_id = p.id WHERE p.city_id = 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('CREATE TABLE IF NOT EXISTS local_pois', @backup_suffix, ' LIKE local_pois');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = CONCAT('INSERT INTO local_pois', @backup_suffix, ' SELECT * FROM local_pois WHERE city_id = 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('CREATE TABLE IF NOT EXISTS local_artisans', @backup_suffix, ' LIKE local_artisans');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = CONCAT('INSERT INTO local_artisans', @backup_suffix, ' SELECT * FROM local_artisans WHERE city_id = 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Suppressions (filles d'abord)
DELETE s FROM local_schedules s
JOIN local_pois p ON s.poi_id = p.id
WHERE p.city_id = 1;

DELETE FROM local_reviews WHERE artisan_id IN (
    SELECT id FROM local_artisans WHERE city_id = 1
);

DELETE FROM local_services WHERE artisan_id IN (
    SELECT id FROM local_artisans WHERE city_id = 1
);

DELETE tm FROM local_testimonial_media tm
JOIN local_testimonials t ON tm.testimonial_id = t.id
WHERE t.artisan_id IN (SELECT id FROM local_artisans WHERE city_id = 1);

DELETE tr FROM local_testimonial_reports tr
JOIN local_testimonials t ON tr.testimonial_id = t.id
WHERE t.artisan_id IN (SELECT id FROM local_artisans WHERE city_id = 1);

DELETE FROM local_testimonials WHERE artisan_id IN (
    SELECT id FROM local_artisans WHERE city_id = 1
);

DELETE ri FROM local_recipe_ingredients ri
JOIN local_recipes r ON ri.recipe_id = r.id
WHERE r.city_id = 1;

DELETE rs FROM local_recipe_steps rs
JOIN local_recipes r ON rs.recipe_id = r.id
WHERE r.city_id = 1;

DELETE ra FROM local_recipe_artisans ra
JOIN local_recipes r ON ra.recipe_id = r.id
WHERE r.city_id = 1;

DELETE rr FROM local_recipe_reports rr
JOIN local_recipes r ON rr.recipe_id = r.id
WHERE r.city_id = 1;

DELETE FROM local_recipes WHERE city_id = 1;

DELETE FROM local_spin_wins
WHERE offer_id IN (
    SELECT id FROM local_spin_offers
    WHERE artisan_id IN (SELECT id FROM local_artisans WHERE city_id = 1)
);

DELETE FROM local_spin_offers WHERE artisan_id IN (
    SELECT id FROM local_artisans WHERE city_id = 1
);

DELETE FROM local_game_instances WHERE city_id = 1;

DELETE pf FROM local_prospect_follow_ups pf
JOIN local_prospects pr ON pf.prospect_id = pr.id
WHERE pr.city_id = 1;

DELETE FROM local_prospects WHERE city_id = 1;

DELETE FROM local_pois WHERE city_id = 1;
DELETE FROM local_artisans WHERE city_id = 1;

SET FOREIGN_KEY_CHECKS = 1;
