-- =====================================================================
-- Migration: ILC Student Assessments Table
-- Per-student assessment records for the ILC portal.
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- Run once against bmc_portal database.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `ilc_student_assessments` (
    `id`             INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_id`     INT           NOT NULL,
    `title`          VARCHAR(100)  NOT NULL            COMMENT 'Assessment title / name',
    `type`           VARCHAR(50)   NOT NULL DEFAULT 'Quiz' COMMENT 'Quiz, Assignment, Midterm, Final Term, Test, etc.',
    `max_marks`      DECIMAL(6,2)  NOT NULL DEFAULT 100  COMMENT 'Total marks possible',
    `weight`         DECIMAL(5,2)  NOT NULL DEFAULT 0    COMMENT 'Weightage percentage 0-100',
    `marks_obtained` DECIMAL(6,2)  NULL                  COMMENT 'Student marks obtained',
    `date`           DATE          NULL,
    `remarks`        VARCHAR(255)  NULL,
    `created_by`     INT           NOT NULL,
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_isa_student` (`student_id`),
    CONSTRAINT `fk_isa_student`  FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_isa_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
