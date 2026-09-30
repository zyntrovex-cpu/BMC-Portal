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

        // Group 3: students soft-delete column
        try {
            $stuCols = array_flip(
                $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN)
            );
            if (!isset($stuCols['deleted_at']))
                $pdo->exec("ALTER TABLE students ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
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
                ENUM('student','teacher','admin','finance','ilc_vp','student_affairs','vp_main','wing_head','montessori_teacher','ilc_teacher')
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
            // Seed predefined categories (INSERT IGNORE skips if already present)
            $pdo->exec("INSERT IGNORE INTO student_categories (name, sort_order) VALUES
                ('AOG I',10),('AOG II',11),('AOG III',12),('AOG IV',13),
                ('AOB I',20),('AOB II',21),('AOB III',22),('AOB IV',23),
                ('ASB I',30),('ASB II',31),('ASB III',32),('ASB IV',33),
                ('ASG I',40),('ASG II',41),('ASG III',42),('ASG IV',43),
                ('NOB I',50),('NOB II',51),('NOB III',52),('NOB IV',53),
                ('NOG I',60),('NOG II',61),('NOG III',62),('NOG IV',63),
                ('NSB I',70),('NSB II',71),('NSB III',72),('NSB IV',73),
                ('NSG I',80),('NSG II',81),('NSG III',82),('NSG IV',83),
                ('NCB I',90),('NCB II',91),('NCB III',92),('NCB IV',93),
                ('NCG I',100),('NCG II',101),('NCG III',102),('NCG IV',103),
                ('POG I',110),('POG II',111),('POG III',112),('POG IV',113),
                ('POB I',120),('POB II',121),('POB III',122),('POB IV',123),
                ('PSB I',130),('PSB II',131),('PSB III',132),('PSB IV',133),
                ('PSG I',140),('PSG II',141),('PSG III',142),('PSG IV',143),
                ('FAC G I',150),('FAC G II',151),('FAC G III',152),('FAC G IV',153),
                ('FAC B I',160),('FAC B II',161),('FAC B III',162),('FAC B IV',163),
                ('CB I',170),('CB II',171),('CB III',172),('CB IV',173),
                ('CG I',180),('CG II',181),('CG III',182),('CG IV',183)");
        } catch (Exception $e) {}
    }
    return $pdo;
}
