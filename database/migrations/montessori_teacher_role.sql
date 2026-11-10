-- =====================================================================
-- Migration: Add montessori_teacher role to users.role ENUM
-- and promote existing montessori-wing teachers to the new role.
-- ⚠️  BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT!
-- Run once against bmc_portal database.
-- =====================================================================

-- 1. Extend the role ENUM to include montessori_teacher
ALTER TABLE users
  MODIFY COLUMN role ENUM(
    'student','teacher','admin','finance',
    'ilc_vp','student_affairs','vp_main','wing_head',
    'montessori_teacher'
  ) NOT NULL;

-- 2. Promote existing Montessori-wing teachers to the new role
UPDATE users u
  JOIN teachers t ON t.user_id = u.id
SET u.role = 'montessori_teacher'
WHERE t.wing = 'montessori'
  AND u.role = 'teacher';
