-- ─────────────────────────────────────────────────────────────────────────────
-- Students Biodata Migration
-- Adds all admission-form fields to the `students` table.
--
-- The PHP import page (admin/import-students.php) runs this automatically
-- on first load via ensureBiodataColumns(), so this file is for manual
-- DB setup / reference / rollback only.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `gr_no`              VARCHAR(30)   DEFAULT NULL COMMENT 'General Register / GR Number',
  ADD COLUMN IF NOT EXISTS `kuickpay_id`        VARCHAR(30)   DEFAULT NULL COMMENT 'Kuickpay student/consumer ID',
  ADD COLUMN IF NOT EXISTS `category`           VARCHAR(30)   DEFAULT NULL COMMENT 'Admission category (e.g. AOB 1, AOG 2)',
  ADD COLUMN IF NOT EXISTS `academic_group`     VARCHAR(50)   DEFAULT NULL COMMENT 'Academic stream/group (Science, Arts, Pre-Medical, Commerce)',
  ADD COLUMN IF NOT EXISTS `child_order`        TINYINT       DEFAULT NULL COMMENT 'Birth order in family (1=first, 2=second, etc.)',
  ADD COLUMN IF NOT EXISTS `domicile`           VARCHAR(100)  DEFAULT NULL COMMENT 'Domicile province/district',
  ADD COLUMN IF NOT EXISTS `permanent_address`  TEXT          DEFAULT NULL COMMENT 'Permanent home address (present address stored in address column)',
  ADD COLUMN IF NOT EXISTS `emergency_phone`    VARCHAR(20)   DEFAULT NULL COMMENT 'Emergency contact phone number',
  ADD COLUMN IF NOT EXISTS `whatsapp_no`        VARCHAR(20)   DEFAULT NULL COMMENT 'WhatsApp contact number',
  ADD COLUMN IF NOT EXISTS `religion`           VARCHAR(50)   DEFAULT NULL COMMENT 'Religion',
  ADD COLUMN IF NOT EXISTS `sect`               VARCHAR(50)   DEFAULT NULL COMMENT 'Sect / denomination',
  ADD COLUMN IF NOT EXISTS `blood_group`        VARCHAR(5)    DEFAULT NULL COMMENT 'Blood group (A+, A-, B+, B-, O+, O-, AB+, AB-)',
  ADD COLUMN IF NOT EXISTS `last_school`        VARCHAR(200)  DEFAULT NULL COMMENT 'Last school / institution attended before admission',
  ADD COLUMN IF NOT EXISTS `nationality`        VARCHAR(100)  DEFAULT NULL COMMENT 'Nationality',
  ADD COLUMN IF NOT EXISTS `father_occupation`  VARCHAR(150)  DEFAULT NULL COMMENT 'Father / guardian occupation',
  ADD COLUMN IF NOT EXISTS `documents_submitted` TEXT         DEFAULT NULL COMMENT 'Documents submitted at admission (B-Form, Report Card, etc.)',
  ADD COLUMN IF NOT EXISTS `medical_info`       TEXT          DEFAULT NULL COMMENT 'Medical history: chronic conditions, allergies, medications, immunizations',
  ADD COLUMN IF NOT EXISTS `skills`             TEXT          DEFAULT NULL COMMENT 'Student skills (comma-separated)',
  ADD COLUMN IF NOT EXISTS `sports`             TEXT          DEFAULT NULL COMMENT 'Sports / co-curricular activities',
  ADD COLUMN IF NOT EXISTS `awards`             TEXT          DEFAULT NULL COMMENT 'Awards, certificates, achievements';

-- Note: ADD COLUMN IF NOT EXISTS requires MySQL 8.0+ or MariaDB 10.3+.
-- For older MySQL 5.7, run each ADD COLUMN separately after checking SHOW COLUMNS.
