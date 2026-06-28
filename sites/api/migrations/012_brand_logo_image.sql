-- Migration: Add logo_image_url to brands table
-- Created: 2026-03-08

ALTER TABLE brands
ADD COLUMN IF NOT EXISTS logo_image_url VARCHAR(500) NULL AFTER logo_svg,
ADD COLUMN IF NOT EXISTS logo_image_path VARCHAR(255) NULL AFTER logo_image_url;

-- Update brand.php to handle logo_image_url in GET and SAVE
-- Update the save endpoint to accept logo_image_url
