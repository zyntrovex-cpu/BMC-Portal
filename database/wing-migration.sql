-- ================================================================
-- Wing Migration — Add wing column to classes and teachers
-- Safe: no tables dropped, no data deleted
-- Run AFTER ilc-migration.sql + hierarchy-migration.sql
-- ================================================================

USE bmc_portal;

-- Add wing to classes (derived from existing is_ilc / is_montessori flags)
ALTER TABLE classes ADD COLUMN IF NOT EXISTS wing ENUM('main','montessori','ilc') NOT NULL DEFAULT 'main';
UPDATE classes SET wing = 'ilc'         WHERE is_ilc = 1;
UPDATE classes SET wing = 'montessori'  WHERE is_montessori = 1;

-- Add wing to teachers (derived from existing is_ilc flag)
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS wing ENUM('main','montessori','ilc') NOT NULL DEFAULT 'main';
UPDATE teachers SET wing = 'ilc' WHERE is_ilc = 1;
