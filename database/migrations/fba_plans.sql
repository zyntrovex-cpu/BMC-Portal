-- =====================================================================
-- Migration: FBA / Behavior Management Plan Table
-- Creates fba_plans table for storing complete digital FBA records.
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- Run once against bmc_portal database.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `fba_plans` (
    `id`           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_id`   INT          NOT NULL,
    `session_no`   VARCHAR(30)  NULL     COMMENT 'Session number / label',
    `session_date` DATE         NULL     COMMENT 'Date of session',
    `review_date`  DATE         NULL     COMMENT 'Scheduled review date',
    `form_data`    TEXT         NOT NULL COMMENT 'Full JSON of all 9 form sections',
    `recorded_by`  INT          NOT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_fba_student` (`student_id`),
    CONSTRAINT `fk_fba_student`  FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fba_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
