-- Migration pour le module Avis Google avec tracking complet
-- Exécuter avec: sql-helper create_reviews_tables.sql

-- Table pour les campagnes d'avis avec tracking complet
CREATE TABLE IF NOT EXISTS `google_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `status` enum('pending','sent','opened','viewed','reviewed','manual_reviewed') NOT NULL DEFAULT 'pending',
  
  -- Tracking timestamps
  `email_sent_at` timestamp NULL DEFAULT NULL,
  `email_opened_at` timestamp NULL DEFAULT NULL,
  `form_viewed_at` timestamp NULL DEFAULT NULL,
  `review_submitted_at` timestamp NULL DEFAULT NULL,
  `last_followup_at` timestamp NULL DEFAULT NULL,
  
  -- Configuration Google
  `google_review_url` text DEFAULT NULL,
  `google_places_api_key` varchar(255) DEFAULT NULL,
  
  -- Contenu généré
  `pregenerated_review_text` text DEFAULT NULL,
  `questions_answers` json DEFAULT NULL,
  `email_template_id` int(11) DEFAULT NULL,
  
  -- Tracking tokens
  `tracking_pixel_token` varchar(64) DEFAULT NULL,
  `form_tracking_token` varchar(64) DEFAULT NULL,
  `review_tracking_token` varchar(64) DEFAULT NULL,
  
  -- Suivi et validation
  `followup_count` int(11) DEFAULT 0,
  `manual_review_submitted` tinyint(1) DEFAULT 0,
  `manual_review_notes` text DEFAULT NULL,
  
  -- Métadonnées
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_tenant_client` (`tenant_id`, `client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_tracking_tokens` (`tracking_pixel_token`, `form_tracking_token`, `review_tracking_token`),
  KEY `idx_dates` (`email_sent_at`, `email_opened_at`, `review_submitted_at`),
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les templates de questions d'avis
CREATE TABLE IF NOT EXISTS `review_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `questions_json` json NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `usage_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`, `is_active`),
  
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter les champs Google Places à la table tenants
ALTER TABLE `tenants` 
ADD COLUMN IF NOT EXISTS `google_places_api_key` varchar(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `google_business_name` varchar(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `google_business_id` varchar(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `google_business_address` text DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `google_review_url_template` varchar(500) DEFAULT NULL;

-- Insérer un template par défaut pour tous les tenants existants
INSERT IGNORE INTO `review_templates` (`tenant_id`, `title`, `description`, `questions_json`, `is_default`)
SELECT 
    id as tenant_id,
    'Template par défaut - Artisan' as title,
    'Questions standards pour les artisans' as description,
    JSON_ARRAY(
        JSON_OBJECT('id', 1, 'question', 'Comment s''est déroulée la prestation ?', 'type', 'rating', 'required', true),
        JSON_OBJECT('id', 2, 'question', 'Êtes-vous satisfait(e) du résultat final ?', 'type', 'rating', 'required', true),
        JSON_OBJECT('id', 3, 'question', 'Le travail a-t-il été réalisé dans les temps ?', 'type', 'rating', 'required', false),
        JSON_OBJECT('id', 4, 'question', 'Recommanderiez-vous notre travail à vos proches ?', 'type', 'rating', 'required', true),
        JSON_OBJECT('id', 5, 'question', 'Un commentaire sur votre expérience ? (facultatif)', 'type', 'text', 'required', false)
    ) as questions_json,
    1 as is_default
FROM `tenants`;

-- Créer un index pour les performances de recherche
CREATE INDEX IF NOT EXISTS `idx_reviews_funnel` ON `google_reviews` 
(`tenant_id`, `status`, `email_sent_at`, `email_opened_at`, `form_viewed_at`, `review_submitted_at`);
