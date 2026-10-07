-- ─────────────────────────────────────────────────────────────────────────────
-- Kuickpay Import — Migration
-- Run this once to create the audit table.
-- The PHP page (finance/kuickpay.php) also runs CREATE TABLE IF NOT EXISTS
-- automatically, so this file is for manual DB setup / reference only.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `kuickpay_transactions` (
    `id`              INT            NOT NULL AUTO_INCREMENT,
    `batch_id`        VARCHAR(30)    NOT NULL                COMMENT 'Import batch ID, e.g. KP20260810143201',
    `tran_datetime`   DATETIME                               COMMENT 'Full transaction timestamp from Kuickpay',
    `reg_num`         VARCHAR(20)                            COMMENT 'Reg. Num. from Kuickpay — matched to students.roll_no',
    `consumer_num`    VARCHAR(30)                            COMMENT 'Consumer Num. from Kuickpay',
    `auth_id`         VARCHAR(30)                            COMMENT 'Auth ID from Kuickpay',
    `tran_date_raw`   VARCHAR(10)                            COMMENT 'Raw YYYYMMDD date string',
    `tran_time_raw`   VARCHAR(10)                            COMMENT 'Raw HHMMSS time string',
    `transaction_id`  VARCHAR(60),
    `voucher_num`     VARCHAR(80)                            COMMENT 'Voucher/Reference number — used for dedup',
    `bank`            VARCHAR(30)                            COMMENT 'Bank short code (MCB, WAS, NBP, BAF, TMF, ...)',
    `amount`          DECIMAL(10,2)  DEFAULT 0               COMMENT 'Gross amount paid by student',
    `fee_charge`      DECIMAL(10,2)  DEFAULT 0               COMMENT 'Kuickpay gateway fee',
    `tax`             DECIMAL(10,2)  DEFAULT 0               COMMENT 'Tax on Kuickpay fee',
    `net_amount`      DECIMAL(10,2)  DEFAULT 0               COMMENT 'Net amount after deducting fee (Amount Aft. Chg.)',
    `consumer_detail` VARCHAR(200)                           COMMENT 'Student name as received from Kuickpay',
    `network`         VARCHAR(50)                            COMMENT 'Payment network (1Link, etc.)',
    `channel`         VARCHAR(50)                            COMMENT 'Channel (BPS, etc.)',
    `fee_month`       TINYINT                                COMMENT '1–12: fee month this import belongs to',
    `fee_year`        SMALLINT                               COMMENT 'Fee year',
    `student_id`      INT            NULL                    COMMENT 'Matched student ID (NULL if unmatched)',
    `status`          ENUM('matched','unmatched','duplicate','already_paid')
                      DEFAULT 'unmatched'                    COMMENT 'Import result status',
    `imported_by`     INT            NULL,
    `imported_at`     TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_batch`   (`batch_id`),
    INDEX `idx_reg`     (`reg_num`),
    INDEX `idx_student` (`student_id`),
    INDEX `idx_period`  (`fee_month`, `fee_year`),
    CONSTRAINT `kp_student_fk`  FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`) ON DELETE SET NULL,
    CONSTRAINT `kp_importer_fk` FOREIGN KEY (`imported_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Kuickpay PPM Payment Log — one row per transaction per import';
