-- ============================================================
-- BMC Portal — Database Schema
-- Bahria Model College · MySQL / MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS bmc_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bmc_portal;

-- ─────────────────────────────────────────
-- 1. USERS (unified auth table)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id           INT PRIMARY KEY AUTO_INCREMENT,
  user_id      VARCHAR(20) UNIQUE NOT NULL COMMENT 'Roll No / T001 / ADM001 / FIN001',
  name         VARCHAR(100) NOT NULL,
  email        VARCHAR(100),
  password     VARCHAR(255) NOT NULL,
  role         ENUM('student','teacher','admin','finance') NOT NULL,
  status       ENUM('active','inactive','pending') DEFAULT 'active',
  last_login   TIMESTAMP NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 2. CLASSES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS classes (
  id       INT PRIMARY KEY AUTO_INCREMENT,
  name     VARCHAR(20) NOT NULL UNIQUE,
  grade    INT NOT NULL,
  section  VARCHAR(5) NOT NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 3. SUBJECTS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS subjects (
  id    INT PRIMARY KEY AUTO_INCREMENT,
  name  VARCHAR(100) NOT NULL,
  code  VARCHAR(20)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 4. STUDENTS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS students (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  user_id       INT NOT NULL UNIQUE,
  roll_no       VARCHAR(20) UNIQUE NOT NULL,
  class_id      INT,
  father_name   VARCHAR(100),
  dob           DATE,
  gender        ENUM('male','female','other'),
  cnic          VARCHAR(20),
  phone         VARCHAR(20),
  address       TEXT,
  parent_phone  VARCHAR(20),
  parent_email  VARCHAR(100),
  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 5. TEACHERS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS teachers (
  id           INT PRIMARY KEY AUTO_INCREMENT,
  user_id      INT NOT NULL UNIQUE,
  emp_id       VARCHAR(20) UNIQUE NOT NULL,
  subject_id   INT,
  designation  VARCHAR(100) DEFAULT 'Subject Teacher',
  phone        VARCHAR(20),
  join_date    DATE,
  FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 6. CLASS ↔ SUBJECT ↔ TEACHER
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS class_subjects (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  class_id    INT NOT NULL,
  subject_id  INT NOT NULL,
  teacher_id  INT,
  UNIQUE KEY uq_cs (class_id, subject_id),
  FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 7. ASSESSMENTS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS assessments (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  type        ENUM('quiz','assignment','class_test','mid_term','final_term','practical') NOT NULL,
  max_marks   DECIMAL(6,2) NOT NULL,
  weight      DECIMAL(5,2) DEFAULT 0,
  date        DATE,
  class_id    INT,
  subject_id  INT,
  teacher_id  INT,
  locked      TINYINT(1) DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE SET NULL,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 8. MARKS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS marks (
  id             INT PRIMARY KEY AUTO_INCREMENT,
  student_id     INT NOT NULL,
  assessment_id  INT NOT NULL,
  marks_obtained DECIMAL(6,2),
  entered_by     INT,
  entered_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mark (student_id, assessment_id),
  FOREIGN KEY (student_id)    REFERENCES students(id)    ON DELETE CASCADE,
  FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
  FOREIGN KEY (entered_by)    REFERENCES teachers(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 9. ATTENDANCE
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS attendance (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  student_id  INT NOT NULL,
  class_id    INT,
  subject_id  INT,
  date        DATE NOT NULL,
  status      ENUM('P','A','L') NOT NULL,
  remarks     VARCHAR(255),
  teacher_id  INT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_att (student_id, subject_id, date),
  FOREIGN KEY (student_id) REFERENCES students(id)  ON DELETE CASCADE,
  FOREIGN KEY (class_id)   REFERENCES classes(id)   ON DELETE SET NULL,
  FOREIGN KEY (subject_id) REFERENCES subjects(id)  ON DELETE SET NULL,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 10. NOTICES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notices (
  id           INT PRIMARY KEY AUTO_INCREMENT,
  title        VARCHAR(255) NOT NULL,
  body         TEXT NOT NULL,
  category     VARCHAR(50) NOT NULL DEFAULT 'general',
  priority     ENUM('normal','important','urgent') DEFAULT 'normal',
  audience     SET('students','teachers','finance','admin') NOT NULL DEFAULT 'students,teachers,finance,admin',
  pinned       TINYINT(1) DEFAULT 0,
  author_id    INT,
  expiry_date  DATE,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 11. FEES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fees (
  id           INT PRIMARY KEY AUTO_INCREMENT,
  student_id   INT NOT NULL,
  month        INT NOT NULL,
  year         INT NOT NULL,
  amount       DECIMAL(10,2) NOT NULL DEFAULT 12000,
  paid         TINYINT(1) DEFAULT 0,
  payment_date DATE,
  payment_mode ENUM('cash','bank','online','cheque'),
  receipt_no   VARCHAR(50),
  remarks      VARCHAR(255),
  recorded_by  INT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fee (student_id, month, year),
  FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 12. TIMETABLE
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS timetable (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  class_id    INT NOT NULL,
  day         ENUM('monday','tuesday','wednesday','thursday','friday') NOT NULL,
  period      INT NOT NULL CHECK (period BETWEEN 1 AND 6),
  subject_id  INT,
  teacher_id  INT,
  room        VARCHAR(50),
  UNIQUE KEY uq_tt (class_id, day, period),
  FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 13. PROFILE CHANGE REQUESTS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS profile_change_requests (
  id           INT PRIMARY KEY AUTO_INCREMENT,
  student_id   INT NOT NULL,
  field        VARCHAR(50) NOT NULL,
  old_value    VARCHAR(255),
  new_value    VARCHAR(255) NOT NULL,
  status       ENUM('pending','approved','rejected') DEFAULT 'pending',
  admin_note   VARCHAR(255),
  reviewed_by  INT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at  TIMESTAMP NULL,
  FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 14. ACTIVITY LOG
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  user_id    INT,
  action     VARCHAR(100) NOT NULL,
  details    TEXT,
  ip_address VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────
-- 15. SYSTEM SETTINGS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  key_name   VARCHAR(100) UNIQUE NOT NULL,
  value      TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Settings
INSERT IGNORE INTO settings (key_name, value) VALUES
  ('school_name',    'Bahria Model College'),
  ('session_year',   '2025-26'),
  ('current_term',   'Term 2'),
  ('fee_per_month',  '12000'),
  ('min_attendance', '75');

-- Classes
INSERT IGNORE INTO classes (name, grade, section) VALUES
  ('8-A',8,'A'),('8-B',8,'B'),('8-C',8,'C'),
  ('9-A',9,'A'),('9-B',9,'B'),('9-C',9,'C'),
  ('10-A',10,'A'),('10-B',10,'B'),('10-C',10,'C'),
  ('11-A',11,'A'),('11-B',11,'B'),
  ('12-A',12,'A'),('12-B',12,'B');

-- Subjects
INSERT IGNORE INTO subjects (name, code) VALUES
  ('Physics','PHY'),('Mathematics','MAT'),('English','ENG'),
  ('Chemistry','CHE'),('Biology','BIO'),('Computer Science','CS'),
  ('Urdu','URD'),('Islamiat','ISL'),('Pakistan Studies','PKS');

-- Users (passwords: student123, teacher123, admin123, finance123 — bcrypt)
INSERT IGNORE INTO users (user_id, name, email, password, role) VALUES
  ('101',    'Ahmed Ali',         'ahmed@bmc.edu.pk',   '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('102',    'Fatima Noor',       'fatima@bmc.edu.pk',  '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('103',    'Hassan Raza',       'hassan@bmc.edu.pk',  '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('104',    'Iqra Shah',         'iqra@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('105',    'Bilal Tariq',       'bilal@bmc.edu.pk',   '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('106',    'Zara Iqbal',        'zara@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('107',    'Saad Qureshi',      'saad@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('108',    'Amna Zahid',        'amna@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('109',    'Usman Ghani',       'usman@bmc.edu.pk',   '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('110',    'Rabia Malik',       'rabia@bmc.edu.pk',   '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('111',    'Faisal Khan',       'faisal@bmc.edu.pk',  '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('112',    'Nida Ansari',       'nida@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('113',    'Asad Mehmood',      'asad@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('114',    'Sana Butt',         'sana@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('115',    'Kamran Elahi',      'kamran@bmc.edu.pk',  '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student'),
  ('T001',   'Dr. Sarah Khan',    'sarah@bmc.edu.pk',   '$2y$12$wL3VFI9UOKWAnCPNKd6yZe.bJhiNbG4gFP2Pnmur1aqWJy.H9Xt/y', 'teacher'),
  ('T002',   'Mr. Hasan Ali',     'hasan@bmc.edu.pk',   '$2y$12$wL3VFI9UOKWAnCPNKd6yZe.bJhiNbG4gFP2Pnmur1aqWJy.H9Xt/y', 'teacher'),
  ('T003',   'Ms. Nadia Raza',    'nadia@bmc.edu.pk',   '$2y$12$wL3VFI9UOKWAnCPNKd6yZe.bJhiNbG4gFP2Pnmur1aqWJy.H9Xt/y', 'teacher'),
  ('T004',   'Dr. Amina Siddiqui','amina@bmc.edu.pk',   '$2y$12$wL3VFI9UOKWAnCPNKd6yZe.bJhiNbG4gFP2Pnmur1aqWJy.H9Xt/y', 'teacher'),
  ('T005',   'Mr. Imran Hassan',  'imran@bmc.edu.pk',   '$2y$12$wL3VFI9UOKWAnCPNKd6yZe.bJhiNbG4gFP2Pnmur1aqWJy.H9Xt/y', 'teacher'),
  ('T006',   'Mr. Farhan Ahmed',  'farhan@bmc.edu.pk',  '$2y$12$wL3VFI9UOKWAnCPNKd6yZe.bJhiNbG4gFP2Pnmur1aqWJy.H9Xt/y', 'teacher'),
  ('ADM001', 'Mr. Tariq Mehmood', 'admin@bmc.edu.pk',   '$2y$12$qDfXpImX0o5JInILNE1j1u6B/xVuGE.n8gm/G6/c/jHnvxJlFbFmm', 'admin'),
  ('FIN001', 'Ms. Ayesha Rizvi',  'finance@bmc.edu.pk', '$2y$12$cIJe0/OLqblLe9DLbpqc8.BI3LFl5yxCgVBTRl2.fVxJ3nxrqEMHi', 'finance');

-- Students (link to users, class 12-A = id 12)
INSERT IGNORE INTO students (user_id, roll_no, class_id, father_name, dob, gender, phone, address, parent_phone, parent_email) VALUES
  ((SELECT id FROM users WHERE user_id='101'), '101', (SELECT id FROM classes WHERE name='12-A'), 'Muhammad Ali',    '2007-03-15', 'male',   '0300-1234567', 'House 12, Street 5, Bahria Town', '0321-9876543', 'mali@gmail.com'),
  ((SELECT id FROM users WHERE user_id='102'), '102', (SELECT id FROM classes WHERE name='12-A'), 'Noor Ahmed',      '2007-07-22', 'female', '0301-2345678', 'House 34, Block C, Bahria Town',  '0322-8765432', 'noor@gmail.com'),
  ((SELECT id FROM users WHERE user_id='103'), '103', (SELECT id FROM classes WHERE name='12-A'), 'Raza Khan',       '2007-01-10', 'male',   '0302-3456789', 'Flat 5, Tower A, Bahria Town',    '0323-7654321', 'raza@gmail.com'),
  ((SELECT id FROM users WHERE user_id='104'), '104', (SELECT id FROM classes WHERE name='12-A'), 'Shahid Hussain',  '2007-11-05', 'female', '0303-4567890', 'House 78, Street 3, Bahria Town', '0324-6543210', 'shah@gmail.com'),
  ((SELECT id FROM users WHERE user_id='105'), '105', (SELECT id FROM classes WHERE name='12-A'), 'Tariq Mehmood',   '2007-08-18', 'male',   '0304-5678901', 'House 22, Block D, Bahria Town',  '0325-5432109', 'tariq@gmail.com'),
  ((SELECT id FROM users WHERE user_id='106'), '106', (SELECT id FROM classes WHERE name='12-A'), 'Iqbal Hussain',   '2007-06-25', 'female', '0305-6789012', 'House 56, Street 9, Bahria Town', '0326-4321098', 'iqb@gmail.com'),
  ((SELECT id FROM users WHERE user_id='107'), '107', (SELECT id FROM classes WHERE name='12-A'), 'Qureshi Sahib',   '2007-04-12', 'male',   '0306-7890123', 'House 88, Block E, Bahria Town',  '0327-3210987', 'qur@gmail.com'),
  ((SELECT id FROM users WHERE user_id='108'), '108', (SELECT id FROM classes WHERE name='12-A'), 'Zahid Ali',       '2007-09-30', 'female', '0307-8901234', 'House 10, Street 7, Bahria Town', '0328-2109876', 'zah@gmail.com'),
  ((SELECT id FROM users WHERE user_id='109'), '109', (SELECT id FROM classes WHERE name='12-A'), 'Ghani Sahib',     '2007-12-14', 'male',   '0308-9012345', 'House 43, Block F, Bahria Town',  '0329-1098765', 'gha@gmail.com'),
  ((SELECT id FROM users WHERE user_id='110'), '110', (SELECT id FROM classes WHERE name='12-A'), 'Malik Sahib',     '2007-02-08', 'female', '0309-0123456', 'House 67, Street 2, Bahria Town', '0330-0987654', 'mal@gmail.com'),
  ((SELECT id FROM users WHERE user_id='111'), '111', (SELECT id FROM classes WHERE name='12-A'), 'Khan Bahadur',    '2007-05-20', 'male',   '0310-1234560', 'House 91, Block G, Bahria Town',  '0331-9876540', 'kha@gmail.com'),
  ((SELECT id FROM users WHERE user_id='112'), '112', (SELECT id FROM classes WHERE name='12-A'), 'Ansari Sahib',    '2007-10-03', 'female', '0311-2345671', 'House 25, Street 6, Bahria Town', '0332-8765431', 'ans@gmail.com'),
  ((SELECT id FROM users WHERE user_id='113'), '113', (SELECT id FROM classes WHERE name='12-A'), 'Mehmood Sahib',   '2007-07-17', 'male',   '0312-3456782', 'House 49, Block H, Bahria Town',  '0333-7654322', 'meh@gmail.com'),
  ((SELECT id FROM users WHERE user_id='114'), '114', (SELECT id FROM classes WHERE name='12-A'), 'Butt Sahib',      '2007-03-28', 'female', '0313-4567893', 'House 73, Street 1, Bahria Town', '0334-6543213', 'but@gmail.com'),
  ((SELECT id FROM users WHERE user_id='115'), '115', (SELECT id FROM classes WHERE name='12-A'), 'Elahi Sahib',     '2007-09-11', 'male',   '0314-5678904', 'House 97, Block I, Bahria Town',  '0335-5432104', 'ela@gmail.com');

-- Teachers
INSERT IGNORE INTO teachers (user_id, emp_id, subject_id, designation, join_date) VALUES
  ((SELECT id FROM users WHERE user_id='T001'), 'T001', (SELECT id FROM subjects WHERE code='PHY'), 'Senior Teacher', '2022-01-15'),
  ((SELECT id FROM users WHERE user_id='T002'), 'T002', (SELECT id FROM subjects WHERE code='MAT'), 'Subject Teacher', '2021-03-10'),
  ((SELECT id FROM users WHERE user_id='T003'), 'T003', (SELECT id FROM subjects WHERE code='ENG'), 'Subject Teacher', '2020-08-20'),
  ((SELECT id FROM users WHERE user_id='T004'), 'T004', (SELECT id FROM subjects WHERE code='CHE'), 'Senior Teacher', '2019-09-05'),
  ((SELECT id FROM users WHERE user_id='T005'), 'T005', (SELECT id FROM subjects WHERE code='BIO'), 'Subject Teacher', '2021-07-12'),
  ((SELECT id FROM users WHERE user_id='T006'), 'T006', (SELECT id FROM subjects WHERE code='CS'),  'Subject Teacher', '2023-02-01');

-- Class 12-A subjects and teachers
INSERT IGNORE INTO class_subjects (class_id, subject_id, teacher_id) VALUES
  ((SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='MAT'), (SELECT id FROM teachers WHERE emp_id='T002')),
  ((SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='ENG'), (SELECT id FROM teachers WHERE emp_id='T003')),
  ((SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='CHE'), (SELECT id FROM teachers WHERE emp_id='T004')),
  ((SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='BIO'), (SELECT id FROM teachers WHERE emp_id='T005')),
  ((SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='CS'),  (SELECT id FROM teachers WHERE emp_id='T006'));

-- Assessments for Physics, Class 12-A
INSERT IGNORE INTO assessments (name, type, max_marks, weight, date, class_id, subject_id, teacher_id, locked) VALUES
  ('Quiz 1',      'quiz',       10,  5, '2026-02-05', (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), (SELECT id FROM teachers WHERE emp_id='T001'), 1),
  ('Quiz 2',      'quiz',       10,  5, '2026-02-20', (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), (SELECT id FROM teachers WHERE emp_id='T001'), 1),
  ('Assignment 1','assignment', 20, 10, '2026-02-28', (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), (SELECT id FROM teachers WHERE emp_id='T001'), 1),
  ('Mid Term',    'mid_term',   40, 30, '2026-03-15', (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), (SELECT id FROM teachers WHERE emp_id='T001'), 1),
  ('Practical',   'practical',  20, 10, '2026-04-10', (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), (SELECT id FROM teachers WHERE emp_id='T001'), 1),
  ('Final Term',  'final_term',100, 40, '2026-06-10', (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), (SELECT id FROM teachers WHERE emp_id='T001'), 0);

-- Marks for Quiz 1 (all 15 students)
INSERT IGNORE INTO marks (student_id, assessment_id, marks_obtained, entered_by) VALUES
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='101'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 9,  (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='102'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 8,  (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='103'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 6,  (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='104'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 10, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='105'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 5,  (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='106'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 7,  (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='107'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 6,  (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='108'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 8,  (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='109'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 4,  (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='110'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 7,  (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='111'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 9,  (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='112'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 6,  (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='113'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 7,  (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='114'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 9,  (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='115'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Quiz 1'), 5,  (SELECT id FROM teachers WHERE emp_id='T001'));

-- Marks for Mid Term (Physics)
INSERT IGNORE INTO marks (student_id, assessment_id, marks_obtained, entered_by) VALUES
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='101'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 34, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='102'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 36, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='103'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 28, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='104'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 38, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='105'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 22, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='106'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 31, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='107'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 25, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='108'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 33, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='109'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 19, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='110'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 29, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='111'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 35, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='112'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 27, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='113'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 32, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='114'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 37, (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='115'), (SELECT a.id FROM assessments a JOIN subjects sb ON a.subject_id=sb.id WHERE sb.code='PHY' AND a.name='Mid Term'), 24, (SELECT id FROM teachers WHERE emp_id='T001'));

-- Attendance for Class 12-A, Physics (last 5 working days)
INSERT IGNORE INTO attendance (student_id, class_id, subject_id, date, status, teacher_id) VALUES
  -- 22 May (Thu)
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='101'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'P', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='102'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'P', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='103'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'A', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='104'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'P', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='105'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'P', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='106'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'P', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='107'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'A', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='108'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'P', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='109'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'L', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='110'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'P', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='111'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'P', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='112'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'P', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='113'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'P', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='114'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'P', (SELECT id FROM teachers WHERE emp_id='T001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='115'), (SELECT id FROM classes WHERE name='12-A'), (SELECT id FROM subjects WHERE code='PHY'), '2026-05-22', 'P', (SELECT id FROM teachers WHERE emp_id='T001'));

-- Fee records for May 2026 (partial — some paid, some not)
INSERT IGNORE INTO fees (student_id, month, year, amount, paid, payment_date, payment_mode, recorded_by) VALUES
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='101'), 5, 2026, 12000, 1, '2026-05-20', 'cash',   (SELECT id FROM users WHERE user_id='FIN001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='102'), 5, 2026, 12000, 1, '2026-05-19', 'bank',   (SELECT id FROM users WHERE user_id='FIN001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='103'), 5, 2026, 12000, 0, NULL, NULL, NULL),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='104'), 5, 2026, 12000, 1, '2026-05-17', 'online', (SELECT id FROM users WHERE user_id='FIN001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='105'), 5, 2026, 12000, 0, NULL, NULL, NULL),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='106'), 5, 2026, 12000, 1, '2026-05-15', 'cash',   (SELECT id FROM users WHERE user_id='FIN001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='107'), 5, 2026, 12000, 0, NULL, NULL, NULL),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='108'), 5, 2026, 12000, 1, '2026-05-12', 'bank',   (SELECT id FROM users WHERE user_id='FIN001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='109'), 5, 2026, 12000, 0, NULL, NULL, NULL),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='110'), 5, 2026, 12000, 1, '2026-05-10', 'cash',   (SELECT id FROM users WHERE user_id='FIN001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='111'), 5, 2026, 12000, 0, NULL, NULL, NULL),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='112'), 5, 2026, 12000, 1, '2026-05-08', 'online', (SELECT id FROM users WHERE user_id='FIN001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='113'), 5, 2026, 12000, 0, NULL, NULL, NULL),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='114'), 5, 2026, 12000, 1, '2026-05-05', 'cash',   (SELECT id FROM users WHERE user_id='FIN001')),
  ((SELECT s.id FROM students s JOIN users u ON s.user_id=u.id WHERE u.user_id='115'), 5, 2026, 12000, 0, NULL, NULL, NULL);

-- Notices
INSERT IGNORE INTO notices (title, body, category, priority, audience, pinned, author_id, expiry_date) VALUES
  ('Final Examination Schedule — June 2026', 'Term 2 Finals commence 10 June 2026. All students must report 30 minutes early. Roll number slips distributed by class teachers.', 'exam', 'urgent', 'students,teachers,finance,admin', 1, (SELECT id FROM users WHERE user_id='ADM001'), '2026-06-10'),
  ('Parent-Teacher Meeting — 27 May 2026', 'PTM scheduled Wednesday 27 May, 09:00 AM–01:00 PM in Main Hall. Parents requested to meet class teachers.', 'general', 'normal', 'students,teachers,finance,admin', 0, (SELECT id FROM users WHERE user_id='ADM001'), '2026-05-27'),
  ('Eid-ul-Adha Holidays — 30 May to 3 June 2026', 'College closed 30 May–3 June 2026 for Eid-ul-Adha. Resumes 4 June 2026.', 'holiday', 'normal', 'students,teachers,finance,admin', 1, (SELECT id FROM users WHERE user_id='ADM001'), '2026-06-04'),
  ('Annual Sports Day — 7 June 2026', 'Register with House In-Charge by 28 May 2026. Events: Athletics, Cricket, Football, Table Tennis, Badminton.', 'sports', 'normal', 'students', 0, (SELECT id FROM users WHERE user_id='ADM001'), '2026-06-07'),
  ('Staff Meeting — 26 May 2026', 'All teaching staff required to attend monthly staff meeting on Monday 26 May at 02:00 PM in Conference Room.', 'staff', 'important', 'teachers,admin', 1, (SELECT id FROM users WHERE user_id='ADM001'), '2026-05-26'),
  ('Mid-Term Result Submission Deadline', 'All subject teachers must submit Mid-Term marks on the portal by 25 May 2026.', 'academic', 'urgent', 'teachers,admin', 0, (SELECT id FROM users WHERE user_id='ADM001'), '2026-05-25'),
  ('Monthly Fee Collection Report — April 2026', 'April 2026 fee collection completed with 89% collection rate. Finance team to finalize reconciliation by 25 May 2026.', 'finance', 'important', 'finance,admin', 1, (SELECT id FROM users WHERE user_id='ADM001'), '2026-05-25'),
  ('System Maintenance — 25 May 2026', 'Portal maintenance from 11:00 PM to 02:00 AM on 25–26 May. System unavailable during this window.', 'system', 'important', 'admin', 1, (SELECT id FROM users WHERE user_id='ADM001'), '2026-05-26');

-- Timetable for Class 12-A
INSERT IGNORE INTO timetable (class_id, day, period, subject_id, teacher_id, room) VALUES
  ((SELECT id FROM classes WHERE name='12-A'), 'monday',    1, (SELECT id FROM subjects WHERE code='PHY'), (SELECT id FROM teachers WHERE emp_id='T001'), 'Lab-A'),
  ((SELECT id FROM classes WHERE name='12-A'), 'monday',    2, (SELECT id FROM subjects WHERE code='ENG'), (SELECT id FROM teachers WHERE emp_id='T003'), 'Room 201'),
  ((SELECT id FROM classes WHERE name='12-A'), 'monday',    3, (SELECT id FROM subjects WHERE code='CHE'), (SELECT id FROM teachers WHERE emp_id='T004'), 'Lab-B'),
  ((SELECT id FROM classes WHERE name='12-A'), 'monday',    4, (SELECT id FROM subjects WHERE code='MAT'), (SELECT id FROM teachers WHERE emp_id='T002'), 'Room 305'),
  ((SELECT id FROM classes WHERE name='12-A'), 'monday',    5, (SELECT id FROM subjects WHERE code='BIO'), (SELECT id FROM teachers WHERE emp_id='T005'), 'Room 212'),
  ((SELECT id FROM classes WHERE name='12-A'), 'monday',    6, (SELECT id FROM subjects WHERE code='CS'),  (SELECT id FROM teachers WHERE emp_id='T006'), 'Lab-C'),
  ((SELECT id FROM classes WHERE name='12-A'), 'tuesday',   1, (SELECT id FROM subjects WHERE code='MAT'), (SELECT id FROM teachers WHERE emp_id='T002'), 'Room 305'),
  ((SELECT id FROM classes WHERE name='12-A'), 'tuesday',   2, (SELECT id FROM subjects WHERE code='BIO'), (SELECT id FROM teachers WHERE emp_id='T005'), 'Room 212'),
  ((SELECT id FROM classes WHERE name='12-A'), 'tuesday',   3, (SELECT id FROM subjects WHERE code='CS'),  (SELECT id FROM teachers WHERE emp_id='T006'), 'Lab-C'),
  ((SELECT id FROM classes WHERE name='12-A'), 'tuesday',   4, (SELECT id FROM subjects WHERE code='PHY'), (SELECT id FROM teachers WHERE emp_id='T001'), 'Lab-A'),
  ((SELECT id FROM classes WHERE name='12-A'), 'tuesday',   5, (SELECT id FROM subjects WHERE code='ENG'), (SELECT id FROM teachers WHERE emp_id='T003'), 'Room 201'),
  ((SELECT id FROM classes WHERE name='12-A'), 'tuesday',   6, (SELECT id FROM subjects WHERE code='CHE'), (SELECT id FROM teachers WHERE emp_id='T004'), 'Lab-B'),
  ((SELECT id FROM classes WHERE name='12-A'), 'wednesday', 1, (SELECT id FROM subjects WHERE code='ENG'), (SELECT id FROM teachers WHERE emp_id='T003'), 'Room 201'),
  ((SELECT id FROM classes WHERE name='12-A'), 'wednesday', 2, (SELECT id FROM subjects WHERE code='PHY'), (SELECT id FROM teachers WHERE emp_id='T001'), 'Lab-A'),
  ((SELECT id FROM classes WHERE name='12-A'), 'wednesday', 3, (SELECT id FROM subjects WHERE code='CHE'), (SELECT id FROM teachers WHERE emp_id='T004'), 'Lab-B'),
  ((SELECT id FROM classes WHERE name='12-A'), 'wednesday', 4, (SELECT id FROM subjects WHERE code='MAT'), (SELECT id FROM teachers WHERE emp_id='T002'), 'Room 305'),
  ((SELECT id FROM classes WHERE name='12-A'), 'wednesday', 5, (SELECT id FROM subjects WHERE code='BIO'), (SELECT id FROM teachers WHERE emp_id='T005'), 'Room 212'),
  ((SELECT id FROM classes WHERE name='12-A'), 'wednesday', 6, (SELECT id FROM subjects WHERE code='CS'),  (SELECT id FROM teachers WHERE emp_id='T006'), 'Lab-C'),
  ((SELECT id FROM classes WHERE name='12-A'), 'thursday',  1, (SELECT id FROM subjects WHERE code='CHE'), (SELECT id FROM teachers WHERE emp_id='T004'), 'Lab-B'),
  ((SELECT id FROM classes WHERE name='12-A'), 'thursday',  2, (SELECT id FROM subjects WHERE code='CS'),  (SELECT id FROM teachers WHERE emp_id='T006'), 'Lab-C'),
  ((SELECT id FROM classes WHERE name='12-A'), 'thursday',  3, (SELECT id FROM subjects WHERE code='MAT'), (SELECT id FROM teachers WHERE emp_id='T002'), 'Room 305'),
  ((SELECT id FROM classes WHERE name='12-A'), 'thursday',  4, (SELECT id FROM subjects WHERE code='BIO'), (SELECT id FROM teachers WHERE emp_id='T005'), 'Room 212'),
  ((SELECT id FROM classes WHERE name='12-A'), 'thursday',  5, (SELECT id FROM subjects WHERE code='PHY'), (SELECT id FROM teachers WHERE emp_id='T001'), 'Lab-A'),
  ((SELECT id FROM classes WHERE name='12-A'), 'thursday',  6, (SELECT id FROM subjects WHERE code='ENG'), (SELECT id FROM teachers WHERE emp_id='T003'), 'Room 201'),
  ((SELECT id FROM classes WHERE name='12-A'), 'friday',    1, (SELECT id FROM subjects WHERE code='BIO'), (SELECT id FROM teachers WHERE emp_id='T005'), 'Room 212'),
  ((SELECT id FROM classes WHERE name='12-A'), 'friday',    2, (SELECT id FROM subjects WHERE code='MAT'), (SELECT id FROM teachers WHERE emp_id='T002'), 'Room 305'),
  ((SELECT id FROM classes WHERE name='12-A'), 'friday',    3, (SELECT id FROM subjects WHERE code='ENG'), (SELECT id FROM teachers WHERE emp_id='T003'), 'Room 201'),
  ((SELECT id FROM classes WHERE name='12-A'), 'friday',    4, (SELECT id FROM subjects WHERE code='CHE'), (SELECT id FROM teachers WHERE emp_id='T004'), 'Lab-B'),
  ((SELECT id FROM classes WHERE name='12-A'), 'friday',    5, (SELECT id FROM subjects WHERE code='CS'),  (SELECT id FROM teachers WHERE emp_id='T006'), 'Lab-C'),
  ((SELECT id FROM classes WHERE name='12-A'), 'friday',    6, (SELECT id FROM subjects WHERE code='PHY'), (SELECT id FROM teachers WHERE emp_id='T001'), 'Lab-A');
