-- =====================================================================
-- Migration: Soft-Delete for Students
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- Run once against bmc_portal database.
-- =====================================================================

-- 1. Add deleted_at column to students table
ALTER TABLE `students`
    ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`;

-- 2. (Optional) Index to speed up the IS NULL filter used on all listings
ALTER TABLE `students`
    ADD INDEX `idx_students_deleted_at` (`deleted_at`);

-- =====================================================================
-- NOTES
-- =====================================================================
-- After applying this migration:
--   * All student list queries in student-affairs/students.php will
--     automatically filter out rows where deleted_at IS NOT NULL.
--   * Deleting a student from the Students page will now SET deleted_at
--     instead of permanently removing the row.
--   * Admins can restore or permanently delete soft-deleted students
--     from: portal/admin/recycle-bin.php
--   * Students soft-deleted more than 30 days ago are flagged in the
--     recycle bin UI for permanent deletion.
-- =====================================================================
