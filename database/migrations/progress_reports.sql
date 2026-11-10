-- =====================================================================
-- Migration: Create progress_reports table
-- Stores Student Progress Reports for Main Campus and Montessori students.
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- Run once against bmc_portal database.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `progress_reports` (
    `id`          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_id`  INT          NOT NULL
        COMMENT 'FK to students.id',
    `term`        VARCHAR(50)  NOT NULL DEFAULT 'Final'
        COMMENT 'First Term / Mid Term / Final Term / Annual Exam',
    `session`     VARCHAR(50)  NOT NULL DEFAULT ''
        COMMENT 'Academic session e.g. 2025-2026',
    `form_data`   TEXT         NOT NULL
        COMMENT 'Full JSON: basic info, english, mathematics, remarks',
    `reported_by` INT          NOT NULL
        COMMENT 'FK to users.id — teacher / vp_main / wing_head who created the report',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_pr_student` (`student_id`),
    CONSTRAINT `fk_pr_student`
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pr_reporter`
        FOREIGN KEY (`reported_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
