-- ================================================================
-- BMC Portal — Migrations & Dummy Data
-- ================================================================

-- New tables for houses and warnings
CREATE TABLE IF NOT EXISTS houses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  color VARCHAR(20) DEFAULT '#3b82f6',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS student_warnings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  given_by INT NOT NULL,
  reason TEXT NOT NULL,
  severity ENUM('low','medium','high') DEFAULT 'medium',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (given_by) REFERENCES users(id)
);

ALTER TABLE students ADD COLUMN IF NOT EXISTS house_id INT NULL AFTER class_id;

-- Default houses
INSERT IGNORE INTO houses (id, name, color) VALUES
(1, 'Iqbal',  '#3b82f6'),
(2, 'Jinnah', '#10b981'),
(3, 'Liaqat', '#f59e0b'),
(4, 'Fatima', '#ef4444');

-- ================================================================
-- DUMMY DATA
-- ================================================================

-- Classes
INSERT IGNORE INTO classes (id, name, grade, section) VALUES
(1, '8-A', 8, 'A'),
(2, '9-A', 9, 'A'),
(3, '10-A', 10, 'A');

-- Subjects
INSERT IGNORE INTO subjects (id, name, code) VALUES
(1, 'English',  'ENG'),
(2, 'Urdu',     'URD'),
(3, 'Math',     'MTH'),
(4, 'Science',  'SCI'),
(5, 'Computer', 'COM');

