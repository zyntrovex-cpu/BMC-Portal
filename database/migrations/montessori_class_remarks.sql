-- Montessori VP: class remarks and photo gallery
CREATE TABLE IF NOT EXISTS montessori_class_remarks (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id   INT UNSIGNED NOT NULL,
    remarks    TEXT NULL,
    photos     JSON NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_class (class_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
