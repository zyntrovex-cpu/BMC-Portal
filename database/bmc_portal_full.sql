-- BMC Portal Database
-- Import this file in phpMyAdmin → Import tab
-- ============================================

CREATE DATABASE IF NOT EXISTS `bmc_portal` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bmc_portal`;


/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: bmc_portal
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` VALUES
(1,22,'login','Logged in','127.0.0.1','2026-06-09 11:42:00'),
(2,1,'login','Logged in','127.0.0.1','2026-06-09 11:42:10'),
(3,16,'login','Logged in','127.0.0.1','2026-06-09 11:43:29'),
(4,23,'login','Logged in','127.0.0.1','2026-06-09 11:43:39'),
(5,1,'login','Logged in','127.0.0.1','2026-06-09 13:09:54'),
(6,16,'login','Logged in','127.0.0.1','2026-06-09 13:10:07'),
(7,22,'login','Logged in','127.0.0.1','2026-06-09 13:10:12'),
(8,23,'login','Logged in','127.0.0.1','2026-06-09 13:10:17'),
(9,1,'login','Logged in','127.0.0.1','2026-06-09 15:12:05'),
(10,16,'login','Logged in','127.0.0.1','2026-06-09 15:12:13'),
(11,23,'login','Logged in','127.0.0.1','2026-06-09 15:12:13'),
(12,22,'login','Logged in','127.0.0.1','2026-06-09 15:12:19'),
(13,22,'login','Logged in','127.0.0.1','2026-06-09 15:31:46'),
(14,16,'login','Logged in','127.0.0.1','2026-06-09 15:31:47'),
(15,1,'login','Logged in','127.0.0.1','2026-06-09 15:31:47'),
(16,23,'login','Logged in','127.0.0.1','2026-06-09 15:31:59'),
(17,22,'login','Logged in','127.0.0.1','2026-06-09 18:27:35'),
(18,22,'user_create','Created TST001 (student)','127.0.0.1','2026-06-09 18:30:34'),
(19,22,'notice_create','Created notice: Test Notice','127.0.0.1','2026-06-09 18:30:40'),
(20,16,'marks_save','Saved marks for assessment #1 (2 students)','127.0.0.1','2026-06-09 18:30:48'),
(21,22,'login','Logged in','127.0.0.1','2026-06-09 18:36:03'),
(22,1,'login','Logged in','127.0.0.1','2026-06-09 18:36:22'),
(23,16,'login','Logged in','127.0.0.1','2026-06-09 18:36:31'),
(24,23,'login','Logged in','127.0.0.1','2026-06-09 18:36:36'),
(25,22,'login','Logged in','127.0.0.1','2026-06-09 19:05:40'),
(26,1,'login','Logged in','127.0.0.1','2026-06-09 19:05:40'),
(27,16,'login','Logged in','127.0.0.1','2026-06-09 19:05:41'),
(28,23,'login','Logged in','127.0.0.1','2026-06-09 19:05:41'),
(29,22,'user_create','Created TST999 (student)','127.0.0.1','2026-06-09 19:05:58'),
(30,22,'user_create','Created TST998 (student)','127.0.0.1','2026-06-09 19:06:06'),
(31,22,'login','Logged in','127.0.0.1','2026-06-09 19:06:19'),
(32,22,'user_create','Created TST997 (student)','127.0.0.1','2026-06-09 19:06:20'),
(33,22,'login','Logged in','127.0.0.1','2026-06-09 19:06:26'),
(34,22,'user_create','Created TST996 (student)','127.0.0.1','2026-06-09 19:06:26'),
(35,22,'login','Logged in','127.0.0.1','2026-06-09 19:06:32'),
(36,22,'user_create','Created TST995 (student)','127.0.0.1','2026-06-09 19:06:35');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assessments`
--

DROP TABLE IF EXISTS `assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('Quiz','Assignment','Mid Term','Final Term','Practical') NOT NULL DEFAULT 'Quiz',
  `max_marks` decimal(6,2) NOT NULL,
  `weight` decimal(5,2) DEFAULT 0.00,
  `date` date DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `locked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `assessments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `assessments_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessments`
