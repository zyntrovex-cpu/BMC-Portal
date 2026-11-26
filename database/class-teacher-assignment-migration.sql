-- ============================================================
-- BMC Portal — Class Teacher Assignments & Import Teachers
-- Migration covers:
--   • Task 2: staff roles (VP, Wing Head) gain teacher records
--   • Task 3: class_teacher_assignments table (Montessori/ILC)
--   • Task 3: attendance.subject_id made nullable (class teachers
--             have no subject)
--   • Supporting columns required by all recent features
--
-- Safe to run on any existing BMC Portal database.
-- All statements are idempotent (IF NOT EXISTS / IF EXISTS).
-- Compatible with MariaDB 10.x / MySQL 8.x (Hostinger).
--
-- How to run in phpMyAdmin:
--   Database → SQL tab → paste entire file → Go
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────
-- 1.  users.role ENUM — extend to include all new roles
--     (Safe to re-run; MySQL/MariaDB silently no-ops if ENUM
--      already contains all listed values.)
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `users` MODIFY COLUMN `role`
  ENUM(
    'student',
    'teacher',
    'admin',
    'finance',
    'ilc_vp',
    'student_affairs',
    'vp_main',
    'wing_head',
    'montessori_teacher',
    'ilc_teacher',
    'examination_head',
    'vp_montessori',
    'secondary_wing_head',
    'higher_secondary_wing_head',
    'primary_wing_head'
  ) NOT NULL;

-- ─────────────────────────────────────────────────────────────
-- 2.  classes table — add wing / is_ilc / is_montessori columns
--     (required by $monteStyleCond queries and class detection)
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `classes`
  ADD COLUMN IF NOT EXISTS `is_ilc`        TINYINT(1)                     NOT NULL DEFAULT 0
    COMMENT 'ILC wing flag',
  ADD COLUMN IF NOT EXISTS `is_montessori` TINYINT(1)                     NOT NULL DEFAULT 0
    COMMENT 'Montessori wing flag',
  ADD COLUMN IF NOT EXISTS `wing`          ENUM('main','montessori','ilc') NOT NULL DEFAULT 'main'
    COMMENT 'Campus wing this class belongs to';

-- Derive wing from existing flags (safe UPDATE; no-ops on correct rows)
UPDATE `classes` SET `wing` = 'ilc'        WHERE `is_ilc`        = 1 AND `wing` = 'main';
UPDATE `classes` SET `wing` = 'montessori' WHERE `is_montessori` = 1 AND `wing` = 'main';

-- ─────────────────────────────────────────────────────────────
-- 3.  teachers table — add is_ilc / wing / qualification columns
--     (base schema only had: id, user_id, emp_id, subject_id,
--      designation, phone, join_date)
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `teachers`
  ADD COLUMN IF NOT EXISTS `is_ilc`        TINYINT(1)                     NOT NULL DEFAULT 0
    COMMENT 'ILC teacher flag',
  ADD COLUMN IF NOT EXISTS `wing`          ENUM('main','montessori','ilc') NOT NULL DEFAULT 'main'
    COMMENT 'Campus wing this teacher belongs to',
  ADD COLUMN IF NOT EXISTS `qualification` VARCHAR(200)                    DEFAULT NULL
    COMMENT 'Teacher qualification / degree';

-- Derive wing from is_ilc flag for existing rows
UPDATE `teachers` SET `wing` = 'ilc' WHERE `is_ilc` = 1 AND `wing` = 'main';

