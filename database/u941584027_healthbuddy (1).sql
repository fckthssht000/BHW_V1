-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 30, 2025 at 09:27 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u941584027_healthbuddy`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `activity_logs_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`activity_logs_id`, `user_id`, `activity`, `created_at`) VALUES
(1, 3, 'DELETED: child_health_record records_id:129', '2025-11-23 09:52:50'),
(2, 2, 'DELETED: infant_record records_id:', '2025-11-26 06:27:45'),
(3, 2, 'DELETED: infant_record records_id:', '2025-11-26 06:27:51'),
(4, 2, 'NOTICE: We would like to inform you that there\'s going to be a medication in Amlodipine 10mg, Carvidolol 12.5mg this 2025-11-27. Please be there. to_user_id:12 schedule:immediate', '2025-11-27 03:32:39'),
(5, 2, 'NOTICE: We would like to inform you that there\'s going to be a medication in Amlodipine 10mg, Carvidolol 12.5mg this 2025-11-27. Please be there. to_user_id:15 schedule:immediate', '2025-11-27 03:32:39'),
(6, 2, 'NOTICE: We would like to inform you that there\'s going to be a medication in Amlodipine 10mg, Carvidolol 12.5mg this 2025-11-27. Please be there. to_user_id:24 schedule:immediate', '2025-11-27 03:32:39'),
(7, 2, 'NOTICE: We would like to inform you that there\'s going to be a medication in Amlodipine 10mg, Carvidolol 12.5mg this 2025-11-27. Please be there. to_user_id:32 schedule:immediate', '2025-11-27 03:32:39'),
(8, 2, 'NOTICE: We would like to inform you that there\'s going to be a medication in Amlodipine 10mg, Carvidolol 12.5mg this 2025-11-27. Please be there. to_user_id:35 schedule:immediate', '2025-11-27 03:32:39'),
(9, 2, 'NOTICE: We would like to inform you that there\'s going to be a medication in Amlodipine 10mg, Carvidolol 12.5mg this 2025-11-27. Please be there. to_user_id:40 schedule:immediate', '2025-11-27 03:32:39'),
(10, 2, 'NOTICE: We would like to inform you that there\'s going to be a medication in Amlodipine 10mg, Carvidolol 12.5mg this 2025-11-27. Please be there. to_user_id:42 schedule:immediate', '2025-11-27 03:32:39'),
(11, 2, 'NOTICE: We would like to inform you that there\'s going to be a medication in Amlodipine 10mg, Carvidolol 12.5mg this 2025-11-27. Please be there. to_user_id:152 schedule:immediate', '2025-11-27 03:32:39'),
(12, 2, 'NOTICE: We would like to inform you that there\'s going to be a medication in Amlodipine 10mg, Carvidolol 12.5mg this 2025-11-27. Please be there. to_user_id:155 schedule:immediate', '2025-11-27 03:32:39'),
(13, 2, 'NOTICE: We would like to inform you that there\'s going to be a medication in Amlodipine 10mg, Carvidolol 12.5mg this 2025-11-27. Please be there. to_user_id:156 schedule:immediate', '2025-11-27 03:32:39'),
(14, 3, 'UPDATED: child_health_record records_id:130', '2025-11-27 04:26:20'),
(15, 3, 'UPDATED: child_health_record records_id:247', '2025-11-27 04:26:39'),
(16, 3, 'UPDATED: child_health_record records_id:131', '2025-11-27 04:26:51'),
(17, 3, 'UPDATED: child_health_record records_id:130', '2025-11-27 04:27:09'),
(18, 2, 'UPDATED: child_health_record records_id:249', '2025-11-27 06:54:13'),
(19, 2, 'DELETED: child_health_record records_id:130', '2025-11-27 06:56:57'),
(20, 5, 'DELETED: infant_record records_id:', '2025-11-27 06:57:16'),
(21, 5, 'DELETED: infant_record records_id:', '2025-11-27 06:58:22'),
(22, 2, 'UPDATED: child_health_record records_id:437', '2025-11-27 06:58:24'),
(23, 5, 'UPDATED: senior_health_record records_id:458', '2025-11-27 12:17:31'),
(24, 5, 'UPDATED: senior_health_record records_id:457', '2025-11-27 12:17:47'),
(25, 5, 'UPDATED: senior_health_record records_id:459', '2025-11-27 12:17:58'),
(26, 2, 'UPDATED: senior_health_record records_id:264', '2025-11-27 12:43:24'),
(27, 2, 'UPDATED: senior_health_record records_id:456', '2025-11-27 12:43:39'),
(28, 2, 'UPDATED: senior_health_record records_id:455', '2025-11-27 12:43:53'),
(29, 2, 'UPDATED: senior_health_record records_id:454', '2025-11-27 12:44:07'),
(30, 2, 'UPDATED: child_health_record records_id:437', '2025-11-27 12:56:14'),
(31, 2, 'UPDATED: child_health_record records_id:246', '2025-11-27 12:56:24'),
(32, 2, 'UPDATED: child_health_record records_id:131', '2025-11-27 12:56:34'),
(33, 2, 'UPDATED: child_health_record records_id:468', '2025-11-27 12:56:53'),
(34, 2, 'UPDATED: child_health_record records_id:249', '2025-11-27 12:57:06'),
(35, 2, 'UPDATED: child_health_record records_id:247', '2025-11-27 12:57:25'),
(36, 2, 'UPDATED: child_health_record records_id:473', '2025-11-27 12:57:39'),
(37, 2, 'UPDATED: child_health_record records_id:471', '2025-11-27 12:57:49'),
(38, 2, 'UPDATED: child_health_record records_id:472', '2025-11-27 12:58:06'),
(39, 2, 'UPDATED: child_health_record records_id:469', '2025-11-27 12:58:19'),
(40, 2, 'UPDATED: child_health_record records_id:434', '2025-11-27 13:25:15'),
(41, 6, 'UPDATED: child_health_record records_id:528', '2025-11-27 13:51:23'),
(42, 2, 'UPDATED: child_health_record records_id:520', '2025-11-27 14:13:55'),
(43, 2, 'NOTICE: We would like to inform you that there\'s going to be a medication in Amlodipine 5mg, Amlodipine 10mg, Losartan 100mg, Metoprolol 50mg, Carvidolol 12.5mg, Simvastatin 20mg, Metformin 500mg, Gliclazide 30mg this 2025-11-28. Please be there. to_user_', '2025-11-28 01:26:00');

-- --------------------------------------------------------

--
-- Table structure for table `address`
--

