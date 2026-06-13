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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES
(1,1,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07'),
(2,2,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07'),
(3,3,12,1,'2026-05-22','A',NULL,1,'2026-06-09 11:24:07'),
(4,4,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07'),
(5,5,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07'),
(6,6,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07'),
(7,7,12,1,'2026-05-22','A',NULL,1,'2026-06-09 11:24:07'),
(8,8,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07'),
(9,9,12,1,'2026-05-22','L',NULL,1,'2026-06-09 11:24:07'),
(10,10,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07'),
(11,11,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07'),
(12,12,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07'),
(13,13,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07'),
(14,14,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07'),
(15,15,12,1,'2026-05-22','P',NULL,1,'2026-06-09 11:24:07');
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fees`
--

LOCK TABLES `fees` WRITE;
/*!40000 ALTER TABLE `fees` DISABLE KEYS */;
INSERT INTO `fees` VALUES
(1,1,5,2026,12000.00,1,'2026-05-20','Cash',NULL,NULL,23,'2026-06-09 11:24:07','2026-05-20'),
(2,2,5,2026,12000.00,1,'2026-05-19','Bank',NULL,NULL,23,'2026-06-09 11:24:07','2026-05-19'),
(3,3,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(4,4,5,2026,12000.00,1,'2026-05-17','Online',NULL,NULL,23,'2026-06-09 11:24:07','2026-05-17'),
(5,5,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(6,6,5,2026,12000.00,1,'2026-05-15','Cash',NULL,NULL,23,'2026-06-09 11:24:07','2026-05-15'),
(7,7,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(8,8,5,2026,12000.00,1,'2026-05-12','Bank',NULL,NULL,23,'2026-06-09 11:24:07','2026-05-12'),
(9,9,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(10,10,5,2026,12000.00,1,'2026-05-10','Cash',NULL,NULL,23,'2026-06-09 11:24:07','2026-05-10'),
(11,11,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(12,12,5,2026,12000.00,1,'2026-05-08','Online',NULL,NULL,23,'2026-06-09 11:24:07','2026-05-08'),
(13,13,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL),
(14,14,5,2026,12000.00,1,'2026-05-05','Cash',NULL,NULL,23,'2026-06-09 11:24:07','2026-05-05'),
(15,15,5,2026,12000.00,0,NULL,'Cash',NULL,NULL,NULL,'2026-06-09 11:24:07',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marks`
--

LOCK TABLES `marks` WRITE;
/*!40000 ALTER TABLE `marks` DISABLE KEYS */;
INSERT INTO `marks` VALUES
(1,1,1,45.00,1,'2026-06-09 11:24:07','Good'),
(2,2,1,38.00,1,'2026-06-09 11:24:07','Average'),
(3,3,1,6.00,1,'2026-06-09 11:24:07',NULL),
(4,4,1,10.00,1,'2026-06-09 11:24:07',NULL),
(5,5,1,5.00,1,'2026-06-09 11:24:07',NULL),
(6,6,1,7.00,1,'2026-06-09 11:24:07',NULL),
(7,7,1,6.00,1,'2026-06-09 11:24:07',NULL),
(8,8,1,8.00,1,'2026-06-09 11:24:07',NULL),
(9,9,1,4.00,1,'2026-06-09 11:24:07',NULL),
(10,10,1,7.00,1,'2026-06-09 11:24:07',NULL),
(11,11,1,9.00,1,'2026-06-09 11:24:07',NULL),
(12,12,1,6.00,1,'2026-06-09 11:24:07',NULL),
(13,13,1,7.00,1,'2026-06-09 11:24:07',NULL),
(14,14,1,9.00,1,'2026-06-09 11:24:07',NULL),
(15,15,1,5.00,1,'2026-06-09 11:24:07',NULL),
(16,1,4,34.00,1,'2026-06-09 11:24:07',NULL),
(17,2,4,36.00,1,'2026-06-09 11:24:07',NULL),
(18,3,4,28.00,1,'2026-06-09 11:24:07',NULL),
(19,4,4,38.00,1,'2026-06-09 11:24:07',NULL),
(20,5,4,22.00,1,'2026-06-09 11:24:07',NULL),
(21,6,4,31.00,1,'2026-06-09 11:24:07',NULL),
(22,7,4,25.00,1,'2026-06-09 11:24:07',NULL),
(23,8,4,33.00,1,'2026-06-09 11:24:07',NULL),
(24,9,4,19.00,1,'2026-06-09 11:24:07',NULL),
(25,10,4,29.00,1,'2026-06-09 11:24:07',NULL),
(26,11,4,35.00,1,'2026-06-09 11:24:07',NULL),
(27,12,4,27.00,1,'2026-06-09 11:24:07',NULL),
(28,13,4,32.00,1,'2026-06-09 11:24:07',NULL),
(29,14,4,37.00,1,'2026-06-09 11:24:07',NULL),
(30,15,4,24.00,1,'2026-06-09 11:24:07',NULL);
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
(1,1,'101',12,'Muhammad Ali','2007-03-15','male',NULL,'0300-1234567','House 12, Street 5, Bahria Town','0321-9876543','mali@gmail.com','Muhammad Ali',NULL),
(2,2,'102',12,'Noor Ahmed','2007-07-22','female',NULL,'0301-2345678','House 34, Block C, Bahria Town','0322-8765432','noor@gmail.com','Noor Ahmed',NULL),
(3,3,'103',12,'Raza Khan','2007-01-10','male',NULL,'0302-3456789','Flat 5, Tower A, Bahria Town','0323-7654321','raza@gmail.com','Raza Khan',NULL),
(4,4,'104',12,'Shahid Hussain','2007-11-05','female',NULL,'0303-4567890','House 78, Street 3, Bahria Town','0324-6543210','shah@gmail.com','Shahid Hussain',NULL),
(5,5,'105',12,'Tariq Mehmood','2007-08-18','male',NULL,'0304-5678901','House 22, Block D, Bahria Town','0325-5432109','tariq@gmail.com','Tariq Mehmood',NULL),
(6,6,'106',12,'Iqbal Hussain','2007-06-25','female',NULL,'0305-6789012','House 56, Street 9, Bahria Town','0326-4321098','iqb@gmail.com','Iqbal Hussain',NULL),
(7,7,'107',12,'Qureshi Sahib','2007-04-12','male',NULL,'0306-7890123','House 88, Block E, Bahria Town','0327-3210987','qur@gmail.com','Qureshi Sahib',NULL),
(8,8,'108',12,'Zahid Ali','2007-09-30','female',NULL,'0307-8901234','House 10, Street 7, Bahria Town','0328-2109876','zah@gmail.com','Zahid Ali',NULL),
(9,9,'109',12,'Ghani Sahib','2007-12-14','male',NULL,'0308-9012345','House 43, Block F, Bahria Town','0329-1098765','gha@gmail.com','Ghani Sahib',NULL),
(10,10,'110',12,'Malik Sahib','2007-02-08','female',NULL,'0309-0123456','House 67, Street 2, Bahria Town','0330-0987654','mal@gmail.com','Malik Sahib',NULL),
(11,11,'111',12,'Khan Bahadur','2007-05-20','male',NULL,'0310-1234560','House 91, Block G, Bahria Town','0331-9876540','kha@gmail.com','Khan Bahadur',NULL),
(12,12,'112',12,'Ansari Sahib','2007-10-03','female',NULL,'0311-2345671','House 25, Street 6, Bahria Town','0332-8765431','ans@gmail.com','Ansari Sahib',NULL),
(13,13,'113',12,'Mehmood Sahib','2007-07-17','male',NULL,'0312-3456782','House 49, Block H, Bahria Town','0333-7654322','meh@gmail.com','Mehmood Sahib',NULL),
(14,14,'114',12,'Butt Sahib','2007-03-28','female',NULL,'0313-4567893','House 73, Street 1, Bahria Town','0334-6543213','but@gmail.com','Butt Sahib',NULL),
(15,15,'115',12,'Elahi Sahib','2007-09-11','male',NULL,'0314-5678904','House 97, Block I, Bahria Town','0335-5432104','ela@gmail.com','Elahi Sahib',NULL);
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
(1,16,'T001',1,'Senior Teacher',NULL,'2022-01-15',NULL),
(2,17,'T002',2,'Subject Teacher',NULL,'2021-03-10',NULL),
(3,18,'T003',3,'Subject Teacher',NULL,'2020-08-20',NULL),
(4,19,'T004',4,'Senior Teacher',NULL,'2019-09-05',NULL),
(5,20,'T005',5,'Subject Teacher',NULL,'2021-07-12',NULL),
(6,21,'T006',6,'Subject Teacher',NULL,'2023-02-01',NULL);
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
(1,'101','Ahmed Ali','ahmed@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active','2026-06-09 19:05:40','2026-06-09 11:24:07',NULL,NULL),
(2,'102','Fatima Noor','fatima@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(3,'103','Hassan Raza','hassan@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(4,'104','Iqra Shah','iqra@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(5,'105','Bilal Tariq','bilal@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(6,'106','Zara Iqbal','zara@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(7,'107','Saad Qureshi','saad@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(8,'108','Amna Zahid','amna@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(9,'109','Usman Ghani','usman@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(10,'110','Rabia Malik','rabia@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(11,'111','Faisal Khan','faisal@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(12,'112','Nida Ansari','nida@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(13,'113','Asad Mehmood','asad@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(14,'114','Sana Butt','sana@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(15,'115','Kamran Elahi','kamran@bmc.edu.pk','$2y$12$zcqiKYOO6J5.3z4sGULHU.MrRxtCGF.zR.C28qJC30mak0EtN5ByO','student','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(16,'T001','Dr. Sarah Khan','sarah@bmc.edu.pk','$2y$12$QAjKdAD4ip7DlW3SCDRGxeJt62KfJjsrB8.1oYo/5AnZX3ob90cUO','teacher','active','2026-06-09 19:05:41','2026-06-09 11:24:07',NULL,NULL),
(17,'T002','Mr. Hasan Ali','hasan@bmc.edu.pk','$2y$12$QAjKdAD4ip7DlW3SCDRGxeJt62KfJjsrB8.1oYo/5AnZX3ob90cUO','teacher','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(18,'T003','Ms. Nadia Raza','nadia@bmc.edu.pk','$2y$12$QAjKdAD4ip7DlW3SCDRGxeJt62KfJjsrB8.1oYo/5AnZX3ob90cUO','teacher','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(19,'T004','Dr. Amina Siddiqui','amina@bmc.edu.pk','$2y$12$QAjKdAD4ip7DlW3SCDRGxeJt62KfJjsrB8.1oYo/5AnZX3ob90cUO','teacher','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(20,'T005','Mr. Imran Hassan','imran@bmc.edu.pk','$2y$12$QAjKdAD4ip7DlW3SCDRGxeJt62KfJjsrB8.1oYo/5AnZX3ob90cUO','teacher','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(21,'T006','Mr. Farhan Ahmed','farhan@bmc.edu.pk','$2y$12$QAjKdAD4ip7DlW3SCDRGxeJt62KfJjsrB8.1oYo/5AnZX3ob90cUO','teacher','active',NULL,'2026-06-09 11:24:07',NULL,NULL),
(22,'ADM001','Mr. Tariq Mehmood','admin@bmc.edu.pk','$2y$12$sD0pAOjdeVxnb38dt9SRL.HWIkHfVk26iPlZ58EBy.TjbQZFo6Fl.','admin','active','2026-06-09 19:06:32','2026-06-09 11:24:07',NULL,NULL),
(23,'FIN001','Ms. Ayesha Rizvi','finance@bmc.edu.pk','$2y$12$KcUMkYlad7b5uhqkkPfKOuCVxIwl1R8txkFYNBxfc9lqh/SgrD3Qi','finance','active','2026-06-09 19:05:41','2026-06-09 11:24:07',NULL,NULL);
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
