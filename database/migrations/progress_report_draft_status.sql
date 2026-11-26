-- Progress Report draft-vs-submitted status
-- -----------------------------------------------------------------------------
-- Adds a status column so autosaved drafts can be distinguished from a report
-- the teacher has intentionally submitted. Default value is 'submitted' so
-- EVERY existing row is treated as a final submission — we never retro-mark
-- historical records as drafts.
--
-- Draft lifecycle (set by portal/api/progress_report_autosave.php):
--   draft      — autosave has written the latest form state
--   submitted  — teacher clicked Submit; report is final
--
-- The autosave endpoint upserts per (student_id, term, session, reported_by),
-- so teachers never create duplicate draft rows for the same report.
-- -----------------------------------------------------------------------------

ALTER TABLE `progress_reports`
    ADD COLUMN IF NOT EXISTS `status` ENUM('draft','submitted') NOT NULL DEFAULT 'submitted'
    COMMENT 'draft = autosaved incomplete form; submitted = teacher submitted final.'
    AFTER `vp_remarks`;

CREATE INDEX IF NOT EXISTS `idx_progress_reports_draft`
    ON `progress_reports` (`reported_by`, `student_id`, `status`);
