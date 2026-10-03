-- =====================================================================
-- Migration: Add missing subjects
-- Safely inserts new subjects using INSERT ... SELECT WHERE NOT EXISTS
-- so no duplicates are created if the migration is re-run.
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- Run once against bmc_portal database.
-- =====================================================================

INSERT INTO subjects (name, code)
SELECT 'General Knowledge', 'GK'
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code = 'GK');

INSERT INTO subjects (name, code)
SELECT 'Islamic Studies', 'IS'
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code = 'IS');

INSERT INTO subjects (name, code)
SELECT 'Art and Drawing', 'ARTD'
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code = 'ARTD');

INSERT INTO subjects (name, code)
SELECT 'Physical and Social Development', 'PSD'
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code = 'PSD');

INSERT INTO subjects (name, code)
SELECT 'Nazra Quran', 'NZQ'
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code = 'NZQ');

INSERT INTO subjects (name, code)
SELECT 'Computer Studies', 'CSTU'
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code = 'CSTU');

INSERT INTO subjects (name, code)
SELECT 'Sindhi', 'SND'
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code = 'SND');

INSERT INTO subjects (name, code)
SELECT 'Moalamul Quran', 'MLQ'
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code = 'MLQ');

INSERT INTO subjects (name, code)
SELECT 'Biology (Botany and Zoology)', 'BZO'
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code = 'BZO');

INSERT INTO subjects (name, code)
SELECT 'Art', 'ART'
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE code = 'ART');
