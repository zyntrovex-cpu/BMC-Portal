-- ============================================================
-- Migration: Profile Photo Upload
-- Run once on production before deploying code changes.
-- ============================================================

ALTER TABLE users
  ADD COLUMN profile_photo          VARCHAR(255)                                               DEFAULT NULL  AFTER status,
  ADD COLUMN photo_status           ENUM('none','pending','approved','rejected') NOT NULL       DEFAULT 'none' AFTER profile_photo,
  ADD COLUMN photo_rejection_reason VARCHAR(255)                                               DEFAULT NULL  AFTER photo_status,
  ADD COLUMN photo_reviewed_by      INT                                                        DEFAULT NULL  AFTER photo_rejection_reason,
  ADD COLUMN photo_reviewed_at      TIMESTAMP NULL                                             DEFAULT NULL  AFTER photo_reviewed_by;

ALTER TABLE users
  ADD CONSTRAINT fk_photo_reviewer
      FOREIGN KEY (photo_reviewed_by) REFERENCES users(id) ON DELETE SET NULL;