-- ─────────────────────────────────────────────────────────────
-- 4.  NEW TABLE: class_teacher_assignments
--     One-to-one: each Montessori/ILC/Class-2-3 class has at
--     most one class teacher (enforced by UNIQUE on class_id).
--     Separate from class_subjects because class teachers have
--     no subject — they teach the whole class.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `class_teacher_assignments` (
  `id`         INT           NOT NULL AUTO_INCREMENT,
  `class_id`   INT           NOT NULL,
  `teacher_id` INT           NOT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cta_class`   (`class_id`),
  KEY           `idx_cta_teacher` (`teacher_id`),
  FOREIGN KEY (`class_id`)   REFERENCES `classes`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Class-level teacher assignment for Montessori/ILC and Main Campus Class 2-3';

-- ─────────────────────────────────────────────────────────────
-- 5.  attendance.subject_id — make nullable
--     Class teachers record attendance without a subject.
--     MODIFY COLUMN is safe even if already nullable.
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `attendance`
  MODIFY COLUMN `subject_id` INT NULL DEFAULT NULL;

-- ─────────────────────────────────────────────────────────────
-- 6.  Rebuild attendance unique index to include class_id and
--     allow NULL subject_id.
--
--     Old index name from base schema : uq_att  (student_id, subject_id, date)
--     Intermediate name from prior migration: uq_attendance (student_id, class_id, subject_id, date)
--     We drop both variants then add the final form.
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `attendance` DROP INDEX IF EXISTS `uq_att`;
ALTER TABLE `attendance` DROP INDEX IF EXISTS `uq_attendance`;
ALTER TABLE `attendance`
  ADD UNIQUE KEY `uq_attendance` (`student_id`, `class_id`, `subject_id`, `date`);

-- ─────────────────────────────────────────────────────────────
-- 7.  Backfill teachers rows for staff roles that function as
--     teachers (VP, Wing Head).
--     INSERT IGNORE skips any user who already has a teachers row.
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `teachers` (`user_id`, `emp_id`, `wing`, `is_ilc`)
SELECT
  u.`id`,
  u.`user_id`,
  CASE u.`role`
    WHEN 'ilc_vp'        THEN 'ilc'
    WHEN 'wing_head'     THEN 'montessori'
    WHEN 'vp_montessori' THEN 'montessori'
    ELSE 'main'
  END  AS `wing`,
  CASE WHEN u.`role` = 'ilc_vp' THEN 1 ELSE 0 END AS `is_ilc`
FROM `users` u
WHERE u.`role`   IN ('vp_main', 'ilc_vp', 'wing_head', 'vp_montessori')
  AND u.`status` = 'active'
  AND NOT EXISTS (
    SELECT 1 FROM `teachers` t2 WHERE t2.`user_id` = u.`id`
  );

-- ─────────────────────────────────────────────────────────────
-- 8.  Sync wing/is_ilc on existing teachers rows whose users
--     have one of the staff roles (catches anyone whose teacher
--     row exists but has wrong wing from before this feature).
-- ─────────────────────────────────────────────────────────────
UPDATE `teachers` t
  JOIN `users` u ON t.`user_id` = u.`id`
SET
  t.`wing`   = CASE u.`role`
                 WHEN 'ilc_vp'        THEN 'ilc'
                 WHEN 'wing_head'     THEN 'montessori'
                 WHEN 'vp_montessori' THEN 'montessori'
                 ELSE t.`wing`
               END,
  t.`is_ilc` = CASE WHEN u.`role` = 'ilc_vp' THEN 1 ELSE t.`is_ilc` END
WHERE u.`role` IN ('vp_main', 'ilc_vp', 'wing_head', 'vp_montessori');

-- ─────────────────────────────────────────────────────────────
-- Done
-- ─────────────────────────────────────────────────────────────
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Verification queries (run these after import to confirm):
--
--   SELECT COUNT(*) FROM class_teacher_assignments;
--   SHOW COLUMNS FROM attendance LIKE 'subject_id';
--   SHOW INDEX FROM attendance WHERE Key_name = 'uq_attendance';
--   SELECT role, COUNT(*) FROM users GROUP BY role;
--   SHOW COLUMNS FROM teachers LIKE 'wing';
--   SHOW COLUMNS FROM teachers LIKE 'qualification';
-- ============================================================
