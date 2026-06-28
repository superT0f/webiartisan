-- Migration: Add social and legal fields to brands table
-- Created: 2026-03-08

ALTER TABLE brands
ADD COLUMN IF NOT EXISTS social JSON NULL AFTER contact,
ADD COLUMN IF NOT EXISTS legal JSON NULL AFTER social;

-- Add comments for documentation
-- social: stores website, facebook, instagram, linkedin, twitter URLs
-- legal: stores siret, siren, vat_number, kbis, ape_code, legal_form, rcs, rm
