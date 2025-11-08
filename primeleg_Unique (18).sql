-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 08, 2025 at 12:31 AM
-- Server version: 10.5.29-MariaDB
-- PHP Version: 8.4.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `primeleg_Unique`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_classes`
--

CREATE TABLE `academic_classes` (
  `id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `section` enum('primary','secondary') NOT NULL,
  `level` enum('nursery','primary','jss','sss') DEFAULT NULL,
  `capacity` int(11) DEFAULT 30,
  `current_students` int(11) DEFAULT 0,
  `class_teacher_id` int(11) DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_classes`
--

INSERT INTO `academic_classes` (`id`, `class_name`, `section`, `level`, `capacity`, `current_students`, `class_teacher_id`, `room_number`, `status`, `created_at`) VALUES
(2, 'Jss1', 'primary', 'nursery', 30, 0, 3, '4', 'active', '2025-11-05 11:30:57');

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `max_marks` decimal(5,2) DEFAULT 10.00,
  `file_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `subject_id`, `title`, `description`, `due_date`, `max_marks`, `file_path`, `created_by`, `created_at`) VALUES
(1, 1, 'Algebra Basics', 'Solve questions 1-5 from page 23 of your textbook. Show all working.', '2025-11-14 00:07:27', 10.00, NULL, 1, '2025-11-07 00:07:27'),
(2, 1, 'Fractions Practice', 'Complete the fractions worksheet attached.', '2025-11-10 00:07:27', 10.00, NULL, 1, '2025-11-07 00:07:27'),
(3, 2, 'Geography Quiz', 'Answer all questions about African countries.', '2025-11-12 00:07:27', 10.00, NULL, 1, '2025-11-07 00:07:27'),
(4, 1, 'Basic Programming Concepts', 'Write a simple program to calculate the area of a circle. Show all your code and explanations.', '2025-11-15 23:59:00', 15.00, NULL, 1, '2025-11-07 11:21:20'),
(5, 1, 'Database Design', 'Design a database schema for a library system. Include tables for books, members, and borrow records.', '2025-11-20 23:59:00', 20.00, NULL, 1, '2025-11-07 11:21:20'),
(6, 2, 'African Countries Map', 'Label all African countries on the provided map and list their capital cities.', '2025-11-12 23:59:00', 10.00, NULL, 1, '2025-11-07 11:21:20'),
(7, 2, 'Climate Zones', 'Explain the different climate zones in Africa with examples of countries in each zone.', '2025-11-18 23:59:00', 15.00, NULL, 1, '2025-11-07 11:21:20'),
(8, 1, 'Basic Programming Concepts', 'Write a simple program to calculate the area of a circle. Show all your code and explanations.', '2025-11-15 23:59:00', 15.00, NULL, 1, '2025-11-07 11:24:43'),
(9, 1, 'Database Design', 'Design a database schema for a library system. Include tables for books, members, and borrow records.', '2025-11-20 23:59:00', 20.00, NULL, 1, '2025-11-07 11:24:43'),
(10, 2, 'African Countries Map', 'Label all African countries on the provided map and list their capital cities.', '2025-11-12 23:59:00', 10.00, NULL, 1, '2025-11-07 11:24:43'),
(11, 2, 'Climate Zones', 'Explain the different climate zones in Africa with examples of countries in each zone.', '2025-11-18 23:59:00', 15.00, NULL, 1, '2025-11-07 11:24:43');

-- --------------------------------------------------------

--
-- Table structure for table `assignment_submissions`
--

