-- =====================================================================
-- Migration: Add Montessori Class-2 and Class-3
-- Safely inserts missing Montessori classes using INSERT IGNORE.
-- The existing Montessori classes (Beginner, Advance, Prep, Class-1)
-- are already present in bmc_portal_complete.sql (IDs 20-23).
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- Run once against bmc_portal database.
-- =====================================================================

INSERT IGNORE INTO classes (name, grade, section, is_ilc, is_montessori, wing)
SELECT 'Class-2', 2, 'A', 0, 1, 'montessori'
WHERE NOT EXISTS (
    SELECT 1 FROM classes WHERE name = 'Class-2' AND wing = 'montessori'
);

INSERT IGNORE INTO classes (name, grade, section, is_ilc, is_montessori, wing)
SELECT 'Class-3', 3, 'A', 0, 1, 'montessori'
WHERE NOT EXISTS (
    SELECT 1 FROM classes WHERE name = 'Class-3' AND wing = 'montessori'
);