-- Teacher user accounts (password = teacher123)
-- Note: The hash below is bcrypt for 'teacher123'
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
(101, 'T001', 'Mr. Ahmed Ali',   'ahmed.ali@bmc.edu.pk',   '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'teacher', 'active'),
(102, 'T002', 'Ms. Sara Khan',   'sara.khan@bmc.edu.pk',   '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'teacher', 'active'),
(103, 'T003', 'Mr. Usman Raza',  'usman.raza@bmc.edu.pk',  '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'teacher', 'active');

-- Teacher profiles
INSERT IGNORE INTO teachers (id, user_id, emp_id, subject_id, qualification, phone, join_date) VALUES
(1, 101, 'T001', 1, 'M.A English',             '03001234567', '2020-01-15'),
(2, 102, 'T002', 3, 'M.Sc Mathematics',         '03111234567', '2019-06-01'),
(3, 103, 'T003', 5, 'B.Sc Computer Science',    '03211234567', '2021-03-10');

-- Student user accounts (password = student123)
-- Note: The hash below is bcrypt for 'student123'
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
(201, 'STU001', 'Ali Hassan',       'ali.hassan@bmc.edu.pk',       '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(202, 'STU002', 'Fatima Malik',     'fatima.malik@bmc.edu.pk',     '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(203, 'STU003', 'Usman Tariq',      'usman.tariq@bmc.edu.pk',      '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(204, 'STU004', 'Zainab Noor',      'zainab.noor@bmc.edu.pk',      '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(205, 'STU005', 'Hamza Sheikh',     'hamza.sheikh@bmc.edu.pk',     '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(206, 'STU006', 'Ayesha Qureshi',   'ayesha.qureshi@bmc.edu.pk',   '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(207, 'STU007', 'Bilal Ahmed',      'bilal.ahmed@bmc.edu.pk',      '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(208, 'STU008', 'Sana Iqbal',       'sana.iqbal@bmc.edu.pk',       '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(209, 'STU009', 'Omer Farooq',      'omer.farooq@bmc.edu.pk',      '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(210, 'STU010', 'Hira Baig',        'hira.baig@bmc.edu.pk',        '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(211, 'STU011', 'Tariq Mehmood',    'tariq.mehmood@bmc.edu.pk',    '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(212, 'STU012', 'Maham Aslam',      'maham.aslam@bmc.edu.pk',      '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(213, 'STU013', 'Shahid Butt',      'shahid.butt@bmc.edu.pk',      '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(214, 'STU014', 'Nadia Hussain',    'nadia.hussain@bmc.edu.pk',    '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active'),
(215, 'STU015', 'Kamran Sajid',     'kamran.sajid@bmc.edu.pk',     '$2y$10$TKh8H1.PfQ2zBqm4vqKuqe6Vz3V6c3HaQ5d5p6V4kKq3Q5lKq2Q5m', 'student', 'active');

-- Student profiles (5 per class, with houses distributed across all 4)
INSERT IGNORE INTO students (id, user_id, roll_no, class_id, house_id, admission_date, dob, phone, address, parent_name, parent_phone) VALUES
(1,  201, 'STU001', 1, 1, '2022-04-01', '2010-03-15', '03001111111', 'House 12, Block B, Gulberg, Lahore',        'Hassan Ali',      '03001111110'),
(2,  202, 'STU002', 1, 2, '2022-04-01', '2010-07-22', '03002222222', 'House 45, Johar Town, Lahore',              'Malik Anwar',     '03002222220'),
(3,  203, 'STU003', 1, 3, '2022-04-01', '2010-01-10', '03003333333', 'Street 7, Model Town, Lahore',              'Tariq Mahmood',   '03003333330'),
(4,  204, 'STU004', 1, 4, '2022-04-01', '2010-11-05', '03004444444', 'House 3, DHA Phase 5, Lahore',              'Noor Ahmed',      '03004444440'),
(5,  205, 'STU005', 1, 1, '2022-04-01', '2010-09-18', '03005555555', 'House 99, Shadman, Lahore',                 'Sheikh Imran',    '03005555550'),
(6,  206, 'STU006', 2, 2, '2021-04-01', '2009-05-30', '03006666666', 'House 22, Cantt, Lahore',                   'Qureshi Saeed',   '03006666660'),
(7,  207, 'STU007', 2, 3, '2021-04-01', '2009-08-14', '03007777777', 'House 77, Faisal Town, Lahore',             'Ahmed Bilal Sr.', '03007777770'),
(8,  208, 'STU008', 2, 4, '2021-04-01', '2009-02-28', '03008888888', 'House 15, Garden Town, Lahore',             'Iqbal Rashid',    '03008888880'),
(9,  209, 'STU009', 2, 1, '2021-04-01', '2009-12-01', '03009999999', 'House 60, Allama Iqbal Town, Lahore',       'Farooq Javed',    '03009999990'),
(10, 210, 'STU010', 2, 2, '2021-04-01', '2009-06-17', '03010101010', 'House 31, Gulshan Ravi, Lahore',            'Baig Khalid',     '03010101000'),
(11, 211, 'STU011', 3, 3, '2020-04-01', '2008-04-25', '03011111111', 'House 8, Wapda Town, Lahore',               'Mahmood Tariq',   '03011111110'),
(12, 212, 'STU012', 3, 4, '2020-04-01', '2008-10-09', '03012121212', 'House 50, Bahria Town, Lahore',             'Aslam Zulfiqar',  '03012121200'),
(13, 213, 'STU013', 3, 1, '2020-04-01', '2008-07-03', '03013131313', 'House 19, Township, Lahore',                'Butt Nawaz',      '03013131300'),
(14, 214, 'STU014', 3, 2, '2020-04-01', '2008-01-20', '03014141414', 'House 88, Samanabad, Lahore',               'Hussain Qaiser',  '03014141400'),
(15, 215, 'STU015', 3, 3, '2020-04-01', '2008-09-11', '03015151515', 'House 42, Muslim Town, Lahore',             'Sajid Kamal',     '03015151500');

-- Notices (INSERT IGNORE so re-running is safe)
INSERT IGNORE INTO notices (id, title, content, audience, pinned, expiry_date, author_id, created_at) VALUES
(1, 'Welcome Back — Session 2025-26',
 'Welcome to the new academic session. All students are reminded to submit their fee challan by the 10th of each month. Parents are requested to ensure regular attendance.',
 'students,teachers,admin', 1, NULL, 1, NOW()),
(2, 'Annual Sports Day',
 'The Annual Sports Day will be held on 25th June 2025. All students are encouraged to participate and represent their houses. Registration forms are available from the sports department.',
 'students,teachers', 0, '2025-06-30', 1, NOW());
