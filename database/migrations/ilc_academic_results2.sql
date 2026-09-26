-- =====================================================================
-- Migration: Create ilc_academic_results2 table
-- Stores Academic Result 2 (First/Mid-Term Progress Report) per student.
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- Run once against bmc_portal database.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `ilc_academic_results2` (
    `id`          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_id`  INT          NOT NULL
        COMMENT 'FK to students.id',
    `term`        VARCHAR(100) NOT NULL DEFAULT 'First Term'
        COMMENT 'First Term / Mid Term / Final Term / Annual Exam',
    `session`     VARCHAR(50)  NOT NULL DEFAULT ''
        COMMENT 'Academic session e.g. 2024-2025',
    `form_data`   TEXT         NOT NULL
        COMMENT 'Full JSON: basic info, subjects, skills, assessment levels, remarks',
    `recorded_by` INT          NOT NULL
        COMMENT 'FK to users.id — ILC VP who recorded the result',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_iar2_student` (`student_id`),
    CONSTRAINT `fk_iar2_student`
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_iar2_recorder`
        FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
