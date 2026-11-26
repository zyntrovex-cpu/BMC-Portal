-- =====================================================================
-- Migration: Add phone column to users table
-- Applies to all non-teacher staff roles (finance, ilc_vp, vp_main,
-- wing_head, student_affairs) whose contact details are only in users.
-- Teachers already have phone in the teachers table.
-- Run once against bmc_portal database.
-- =====================================================================

ALTER TABLE users
  ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER email;