CREATE TABLE `assignment_submissions` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submission_text` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `marks_awarded` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `status` enum('submitted','graded','late') DEFAULT 'submitted'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `assignment_submissions`
--

INSERT INTO `assignment_submissions` (`id`, `assignment_id`, `student_id`, `submission_text`, `file_path`, `submitted_at`, `marks_awarded`, `feedback`, `status`) VALUES
(1, 1, 1, 'I have completed the programming assignment. Please find my solution attached.', NULL, '2025-11-07 11:21:20', NULL, NULL, 'submitted'),
(2, 1, 2, 'I have completed the programming assignment. Please find my solution attached.', NULL, '2025-11-07 11:24:43', NULL, NULL, 'submitted'),
(3, 6, 2, '', '[{\"path\":\"uploads\\/receipts\\/assignment_6_student_2_1762516340_0.jpg\",\"name\":\"assignment_6_student_2_1762516340_0.jpg\",\"original_name\":\"1000248736.jpg\",\"size\":297221,\"type\":\"image\\/jpeg\"}]', '2025-11-07 11:52:20', NULL, NULL, 'submitted');

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `term` enum('all_terms','first_term','second_term','third_term') DEFAULT 'all_terms',
  `class_level` enum('all_classes','primary','jss1','jss2','jss3','ss1','ss2','ss3') DEFAULT 'all_classes'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `price`, `category`, `is_available`, `created_at`, `term`, `class_level`) VALUES
(1, 'Mathematics for Primary 4', 'Dr. John Smith', 25.00, 'Mathematics', 1, '2025-10-31 01:32:46', 'all_terms', 'all_classes');

-- --------------------------------------------------------

--
-- Table structure for table `class_notes`
--

CREATE TABLE `class_notes` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT 0,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `class_notes`
--

INSERT INTO `class_notes` (`id`, `subject_id`, `title`, `description`, `file_path`, `file_name`, `file_size`, `file_type`, `uploaded_by`, `uploaded_at`, `status`) VALUES
(1, 1, 'Programming Basics PDF', 'Complete notes on programming fundamentals', 'uploads/notes/programming_basics.pdf', 'programming_basics.pdf', 0, NULL, NULL, '2025-11-07 11:58:43', 'active'),
(2, 1, 'HTML Cheat Sheet', 'Quick reference for HTML tags', 'uploads/notes/html_cheatsheet.pdf', 'html_cheatsheet.pdf', 0, NULL, NULL, '2025-11-07 11:58:43', 'active'),
(3, 2, 'African Countries Map', 'Printable map of African countries', 'uploads/notes/africa_map.pdf', 'africa_map.pdf', 0, NULL, NULL, '2025-11-07 11:58:43', 'active'),
(4, 2, 'Geography Terms Glossary', 'Important geography terminology', 'uploads/notes/geography_glossary.pdf', 'geography_glossary.pdf', 0, NULL, NULL, '2025-11-07 11:58:43', 'active'),
(5, 1, 'Programming Basics PDF', 'Complete notes on programming fundamentals', 'uploads/notes/programming_basics.pdf', 'programming_basics.pdf', 2048000, NULL, NULL, '2025-11-07 12:01:17', 'active'),
(6, 1, 'HTML Cheat Sheet', 'Quick reference for HTML tags', 'uploads/notes/html_cheatsheet.pdf', 'html_cheatsheet.pdf', 1024000, NULL, NULL, '2025-11-07 12:01:17', 'active'),
(7, 1, 'CSS Styling Guide', 'Complete CSS styling reference', 'uploads/notes/css_guide.pdf', 'css_guide.pdf', 1536000, NULL, NULL, '2025-11-07 12:01:17', 'active'),
(8, 2, 'African Countries Map', 'Printable map of African countries', 'uploads/notes/africa_map.pdf', 'africa_map.pdf', 2560000, NULL, NULL, '2025-11-07 12:01:17', 'active'),
(9, 2, 'Geography Terms Glossary', 'Important geography terminology', 'uploads/notes/geography_glossary.pdf', 'geography_glossary.pdf', 1280000, NULL, NULL, '2025-11-07 12:01:17', 'active'),
(10, 2, 'Climate Zones Study Guide', 'Detailed climate zone explanations', 'uploads/notes/climate_zones.pdf', 'climate_zones.pdf', 1792000, NULL, NULL, '2025-11-07 12:01:17', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `class_teachers`
--

CREATE TABLE `class_teachers` (
  `id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `section` enum('primary','secondary') NOT NULL,
  `staff_id` int(11) NOT NULL,
  `academic_year` varchar(20) DEFAULT '2024/2025',
  `subjects` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_teachers`
--

INSERT INTO `class_teachers` (`id`, `class_name`, `section`, `staff_id`, `academic_year`, `subjects`, `status`, `created_at`) VALUES
(1, 'Jss1', 'primary', 3, '2025/2026', '1', 'active', '2025-11-05 11:46:41');

-- --------------------------------------------------------

--
-- Table structure for table `course_assignments`
--

CREATE TABLE `course_assignments` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assigned_to_type` enum('student','staff') NOT NULL,
  `assigned_to_id` int(11) NOT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT '2024/2025',
  `assigned_by` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `event_type` enum('academic','social','sports','cultural','other') DEFAULT 'academic',
  `audience` enum('all','students','staff','parents','specific_classes') DEFAULT 'all',
  `specific_classes` text DEFAULT NULL COMMENT 'JSON array for class restrictions',
  `requires_payment` tinyint(1) DEFAULT 0,
  `payment_amount` decimal(10,2) DEFAULT 0.00,
  `max_participants` int(11) DEFAULT NULL,
  `status` enum('active','cancelled','completed') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `event_date`, `event_time`, `venue`, `event_type`, `audience`, `specific_classes`, `requires_payment`, `payment_amount`, `max_participants`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'End of the year', 'Hosbsu', '2025-11-05', '15:06:00', 'School', 'sports', 'all', NULL, 1, 0.50, NULL, 'active', 1, '2025-11-05 14:06:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('pending','paid','approved','rejected') DEFAULT 'pending',
  `payment_amount` decimal(10,2) NOT NULL,
  `receipt_image` varchar(255) DEFAULT NULL,
  `receipt_filename` varchar(255) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fee_transactions`
--

CREATE TABLE `fee_transactions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `term` enum('first_term','second_term','third_term') NOT NULL,
  `fee_type` enum('tuition','books','stationery','event','other') DEFAULT 'tuition',
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_type` enum('fee_issued','payment','adjustment') NOT NULL,
  `status` enum('unpaid','paid','cancelled') DEFAULT 'unpaid',
  `due_date` date DEFAULT NULL,
  `receipt_image` varchar(255) DEFAULT NULL,
  `receipt_filename` varchar(255) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `fee_transactions`
--

INSERT INTO `fee_transactions` (`id`, `student_id`, `term`, `fee_type`, `description`, `amount`, `transaction_type`, `status`, `due_date`, `receipt_image`, `receipt_filename`, `admin_notes`, `approved_by`, `approved_at`, `created_at`) VALUES
(1, 10, 'first_term', 'tuition', 'Tuition Fee', 25000.00, 'fee_issued', 'unpaid', '2025-12-06', NULL, NULL, NULL, NULL, NULL, '2025-11-06 12:34:05'),
(2, 10, 'first_term', 'tuition', 'Past payment - cash', 21000.00, 'payment', 'paid', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-06 00:00:00'),
(3, 10, 'first_term', 'tuition', 'Tuition Fee', 10000.00, 'fee_issued', 'unpaid', '2025-12-06', NULL, NULL, NULL, NULL, NULL, '2025-11-06 13:20:15'),
(4, 1, 'first_term', 'tuition', 'Tuition Fee', 10000.00, 'fee_issued', 'unpaid', '2025-12-06', NULL, NULL, NULL, NULL, NULL, '2025-11-06 21:10:07'),
(5, 1, 'first_term', 'tuition', 'Tuition Fee', 2000.00, 'fee_issued', 'unpaid', '2025-12-06', NULL, NULL, NULL, NULL, NULL, '2025-11-06 21:24:10'),
(6, 2, 'first_term', 'tuition', 'Tuition Fee', 1080.00, 'fee_issued', 'unpaid', '2025-12-06', NULL, NULL, NULL, NULL, NULL, '2025-11-06 21:46:00'),
(7, 2, 'first_term', 'tuition', 'Fee Payment - cash (Ref: Ushshshabahah)', 1080.00, 'payment', 'paid', NULL, 'uploads/fee_receipts/fee_receipt_2_1762465704.jpg', 'fee_receipt_2_1762465704.jpg', NULL, 1, '2025-11-06 21:49:11', '2025-11-06 21:48:24');

-- --------------------------------------------------------

--
-- Table structure for table `lecture_links`
--

CREATE TABLE `lecture_links` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `youtube_url` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `posted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `lecture_links`
--

