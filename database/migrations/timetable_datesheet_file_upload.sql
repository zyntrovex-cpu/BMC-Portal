-- Migration: add file-upload columns to exam_date_sheets and create timetable_documents
-- Run this if the app auto-migrator (config/db.php Groups 4 & 7) has not yet been executed.

-- 1. Add term column to exam_date_sheets (if missing)
ALTER TABLE exam_date_sheets
    ADD COLUMN IF NOT EXISTS term VARCHAR(100) NOT NULL DEFAULT 'General' AFTER title;

-- 2. Add file-storage columns to exam_date_sheets (if missing)
ALTER TABLE exam_date_sheets
    ADD COLUMN IF NOT EXISTS original_filename VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS stored_filename   VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS file_type         VARCHAR(10)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS file_size         INT UNSIGNED DEFAULT NULL;

-- 3. Create timetable_documents table (if not exists)
CREATE TABLE IF NOT EXISTS timetable_documents (
    id                INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title             VARCHAR(200)     NOT NULL,
    wing              ENUM('main','montessori','ilc','all') NOT NULL DEFAULT 'all',
    academic_year     VARCHAR(20)      NOT NULL,
    notes             TEXT             DEFAULT NULL,
    original_filename VARCHAR(255)     DEFAULT NULL,
    stored_filename   VARCHAR(255)     DEFAULT NULL,
    file_type         VARCHAR(10)      DEFAULT NULL,
    file_size         INT UNSIGNED     DEFAULT NULL,
    uploaded_by       INT              NOT NULL,
    created_at        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tt_docs_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
