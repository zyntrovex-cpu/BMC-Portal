-- Report card redesign: separate per-assessment-type report cards with VP remarks
-- Teacher remarks already exist in marks.remarks; this adds VP-level remarks per assessment.
ALTER TABLE assessments
    ADD COLUMN IF NOT EXISTS vp_remarks TEXT NULL AFTER review_note;
