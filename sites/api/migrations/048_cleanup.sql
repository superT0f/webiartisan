-- ============================================================
-- WebiArtisan — Migration 048 : Nettoyage prod (VALIDÉE PAR L'UTILISATEUR)
-- À appliquer manuellement via phpMyAdmin (destructif — jamais en auto-migrate).
-- Inventaire : dump prod du 2026-07-27 + croisement avec le code API.
-- ============================================================
SET NAMES utf8mb4;

-- -----------------------------------------------------------
-- A. Tables de backup datées (2026-07-10) — doublons figés des tables live
-- -----------------------------------------------------------
DROP TABLE IF EXISTS local_artisans_backup_20260710;
DROP TABLE IF EXISTS local_artisans_backup_20260710_162057;
DROP TABLE IF EXISTS local_pois_backup_20260710;
DROP TABLE IF EXISTS local_pois_backup_20260710_162057;
DROP TABLE IF EXISTS local_schedules_backup_20260710;
DROP TABLE IF EXISTS local_schedules_backup_20260710_162057;

-- -----------------------------------------------------------
-- B. Tables orphelines du projet legacy (aucune référence dans le code actuel)
-- -----------------------------------------------------------
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS ai_generated_sites;
DROP TABLE IF EXISTS depenses;
DROP TABLE IF EXISTS google_reviews;
DROP TABLE IF EXISTS message_notes;
DROP TABLE IF EXISTS message_tag_relations;
DROP TABLE IF EXISTS message_tags;
DROP TABLE IF EXISTS paiements_client;
DROP TABLE IF EXISTS plan_usage;
DROP TABLE IF EXISTS review_templates;
DROP TABLE IF EXISTS site_analytics;
DROP TABLE IF EXISTS site_visitor_hashes;
DROP TABLE IF EXISTS status_logs;
DROP TABLE IF EXISTS tenant_goals;

-- -----------------------------------------------------------
-- C. Purge des données transitoires (tables conservées)
-- -----------------------------------------------------------
-- Objets du monde ramassés/expirés depuis +7 jours (l'historique joueur
-- est dans local_object_pickups, conservé)
DELETE FROM local_world_objects
 WHERE status IN ('collected','expired') AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- État temps réel des combats de boss : résidus de +1 jour
DELETE FROM local_boss_fights_live
 WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY);

-- File d'emails traités (envoyés) depuis +7 jours
DELETE FROM email_queue
 WHERE status = 'sent' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Buckets de rate limiting de +1 heure (transitoire par design)
-- (pas de created_at en prod : window_start est un timestamp unix)
DELETE FROM api_rate_limits
 WHERE window_start < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Codes email expirés
DELETE FROM local_user_email_codes
 WHERE expires_at < NOW();

-- Sessions expirées (joueurs + artisans)
DELETE FROM local_user_sessions
 WHERE expires_at < NOW();
DELETE FROM local_artisan_sessions
 WHERE expires_at < NOW();

-- -----------------------------------------------------------
-- CONSERVÉS volontairement : local_checkins, local_object_pickups,
-- local_user_actions, local_daily_quests (historique gamification),
-- local_boss_fights (palmarès des combats).
-- -----------------------------------------------------------
