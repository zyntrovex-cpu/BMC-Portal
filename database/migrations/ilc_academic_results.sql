-- =====================================================================
-- Migration: Create ilc_academic_results table
-- Stores Academic Result 1 (Special Children's Wing) per student.
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- Run once against bmc_portal database.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `ilc_academic_results` (
    `id`          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_id`  INT          NOT NULL
        COMMENT 'FK to students.id — which student this result belongs to',
    `term`        VARCHAR(100) NOT NULL DEFAULT 'Final Term'
        COMMENT 'Mid Term / Final Term / Annual Exam',
    `session`     VARCHAR(50)  NOT NULL DEFAULT ''
        COMMENT 'Academic session e.g. 2023-2024',
    `form_data`   TEXT         NOT NULL
        COMMENT 'Full JSON of all graded sections and attendance',
    `recorded_by` INT          NOT NULL
        COMMENT 'FK to users.id — ILC VP who entered the result',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_iar_student` (`student_id`),
    CONSTRAINT `fk_iar_student`
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_iar_recorder`
        FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
