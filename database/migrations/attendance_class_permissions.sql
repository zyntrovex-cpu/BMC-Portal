-- attendance_class_permissions: which classes each teacher may take attendance for
CREATE TABLE IF NOT EXISTS attendance_class_permissions (
    id          INT       PRIMARY KEY AUTO_INCREMENT,
    teacher_id  INT       NOT NULL,
    class_id    INT       NOT NULL,
    granted_by  INT       NULL DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_acp (teacher_id, class_id),
    FOREIGN KEY (teacher_id)  REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id)    REFERENCES classes(id)  ON DELETE CASCADE,
    FOREIGN KEY (granted_by)  REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- Revoke attendance by default for teacher-like roles with no existing permission record
INSERT IGNORE INTO user_permissions (user_id, permission, granted)
SELECT u.id, 'attendance', 0
FROM users u
WHERE u.role IN ('teacher','montessori_teacher','ilc_teacher',
                 'secondary_wing_head','higher_secondary_wing_head','primary_wing_head')
  AND NOT EXISTS (
    SELECT 1 FROM user_permissions up
    WHERE up.user_id = u.id AND up.permission = 'attendance'
  );

-- Make attendance_edit_requests.subject_id nullable for class-only correction requests
ALTER TABLE attendance_edit_requests MODIFY COLUMN subject_id INT NULL DEFAULT NULL;
