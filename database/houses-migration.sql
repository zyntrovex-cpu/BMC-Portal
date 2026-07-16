-- ================================================================
-- Houses Migration — run AFTER schema.sql + migrations.sql
-- Creates the houses table and adds house_id to students.
-- ================================================================

USE bmc_portal;

CREATE TABLE IF NOT EXISTS houses (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(100) NOT NULL,
  color      VARCHAR(20)  NOT NULL DEFAULT '#3b82f6',
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE students
  ADD COLUMN IF NOT EXISTS house_id INT NULL,
  ADD CONSTRAINT fk_student_house
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE SET NULL;

-- Starter houses (optional — delete or rename as needed)
INSERT IGNORE INTO houses (id, name, color) VALUES
(1, 'Allama Iqbal', '#3b82f6'),
(2, 'Quaid-e-Azam', '#22c55e'),
(3, 'Fatima Jinnah', '#f97316'),
(4, 'Sir Syed',      '#a855f7');
