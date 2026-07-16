-- ================================================================
-- Fix: Standardise all user passwords to student123
-- Run this if ADM001 / T001-T006 / FIN001 cannot log in.
-- Hash below = password_hash("student123", PASSWORD_BCRYPT, ['cost'=>12])
-- ================================================================

USE bmc_portal;

UPDATE users
SET password = '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG'
WHERE user_id IN ('ADM001','T001','T002','T003','T004','T005','T006','FIN001');
