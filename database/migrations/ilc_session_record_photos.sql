-- Add photos JSON column to ilc_session_records for multiple image uploads
ALTER TABLE ilc_session_records ADD COLUMN IF NOT EXISTS photos JSON NULL DEFAULT NULL;
