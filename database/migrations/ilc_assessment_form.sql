-- =====================================================================
-- Migration: ILC Assessment Form (Full Digital Form Storage)
-- Adds assessment_data JSON column and enrollment tracking to
-- admission_requests. Run once against bmc_portal database.
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- =====================================================================

ALTER TABLE admission_requests
    ADD COLUMN assessment_data    TEXT         NULL COMMENT 'JSON Q1-Q4 ILC assessment form answers' AFTER disability_notes,
    ADD COLUMN enrolled_student_id INT          NULL COMMENT 'students.id after SA approval enrollment' AFTER assessment_data,
    ADD COLUMN enrolled_gr_no     VARCHAR(30)  NULL COMMENT 'Auto-generated GR number on enrollment' AFTER enrolled_student_id;
