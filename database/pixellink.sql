-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 01, 2025 at 05:14 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pixellink`
--

-- --------------------------------------------------------

--
-- Table structure for table `client_job_requests`
--

CREATE TABLE `client_job_requests` (
  `request_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `budget` varchar(100) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `posted_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('open','assigned','completed','cancelled') DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_job_requests`
--

INSERT INTO `client_job_requests` (`request_id`, `client_id`, `title`, `description`, `category_id`, `budget`, `deadline`, `posted_date`, `status`) VALUES
(1, 3, 'ต้องการออกแบบแบนเนอร์โฆษณา', 'แบนเนอร์สำหรับโปรโมทสินค้าใหม่ 5 ชิ้น ขนาดต่างๆ', 1, '2,000-5,000 บาท', '2025-06-20', '2025-06-07 15:44:37', 'assigned'),
(2, 5, 'พัฒนาหน้า Landing Page', 'สำหรับแคมเปญการตลาดใหม่ เน้นการแปลงผู้เข้าชมเป็นลูกค้า', 3, '15,000-30,000 บาท', '2025-07-15', '2025-06-07 15:44:37', 'assigned'),
(3, 3, 'จ้างนักออกแบบ Package Product', 'ออกแบบบรรจุภัณฑ์สินค้าใหม่ 3 ชิ้น', 1, '8,000-15,000 บาท', '2025-07-01', '2025-06-07 15:44:37', 'open');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `contract_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `designer_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `agreed_price` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `contract_status` enum('pending','active','completed','cancelled') DEFAULT 'pending',
  `payment_status` enum('pending','paid','partially_paid','refunded') DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`contract_id`, `request_id`, `designer_id`, `client_id`, `agreed_price`, `start_date`, `end_date`, `contract_status`, `payment_status`, `created_at`) VALUES
(1, 1, 2, 3, 3500.00, '2025-06-02', '2025-06-15', 'active', 'pending', '2025-06-09 14:41:52');

-- --------------------------------------------------------

--
-- Table structure for table `job_applications`
--

