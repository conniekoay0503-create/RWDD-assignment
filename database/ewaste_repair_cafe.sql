-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 05, 2026 at 04:25 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ewaste_repair_cafe`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `tp_number` varchar(10) NOT NULL,
  `assigned_by` varchar(10) DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`tp_number`, `assigned_by`, `assigned_at`) VALUES
('TP090995', NULL, '2026-07-07 19:58:38'),
('TP100002', 'TP090995', '2026-07-27 19:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcementID` varchar(10) NOT NULL,
  `created_by` varchar(10) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcementID`, `created_by`, `title`, `message`, `created_at`) VALUES
('A001', 'TP090995', 'Welcome to the E-Waste & Repair Cafe', 'Drop your broken gadgets at the Green Hub, Block E. Volunteers repair what they can and recycle the rest properly.', '2026-09-05 19:58:38'),
('A002', 'TP090995', 'New challenge: Month of Repair', 'Get 5 kg of your devices repaired or recycled this month to earn 50 bonus points.', '2026-10-23 19:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `categoryID` varchar(10) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `multiplier` decimal(7,2) NOT NULL,
  `max_points` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  CONSTRAINT `chk_cat_multiplier` CHECK (`multiplier` > 0),
  CONSTRAINT `chk_cat_max` CHECK (`max_points` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`categoryID`, `category_name`, `multiplier`, `max_points`, `is_active`) VALUES
('C001', 'Battery', 1000.00, 100, 1),
('C002', 'Phone / Tablet', 500.00, 300, 1),
('C003', 'Laptop / Desktop', 300.00, 500, 1),
('C004', 'Cable / Accessory', 100.00, 50, 1),
('C005', 'Printer / Scanner', 200.00, 200, 0);

-- --------------------------------------------------------

--
-- Table structure for table `challenges`
--

CREATE TABLE `challenges` (
  `challengeID` varchar(10) NOT NULL,
  `created_by` varchar(10) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `target_kg` decimal(8,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `bonus_points` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  CONSTRAINT `chk_ch_target` CHECK (`target_kg` > 0),
  CONSTRAINT `chk_ch_bonus` CHECK (`bonus_points` > 0),
  CONSTRAINT `chk_ch_dates` CHECK (`end_date` >= `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `challenges`
--

