-- ILC: Assign Classes to Teachers
CREATE TABLE IF NOT EXISTS ilc_teacher_class_assignments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id  INT UNSIGNED NOT NULL,
    class_id    INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_teacher_class (teacher_id, class_id),
    INDEX idx_teacher (teacher_id),
    INDEX idx_class   (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
