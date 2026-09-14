-- =====================================================================
-- Migration: ILC Portal Features — Therapy, Assessments, Fee Status
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- Run once against bmc_portal database.
-- =====================================================================

-- 1. Behaviour Therapy Monthly Reports
CREATE TABLE IF NOT EXISTS `behaviour_therapy_reports` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_id`       INT UNSIGNED     NOT NULL,
    `month`            DATE             NOT NULL COMMENT 'Stored as first of month (YYYY-MM-01)',
    `therapist_notes`  TEXT             NULL,
    `progress_summary` TEXT             NULL,
    `goals_next_month` TEXT             NULL,
    `recorded_by`      INT UNSIGNED     NOT NULL,
    `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_btr_student_month` (`student_id`, `month`),
    KEY `idx_btr_student` (`student_id`),
    CONSTRAINT `fk_btr_student`  FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_btr_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Speech Therapy Monthly Reports
CREATE TABLE IF NOT EXISTS `speech_therapy_reports` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_id`       INT UNSIGNED     NOT NULL,
    `month`            DATE             NOT NULL COMMENT 'Stored as first of month (YYYY-MM-01)',
    `therapist_notes`  TEXT             NULL,
    `progress_summary` TEXT             NULL,
    `goals_next_month` TEXT             NULL,
    `recorded_by`      INT UNSIGNED     NOT NULL,
    `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_str_student_month` (`student_id`, `month`),
    KEY `idx_str_student` (`student_id`),
    CONSTRAINT `fk_str_student`  FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_str_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ILC Assessments
CREATE TABLE IF NOT EXISTS `ilc_assessments` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_id`       INT UNSIGNED     NOT NULL,
    `assessment_date`  DATE             NOT NULL,
    `assessment_type`  VARCHAR(100)     NOT NULL DEFAULT 'Initial Intake',
    `strengths`        TEXT             NULL,
    `challenges`       TEXT             NULL,
    `recommendations`  TEXT             NULL,
    `conducted_by`     INT UNSIGNED     NOT NULL,
    `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ia_student` (`student_id`),
    CONSTRAINT `fk_ia_student`    FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ia_conducted`  FOREIGN KEY (`conducted_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ILC Fee Payments (per-month tracking)
CREATE TABLE IF NOT EXISTS `ilc_fee_payments` (
    `id`          INT UNSIGNED                  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `student_id`  INT UNSIGNED                  NOT NULL,
    `month`       DATE                          NOT NULL COMMENT 'First of month (YYYY-MM-01)',
    `status`      ENUM('paid','unpaid')         NOT NULL DEFAULT 'unpaid',
    `amount`      DECIMAL(10,2)                 NULL,
    `paid_on`     DATE                          NULL,
    `recorded_by` INT UNSIGNED                  NOT NULL,
    `created_at`  TIMESTAMP                     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_ifp_student_month` (`student_id`, `month`),
    KEY `idx_ifp_student` (`student_id`),
    CONSTRAINT `fk_ifp_student`  FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ifp_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- No changes to disability_categories / disability_subtypes schema.
-- Category/subtype CRUD is handled via new UI in disabilities.php.
-- =====================================================================
