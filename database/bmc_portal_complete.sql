-- ============================================================
--  BMC Portal — COMPLETE DATABASE
--  Bahria Model College Bin Qasim
--  Run once on a fresh MySQL / MariaDB server:
--    mysql -u root -p < bmc_portal_complete.sql
--
--  This file merges ALL schema files + migrations into one.
--  Safe to re-run (uses CREATE TABLE IF NOT EXISTS + INSERT IGNORE).
--  DROP DATABASE block at top is commented out by default — uncomment
--  ONLY if you want a clean wipe.
-- ============================================================

-- OPTIONAL — Uncomment to wipe and recreate:
-- DROP DATABASE IF EXISTS bmc_portal;

CREATE DATABASE IF NOT EXISTS bmc_portal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE bmc_portal;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
--  TABLE 1 — settings   (portal settings, no FK)
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  key_name   VARCHAR(100) UNIQUE NOT NULL,
  value      TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 2 — classes
-- ============================================================
CREATE TABLE IF NOT EXISTS classes (
  id             INT PRIMARY KEY AUTO_INCREMENT,
  name           VARCHAR(20)  NOT NULL UNIQUE,
  grade          INT          NOT NULL,
  section        VARCHAR(5)   NOT NULL,
  is_ilc         TINYINT(1)   NOT NULL DEFAULT 0,
  is_montessori  TINYINT(1)   NOT NULL DEFAULT 0,
  wing           ENUM('main','montessori','ilc') NOT NULL DEFAULT 'main'
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 3 — subjects
-- ============================================================
CREATE TABLE IF NOT EXISTS subjects (
  id   INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(20)
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 4 — houses
-- ============================================================
CREATE TABLE IF NOT EXISTS houses (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(100) NOT NULL,
  color      VARCHAR(20)  NOT NULL DEFAULT '#3b82f6',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 5 — disability_categories
-- ============================================================
CREATE TABLE IF NOT EXISTS disability_categories (
  id   INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 6 — disability_subtypes
-- ============================================================
CREATE TABLE IF NOT EXISTS disability_subtypes (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  category_id INT NOT NULL,
  name        VARCHAR(150) NOT NULL,
  FOREIGN KEY (category_id) REFERENCES disability_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 7 — users  (all roles; includes profile-photo cols)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id                    INT PRIMARY KEY AUTO_INCREMENT,
  user_id               VARCHAR(20) UNIQUE NOT NULL
                          COMMENT 'Roll No / T001 / ADM001 / FIN001',
  name                  VARCHAR(100) NOT NULL,
  email                 VARCHAR(100),
  password              VARCHAR(255) NOT NULL,
  role                  ENUM(
                          'student','teacher','admin','finance',
                          'ilc_vp','student_affairs',
                          'vp_main','wing_head'
                        ) NOT NULL,
  status                ENUM('active','inactive','pending') DEFAULT 'active',
  profile_photo         VARCHAR(255)  DEFAULT NULL,
  photo_status          ENUM('none','pending','approved','rejected')
                          NOT NULL DEFAULT 'none',
  photo_rejection_reason VARCHAR(255) DEFAULT NULL,
  photo_reviewed_by     INT           DEFAULT NULL,
  photo_reviewed_at     TIMESTAMP NULL DEFAULT NULL,
  last_login            TIMESTAMP NULL,
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photo_reviewer
    FOREIGN KEY (photo_reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 8 — teachers
-- ============================================================
CREATE TABLE IF NOT EXISTS teachers (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  user_id       INT NOT NULL UNIQUE,
  emp_id        VARCHAR(20) UNIQUE NOT NULL,
  subject_id    INT,
  designation   VARCHAR(100) DEFAULT 'Subject Teacher',
  qualification VARCHAR(200),
  phone         VARCHAR(20),
  join_date     DATE,
  is_ilc        TINYINT(1)   NOT NULL DEFAULT 0,
  wing          ENUM('main','montessori','ilc') NOT NULL DEFAULT 'main',
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 9 — students  (all biodata columns included)
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
  id                  INT PRIMARY KEY AUTO_INCREMENT,
  user_id             INT NOT NULL UNIQUE,
  roll_no             VARCHAR(20) UNIQUE NOT NULL,
  class_id            INT,
  house_id            INT,
  gr_no               VARCHAR(30)  DEFAULT NULL COMMENT 'General Register / GR Number',
  kuickpay_id         VARCHAR(30)  DEFAULT NULL COMMENT 'Kuickpay student/consumer ID',
  category            VARCHAR(30)  DEFAULT NULL COMMENT 'Admission category',
  student_category    ENUM('civilian','cpo','sailor') NULL,
  academic_group      VARCHAR(50)  DEFAULT NULL COMMENT 'Academic stream',
  father_name         VARCHAR(100),
  dob                 DATE,
  gender              ENUM('male','female','other'),
  cnic                VARCHAR(20),
  phone               VARCHAR(20),
  address             TEXT,
  permanent_address   TEXT         DEFAULT NULL,
  parent_phone        VARCHAR(20),
  parent_email        VARCHAR(100),
  parent_name         VARCHAR(150),
  admission_date      DATE,
  child_order         TINYINT      DEFAULT NULL,
  domicile            VARCHAR(100) DEFAULT NULL,
  emergency_phone     VARCHAR(20)  DEFAULT NULL,
  whatsapp_no         VARCHAR(20)  DEFAULT NULL,
  religion            VARCHAR(50)  DEFAULT NULL,
  sect                VARCHAR(50)  DEFAULT NULL,
  blood_group         VARCHAR(5)   DEFAULT NULL,
  nationality         VARCHAR(100) DEFAULT NULL,
  last_school         VARCHAR(200) DEFAULT NULL,
  father_occupation   VARCHAR(150) DEFAULT NULL,
  documents_submitted TEXT         DEFAULT NULL,
  medical_info        TEXT         DEFAULT NULL,
  skills              TEXT         DEFAULT NULL,
  sports              TEXT         DEFAULT NULL,
  awards              TEXT         DEFAULT NULL,
  FOREIGN KEY (user_id)  REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id)  ON DELETE SET NULL,
  FOREIGN KEY (house_id) REFERENCES houses(id)   ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 10 — class_subjects
-- ============================================================
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

-- ============================================================
--  TABLE 11 — assessments
-- ============================================================
CREATE TABLE IF NOT EXISTS assessments (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  title       VARCHAR(100),
  type        ENUM('Quiz','Assignment','Mid Term','Final Term','Practical',
                   'quiz','assignment','class_test','mid_term','final_term','practical')
                NOT NULL DEFAULT 'Quiz',
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

-- ============================================================
--  TABLE 12 — marks
-- ============================================================
CREATE TABLE IF NOT EXISTS marks (
  id             INT PRIMARY KEY AUTO_INCREMENT,
  student_id     INT NOT NULL,
  assessment_id  INT NOT NULL,
  marks_obtained DECIMAL(6,2),
  remarks        VARCHAR(255),
  entered_by     INT,
  entered_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mark (student_id, assessment_id),
  FOREIGN KEY (student_id)    REFERENCES students(id)    ON DELETE CASCADE,
  FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
  FOREIGN KEY (entered_by)    REFERENCES teachers(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 13 — attendance
-- ============================================================
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

-- ============================================================
--  TABLE 14 — fees
-- ============================================================
CREATE TABLE IF NOT EXISTS fees (
  id           INT PRIMARY KEY AUTO_INCREMENT,
  student_id   INT NOT NULL,
  month        INT NOT NULL,
  year         INT NOT NULL,
  amount       DECIMAL(10,2) NOT NULL DEFAULT 12000,
  paid         TINYINT(1) DEFAULT 0,
  payment_date DATE,
  payment_mode ENUM('cash','bank','online','cheque','kuickpay'),
  receipt_no   VARCHAR(50),
  remarks      VARCHAR(255),
  recorded_by  INT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fee (student_id, month, year),
  FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 15 — timetable
-- ============================================================
CREATE TABLE IF NOT EXISTS timetable (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  class_id    INT NOT NULL,
  day         ENUM('monday','tuesday','wednesday','thursday','friday') NOT NULL,
  period      INT NOT NULL,
  subject_id  INT,
  teacher_id  INT,
  room        VARCHAR(50),
  UNIQUE KEY uq_tt (class_id, day, period),
  FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 16 — notices  (portal)
-- ============================================================
CREATE TABLE IF NOT EXISTS notices (
  id           INT PRIMARY KEY AUTO_INCREMENT,
  title        VARCHAR(255) NOT NULL,
  body         TEXT,
  content      TEXT,
  category     VARCHAR(50)  NOT NULL DEFAULT 'general',
  priority     ENUM('normal','important','urgent') DEFAULT 'normal',
  audience     SET('students','teachers','finance','admin') NOT NULL
                 DEFAULT 'students,teachers,finance,admin',
  pinned       TINYINT(1) DEFAULT 0,
  author_id    INT,
  expiry_date  DATE,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 17 — profile_change_requests
-- ============================================================
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

-- ============================================================
--  TABLE 18 — activity_log
-- ============================================================
CREATE TABLE IF NOT EXISTS activity_log (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  user_id    INT,
  action     VARCHAR(100) NOT NULL,
  details    TEXT,
  ip_address VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 19 — student_warnings
-- ============================================================
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

-- ============================================================
--  TABLE 20 — student_complaints
-- ============================================================
CREATE TABLE IF NOT EXISTS student_complaints (
  id               INT PRIMARY KEY AUTO_INCREMENT,
  student_id       INT NOT NULL,
  teacher_id       INT NOT NULL,
  subject          VARCHAR(255) NOT NULL,
  message          TEXT NOT NULL,
  teacher_response TEXT,
  status           ENUM('pending','reviewed','resolved') NOT NULL DEFAULT 'pending',
  responded_at     DATETIME,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 21 — daily_diary
-- ============================================================
CREATE TABLE IF NOT EXISTS daily_diary (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  teacher_id INT NOT NULL,
  class_id   INT NOT NULL,
  date       DATE NOT NULL,
  title      VARCHAR(255) NOT NULL,
  content    TEXT NOT NULL,
  homework   TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 22 — diary_media
-- ============================================================
CREATE TABLE IF NOT EXISTS diary_media (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  diary_id   INT NOT NULL,
  filename   VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (diary_id) REFERENCES daily_diary(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 23 — academic_calendars
-- ============================================================
CREATE TABLE IF NOT EXISTS academic_calendars (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  title       VARCHAR(255) NOT NULL,
  description TEXT,
  filename    VARCHAR(255) NOT NULL,
  year        YEAR NOT NULL DEFAULT (YEAR(CURDATE())),
  uploaded_by INT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 24 — attendance_edit_requests
-- ============================================================
CREATE TABLE IF NOT EXISTS attendance_edit_requests (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  teacher_id  INT NOT NULL,
  student_id  INT NOT NULL,
  class_id    INT NOT NULL,
  subject_id  INT NOT NULL,
  date        DATE NOT NULL,
  old_status  ENUM('P','A','L') NOT NULL,
  new_status  ENUM('P','A','L') NOT NULL,
  reason      TEXT NOT NULL,
  status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by INT,
  reviewed_at DATETIME,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id)  REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id)    REFERENCES classes(id)  ON DELETE CASCADE,
  FOREIGN KEY (subject_id)  REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 25 — user_permissions
-- ============================================================
CREATE TABLE IF NOT EXISTS user_permissions (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  user_id    INT NOT NULL,
  permission VARCHAR(60) NOT NULL,
  granted    TINYINT(1)  NOT NULL DEFAULT 1,
  UNIQUE KEY uq_user_perm (user_id, permission),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 26 — student_disabilities
-- ============================================================
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

-- ============================================================
--  TABLE 27 — admission_requests
-- ============================================================
CREATE TABLE IF NOT EXISTS admission_requests (
  id               INT PRIMARY KEY AUTO_INCREMENT,
  student_name     VARCHAR(150) NOT NULL,
  parent_name      VARCHAR(150),
  parent_phone     VARCHAR(20),
  dob              DATE,
  requested_class  VARCHAR(50),
  student_category ENUM('civilian','cpo','sailor') NULL,
  wing             ENUM('main','montessori','ilc') NULL,
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

-- ============================================================
--  TABLE 28 — medical_records
-- ============================================================
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

-- ============================================================
--  TABLE 29 — ilc_session_records
-- ============================================================
CREATE TABLE IF NOT EXISTS ilc_session_records (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  title       VARCHAR(255) NOT NULL,
  description TEXT,
  filename    VARCHAR(255) NOT NULL,
  file_type   ENUM('pdf','video') NOT NULL DEFAULT 'pdf',
  uploaded_by INT,
  expires_at  DATETIME NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
--  TABLE 30 — kuickpay_transactions
-- ============================================================
CREATE TABLE IF NOT EXISTS kuickpay_transactions (
  id              INT            NOT NULL AUTO_INCREMENT,
  batch_id        VARCHAR(30)    NOT NULL,
  tran_datetime   DATETIME,
  reg_num         VARCHAR(20),
  consumer_num    VARCHAR(30),
  auth_id         VARCHAR(30),
  tran_date_raw   VARCHAR(10),
  tran_time_raw   VARCHAR(10),
  transaction_id  VARCHAR(60),
  voucher_num     VARCHAR(80),
  bank            VARCHAR(30),
  amount          DECIMAL(10,2)  DEFAULT 0,
  fee_charge      DECIMAL(10,2)  DEFAULT 0,
  tax             DECIMAL(10,2)  DEFAULT 0,
  net_amount      DECIMAL(10,2)  DEFAULT 0,
  consumer_detail VARCHAR(200),
  network         VARCHAR(50),
  channel         VARCHAR(50),
  fee_month       TINYINT,
  fee_year        SMALLINT,
  student_id      INT            NULL,
  status          ENUM('matched','unmatched','duplicate','already_paid') DEFAULT 'unmatched',
  imported_by     INT            NULL,
  imported_at     TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_batch   (batch_id),
  INDEX idx_reg     (reg_num),
  INDEX idx_student (student_id),
  INDEX idx_period  (fee_month, fee_year),
  FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE SET NULL,
  FOREIGN KEY (imported_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  WEBSITE TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS site_settings (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  `key`      VARCHAR(100) NOT NULL UNIQUE,
  `value`    TEXT,
  label      VARCHAR(255),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_sliders (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  title       VARCHAR(255),
  subtitle    TEXT,
  image       VARCHAR(255) NOT NULL,
  button_text VARCHAR(100),
  button_url  VARCHAR(255),
  sort_order  INT DEFAULT 0,
  is_active   TINYINT(1) DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_news (
  id           INT PRIMARY KEY AUTO_INCREMENT,
  title        VARCHAR(500) NOT NULL,
  slug         VARCHAR(500) NOT NULL UNIQUE,
  excerpt      TEXT,
  content      LONGTEXT,
  image        VARCHAR(255),
  category     VARCHAR(100) DEFAULT 'General',
  tags         VARCHAR(500),
  is_published TINYINT(1) DEFAULT 1,
  is_featured  TINYINT(1) DEFAULT 0,
  published_at DATETIME,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_events (
  id           INT PRIMARY KEY AUTO_INCREMENT,
  title        VARCHAR(500) NOT NULL,
  slug         VARCHAR(500) NOT NULL UNIQUE,
  description  LONGTEXT,
  image        VARCHAR(255),
  event_date   DATE NOT NULL,
  event_time   TIME,
  end_date     DATE,
  venue        VARCHAR(500),
  is_published TINYINT(1) DEFAULT 1,
  is_featured  TINYINT(1) DEFAULT 0,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_notices (
  id           INT PRIMARY KEY AUTO_INCREMENT,
  title        VARCHAR(500) NOT NULL,
  content      TEXT,
  category     VARCHAR(100) DEFAULT 'General',
  priority     ENUM('normal','important','urgent') DEFAULT 'normal',
  attachment   VARCHAR(255),
  expires_at   DATE,
  is_published TINYINT(1) DEFAULT 1,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_departments (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  name        VARCHAR(255) NOT NULL,
  slug        VARCHAR(255) NOT NULL UNIQUE,
  description TEXT,
  image       VARCHAR(255),
  icon        VARCHAR(100) DEFAULT 'fa-university',
  sort_order  INT DEFAULT 0,
  is_active   TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_programs (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  department_id INT,
  name          VARCHAR(255) NOT NULL,
  description   TEXT,
  duration      VARCHAR(100),
  eligibility   TEXT,
  is_active     TINYINT(1) DEFAULT 1,
  FOREIGN KEY (department_id) REFERENCES site_departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_faculty (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  name          VARCHAR(255) NOT NULL,
  slug          VARCHAR(255) NOT NULL UNIQUE,
  designation   VARCHAR(255),
  department_id INT,
  qualification VARCHAR(500),
  email         VARCHAR(255),
  phone         VARCHAR(50),
  image         VARCHAR(255),
  bio           TEXT,
  research      TEXT,
  publications  TEXT,
  sort_order    INT DEFAULT 0,
  is_active     TINYINT(1) DEFAULT 1,
  FOREIGN KEY (department_id) REFERENCES site_departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_albums (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  name        VARCHAR(255) NOT NULL,
  description TEXT,
  cover_image VARCHAR(255),
  is_active   TINYINT(1) DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_gallery (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  album_id   INT,
  title      VARCHAR(255),
  filename   VARCHAR(255) NOT NULL,
  caption    TEXT,
  sort_order INT DEFAULT 0,
  is_active  TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (album_id) REFERENCES site_albums(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_videos (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  title      VARCHAR(255) NOT NULL,
  url        VARCHAR(500) NOT NULL,
  thumbnail  VARCHAR(255),
  category   VARCHAR(100) DEFAULT 'General',
  is_active  TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_downloads (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  title      VARCHAR(500) NOT NULL,
  category   VARCHAR(100) DEFAULT 'General',
  filename   VARCHAR(255) NOT NULL,
  file_size  VARCHAR(50),
  is_active  TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_admission_forms (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  title      VARCHAR(255) NOT NULL,
  year       VARCHAR(20),
  filename   VARCHAR(255) NOT NULL,
  is_active  TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_testimonials (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  name        VARCHAR(255) NOT NULL,
  designation VARCHAR(255),
  content     TEXT NOT NULL,
  image       VARCHAR(255),
  rating      TINYINT DEFAULT 5,
  is_active   TINYINT(1) DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_stats (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  label      VARCHAR(255) NOT NULL,
  value      INT NOT NULL DEFAULT 0,
  icon       VARCHAR(100) DEFAULT 'fa-star',
  suffix     VARCHAR(20) DEFAULT '+',
  sort_order INT DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_partners (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(255) NOT NULL,
  logo       VARCHAR(255),
  website    VARCHAR(500),
  sort_order INT DEFAULT 0,
  is_active  TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_contact_messages (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(255) NOT NULL,
  email      VARCHAR(255) NOT NULL,
  phone      VARCHAR(50),
  subject    VARCHAR(500),
  message    TEXT NOT NULL,
  is_read    TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_admins (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(255) NOT NULL,
  email      VARCHAR(255) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  role       ENUM('super_admin','admin','content_manager') DEFAULT 'admin',
  is_active  TINYINT(1) DEFAULT 1,
  last_login DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_pages (
  id               INT PRIMARY KEY AUTO_INCREMENT,
  title            VARCHAR(500) NOT NULL,
  slug             VARCHAR(500) NOT NULL UNIQUE,
  content          LONGTEXT,
  meta_title       VARCHAR(500),
  meta_description TEXT,
  is_published     TINYINT(1) DEFAULT 1,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_careers (
  id           INT PRIMARY KEY AUTO_INCREMENT,
  title        VARCHAR(500) NOT NULL,
  department   VARCHAR(255),
  description  TEXT,
  requirements TEXT,
  deadline     DATE,
  is_published TINYINT(1) DEFAULT 1,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  SEED DATA
-- ============================================================

-- ── Portal Settings ───────────────────────────────────────────────
INSERT IGNORE INTO settings (key_name, value) VALUES
  ('school_name',    'Bahria Model College Bin Qasim'),
  ('school_address', 'Bin Qasim, Karachi, Pakistan'),
  ('session_year',   '2025-26'),
  ('current_term',   'Term 2'),
  ('fee_per_month',  '12000'),
  ('min_attendance', '75'),
  ('principal_name', 'Lt. Cdr. Abu Bakar');

-- ── Classes ───────────────────────────────────────────────────────
INSERT IGNORE INTO classes (id, name, grade, section, is_ilc, is_montessori, wing) VALUES
  -- Main wing classes
  ( 1, '8-A',  8,  'A', 0, 0, 'main'),
  ( 2, '8-B',  8,  'B', 0, 0, 'main'),
  ( 3, '8-C',  8,  'C', 0, 0, 'main'),
  ( 4, '9-A',  9,  'A', 0, 0, 'main'),
  ( 5, '9-B',  9,  'B', 0, 0, 'main'),
  ( 6, '9-C',  9,  'C', 0, 0, 'main'),
  ( 7, '10-A', 10, 'A', 0, 0, 'main'),
  ( 8, '10-B', 10, 'B', 0, 0, 'main'),
  ( 9, '10-C', 10, 'C', 0, 0, 'main'),
  (14, '11-A', 11, 'A', 0, 0, 'main'),
  (15, '11-B', 11, 'B', 0, 0, 'main'),
  (16, '12-A', 12, 'A', 0, 0, 'main'),
  (17, '12-B', 12, 'B', 0, 0, 'main'),
  -- ILC classes
  (10, 'ILC-A', 0, 'A', 1, 0, 'ilc'),
  (11, 'ILC-B', 0, 'B', 1, 0, 'ilc'),
  -- Montessori classes
  (20, 'Beginner', 0, 'A', 0, 1, 'montessori'),
  (21, 'Advance',  0, 'B', 0, 1, 'montessori'),
  (22, 'Prep',     0, 'C', 0, 1, 'montessori'),
  (23, 'Class-1',  1, 'A', 0, 1, 'montessori'),
  (24, 'Class-2',  2, 'A', 0, 1, 'montessori'),
  (25, 'Class-3',  3, 'A', 0, 1, 'montessori');

-- ── Subjects ──────────────────────────────────────────────────────
INSERT IGNORE INTO subjects (id, name, code) VALUES
  (1,  'Physics',                        'PHY'),
  (2,  'Mathematics',                    'MAT'),
  (3,  'English',                        'ENG'),
  (4,  'Chemistry',                      'CHE'),
  (5,  'Biology',                        'BIO'),
  (6,  'Computer Science',               'CS'),
  (7,  'Urdu',                           'URD'),
  (8,  'Islamiat',                       'ISL'),
  (9,  'Pakistan Studies',               'PKS'),
  (10, 'Science',                        'SCI'),
  (11, 'General Knowledge',              'GK'),
  (12, 'Islamic Studies',                'IS'),
  (13, 'Art and Drawing',                'ARTD'),
  (14, 'Physical and Social Development','PSD'),
  (15, 'Nazra Quran',                    'NZQ'),
  (16, 'Computer Studies',               'CSTU'),
  (17, 'Sindhi',                         'SND'),
  (18, 'Moalamul Quran',                 'MLQ'),
  (19, 'Biology (Botany and Zoology)',   'BZO'),
  (20, 'Art',                            'ART');

-- ── Houses ────────────────────────────────────────────────────────
INSERT IGNORE INTO houses (id, name, color) VALUES
  (1, 'Allama Iqbal',   '#3b82f6'),
  (2, 'Quaid-e-Azam',  '#22c55e'),
  (3, 'Fatima Jinnah', '#f97316'),
  (4, 'Sir Syed',      '#a855f7');

-- ── Disability Categories & Subtypes ─────────────────────────────
INSERT IGNORE INTO disability_categories (id, name) VALUES
  (1, 'Learning Disability'),
  (2, 'Blind & VI (Visual Impairment)'),
  (3, 'HI (Hearing Impaired)'),
  (4, 'Down Syndrome'),
  (5, 'Autistic Disorders'),
  (6, 'ADHD');

INSERT IGNORE INTO disability_subtypes (id, category_id, name) VALUES
  ( 1, 1, 'Dyslexia'),
  ( 2, 1, 'Dysgraphia'),
  ( 3, 1, 'Dyscalculia'),
  ( 4, 1, 'NVLD (Nonverbal Learning Disability)'),
  ( 5, 1, 'Auditory Processing Disorder'),
  ( 6, 1, 'Visual Processing Disorder'),
  ( 7, 2, 'Low vision'),
  ( 8, 2, 'Partial sight'),
  ( 9, 2, 'Color blindness'),
  (10, 2, 'Night blindness'),
  (11, 3, 'Conductive hearing loss'),
  (12, 3, 'Mixed hearing loss'),
  (13, 3, 'Bilateral hearing loss'),
  (14, 3, 'Sensorineural hearing loss'),
  (15, 4, 'Trisomy 21'),
  (16, 4, 'Translocation Down Syndrome'),
  (17, 4, 'Mosaic Down Syndrome'),
  (18, 4, 'Partial Trisomy 21'),
  (19, 5, 'Asperger''s Syndrome'),
  (20, 5, 'Level 1 ASD'),
  (21, 5, 'Level 2 ASD'),
  (22, 5, 'Level 3 ASD'),
  (23, 5, 'Rett Syndrome'),
  (24, 6, 'Over-focused ADHD'),
  (25, 6, 'Predominantly inattentive ADHD'),
  (26, 6, 'Limbic ADHD');

-- ── Users ─────────────────────────────────────────────────────────
-- Passwords:
--   student123  → $2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC
--   teacher123  → $2y$12$wL3VFI9UOKWAnCPNKd6yZe.bJhiNbG4gFP2Pnmur1aqWJy.H9Xt/y
--   admin123    → $2y$12$qDfXpImX0o5JInILNE1j1u6B/xVuGE.n8gm/G6/c/jHnvxJlFbFmm
--   finance123  → $2y$12$cIJe0/OLqblLe9DLbpqc8.BI3LFl5yxCgVBTRl2.fVxJ3nxrqEMHi
--   Admin@2025  → $2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG
--   (ILC/SA/VP/WH use Admin@2025 hash — change on first login)

-- Admin
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
  (1, '1001', 'Mr. Tariq Mehmood', 'admin@bmc.edu.pk',
   '$2y$12$qDfXpImX0o5JInILNE1j1u6B/xVuGE.n8gm/G6/c/jHnvxJlFbFmm', 'admin', 'active');

-- Finance
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
  (2, '1002', 'Ms. Ayesha Rizvi', 'finance@bmc.edu.pk',
   '$2y$12$cIJe0/OLqblLe9DLbpqc8.BI3LFl5yxCgVBTRl2.fVxJ3nxrqEMHi', 'finance', 'active');

-- ILC VP
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
  (3, '1003', 'Dr. Amna Siddiqui', 'amna.ilc@bmc.edu.pk',
   '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'ilc_vp', 'active');

-- Student Affairs
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
  (4, '1004', 'Mr. Tariq Aziz', 'tariq.sa@bmc.edu.pk',
   '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student_affairs', 'active');

-- VP Main
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
  (5, '1005', 'Mr. Asad Khan', 'asad.vp@bmc.edu.pk',
   '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'vp_main', 'active');

-- Wing Head
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
  (6, '1006', 'Ms. Rubina Akhtar', 'rubina.wh@bmc.edu.pk',
   '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'wing_head', 'active');

-- Teachers (IDs 101–106)
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
  (101, '2001', 'Dr. Sarah Khan',     'sarah@bmc.edu.pk',   '$2y$12$wL3VFI9UOKWAnCPNKd6yZe.bJhiNbG4gFP2Pnmur1aqWJy.H9Xt/y', 'teacher', 'active'),
  (102, '2002', 'Mr. Hasan Ali',      'hasan@bmc.edu.pk',   '$2y$12$wL3VFI9UOKWAnCPNKd6yZe.bJhiNbG4gFP2Pnmur1aqWJy.H9Xt/y', 'teacher', 'active'),
  (103, '2003', 'Ms. Nadia Raza',     'nadia@bmc.edu.pk',   '$2y$12$wL3VFI9UOKWAnCPNKd6yZe.bJhiNbG4gFP2Pnmur1aqWJy.H9Xt/y', 'teacher', 'active'),
  (104, '2004', 'Dr. Amina Siddiqui', 'amina@bmc.edu.pk',   '$2y$12$wL3VFI9UOKWAnCPNKd6yZe.bJhiNbG4gFP2Pnmur1aqWJy.H9Xt/y', 'teacher', 'active'),
  (105, '2005', 'Mr. Imran Hassan',   'imran@bmc.edu.pk',   '$2y$12$wL3VFI9UOKWAnCPNKd6yZe.bJhiNbG4gFP2Pnmur1aqWJy.H9Xt/y', 'teacher', 'active'),
  (106, '2006', 'Mr. Farhan Ahmed',   'farhan@bmc.edu.pk',  '$2y$12$wL3VFI9UOKWAnCPNKd6yZe.bJhiNbG4gFP2Pnmur1aqWJy.H9Xt/y', 'teacher', 'active');

-- Students (IDs 201–215)
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
  (201, '3001', 'Ahmed Ali',         'ahmed@bmc.edu.pk',   '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (202, '3002', 'Fatima Noor',       'fatima@bmc.edu.pk',  '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (203, '3003', 'Hassan Raza',       'hassan@bmc.edu.pk',  '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (204, '3004', 'Iqra Shah',         'iqra@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (205, '3005', 'Bilal Tariq',       'bilal@bmc.edu.pk',   '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (206, '3006', 'Zara Iqbal',        'zara@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (207, '3007', 'Saad Qureshi',      'saad@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (208, '3008', 'Amna Zahid',        'amna@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (209, '3009', 'Usman Ghani',       'usman@bmc.edu.pk',   '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (210, '3010', 'Rabia Malik',       'rabia@bmc.edu.pk',   '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (211, '3011', 'Faisal Khan',       'faisal@bmc.edu.pk',  '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (212, '3012', 'Nida Ansari',       'nida@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (213, '3013', 'Asad Mehmood',      'asad@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (214, '3014', 'Sana Butt',         'sana@bmc.edu.pk',    '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active'),
  (215, '3015', 'Kamran Elahi',      'kamran@bmc.edu.pk',  '$2y$12$yQmv/GNaEXY/.UZX9xVbIeN4Dm5sOTIUBkrwjQs6HvGb1OL4uYvRC', 'student', 'active');

-- ── Teachers ──────────────────────────────────────────────────────
INSERT IGNORE INTO teachers (id, user_id, emp_id, subject_id, designation, qualification, join_date) VALUES
  (1, 101, '2001', 1, 'Senior Teacher',   'M.Phil Physics',          '2019-09-05'),
  (2, 102, '2002', 2, 'Subject Teacher',  'M.Sc Mathematics',        '2021-03-10'),
  (3, 103, '2003', 3, 'Subject Teacher',  'M.A English',             '2020-08-20'),
  (4, 104, '2004', 4, 'Senior Teacher',   'M.Sc Chemistry',          '2022-01-15'),
  (5, 105, '2005', 5, 'Subject Teacher',  'M.Sc Biology',            '2021-07-12'),
  (6, 106, '2006', 6, 'Subject Teacher',  'B.Sc Computer Science',   '2023-02-01');

-- ── Students ──────────────────────────────────────────────────────
-- All in class 12-A (id=16)
INSERT IGNORE INTO students
  (id, user_id, roll_no, class_id, house_id, father_name, dob, gender,
   phone, address, parent_phone, parent_email, admission_date,
   blood_group, nationality, religion, academic_group)
VALUES
  (1,  201, '3001', 16, 1, 'Muhammad Ali',   '2007-03-15', 'male',
   '0300-1234567', 'House 12, Block B, Bahria Town', '0321-9876543', 'mali@gmail.com', '2022-04-01',
   'B+', 'Pakistani', 'Islam', 'Science'),
  (2,  202, '3002', 16, 2, 'Noor Ahmed',     '2007-07-22', 'female',
   '0301-2345678', 'House 34, Block C, Bahria Town', '0322-8765432', 'noor@gmail.com', '2022-04-01',
   'O+', 'Pakistani', 'Islam', 'Science'),
  (3,  203, '3003', 16, 3, 'Raza Khan',      '2007-01-10', 'male',
   '0302-3456789', 'Flat 5, Tower A, Bahria Town',   '0323-7654321', 'raza@gmail.com', '2022-04-01',
   'A+', 'Pakistani', 'Islam', 'Pre-Medical'),
  (4,  204, '3004', 16, 4, 'Shahid Hussain', '2007-11-05', 'female',
   '0303-4567890', 'House 78, Street 3, Bahria Town','0324-6543210', 'shah@gmail.com', '2022-04-01',
   'A-', 'Pakistani', 'Islam', 'Science'),
  (5,  205, '3005', 16, 1, 'Tariq Mehmood',  '2007-08-18', 'male',
   '0304-5678901', 'House 22, Block D, Bahria Town', '0325-5432109', 'tariq@gmail.com', '2022-04-01',
   'B-', 'Pakistani', 'Islam', 'Commerce'),
  (6,  206, '3006', 16, 2, 'Iqbal Hussain',  '2007-06-25', 'female',
   '0305-6789012', 'House 56, Street 9, Bahria Town','0326-4321098', 'iqb@gmail.com', '2022-04-01',
   'O-', 'Pakistani', 'Islam', 'Science'),
  (7,  207, '3007', 16, 3, 'Qureshi Sahib',  '2007-04-12', 'male',
   '0306-7890123', 'House 88, Block E, Bahria Town', '0327-3210987', 'qur@gmail.com', '2022-04-01',
   'AB+','Pakistani', 'Islam', 'Arts'),
  (8,  208, '3008', 16, 4, 'Zahid Ali',      '2007-09-30', 'female',
   '0307-8901234', 'House 10, Street 7, Bahria Town','0328-2109876', 'zah@gmail.com', '2022-04-01',
   'A+', 'Pakistani', 'Islam', 'Science'),
  (9,  209, '3009', 16, 1, 'Ghani Sahib',    '2007-12-14', 'male',
   '0308-9012345', 'House 43, Block F, Bahria Town', '0329-1098765', 'gha@gmail.com', '2022-04-01',
   'B+', 'Pakistani', 'Islam', 'Pre-Medical'),
  (10, 210, '3010', 16, 2, 'Malik Sahib',    '2007-02-08', 'female',
   '0309-0123456', 'House 67, Street 2, Bahria Town','0330-0987654', 'mal@gmail.com', '2022-04-01',
   'O+', 'Pakistani', 'Islam', 'Arts'),
  (11, 211, '3011', 16, 3, 'Khan Bahadur',   '2007-05-20', 'male',
   '0310-1234560', 'House 91, Block G, Bahria Town', '0331-9876540', 'kha@gmail.com', '2022-04-01',
   'A-', 'Pakistani', 'Islam', 'Commerce'),
  (12, 212, '3012', 16, 4, 'Ansari Sahib',   '2007-10-03', 'female',
   '0311-2345671', 'House 25, Street 6, Bahria Town','0332-8765431', 'ans@gmail.com', '2022-04-01',
   'B-', 'Pakistani', 'Islam', 'Science'),
  (13, 213, '3013', 16, 1, 'Mehmood Sahib',  '2007-07-17', 'male',
   '0312-3456782', 'House 49, Block H, Bahria Town', '0333-7654322', 'meh@gmail.com', '2022-04-01',
   'AB-','Pakistani', 'Islam', 'Pre-Medical'),
  (14, 214, '3014', 16, 2, 'Butt Sahib',     '2007-03-28', 'female',
   '0313-4567893', 'House 73, Street 1, Bahria Town','0334-6543213', 'but@gmail.com', '2022-04-01',
   'O-', 'Pakistani', 'Islam', 'Science'),
  (15, 215, '3015', 16, 3, 'Elahi Sahib',    '2007-09-11', 'male',
   '0314-5678904', 'House 97, Block I, Bahria Town', '0335-5432104', 'ela@gmail.com', '2022-04-01',
   'A+', 'Pakistani', 'Islam', 'Commerce');

-- ── Class–Subject assignments ─────────────────────────────────────
INSERT IGNORE INTO class_subjects (class_id, subject_id, teacher_id) VALUES
  (16, 1, 1),  -- 12-A: Physics → T001
  (16, 2, 2),  -- 12-A: Math    → T002
  (16, 3, 3),  -- 12-A: English → T003
  (16, 4, 4),  -- 12-A: Chemistry → T004
  (16, 5, 5),  -- 12-A: Biology → T005
  (16, 6, 6);  -- 12-A: CS      → T006

-- ── Assessments for Physics (class 12-A) ─────────────────────────
INSERT IGNORE INTO assessments (id, name, title, type, max_marks, weight, date, class_id, subject_id, teacher_id, locked) VALUES
  (1, 'Quiz 1',       'Quiz 1',       'Quiz',       10,  5, '2026-02-05', 16, 1, 1, 1),
  (2, 'Quiz 2',       'Quiz 2',       'Quiz',       10,  5, '2026-02-20', 16, 1, 1, 1),
  (3, 'Assignment 1', 'Assignment 1', 'Assignment', 20, 10, '2026-02-28', 16, 1, 1, 1),
  (4, 'Mid Term',     'Mid Term',     'Mid Term',   40, 30, '2026-03-15', 16, 1, 1, 1),
  (5, 'Practical',    'Practical',    'Practical',  20, 10, '2026-04-10', 16, 1, 1, 1),
  (6, 'Final Term',   'Final Term',   'Final Term', 100,40, '2026-06-10', 16, 1, 1, 0);

-- ── Marks — Quiz 1 ────────────────────────────────────────────────
INSERT IGNORE INTO marks (student_id, assessment_id, marks_obtained, entered_by) VALUES
  (1,  1, 9,  1), (2,  1, 8,  1), (3,  1, 6,  1), (4,  1, 10, 1), (5,  1, 5,  1),
  (6,  1, 7,  1), (7,  1, 6,  1), (8,  1, 8,  1), (9,  1, 4,  1), (10, 1, 7,  1),
  (11, 1, 9,  1), (12, 1, 6,  1), (13, 1, 7,  1), (14, 1, 9,  1), (15, 1, 5,  1);

-- ── Marks — Quiz 2 ────────────────────────────────────────────────
INSERT IGNORE INTO marks (student_id, assessment_id, marks_obtained, entered_by) VALUES
  (1,  2, 8,  1), (2,  2, 9,  1), (3,  2, 5,  1), (4,  2, 10, 1), (5,  2, 4,  1),
  (6,  2, 7,  1), (7,  2, 5,  1), (8,  2, 9,  1), (9,  2, 3,  1), (10, 2, 6,  1),
  (11, 2, 8,  1), (12, 2, 7,  1), (13, 2, 6,  1), (14, 2, 10, 1), (15, 2, 4,  1);

-- ── Marks — Assignment 1 ──────────────────────────────────────────
INSERT IGNORE INTO marks (student_id, assessment_id, marks_obtained, entered_by) VALUES
  (1,  3, 18, 1), (2,  3, 17, 1), (3,  3, 13, 1), (4,  3, 19, 1), (5,  3, 11, 1),
  (6,  3, 15, 1), (7,  3, 12, 1), (8,  3, 16, 1), (9,  3, 9,  1), (10, 3, 14, 1),
  (11, 3, 18, 1), (12, 3, 13, 1), (13, 3, 15, 1), (14, 3, 17, 1), (15, 3, 10, 1);

-- ── Marks — Mid Term ──────────────────────────────────────────────
INSERT IGNORE INTO marks (student_id, assessment_id, marks_obtained, entered_by) VALUES
  (1,  4, 34, 1), (2,  4, 36, 1), (3,  4, 28, 1), (4,  4, 38, 1), (5,  4, 22, 1),
  (6,  4, 31, 1), (7,  4, 25, 1), (8,  4, 33, 1), (9,  4, 19, 1), (10, 4, 29, 1),
  (11, 4, 35, 1), (12, 4, 27, 1), (13, 4, 32, 1), (14, 4, 37, 1), (15, 4, 24, 1);

-- ── Marks — Practical ─────────────────────────────────────────────
INSERT IGNORE INTO marks (student_id, assessment_id, marks_obtained, entered_by) VALUES
  (1,  5, 18, 1), (2,  5, 17, 1), (3,  5, 14, 1), (4,  5, 20, 1), (5,  5, 11, 1),
  (6,  5, 16, 1), (7,  5, 13, 1), (8,  5, 17, 1), (9,  5, 10, 1), (10, 5, 15, 1),
  (11, 5, 18, 1), (12, 5, 14, 1), (13, 5, 16, 1), (14, 5, 19, 1), (15, 5, 12, 1);

-- ── Attendance — Physics, May 2026 (5 sessions) ───────────────────
INSERT IGNORE INTO attendance (student_id, class_id, subject_id, date, status, teacher_id) VALUES
  -- 19 May
  (1,16,1,'2026-05-19','P',1),(2,16,1,'2026-05-19','P',1),(3,16,1,'2026-05-19','A',1),
  (4,16,1,'2026-05-19','P',1),(5,16,1,'2026-05-19','P',1),(6,16,1,'2026-05-19','P',1),
  (7,16,1,'2026-05-19','A',1),(8,16,1,'2026-05-19','P',1),(9,16,1,'2026-05-19','P',1),
  (10,16,1,'2026-05-19','P',1),(11,16,1,'2026-05-19','P',1),(12,16,1,'2026-05-19','L',1),
  (13,16,1,'2026-05-19','P',1),(14,16,1,'2026-05-19','P',1),(15,16,1,'2026-05-19','P',1),
  -- 20 May
  (1,16,1,'2026-05-20','P',1),(2,16,1,'2026-05-20','P',1),(3,16,1,'2026-05-20','P',1),
  (4,16,1,'2026-05-20','P',1),(5,16,1,'2026-05-20','A',1),(6,16,1,'2026-05-20','P',1),
  (7,16,1,'2026-05-20','P',1),(8,16,1,'2026-05-20','P',1),(9,16,1,'2026-05-20','A',1),
  (10,16,1,'2026-05-20','P',1),(11,16,1,'2026-05-20','P',1),(12,16,1,'2026-05-20','P',1),
  (13,16,1,'2026-05-20','P',1),(14,16,1,'2026-05-20','P',1),(15,16,1,'2026-05-20','P',1),
  -- 21 May
  (1,16,1,'2026-05-21','P',1),(2,16,1,'2026-05-21','P',1),(3,16,1,'2026-05-21','P',1),
  (4,16,1,'2026-05-21','A',1),(5,16,1,'2026-05-21','P',1),(6,16,1,'2026-05-21','P',1),
  (7,16,1,'2026-05-21','P',1),(8,16,1,'2026-05-21','P',1),(9,16,1,'2026-05-21','P',1),
  (10,16,1,'2026-05-21','P',1),(11,16,1,'2026-05-21','L',1),(12,16,1,'2026-05-21','P',1),
  (13,16,1,'2026-05-21','P',1),(14,16,1,'2026-05-21','A',1),(15,16,1,'2026-05-21','P',1),
  -- 22 May
  (1,16,1,'2026-05-22','P',1),(2,16,1,'2026-05-22','P',1),(3,16,1,'2026-05-22','A',1),
  (4,16,1,'2026-05-22','P',1),(5,16,1,'2026-05-22','P',1),(6,16,1,'2026-05-22','P',1),
  (7,16,1,'2026-05-22','A',1),(8,16,1,'2026-05-22','P',1),(9,16,1,'2026-05-22','L',1),
  (10,16,1,'2026-05-22','P',1),(11,16,1,'2026-05-22','P',1),(12,16,1,'2026-05-22','P',1),
  (13,16,1,'2026-05-22','P',1),(14,16,1,'2026-05-22','P',1),(15,16,1,'2026-05-22','P',1),
  -- 23 May
  (1,16,1,'2026-05-23','P',1),(2,16,1,'2026-05-23','P',1),(3,16,1,'2026-05-23','P',1),
  (4,16,1,'2026-05-23','P',1),(5,16,1,'2026-05-23','P',1),(6,16,1,'2026-05-23','A',1),
  (7,16,1,'2026-05-23','P',1),(8,16,1,'2026-05-23','P',1),(9,16,1,'2026-05-23','P',1),
  (10,16,1,'2026-05-23','L',1),(11,16,1,'2026-05-23','P',1),(12,16,1,'2026-05-23','P',1),
  (13,16,1,'2026-05-23','A',1),(14,16,1,'2026-05-23','P',1),(15,16,1,'2026-05-23','P',1);

-- ── Fees — May 2026 ───────────────────────────────────────────────
INSERT IGNORE INTO fees (student_id, month, year, amount, paid, payment_date, payment_mode, recorded_by) VALUES
  (1,  5, 2026, 12000, 1, '2026-05-20', 'cash',   2),
  (2,  5, 2026, 12000, 1, '2026-05-19', 'bank',   2),
  (3,  5, 2026, 12000, 0, NULL, NULL, NULL),
  (4,  5, 2026, 12000, 1, '2026-05-17', 'online', 2),
  (5,  5, 2026, 12000, 0, NULL, NULL, NULL),
  (6,  5, 2026, 12000, 1, '2026-05-15', 'cash',   2),
  (7,  5, 2026, 12000, 0, NULL, NULL, NULL),
  (8,  5, 2026, 12000, 1, '2026-05-12', 'bank',   2),
  (9,  5, 2026, 12000, 0, NULL, NULL, NULL),
  (10, 5, 2026, 12000, 1, '2026-05-10', 'cash',   2),
  (11, 5, 2026, 12000, 0, NULL, NULL, NULL),
  (12, 5, 2026, 12000, 1, '2026-05-08', 'online', 2),
  (13, 5, 2026, 12000, 0, NULL, NULL, NULL),
  (14, 5, 2026, 12000, 1, '2026-05-05', 'cash',   2),
  (15, 5, 2026, 12000, 0, NULL, NULL, NULL);

-- ── Timetable — Class 12-A ────────────────────────────────────────
INSERT IGNORE INTO timetable (class_id, day, period, subject_id, teacher_id, room) VALUES
  (16,'monday',   1,1,1,'Lab-A'),  (16,'monday',   2,3,3,'Rm-201'),(16,'monday',   3,4,4,'Lab-B'),
  (16,'monday',   4,2,2,'Rm-305'), (16,'monday',   5,5,5,'Rm-212'),(16,'monday',   6,6,6,'Lab-C'),
  (16,'tuesday',  1,2,2,'Rm-305'), (16,'tuesday',  2,5,5,'Rm-212'),(16,'tuesday',  3,6,6,'Lab-C'),
  (16,'tuesday',  4,1,1,'Lab-A'),  (16,'tuesday',  5,3,3,'Rm-201'),(16,'tuesday',  6,4,4,'Lab-B'),
  (16,'wednesday',1,3,3,'Rm-201'), (16,'wednesday',2,1,1,'Lab-A'), (16,'wednesday',3,4,4,'Lab-B'),
  (16,'wednesday',4,2,2,'Rm-305'), (16,'wednesday',5,5,5,'Rm-212'),(16,'wednesday',6,6,6,'Lab-C'),
  (16,'thursday', 1,4,4,'Lab-B'),  (16,'thursday', 2,6,6,'Lab-C'), (16,'thursday', 3,2,2,'Rm-305'),
  (16,'thursday', 4,5,5,'Rm-212'), (16,'thursday', 5,1,1,'Lab-A'), (16,'thursday', 6,3,3,'Rm-201'),
  (16,'friday',   1,5,5,'Rm-212'), (16,'friday',   2,2,2,'Rm-305'),(16,'friday',   3,3,3,'Rm-201'),
  (16,'friday',   4,4,4,'Lab-B'),  (16,'friday',   5,6,6,'Lab-C'), (16,'friday',   6,1,1,'Lab-A');

-- ── Portal Notices ────────────────────────────────────────────────
INSERT IGNORE INTO notices (title, body, category, priority, audience, pinned, author_id, expiry_date) VALUES
  ('Final Examination Schedule — June 2026',
   'Term 2 Finals commence 10 June 2026. All students must report 30 minutes early. Roll number slips distributed by class teachers.',
   'exam', 'urgent', 'students,teachers,finance,admin', 1, 1, '2026-06-10'),
  ('Parent-Teacher Meeting — 27 May 2026',
   'PTM scheduled Wednesday 27 May, 09:00 AM–01:00 PM in Main Hall.',
   'general', 'normal', 'students,teachers,finance,admin', 0, 1, '2026-05-27'),
  ('Eid-ul-Adha Holidays — 30 May to 3 June 2026',
   'College closed 30 May–3 June 2026. Resumes 4 June 2026.',
   'holiday', 'normal', 'students,teachers,finance,admin', 1, 1, '2026-06-04'),
  ('Annual Sports Day — 7 June 2026',
   'Register with House In-Charge by 28 May 2026.',
   'sports', 'normal', 'students', 0, 1, '2026-06-07'),
  ('Staff Meeting — 26 May 2026',
   'All teaching staff required to attend monthly staff meeting on Monday 26 May at 02:00 PM.',
   'staff', 'important', 'teachers,admin', 1, 1, '2026-05-26'),
  ('Mid-Term Result Submission Deadline',
   'All subject teachers must submit Mid-Term marks on the portal by 25 May 2026.',
   'academic', 'urgent', 'teachers,admin', 0, 1, '2026-05-25'),
  ('Monthly Fee Collection Report — April 2026',
   'April 2026 fee collection completed with 89% collection rate.',
   'finance', 'important', 'finance,admin', 1, 1, '2026-05-25'),
  ('System Maintenance — 25 May 2026',
   'Portal maintenance from 11:00 PM to 02:00 AM on 25–26 May.',
   'system', 'important', 'admin', 1, 1, '2026-05-26');

-- ── User Permissions (seed all staff with full access) ────────────
INSERT IGNORE INTO user_permissions (user_id, permission, granted)
SELECT u.id, p.perm, 1
FROM users u
JOIN (
  SELECT 'teacher'        AS role, 'marks'              AS perm UNION ALL
  SELECT 'teacher',                'attendance'                 UNION ALL
  SELECT 'teacher',                'timetable'                  UNION ALL
  SELECT 'teacher',                'notices'                    UNION ALL
  SELECT 'teacher',                'warnings'                   UNION ALL
  SELECT 'teacher',                'diary'                      UNION ALL
  SELECT 'teacher',                'complaints'                 UNION ALL
  SELECT 'finance',                'fee_collection'             UNION ALL
  SELECT 'finance',                'fee_monthly'                UNION ALL
  SELECT 'finance',                'fee_records'                UNION ALL
  SELECT 'finance',                'fee_defaulters'             UNION ALL
  SELECT 'finance',                'fee_reports'                UNION ALL
  SELECT 'ilc_vp',                 'ilc_students'               UNION ALL
  SELECT 'ilc_vp',                 'ilc_teachers'               UNION ALL
  SELECT 'ilc_vp',                 'ilc_disabilities'           UNION ALL
  SELECT 'ilc_vp',                 'ilc_admissions'             UNION ALL
  SELECT 'ilc_vp',                 'ilc_records'                UNION ALL
  SELECT 'ilc_vp',                 'ilc_attendance'             UNION ALL
  SELECT 'ilc_vp',                 'ilc_results'                UNION ALL
  SELECT 'ilc_vp',                 'ilc_timetable'              UNION ALL
  SELECT 'ilc_vp',                 'ilc_viewas'                 UNION ALL
  SELECT 'student_affairs',        'sa_students'                UNION ALL
  SELECT 'student_affairs',        'sa_admissions'              UNION ALL
  SELECT 'student_affairs',        'sa_medical'                 UNION ALL
  SELECT 'student_affairs',        'sa_calendar'                UNION ALL
  SELECT 'vp_main',                'vp_teachers'                UNION ALL
  SELECT 'vp_main',                'vp_students'                UNION ALL
  SELECT 'vp_main',                'vp_attendance'              UNION ALL
  SELECT 'vp_main',                'vp_att_requests'            UNION ALL
  SELECT 'vp_main',                'vp_results'                 UNION ALL
  SELECT 'vp_main',                'vp_timetable'               UNION ALL
  SELECT 'vp_main',                'vp_calendar'                UNION ALL
  SELECT 'vp_main',                'vp_viewas'                  UNION ALL
  SELECT 'wing_head',              'wh_students'                UNION ALL
  SELECT 'wing_head',              'wh_classes'
) p ON u.role = p.role
WHERE u.role NOT IN ('admin','student');

-- ============================================================
--  WEBSITE SEED DATA
-- ============================================================

-- Site admin (password: Admin@2025)
INSERT IGNORE INTO site_admins (name, email, password, role) VALUES
  ('Super Admin', 'admin@bmc.edu.pk',
   '$2y$12$Zx/u/KsXlhUA.Hj5r.N8eONDsSETfIb57BA6UrkSlnk4fczeRg4.O', 'super_admin');

-- Site settings
INSERT IGNORE INTO site_settings (`key`, `value`, `label`) VALUES
  ('site_name',          'Bahria Model College Bin Qasim', 'College Name'),
  ('site_tagline',       'Shaping Leaders of Tomorrow',    'Tagline'),
  ('site_address',       'Bin Qasim, Karachi, Pakistan',   'Address'),
  ('site_phone',         '+92 21 XXXX XXXX',               'Phone'),
  ('site_email',         'info@bmc.edu.pk',                'Email'),
  ('site_facebook',      '#',                              'Facebook URL'),
  ('site_twitter',       '#',                              'Twitter URL'),
  ('site_instagram',     '#',                              'Instagram URL'),
  ('site_youtube',       '#',                              'YouTube URL'),
  ('site_map_embed',     '',                               'Google Maps Embed URL'),
  ('principal_name',     'Lt. Cdr. Abu Bakar',             'Principal Name'),
  ('principal_designation','Lieutenant Commander, Pakistan Navy / PN Parachutist', 'Principal Designation'),
  ('principal_message',
   'Welcome to Bahria Model College Bin Qasim — an institution dedicated to academic excellence, moral character, and the holistic development of every student. Our commitment is to nurture curious minds, build strong leaders, and shape responsible citizens who contribute meaningfully to society and the nation.',
   'Principal Message'),
  ('principal_image',    '',                               'Principal Image'),
  ('about_short',
   'Established under the Bahria Foundation, Bahria Model College Bin Qasim is a premier educational institution committed to delivering world-class education. We combine rigorous academics with character development to produce well-rounded graduates.',
   'About (Short)'),
  ('vision',
   'To be a center of academic excellence that cultivates innovative thinkers, compassionate leaders, and responsible global citizens.',
   'Vision'),
  ('mission',
   'To provide quality education through dedicated faculty, modern facilities, and a nurturing environment that fosters intellectual growth, ethical values, and lifelong learning.',
   'Mission'),
  ('footer_text',
   'Bahria Model College Bin Qasim is committed to academic excellence and the holistic development of students.',
   'Footer Text'),
  ('whatsapp',           '',   'WhatsApp Number'),
  ('admission_open',     '1',  'Admissions Open (1=Yes, 0=No)'),
  ('admission_year',     '2025-26', 'Admission Year');

-- Stats
INSERT IGNORE INTO site_stats (label, value, icon, suffix, sort_order) VALUES
  ('Students Enrolled',   4500, 'fa-user-graduate',       '+', 1),
  ('Expert Faculty',       185, 'fa-chalkboard-teacher',  '+', 2),
  ('Years of Excellence',   30, 'fa-award',               '+', 3),
  ('Programs Offered',      24, 'fa-book-open',           '+', 4);

-- Departments
INSERT IGNORE INTO site_departments (name, slug, description, icon, sort_order) VALUES
  ('Science & Technology',     'science-technology',     'Cutting-edge science programs including Physics, Chemistry, Biology, and Computer Science.', 'fa-flask',         1),
  ('Arts & Humanities',        'arts-humanities',        'A diverse range of arts and humanities programs fostering creativity and critical thinking.',  'fa-palette',       2),
  ('Commerce & Management',    'commerce-management',    'Business and commerce programs designed to build the next generation of entrepreneurs.',       'fa-chart-line',    3),
  ('Medical Sciences',         'medical-sciences',       'Pre-medical programs preparing students for careers in healthcare and medicine.',               'fa-heartbeat',     4),
  ('Engineering Preparatory',  'engineering-preparatory','Foundational engineering programs bridging school and university-level study.',                  'fa-cogs',          5),
  ('ILC (Inclusive Learning)', 'ilc',                   'Inclusive Learning Center supporting students with diverse learning needs.',                    'fa-hands-helping', 6);

-- Testimonials
INSERT IGNORE INTO site_testimonials (name, designation, content, rating) VALUES
  ('Ahmed Khan',      'Alumni, Class of 2022',          'BMC gave me the foundation to excel at university. The faculty are dedicated and the environment is truly motivating.', 5),
  ('Sara Iqbal',      'Current Student, FSc Pre-Medical','The labs, library, and support from teachers here are exceptional. I am proud to be a BMC student.',                  5),
  ('Muhammad Ali',    'Parent',                          'My daughter has grown tremendously as a student and as a person since joining BMC.',                                   5),
  ('Fatima Siddiqui', 'Alumni, Class of 2020',          'The values and work ethic I developed at BMC have shaped my professional life.',                                       5);

-- Events
INSERT IGNORE INTO site_events (title, slug, description, event_date, event_time, venue, is_published, is_featured) VALUES
  ('Annual Prize Distribution Ceremony',
   'annual-prize-distribution-ceremony-2026',
   'Annual celebration recognising academic excellence and co-curricular achievements across all classes.',
   '2026-09-15', '09:00:00', 'Main Auditorium', 1, 1),
  ('Inter-House Sports Week',
   'inter-house-sports-week-2026',
   'A week-long inter-house sports competition featuring athletics, cricket, football, and indoor games.',
   '2026-09-20', '08:00:00', 'Sports Ground', 1, 0),
  ('Science & Technology Fair',
   'science-technology-fair-2026',
   'Students showcase innovative science projects and technology demonstrations open to all classes.',
   '2026-10-05', '10:00:00', 'Science Block', 1, 0),
  ('Parent-Teacher Meeting',
   'parent-teacher-meeting-2026',
   'Mid-term parent-teacher meeting to discuss student progress, attendance, and academic performance.',
   '2026-10-12', '09:00:00', 'Main Hall', 1, 0);

-- ============================================================
--  SUMMARY
-- ============================================================
-- Portal login credentials (use email OR numeric user_id to log in):
--
--   Email                  Password     user_id  Role
--   admin@bmc.edu.pk       admin123     1001     Admin
--   finance@bmc.edu.pk     finance123   1002     Finance
--   amna.ilc@bmc.edu.pk    Admin@2025   1003     ILC VP
--   tariq.sa@bmc.edu.pk    Admin@2025   1004     Student Affairs
--   asad.vp@bmc.edu.pk     Admin@2025   1005     VP Main
--   rubina.wh@bmc.edu.pk   Admin@2025   1006     Wing Head
--   sarah@bmc.edu.pk       teacher123   2001     Teacher (Physics)
--   hasan@bmc.edu.pk       teacher123   2002     Teacher (Mathematics)
--   nadia@bmc.edu.pk       teacher123   2003     Teacher (English)
--   amina@bmc.edu.pk       teacher123   2004     Teacher (Chemistry)
--   imran@bmc.edu.pk       teacher123   2005     Teacher (Biology)
--   farhan@bmc.edu.pk      teacher123   2006     Teacher (CS)
--   ahmed@bmc.edu.pk       student123   3001     Student (12-A)
--   fatima@bmc.edu.pk      student123   3002     Student (12-A)
--   hassan@bmc.edu.pk      student123   3003     Student (12-A)
--   ... (3004–3015 follow same pattern)
--
-- Website admin:
--   admin@bmc.edu.pk / Admin@2025
-- ============================================================
