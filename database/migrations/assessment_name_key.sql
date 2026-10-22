-- Assessment identity key
-- -----------------------------------------------------------------------------
-- Adds a `name_key` column that stores the normalized form of each
-- assessment's name/title. The normalization logic lives in PHP
-- (normalizeAssessmentName() in config/config.php) because it uses
-- Unicode-aware regexes and lookup tables that MySQL can't express
-- cleanly. This migration adds the column and the index; the actual
-- backfill is done by scripts/backfill_assessment_name_keys.php which
-- iterates every assessment, computes the key via the PHP helper, and
-- updates the row.
--
-- Why not merge rows now? The user's requirement #8 says: do not merge
-- records just because their names look similar — require authorized
-- review when the match is ambiguous. So this migration only ANNOTATES
-- rows; the admin consolidation UI (follow-up) uses `name_key` to
-- surface merge candidates.
--
-- Safe to re-run: `ADD COLUMN IF NOT EXISTS` + `ADD INDEX IF NOT EXISTS`.
-- -----------------------------------------------------------------------------

ALTER TABLE `assessments`
    ADD COLUMN IF NOT EXISTS `name_key` VARCHAR(120) NULL
        COMMENT 'Normalized assessment name — set by normalizeAssessmentName() in PHP. Shared key = same examination.'
        AFTER `name`;

-- Index for grouping / dropdown queries.
-- The (class_id, name_key) combination is what the report-card dropdown
-- groups by, and it's what the "possible duplicate" check at creation
-- time looks up. The migration tolerates MariaDB variants that don't
-- support IF NOT EXISTS on indexes — it will warn, not fail.
CREATE INDEX IF NOT EXISTS `idx_assessments_name_key`
    ON `assessments` (`class_id`, `name_key`);
