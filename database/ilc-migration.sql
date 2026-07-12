-- ================================================================
-- ILC Module Migration — run AFTER schema.sql + migrations.sql
-- ================================================================

USE bmc_portal;

-- ── 1. Extend users.role ENUM ────────────────────────────────────
ALTER TABLE users
  MODIFY COLUMN role ENUM('student','teacher','admin','finance','ilc_vp','student_affairs') NOT NULL;

-- ── 2. Mark ILC classes ──────────────────────────────────────────
ALTER TABLE classes ADD COLUMN IF NOT EXISTS is_ilc TINYINT(1) NOT NULL DEFAULT 0;

-- ── 3. Mark ILC teachers ─────────────────────────────────────────
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS is_ilc TINYINT(1) NOT NULL DEFAULT 0;

-- ── 4. Disability classification ─────────────────────────────────
CREATE TABLE IF NOT EXISTS disability_categories (
  id   INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS disability_subtypes (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  category_id INT NOT NULL,
  name        VARCHAR(150) NOT NULL,
  FOREIGN KEY (category_id) REFERENCES disability_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_disabilities (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  student_id  INT NOT NULL,
  subtype_id  INT NOT NULL,
  notes       TEXT NULL,
  recorded_by INT NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_subtype (student_id, subtype_id),
  FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (subtype_id)  REFERENCES disability_subtypes(id),
  FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ── 5. Admission requests ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admission_requests (
  id               INT PRIMARY KEY AUTO_INCREMENT,
  student_name     VARCHAR(150) NOT NULL,
  parent_name      VARCHAR(150),
  parent_phone     VARCHAR(20),
  dob              DATE,
  requested_class  VARCHAR(50),
  disability_notes TEXT,
  status           ENUM('pending','reviewed','approved','rejected') DEFAULT 'pending',
  requested_by     INT NOT NULL,
  reviewed_by      INT NULL,
  review_notes     TEXT NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at      TIMESTAMP NULL,
  FOREIGN KEY (requested_by) REFERENCES users(id),
  FOREIGN KEY (reviewed_by)  REFERENCES users(id)
) ENGINE=InnoDB;

-- ── 6. Seed disability categories ───────────────────────────────
INSERT IGNORE INTO disability_categories (id, name) VALUES
(1, 'Learning Disability'),
(2, 'Blind & VI (Visual Impairment)'),
(3, 'HI (Hearing Impaired)'),
(4, 'Down Syndrome'),
(5, 'Autistic Disorders'),
(6, 'ADHD');

-- ── 7. Seed disability subtypes ──────────────────────────────────
INSERT IGNORE INTO disability_subtypes (id, category_id, name) VALUES
-- Learning Disability
( 1, 1, 'Dyslexia'),
( 2, 1, 'Dysgraphia'),
( 3, 1, 'Dyscalculia'),
( 4, 1, 'NVLD (Nonverbal Learning Disability)'),
( 5, 1, 'Auditory Processing Disorder'),
( 6, 1, 'Visual Processing Disorder'),
-- Blind & VI
( 7, 2, 'Low vision'),
( 8, 2, 'Partial sight'),
( 9, 2, 'Color blindness'),
(10, 2, 'Night blindness'),
-- HI
(11, 3, 'Conductive hearing loss'),
(12, 3, 'Mixed hearing loss'),
(13, 3, 'Bilateral hearing loss'),
(14, 3, 'Sensorineural hearing loss'),
-- Down Syndrome
(15, 4, 'Trisomy 21'),
(16, 4, 'Translocation Down Syndrome'),
(17, 4, 'Mosaic Down Syndrome'),
(18, 4, 'Partial Trisomy 21'),
-- Autistic Disorders
(19, 5, 'Asperger''s Syndrome'),
(20, 5, 'Level 1 ASD'),
(21, 5, 'Level 2 ASD'),
(22, 5, 'Level 3 ASD'),
(23, 5, 'Rett Syndrome'),
-- ADHD
(24, 6, 'Over-focused ADHD'),
(25, 6, 'Predominantly inattentive ADHD'),
(26, 6, 'Limbic ADHD');

-- ── 8. ILC sample classes ────────────────────────────────────────
INSERT IGNORE INTO classes (id, name, grade, section, is_ilc) VALUES
(10, 'ILC-A', 0, 'A', 1),
(11, 'ILC-B', 0, 'B', 1);

-- ── 9. Sample ILC VP user (password: student123 — change via reset link) ──
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
(301, 'ILC001', 'Dr. Amna Siddiqui', 'amna.ilc@bmc.edu.pk',
 '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'ilc_vp', 'active');

-- ── 10. Sample Student Affairs user (password: student123 — change via reset link) ──
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
(302, 'SA001', 'Mr. Tariq Aziz', 'tariq.sa@bmc.edu.pk',
 '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student_affairs', 'active');

-- ── 11. Sample ILC students (re-use existing STU011-STU013 users, move to ILC-A) ──
-- NOTE: Only run this if you want to reassign some existing students to ILC classes for testing.
-- UPDATE students SET class_id = 10 WHERE id IN (11, 12, 13);