--

LOCK TABLES `assessments` WRITE;
/*!40000 ALTER TABLE `assessments` DISABLE KEYS */;
INSERT INTO `assessments` VALUES
(1,'Quiz 1','Quiz 1','Quiz',10.00,5.00,'2026-02-05',12,1,1,1,'2026-06-09 11:24:07'),
(2,'Quiz 2','Quiz 2','Quiz',10.00,5.00,'2026-02-20',12,1,1,1,'2026-06-09 11:24:07'),
(3,'Assignment 1','Assignment 1','Assignment',20.00,10.00,'2026-02-28',12,1,1,1,'2026-06-09 11:24:07'),
(4,'Mid Term','Mid Term','Mid Term',40.00,30.00,'2026-03-15',12,1,1,1,'2026-06-09 11:24:07'),
(5,'Practical','Practical','Practical',20.00,10.00,'2026-04-10',12,1,1,1,'2026-06-09 11:24:07'),
(6,'Final Term','Final Term','Final Term',100.00,40.00,'2026-06-10',12,1,1,0,'2026-06-09 11:24:07');
/*!40000 ALTER TABLE `assessments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `status` enum('P','A','L') NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_att` (`student_id`,`subject_id`,`date`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_ibfk_4` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES
(1,17,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07'),
(2,18,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07'),
(3,19,12,1,'2026-05-22','A',NULL,1,'2026-06-09 11:24:07'),
(4,20,12,1,'2026-05-22','L',NULL,1,'2026-06-09 11:24:07');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_subjects`
--

DROP TABLE IF EXISTS `class_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cs` (`class_id`,`subject_id`),
  KEY `subject_id` (`subject_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `class_subjects_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_subjects_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_subjects`
--

LOCK TABLES `class_subjects` WRITE;
/*!40000 ALTER TABLE `class_subjects` DISABLE KEYS */;
INSERT INTO `class_subjects` VALUES
(1,12,1,1),
(2,12,2,2),
(3,12,3,3),
(4,12,4,4),
(5,12,5,5),
(6,12,6,6);
/*!40000 ALTER TABLE `class_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `grade` int(11) NOT NULL,
  `section` varchar(5) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES
(1,'8-A',8,'A'),
(2,'8-B',8,'B'),
(3,'8-C',8,'C'),
(4,'9-A',9,'A'),
(5,'9-B',9,'B'),
(6,'9-C',9,'C'),
(7,'10-A',10,'A'),
(8,'10-B',10,'B'),
(9,'10-C',10,'C'),
(10,'11-A',11,'A'),
(11,'11-B',11,'B'),
(12,'12-A',12,'A'),
(13,'12-B',12,'B');
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fees`
--

DROP TABLE IF EXISTS `fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 12000.00,
  `paid` tinyint(1) DEFAULT 0,
  `payment_date` date DEFAULT NULL,
  `payment_mode` enum('Cash','Bank','Online','Cheque') DEFAULT 'Cash',
  `receipt_no` varchar(50) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `paid_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee` (`student_id`,`month`,`year`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fees_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fees`
--

LOCK TABLES `fees` WRITE;
/*!40000 ALTER TABLE `fees` DISABLE KEYS */;
INSERT INTO `fees` VALUES
(1,1,5,2026,12000.00,1,'2026-05-20','Cash',NULL,NULL,28,'2026-06-09 11:24:07','2026-05-20'),
(2,2,5,2026,12000.00,1,'2026-05-19','Bank',NULL,NULL,28,'2026-06-09 11:24:07','2026-05-19'),
(3,3,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(4,4,5,2026,12000.00,1,'2026-05-17','Online',NULL,NULL,28,'2026-06-09 11:24:07','2026-05-17'),
(5,5,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(6,6,5,2026,12000.00,1,'2026-05-15','Cash',NULL,NULL,28,'2026-06-09 11:24:07','2026-05-15'),
(7,7,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(8,8,5,2026,12000.00,1,'2026-05-12','Bank',NULL,NULL,28,'2026-06-09 11:24:07','2026-05-12'),
(9,9,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(10,10,5,2026,12000.00,1,'2026-05-10','Cash',NULL,NULL,28,'2026-06-09 11:24:07','2026-05-10'),
(11,11,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(12,12,5,2026,12000.00,1,'2026-05-08','Online',NULL,NULL,28,'2026-06-09 11:24:07','2026-05-08'),
(13,13,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(14,14,5,2026,12000.00,1,'2026-05-05','Cash',NULL,NULL,28,'2026-06-09 11:24:07','2026-05-05'),
(15,15,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(16,16,5,2026,12000.00,1,'2026-05-03','Bank',NULL,NULL,28,'2026-06-09 11:24:07','2026-05-03'),
(17,17,5,2026,12000.00,1,'2026-05-21','Online',NULL,NULL,28,'2026-06-09 11:24:07','2026-05-21'),
(18,18,5,2026,12000.00,1,'2026-05-16','Cash',NULL,NULL,28,'2026-06-09 11:24:07','2026-05-16'),
(19,19,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(20,20,5,2026,12000.00,1,'2026-05-11','Bank',NULL,NULL,28,'2026-06-09 11:24:07','2026-05-11');
/*!40000 ALTER TABLE `fees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marks`
--

DROP TABLE IF EXISTS `marks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `marks_obtained` decimal(6,2) DEFAULT NULL,
  `entered_by` int(11) DEFAULT NULL,
  `entered_at` timestamp NULL DEFAULT current_timestamp(),
  `remarks` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mark` (`student_id`,`assessment_id`),
  KEY `assessment_id` (`assessment_id`),
  KEY `entered_by` (`entered_by`),
  CONSTRAINT `marks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marks_ibfk_2` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marks_ibfk_3` FOREIGN KEY (`entered_by`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marks`
--

LOCK TABLES `marks` WRITE;
/*!40000 ALTER TABLE `marks` DISABLE KEYS */;
INSERT INTO `marks` VALUES
(1,17,1,47.00,1,'2026-06-09 11:24:07','Good'),
(2,18,1,42.00,1,'2026-06-09 11:24:07',NULL),
(3,19,1,38.00,1,'2026-06-09 11:24:07',NULL),
(4,20,1,44.00,1,'2026-06-09 11:24:07',NULL),
(5,17,4,36.00,1,'2026-06-09 11:24:07',NULL),
(6,18,4,39.00,1,'2026-06-09 11:24:07',NULL),
(7,19,4,28.00,1,'2026-06-09 11:24:07',NULL),
(8,20,4,41.00,1,'2026-06-09 11:24:07',NULL);
/*!40000 ALTER TABLE `marks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notices`
--

DROP TABLE IF EXISTS `notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `priority` enum('normal','important','urgent') DEFAULT 'normal',
  `audience` set('students','teachers','finance','admin') NOT NULL DEFAULT 'students,teachers,finance,admin',
  `pinned` tinyint(1) DEFAULT 0,
  `author_id` int(11) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `author_id` (`author_id`),
  CONSTRAINT `notices_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notices`
--

LOCK TABLES `notices` WRITE;
/*!40000 ALTER TABLE `notices` DISABLE KEYS */;
INSERT INTO `notices` VALUES
(1,'Final Examination Schedule â€” June 2026','Term 2 Finals commence 10 June 2026. All students must report 30 minutes early. Roll number slips distributed by class teachers.','Exam','urgent','students,teachers,finance,admin',1,22,'2026-06-10','2026-06-09 11:24:07'),
(2,'Parent-Teacher Meeting â€” 27 May 2026','PTM scheduled Wednesday 27 May, 09:00 AMâ€“01:00 PM in Main Hall. Parents requested to meet class teachers.','General','normal','students,teachers,finance,admin',0,22,'2026-05-27','2026-06-09 11:24:07'),
(3,'Eid-ul-Adha Holidays â€” 30 May to 3 June 2026','College closed 30 Mayâ€“3 June 2026 for Eid-ul-Adha. Resumes 4 June 2026.','Holiday','normal','students,teachers,finance,admin',1,22,'2026-06-04','2026-06-09 11:24:07'),
(4,'Annual Sports Day â€” 7 June 2026','Register with House In-Charge by 28 May 2026. Events: Athletics, Cricket, Football, Table Tennis, Badminton.','Sports','normal','students',0,22,'2026-06-07','2026-06-09 11:24:07'),
(5,'Staff Meeting â€” 26 May 2026','All teaching staff required to attend monthly staff meeting on Monday 26 May at 02:00 PM in Conference Room.','Staff','important','teachers,admin',1,22,'2026-05-26','2026-06-09 11:24:07'),
(6,'Mid-Term Result Submission Deadline','All subject teachers must submit Mid-Term marks on the portal by 25 May 2026.','Academic','urgent','teachers,admin',0,22,'2026-05-25','2026-06-09 11:24:07'),
(7,'Monthly Fee Collection Report â€” April 2026','April 2026 fee collection completed with 89% collection rate. Finance team to finalize reconciliation by 25 May 2026.','Finance','important','finance,admin',1,22,'2026-05-25','2026-06-09 11:24:07'),
(8,'System Maintenance â€” 25 May 2026','Portal maintenance from 11:00 PM to 02:00 AM on 25â€“26 May. System unavailable during this window.','System','important','admin',1,22,'2026-05-26','2026-06-09 11:24:07'),
(9,'Test Notice','Test body content','General','normal','students,teachers',0,22,NULL,'2026-06-09 18:30:40');
/*!40000 ALTER TABLE `notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `profile_change_requests`
--

DROP TABLE IF EXISTS `profile_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_change_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `field` varchar(50) NOT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_note` varchar(255) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `field_name` varchar(50) GENERATED ALWAYS AS (`field`) VIRTUAL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `profile_change_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `profile_change_requests_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `profile_change_requests`
--

LOCK TABLES `profile_change_requests` WRITE;
/*!40000 ALTER TABLE `profile_change_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `profile_change_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_name` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_name` (`key_name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES
(1,'school_name','Bahria Model College','2026-06-09 11:24:07'),
(2,'session_year','2025-26','2026-06-09 11:24:07'),
(3,'current_term','Term 2','2026-06-09 11:24:07'),
(4,'fee_per_month','12000','2026-06-09 11:24:07'),
(5,'min_attendance','75','2026-06-09 11:24:07'),
(6,'smtp_host','smtp.gmail.com','2026-06-09 13:01:23'),
(7,'smtp_port','587','2026-06-09 13:01:23'),
(8,'smtp_user','','2026-06-09 13:01:23'),
(9,'smtp_pass','','2026-06-09 13:01:23'),
(10,'smtp_from','','2026-06-09 13:01:23'),
(11,'smtp_from_name','BMC Portal','2026-06-09 13:01:23'),
(12,'smtp_enabled','0','2026-06-09 13:01:23');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `roll_no` varchar(20) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `cnic` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `parent_email` varchar(100) DEFAULT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `admission_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `roll_no` (`roll_no`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `students_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES
(1,1,'801',1,'Zafar Ahmed','2012-04-12','male','35202-1122334-1','03001112233','House 5, Street 3, Johar Town, Lahore','03007788990','zafar.parent@gmail.com','Zafar Ahmed','2026-06-09'),
(2,2,'802',1,'Yousaf Saleem','2012-09-27','female','35202-2233445-2','03002223344','House 22, Block D, Model Town, Lahore','03006677889','yousaf.s@gmail.com','Yousaf Saleem','2026-06-09'),
(3,3,'803',1,'Mahmood Shah','2012-01-15','male','35202-3344556-3','03003334455','House 44, Phase 6, DHA, Lahore','03005566778','mahmood.s@gmail.com','Mahmood Shah','2026-06-09'),
(4,4,'804',1,'Nawaz Hussain','2012-06-08','female','35202-4455667-4','03004445566','House 7, Gulberg III, Lahore','03004455667','nawaz.h@gmail.com','Nawaz Hussain','2026-06-09'),
(5,5,'901',5,'Akhtar Hussain','2011-03-20','male','42201-5566778-5','03005556677','Flat 9, Block 4, Gulshan-e-Iqbal, Karachi','03003344556','akhtar.h@gmail.com','Akhtar Hussain','2026-06-09'),
(6,6,'902',5,'Siddiqui Rashid','2011-11-05','female','42201-6677889-6','03006667788','House 33, Clifton Block 2, Karachi','03002233445','siddiqui.r@gmail.com','Siddiqui Rashid','2026-06-09'),
(7,7,'903',5,'Farooq Javed','2011-07-30','male','42201-7788990-7','03007778899','House 55, North Nazimabad, Karachi','03001122334','farooq.j@gmail.com','Farooq Javed','2026-06-09'),
(8,8,'904',5,'Rehman Bhayo','2011-04-14','female','42201-8899001-8','03008889900','House 17, PECHS Block 6, Karachi','03009900112','rehman.b@gmail.com','Rehman Bhayo','2026-06-09'),
(9,9,'1001',7,'Asif Zaman','2010-08-22','male','61101-9900112-9','03009990011','House 3, Hayatabad Ph-2, Peshawar','03008899001','asif.z@gmail.com','Asif Zaman','2026-06-09'),
(10,10,'1002',7,'Fatima Gul','2010-02-09','female','61101-0011223-0','03010001122','Street 7, University Town, Peshawar','03007788001','fatima.g@gmail.com','Fatima Gul','2026-06-09'),
(11,11,'1003',7,'Malik Gul','2010-05-17','male','61101-1122334-1','03011112233','House 12, Dalazak Road, Peshawar','03006677001','malik.g@gmail.com','Malik Gul','2026-06-09'),
(12,12,'1004',7,'Tariq Shah','2010-12-03','female','61101-2233445-2','03012223344','House 44, Warsak Road, Peshawar','03005566001','tariq.sh@gmail.com','Tariq Shah','2026-06-09'),
(13,13,'1101',10,'Raza Ali','2009-06-24','male','38401-3344556-3','03013334455','House 8, Jinnah Town, Quetta','03004455001','raza.a@gmail.com','Raza Ali','2026-06-09'),
(14,14,'1102',10,'Baloch Khan','2009-01-11','female','38401-4455667-4','03014445566','House 22, Samungli Road, Quetta','03003344001','baloch.k@gmail.com','Baloch Khan','2026-06-09'),
(15,15,'1103',10,'Abbasi Sahib','2009-10-28','male','38401-5566778-5','03015556677','House 35, Satellite Town, Quetta','03002233001','abbasi.s@gmail.com','Abbasi Sahib','2026-06-09'),
(16,16,'1104',10,'Kakar Sahib','2009-03-15','female','38401-6677889-6','03016667788','House 6, Airport Road, Quetta','03001122001','kakar.s@gmail.com','Kakar Sahib','2026-06-09'),
(17,17,'1201',12,'Qureshi Nadeem','2008-07-30','male','61501-7788990-7','03017778899','House 14, F-7/3, Islamabad','03009900001','qureshi.n@gmail.com','Qureshi Nadeem','2026-06-09'),
(18,18,'1202',12,'Baig Akhtar','2008-11-18','female','61501-8899001-8','03018889900','House 45, G-11/2, Islamabad','03008899001','baig.a@gmail.com','Baig Akhtar','2026-06-09'),
(19,19,'1203',12,'Ishaq Ahmad','2008-04-05','male','61501-9900112-9','03019990011','House 77, I-8/4, Islamabad','03007788001','ishaq.a@gmail.com','Ishaq Ahmad','2026-06-09'),
(20,20,'1204',12,'Chaudhry Riaz','2008-08-13','female','61501-0011223-0','03020001122','House 32, E-7, Islamabad','03006677001','chaudhry.r@gmail.com','Chaudhry Riaz','2026-06-09');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT INTO `subjects` VALUES
(1,'Physics','PHY'),
(2,'Mathematics','MAT'),
(3,'English','ENG'),
(4,'Chemistry','CHE'),
(5,'Biology','BIO'),
(6,'Computer Science','CS'),
(7,'Urdu','URD'),
(8,'Islamiat','ISL'),
(9,'Pakistan Studies','PKS');
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `emp_id` varchar(20) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `designation` varchar(100) DEFAULT 'Subject Teacher',
  `phone` varchar(20) DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `qualification` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `emp_id` (`emp_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teachers_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teachers`
--

LOCK TABLES `teachers` WRITE;
/*!40000 ALTER TABLE `teachers` DISABLE KEYS */;
INSERT INTO `teachers` VALUES
(1,21,'T001',1,'Senior Teacher',NULL,'2022-01-15',NULL),
(2,22,'T002',2,'Subject Teacher',NULL,'2021-03-10',NULL),
(3,23,'T003',3,'Subject Teacher',NULL,'2020-08-20',NULL),
(4,24,'T004',4,'Senior Teacher',NULL,'2019-09-05',NULL),
(5,25,'T005',5,'Subject Teacher',NULL,'2021-07-12',NULL),
(6,26,'T006',6,'Subject Teacher',NULL,'2023-02-01',NULL);
/*!40000 ALTER TABLE `teachers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `timetable`
--

DROP TABLE IF EXISTS `timetable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `timetable` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `day` enum('monday','tuesday','wednesday','thursday','friday') NOT NULL,
  `period` int(11) NOT NULL CHECK (`period` between 1 and 6),
  `subject_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tt` (`class_id`,`day`,`period`),
  KEY `subject_id` (`subject_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `timetable_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `timetable_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timetable`
--

LOCK TABLES `timetable` WRITE;
/*!40000 ALTER TABLE `timetable` DISABLE KEYS */;
INSERT INTO `timetable` VALUES
(1,12,'monday',1,1,1,'Lab-A'),
(2,12,'monday',2,3,3,'Room 201'),
(3,12,'monday',3,4,4,'Lab-B'),
(4,12,'monday',4,2,2,'Room 305'),
(5,12,'monday',5,5,5,'Room 212'),
(6,12,'monday',6,6,6,'Lab-C'),
(7,12,'tuesday',1,2,2,'Room 305'),
(8,12,'tuesday',2,5,5,'Room 212'),
(9,12,'tuesday',3,6,6,'Lab-C'),
(10,12,'tuesday',4,1,1,'Lab-A'),
(11,12,'tuesday',5,3,3,'Room 201'),
(12,12,'tuesday',6,4,4,'Lab-B'),
(13,12,'wednesday',1,3,3,'Room 201'),
(14,12,'wednesday',2,1,1,'Lab-A'),
(15,12,'wednesday',3,4,4,'Lab-B'),
(16,12,'wednesday',4,2,2,'Room 305'),
(17,12,'wednesday',5,5,5,'Room 212'),
(18,12,'wednesday',6,6,6,'Lab-C'),
(19,12,'thursday',1,4,4,'Lab-B'),
(20,12,'thursday',2,6,6,'Lab-C'),
(21,12,'thursday',3,2,2,'Room 305'),
(22,12,'thursday',4,5,5,'Room 212'),
(23,12,'thursday',5,1,1,'Lab-A'),
(24,12,'thursday',6,3,3,'Room 201'),
(25,12,'friday',1,5,5,'Room 212'),
(26,12,'friday',2,2,2,'Room 305'),
(27,12,'friday',3,3,3,'Room 201'),
(28,12,'friday',4,4,4,'Lab-B'),
(29,12,'friday',5,6,6,'Lab-C'),
(30,12,'friday',6,1,1,'Lab-A');
/*!40000 ALTER TABLE `timetable` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(20) NOT NULL COMMENT 'Roll No / T001 / ADM001 / FIN001',
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','teacher','admin','finance') NOT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'BMC2025001','Arham Zafar','arham.zafar@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active','2026-08-01 09:00:00','2026-06-09 11:24:07',NULL,NULL),
(2,'BMC2025002','Maryam Yousaf','maryam.yousaf@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active','2026-08-01 09:00:00','2026-06-09 11:24:07',NULL,NULL),
(3,'BMC2025003','Talha Mahmood','talha.mahmood@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active','2026-08-01 09:00:00','2026-06-09 11:24:07',NULL,NULL),
(4,'BMC2025004','Hira Nawaz','hira.nawaz@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active','2026-08-01 09:00:00','2026-06-09 11:24:07',NULL,NULL),
(5,'BMC2025005','Farhan Akhtar','farhan.akhtar@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(6,'BMC2025006','Aiman Siddiqui','aiman.siddiqui@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(7,'BMC2025007','Umer Farooq','umer.farooq@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(8,'BMC2025008','Sadia Rehman','sadia.rehman@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(9,'BMC2025009','Bilal Asif','bilal.asif@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(10,'BMC2025010','Noor Fatima','noor.fatima@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(11,'BMC2025011','Hamza Malik','hamza.malik@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(12,'BMC2025012','Zoya Tariq','zoya.tariq@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(13,'BMC2025013','Saad Raza','saad.raza@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(14,'BMC2025014','Iqra Baloch','iqra.baloch@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(15,'BMC2025015','Waseem Abbasi','waseem.abbasi@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(16,'BMC2025016','Mehwish Kakar','mehwish.kakar@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(17,'BMC2025017','Rehan Qureshi','rehan.qureshi@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(18,'BMC2025018','Amna Baig','amna.baig@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(19,'BMC2025019','Kamran Ishaq','kamran.ishaq@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(20,'BMC2025020','Laiba Chaudhry','laiba.chaudhry@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(21,'T001','Dr. Sarah Khan','sarah@bmc.edu.pk','$2y$12$QAjKdAD4ip7DlW3SCDRGxeJt62KfJjsrB8.1oYo/5AnZX3ob90cUO','teacher','active','2026-06-09 19:05:41','2026-06-09 11:24:07',NULL,NULL),
(22,'T002','Mr. Hasan Ali','hasan@bmc.edu.pk','$2y$12$QAjKdAD4ip7DlW3SCDRGxeJt62KfJjsrB8.1oYo/5AnZX3ob90cUO','teacher','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(23,'T003','Ms. Nadia Raza','nadia@bmc.edu.pk','$2y$12$QAjKdAD4ip7DlW3SCDRGxeJt62KfJjsrB8.1oYo/5AnZX3ob90cUO','teacher','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(24,'T004','Dr. Amina Siddiqui','amina@bmc.edu.pk','$2y$12$QAjKdAD4ip7DlW3SCDRGxeJt62KfJjsrB8.1oYo/5AnZX3ob90cUO','teacher','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(25,'T005','Mr. Imran Hassan','imran@bmc.edu.pk','$2y$12$QAjKdAD4ip7DlW3SCDRGxeJt62KfJjsrB8.1oYo/5AnZX3ob90cUO','teacher','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(26,'T006','Mr. Farhan Ahmed','farhan@bmc.edu.pk','$2y$12$QAjKdAD4ip7DlW3SCDRGxeJt62KfJjsrB8.1oYo/5AnZX3ob90cUO','teacher','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(27,'ADM001','Mr. Tariq Mehmood','admin@bmc.edu.pk','$2y$12$sD0pAOjdeVxnb38dt9SRL.HWIkHfVk26iPlZ58EBy.TjbQZFo6Fl.','admin','active','2026-06-09 19:06:32','2026-06-09 11:24:07',NULL,NULL),
(28,'FIN001','Ms. Ayesha Rizvi','finance@bmc.edu.pk','$2y$12$KcUMkYlad7b5uhqkkPfKOuCVxIwl1R8txkFYNBxfc9lqh/SgrD3Qi','finance','active','2026-06-09 19:05:41','2026-06-09 11:24:07',NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-13 10:12:23