CREATE TABLE `job_applications` (
  `application_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `designer_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `proposal_text` text DEFAULT NULL,
  `offered_price` decimal(10,2) DEFAULT NULL,
  `application_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','accepted','rejected','cancelled') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_applications`
--

INSERT INTO `job_applications` (`application_id`, `request_id`, `designer_id`, `client_id`, `proposal_text`, `offered_price`, `application_date`, `status`) VALUES
(1, 1, 2, 3, 'สามารถออกแบบให้ตรงคอนเซ็ปต์และส่งงานได้ตามกำหนดครับ', 3500.00, '2025-06-07 15:44:37', 'pending'),
(2, 2, 2, 5, 'สนใจงาน Landing Page ครับ มีประสบการณ์ด้านนี้โดยตรง สามารถทำให้ติด SEO ได้', 20000.00, '2025-06-07 15:44:37', 'rejected'),
(3, 3, 4, 3, 'ถนัดงานออกแบบแพ็กเกจจิ้งครับ มีตัวอย่างผลงานให้ดู', 10000.00, '2025-06-07 15:44:37', 'pending'),
(4, 1, 2, 3, '0', 2500.00, '2025-06-09 07:07:29', 'rejected');

-- --------------------------------------------------------

--
-- Table structure for table `job_categories`
--

CREATE TABLE `job_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_categories`
--

INSERT INTO `job_categories` (`category_id`, `category_name`) VALUES
(1, 'Graphic Design'),
(5, 'Illustration'),
(4, 'Logo Design'),
(6, 'Photography'),
(2, 'UI/UX Design'),
(3, 'Web Development');

-- --------------------------------------------------------

--
-- Table structure for table `job_postings`
--

CREATE TABLE `job_postings` (
  `post_id` int(11) NOT NULL,
  `designer_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price_range` varchar(100) DEFAULT NULL,
  `posted_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive','completed') DEFAULT 'active',
  `client_id` int(11) NOT NULL,
  `main_image_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_postings`
--

INSERT INTO `job_postings` (`post_id`, `designer_id`, `title`, `description`, `category_id`, `price_range`, `posted_date`, `status`, `client_id`, `main_image_id`) VALUES
(1, 2, 'รับงาน UI/UX Design', 'ออกแบบเว็บไซต์และแอปพลิเคชันที่ใช้งานง่ายและสวยงาม', 2, '10,000-25,000 บาท', '2025-06-07 15:44:37', 'active', 0, NULL),
(2, 2, 'บริการออกแบบโลโก้', 'ออกแบบโลโก้สำหรับธุรกิจขนาดเล็กและสตาร์ทอัพ', 4, '3,000-8,000 บาท', '2025-06-07 15:44:37', 'active', 0, NULL),
(3, 4, 'รับวาดภาพประกอบดิจิทัล', 'รับงานภาพประกอบสำหรับหนังสือ, โฆษณา, เกม', 5, '5,000-15,000 บาท', '2025-06-07 15:44:37', 'active', 0, NULL),
(4, 7, 'รับออกแบบโปสเตอร์สินค้า', 'ออกแบบโปสเตอร์โฆษณาสินค้าแบบมืออาชีพ สะดุดตา', 1, '2,500–6,000 บาท', '2025-07-10 16:34:16', 'active', 0, NULL),
(14, 10, 'ทำอินโฟกราฟิกนำเสนอ', 'ออกแบบภาพอินโฟกราฟิกสำหรับพรีเซนต์หรือโซเชียลมีเดีย', 1, '3,000–8,000 บาท', '2025-07-10 16:40:37', 'active', 0, NULL),
(15, 10, 'วาดภาพประกอบนิทาน', 'วาดภาพประกอบแนวเด็กน่ารักสดใส สำหรับหนังสือนิทาน', 2, '5,000–12,000 บาท', '2025-07-10 16:43:43', 'active', 0, NULL),
(16, 4, 'วาดตัวละครแนวแฟนตาซี', 'รับวาดคาแรคเตอร์สไตล์เกม/อนิเมะแฟนตาซี', 2, '8,000–20,000 บาท', '2025-07-10 16:43:43', 'active', 0, NULL),
(17, 7, 'ออกแบบโลโก้แบรนด์แฟชั่น', 'สร้างโลโก้สำหรับแบรนด์เสื้อผ้าหรือแฟชั่นสมัยใหม่', 3, '4,000–10,000 บาท', '2025-07-10 16:43:43', 'active', 0, NULL),
(18, 7, 'โลโก้ธุรกิจท้องถิ่น', 'โลโก้เรียบง่าย เหมาะสำหรับร้านอาหาร คาเฟ่ และ SME', 3, '2,000–5,000 บาท', '2025-07-10 16:43:43', 'active', 0, NULL),
(19, 4, 'ถ่ายภาพโปรไฟล์', 'รับถ่ายภาพโปรไฟล์สำหรับใช้ในงานหรือโซเชียลมีเดีย', 4, '1,500–4,000 บาท', '2025-07-10 16:43:43', 'active', 0, NULL),
(20, 10, 'ถ่ายสินค้าเพื่อขายออนไลน์', 'ถ่ายภาพสินค้าพร้อมแต่งภาพ เหมาะกับตลาดออนไลน์', 4, '3,000–7,000 บาท', '2025-07-10 16:43:43', 'active', 0, NULL),
(21, 2, 'ออกแบบ UI เว็บไซต์', 'ดีไซน์หน้าเว็บให้สวยงาม น่าใช้งาน และตอบโจทย์ UX', 5, '10,000–25,000 บาท', '2025-07-10 16:43:43', 'active', 0, NULL),
(22, 4, 'พัฒนาเว็บไซต์ด้วย HTML/CSS', 'รับสร้างเว็บไซต์พื้นฐานด้วย HTML/CSS ตามแบบที่ลูกค้าต้องการ', 6, '8,000–20,000 บาท', '2025-07-10 16:43:43', 'active', 0, NULL),
(23, 2, 'ออกแบบป้ายงานบวช', 'รับออกแบบป้าย ทันสมัย,สีสันสดสวย,คุ้มราคา100%', 1, '500–3000 บาท', '2025-07-23 17:24:13', 'active', 0, 46);

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`log_id`, `user_id`, `action`, `details`, `ip_address`, `timestamp`) VALUES
(1, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-23 23:41:02'),
(2, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-23 23:41:10'),
(3, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-24 00:13:54'),
(4, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 00:14:02'),
(5, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 00:21:15'),
(6, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-24 00:26:42'),
(7, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 00:26:49'),
(8, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 00:28:20'),
(9, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-24 01:04:59'),
(10, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-24 01:05:15'),
(11, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 01:05:24'),
(12, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-24 01:07:40'),
(13, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-24 01:33:18'),
(14, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-24 01:36:37'),
(15, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 01:36:43'),
(16, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-24 13:21:56'),
(17, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 13:22:02'),
(18, 8, 'Login Successful', 'User logged in: chalida', '::1', '2025-06-24 13:24:52'),
(19, 2, 'Login Attempt Failed', 'Inactive account: khoapun', '::1', '2025-06-24 13:29:42'),
(20, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-24 13:29:51'),
(21, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 13:29:58'),
(22, 2, 'Login Attempt Failed', 'Inactive account: khoapun', '::1', '2025-06-24 13:33:15'),
(23, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 13:33:36'),
(24, 2, 'Login Attempt Failed', 'Inactive account: khoapun', '::1', '2025-06-24 13:34:04'),
(25, 7, 'Login Successful', 'User logged in: pakawat.in', '::1', '2025-06-24 13:34:19'),
(26, 7, 'Login Attempt Failed', 'Invalid user type: designer for user pakawat.in', '::1', '2025-06-24 13:34:19'),
(27, 2, 'Login Attempt Failed', 'Inactive account: khoapun', '::1', '2025-06-24 13:36:13'),
(28, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-06-24 13:36:34'),
(29, 2, 'Login Attempt Failed', 'Inactive account: khoapun', '::1', '2025-06-24 13:38:45'),
(30, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-06-24 13:38:51'),
(31, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 13:46:19'),
(32, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-06-24 13:46:27'),
(33, 2, 'Login Attempt Failed', 'Inactive account: khoapun', '::1', '2025-06-24 13:47:01'),
(34, 7, 'Login Successful', 'User logged in: pakawat.in', '::1', '2025-06-24 13:49:33'),
(35, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-06-24 13:49:42'),
(36, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 13:49:52'),
(37, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-06-24 13:50:18'),
(38, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-06-24 13:51:04'),
(39, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 13:51:14'),
(40, 8, 'Login Successful', 'User logged in: chalida', '::1', '2025-06-24 13:52:12'),
(41, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 13:52:22'),
(42, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 13:56:22'),
(43, 8, 'Login Attempt Failed', 'Account not approved: chalida', '::1', '2025-06-24 13:56:36'),
(44, 7, 'Login Attempt Failed', 'Incorrect password for: pakawat.in', '::1', '2025-06-24 13:57:34'),
(45, 7, 'Login Attempt Failed', 'Account not approved: pakawat.in', '::1', '2025-06-24 13:57:41'),
(46, 2, 'Login Attempt Failed', 'Inactive account: khoapun', '::1', '2025-06-24 13:57:49'),
(47, 2, 'Login Attempt Failed', 'Inactive account: khoapun', '::1', '2025-06-24 13:58:07'),
(48, 3, 'Login Attempt Failed', 'Account not approved: beer888', '::1', '2025-06-24 13:58:22'),
(49, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-24 13:58:28'),
(50, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 13:58:32'),
(51, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-06-24 13:58:57'),
(52, 2, 'Login Attempt Failed', 'Inactive account: khoapun', '::1', '2025-06-24 13:59:16'),
(53, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-06-24 13:59:33'),
(54, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 13:59:42'),
(55, 2, 'Login Attempt Failed', 'Account not approved: khoapun', '::1', '2025-06-24 14:00:05'),
(56, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 14:00:09'),
(57, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-06-24 14:00:23'),
(58, 7, 'Login Attempt Failed', 'Account not approved: pakawat.in', '::1', '2025-06-24 14:00:41'),
(59, 8, 'Login Attempt Failed', 'Account not approved: chalida', '::1', '2025-06-24 14:00:45'),
(60, 9, 'Login Attempt Failed', 'Account not approved: party', '::1', '2025-06-24 14:53:24'),
(61, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-24 14:53:32'),
(62, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 14:53:37'),
(63, 8, 'Login Attempt Failed', 'Incorrect password for: chalida', '::1', '2025-06-24 15:50:39'),
(64, 8, 'Login Attempt Failed', 'Incorrect password for: chalida', '::1', '2025-06-24 15:50:54'),
(65, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 15:51:08'),
(66, 8, 'Login Attempt Failed', 'Incorrect password for: chalida', '::1', '2025-06-24 15:52:06'),
(67, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 16:09:30'),
(68, 8, 'Login Attempt Failed', 'Incorrect password for: chalida', '::1', '2025-06-24 16:17:37'),
(69, 8, 'Login Attempt Failed', 'Incorrect password for: chalida', '::1', '2025-06-24 16:17:52'),
(70, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 16:21:24'),
(71, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 16:39:19'),
(72, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-24 17:18:39'),
(73, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 17:18:43'),
(74, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-06-24 17:19:53'),
(75, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-06-24 17:20:05'),
(76, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-06-24 17:25:53'),
(77, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-24 17:31:26'),
(78, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-06-24 17:31:36'),
(79, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-06-24 17:34:08'),
(80, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-06-24 17:42:04'),
(81, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-30 21:27:26'),
(82, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-30 21:29:33'),
(83, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-06-30 22:40:26'),
(84, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-03 15:43:27'),
(85, 7, 'Login Attempt Failed', 'Incorrect password for: pakawat.in', '::1', '2025-07-03 15:51:11'),
(86, 7, 'Login Attempt Failed', 'Account not approved: pakawat.in', '::1', '2025-07-03 15:51:24'),
(87, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-03 15:51:34'),
(88, 7, 'Login Successful', 'User logged in: pakawat.in', '::1', '2025-07-03 15:52:42'),
(89, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-03 15:53:32'),
(90, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-03 15:55:30'),
(91, 7, 'Login Successful', 'User logged in: pakawat.in', '::1', '2025-07-03 15:55:46'),
(92, 7, 'Login Successful', 'User logged in: pakawat.in', '::1', '2025-07-03 15:56:48'),
(93, 7, 'Login Successful', 'User logged in: pakawat.in', '::1', '2025-07-03 15:57:26'),
(94, 7, 'Login Successful', 'User logged in: pakawat.in', '::1', '2025-07-03 16:04:11'),
(95, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-03 16:05:04'),
(96, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-03 16:05:44'),
(97, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-07 16:25:23'),
(98, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-07 17:44:12'),
(99, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-07 17:47:09'),
(100, 7, 'Login Successful', 'User logged in: pakawat.in', '::1', '2025-07-07 17:47:19'),
(101, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-07 17:47:28'),
(102, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-07 17:49:05'),
(103, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-07 17:49:51'),
(104, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-07 17:50:23'),
(105, 12, 'Login Attempt Failed', 'Account not approved: TESTTTTT', '::1', '2025-07-07 21:55:43'),
(106, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-07 21:55:49'),
(107, 12, 'Login Successful', 'User logged in: TESTTTTT', '::1', '2025-07-07 21:56:08'),
(108, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-10 21:09:13'),
(109, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-10 21:12:19'),
(110, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 21:25:36'),
(111, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 21:26:31'),
(112, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 21:29:30'),
(113, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-10 21:31:35'),
(114, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-10 21:54:16'),
(115, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-10 21:55:25'),
(116, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 21:56:33'),
(117, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 21:57:08'),
(118, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 22:02:33'),
(119, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 22:02:56'),
(120, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 22:04:01'),
(121, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 22:14:58'),
(122, 7, 'Login Successful', 'User logged in: pakawat.in', '::1', '2025-07-10 22:16:08'),
(123, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 22:17:44'),
(124, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 22:20:19'),
(125, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 22:20:56'),
(126, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 22:23:07'),
(127, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 22:38:20'),
(128, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 22:44:35'),
(129, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 22:45:31'),
(130, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 23:06:27'),
(131, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 23:09:25'),
(132, 2, 'Login Attempt Failed', 'Incorrect password for: khoapun', '::1', '2025-07-10 23:10:40'),
(133, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 23:12:43'),
(134, 2, 'Login Attempt Failed', 'Incorrect password for: khoapun', '::1', '2025-07-10 23:16:05'),
(135, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 23:16:10'),
(136, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 23:21:58'),
(137, 2, 'Login Attempt Failed', 'Incorrect password for: khoapun', '::1', '2025-07-10 23:24:56'),
(138, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 23:25:02'),
(139, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 23:51:40'),
(140, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 23:56:44'),
(141, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 23:58:46'),
(142, 2, 'Login Attempt Failed', 'Incorrect password for: khoapun', '::1', '2025-07-10 23:59:08'),
(143, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-10 23:59:15'),
(144, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 00:03:57'),
(145, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 00:07:26'),
(146, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 00:11:49'),
(147, 2, 'Login Attempt Failed', 'Incorrect password for: khoapun', '::1', '2025-07-11 00:14:06'),
(148, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 00:14:12'),
(149, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 00:17:38'),
(150, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 00:23:46'),
(151, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 00:24:55'),
(152, NULL, 'Login Attempt Failed', 'Username not found: ิbeer888', '::1', '2025-07-11 00:31:56'),
(153, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 00:32:01'),
(154, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 00:33:33'),
(155, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 00:35:41'),
(156, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 00:40:28'),
(157, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 00:43:42'),
(158, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 00:44:27'),
(159, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 00:45:10'),
(160, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 00:45:51'),
(161, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 00:46:05'),
(162, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 00:46:22'),
(163, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 00:47:29'),
(164, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 00:48:51'),
(165, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 00:49:03'),
(166, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 00:50:51'),
(167, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 00:54:22'),
(168, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 00:58:14'),
(169, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 00:59:05'),
(170, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 01:00:21'),
(171, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 01:02:51'),
(172, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 01:05:20'),
(173, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-11 14:59:35'),
(174, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-11 14:59:51'),
(175, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-11 15:01:03'),
(176, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-12 21:07:26'),
(177, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-12 21:08:08'),
(178, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-12 21:09:57'),
(179, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-12 21:12:23'),
(180, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-12 21:13:50'),
(181, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-12 21:16:22'),
(182, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-12 21:17:26'),
(183, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-12 21:45:17'),
(184, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-12 21:46:09'),
(185, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-12 21:52:44'),
(186, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-12 22:01:20'),
(187, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-12 22:49:31'),
(188, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-12 23:42:55'),
(189, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-13 01:15:06'),
(190, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-13 02:18:40'),
(191, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-13 02:21:01'),
(192, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-13 02:21:31'),
(193, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 20:57:52'),
(194, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-07-14 20:58:50'),
(195, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-14 20:58:54'),
(196, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-14 21:24:47'),
(197, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-14 21:28:49'),
(198, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 21:32:24'),
(199, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-14 21:58:34'),
(200, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 21:59:48'),
(201, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-14 22:05:16'),
(202, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 22:10:23'),
(203, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-07-14 22:12:31'),
(204, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 22:12:36'),
(205, NULL, 'Login Attempt Failed', 'Username not found: ad', '::1', '2025-07-14 22:28:43'),
(206, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-07-14 22:28:54'),
(207, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-14 22:29:01'),
(208, 2, 'Login Attempt Failed', 'Incorrect password for: khoapun', '::1', '2025-07-14 22:31:13'),
(209, NULL, 'Login Attempt Failed', 'Username not found: khoa', '::1', '2025-07-14 22:31:18'),
(210, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 22:31:23'),
(211, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-07-14 22:41:19'),
(212, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 22:41:25'),
(213, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 22:42:54'),
(214, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 23:27:42'),
(215, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 23:27:59'),
(216, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 23:31:43'),
(217, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 23:38:44'),
(218, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 23:48:37'),
(219, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 23:48:59'),
(220, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 23:52:41'),
(221, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-14 23:53:08'),
(222, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-15 00:00:49'),
(223, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-15 00:14:17'),
(224, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-07-15 00:14:30'),
(225, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-07-15 00:22:44'),
(226, NULL, 'Login Attempt Failed', 'Username not found: asd', '::1', '2025-07-15 00:25:48'),
(227, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-15 00:26:06'),
(228, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-15 00:26:24'),
(229, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-15 00:33:42'),
(230, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-16 23:52:18'),
(231, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-23 23:02:23'),
(232, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-23 23:52:03'),
(233, 10, 'Login Attempt Failed', 'Account not approved: party888', '::1', '2025-07-24 00:38:34'),
(234, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-24 00:38:42'),
(235, 10, 'Login Successful', 'User logged in: party888', '::1', '2025-07-24 00:38:59'),
(236, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-24 00:42:01'),
(237, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-24 00:43:24'),
(238, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-24 01:19:10'),
(239, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-24 01:37:09'),
(240, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-25 23:02:47'),
(241, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-25 23:05:17'),
(242, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-25 23:09:45'),
(243, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-25 23:11:03'),
(244, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-25 23:13:02'),
(245, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-25 23:15:45'),
(246, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-25 23:21:05'),
(247, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-25 23:25:44'),
(248, 2, 'Login Attempt Failed', 'Incorrect password for: khoapun', '::1', '2025-07-26 00:02:09'),
(249, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-26 00:02:14'),
(250, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-26 00:04:40'),
(251, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-26 00:14:14'),
(252, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-26 00:19:03'),
(253, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-26 01:36:06'),
(254, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-26 01:36:45'),
(255, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-26 01:37:26'),
(256, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-26 22:38:44'),
(257, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-27 00:00:42'),
(258, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-27 00:59:09'),
(259, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-27 01:01:56'),
(260, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-27 01:04:26'),
(261, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-27 01:05:48'),
(262, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-27 01:06:22'),
(263, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-27 01:09:05'),
(264, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 14:04:56'),
(265, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 14:30:44'),
(266, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 15:34:11'),
(267, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 15:35:42'),
(268, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 15:39:30'),
(269, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 16:13:00'),
(270, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 16:17:28'),
(271, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 16:21:25'),
(272, 10, 'Login Successful', 'User logged in: party888', '::1', '2025-07-30 16:21:47'),
(273, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-07-30 16:22:07'),
(274, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 16:22:44'),
(275, 4, 'Login Successful', 'User logged in: anna', '::1', '2025-07-30 16:27:32'),
(276, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 16:29:27'),
(277, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 19:55:42'),
(278, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 21:08:49'),
(279, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 21:10:03'),
(280, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 21:30:03'),
(281, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 22:26:59'),
(282, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-07-30 22:43:23'),
(283, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-02 21:03:18'),
(284, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-02 21:33:11'),
(285, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-02 21:33:29'),
(286, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-02 21:50:31'),
(287, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-02 22:00:03'),
(288, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-02 22:02:02'),
(289, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-02 22:10:51'),
(290, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-02 22:13:02'),
(291, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-02 22:14:26'),
(292, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-02 22:31:11'),
(293, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-02 23:29:20'),
(294, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-02 23:45:46'),
(295, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-02 23:50:58'),
(296, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-03 00:02:31'),
(297, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-03 00:28:45'),
(298, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-04 15:42:09'),
(299, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-08-04 22:06:26'),
(300, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-08-05 08:43:11'),
(301, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-08-05 08:43:17'),
(302, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-05 08:43:40'),
(303, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-08-05 09:02:30'),
(304, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-05 09:07:00'),
(305, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-08-05 09:18:07'),
(306, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-05 09:19:13'),
(307, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-08-05 09:22:26'),
(308, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-05 09:23:40'),
(309, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-08-05 09:38:55'),
(310, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-05 09:44:13'),
(311, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-08-05 12:10:04'),
(312, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-05 12:10:25'),
(313, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-08-05 12:13:21'),
(314, 1, 'Login Attempt Failed', 'Incorrect password for: admin', '::1', '2025-08-05 12:13:30'),
(315, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-05 12:25:39'),
(316, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-08-05 12:42:40'),
(317, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-05 12:58:35'),
(318, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-08-05 12:59:57'),
(319, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-05 13:06:03'),
(320, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-05 13:06:53'),
(321, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-08-07 12:07:03'),
(322, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-07 12:08:50'),
(323, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-08-07 12:17:38'),
(324, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-07 12:19:27'),
(325, 1, 'Login Successful', 'User logged in: admin', '::1', '2025-08-07 13:39:11'),
(326, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-07 13:40:13'),
(327, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-26 18:02:04'),
(328, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-08-26 18:09:18'),
(329, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-26 18:21:07'),
(330, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-26 18:43:19'),
(331, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-26 18:51:12'),
(332, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-27 12:21:58'),
(333, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-27 12:30:50'),
(334, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-27 13:25:18'),
(335, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-27 13:27:56'),
(336, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-08-27 14:10:43'),
(337, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-08-27 14:16:11'),
(338, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-08-27 14:32:30'),
(339, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-28 14:53:30'),
(340, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-28 14:59:48'),
(341, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-28 15:06:58'),
(342, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-08-28 15:11:52'),
(343, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-08-28 15:15:52'),
(344, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-08-28 15:20:32'),
(345, 3, 'Login Attempt Failed', 'Incorrect password for: beer888', '::1', '2025-09-01 19:06:02'),
(346, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-09-01 19:06:09'),
(347, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-09-01 20:46:18'),
(348, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-09-01 20:51:45'),
(349, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-09-01 20:52:22'),
(350, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-09-01 21:13:42'),
(351, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-09-01 21:18:03'),
(352, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-09-01 21:21:29'),
(353, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-09-01 21:27:23'),
(354, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-09-01 21:51:41'),
(355, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-09-01 21:52:01'),
(356, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-09-01 21:52:58'),
(357, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-09-01 21:53:28'),
(358, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-09-01 21:54:09'),
(359, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-09-01 22:02:07'),
(360, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-09-01 22:02:18'),
(361, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-09-01 22:02:40'),
(362, 2, 'Login Successful', 'User logged in: khoapun', '::1', '2025-09-01 22:05:43'),
(363, NULL, 'Login Attempt Failed', 'Username not found: ิbeer888', '::1', '2025-09-01 22:11:59'),
(364, 3, 'Login Successful', 'User logged in: beer888', '::1', '2025-09-01 22:12:07');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=unread, 1=read'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `from_user_id`, `to_user_id`, `message`, `timestamp`, `is_read`) VALUES
(1, 3, 2, '55555', '2025-08-28 09:10:58', 1),
(2, 3, 2, '55555', '2025-08-28 09:11:00', 1),
(3, 3, 2, '55555', '2025-08-28 09:11:01', 1),
(4, 3, 2, '55555', '2025-08-28 09:11:02', 1),
(5, 3, 2, '55555', '2025-08-28 09:11:02', 1),
(6, 3, 2, '55555', '2025-08-28 09:11:02', 1),
(7, 3, 2, '55555', '2025-08-28 09:11:02', 1),
(8, 3, 2, '55555', '2025-08-28 09:11:10', 1),
(9, 3, 2, '55555', '2025-08-28 09:11:10', 1),
(10, 3, 2, '55555', '2025-08-28 09:11:10', 1),
(11, 3, 2, '5456', '2025-08-28 09:19:50', 1),
(12, 3, 2, '5456', '2025-08-28 09:19:51', 1),
(13, 3, 2, '5456', '2025-08-28 09:19:51', 1),
(14, 3, 2, '5456', '2025-08-28 09:19:51', 1),
(15, 3, 2, 'คคคคคคคคคคคคค', '2025-08-28 09:26:17', 1),
(16, 3, 2, 'นร้รน้', '2025-08-28 09:34:14', 1),
(17, 3, 2, 'test', '2025-08-28 09:34:41', 1),
(18, 3, 2, 'uuuuu', '2025-08-28 09:38:42', 1),
(19, 3, 2, 'nnnn', '2025-08-28 09:43:13', 1),
(20, 3, 2, 'nnnn', '2025-08-28 09:43:25', 1),
(21, 3, 2, ';;;;', '2025-08-28 09:49:20', 1),
(22, 3, 2, 'wwww', '2025-08-28 09:50:20', 1),
(23, 3, 2, 'ำดำดำ', '2025-08-28 10:13:21', 1),
(24, 3, 2, 'ำดำดำดำด', '2025-08-28 10:13:33', 1),
(25, 3, 2, 'สวัสดีครับ', '2025-09-01 12:27:18', 1),
(26, 3, 2, 'หกดห', '2025-09-01 12:30:22', 1),
(27, 3, 2, 'ะะพะพ', '2025-09-01 12:32:36', 1),
(28, 3, 2, 'ะำหะ', '2025-09-01 12:38:47', 1),
(29, 3, 2, 'ะำหะ', '2025-09-01 12:38:51', 1),
(30, 3, 2, 'test', '2025-09-01 13:22:54', 1),
(31, 3, 2, 'test', '2025-09-01 13:44:17', 1),
(32, 3, 2, '5656', '2025-09-01 13:45:48', 1),
(33, 2, 3, 'ว่าไงจ้ะรูปหล่อ', '2025-09-01 14:13:05', 1),
(34, 2, 3, 'เก่งอยู่นี้หน่า', '2025-09-01 14:13:18', 1),
(35, 3, 2, 'จ้าาา', '2025-09-01 14:13:55', 1),
(36, 3, 2, '56454', '2025-09-01 14:51:51', 1),
(37, 3, 2, '45456', '2025-09-01 14:51:53', 1),
(38, 3, 2, 'ojo', '2025-09-01 14:51:53', 1),
(39, 2, 3, 'dwdwdwd', '2025-09-01 14:53:45', 1),
(40, 2, 3, 'เห้ย', '2025-09-01 15:02:29', 1),
(41, 2, 3, 'ยากจังวะ', '2025-09-01 15:02:31', 1),
(42, 3, 2, 'bfbfbfb', '2025-09-01 15:05:33', 1);

-- --------------------------------------------------------

--
-- Table structure for table `profiles`
--

CREATE TABLE `profiles` (
  `profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address` text DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `portfolio_url` varchar(255) DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `profile_picture_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profiles`
--

INSERT INTO `profiles` (`profile_id`, `user_id`, `address`, `company_name`, `bio`, `portfolio_url`, `skills`, `profile_picture_url`) VALUES
(1, 2, '123 Design St, BKK', 'PixelLink co. ltd', 'Passionate UI/UX designer and dev.', 'https://www.twitch.tv/', 'UX/UI, Figma, Photoshop, AI,Canva', '../uploads/profile_pictures/profile_2_1754549012.jpg'),
(2, 3, '456 Business Rd, Nonthaburi', 'Acme Corp', NULL, NULL, NULL, '/uploads/bob.jpg'),
(3, 4, '789 Art Ave, Chiang Mai', NULL, 'Junior graphic designer looking for freelance work.', 'anna.artstation.com', 'Photoshop, Illustrator', '/uploads/anna.png'),
(4, 5, '101 Tech Tower, Bangkok', 'Tech Corp', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reported_user_id` int(11) DEFAULT NULL,
  `reported_post_id` int(11) DEFAULT NULL,
  `report_type` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `report_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','resolved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`report_id`, `reporter_id`, `reported_user_id`, `reported_post_id`, `report_type`, `description`, `report_date`, `status`) VALUES
(1, 3, NULL, NULL, 'spam', 'มีผู้ใช้งานส่งข้อความสแปมเข้ามาในแชท', '2025-06-07 15:44:37', 'pending'),
(2, 2, 5, NULL, 'user_misconduct', 'ผู้ว่าจ้างไม่ตอบกลับหลังจากตกลงราคาแล้ว', '2025-06-07 15:44:37', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `reviewed_user_id` int(11) NOT NULL,
  `rating` decimal(2,1) DEFAULT NULL CHECK (`rating` >= 1.0 and `rating` <= 5.0),
  `comment` text DEFAULT NULL,
  `review_type` enum('designer_review_client','client_review_designer') DEFAULT NULL,
  `review_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `contract_id`, `reviewer_id`, `reviewed_user_id`, `rating`, `comment`, `review_type`, `review_date`, `status`) VALUES
(1, 1, 3, 2, 5.0, 'ออกแบบได้สวยงาม รวดเร็ว และเข้าใจความต้องการเป็นอย่างดีครับ', 'client_review_designer', '2025-06-07 15:44:37', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'site_name', 'PixelLink Platform', 'ชื่อเว็บไซต์หรือแพลตฟอร์ม', '2025-06-09 14:46:36'),
(2, 'admin_email', 'admin@example.com', 'อีเมลสำหรับผู้ดูแลระบบหรือการแจ้งเตือน', '2025-06-09 14:46:36'),
(3, 'platform_commission_rate', '10.00', 'อัตราค่าคอมมิชชั่นของแพลตฟอร์ม (เป็นเปอร์เซ็นต์)', '2025-06-09 14:46:36'),
(4, 'min_withdrawal_amount', '500', 'จำนวนเงินขั้นต่ำในการถอนเงิน', '2025-06-09 14:46:36');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `payer_id` int(11) NOT NULL,
  `payee_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `contract_id`, `payer_id`, `payee_id`, `amount`, `transaction_date`, `payment_method`, `status`) VALUES
(1, 1, 3, 2, 3500.00, '2025-06-07 15:44:37', 'Credit Card', 'completed');

-- --------------------------------------------------------

--
-- Table structure for table `uploaded_files`
--

CREATE TABLE `uploaded_files` (
  `file_id` int(11) NOT NULL,
  `contract_id` int(11) DEFAULT NULL,
  `job_post_id` int(11) DEFAULT NULL,
  `uploader_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by_user_id` int(11) DEFAULT NULL,
  `file_type` varchar(255) DEFAULT NULL,
  `uploaded_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `uploaded_files`
--

INSERT INTO `uploaded_files` (`file_id`, `contract_id`, `job_post_id`, `uploader_id`, `file_name`, `file_path`, `uploaded_at`, `file_size`, `uploaded_by_user_id`, `file_type`, `uploaded_date`) VALUES
(23, NULL, NULL, 2, '', '../uploads/job_images/job_img_6889ca3c8f767.jpg', '2025-07-30 07:31:08', 314895, 2, 'image/jpeg', '2025-07-30 14:31:08'),
(24, NULL, NULL, 2, '', '../uploads/job_images/job_img_6889ce803fae4.jpg', '2025-07-30 07:49:20', 314895, 2, 'image/jpeg', '2025-07-30 14:49:20'),
(25, NULL, NULL, 2, 'job_img_6889d42d330ec1753863213.jpg', '../uploads/job_images/job_img_6889d42d330ec1753863213.jpg', '2025-07-30 08:13:33', 568822, NULL, 'image/jpeg', '2025-07-30 15:13:33'),
(26, NULL, NULL, 2, 'job_img_688a2790a325c1753884560.png', '../uploads/job_images/job_img_688a2790a325c1753884560.png', '2025-07-30 14:09:20', 206400, NULL, 'image/png', '2025-07-30 21:09:20'),
(27, NULL, NULL, 2, 'job_img_689075243fba91754297636.png', '../uploads/job_images/job_img_689075243fba91754297636.png', '2025-08-04 08:53:56', 206400, NULL, 'image/png', '2025-08-04 15:53:56'),
(29, NULL, NULL, 2, 'job_img_6890a9c2375571754311106.jpg', '../uploads/job_images/job_img_6890a9c2375571754311106.jpg', '2025-08-04 12:38:26', 295791, NULL, 'image/jpeg', '2025-08-04 19:38:26'),
(35, NULL, NULL, 2, 'job_img_6891638b0f6ec1754358667.png', '../uploads/job_images/job_img_6891638b0f6ec1754358667.png', '2025-08-05 01:51:07', 1058584, NULL, 'image/png', '2025-08-05 08:51:07'),
(36, NULL, NULL, 2, 'job_img_689164471af951754358855.jpg', '../uploads/job_images/job_img_689164471af951754358855.jpg', '2025-08-05 01:54:15', 50249, NULL, 'image/jpeg', '2025-08-05 08:54:15'),
(38, NULL, NULL, 2, 'job_img_68916a6ee76d51754360430.png', '../uploads/job_images/job_img_68916a6ee76d51754360430.png', '2025-08-05 02:20:30', 1058584, NULL, 'image/png', '2025-08-05 09:20:30'),
(41, NULL, NULL, 2, 'job_img_6891925c8b2381754370652.png', '../uploads/job_images/job_img_6891925c8b2381754370652.png', '2025-08-05 05:10:52', 1058584, NULL, 'image/png', '2025-08-05 12:10:52'),
(44, NULL, NULL, 2, 'job_img_689437dad240e1754544090.png', '../uploads/job_images/job_img_689437dad240e1754544090.png', '2025-08-07 05:21:30', 1058584, NULL, 'image/png', '2025-08-07 12:21:30'),
(46, NULL, NULL, 2, 'job_img_68b5a673ab19e1756735091.png', '../uploads/job_images/job_img_68b5a673ab19e1756735091.png', '2025-09-01 13:58:11', 206400, NULL, 'image/png', '2025-09-01 20:58:11');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `user_type` enum('admin','designer','client') NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_approved` tinyint(1) DEFAULT 0,
  `last_activity` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `first_name`, `last_name`, `phone_number`, `user_type`, `registration_date`, `is_approved`, `last_activity`, `is_active`, `last_login`) VALUES
(1, 'admin', '12345678', 'admin@pixellink.com', 'กฤษดา', 'บุญจันดา', '0901234567', 'admin', '2025-06-07 15:44:37', 1, NULL, 1, NULL),
(2, 'khoapun', '1234', 'jane@example.com', 'ศิขริน', 'คอมิธิน', '0812345678', 'designer', '2025-06-07 15:44:37', 1, NULL, 1, NULL),
(3, 'beer888', '1234', 'bob@company.com', 'เบียร์', 'สมิท', '0987654321', 'client', '2025-06-07 15:44:37', 1, '2025-09-01 22:14:27', 1, NULL),
(4, 'anna', '1234', 'anna@portfolio.net', 'Anna', 'Lee', '0891112222', 'designer', '2025-06-07 15:44:37', 1, NULL, 1, NULL),
(5, 'tech_corp', 'tech_pass', 'hr@techcorp.com', 'Tech', 'Corp HR', '029998888', 'client', '2025-06-07 15:44:37', 0, NULL, 1, NULL),
(6, 'krit.ti', '12345678', 'krit.ti@rmuti.ac.th', 'Krit', 'T.siriwattana', '0000000000', 'admin', '2025-06-08 11:16:59', 1, NULL, 1, NULL),
(7, 'kitsada.in', '1234', 'pakawat.in@gmail.com', 'kitsada', 'Ariyawatkul\r\n', '0000000000', 'designer', '2025-06-09 07:58:49', 1, NULL, 1, NULL),
(10, 'party888', '1234', 'kkiii@gmail.com', 'กิตติพงศ์', 'เถื่อนกลาง', '0555555555', 'designer', '2025-06-24 09:38:07', 1, NULL, 1, NULL),
(12, 'TESTTTTT', 'Test_lll123456789@', 'KKKKKKK@gmail.com', 'TEST12332', 'PROJECT1', '0999999999', 'designer', '2025-07-07 14:54:31', 1, NULL, 1, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `client_job_requests`
--
ALTER TABLE `client_job_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`contract_id`),
  ADD UNIQUE KEY `request_id` (`request_id`),
  ADD KEY `designer_id` (`designer_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `designer_id` (`designer_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `job_categories`
--
ALTER TABLE `job_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `designer_id` (`designer_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `from_user_id` (`from_user_id`),
  ADD KEY `to_user_id` (`to_user_id`);

--
-- Indexes for table `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`profile_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `reporter_id` (`reporter_id`),
  ADD KEY `reported_user_id` (`reported_user_id`),
  ADD KEY `reported_post_id` (`reported_post_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `contract_id` (`contract_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `reviewed_user_id` (`reviewed_user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `contract_id` (`contract_id`),
  ADD KEY `payer_id` (`payer_id`),
  ADD KEY `payee_id` (`payee_id`);

--
-- Indexes for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `contract_id` (`contract_id`),
  ADD KEY `uploader_id` (`uploader_id`),
  ADD KEY `fk_job_post_id` (`job_post_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `client_job_requests`
--
ALTER TABLE `client_job_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `contract_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `job_applications`
--
ALTER TABLE `job_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `job_categories`
--
ALTER TABLE `job_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `job_postings`
--
ALTER TABLE `job_postings`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=365;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `profiles`
--
ALTER TABLE `profiles`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `client_job_requests`
--
ALTER TABLE `client_job_requests`
  ADD CONSTRAINT `client_job_requests_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `client_job_requests_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `job_categories` (`category_id`);

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `contracts_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `client_job_requests` (`request_id`),
  ADD CONSTRAINT `contracts_ibfk_2` FOREIGN KEY (`designer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `contracts_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD CONSTRAINT `job_applications_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `client_job_requests` (`request_id`),
  ADD CONSTRAINT `job_applications_ibfk_2` FOREIGN KEY (`designer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `job_applications_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD CONSTRAINT `job_postings_ibfk_1` FOREIGN KEY (`designer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `job_postings_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `job_categories` (`category_id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `profiles`
--
ALTER TABLE `profiles`
  ADD CONSTRAINT `profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`reported_post_id`) REFERENCES `job_postings` (`post_id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`contract_id`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`reviewed_user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`contract_id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`payer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`payee_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  ADD CONSTRAINT `fk_job_post_id` FOREIGN KEY (`job_post_id`) REFERENCES `job_postings` (`post_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `uploaded_files_ibfk_2` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
