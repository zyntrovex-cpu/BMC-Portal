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
                created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");

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
    }
    return $pdo;
}