CREATE TABLE `address` (
  `address_id` int(11) NOT NULL,
  `street` varchar(255) DEFAULT NULL,
  `purok` varchar(255) DEFAULT NULL,
  `barangay` varchar(255) DEFAULT NULL,
  `municipality` varchar(255) DEFAULT NULL,
  `province` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `address`
--

INSERT INTO `address` (`address_id`, `street`, `purok`, `barangay`, `municipality`, `province`, `country`) VALUES
(1, '', 'Purok 1', 'Sta. Maria', 'Camiling', 'Tarlac', 'Philippines'),
(2, '', 'Purok 2', 'Sta. Maria', 'Camiling', 'Tarlac', 'Philippines'),
(3, '', 'Purok 3', 'Sta. Maria', 'Camiling', 'Tarlac', 'Philippines'),
(4, '', 'Purok 4A', 'Sta. Maria', 'Camiling', 'Tarlac', 'Philippines'),
(5, '', 'Purok 4B', 'Sta. Maria', 'Camiling', 'Tarlac', 'Philippines'),
(6, '', 'Purok 5', 'Sta. Maria', 'Camiling', 'Tarlac', 'Philippines'),
(7, '', 'Purok 6', 'Sta. Maria', 'Camiling', 'Tarlac', 'Philippines'),
(8, '', 'Purok 7', 'Sta. Maria', 'Camiling', 'Tarlac', 'Philippines');

-- --------------------------------------------------------

--
-- Table structure for table `child_immunization`
--

CREATE TABLE `child_immunization` (
  `child_record_id` int(11) NOT NULL,
  `immunization_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `child_immunization`
--

INSERT INTO `child_immunization` (`child_record_id`, `immunization_id`) VALUES
(24, 1),
(24, 2),
(24, 3),
(24, 4),
(24, 6),
(24, 7),
(24, 9),
(24, 11),
(24, 12);

-- --------------------------------------------------------

--
-- Table structure for table `child_record`
--

CREATE TABLE `child_record` (
  `child_record_id` int(11) NOT NULL,
  `records_id` int(11) NOT NULL,
  `child_type` enum('Infant','Child') DEFAULT NULL,
  `weight` varchar(255) DEFAULT NULL,
  `height` varchar(255) DEFAULT NULL,
  `measurement_date` date DEFAULT NULL,
  `immunization_status` text DEFAULT NULL,
  `risk_observed` varchar(255) DEFAULT NULL,
  `service_source` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `child_record`
--

INSERT INTO `child_record` (`child_record_id`, `records_id`, `child_type`, `weight`, `height`, `measurement_date`, `immunization_status`, `risk_observed`, `service_source`, `created_at`, `updated_at`) VALUES
(3, 131, 'Child', '7.5', '64', '2025-10-22', 'MMR (12-15 Months),Vitamin A (12-59 Months)', '', 'Health Center', '2025-11-23 09:53:16', '2025-11-27 12:56:34'),
(4, 246, 'Child', '5.41', '50', '2025-10-22', 'MMR (12-15 Months),Vitamin A (12-59 Months)', 'Others,Pneumonia', 'Health Center', '2025-11-27 04:23:44', '2025-11-27 12:56:24'),
(5, 247, 'Child', '5.43', '48', '2025-10-22', 'Vitamin A (12-59 Months),Fully Immunized (FIC)', 'Others,Pneumonia', 'Health Center', '2025-11-27 04:24:22', '2025-11-27 12:57:25'),
(6, 249, 'Child', '5.11', '50.56', '2025-10-22', 'Vitamin A (12-59 Months),Fully Immunized (FIC)', '', 'Health Center', '2025-11-27 04:25:38', '2025-11-27 12:57:06'),
(7, 434, 'Child', '14.5', '94', '2024-11-30', 'MMR (12-15 Months),Vitamin A (12-59 Months)', '', 'Health Center', '2025-11-27 06:53:35', '2025-11-27 13:25:15'),
(8, 436, 'Child', '20.3', '115', '0000-00-00', 'Vitamin A (12-59 Months),Fully Immunized (FIC)', '', 'Private Clinic', '2025-11-27 06:55:14', '2025-11-27 06:55:14'),
(9, 437, 'Child', '20.3', '115', '2025-10-22', 'Vitamin A (12-59 Months),Fully Immunized (FIC)', '', 'Private Clinic', '2025-11-27 06:57:55', '2025-11-27 12:56:14'),
(10, 468, 'Child', '14.2', '96', '2025-10-22', 'Vitamin A (12-59 Months),Fully Immunized (FIC)', 'Tigdas,', 'Health Center', '2025-11-27 12:51:41', '2025-11-27 12:56:53'),
(11, 469, 'Child', '12.1', '86', '2025-10-22', 'Vitamin A (12-59 Months)', 'Others,Diarrhea', 'Private Clinic', '2025-11-27 12:52:55', '2025-11-27 12:58:19'),
(12, 470, 'Child', '20.6', '117', '2025-10-23', 'Vitamin A (12-59 Months),Completely Immunized (CIC)', '', 'Health Center', '2025-11-27 12:53:56', '2025-11-27 12:53:56'),
(13, 471, 'Child', '12', '85', '2025-10-22', 'MMR (12-15 Months)', 'Tigdas,', 'Health Center', '2025-11-27 12:54:28', '2025-11-27 12:57:49'),
(14, 472, 'Child', '17', '105', '2025-10-22', 'Vitamin A (12-59 Months),Fully Immunized (FIC)', 'Others,Diarrhea', 'Private Clinic', '2025-11-27 12:55:06', '2025-11-27 12:58:06'),
(15, 473, 'Child', '10.2', '78', '2025-10-22', 'MMR (12-15 Months),Vitamin A (12-59 Months)', '', 'Health Center', '2025-11-27 12:55:48', '2025-11-27 12:57:39'),
(16, 476, 'Child', '18.5', '110', '2025-10-22', 'Vitamin A (12-59 Months),Completely Immunized (CIC)', '', 'Health Center', '2025-11-27 13:08:50', '2025-11-27 13:08:50'),
(17, 477, 'Child', '17.2', '106', '2025-10-22', 'Vitamin A (12-59 Months),Fully Immunized (FIC)', '', 'Private Clinic', '2025-11-27 13:12:36', '2025-11-27 13:12:36'),
(18, 518, 'Child', '13.7', '91', '2025-10-22', 'Vitamin A (12-59 Months),Fully Immunized (FIC)', 'Diarrhea', 'Health Center', '2025-11-27 13:36:50', '2025-11-27 13:36:50'),
(19, 519, 'Child', '19.5', '112', '2025-10-22', 'Vitamin A (12-59 Months),Completely Immunized (CIC)', 'Diarrhea', 'Health Center', '2025-11-27 13:37:29', '2025-11-27 13:37:29'),
(20, 520, 'Child', '16.8', '103', '2025-10-30', 'Vitamin A (12-59 Months),Fully Immunized (FIC)', '', 'Health Center', '2025-11-27 13:37:56', '2025-11-27 14:13:55'),
(21, 525, 'Child', '10.2', '78', '2025-10-22', 'MMR (12-15 Months),Vitamin A (12-59 Months)', '', 'Health Center', '2025-11-27 13:43:03', '2025-11-27 13:43:03'),
(22, 528, 'Child', '19.63', '112', '2025-10-22', 'MMR (12-15 Months),Vitamin A (12-59 Months)', '', 'Health Center', '2025-11-27 13:51:03', '2025-11-27 13:51:23'),
(23, 534, 'Child', '17.2', '106', '2025-10-22', 'Vitamin A (12-59 Months)', 'Pneumonia', 'Health Center', '2025-11-27 13:53:35', '2025-11-27 13:53:35'),
(24, 596, 'Infant', '3.8', '50', '2025-08-14', NULL, NULL, 'Private Clinic', '2025-11-27 15:59:10', '2025-11-27 15:59:10');

-- --------------------------------------------------------

--
-- Table structure for table `family_planning_record`
--

CREATE TABLE `family_planning_record` (
  `fp_id` int(11) NOT NULL,
  `records_id` int(11) NOT NULL,
  `uses_fp_method` enum('Y','N') NOT NULL,
  `fp_method` varchar(255) DEFAULT NULL,
  `months_used` varchar(255) DEFAULT NULL,
  `reason_not_using` text DEFAULT NULL,
  `service_source` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `family_planning_record`
--

INSERT INTO `family_planning_record` (`fp_id`, `records_id`, `uses_fp_method`, `fp_method`, `months_used`, `reason_not_using`, `service_source`, `created_at`, `updated_at`) VALUES
(1, 550, 'Y', 'P (Pills),IUD,DMPA,NFP-LAM (Lactation Amenorrhea Method),Condom', 'March,April,May,June', '', NULL, '2025-11-27 14:42:30', '2025-11-27 14:42:30'),
(3, 552, 'Y', 'P (Pills)', 'January,February,March,April,May,June,July', '', NULL, '2025-11-27 15:02:22', '2025-11-27 15:02:22'),
(4, 553, 'N', '', '', 'Dalaga', NULL, '2025-11-27 15:02:45', '2025-11-27 15:02:45'),
(5, 554, 'N', '', '', 'Dalaga', NULL, '2025-11-27 15:03:02', '2025-11-27 15:03:02'),
(6, 555, 'N', '', '', 'Dalaga', NULL, '2025-11-27 15:03:15', '2025-11-27 15:03:15'),
(7, 556, 'N', '', '', 'OFW', NULL, '2025-11-27 15:03:41', '2025-11-27 15:03:41'),
(8, 557, 'N', '', '', 'Dalaga', NULL, '2025-11-27 15:05:11', '2025-11-27 15:05:11'),
(9, 558, 'N', '', '', 'Ayaw', NULL, '2025-11-27 15:05:38', '2025-11-27 15:05:38'),
(10, 560, 'N', '', '', 'Dalaga', NULL, '2025-11-27 15:11:24', '2025-11-27 15:11:24'),
(11, 561, 'N', '', '', 'Dalaga', NULL, '2025-11-27 15:11:37', '2025-11-27 15:11:37'),
(12, 562, 'N', '', '', 'Dalaga', NULL, '2025-11-27 15:11:56', '2025-11-27 15:11:56'),
(13, 563, 'N', '', '', 'Wala', NULL, '2025-11-27 15:12:50', '2025-11-27 15:12:50'),
(14, 564, 'N', '', '', 'Dalaga', NULL, '2025-11-27 15:13:38', '2025-11-27 15:13:38'),
(15, 565, 'N', '', '', 'Walang Asawa', NULL, '2025-11-27 15:13:51', '2025-11-27 15:13:51'),
(16, 566, 'N', '', '', 'Wala', NULL, '2025-11-27 15:14:06', '2025-11-27 15:14:06'),
(17, 567, 'N', '', '', 'Wala', NULL, '2025-11-27 15:14:18', '2025-11-27 15:14:18'),
(18, 568, 'N', '', '', 'Wala', NULL, '2025-11-27 15:17:46', '2025-11-27 15:17:46'),
(19, 569, 'N', '', '', 'Wala', NULL, '2025-11-27 15:17:57', '2025-11-27 15:17:57'),
(20, 570, 'N', '', '', 'Walang Asawa', NULL, '2025-11-27 15:18:09', '2025-11-27 15:18:09'),
(21, 571, 'N', '', '', 'Wala', NULL, '2025-11-27 15:18:33', '2025-11-27 15:18:33'),
(22, 572, 'N', '', '', 'Wala', NULL, '2025-11-27 15:29:11', '2025-11-27 15:29:11'),
(23, 573, 'N', '', '', 'Dalaga', NULL, '2025-11-27 15:29:19', '2025-11-27 15:29:19'),
(24, 574, 'N', '', '', 'Dalaga', NULL, '2025-11-27 15:29:27', '2025-11-27 15:29:27'),
(25, 575, 'N', '', '', 'Wala', NULL, '2025-11-27 15:29:36', '2025-11-27 15:29:36'),
(26, 576, 'N', '', '', 'Walang asawa', NULL, '2025-11-27 15:29:49', '2025-11-27 15:29:49'),
(27, 583, 'N', '', '', 'Wala', NULL, '2025-11-27 15:46:17', '2025-11-27 15:46:17'),
(28, 584, 'N', '', '', 'Dalaga', NULL, '2025-11-27 15:46:33', '2025-11-27 15:46:33'),
(29, 585, 'N', '', '', 'Wala', NULL, '2025-11-27 15:46:51', '2025-11-27 15:46:51'),
(30, 586, 'N', '', '', 'Wala', NULL, '2025-11-27 15:47:11', '2025-11-27 15:47:11'),
(31, 587, 'N', '', '', 'Wala', NULL, '2025-11-27 15:47:24', '2025-11-27 15:47:24'),
(32, 588, 'N', '', '', 'Wala', NULL, '2025-11-27 15:47:47', '2025-11-27 15:47:47'),
(33, 589, 'N', '', '', 'Dalaga', NULL, '2025-11-27 15:47:56', '2025-11-27 15:47:56'),
(34, 590, 'N', '', '', 'Wala', NULL, '2025-11-27 15:48:06', '2025-11-27 15:48:06'),
(35, 591, 'N', '', '', 'Wala', NULL, '2025-11-27 15:48:27', '2025-11-27 15:48:27'),
(36, 592, 'N', '', '', 'Wala', NULL, '2025-11-27 15:48:41', '2025-11-27 15:48:41'),
(37, 593, 'N', '', '', 'Wala', NULL, '2025-11-27 15:49:03', '2025-11-27 15:49:03'),
(38, 594, 'N', '', '', 'Dalaga', NULL, '2025-11-27 15:49:11', '2025-11-27 15:49:11'),
(39, 595, 'N', '', '', 'Ayaw', NULL, '2025-11-27 15:49:18', '2025-11-27 15:49:18'),
(40, 600, 'Y', 'P (Pills),Condom', 'January,February,March,April,May,June', '', NULL, '2025-11-27 17:36:16', '2025-11-27 17:36:16'),
(41, 601, 'N', '', '', 'Wala', NULL, '2025-11-27 17:36:25', '2025-11-27 17:36:25'),
(42, 602, 'N', '', '', 'Wala', NULL, '2025-11-27 17:36:36', '2025-11-27 17:36:36'),
(43, 603, 'N', '', '', 'Dalaga', NULL, '2025-11-27 17:36:44', '2025-11-27 17:36:44'),
(44, 604, 'N', '', '', 'Dalaga', NULL, '2025-11-27 17:36:51', '2025-11-27 17:36:51'),
(45, 605, 'N', '', '', 'Walang asawa', NULL, '2025-11-27 17:37:02', '2025-11-27 17:37:02'),
(46, 606, 'N', '', '', 'Buntis', NULL, '2025-11-27 17:37:30', '2025-11-27 17:37:30'),
(47, 607, 'N', '', '', 'wala', NULL, '2025-11-27 17:37:46', '2025-11-27 17:37:46'),
(48, 608, 'N', '', '', 'Walang asawa', NULL, '2025-11-27 17:38:02', '2025-11-27 17:38:02'),
(49, 609, 'Y', 'P (Pills),Condom', '', '', NULL, '2025-11-27 17:38:45', '2025-11-27 17:38:45'),
(50, 610, 'N', '', '', 'Wala', NULL, '2025-11-27 17:39:05', '2025-11-27 17:39:05'),
(51, 611, 'N', '', '', 'Wala', NULL, '2025-11-27 17:39:33', '2025-11-27 17:39:33'),
(52, 612, 'N', '', '', 'Dalaga', NULL, '2025-11-27 17:39:46', '2025-11-27 17:39:46'),
(53, 613, 'N', '', '', 'Wala', NULL, '2025-11-27 17:41:45', '2025-11-27 17:41:45'),
(54, 614, 'N', '', '', 'Dalaga', NULL, '2025-11-27 17:42:05', '2025-11-27 17:42:05'),
(55, 615, 'N', '', '', 'Dalaga', NULL, '2025-11-27 17:42:16', '2025-11-27 17:42:16'),
(56, 616, 'N', '', '', 'Wala', NULL, '2025-11-27 17:43:43', '2025-11-27 17:43:43'),
(57, 617, 'N', '', '', 'Wala', NULL, '2025-11-27 17:43:56', '2025-11-27 17:43:56'),
(58, 618, 'N', '', '', 'Ayaw', NULL, '2025-11-27 17:44:05', '2025-11-27 17:44:05'),
(59, 619, 'N', '', '', 'Walang asawa', NULL, '2025-11-27 17:44:14', '2025-11-27 17:44:14'),
(60, 620, 'N', '', '', 'Ayaw', NULL, '2025-11-27 17:44:30', '2025-11-27 17:44:30'),
(61, 621, 'N', '', '', 'Dalaga', NULL, '2025-11-27 17:45:03', '2025-11-27 17:45:03');

-- --------------------------------------------------------

--
-- Table structure for table `household_record`
--

CREATE TABLE `household_record` (
  `household_record_id` int(11) NOT NULL,
  `records_id` int(11) NOT NULL,
  `water_source` varchar(100) DEFAULT NULL,
  `toilet_type` varchar(100) DEFAULT NULL,
  `visit_months` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `household_record`
--

INSERT INTO `household_record` (`household_record_id`, `records_id`, `water_source`, `toilet_type`, `visit_months`, `created_at`, `updated_at`) VALUES
(1, 67, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:17:39', '2025-11-23 08:17:39'),
(2, 68, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:17:39', '2025-11-23 08:17:39'),
(3, 69, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:17:39', '2025-11-23 08:17:39'),
(4, 70, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:17:39', '2025-11-23 08:17:39'),
(5, 71, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:18:52', '2025-11-23 08:18:52'),
(6, 72, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:18:52', '2025-11-23 08:18:52'),
(7, 73, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:18:52', '2025-11-23 08:18:52'),
(8, 74, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:18:52', '2025-11-23 08:18:52'),
(9, 75, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(10, 76, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(11, 77, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(12, 78, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(13, 79, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(14, 80, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(15, 81, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(16, 82, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(17, 83, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(18, 84, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(19, 85, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(20, 86, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(21, 87, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(22, 88, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(23, 89, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(24, 90, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(25, 91, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(26, 92, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:32:01', '2025-11-23 08:32:01'),
(27, 93, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:32:01', '2025-11-23 08:32:01'),
(28, 94, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:32:01', '2025-11-23 08:32:01'),
(29, 95, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:32:01', '2025-11-23 08:32:01'),
(30, 96, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(31, 97, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(32, 98, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(33, 99, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(34, 100, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(35, 101, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(36, 102, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(37, 103, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(38, 104, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:33:13', '2025-11-23 08:33:13'),
(39, 105, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:33:13', '2025-11-23 08:33:13'),
(40, 106, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:33:13', '2025-11-23 08:33:13'),
(41, 107, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:33:13', '2025-11-23 08:33:13'),
(42, 108, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:33:51', '2025-11-23 08:33:51'),
(43, 109, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:33:51', '2025-11-23 08:33:51'),
(44, 110, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:33:51', '2025-11-23 08:33:51'),
(45, 111, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:33:51', '2025-11-23 08:33:51'),
(46, 112, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:33:51', '2025-11-23 08:33:51'),
(47, 113, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:33:51', '2025-11-23 08:33:51'),
(48, 114, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:39:28', '2025-11-23 08:39:28'),
(49, 115, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:40:34', '2025-11-23 08:40:34'),
(50, 116, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:40:34', '2025-11-23 08:40:34'),
(51, 117, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:40:34', '2025-11-23 08:40:34'),
(52, 118, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:40:34', '2025-11-23 08:40:34'),
(53, 119, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:40:34', '2025-11-23 08:40:34'),
(54, 120, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:40:34', '2025-11-23 08:40:34'),
(55, 121, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(56, 122, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(57, 123, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(58, 124, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(59, 125, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(60, 126, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(61, 127, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(62, 225, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:49:39', '2025-11-27 03:49:39'),
(63, 226, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:49:51', '2025-11-27 03:49:51'),
(64, 227, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:50:33', '2025-11-27 03:50:33'),
(65, 228, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:50:48', '2025-11-27 03:50:48'),
(66, 229, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:50:48', '2025-11-27 03:50:48'),
(67, 230, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:50:48', '2025-11-27 03:50:48'),
(68, 231, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:50:48', '2025-11-27 03:50:48'),
(69, 232, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:51:13', '2025-11-27 03:51:13'),
(70, 233, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:51:13', '2025-11-27 03:51:13'),
(71, 234, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:55:39', '2025-11-27 03:55:39'),
(72, 235, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:55:56', '2025-11-27 03:55:56'),
(73, 236, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:56:19', '2025-11-27 03:56:19'),
(74, 237, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:56:52', '2025-11-27 03:56:52'),
(75, 238, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 03:57:28', '2025-11-27 03:57:28'),
(76, 239, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:00:25', '2025-11-27 04:00:25'),
(77, 240, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:00:51', '2025-11-27 04:00:51'),
(78, 241, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:01:15', '2025-11-27 04:01:15'),
(79, 242, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:02:45', '2025-11-27 04:02:45'),
(80, 243, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:04:06', '2025-11-27 04:04:06'),
(81, 244, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:05:57', '2025-11-27 04:05:57'),
(82, 245, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:09:59', '2025-11-27 04:09:59'),
(83, 248, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:25:22', '2025-11-27 04:25:22'),
(84, 250, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:25:44', '2025-11-27 04:25:44'),
(85, 251, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May', '2025-11-27 04:26:18', '2025-11-27 04:26:18'),
(86, 252, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:26:38', '2025-11-27 04:26:38'),
(87, 253, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:26:53', '2025-11-27 04:26:53'),
(88, 254, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:27:22', '2025-11-27 04:27:22'),
(89, 255, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:27:39', '2025-11-27 04:27:39'),
(90, 256, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:28:00', '2025-11-27 04:28:00'),
(91, 257, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:28:20', '2025-11-27 04:28:20'),
(92, 258, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:30:56', '2025-11-27 04:30:56'),
(93, 259, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:30:59', '2025-11-27 04:30:59'),
(94, 260, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:31:01', '2025-11-27 04:31:01'),
(95, 261, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:31:03', '2025-11-27 04:31:03'),
(96, 262, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:31:07', '2025-11-27 04:31:07'),
(97, 263, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:31:33', '2025-11-27 04:31:33'),
(98, 268, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Sep,Oct', '2025-11-27 04:49:20', '2025-11-27 04:49:20'),
(99, 269, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Sep,Oct', '2025-11-27 04:49:20', '2025-11-27 04:49:20'),
(100, 270, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Sep,Oct', '2025-11-27 04:49:20', '2025-11-27 04:49:20'),
(101, 271, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:52:47', '2025-11-27 04:52:47'),
(102, 272, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:52:49', '2025-11-27 04:52:49'),
(103, 273, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:52:51', '2025-11-27 04:52:51'),
(104, 274, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:52:53', '2025-11-27 04:52:53'),
(105, 275, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Oct,Nov', '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(106, 276, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Oct,Nov', '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(107, 277, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Oct,Nov', '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(108, 278, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Oct,Nov', '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(109, 279, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Oct,Nov', '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(110, 280, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Oct,Nov', '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(111, 281, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Oct,Nov', '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(112, 282, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 04:52:55', '2025-11-27 04:52:55'),
(113, 283, 'WRS (Water Refilling Station)', 'De Buhos', 'Jun,Aug,Oct,Nov', '2025-11-27 04:54:03', '2025-11-27 04:54:03'),
(114, 284, 'Level 3 (Nawasa)', 'De Buhos', 'May,Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(115, 285, 'Level 3 (Nawasa)', 'De Buhos', 'May,Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(116, 286, 'Level 3 (Nawasa)', 'De Buhos', 'May,Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(117, 287, 'Level 3 (Nawasa)', 'De Buhos', 'May,Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(118, 288, 'Level 3 (Nawasa)', 'De Buhos', 'May,Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(119, 289, 'Level 3 (Nawasa)', 'De Buhos', 'May,Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(120, 290, 'Level 3 (Nawasa)', 'De Buhos', 'May,Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(121, 291, 'Level 3 (Nawasa)', 'De Buhos', 'May,Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(122, 292, 'Level 3 (Nawasa)', 'De Buhos', 'May,Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(123, 293, 'Level 3 (Nawasa)', 'De Buhos', 'May,Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(124, 294, 'Level 3 (Nawasa)', 'De Buhos', 'May,Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(125, 295, 'Level 3 (Nawasa)', 'De Buhos', 'May,Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(126, 296, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:08:01', '2025-11-27 05:08:01'),
(127, 297, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:09:03', '2025-11-27 05:09:03'),
(128, 298, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:09:29', '2025-11-27 05:09:29'),
(129, 299, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:09:38', '2025-11-27 05:09:38'),
(130, 300, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Sep,Oct', '2025-11-27 05:09:55', '2025-11-27 05:09:55'),
(131, 301, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:10:09', '2025-11-27 05:10:09'),
(132, 302, 'Level 3 (Nawasa)', 'De Buhos', 'Oct,Nov', '2025-11-27 05:11:10', '2025-11-27 05:11:10'),
(133, 304, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:11:30', '2025-11-27 05:11:30'),
(134, 305, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:11:30', '2025-11-27 06:31:48'),
(135, 306, 'Level 3 (Nawasa)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:11:37', '2025-11-27 05:11:37'),
(136, 307, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:11:43', '2025-11-27 05:11:43'),
(137, 308, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:11:53', '2025-11-27 05:11:53'),
(138, 309, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:12:06', '2025-11-27 05:12:06'),
(139, 310, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:12:17', '2025-11-27 05:12:17'),
(143, 314, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:13:41', '2025-11-27 05:13:41'),
(144, 315, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:13:48', '2025-11-27 05:13:48'),
(145, 316, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:13:55', '2025-11-27 05:13:55'),
(146, 317, 'Level 3 (Nawasa)', 'De Buhos', 'Jun,Aug,Nov', '2025-11-27 05:13:58', '2025-11-27 05:13:58'),
(147, 318, 'Level 3 (Nawasa)', 'De Buhos', 'Jun,Aug,Nov', '2025-11-27 05:13:58', '2025-11-27 05:13:58'),
(148, 319, 'Level 3 (Nawasa)', 'De Buhos', 'Jun,Aug,Nov', '2025-11-27 05:13:58', '2025-11-27 05:13:58'),
(149, 320, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:14:07', '2025-11-27 05:14:07'),
(150, 321, 'Level 3 (Nawasa)', 'De Buhos', 'Oct,Nov', '2025-11-27 05:14:58', '2025-11-27 05:14:58'),
(151, 322, 'Level 3 (Nawasa)', 'De Buhos', 'Oct,Nov', '2025-11-27 05:14:58', '2025-11-27 05:14:58'),
(152, 323, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:15:03', '2025-11-27 05:15:03'),
(153, 324, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:15:15', '2025-11-27 05:15:15'),
(154, 325, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:15:28', '2025-11-27 05:15:28'),
(155, 326, 'Level 3 (Nawasa)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:15:52', '2025-11-27 05:15:52'),
(156, 327, 'Level 3 (Nawasa)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:15:52', '2025-11-27 05:15:52'),
(157, 328, 'Level 3 (Nawasa)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:15:52', '2025-11-27 05:15:52'),
(158, 329, 'Level 3 (Nawasa)', 'De Buhos', 'Jun,Sep,Nov', '2025-11-27 05:16:14', '2025-11-27 05:16:14'),
(159, 330, 'Level 3 (Nawasa)', 'De Buhos', 'Jun,Sep,Nov', '2025-11-27 05:16:14', '2025-11-27 05:16:14'),
(160, 331, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:16:34', '2025-11-27 05:16:34'),
(161, 332, 'WRS (Water Refilling Station)', 'De Buhos', 'Aug,Sep,Oct,Nov', '2025-11-27 05:16:37', '2025-11-27 05:16:37'),
(162, 333, 'WRS (Water Refilling Station)', 'De Buhos', 'Aug,Sep,Oct,Nov', '2025-11-27 05:16:37', '2025-11-27 05:16:37'),
(163, 334, 'WRS (Water Refilling Station)', 'De Buhos', 'Aug,Sep,Oct,Nov', '2025-11-27 05:16:37', '2025-11-27 05:16:37'),
(164, 335, 'WRS (Water Refilling Station)', 'Sanitary Pit', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:18:05', '2025-11-27 05:18:05'),
(165, 336, 'WRS (Water Refilling Station)', 'Sanitary Pit', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:18:05', '2025-11-27 05:18:05'),
(166, 337, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:18:27', '2025-11-27 05:18:27'),
(167, 338, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:18:27', '2025-11-27 05:18:27'),
(168, 339, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:18:27', '2025-11-27 05:18:27'),
(169, 340, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:18:27', '2025-11-27 05:18:27'),
(170, 342, 'Level 3 (Nawasa)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:18:46', '2025-11-27 05:18:46'),
(171, 343, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:18:53', '2025-11-27 05:18:53'),
(172, 344, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:18:53', '2025-11-27 05:18:53'),
(173, 345, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:18:53', '2025-11-27 05:18:53'),
(174, 346, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:18:53', '2025-11-27 05:18:53'),
(175, 347, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Sep,Oct,Nov', '2025-11-27 05:19:04', '2025-11-27 05:19:04'),
(176, 348, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Sep,Oct,Nov', '2025-11-27 05:19:04', '2025-11-27 05:19:04'),
(177, 349, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Sep,Oct,Nov', '2025-11-27 05:19:04', '2025-11-27 05:19:04'),
(178, 350, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Sep,Oct,Nov', '2025-11-27 05:19:04', '2025-11-27 05:19:04'),
(179, 351, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Sep,Oct,Nov', '2025-11-27 05:19:28', '2025-11-27 05:19:28'),
(180, 352, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Sep,Oct,Nov', '2025-11-27 05:19:28', '2025-11-27 05:19:28'),
(181, 353, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:19:34', '2025-11-27 05:19:34'),
(182, 354, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:19:34', '2025-11-27 05:19:34'),
(183, 355, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:19:34', '2025-11-27 05:19:34'),
(184, 356, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:19:34', '2025-11-27 05:19:34'),
(185, 357, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:19:34', '2025-11-27 05:19:34'),
(186, 358, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:19:34', '2025-11-27 05:19:34'),
(187, 359, 'Level 3 (Nawasa)', 'De Buhos', 'Oct,Nov', '2025-11-27 05:19:48', '2025-11-27 05:19:48'),
(188, 360, 'Level 3 (Nawasa)', 'De Buhos', 'Oct,Nov', '2025-11-27 05:19:48', '2025-11-27 05:19:48'),
(189, 361, 'Level 3 (Nawasa)', 'De Buhos', 'Oct,Nov', '2025-11-27 05:19:48', '2025-11-27 05:19:48'),
(190, 362, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:20:12', '2025-11-27 05:20:12'),
(191, 363, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:20:12', '2025-11-27 05:20:12'),
(192, 364, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:20:12', '2025-11-27 05:20:12'),
(193, 365, 'Level 2 (Gripong Pinagkukunan ng Lima o Higit pang Pamilya)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:20:12', '2025-11-27 05:20:12'),
(194, 366, 'Level 3 (Nawasa)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:21:11', '2025-11-27 05:21:11'),
(195, 367, 'Level 3 (Nawasa)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:21:11', '2025-11-27 05:21:11'),
(196, 368, 'Level 3 (Nawasa)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:21:11', '2025-11-27 05:21:11'),
(197, 369, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:21:39', '2025-11-27 05:21:39'),
(198, 370, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:21:39', '2025-11-27 05:21:39'),
(199, 371, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:21:39', '2025-11-27 05:21:39'),
(200, 372, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:21:39', '2025-11-27 05:21:39'),
(201, 373, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Sep,Oct,Nov', '2025-11-27 05:21:51', '2025-11-27 05:21:51'),
(202, 374, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:21:58', '2025-11-27 05:21:58'),
(203, 375, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:21:58', '2025-11-27 05:21:58'),
(204, 376, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:21:58', '2025-11-27 05:21:58'),
(205, 377, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:21:58', '2025-11-27 05:21:58'),
(206, 378, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:22:16', '2025-11-27 05:22:16'),
(207, 379, 'Level 3 (Nawasa)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:22:19', '2025-11-27 05:22:19'),
(208, 380, 'Level 3 (Nawasa)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:22:19', '2025-11-27 05:22:19'),
(209, 381, 'Level 3 (Nawasa)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:22:19', '2025-11-27 05:22:19'),
(210, 382, 'Level 3 (Nawasa)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:22:48', '2025-11-27 05:22:48'),
(211, 383, 'Level 3 (Nawasa)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:22:48', '2025-11-27 05:22:48'),
(212, 384, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:23:20', '2025-11-27 05:23:20'),
(213, 385, 'WRS (Water Refilling Station)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:23:26', '2025-11-27 05:23:26'),
(214, 386, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:23:39', '2025-11-27 05:23:39'),
(215, 387, 'WRS (Water Refilling Station)', 'De Buhos', 'Mar,Apr,May,Aug,Sep,Oct,Nov', '2025-11-27 05:24:40', '2025-11-27 05:24:40'),
(216, 388, 'WRS (Water Refilling Station)', 'De Buhos', 'Mar,Apr,May,Aug,Sep,Oct,Nov', '2025-11-27 05:24:40', '2025-11-27 05:24:40'),
(217, 389, 'WRS (Water Refilling Station)', 'De Buhos', 'Mar,Apr,May,Aug,Sep,Oct,Nov', '2025-11-27 05:24:40', '2025-11-27 05:24:40'),
(218, 391, 'Level 3 (Nawasa)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:25:01', '2025-11-27 05:25:01'),
(219, 392, 'Level 3 (Nawasa)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:25:01', '2025-11-27 05:25:01'),
(220, 393, 'Level 3 (Nawasa)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:25:01', '2025-11-27 05:25:01'),
(221, 394, 'WRS (Water Refilling Station)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:25:24', '2025-11-27 05:25:24'),
(222, 395, 'WRS (Water Refilling Station)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:25:24', '2025-11-27 05:25:24'),
(223, 396, 'WRS (Water Refilling Station)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:25:24', '2025-11-27 05:25:24'),
(224, 397, 'WRS (Water Refilling Station)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:25:24', '2025-11-27 05:25:24'),
(225, 398, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:25:35', '2025-11-27 05:25:35'),
(226, 399, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Mar,May,Jul,Sep,Nov', '2025-11-27 05:33:13', '2025-11-27 05:33:13'),
(227, 400, 'Level 3 (Nawasa)', 'Sanitary Pit', 'Jun,Aug,Oct,Nov', '2025-11-27 05:33:31', '2025-11-27 05:33:31'),
(228, 401, 'Level 1 (Poso)', 'De Buhos', 'Aug,Sep,Oct,Nov', '2025-11-27 05:34:20', '2025-11-27 05:34:20'),
(229, 402, 'Level 1 (Poso)', 'De Buhos', 'Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:34:49', '2025-11-27 05:34:49'),
(230, 404, 'Level 3 (Nawasa)', 'De Buhos', 'Aug,Sep,Oct,Nov', '2025-11-27 05:35:23', '2025-11-27 05:35:23'),
(231, 405, 'Level 3 (Nawasa)', 'De Buhos', 'Sep,Oct,Nov', '2025-11-27 05:35:42', '2025-11-27 05:35:42'),
(232, 406, 'Level 1 (Poso)', 'De Buhos', 'Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:36:15', '2025-11-27 05:36:15'),
(233, 407, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Mar,May,Jul,Sep,Nov', '2025-11-27 05:37:12', '2025-11-27 05:37:12'),
(234, 408, 'WRS (Water Refilling Station)', 'De Buhos', 'Apr,Jun,Jul,Sep,Nov', '2025-11-27 05:37:37', '2025-11-27 05:37:37'),
(235, 409, 'Level 3 (Nawasa)', 'De Buhos', 'Apr,Jun,Aug,Sep,Nov', '2025-11-27 05:38:09', '2025-11-27 05:38:09'),
(236, 410, 'Level 3 (Nawasa)', 'De Buhos', 'May,Aug,Oct,Nov', '2025-11-27 05:38:48', '2025-11-27 05:38:48'),
(237, 411, 'WRS (Water Refilling Station)', 'De Buhos', 'May,Jun,Jul,Sep,Oct,Nov', '2025-11-27 05:39:18', '2025-11-27 05:39:18'),
(238, 412, 'Level 3 (Nawasa)', 'De Buhos', 'Apr,May,Jun,Sep,Oct,Nov', '2025-11-27 05:39:48', '2025-11-27 05:39:48'),
(239, 413, 'Level 3 (Nawasa)', 'De Buhos', 'May,Jul,Sep,Oct,Nov', '2025-11-27 05:40:12', '2025-11-27 05:40:12'),
(240, 414, 'Level 3 (Nawasa)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:40:45', '2025-11-27 05:40:45'),
(241, 415, 'Level 3 (Nawasa)', 'Sanitary Pit', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 05:41:30', '2025-11-27 14:00:59'),
(242, 416, 'Level 3 (Nawasa)', 'De Buhos', 'Mar,May,Jul,Sep,Nov', '2025-11-27 05:42:00', '2025-11-27 05:42:00'),
(243, 417, 'Level 3 (Nawasa)', 'De Buhos', 'Jun,Jul,Sep,Nov', '2025-11-27 05:42:30', '2025-11-27 05:42:30'),
(244, 418, 'Level 3 (Nawasa)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:47:55', '2025-11-27 05:47:55'),
(245, 419, 'Level 3 (Nawasa)', 'De Buhos', 'Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:48:31', '2025-11-27 05:48:31'),
(246, 420, 'Level 1 (Poso)', 'De Buhos', 'Jun,Aug,Oct,Nov', '2025-11-27 05:49:05', '2025-11-27 05:49:05'),
(247, 421, 'WRS (Water Refilling Station)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:49:30', '2025-11-27 05:49:30'),
(248, 422, 'Level 3 (Nawasa)', 'De Buhos', 'Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:49:54', '2025-11-27 05:49:54'),
(249, 423, 'Level 3 (Nawasa)', 'De Buhos', 'Jun,Jul,Aug,Oct,Nov', '2025-11-27 05:51:05', '2025-11-27 05:51:05'),
(250, 426, 'WRS (Water Refilling Station)', 'De Buhos', 'Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 05:59:50', '2025-11-27 05:59:50'),
(251, 427, 'Level 3 (Nawasa)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 06:00:57', '2025-11-27 06:00:57'),
(252, 428, 'Level 3 (Nawasa)', 'De Buhos', 'Jul,Aug,Sep,Oct,Nov', '2025-11-27 06:01:41', '2025-11-27 06:01:41'),
(253, 429, 'Level 3 (Nawasa)', 'De Buhos', 'Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 06:02:14', '2025-11-27 06:02:14'),
(254, 430, 'WRS (Water Refilling Station)', 'De Buhos', 'Jun,Jul,Aug,Sep,Oct,Nov', '2025-11-27 06:02:47', '2025-11-27 06:02:47'),
(255, 474, 'Level 3 (Nawasa)', 'Sanitary Pit', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:03:50', '2025-11-27 13:03:50'),
(256, 475, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:04:17', '2025-11-27 13:04:17'),
(257, 478, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:32:33', '2025-11-27 13:32:33'),
(258, 479, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:32:33', '2025-11-27 13:32:33'),
(259, 480, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:32:33', '2025-11-27 13:32:33'),
(260, 481, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:32:50', '2025-11-27 13:32:50'),
(261, 482, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:32:50', '2025-11-27 13:32:50'),
(262, 483, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:32:50', '2025-11-27 13:32:50'),
(263, 484, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:33:51', '2025-11-27 13:33:51'),
(264, 485, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:33:51', '2025-11-27 13:33:51'),
(265, 486, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:33:52', '2025-11-27 13:33:52'),
(266, 487, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:33:52', '2025-11-27 13:33:52'),
(267, 488, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:33:52', '2025-11-27 13:33:52'),
(268, 489, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:33:52', '2025-11-27 13:33:52'),
(269, 490, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:33:52', '2025-11-27 13:33:52'),
(270, 491, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:34:08', '2025-11-27 13:34:08'),
(271, 492, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:34:08', '2025-11-27 13:34:08'),
(272, 493, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:34:08', '2025-11-27 13:34:08'),
(273, 494, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:34:08', '2025-11-27 13:34:08'),
(274, 495, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:34:23', '2025-11-27 13:34:23'),
(275, 496, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:34:23', '2025-11-27 13:34:23'),
(276, 497, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:34:23', '2025-11-27 13:34:23'),
(277, 498, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:34:23', '2025-11-27 13:34:23'),
(278, 499, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:34:46', '2025-11-27 13:34:46'),
(279, 500, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:34:46', '2025-11-27 13:34:46'),
(280, 501, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:34:46', '2025-11-27 13:34:46'),
(281, 502, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:34:46', '2025-11-27 13:34:46'),
(282, 503, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:35:06', '2025-11-27 13:35:06'),
(283, 504, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:35:06', '2025-11-27 13:35:06'),
(284, 505, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:35:06', '2025-11-27 13:35:06'),
(285, 506, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:35:06', '2025-11-27 13:35:06'),
(286, 507, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:35:06', '2025-11-27 13:35:06'),
(287, 508, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:35:24', '2025-11-27 13:35:24'),
(288, 509, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:35:24', '2025-11-27 13:35:24'),
(289, 510, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:35:24', '2025-11-27 13:35:24'),
(290, 511, 'Level 1 (Poso)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:35:45', '2025-11-27 13:35:45'),
(291, 512, 'Level 1 (Poso)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:35:45', '2025-11-27 13:35:45'),
(292, 513, 'Level 1 (Poso)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:35:45', '2025-11-27 13:35:45'),
(293, 514, 'Level 1 (Poso)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:35:45', '2025-11-27 13:35:45'),
(294, 516, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:36:08', '2025-11-27 13:36:08'),
(295, 517, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:36:08', '2025-11-27 13:36:08'),
(296, 522, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:42:28', '2025-11-27 13:42:28'),
(297, 523, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:42:28', '2025-11-27 13:42:28'),
(298, 524, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:42:28', '2025-11-27 13:42:28'),
(299, 527, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:50:32', '2025-11-27 13:50:32'),
(300, 529, 'Level 3 (Nawasa)', 'Sanitary Pit', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:53:31', '2025-11-27 13:53:31'),
(301, 530, 'Level 3 (Nawasa)', 'Sanitary Pit', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:53:31', '2025-11-27 13:53:31'),
(302, 531, 'Level 3 (Nawasa)', 'Sanitary Pit', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:53:31', '2025-11-27 13:53:31'),
(303, 532, 'Level 3 (Nawasa)', 'Sanitary Pit', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:53:31', '2025-11-27 13:53:31'),
(304, 533, 'Level 3 (Nawasa)', 'Sanitary Pit', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:53:31', '2025-11-27 13:53:31'),
(305, 535, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:55:30', '2025-11-27 13:55:30'),
(306, 536, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:55:30', '2025-11-27 13:55:30'),
(307, 537, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:55:30', '2025-11-27 13:55:30'),
(308, 538, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 13:55:30', '2025-11-27 13:55:30'),
(309, 541, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 14:04:24', '2025-11-27 14:04:24'),
(310, 542, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 14:04:24', '2025-11-27 14:04:24'),
(311, 543, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 14:04:24', '2025-11-27 14:04:24'),
(312, 544, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 14:04:24', '2025-11-27 14:04:24'),
(313, 545, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 14:04:46', '2025-11-27 14:04:46'),
(314, 546, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 14:04:46', '2025-11-27 14:04:46'),
(315, 547, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 14:04:46', '2025-11-27 14:04:46'),
(316, 548, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 14:07:13', '2025-11-27 14:07:13'),
(317, 577, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 15:35:06', '2025-11-27 15:35:06'),
(318, 578, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 15:35:06', '2025-11-27 15:35:06'),
(319, 579, 'Level 3 (Nawasa)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 15:35:06', '2025-11-27 15:35:06'),
(323, 597, 'WRS (Water Refilling Station)', 'De Buhos', 'Jan,Feb,Mar,Apr,May,Jun', '2025-11-27 15:59:38', '2025-11-27 15:59:38');

-- --------------------------------------------------------

--
-- Table structure for table `immunization`
--

CREATE TABLE `immunization` (
  `immunization_id` int(11) NOT NULL,
  `immunization_type` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `immunization`
--

INSERT INTO `immunization` (`immunization_id`, `immunization_type`) VALUES
(1, 'BCG'),
(2, 'HepB'),
(3, 'DTP1'),
(4, 'DTP2'),
(5, 'DTP3'),
(6, 'OPV1'),
(7, 'OPV2'),
(8, 'OPV3'),
(9, 'IPV1'),
(10, 'IPV2'),
(11, 'PCV1'),
(12, 'PCV2'),
(13, 'PCV3'),
(14, 'MCV1'),
(15, 'MCV2'),
(16, 'TT1'),
(17, 'TT2'),
(18, 'TT3'),
(19, 'TT4'),
(20, 'TT5');

-- --------------------------------------------------------

--
-- Table structure for table `infant_record`
--

CREATE TABLE `infant_record` (
  `infant_record_id` int(11) NOT NULL,
  `child_record_id` int(11) NOT NULL,
  `exclusive_breastfeeding` enum('Y','N','M') DEFAULT NULL,
  `breastfeeding_months` varchar(255) NOT NULL,
  `solid_food_start` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `infant_record`
--

INSERT INTO `infant_record` (`infant_record_id`, `child_record_id`, `exclusive_breastfeeding`, `breastfeeding_months`, `solid_food_start`) VALUES
(1, 24, 'Y', 'First Month,Second Month', '');

-- --------------------------------------------------------

--
-- Table structure for table `medication`
--

CREATE TABLE `medication` (
  `medication_id` int(11) NOT NULL,
  `medication_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medication`
--

INSERT INTO `medication` (`medication_id`, `medication_name`) VALUES
(2, 'Ferrous Sulfate with Folic Acid'),
(3, 'Vitamin A'),
(4, 'Amlodipine 10mg'),
(5, 'Losartan 100mg'),
(6, 'Gliclazide 30mg'),
(7, 'Amlodipine 5mg'),
(8, 'Metoprolol 50mg'),
(9, 'Carvidolol 12.5mg'),
(10, 'Simvastatin 20mg'),
(11, 'Metformin 500mg'),
(12, 'Tetanus Toxoid'),
(13, 'None');

-- --------------------------------------------------------

--
-- Table structure for table `person`
--

CREATE TABLE `person` (
  `person_id` int(11) NOT NULL,
  `household_number` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `address_id` int(11) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `related_person_id` int(11) DEFAULT NULL,
  `relationship_type` varchar(50) DEFAULT NULL,
  `philhealth_number` varchar(100) DEFAULT NULL,
  `health_condition` varchar(255) DEFAULT NULL,
  `deceased` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `person`
--

INSERT INTO `person` (`person_id`, `household_number`, `full_name`, `address_id`, `birthdate`, `age`, `gender`, `contact_number`, `civil_status`, `related_person_id`, `relationship_type`, `philhealth_number`, `health_condition`, `deceased`, `created_at`, `updated_at`) VALUES
(1, '0', 'super, admin ', 1, '1990-03-08', 35, 'M', '', 'Single', NULL, 'Head', '', '', 0, '2025-09-27 01:38:39', '2025-10-26 13:27:49'),
(2, '0', 'bhw, head ', 1, '1975-01-27', 50, 'M', '', 'Single', NULL, 'Head', '', '', 0, '2025-09-27 01:40:53', '2025-10-26 13:27:49'),
(3, '0', 'bhw, staff one', 1, '1988-01-21', 37, 'M', '', 'Single', NULL, 'Head', '', '', 0, '2025-09-27 01:42:30', '2025-10-26 13:27:49'),
(4, '0', 'bhw, staff two', 2, '1988-01-21', 37, 'M', '', 'Single', NULL, 'Head', '', '', 0, '2025-09-27 01:42:30', '2025-10-26 13:27:49'),
(5, '0', 'bhw, staff three', 3, '1988-01-21', 37, 'M', '', 'Single', NULL, 'Head', '', '', 0, '2025-09-27 01:42:30', '2025-10-26 13:27:49'),
(6, '0', 'bhw, staff foura', 4, '1988-01-21', 37, 'M', '', 'Single', NULL, 'Head', '', '', 0, '2025-09-27 01:42:30', '2025-10-26 13:27:49'),
(7, '0', 'bhw, staff fourb', 5, '1988-01-21', 37, 'M', '', 'Single', NULL, 'Head', '', '', 0, '2025-09-27 01:42:30', '2025-10-26 13:27:49'),
(8, '0', 'bhw, staff five', 6, '1988-01-21', 37, 'M', '', 'Single', NULL, 'Head', '', '', 0, '2025-09-27 01:42:30', '2025-10-26 13:27:49'),
(9, '0', 'bhw, staff six', 7, '1988-01-21', 37, 'M', '', 'Single', NULL, 'Head', '', '', 0, '2025-09-27 01:42:30', '2025-10-26 13:27:49'),
(10, '0', 'bhw, staff seven', 8, '1988-01-21', 37, 'M', '', 'Single', NULL, 'Head', '', '', 0, '2025-09-27 01:42:30', '2025-10-26 13:27:49'),
(11, '42001', 'Laza, Evelyn S', 5, '1966-11-02', 58, 'F', '', 'Separated', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-24 06:05:34', '2025-11-27 13:32:33'),
(12, '42001', 'Fernandez, Analyn L', 5, '1999-10-22', 26, 'F', '', 'Married', 11, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-24 06:06:40', '2025-11-27 13:32:33'),
(13, '42001', 'Fernandez, Aizen Geo L', 5, '2022-09-28', 3, 'M', '', 'Single', 11, 'Grandson', NULL, 'C (Child 12-71 months)', NULL, '2025-10-24 06:07:22', '2025-11-27 13:32:33'),
(14, '42002', 'Castaneda, Naty A', 5, '1960-07-31', 65, 'F', '', 'Single', NULL, 'Head', NULL, 'E (Elderly 60+)', 0, '2025-10-24 06:08:52', '2025-11-27 13:32:50'),
(15, '42002', 'Castaneda, Mercely A', 5, '1962-12-15', 62, 'F', '', 'Single', 14, 'Sister', NULL, 'E (Elderly 60+)', NULL, '2025-10-24 06:14:58', '2025-11-27 13:32:50'),
(16, '42002', 'Castaneda, Jose Rene A', 5, '1993-05-23', 32, 'M', '', 'Single', 14, 'Nephew', NULL, 'NRP (No Record Provided)', NULL, '2025-10-24 06:16:14', '2025-11-27 13:32:50'),
(17, '42003', 'Santos, Leonardo Sr. C', 5, '1979-11-04', 45, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-24 08:08:47', '2025-11-27 13:33:51'),
(18, '42003', 'Santos, Jay-ann S', 5, '1986-07-19', 39, 'F', '', 'Married', 17, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-24 08:11:10', '2025-11-27 13:33:51'),
(19, '42003', 'Santos, Lenard S', 5, '2003-03-12', 22, 'M', '', 'Single', 17, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-24 08:11:53', '2025-11-27 13:33:52'),
(20, '42003', 'Santos, Leonardo Jr. S', 5, '2006-05-05', 19, 'M', '', 'Single', 17, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-24 08:12:46', '2025-11-27 13:33:52'),
(21, '42003', 'Santos, Yanica S', 5, '2008-07-05', 17, 'F', '', 'Single', 17, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-24 08:14:05', '2025-11-27 13:33:52'),
(22, '42003', 'Santos, May S', 5, '2007-08-07', 18, 'F', '', 'Single', 17, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-24 08:15:19', '2025-11-27 13:33:52'),
(23, '42003', 'Sampayan, Concepcion P', 5, '1958-12-08', 66, 'F', '', 'Widowed', 17, 'Mother-in-law', NULL, 'E (Elderly 60+)', NULL, '2025-10-24 08:16:52', '2025-11-27 13:33:52'),
(24, '42004', 'Salome, Cerio C', 5, '1988-11-28', 36, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 02:50:41', '2025-11-27 13:34:08'),
(25, '42004', 'Salome, Analyn M', 5, '1988-11-20', 36, 'F', '', 'Married', 24, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-25 02:51:33', '2025-11-27 13:34:08'),
(26, '42004', 'Salome, Jm M', 5, '2010-08-18', 15, 'M', '', 'Single', 24, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 02:52:14', '2025-11-27 13:34:08'),
(27, '42004', 'Salome, Andrea M', 5, '2018-11-14', 6, 'F', '', 'Single', 24, 'Daughter', NULL, 'C (Child 12-71 months)', NULL, '2025-10-25 02:52:52', '2025-11-27 13:34:08'),
(28, '42005', 'Salome, Lorenzo O', 5, '1949-05-20', 76, 'M', '', 'Married', NULL, 'Head', NULL, 'E (Elderly 60+)', NULL, '2025-10-25 02:54:09', '2025-11-27 13:34:23'),
(29, '42005', 'Salome, Flora C', 5, '1957-09-17', 68, 'F', '', 'Married', 28, 'Spouse', NULL, 'E (Elderly 60+)', NULL, '2025-10-25 02:54:56', '2025-11-27 13:34:23'),
(30, '42005', 'Salome, Eugenio C', 5, '1980-12-01', 44, 'M', '', 'Single', 28, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 02:55:46', '2025-11-27 13:34:23'),
(31, '42005', 'Salome, Jovie C', 5, '1986-10-07', 39, 'F', '', 'Single', 28, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-25 02:56:29', '2025-11-27 13:34:23'),
(32, '42006', 'Salome, Dominador C', 5, '1990-01-12', 35, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 02:57:40', '2025-11-27 13:34:46'),
(33, '42006', 'Salome, Rose Marie I', 5, '1981-12-11', 43, 'F', '', 'Married', 32, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-25 02:58:36', '2025-11-27 13:34:46'),
(34, '42006', 'Salome, Donna Rose  I', 5, '2019-10-09', 6, 'F', '', 'Single', 32, 'Daughter', NULL, 'C (Child 12-71 months)', NULL, '2025-10-25 02:59:31', '2025-11-27 13:34:46'),
(35, '42006', 'Salome, Amiel Nash I', 5, '2023-10-10', 2, 'M', '', 'Single', 32, 'Son', NULL, 'C (Child 12-71 months)', NULL, '2025-10-25 03:00:05', '2025-11-27 13:34:46'),
(36, '42007', 'Feliciano, Nelson Sr. L', 5, '1968-11-26', 56, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 13:05:23', '2025-11-27 13:35:06'),
(37, '42007', 'Feliciano, Susan G', 5, '1969-03-18', 56, 'F', '', 'Married', 36, 'Spouse', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 13:07:54', '2025-11-27 13:35:06'),
(38, '42007', 'Feliciano, Chabelita G', 5, '1999-11-07', 25, 'F', '', 'Single', 36, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-25 13:08:36', '2025-11-27 13:35:06'),
(39, '42007', 'Feliciano, Mark Anthony G', 5, '1988-08-11', 37, 'M', '', 'Single', 36, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 13:09:12', '2025-11-27 13:35:06'),
(40, '42007', 'Feliciano, Nelson Jr. G', 5, '2003-06-19', 22, 'M', '', 'Single', 36, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 13:09:42', '2025-11-27 13:35:06'),
(41, '42008', 'Pagarigan, Ma. Crisanta G', 5, '1997-04-17', 28, 'F', '', 'Married', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-25 13:13:11', '2025-11-27 13:35:24'),
(42, '42008', 'Pagarigan, Trustom Josh ', 5, '1995-08-16', 30, 'M', '', 'Married', 41, 'Spouse', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 13:13:56', '2025-11-27 13:35:24'),
(43, '42008', 'Pagarigan, Mark Topson ', 5, '2022-01-27', 3, 'M', '', 'Single', 41, 'Son', NULL, 'C (Child 12-71 months)', NULL, '2025-10-25 13:14:34', '2025-11-27 13:35:24'),
(44, '42009', 'Feliciano, Luigi Janel G', 5, '1987-07-05', 38, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 13:17:44', '2025-11-27 13:35:45'),
(45, '42009', 'Feliciano, Jaisa Jane R', 5, '1996-09-08', 29, 'F', '', 'Married', 44, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-25 13:18:59', '2025-11-27 13:35:45'),
(46, '42009', 'Feliciano, L Jhay R', 5, '2015-06-04', 10, 'M', '', 'Single', 44, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 13:19:42', '2025-11-27 13:35:45'),
(47, '42009', 'Feliciano, Leane Jade R', 5, '2018-05-16', 7, 'F', '', 'Single', 44, 'Daughter', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 13:20:41', '2025-11-27 13:35:45'),
(48, '42010', 'Feliciano, Neil I', 5, '2000-10-13', 25, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 13:26:49', '2025-11-27 13:36:08'),
(49, '42010', 'Domingo, Poli Mae N', 5, '2002-09-01', 23, 'F', '', 'Married', 48, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-25 13:28:16', '2025-11-27 13:36:08'),
(50, '10001', 'Legaspi, Emmanuel A', 1, '1981-04-17', 44, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-25 13:31:29', '2025-11-23 08:17:39'),
(51, '10001', 'Legaspi, Jesamie B', 1, '1993-10-02', 32, 'F', '', 'Married', 50, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-25 13:32:29', '2025-11-23 08:17:39'),
(52, '10001', 'Legaspi, Zack Raigen B', 1, '2020-08-07', 5, 'M', '', 'Single', 50, 'Son', NULL, 'C (Child 12-71 months)', NULL, '2025-10-26 02:49:41', '2025-11-23 08:17:39'),
(53, '10001', 'Legaspi, Kate Hailey B', 1, '2021-11-04', 3, 'F', '', 'Single', 50, 'Daughter', NULL, 'C (Child 12-71 months)', NULL, '2025-10-26 02:50:35', '2025-11-23 08:17:39'),
(54, '10002', 'Esteban, Rolly O', 1, '1977-10-25', 48, 'M', '', 'Single', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 02:52:35', '2025-11-23 08:18:52'),
(55, '10002', 'Bautista, Mark  T', 1, '1995-04-14', 30, 'M', '', 'Married', 54, 'Nephew', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 02:54:33', '2025-11-23 08:18:52'),
(56, '10002', 'Bautista, Catherine M', 1, '2001-03-09', 24, 'F', '', 'Married', 54, 'Niece', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 02:55:14', '2025-11-23 08:18:52'),
(57, '10002', 'Bautista, Elisha Jewel M', 1, '2023-03-13', 2, 'F', '', 'Single', 54, 'Granddaughter', NULL, 'C (Child 12-71 months)', NULL, '2025-10-26 03:26:18', '2025-11-23 08:18:52'),
(58, '10003', 'Grospe, Benjie J', 1, '1995-05-28', 30, 'M', '', 'Single', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 03:27:31', '2025-11-23 08:19:36'),
(59, '10003', 'Grospe, Vergel J', 1, '1993-05-27', 32, 'M', '', 'Single', 58, 'Brother', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 03:28:09', '2025-11-23 08:19:36'),
(60, '10003', 'Grospe, Vivian J', 1, '1998-02-14', 27, 'F', '', 'Live-in', 58, 'Sister', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 03:47:21', '2025-11-23 08:19:36'),
(61, '10003', 'Grospe, Mark Anthony J', 1, '1999-03-21', 26, 'M', '', 'Single', 58, 'Brother', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 03:47:56', '2025-11-23 08:19:36'),
(62, '10003', 'Grospe, Dexter J', 1, '2000-12-18', 24, 'M', '', 'Single', 58, 'Brother', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 03:48:27', '2025-11-23 08:19:36'),
(63, '10003', 'Grospe, Pauline Joy J', 1, '2004-01-03', 21, 'F', '', 'Single', 58, 'Sister', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 03:49:02', '2025-11-23 08:19:36'),
(64, '10003', 'Grospe, Mary Jane J', 1, '2008-01-18', 17, 'F', '', 'Single', 58, 'Sister', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 03:49:55', '2025-11-23 08:19:36'),
(65, '10003', 'Grospe, Paulina R', 1, '1943-12-02', 81, 'F', '', 'Widowed', 58, 'Grandmother', NULL, 'E (Elderly 60+),HPN (High Blood Pressure)', NULL, '2025-10-26 03:50:45', '2025-11-23 08:21:09'),
(66, '10003', 'Vista, Christine P', 1, '1996-07-29', 29, 'F', '', 'Live-in', 58, 'Live-in', '05-472950318-2', 'NP (Non-Pregnant)', NULL, '2025-10-26 03:51:34', '2025-11-27 17:18:54'),
(67, '10004', 'Perez, Manuel Jr. R', 1, '1952-04-26', 73, 'M', '', 'Widowed', NULL, 'Head', NULL, 'E (Elderly 60+),HPN (High Blood Pressure)', NULL, '2025-10-26 03:53:06', '2025-11-23 08:31:08'),
(68, '10004', 'Perez, Nida S', 1, '1992-08-24', 33, 'F', '', 'Single', 67, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 03:53:53', '2025-11-23 08:31:08'),
(69, '10004', 'Perez, Novelyn S', 1, '1988-08-05', 37, 'F', '', 'Single', 67, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 03:54:27', '2025-11-23 08:31:08'),
(70, '10004', 'Perez, Ashley Yvon ', 1, '2012-01-17', 13, 'F', '', 'Single', 67, 'Granddaughter', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 03:55:21', '2025-11-23 08:31:08'),
(71, '10004', 'Alegado, Christopher B', 1, '1991-07-12', 34, 'M', '', 'Married', 67, 'Son-in-law', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 03:56:12', '2025-11-23 08:31:08'),
(72, '10004', 'Alegado, Marissa P', 1, '1994-03-09', 31, 'F', '', 'Married', 67, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 03:57:02', '2025-11-23 08:31:08'),
(73, '10004', 'Alegado, Chrissa Mhea P', 1, '2014-11-25', 10, 'F', '', 'Single', 67, 'Granddaughter', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 03:57:32', '2025-11-23 08:31:08'),
(74, '10004', 'Alegado, Scarlet Brille P', 1, '2021-11-11', 3, 'F', '', 'Single', 67, 'Granddaughter', NULL, 'C (Child 12-71 months)', NULL, '2025-10-26 03:58:02', '2025-11-23 08:31:08'),
(75, '10005', 'Felipe, Christian P', 1, '1987-12-13', 37, 'M', '', 'Live-in', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 04:18:13', '2025-11-23 08:32:01'),
(76, '10005', 'Bautista, Ma. Estela ', 1, '1995-11-02', 29, 'F', '', 'Live-in', 75, 'Live-in', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 04:19:06', '2025-11-23 08:32:01'),
(77, '10005', 'Estanoctoc, Sophia Nicole B', 1, '2016-01-28', 9, 'F', '', 'Single', 75, 'Step-daughter', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 04:20:03', '2025-11-23 08:32:01'),
(78, '10005', 'Felipe, Camille Joy B', 1, '2020-11-03', 4, 'F', '', 'Single', 75, 'Daughter', NULL, 'C (Child 12-71 months)', NULL, '2025-10-26 04:20:33', '2025-11-23 08:32:01'),
(79, '10006', 'Dela Cruz, Armando B', 1, '1970-12-16', 54, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 04:22:42', '2025-11-23 08:32:42'),
(80, '10006', 'Dela Cruz, Bernadette S', 1, '1969-08-20', 56, 'F', '', 'Married', 79, 'Spouse', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 04:23:32', '2025-11-23 08:32:42'),
(81, '10006', 'Dela Cruz, Arvin S', 1, '1996-09-07', 29, 'M', '', 'Live-in', 79, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 04:24:29', '2025-11-23 08:32:42'),
(82, '10006', 'Dela Cruz, Ariel S', 1, '1998-05-14', 27, 'M', '', 'Single', 79, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 04:25:08', '2025-11-23 08:32:42'),
(83, '10006', 'Dela Cruz, Albert S', 1, '2000-08-10', 25, 'M', '', 'Single', 79, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 04:25:42', '2025-11-23 08:32:42'),
(84, '10006', 'Dela Cruz, Andrew S', 1, '2005-10-18', 20, 'M', '', 'Single', 79, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 04:26:29', '2025-11-23 08:32:42'),
(85, '10006', 'Simon, Cresereia R', 1, '1940-07-14', 85, 'F', '', 'Widowed', 79, 'Mother-in-law', NULL, 'E (Elderly 60+)', NULL, '2025-10-26 04:27:13', '2025-11-23 08:32:42'),
(86, '10006', 'Simon, Asunsion R', 1, '1970-10-22', 55, 'F', '', 'Single', 79, 'Sister-in-law', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 04:27:50', '2025-11-23 08:32:42'),
(87, '10007', 'Pabilona, Carlo P', 1, '1997-06-03', 28, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 07:47:20', '2025-11-23 08:33:13'),
(88, '10007', 'Pabilona, Gemma E', 1, '1997-06-24', 28, 'F', '', 'Married', 87, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 07:48:05', '2025-11-23 08:33:13'),
(89, '10007', 'Pabilona, Preciona E', 1, '2017-07-13', 8, 'F', '', 'Single', 87, 'Daughter', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 07:48:42', '2025-11-23 08:33:13'),
(90, '10007', 'Pabilona, Arah Maureen E', 1, '2022-03-27', 3, 'F', '', 'Single', 87, 'Daughter', NULL, 'C (Child 12-71 months)', NULL, '2025-10-26 07:49:08', '2025-11-23 08:33:13'),
(91, '10008', 'Pabilona, Mylene R', 1, '1985-04-26', 40, 'F', '', 'Live-in', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 07:50:17', '2025-11-23 08:33:51'),
(92, '10008', 'Marecelino, Bonifacio A', 1, '1970-05-25', 55, 'M', '', 'Live-in', 91, 'Live-in', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 07:51:07', '2025-11-23 08:33:51'),
(93, '10008', 'Quilapio, Arlene P', 1, '2000-07-20', 25, 'F', '', 'Single', 91, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 07:51:51', '2025-11-23 08:33:51'),
(94, '10008', 'Quilapio, Remelyn P', 1, '2007-11-07', 17, 'F', '', 'Live-in', 91, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 07:52:28', '2025-11-23 08:33:51'),
(95, '10008', 'Quilapio, Atasha Coleen P', 1, '2017-08-01', 8, 'F', '', 'Single', 91, 'Daughter', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 07:53:16', '2025-11-23 08:33:51'),
(96, '10008', 'Quilapio, Asher Kaiden ', 1, '2022-09-30', 3, 'M', '', 'Single', 91, 'Grandson', NULL, 'C (Child 12-71 months)', NULL, '2025-10-26 07:53:49', '2025-11-23 08:33:51'),
(97, '10009', 'Atienza, Pio T', 1, '1973-11-02', 51, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 07:55:22', '2025-11-23 08:40:34'),
(98, '10009', 'Atienza, Marites R', 1, '1976-03-17', 49, 'F', '', 'Married', 97, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 07:56:04', '2025-11-23 08:40:34'),
(99, '10009', 'Atienza, James R', 1, '1997-07-01', 28, 'M', '', 'Single', 97, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 07:56:42', '2025-11-23 08:40:34'),
(100, '10009', 'Atienza, John Audrey R', 1, '2001-02-15', 24, 'M', '', 'Single', 97, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 07:57:25', '2025-11-23 08:40:34'),
(101, '10009', 'Atienza, Jaymie R', 1, '2004-01-30', 21, 'M', '', 'Single', 97, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 07:58:06', '2025-11-23 08:40:34'),
(102, '10009', 'Atienza, Alexandria R', 1, '2009-08-03', 16, 'F', '', 'Single', 97, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 07:58:32', '2025-11-23 08:40:34'),
(103, '10010', 'Reyes, Hilda A', 1, '1972-08-25', 53, 'F', '', 'Widowed', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 07:59:43', '2025-11-23 08:41:02'),
(104, '10010', 'Dela Cruz, Althea R', 1, '2005-12-22', 19, 'F', '', 'Single', 103, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 08:00:25', '2025-11-23 08:41:02'),
(105, '10010', 'Dela Cruz, Aldrin R', 1, '2008-11-18', 16, 'M', '', 'Single', 103, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 08:01:11', '2025-11-23 08:41:02'),
(106, '10010', 'Jimenez, Maika J', 1, '1997-10-05', 28, 'F', '', 'Separated', 103, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 08:01:44', '2025-11-23 08:41:02'),
(107, '10010', 'Jimenez, Jevy Phonex J', 1, '2019-01-02', 6, 'M', '', 'Single', 103, 'Grandson', NULL, 'C (Child 12-71 months)', NULL, '2025-10-26 08:02:45', '2025-11-23 08:41:02'),
(108, '10010', 'Jimenez, Amarah Elisse J', 1, '2023-03-25', 2, 'F', '', 'Single', 103, 'Granddaughter', NULL, 'C (Child 12-71 months)', NULL, '2025-10-26 08:03:26', '2025-11-23 08:41:02'),
(109, '10010', 'Jimenez, Anieka Reign J', 1, '2021-07-22', 4, 'F', '', 'Single', 103, 'Granddaughter', NULL, 'C (Child 12-71 months)', NULL, '2025-10-26 08:03:59', '2025-11-23 08:41:02'),
(110, '30001', 'Andrade, John Paul A', 3, '1996-09-16', 29, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 09:39:20', '2025-11-27 04:49:20'),
(111, '30001', 'Andrade, Maylene M', 3, '1992-10-17', 33, 'F', '', 'Married', 110, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 09:41:01', '2025-11-27 04:49:20'),
(112, '30001', 'Andrade, Maliah Jade M', 3, '2024-12-18', 0, 'F', '', 'Single', 110, 'Daughter', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 09:41:28', '2025-11-27 04:49:20'),
(113, '30002', 'Asuncion, Perlita R', 3, '1958-11-17', 66, 'F', '', 'Widowed', NULL, 'Head', NULL, 'E (Elderly 60+)', NULL, '2025-10-26 09:44:02', '2025-11-27 04:52:54'),
(114, '30002', 'Asuncion, Abemelec R', 3, '1996-09-10', 29, 'M', '', 'Single', 113, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 09:53:28', '2025-11-27 04:52:54'),
(115, '30002', 'Asuncion, Jhesalom R', 3, '1997-10-18', 28, 'M', '', 'Single', 113, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 09:54:17', '2025-11-27 04:52:54'),
(116, '30002', 'Asuncion, Ejra Jean R', 3, '2000-10-30', 24, 'F', '', 'Single', 113, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 09:54:46', '2025-11-27 04:52:54'),
(117, '30002', 'Asuncion, Qwyncy C', 3, '2008-01-20', 17, 'F', '', 'Single', 113, 'Granddaughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 09:55:27', '2025-11-27 04:52:54'),
(118, '30002', 'Asuncion, Aesha Kin C', 3, '2014-05-04', 11, 'F', '', 'Single', 113, 'Granddaughter', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 09:55:59', '2025-11-27 04:52:54'),
(119, '30002', 'Asuncion, Keisha Mae R', 3, '2006-10-20', 19, 'F', '', 'Single', 113, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-26 09:56:27', '2025-11-27 04:52:54'),
(120, '30003', 'Asuncion, Clarence R', 3, '1978-06-12', 47, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-26 09:57:57', '2025-11-27 04:54:03'),
(121, '50001', 'Apostol, Ester C', 6, '1986-10-04', 39, 'F', '', 'Married', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-31 13:25:40', '2025-11-27 05:18:46'),
(122, '50002', 'Asoy, Ranilo D', 6, '1963-01-18', 62, 'M', '', 'Married', NULL, 'Head', NULL, 'E (Elderly 60+)', NULL, '2025-10-31 13:37:35', '2025-11-27 05:19:04'),
(123, '50002', 'Asoy, Fe T', 6, '1965-08-01', 60, 'F', '', 'Married', 122, 'Spouse', NULL, 'E (Elderly 60+)', NULL, '2025-10-31 13:40:03', '2025-11-27 05:19:04'),
(124, '50002', 'Asoy, Mark Jason ', 6, '1993-08-13', 32, 'M', '', 'Single', 122, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-10-31 13:41:20', '2025-11-27 05:19:04'),
(125, '50002', 'Asoy, Trisha Mae ', 6, '1998-03-17', 27, 'F', '', 'Single', 122, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-10-31 13:42:42', '2025-11-27 05:19:04'),
(126, '50003', 'Agustin , Paul Michael  B', 6, '1981-03-16', 44, 'M', '', 'Single', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-10-31 13:45:08', '2025-11-27 05:19:28'),
(127, '50003', 'Agustin, Sherlita B', 6, '1960-07-07', 65, 'F', '', 'Widowed', 126, 'Mother', NULL, 'E (Elderly 60+)', NULL, '2025-10-31 13:47:44', '2025-11-27 05:19:28'),
(128, '50004', 'Agas, Ricky R', 6, '1973-03-27', 52, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-02 06:49:06', '2025-11-27 05:19:48'),
(129, '50004', 'Agas, Cynthia  O', 6, '1975-12-18', 49, 'F', '', 'Married', 128, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-02 06:50:21', '2025-11-27 05:19:48'),
(130, '50004', 'Agas, Rhiya Cyrel ', 6, '1998-09-05', 27, 'F', '', 'Married', 128, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-02 06:51:37', '2025-11-27 05:19:48'),
(131, '50005', 'Agas, Roderick  E', 6, '1977-10-27', 48, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-02 07:09:36', '2025-11-27 05:20:12'),
(132, '50005', 'Agas , Ma. Babyline A', 6, '1976-05-25', 49, 'F', '', 'Married', 131, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-02 07:10:56', '2025-11-27 05:20:12'),
(133, '50005', 'Ags, Xeth ', 6, '2002-06-07', 23, 'F', '', 'Married', 131, 'Daughter', NULL, 'NP (Non-Pregnant)', 0, '2025-11-02 07:11:45', '2025-11-27 05:20:12'),
(134, '50005', 'Agas, Xamh ', 6, '2010-08-25', 15, 'F', '', 'Single', 131, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-02 07:12:48', '2025-11-27 05:20:12'),
(135, '50006', 'Agustin, Wyler L', 6, '1990-01-01', 35, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-02 07:15:18', '2025-11-27 05:21:11'),
(136, '50006', 'Agustin, Lourewce D', 6, '1991-04-29', 34, 'F', '', 'Married', 135, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-02 07:16:59', '2025-11-27 05:21:11'),
(137, '50006', 'Agustin, Lhar Wayne ', 6, '2014-04-15', 11, 'M', '', 'Single', 135, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-02 07:19:20', '2025-11-27 05:21:11'),
(138, '50007', 'Agustin, Ernesto  C', 6, '1953-07-13', 72, 'M', '', 'Widowed', NULL, 'Head', NULL, 'E (Elderly 60+)', NULL, '2025-11-02 07:24:48', '2025-11-27 05:21:51'),
(139, '50008', 'Abad, Crisanto Q', 6, '1966-04-22', 59, 'M', '', 'Live In', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-02 07:33:18', '2025-11-27 05:22:19'),
(140, '50008', 'Sadio, Erlinda A', 6, '1966-03-25', 59, 'F', '', 'Married', 139, 'Spouse', NULL, 'NRP (No Record Provided)', NULL, '2025-11-02 07:35:06', '2025-11-27 05:22:19'),
(141, '50008', 'Abad, Chino Paulo S', 6, '2004-11-25', 20, 'M', '', 'Single', 139, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-02 07:36:02', '2025-11-27 05:22:19'),
(142, '50009', 'Abella, Conrado E', 6, '1949-11-26', 75, 'M', '', 'Married', NULL, 'Head', NULL, 'E (Elderly 60+)', NULL, '2025-11-02 07:38:29', '2025-11-27 05:22:48'),
(143, '50009', 'Abella , Aurora A', 6, '1949-10-27', 76, 'F', '', 'Married', 142, 'Spouse', NULL, 'E (Elderly 60+)', NULL, '2025-11-02 07:46:02', '2025-11-27 05:22:48'),
(150, '50010', 'Artetche, Emmanuel Sr. M', 6, '1971-12-01', 53, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-03 06:24:48', '2025-11-27 05:23:26'),
(151, '50011', 'Antonio , Luz A', 6, '1975-10-12', 50, 'F', '', 'Widowed', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-03 06:37:20', '2025-11-27 05:24:40'),
(153, '50011', 'Antonio , Nikka Pauline ', 6, '1999-05-25', 26, 'F', '', 'Single', 151, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-03 07:10:03', '2025-11-27 05:24:40'),
(154, '50011', 'Antonio, Gianne Alyanna ', 6, '2020-11-03', 5, 'F', '', 'Single', 151, 'Daughter', NULL, 'C (Child 12-71 months)', NULL, '2025-11-03 07:11:09', '2025-11-27 05:24:40'),
(155, '50012', 'Bravo, Suanito D', 6, '1977-05-06', 48, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-03 07:14:35', '2025-11-27 05:25:01'),
(156, '50012', 'Bravo, Mary Jane E', 6, '1976-04-23', 49, 'F', '', 'Married', 155, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-03 07:15:55', '2025-11-27 05:25:01'),
(157, '50012', 'Bravo, Jasmine ', 6, '2008-04-29', 17, 'F', '', 'Single', 155, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-03 07:17:45', '2025-11-27 05:25:01'),
(158, '50013', 'Bravo, Ramon Sr D', 6, '1975-01-11', 50, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-03 07:24:23', '2025-11-27 05:25:24'),
(159, '50013', 'Bravo, Cresencia P', 6, '1968-12-26', 56, 'F', '', 'Married', 158, 'Spouse', NULL, 'NRP (No Record Provided)', NULL, '2025-11-03 07:25:38', '2025-11-27 05:25:24'),
(160, '50013', 'Bravo, John Ramon ', 6, '2002-05-23', 23, 'M', '', 'Single', 158, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-03 07:26:51', '2025-11-27 05:25:24'),
(161, '50013', 'Bravo, Sean Christian ', 6, '2004-05-26', 21, 'M', '', 'Single', 158, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-03 07:28:14', '2025-11-27 05:25:24'),
(162, '30004', 'Carpio, Joselito E', 3, '1976-11-01', 49, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-03 13:38:22', '2025-11-27 05:06:32'),
(163, '30004', 'Carpio, Rose marie E', 3, '1976-08-06', 49, 'F', '', 'Married', 162, 'Spouse', NULL, 'NP (Non-Pregnant)', 0, '2025-11-03 13:39:27', '2025-11-27 05:06:32'),
(164, '30004', 'Carpio, Joan E', 3, '2000-06-09', 25, 'F', '', 'Single', 162, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-03 13:40:56', '2025-11-27 05:06:32'),
(165, '30004', 'Carpio, Jessica E', 3, '2002-03-29', 23, 'F', '', 'Single', 162, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-03 13:41:57', '2025-11-27 05:06:32'),
(166, '30004', 'Carpio, Joanalyn E', 3, '2004-02-02', 21, 'F', '', 'Single', 162, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-03 13:42:38', '2025-11-27 05:06:32'),
(167, '30004', 'Carpio, Mark Anthony E', 3, '2007-06-09', 18, 'M', '', 'Single', 162, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-03 13:44:24', '2025-11-27 05:06:32'),
(168, '30004', 'Carpio, Jayson E', 3, '2009-12-23', 15, 'M', '', 'Single', 162, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-03 13:45:02', '2025-11-27 05:06:32'),
(169, '30004', 'Carpio, Joshua E', 3, '2012-07-16', 13, 'M', '', 'Single', 162, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-03 13:46:33', '2025-11-27 05:06:32'),
(170, '30004', 'Carpio, Justin E', 3, '2014-10-06', 11, 'M', '', 'Single', 162, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-03 13:47:09', '2025-11-27 05:06:32'),
(171, '30004', 'Carpio, John Dave E', 3, '2017-09-14', 8, 'M', '', 'Single', 162, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-03 13:48:02', '2025-11-27 05:06:32'),
(172, '30004', 'Carpio, Jasmin E', 3, '2018-11-08', 6, 'F', '', 'Single', 162, 'Daughter', NULL, 'C (Child 12-71 months)', NULL, '2025-11-03 13:48:35', '2025-11-27 05:06:32'),
(173, '30004', 'Carpio, Javan E', 3, '2021-07-05', 4, 'M', '', 'Single', 162, 'Son', NULL, 'C (Child 12-71 months)', NULL, '2025-11-03 13:49:04', '2025-11-27 05:06:32'),
(174, '70001', 'Briones, Rebecca C.', 8, '1970-10-12', 55, 'F', '', 'Widowed', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-04 03:15:35', '2025-11-27 05:18:05'),
(175, '70001', 'Briones, Robina C.', 8, '1990-03-14', 35, 'F', '', 'Single', 174, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-04 03:17:29', '2025-11-27 05:18:05'),
(176, '41001', 'Ragos, Rudy R', 4, '1978-11-28', 46, 'M', '', 'Separated', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 05:53:29', '2025-11-27 05:11:30'),
(177, '41001', 'Ragos, Jhon Aldwin  ', 4, '2003-04-29', 22, 'M', '', 'Single', 176, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 06:01:13', '2025-11-27 05:11:30'),
(178, '70002', 'Rafael, Jose R', 8, '1988-03-19', 37, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 11:49:44', '2025-11-27 05:18:27'),
(179, '70002', 'Rafael, Lina L', 8, '1978-04-22', 47, 'F', '', 'Married', 178, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-11 11:50:59', '2025-11-27 05:18:27'),
(180, '70002', 'Rafel, Joyce Lingette L', 8, '1996-04-01', 29, 'F', '', 'Single', 178, 'Daughter', NULL, 'NP (Non-Pregnant)', 0, '2025-11-11 11:52:03', '2025-11-27 05:18:27'),
(181, '70002', 'Rafael, John Lloyd L', 8, '2001-09-17', 24, 'M', '', 'Single', 178, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 11:53:09', '2025-11-27 05:18:27'),
(182, '70003', 'Bensan, Jonel F', 8, '1987-03-08', 38, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 11:55:07', '2025-11-27 05:18:53'),
(183, '70003', 'Bensan , Rose Marie A', 8, '1990-09-03', 35, 'F', '', 'Married', 182, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-11 11:56:14', '2025-11-27 05:18:53'),
(184, '70003', 'Bensan, John Paul A', 8, '2002-06-19', 23, 'M', '', 'Single', 182, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 11:57:05', '2025-11-27 05:18:53'),
(185, '70003', 'Bensan, John Michael A', 8, '2003-07-12', 22, 'M', '', 'Single', 182, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 11:59:24', '2025-11-27 05:18:53'),
(186, '70004', 'Apostol, Marcelo R', 8, '1978-11-13', 46, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 12:06:03', '2025-11-27 05:19:34'),
(187, '70004', 'Apostol, Josephina B', 8, '1984-12-13', 40, 'F', '', 'Married', 186, 'Spouse', NULL, 'NP (Non-Pregnant)', 0, '2025-11-11 12:07:12', '2025-11-27 05:19:34'),
(188, '70004', 'Laurel, Lyra A', 8, '1990-12-27', 34, 'F', '', 'Married', 186, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-11 12:09:11', '2025-11-27 05:19:34'),
(189, '70004', 'Laurel, Prince Kyle A', 8, '2010-09-04', 15, 'M', '', 'Single', 186, 'Grandson', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 12:10:26', '2025-11-27 05:19:34'),
(190, '70004', 'Laurel, Jester  A', 8, '2014-03-17', 11, 'M', '', 'Single', 186, 'Grandson', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 12:11:02', '2025-11-27 05:19:34'),
(191, '70004', 'Laurel, Kendra A', 8, '2021-07-15', 4, 'F', '', 'Single', 186, 'Granddaughter', NULL, 'C (Child 12-71 months)', NULL, '2025-11-11 12:11:33', '2025-11-27 05:19:34'),
(192, '70005', 'Luzano, Ronaldo S', 8, '1979-11-13', 45, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 12:13:38', '2025-11-27 05:21:39'),
(193, '70005', 'Luzano, Rose Marie D', 8, '1988-03-31', 37, 'M', '', 'Married', 192, 'Spouse', NULL, 'NRP (No Record Provided)', 0, '2025-11-11 12:18:35', '2025-11-27 05:21:39'),
(194, '70005', 'Luzano, Christian  D', 8, '1999-03-31', 26, 'M', '', 'Single', 192, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 12:19:43', '2025-11-27 05:21:39'),
(195, '70005', 'Luzano, John Christoper D', 8, '2005-12-30', 19, 'M', '', 'Single', 192, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 12:20:25', '2025-11-27 05:21:39'),
(196, '70006', 'Salcedo, Jovita Q', 8, '1998-06-18', 27, 'F', '', 'Married', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-11 12:21:48', '2025-11-27 05:21:58'),
(197, '70006', 'Salcedo, Jica Q', 8, '2002-03-31', 23, 'F', '', 'Single', 196, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-11 12:23:11', '2025-11-27 05:21:58'),
(198, '70006', 'Salcedo, Mark Jacob Q', 8, '2004-04-23', 21, 'M', '', 'Single', 196, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 12:23:52', '2025-11-27 05:21:58'),
(199, '70006', 'Salcedo, Lito Q', 8, '2006-02-07', 19, 'M', '', 'Single', 196, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-11 12:24:22', '2025-11-27 05:21:58'),
(200, '20001', 'Agbayani  , Janel O', 2, '1990-01-01', 35, 'F', '', 'Married', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-22 14:42:52', '2025-11-27 03:49:39'),
(201, '20002', 'Agbayani, Remedios O', 2, '1989-01-14', 36, 'F', '', 'Married', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-22 14:44:35', '2025-11-27 03:49:51'),
(202, '20003', 'Abalos, Irish I', 2, '1995-09-20', 30, 'M', '', 'Single', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-22 14:45:50', '2025-11-27 03:50:33'),
(203, '20004', 'Balanay, Richard R', 2, '1979-06-04', 46, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-22 14:47:08', '2025-11-27 03:50:48'),
(204, '20005', 'Bagay, Gilbert D', 2, '1975-10-04', 50, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-22 14:48:15', '2025-11-27 03:51:13'),
(205, '20006', 'Baniqued, Marcelo S', 2, '1978-04-23', 47, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-22 14:50:43', '2025-11-27 03:55:39'),
(206, '10008', 'Quilapio, Mc Jaiden ', 1, '2024-10-13', 1, 'M', '', 'Single', 91, 'Son', NULL, 'C (Child 12-71 months)', NULL, '2025-11-23 08:39:08', '2025-11-23 08:39:28'),
(207, '20007', 'Mas, Annjie M', 2, '1988-08-27', 37, 'F', '', 'Married', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-23 11:21:28', '2025-11-27 03:55:56'),
(208, '20008', 'Canvela, Zenaida F', 2, '1986-12-28', 38, 'F', '', 'Separated', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-23 12:16:35', '2025-11-27 03:56:19'),
(209, '20009', 'Curameng, Bernabe S', 2, '1992-05-12', 33, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 12:17:42', '2025-11-27 03:56:52'),
(210, '20010', 'Corpuz, Eyren G', 2, '1991-03-04', 34, 'M', '', 'Separated', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 12:19:29', '2025-11-27 03:57:28'),
(211, '20011', 'Ansong, Neriza C', 2, '1980-07-29', 45, 'F', '', 'Married', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-23 12:20:36', '2025-11-27 04:00:25'),
(212, '20012', 'Corpuz, Teofilo ', 2, '1999-02-04', 26, 'M', '', 'Single', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 12:21:40', '2025-11-27 04:00:51'),
(213, '20013', 'Cozpuz, Wilfred C', 2, '1979-12-19', 45, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 12:22:43', '2025-11-27 04:01:15'),
(214, '20014', 'Cozpuz, Robert D', 2, '1969-04-13', 56, 'M', '', 'Separated', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 12:26:49', '2025-11-27 04:02:45'),
(215, '20015', 'Corpuz, Regie R', 2, '1988-12-20', 36, 'M', '', 'Single', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 12:27:48', '2025-11-27 04:04:06'),
(216, '20016', 'Domingo, Sonny B', 2, '1979-11-07', 46, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 12:29:04', '2025-11-27 04:05:57'),
(217, '20017', 'Domingo, Loreto T', 2, '1968-02-02', 57, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 12:29:57', '2025-11-27 04:09:59'),
(218, '20018', 'Domingo, Saturnina A', 2, '1969-12-04', 55, 'F', '', 'Widowed', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 12:31:10', '2025-11-27 04:25:22'),
(219, '20019', 'Espejo, Gabriel M', 2, '1997-04-27', 28, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:00:32', '2025-11-27 04:25:44'),
(220, '20020', 'Felipe, Sherwin ', 2, '1979-03-04', 46, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:03:50', '2025-11-27 04:26:18'),
(221, '20021', 'Ferreras, Ariel F', 2, '1988-12-12', 36, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:05:08', '2025-11-27 04:26:38'),
(222, '20022', 'Ferreras, Leona F', 2, '1979-04-03', 46, 'M', '', 'Separated', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:07:09', '2025-11-27 04:26:53'),
(223, '20023', 'Gapasin, Jerry M', 2, '1999-03-04', 26, 'M', '', 'Single', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:09:38', '2025-11-27 04:27:22'),
(224, '20024', 'Gacusan, Rommel D', 2, '1993-01-19', 32, 'M', '', 'Single', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:12:02', '2025-11-27 04:27:39'),
(225, '20025', 'Huyana, Eduardo C', 2, '1971-07-08', 54, 'M', '', 'Widowed', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:14:22', '2025-11-27 04:28:20'),
(226, '20026', 'Ibarra, Trece D', 2, '1978-05-13', 47, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:25:33', '2025-11-27 04:30:56'),
(227, '20027', 'Ibarra, Enersto V', 2, '1987-10-12', 38, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:26:31', '2025-11-27 04:30:59'),
(228, '20028', 'Galleto, Alona J', 2, '1979-01-31', 46, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:28:51', '2025-11-27 04:28:00'),
(229, '20029', 'Ibarra, Rosita F', 2, '1979-11-23', 46, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:33:47', '2025-11-27 04:31:01'),
(230, '20030', 'Ibarra, John C', 2, '2001-12-30', 23, 'M', '', 'Single', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:34:49', '2025-11-27 04:31:03'),
(231, '20031', 'Ibarra, Jaime V', 2, '1979-12-16', 45, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:36:15', '2025-11-27 04:31:07'),
(232, '20032', 'Ines, Carmelita Q', 2, '1978-06-22', 47, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-23 13:37:22', '2025-11-27 04:31:33'),
(233, '70007', 'Manzano , Maria  M', 8, '1989-11-07', 36, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 05:00:59', '2025-11-27 05:22:16'),
(234, '70008', 'Manzano, Wilcon D', 8, '1996-11-06', 29, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 05:03:36', '2025-11-27 05:23:20'),
(235, '70009', 'Apostol, Armando M', 8, '1979-06-07', 46, 'M', '', 'Separated', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 05:07:14', '2025-11-27 05:23:39'),
(236, '70010', 'Sapirano, Ermeraldo G', 8, '1989-07-29', 36, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 05:13:50', '2025-11-27 14:07:13'),
(237, '70011', 'Curameng , Maria M', 8, '1978-12-12', 46, 'F', '', 'Single', NULL, 'Head', NULL, NULL, NULL, '2025-11-24 05:22:42', '2025-11-24 05:22:42'),
(238, '70012', 'Apostol, Daniel B', 8, '1990-09-10', 35, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-24 05:40:42', '2025-11-24 05:40:42'),
(239, '70013', 'Jatoy, Ruben A', 8, '1999-01-11', 26, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 05:44:50', '2025-11-27 05:25:35'),
(240, '41002', 'Agustin, Lyle P', 4, '1990-04-22', 35, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 06:58:24', '2025-11-27 04:52:47'),
(241, '41003', 'Agustin, Rodel G', 4, '1978-08-11', 47, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 06:59:49', '2025-11-27 04:52:49'),
(242, '41004', 'Ramales, Rowil J', 4, '1979-08-08', 46, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 07:00:47', '2025-11-27 05:11:43'),
(243, '41005', 'Ramales, Roberto R', 4, '1989-02-05', 36, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 07:01:47', '2025-11-27 05:11:53'),
(244, '41006', 'Ragos, Ronita R', 4, '1979-08-03', 46, 'F', '', 'Married', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-24 07:02:55', '2025-11-27 05:12:06'),
(245, '41007', 'Ragos, Ridy R', 4, '1989-03-03', 36, 'F', '', 'Married', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-24 07:04:01', '2025-11-27 05:12:17'),
(246, '41008', 'Oleganio, Richard G', 4, '1999-03-09', 26, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 07:05:53', '2025-11-27 05:09:38'),
(247, '41009', 'Manzano , Jilna G', 4, '1957-02-05', 68, 'F', '', '', NULL, 'Head', NULL, 'E (Elderly 60+)', 0, '2025-11-24 07:07:27', '2025-11-27 13:58:19'),
(248, '41010', 'Relacio, Allan A', 4, '1979-02-12', 46, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 07:09:35', '2025-11-27 05:13:48'),
(249, '41011', 'Ramales, Jovita R', 4, '1978-12-11', 46, 'F', '', 'Separated', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-24 07:11:20', '2025-11-27 05:13:41'),
(250, '41012', 'Tigno, Develi ', 4, '1997-12-21', 27, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 07:12:22', '2025-11-27 05:15:15'),
(251, '41013', 'Ramales, Reymark G', 4, '1990-11-12', 35, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 07:24:03', '2025-11-27 05:13:55'),
(252, '41014', 'Ramales, Nestor  D', 4, '1979-01-06', 46, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 07:25:27', '2025-11-27 05:14:07'),
(253, '41015', 'Erestain, Anthony T', 4, '1993-06-26', 32, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 07:28:23', '2025-11-27 05:08:01'),
(254, '41016', 'Gutierez, Rodeljo A', 4, '1987-02-02', 38, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 12:52:07', '2025-11-27 05:09:03'),
(255, '41017', 'Parazo, Rafaela D', 4, '1999-11-11', 26, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', 0, '2025-11-24 12:58:54', '2025-11-27 13:49:11'),
(256, '41018', 'Tormes, Jiwenit G', 4, '1990-01-11', 35, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 13:01:36', '2025-11-27 05:15:28'),
(257, '41019', 'Dacayanan, Maryjane D', 4, '1980-06-12', 45, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 13:02:47', '2025-11-27 04:52:51'),
(258, '41020', 'Dacayanan, Alfedo S', 4, '1989-05-20', 36, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 13:05:49', '2025-11-27 04:52:53'),
(259, '41021', 'Rivera, Willy L', 4, '1989-11-28', 35, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 13:09:46', '2025-11-27 05:15:03'),
(260, '41022', 'Dacayanan, Jocelyn T', 4, '1979-01-23', 46, 'F', '', 'Married', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-24 13:10:45', '2025-11-27 04:52:55'),
(261, '41023', 'Rivera, Aldrin L', 4, '1998-02-27', 27, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 13:11:42', '2025-11-27 05:16:34'),
(262, '20004', 'Balanay, Barbara B', 2, '1978-03-05', 47, 'F', '', 'Married', 203, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-24 13:19:32', '2025-11-27 03:50:48'),
(263, '20004', 'Balanay, Matt B', 2, '2020-06-17', 5, 'M', '', 'Single', 203, 'Son', NULL, 'NRP (No Record Provided)', 0, '2025-11-24 13:20:29', '2025-11-27 13:47:01'),
(264, '20004', 'Balanay, Patrizia B', 2, '2003-04-25', 22, 'F', '', 'Single', 203, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-24 13:21:47', '2025-11-27 03:50:48'),
(265, '20005', 'Bagay, Kevin ', 2, '2000-07-08', 25, 'M', '', 'Single', 204, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-24 13:23:52', '2025-11-27 03:51:13'),
(266, '60001', 'Adi, Eduardo R', 7, '1978-07-30', 47, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 12:24:28', '2025-11-27 05:33:13'),
(267, '60002', 'Aquino, Federico C', 7, '1979-03-31', 46, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 12:27:33', '2025-11-27 05:33:31'),
(268, '60003', 'Bagay, Natividad D.c', 7, '1999-05-13', 26, 'F', '', 'Single', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-25 12:31:25', '2025-11-27 05:34:20'),
(269, '60004', 'Bagay, Lindon D', 7, '1979-11-04', 46, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 12:32:39', '2025-11-27 05:34:49'),
(270, '60005', 'Bagay, Led M', 7, '1990-09-12', 35, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 12:34:31', '2025-11-27 05:35:23'),
(271, '60006', 'Bagay, Alego D', 7, '1989-06-10', 36, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 12:36:07', '2025-11-27 05:35:42'),
(272, '60007', 'Bagay, Romeo  V', 7, '1989-03-29', 36, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 12:45:12', '2025-11-27 05:36:15'),
(273, '60008', 'Bautista, Connie M', 7, '1968-12-04', 56, 'F', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 12:48:16', '2025-11-27 05:37:12'),
(274, '60009', 'Baltazar, Joven ', 7, '1980-02-24', 45, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 12:49:07', '2025-11-27 05:37:37'),
(275, '60010', 'Bugarin, Vitor A', 7, '1998-05-21', 27, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 12:49:58', '2025-11-27 05:38:09'),
(276, '60011', 'Arnio, Arnel ', 7, '1989-03-21', 36, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 12:53:42', '2025-11-27 05:38:48'),
(277, '60012', 'Cabigas, Genne B', 7, '1988-05-31', 37, 'M', '', 'Separated', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 13:02:37', '2025-11-27 05:39:18'),
(278, '60013', 'Corpuz, Sona D', 7, '1979-02-12', 46, 'M', '', 'Widowed', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 13:06:32', '2025-11-27 05:39:48'),
(279, '60014', 'Corpuz, Felipe D', 7, '1977-01-23', 48, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 13:07:20', '2025-11-27 05:40:12'),
(280, '60015', 'Corpuz, Andora F', 7, '1999-08-12', 26, 'F', '', 'Married', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-25 13:12:01', '2025-11-27 05:40:45'),
(281, '60016', 'Delacruz, Amador R', 7, '1954-06-09', 71, 'M', '', '', NULL, 'Head', NULL, 'E (Elderly 60+)', 0, '2025-11-25 13:18:16', '2025-11-27 14:00:59'),
(282, '60017', 'Delacruz, Reynante R', 7, '1979-12-03', 45, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 13:19:54', '2025-11-27 05:42:00'),
(283, '60018', 'Delacruz, Larry R', 7, '1989-12-23', 35, 'M', '', 'Single', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 13:20:51', '2025-11-27 05:42:30'),
(284, '60019', 'Domingo, Flor ', 7, '2002-02-04', 23, 'F', '', 'Single', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-25 13:21:51', '2025-11-27 05:47:55'),
(285, '60020', 'Domingo, Mariano ', 7, '1999-12-09', 25, 'M', '', 'Single', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 13:22:33', '2025-11-27 05:48:31'),
(286, '60021', 'Domingo, Archie ', 7, '2000-03-21', 25, 'F', '', 'Single', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-25 13:23:18', '2025-11-27 05:49:05'),
(287, '60022', 'Facun, Marisol R', 7, '1989-03-21', 36, 'F', '', 'Separated', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-25 13:24:06', '2025-11-27 05:49:30'),
(288, '60023', 'Feliciano, Francisco M', 7, '1979-01-31', 46, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 13:25:51', '2025-11-27 05:49:54'),
(289, '60024', 'Felipe, Apolinaria R', 7, '1994-03-01', 31, 'F', '', 'Married', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-25 13:28:00', '2025-11-27 05:51:05'),
(290, '60025', 'Fernando, Sonny ', 7, '2001-09-17', 24, 'M', '', 'Single', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 13:28:52', '2025-11-27 05:59:50'),
(291, '60026', 'Galsim, Evelyn B', 7, '1977-08-19', 48, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 13:29:53', '2025-11-27 06:00:57'),
(292, '60027', 'Gutierez, Leonina D', 7, '1997-03-17', 28, 'F', '', 'Single', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-25 13:33:55', '2025-11-27 06:01:41'),
(293, '60028', 'Gonzales, Romel C', 7, '1989-01-31', 36, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-25 13:35:13', '2025-11-27 06:02:14'),
(294, '60029', 'Juan, James P', 7, '1998-01-31', 27, 'F', '', 'Married', NULL, 'Head', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-25 13:36:03', '2025-11-27 06:02:47');
INSERT INTO `person` (`person_id`, `household_number`, `full_name`, `address_id`, `birthdate`, `age`, `gender`, `contact_number`, `civil_status`, `related_person_id`, `relationship_type`, `philhealth_number`, `health_condition`, `deceased`, `created_at`, `updated_at`) VALUES
(295, '30005', 'Agustin, Aurora A.', 3, '1971-08-16', 54, 'F', '', 'Widowed', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 02:54:19', '2025-11-27 05:09:55'),
(296, '30006', 'Agustin, Juan C.', 3, '1965-06-24', 60, 'M', '', 'Separated', NULL, 'Head', NULL, 'E (Elderly 60+)', NULL, '2025-11-27 02:55:51', '2025-11-27 05:11:10'),
(297, '30007', 'Altre, Rovel C.', 3, '1966-01-29', 59, 'M', '', 'Separated', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 03:04:52', '2025-11-27 05:11:37'),
(301, '30009', 'Apresto, Casimiro ', 3, '1965-01-03', 60, 'M', '', 'Married', NULL, 'Head', NULL, 'E (Elderly 60+)', NULL, '2025-11-27 03:15:29', '2025-11-27 05:13:58'),
(302, '30009', 'Apresto, Elvie M.', 3, '1969-02-05', 56, 'F', '', 'Married', 301, 'Spouse', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 03:18:00', '2025-11-27 05:13:58'),
(303, '30009', 'Apresto, Samantha M', 3, '2000-05-22', 25, 'F', '', 'Single', 301, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-27 03:18:49', '2025-11-27 05:13:58'),
(304, '30010', 'Asuncion, Perlita R', 3, '1958-11-17', 67, 'F', '', 'Widowed', NULL, 'Head', NULL, 'E (Elderly 60+)', NULL, '2025-11-27 03:22:52', '2025-11-27 05:14:58'),
(305, '30010', 'Asuncion, Abemelec R', 3, '1996-09-20', 29, 'M', '', 'Single', 304, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 03:24:03', '2025-11-27 05:14:58'),
(306, '30011', 'Asuncion, Clarence R', 3, '1978-06-12', 47, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 03:25:50', '2025-11-27 05:15:52'),
(307, '30011', 'Asuncion, Filipina M', 3, '1980-06-17', 45, 'F', '', 'Married', 306, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-27 03:26:56', '2025-11-27 05:15:52'),
(308, '30011', ' Asuncion, Joseph Aaron M', 3, '2004-09-17', 21, 'M', '', 'Single', 306, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 03:28:30', '2025-11-27 05:15:52'),
(309, '30012', 'Asuncion, Erwin Joy R', 3, '1987-12-27', 37, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 03:31:14', '2025-11-27 05:16:14'),
(310, '30012', 'Asuncion, Luisalyn E', 3, '1994-09-14', 31, 'F', '', 'Married', 309, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-27 03:32:15', '2025-11-27 05:16:14'),
(311, '30013', 'Alitida, Julito C', 3, '1970-07-31', 55, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 03:34:23', '2025-11-27 05:16:37'),
(312, '30013', 'Alitado, Cristy R', 3, '1978-10-28', 47, 'F', '', 'Married', 311, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-27 03:35:47', '2025-11-27 05:16:37'),
(313, '30013', 'Alitada, Jerome R', 3, '2004-05-28', 21, 'M', '', 'Single', 311, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 03:36:42', '2025-11-27 05:16:37'),
(314, '42011', 'Laza, Eddie S', 5, '1988-06-21', 37, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 05:11:23', '2025-11-27 15:35:06'),
(315, '42011', 'Laza, Jessmer R', 5, '1987-01-02', 38, 'F', '', 'Married', 314, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-27 05:15:10', '2025-11-27 15:35:06'),
(316, '42011', 'Laza, Althea Janine R', 5, '2010-02-09', 15, 'F', '', 'Single', 314, 'Daughter', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-27 05:16:09', '2025-11-27 15:35:06'),
(320, '42012', 'Ramalu, Emily D', 5, '1988-11-02', 37, 'F', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 05:24:50', '2025-11-27 05:24:50'),
(321, '42012', 'Barde, Francis C', 5, '1983-09-12', 42, 'M', '', 'Married', 320, 'Spouse', NULL, NULL, NULL, '2025-11-27 05:28:43', '2025-11-27 05:28:43'),
(322, '42012', 'Barde, Sky Emz Arche R', 5, '2024-04-30', 1, 'F', '', 'Single', 320, 'Daughter', NULL, NULL, NULL, '2025-11-27 05:33:28', '2025-11-27 05:33:28'),
(323, '42013', 'Barot, Robert A', 5, '1972-06-07', 53, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 05:35:15', '2025-11-27 13:04:17'),
(324, '20014', 'Barot, Jeanit L', 2, '1990-12-15', 34, 'F', '', 'Married', 214, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-27 05:40:54', '2025-11-27 13:42:28'),
(325, '20014', 'Barot, Richard P', 2, '2015-12-19', 9, 'M', '', 'Single', 214, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 05:41:38', '2025-11-27 13:42:28'),
(326, '20014', 'Barot, Jennilyn P', 2, '2023-10-15', 2, 'F', '', 'Single', 214, 'Daughter', NULL, 'C (Child 12-71 months)', NULL, '2025-11-27 05:42:29', '2025-11-27 13:42:28'),
(327, '42014', 'Barot, Marcelo A', 5, '1978-07-05', 47, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 05:51:34', '2025-11-27 13:03:50'),
(328, '42015', 'Delfonso Sr, Janico L', 5, '1990-06-21', 35, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 05:55:19', '2025-11-27 05:55:19'),
(329, '42015', 'Delfonso, Fatima B', 5, '1990-06-10', 35, 'F', '', 'Married', 328, 'Spouse', NULL, NULL, NULL, '2025-11-27 05:56:42', '2025-11-27 05:56:42'),
(330, '42015', 'Delfonso, Mark Daniel L', 5, '2009-12-14', 15, 'M', '', 'Single', 328, 'Son', NULL, NULL, NULL, '2025-11-27 05:58:39', '2025-11-27 05:58:39'),
(331, '42015', 'Delfonso Jr, Janico L', 5, '2016-02-25', 9, 'M', '', 'Single', 328, 'Son', NULL, NULL, NULL, '2025-11-27 06:01:00', '2025-11-27 06:01:00'),
(332, '42016', 'Labrador, Ferdinand B', 5, '1977-03-26', 48, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 06:03:47', '2025-11-27 06:03:47'),
(333, '42016', 'Labrador, Ma Louedu V', 5, '1985-10-14', 40, 'F', '', 'Married', 332, 'Spouse', NULL, NULL, NULL, '2025-11-27 06:10:49', '2025-11-27 06:10:49'),
(334, '42016', 'Labrador, Mark Angelo V', 5, '2004-02-02', 21, 'M', '', 'Single', 332, 'Brother', NULL, NULL, NULL, '2025-11-27 06:12:52', '2025-11-27 06:12:52'),
(336, '42017', 'Ohedo, Joven V', 5, '1986-06-27', 39, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 06:35:35', '2025-11-27 06:35:35'),
(337, '42018', 'Ohedo, Lovely Joy D', 5, '1989-06-12', 36, 'F', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 06:40:57', '2025-11-27 06:40:57'),
(338, '42018', 'Ohedo, Joven Villar', 5, '1986-06-27', 39, 'M', '', 'Married', 337, 'Spouse', NULL, NULL, NULL, '2025-11-27 06:42:24', '2025-11-27 06:42:24'),
(339, '42018', 'Ohedo, Juvelyn De Jesus', 5, '2010-10-24', 15, 'F', '', 'Single', 337, 'Daughter', NULL, NULL, NULL, '2025-11-27 06:43:36', '2025-11-27 06:43:36'),
(340, '10012', 'Legaspi, Emmanuel A', 1, '1981-04-17', 44, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 06:54:49', '2025-11-27 06:54:49'),
(341, '10012', 'Legaspi, Jeremie B', 1, '1993-10-02', 32, 'F', '', 'Married', 340, 'Spouse', NULL, NULL, NULL, '2025-11-27 06:55:51', '2025-11-27 06:55:51'),
(342, '10012', 'Legaspi, Zack Raigen B', 1, '2020-08-07', 5, 'M', '', 'Single', 340, 'Son', NULL, NULL, NULL, '2025-11-27 06:56:56', '2025-11-27 06:56:56'),
(343, '10013', 'Esteban, Rolly O', 1, '1977-10-25', 48, 'M', '', 'Single', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 06:58:48', '2025-11-27 06:58:48'),
(344, '10013', 'Bautista, Mark T', 1, '1995-04-14', 30, 'M', '', 'Married', 343, 'Pamangkin', NULL, NULL, NULL, '2025-11-27 07:14:45', '2025-11-27 07:14:45'),
(345, '10013', 'Bautista, Catherine M', 1, '2001-03-09', 24, 'F', '', 'Married', 343, 'Pamangkin', NULL, NULL, NULL, '2025-11-27 07:16:41', '2025-11-27 07:16:41'),
(346, '60030', 'Domingo, Archie ', 7, '2000-09-06', 25, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 07:17:59', '2025-11-27 13:53:31'),
(347, '10014', 'Dela Cruz, Violeta C', 1, '1978-08-14', 47, 'F', '', 'Widowed', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 07:19:39', '2025-11-27 07:19:39'),
(348, '60030', 'Facun , Hyacinth Rose  R', 7, '1999-07-23', 26, 'F', '', 'Married', 346, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-27 07:21:25', '2025-11-27 13:53:31'),
(349, '10015', 'Grospe, Benjie J', 1, '1995-05-28', 30, 'M', '', 'Single', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 07:21:50', '2025-11-27 07:21:50'),
(350, '60030', 'Facun , Yosef Noeh R', 7, '2004-08-08', 21, 'M', '', 'Single', 346, 'Brother-in-law', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 07:22:31', '2025-11-27 13:53:31'),
(351, '60030', 'Domingo, Zack Alvin F', 7, '2016-06-08', 9, 'M', '', 'Single', 346, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 07:23:42', '2025-11-27 13:53:31'),
(352, '10015', 'Grospe, Vergel J', 1, '1996-05-27', 29, 'M', '', 'Single', 349, 'Brother', NULL, NULL, NULL, '2025-11-27 07:24:47', '2025-11-27 07:24:47'),
(353, '60030', 'Domingo, Zeckia Abril F', 7, '2019-12-17', 5, 'F', '', 'Single', 346, 'Daughter', NULL, 'C (Child 12-71 months)', NULL, '2025-11-27 07:24:49', '2025-11-27 13:53:31'),
(354, '10015', 'Grospe, Vivian J', 1, '1998-02-14', 27, 'F', '', 'Live In', 349, 'Sister', NULL, NULL, NULL, '2025-11-27 07:25:49', '2025-11-27 07:25:49'),
(355, '10016', 'Ganotese, Rolando D', 1, '1965-10-24', 60, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 07:30:00', '2025-11-27 07:30:00'),
(356, '10016', 'Ganotese, Shirley G', 1, '1964-12-17', 60, 'F', '', 'Married', 355, 'Spouse', NULL, NULL, NULL, '2025-11-27 07:30:51', '2025-11-27 07:30:51'),
(357, '10016', 'Ganotese, Raquel G', 1, '2005-12-19', 19, 'F', '', 'Single', 355, 'Daughter', NULL, NULL, NULL, '2025-11-27 07:31:45', '2025-11-27 07:31:45'),
(358, '50014', 'Gutierrez, Felix R', 6, '1943-03-13', 82, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 07:32:28', '2025-11-27 07:32:28'),
(359, '50014', 'Gutierrez, Claire ', 6, '1942-10-29', 83, 'F', '', 'Married', 358, 'Spouse', NULL, NULL, NULL, '2025-11-27 07:35:54', '2025-11-27 07:35:54'),
(360, '50015', 'Gumen, Carlito S', 6, '1957-01-11', 68, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 07:48:00', '2025-11-27 07:48:00'),
(361, '50015', 'Gumen, Joycilyn D', 6, '1958-12-16', 66, 'F', '', 'Married', 360, 'Spouse', NULL, NULL, NULL, '2025-11-27 07:51:31', '2025-11-27 07:51:31'),
(362, '20033', 'Hufano, Eduardo', 2, '1965-04-13', 60, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 08:08:55', '2025-11-27 08:28:10'),
(363, '30014', 'Cancino Sr, Alejandro  S', 3, '1973-12-17', 51, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 08:10:21', '2025-11-27 08:10:21'),
(364, '30014', 'Cancino, Imelda Y', 3, '1969-11-13', 56, 'F', '', 'Married', 363, 'Spouse', NULL, NULL, NULL, '2025-11-27 08:11:58', '2025-11-27 08:11:58'),
(365, '30014', 'Cancino, Angelica Y', 3, '1999-08-03', 26, 'F', '', 'Single', 363, 'Daughter', NULL, NULL, NULL, '2025-11-27 08:13:03', '2025-11-27 08:13:03'),
(368, '30015', 'Conception, Julita F', 3, '1978-04-25', 47, 'F', '', 'Widowed', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 08:18:16', '2025-11-27 08:18:16'),
(369, '30015', 'Conception, Julie Cris F', 3, '1997-07-02', 28, 'F', '', 'Live In', 368, 'Daughter', NULL, NULL, NULL, '2025-11-27 08:23:27', '2025-11-27 08:23:27'),
(370, '20033', 'Hufano, Evangeline  C', 2, '1971-11-04', 54, 'F', '', 'Married', 362, 'Spouse', NULL, NULL, NULL, '2025-11-27 08:31:55', '2025-11-27 08:31:55'),
(371, '20033', 'Hufano, Debbie Anne ', 2, '1997-11-16', 28, 'F', '', 'Single', 362, 'Daughter', NULL, NULL, NULL, '2025-11-27 08:32:55', '2025-11-27 08:32:55'),
(372, '20034', 'Insigne, Christian F', 2, '1985-01-27', 40, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 08:38:35', '2025-11-27 08:38:35'),
(373, '20034', 'Insigne, Katarina P', 2, '1985-03-02', 40, 'F', '', 'Married', 372, 'Spouse', NULL, NULL, NULL, '2025-11-27 08:40:22', '2025-11-27 08:40:22'),
(374, '20035', 'Lactaoen, Bonifacio P', 2, '1944-02-29', 81, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 08:42:30', '2025-11-27 08:42:30'),
(375, '20035', 'Lactaoen, Isidra O', 2, '1949-05-15', 76, 'F', '', 'Married', 374, 'Spouse', NULL, NULL, NULL, '2025-11-27 08:43:25', '2025-11-27 08:43:25'),
(376, '42019', 'De Jesus, Edwin Q', 5, '1991-02-03', 34, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 08:47:50', '2025-11-27 08:47:50'),
(377, '42019', 'Gregorio, Maritess ', 5, '1990-03-12', 35, 'F', '', 'Married', 376, 'Spouse', NULL, NULL, NULL, '2025-11-27 08:49:24', '2025-11-27 08:49:24'),
(378, '42019', 'De Jesus, Ian  C', 5, '2019-05-08', 6, 'M', '', 'Single', 376, 'Son', NULL, NULL, NULL, '2025-11-27 08:50:43', '2025-11-27 08:50:43'),
(379, '70014', 'Lacuesta, Juliana B', 8, '1949-09-13', 76, 'F', '', 'Widowed', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 08:57:35', '2025-11-27 08:57:35'),
(380, '70014', 'Bragasin, Pepito A', 8, '1962-02-10', 63, 'M', '', 'Single', 379, 'Brother', NULL, NULL, NULL, '2025-11-27 08:58:33', '2025-11-27 08:58:33'),
(381, '70015', 'Madrigal, Sofranio G', 8, '1970-01-18', 55, 'M', '', 'Married', NULL, 'Head', NULL, NULL, NULL, '2025-11-27 09:01:01', '2025-11-27 09:01:01'),
(382, '70015', 'Madrigal, Tessie A', 8, '1972-07-27', 53, 'F', '', 'Married', 381, 'Spouse', NULL, NULL, NULL, '2025-11-27 09:02:15', '2025-11-27 09:02:15'),
(383, '70015', 'Madrigal, John Troy ', 8, '2003-02-19', 22, 'M', '', 'Single', 381, 'Son', NULL, NULL, NULL, '2025-11-27 09:03:11', '2025-11-27 09:03:11'),
(384, '70015', 'Madrigal, Kevin Oustin ', 8, '2006-09-16', 19, 'F', '', 'Married', 381, 'Spouse', NULL, NULL, NULL, '2025-11-27 09:05:21', '2025-11-27 09:05:21'),
(385, '41024', 'Lucero, Bernard S', 4, '1974-05-12', 51, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 13:36:01', '2025-11-27 14:04:46'),
(386, '41024', 'Lucero, Myrna F', 4, '1973-08-09', 52, 'F', '', 'Married', 385, 'Spouse', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 13:36:58', '2025-11-27 14:04:46'),
(387, '41024', 'Lucero, Precious Mira ', 4, '2014-11-08', 11, 'F', '', 'Single', 385, 'Daughter', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 13:37:43', '2025-11-27 14:04:46'),
(388, '41025', 'Lijauco, Leonel P', 4, '1971-04-21', 54, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 13:40:12', '2025-11-27 13:55:30'),
(389, '41025', 'Lijuaco, Angelica D', 4, '1971-08-19', 54, 'F', '', 'Married', 388, 'Spouse', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 13:41:04', '2025-11-27 13:55:30'),
(390, '41025', 'Lijuaco, Leo Austine ', 4, '1998-08-10', 27, 'M', '', 'Single', 388, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 13:41:51', '2025-11-27 13:55:30'),
(391, '41025', 'Lijuaco, Louise Ken ', 4, '2002-01-12', 23, 'M', '', 'Single', 388, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 13:42:29', '2025-11-27 13:55:30'),
(392, '30016', 'Mundala, Chryster Zanderson R', 3, '1995-11-29', 29, 'M', '', 'Married', NULL, 'Head', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 13:46:12', '2025-11-27 14:04:24'),
(393, '30016', 'Mundala, Jessa R', 3, '1986-04-05', 39, 'F', '', 'Married', 392, 'Spouse', NULL, 'NP (Non-Pregnant)', NULL, '2025-11-27 13:47:17', '2025-11-27 14:04:24'),
(394, '30016', 'Mundala, Chryster Cyrus R', 3, '2017-06-14', 8, 'M', '', 'Single', 392, 'Son', NULL, 'NRP (No Record Provided)', NULL, '2025-11-27 13:48:18', '2025-11-27 14:04:24'),
(395, '30016', 'Mundala, Kendal Faith R', 3, '2022-10-15', 3, 'F', '', 'Single', 392, 'Daughter', NULL, 'C (Child 12-71 months)', NULL, '2025-11-27 13:48:57', '2025-11-27 14:04:24'),
(396, '41017', 'Parazo, Jeremy D', 4, '2019-10-24', 6, 'M', '', 'Single', 255, 'Son', NULL, 'C (Child 12-71 months)', NULL, '2025-11-27 13:50:09', '2025-11-27 13:50:32'),
(397, '10001', 'Legaspi, Lucas Rein', 1, '2025-08-13', 0, 'M', '0', 'Single', 50, 'Son', NULL, 'I (Infant 1-11 months)', NULL, '2025-11-27 15:59:10', '2025-11-27 16:00:08');

-- --------------------------------------------------------

--
-- Table structure for table `postnatal`
--

CREATE TABLE `postnatal` (
  `postnatal_id` int(11) NOT NULL,
  `pregnancy_record_id` int(11) NOT NULL,
  `delivery_location` varchar(255) DEFAULT NULL,
  `attendant` varchar(255) DEFAULT NULL,
  `service_source` varchar(255) DEFAULT NULL,
  `postnatal_checkups` varchar(255) DEFAULT NULL,
  `family_planning_intent` char(1) DEFAULT NULL,
  `risk_observed` varchar(255) DEFAULT NULL,
  `date_delivered` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pregnancy_medication`
--

CREATE TABLE `pregnancy_medication` (
  `pregnancy_record_id` int(11) NOT NULL,
  `medication_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pregnancy_medication`
--

INSERT INTO `pregnancy_medication` (`pregnancy_record_id`, `medication_id`) VALUES
(1, 2),
(1, 12);

-- --------------------------------------------------------

--
-- Table structure for table `pregnancy_record`
--

CREATE TABLE `pregnancy_record` (
  `pregnancy_record_id` int(11) NOT NULL,
  `records_id` int(11) NOT NULL,
  `pregnancy_period` enum('Prenatal','Postnatal') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pregnancy_record`
--

INSERT INTO `pregnancy_record` (`pregnancy_record_id`, `records_id`, `pregnancy_period`, `created_at`, `updated_at`) VALUES
(1, 599, 'Prenatal', '2025-11-27 17:18:54', '2025-11-27 17:18:54');

-- --------------------------------------------------------

--
-- Table structure for table `prenatal`
--

CREATE TABLE `prenatal` (
  `prenatal_id` int(11) NOT NULL,
  `pregnancy_record_id` int(11) NOT NULL,
  `months_pregnancy` varchar(255) DEFAULT NULL,
  `checkup_date` varchar(255) DEFAULT NULL,
  `birth_plan` varchar(255) DEFAULT NULL,
  `risk_observed` text DEFAULT NULL,
  `last_menstruation` date DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `preg_count` int(11) DEFAULT NULL,
  `child_alive` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prenatal`
--

INSERT INTO `prenatal` (`prenatal_id`, `pregnancy_record_id`, `months_pregnancy`, `checkup_date`, `birth_plan`, `risk_observed`, `last_menstruation`, `expected_delivery_date`, `preg_count`, `child_alive`) VALUES
(1, 1, '6', 'First Trimester (0-84 days),Second Trimester (85-189 days),Third Trimester (190+ days)', 'Y', 'Severe Abdominal Pain', '2025-05-21', '2026-02-25', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `privilege`
--

CREATE TABLE `privilege` (
  `privilege_id` int(11) NOT NULL,
  `privilege_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `privilege`
--

INSERT INTO `privilege` (`privilege_id`, `privilege_name`) VALUES
(1, 'access_household'),
(2, 'access_infant'),
(3, 'access_child_health'),
(4, 'access_pregnant'),
(5, 'access_postnatal'),
(6, 'access_family_planning'),
(7, 'access_patient_medication'),
(8, 'access_manage_account'),
(9, 'access_dashboard'),
(10, 'access_family'),
(11, 'access_map'),
(12, 'access_notice'),
(13, 'access_register'),
(14, 'access_privileges'),
(15, 'access_puroks'),
(16, 'access_records'),
(17, 'access_reports');

-- --------------------------------------------------------

--
-- Table structure for table `records`
--

CREATE TABLE `records` (
  `records_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `record_type` varchar(50) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `records`
--

INSERT INTO `records` (`records_id`, `user_id`, `person_id`, `record_type`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '', NULL, '2025-10-24 03:09:31', '2025-10-24 03:09:31'),
(2, 2, 2, '', NULL, '2025-10-24 03:09:31', '2025-10-24 03:09:31'),
(3, 3, 3, '', NULL, '2025-10-24 03:09:31', '2025-10-24 03:09:31'),
(4, 4, 4, '', NULL, '2025-10-24 03:09:31', '2025-10-24 03:09:31'),
(5, 5, 5, '', NULL, '2025-10-24 03:09:31', '2025-10-24 03:09:31'),
(6, 6, 6, '', NULL, '2025-10-24 03:09:31', '2025-10-24 03:09:31'),
(7, 7, 7, '', NULL, '2025-10-24 03:09:31', '2025-10-24 03:09:31'),
(8, 8, 8, '', NULL, '2025-10-24 03:09:31', '2025-10-24 03:09:31'),
(9, 9, 9, '', NULL, '2025-10-24 03:09:31', '2025-10-24 03:09:31'),
(10, 10, 10, '', NULL, '2025-10-24 03:09:31', '2025-10-24 03:09:31'),
(11, 11, 11, '', NULL, '2025-10-24 06:05:34', '2025-10-24 06:05:34'),
(12, 12, 14, '', NULL, '2025-10-24 06:08:52', '2025-10-24 06:08:52'),
(13, 13, 17, '', NULL, '2025-10-24 08:08:47', '2025-10-24 08:08:47'),
(14, 14, 24, '', NULL, '2025-10-25 02:50:41', '2025-10-25 02:50:41'),
(15, 15, 28, '', NULL, '2025-10-25 02:54:09', '2025-10-25 02:54:09'),
(16, 16, 32, '', NULL, '2025-10-25 02:57:40', '2025-10-25 02:57:40'),
(17, 17, 36, '', NULL, '2025-10-25 13:05:23', '2025-10-25 13:05:23'),
(18, 18, 41, '', NULL, '2025-10-25 13:13:11', '2025-10-25 13:13:11'),
(19, 19, 44, '', NULL, '2025-10-25 13:17:44', '2025-10-25 13:17:44'),
(20, 20, 48, '', NULL, '2025-10-25 13:26:49', '2025-10-25 13:26:49'),
(21, 21, 50, '', NULL, '2025-10-25 13:31:29', '2025-10-25 13:31:29'),
(22, 22, 54, '', NULL, '2025-10-26 02:52:36', '2025-10-26 02:52:36'),
(23, 23, 58, '', NULL, '2025-10-26 03:27:31', '2025-10-26 03:27:31'),
(24, 24, 67, '', NULL, '2025-10-26 03:53:06', '2025-10-26 03:53:06'),
(25, 25, 75, '', NULL, '2025-10-26 04:18:13', '2025-10-26 04:18:13'),
(26, 26, 79, '', NULL, '2025-10-26 04:22:42', '2025-10-26 04:22:42'),
(27, 27, 87, '', NULL, '2025-10-26 07:47:20', '2025-10-26 07:47:20'),
(28, 28, 91, '', NULL, '2025-10-26 07:50:17', '2025-10-26 07:50:17'),
(29, 29, 97, '', NULL, '2025-10-26 07:55:22', '2025-10-26 07:55:22'),
(30, 30, 103, '', NULL, '2025-10-26 07:59:43', '2025-10-26 07:59:43'),
(31, 31, 110, '', NULL, '2025-10-26 09:39:20', '2025-10-26 09:39:20'),
(32, 32, 113, '', NULL, '2025-10-26 09:44:02', '2025-10-26 09:44:02'),
(33, 33, 120, '', NULL, '2025-10-26 09:57:57', '2025-10-26 09:57:57'),
(34, 34, 121, NULL, NULL, '2025-10-31 13:25:41', '2025-10-31 13:25:41'),
(35, 35, 122, NULL, NULL, '2025-10-31 13:37:35', '2025-10-31 13:37:35'),
(36, 36, 126, NULL, NULL, '2025-10-31 13:45:08', '2025-10-31 13:45:08'),
(37, 37, 128, NULL, NULL, '2025-11-02 06:49:06', '2025-11-02 06:49:06'),
(38, 38, 131, NULL, NULL, '2025-11-02 07:09:36', '2025-11-02 07:09:36'),
(39, 39, 135, NULL, NULL, '2025-11-02 07:15:18', '2025-11-02 07:15:18'),
(40, 40, 138, NULL, NULL, '2025-11-02 07:24:48', '2025-11-02 07:24:48'),
(41, 41, 139, NULL, NULL, '2025-11-02 07:33:18', '2025-11-02 07:33:18'),
(42, 42, 142, NULL, NULL, '2025-11-02 07:38:29', '2025-11-02 07:38:29'),
(49, 49, 150, NULL, NULL, '2025-11-03 06:24:48', '2025-11-03 06:24:48'),
(50, 50, 151, NULL, NULL, '2025-11-03 06:37:20', '2025-11-03 06:37:20'),
(51, 51, 155, NULL, NULL, '2025-11-03 07:14:35', '2025-11-03 07:14:35'),
(52, 52, 158, NULL, NULL, '2025-11-03 07:24:23', '2025-11-03 07:24:23'),
(53, 53, 162, NULL, NULL, '2025-11-03 13:38:22', '2025-11-03 13:38:22'),
(54, 54, 174, NULL, NULL, '2025-11-04 03:15:35', '2025-11-04 03:15:35'),
(55, 55, 176, NULL, NULL, '2025-11-11 05:53:29', '2025-11-11 05:53:29'),
(56, 56, 178, NULL, NULL, '2025-11-11 11:49:44', '2025-11-11 11:49:44'),
(57, 57, 182, NULL, NULL, '2025-11-11 11:55:07', '2025-11-11 11:55:07'),
(58, 58, 186, NULL, NULL, '2025-11-11 12:06:03', '2025-11-11 12:06:03'),
(59, 59, 192, NULL, NULL, '2025-11-11 12:13:38', '2025-11-11 12:13:38'),
(60, 60, 196, NULL, NULL, '2025-11-11 12:21:48', '2025-11-11 12:21:48'),
(61, 61, 200, NULL, NULL, '2025-11-22 14:42:53', '2025-11-22 14:42:53'),
(62, 62, 201, NULL, NULL, '2025-11-22 14:44:36', '2025-11-22 14:44:36'),
(63, 63, 202, NULL, NULL, '2025-11-22 14:45:50', '2025-11-22 14:45:50'),
(64, 64, 203, NULL, NULL, '2025-11-22 14:47:09', '2025-11-22 14:47:09'),
(65, 65, 204, NULL, NULL, '2025-11-22 14:48:15', '2025-11-22 14:48:15'),
(66, 66, 205, NULL, NULL, '2025-11-22 14:50:44', '2025-11-22 14:50:44'),
(67, 21, 50, 'household_record', 3, '2025-11-23 08:17:39', '2025-11-23 08:17:39'),
(68, 21, 51, 'household_record', 3, '2025-11-23 08:17:39', '2025-11-23 08:17:39'),
(69, 21, 52, 'household_record', 3, '2025-11-23 08:17:39', '2025-11-23 08:17:39'),
(70, 21, 53, 'household_record', 3, '2025-11-23 08:17:39', '2025-11-23 08:17:39'),
(71, 22, 54, 'household_record', 3, '2025-11-23 08:18:52', '2025-11-23 08:18:52'),
(72, 22, 55, 'household_record', 3, '2025-11-23 08:18:52', '2025-11-23 08:18:52'),
(73, 22, 56, 'household_record', 3, '2025-11-23 08:18:52', '2025-11-23 08:18:52'),
(74, 22, 57, 'household_record', 3, '2025-11-23 08:18:52', '2025-11-23 08:18:52'),
(75, 23, 58, 'household_record', 3, '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(76, 23, 59, 'household_record', 3, '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(77, 23, 60, 'household_record', 3, '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(78, 23, 61, 'household_record', 3, '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(79, 23, 62, 'household_record', 3, '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(80, 23, 63, 'household_record', 3, '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(81, 23, 64, 'household_record', 3, '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(82, 23, 65, 'household_record', 3, '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(83, 23, 66, 'household_record', 3, '2025-11-23 08:19:36', '2025-11-23 08:19:36'),
(84, 24, 67, 'household_record', 3, '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(85, 24, 68, 'household_record', 3, '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(86, 24, 69, 'household_record', 3, '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(87, 24, 70, 'household_record', 3, '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(88, 24, 71, 'household_record', 3, '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(89, 24, 72, 'household_record', 3, '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(90, 24, 73, 'household_record', 3, '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(91, 24, 74, 'household_record', 3, '2025-11-23 08:31:08', '2025-11-23 08:31:08'),
(92, 25, 75, 'household_record', 3, '2025-11-23 08:32:01', '2025-11-23 08:32:01'),
(93, 25, 76, 'household_record', 3, '2025-11-23 08:32:01', '2025-11-23 08:32:01'),
(94, 25, 77, 'household_record', 3, '2025-11-23 08:32:01', '2025-11-23 08:32:01'),
(95, 25, 78, 'household_record', 3, '2025-11-23 08:32:01', '2025-11-23 08:32:01'),
(96, 26, 79, 'household_record', 3, '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(97, 26, 80, 'household_record', 3, '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(98, 26, 81, 'household_record', 3, '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(99, 26, 82, 'household_record', 3, '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(100, 26, 83, 'household_record', 3, '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(101, 26, 84, 'household_record', 3, '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(102, 26, 85, 'household_record', 3, '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(103, 26, 86, 'household_record', 3, '2025-11-23 08:32:42', '2025-11-23 08:32:42'),
(104, 27, 87, 'household_record', 3, '2025-11-23 08:33:13', '2025-11-23 08:33:13'),
(105, 27, 88, 'household_record', 3, '2025-11-23 08:33:13', '2025-11-23 08:33:13'),
(106, 27, 89, 'household_record', 3, '2025-11-23 08:33:13', '2025-11-23 08:33:13'),
(107, 27, 90, 'household_record', 3, '2025-11-23 08:33:13', '2025-11-23 08:33:13'),
(108, 28, 91, 'household_record', 3, '2025-11-23 08:33:51', '2025-11-23 08:33:51'),
(109, 28, 92, 'household_record', 3, '2025-11-23 08:33:51', '2025-11-23 08:33:51'),
(110, 28, 93, 'household_record', 3, '2025-11-23 08:33:51', '2025-11-23 08:33:51'),
(111, 28, 94, 'household_record', 3, '2025-11-23 08:33:51', '2025-11-23 08:33:51'),
(112, 28, 95, 'household_record', 3, '2025-11-23 08:33:51', '2025-11-23 08:33:51'),
(113, 28, 96, 'household_record', 3, '2025-11-23 08:33:51', '2025-11-23 08:33:51'),
(114, 28, 206, 'household_record', 3, '2025-11-23 08:39:28', '2025-11-23 08:39:28'),
(115, 29, 97, 'household_record', 3, '2025-11-23 08:40:34', '2025-11-23 08:40:34'),
(116, 29, 98, 'household_record', 3, '2025-11-23 08:40:34', '2025-11-23 08:40:34'),
(117, 29, 99, 'household_record', 3, '2025-11-23 08:40:34', '2025-11-23 08:40:34'),
(118, 29, 100, 'household_record', 3, '2025-11-23 08:40:34', '2025-11-23 08:40:34'),
(119, 29, 101, 'household_record', 3, '2025-11-23 08:40:34', '2025-11-23 08:40:34'),
(120, 29, 102, 'household_record', 3, '2025-11-23 08:40:34', '2025-11-23 08:40:34'),
(121, 30, 103, 'household_record', 3, '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(122, 30, 104, 'household_record', 3, '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(123, 30, 105, 'household_record', 3, '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(124, 30, 106, 'household_record', 3, '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(125, 30, 107, 'household_record', 3, '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(126, 30, 108, 'household_record', 3, '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(127, 30, 109, 'household_record', 3, '2025-11-23 08:41:02', '2025-11-23 08:41:02'),
(128, 21, 52, 'child_record', 3, '2025-11-23 08:49:04', '2025-11-23 08:49:04'),
(131, 21, 52, 'child_record', 3, '2025-11-23 09:53:16', '2025-11-23 09:53:16'),
(132, 67, 207, NULL, NULL, '2025-11-23 11:21:28', '2025-11-23 11:21:28'),
(133, 68, 208, NULL, NULL, '2025-11-23 12:16:35', '2025-11-23 12:16:35'),
(134, 69, 209, NULL, NULL, '2025-11-23 12:17:42', '2025-11-23 12:17:42'),
(135, 70, 210, NULL, NULL, '2025-11-23 12:19:29', '2025-11-23 12:19:29'),
(136, 71, 211, NULL, NULL, '2025-11-23 12:20:36', '2025-11-23 12:20:36'),
(137, 72, 212, NULL, NULL, '2025-11-23 12:21:40', '2025-11-23 12:21:40'),
(138, 73, 213, NULL, NULL, '2025-11-23 12:22:43', '2025-11-23 12:22:43'),
(139, 74, 214, NULL, NULL, '2025-11-23 12:26:50', '2025-11-23 12:26:50'),
(140, 75, 215, NULL, NULL, '2025-11-23 12:27:48', '2025-11-23 12:27:48'),
(141, 76, 216, NULL, NULL, '2025-11-23 12:29:04', '2025-11-23 12:29:04'),
(142, 77, 217, NULL, NULL, '2025-11-23 12:29:57', '2025-11-23 12:29:57'),
(143, 78, 218, NULL, NULL, '2025-11-23 12:31:11', '2025-11-23 12:31:11'),
(144, 79, 219, NULL, NULL, '2025-11-23 13:00:32', '2025-11-23 13:00:32'),
(145, 80, 220, NULL, NULL, '2025-11-23 13:03:50', '2025-11-23 13:03:50'),
(146, 81, 221, NULL, NULL, '2025-11-23 13:05:08', '2025-11-23 13:05:08'),
(147, 82, 222, NULL, NULL, '2025-11-23 13:07:09', '2025-11-23 13:07:09'),
(148, 83, 223, NULL, NULL, '2025-11-23 13:09:38', '2025-11-23 13:09:38'),
(149, 84, 224, NULL, NULL, '2025-11-23 13:12:02', '2025-11-23 13:12:02'),
(150, 85, 225, NULL, NULL, '2025-11-23 13:14:22', '2025-11-23 13:14:22'),
(151, 86, 226, NULL, NULL, '2025-11-23 13:25:33', '2025-11-23 13:25:33'),
(152, 87, 227, NULL, NULL, '2025-11-23 13:26:31', '2025-11-23 13:26:31'),
(153, 88, 228, NULL, NULL, '2025-11-23 13:28:51', '2025-11-23 13:28:51'),
(154, 89, 229, NULL, NULL, '2025-11-23 13:33:47', '2025-11-23 13:33:47'),
(155, 90, 230, NULL, NULL, '2025-11-23 13:34:50', '2025-11-23 13:34:50'),
(156, 91, 231, NULL, NULL, '2025-11-23 13:36:15', '2025-11-23 13:36:15'),
(157, 92, 232, NULL, NULL, '2025-11-23 13:37:22', '2025-11-23 13:37:22'),
(158, 93, 233, NULL, NULL, '2025-11-24 05:00:59', '2025-11-24 05:00:59'),
(159, 94, 234, NULL, NULL, '2025-11-24 05:03:36', '2025-11-24 05:03:36'),
(160, 95, 235, NULL, NULL, '2025-11-24 05:07:14', '2025-11-24 05:07:14'),
(161, 96, 236, NULL, NULL, '2025-11-24 05:13:50', '2025-11-24 05:13:50'),
(162, 97, 237, NULL, NULL, '2025-11-24 05:22:42', '2025-11-24 05:22:42'),
(163, 98, 238, NULL, NULL, '2025-11-24 05:40:43', '2025-11-24 05:40:43'),
(164, 99, 239, NULL, NULL, '2025-11-24 05:44:51', '2025-11-24 05:44:51'),
(165, 100, 240, NULL, NULL, '2025-11-24 06:58:25', '2025-11-24 06:58:25'),
(166, 101, 241, NULL, NULL, '2025-11-24 06:59:49', '2025-11-24 06:59:49'),
(167, 102, 242, NULL, NULL, '2025-11-24 07:00:47', '2025-11-24 07:00:47'),
(168, 103, 243, NULL, NULL, '2025-11-24 07:01:47', '2025-11-24 07:01:47'),
(169, 104, 244, NULL, NULL, '2025-11-24 07:02:55', '2025-11-24 07:02:55'),
(170, 105, 245, NULL, NULL, '2025-11-24 07:04:01', '2025-11-24 07:04:01'),
(171, 106, 246, NULL, NULL, '2025-11-24 07:05:53', '2025-11-24 07:05:53'),
(172, 107, 247, NULL, NULL, '2025-11-24 07:07:27', '2025-11-24 07:07:27'),
(173, 108, 248, NULL, NULL, '2025-11-24 07:09:35', '2025-11-24 07:09:35'),
(174, 109, 249, NULL, NULL, '2025-11-24 07:11:20', '2025-11-24 07:11:20'),
(175, 110, 250, NULL, NULL, '2025-11-24 07:12:22', '2025-11-24 07:12:22'),
(176, 111, 251, NULL, NULL, '2025-11-24 07:24:04', '2025-11-24 07:24:04'),
(177, 112, 252, NULL, NULL, '2025-11-24 07:25:27', '2025-11-24 07:25:27'),
(178, 113, 253, NULL, NULL, '2025-11-24 07:28:23', '2025-11-24 07:28:23'),
(179, 114, 254, NULL, NULL, '2025-11-24 12:52:07', '2025-11-24 12:52:07'),
(180, 115, 255, NULL, NULL, '2025-11-24 12:58:54', '2025-11-24 12:58:54'),
(181, 116, 256, NULL, NULL, '2025-11-24 13:01:37', '2025-11-24 13:01:37'),
(182, 117, 257, NULL, NULL, '2025-11-24 13:02:47', '2025-11-24 13:02:47'),
(183, 118, 258, NULL, NULL, '2025-11-24 13:05:49', '2025-11-24 13:05:49'),
(184, 119, 259, NULL, NULL, '2025-11-24 13:09:46', '2025-11-24 13:09:46'),
(185, 120, 260, NULL, NULL, '2025-11-24 13:10:45', '2025-11-24 13:10:45'),
(186, 121, 261, NULL, NULL, '2025-11-24 13:11:42', '2025-11-24 13:11:42'),
(187, 122, 266, NULL, NULL, '2025-11-25 12:24:28', '2025-11-25 12:24:28'),
(188, 123, 267, NULL, NULL, '2025-11-25 12:27:33', '2025-11-25 12:27:33'),
(189, 124, 268, NULL, NULL, '2025-11-25 12:31:25', '2025-11-25 12:31:25'),
(190, 125, 269, NULL, NULL, '2025-11-25 12:32:39', '2025-11-25 12:32:39'),
(191, 126, 270, NULL, NULL, '2025-11-25 12:34:31', '2025-11-25 12:34:31'),
(192, 127, 271, NULL, NULL, '2025-11-25 12:36:07', '2025-11-25 12:36:07'),
(193, 128, 272, NULL, NULL, '2025-11-25 12:45:12', '2025-11-25 12:45:12'),
(194, 129, 273, NULL, NULL, '2025-11-25 12:48:16', '2025-11-25 12:48:16'),
(195, 130, 274, NULL, NULL, '2025-11-25 12:49:07', '2025-11-25 12:49:07'),
(196, 131, 275, NULL, NULL, '2025-11-25 12:49:59', '2025-11-25 12:49:59'),
(197, 132, 276, NULL, NULL, '2025-11-25 12:53:42', '2025-11-25 12:53:42'),
(198, 133, 277, NULL, NULL, '2025-11-25 13:02:37', '2025-11-25 13:02:37'),
(199, 134, 278, NULL, NULL, '2025-11-25 13:06:32', '2025-11-25 13:06:32'),
(200, 135, 279, NULL, NULL, '2025-11-25 13:07:20', '2025-11-25 13:07:20'),
(201, 136, 280, NULL, NULL, '2025-11-25 13:12:01', '2025-11-25 13:12:01'),
(202, 137, 281, NULL, NULL, '2025-11-25 13:18:16', '2025-11-25 13:18:16'),
(203, 138, 282, NULL, NULL, '2025-11-25 13:19:54', '2025-11-25 13:19:54'),
(204, 139, 283, NULL, NULL, '2025-11-25 13:20:51', '2025-11-25 13:20:51'),
(205, 140, 284, NULL, NULL, '2025-11-25 13:21:51', '2025-11-25 13:21:51'),
(206, 141, 285, NULL, NULL, '2025-11-25 13:22:33', '2025-11-25 13:22:33'),
(207, 142, 286, NULL, NULL, '2025-11-25 13:23:18', '2025-11-25 13:23:18'),
(208, 143, 287, NULL, NULL, '2025-11-25 13:24:06', '2025-11-25 13:24:06'),
(209, 144, 288, NULL, NULL, '2025-11-25 13:25:51', '2025-11-25 13:25:51'),
(210, 145, 289, NULL, NULL, '2025-11-25 13:28:00', '2025-11-25 13:28:00'),
(211, 146, 290, NULL, NULL, '2025-11-25 13:28:52', '2025-11-25 13:28:52'),
(212, 147, 291, NULL, NULL, '2025-11-25 13:29:53', '2025-11-25 13:29:53'),
(213, 148, 292, NULL, NULL, '2025-11-25 13:33:55', '2025-11-25 13:33:55'),
(214, 149, 293, NULL, NULL, '2025-11-25 13:35:13', '2025-11-25 13:35:13'),
(215, 150, 294, NULL, NULL, '2025-11-25 13:36:03', '2025-11-25 13:36:03'),
(216, 151, 295, NULL, NULL, '2025-11-27 02:54:19', '2025-11-27 02:54:19'),
(217, 152, 296, NULL, NULL, '2025-11-27 02:55:51', '2025-11-27 02:55:51'),
(218, 153, 297, NULL, NULL, '2025-11-27 03:04:52', '2025-11-27 03:04:52'),
(220, 155, 301, NULL, NULL, '2025-11-27 03:15:29', '2025-11-27 03:15:29'),
(221, 156, 304, NULL, NULL, '2025-11-27 03:22:52', '2025-11-27 03:22:52'),
(222, 157, 306, NULL, NULL, '2025-11-27 03:25:50', '2025-11-27 03:25:50'),
(223, 158, 309, NULL, NULL, '2025-11-27 03:31:14', '2025-11-27 03:31:14'),
(224, 159, 311, NULL, NULL, '2025-11-27 03:34:23', '2025-11-27 03:34:23'),
(225, 61, 200, 'household_record', 4, '2025-11-27 03:49:39', '2025-11-27 03:49:39'),
(226, 62, 201, 'household_record', 4, '2025-11-27 03:49:51', '2025-11-27 03:49:51'),
(227, 63, 202, 'household_record', 4, '2025-11-27 03:50:33', '2025-11-27 03:50:33'),
(228, 64, 203, 'household_record', 4, '2025-11-27 03:50:48', '2025-11-27 03:50:48'),
(229, 64, 262, 'household_record', 4, '2025-11-27 03:50:48', '2025-11-27 03:50:48'),
(230, 64, 263, 'household_record', 4, '2025-11-27 03:50:48', '2025-11-27 03:50:48'),
(231, 64, 264, 'household_record', 4, '2025-11-27 03:50:48', '2025-11-27 03:50:48'),
(232, 65, 204, 'household_record', 4, '2025-11-27 03:51:13', '2025-11-27 03:51:13'),
(233, 65, 265, 'household_record', 4, '2025-11-27 03:51:13', '2025-11-27 03:51:13'),
(234, 66, 205, 'household_record', 4, '2025-11-27 03:55:39', '2025-11-27 03:55:39'),
(235, 67, 207, 'household_record', 4, '2025-11-27 03:55:56', '2025-11-27 03:55:56'),
(236, 68, 208, 'household_record', 4, '2025-11-27 03:56:19', '2025-11-27 03:56:19'),
(237, 69, 209, 'household_record', 4, '2025-11-27 03:56:52', '2025-11-27 03:56:52'),
(238, 70, 210, 'household_record', 4, '2025-11-27 03:57:28', '2025-11-27 03:57:28'),
(239, 71, 211, 'household_record', 4, '2025-11-27 04:00:25', '2025-11-27 04:00:25'),
(240, 72, 212, 'household_record', 4, '2025-11-27 04:00:51', '2025-11-27 04:00:51'),
(241, 73, 213, 'household_record', 4, '2025-11-27 04:01:15', '2025-11-27 04:01:15'),
(242, 74, 214, 'household_record', 4, '2025-11-27 04:02:45', '2025-11-27 04:02:45'),
(243, 75, 215, 'household_record', 4, '2025-11-27 04:04:06', '2025-11-27 04:04:06'),
(244, 76, 216, 'household_record', 4, '2025-11-27 04:05:57', '2025-11-27 04:05:57'),
(245, 77, 217, 'household_record', 4, '2025-11-27 04:09:59', '2025-11-27 04:09:59'),
(246, 22, 57, 'child_record', 3, '2025-11-27 04:23:44', '2025-11-27 04:23:44'),
(247, 24, 74, 'child_record', 3, '2025-11-27 04:24:22', '2025-11-27 04:24:22'),
(248, 78, 218, 'household_record', 4, '2025-11-27 04:25:22', '2025-11-27 04:25:22'),
(249, 25, 78, 'child_record', 3, '2025-11-27 04:25:38', '2025-11-27 04:25:38'),
(250, 79, 219, 'household_record', 4, '2025-11-27 04:25:44', '2025-11-27 04:25:44'),
(251, 80, 220, 'household_record', 4, '2025-11-27 04:26:18', '2025-11-27 04:26:18'),
(252, 81, 221, 'household_record', 4, '2025-11-27 04:26:38', '2025-11-27 04:26:38'),
(253, 82, 222, 'household_record', 4, '2025-11-27 04:26:53', '2025-11-27 04:26:53'),
(254, 83, 223, 'household_record', 4, '2025-11-27 04:27:22', '2025-11-27 04:27:22'),
(255, 84, 224, 'household_record', 4, '2025-11-27 04:27:39', '2025-11-27 04:27:39'),
(256, 88, 228, 'household_record', 4, '2025-11-27 04:28:00', '2025-11-27 04:28:00'),
(257, 85, 225, 'household_record', 4, '2025-11-27 04:28:20', '2025-11-27 04:28:20'),
(258, 86, 226, 'household_record', 4, '2025-11-27 04:30:56', '2025-11-27 04:30:56'),
(259, 87, 227, 'household_record', 4, '2025-11-27 04:30:59', '2025-11-27 04:30:59'),
(260, 89, 229, 'household_record', 4, '2025-11-27 04:31:01', '2025-11-27 04:31:01'),
(261, 90, 230, 'household_record', 4, '2025-11-27 04:31:03', '2025-11-27 04:31:03'),
(262, 91, 231, 'household_record', 4, '2025-11-27 04:31:07', '2025-11-27 04:31:07'),
(263, 92, 232, 'household_record', 4, '2025-11-27 04:31:33', '2025-11-27 04:31:33'),
(264, 23, 65, 'senior_record.medication', 3, '2025-11-27 04:38:58', '2025-11-27 04:38:58'),
(265, 24, 67, 'senior_record.medication', 3, '2025-11-27 04:39:46', '2025-11-27 04:39:46'),
(266, 24, 67, 'senior_record.medication', 3, '2025-11-27 04:45:51', '2025-11-27 04:45:51'),
(267, 26, 85, 'senior_record.medication', 3, '2025-11-27 04:46:30', '2025-11-27 04:46:30'),
(268, 31, 110, 'household_record', 5, '2025-11-27 04:49:20', '2025-11-27 04:49:20'),
(269, 31, 111, 'household_record', 5, '2025-11-27 04:49:20', '2025-11-27 04:49:20'),
(270, 31, 112, 'household_record', 5, '2025-11-27 04:49:20', '2025-11-27 04:49:20'),
(271, 100, 240, 'household_record', 6, '2025-11-27 04:52:47', '2025-11-27 04:52:47'),
(272, 101, 241, 'household_record', 6, '2025-11-27 04:52:49', '2025-11-27 04:52:49'),
(273, 117, 257, 'household_record', 6, '2025-11-27 04:52:51', '2025-11-27 04:52:51'),
(274, 118, 258, 'household_record', 6, '2025-11-27 04:52:53', '2025-11-27 04:52:53'),
(275, 32, 113, 'household_record', 5, '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(276, 32, 114, 'household_record', 5, '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(277, 32, 115, 'household_record', 5, '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(278, 32, 116, 'household_record', 5, '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(279, 32, 117, 'household_record', 5, '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(280, 32, 118, 'household_record', 5, '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(281, 32, 119, 'household_record', 5, '2025-11-27 04:52:54', '2025-11-27 04:52:54'),
(282, 120, 260, 'household_record', 6, '2025-11-27 04:52:55', '2025-11-27 04:52:55'),
(283, 33, 120, 'household_record', 5, '2025-11-27 04:54:03', '2025-11-27 04:54:03'),
(284, 53, 162, 'household_record', 5, '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(285, 53, 163, 'household_record', 5, '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(286, 53, 164, 'household_record', 5, '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(287, 53, 165, 'household_record', 5, '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(288, 53, 166, 'household_record', 5, '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(289, 53, 167, 'household_record', 5, '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(290, 53, 168, 'household_record', 5, '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(291, 53, 169, 'household_record', 5, '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(292, 53, 170, 'household_record', 5, '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(293, 53, 171, 'household_record', 5, '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(294, 53, 172, 'household_record', 5, '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(295, 53, 173, 'household_record', 5, '2025-11-27 05:06:32', '2025-11-27 05:06:32'),
(296, 113, 253, 'household_record', 6, '2025-11-27 05:08:01', '2025-11-27 05:08:01'),
(297, 114, 254, 'household_record', 6, '2025-11-27 05:09:03', '2025-11-27 05:09:03'),
(298, 107, 247, 'household_record', 6, '2025-11-27 05:09:29', '2025-11-27 05:09:29'),
(299, 106, 246, 'household_record', 6, '2025-11-27 05:09:38', '2025-11-27 05:09:38'),
(300, 151, 295, 'household_record', 5, '2025-11-27 05:09:55', '2025-11-27 05:09:55'),
(301, 115, 255, 'household_record', 6, '2025-11-27 05:10:09', '2025-11-27 05:10:09'),
(302, 152, 296, 'household_record', 5, '2025-11-27 05:11:10', '2025-11-27 05:11:10'),
(303, 160, 314, NULL, NULL, '2025-11-27 05:11:23', '2025-11-27 05:11:23'),
(304, 55, 176, 'household_record', 6, '2025-11-27 05:11:30', '2025-11-27 05:11:30'),
(305, 55, 177, 'household_record', 6, '2025-11-27 05:11:30', '2025-11-27 05:11:30'),
(306, 153, 297, 'household_record', 5, '2025-11-27 05:11:37', '2025-11-27 05:11:37'),
(307, 102, 242, 'household_record', 6, '2025-11-27 05:11:43', '2025-11-27 05:11:43'),
(308, 103, 243, 'household_record', 6, '2025-11-27 05:11:53', '2025-11-27 05:11:53'),
(309, 104, 244, 'household_record', 6, '2025-11-27 05:12:06', '2025-11-27 05:12:06'),
(310, 105, 245, 'household_record', 6, '2025-11-27 05:12:17', '2025-11-27 05:12:17'),
(314, 109, 249, 'household_record', 6, '2025-11-27 05:13:41', '2025-11-27 05:13:41'),
(315, 108, 248, 'household_record', 6, '2025-11-27 05:13:48', '2025-11-27 05:13:48'),
(316, 111, 251, 'household_record', 6, '2025-11-27 05:13:55', '2025-11-27 05:13:55'),
(317, 155, 301, 'household_record', 5, '2025-11-27 05:13:58', '2025-11-27 05:13:58'),
(318, 155, 302, 'household_record', 5, '2025-11-27 05:13:58', '2025-11-27 05:13:58'),
(319, 155, 303, 'household_record', 5, '2025-11-27 05:13:58', '2025-11-27 05:13:58'),
(320, 112, 252, 'household_record', 6, '2025-11-27 05:14:07', '2025-11-27 05:14:07'),
(321, 156, 304, 'household_record', 5, '2025-11-27 05:14:58', '2025-11-27 05:14:58'),
(322, 156, 305, 'household_record', 5, '2025-11-27 05:14:58', '2025-11-27 05:14:58'),
(323, 119, 259, 'household_record', 6, '2025-11-27 05:15:03', '2025-11-27 05:15:03'),
(324, 110, 250, 'household_record', 6, '2025-11-27 05:15:15', '2025-11-27 05:15:15'),
(325, 116, 256, 'household_record', 6, '2025-11-27 05:15:28', '2025-11-27 05:15:28'),
(326, 157, 306, 'household_record', 5, '2025-11-27 05:15:52', '2025-11-27 05:15:52'),
(327, 157, 307, 'household_record', 5, '2025-11-27 05:15:52', '2025-11-27 05:15:52'),
(328, 157, 308, 'household_record', 5, '2025-11-27 05:15:52', '2025-11-27 05:15:52'),
(329, 158, 309, 'household_record', 5, '2025-11-27 05:16:14', '2025-11-27 05:16:14'),
(330, 158, 310, 'household_record', 5, '2025-11-27 05:16:14', '2025-11-27 05:16:14'),
(331, 121, 261, 'household_record', 6, '2025-11-27 05:16:34', '2025-11-27 05:16:34'),
(332, 159, 311, 'household_record', 5, '2025-11-27 05:16:37', '2025-11-27 05:16:37'),
(333, 159, 312, 'household_record', 5, '2025-11-27 05:16:37', '2025-11-27 05:16:37'),
(334, 159, 313, 'household_record', 5, '2025-11-27 05:16:37', '2025-11-27 05:16:37'),
(335, 54, 174, 'household_record', 10, '2025-11-27 05:18:05', '2025-11-27 05:18:05'),
(336, 54, 175, 'household_record', 10, '2025-11-27 05:18:05', '2025-11-27 05:18:05'),
(337, 56, 178, 'household_record', 10, '2025-11-27 05:18:27', '2025-11-27 05:18:27'),
(338, 56, 179, 'household_record', 10, '2025-11-27 05:18:27', '2025-11-27 05:18:27'),
(339, 56, 180, 'household_record', 10, '2025-11-27 05:18:27', '2025-11-27 05:18:27'),
(340, 56, 181, 'household_record', 10, '2025-11-27 05:18:27', '2025-11-27 05:18:27'),
(342, 34, 121, 'household_record', 8, '2025-11-27 05:18:46', '2025-11-27 05:18:46'),
(343, 57, 182, 'household_record', 10, '2025-11-27 05:18:53', '2025-11-27 05:18:53'),
(344, 57, 183, 'household_record', 10, '2025-11-27 05:18:53', '2025-11-27 05:18:53'),
(345, 57, 184, 'household_record', 10, '2025-11-27 05:18:53', '2025-11-27 05:18:53'),
(346, 57, 185, 'household_record', 10, '2025-11-27 05:18:53', '2025-11-27 05:18:53'),
(347, 35, 122, 'household_record', 8, '2025-11-27 05:19:04', '2025-11-27 05:19:04'),
(348, 35, 123, 'household_record', 8, '2025-11-27 05:19:04', '2025-11-27 05:19:04'),
(349, 35, 124, 'household_record', 8, '2025-11-27 05:19:04', '2025-11-27 05:19:04'),
(350, 35, 125, 'household_record', 8, '2025-11-27 05:19:04', '2025-11-27 05:19:04'),
(351, 36, 126, 'household_record', 8, '2025-11-27 05:19:28', '2025-11-27 05:19:28'),
(352, 36, 127, 'household_record', 8, '2025-11-27 05:19:28', '2025-11-27 05:19:28'),
(353, 58, 186, 'household_record', 10, '2025-11-27 05:19:34', '2025-11-27 05:19:34'),
(354, 58, 187, 'household_record', 10, '2025-11-27 05:19:34', '2025-11-27 05:19:34'),
(355, 58, 188, 'household_record', 10, '2025-11-27 05:19:34', '2025-11-27 05:19:34'),
(356, 58, 189, 'household_record', 10, '2025-11-27 05:19:34', '2025-11-27 05:19:34'),
(357, 58, 190, 'household_record', 10, '2025-11-27 05:19:34', '2025-11-27 05:19:34'),
(358, 58, 191, 'household_record', 10, '2025-11-27 05:19:34', '2025-11-27 05:19:34'),
(359, 37, 128, 'household_record', 8, '2025-11-27 05:19:48', '2025-11-27 05:19:48'),
(360, 37, 129, 'household_record', 8, '2025-11-27 05:19:48', '2025-11-27 05:19:48'),
(361, 37, 130, 'household_record', 8, '2025-11-27 05:19:48', '2025-11-27 05:19:48'),
(362, 38, 131, 'household_record', 8, '2025-11-27 05:20:12', '2025-11-27 05:20:12'),
(363, 38, 132, 'household_record', 8, '2025-11-27 05:20:12', '2025-11-27 05:20:12'),
(364, 38, 133, 'household_record', 8, '2025-11-27 05:20:12', '2025-11-27 05:20:12'),
(365, 38, 134, 'household_record', 8, '2025-11-27 05:20:12', '2025-11-27 05:20:12'),
(366, 39, 135, 'household_record', 8, '2025-11-27 05:21:11', '2025-11-27 05:21:11'),
(367, 39, 136, 'household_record', 8, '2025-11-27 05:21:11', '2025-11-27 05:21:11'),
(368, 39, 137, 'household_record', 8, '2025-11-27 05:21:11', '2025-11-27 05:21:11'),
(369, 59, 192, 'household_record', 10, '2025-11-27 05:21:39', '2025-11-27 05:21:39'),
(370, 59, 193, 'household_record', 10, '2025-11-27 05:21:39', '2025-11-27 05:21:39'),
(371, 59, 194, 'household_record', 10, '2025-11-27 05:21:39', '2025-11-27 05:21:39'),
(372, 59, 195, 'household_record', 10, '2025-11-27 05:21:39', '2025-11-27 05:21:39'),
(373, 40, 138, 'household_record', 8, '2025-11-27 05:21:51', '2025-11-27 05:21:51'),
(374, 60, 196, 'household_record', 10, '2025-11-27 05:21:58', '2025-11-27 05:21:58'),
(375, 60, 197, 'household_record', 10, '2025-11-27 05:21:58', '2025-11-27 05:21:58'),
(376, 60, 198, 'household_record', 10, '2025-11-27 05:21:58', '2025-11-27 05:21:58'),
(377, 60, 199, 'household_record', 10, '2025-11-27 05:21:58', '2025-11-27 05:21:58'),
(378, 93, 233, 'household_record', 10, '2025-11-27 05:22:16', '2025-11-27 05:22:16'),
(379, 41, 139, 'household_record', 8, '2025-11-27 05:22:19', '2025-11-27 05:22:19'),
(380, 41, 140, 'household_record', 8, '2025-11-27 05:22:19', '2025-11-27 05:22:19'),
(381, 41, 141, 'household_record', 8, '2025-11-27 05:22:19', '2025-11-27 05:22:19'),
(382, 42, 142, 'household_record', 8, '2025-11-27 05:22:48', '2025-11-27 05:22:48'),
(383, 42, 143, 'household_record', 8, '2025-11-27 05:22:48', '2025-11-27 05:22:48'),
(384, 94, 234, 'household_record', 10, '2025-11-27 05:23:20', '2025-11-27 05:23:20'),
(385, 49, 150, 'household_record', 8, '2025-11-27 05:23:26', '2025-11-27 05:23:26'),
(386, 95, 235, 'household_record', 10, '2025-11-27 05:23:39', '2025-11-27 05:23:39'),
(387, 50, 151, 'household_record', 8, '2025-11-27 05:24:40', '2025-11-27 05:24:40'),
(388, 50, 153, 'household_record', 8, '2025-11-27 05:24:40', '2025-11-27 05:24:40'),
(389, 50, 154, 'household_record', 8, '2025-11-27 05:24:40', '2025-11-27 05:24:40'),
(390, 162, 320, NULL, NULL, '2025-11-27 05:24:50', '2025-11-27 05:24:50'),
(391, 51, 155, 'household_record', 8, '2025-11-27 05:25:01', '2025-11-27 05:25:01'),
(392, 51, 156, 'household_record', 8, '2025-11-27 05:25:01', '2025-11-27 05:25:01'),
(393, 51, 157, 'household_record', 8, '2025-11-27 05:25:01', '2025-11-27 05:25:01'),
(394, 52, 158, 'household_record', 8, '2025-11-27 05:25:24', '2025-11-27 05:25:24'),
(395, 52, 159, 'household_record', 8, '2025-11-27 05:25:24', '2025-11-27 05:25:24'),
(396, 52, 160, 'household_record', 8, '2025-11-27 05:25:24', '2025-11-27 05:25:24'),
(397, 52, 161, 'household_record', 8, '2025-11-27 05:25:24', '2025-11-27 05:25:24'),
(398, 99, 239, 'household_record', 10, '2025-11-27 05:25:35', '2025-11-27 05:25:35'),
(399, 122, 266, 'household_record', 9, '2025-11-27 05:33:13', '2025-11-27 05:33:13'),
(400, 123, 267, 'household_record', 9, '2025-11-27 05:33:31', '2025-11-27 05:33:31'),
(401, 124, 268, 'household_record', 9, '2025-11-27 05:34:20', '2025-11-27 05:34:20'),
(402, 125, 269, 'household_record', 9, '2025-11-27 05:34:49', '2025-11-27 05:34:49'),
(403, 163, 323, NULL, NULL, '2025-11-27 05:35:15', '2025-11-27 05:35:15'),
(404, 126, 270, 'household_record', 9, '2025-11-27 05:35:23', '2025-11-27 05:35:23'),
(405, 127, 271, 'household_record', 9, '2025-11-27 05:35:42', '2025-11-27 05:35:42'),
(406, 128, 272, 'household_record', 9, '2025-11-27 05:36:15', '2025-11-27 05:36:15'),
(407, 129, 273, 'household_record', 9, '2025-11-27 05:37:12', '2025-11-27 05:37:12'),
(408, 130, 274, 'household_record', 9, '2025-11-27 05:37:37', '2025-11-27 05:37:37'),
(409, 131, 275, 'household_record', 9, '2025-11-27 05:38:09', '2025-11-27 05:38:09'),
(410, 132, 276, 'household_record', 9, '2025-11-27 05:38:48', '2025-11-27 05:38:48'),
(411, 133, 277, 'household_record', 9, '2025-11-27 05:39:18', '2025-11-27 05:39:18'),
(412, 134, 278, 'household_record', 9, '2025-11-27 05:39:48', '2025-11-27 05:39:48'),
(413, 135, 279, 'household_record', 9, '2025-11-27 05:40:12', '2025-11-27 05:40:12'),
(414, 136, 280, 'household_record', 9, '2025-11-27 05:40:45', '2025-11-27 05:40:45'),
(415, 137, 281, 'household_record', 9, '2025-11-27 05:41:30', '2025-11-27 05:41:30'),
(416, 138, 282, 'household_record', 9, '2025-11-27 05:42:00', '2025-11-27 05:42:00'),
(417, 139, 283, 'household_record', 9, '2025-11-27 05:42:30', '2025-11-27 05:42:30'),
(418, 140, 284, 'household_record', 9, '2025-11-27 05:47:55', '2025-11-27 05:47:55'),
(419, 141, 285, 'household_record', 9, '2025-11-27 05:48:31', '2025-11-27 05:48:31'),
(420, 142, 286, 'household_record', 9, '2025-11-27 05:49:05', '2025-11-27 05:49:05'),
(421, 143, 287, 'household_record', 9, '2025-11-27 05:49:30', '2025-11-27 05:49:30'),
(422, 144, 288, 'household_record', 9, '2025-11-27 05:49:54', '2025-11-27 05:49:54'),
(423, 145, 289, 'household_record', 9, '2025-11-27 05:51:05', '2025-11-27 05:51:05'),
(424, 164, 327, NULL, NULL, '2025-11-27 05:51:34', '2025-11-27 05:51:34'),
(425, 165, 328, NULL, NULL, '2025-11-27 05:55:19', '2025-11-27 05:55:19'),
(426, 146, 290, 'household_record', 9, '2025-11-27 05:59:50', '2025-11-27 05:59:50'),
(427, 147, 291, 'household_record', 9, '2025-11-27 06:00:57', '2025-11-27 06:00:57'),
(428, 148, 292, 'household_record', 9, '2025-11-27 06:01:41', '2025-11-27 06:01:41'),
(429, 149, 293, 'household_record', 9, '2025-11-27 06:02:14', '2025-11-27 06:02:14'),
(430, 150, 294, 'household_record', 9, '2025-11-27 06:02:47', '2025-11-27 06:02:47'),
(431, 166, 332, NULL, NULL, '2025-11-27 06:03:48', '2025-11-27 06:03:48'),
(432, 167, 336, NULL, NULL, '2025-11-27 06:35:35', '2025-11-27 06:35:35'),
(433, 168, 337, NULL, NULL, '2025-11-27 06:40:57', '2025-11-27 06:40:57'),
(434, 53, 173, 'child_record', 2, '2025-11-27 06:53:35', '2025-11-27 06:53:35'),
(435, 169, 340, NULL, NULL, '2025-11-27 06:54:49', '2025-11-27 06:54:49'),
(436, 53, 172, 'child_record', 2, '2025-11-27 06:55:14', '2025-11-27 06:55:14'),
(437, 21, 53, 'child_record', 2, '2025-11-27 06:57:55', '2025-11-27 06:57:55'),
(438, 170, 343, NULL, NULL, '2025-11-27 06:58:48', '2025-11-27 06:58:48'),
(439, 171, 346, NULL, NULL, '2025-11-27 07:17:59', '2025-11-27 07:17:59'),
(440, 172, 347, NULL, NULL, '2025-11-27 07:19:39', '2025-11-27 07:19:39'),
(441, 173, 349, NULL, NULL, '2025-11-27 07:21:50', '2025-11-27 07:21:50'),
(442, 174, 355, NULL, NULL, '2025-11-27 07:30:00', '2025-11-27 07:30:00'),
(443, 175, 358, NULL, NULL, '2025-11-27 07:32:28', '2025-11-27 07:32:28'),
(444, 176, 360, NULL, NULL, '2025-11-27 07:48:00', '2025-11-27 07:48:00'),
(445, 177, 362, NULL, NULL, '2025-11-27 08:08:55', '2025-11-27 08:08:55'),
(446, 178, 363, NULL, NULL, '2025-11-27 08:10:21', '2025-11-27 08:10:21'),
(447, 179, 368, NULL, NULL, '2025-11-27 08:18:16', '2025-11-27 08:18:16'),
(448, 180, 372, NULL, NULL, '2025-11-27 08:38:35', '2025-11-27 08:38:35'),
(449, 181, 374, NULL, NULL, '2025-11-27 08:42:30', '2025-11-27 08:42:30'),
(450, 182, 376, NULL, NULL, '2025-11-27 08:47:50', '2025-11-27 08:47:50'),
(451, 183, 379, NULL, NULL, '2025-11-27 08:57:35', '2025-11-27 08:57:35'),
(452, 184, 381, NULL, NULL, '2025-11-27 09:01:01', '2025-11-27 09:01:01'),
(453, 12, 14, 'senior_record.medication', 2, '2025-11-27 12:04:08', '2025-11-27 12:04:08'),
(454, 15, 28, 'senior_record.medication', 2, '2025-11-27 12:04:58', '2025-11-27 12:04:58'),
(455, 177, 362, 'senior_record.medication', 4, '2025-11-27 12:05:48', '2025-11-27 12:05:48'),
(456, 181, 374, 'senior_record.medication', 4, '2025-11-27 12:06:03', '2025-11-27 12:06:03'),
(457, 32, 113, 'senior_record.medication', 5, '2025-11-27 12:07:13', '2025-11-27 12:07:13'),
(458, 152, 296, 'senior_record.medication', 5, '2025-11-27 12:07:49', '2025-11-27 12:07:49'),
(459, 155, 301, 'senior_record.medication', 5, '2025-11-27 12:08:08', '2025-11-27 12:08:08'),
(460, 35, 122, 'senior_record.medication', 8, '2025-11-27 12:22:20', '2025-11-27 12:22:20'),
(461, 36, 127, 'senior_record.medication', 8, '2025-11-27 12:28:12', '2025-11-27 12:28:12'),
(462, 40, 138, 'senior_record.medication', 8, '2025-11-27 12:28:33', '2025-11-27 12:28:33'),
(463, 42, 142, 'senior_record.medication', 8, '2025-11-27 12:28:52', '2025-11-27 12:28:52'),
(464, 42, 143, 'senior_record.medication', 8, '2025-11-27 12:29:10', '2025-11-27 12:29:10'),
(465, 175, 358, 'senior_record.medication', 8, '2025-11-27 12:38:26', '2025-11-27 12:38:26'),
(466, 176, 360, 'senior_record.medication', 8, '2025-11-27 12:39:10', '2025-11-27 12:39:10'),
(467, 183, 379, 'senior_record.medication', 10, '2025-11-27 12:45:43', '2025-11-27 12:45:43'),
(468, 27, 90, 'child_record', 3, '2025-11-27 12:51:41', '2025-11-27 12:51:41'),
(469, 28, 96, 'child_record', 3, '2025-11-27 12:52:55', '2025-11-27 12:52:55'),
(470, 30, 107, 'child_record', 3, '2025-11-27 12:53:56', '2025-11-27 12:53:56'),
(471, 30, 108, 'child_record', 3, '2025-11-27 12:54:28', '2025-11-27 12:54:28'),
(472, 30, 109, 'child_record', 3, '2025-11-27 12:55:06', '2025-11-27 12:55:06'),
(473, 28, 206, 'child_record', 3, '2025-11-27 12:55:48', '2025-11-27 12:55:48'),
(474, 164, 327, 'household_record', 2, '2025-11-27 13:03:50', '2025-11-27 13:03:50'),
(475, 163, 323, 'household_record', 2, '2025-11-27 13:04:17', '2025-11-27 13:04:17'),
(476, 50, 154, 'child_record', 8, '2025-11-27 13:08:50', '2025-11-27 13:08:50'),
(477, 58, 191, 'child_record', 10, '2025-11-27 13:12:36', '2025-11-27 13:12:36'),
(478, 11, 11, 'household_record', 7, '2025-11-27 13:32:33', '2025-11-27 13:32:33'),
(479, 11, 12, 'household_record', 7, '2025-11-27 13:32:33', '2025-11-27 13:32:33'),
(480, 11, 13, 'household_record', 7, '2025-11-27 13:32:33', '2025-11-27 13:32:33'),
(481, 12, 14, 'household_record', 7, '2025-11-27 13:32:50', '2025-11-27 13:32:50'),
(482, 12, 15, 'household_record', 7, '2025-11-27 13:32:50', '2025-11-27 13:32:50'),
(483, 12, 16, 'household_record', 7, '2025-11-27 13:32:50', '2025-11-27 13:32:50'),
(484, 13, 17, 'household_record', 7, '2025-11-27 13:33:51', '2025-11-27 13:33:51'),
(485, 13, 18, 'household_record', 7, '2025-11-27 13:33:51', '2025-11-27 13:33:51'),
(486, 13, 19, 'household_record', 7, '2025-11-27 13:33:52', '2025-11-27 13:33:52'),
(487, 13, 20, 'household_record', 7, '2025-11-27 13:33:52', '2025-11-27 13:33:52'),
(488, 13, 21, 'household_record', 7, '2025-11-27 13:33:52', '2025-11-27 13:33:52'),
(489, 13, 22, 'household_record', 7, '2025-11-27 13:33:52', '2025-11-27 13:33:52'),
(490, 13, 23, 'household_record', 7, '2025-11-27 13:33:52', '2025-11-27 13:33:52'),
(491, 14, 24, 'household_record', 7, '2025-11-27 13:34:08', '2025-11-27 13:34:08'),
(492, 14, 25, 'household_record', 7, '2025-11-27 13:34:08', '2025-11-27 13:34:08'),
(493, 14, 26, 'household_record', 7, '2025-11-27 13:34:08', '2025-11-27 13:34:08'),
(494, 14, 27, 'household_record', 7, '2025-11-27 13:34:08', '2025-11-27 13:34:08'),
(495, 15, 28, 'household_record', 7, '2025-11-27 13:34:23', '2025-11-27 13:34:23'),
(496, 15, 29, 'household_record', 7, '2025-11-27 13:34:23', '2025-11-27 13:34:23'),
(497, 15, 30, 'household_record', 7, '2025-11-27 13:34:23', '2025-11-27 13:34:23'),
(498, 15, 31, 'household_record', 7, '2025-11-27 13:34:23', '2025-11-27 13:34:23'),
(499, 16, 32, 'household_record', 7, '2025-11-27 13:34:46', '2025-11-27 13:34:46'),
(500, 16, 33, 'household_record', 7, '2025-11-27 13:34:46', '2025-11-27 13:34:46'),
(501, 16, 34, 'household_record', 7, '2025-11-27 13:34:46', '2025-11-27 13:34:46'),
(502, 16, 35, 'household_record', 7, '2025-11-27 13:34:46', '2025-11-27 13:34:46'),
(503, 17, 36, 'household_record', 7, '2025-11-27 13:35:06', '2025-11-27 13:35:06'),
(504, 17, 37, 'household_record', 7, '2025-11-27 13:35:06', '2025-11-27 13:35:06'),
(505, 17, 38, 'household_record', 7, '2025-11-27 13:35:06', '2025-11-27 13:35:06'),
(506, 17, 39, 'household_record', 7, '2025-11-27 13:35:06', '2025-11-27 13:35:06'),
(507, 17, 40, 'household_record', 7, '2025-11-27 13:35:06', '2025-11-27 13:35:06'),
(508, 18, 41, 'household_record', 7, '2025-11-27 13:35:24', '2025-11-27 13:35:24'),
(509, 18, 42, 'household_record', 7, '2025-11-27 13:35:24', '2025-11-27 13:35:24'),
(510, 18, 43, 'household_record', 7, '2025-11-27 13:35:24', '2025-11-27 13:35:24'),
(511, 19, 44, 'household_record', 7, '2025-11-27 13:35:45', '2025-11-27 13:35:45'),
(512, 19, 45, 'household_record', 7, '2025-11-27 13:35:45', '2025-11-27 13:35:45'),
(513, 19, 46, 'household_record', 7, '2025-11-27 13:35:45', '2025-11-27 13:35:45'),
(514, 19, 47, 'household_record', 7, '2025-11-27 13:35:45', '2025-11-27 13:35:45'),
(515, 185, 385, NULL, NULL, '2025-11-27 13:36:01', '2025-11-27 13:36:01'),
(516, 20, 48, 'household_record', 7, '2025-11-27 13:36:08', '2025-11-27 13:36:08'),
(517, 20, 49, 'household_record', 7, '2025-11-27 13:36:08', '2025-11-27 13:36:08'),
(518, 11, 13, 'child_record', 7, '2025-11-27 13:36:50', '2025-11-27 13:36:50'),
(519, 14, 27, 'child_record', 7, '2025-11-27 13:37:29', '2025-11-27 13:37:29'),
(520, 18, 43, 'child_record', 7, '2025-11-27 13:37:56', '2025-11-27 13:37:56'),
(521, 186, 388, NULL, NULL, '2025-11-27 13:40:12', '2025-11-27 13:40:12'),
(522, 74, 324, 'household_record', 4, '2025-11-27 13:42:28', '2025-11-27 13:42:28'),
(523, 74, 325, 'household_record', 4, '2025-11-27 13:42:28', '2025-11-27 13:42:28'),
(524, 74, 326, 'household_record', 4, '2025-11-27 13:42:28', '2025-11-27 13:42:28'),
(525, 74, 326, 'child_record', 4, '2025-11-27 13:43:03', '2025-11-27 13:43:03'),
(526, 187, 392, NULL, NULL, '2025-11-27 13:46:12', '2025-11-27 13:46:12'),
(527, 115, 396, 'household_record', 6, '2025-11-27 13:50:32', '2025-11-27 13:50:32'),
(528, 115, 396, 'child_record', 6, '2025-11-27 13:51:03', '2025-11-27 13:51:03'),
(529, 171, 346, 'household_record', 9, '2025-11-27 13:53:31', '2025-11-27 13:53:31'),
(530, 171, 348, 'household_record', 9, '2025-11-27 13:53:31', '2025-11-27 13:53:31'),
(531, 171, 350, 'household_record', 9, '2025-11-27 13:53:31', '2025-11-27 13:53:31'),
(532, 171, 351, 'household_record', 9, '2025-11-27 13:53:31', '2025-11-27 13:53:31'),
(533, 171, 353, 'household_record', 9, '2025-11-27 13:53:31', '2025-11-27 13:53:31'),
(534, 171, 353, 'child_record', 9, '2025-11-27 13:53:35', '2025-11-27 13:53:35'),
(535, 186, 388, 'household_record', 6, '2025-11-27 13:55:30', '2025-11-27 13:55:30'),
(536, 186, 389, 'household_record', 6, '2025-11-27 13:55:30', '2025-11-27 13:55:30'),
(537, 186, 390, 'household_record', 6, '2025-11-27 13:55:30', '2025-11-27 13:55:30'),
(538, 186, 391, 'household_record', 6, '2025-11-27 13:55:30', '2025-11-27 13:55:30'),
(539, 107, 247, 'senior_record.medication', 2, '2025-11-27 13:59:13', '2025-11-27 13:59:13'),
(540, 137, 281, 'senior_record.medication', 2, '2025-11-27 14:01:56', '2025-11-27 14:01:56'),
(541, 187, 392, 'household_record', 2, '2025-11-27 14:04:24', '2025-11-27 14:04:24'),
(542, 187, 393, 'household_record', 2, '2025-11-27 14:04:24', '2025-11-27 14:04:24'),
(543, 187, 394, 'household_record', 2, '2025-11-27 14:04:24', '2025-11-27 14:04:24'),
(544, 187, 395, 'household_record', 2, '2025-11-27 14:04:24', '2025-11-27 14:04:24'),
(545, 185, 385, 'household_record', 2, '2025-11-27 14:04:46', '2025-11-27 14:04:46'),
(546, 185, 386, 'household_record', 2, '2025-11-27 14:04:46', '2025-11-27 14:04:46'),
(547, 185, 387, 'household_record', 2, '2025-11-27 14:04:46', '2025-11-27 14:04:46'),
(548, 96, 236, 'household_record', 2, '2025-11-27 14:07:13', '2025-11-27 14:07:13'),
(550, 11, 12, 'family_planning_record', 2, '2025-11-27 14:42:30', '2025-11-27 14:42:30'),
(552, 31, 111, 'family_planning_record', 5, '2025-11-27 15:02:22', '2025-11-27 15:02:22'),
(553, 32, 116, 'family_planning_record', 5, '2025-11-27 15:02:45', '2025-11-27 15:02:45'),
(554, 32, 119, 'family_planning_record', 5, '2025-11-27 15:03:02', '2025-11-27 15:03:02'),
(555, 32, 117, 'family_planning_record', 5, '2025-11-27 15:03:15', '2025-11-27 15:03:15'),
(556, 157, 307, 'family_planning_record', 5, '2025-11-27 15:03:41', '2025-11-27 15:03:41'),
(557, 155, 303, 'family_planning_record', 5, '2025-11-27 15:05:11', '2025-11-27 15:05:11'),
(558, 159, 312, 'family_planning_record', 5, '2025-11-27 15:05:38', '2025-11-27 15:05:38'),
(560, 53, 165, 'family_planning_record', 5, '2025-11-27 15:11:24', '2025-11-27 15:11:24'),
(561, 53, 164, 'family_planning_record', 5, '2025-11-27 15:11:37', '2025-11-27 15:11:37'),
(562, 53, 166, 'family_planning_record', 5, '2025-11-27 15:11:56', '2025-11-27 15:11:56'),
(563, 158, 310, 'family_planning_record', 5, '2025-11-27 15:12:50', '2025-11-27 15:12:50'),
(564, 5, 365, 'family_planning_record', 5, '2025-11-27 15:13:38', '2025-11-27 15:13:38'),
(565, 179, 368, 'family_planning_record', 5, '2025-11-27 15:13:51', '2025-11-27 15:13:51'),
(566, 5, 369, 'family_planning_record', 5, '2025-11-27 15:14:06', '2025-11-27 15:14:06'),
(567, 187, 393, 'family_planning_record', 5, '2025-11-27 15:14:18', '2025-11-27 15:14:18'),
(568, 104, 244, 'family_planning_record', 6, '2025-11-27 15:17:46', '2025-11-27 15:17:46'),
(569, 105, 245, 'family_planning_record', 6, '2025-11-27 15:17:57', '2025-11-27 15:17:57'),
(570, 109, 249, 'family_planning_record', 6, '2025-11-27 15:18:09', '2025-11-27 15:18:09'),
(571, 120, 260, 'family_planning_record', 6, '2025-11-27 15:18:33', '2025-11-27 15:18:33'),
(572, 13, 18, 'family_planning_record', 7, '2025-11-27 15:29:11', '2025-11-27 15:29:11'),
(573, 13, 21, 'family_planning_record', 7, '2025-11-27 15:29:19', '2025-11-27 15:29:19'),
(574, 13, 22, 'family_planning_record', 7, '2025-11-27 15:29:27', '2025-11-27 15:29:27'),
(575, 14, 25, 'family_planning_record', 7, '2025-11-27 15:29:36', '2025-11-27 15:29:36'),
(576, 15, 31, 'family_planning_record', 7, '2025-11-27 15:29:49', '2025-11-27 15:29:49'),
(577, 160, 314, 'household_record', 7, '2025-11-27 15:35:06', '2025-11-27 15:35:06'),
(578, 160, 315, 'household_record', 7, '2025-11-27 15:35:06', '2025-11-27 15:35:06'),
(579, 160, 316, 'household_record', 7, '2025-11-27 15:35:06', '2025-11-27 15:35:06'),
(583, 16, 33, 'family_planning_record', 7, '2025-11-27 15:46:17', '2025-11-27 15:46:17'),
(584, 17, 38, 'family_planning_record', 7, '2025-11-27 15:46:33', '2025-11-27 15:46:33'),
(585, 18, 41, 'family_planning_record', 7, '2025-11-27 15:46:51', '2025-11-27 15:46:51'),
(586, 19, 45, 'family_planning_record', 7, '2025-11-27 15:47:11', '2025-11-27 15:47:11'),
(587, 20, 49, 'family_planning_record', 7, '2025-11-27 15:47:24', '2025-11-27 15:47:24'),
(588, 160, 315, 'family_planning_record', 7, '2025-11-27 15:47:47', '2025-11-27 15:47:47'),
(589, 160, 316, 'family_planning_record', 7, '2025-11-27 15:47:56', '2025-11-27 15:47:56'),
(590, 162, 320, 'family_planning_record', 7, '2025-11-27 15:48:06', '2025-11-27 15:48:06'),
(591, 7, 329, 'family_planning_record', 7, '2025-11-27 15:48:27', '2025-11-27 15:48:27'),
(592, 7, 333, 'family_planning_record', 7, '2025-11-27 15:48:41', '2025-11-27 15:48:41'),
(593, 168, 337, 'family_planning_record', 7, '2025-11-27 15:49:03', '2025-11-27 15:49:03'),
(594, 7, 339, 'family_planning_record', 7, '2025-11-27 15:49:11', '2025-11-27 15:49:11'),
(595, 7, 377, 'family_planning_record', 7, '2025-11-27 15:49:18', '2025-11-27 15:49:18'),
(596, 21, 397, 'child_record.infant_record', 3, '2025-11-27 15:59:10', '2025-11-27 15:59:10'),
(597, 21, 397, 'household_record', 3, '2025-11-27 15:59:38', '2025-11-27 15:59:38'),
(599, 23, 66, 'pregnancy_record.prenatal', 3, '2025-11-27 17:18:54', '2025-11-27 17:18:54'),
(600, 21, 51, 'family_planning_record', 3, '2025-11-27 17:36:16', '2025-11-27 17:36:16'),
(601, 22, 56, 'family_planning_record', 3, '2025-11-27 17:36:25', '2025-11-27 17:36:25'),
(602, 23, 60, 'family_planning_record', 3, '2025-11-27 17:36:36', '2025-11-27 17:36:36'),
(603, 23, 63, 'family_planning_record', 3, '2025-11-27 17:36:44', '2025-11-27 17:36:44'),
(604, 23, 64, 'family_planning_record', 3, '2025-11-27 17:36:51', '2025-11-27 17:36:51'),
(605, 24, 68, 'family_planning_record', 3, '2025-11-27 17:37:02', '2025-11-27 17:37:02'),
(606, 23, 66, 'family_planning_record', 3, '2025-11-27 17:37:30', '2025-11-27 17:37:30'),
(607, 24, 72, 'family_planning_record', 3, '2025-11-27 17:37:46', '2025-11-27 17:37:46'),
(608, 24, 69, 'family_planning_record', 3, '2025-11-27 17:38:02', '2025-11-27 17:38:02'),
(609, 25, 76, 'family_planning_record', 3, '2025-11-27 17:38:45', '2025-11-27 17:38:45'),
(610, 27, 88, 'family_planning_record', 3, '2025-11-27 17:39:05', '2025-11-27 17:39:05'),
(611, 28, 91, 'family_planning_record', 3, '2025-11-27 17:39:33', '2025-11-27 17:39:33'),
(612, 28, 93, 'family_planning_record', 3, '2025-11-27 17:39:46', '2025-11-27 17:39:46'),
(613, 28, 94, 'family_planning_record', 3, '2025-11-27 17:41:45', '2025-11-27 17:41:45'),
(614, 29, 102, 'family_planning_record', 3, '2025-11-27 17:42:05', '2025-11-27 17:42:05'),
(615, 30, 104, 'family_planning_record', 3, '2025-11-27 17:42:16', '2025-11-27 17:42:16'),
(616, 30, 106, 'family_planning_record', 3, '2025-11-27 17:43:43', '2025-11-27 17:43:43'),
(617, 3, 341, 'family_planning_record', 3, '2025-11-27 17:43:56', '2025-11-27 17:43:56'),
(618, 3, 345, 'family_planning_record', 3, '2025-11-27 17:44:05', '2025-11-27 17:44:05'),
(619, 172, 347, 'family_planning_record', 3, '2025-11-27 17:44:14', '2025-11-27 17:44:14'),
(620, 3, 354, 'family_planning_record', 3, '2025-11-27 17:44:30', '2025-11-27 17:44:30'),
(621, 3, 357, 'family_planning_record', 3, '2025-11-27 17:45:03', '2025-11-27 17:45:03');

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`role_id`, `role_name`) VALUES
(1, 'BHW Head'),
(2, 'BHW Staff'),
(3, 'Household User'),
(4, 'Super Admin');

-- --------------------------------------------------------

--
-- Table structure for table `role_privilege`
--

CREATE TABLE `role_privilege` (
  `role_id` int(11) NOT NULL,
  `privilege_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_privilege`
--

INSERT INTO `role_privilege` (`role_id`, `privilege_id`) VALUES
(1, 1),
(2, 1),
(4, 1),
(1, 2),
(2, 2),
(4, 2),
(1, 3),
(2, 3),
(4, 3),
(1, 4),
(2, 4),
(4, 4),
(1, 5),
(2, 5),
(4, 5),
(1, 6),
(2, 6),
(4, 6),
(1, 7),
(2, 7),
(4, 7),
(1, 8),
(4, 8),
(1, 9),
(2, 9),
(3, 9),
(4, 9),
(1, 10),
(2, 10),
(3, 10),
(4, 10),
(1, 11),
(2, 11),
(4, 11),
(1, 12),
(2, 12),
(4, 12),
(3, 13),
(4, 13),
(4, 14),
(4, 15),
(1, 16),
(2, 16),
(4, 16),
(1, 17),
(2, 17),
(4, 17);

-- --------------------------------------------------------

--
-- Table structure for table `senior_medication`
--

CREATE TABLE `senior_medication` (
  `senior_record_id` int(11) NOT NULL,
  `medication_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `senior_medication`
--

INSERT INTO `senior_medication` (`senior_record_id`, `medication_id`) VALUES
(5, 4),
(6, 4),
(7, 4),
(9, 4),
(17, 4),
(21, 4),
(8, 5),
(15, 5),
(1, 7),
(2, 7),
(3, 7),
(10, 7),
(11, 7),
(12, 7),
(14, 7),
(16, 7),
(18, 7),
(20, 7),
(13, 8),
(12, 9),
(19, 9);

-- --------------------------------------------------------

--
-- Table structure for table `senior_record`
--

CREATE TABLE `senior_record` (
  `senior_record_id` int(11) NOT NULL,
  `records_id` int(11) NOT NULL,
  `bp_reading` varchar(20) DEFAULT NULL,
  `bp_date_taken` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `senior_record`
--

INSERT INTO `senior_record` (`senior_record_id`, `records_id`, `bp_reading`, `bp_date_taken`, `created_at`, `updated_at`) VALUES
(1, 264, '120/80', '2025-10-29', '2025-11-27 04:38:58', '2025-11-27 12:43:24'),
(2, 265, '120/80', '2025-09-17', '2025-11-27 04:39:46', '2025-11-27 04:39:46'),
(3, 266, '110/70', '2025-09-17', '2025-11-27 04:45:51', '2025-11-27 04:45:51'),
(4, 267, '109/73', '2025-06-10', '2025-11-27 04:46:30', '2025-11-27 04:46:30'),
(5, 453, '118/74 mmHg', '2024-12-11', '2025-11-27 12:04:08', '2025-11-27 12:04:08'),
(6, 454, '97/63', '2025-09-17', '2025-11-27 12:04:58', '2025-11-27 12:44:07'),
(7, 455, '105/70', '2025-10-17', '2025-11-27 12:05:48', '2025-11-27 12:43:53'),
(8, 456, '113/69', '2025-10-16', '2025-11-27 12:06:03', '2025-11-27 12:43:39'),
(9, 457, '108/72', '2025-10-20', '2025-11-27 12:07:13', '2025-11-27 12:17:47'),
(10, 458, '118/75', '2025-10-20', '2025-11-27 12:07:49', '2025-11-27 12:17:31'),
(11, 459, '112/70', '2025-10-20', '2025-11-27 12:08:08', '2025-11-27 12:17:58'),
(12, 460, '115/73', '2025-10-20', '2025-11-27 12:22:20', '2025-11-27 12:22:20'),
(13, 461, '118/76', '2025-10-20', '2025-11-27 12:28:12', '2025-11-27 12:28:12'),
(14, 462, '124/82', '2025-06-17', '2025-11-27 12:28:33', '2025-11-27 12:28:33'),
(15, 463, '132/84', '2025-10-15', '2025-11-27 12:28:52', '2025-11-27 12:28:52'),
(16, 464, '128/80', '2025-06-18', '2025-11-27 12:29:10', '2025-11-27 12:29:10'),
(17, 465, '135/85', '2025-10-20', '2025-11-27 12:38:26', '2025-11-27 12:38:26'),
(18, 466, '126/78', '2025-10-20', '2025-11-27 12:39:10', '2025-11-27 12:39:10'),
(19, 467, '130/82', '2025-10-21', '2025-11-27 12:45:43', '2025-11-27 12:45:43'),
(20, 539, '138/89', '2025-10-20', '2025-11-27 13:59:13', '2025-11-27 13:59:13'),
(21, 540, '126/78', '2025-10-20', '2025-11-27 14:01:56', '2025-11-27 14:01:56');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role_id`, `email`, `username`, `password`) VALUES
(1, 4, '', 'superadmin', '$2y$10$d/PMFcqS/G6ig27vWKqlT.MBnK7qyVY27nmKLAyfmzvYx8B2z410i'),
(2, 1, '', 'bhwhead', '$2y$10$d/PMFcqS/G6ig27vWKqlT.MBnK7qyVY27nmKLAyfmzvYx8B2z410i'),
(3, 2, '', 'bhwstaff1', '$2y$10$d/PMFcqS/G6ig27vWKqlT.MBnK7qyVY27nmKLAyfmzvYx8B2z410i'),
(4, 2, '', 'bhwstaff2', '$2y$10$d/PMFcqS/G6ig27vWKqlT.MBnK7qyVY27nmKLAyfmzvYx8B2z410i'),
(5, 2, '', 'bhwstaff3', '$2y$10$d/PMFcqS/G6ig27vWKqlT.MBnK7qyVY27nmKLAyfmzvYx8B2z410i'),
(6, 2, '', 'bhwstaff4a', '$2y$10$d/PMFcqS/G6ig27vWKqlT.MBnK7qyVY27nmKLAyfmzvYx8B2z410i'),
(7, 2, '', 'bhwstaff4b', '$2y$10$d/PMFcqS/G6ig27vWKqlT.MBnK7qyVY27nmKLAyfmzvYx8B2z410i'),
(8, 2, '', 'bhwstaff5', '$2y$10$d/PMFcqS/G6ig27vWKqlT.MBnK7qyVY27nmKLAyfmzvYx8B2z410i'),
(9, 2, '', 'bhwstaff6', '$2y$10$d/PMFcqS/G6ig27vWKqlT.MBnK7qyVY27nmKLAyfmzvYx8B2z410i'),
(10, 2, '', 'bhwstaff7', '$2y$10$d/PMFcqS/G6ig27vWKqlT.MBnK7qyVY27nmKLAyfmzvYx8B2z410i'),
(11, 3, 'djdtabuan@gmail.com', 'evelyn', '$2y$10$ydvGp4EI4cAaGtmcsLHhPuf4JBm5QXx4UKMbFrcna.J49VfuXy2qW'),
(12, 3, 'naty.castaneda12@gmail.com', 'natycastaneda', '$2y$10$bABz6whu5UL.6x0QmyfYAuQTQJIeMHMNvW/1Gt4.Euqe3AOVBlGWu'),
(13, 3, 'leonardo.santos13@gmail.com', 'leonardo', '$2y$10$qzAZR1s4CB/mPddqRqfH9.lt8c26pIALq71gpwAO3iWen/jUfPphS'),
(14, 3, 'cerio.salome14@gmail.com', 'ceriosalome', '$2y$10$QGDXou9O4NLPCn1njA6Pde1l9OaxxdDadratjvRAQ2zLroaMsBz4e'),
(15, 3, 'lorenzo.salome15@gmail.com', 'lorenzosalome', '$2y$10$GUYKYYF.cvS6FqTmQzqS3eZnfuy1gFtil9effZ.8VR9hbQmNYmmC6'),
(16, 3, 'dominador.salome16@gmail.com', 'domsalome', '$2y$10$cgjW4r22GpPEbQYynQJWsOp2ykDxgbB7eXKPigEbgpC2w8I5VnbIy'),
(17, 3, 'nelson.feliciano17@gmail.com', 'nelfeliciano', '$2y$10$TalrSB/XT3M6eHEH.myqNuxQAdnmt2zWuWfGCvZhqMyFO8eWoX3eu'),
(18, 3, 'crisanta.pagarigan18@gmail.com', 'mcrizpagarigan', '$2y$10$dwdsk/SntGG0Mb/FP6kcbuHTGojzD4iJHm5hDgAwKpqOKsFrLAFwO'),
(19, 3, 'luigi.feliciano19@gmail.com', 'luigifeliciano', '$2y$10$qFT3F2FaU7EL80STP.iq9.7juxefzc/BhJNazk6.EEkP3M4vHZTYG'),
(20, 3, 'neil.feliciano20@gmail.com', 'neilfeliciano', '$2y$10$wigux7IrDTpOVAJCpT7DAeJRFaRtxYdNTVGMp6lX7FaaF75TqPOCy'),
(21, 3, 'emmanuel.legaspi21@gmail.com', 'emmanlegaspi', '$2y$10$EiWq1Am15fnhDp31lsi55OG5O4nW61.h0bYNjjfCCWFLYYgRNpRdS'),
(22, 3, 'rolly.esteban22@gmail.com', 'rollyesteban', '$2y$10$wB2Ne.9V1IZwmmhwaghWtujKRvfrdG2f.cLclasCSBMkC8FX4CNHe'),
(23, 3, 'benjie.grospe23@gmail.com', 'benjiegrospe', '$2y$10$BVTb3/BV5HZVnQPHND2JC.WiOUK6BChw9Qr2OOKW7/3NiT.EJwdrC'),
(24, 3, 'manuel.perez24@gmail.com', 'manuelperez', '$2y$10$9ZXZkJjl2nQs.N0rY95E9.YiMT4n/XunI0TdO.pGuwc.Ph.T6we/y'),
(25, 3, 'christian.felipe25@gmail.com', 'chrisfelipe', '$2y$10$9O3qGuEthv9lLemrgIgO/.bOmTHOZQ/Em/KEcjZS..HiYVxmcTu.a'),
(26, 3, 'armando.delacruz26@gmail.com', 'armandelacruz', '$2y$10$TLRD/w9RjBVfZhxD55vziettAsyllK.fX0iALDSk6AlMHWjmQHFfW'),
(27, 3, 'carlo.pabilona27@gmail.com', 'carlopabilona', '$2y$10$zkaM5GvyVARgiXcX.0KwXOiC/UbZSVWZ24Ux87EBXfi30R4zmxaxW'),
(28, 3, 'mylene.pabilona28@gmail.com', 'mylenepabilona', '$2y$10$LWWh04tMIVJ4cMBNP9SH/uAvGt2XZMZxD1WEowB47gd.dznDGdCxa'),
(29, 3, 'pio.atienza29@gmail.com', 'pioatienza', '$2y$10$vMC6uVfBdNO/JK.oA0elI.uBeXiSHjLHIIhtmoZwck4QI4Q1fHsHa'),
(30, 3, 'hilda.reyes30@gmail.com', 'hildareyes', '$2y$10$Z2tODbyMOxgciCPNon526eqZe7gOIoDRbueohFoj9D6btXDrHER.W'),
(31, 3, 'johnpaul.andrade31@gmail.com', 'jpandrade', '$2y$10$Z2tODbyMOxgciCPNon526eqZe7gOIoDRbueohFoj9D6btXDrHER.W'),
(32, 3, 'perlita.asuncion32@gmail.com', 'perlitaasuncion', '$2y$10$HfkzAZnn6FiuN8nBe4XJJOSk4duNR6gmhTNGPGZhz1gLAfiG9SPNO'),
(33, 3, 'clarence.asuncion33@gmail.com', 'clarenceasuncion', '$2y$10$x4e6uf/M.I75H4QzzN8ONePuASLp7SaRt1J5Q0J.rLJDsxT0gWhvq'),
(34, 3, 'ester.apostol34@gmail.com', 'Ester', '$2y$10$htawN.oqbR7Gc.nyQhOFrOt4cdc7t0NGVvVXl40HtJlgyxkLJ3mUe'),
(35, 3, 'ranilo.asoy35@gmail.com', 'Ranilo', '$2y$10$QDZeMPE..wprXDKpeIEl9ueL5n0Wkt.wNh.n9G2.T0c3zJvDRPtR.'),
(36, 3, 'paulmichael.agustin36@gmail.com', 'Michael', '$2y$10$.Xtb32LJhe8zgxijenRMvOSvQB3tei9oqA4b2EtIB4YAl6T9mkM52'),
(37, 3, 'ricky.agas37@gmail.com', 'Ricky', '$2y$10$D1VSZgVlDvymPSi.K8rHF.RDYRwHnAsE4Ysn.x.C2CFIG/eJc59.y'),
(38, 3, 'roderick.agas38@gmail.com', 'Roderick ', '$2y$10$3zEZOBG1oeP0KEhg3ejW5eZcJVFUkWIn9RmlQYhUDtIxSaVHIb6oe'),
(39, 3, 'wyler.agustin39@gmail.com', 'Wyler', '$2y$10$LdbIK6T40xP9vEF8U4lQw.SJz98td3pDaldutJEhn.WVoTX1tvKBi'),
(40, 3, 'ernesto.agustin40@gmail.com', 'Ernesto', '$2y$10$1lbJwTU4Sr6nTAJgxmdR1ejipvxEJxa4qCsAwN01ji2.aXGJ/F.jK'),
(41, 3, 'crisanto.abad41@gmail.com', 'Crisanto', '$2y$10$8uwXAF8e6bcKzE20YJ7jEuDoARZ.svCF0tmb4/s9OqatEIo3jxxkC'),
(42, 3, 'conrado.abella42@gmail.com', 'Abella', '$2y$10$tHgTedwLqRWItg.lVdtGTuN338V6wi0rhGDwwVl66f5T.eUoMiAC.'),
(49, 3, 'emmanuel.artetche49@gmail.com', 'emmanartetche', '$2y$10$oHXQG0t/oJ9bkWDnB3/lneuvIR5jSz55otqfzQ.GwCuvqWR31Cri2'),
(50, 3, 'luz.antonio50@gmail.com', 'Antonio', '$2y$10$ke61Zcl3KcalUhW1pfx/ouQtr5romzy3.wf.qdQWyf2BrD9vJWT3W'),
(51, 3, 'suanito.bravo51@gmail.com', 'Suanito', '$2y$10$HlTF92EsF08Y2vwOX7XnSuTWhbfZAYMMMOtAMBm3v96YkTttp2mWu'),
(52, 3, 'ramon.bravo52@gmail.com', 'Ramon', '$2y$10$yB0.a0OWE3lW9haEE.U5B.AwCu1wqA0A8CJpuaM1bwf1/Eu2hd9g6'),
(53, 3, 'joselito.carpio53@gmail.com', 'joselito', '$2y$10$/51qOY/HGXsO1bU9HVNQ5OvsK1BWOUHIEeXcwAan.Oalbye/z5ALa'),
(54, 3, 'rebecca.briones54@gmail.com', 'Rebecca', '$2y$10$huDQh/c4jx3kVfLq8E7Su.EnpEU4rbkUkIsxFx080WXStx4AffbDi'),
(55, 3, 'rudy.ragos55@gmail.com', 'rudyy', '$2y$10$RW5rh0sQXsB0APEmfzlgre3bktcfKcK6Msz1GSb7i9Bz4oNJ3/NOO'),
(56, 3, 'jose.rafael56@gmail.com', 'josee', '$2y$10$cWUKbOkn8eJ3QBt/09YvBujW/0juljNKFVek1urgoDoMO6BleigDu'),
(57, 3, 'jonel.bensan57@gmail.com', 'jonel', '$2y$10$aBRrQ7YsyPHBmaGreiN9VO5WabrHqzdmiGd4DNQ7qis.u0Spzn5eO'),
(58, 3, 'marcelo.apostol58@gmail.com', 'marcelo', '$2y$10$oW1B2jyGmTylizk2B9wG1ul.q6q/TCOOAj6ZDczeHc78WipNiBWUW'),
(59, 3, 'ronaldo.luzano59@gmail.com', 'ronaldo', '$2y$10$yqOr6UmdohZDUb28Ib414uRLN0.gWcg0kGN/qx2uH0YPgs0ElP1Nu'),
(60, 3, 'jovita.salcedo60@gmail.com', 'jovita', '$2y$10$B7d10PECnN.W6co21vjH9OQUXnGChThbCdwyHtZTrJK10LD5IgHqW'),
(61, 3, 'janel.agbayani61@gmail.com', 'janell', '$2y$10$nH5/o5irbOo6cTtlHctiXuC9/s0gp3EnnqNApd70QDVQfR8LoXT52'),
(62, 3, 'remedios.agbayani62@gmail.com', 'remidios', '$2y$10$c/8KJ3C84Yvf4/eSYyt2Aup9Guaw4JkddeeMnCpekV4DClXnS.eri'),
(63, 3, 'irish.abalos63@gmail.com', 'irish', '$2y$10$RBWAQiCaKLKSDOt5oqlrC.olvYItXnL6yUoYLqtIaej2nNU.IwFSK'),
(64, 3, 'richard.balanay64@gmail.com', 'richard', '$2y$10$h44Zh.elsKz0UNvsC3V2LehyzNDezeO6x0FQhzRKdUID6oOvAW8nK'),
(65, 3, 'gilbert.bagay65@gmail.com', 'gilbert', '$2y$10$SJysOUVrVJwwelfSQnnX6u2PBCRB6sokDg4D/46WQ65mItWfLKm7.'),
(66, 3, 'marcelo.baniqued66@gmail.com', 'marcelo', '$2y$10$oikhtJl1HbhHv3LMkit0f.8P8U8dI0ufme25dvOiiL4TziDjnOeWS'),
(67, 3, 'annjie.mas67@gmail.com', 'annjie', '$2y$10$HQD8BmVZTG/qGU/OE24.CeCjo5kj.kNM7hcmvcuWMbkTlv3R4Rfzu'),
(68, 3, 'zenaida.canvela68@gmail.com', 'zenaida', '$2y$10$rDhpkTEDO/fX60kXWN8AzOovDE.8vl.rCXEbVRffPkKAHC0crVynu'),
(69, 3, 'bernabe.curameng69@gmail.com', 'bernabe', '$2y$10$FWYTJZp8ZxYvL/ARSEhPkeSr9dTTgmhMSf5HhB.XXrO.pTya2Bk2S'),
(70, 3, 'eyren.corp HSCuz70@gmail.com', 'eyren', '$2y$10$KnbtXm4pCMrsqxl/vcQSle87gpK5G7WkSfQqEYF0YmypxwYAI4tkq'),
(71, 3, 'neriza.ansong71@gmail.com', 'neriza', '$2y$10$rjQDsLC6PxNaDbURWBc/8eCkp2yT9RkFin0Z.bh4vi6GpFqU.VcXG'),
(72, 3, 'teofilo.corpuz72@gmail.com', 'teofilo', '$2y$10$n/VYFEjGhkHRS3NXhpLQteCiaP1zH5F6x2Q5VgF5qWR4E1dRzoile'),
(73, 3, 'wilfred.cozpuz73@gmail.com', 'wilfred', '$2y$10$9YdmHbAcQ8Ahj9isjpRNKebvFabwaUB6bfsTWjAIzyVwru2Um4AmG'),
(74, 3, 'robert.cozpuz74@gmail.com', 'robert', '$2y$10$jVrdhKTweLwR4MTocMvXm.AsEeyRVZozyaYUyLf7leXN78ZMySvg.'),
(75, 3, 'regie.corpuz75@gmail.com', 'regie', '$2y$10$rwm4Ofue0t60ZVmTxVwQSe7LmjRVb0Nv3xtkPMWWbf.Kbm9aYNi9O'),
(76, 3, 'sonny.domingo76@gmail.com', 'sonny', '$2y$10$//UDx.KjTDKK3EhMMaOzAuJUP9XfUcwYjZih2pXdkF.yg9k9qiV5a'),
(77, 3, 'loreto.domingo77@gmail.com', 'loreto', '$2y$10$CUXAf1H7a2huzrr8JiGWYOzzaB6zBGwNBbwf.HUO7b0FuMNNbB/nq'),
(78, 3, 'saturnina.domingo78@gmail.com', 'saturnina', '$2y$10$Stsbp3delm1DmsW4DN.MBuzxfJiY4BscfhUdQIVg7IHcYe/z2cGkS'),
(79, 3, 'gabriel.espejo79@gmail.com', 'gabriel', '$2y$10$cYZjcE/wxwCRfkDdAL1/GeVJyB79CTQeqcSA8EA1DksB0u5N7ddI.'),
(80, 3, 'sherwin.felipe80@gmail.com', 'sherwin', '$2y$10$TpVC2XVim7OM5dklOzxNJuQIP.btRMwp0I3ishP21r6Rd4iSLPfwO'),
(81, 3, 'ariel.ferreras81@gmail.com', 'arnel', '$2y$10$w63IcmxmmNrkbsP9wrEl1OHY/L5hs20Mg8P5FzCLw.DRO6d5n4xYy'),
(82, 3, 'leona.ferreras82@gmail.com', 'leona', '$2y$10$G6oL3v.9wSVzrzNhvX8Z1egyEsrkGozwfNmWGyCYZO5rgfZqRptv.'),
(83, 3, 'jerry.gapasin83@gmail.com', 'jerry', '$2y$10$94fIiPbycX3CGunb/DbOQeaLZv0h42NEz3dWyEKKQSZHEUqj2i..e'),
(84, 3, 'rommel.gacusan84@gmail.com', 'rommel', '$2y$10$wsG/Af7Hz6T.CdHta60Uae3EuI4cv4sGpUZxZYzqAHiOoFhXXcnSq'),
(85, 3, 'eduardo.huyana85@gmail.com', 'eduardo', '$2y$10$K2mmYVeaV9iwzhOvahYjnOgPOzVKYidqufDpQmpkvXeo6eq8MKtyq'),
(86, 3, 'trece.ibarra86@gmail.com', 'trece', '$2y$10$CL6/Zt/uouDye83IAFT3l.qzkTov0ZPM5nlmyGJ3Bba/1SwBKelaK'),
(87, 3, 'enersto.ibarra87@gmail.com', 'ernesto', '$2y$10$B/Smlnmm6DqufxYPCgq3suJ0L8pH5FXAUz8V327bEW3YsX.L26h5K'),
(88, 3, 'alona.galleto88@gmail.com', 'alona', '$2y$10$R8f.4FbeGF2NdQeo79fF9uir.z6ArB3L32wBs.JKDYHqY3AMWhxjm'),
(89, 3, 'rosita.ibarra89@gmail.com', 'rosita', '$2y$10$aRl/SloCmrbk4QX8930jVOW1ZMu.GhNn0DGXnzCZLhtjR57ASRM7e'),
(90, 3, 'john.ibarra90@gmail.com', 'johnn', '$2y$10$4gHAnPn/8K0F8FpBh7CP/OXpNshGnZxkWkAv19iAdfl1cFP9onAW6'),
(91, 3, 'jaime.ibarra91@gmail.com', 'jaime', '$2y$10$aehfaC6CdSwNY6XRJeDfq.C4OGxpTq7TeQB0r08OoGzNF..xAzT6C'),
(92, 3, 'carmelita.ines92@gmail.com', 'carmerita', '$2y$10$HXI6LeVLzJK5onxFCbQYt.loMgM3fDL1WESHtZoCN.YQN8SfQSWSm'),
(93, 3, 'maria.manzano93@gmail.com', 'maria', '$2y$10$.rKzPAjgSRPk63STV1TDo.xLlPTsFRNa.DT1cognRdZ2nCbNxxX3e'),
(94, 3, 'wilcon.manzano94@gmail.com', 'wilcon', '$2y$10$KO9fXLkZg61HjvDtqYtJD.V5r.g4c.ftCeGPpfGuZUzj4r04MW7Qm'),
(95, 3, 'armando.apostol95@gmail.com', 'armando', '$2y$10$dBbniQDGTJVMQhjK.wmwSewYYb.nA2sW.UmlwDiYe.dcvtl7ZdY7a'),
(96, 3, 'ermeraldo.sapirano96@gmail.com', 'ermeraldo', '$2y$10$UTLOjEtYV9lQpPr0JnuSR.vyn9sBwax.h1cZmo9fxpLweNkMdK5UK'),
(97, 3, 'maria.curameng97@gmail.com', 'maria', '$2y$10$Yaef6rELkDdeFXpPvMiayOkWJzuYqvL62PwS6cmMeOmr2l54ZYjYq'),
(98, 3, 'daniel.apostol98@gmail.com', 'daniel', '$2y$10$nfQly5G3.7WyILuMuvJwg.ze04kASBIq5fDRLoSkDeekPFS4.Gsli'),
(99, 3, 'ruben.jatoy99@gmail.com', 'ruben', '$2y$10$3T8po0mdCdUhfLROhmAIL.iZ6TkjLO3VCW1i0Jsl660xSBFZzTpTa'),
(100, 3, 'lyle.agustin100@gmail.com', 'lylee', '$2y$10$zQuo3/HEWjIe9JpbWJca5er7rbdoBPwrnq6UIBB6DJSOXM5NBV7Wa'),
(101, 3, 'rodel.agustin101@gmail.com', 'rodel', '$2y$10$BKcOHsbBLlf/Uqq5SW2Qd.Yso5LpGm.eKnttN7smAMmWqTDs0u.YO'),
(102, 3, 'rowil.ramales102@gmail.com', 'rowil', '$2y$10$17kb76FYyjjB2f/Y37cv4.7KA4Kca7a36KjStVzB2OzNQ7Qk5P5hq'),
(103, 3, 'roberto.ramales103@gmail.com', 'roberto', '$2y$10$R33/wq1II5XWzYGVETnLk.9Ciu73qJunJ3odgKF1smdH95SNTHaqy'),
(104, 3, 'ronita.ragos104@gmail.com', 'ronita', '$2y$10$h/SXgEf0CzVeSa5YuKHCseB7af8nWvCKQwNFMUgkPMj9TgvTUjHRy'),
(105, 3, 'ridy.ragos105@gmail.com', 'ragos', '$2y$10$L1vXDtKzUvOgyBB87QlM/eWX3Jjqb8WdgxS6ZSQVJR2k.tsie.gQe'),
(106, 3, 'richard.oleganio106@gmail.com', 'richard', '$2y$10$NaMWdR3XtjLvKZ0OAUtRDeUrAAb9o4tpJqjOIBqgOl9T2FfEwPBAm'),
(107, 3, 'jilna.manzano107@gmail.com', 'jilna', '$2y$10$NrfgeAV0TOS1BHp.qfbc8uSiKvg0EFCGYh/ZGxMwtcVMflecDQmSi'),
(108, 3, 'allan.relacio108@gmail.com', 'allan', '$2y$10$zX.bpa7uc5RRiB/IZDQgiepGyMxKwG77NSyL3V2VE4/45fL7G/n2S'),
(109, 3, 'jovita.ramales109@gmail.com', 'jovita', '$2y$10$JykhzNhz3hchRIpux6Saw.VXmnAaed9qX6lnjIsKHTc3/rgxhpCn2'),
(110, 3, 'develi.tigno110@gmail.com', 'develi', '$2y$10$084bPiyk8zqy8odkTl1xF..WxOqft9YGZgFmXuq88Ltk.eE4cvkYC'),
(111, 3, 'reymark.ramales111@gmail.com', 'reymark', '$2y$10$7wukiHqP2CBQVdMe9m54pOMPnbz7g0OvBea.5i1AbI1TVGzjQNWAu'),
(112, 3, 'nestor.ramales112@gmail.com', 'nestor', '$2y$10$0L5V5rFivVqPBK7NAWhrQuG1OJ0Zus8NV53MF8DZlEUDYz4ZnfLGW'),
(113, 3, 'anthony.erestain113@gmail.com', 'anthony', '$2y$10$Xzjb7EQxMicCk4MAXJx91OfXw6DGeosCNHrEHSAYsWg.6zgzmSo3q'),
(114, 3, 'rodeljo.gutierrez114@gmail.com', 'rodeljo', '$2y$10$VrC2QEVTZkop2Ea6yu/O9u/84Dc4JvFisLO9IDRW0qm5Dsiz4runO'),
(115, 3, 'rafaela.parazo115@gmail.com', 'rafaela', '$2y$10$BZQz2Lpm2I7DtSz2MQsR0uylbLd/8ICw2K/zTF.B8FUjO5n5n166y'),
(116, 3, 'jiwenit.tormes116@gmail.com', 'jiwenit', '$2y$10$vBdP0P95jWbRPdul7VuoyO097APcpCKp0DarAQolJDbEqr5QUwkl.'),
(117, 3, 'maryjane.dacayanan117@gmail.com', 'maryjane', '$2y$10$IOPHW2wJjwdmf.pmJAz.WOV03j.XspcIctyYkS8WhhZaG1/dp9I3W'),
(118, 3, 'alfedo.dacayanan118@gmail.com', 'alfedo', '$2y$10$uLabHqjXFG28/xkAUy1Dse4QLSQlpZRCqpO2x4ce30rdAknaSSMta'),
(119, 3, 'willy.rivera119@gmail.com', 'willy', '$2y$10$xpusOM1ouVSVXFS/TDBKrO/Q0DfzceUEKnw8HdvDRXqPal.gR0CPO'),
(120, 3, 'jocelyn.dacayanan120@gmail.com', 'jocelyn', '$2y$10$PSYN50mQghKKzzWr5PXNTufet9S45I2OvTBonNcqDO/9TyPQXxvRi'),
(121, 3, 'aldrin.rivera121@gmail.com', 'aldrin', '$2y$10$ywfdBtOxKyrTH3OJj.lgXe7IEYZClemMsEksrXLEhtydXp9Wx0LZS'),
(122, 3, 'eduardo.adi122@gmail.com', 'eduardo', '$2y$10$3B/3zEuoEfBmUV98dmsTUOGO/rj4Fwe9PRsmlYSbsD1mQYnP9iYaa'),
(123, 3, 'federico.aquino123@gmail.com', 'federico', '$2y$10$Q6756WSVi6./7CkmiDdYbesNXusFHDftw9G.wODnKOsf6iDwWBX7a'),
(124, 3, 'natividad.bagay124@gmail.com', 'natividad', '$2y$10$ZTrjRvSyFHiUccJbDbc7sOtYG7aodwJ63La0rcC5b.T6X56qUwspK'),
(125, 3, 'lindon.bagay125@gmail.com', 'lindon', '$2y$10$iZmewSBnmPy6HYtcfu4PF.OZC46TspVRFDamaoG9ex3pInJvrf6mC'),
(126, 3, 'led.bagay126@gmail.com', 'leed2025', '$2y$10$Bxe.5CO0pJDmwqUONgMkIua/GGxHUtybuvoD1.IQgE73yXBeKwzBe'),
(127, 3, 'alego.bagay127@gmail.com', 'alejo', '$2y$10$n1ySYH3JpDrULzLQ/uSF4Op.ERPHeDNfrrBppJJxH0XCgqF4QCHUW'),
(128, 3, 'romeo.bagay128@gmail.com', 'romeo', '$2y$10$aFixEK3NI0gLp8V63Oj/b.MZrdd1JO48u.yj86mo.OlxkrN/RTjuK'),
(129, 3, 'connie.bautista129@gmail.com', 'connie', '$2y$10$oljZcNVQWzH4KGFu.d8TnOqRhK2dCciK.eH27aOrP9Vy5iiQSK4lq'),
(130, 3, 'joven.baltazar130@gmail.com', 'joven', '$2y$10$13pBx3QB/x04q07plvPhmOS2viz27hWWXoD/i1MWElzutL5xUefCW'),
(131, 3, 'vitor.bugarin131@gmail.com', 'victor', '$2y$10$iJkRNmMRFJuizYTsl7xbdegTats9wiEHrXSx2EHvqymUs5Pq.XK/6'),
(132, 3, 'arnel.arnio132@gmail.com', 'arnel', '$2y$10$VqUZNM7tO0mkfr3Xhq.xZ.UdjLNVoNHyCHg0kxRxQ1RFD78RCdgoa'),
(133, 3, 'genne.cabigas133@gmail.com', 'genne', '$2y$10$4uv3fXLVYfxhu1dfTiLIvOyLJ5knjZx8vLOr2pM6mxJ6t5qNCsZcC'),
(134, 3, 'sona.corpuz134@gmail.com', 'sonaa', '$2y$10$S84CaSNsYb0M0wee58gPbuTljjW1Gl.0Gl7rpw69MAgRkXLlMOCFa'),
(135, 3, 'felipe.corpuz135@gmail.com', 'felipe', '$2y$10$kQTQSwuzJXfYe9UNVTk6tuhylTbKeUVywKwLo6bkENkEj.vSMwOca'),
(136, 3, 'andora.corpuz136@gmail.com', 'adora', '$2y$10$1B0n8QuOi/FsokvhPSQHbOViQYI7gdA33w/fPDv.PQbaP8OZkpe7y'),
(137, 3, 'amador.delacruz137@gmail.com', 'amador', '$2y$10$k2ywlYm2tr3uxzJ7AR47.eKEgp6cHbuvoLiweIV36TGfp8aaW/RvS'),
(138, 3, 'reynante.delacruz138@gmail.com', 'reynante', '$2y$10$EWDQzwJsZUPv.7O.YMmJPOG1ASWsjB/zsRUd8VhvpStU0cNZ19TVi'),
(139, 3, 'larry.delacruz139@gmail.com', 'larry', '$2y$10$G44pak2RI/P/4Xu8V1GlhOrZdsTy0789yaaQ9krSw9BGbIv55fAse'),
(140, 3, 'flor.domingo140@gmail.com', 'florr', '$2y$10$LEdIZaEgkCHtaznfLLuuiuTG0k8H2YIaik4hXBS5lPK17V1HnmWK.'),
(141, 3, 'mariano.domingo141@gmail.com', 'mariano', '$2y$10$J4RyyATTs6IosXxMzgJ79uyaDlSf4b9g5PTg/XIeUlyMZyuM7e2uS'),
(142, 3, 'archie.domingo142@gmail.com', 'archie', '$2y$10$RYofFBgjrSDoqHPkQG900utnovbUi34w6q9keFxnKzDmyVHe6kzTG'),
(143, 3, 'marisol.facun143@gmail.com', 'marisol', '$2y$10$MQhGWA4w26DcvgqnspYWe.hWakhqKudTwWFuhQFQ34V5gCqx8NbTW'),
(144, 3, 'francisco.feliciano144@gmail.com', 'francisco', '$2y$10$lsg0CYs.GqUjJMTCDJa4RuUZTK8DDM0/JLKEJ5.hSC3n9wfop4av2'),
(145, 3, 'apolinaria.felipe145@gmail.com', 'apolinaria', '$2y$10$3vaAgG/2Rm2IGqKVV06ZZ.g7A6YUqm147trs0zxs6QFWZBYmmdLI2'),
(146, 3, 'sonny.fernando146@gmail.com', 'sonny', '$2y$10$4slBKLYCGziz3hh9VlODxecmf10MwHGyy48Su1XTPTMuWMW2tByna'),
(147, 3, 'evelyn.galsim147@gmail.com', 'evelyn', '$2y$10$A4HeCKieB2x8Q7T7wNmXSunCg6Aswqsz/FsSgipzpo24ygm8QR3l2'),
(148, 3, 'leonina.gutierrez148@gmail.com', 'leonina', '$2y$10$76erIXFhR/4123igQiDto.v0CAaXs2EjWShu9s5NM70yzD9/ycZAO'),
(149, 3, 'romel.gonzales149@gmail.com', 'romel', '$2y$10$H0VwtY.TPCciWqMNbZrY2O3wtBBP3IkJWojO/KhQrSEYwrNH1YqsK'),
(150, 3, 'james.juan150@gmail.com', 'james', '$2y$10$NvW07N.8P5LXpasscHTSTOh3ncY3v.Sz6nFTvm7l/Fl/1VyZPMFPS'),
(151, 3, 'aurora.agustin151@gmail.com', 'Aurora', '$2y$10$mq6IHi0tdTC58WYfsYT3Z.ZLkwJB/uZZl8RM.aOtx1IENRYTjhgWy'),
(152, 3, 'juan.agustin152@gmail.com', 'Juan', '$2y$10$yrQqnofdqUlGoMZnrMP/N.VUL9Ifdy2XbCPfEaVzZJjvyT5/s2ocS'),
(153, 3, 'rovel.altre153@gmail.com', 'Rovel', '$2y$10$xEsxCIIyk1Ofur44y04hBuzoGlJGEGprFGOPCPQQ.Wf2kXEoqYT/e'),
(154, 3, 'johnpaul.andrade31@gmail.com', 'JohnPaul', '$2y$10$X1iqP1M90lCKXavlmPQkdeAh2jNkzjA/yTpyHb6eUciFL.H.B8wHu'),
(155, 3, 'casimiro.apresto155@gmail.com', 'Casimiro', '$2y$10$JbU3o6ETThX4JcvUa0hVOe4dnNpgFi7HVzBlZUzcsyc2hHoc/rgCS'),
(156, 3, 'perlita.asuncion156@gmail.com', 'perlita', '$2y$10$wzSE8QtLNd.c4z.qx.XFRu34N41ET00ulSeXmqNVsyCLt7woQHlou'),
(157, 3, 'clarence.asuncion157@gmail.com', 'clarence', '$2y$10$fhzparthkhFwVJhbQY/Jhu.2GvrA678Q3HFVUpU0ZJwYGS9.tLTXG'),
(158, 3, 'erwinjoy.asuncion158@gmail.com', 'erwin', '$2y$10$Vkh2oXM8bzhrtpqo4Aq2deTPBkRlkEz6hOoDTxDGmduQwQSBYgt6.'),
(159, 3, 'julito.alitida159@gmail.com', 'julito', '$2y$10$x4qFdetiZI52OuHVmO6M.OFPHzL8OQhgxoMpW5EkpNPT9fZhyFYGK'),
(160, 3, 'eddie.laza160@gmail.com', 'eddie', '$2y$10$XUd7fl6R0ajACCZZRXGDGuv5M6cVLnBxICobX59CvCLzlfjkXrEUG'),
(161, 3, 'rochelda@gmail.com', 'rochelda', '$2y$10$OFV6FJxXLWfASQkuo0BTRu.kw1o91KR1dGIXRea9eFgXz4xSWLPuC'),
(162, 3, 'emily.ramalu162@gmail.com', 'emily', '$2y$10$7ebznJEbc69/.SH/L08eNuipIzrK2iz.U25e52LHP2zHWYj8fzz5e'),
(163, 3, 'robert.barot163@gmail.com', 'robert', '$2y$10$8SzBUBZQK05Y6OrCwUJFAu4QlpIqpfw4poUCZUeM6S8L4oC1axvnW'),
(164, 3, 'marcelo.barot164@gmail.com', 'marcelo', '$2y$10$PzVUDhe0bxcJotYnL05ftOXVW.xEE4pY6k1HyUx5paD6/2l40Y/wq'),
(165, 3, 'janico.delfonso165@gmail.com', 'Janico', '$2y$10$I8I5.B9SMuci9XG7PsOpau2In2.kaUUbpChoVsWbogHXF.Kn/rSx6'),
(166, 3, 'ferdinand.labrador166@gmail.com', 'Ferdinand', '$2y$10$chORORhAAJtHY4uTPBjrG.Ys5cxhBPL17myz1EQYDdbi6PStUZEim'),
(167, 3, 'joven.ohedo167@gmail.com', 'Joven', '$2y$10$hVTIPsJ9p/gaBzsao9Fa4.481fMTkxLfKykovJDY3Bx.SuSpZ4b.S'),
(168, 3, 'lovelyjoy.ohedo168@gmail.com', 'lovelyjoy', '$2y$10$k8iyb2KKjwnjnEvpoF3t4Od4nOrSjgreRLhsjY.M1DtZuXV.GC8TK'),
(169, 3, 'emmanuel.legaspi169@gmail.com', 'Emmanuel', '$2y$10$fRUHZbGRw.lkbbeHBKv5ousSAIp1Zvf3D9Ni7siq6zZ0U4J4dQmQW'),
(170, 3, 'rolly.esteban170@gmail.com', 'Rolly', '$2y$10$PPDMScTS3wMoZrdeWgkVgOetk9LAy5fwdVNp5Gtp7CbXloA/vpG0e'),
(171, 3, 'archie.domingo171@gmail.com', 'arcdomingo', '$2y$10$Wa/QXUxQshSX3DNqzTJ8.OHh2Yt8FpJqFRdOJORHTq1Udwt40Xl4W'),
(172, 3, 'violeta.delacruz172@gmail.com', 'Violeta', '$2y$10$YfoFXyXM8pJMWlNOcHlJB.WfPk1YpAxF0PPbw.nUOgw6y0033huMS'),
(173, 3, 'benjie.grospe173@gmail.com', 'benjie', '$2y$10$YX3tHaeTTSxwcz9tQc/QNuzVOVQzFHReEvJSgjBd7F2g9RohBMhkS'),
(174, 3, 'rolando.ganotese174@gmail.com', 'Rolando', '$2y$10$55wNXV/uqVhnSkHu0e.6U.9ghEHJBWPeY5hSJlS23vI0Ja9.MNiHe'),
(175, 3, 'felix.gutierrez175@gmail.com', 'gutierrez', '$2y$10$XPPmeOzZhAH1h5HDzTUKguzhupi7qcjh4cqh.KOPM4q8fESfmmaUO'),
(176, 3, 'carlito.gumen176@gmail.com', 'gumencarlito', '$2y$10$uDY4bd9CmYhWnt8V6ZGCTuCU/72K8IVhM5wGozAsVSZmJIUqwguPq'),
(177, 3, 'eduardo.hufano177@gmail.com', 'edhufano', '$2y$10$XKiOFUM7.Ama0nzWJRazSeuRcdh0JELk/u0me90KKso4RdV7yWwcu'),
(178, 3, 'alejandro.cancino178@gmail.com', 'Alejandro', '$2y$10$W5ek2ASkeb8vE2R9f9SfjeC4l/Nhq3K.oDunFMFtxJhxFe1iJWG3.'),
(179, 3, 'julita.conception179@gmail.com', 'Julita', '$2y$10$jXRyf4h8cQNpLVpGQcoqZ.38Ysd30AsfuhvJLge9dPBOqvjSOo/5G'),
(180, 3, 'christian.insigne180@gmail.com', 'insigne', '$2y$10$Mui2r4fGzmXW61w8YODE2.TZRdcH/LGDHy0dXBrnjClenHrCnznCm'),
(181, 3, 'bonifacio.lactaoen181@gmail.com', 'lactaoen', '$2y$10$d6DG2o1MDUdZNKNZNaQSZuXOJlxbIZhArOPfuQhnWvc5tOUwNMQ9G'),
(182, 3, 'edwin.dejesus182@gmail.com', 'dejesus', '$2y$10$FqmvWJ.hTYc9PoIvoutN4.G0eGsw67En9276fmw1nNgkcbDSk1DD6'),
(183, 3, 'juliana.lacuesta183@gmail.com', 'lacuesta', '$2y$10$i0KpJCorEZJlAuaasGyDw.o7kXHDk1IsGevN1U2lj1n4uq0v8Nmea'),
(184, 3, 'sofranio.madrigal184@gmail.com', 'madrigal', '$2y$10$Y2kHJ8F5mh5WZBR/TOMsHO.fDjxYdI8gKtZyOyGMo8tqYGrr6YUMa'),
(185, 3, 'bernard.lucero185@gmail.com', 'luceronard', '$2y$10$9.39U5sBaK07wFatGxxU/.1Psl3hbbVKdZJ1gdE1UKHLWwruk/apq'),
(186, 3, 'leonel.lijauco186@gmail.com', 'lijuaco', '$2y$10$BMIE7xOagj40MddRYuYPXOOhzWiHGg64QxDf2Xy3CkrCCUtGbPexe'),
(187, 3, 'chryster.mundala187@gmail.com', 'mundala', '$2y$10$6crPNs5/GDqqJeoJkVF06OGOj6Q0td1D.nm3ylcuVwa9x7lAwwpAy');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`activity_logs_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `address`
--
ALTER TABLE `address`
  ADD PRIMARY KEY (`address_id`);

--
-- Indexes for table `child_immunization`
--
ALTER TABLE `child_immunization`
  ADD PRIMARY KEY (`child_record_id`,`immunization_id`),
  ADD KEY `immunization_id` (`immunization_id`);

--
-- Indexes for table `child_record`
--
ALTER TABLE `child_record`
  ADD PRIMARY KEY (`child_record_id`),
  ADD KEY `records_id` (`records_id`);

--
-- Indexes for table `family_planning_record`
--
ALTER TABLE `family_planning_record`
  ADD PRIMARY KEY (`fp_id`),
  ADD KEY `records_id` (`records_id`);

--
-- Indexes for table `household_record`
--
ALTER TABLE `household_record`
  ADD PRIMARY KEY (`household_record_id`),
  ADD KEY `records_id` (`records_id`);

--
-- Indexes for table `immunization`
--
ALTER TABLE `immunization`
  ADD PRIMARY KEY (`immunization_id`);

--
-- Indexes for table `infant_record`
--
ALTER TABLE `infant_record`
  ADD PRIMARY KEY (`infant_record_id`),
  ADD KEY `child_record_id` (`child_record_id`);

--
-- Indexes for table `medication`
--
ALTER TABLE `medication`
  ADD PRIMARY KEY (`medication_id`);

--
-- Indexes for table `person`
--
ALTER TABLE `person`
  ADD PRIMARY KEY (`person_id`),
  ADD KEY `person_ibfk_1` (`address_id`),
  ADD KEY `person_ibfk_2` (`related_person_id`);

--
-- Indexes for table `postnatal`
--
ALTER TABLE `postnatal`
  ADD PRIMARY KEY (`postnatal_id`),
  ADD KEY `pregnancy_record_id` (`pregnancy_record_id`);

--
-- Indexes for table `pregnancy_medication`
--
ALTER TABLE `pregnancy_medication`
  ADD PRIMARY KEY (`pregnancy_record_id`,`medication_id`),
  ADD KEY `medication_id` (`medication_id`);

--
-- Indexes for table `pregnancy_record`
--
ALTER TABLE `pregnancy_record`
  ADD PRIMARY KEY (`pregnancy_record_id`),
  ADD KEY `records_id` (`records_id`);

--
-- Indexes for table `prenatal`
--
ALTER TABLE `prenatal`
  ADD PRIMARY KEY (`prenatal_id`),
  ADD KEY `pregnancy_record_id` (`pregnancy_record_id`);

--
-- Indexes for table `privilege`
--
ALTER TABLE `privilege`
  ADD PRIMARY KEY (`privilege_id`);

--
-- Indexes for table `records`
--
ALTER TABLE `records`
  ADD PRIMARY KEY (`records_id`),
  ADD KEY `records_ibfk_1` (`user_id`),
  ADD KEY `records_ibfk_2` (`person_id`),
  ADD KEY `records_ibfk_3` (`created_by`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `role_privilege`
--
ALTER TABLE `role_privilege`
  ADD PRIMARY KEY (`role_id`,`privilege_id`),
  ADD KEY `privilege_id` (`privilege_id`);

--
-- Indexes for table `senior_medication`
--
ALTER TABLE `senior_medication`
  ADD PRIMARY KEY (`senior_record_id`,`medication_id`),
  ADD KEY `medication_id` (`medication_id`);

--
-- Indexes for table `senior_record`
--
ALTER TABLE `senior_record`
  ADD PRIMARY KEY (`senior_record_id`),
  ADD KEY `records_id` (`records_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `users_ibfk_1` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `activity_logs_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `address`
--
ALTER TABLE `address`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `child_record`
--
ALTER TABLE `child_record`
  MODIFY `child_record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `family_planning_record`
--
ALTER TABLE `family_planning_record`
  MODIFY `fp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `household_record`
--
ALTER TABLE `household_record`
  MODIFY `household_record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=324;

--
-- AUTO_INCREMENT for table `immunization`
--
ALTER TABLE `immunization`
  MODIFY `immunization_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `infant_record`
--
ALTER TABLE `infant_record`
  MODIFY `infant_record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `medication`
--
ALTER TABLE `medication`
  MODIFY `medication_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `person`
--
ALTER TABLE `person`
  MODIFY `person_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=398;

--
-- AUTO_INCREMENT for table `postnatal`
--
ALTER TABLE `postnatal`
  MODIFY `postnatal_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pregnancy_record`
--
ALTER TABLE `pregnancy_record`
  MODIFY `pregnancy_record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `prenatal`
--
ALTER TABLE `prenatal`
  MODIFY `prenatal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `privilege`
--
ALTER TABLE `privilege`
  MODIFY `privilege_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `records`
--
ALTER TABLE `records`
  MODIFY `records_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=622;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `senior_record`
--
ALTER TABLE `senior_record`
  MODIFY `senior_record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=188;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `child_immunization`
--
ALTER TABLE `child_immunization`
  ADD CONSTRAINT `child_immunization_ibfk_1` FOREIGN KEY (`child_record_id`) REFERENCES `child_record` (`child_record_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `child_immunization_ibfk_2` FOREIGN KEY (`immunization_id`) REFERENCES `immunization` (`immunization_id`) ON DELETE CASCADE;

--
-- Constraints for table `child_record`
--
ALTER TABLE `child_record`
  ADD CONSTRAINT `child_record_ibfk_1` FOREIGN KEY (`records_id`) REFERENCES `records` (`records_id`) ON DELETE CASCADE;

--
-- Constraints for table `family_planning_record`
--
ALTER TABLE `family_planning_record`
  ADD CONSTRAINT `family_planning_record_ibfk_1` FOREIGN KEY (`records_id`) REFERENCES `records` (`records_id`) ON DELETE CASCADE;

--
-- Constraints for table `household_record`
--
ALTER TABLE `household_record`
  ADD CONSTRAINT `household_record_ibfk_1` FOREIGN KEY (`records_id`) REFERENCES `records` (`records_id`) ON DELETE CASCADE;

--
-- Constraints for table `infant_record`
--
ALTER TABLE `infant_record`
  ADD CONSTRAINT `infant_record_ibfk_1` FOREIGN KEY (`child_record_id`) REFERENCES `child_record` (`child_record_id`) ON DELETE CASCADE;

--
-- Constraints for table `person`
--
ALTER TABLE `person`
  ADD CONSTRAINT `person_ibfk_1` FOREIGN KEY (`address_id`) REFERENCES `address` (`address_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `person_ibfk_2` FOREIGN KEY (`related_person_id`) REFERENCES `person` (`person_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `postnatal`
--
ALTER TABLE `postnatal`
  ADD CONSTRAINT `postnatal_ibfk_1` FOREIGN KEY (`pregnancy_record_id`) REFERENCES `pregnancy_record` (`pregnancy_record_id`) ON DELETE CASCADE;

--
-- Constraints for table `pregnancy_medication`
--
ALTER TABLE `pregnancy_medication`
  ADD CONSTRAINT `pregnancy_medication_ibfk_1` FOREIGN KEY (`pregnancy_record_id`) REFERENCES `pregnancy_record` (`pregnancy_record_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pregnancy_medication_ibfk_2` FOREIGN KEY (`medication_id`) REFERENCES `medication` (`medication_id`) ON DELETE CASCADE;

--
-- Constraints for table `pregnancy_record`
--
ALTER TABLE `pregnancy_record`
  ADD CONSTRAINT `pregnancy_record_ibfk_1` FOREIGN KEY (`records_id`) REFERENCES `records` (`records_id`) ON DELETE CASCADE;

--
-- Constraints for table `prenatal`
--
ALTER TABLE `prenatal`
  ADD CONSTRAINT `prenatal_ibfk_1` FOREIGN KEY (`pregnancy_record_id`) REFERENCES `pregnancy_record` (`pregnancy_record_id`) ON DELETE CASCADE;

--
-- Constraints for table `records`
--
ALTER TABLE `records`
  ADD CONSTRAINT `records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `records_ibfk_2` FOREIGN KEY (`person_id`) REFERENCES `person` (`person_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `records_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `role_privilege`
--
ALTER TABLE `role_privilege`
  ADD CONSTRAINT `role_privilege_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `role` (`role_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_privilege_ibfk_2` FOREIGN KEY (`privilege_id`) REFERENCES `privilege` (`privilege_id`) ON DELETE CASCADE;

--
-- Constraints for table `senior_medication`
--
ALTER TABLE `senior_medication`
  ADD CONSTRAINT `senior_medication_ibfk_1` FOREIGN KEY (`senior_record_id`) REFERENCES `senior_record` (`senior_record_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `senior_medication_ibfk_2` FOREIGN KEY (`medication_id`) REFERENCES `medication` (`medication_id`) ON DELETE CASCADE;

--
-- Constraints for table `senior_record`
--
ALTER TABLE `senior_record`
  ADD CONSTRAINT `senior_record_ibfk_1` FOREIGN KEY (`records_id`) REFERENCES `records` (`records_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `role` (`role_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
