-- =====================================================================
-- Migration: Exam Date Sheet feature
-- Creates exam_date_sheets (headers) and exam_date_sheet_entries (rows).
-- Run once against bmc_portal database.
-- =====================================================================

CREATE TABLE IF NOT EXISTS exam_date_sheets (
  id                INT PRIMARY KEY AUTO_INCREMENT,
  title             VARCHAR(200)  NOT NULL               COMMENT 'e.g. Mid-Term Exams 2025-2026',
  term              VARCHAR(100)  NOT NULL DEFAULT 'General',
  wing              ENUM('main','montessori','ilc','all') NOT NULL DEFAULT 'all',
  academic_year     VARCHAR(20)   NOT NULL,
  status            ENUM('draft','published')            NOT NULL DEFAULT 'draft',
  notes             TEXT          DEFAULT NULL,
  original_filename VARCHAR(255)  DEFAULT NULL,
  stored_filename   VARCHAR(255)  DEFAULT NULL,
  file_type         VARCHAR(10)   DEFAULT NULL,
  file_size         INT UNSIGNED  DEFAULT NULL,
  created_by        INT           NOT NULL,
  created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_date_sheet_entries (
  id              INT PRIMARY KEY AUTO_INCREMENT,
  date_sheet_id   INT           NOT NULL,
  class_id        INT           DEFAULT NULL         COMMENT 'NULL = applies to all classes in this wing',
  subject         VARCHAR(150)  NOT NULL,
  exam_date       DATE          NOT NULL,
  start_time      TIME          NOT NULL,
  end_time        TIME          NOT NULL,
  venue           VARCHAR(100)  DEFAULT NULL,
  notes           VARCHAR(255)  DEFAULT NULL,
  sort_order      INT           NOT NULL DEFAULT 0,
  FOREIGN KEY (date_sheet_id) REFERENCES exam_date_sheets(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id)      REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;
