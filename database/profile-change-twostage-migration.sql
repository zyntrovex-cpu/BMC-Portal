-- Two-stage profile change request workflow
-- Adds SA (Student Affairs) first-level approval columns to profile_change_requests.
-- Run once; PHP pages auto-apply this migration on first visit.

ALTER TABLE `profile_change_requests`
    ADD COLUMN IF NOT EXISTS `sa_status`      ENUM('pending','approved','rejected') DEFAULT 'pending' AFTER `status`,
    ADD COLUMN IF NOT EXISTS `sa_reviewed_by` INT DEFAULT NULL AFTER `sa_status`,
    ADD COLUMN IF NOT EXISTS `sa_reviewed_at` DATETIME DEFAULT NULL AFTER `sa_reviewed_by`;

-- Grandfather existing pending requests so Admin can still process them
-- (treats them as already SA-approved)
UPDATE `profile_change_requests`
SET `sa_status` = 'approved'
WHERE `status` = 'pending' AND (`sa_status` IS NULL OR `sa_status` = 'pending');
