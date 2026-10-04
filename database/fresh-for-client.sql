-- ============================================================
-- BMC Portal — Fresh-for-Client Reset Script
-- Run this in phpMyAdmin SQL tab on your bmc_portal database.
-- Wipes all transactional/demo data; keeps structure + config.
-- After running: log in with admin@bmc.edu.pk / Admin@2025
-- and change the password immediately.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── Marks & Assessments ──────────────────────────────────────
TRUNCATE TABLE marks;
TRUNCATE TABLE assessments;

-- ── Attendance ───────────────────────────────────────────────
TRUNCATE TABLE attendance;
TRUNCATE TABLE attendance_edit_requests;

-- ── Finance ──────────────────────────────────────────────────
TRUNCATE TABLE fees;
TRUNCATE TABLE kuickpay_transactions;

-- ── Student records ──────────────────────────────────────────
TRUNCATE TABLE student_warnings;
TRUNCATE TABLE student_complaints;
TRUNCATE TABLE student_promotion_history;
TRUNCATE TABLE medical_records;
TRUNCATE TABLE profile_change_requests;
TRUNCATE TABLE student_disabilities;

-- ── ILC ──────────────────────────────────────────────────────
TRUNCATE TABLE ilc_session_records;
TRUNCATE TABLE admission_requests;

-- ── Diary & Timetable ────────────────────────────────────────
TRUNCATE TABLE diary_media;
TRUNCATE TABLE daily_diary;
TRUNCATE TABLE timetable;

-- ── Exam scheduling ──────────────────────────────────────────
TRUNCATE TABLE exam_date_sheet_entries;
TRUNCATE TABLE exam_date_sheets;

-- ── Notices & Activity ───────────────────────────────────────
TRUNCATE TABLE notices;
TRUNCATE TABLE activity_log;

-- ── Website content (client will fill their own) ─────────────
TRUNCATE TABLE site_admission_forms;
TRUNCATE TABLE site_contact_messages;
TRUNCATE TABLE site_news;
TRUNCATE TABLE site_events;
TRUNCATE TABLE site_gallery;
TRUNCATE TABLE site_albums;
TRUNCATE TABLE site_videos;
TRUNCATE TABLE site_sliders;
TRUNCATE TABLE site_testimonials;
TRUNCATE TABLE site_partners;
TRUNCATE TABLE site_careers;
TRUNCATE TABLE site_downloads;
TRUNCATE TABLE site_pages;
TRUNCATE TABLE site_notices;
TRUNCATE TABLE site_faculty;

-- ── Demo people (users / students / teachers) ────────────────
TRUNCATE TABLE user_permissions;
TRUNCATE TABLE students;
TRUNCATE TABLE teachers;
TRUNCATE TABLE users;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Re-seed the admin account ────────────────────────────────
-- Password: Admin@2025  ← CHANGE IMMEDIATELY after first login
INSERT INTO users (name, user_id, email, password, role, status, created_at)
VALUES (
  'Admin',
  'admin',
  'admin@bmc.edu.pk',
  '$2y$12$Zx/u/KsXlhUA.Hj5r.N8eONDsSETfIb57BA6UrkSlnk4fczeRg4.O',
  'admin',
  'active',
  NOW()
);

-- ── Done ─────────────────────────────────────────────────────
SELECT
  (SELECT COUNT(*) FROM users)    AS users_remaining,
  (SELECT COUNT(*) FROM students) AS students_remaining,
  (SELECT COUNT(*) FROM teachers) AS teachers_remaining,
  (SELECT COUNT(*) FROM marks)    AS marks_remaining,
  (SELECT COUNT(*) FROM fees)     AS fees_remaining,
  (SELECT COUNT(*) FROM activity_log) AS activity_remaining;
