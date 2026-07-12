-- ================================================================
-- BMC Portal — Comprehensive Dummy Data
-- Run order: bmc_portal_full.sql → ilc-migration.sql → hierarchy-migration.sql → THIS FILE
-- Password for all new users: student123
-- ================================================================

USE bmc_portal;

-- ══════════════════════════════════════════════════════════════════
-- SECTION A: ILC PORTAL
-- ══════════════════════════════════════════════════════════════════

-- A1. Mark ILC classes (ids 10 & 11 inserted by ilc-migration.sql)
UPDATE classes SET is_ilc = 1 WHERE id IN (10, 11);

-- A2. ILC Teachers
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
(401, 'T007', 'Ms. Asma Riaz',   'asma.ilc@bmc.edu.pk',  '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'teacher', 'active'),
(402, 'T008', 'Mr. Nadeem Shah', 'nadeem.ilc@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'teacher', 'active');

INSERT IGNORE INTO teachers (user_id, emp_id, subject_id, designation, is_ilc, join_date) VALUES
(401, 'T007', NULL, 'ILC Class Teacher',   1, '2023-03-01'),
(402, 'T008', NULL, 'ILC Support Teacher', 1, '2023-06-15');

-- A3. ILC Students — ILC-A (class_id = 10)
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
(411, 'ILC101', 'Hamza Tanveer', 'hamza.ilc@bmc.edu.pk',   '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(412, 'ILC102', 'Sara Baig',     'sara.ilc@bmc.edu.pk',    '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(413, 'ILC103', 'Zaid Mansoor',  'zaid.ilc@bmc.edu.pk',    '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(414, 'ILC104', 'Maria Imran',   'maria.ilc@bmc.edu.pk',   '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(415, 'ILC105', 'Amir Sohail',   'amir.ilc@bmc.edu.pk',    '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active');

-- A4. ILC Students — ILC-B (class_id = 11)
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
(416, 'ILC201', 'Dua Waheed',   'dua.ilc@bmc.edu.pk',     '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(417, 'ILC202', 'Omer Farooq',  'omer.ilc@bmc.edu.pk',    '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(418, 'ILC203', 'Hira Nawaz',   'hira.ilc@bmc.edu.pk',    '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(419, 'ILC204', 'Shahbaz Alam', 'shahbaz.ilc@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active');

-- A5. Students table rows for ILC students
INSERT IGNORE INTO students (user_id, roll_no, class_id, father_name, dob, gender, parent_name, parent_phone) VALUES
(411, 'ILC101', 10, 'Tanveer Ahmed', '2012-04-10', 'male',   'Tanveer Ahmed', '0311-1111101'),
(412, 'ILC102', 10, 'Baig Sahib',    '2013-07-22', 'female', 'Baig Sahib',    '0311-1111102'),
(413, 'ILC103', 10, 'Mansoor Ali',   '2012-11-05', 'male',   'Mansoor Ali',   '0311-1111103'),
(414, 'ILC104', 10, 'Imran Khan',    '2013-03-18', 'female', 'Imran Khan',    '0311-1111104'),
(415, 'ILC105', 10, 'Sohail Baig',   '2012-09-30', 'male',   'Sohail Baig',   '0311-1111105'),
(416, 'ILC201', 11, 'Waheed Ahmed',  '2014-02-14', 'female', 'Waheed Ahmed',  '0311-1111201'),
(417, 'ILC202', 11, 'Farooq Sahib',  '2013-08-25', 'male',   'Farooq Sahib',  '0311-1111202'),
(418, 'ILC203', 11, 'Nawaz Hussain', '2014-05-07', 'female', 'Nawaz Hussain', '0311-1111203'),
(419, 'ILC204', 11, 'Alam Khan',     '2013-12-19', 'male',   'Alam Khan',     '0311-1111204');

-- A6. Disability records for ILC students (recorded_by = 301 = ILC VP)
INSERT IGNORE INTO student_disabilities (student_id, subtype_id, notes, recorded_by) VALUES
((SELECT id FROM students WHERE user_id = 411),  1, 'Requires extra reading time. Uses phonics-based approach.',         301),
((SELECT id FROM students WHERE user_id = 411), 24, 'Fidgety; benefits from short task intervals.',                     301),
((SELECT id FROM students WHERE user_id = 412),  7, 'Low vision — uses large-font materials and magnifier.',            301),
((SELECT id FROM students WHERE user_id = 413), 19, 'High-functioning; strong math skills, needs social cues support.', 301),
((SELECT id FROM students WHERE user_id = 414), 15, 'Trisomy 21. Responds well to visual learning aids.',              301),
((SELECT id FROM students WHERE user_id = 415), 25, 'Predominantly inattentive. Benefits from one-on-one attention.',  301),
((SELECT id FROM students WHERE user_id = 416), 11, 'Uses hearing aids. Prefers front-row seating.',                   301),
((SELECT id FROM students WHERE user_id = 417),  2, 'Difficulty writing — uses laptop for assignments.',                301),
((SELECT id FROM students WHERE user_id = 418), 20, 'Level 1 ASD. Routine-dependent; minimal transitions.',            301),
((SELECT id FROM students WHERE user_id = 419),  3, 'Struggles with numbers. Tactile counting aids help.',             301);

-- A7. Admission requests (requested_by=301 ILC VP, reviewed_by=302 SA)
INSERT IGNORE INTO admission_requests
  (student_name, parent_name, parent_phone, dob, requested_class, student_category, wing, disability_notes, status, requested_by, reviewed_by, review_notes, reviewed_at)
VALUES
('Ayesha Farhan',  'Farhan Ahmed',  '0321-5001001', '2013-03-10', 'ILC-A', 'cpo',     'ilc', 'Suspected dyslexia — reading delays noted.',             'approved', 301, 302, 'Documents verified. Seat available in ILC-A.', '2026-06-15 10:00:00'),
('Ibrahim Yousuf', 'Yousuf Rafiq',  '0321-5001002', '2014-01-22', 'ILC-B', 'civilian','ilc', 'Mild hearing impairment — wears hearing aid.',           'reviewed', 301, 302, 'Audiologist report required before final approval.', '2026-06-18 11:30:00'),
('Nimra Salman',   'Salman Rashid', '0321-5001003', '2013-07-05', 'ILC-A', 'sailor',  'ilc', 'Down Syndrome — Trisomy 21 confirmed.',                  'pending',  301, NULL, NULL, NULL),
('Rehman Tariq',   'Tariq Hussain', '0321-5001004', '2014-05-18', 'ILC-B', 'cpo',     'ilc', 'Level 2 ASD. Currently in mainstream school.',          'rejected', 301, 302, 'Class at capacity. Reapply next session.', '2026-06-20 14:00:00'),
('Sadia Basit',    'Basit Ullah',   '0321-5001005', '2013-11-30', 'ILC-A', 'civilian','ilc', 'ADHD — on medication. Attention span 10-15 min.',        'pending',  301, NULL, NULL, NULL),
('Usman Haider',   'Haider Ali',    '0321-5001006', '2014-02-14', 'ILC-B', NULL,      'ilc', NULL,                                                      'pending',  301, NULL, NULL, NULL);

-- ══════════════════════════════════════════════════════════════════
-- SECTION B: MONTESSORI / WING HEAD PORTAL
-- ══════════════════════════════════════════════════════════════════

-- B1. Mark Montessori classes (ids 20-23 from hierarchy-migration.sql)
UPDATE classes SET is_montessori = 1 WHERE id IN (20, 21, 22, 23);

-- B2. Montessori student user accounts
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
-- Beginner (class 20)
(421, 'M101', 'Ali Raza',      'm101@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(422, 'M102', 'Zainab Noor',   'm102@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(423, 'M103', 'Ibrahim Shah',  'm103@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
-- Advance (class 21)
(424, 'M201', 'Maryam Khalid', 'm201@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(425, 'M202', 'Hassan Tariq',  'm202@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(426, 'M203', 'Fatima Zahra',  'm203@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
-- Prep (class 22)
(427, 'M301', 'Hamza Aziz',    'm301@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(428, 'M302', 'Aqsa Mehmood',  'm302@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(429, 'M303', 'Saad Amin',     'm303@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
-- Class-1 (class 23)
(430, 'M401', 'Hira Baig',     'm401@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(431, 'M402', 'Talha Iqbal',   'm402@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(432, 'M403', 'Noor Fatima',   'm403@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active');

-- B3. Students table rows for Montessori students (with Civilian/CPO/Sailor categories)
INSERT IGNORE INTO students (user_id, roll_no, class_id, father_name, dob, gender, parent_name, parent_phone, student_category) VALUES
(421, 'M101', 20, 'Raza Hussain',   '2020-05-10', 'male',   'Raza Hussain',   '0312-2001101', 'sailor'),
(422, 'M102', 20, 'Noor Ahmad',     '2020-08-22', 'female', 'Noor Ahmad',     '0312-2001102', 'civilian'),
(423, 'M103', 20, 'Shah Jahan',     '2020-03-15', 'male',   'Shah Jahan',     '0312-2001103', 'cpo'),
(424, 'M201', 21, 'Khalid Pervaiz', '2019-11-07', 'female', 'Khalid Pervaiz', '0312-2002201', 'cpo'),
(425, 'M202', 21, 'Tariq Saleem',   '2019-07-19', 'male',   'Tariq Saleem',   '0312-2002202', 'sailor'),
(426, 'M203', 21, 'Zahra Bibi',     '2019-09-30', 'female', 'Zahra Bibi',     '0312-2002203', 'civilian'),
(427, 'M301', 22, 'Aziz Ahmed',     '2018-04-12', 'male',   'Aziz Ahmed',     '0312-2003301', 'sailor'),
(428, 'M302', 22, 'Mehmood Khan',   '2018-06-25', 'female', 'Mehmood Khan',   '0312-2003302', 'civilian'),
(429, 'M303', 22, 'Amin Ullah',     '2018-01-08', 'male',   'Amin Ullah',     '0312-2003303', 'cpo'),
(430, 'M401', 23, 'Baig Ahmad',     '2017-10-20', 'female', 'Baig Ahmad',     '0312-2004401', 'civilian'),
(431, 'M402', 23, 'Iqbal Hussain',  '2017-12-03', 'male',   'Iqbal Hussain',  '0312-2004402', 'sailor'),
(432, 'M403', 23, 'Fatima Noor',    '2018-02-17', 'female', 'Fatima Noor',    '0312-2004403', 'cpo');

-- ══════════════════════════════════════════════════════════════════
-- SECTION C: VP MAIN — EXTRA SCHOOL STUDENTS (9-A and 10-A)
-- ══════════════════════════════════════════════════════════════════
-- class_id 4 = 9-A, class_id 7 = 10-A (from bmc_portal_full.sql)

-- C1. User accounts
INSERT IGNORE INTO users (id, user_id, name, email, password, role, status) VALUES
(441, 'S901',  'Zubair Ahmed',  'zubair@bmc.edu.pk',  '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(442, 'S902',  'Sana Qasim',    'sanaq@bmc.edu.pk',   '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(443, 'S903',  'Kamran Rauf',   'kamranr@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(444, 'S904',  'Naila Hassan',  'nailah@bmc.edu.pk',  '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(445, 'S905',  'Tariq Jameel',  'tariqj@bmc.edu.pk',  '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(446, 'S1001', 'Waqar Ullah',   'waqar@bmc.edu.pk',   '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(447, 'S1002', 'Mehwish Ali',   'mehwish@bmc.edu.pk', '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active'),
(448, 'S1003', 'Danish Khan',   'danish@bmc.edu.pk',  '$2y$12$BAsRJJaK24jPek..UJB/puV9NRQb2gLuAXju4fRBH263btU2OmkCG', 'student', 'active');

-- C2. Students table rows
INSERT IGNORE INTO students (user_id, roll_no, class_id, father_name, dob, gender, parent_name, parent_phone) VALUES
(441, 'S901',  4, 'Ahmed Qasim',   '2010-03-15', 'male',   'Ahmed Qasim',   '0315-9001001'),
(442, 'S902',  4, 'Qasim Hussain', '2010-07-22', 'female', 'Qasim Hussain', '0315-9001002'),
(443, 'S903',  4, 'Rauf Sahib',    '2010-01-10', 'male',   'Rauf Sahib',    '0315-9001003'),
(444, 'S904',  4, 'Hassan Malik',  '2010-11-05', 'female', 'Hassan Malik',  '0315-9001004'),
(445, 'S905',  4, 'Jameel Ahmed',  '2010-08-18', 'male',   'Jameel Ahmed',  '0315-9001005'),
(446, 'S1001', 7, 'Ullah Sahib',   '2009-04-20', 'male',   'Ullah Sahib',   '0315-1001001'),
(447, 'S1002', 7, 'Ali Khan',      '2009-09-12', 'female', 'Ali Khan',      '0315-1001002'),
(448, 'S1003', 7, 'Khan Baig',     '2009-06-28', 'male',   'Khan Baig',     '0315-1001003');

-- C3. Class subjects for 9-A (4) and 10-A (7)
-- teacher_id: 1=T001(PHY), 2=T002(MAT), 3=T003(ENG), 4=T004(CHE), 5=T005(BIO)
INSERT IGNORE INTO class_subjects (class_id, subject_id, teacher_id) VALUES
(4, 1, 1), (4, 2, 2), (4, 3, 3), (4, 5, 5),
(7, 1, 1), (7, 2, 2), (7, 3, 3), (7, 4, 4);

-- C4. Timetable for 9-A
INSERT IGNORE INTO timetable (class_id, day, period, subject_id, teacher_id, room) VALUES
(4,'monday',   1,1,1,'Lab-A'   ),(4,'monday',   2,3,3,'Room 101'),(4,'monday',   3,2,2,'Room 102'),(4,'monday',   4,5,5,'Room 103'),
(4,'tuesday',  1,2,2,'Room 102'),(4,'tuesday',  2,5,5,'Room 103'),(4,'tuesday',  3,1,1,'Lab-A'   ),(4,'tuesday',  4,3,3,'Room 101'),
(4,'wednesday',1,3,3,'Room 101'),(4,'wednesday',2,1,1,'Lab-A'   ),(4,'wednesday',3,5,5,'Room 103'),(4,'wednesday',4,2,2,'Room 102'),
(4,'thursday', 1,5,5,'Room 103'),(4,'thursday', 2,2,2,'Room 102'),(4,'thursday', 3,3,3,'Room 101'),(4,'thursday', 4,1,1,'Lab-A'   ),
(4,'friday',   1,2,2,'Room 102'),(4,'friday',   2,3,3,'Room 101'),(4,'friday',   3,1,1,'Lab-A'   ),(4,'friday',   4,5,5,'Room 103');

-- C5. Timetable for 10-A
INSERT IGNORE INTO timetable (class_id, day, period, subject_id, teacher_id, room) VALUES
(7,'monday',   1,1,1,'Lab-A'   ),(7,'monday',   2,4,4,'Lab-B'   ),(7,'monday',   3,2,2,'Room 201'),(7,'monday',   4,3,3,'Room 202'),
(7,'tuesday',  1,3,3,'Room 202'),(7,'tuesday',  2,1,1,'Lab-A'   ),(7,'tuesday',  3,4,4,'Lab-B'   ),(7,'tuesday',  4,2,2,'Room 201'),
(7,'wednesday',1,2,2,'Room 201'),(7,'wednesday',2,3,3,'Room 202'),(7,'wednesday',3,1,1,'Lab-A'   ),(7,'wednesday',4,4,4,'Lab-B'   ),
(7,'thursday', 1,4,4,'Lab-B'   ),(7,'thursday', 2,2,2,'Room 201'),(7,'thursday', 3,3,3,'Room 202'),(7,'thursday', 4,1,1,'Lab-A'   ),
(7,'friday',   1,1,1,'Lab-A'   ),(7,'friday',   2,3,3,'Room 202'),(7,'friday',   3,2,2,'Room 201'),(7,'friday',   4,4,4,'Lab-B'   );

-- C6. Attendance for 9-A students (Physics = subject_id 1)
INSERT IGNORE INTO attendance (student_id, class_id, subject_id, date, status, teacher_id) VALUES
((SELECT id FROM students WHERE user_id=441), 4,1,'2026-05-22','P',1),
((SELECT id FROM students WHERE user_id=442), 4,1,'2026-05-22','P',1),
((SELECT id FROM students WHERE user_id=443), 4,1,'2026-05-22','A',1),
((SELECT id FROM students WHERE user_id=444), 4,1,'2026-05-22','P',1),
((SELECT id FROM students WHERE user_id=445), 4,1,'2026-05-22','L',1),
((SELECT id FROM students WHERE user_id=441), 4,1,'2026-05-19','P',1),
((SELECT id FROM students WHERE user_id=442), 4,1,'2026-05-19','A',1),
((SELECT id FROM students WHERE user_id=443), 4,1,'2026-05-19','P',1),
((SELECT id FROM students WHERE user_id=444), 4,1,'2026-05-19','P',1),
((SELECT id FROM students WHERE user_id=445), 4,1,'2026-05-19','P',1);

-- C7. Second attendance date for 12-A (for VP attendance view)
INSERT IGNORE INTO attendance (student_id, class_id, subject_id, date, status, teacher_id) VALUES
(1,12,1,'2026-05-19','P',1),(2,12,1,'2026-05-19','P',1),( 3,12,1,'2026-05-19','P',1),
(4,12,1,'2026-05-19','P',1),(5,12,1,'2026-05-19','A',1),( 6,12,1,'2026-05-19','P',1),
(7,12,1,'2026-05-19','P',1),(8,12,1,'2026-05-19','P',1),( 9,12,1,'2026-05-19','L',1),
(10,12,1,'2026-05-19','P',1),(11,12,1,'2026-05-19','P',1),(12,12,1,'2026-05-19','P',1);

-- ══════════════════════════════════════════════════════════════════
-- SECTION D: STUDENT AFFAIRS — MEDICAL RECORDS
-- (recorded_by = 302 = SA001 Student Affairs)
-- ══════════════════════════════════════════════════════════════════

INSERT IGNORE INTO medical_records (student_id, record_type, description, recorded_by, recorded_at) VALUES
-- Regular 12-A students (student ids 1-5 from bmc_portal_full.sql)
(1, 'Vaccination',   'Hepatitis B booster administered. Next due 2027.',            302, '2026-01-15'),
(2, 'Allergy',       'Penicillin allergy confirmed. Update prescription records.',  302, '2026-02-10'),
(3, 'Physical Exam', 'Annual physical completed. All vitals normal.',               302, '2026-03-05'),
(4, 'Vision Test',   'Mild myopia (-1.5D). Spectacles recommended.',               302, '2026-03-20'),
(5, 'Dental Checkup','Cavity in lower-left molar. Referred to dentist.',           302, '2026-04-12'),
-- ILC students
((SELECT id FROM students WHERE user_id=411), 'Psychological Assessment', 'Dyslexia confirmed via WISC-V psycho-educational battery.',        302, '2026-01-20'),
((SELECT id FROM students WHERE user_id=412), 'Vision Test',              'Best corrected visual acuity 6/60. Low vision certified.',         302, '2026-02-05'),
((SELECT id FROM students WHERE user_id=413), 'Developmental Screening',  'ADOS-2 administered. ASD Level 1 confirmed.',                     302, '2026-03-01'),
((SELECT id FROM students WHERE user_id=414), 'Cardiology Report',        'Echocardiogram normal. Down Syndrome cardiac clearance given.',    302, '2026-01-30'),
((SELECT id FROM students WHERE user_id=415), 'ADHD Evaluation',          'Conners-3 scale. Predominantly inattentive ADHD confirmed.',      302, '2026-02-22'),
-- Montessori students
((SELECT id FROM students WHERE user_id=421), 'Vaccination',   'MMR vaccine administered. Certificate issued.',         302, '2026-02-18'),
((SELECT id FROM students WHERE user_id=424), 'Physical Exam', 'Annual height/weight check. BMI normal for age.',       302, '2026-03-10'),
((SELECT id FROM students WHERE user_id=427), 'Dental Checkup','All primary teeth present. No cavities found.',         302, '2026-04-05');

-- ══════════════════════════════════════════════════════════════════
-- SECTION E: ADDITIONAL MARKS FOR 12-A (Quiz 2 & Assignment 1)
-- assessment_id: 1=Quiz 1, 2=Quiz 2, 3=Assignment 1, 4=Mid Term
-- student_id 1-15 = students 101-115 (from bmc_portal_full.sql)
-- ══════════════════════════════════════════════════════════════════

INSERT IGNORE INTO marks (student_id, assessment_id, marks_obtained, entered_by) VALUES
-- Quiz 2
(1,2,8,1),(2,2,7,1),(3,2,5,1),(4,2,9,1),(5,2,6,1),
(6,2,8,1),(7,2,5,1),(8,2,7,1),(9,2,4,1),(10,2,8,1),
(11,2,9,1),(12,2,6,1),(13,2,7,1),(14,2,8,1),(15,2,5,1),
-- Assignment 1
(1,3,18,1),(2,3,15,1),(3,3,12,1),(4,3,19,1),(5,3,14,1),
(6,3,16,1),(7,3,11,1),(8,3,17,1),(9,3,10,1),(10,3,15,1),
(11,3,18,1),(12,3,13,1),(13,3,16,1),(14,3,19,1),(15,3,12,1);

-- ══════════════════════════════════════════════════════════════════
-- SECTION F: APRIL FEES (additional months for finance portal)
-- ══════════════════════════════════════════════════════════════════

INSERT IGNORE INTO fees (student_id, month, year, amount, paid, payment_date, payment_mode, recorded_by) VALUES
(1, 4,2026,12000,1,'2026-04-18','Bank',  23),
(2, 4,2026,12000,1,'2026-04-15','Cash',  23),
(3, 4,2026,12000,1,'2026-04-20','Online',23),
(4, 4,2026,12000,1,'2026-04-10','Cash',  23),
(5, 4,2026,12000,0, NULL,       'Cash',  NULL),
(6, 4,2026,12000,1,'2026-04-12','Bank',  23),
(7, 4,2026,12000,0, NULL,       'Cash',  NULL),
(8, 4,2026,12000,1,'2026-04-08','Cash',  23),
(9, 4,2026,12000,0, NULL,       'Cash',  NULL),
(10,4,2026,12000,1,'2026-04-05','Online',23);
