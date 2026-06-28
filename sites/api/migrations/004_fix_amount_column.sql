-- ============================================
-- Fix: Add amount column to factures and devis
-- Dashboard API uses 'amount' but tables only have 'total_ht'
-- ============================================

-- Add amount column to factures (will store TTC total)
ALTER TABLE factures 
ADD COLUMN amount DECIMAL(12,2) NULL AFTER total_ht;

-- Populate with calculated value (total_ht + TVA)
UPDATE factures 
SET amount = ROUND(total_ht * (1 + COALESCE(tva_rate, 0) / 100), 2)
WHERE amount IS NULL;

-- Add amount column to devis
ALTER TABLE devis 
ADD COLUMN amount DECIMAL(12,2) NULL AFTER total_ht;

-- Populate with calculated value
UPDATE devis 
SET amount = ROUND(total_ht * (1 + COALESCE(tva_rate, 0) / 100), 2)
WHERE amount IS NULL;

-- Add indexes for dashboard queries
CREATE INDEX idx_factures_amount ON factures(tenant_id, status, amount);
CREATE INDEX idx_devis_amount ON devis(tenant_id, status, amount);
