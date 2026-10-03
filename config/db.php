<?php
// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'bmc_portal');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo      = null;
    static $migrated = false;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    // Auto-migrate missing schema columns (runs once per process after first connection).
    // Each migration group is isolated so one failure never blocks the others.
    if (!$migrated) {
        $migrated = true;

        // Group 1: classes wing columns
        try {
            $classCols = array_flip(
                $pdo->query("SHOW COLUMNS FROM classes")->fetchAll(PDO::FETCH_COLUMN)
            );
            if (!isset($classCols['is_ilc']))
                $pdo->exec("ALTER TABLE classes ADD COLUMN is_ilc TINYINT(1) NOT NULL DEFAULT 0");
            if (!isset($classCols['is_montessori']))
                $pdo->exec("ALTER TABLE classes ADD COLUMN is_montessori TINYINT(1) NOT NULL DEFAULT 0");
        } catch (Exception $e) {}

        // Group 2: teachers is_ilc column
        try {
            $tchCols = array_flip(
                $pdo->query("SHOW COLUMNS FROM teachers")->fetchAll(PDO::FETCH_COLUMN)
            );
            if (!isset($tchCols['is_ilc']))
                $pdo->exec("ALTER TABLE teachers ADD COLUMN is_ilc TINYINT(1) NOT NULL DEFAULT 0");
        } catch (Exception $e) {}

        // Group 3: students columns (soft-delete + category)
        try {
            $stuCols = array_flip(
                $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN)
            );
            if (!isset($stuCols['deleted_at']))
                $pdo->exec("ALTER TABLE students ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
            if (!isset($stuCols['category']))
                $pdo->exec("ALTER TABLE students ADD COLUMN category VARCHAR(50) NULL DEFAULT NULL");
        } catch (Exception $e) {}

        // Group 4: exam date sheet tables — isolated so earlier failures never skip this
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS exam_date_sheets (
                id            INT PRIMARY KEY AUTO_INCREMENT,
                title         VARCHAR(200)  NOT NULL,
                term          VARCHAR(100)  NOT NULL DEFAULT 'General',
                wing          ENUM('main','montessori','ilc','all') NOT NULL DEFAULT 'all',
                academic_year VARCHAR(20)   NOT NULL,
                status        ENUM('draft','published') NOT NULL DEFAULT 'draft',
                notes         TEXT DEFAULT NULL,
                created_by    INT NOT NULL,
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");

            // Add term column if the table already existed without it
            $dsCols = array_flip(
                $pdo->query("SHOW COLUMNS FROM exam_date_sheets")->fetchAll(PDO::FETCH_COLUMN)
            );
            if (!isset($dsCols['term']))
                $pdo->exec("ALTER TABLE exam_date_sheets ADD COLUMN term VARCHAR(100) NOT NULL DEFAULT 'General' AFTER wing");

            $pdo->exec("CREATE TABLE IF NOT EXISTS exam_date_sheet_entries (
                id              INT PRIMARY KEY AUTO_INCREMENT,
                date_sheet_id   INT NOT NULL,
                class_id        INT DEFAULT NULL,
                subject         VARCHAR(150) NOT NULL,
                exam_date       DATE NOT NULL,
                start_time      TIME NOT NULL,
                end_time        TIME NOT NULL,
                venue           VARCHAR(100) DEFAULT NULL,
                notes           VARCHAR(255) DEFAULT NULL,
                sort_order      INT NOT NULL DEFAULT 0,
                FOREIGN KEY (date_sheet_id) REFERENCES exam_date_sheets(id) ON DELETE CASCADE,
                FOREIGN KEY (class_id)      REFERENCES classes(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}

        // Group 5: extend users role ENUM
        try {
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role
                ENUM('student','teacher','admin','finance','ilc_vp','student_affairs','vp_main','wing_head','montessori_teacher','ilc_teacher','examination_head')
                NOT NULL");
        } catch (Exception $e) {}

        // Group 6: student categories lookup table + seed data
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS student_categories (
                id         INT          PRIMARY KEY AUTO_INCREMENT,
                name       VARCHAR(50)  NOT NULL,
                sort_order INT          NOT NULL DEFAULT 0,
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cat_name (name)
            ) ENGINE=InnoDB");
            // Seed predefined categories one family at a time to stay within max_allowed_packet
            $seedFamilies = [
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('AOG I',10),('AOG II',11),('AOG III',12),('AOG IV',13)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('AOB I',20),('AOB II',21),('AOB III',22),('AOB IV',23)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('ASB I',30),('ASB II',31),('ASB III',32),('ASB IV',33)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('ASG I',40),('ASG II',41),('ASG III',42),('ASG IV',43)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('NOB I',50),('NOB II',51),('NOB III',52),('NOB IV',53)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('NOG I',60),('NOG II',61),('NOG III',62),('NOG IV',63)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('NSB I',70),('NSB II',71),('NSB III',72),('NSB IV',73)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('NSG I',80),('NSG II',81),('NSG III',82),('NSG IV',83)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('NCB I',90),('NCB II',91),('NCB III',92),('NCB IV',93)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('NCG I',100),('NCG II',101),('NCG III',102),('NCG IV',103)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('POG I',110),('POG II',111),('POG III',112),('POG IV',113)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('POB I',120),('POB II',121),('POB III',122),('POB IV',123)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('PSB I',130),('PSB II',131),('PSB III',132),('PSB IV',133)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('PSG I',140),('PSG II',141),('PSG III',142),('PSG IV',143)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('FAC G I',150),('FAC G II',151),('FAC G III',152),('FAC G IV',153)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('FAC B I',160),('FAC B II',161),('FAC B III',162),('FAC B IV',163)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('CB I',170),('CB II',171),('CB III',172),('CB IV',173)",
                "INSERT IGNORE INTO student_categories (name,sort_order) VALUES ('CG I',180),('CG II',181),('CG III',182),('CG IV',183)",
            ];
            foreach ($seedFamilies as $sql) { $pdo->exec($sql); }
        } catch (Exception $e) {}

        // Group 7: document upload tables for timetable and exam date sheets
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS timetable_documents (
                id                INT          PRIMARY KEY AUTO_INCREMENT,
                title             VARCHAR(200) NOT NULL,
                wing              ENUM('main','montessori','ilc','all') NOT NULL DEFAULT 'all',
                academic_year     VARCHAR(20)  NOT NULL,
                notes             TEXT         DEFAULT NULL,
                original_filename VARCHAR(255) NOT NULL,
                stored_filename   VARCHAR(255) NOT NULL,
                file_type         VARCHAR(50)  NOT NULL,
                file_size         INT          NOT NULL DEFAULT 0,
                uploaded_by       INT          NOT NULL,
                status            ENUM('active','archived') NOT NULL DEFAULT 'active',
                created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");

            // Add status column if the table already existed without it
            $ttCols = array_flip(
                $pdo->query("SHOW COLUMNS FROM timetable_documents")->fetchAll(PDO::FETCH_COLUMN)
            );
            if (!isset($ttCols['status']))
                $pdo->exec("ALTER TABLE timetable_documents ADD COLUMN status ENUM('active','archived') NOT NULL DEFAULT 'active'");

            // Add file columns to exam_date_sheets if missing
            $dsCols2 = array_flip(
                $pdo->query("SHOW COLUMNS FROM exam_date_sheets")->fetchAll(PDO::FETCH_COLUMN)
            );
            if (!isset($dsCols2['original_filename']))
                $pdo->exec("ALTER TABLE exam_date_sheets ADD COLUMN original_filename VARCHAR(255) NULL DEFAULT NULL");
            if (!isset($dsCols2['stored_filename']))
                $pdo->exec("ALTER TABLE exam_date_sheets ADD COLUMN stored_filename VARCHAR(255) NULL DEFAULT NULL");
            if (!isset($dsCols2['file_type']))
                $pdo->exec("ALTER TABLE exam_date_sheets ADD COLUMN file_type VARCHAR(50) NULL DEFAULT NULL");
            if (!isset($dsCols2['file_size']))
                $pdo->exec("ALTER TABLE exam_date_sheets ADD COLUMN file_size INT NULL DEFAULT NULL");
        } catch (Exception $e) {}

        // Group 8: change assessments.type from ENUM to VARCHAR(100) for free-text input
        try {
            $asmCols = $pdo->query("SHOW COLUMNS FROM assessments LIKE 'type'")->fetch();
            if ($asmCols && stripos($asmCols['Type'], 'enum') !== false) {
                $pdo->exec("ALTER TABLE assessments MODIFY COLUMN type VARCHAR(100) NOT NULL DEFAULT 'Quiz'");
            }
        } catch (Exception $e) {}

        // Group 10: syllabus documents table (uploaded by examination_head)
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS syllabus_documents (
                id                INT          PRIMARY KEY AUTO_INCREMENT,
                title             VARCHAR(200) NOT NULL,
                class_id          INT          NULL,
                academic_year     VARCHAR(20)  NOT NULL,
                notes             TEXT         DEFAULT NULL,
                original_filename VARCHAR(255) NOT NULL,
                stored_filename   VARCHAR(255) NOT NULL,
                file_type         VARCHAR(50)  NOT NULL,
                file_size         INT          NOT NULL DEFAULT 0,
                uploaded_by       INT          NOT NULL,
                created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (class_id)    REFERENCES classes(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}

        // Group 9: marks entry permission & approval workflow
        try {
            // Ensure teachers.wing column exists (added by wing-migration.sql; add defensively here)
            $tchCols9 = array_flip($pdo->query("SHOW COLUMNS FROM teachers")->fetchAll(PDO::FETCH_COLUMN));
            if (!isset($tchCols9['wing'])) {
                $pdo->exec("ALTER TABLE teachers ADD COLUMN wing ENUM('main','montessori','ilc') NOT NULL DEFAULT 'main'");
                $pdo->exec("UPDATE teachers SET wing='ilc' WHERE COALESCE(is_ilc,0)=1");
            }

            // Marks permission requests table
            $pdo->exec("CREATE TABLE IF NOT EXISTS marks_permission_requests (
                id               INT          PRIMARY KEY AUTO_INCREMENT,
                teacher_id       INT          NOT NULL,
                assessment_id    INT          NOT NULL,
                request_reason   TEXT         NULL,
                status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                reviewed_by      INT          NULL,
                reviewed_at      TIMESTAMP    NULL,
                rejection_reason TEXT         NULL,
                academic_year    VARCHAR(20)  NOT NULL DEFAULT '',
                created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_id)    REFERENCES teachers(id)   ON DELETE CASCADE,
                FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
                FOREIGN KEY (reviewed_by)   REFERENCES users(id)      ON DELETE SET NULL,
                UNIQUE KEY uq_mpr (teacher_id, assessment_id)
            ) ENGINE=InnoDB");

            // Portal notifications table
            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                id         INT          PRIMARY KEY AUTO_INCREMENT,
                user_id    INT          NOT NULL,
                type       VARCHAR(60)  NOT NULL,
                message    TEXT         NOT NULL,
                ref_id     INT          NULL,
                ref_type   VARCHAR(50)  NULL,
                is_read    TINYINT(1)   NOT NULL DEFAULT 0,
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_notif_user (user_id, is_read)
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}

        // Group 12: Montessori daily assessment tables
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS montessori_daily_assessments (
                id              INT          PRIMARY KEY AUTO_INCREMENT,
                class_id        INT          NOT NULL,
                subject_id      INT          NOT NULL,
                topic           VARCHAR(200) DEFAULT NULL,
                assessment_date DATE         NOT NULL,
                criteria        TEXT         NOT NULL,
                teacher_id      INT          NOT NULL,
                created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_mda (class_id, subject_id, assessment_date),
                FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
                FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE IF NOT EXISTS montessori_daily_assessment_entries (
                id            INT          PRIMARY KEY AUTO_INCREMENT,
                assessment_id INT          NOT NULL,
                student_id    INT          NOT NULL,
                ratings       TEXT         NOT NULL DEFAULT '{}',
                overall       VARCHAR(10)  DEFAULT NULL,
                remarks       VARCHAR(500) DEFAULT NULL,
                UNIQUE KEY uq_mdae (assessment_id, student_id),
                FOREIGN KEY (assessment_id) REFERENCES montessori_daily_assessments(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id)    REFERENCES students(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}

        // Group 11: admission request file attachments
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS admission_request_attachments (
                id                INT          PRIMARY KEY AUTO_INCREMENT,
                request_id        INT          NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                stored_filename   VARCHAR(255) NOT NULL,
                file_type         VARCHAR(50)  NOT NULL,
                file_size         INT          NOT NULL DEFAULT 0,
                uploaded_by       INT          NOT NULL,
                created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (request_id)  REFERENCES admission_requests(id) ON DELETE CASCADE,
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_ara_request (request_id)
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}

        // Group 13: per-student montessori assessment support
        try {
            $pdo->exec("ALTER TABLE montessori_daily_assessments ADD COLUMN student_id INT NULL AFTER class_id");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE montessori_daily_assessments ADD CONSTRAINT fk_mda_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE montessori_daily_assessments DROP INDEX uq_mda");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE montessori_daily_assessments ADD UNIQUE KEY uq_mda_student (student_id, subject_id, assessment_date)");
        } catch (Exception $e) {}

        // Group 15: Allow multiple formative assessments per student+subject (drop date-level uniqueness)
        try {
            $pdo->exec("ALTER TABLE montessori_daily_assessments DROP INDEX uq_mda_student");
        } catch (Exception $e) {}

        // Group 14: Montessori anecdotal records table
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS montessori_anecdotal_records (
                id            INT          PRIMARY KEY AUTO_INCREMENT,
                class_id      INT          NOT NULL,
                student_id    INT          NOT NULL,
                teacher_id    INT          NOT NULL,
                subject_focus VARCHAR(200) NOT NULL DEFAULT 'General Observation',
                record_date   DATE         NOT NULL,
                topic         VARCHAR(300) DEFAULT NULL,
                observation   TEXT         NOT NULL,
                created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_mar_student (student_id, record_date),
                INDEX idx_mar_class (class_id),
                FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}

        // Group 16: progress_reports table
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS progress_reports (
                id          INT          PRIMARY KEY AUTO_INCREMENT,
                student_id  INT          NOT NULL,
                term        VARCHAR(50)  NOT NULL DEFAULT 'Final',
                session     VARCHAR(50)  NOT NULL DEFAULT '',
                form_data   TEXT         NOT NULL,
                reported_by INT          NOT NULL,
                created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_pr_student (student_id),
                FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (reported_by) REFERENCES users(id)    ON DELETE CASCADE
            ) ENGINE=InnoDB");
            $prCols = array_flip($pdo->query("SHOW COLUMNS FROM progress_reports")->fetchAll(PDO::FETCH_COLUMN));
            if (!isset($prCols['updated_at']))
                $pdo->exec("ALTER TABLE progress_reports ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        } catch (Exception $e) {}

        // Group 17: student_complaints, daily_diary, diary_media, academic_calendars, attendance_edit_requests
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS student_complaints (
                id               INT          PRIMARY KEY AUTO_INCREMENT,
                student_id       INT          NOT NULL,
                teacher_id       INT          NOT NULL,
                subject          VARCHAR(255) NOT NULL,
                message          TEXT         NOT NULL,
                teacher_response TEXT         DEFAULT NULL,
                status           ENUM('pending','reviewed','resolved') NOT NULL DEFAULT 'pending',
                responded_at     DATETIME     DEFAULT NULL,
                created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (teacher_id) REFERENCES users(id)    ON DELETE CASCADE
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS daily_diary (
                id         INT          PRIMARY KEY AUTO_INCREMENT,
                teacher_id INT          NOT NULL,
                class_id   INT          NOT NULL,
                date       DATE         NOT NULL,
                title      VARCHAR(255) NOT NULL,
                content    TEXT         NOT NULL,
                homework   TEXT         DEFAULT NULL,
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
                FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE
            ) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE IF NOT EXISTS diary_media (
                id         INT          PRIMARY KEY AUTO_INCREMENT,
                diary_id   INT          NOT NULL,
                filename   VARCHAR(255) NOT NULL,
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (diary_id) REFERENCES daily_diary(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS academic_calendars (
                id          INT          PRIMARY KEY AUTO_INCREMENT,
                title       VARCHAR(255) NOT NULL,
                description TEXT         DEFAULT NULL,
                filename    VARCHAR(255) NOT NULL,
                year        YEAR         NOT NULL DEFAULT (YEAR(CURDATE())),
                uploaded_by INT          DEFAULT NULL,
                created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_edit_requests (
                id          INT          PRIMARY KEY AUTO_INCREMENT,
                teacher_id  INT          NOT NULL,
                student_id  INT          NOT NULL,
                class_id    INT          NOT NULL,
                subject_id  INT          NOT NULL,
                date        DATE         NOT NULL,
                old_status  ENUM('P','A','L') NOT NULL,
                new_status  ENUM('P','A','L') NOT NULL,
                reason      TEXT         NOT NULL,
                status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                reviewed_by INT          DEFAULT NULL,
                reviewed_at DATETIME     DEFAULT NULL,
                created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_id)  REFERENCES teachers(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (class_id)    REFERENCES classes(id)  ON DELETE CASCADE,
                FOREIGN KEY (subject_id)  REFERENCES subjects(id) ON DELETE CASCADE,
                FOREIGN KEY (reviewed_by) REFERENCES users(id)    ON DELETE SET NULL
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}

        // Group 18: admission_requests (required by Group 11 FK) + retry admission_request_attachments
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS admission_requests (
                id               INT          PRIMARY KEY AUTO_INCREMENT,
                student_name     VARCHAR(150) NOT NULL,
                parent_name      VARCHAR(150) DEFAULT NULL,
                parent_phone     VARCHAR(20)  DEFAULT NULL,
                dob              DATE         DEFAULT NULL,
                requested_class  VARCHAR(50)  DEFAULT NULL,
                disability_notes TEXT         DEFAULT NULL,
                status           ENUM('pending','reviewed','approved','rejected') NOT NULL DEFAULT 'pending',
                requested_by     INT          NOT NULL,
                reviewed_by      INT          DEFAULT NULL,
                review_notes     TEXT         DEFAULT NULL,
                created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reviewed_at      TIMESTAMP    DEFAULT NULL,
                FOREIGN KEY (requested_by) REFERENCES users(id),
                FOREIGN KEY (reviewed_by)  REFERENCES users(id)
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}
        try {
            // Retry: Group 11 silently failed if admission_requests did not exist at that point
            $pdo->exec("CREATE TABLE IF NOT EXISTS admission_request_attachments (
                id                INT          PRIMARY KEY AUTO_INCREMENT,
                request_id        INT          NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                stored_filename   VARCHAR(255) NOT NULL,
                file_type         VARCHAR(50)  NOT NULL,
                file_size         INT          NOT NULL DEFAULT 0,
                uploaded_by       INT          NOT NULL,
                created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (request_id)  REFERENCES admission_requests(id) ON DELETE CASCADE,
                FOREIGN KEY (uploaded_by) REFERENCES users(id)              ON DELETE CASCADE,
                INDEX idx_ara_request (request_id)
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}

        // Group 19: student_warnings table
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS student_warnings (
                id         INT          PRIMARY KEY AUTO_INCREMENT,
                student_id INT          NOT NULL,
                given_by   INT          NOT NULL,
                reason     TEXT         NOT NULL,
                severity   ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (given_by)   REFERENCES users(id)    ON DELETE CASCADE
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}

        // Group 20: ILC disability & session tables
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS disability_categories (
                id   INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL
            ) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE IF NOT EXISTS disability_subtypes (
                id          INT PRIMARY KEY AUTO_INCREMENT,
                category_id INT NOT NULL,
                name        VARCHAR(150) NOT NULL,
                FOREIGN KEY (category_id) REFERENCES disability_categories(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE IF NOT EXISTS student_disabilities (
                id          INT PRIMARY KEY AUTO_INCREMENT,
                student_id  INT NOT NULL,
                subtype_id  INT NOT NULL,
                notes       TEXT NULL,
                recorded_by INT NOT NULL,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_student_subtype (student_id, subtype_id),
                FOREIGN KEY (student_id)  REFERENCES students(id)          ON DELETE CASCADE,
                FOREIGN KEY (subtype_id)  REFERENCES disability_subtypes(id),
                FOREIGN KEY (recorded_by) REFERENCES users(id)
            ) ENGINE=InnoDB");
            // Seed disability categories
            $pdo->exec("INSERT IGNORE INTO disability_categories (id, name) VALUES
                (1,'Learning Disability'),(2,'Blind & VI (Visual Impairment)'),
                (3,'HI (Hearing Impaired)'),(4,'Down Syndrome'),
                (5,'Autistic Disorders'),(6,'ADHD')");
            // Seed disability subtypes
            $pdo->exec("INSERT IGNORE INTO disability_subtypes (id, category_id, name) VALUES
                (1,1,'Dyslexia'),(2,1,'Dysgraphia'),(3,1,'Dyscalculia'),
                (4,1,'NVLD (Nonverbal Learning Disability)'),(5,1,'Auditory Processing Disorder'),
                (6,1,'Visual Processing Disorder'),
                (7,2,'Low vision'),(8,2,'Partial sight'),(9,2,'Color blindness'),(10,2,'Night blindness'),
                (11,3,'Conductive hearing loss'),(12,3,'Mixed hearing loss'),
                (13,3,'Bilateral hearing loss'),(14,3,'Sensorineural hearing loss'),
                (15,4,'Trisomy 21'),(16,4,'Translocation Down Syndrome'),
                (17,4,'Mosaic Down Syndrome'),(18,4,'Partial Trisomy 21'),
                (19,5,'Asperger''s Syndrome'),(20,5,'Level 1 ASD'),(21,5,'Level 2 ASD'),
                (22,5,'Level 3 ASD'),(23,5,'Rett Syndrome'),
                (24,6,'Inattentive Type'),(25,6,'Hyperactive-Impulsive Type'),(26,6,'Combined Type')");
        } catch (Exception $e) {}
        try {
            // ilc_session_records base table
            $pdo->exec("CREATE TABLE IF NOT EXISTS ilc_session_records (
                id          INT PRIMARY KEY AUTO_INCREMENT,
                title       VARCHAR(255) NOT NULL,
                description TEXT,
                filename    VARCHAR(255) NOT NULL,
                file_type   ENUM('pdf','video') NOT NULL DEFAULT 'pdf',
                uploaded_by INT,
                expires_at  DATETIME NULL,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
            // Backfill extra columns added by ilc_session_records_student.sql
            $isrCols = array_flip($pdo->query("SHOW COLUMNS FROM ilc_session_records")->fetchAll(PDO::FETCH_COLUMN));
            if (!isset($isrCols['student_id']))
                $pdo->exec("ALTER TABLE ilc_session_records ADD COLUMN student_id INT NULL AFTER id");
            if (!isset($isrCols['session_date']))
                $pdo->exec("ALTER TABLE ilc_session_records ADD COLUMN session_date DATE NULL AFTER description");
            if (!isset($isrCols['session_type']))
                $pdo->exec("ALTER TABLE ilc_session_records ADD COLUMN session_type VARCHAR(100) NULL AFTER session_date");
            // Make expires_at nullable if it was created NOT NULL on an existing install
            $pdo->exec("ALTER TABLE ilc_session_records MODIFY COLUMN expires_at DATETIME NULL");
            // Add FK + index if not already present
            try {
                $pdo->exec("ALTER TABLE ilc_session_records ADD KEY idx_isr_student (student_id)");
            } catch (Exception $e2) {}
            try {
                $pdo->exec("ALTER TABLE ilc_session_records ADD CONSTRAINT fk_isr_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE");
            } catch (Exception $e2) {}
        } catch (Exception $e) {}

        // Group 21: ILC assessment & results tables
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ilc_student_assessments (
                id             INT           PRIMARY KEY AUTO_INCREMENT,
                student_id     INT           NOT NULL,
                title          VARCHAR(100)  NOT NULL,
                type           VARCHAR(50)   NOT NULL DEFAULT 'Quiz',
                max_marks      DECIMAL(6,2)  NOT NULL DEFAULT 100,
                weight         DECIMAL(5,2)  NOT NULL DEFAULT 0,
                marks_obtained DECIMAL(6,2)  NULL,
                date           DATE          NULL,
                remarks        VARCHAR(255)  NULL,
                created_by     INT           NOT NULL,
                created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_isa_student (student_id),
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id)
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS fba_plans (
                id           INT          PRIMARY KEY AUTO_INCREMENT,
                student_id   INT          NOT NULL,
                session_no   VARCHAR(30)  NULL,
                session_date DATE         NULL,
                review_date  DATE         NULL,
                form_data    TEXT         NOT NULL,
                recorded_by  INT          NOT NULL,
                created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_fba_student (student_id),
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (recorded_by) REFERENCES users(id)
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ilc_academic_results (
                id          INT          PRIMARY KEY AUTO_INCREMENT,
                student_id  INT          NOT NULL,
                term        VARCHAR(100) NOT NULL DEFAULT 'Final Term',
                session     VARCHAR(50)  NOT NULL DEFAULT '',
                form_data   TEXT         NOT NULL,
                recorded_by INT          NOT NULL,
                created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_iar_student (student_id),
                FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (recorded_by) REFERENCES users(id)
            ) ENGINE=InnoDB");
            $pdo->exec("CREATE TABLE IF NOT EXISTS ilc_academic_results2 (
                id          INT          PRIMARY KEY AUTO_INCREMENT,
                student_id  INT          NOT NULL,
                term        VARCHAR(100) NOT NULL DEFAULT 'First Term',
                session     VARCHAR(50)  NOT NULL DEFAULT '',
                form_data   TEXT         NOT NULL,
                recorded_by INT          NOT NULL,
                created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_iar2_student (student_id),
                FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (recorded_by) REFERENCES users(id)
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}

        // Group 22: ILC therapy tables
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS behaviour_therapy_reports (
                id               INT       PRIMARY KEY AUTO_INCREMENT,
                student_id       INT       NOT NULL,
                month            DATE      NOT NULL,
                therapist_notes  TEXT      NULL,
                progress_summary TEXT      NULL,
                goals_next_month TEXT      NULL,
                recorded_by      INT       NOT NULL,
                created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_btr_student_month (student_id, month),
                KEY idx_btr_student (student_id),
                FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (recorded_by) REFERENCES users(id)
            ) ENGINE=InnoDB");
            // Backfill aba_data column (added by aba_therapy.sql migration)
            $btrCols = array_flip($pdo->query("SHOW COLUMNS FROM behaviour_therapy_reports")->fetchAll(PDO::FETCH_COLUMN));
            if (!isset($btrCols['aba_data']))
                $pdo->exec("ALTER TABLE behaviour_therapy_reports ADD COLUMN aba_data TEXT NULL AFTER goals_next_month");
        } catch (Exception $e) {}
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS speech_therapy_reports (
                id               INT       PRIMARY KEY AUTO_INCREMENT,
                student_id       INT       NOT NULL,
                month            DATE      NOT NULL,
                therapist_notes  TEXT      NULL,
                progress_summary TEXT      NULL,
                goals_next_month TEXT      NULL,
                recorded_by      INT       NOT NULL,
                created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_str_student_month (student_id, month),
                KEY idx_str_student (student_id),
                FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (recorded_by) REFERENCES users(id)
            ) ENGINE=InnoDB");
            // Backfill assessment_data column (added by speech_therapy_assessment.sql migration)
            $strCols = array_flip($pdo->query("SHOW COLUMNS FROM speech_therapy_reports")->fetchAll(PDO::FETCH_COLUMN));
            if (!isset($strCols['assessment_data']))
                $pdo->exec("ALTER TABLE speech_therapy_reports ADD COLUMN assessment_data TEXT NULL AFTER goals_next_month");
        } catch (Exception $e) {}
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ilc_assessments (
                id              INT       PRIMARY KEY AUTO_INCREMENT,
                student_id      INT       NOT NULL,
                assessment_date DATE      NOT NULL,
                assessment_type VARCHAR(100) NOT NULL DEFAULT 'Initial Intake',
                strengths       TEXT      NULL,
                challenges      TEXT      NULL,
                recommendations TEXT      NULL,
                conducted_by    INT       NOT NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_ia_student (student_id),
                FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (conducted_by) REFERENCES users(id)
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ilc_fee_payments (
                id          INT           PRIMARY KEY AUTO_INCREMENT,
                student_id  INT           NOT NULL,
                month       DATE          NOT NULL,
                status      ENUM('paid','unpaid') NOT NULL DEFAULT 'unpaid',
                amount      DECIMAL(10,2) NULL,
                paid_on     DATE          NULL,
                recorded_by INT           NOT NULL,
                created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_ifp_student_month (student_id, month),
                KEY idx_ifp_student (student_id),
                FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (recorded_by) REFERENCES users(id)
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}

        // Group 23: column backfills for admission_requests (ILC assessment form)
        try {
            $arCols = array_flip($pdo->query("SHOW COLUMNS FROM admission_requests")->fetchAll(PDO::FETCH_COLUMN));
            if (!isset($arCols['assessment_data']))
                $pdo->exec("ALTER TABLE admission_requests ADD COLUMN assessment_data TEXT NULL AFTER disability_notes");
            if (!isset($arCols['enrolled_student_id']))
                $pdo->exec("ALTER TABLE admission_requests ADD COLUMN enrolled_student_id INT NULL AFTER assessment_data");
            if (!isset($arCols['enrolled_gr_no']))
                $pdo->exec("ALTER TABLE admission_requests ADD COLUMN enrolled_gr_no VARCHAR(30) NULL AFTER enrolled_student_id");
        } catch (Exception $e) {}

        // Group 24: houses table + students.house_id + promotion history + graduation columns
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS houses (
                id         INT PRIMARY KEY AUTO_INCREMENT,
                name       VARCHAR(100) NOT NULL,
                color      VARCHAR(20)  NOT NULL DEFAULT '#3b82f6',
                created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
            // Seed starter houses
            $pdo->exec("INSERT IGNORE INTO houses (id, name, color) VALUES
                (1,'Allama Iqbal','#3b82f6'),
                (2,'Quaid-e-Azam','#22c55e'),
                (3,'Fatima Jinnah','#f97316'),
                (4,'Sir Syed','#a855f7')");
            // Add house_id to students if missing
            $stuCols24 = array_flip($pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN));
            if (!isset($stuCols24['house_id'])) {
                $pdo->exec("ALTER TABLE students ADD COLUMN house_id INT NULL");
                try {
                    $pdo->exec("ALTER TABLE students ADD CONSTRAINT fk_student_house FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE SET NULL");
                } catch (Exception $e2) {}
            }
            // Graduation columns
            if (!isset($stuCols24['graduated_at']))
                $pdo->exec("ALTER TABLE students ADD COLUMN graduated_at TIMESTAMP NULL DEFAULT NULL");
            if (!isset($stuCols24['graduation_year']))
                $pdo->exec("ALTER TABLE students ADD COLUMN graduation_year VARCHAR(20) NULL DEFAULT NULL");
            try {
                $pdo->exec("ALTER TABLE students ADD INDEX idx_students_graduated_at (graduated_at)");
            } catch (Exception $e2) {}
        } catch (Exception $e) {}
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS student_promotion_history (
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
            ) ENGINE=InnoDB");
        } catch (Exception $e) {}
    }
    return $pdo;
}
