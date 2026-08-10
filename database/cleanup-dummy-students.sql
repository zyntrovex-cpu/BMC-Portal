-- ─────────────────────────────────────────────────────────────────────────────
-- Cleanup Dummy Students
-- Removes the 15 original seed students (user_ids 101–115).
-- CASCADE constraints handle fees, attendance, results, profile_change_requests,
-- kuickpay_transactions, and student_warnings automatically.
-- Run this ONCE in your MySQL/MariaDB client before importing real students.
-- ─────────────────────────────────────────────────────────────────────────────

SET FOREIGN_KEY_CHECKS = 0;

-- Delete dummy student users (cascades to students, fees, attendance, etc.)
DELETE FROM users
WHERE role = 'student'
  AND user_id IN ('101','102','103','104','105','106','107',
                  '108','109','110','111','112','113','114','115');

-- Clear any orphaned kuickpay_transactions that referenced these students
DELETE FROM kuickpay_transactions WHERE student_id IS NULL AND reg_num IN
  ('0101','0102','0103','0104','0105','0106','0107',
   '0108','0109','0110','0111','0112','0113','0114','0115');

SET FOREIGN_KEY_CHECKS = 1;

-- Verify cleanup
SELECT COUNT(*) AS remaining_students FROM users WHERE role = 'student';
