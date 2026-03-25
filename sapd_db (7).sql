-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 25, 2026 at 04:06 AM
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
-- Database: `sapd_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cctv_requests`
--

CREATE TABLE `cctv_requests` (
  `id` int(11) NOT NULL,
  `requestor_name` varchar(255) NOT NULL,
  `dept` varchar(255) DEFAULT NULL,
  `incident_date` date NOT NULL,
  `incident_time` time NOT NULL,
  `location` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `purpose` text NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `evaluation` text DEFAULT NULL,
  `level_section` varchar(255) DEFAULT NULL,
  `reason` text NOT NULL,
  `assisted_by` varchar(255) DEFAULT NULL,
  `reviewed_by` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cctv_requests`
--

INSERT INTO `cctv_requests` (`id`, `requestor_name`, `dept`, `incident_date`, `incident_time`, `location`, `description`, `purpose`, `status`, `created_at`, `evaluation`, `level_section`, `reason`, `assisted_by`, `reviewed_by`) VALUES
(4, 'Jhon Vianey D. Bulanadi', NULL, '2026-02-26', '09:59:00', 'Hallway', '', '', 'Pending', '2026-02-26 01:59:55', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'BSIT ', 'Lost Wallet', 'asasasasasa', 'sasasasasasas');

-- --------------------------------------------------------

--
-- Table structure for table `employee_applications`
--

CREATE TABLE `employee_applications` (
  `id` int(11) NOT NULL,
  `applicant_type` varchar(50) DEFAULT 'EMPLOYEE',
  `applicant_name` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `license_no` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `fb_account` varchar(100) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `vehicle_brand` varchar(50) DEFAULT NULL,
  `vehicle_color` varchar(50) DEFAULT NULL,
  `or_no` varchar(50) DEFAULT NULL,
  `cr_no` varchar(50) DEFAULT NULL,
  `emerg_name` varchar(255) DEFAULT NULL,
  `emerg_address` text DEFAULT NULL,
  `emerg_relation` varchar(100) DEFAULT NULL,
  `emerg_contact` varchar(50) DEFAULT NULL,
  `checklist_data` text DEFAULT NULL,
  `secondary_vehicles` text DEFAULT NULL,
  `violation_data` text DEFAULT NULL,
  `image_paths` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_applications`
--

INSERT INTO `employee_applications` (`id`, `applicant_type`, `applicant_name`, `department`, `address`, `contact_number`, `license_no`, `email`, `fb_account`, `vehicle_type`, `vehicle_brand`, `vehicle_color`, `or_no`, `cr_no`, `emerg_name`, `emerg_address`, `emerg_relation`, `emerg_contact`, `checklist_data`, `secondary_vehicles`, `violation_data`, `image_paths`, `created_at`) VALUES
(1, 'EMPLOYEE', 'dadasd', 'asdad', 'sdad', 'asdad', 'asdad', 'asdad@gmail.com', 'adssad', 'asdad', 'asdad', 'asdad', 'asdad', 'asdad', 'asdad', 'asdsad', 'asdad', 'asdadsad', '{\"chk_approved\":\"1\",\"chk_cr\":\"1\",\"chk_or\":\"1\",\"chk_nonpro_lic\":\"1\",\"chk_id_2x2\":\"1\",\"chk_id_1x1\":\"1\"}', '[]', '[]', '[]', '2026-03-22 16:59:13');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `start_event` datetime NOT NULL,
  `end_event` datetime NOT NULL,
  `color` varchar(20) DEFAULT '#4318ff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facilities_inspections`
--

CREATE TABLE `facilities_inspections` (
  `id` int(11) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `reporter` varchar(255) DEFAULT NULL,
  `report_date` date NOT NULL,
  `report_time` time NOT NULL,
  `concerns` text DEFAULT NULL,
  `other_concern` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `image_paths` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Recorded',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facility_inspections`
--

CREATE TABLE `facility_inspections` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `inspection_date` date NOT NULL,
  `inspection_time` time NOT NULL,
  `description` text NOT NULL,
  `image_paths` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Inspected',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_sizes` text DEFAULT NULL,
  `image_size` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facility_inspections`
--

INSERT INTO `facility_inspections` (`id`, `title`, `location`, `inspection_date`, `inspection_time`, `description`, `image_paths`, `status`, `created_at`, `image_sizes`, `image_size`) VALUES
(5, 'Sample', 'test', '2026-03-10', '11:27:00', 'sample', '[\"uploads\\/facilities\\/fac_69af90f82f3a1_0.png\",\"uploads\\/facilities\\/fac_69af90f82fe75_1.png\"]', 'Inspected', '2026-03-10 03:33:12', NULL, '[35,54]'),
(7, 'Sample', 'sample', '2026-03-24', '19:55:00', 'dasdsadasdsadsadsadsad', NULL, 'Inspected', '2026-03-24 11:55:26', NULL, '[]');

-- --------------------------------------------------------

--
-- Table structure for table `form_submissions`
--

CREATE TABLE `form_submissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `form_type` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `global_permit_sequence`
--

CREATE TABLE `global_permit_sequence` (
  `id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `global_permit_sequence`
--

INSERT INTO `global_permit_sequence` (`id`, `created_at`) VALUES
(1, '2026-03-22 10:43:07'),
(2, '2026-03-22 10:43:29'),
(3, '2026-03-22 10:43:42');

-- --------------------------------------------------------

--
-- Table structure for table `guidance_images`
--

CREATE TABLE `guidance_images` (
  `id` int(11) NOT NULL,
  `referral_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guidance_referrals`
--

CREATE TABLE `guidance_referrals` (
  `id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `grade_section` varchar(255) NOT NULL,
  `referred_by` varchar(255) NOT NULL,
  `referral_date` date NOT NULL,
  `reason` text NOT NULL,
  `actions_taken` text DEFAULT NULL,
  `image_paths` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `offense_list` text DEFAULT NULL,
  `action_list` text DEFAULT NULL,
  `narrative_action` text DEFAULT NULL,
  `offense_type` text DEFAULT NULL,
  `referral_time` time DEFAULT NULL,
  `reason_list` text DEFAULT NULL,
  `other_reason` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `referrer` varchar(255) DEFAULT NULL,
  `reasons` text DEFAULT NULL,
  `image_size` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guidance_referrals`
--

INSERT INTO `guidance_referrals` (`id`, `student_name`, `grade_section`, `referred_by`, `referral_date`, `reason`, `actions_taken`, `image_paths`, `status`, `created_at`, `offense_list`, `action_list`, `narrative_action`, `offense_type`, `referral_time`, `reason_list`, `other_reason`, `description`, `referrer`, `reasons`, `image_size`) VALUES
(7, 'Sample', 'BSIt', '', '2026-03-05', '', NULL, '[\"uploads\\/guidance\\/guide_69a92d26bcb65_0.png\",\"uploads\\/guidance\\/guide_69a92d26bd457_1.png\"]', 'Pending', '2026-03-05 07:13:42', NULL, NULL, NULL, NULL, '15:12:00', NULL, '', 'asadadasdasdadasdsadasdasdadadadadadadadadadsadasdadadsasdadadadadadadsasdasdadaddasdada', 'Sample', '[\"Victim of bullying\"]', NULL),
(10, 'Sample', 'BSIT', '', '2026-03-25', '', NULL, '[\"uploads\\/guidance\\/guide_69c3302c9d19c_0.jpg\",\"uploads\\/guidance\\/guide_69c3302c9dce2_1.png\"]', 'Pending', '2026-03-25 00:45:32', NULL, NULL, NULL, NULL, '08:43:00', NULL, '', 'sample', 'Sample', '[\"Victim of bullying\"]', '[34,62]');

-- --------------------------------------------------------

--
-- Table structure for table `incident_reports`
--

CREATE TABLE `incident_reports` (
  `id` int(11) NOT NULL,
  `case_title` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `incident_date` date NOT NULL,
  `incident_time` time NOT NULL,
  `description` text NOT NULL,
  `status` varchar(50) DEFAULT 'Recorded',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_path` varchar(255) DEFAULT NULL,
  `image_paths` text DEFAULT NULL,
  `student_name` varchar(255) DEFAULT NULL,
  `level_section` varchar(100) DEFAULT NULL,
  `parent_name` varchar(255) DEFAULT NULL,
  `adviser` varchar(255) DEFAULT NULL,
  `image_size` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incident_reports`
--

INSERT INTO `incident_reports` (`id`, `case_title`, `location`, `incident_date`, `incident_time`, `description`, `status`, `created_at`, `image_path`, `image_paths`, `student_name`, `level_section`, `parent_name`, `adviser`, `image_size`) VALUES
(10, 'dasdadadasd', 'dadadasd', '2026-03-06', '14:32:00', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Recorded', '2026-03-06 06:32:08', NULL, '[\"uploads\\/incidents\\/inc_69aa74e811ebf_0.png\",\"uploads\\/incidents\\/inc_69aa74e81305b_1.png\"]', 'asdadadasd', 'adadadadada', 'adadsadada', 'adadadadada', '[40,32]'),
(11, 'adadadad', 'adadadads', '2026-03-10', '08:58:00', 'dasdaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Recorded', '2026-03-10 00:58:45', NULL, '[\"uploads\\/incidents\\/inc_69af6cc5c60dc_0.png\",\"uploads\\/incidents\\/inc_69af6cc5c6d59_1.png\"]', 'adadsads', 'adsaadads', 'eqwdaddqdad', 'adadadasd', '[38,40]');

-- --------------------------------------------------------

--
-- Table structure for table `layout_settings`
--

CREATE TABLE `layout_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) DEFAULT 'default',
  `card_w` int(11) DEFAULT 350,
  `card_h` int(11) DEFAULT 240,
  `name_size` int(11) DEFAULT 12,
  `name_x` int(11) DEFAULT 0,
  `name_y` int(11) DEFAULT 120,
  `dept_size` int(11) DEFAULT 11,
  `dept_x` int(11) DEFAULT 0,
  `dept_y` int(11) DEFAULT 139,
  `plate_size` int(11) DEFAULT 11,
  `plate_x` int(11) DEFAULT 45,
  `plate_y` int(11) DEFAULT 35,
  `qr_size` int(11) DEFAULT 60,
  `qr_x` int(11) DEFAULT 5,
  `qr_y` int(11) DEFAULT 15,
  `count_size` int(11) DEFAULT 20,
  `count_x` int(11) DEFAULT 0,
  `count_y` int(11) DEFAULT -25,
  `sy_size` int(11) DEFAULT 11,
  `sy_x` int(11) DEFAULT 0,
  `sy_y` int(11) DEFAULT 58,
  `school_year` varchar(50) DEFAULT '2025-2026',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `layout_settings`
--

INSERT INTO `layout_settings` (`id`, `setting_name`, `card_w`, `card_h`, `name_size`, `name_x`, `name_y`, `dept_size`, `dept_x`, `dept_y`, `plate_size`, `plate_x`, `plate_y`, `qr_size`, `qr_x`, `qr_y`, `count_size`, `count_x`, `count_y`, `sy_size`, `sy_x`, `sy_y`, `school_year`, `created_at`) VALUES
(3, 'Default Layout', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', '2026-01-09 07:06:27');

-- --------------------------------------------------------

--
-- Table structure for table `non_pro_permits`
--

CREATE TABLE `non_pro_permits` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `course` varchar(255) NOT NULL,
  `plate_number` varchar(50) NOT NULL,
  `fb_link` text DEFAULT NULL,
  `permit_number` int(11) NOT NULL,
  `school_year` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `non_pro_permits`
--

INSERT INTO `non_pro_permits` (`id`, `name`, `course`, `plate_number`, `fb_link`, `permit_number`, `school_year`, `created_at`) VALUES
(1, 'dasdad', 'dadsadsa', 'adsad', 'adsad', 3, 'Enter AY', '2026-03-22 10:43:42');

-- --------------------------------------------------------

--
-- Table structure for table `parking_applications`
--

CREATE TABLE `parking_applications` (
  `id` int(11) NOT NULL,
  `app_date` date DEFAULT NULL,
  `file_no` varchar(50) DEFAULT NULL,
  `applicant_type` enum('Employee','Student') DEFAULT 'Employee',
  `last_name` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `mi` varchar(10) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `cel_no` varchar(50) DEFAULT NULL,
  `license_no` varchar(50) DEFAULT NULL,
  `or_no` varchar(50) DEFAULT NULL,
  `cr_no` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `fb_account` varchar(100) DEFAULT NULL,
  `p_type` varchar(50) DEFAULT NULL,
  `p_brand` varchar(50) DEFAULT NULL,
  `p_color` varchar(50) DEFAULT NULL,
  `emergency_name` varchar(150) DEFAULT NULL,
  `emergency_addr` varchar(255) DEFAULT NULL,
  `emergency_rel` varchar(50) DEFAULT NULL,
  `emergency_contact` varchar(50) DEFAULT NULL,
  `extra_vehicles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_vehicles`)),
  `documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`documents`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `applicant_name` varchar(255) DEFAULT '',
  `contact_number` varchar(255) DEFAULT '',
  `vehicle_type` varchar(255) DEFAULT '',
  `vehicle_brand` varchar(255) DEFAULT '',
  `vehicle_color` varchar(255) DEFAULT '',
  `emerg_name` varchar(255) DEFAULT '',
  `emerg_address` text DEFAULT NULL,
  `emerg_relation` varchar(255) DEFAULT '',
  `emerg_contact` varchar(255) DEFAULT '',
  `image_paths` text DEFAULT NULL,
  `checklist_data` text DEFAULT NULL,
  `secondary_vehicles` text DEFAULT NULL,
  `violation_data` text DEFAULT NULL,
  `course_year` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_applications`
--

INSERT INTO `parking_applications` (`id`, `app_date`, `file_no`, `applicant_type`, `last_name`, `first_name`, `mi`, `department`, `address`, `cel_no`, `license_no`, `or_no`, `cr_no`, `email`, `fb_account`, `p_type`, `p_brand`, `p_color`, `emergency_name`, `emergency_addr`, `emergency_rel`, `emergency_contact`, `extra_vehicles`, `documents`, `created_at`, `applicant_name`, `contact_number`, `vehicle_type`, `vehicle_brand`, `vehicle_color`, `emerg_name`, `emerg_address`, `emerg_relation`, `emerg_contact`, `image_paths`, `checklist_data`, `secondary_vehicles`, `violation_data`, `course_year`) VALUES
(1, NULL, NULL, 'Employee', NULL, NULL, NULL, 'BSHM', 'Lacmit Arayat Pampanga', NULL, 'c1024006075', '2233904680', '48547067', 'Christianjeffluciano@gmail.com', 'Christian jeff luciano', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-16 05:10:42', 'Luciano, christian jeff, garcia', '09567548085', 'Single Motor', 'Aerox Yamaha', 'Black', 'Florencia Luciano', 'Lacmit Arayat Pampanga', 'Mother', '0956718085', '', '', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `permits`
--

CREATE TABLE `permits` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `plate_number` varchar(50) NOT NULL,
  `fb_link` text DEFAULT NULL,
  `permit_number` int(11) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permits`
--

INSERT INTO `permits` (`id`, `name`, `department`, `plate_number`, `fb_link`, `permit_number`, `school_year`, `created_at`) VALUES
(1, 'test', 'bsit', 'sadada', 'sample', 1, 'Enter AY', '2026-03-22 10:43:07');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `card_w` int(11) DEFAULT 480,
  `card_h` int(11) DEFAULT 270,
  `name_size` int(11) DEFAULT 18,
  `name_x` int(11) DEFAULT 130,
  `name_y` int(11) DEFAULT 120,
  `dept_size` int(11) DEFAULT 12,
  `dept_x` int(11) DEFAULT 130,
  `dept_y` int(11) DEFAULT 145,
  `plate_size` int(11) DEFAULT 11,
  `plate_x` int(11) DEFAULT 87,
  `plate_y` int(11) DEFAULT 24,
  `qr_size` int(11) DEFAULT 60,
  `qr_x` int(11) DEFAULT 20,
  `qr_y` int(11) DEFAULT 15,
  `count_size` int(11) DEFAULT 20,
  `count_x` int(11) DEFAULT 0,
  `count_y` int(11) DEFAULT -20
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `card_w`, `card_h`, `name_size`, `name_x`, `name_y`, `dept_size`, `dept_x`, `dept_y`, `plate_size`, `plate_x`, `plate_y`, `qr_size`, `qr_x`, `qr_y`, `count_size`, `count_x`, `count_y`) VALUES
(1, 480, 270, 18, 130, 135, 12, 130, 160, 11, 45, 35, 60, 20, 15, 20, 0, -25);

-- --------------------------------------------------------

--
-- Table structure for table `student_applications`
--

CREATE TABLE `student_applications` (
  `id` int(11) NOT NULL,
  `applicant_type` varchar(50) DEFAULT 'STUDENT',
  `applicant_name` varchar(255) DEFAULT NULL,
  `course_year` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `license_no` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `fb_account` varchar(100) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `vehicle_brand` varchar(50) DEFAULT NULL,
  `vehicle_color` varchar(50) DEFAULT NULL,
  `or_no` varchar(50) DEFAULT NULL,
  `cr_no` varchar(50) DEFAULT NULL,
  `emerg_name` varchar(255) DEFAULT NULL,
  `emerg_address` text DEFAULT NULL,
  `emerg_relation` varchar(100) DEFAULT NULL,
  `emerg_contact` varchar(50) DEFAULT NULL,
  `checklist_data` text DEFAULT NULL,
  `secondary_vehicles` text DEFAULT NULL,
  `violation_data` text DEFAULT NULL,
  `image_paths` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_applications`
--

INSERT INTO `student_applications` (`id`, `applicant_type`, `applicant_name`, `course_year`, `address`, `contact_number`, `license_no`, `email`, `fb_account`, `vehicle_type`, `vehicle_brand`, `vehicle_color`, `or_no`, `cr_no`, `emerg_name`, `emerg_address`, `emerg_relation`, `emerg_contact`, `checklist_data`, `secondary_vehicles`, `violation_data`, `image_paths`, `created_at`) VALUES
(3, 'STUDENT', 'Jhon Vianey D. Bulanadi', 'BSIT', 'bana. road, pangclara candaba, pampanga', '09553315195', 'c1024006075', 'jhonvianeydelapenabulanadi@gmail.com', 'Jhon Vianey D. Bulanadi', 'Single Motor', 'Aerox Yamaha', 'Black', '2233904680', '48547067', 'Honorio F. Bulanadi', 'bana. road, pangclara candaba, pampanga', 'Father', '0956718085', '{\"chk_cr\":\"1\",\"chk_nonpro_lic\":\"1\",\"chk_pro_lic\":\"1\",\"chk_id_2x2\":\"1\",\"chk_id_1x1\":\"1\"}', '[]', '[]', '[]', '2026-03-22 16:51:21');

-- --------------------------------------------------------

--
-- Table structure for table `student_permits`
--

CREATE TABLE `student_permits` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `plate_number` varchar(50) NOT NULL,
  `fb_link` text DEFAULT NULL,
  `permit_number` int(11) NOT NULL,
  `school_year` varchar(50) NOT NULL,
  `valid_until` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_permits`
--

INSERT INTO `student_permits` (`id`, `name`, `department`, `plate_number`, `fb_link`, `permit_number`, `school_year`, `valid_until`, `created_at`) VALUES
(1, 'dadadada', 'dasdadasd', 'dasad', 'dasdsada', 2, '2024-2025', 'dadsadad', '2026-03-22 10:43:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'user',
  `status` varchar(20) DEFAULT 'pending',
  `verification_code` int(6) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `username`, `password`, `role`, `status`, `verification_code`, `created_at`) VALUES
(3, 'Sapd Staff', 'sapdstaff@gmail.com', 'sapdstaff', '$2y$10$nRIXjycQxV20CmaNNQRPpuTiZcMIpoVJH4JGmsfrNXZpoN9PJXFRG', 'user', 'active', 0, '2026-01-06 04:52:46'),
(4, 'System Admin', 'admin@sapd.com', 'admin', '$2y$10$dcTJNGOPC0Ji9NBW2EWN2uqtGIXE.mMwOPeSB7sOoSs565mL12M2a', 'admin', 'active', 0, '2026-01-06 04:54:07');

-- --------------------------------------------------------

--
-- Table structure for table `vaping_reports`
--

CREATE TABLE `vaping_reports` (
  `id` int(11) NOT NULL,
  `case_title` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `incident_date` date NOT NULL,
  `incident_time` time NOT NULL,
  `description` text NOT NULL,
  `image_paths` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Recorded',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_size` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vaping_reports`
--

INSERT INTO `vaping_reports` (`id`, `case_title`, `location`, `incident_date`, `incident_time`, `description`, `image_paths`, `status`, `created_at`, `image_size`) VALUES
(3, 'sadsa', 'asdad', '2026-03-22', '19:33:00', 'asdad', NULL, 'Recorded', '2026-03-22 11:33:45', '[]');

-- --------------------------------------------------------

--
-- Table structure for table `violators_report`
--

CREATE TABLE `violators_report` (
  `id` int(11) NOT NULL,
  `violator_name` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `violation` text NOT NULL,
  `incident_date` date NOT NULL,
  `incident_time` time NOT NULL,
  `safety_officer` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Logged',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `violator_logs`
--

CREATE TABLE `violator_logs` (
  `id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `student_name` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `violation` varchar(255) DEFAULT NULL,
  `report_time` time NOT NULL,
  `officer_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violator_logs`
--

INSERT INTO `violator_logs` (`id`, `report_date`, `student_name`, `location`, `violation`, `report_time`, `officer_name`, `created_at`) VALUES
(8, '2026-03-25', 'Sample', 'sample', 'sample', '08:36:00', 'sample', '2026-03-25 00:37:01');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cctv_requests`
--
ALTER TABLE `cctv_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_applications`
--
ALTER TABLE `employee_applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facilities_inspections`
--
ALTER TABLE `facilities_inspections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facility_inspections`
--
ALTER TABLE `facility_inspections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `form_submissions`
--
ALTER TABLE `form_submissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `global_permit_sequence`
--
ALTER TABLE `global_permit_sequence`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `guidance_images`
--
ALTER TABLE `guidance_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `referral_id` (`referral_id`);

--
-- Indexes for table `guidance_referrals`
--
ALTER TABLE `guidance_referrals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `incident_reports`
--
ALTER TABLE `incident_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `layout_settings`
--
ALTER TABLE `layout_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `non_pro_permits`
--
ALTER TABLE `non_pro_permits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parking_applications`
--
ALTER TABLE `parking_applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permits`
--
ALTER TABLE `permits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `student_applications`
--
ALTER TABLE `student_applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `student_permits`
--
ALTER TABLE `student_permits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_permit_number` (`permit_number`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vaping_reports`
--
ALTER TABLE `vaping_reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `violators_report`
--
ALTER TABLE `violators_report`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `violator_logs`
--
ALTER TABLE `violator_logs`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cctv_requests`
--
ALTER TABLE `cctv_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `employee_applications`
--
ALTER TABLE `employee_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facilities_inspections`
--
ALTER TABLE `facilities_inspections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facility_inspections`
--
ALTER TABLE `facility_inspections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `form_submissions`
--
ALTER TABLE `form_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `global_permit_sequence`
--
ALTER TABLE `global_permit_sequence`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `guidance_images`
--
ALTER TABLE `guidance_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guidance_referrals`
--
ALTER TABLE `guidance_referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `incident_reports`
--
ALTER TABLE `incident_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `layout_settings`
--
ALTER TABLE `layout_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `non_pro_permits`
--
ALTER TABLE `non_pro_permits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `parking_applications`
--
ALTER TABLE `parking_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `permits`
--
ALTER TABLE `permits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_applications`
--
ALTER TABLE `student_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_permits`
--
ALTER TABLE `student_permits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vaping_reports`
--
ALTER TABLE `vaping_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `violators_report`
--
ALTER TABLE `violators_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `violator_logs`
--
ALTER TABLE `violator_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `guidance_images`
--
ALTER TABLE `guidance_images`
  ADD CONSTRAINT `guidance_images_ibfk_1` FOREIGN KEY (`referral_id`) REFERENCES `guidance_referrals` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