INSERT INTO `lecture_links` (`id`, `subject_id`, `title`, `youtube_url`, `description`, `posted_at`) VALUES
(1, 1, 'Introduction to Algebra', 'https://www.youtube.com/watch?v=abc123', 'Watch this to better understand algebraic expressions', '2025-11-07 00:07:27'),
(2, 2, 'African Geography', 'https://www.youtube.com/watch?v=xyz789', 'Learn about African countries and their capitals', '2025-11-07 00:07:27'),
(3, 1, 'Programming Basics', 'https://www.youtube.com/watch?v=k3aWFAsB4y0', 'Introduction to programming concepts and logic', '2025-11-07 11:58:43'),
(4, 1, 'HTML & CSS Fundamentals', 'https://www.youtube.com/watch?v=qz0aGYrrlhU', 'Learn the basics of web development', '2025-11-07 11:58:43'),
(5, 2, 'African Geography Overview', 'https://www.youtube.com/watch?v=Wdd7RQJywog', 'Complete overview of African continent geography', '2025-11-07 11:58:43'),
(6, 2, 'Climate Patterns in Africa', 'https://www.youtube.com/watch?v=4eQH_6cbDMA', 'Understanding weather and climate zones', '2025-11-07 11:58:43'),
(7, 1, 'Programming Basics', 'https://www.youtube.com/watch?v=k3aWFAsB4y0', 'Introduction to programming concepts and logic', '2025-11-07 12:01:17'),
(8, 1, 'HTML & CSS Fundamentals', 'https://www.youtube.com/watch?v=qz0aGYrrlhU', 'Learn the basics of web development', '2025-11-07 12:01:17'),
(9, 1, 'JavaScript Tutorial', 'https://www.youtube.com/watch?v=W6NZfCO5SIk', 'JavaScript programming for beginners', '2025-11-07 12:01:17'),
(10, 2, 'African Geography Overview', 'https://www.youtube.com/watch?v=Wdd7RQJywog', 'Complete overview of African continent geography', '2025-11-07 12:01:17'),
(11, 2, 'Climate Patterns in Africa', 'https://www.youtube.com/watch?v=4eQH_6cbDMA', 'Understanding weather and climate zones', '2025-11-07 12:01:17'),
(12, 2, 'African Rivers and Lakes', 'https://www.youtube.com/watch?v=K2W1xSFTg7c', 'Major water bodies in Africa', '2025-11-07 12:01:17');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','failed') DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `student_id`, `payment_type`, `amount`, `status`, `due_date`, `created_at`) VALUES
(1, 1, 'Tuition Fee', 500.00, 'pending', '2025-11-30', '2025-10-31 01:32:46');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL,
  `purchase_request_id` int(11) NOT NULL,
  `item_type` enum('book','stationery') NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `class` varchar(50) NOT NULL,
  `items` text NOT NULL COMMENT 'JSON array of purchased items',
  `total_amount` decimal(10,2) NOT NULL,
  `receipt_image` varchar(255) DEFAULT NULL,
  `receipt_filename` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `purchase_date` date NOT NULL,
  `admin_notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `staff_id` varchar(20) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `state_of_origin` varchar(100) DEFAULT NULL,
  `marital_status` varchar(20) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `employment_date` date DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `subject_specialization` text DEFAULT NULL,
  `qualifications` text DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `photo` varchar(255) DEFAULT NULL,
  `added_by` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `first_name`, `last_name`, `email`, `staff_id`, `department`, `position`, `gender`, `state_of_origin`, `marital_status`, `religion`, `employment_date`, `salary`, `phone`, `emergency_contact`, `address`, `date_of_birth`, `subject_specialization`, `qualifications`, `account_name`, `account_number`, `bank_name`, `account_updated_at`, `photo`, `added_by`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Justice', 'King', 'justiceking006@gmail.com', 'STAFF20259907', 'Academic', 'Teacher', NULL, NULL, NULL, NULL, NULL, NULL, '+2348161431114', NULL, NULL, NULL, 'Computer, Data Processing, Data Processing', NULL, 'Justice ', '182728277', 'First Bank ', '2025-11-04 18:26:28', NULL, NULL, 'active', '2025-11-01 22:40:15', '2025-11-04 18:26:28'),
(3, 'Omotayo', 'Kemi', 'omo@gmail.com', 'STAFF20250125', 'Academic', 'Teacher', 'Female', 'Oyo', 'Married', NULL, '2025-11-04', NULL, '0809764521', '08079645281', 'Ajara', '2025-11-04', 'English, English, Business Studies, English, English, Government', 'Nce', NULL, NULL, NULL, '2025-11-04 18:24:00', NULL, NULL, 'active', '2025-11-04 17:22:06', '2025-11-04 17:59:10');

-- --------------------------------------------------------

--
-- Table structure for table `staff_attendance`
--

CREATE TABLE `staff_attendance` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `status` enum('present','absent','late','leave') DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_leaves`
--

