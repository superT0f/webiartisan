-- ============================================
-- WebIArtisan Platform — Client Portal Features
-- Add client token support to devis table
-- ============================================

-- Add client portal columns to devis table (production-safe)
ALTER TABLE devis 
ADD COLUMN client_token VARCHAR(64) NULL AFTER status,
ADD COLUMN client_token_expires_at DATETIME NULL AFTER client_token,
ADD COLUMN signed_at DATETIME NULL AFTER client_token_expires_at,
ADD COLUMN signed_ip VARCHAR(45) NULL AFTER signed_at,
ADD COLUMN signed_signature_b64 LONGTEXT NULL AFTER signed_ip,
ADD COLUMN date_validite DATE NULL AFTER created_at,
ADD COLUMN acompte_pourcentage DECIMAL(5,2) DEFAULT 0 AFTER tva_rate,
ADD COLUMN acompte_montant DECIMAL(10,2) DEFAULT 0 AFTER acompte_pourcentage;

-- Generate unique tokens for existing devis (only if they don't have one already)
-- Use a shorter format: 32 chars from UUID + padded ID to fit in 64 chars
UPDATE devis SET client_token = CONCAT(
    SUBSTRING(HEX(UUID()), 1, 32), 
    LPAD(id, 8, '0')
) WHERE client_token IS NULL;

-- Now make the column NOT NULL and UNIQUE after all tokens are generated
ALTER TABLE devis 
MODIFY COLUMN client_token VARCHAR(64) NOT NULL,
ADD UNIQUE KEY idx_devis_client_token (client_token);

-- Add indexes for performance
CREATE INDEX idx_devis_token_expires (devis(client_token, client_token_expires_at));
