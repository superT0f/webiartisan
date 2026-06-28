-- ============================================
-- Fix: Add updated_at column to multiple tables
-- Dashboard API uses updated_at for activity feed
-- ============================================

-- Add updated_at to activity_log
ALTER TABLE activity_log 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add updated_at to chantiers
ALTER TABLE chantiers 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add updated_at to chantier_medias
ALTER TABLE chantier_medias 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add updated_at to clients
ALTER TABLE clients 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add updated_at to devis
ALTER TABLE devis 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add updated_at to factures
ALTER TABLE factures 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
