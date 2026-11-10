-- Excel-sourced classes migration
-- Widens name/section columns and seeds all 20 class names from the
-- Students_Data_template.xlsx file. Safe to run multiple times (INSERT IGNORE).
--
-- NOTE: portal/admin/classes.php applies this automatically on page load.
-- Run this manually only for direct DB bootstrapping.

-- 1. Widen columns to accommodate longer Montessori section names
ALTER TABLE `classes` MODIFY `name`    VARCHAR(60) NOT NULL;
ALTER TABLE `classes` MODIFY `section` VARCHAR(30) NOT NULL DEFAULT '';

-- 2. Ensure wing / montessori / ILC flag columns exist
ALTER TABLE `classes` ADD COLUMN IF NOT EXISTS `is_montessori` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `classes` ADD COLUMN IF NOT EXISTS `is_ilc`        TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `classes` ADD COLUMN IF NOT EXISTS `wing`
    ENUM('main','montessori','ilc') NOT NULL DEFAULT 'main';

UPDATE `classes` SET wing = 'ilc'        WHERE is_ilc = 1        AND wing = 'main';
UPDATE `classes` SET wing = 'montessori' WHERE is_montessori = 1 AND wing = 'main';

-- 3. Seed Excel class names (INSERT IGNORE = duplicate-safe)
INSERT IGNORE INTO `classes` (name, grade, section, is_montessori, is_ilc, wing) VALUES
-- Main wing (single-section, grade-only names)
('2',  2, '', 0, 0, 'main'),
('3',  3, '', 0, 0, 'main'),
('4',  4, '', 0, 0, 'main'),
('5',  5, '', 0, 0, 'main'),
('6',  6, '', 0, 0, 'main'),
('7',  7, '', 0, 0, 'main'),
('8',  8, '', 0, 0, 'main'),
('9',  9, '', 0, 0, 'main'),
('10',10, '', 0, 0, 'main'),
('11',11, '', 0, 0, 'main'),
('12',12, '', 0, 0, 'main'),
-- Montessori wing
('BEGINNERS(ROSE)',      0, 'ROSE',      1, 0, 'montessori'),
('BEGINNERS(SUNFLOWER)', 0, 'SUNFLOWER', 1, 0, 'montessori'),
('PREP(BLUBELL)',        0, 'BLUBELL',   1, 0, 'montessori'),
('PREP(DAFFODIL)',       0, 'DAFFODIL',  1, 0, 'montessori'),
('ONE (JASMINE)',        1, 'JASMINE',   1, 0, 'montessori'),
('ONE (MARIGOLD)',       1, 'MARIGOLD',  1, 0, 'montessori'),
('ADVANCE(DAISY)',       2, 'DAISY',     1, 0, 'montessori'),
('ADVANCE(LILLY)',       2, 'LILLY',     1, 0, 'montessori'),
-- ILC wing
('ILC', 0, '', 0, 1, 'ilc');