CREATE TABLE `staff_leaves` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `leave_type` enum('sick','casual','annual','maternity','paternity','emergency') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_payments`
--

CREATE TABLE `staff_payments` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_period` varchar(50) NOT NULL,
  `gross_salary` decimal(10,2) NOT NULL,
  `tax_deduction` decimal(10,2) DEFAULT 0.00,
  `pension_deduction` decimal(10,2) DEFAULT 0.00,
  `other_deductions` decimal(10,2) DEFAULT 0.00,
  `net_pay` decimal(10,2) NOT NULL,
  `payment_method` enum('bank_transfer','cash','cheque') DEFAULT 'bank_transfer',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `receipt_filename` varchar(255) DEFAULT NULL,
  `status` enum('pending','paid','failed') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_payments`
--

INSERT INTO `staff_payments` (`id`, `staff_id`, `payment_date`, `payment_period`, `gross_salary`, `tax_deduction`, `pension_deduction`, `other_deductions`, `net_pay`, `payment_method`, `transaction_ref`, `receipt_filename`, `status`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, '2025-11-04', 'November 2025', 50000.00, 0.00, 0.00, 0.00, 50000.00, 'bank_transfer', 'Ushshshabahah', NULL, 'paid', 'Okay.', 1, '2025-11-04 18:27:21'),
(2, 1, '2025-11-04', 'November 2025', 12000.00, 0.00, 0.00, 0.00, 12000.00, 'bank_transfer', NULL, 'receipt_1762281685_1.jpg', 'paid', '', 1, '2025-11-04 18:41:26');

-- --------------------------------------------------------

--
-- Table structure for table `stationery_items`
--

CREATE TABLE `stationery_items` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) DEFAULT 'stationery',
  `stock_quantity` int(11) DEFAULT 0,
  `min_stock_level` int(11) DEFAULT 10,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stationery_items`
