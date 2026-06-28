-- ============================================
-- Fix: Add reference column to factures and devis
-- Dashboard uses 'reference' but tables only have 'numero'
-- ============================================

-- Add reference to factures (alias for numero)
ALTER TABLE factures 
ADD COLUMN reference VARCHAR(50) NULL AFTER numero;

-- Populate with numero values
UPDATE factures 
SET reference = numero
WHERE reference IS NULL;

-- Add reference to devis
ALTER TABLE devis 
ADD COLUMN reference VARCHAR(50) NULL AFTER numero;

-- Populate with numero values
UPDATE devis 
SET reference = numero
WHERE reference IS NULL;

-- Add indexes
CREATE INDEX idx_factures_reference ON factures(reference);
CREATE INDEX idx_devis_reference ON devis(reference);
