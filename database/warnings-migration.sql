-- ================================================================
-- Warnings Migration — run AFTER schema.sql + migrations.sql
-- Creates the student_warnings table.
-- ================================================================

USE bmc_portal;

CREATE TABLE IF NOT EXISTS student_warnings (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  given_by   INT NOT NULL,
  reason     TEXT NOT NULL,
  severity   ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (given_by)   REFERENCES users(id)
) ENGINE=InnoDB;
