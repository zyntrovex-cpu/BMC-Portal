-- ================================================================
-- Hierarchy Migration — VP Main/Montessori, Wing Head, Medical Records
-- Run AFTER schema.sql + migrations.sql + ilc-migration.sql
-- ================================================================

USE bmc_portal;

-- ── 1. Extend users.role ENUM ────────────────────────────────────
ALTER TABLE users
  MODIFY COLUMN role ENUM(
    'student','teacher','admin','finance',
    'ilc_vp','student_affairs',
    'vp_main','wing_head'
  ) NOT NULL;

-- ── 2. Montessori flag for classes ───────────────────────────────
ALTER TABLE classes ADD COLUMN IF NOT EXISTS is_montessori TINYINT(1) NOT NULL DEFAULT 0;

-- ── 3. Student category (Civilian / CPO / Sailor) ─────────────────
ALTER TABLE students
  ADD COLUMN IF NOT EXISTS student_category ENUM('civilian','cpo','sailor') NULL;

-- ── 4. Add category + wing to admission_requests ─────────────────
ALTER TABLE admission_requests
  ADD COLUMN IF NOT EXISTS student_category ENUM('civilian','cpo','sailor') NULL AFTER requested_class,
  ADD COLUMN IF NOT EXISTS wing ENUM('main','montessori','ilc') NULL AFTER student_category;

-- ── 5. Medical records ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS medical_records (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  student_id  INT NOT NULL,
  record_type VARCHAR(100) NOT NULL,
  description TEXT,
  recorded_by INT NOT NULL,
  recorded_at DATE NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ── 6. Montessori classes ────────────────────────────────────────
INSERT IGNORE INTO classes (id, name, grade, section, is_montessori) VALUES
(20, 'Beginner', 0, 'A', 1),
(21, 'Advance',  0, 'B', 1),
(22, 'Prep',     0, 'C', 1),
(23, 'Class-1',  1, 'A', 1);

-- ── 7. Sample VP — Main & Montessori (password: student123) ──────
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
(303, 'VP001', 'Mr. Asad Khan',   'asad.vp@bmc.edu.pk',
 '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'vp_main', 'active');

-- ── 8. Sample Wing Head (password: student123) ───────────────────
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
(304, 'WH001', 'Ms. Rubina Akhtar', 'rubina.wh@bmc.edu.pk',
 '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'wing_head', 'active');
