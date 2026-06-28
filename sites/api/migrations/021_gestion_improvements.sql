-- ============================================
-- 021 — Gestion improvements
-- Add missing columns to devis, factures, clients
-- ============================================

-- Devis: add valid_until, notes, updated_at
ALTER TABLE devis ADD COLUMN IF NOT EXISTS valid_until DATE DEFAULT NULL;
ALTER TABLE devis ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT NULL;
ALTER TABLE devis ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Factures: add due_date, notes, updated_at
ALTER TABLE factures ADD COLUMN IF NOT EXISTS due_date DATE DEFAULT NULL;
ALTER TABLE factures ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT NULL;
ALTER TABLE factures ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Clients: ensure is_active and updated_at exist
ALTER TABLE clients ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
