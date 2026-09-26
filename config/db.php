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

            // users: extend role ENUM if legacy install
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role
                ENUM('student','teacher','admin','finance','ilc_vp','student_affairs','vp_main','wing_head')
                NOT NULL");
        } catch (Exception $e) {
            // Non-fatal — column already exists or table not yet created
        }
    }
    return $pdo;
}
