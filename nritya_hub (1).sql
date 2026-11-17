-- MariaDB dump 10.19  Distrib 10.4.24-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: nritya_hub
-- ------------------------------------------------------
-- Server version	10.4.24-MariaDB

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
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `event_date` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--



LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
INSERT INTO `announcements` VALUES (1,'Autumn Recital 2026','Annual Autumn Recital on 18 October at Kalamandir Main Auditorium. Registrations and rehearsals begin this week.','18 Oct 2026',10,'2026-11-02 07:48:30'),(2,'Workshop on Navarasas & Abhinaya','Special intensive weekend workshop conducted by guest vidwans for intermediate and advanced students.','05 Nov 2026',10,'2026-11-02 07:48:30'),(3,'Tala & Nattuvangam Special Masterclass','Masterclass on thalam cycles and reciting sollukattu for Bharatanatyam dancers.','20 Nov 2026',10,'2026-11-02 07:48:30');
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent','Excused') NOT NULL DEFAULT 'Present',
  `marked_by` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance_record` (`batch_id`,`student_id`,`attendance_date`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (1,1,5,'2026-10-27','Present',2,'Class Adavu drill','2026-11-02 07:48:30'),(2,1,6,'2026-10-27','Present',2,'Class Adavu drill','2026-11-02 07:48:30'),(3,1,7,'2026-10-27','Absent',2,'Informed absence','2026-11-02 07:48:30'),(4,1,5,'2026-10-29','Present',2,'Tala rhythm work','2026-11-02 07:48:30'),(5,1,6,'2026-10-29','Absent',2,'Sick leave','2026-11-02 07:48:30'),(6,1,7,'2026-10-29','Present',2,'Tala rhythm work','2026-11-02 07:48:30'),(7,1,5,'2026-10-31','Present',2,'Hastaprana practice','2026-11-02 07:48:30'),(8,1,6,'2026-10-31','Present',2,'Hastaprana practice','2026-11-02 07:48:30'),(9,1,7,'2026-10-31','Excused',2,'School exam conflict','2026-11-02 07:48:30'),(10,1,5,'2026-11-01','Present',2,'Recital rehearsal','2026-11-02 07:48:30'),(11,1,6,'2026-11-01','Present',2,'Recital rehearsal','2026-11-02 07:48:30'),(12,1,7,'2026-11-01','Present',2,'Recital rehearsal','2026-11-02 07:48:30'),(13,1,20,'2026-11-02','Present',11,'','2026-11-02 07:59:57'),(14,1,21,'2026-11-02','Absent',11,'Fever','2026-11-02 07:59:57'),(15,1,22,'2026-11-02','Excused',11,'School exam','2026-11-02 07:59:57');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `batch_students`
--

DROP TABLE IF EXISTS `batch_students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `batch_students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Inactive') DEFAULT 'Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_batch_student` (`batch_id`,`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `batch_students`
--

LOCK TABLES `batch_students` WRITE;
/*!40000 ALTER TABLE `batch_students` DISABLE KEYS */;
INSERT INTO `batch_students` VALUES (1,1,20,'2026-11-02 07:45:28','Active'),(2,1,21,'2026-11-02 07:45:28','Active'),(3,1,22,'2026-11-02 07:45:28','Active'),(4,2,23,'2026-11-02 07:45:28','Active'),(5,2,24,'2026-11-02 07:45:28','Active'),(6,2,25,'2026-11-02 07:45:28','Active'),(7,1,5,'2026-11-02 07:48:30','Active'),(8,1,6,'2026-11-02 07:48:30','Active'),(9,1,7,'2026-11-02 07:48:30','Active'),(10,2,8,'2026-11-02 07:48:30','Active'),(11,2,9,'2026-11-02 07:48:30','Active'),(12,2,10,'2026-11-02 07:48:30','Active'),(13,1,27,'2026-11-02 08:02:24','Active');
/*!40000 ALTER TABLE `batch_students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `batches`
--

DROP TABLE IF EXISTS `batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_name` varchar(100) NOT NULL,
  `dance_style` varchar(50) NOT NULL,
  `level` varchar(50) NOT NULL,
  `instructor_id` int(11) DEFAULT NULL,
  `capacity` int(11) NOT NULL DEFAULT 24,
  `studio` varchar(50) DEFAULT 'Studio 1',
  `days` varchar(100) DEFAULT 'Tue, Thu, Sat',
  `start_time` varchar(20) DEFAULT '6:00 PM',
  `end_time` varchar(20) DEFAULT '7:00 PM',
  `monthly_tuition` varchar(50) DEFAULT '?2,400',
  `status` enum('Active','Inactive','Full') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `batches`
--

LOCK TABLES `batches` WRITE;
/*!40000 ALTER TABLE `batches` DISABLE KEYS */;
INSERT INTO `batches` VALUES (1,'Bharatanatyam - Foundation F1','Bharatanatyam','Foundation',11,24,'Studio 2','Tue, Thu, Sat','6:00 PM','7:00 PM','?2,400','Active','2026-11-02 07:45:28'),(2,'Bharatanatyam - Foundation F2','Bharatanatyam','Foundation',11,24,'Studio 1','Tue, Thu, Sat','4:30 PM','5:30 PM','?2,400','Active','2026-11-02 07:45:28'),(3,'Bharatanatyam - Intermediate I1','Bharatanatyam','Intermediate',12,20,'Studio 1','Mon, Wed, Fri','5:00 PM','6:30 PM','?3,000','Active','2026-11-02 07:45:28'),(4,'Bharatanatyam - Advanced A1','Bharatanatyam','Advanced',4,15,'Studio 3','Mon, Wed, Fri','6:30 PM','8:00 PM','₹3,500','Active','2026-11-02 07:48:30');
/*!40000 ALTER TABLE `batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fees_records`
--

DROP TABLE IF EXISTS `fees_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fees_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_type` enum('student_fee','staff_salary') NOT NULL DEFAULT 'student_fee',
  `user_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `amount` varchar(50) NOT NULL,
  `due_date` varchar(50) DEFAULT NULL,
  `status` enum('Paid','Pending','Overdue','Calculated') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fees_records`
--

LOCK TABLES `fees_records` WRITE;
/*!40000 ALTER TABLE `fees_records` DISABLE KEYS */;
INSERT INTO `fees_records` VALUES (1,'student_fee',5,1,'October Monthly Tuition','₹2,400','10 Oct 2026','Paid','2026-11-02 07:48:30'),(2,'student_fee',5,1,'Autumn Recital & Costume Fee','₹3,500','25 Oct 2026','Paid','2026-11-02 07:48:30'),(3,'student_fee',6,1,'October Monthly Tuition','₹2,400','10 Oct 2026','Pending','2026-11-02 07:48:30'),(4,'student_fee',7,1,'October Monthly Tuition','₹2,400','10 Oct 2026','Paid','2026-11-02 07:48:30'),(5,'student_fee',8,2,'October Monthly Tuition','₹2,400','10 Oct 2026','Overdue','2026-11-02 07:48:30'),(6,'staff_salary',11,1,'October Instructor Payout','₹45,000','31 Oct 2026','Calculated','2026-11-02 07:48:30'),(7,'staff_salary',3,3,'October Instructor Payout','₹35,000','31 Oct 2026','Calculated','2026-11-02 07:48:30'),(8,'staff_salary',4,2,'October Instructor Payout','₹35,000','31 Oct 2026','Calculated','2026-11-02 07:48:30'),(9,'staff_salary',26,NULL,'Monthly Instructor Salary','₹40,000 / mo',NULL,'Calculated','2026-11-02 08:02:24'),(10,'student_fee',20,1,'October Monthly Tuition','?2,400','10 Oct 2026','Paid','2026-11-02 08:03:05'),(11,'student_fee',20,1,'Autumn Recital Fee','?3,500','25 Oct 2026','Paid','2026-11-02 08:03:05'),(12,'student_fee',21,1,'October Monthly Tuition','?2,400','10 Oct 2026','Pending','2026-11-02 08:03:05'),(13,'student_fee',22,1,'October Monthly Tuition','?2,400','10 Oct 2026','Paid','2026-11-02 08:03:05'),(14,'student_fee',23,2,'October Monthly Tuition','?2,400','10 Oct 2026','Overdue','2026-11-02 08:03:05'),(15,'student_fee',24,2,'October Monthly Tuition','?2,400','10 Oct 2026','Pending','2026-11-02 08:03:05');
/*!40000 ALTER TABLE `fees_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homework`
--

DROP TABLE IF EXISTS `homework`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homework` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `topic` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `reference_media_url` varchar(255) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT 'video',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homework`
--

LOCK TABLES `homework` WRITE;
/*!40000 ALTER TABLE `homework` DISABLE KEYS */;
INSERT INTO `homework` VALUES (1,1,11,'Thattadavu Steps 1–5','Tatta Adavu','Record 1-2 minutes performing steps 1 to 5 with proper aramandi stance and clear mudras.','2026-11-10','uploads/references/thattadavu_demo.mp4','video','2026-11-02 07:48:30'),(2,1,11,'Mooshika Vahana Sloka','Sloka & Mudras','Recitation and mudra demonstration with correct Sanskrit pronunciation.','2026-11-15','uploads/references/sloka_notes.pdf','document','2026-11-02 07:48:30'),(3,2,11,'Hasta Mudras Basics','Asamyutta Hastas','Record continuous recitation and mudras 1 through 15.','2026-11-12','uploads/references/hasta_demo.mp4','video','2026-11-02 07:48:30');
/*!40000 ALTER TABLE `homework` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homework_reviews`
--

DROP TABLE IF EXISTS `homework_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homework_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submission_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `feedback` text DEFAULT NULL,
  `next_step` text DEFAULT NULL,
  `checklist` longtext DEFAULT NULL,
  `status` enum('Draft','Published') DEFAULT 'Published',
  `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_submission_review` (`submission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homework_reviews`
--

LOCK TABLES `homework_reviews` WRITE;
/*!40000 ALTER TABLE `homework_reviews` DISABLE KEYS */;
INSERT INTO `homework_reviews` VALUES (1,1,11,'Your rhythm is settling beautifully! Practice relaxing your shoulders during the 3rd speed set.','Practice a comfortable araimandi for 2 minutes daily before recording.','[{\"id\":\"1\",\"title\":\"Anga Shuddham\",\"description\":\"Body posture and clarity of form\",\"isChecked\":true},{\"id\":\"2\",\"title\":\"Aramandi\",\"description\":\"Proper half-sitting stance maintenance\",\"isChecked\":true},{\"id\":\"3\",\"title\":\"Mudras Clarity\",\"description\":\"Hand gesture accuracy and precision\",\"isChecked\":true},{\"id\":\"4\",\"title\":\"Bhavam\",\"description\":\"Expressive facial and emotional engagement\",\"isChecked\":false},{\"id\":\"5\",\"title\":\"Steps\",\"description\":\"Correct execution of adavu steps\",\"isChecked\":true},{\"id\":\"6\",\"title\":\"Tala\",\"description\":\"Rhythm and time cycle synchronization\",\"isChecked\":true},{\"id\":\"7\",\"title\":\"Leg Posture\",\"description\":\"Foot placement and leg positioning\",\"isChecked\":true},{\"id\":\"8\",\"title\":\"Neatness\",\"description\":\"Cleanliness of movements and execution\",\"isChecked\":true},{\"id\":\"9\",\"title\":\"Body Stiffness\",\"description\":\"Flexibility and absence of unwanted tension\",\"isChecked\":false},{\"id\":\"10\",\"title\":\"Back Position\",\"description\":\"Straight spine and torso alignment\",\"isChecked\":true}]','Published','2026-11-02 07:48:53'),(2,4,11,'Wonderful rhythm and clear aramandi stance! Keep arms steady in the second speed.','Practice 3 sets of aramandi holds for 2 minutes each day.','[{\"id\":\"1\",\"title\":\"Anga Shuddham\",\"description\":\"Body posture and clarity of form\",\"isChecked\":true},{\"id\":\"2\",\"title\":\"Aramandi\",\"description\":\"Proper half-sitting stance maintenance\",\"isChecked\":true},{\"id\":\"3\",\"title\":\"Mudras Clarity\",\"description\":\"Hand gesture accuracy and precision\",\"isChecked\":true},{\"id\":\"4\",\"title\":\"Bhavam\",\"description\":\"Expressive facial engagement\",\"isChecked\":true},{\"id\":\"5\",\"title\":\"Steps\",\"description\":\"Correct execution of adavu steps\",\"isChecked\":true},{\"id\":\"6\",\"title\":\"Tala\",\"description\":\"Rhythm synchronization\",\"isChecked\":true},{\"id\":\"7\",\"title\":\"Leg Posture\",\"description\":\"Foot placement\",\"isChecked\":true},{\"id\":\"8\",\"title\":\"Neatness\",\"description\":\"Cleanliness of movements\",\"isChecked\":true},{\"id\":\"9\",\"title\":\"Body Stiffness\",\"description\":\"Flexibility\",\"isChecked\":false},{\"id\":\"10\",\"title\":\"Back Position\",\"description\":\"Straight spine alignment\",\"isChecked\":true}]','Published','2026-11-02 08:01:26');
/*!40000 ALTER TABLE `homework_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homework_submissions`
--

DROP TABLE IF EXISTS `homework_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homework_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `homework_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `student_id` int(11) NOT NULL,
  `topic` varchar(150) NOT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_details` varchar(100) DEFAULT NULL,
  `student_note` text DEFAULT NULL,
  `status` enum('Pending','Reviewed') DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homework_submissions`
--

LOCK TABLES `homework_submissions` WRITE;
/*!40000 ALTER TABLE `homework_submissions` DISABLE KEYS */;
INSERT INTO `homework_submissions` VALUES (1,1,1,20,'Tatta Adavu','uploads/submissions/ananya_tatta_adavu.mp4','ananya_tatta_adavu.mp4','01:18 · 24 MB · Upload complete','My count feels steadier now. I would love help keeping my shoulders relaxed in the last set.','Reviewed','2026-11-01 19:40:00'),(2,1,1,21,'Tatta Adavu','uploads/submissions/arjun_tatta_adavu.mp4','arjun_tatta_adavu.mp4','00:58 · 18 MB · Upload complete','Working on keeping steady speed in the second kalam.','Pending','2026-11-02 10:15:00'),(3,3,2,8,'Hasta Mudras Basics','uploads/submissions/kavya_hastas.mp4','kavya_hastas.mp4','00:52 · 14 MB · Upload complete','Practiced Pataka to Kapittha.','Pending','2026-11-02 11:30:00'),(4,NULL,1,20,'Tatta Adavu Practice','uploads/submissions/submission_20.mp4','submission_20.mp4','01:18 · 24 MB · Upload complete','Steady practice','Reviewed','2026-11-02 08:01:16');
/*!40000 ALTER TABLE `homework_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_image` varchar(255) DEFAULT NULL,
  `role` enum('admin','instructor','student') NOT NULL DEFAULT 'student',
  `staff_role` varchar(100) DEFAULT NULL,
  `monthly_salary` varchar(50) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,

  
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `mobile` (`mobile`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Ananya Sharma','priyakumar5743','9876543210','ananya@exomle.com','$2y$10$Ie.sAESRZJU9zo/UIyQFZOdF0gm./Sc/ZdFkydx/l/9/HyiIYEC9W','2026-10-27 07:27:08','uploads/profiles/bfd88b6eef1f87813e347f33957c9bf6.jpg','admin','Founder & Chief Administrator','₹65,000 / mo',NULL,'Founder and Chief Administrator of Sri Kanchi Shethira Kala Mandir.','active',NULL),(2,'Ananya Kumar','ananyakumar707040','9876543211','ananya@example.com','$2y$10$Ie.sAESRZJU9zo/UIyQFZOdF0gm./Sc/ZdFkydx/l/9/HyiIYEC9W','2026-10-27 07:41:08',NULL,'instructor','Senior Bharatanatyam Instructor','₹45,000 / mo',1,'I help young dancers build a strong foundation in Bharatanatyam, with care for posture, rhythm and expression.','active',NULL),(4,'Lakshmi Narayanan','lakshmi.narayanan','9876543223','lakshmi.narayanan@nritahub.com','$2y$10$Ie.sAESRZJU9zo/UIyQFZOdF0gm./Sc/ZdFkydx/l/9/HyiIYEC9W','2026-11-02 07:48:30',NULL,'instructor','Abhinaya Specialist','₹35,000 / mo',2,'Senior Abhinaya and Sanskrit Natyashastra theory instructor.','active',NULL),(10,'Dr. Rukmini Devi','rukmini.admin','9876543220','rukmini.admin@nritahub.com','$2y$10$Ie.sAESRZJU9zo/UIyQFZOdF0gm./Sc/ZdFkydx/l/9/HyiIYEC9W','2026-11-02 07:45:28',NULL,'admin','Founder & Chief Administrator','?65,000 / mo',NULL,'Founder and Chief Administrator of Sri Kanchi Shethira Kala Mandir.','active',NULL),(11,'Meera Iyer','meera.iyer','9876543221','meera.iyer@nritahub.com','$2y$10$Ie.sAESRZJU9zo/UIyQFZOdF0gm./Sc/ZdFkydx/l/9/HyiIYEC9W','2026-11-02 07:45:28',NULL,'instructor','Senior Bharatanatyam Instructor','?45,000 / mo',NULL,'I help young dancers build a strong foundation in Bharatanatyam.','active',NULL),(12,'Siddharth Menon','siddharth.menon','9876543222','siddharth.menon@nritahub.com','$2y$10$Ie.sAESRZJU9zo/UIyQFZOdF0gm./Sc/ZdFkydx/l/9/HyiIYEC9W','2026-11-02 07:45:28',NULL,'instructor','Nattuvangam & Rhythm Instructor','₹35,000 / mo',3,'Specialist in Tala systems and Nattuvangam accompaniment.','active',NULL),(20,'Ananya Rao','ananya.rao','9876543231','ananya.rao@nritahub.com','$2y$10$Ie.sAESRZJU9zo/UIyQFZOdF0gm./Sc/ZdFkydx/l/9/HyiIYEC9W','2026-11-02 07:45:28',NULL,'student',NULL,NULL,1,'Dedicated student of Bharatanatyam Foundation F1.','active',NULL),(21,'Arjun Varma','arjun.varma','9876543232','arjun.varma@nritahub.com','$2y$10$Ie.sAESRZJU9zo/UIyQFZOdF0gm./Sc/ZdFkydx/l/9/HyiIYEC9W','2026-11-02 07:45:28',NULL,'student',NULL,NULL,1,'Student in Bharatanatyam Foundation F1.','active',NULL),(22,'Diya Menon','diya.menon','9876543233','diya.menon@nritahub.com','$2y$10$Ie.sAESRZJU9zo/UIyQFZOdF0gm./Sc/ZdFkydx/l/9/HyiIYEC9W','2026-11-02 07:45:28',NULL,'student',NULL,NULL,1,'Student in Bharatanatyam Foundation F1.','active',NULL),(23,'Kavya Shah','kavya.shah','9876543234','kavya.shah@nritahub.com','$2y$10$Ie.sAESRZJU9zo/UIyQFZOdF0gm./Sc/ZdFkydx/l/9/HyiIYEC9W','2026-11-02 07:45:28',NULL,'student',NULL,NULL,2,'Student in Bharatanatyam Foundation F2.','active',NULL),(24,'Rohan Patel','rohan.patel','9876543235','rohan.patel@nritahub.com','$2y$10$Ie.sAESRZJU9zo/UIyQFZOdF0gm./Sc/ZdFkydx/l/9/HyiIYEC9W','2026-11-02 07:45:28',NULL,'student',NULL,NULL,2,'Student in Bharatanatyam Foundation F2.','active',NULL),(25,'Aditi Kulkarni','aditi.kulkarni','9876543236','aditi.kulkarni@nritahub.com','$2y$10$Ie.sAESRZJU9zo/UIyQFZOdF0gm./Sc/ZdFkydx/l/9/HyiIYEC9W','2026-11-02 07:45:28',NULL,'student',NULL,NULL,2,NULL,'active',NULL),(26,'Priya Sundaram','priyasundaram7745','9463692807','priya.staff@nritahub.com','$2y$10$7RGoPGpnpDGQaqGWS7lxTOyv/IDzBkRHx7DzX1.76OH7EnDVtmQty','2026-11-02 08:02:24',NULL,'instructor','Senior Abhinaya Instructor','₹40,000 / mo',NULL,'','active',10),(27,'Aarav Sharma','aaravsharma861','9476566259','aarav.sharma@nritahub.com','$2y$10$TjI/5qeBIQNlwOZqOaEYaO3qG9G2PqN1iwc3gBmyuW7C8DJdshHSO','2026-11-02 08:02:24',NULL,'student','','',1,'','active',11);
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

-- Dump completed on 2026-11-02 13:33:19
