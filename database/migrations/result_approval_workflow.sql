-- ============================================================
-- BMC Portal — Main Campus Result Approval & Publishing Workflow
-- Also: Botany and Zoology subjects (split from Biology)
--
-- Safe to run on any existing BMC Portal database.
-- All statements are idempotent.
-- ============================================================

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 1.  assessments — publish status and workflow columns
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `assessments`
  ADD COLUMN IF NOT EXISTS `publish_status` ENUM('draft','pending_review','changes_requested','approved','published')
    NOT NULL DEFAULT 'published'
    COMMENT 'Main campus result approval state; default published keeps existing records visible',
  ADD COLUMN IF NOT EXISTS `submitted_at`   DATETIME NULL DEFAULT NULL
    COMMENT 'When teacher submitted for VP review',
  ADD COLUMN IF NOT EXISTS `review_note`    TEXT     NULL DEFAULT NULL
    COMMENT 'VP note on rejection or changes requested',
  ADD COLUMN IF NOT EXISTS `reviewed_by`    INT      NULL DEFAULT NULL
    COMMENT 'User ID who approved/rejected',
  ADD COLUMN IF NOT EXISTS `published_by`   INT      NULL DEFAULT NULL
    COMMENT 'User ID who published the result',
  ADD COLUMN IF NOT EXISTS `published_at`   DATETIME NULL DEFAULT NULL
    COMMENT 'When result was published';

-- ─────────────────────────────────────────────────────────────
-- 2.  Botany and Zoology subjects
--     Biology row is kept to preserve existing marks records.
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `subjects` (`name`, `code`) VALUES ('Botany', 'BOT');
INSERT IGNORE INTO `subjects` (`name`, `code`) VALUES ('Zoology', 'ZOO');

-- ─────────────────────────────────────────────────────────────
-- Done
-- ─────────────────────────────────────────────────────────────
-- Verification queries:
--   SHOW COLUMNS FROM assessments LIKE 'publish_status';
--   SELECT id, name, code FROM subjects WHERE name IN ('Botany','Zoology','Biology');
-- ============================================================
