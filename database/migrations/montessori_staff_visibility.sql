-- Montessori staff visibility backfill
-- -----------------------------------------------------------------------------
-- Problem: VP Montessori dashboard was missing Coordinators (role=wing_head)
-- entirely and could silently drop a Teacher whose role was correct
-- (montessori_teacher) but whose `teachers` row was never created. The earlier
-- class-teacher-assignment-migration.sql only backfills `teachers` rows for
-- vp_main / ilc_vp / wing_head / vp_montessori; a directly-created
-- montessori_teacher never had one.
--
-- This migration:
--   1. Creates a `teachers` row for every active montessori_teacher / ilc_teacher
--      who doesn't already have one.
--   2. Normalises `teachers.wing` so every Montessori staff member (wing_head
--      coordinators + montessori_teachers + vp_montessori) carries
--      wing='montessori' explicitly.
--   3. Leaves plain `teacher` rows untouched — Main Campus is unaffected.
-- -----------------------------------------------------------------------------

-- 1. Backfill teachers rows for Montessori / ILC teachers who are missing them.
INSERT IGNORE INTO `teachers` (`user_id`, `emp_id`, `wing`, `is_ilc`)
SELECT u.`id`,
       u.`user_id`,
       CASE u.`role`
           WHEN 'montessori_teacher' THEN 'montessori'
           WHEN 'ilc_teacher'        THEN 'ilc'
           ELSE 'main'
       END,
       CASE WHEN u.`role` = 'ilc_teacher' THEN 1 ELSE 0 END
FROM `users` u
WHERE u.`role` IN ('montessori_teacher', 'ilc_teacher')
  AND COALESCE(u.`status`, 'active') = 'active'
  AND NOT EXISTS (SELECT 1 FROM `teachers` t WHERE t.`user_id` = u.`id`);

-- 2. Force `teachers.wing` to match the user's declared role so every
--    role-based query and every wing-based query see the same set.
UPDATE `teachers` t
    JOIN `users` u ON t.`user_id` = u.`id`
SET t.`wing`   = 'montessori',
    t.`is_ilc` = 0
WHERE u.`role` IN ('montessori_teacher', 'vp_montessori', 'wing_head');
