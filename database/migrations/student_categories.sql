-- Student Categories lookup table
-- Safe to re-run (IF NOT EXISTS + INSERT IGNORE).

CREATE TABLE IF NOT EXISTS student_categories (
  id         INT          PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(50)  NOT NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cat_name (name)
) ENGINE=InnoDB;

-- Predefined categories (seeded once; INSERT IGNORE skips duplicates on re-run)
INSERT IGNORE INTO student_categories (name, sort_order) VALUES
('AOG I',   10), ('AOG II',   11), ('AOG III',   12), ('AOG IV',   13),
('AOB I',   20), ('AOB II',   21), ('AOB III',   22), ('AOB IV',   23),
('ASB I',   30), ('ASB II',   31), ('ASB III',   32), ('ASB IV',   33),
('ASG I',   40), ('ASG II',   41), ('ASG III',   42), ('ASG IV',   43),
('NOB I',   50), ('NOB II',   51), ('NOB III',   52), ('NOB IV',   53),
('NOG I',   60), ('NOG II',   61), ('NOG III',   62), ('NOG IV',   63),
('NSB I',   70), ('NSB II',   71), ('NSB III',   72), ('NSB IV',   73),
('NSG I',   80), ('NSG II',   81), ('NSG III',   82), ('NSG IV',   83),
('NCB I',   90), ('NCB II',   91), ('NCB III',   92), ('NCB IV',   93),
('NCG I',  100), ('NCG II',  101), ('NCG III',  102), ('NCG IV',  103),
('POG I',  110), ('POG II',  111), ('POG III',  112), ('POG IV',  113),
('POB I',  120), ('POB II',  121), ('POB III',  122), ('POB IV',  123),
('PSB I',  130), ('PSB II',  131), ('PSB III',  132), ('PSB IV',  133),
('PSG I',  140), ('PSG II',  141), ('PSG III',  142), ('PSG IV',  143),
('FAC G I',150), ('FAC G II',151), ('FAC G III',152), ('FAC G IV',153),
('FAC B I',160), ('FAC B II',161), ('FAC B III',162), ('FAC B IV',163),
('CB I',   170), ('CB II',   171), ('CB III',   172), ('CB IV',   173),
('CG I',   180), ('CG II',   181), ('CG III',   182), ('CG IV',   183);
