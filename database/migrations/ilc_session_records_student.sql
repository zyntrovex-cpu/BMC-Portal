-- =====================================================================
-- Migration: Add student_id, session_date, session_type to ilc_session_records
-- Links session records to specific ILC students.
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- Run once against bmc_portal database.
-- =====================================================================

ALTER TABLE `ilc_session_records`
    ADD COLUMN `student_id`   INT          NULL
        COMMENT 'FK to students.id — which student this record belongs to'
        AFTER `id`,
    ADD COLUMN `session_date` DATE         NULL
        COMMENT 'Date the session took place'
        AFTER `description`,
    ADD COLUMN `session_type` VARCHAR(100) NULL
        COMMENT 'Type of session (e.g. Therapy, IEP Meeting, Progress Review)'
        AFTER `session_date`,
    MODIFY COLUMN `expires_at` DATETIME NULL
        COMMENT 'NULL = no auto-delete; non-null = auto-deleted after this datetime',
    ADD KEY `idx_isr_student` (`student_id`),
    ADD CONSTRAINT `fk_isr_student`
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE;
