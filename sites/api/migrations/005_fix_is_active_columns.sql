-- ============================================
-- Fix: Add is_active column to missing tables
-- Dashboard API uses is_active filter but columns don't exist
-- ============================================

-- Add is_active to factures
ALTER TABLE factures 
ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER status;

-- Add is_active to devis
ALTER TABLE devis 
ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER status;

-- Add is_active to chantiers
ALTER TABLE chantiers 
ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER status;

-- Add is_active to clients
ALTER TABLE clients 
ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER notes;

-- Set all existing rows to active
UPDATE factures SET is_active = TRUE WHERE is_active IS NULL;
UPDATE devis SET is_active = TRUE WHERE is_active IS NULL;
UPDATE chantiers SET is_active = TRUE WHERE is_active IS NULL;
UPDATE clients SET is_active = TRUE WHERE is_active IS NULL;

-- Add indexes for dashboard queries
CREATE INDEX idx_factures_active ON factures(tenant_id, is_active, status);
CREATE INDEX idx_devis_active ON devis(tenant_id, is_active, status);
CREATE INDEX idx_chantiers_active ON chantiers(tenant_id, is_active, status);
CREATE INDEX idx_clients_active ON clients(tenant_id, is_active);
