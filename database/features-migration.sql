-- ===================================================================
-- BMC Portal — Features Migration
-- Run this in phpMyAdmin (SQL tab) or via CLI: mysql -u root bmc_portal < features-migration.sql
-- ===================================================================

USE bmc_portal;

-- ── 1. Student Complaints ─────────────────────────────────────────
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

-- ── 2. Daily Diary ────────────────────────────────────────────────
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

CREATE TABLE IF NOT EXISTS diary_media (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    diary_id   INT NOT NULL,
    filename   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (diary_id) REFERENCES daily_diary(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 3. Academic Calendars ─────────────────────────────────────────
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

-- ── 4. Attendance Edit Requests ───────────────────────────────────
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

-- ── 5. Seed new permissions for existing staff ───────────────────
-- New teacher permissions: diary, complaints
INSERT IGNORE INTO user_permissions (user_id, permission, granted)
SELECT u.id, perm.p, 1
FROM users u
CROSS JOIN (SELECT 'diary' AS p UNION SELECT 'complaints') perm
WHERE u.role = 'teacher' AND u.status = 'active';

-- New VP permissions: vp_calendar, vp_att_requests
INSERT IGNORE INTO user_permissions (user_id, permission, granted)
SELECT u.id, perm.p, 1
FROM users u
CROSS JOIN (SELECT 'vp_calendar' AS p UNION SELECT 'vp_att_requests') perm
WHERE u.role = 'vp_main' AND u.status = 'active';

-- New SA permissions: sa_calendar
INSERT IGNORE INTO user_permissions (user_id, permission, granted)
SELECT u.id, 'sa_calendar', 1
FROM users u
WHERE u.role = 'student_affairs' AND u.status = 'active';
