-- ============================================================
-- WebiArtisan — Migration 038 : Recipes default to pending (moderation)
-- ============================================================
-- New community recipe submissions must be approved by an admin
-- before appearing publicly. Existing rows keep their status.
-- Idempotent: MODIFY produces the same schema when re-run.
-- ============================================================
SET NAMES utf8mb4;

ALTER TABLE local_recipes
    MODIFY COLUMN status ENUM('pending','published','reported','archived') NOT NULL DEFAULT 'pending';
