-- ============================================
-- Fix: Add end_date column to chantiers table
-- Dashboard uses end_date to detect late chantiers
-- ============================================

ALTER TABLE chantiers 
ADD COLUMN end_date DATE NULL AFTER status;

-- Optional: Set default end_date for existing chantiers (30 days after creation)
UPDATE chantiers 
SET end_date = DATE_ADD(created_at, INTERVAL 30 DAY)
WHERE end_date IS NULL;

-- Add index for dashboard query
CREATE INDEX idx_chantiers_end_date ON chantiers(tenant_id, end_date, status);