--

INSERT INTO `stationery_items` (`id`, `item_name`, `description`, `price`, `category`, `stock_quantity`, `min_stock_level`, `is_available`, `created_at`) VALUES
(1, 'Exercise Book - 40 leaves', '40 leaves exercise book', 400.00, 'stationery', 100, 10, 1, '2025-11-03 19:45:17'),
(2, 'Exercise Book - 60 leaves', '60 leaves exercise book', 600.00, 'stationery', 100, 10, 1, '2025-11-03 19:45:17'),
(3, 'Exercise Book - Higher Education', 'Higher education exercise book', 1000.00, 'stationery', 50, 10, 1, '2025-11-03 19:45:17'),
(4, 'Pen', 'Writing pen', 100.00, 'stationery', 200, 10, 1, '2025-11-03 19:45:17'),
(5, 'Pencil', 'Writing pencil', 100.00, 'stationery', 200, 10, 1, '2025-11-03 19:45:17'),
(6, 'Eraser', 'Pencil eraser', 100.00, 'stationery', 150, 10, 0, '2025-11-03 19:45:17'),
(7, 'Mathematical Set', 'Complete mathematical set', 1500.00, 'stationery', 30, 10, 1, '2025-11-03 19:45:17'),
(8, 'Graph Book', 'Graph paper book', 300.00, 'stationery', 80, 10, 1, '2025-11-03 19:45:17'),
(9, 'Drawing Book', 'Drawing sketch book', 300.00, 'stationery', 60, 10, 1, '2025-11-03 19:45:17'),
(10, 'Gum', 'Adhesive gum', 250.00, 'stationery', 100, 10, 1, '2025-11-03 19:45:17');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `section` enum('primary','secondary') NOT NULL,
  `level` enum('jss','sss') DEFAULT NULL,
  `class` varchar(50) NOT NULL,
  `department` enum('science','art','commercial') DEFAULT NULL,
  `guardian_name` varchar(200) NOT NULL,
  `guardian_phone` varchar(20) NOT NULL,
  `admission_receipt_image` varchar(255) DEFAULT NULL,
  `admission_receipt_filename` varchar(255) DEFAULT NULL,
  `admission_fee_paid` tinyint(1) DEFAULT 0,
  `admission_fee_amount` decimal(10,2) DEFAULT 3000.00,
  `student_code` varchar(20) NOT NULL,
  `student_pin` varchar(6) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `first_name`, `last_name`, `email`, `section`, `level`, `class`, `department`, `guardian_name`, `guardian_phone`, `admission_receipt_image`, `admission_receipt_filename`, `admission_fee_paid`, `admission_fee_amount`, `student_code`, `student_pin`, `status`, `created_at`, `approved_at`, `approved_by`) VALUES
