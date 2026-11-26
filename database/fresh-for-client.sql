-- ============================================================
-- BMC Portal — Fresh-for-Client Reset Script
-- HOW TO RUN IN phpMADMIN:
--   1. Select your database in the left sidebar
--   2. Click the SQL tab
--   3. Paste this entire script
--   4. UNCHECK "Enable foreign key checks" (bottom of the page)
--   5. Click Go
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── Level 1: deepest children (reference marks/diary/exam entries) ──
DELETE FROM diary_media;
DELETE FROM exam_date_sheet_entries;

-- ── Level 2: marks & attendance (reference assessments, students) ──
DELETE FROM marks;
DELETE FROM attendance;
DELETE FROM attendance_edit_requests;

-- ── Level 3: assessments & timetable (reference classes/subjects/teachers) ──
DELETE FROM assessments;
DELETE FROM timetable;
DELETE FROM exam_date_sheets;

-- ── Level 4: student-linked records ──
DELETE FROM student_disabilities;
DELETE FROM student_warnings;
DELETE FROM student_complaints;
DELETE FROM student_promotion_history;
DELETE FROM medical_records;
DELETE FROM profile_change_requests;
DELETE FROM ilc_session_records;
DELETE FROM admission_requests;

-- ── Level 5: diary & finance ──
DELETE FROM daily_diary;
DELETE FROM fees;
DELETE FROM kuickpay_transactions;

-- ── Level 6: notices & activity ──
DELETE FROM notices;
DELETE FROM activity_log;

-- ── Level 7: website content ──
DELETE FROM site_admission_forms;
DELETE FROM site_contact_messages;
DELETE FROM site_gallery;
DELETE FROM site_albums;
DELETE FROM site_news;
DELETE FROM site_events;
DELETE FROM site_videos;
DELETE FROM site_sliders;
DELETE FROM site_testimonials;
DELETE FROM site_partners;
DELETE FROM site_careers;
DELETE FROM site_downloads;
DELETE FROM site_pages;
DELETE FROM site_notices;
DELETE FROM site_faculty;

-- ── Level 8: people (order: dependents before parents) ──
DELETE FROM user_permissions;
DELETE FROM students;
DELETE FROM teachers;
DELETE FROM users;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Reset auto-increment counters ────────────────────────────
ALTER TABLE users                    AUTO_INCREMENT = 1;
ALTER TABLE students                 AUTO_INCREMENT = 1;
ALTER TABLE teachers                 AUTO_INCREMENT = 1;
ALTER TABLE marks                    AUTO_INCREMENT = 1;
ALTER TABLE assessments              AUTO_INCREMENT = 1;
ALTER TABLE attendance               AUTO_INCREMENT = 1;
ALTER TABLE fees                     AUTO_INCREMENT = 1;
ALTER TABLE notices                  AUTO_INCREMENT = 1;
ALTER TABLE activity_log             AUTO_INCREMENT = 1;
ALTER TABLE timetable                AUTO_INCREMENT = 1;
ALTER TABLE daily_diary              AUTO_INCREMENT = 1;
ALTER TABLE medical_records          AUTO_INCREMENT = 1;
ALTER TABLE student_warnings         AUTO_INCREMENT = 1;
ALTER TABLE student_complaints       AUTO_INCREMENT = 1;
ALTER TABLE exam_date_sheets         AUTO_INCREMENT = 1;
ALTER TABLE exam_date_sheet_entries  AUTO_INCREMENT = 1;

-- ── Re-seed the single admin account ─────────────────────────
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

-- ── Verify ───────────────────────────────────────────────────
SELECT
  (SELECT COUNT(*) FROM users)        AS users_remaining,
  (SELECT COUNT(*) FROM students)     AS students_remaining,
  (SELECT COUNT(*) FROM teachers)     AS teachers_remaining,
  (SELECT COUNT(*) FROM marks)        AS marks_remaining,
  (SELECT COUNT(*) FROM assessments)  AS assessments_remaining,
  (SELECT COUNT(*) FROM fees)         AS fees_remaining,
  (SELECT COUNT(*) FROM activity_log) AS activity_remaining;
