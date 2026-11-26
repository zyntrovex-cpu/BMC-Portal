-- ─────────────────────────────────────────────────────────────────────────────
-- Cleanup Old Dummy Students (user_ids 101–115)
--
-- USE THIS ONLY if you are upgrading from an old installation that still has
-- the original 15 seed students (101-115). If you did a fresh install using
-- the updated bmc_portal_full.sql, these students do NOT exist and this script
-- is a no-op.
--
-- CASCADE constraints handle fees, attendance, marks, profile_change_requests,
-- kuickpay_transactions, and student_warnings automatically.
-- ─────────────────────────────────────────────────────────────────────────────

SET FOREIGN_KEY_CHECKS = 0;

-- Delete old dummy student users — cascades to students + all related tables
DELETE FROM users
WHERE role = 'student'
  AND user_id IN ('101','102','103','104','105','106','107',
                  '108','109','110','111','112','113','114','115');

SET FOREIGN_KEY_CHECKS = 1;

-- Verify cleanup
SELECT COUNT(*) AS remaining_students FROM users WHERE role = 'student';
