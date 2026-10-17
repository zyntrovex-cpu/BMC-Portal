-- montessori_assessment_templates: save-and-reuse criteria + topic per class/subject
CREATE TABLE IF NOT EXISTS montessori_assessment_templates (
    id          INT           PRIMARY KEY AUTO_INCREMENT,
    class_id    INT           NOT NULL,
    subject_id  INT           NOT NULL,
    criteria    JSON          NOT NULL,
    topic       VARCHAR(200)  NULL DEFAULT NULL,
    updated_by  INT           NULL DEFAULT NULL,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mat (class_id, subject_id),
    FOREIGN KEY (class_id)   REFERENCES classes(id)   ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id)  ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES teachers(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- Add topic column if table already exists without it
ALTER TABLE montessori_assessment_templates
    ADD COLUMN IF NOT EXISTS topic VARCHAR(200) NULL DEFAULT NULL AFTER criteria;