INSERT INTO `challenges` (`challengeID`, `created_by`, `title`, `description`, `target_kg`, `start_date`, `end_date`, `bonus_points`, `created_at`) VALUES
('CH001', 'TP090995', 'Battery Round-Up', 'Recycle or repair 0.5 kg of e-waste during the challenge', 0.50, '2026-09-15', '2026-10-14', 30, '2026-09-10 19:58:38'),
('CH002', 'TP090995', 'Month of Repair', 'Get 5 kg of your devices repaired or recycled this month', 5.00, '2026-10-25', '2026-11-24', 50, '2026-10-23 19:58:38'),
('CH003', 'TP100002', 'Final Week Cable Drive', 'Bring in old cables and accessories', 1.00, '2026-12-04', '2026-12-11', 40, '2026-11-03 19:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `deviceID` varchar(10) NOT NULL,
  `submitted_by` varchar(10) NOT NULL,
  `categoryID` varchar(10) NOT NULL,
  `claimed_by` varchar(10) DEFAULT NULL,
  `processed_by` varchar(10) DEFAULT NULL,
  `device_name` varchar(100) NOT NULL,
  `fault_description` varchar(255) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `weight_kg` decimal(6,2) DEFAULT NULL,
  `status` enum('Submitted','Cancelled','Dropped Off','Claimed','In Repair','Ready for Collection','Completed','Pending Consent','Return Requested','Awaiting Recycle','Recycled','Returned Unrepaired','Collected Unrepaired','Disposed','Returned Unclaimed') NOT NULL DEFAULT 'Submitted',
  `disposal_method` enum('Certified e-waste recycler','Battery take-back programme','Metal recovery','Harvest parts for repair stock') DEFAULT NULL,
  `terms_accepted` tinyint(1) NOT NULL,
  `recycle_consent` tinyint(1) DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status_changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  CONSTRAINT `chk_dev_weight` CHECK (`weight_kg` IS NULL OR (`weight_kg` > 0 AND `weight_kg` <= 50)),
  CONSTRAINT `chk_dev_terms` CHECK (`terms_accepted` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`deviceID`, `submitted_by`, `categoryID`, `claimed_by`, `processed_by`, `device_name`, `fault_description`, `photo_path`, `weight_kg`, `status`, `disposal_method`, `terms_accepted`, `recycle_consent`, `submitted_at`, `status_changed_at`) VALUES
('D001', 'TP087791', 'C002', 'TP091586', NULL, 'Pixel 6', 'Battery drains within 2 hours', 'uploads/devices/demo_1.jpg', 0.21, 'In Repair', NULL, 1, NULL, '2026-10-26 19:58:38', '2026-10-31 19:58:38'),
('D002', 'TP087791', 'C003', 'TP091586', NULL, 'ThinkPad T480', 'Screen does not turn on', 'uploads/devices/demo_2.jpg', 2.10, 'Pending Consent', NULL, 1, NULL, '2026-10-23 19:58:38', '2026-11-02 19:58:38'),
('D003', 'TP087791', 'C004', NULL, NULL, 'USB-C charging cable', 'Only charges at one angle', 'uploads/devices/demo_3.jpg', NULL, 'Submitted', NULL, 1, NULL, '2026-11-03 19:58:38', '2026-11-03 19:58:38'),
('D004', 'TP095003', 'C002', 'TP091586', 'TP087766', 'Galaxy Tab A', 'Cracked screen and swollen battery', 'uploads/devices/demo_4.jpg', 0.48, 'Recycled', 'Certified e-waste recycler', 1, 1, '2026-09-20 19:58:38', '2026-09-29 19:58:38'),
('D005', 'TP095003', 'C001', 'TP091586', 'TP087766', 'Power bank cell', 'Does not hold charge', 'uploads/devices/demo_5.jpg', 0.05, 'Recycled', 'Battery take-back programme', 1, 1, '2026-09-25 19:58:38', '2026-10-02 19:58:38'),
('D006', 'TP095001', 'C001', 'TP091586', NULL, 'Laptop battery pack', 'Battery is swollen', 'uploads/devices/demo_6.jpg', 0.32, 'Awaiting Recycle', NULL, 1, 1, '2026-10-27 19:58:38', '2026-10-31 19:58:38'),
('D007', 'TP095002', 'C002', 'TP091586', NULL, 'Nokia 3310 (2017)', 'Keypad not responding', 'uploads/devices/demo_7.jpg', 0.13, 'Awaiting Recycle', NULL, 1, 1, '2026-10-28 19:58:38', '2026-11-01 19:58:38'),
('D008', 'TP095001', 'C002', 'TP091586', NULL, 'Redmi Note 11', 'Charging port loose', 'uploads/devices/demo_8.jpg', 0.20, 'Completed', NULL, 1, NULL, '2026-10-05 19:58:38', '2026-10-11 19:58:38'),
('D009', 'TP095004', 'C003', NULL, NULL, 'Dell Inspiron 15', 'Coffee spill, keyboard row stopped working', 'uploads/devices/demo_9.jpg', NULL, 'Dropped Off', NULL, 1, NULL, '2026-10-31 19:58:38', '2026-11-01 19:58:38'),
('D010', 'TP095002', 'C002', 'TP091586', NULL, 'iPad Air 2', 'Will not charge', 'uploads/devices/demo_10.jpg', 0.44, 'Ready for Collection', NULL, 1, NULL, '2026-10-13 19:58:38', '2026-10-25 19:58:38'),
('D011', 'TP095004', 'C004', 'TP091586', NULL, 'Logitech wireless mouse', 'Left click double-clicks', 'uploads/devices/demo_11.jpg', 0.09, 'Returned Unrepaired', NULL, 1, 0, '2026-10-20 19:58:38', '2026-11-01 19:58:38'),
('D012', 'TP095006', 'C001', NULL, NULL, 'Xiaomi power bank', 'Only charges to 45%', 'uploads/devices/demo_12.jpg', NULL, 'Dropped Off', NULL, 1, NULL, '2026-11-01 19:58:38', '2026-11-02 19:58:38'),
('D013', 'TP095001', 'C004', NULL, NULL, 'Old phone charger', 'Frayed cable', 'uploads/devices/demo_13.jpg', NULL, 'Cancelled', NULL, 1, NULL, '2026-10-15 19:58:38', '2026-10-16 19:58:38'),
('D014', 'TP095003', 'C002', 'TP091586', NULL, 'Kindle Paperwhite', 'Screen frozen', 'uploads/devices/demo_14.jpg', 0.18, 'Collected Unrepaired', NULL, 1, 0, '2026-10-07 19:58:38', '2026-10-16 19:58:38'),
('D015', 'TP095002', 'C004', 'TP091586', 'TP087766', 'Wired earbuds', 'Left side silent', 'uploads/devices/demo_15.jpg', 0.03, 'Disposed', 'Certified e-waste recycler', 1, NULL, '2026-09-30 19:58:38', '2026-10-15 19:58:38'),
('D016', 'TP095001', 'C003', 'TP091586', NULL, 'Acer Aspire 5', 'Hinge snapped, screen flickers', 'uploads/devices/demo_16.jpg', 1.70, 'Return Requested', NULL, 1, 0, '2026-10-27 19:58:38', '2026-11-02 19:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `device_status_history`
--

CREATE TABLE `device_status_history` (
  `historyID` varchar(10) NOT NULL,
  `deviceID` varchar(10) NOT NULL,
  `changed_by` varchar(10) DEFAULT NULL,
  `new_status` enum('Submitted','Cancelled','Dropped Off','Claimed','In Repair','Ready for Collection','Completed','Pending Consent','Return Requested','Awaiting Recycle','Recycled','Returned Unrepaired','Collected Unrepaired','Disposed','Returned Unclaimed') NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `device_status_history`
--

INSERT INTO `device_status_history` (`historyID`, `deviceID`, `changed_by`, `new_status`, `remarks`, `changed_at`) VALUES
('H001', 'D001', 'TP087791', 'Submitted', NULL, '2026-10-26 19:58:38'),
('H002', 'D001', 'TP087791', 'Dropped Off', NULL, '2026-10-27 19:58:38'),
('H003', 'D001', 'TP091586', 'Claimed', NULL, '2026-10-28 19:58:38'),
('H004', 'D001', 'TP091586', 'In Repair', 'Received, weight recorded', '2026-10-29 19:58:38'),
('H005', 'D001', 'TP091586', 'In Repair', 'Ordered a replacement battery', '2026-10-31 19:58:38'),
('H006', 'D002', 'TP087791', 'Submitted', NULL, '2026-10-23 19:58:38'),
('H007', 'D002', 'TP087791', 'Dropped Off', NULL, '2026-10-24 19:58:38'),
('H008', 'D002', 'TP091586', 'Claimed', NULL, '2026-10-25 19:58:38'),
('H009', 'D002', 'TP091586', 'In Repair', 'Received, weight recorded', '2026-10-26 19:58:38'),
('H010', 'D002', 'TP091586', 'Pending Consent', 'Unrepairable - motherboard damaged', '2026-11-02 19:58:38'),
('H011', 'D003', 'TP087791', 'Submitted', NULL, '2026-11-03 19:58:38'),
('H012', 'D004', 'TP095003', 'Submitted', NULL, '2026-09-20 19:58:38'),
('H013', 'D004', 'TP095003', 'Dropped Off', NULL, '2026-09-21 19:58:38'),
('H014', 'D004', 'TP091586', 'Claimed', NULL, '2026-09-22 19:58:38'),
('H015', 'D004', 'TP091586', 'In Repair', 'Received, weight recorded', '2026-09-23 19:58:38'),
('H016', 'D004', 'TP091586', 'Pending Consent', 'Unrepairable - battery unsafe', '2026-09-25 19:58:38'),
('H017', 'D004', 'TP095003', 'Awaiting Recycle', NULL, '2026-09-26 19:58:38'),
('H018', 'D004', 'TP087766', 'Recycled', 'Certified e-waste recycler', '2026-09-29 19:58:38'),
('H019', 'D005', 'TP095003', 'Submitted', NULL, '2026-09-25 19:58:38'),
('H020', 'D005', 'TP095003', 'Dropped Off', NULL, '2026-09-26 19:58:38'),
('H021', 'D005', 'TP091586', 'Claimed', NULL, '2026-09-27 19:58:38'),
('H022', 'D005', 'TP091586', 'In Repair', 'Received, weight recorded', '2026-09-28 19:58:38'),
('H023', 'D005', 'TP091586', 'Pending Consent', 'Cell is dead', '2026-09-29 19:58:38'),
('H024', 'D005', 'TP095003', 'Awaiting Recycle', NULL, '2026-09-30 19:58:38'),
('H025', 'D005', 'TP087766', 'Recycled', 'Battery take-back programme', '2026-10-02 19:58:38'),
('H026', 'D006', 'TP095001', 'Submitted', NULL, '2026-10-27 19:58:38'),
('H027', 'D006', 'TP095001', 'Dropped Off', NULL, '2026-10-28 19:58:38'),
('H028', 'D006', 'TP091586', 'Claimed', NULL, '2026-10-29 19:58:38'),
('H029', 'D006', 'TP091586', 'In Repair', 'Received, weight recorded', '2026-10-29 19:58:38'),
('H030', 'D006', 'TP091586', 'Pending Consent', 'Unsafe to repair', '2026-10-30 19:58:38'),
('H031', 'D006', 'TP095001', 'Awaiting Recycle', NULL, '2026-10-31 19:58:38'),
('H032', 'D007', 'TP095002', 'Submitted', NULL, '2026-10-28 19:58:38'),
('H033', 'D007', 'TP095002', 'Dropped Off', NULL, '2026-10-29 19:58:38'),
('H034', 'D007', 'TP091586', 'Claimed', NULL, '2026-10-30 19:58:38'),
('H035', 'D007', 'TP091586', 'In Repair', 'Received, weight recorded', '2026-10-30 19:58:38'),
('H036', 'D007', 'TP091586', 'Pending Consent', 'Keypad board corroded', '2026-10-31 19:58:38'),
('H037', 'D007', 'TP095002', 'Awaiting Recycle', NULL, '2026-11-01 19:58:38'),
('H038', 'D008', 'TP095001', 'Submitted', NULL, '2026-10-05 19:58:38'),
('H039', 'D008', 'TP095001', 'Dropped Off', NULL, '2026-10-06 19:58:38'),
('H040', 'D008', 'TP091586', 'Claimed', NULL, '2026-10-07 19:58:38'),
('H041', 'D008', 'TP091586', 'In Repair', 'Received, weight recorded', '2026-10-08 19:58:38'),
('H042', 'D008', 'TP091586', 'Ready for Collection', 'Replaced the charging port', '2026-10-10 19:58:38'),
('H043', 'D008', 'TP095001', 'Completed', NULL, '2026-10-11 19:58:38'),
('H044', 'D009', 'TP095004', 'Submitted', NULL, '2026-10-31 19:58:38'),
('H045', 'D009', 'TP095004', 'Dropped Off', NULL, '2026-11-01 19:58:38'),
('H046', 'D010', 'TP095002', 'Submitted', NULL, '2026-10-13 19:58:38'),
('H047', 'D010', 'TP095002', 'Dropped Off', NULL, '2026-10-14 19:58:38'),
('H048', 'D010', 'TP091586', 'Claimed', NULL, '2026-10-15 19:58:38'),
('H049', 'D010', 'TP091586', 'In Repair', 'Received, weight recorded', '2026-10-16 19:58:38'),
('H050', 'D010', 'TP091586', 'Ready for Collection', 'Cleaned the charging port', '2026-10-25 19:58:38'),
('H051', 'D011', 'TP095004', 'Submitted', NULL, '2026-10-20 19:58:38'),
('H052', 'D011', 'TP095004', 'Dropped Off', NULL, '2026-10-21 19:58:38'),
('H053', 'D011', 'TP091586', 'Claimed', NULL, '2026-10-22 19:58:38'),
('H054', 'D011', 'TP091586', 'In Repair', 'Received, weight recorded', '2026-10-23 19:58:38'),
('H055', 'D011', 'TP091586', 'Pending Consent', 'Switch cannot be replaced', '2026-10-29 19:58:38'),
('H056', 'D011', 'TP095004', 'Return Requested', NULL, '2026-10-30 19:58:38'),
('H057', 'D011', 'TP091586', 'Returned Unrepaired', 'Ready at the Green Hub counter', '2026-11-01 19:58:38'),
('H058', 'D012', 'TP095006', 'Submitted', NULL, '2026-11-01 19:58:38'),
('H059', 'D012', 'TP095006', 'Dropped Off', NULL, '2026-11-02 19:58:38'),
('H060', 'D013', 'TP095001', 'Submitted', NULL, '2026-10-15 19:58:38'),
('H061', 'D013', 'TP095001', 'Cancelled', NULL, '2026-10-16 19:58:38'),
('H062', 'D014', 'TP095003', 'Submitted', NULL, '2026-10-07 19:58:38'),
('H063', 'D014', 'TP095003', 'Dropped Off', NULL, '2026-10-08 19:58:38'),
('H064', 'D014', 'TP091586', 'Claimed', NULL, '2026-10-09 19:58:38'),
('H065', 'D014', 'TP091586', 'In Repair', 'Received, weight recorded', '2026-10-10 19:58:38'),
('H066', 'D014', 'TP091586', 'Pending Consent', 'Display panel broken', '2026-10-13 19:58:38'),
('H067', 'D014', 'TP095003', 'Return Requested', NULL, '2026-10-14 19:58:38'),
('H068', 'D014', 'TP091586', 'Returned Unrepaired', 'Ready at the Green Hub counter', '2026-10-15 19:58:38'),
('H069', 'D014', 'TP095003', 'Collected Unrepaired', NULL, '2026-10-16 19:58:38'),
('H070', 'D015', 'TP095002', 'Submitted', NULL, '2026-09-30 19:58:38'),
('H071', 'D015', 'TP095002', 'Dropped Off', NULL, '2026-10-01 19:58:38'),
('H072', 'D015', 'TP091586', 'Claimed', NULL, '2026-10-02 19:58:38'),
('H073', 'D015', 'TP091586', 'In Repair', 'Received, weight recorded', '2026-10-03 19:58:38'),
('H074', 'D015', 'TP091586', 'Pending Consent', 'Wire broken inside the jack', '2026-10-05 19:58:38'),
('H075', 'D015', 'TP087766', 'Disposed', 'Not collected within 7 days', '2026-10-15 19:58:38'),
('H076', 'D016', 'TP095001', 'Submitted', NULL, '2026-10-27 19:58:38'),
('H077', 'D016', 'TP095001', 'Dropped Off', NULL, '2026-10-28 19:58:38'),
('H078', 'D016', 'TP091586', 'Claimed', NULL, '2026-10-29 19:58:38'),
('H079', 'D016', 'TP091586', 'In Repair', 'Received, weight recorded', '2026-10-30 19:58:38'),
('H080', 'D016', 'TP091586', 'Pending Consent', 'Screen cable and hinge both broken', '2026-11-01 19:58:38'),
('H081', 'D016', 'TP095001', 'Return Requested', NULL, '2026-11-02 19:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedbackID` varchar(10) NOT NULL,
  `tp_number` varchar(10) NOT NULL,
  `deviceID` varchar(10) NOT NULL,
  `replied_by` varchar(10) DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `admin_reply` varchar(255) DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `replied_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedbackID`, `tp_number`, `deviceID`, `replied_by`, `message`, `admin_reply`, `submitted_at`, `replied_at`) VALUES
('F001', 'TP095003', 'D004', 'TP090995', 'Quick and clear updates, thank you!', 'Thank you for recycling with us!', '2026-09-30 19:58:38', '2026-10-01 19:58:38'),
('F002', 'TP095001', 'D008', NULL, 'My phone works again. Could the Green Hub open on Saturdays?', NULL, '2026-11-02 19:58:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notificationID` varchar(10) NOT NULL,
  `tp_number` varchar(10) NOT NULL,
  `created_by` varchar(10) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notificationID`, `tp_number`, `created_by`, `title`, `message`, `is_read`, `created_at`) VALUES
('N001', 'TP087791', NULL, 'Decision needed', 'Your ThinkPad T480 cannot be repaired. Please allow recycling or ask for it back.', 0, '2026-11-02 19:58:38'),
('N002', 'TP095002', NULL, 'Ready for collection', 'Your iPad Air 2 is repaired. Please collect it within 7 days.', 1, '2026-10-25 19:58:38'),
('N003', 'TP091586', 'TP090995', 'Request approved', 'You are now a Repair Volunteer.', 1, '2026-08-08 19:58:38'),
('N004', 'TP095004', 'TP100002', 'Request rejected', 'Your volunteer request was not approved this time.', 1, '2026-10-16 19:58:38'),
('N005', 'TP090995', NULL, 'New cashback request', 'Daniel Wong redeemed RM2 Cashback (100 points). Please pay it to the APCard.', 1, '2026-11-03 19:58:38'),
('N006', 'TP100002', NULL, 'New cashback request', 'Daniel Wong redeemed RM2 Cashback (100 points). Please pay it to the APCard.', 0, '2026-11-03 19:58:38'),
('N007', 'TP090995', NULL, 'New volunteer request', 'Siti Aminah asked to become a Repair Volunteer.', 1, '2026-11-02 19:58:38'),
('N008', 'TP100002', NULL, 'New volunteer request', 'Siti Aminah asked to become a Repair Volunteer.', 0, '2026-11-02 19:58:38'),
('N009', 'TP090995', NULL, 'New feedback', 'Daniel Wong sent feedback about Redmi Note 11.', 1, '2026-11-02 19:58:38'),
('N010', 'TP100002', NULL, 'New feedback', 'Daniel Wong sent feedback about Redmi Note 11.', 0, '2026-11-02 19:58:38'),
('N011', 'TP090995', 'TP100002', 'Challenge scheduled', 'I created the Final Week Cable Drive challenge. Please check the dates.', 1, '2026-11-03 19:58:38'),
('N012', 'TP090995', NULL, 'New feedback', 'Mei Ling Tan sent feedback about Galaxy Tab A.', 1, '2026-09-30 19:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `officers`
--

CREATE TABLE `officers` (
  `tp_number` varchar(10) NOT NULL,
  `assigned_by` varchar(10) NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`tp_number`, `assigned_by`, `assigned_at`) VALUES
('TP087766', 'TP090995', '2026-07-19 19:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `points_transactions`
--

CREATE TABLE `points_transactions` (
  `transactionID` varchar(10) NOT NULL,
  `tp_number` varchar(10) NOT NULL,
  `deviceID` varchar(10) DEFAULT NULL,
  `redemptionID` varchar(10) DEFAULT NULL,
  `challengeID` varchar(10) DEFAULT NULL,
  `points` int(11) NOT NULL,
  `txn_type` enum('earn','redeem','bonus') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `points_transactions`
--

INSERT INTO `points_transactions` (`transactionID`, `tp_number`, `deviceID`, `redemptionID`, `challengeID`, `points`, `txn_type`, `description`, `created_at`) VALUES
('T001', 'TP095003', 'D004', NULL, NULL, 240, 'earn', 'Galaxy Tab A recycled', '2026-09-29 19:58:38'),
('T002', 'TP095003', 'D005', NULL, NULL, 50, 'earn', 'Power bank cell recycled', '2026-10-02 19:58:38'),
('T003', 'TP095003', NULL, NULL, 'CH001', 30, 'bonus', 'Challenge bonus: Battery Round-Up', '2026-10-02 19:58:38'),
('T004', 'TP095003', NULL, 'R001', NULL, -150, 'redeem', 'Redeemed RM3 Cashback', '2026-10-05 19:58:38'),
('T005', 'TP095001', 'D008', NULL, NULL, 200, 'earn', 'Redmi Note 11 repaired', '2026-10-11 19:58:38'),
('T006', 'TP091586', 'D008', NULL, NULL, 100, 'earn', 'Repaired Redmi Note 11', '2026-10-11 19:58:38'),
('T007', 'TP095001', NULL, 'R002', NULL, -100, 'redeem', 'Redeemed RM2 Cashback', '2026-11-03 19:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `redemptions`
--

CREATE TABLE `redemptions` (
  `redemptionID` varchar(10) NOT NULL,
  `tp_number` varchar(10) NOT NULL,
  `rewardID` varchar(10) NOT NULL,
  `reviewed_by` varchar(10) DEFAULT NULL,
  `points_used` int(11) NOT NULL,
  `cash_value` decimal(8,2) NOT NULL,
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `redemptions`
--

INSERT INTO `redemptions` (`redemptionID`, `tp_number`, `rewardID`, `reviewed_by`, `points_used`, `cash_value`, `status`, `requested_at`, `reviewed_at`) VALUES
('R001', 'TP095003', 'RW002', 'TP090995', 150, 3.00, 'completed', '2026-10-05 19:58:38', '2026-10-06 19:58:38'),
('R002', 'TP095001', 'RW001', NULL, 100, 2.00, 'pending', '2026-11-03 19:58:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rewards`
--

CREATE TABLE `rewards` (
  `rewardID` varchar(10) NOT NULL,
  `reward_name` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `points_required` int(11) NOT NULL,
  `cash_value` decimal(8,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  CONSTRAINT `chk_rw_points` CHECK (`points_required` > 0),
  CONSTRAINT `chk_rw_cash` CHECK (`cash_value` > 0),
  CONSTRAINT `chk_rw_stock` CHECK (`stock` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rewards`
--

INSERT INTO `rewards` (`rewardID`, `reward_name`, `description`, `points_required`, `cash_value`, `stock`) VALUES
('RW001', 'RM2 Cashback', 'Paid to the APCard linked to your email', 100, 2.00, 20),
('RW002', 'RM3 Cashback', 'Paid to the APCard linked to your email', 150, 3.00, 14),
('RW003', 'RM5 Cashback', 'Paid to the APCard linked to your email', 240, 5.00, 10),
('RW004', 'RM10 Cashback', 'Paid to the APCard linked to your email', 460, 10.00, 5),
('RW005', 'RM20 Cashback', 'Paid to the APCard linked to your email', 880, 20.00, 2);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `tp_number` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`tp_number`) VALUES
('TP087791'),
('TP095001'),
('TP095002'),
('TP095003'),
('TP095004'),
('TP095005'),
('TP095006');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `tp_number` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`tp_number`, `name`, `email`, `password`, `phone`, `is_active`, `created_at`) VALUES
('TP087766', 'Jerald Long Jitson', 'jerald@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0145556677', 1, '2026-07-17 19:58:38'),
('TP087791', 'Genieve Ki Yi Nee', 'genieve@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0171112233', 1, '2026-08-16 19:58:38'),
('TP090995', 'Koay Shin Shee', 'koay@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0123456789', 1, '2026-07-07 19:58:38'),
('TP091586', 'Lim Wen Ying', 'lim@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0167778899', 1, '2026-08-06 19:58:38'),
('TP095001', 'Daniel Wong', 'daniel@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0182223344', 1, '2026-08-21 19:58:38'),
('TP095002', 'Priya Suresh', 'priya@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0193334455', 1, '2026-08-26 19:58:38'),
('TP095003', 'Mei Ling Tan', 'meiling@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0114445566', 1, '2026-08-26 19:58:38'),
('TP095004', 'Arjun Nair', 'arjun@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0125556677', 1, '2026-09-05 19:58:38'),
('TP095005', 'Ken Ho', 'ken@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0136667788', 0, '2026-09-15 19:58:38'),
('TP095006', 'Siti Aminah', 'siti@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0147778899', 1, '2026-10-05 19:58:38'),
('TP100002', 'Nur Aisyah Rahman', 'aisyah@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0132223344', 1, '2026-07-27 19:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `volunteers`
--

CREATE TABLE `volunteers` (
  `tp_number` varchar(10) NOT NULL,
  `assigned_by` varchar(10) NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `volunteers`
--

INSERT INTO `volunteers` (`tp_number`, `assigned_by`, `assigned_at`) VALUES
('TP091586', 'TP090995', '2026-08-08 19:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_request`
--

CREATE TABLE `volunteer_request` (
  `requestID` varchar(10) NOT NULL,
  `tp_number` varchar(10) NOT NULL,
  `reviewed_by` varchar(10) DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `volunteer_request`
--

INSERT INTO `volunteer_request` (`requestID`, `tp_number`, `reviewed_by`, `reason`, `status`, `requested_at`, `reviewed_at`) VALUES
('VR001', 'TP091586', 'TP090995', 'I have fixed phones for my family for years', 'approved', '2026-08-07 19:58:38', '2026-08-08 19:58:38'),
('VR002', 'TP095004', 'TP100002', 'Want volunteer hours', 'rejected', '2026-10-15 19:58:38', '2026-10-16 19:58:38'),
('VR003', 'TP095006', NULL, 'I am studying electronics and want hands-on repair practice', 'pending', '2026-11-02 19:58:38', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`tp_number`),
  ADD KEY `fk_admins_by` (`assigned_by`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcementID`),
  ADD KEY `fk_announce_admin` (`created_by`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`categoryID`),
  ADD UNIQUE KEY `uk_category_name` (`category_name`);

--
-- Indexes for table `challenges`
--
ALTER TABLE `challenges`
  ADD PRIMARY KEY (`challengeID`),
  ADD KEY `fk_challenges_admin` (`created_by`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`deviceID`),
  ADD KEY `fk_dev_user` (`submitted_by`),
  ADD KEY `fk_dev_category` (`categoryID`),
  ADD KEY `fk_dev_volunteer` (`claimed_by`),
  ADD KEY `fk_dev_officer` (`processed_by`);

--
-- Indexes for table `device_status_history`
--
ALTER TABLE `device_status_history`
  ADD PRIMARY KEY (`historyID`),
  ADD KEY `fk_dsh_device` (`deviceID`),
  ADD KEY `fk_dsh_user` (`changed_by`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedbackID`),
  ADD KEY `fk_fb_user` (`tp_number`),
  ADD KEY `fk_fb_device` (`deviceID`),
  ADD KEY `fk_fb_admin` (`replied_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notificationID`),
  ADD KEY `fk_notif_user` (`tp_number`),
  ADD KEY `fk_notif_admin` (`created_by`);

--
-- Indexes for table `officers`
--
ALTER TABLE `officers`
  ADD PRIMARY KEY (`tp_number`),
  ADD KEY `fk_officers_by` (`assigned_by`);

--
-- Indexes for table `points_transactions`
--
ALTER TABLE `points_transactions`
  ADD PRIMARY KEY (`transactionID`),
  ADD UNIQUE KEY `uk_pt_device_user` (`deviceID`,`tp_number`),
  ADD UNIQUE KEY `uk_pt_challenge_user` (`challengeID`,`tp_number`),
  ADD UNIQUE KEY `uk_pt_redemption` (`redemptionID`),
  ADD KEY `fk_pt_user` (`tp_number`);

--
-- Indexes for table `redemptions`
--
ALTER TABLE `redemptions`
  ADD PRIMARY KEY (`redemptionID`),
  ADD KEY `fk_red_user` (`tp_number`),
  ADD KEY `fk_red_reward` (`rewardID`),
  ADD KEY `fk_red_admin` (`reviewed_by`);

--
-- Indexes for table `rewards`
--
ALTER TABLE `rewards`
  ADD PRIMARY KEY (`rewardID`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`tp_number`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`tp_number`),
  ADD UNIQUE KEY `uk_users_email` (`email`);

--
-- Indexes for table `volunteers`
--
ALTER TABLE `volunteers`
  ADD PRIMARY KEY (`tp_number`),
  ADD KEY `fk_volunteers_by` (`assigned_by`);

--
-- Indexes for table `volunteer_request`
--
ALTER TABLE `volunteer_request`
  ADD PRIMARY KEY (`requestID`),
  ADD KEY `fk_vr_user` (`tp_number`),
  ADD KEY `fk_vr_admin` (`reviewed_by`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `fk_admins_by` FOREIGN KEY (`assigned_by`) REFERENCES `admins` (`tp_number`),
  ADD CONSTRAINT `fk_admins_user` FOREIGN KEY (`tp_number`) REFERENCES `users` (`tp_number`);

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announce_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`tp_number`);

--
-- Constraints for table `challenges`
--
ALTER TABLE `challenges`
  ADD CONSTRAINT `fk_challenges_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`tp_number`);

--
-- Constraints for table `devices`
--
ALTER TABLE `devices`
  ADD CONSTRAINT `fk_dev_category` FOREIGN KEY (`categoryID`) REFERENCES `categories` (`categoryID`),
  ADD CONSTRAINT `fk_dev_officer` FOREIGN KEY (`processed_by`) REFERENCES `officers` (`tp_number`),
  ADD CONSTRAINT `fk_dev_user` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`tp_number`),
  ADD CONSTRAINT `fk_dev_volunteer` FOREIGN KEY (`claimed_by`) REFERENCES `volunteers` (`tp_number`);

--
-- Constraints for table `device_status_history`
--
ALTER TABLE `device_status_history`
  ADD CONSTRAINT `fk_dsh_device` FOREIGN KEY (`deviceID`) REFERENCES `devices` (`deviceID`),
  ADD CONSTRAINT `fk_dsh_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`tp_number`);

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_fb_admin` FOREIGN KEY (`replied_by`) REFERENCES `admins` (`tp_number`),
  ADD CONSTRAINT `fk_fb_device` FOREIGN KEY (`deviceID`) REFERENCES `devices` (`deviceID`),
  ADD CONSTRAINT `fk_fb_user` FOREIGN KEY (`tp_number`) REFERENCES `users` (`tp_number`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`tp_number`),
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`tp_number`) REFERENCES `users` (`tp_number`);

--
-- Constraints for table `officers`
--
ALTER TABLE `officers`
  ADD CONSTRAINT `fk_officers_by` FOREIGN KEY (`assigned_by`) REFERENCES `admins` (`tp_number`),
  ADD CONSTRAINT `fk_officers_user` FOREIGN KEY (`tp_number`) REFERENCES `users` (`tp_number`);

--
-- Constraints for table `points_transactions`
--
ALTER TABLE `points_transactions`
  ADD CONSTRAINT `fk_pt_challenge` FOREIGN KEY (`challengeID`) REFERENCES `challenges` (`challengeID`),
  ADD CONSTRAINT `fk_pt_device` FOREIGN KEY (`deviceID`) REFERENCES `devices` (`deviceID`),
  ADD CONSTRAINT `fk_pt_redemption` FOREIGN KEY (`redemptionID`) REFERENCES `redemptions` (`redemptionID`),
  ADD CONSTRAINT `fk_pt_user` FOREIGN KEY (`tp_number`) REFERENCES `users` (`tp_number`);

--
-- Constraints for table `redemptions`
--
ALTER TABLE `redemptions`
  ADD CONSTRAINT `fk_red_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`tp_number`),
  ADD CONSTRAINT `fk_red_reward` FOREIGN KEY (`rewardID`) REFERENCES `rewards` (`rewardID`),
  ADD CONSTRAINT `fk_red_user` FOREIGN KEY (`tp_number`) REFERENCES `users` (`tp_number`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`tp_number`) REFERENCES `users` (`tp_number`);

--
-- Constraints for table `volunteers`
--
ALTER TABLE `volunteers`
  ADD CONSTRAINT `fk_volunteers_by` FOREIGN KEY (`assigned_by`) REFERENCES `admins` (`tp_number`),
  ADD CONSTRAINT `fk_volunteers_user` FOREIGN KEY (`tp_number`) REFERENCES `users` (`tp_number`);

--
-- Constraints for table `volunteer_request`
--
ALTER TABLE `volunteer_request`
  ADD CONSTRAINT `fk_vr_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`tp_number`),
  ADD CONSTRAINT `fk_vr_user` FOREIGN KEY (`tp_number`) REFERENCES `users` (`tp_number`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
