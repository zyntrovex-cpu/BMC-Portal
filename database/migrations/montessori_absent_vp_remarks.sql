-- Montessori Progress Report: VP remarks column
-- "Absent" rating code (ABS) requires no schema change — overall is VARCHAR(10).
ALTER TABLE progress_reports
    ADD COLUMN IF NOT EXISTS vp_remarks TEXT NULL AFTER form_data;
