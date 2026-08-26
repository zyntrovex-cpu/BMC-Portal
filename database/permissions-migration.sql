-- ================================================================
-- Permissions Migration — run AFTER schema.sql
-- Stores per-user permission grants for non-admin roles.
-- ================================================================

USE bmc_portal;

CREATE TABLE IF NOT EXISTS user_permissions (
  id         INT PRIMARY KEY AUTO_INCREMENT,
  user_id    INT NOT NULL,
  permission VARCHAR(60) NOT NULL,
  granted    TINYINT(1)  NOT NULL DEFAULT 1,
  UNIQUE KEY uq_user_perm (user_id, permission),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed all existing non-admin staff with full access (all permissions granted).
-- This ensures existing accounts are unaffected by the new permission system.
INSERT IGNORE INTO user_permissions (user_id, permission, granted)
SELECT u.id, p.perm, 1
FROM users u
JOIN (
  -- teacher permissions
  SELECT 'teacher' AS role, 'marks'           AS perm UNION ALL
  SELECT 'teacher',          'attendance'             UNION ALL
  SELECT 'teacher',          'timetable'              UNION ALL
  SELECT 'teacher',          'notices'                UNION ALL
  SELECT 'teacher',          'warnings'               UNION ALL
  -- finance permissions
  SELECT 'finance',          'fee_collection'         UNION ALL
  SELECT 'finance',          'fee_monthly'            UNION ALL
  SELECT 'finance',          'fee_records'            UNION ALL
  SELECT 'finance',          'fee_defaulters'         UNION ALL
  SELECT 'finance',          'fee_reports'            UNION ALL
  -- ilc_vp permissions
  SELECT 'ilc_vp',           'ilc_students'           UNION ALL
  SELECT 'ilc_vp',           'ilc_teachers'           UNION ALL
  SELECT 'ilc_vp',           'ilc_disabilities'       UNION ALL
  SELECT 'ilc_vp',           'ilc_admissions'         UNION ALL
  SELECT 'ilc_vp',           'ilc_viewas'             UNION ALL
  -- student_affairs permissions
  SELECT 'student_affairs',  'sa_students'            UNION ALL
  SELECT 'student_affairs',  'sa_admissions'          UNION ALL
  SELECT 'student_affairs',  'sa_medical'             UNION ALL
  -- vp_main permissions
  SELECT 'vp_main',          'vp_teachers'            UNION ALL
  SELECT 'vp_main',          'vp_students'            UNION ALL
  SELECT 'vp_main',          'vp_attendance'          UNION ALL
  SELECT 'vp_main',          'vp_results'             UNION ALL
  SELECT 'vp_main',          'vp_timetable'           UNION ALL
  SELECT 'vp_main',          'vp_viewas'              UNION ALL
  -- wing_head permissions
  SELECT 'wing_head',        'wh_students'            UNION ALL
  SELECT 'wing_head',        'wh_classes'
) p ON u.role = p.role
WHERE u.role != 'admin' AND u.role != 'student';