(1, 'Justice', 'King', 'justiceking006@gmail.com', 'secondary', 'sss', 'SSS3', 'science', 'Goodness', '+2348161431114', NULL, NULL, 0, 3000.00, 'UBS2025D8C594C0', NULL, 'approved', '2025-10-31 00:42:52', NULL, NULL),
(2, 'Justice', 'King', 'jusyking.webdesign@gmail.com', 'secondary', 'sss', 'SSS1', 'science', 'Goodness', '+2348161431114', NULL, NULL, 0, 3000.00, 'UBS2025DF49DE17', '107342', 'approved', '2025-10-31 01:05:03', NULL, NULL),
(5, 'Oloyede', 'Iremide', 'iremideoloyede98@gmail.com', 'secondary', 'sss', 'SSS2', 'art', 'Mr&amp;Mrs OLOYEDE', '08053005288', NULL, NULL, 0, 3000.00, 'UBS2025C4BE44E1', '906110', 'pending', '2025-11-01 19:48:13', NULL, NULL),
(6, 'Ayo', 'Ponmile2', 'ayo126@gmail.com', 'secondary', 'jss', 'JSS1', '', 'My mom', '+2348161431114', NULL, NULL, 0, 3000.00, 'UBS2025C5786CCF', '617088', 'approved', '2025-11-04 10:14:21', '2025-11-04 10:14:21', 1),
(7, 'Jessi', 'King', 'justiceking0061@gmail.com', 'primary', 'jss', 'Primary 4', NULL, 'Goodness', '+2348161431114', NULL, NULL, 0, 3000.00, 'UBS2025232705AC', '265566', 'pending', '2025-11-05 15:36:03', NULL, NULL),
(8, 'Joan', 'Smith', 'ademola_are@yahoo.com', 'primary', 'jss', 'Primary 4', NULL, 'My dad&#039;s', '+2348161451114', NULL, NULL, 0, 3000.00, 'UBS202548C7FA80', '991474', 'pending', '2025-11-05 15:38:50', NULL, NULL),
(9, 'Justice', 'King', 'justiceking0106@gmail.com', 'primary', 'jss', 'Primary 4', NULL, 'My dad&#039;s', '+2348661431114', NULL, NULL, 0, 3000.00, 'UBS202590F759FE', '434039', 'pending', '2025-11-05 15:39:58', NULL, NULL),
(10, 'Asa', 'Ki', 'adsemola_are@yahoo.com', 'primary', 'jss', 'Primary 4', NULL, 'My dad&#039;s', '+2348161431114', 'uploads/receipts/receipt_UBS20259BB5A637_1762358456.jpg', 'receipt_UBS20259BB5A637_1762358456.jpg', 1, 3000.00, 'UBS20259BB5A637', '376617', 'approved', '2025-11-05 16:00:29', '2025-11-06 11:54:49', 1),
(11, 'Treasure', 'Somyu', 'tolu@gmail.com', 'primary', 'jss', 'Primary 4', NULL, 'My dad&#039;s', '+2348161431114', NULL, NULL, 0, 3000.00, 'UBS2025537B79B7', '971471', 'pending', '2025-11-06 10:48:20', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_course_enrollments`
--

CREATE TABLE `student_course_enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `term` enum('first_term','second_term','third_term') NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','dropped') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `student_course_enrollments`
--

INSERT INTO `student_course_enrollments` (`id`, `student_id`, `subject_id`, `class_name`, `term`, `academic_year`, `enrolled_at`, `status`) VALUES
(1, 1, 1, 'JSS1', 'first_term', '2024/2025', '2025-11-07 00:07:27', 'active'),
(2, 1, 2, 'JSS1', 'first_term', '2024/2025', '2025-11-07 00:07:27', 'active'),
(3, 1, 1, 'JSS1', 'first_term', '2024/2025', '2025-11-07 11:21:20', 'active'),
(4, 1, 2, 'JSS1', 'first_term', '2024/2025', '2025-11-07 11:21:20', 'active'),
(5, 2, 1, 'JSS1', 'first_term', '2024/2025', '2025-11-07 11:24:43', 'active'),
(6, 2, 2, 'JSS1', 'first_term', '2024/2025', '2025-11-07 11:24:43', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `student_documents`
--

CREATE TABLE `student_documents` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `document_type` enum('fee_receipt','assignment','admission_receipt','other') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT 0,
  `file_type` varchar(100) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','archived') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `student_documents`
--

INSERT INTO `student_documents` (`id`, `student_id`, `document_type`, `title`, `description`, `file_path`, `file_name`, `file_size`, `file_type`, `related_id`, `uploaded_at`, `status`) VALUES
(1, 2, 'fee_receipt', 'Fee Receipt - November 2025', NULL, 'uploads/fee_receipts/fee_receipt_2_1762465704.jpg', 'fee_receipt_2_1762465704.jpg', 0, NULL, 7, '2025-11-06 21:48:24', 'active'),
(2, 10, 'admission_receipt', 'Admission Fee Receipt', NULL, 'uploads/receipts/receipt_UBS20259BB5A637_1762358456.jpg', 'receipt_UBS20259BB5A637_1762358456.jpg', 0, NULL, NULL, '2025-11-05 16:00:29', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `student_fees`
--

CREATE TABLE `student_fees` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `term` enum('first_term','second_term','third_term') NOT NULL,
  `total_fee` decimal(10,2) NOT NULL,
  `academic_year` varchar(20) DEFAULT '2024/2025',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `student_fees`
--

INSERT INTO `student_fees` (`id`, `student_id`, `term`, `total_fee`, `academic_year`, `status`, `created_at`) VALUES
(1, 10, 'first_term', 50000.00, '2024/2025', 'active', '2025-11-06 12:33:21'),
(2, 1, 'first_term', 35000.00, '2024/2025', 'active', '2025-11-06 14:44:35'),
(3, 2, 'first_term', 52000.00, '2024/2025', 'active', '2025-11-06 21:45:48');

-- --------------------------------------------------------

--
-- Table structure for table `student_results`
--

CREATE TABLE `student_results` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `term` enum('first_term','second_term','third_term') NOT NULL,
  `academic_year` varchar(20) NOT NULL DEFAULT '2024/2025',
  `first_test` decimal(5,2) DEFAULT NULL,
  `second_test` decimal(5,2) DEFAULT NULL,
  `third_test` decimal(5,2) DEFAULT NULL,
  `exam_score` decimal(5,2) DEFAULT NULL,
  `total_score` decimal(5,2) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('core','elective','extra_curricular') DEFAULT 'core',
  `level` enum('primary','jss','sss') DEFAULT NULL,
  `department` enum('science','art','commercial') DEFAULT NULL,
  `credits` int(11) DEFAULT 1,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `description`, `category`, `level`, `department`, `credits`, `status`, `created_at`) VALUES
(1, 'Cs', 'Computer ', 'Junior computer', 'core', 'primary', NULL, 1, 'active', '2025-11-03 19:28:57'),
(2, 'Geo1', 'Geography ', 'Hsh', 'core', 'sss', 'science', 1, 'active', '2025-11-05 11:49:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('student','staff','admin') DEFAULT 'student',
  `staff_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `student_id`, `email`, `password`, `user_type`, `staff_id`) VALUES
(1, NULL, 'admin@uniquebrilliant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL),
(2, 1, 'justiceking006@gmail.com', '$2y$10$VBgCzLi5lLAn4MGnFa3wYelByfvlWkTXrBRXeRkFL1x/FZPjY57JW', 'student', NULL),
(3, 2, 'jusyking.webdesign@gmail.com', '$2y$10$UK26uFJsdRACN1IgdonI0OoOA/uZpMTi65BsQsJn4H2iN.3S3AJ.e', 'student', NULL),
(7, 5, 'iremideoloyede98@gmail.com', '$2y$10$yoY5lA0D0dJwy/vAL7iai.MWcDAfWdmmyI6GKlqWX8tGmDm0Kb77.', 'student', NULL),
(9, 6, 'ayo126@gmail.com', '$2y$10$PP0T/7PV2VIpH/J06wcUS.NEnQNgxK/jDwSpnQpNBI1Dy95fQQfne', 'student', NULL),
(11, NULL, 'omo@gmail.com', '$2y$10$O3e.Rb2poDkmJN3m91A/VOpZC2/rJKBF9ZzKKBhoqhLriQ9g/vMSG', 'staff', 3),
(12, 7, 'justiceking0061@gmail.com', '$2y$10$VxDexm0qaysl6V3FzfEzHeqmDKQOdtPis9GrML30xuhO3KBNw3FUK', 'student', NULL),
(13, 8, 'ademola_are@yahoo.com', '$2y$10$Rbo75jToxOO1cFvicRjKuu59nsRc1GQ/otawJbk7N6cg0P4Ao1LNy', 'student', NULL),
(14, 9, 'justiceking0106@gmail.com', '$2y$10$xBAWiiLlnXtYje4aklijJeSZksH84jH282M8/zz0o4BsQcClb4Kn.', 'student', NULL),
(15, 10, 'adsemola_are@yahoo.com', '$2y$10$/R.LCrqBP.o8jxJLveF3f.09WYao9.Np9JlB.r1gMyqG64YFk2Jje', 'student', NULL),
(16, 11, 'tolu@gmail.com', '$2y$10$EdzGrBYRbX5JN2WchkylluiyXRGEXRtn85UXMWSPV6ngI9xk4unIC', 'student', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_classes`
--
ALTER TABLE `academic_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_class` (`class_name`,`section`),
  ADD KEY `class_teacher_id` (`class_teacher_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `class_notes`
--
ALTER TABLE `class_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `class_teachers`
--
ALTER TABLE `class_teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_class_teacher` (`class_name`,`academic_year`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `course_assignments`
--
ALTER TABLE `course_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`subject_id`,`assigned_to_type`,`assigned_to_id`,`academic_year`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_date` (`event_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_registration` (`event_id`,`student_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `payment_status` (`payment_status`);

--
-- Indexes for table `fee_transactions`
--
ALTER TABLE `fee_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `lecture_links`
--
ALTER TABLE `lecture_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_request_id` (`purchase_request_id`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `staff_id` (`staff_id`);

--
-- Indexes for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`staff_id`,`attendance_date`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `staff_leaves`
--
ALTER TABLE `staff_leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `staff_payments`
--
ALTER TABLE `staff_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `stationery_items`
--
ALTER TABLE `stationery_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_code` (`student_code`);

--
-- Indexes for table `student_course_enrollments`
--
ALTER TABLE `student_course_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `student_documents`
--
ALTER TABLE `student_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_documents_type` (`student_id`,`document_type`),
  ADD KEY `idx_student_documents_uploaded` (`uploaded_at`);

--
-- Indexes for table `student_fees`
--
ALTER TABLE `student_fees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_term` (`student_id`,`term`,`academic_year`);

--
-- Indexes for table `student_results`
--
ALTER TABLE `student_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject_term` (`student_id`,`subject_id`,`term`,`academic_year`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_classes`
--
ALTER TABLE `academic_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `class_notes`
--
ALTER TABLE `class_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `class_teachers`
--
ALTER TABLE `class_teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `course_assignments`
--
ALTER TABLE `course_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `event_registrations`
--
ALTER TABLE `event_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fee_transactions`
--
ALTER TABLE `fee_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `lecture_links`
--
ALTER TABLE `lecture_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_leaves`
--
ALTER TABLE `staff_leaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_payments`
--
ALTER TABLE `staff_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stationery_items`
--
ALTER TABLE `stationery_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `student_course_enrollments`
--
ALTER TABLE `student_course_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_documents`
--
ALTER TABLE `student_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `student_fees`
--
ALTER TABLE `student_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_results`
--
ALTER TABLE `student_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_classes`
--
ALTER TABLE `academic_classes`
  ADD CONSTRAINT `academic_classes_ibfk_1` FOREIGN KEY (`class_teacher_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD CONSTRAINT `assignment_submissions_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  ADD CONSTRAINT `assignment_submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `class_notes`
--
ALTER TABLE `class_notes`
  ADD CONSTRAINT `class_notes_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `class_teachers`
--
ALTER TABLE `class_teachers`
  ADD CONSTRAINT `class_teachers_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_assignments`
--
ALTER TABLE `course_assignments`
  ADD CONSTRAINT `course_assignments_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD CONSTRAINT `event_registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_registrations_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fee_transactions`
--
ALTER TABLE `fee_transactions`
  ADD CONSTRAINT `fee_transactions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lecture_links`
--
ALTER TABLE `lecture_links`
  ADD CONSTRAINT `lecture_links_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD CONSTRAINT `purchase_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD CONSTRAINT `staff_attendance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_leaves`
--
ALTER TABLE `staff_leaves`
  ADD CONSTRAINT `staff_leaves_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_leaves_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `staff_payments`
--
ALTER TABLE `staff_payments`
  ADD CONSTRAINT `staff_payments_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_course_enrollments`
--
ALTER TABLE `student_course_enrollments`
  ADD CONSTRAINT `student_course_enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `student_course_enrollments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `student_documents`
--
ALTER TABLE `student_documents`
  ADD CONSTRAINT `student_documents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `student_fees`
--
ALTER TABLE `student_fees`
  ADD CONSTRAINT `student_fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_results`
--
ALTER TABLE `student_results`
  ADD CONSTRAINT `student_results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_results_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_results_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
