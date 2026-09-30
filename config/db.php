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
    // Auto-migrate missing schema columns (runs once per process after first connection)
    if (!$migrated) {
        $migrated = true;
        try {
            // classes: is_ilc, is_montessori
            $classCols = array_flip(
                $pdo->query("SHOW COLUMNS FROM classes")->fetchAll(PDO::FETCH_COLUMN)
            );
            if (!isset($classCols['is_ilc']))
                $pdo->exec("ALTER TABLE classes ADD COLUMN is_ilc TINYINT(1) NOT NULL DEFAULT 0");
            if (!isset($classCols['is_montessori']))
                $pdo->exec("ALTER TABLE classes ADD COLUMN is_montessori TINYINT(1) NOT NULL DEFAULT 0");

            // teachers: is_ilc
            $tchCols = array_flip(
                $pdo->query("SHOW COLUMNS FROM teachers")->fetchAll(PDO::FETCH_COLUMN)
            );
            if (!isset($tchCols['is_ilc']))
                $pdo->exec("ALTER TABLE teachers ADD COLUMN is_ilc TINYINT(1) NOT NULL DEFAULT 0");

            // students: soft-delete column
            $stuCols = array_flip(
                $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN)
            );
            if (!isset($stuCols['deleted_at']))
                $pdo->exec("ALTER TABLE students ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");

            // exam_date_sheets + exam_date_sheet_entries (exam datesheet feature)
            $pdo->exec("CREATE TABLE IF NOT EXISTS exam_date_sheets (
                id            INT PRIMARY KEY AUTO_INCREMENT,
                title         VARCHAR(200)  NOT NULL,
                wing          ENUM('main','montessori','ilc','all') NOT NULL DEFAULT 'all',
                academic_year VARCHAR(20)   NOT NULL,
                status        ENUM('draft','published') NOT NULL DEFAULT 'draft',
                notes         TEXT DEFAULT NULL,
                created_by    INT NOT NULL,
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");
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

            // users: extend role ENUM to include montessori_teacher
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role
                ENUM('student','teacher','admin','finance','ilc_vp','student_affairs','vp_main','wing_head','montessori_teacher','ilc_teacher')
                NOT NULL");

        } catch (Exception $e) {
            // Non-fatal — column already exists or table not yet created
        }
    }
    return $pdo;
}
