-- ===================================================================
-- BMC Portal — ILC Features Migration
-- Run this in phpMyAdmin (SQL tab) or via CLI:
--   mysql -u root bmc_portal < ilc-features-migration.sql
-- ===================================================================

USE bmc_portal;

-- ── 1. ILC Session Records ────────────────────────────────────────
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

-- ── 2. Seed new ILC VP permissions for existing ILC VP staff ──────
INSERT IGNORE INTO user_permissions (user_id, permission, granted)
SELECT u.id, perm.p, 1
FROM users u
CROSS JOIN (
    SELECT 'ilc_records'    AS p
    UNION SELECT 'ilc_attendance'
    UNION SELECT 'ilc_results'
    UNION SELECT 'ilc_timetable'
) perm
WHERE u.role = 'ilc_vp' AND u.status = 'active';
