-- ============================================
-- WebIArtisan Platform — Client Portal Features
-- Rollback script for 017_client_portal_devis_tokens.sql
-- ============================================

-- Remove indexes first
DROP INDEX IF EXISTS idx_devis_token_expires ON devis;
DROP INDEX IF EXISTS idx_devis_client_token ON devis;

-- Remove the UNIQUE constraint and make column nullable
ALTER TABLE devis 
MODIFY COLUMN client_token VARCHAR(64) NULL;

-- Remove columns (reverse order of addition)
ALTER TABLE devis 
DROP COLUMN IF EXISTS acompte_montant,
DROP COLUMN IF EXISTS acompte_pourcentage,
DROP COLUMN IF EXISTS date_validite,
DROP COLUMN IF EXISTS signed_signature_b64,
DROP COLUMN IF EXISTS signed_ip,
DROP COLUMN IF EXISTS signed_at,
DROP COLUMN IF EXISTS client_token_expires_at,
DROP COLUMN IF EXISTS client_token;
