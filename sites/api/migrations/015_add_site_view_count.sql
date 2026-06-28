-- Migration: Add view_count to sites table
-- Created: 2026-03-12

ALTER TABLE sites
ADD COLUMN view_count INT DEFAULT 0 AFTER public;

-- Also add it to legacy table if exists
-- ALTER TABLE ai_generated_sites ADD COLUMN view_count INT DEFAULT 0 AFTER ip_list;
