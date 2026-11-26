-- =====================================================================
-- Migration: ABA Therapy Data Column
-- Adds aba_data JSON column to behaviour_therapy_reports so the full
-- ABA Therapy Program form can be stored alongside the basic fields.
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- =====================================================================

ALTER TABLE behaviour_therapy_reports
    ADD COLUMN aba_data TEXT NULL COMMENT 'Full JSON ABA Therapy Program report data' AFTER goals_next_month;
