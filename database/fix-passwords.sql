-- ================================================================
-- Fix: Standardise ALL user passwords to student123
-- Run this if any user cannot log in.
-- Hash below = password_hash("student123", PASSWORD_BCRYPT, ['cost'=>12])
-- ================================================================

USE bmc_portal;

UPDATE users
SET password = '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG'
WHERE user_id IN (
    'ADM001','FIN001',
    'T001','T002','T003','T004','T005','T006',
    'ILC001','SA001','VP001','WH001',
    'BMC2025001','BMC2025002','BMC2025003','BMC2025004','BMC2025005',
    'BMC2025006','BMC2025007','BMC2025008','BMC2025009','BMC2025010',
    'BMC2025011','BMC2025012','BMC2025013','BMC2025014','BMC2025015',
    'BMC2025016','BMC2025017','BMC2025018','BMC2025019','BMC2025020'
);
