-- =====================================================================
-- Migration: Individual Promotion, Graduation/Alumni support
-- Run once against bmc_portal database.
-- =====================================================================

-- 1. Add graduation columns to students table
ALTER TABLE students
  ADD COLUMN IF NOT EXISTS graduated_at    TIMESTAMP   NULL DEFAULT NULL
    COMMENT 'Set when student is marked graduated; NULL = still active',
  ADD COLUMN IF NOT EXISTS graduation_year VARCHAR(20) NULL DEFAULT NULL
    COMMENT 'e.g. 2025 — the session/batch year';

-- 2. Index for quick alumni queries
ALTER TABLE students
  ADD INDEX IF NOT EXISTS idx_students_graduated_at (graduated_at);

-- 3. Promotion history log
CREATE TABLE IF NOT EXISTS student_promotion_history (
  id               INT          PRIMARY KEY AUTO_INCREMENT,
  student_id       INT          NOT NULL,
  student_name     VARCHAR(100) NOT NULL,
  roll_no          VARCHAR(20)  NOT NULL,
  from_class_id    INT          DEFAULT NULL,
  from_class_name  VARCHAR(50)  DEFAULT NULL,
  to_class_id      INT          DEFAULT NULL,
  to_class_name    VARCHAR(50)  DEFAULT NULL,
  action           ENUM('promoted','demoted','graduated','ungraduated') NOT NULL,
  promoted_by      INT          NOT NULL,
  promoted_by_name VARCHAR(100) NOT NULL,
  notes            VARCHAR(255) DEFAULT NULL,
  created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (promoted_by) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;
