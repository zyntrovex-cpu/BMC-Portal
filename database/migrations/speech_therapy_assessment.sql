-- =====================================================================
-- Migration: Speech Therapy Assessment Form Column
-- Adds assessment_data JSON column to speech_therapy_reports table.
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- Run once against bmc_portal database.
-- =====================================================================

ALTER TABLE `speech_therapy_reports`
    ADD COLUMN `assessment_data` TEXT NULL
        COMMENT 'Full JSON of all 9 ILC Speech & Language Therapy Assessment sections'
        AFTER `goals_next_month`;
