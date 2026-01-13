-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 13, 2026 at 10:36 AM
-- Server version: 10.11.13-MariaDB-0ubuntu0.24.04.1
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `utumishi`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_cases`
-- (See below for the actual view)
--
CREATE TABLE `active_cases` (
`id` int(11)
,`ob_number` varchar(30)
,`title` varchar(200)
,`category` varchar(50)
,`status` enum('reported','assigned','in_progress','resolved','closed')
,`location_county` varchar(50)
,`location_constituency` varchar(50)
,`reporter_name` varchar(100)
,`recorded_by` varchar(100)
,`assigned_officer` varchar(123)
,`station_name` varchar(100)
,`created_at` timestamp
,`estimated_resolution_hours` int(11)
,`hours_since_reported` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `cases`
--

CREATE TABLE `cases` (
  `id` int(11) NOT NULL,
  `ob_number` varchar(30) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(50) NOT NULL,
  `location_county` varchar(50) NOT NULL,
  `location_constituency` varchar(50) NOT NULL,
  `reported_by_citizen_id` int(11) NOT NULL,
  `recorded_by_officer_id` int(11) NOT NULL,
  `assigned_officer_id` int(11) DEFAULT NULL,
  `station_id` int(11) NOT NULL,
  `status` enum('reported','assigned','in_progress','resolved','closed') DEFAULT 'reported',
  `estimated_resolution_hours` int(11) DEFAULT 72,
  `actual_resolution_hours` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cases`
--

INSERT INTO `cases` (`id`, `ob_number`, `title`, `description`, `category`, `location_county`, `location_constituency`, `reported_by_citizen_id`, `recorded_by_officer_id`, `assigned_officer_id`, `station_id`, `status`, `estimated_resolution_hours`, `actual_resolution_hours`, `created_at`, `updated_at`, `closed_at`) VALUES
(1, 'OB-NRB-2025-00001', 'Theft of Mobile Phone', 'Phone stolen at bus stop near University Way', 'Theft', 'Nairobi', 'Starehe', 9, 4, 1, 1, 'in_progress', 72, NULL, '2025-01-15 06:30:00', '2025-09-16 17:19:36', NULL),
(2, 'OB-NRB-2025-00002', 'House Break-in', 'Burglars broke into house and stole electronics', 'Burglary', 'Nairobi', 'Starehe', 10, 5, 1, 1, 'assigned', 96, NULL, '2025-01-16 11:20:00', '2025-09-16 17:19:36', NULL),
(3, 'OB-LGT-2025-00001', 'Assault Case', 'Physical altercation between neighbors', 'Assault', 'Nairobi', 'Langata', 11, 6, 2, 2, 'resolved', 48, 46, '2025-01-14 13:45:00', '2025-09-16 17:19:36', '2025-01-16 11:45:00'),
(4, 'OB-LGT-2025-00002', 'Car Theft', 'Vehicle stolen from parking lot', 'Theft', 'Nairobi', 'Langata', 12, 7, 3, 2, 'in_progress', 72, NULL, '2025-01-17 08:15:00', '2025-09-16 17:19:36', NULL),
(5, 'OB-NRB-2025-00003', 'Cybercrime - Identity Theft', 'Someone using stolen identity for fraud', 'Cybercrime', 'Nairobi', 'Starehe', 13, 4, 3, 1, 'assigned', 96, NULL, '2025-01-18 05:30:00', '2025-09-16 17:19:36', NULL),
(6, 'OB-LGT-2025-00003', 'Domestic Violence', 'Spousal abuse reported by neighbor', 'Domestic Violence', 'Nairobi', 'Langata', 14, 6, 2, 2, 'closed', 24, 22, '2025-01-10 16:30:00', '2025-09-16 17:19:37', '2025-01-11 14:30:00'),
(7, 'OB-NRB-2025-00004', 'Fraud Case', 'M-Pesa fraud involving fake transactions', 'Fraud', 'Nairobi', 'Starehe', 15, 5, 1, 1, 'in_progress', 72, NULL, '2025-01-19 10:45:00', '2025-09-16 17:19:36', NULL),
(8, 'OB-LGT-2025-00004', 'Public Disturbance', 'Noise complaint from residential area', 'Public Order', 'Nairobi', 'Langata', 16, 7, 4, 2, 'resolved', 12, NULL, '2025-01-20 17:15:00', '2025-09-16 17:19:36', NULL),
(9, 'OB-NRB-2025-00005', 'Drug Possession', 'Suspected drug dealer caught with substances', 'Drug Offenses', 'Nairobi', 'Starehe', 17, 4, 5, 1, 'assigned', 48, NULL, '2025-01-21 12:20:00', '2025-09-16 17:19:36', NULL),
(10, 'OB-LGT-2025-00005', 'Traffic Violation', 'Reckless driving causing property damage', 'Traffic Offenses', 'Nairobi', 'Langata', 18, 6, 4, 2, 'reported', 24, NULL, '2025-01-22 09:00:00', '2025-09-16 17:19:36', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `case_evidence`
--

CREATE TABLE `case_evidence` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_type` varchar(10) NOT NULL,
  `uploaded_by_officer_id` int(11) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `case_updates`
--

CREATE TABLE `case_updates` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `officer_id` int(11) NOT NULL,
  `update_text` text NOT NULL,
  `status_before` varchar(20) NOT NULL,
  `status_after` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `case_updates`
--

INSERT INTO `case_updates` (`id`, `case_id`, `officer_id`, `update_text`, `status_before`, `status_after`, `created_at`) VALUES
(1, 1, 4, 'Case assigned to Officer Mwangi for investigation', 'reported', 'assigned', '2025-01-15 07:00:00'),
(2, 1, 4, 'Initial investigation started, reviewing CCTV footage', 'assigned', 'in_progress', '2025-01-15 11:30:00'),
(3, 3, 6, 'Suspect identified and arrested', 'assigned', 'in_progress', '2025-01-14 15:00:00'),
(4, 3, 6, 'Case resolved, suspect charged in court', 'in_progress', 'resolved', '2025-01-16 11:45:00'),
(5, 6, 6, 'Victim provided with protection and counseling', 'assigned', 'in_progress', '2025-01-10 18:00:00'),
(6, 6, 6, 'Case closed after successful intervention', 'resolved', 'closed', '2025-01-11 14:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `crime_statistics`
--

CREATE TABLE `crime_statistics` (
  `id` int(11) NOT NULL,
  `station_id` int(11) NOT NULL,
  `crime_category` varchar(50) NOT NULL,
  `county` varchar(50) NOT NULL,
  `constituency` varchar(50) NOT NULL,
  `month` varchar(7) NOT NULL,
  `total_reported` int(11) DEFAULT 0,
  `total_resolved` int(11) DEFAULT 0,
  `avg_resolution_hours` decimal(5,2) DEFAULT 0.00,
  `peak_hours` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crime_statistics`
--

INSERT INTO `crime_statistics` (`id`, `station_id`, `crime_category`, `county`, `constituency`, `month`, `total_reported`, `total_resolved`, `avg_resolution_hours`, `peak_hours`, `created_at`, `updated_at`) VALUES
(1, 1, 'Theft', 'Nairobi', 'Starehe', '2025-01', 15, 12, 68.50, '14:00-18:00', '2025-09-16 17:19:37', '2025-09-16 17:19:37'),
(2, 1, 'Assault', 'Nairobi', 'Starehe', '2025-01', 8, 7, 45.20, '20:00-02:00', '2025-09-16 17:19:37', '2025-09-16 17:19:37'),
(3, 1, 'Cybercrime', 'Nairobi', 'Starehe', '2025-01', 5, 3, 89.30, '09:00-17:00', '2025-09-16 17:19:37', '2025-09-16 17:19:37'),
(4, 2, 'Theft', 'Nairobi', 'Langata', '2025-01', 12, 10, 71.80, '19:00-23:00', '2025-09-16 17:19:37', '2025-09-16 17:19:37'),
(5, 2, 'Domestic Violence', 'Nairobi', 'Langata', '2025-01', 6, 6, 26.40, '18:00-22:00', '2025-09-16 17:19:37', '2025-09-16 17:19:37'),
(6, 2, 'Traffic Offenses', 'Nairobi', 'Langata', '2025-01', 20, 18, 8.50, '07:00-09:00', '2025-09-16 17:19:37', '2025-09-16 17:19:37');

-- --------------------------------------------------------

-- --------------------------------------------------------

--
-- Table structure for table `officers`
--

CREATE TABLE `officers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `badge_number` varchar(20) NOT NULL,
  `expertise_categories` text DEFAULT NULL,
  `current_case_load` int(11) DEFAULT 0,
  `total_cases_resolved` int(11) DEFAULT 0,
  `avg_resolution_time_hours` decimal(5,2) DEFAULT 0.00,
  `joined_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`id`, `user_id`, `badge_number`, `expertise_categories`, `current_case_load`, `total_cases_resolved`, `avg_resolution_time_hours`, `joined_date`) VALUES
(1, 4, 'KPS-1145', '[\"Theft\", \"Burglary\", \"Fraud\"]', 3, 25, 0.00, '2020-03-15'),
(2, 5, 'KPS-1146', '[\"Assault\", \"Domestic Violence\", \"Public Order\"]', 2, 30, 0.00, '2019-08-22'),
(3, 6, 'KPS-2201', '[\"Cybercrime\", \"Fraud\", \"Identity Theft\"]', 4, 18, 0.00, '2021-01-10'),
(4, 7, 'KPS-2202', '[\"Traffic Offenses\", \"DUI\", \"Reckless Driving\"]', 1, 22, 0.00, '2020-11-05'),
(5, 8, 'KPS-3301', '[\"Drug Offenses\", \"Trafficking\", \"Possession\"]', 2, 15, 0.00, '2022-06-18');

-- --------------------------------------------------------

--
-- Stand-in structure for view `officer_performance`
-- (See below for the actual view)
--
CREATE TABLE `officer_performance` (
`id` int(11)
,`name` varchar(100)
,`badge_number` varchar(20)
,`station_name` varchar(100)
,`current_case_load` int(11)
,`total_cases_resolved` int(11)
,`avg_resolution_time_hours` decimal(5,2)
,`workload_status` varchar(10)
);

-- --------------------------------------------------------

--
-- Table structure for table `stations`
--

CREATE TABLE `stations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `station_code` varchar(10) NOT NULL,
  `county` varchar(50) NOT NULL,
  `constituency` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `contact_phone` varchar(15) DEFAULT NULL,
  `budget_allocated` decimal(15,2) DEFAULT 0.00,
  `commander_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stations`
--

INSERT INTO `stations` (`id`, `name`, `station_code`, `county`, `constituency`, `address`, `contact_phone`, `budget_allocated`, `commander_id`, `created_at`) VALUES
(1, 'Nairobi Central Police Station', 'NRB', 'Nairobi', 'Starehe', 'University Way, Nairobi', '+254202345600', 5000000.00, 2, '2025-09-16 17:19:36'),
(2, 'Langata Police Station', 'LGT', 'Nairobi', 'Langata', 'Langata Road, Nairobi', '+254202345601', 3500000.00, 3, '2025-09-16 17:19:36'),
(3, 'Kiambu Police Station', 'KMB', 'Kiambu', 'Kiambu Town', 'Kiambu Town Center', '+254202345602', 2800000.00, NULL, '2025-09-16 17:19:36'),
(4, 'Mombasa Central Police Station', 'MSA', 'Mombasa', 'Mvita', 'Digo Road, Mombasa', '+254412345600', 4200000.00, NULL, '2025-09-16 17:19:36'),
(5, 'Eldoret Police Station', 'ELD', 'Uasin Gishu', 'Eldoret East', 'Uganda Road, Eldoret', '+254532345600', 3000000.00, NULL, '2025-09-16 17:19:36');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'session_timeout', '1800', 'Session timeout in seconds (30 minutes)', '2025-09-16 17:19:36'),
(2, 'default_resolution_hours_theft', '72', 'Default resolution time for theft cases', '2025-09-16 17:19:36'),
(3, 'default_resolution_hours_assault', '48', 'Default resolution time for assault cases', '2025-09-16 17:19:36'),
(4, 'default_resolution_hours_cybercrime', '96', 'Default resolution time for cybercrime cases', '2025-09-16 17:19:36'),
(5, 'default_resolution_hours_domestic', '24', 'Default resolution time for domestic violence', '2025-09-16 17:19:36'),
(6, 'high_crime_threshold', '50', 'Threshold for high crime area classification', '2025-09-16 17:19:36'),
(7, 'max_case_load_per_officer', '15', 'Maximum cases per officer', '2025-09-16 17:19:36'),
(8, 'evidence_max_file_size', '5242880', 'Max file size for evidence upload (5MB)', '2025-09-16 17:19:36');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `national_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','ocs','officer','citizen') NOT NULL,
  `station_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `national_id`, `name`, `email`, `phone`, `password`, `role`, `station_id`, `created_at`, `last_login`, `is_active`) VALUES
(1, '12345678', 'System Administrator', 'admin@police.go.ke', '+254700000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, '2025-09-16 17:19:36', '2025-11-18 13:58:57', 1),
(2, '23456789', 'John Kamau', 'j.kamau@police.go.ke', '+254701000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ocs', 1, '2025-09-16 17:19:36', '2025-11-18 13:58:07', 1),
(3, '34567890', 'Mary Wanjiku', 'm.wanjiku@police.go.ke', '+254701000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ocs', 2, '2025-09-16 17:19:36', NULL, 1),
(4, '45678901', 'Peter Mwangi', 'p.mwangi@police.go.ke', '+254702000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer', 1, '2025-09-16 17:19:36', '2026-01-10 01:38:42', 1),
(5, '56789012', 'Grace Atieno', 'g.atieno@police.go.ke', '+254702000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer', 1, '2025-09-16 17:19:36', NULL, 1),
(6, '67890123', 'David Kiprop', 'd.kiprop@police.go.ke', '+254702000003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer', 2, '2025-09-16 17:19:36', NULL, 1),
(7, '78901234', 'Alice Nyong\'o', 'a.nyongo@police.go.ke', '+254702000004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer', 2, '2025-09-16 17:19:36', NULL, 1),
(8, '89012345', 'James Kuria', 'j.kuria@police.go.ke', '+254702000005', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer', 3, '2025-09-16 17:19:36', NULL, 1),
(9, '11111111', 'Francis Mutua', NULL, '+254703000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'citizen', NULL, '2025-09-16 17:19:36', '2025-11-18 13:56:25', 1),
(10, '22222222', 'Sarah Kiprotich', NULL, '+254703000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'citizen', NULL, '2025-09-16 17:19:36', NULL, 1),
(11, '33333333', 'Michael Ochieng', NULL, '+254703000003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'citizen', NULL, '2025-09-16 17:19:36', NULL, 1),
(12, '44444444', 'Jane Wambui', NULL, '+254703000004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'citizen', NULL, '2025-09-16 17:19:36', NULL, 1),
(13, '55555555', 'Robert Kibet', NULL, '+254703000005', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'citizen', NULL, '2025-09-16 17:19:36', NULL, 1),
(14, '66666666', 'Christine Muthoni', NULL, '+254703000006', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'citizen', NULL, '2025-09-16 17:19:36', NULL, 1),
(15, '77777777', 'Daniel Cherono', NULL, '+254703000007', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'citizen', NULL, '2025-09-16 17:19:36', NULL, 1),
(16, '88888888', 'Rose Njoki', NULL, '+254703000008', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'citizen', NULL, '2025-09-16 17:19:36', NULL, 1),
(17, '99999999', 'Samuel Rotich', NULL, '+254703000009', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'citizen', NULL, '2025-09-16 17:19:36', NULL, 1),
(18, '10101010', 'Elizabeth Wanjiru', NULL, '+254703000010', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'citizen', NULL, '2025-09-16 17:19:36', NULL, 1),
(19, '32894832', 'Paul Kagai', 'paul@gmail.com', '0729426791', '$2y$10$WyRBI/N1Yl0U3.4KI.P.ZeZ2UgPG4oz.xmOfFOjAc5q2R68hPfy0G', 'citizen', NULL, '2025-09-30 10:06:58', '2025-10-17 02:59:31', 1);

-- --------------------------------------------------------

--
-- Structure for view `active_cases`
--
DROP TABLE IF EXISTS `active_cases`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_cases`  AS SELECT `c`.`id` AS `id`, `c`.`ob_number` AS `ob_number`, `c`.`title` AS `title`, `c`.`category` AS `category`, `c`.`status` AS `status`, `c`.`location_county` AS `location_county`, `c`.`location_constituency` AS `location_constituency`, `u1`.`name` AS `reporter_name`, `u2`.`name` AS `recorded_by`, concat(`u3`.`name`,' (',`o`.`badge_number`,')') AS `assigned_officer`, `s`.`name` AS `station_name`, `c`.`created_at` AS `created_at`, `c`.`estimated_resolution_hours` AS `estimated_resolution_hours`, timestampdiff(HOUR,`c`.`created_at`,current_timestamp()) AS `hours_since_reported` FROM (((((`cases` `c` join `users` `u1` on(`c`.`reported_by_citizen_id` = `u1`.`id`)) join `users` `u2` on(`c`.`recorded_by_officer_id` = `u2`.`id`)) left join `officers` `o` on(`c`.`assigned_officer_id` = `o`.`id`)) left join `users` `u3` on(`o`.`user_id` = `u3`.`id`)) join `stations` `s` on(`c`.`station_id` = `s`.`id`)) WHERE `c`.`status` <> 'closed' ;

-- --------------------------------------------------------

--
-- Structure for view `officer_performance`
--
DROP TABLE IF EXISTS `officer_performance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `officer_performance`  AS SELECT `u`.`id` AS `id`, `u`.`name` AS `name`, `o`.`badge_number` AS `badge_number`, `s`.`name` AS `station_name`, `o`.`current_case_load` AS `current_case_load`, `o`.`total_cases_resolved` AS `total_cases_resolved`, `o`.`avg_resolution_time_hours` AS `avg_resolution_time_hours`, CASE WHEN `o`.`current_case_load` > 10 THEN 'Overloaded' WHEN `o`.`current_case_load` > 5 THEN 'Normal' ELSE 'Light Load' END AS `workload_status` FROM ((`users` `u` join `officers` `o` on(`u`.`id` = `o`.`user_id`)) join `stations` `s` on(`u`.`station_id` = `s`.`id`)) WHERE `u`.`role` = 'officer' AND `u`.`is_active` = 1 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cases`
--
ALTER TABLE `cases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ob_number` (`ob_number`),
  ADD KEY `idx_ob_number` (`ob_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_station` (`station_id`),
  ADD KEY `idx_citizen` (`reported_by_citizen_id`),
  ADD KEY `idx_assigned` (`assigned_officer_id`),
  ADD KEY `recorded_by_officer_id` (`recorded_by_officer_id`),
  ADD KEY `idx_cases_created_at` (`created_at`),
  ADD KEY `idx_cases_county_category` (`location_county`,`category`);

--
-- Indexes for table `case_evidence`
--
ALTER TABLE `case_evidence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_case` (`case_id`),
  ADD KEY `uploaded_by_officer_id` (`uploaded_by_officer_id`);

--
-- Indexes for table `case_updates`
--
ALTER TABLE `case_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_case` (`case_id`),
  ADD KEY `idx_officer` (`officer_id`);

--
-- Indexes for table `crime_statistics`
--
ALTER TABLE `crime_statistics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_station_month` (`station_id`,`month`),
  ADD KEY `idx_category` (`crime_category`),
  ADD KEY `idx_county_month` (`county`,`month`),
  ADD KEY `idx_crime_stats_month_category` (`month`,`crime_category`);



--
-- Indexes for table `officers`
--
ALTER TABLE `officers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `badge_number` (`badge_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_badge` (`badge_number`),
  ADD KEY `idx_officers_case_load` (`current_case_load`);

--
-- Indexes for table `stations`
--
ALTER TABLE `stations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `station_code` (`station_code`),
  ADD KEY `idx_county` (`county`),
  ADD KEY `idx_constituency` (`constituency`),
  ADD KEY `idx_commander` (`commander_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `national_id` (`national_id`),
  ADD KEY `idx_national_id` (`national_id`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_station` (`station_id`),
  ADD KEY `idx_users_role_station` (`role`,`station_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cases`
--
ALTER TABLE `cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `case_evidence`
--
ALTER TABLE `case_evidence`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_updates`
--
ALTER TABLE `case_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `crime_statistics`
--
ALTER TABLE `crime_statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;



--
-- AUTO_INCREMENT for table `officers`
--
ALTER TABLE `officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stations`
--
ALTER TABLE `stations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cases`
--
ALTER TABLE `cases`
  ADD CONSTRAINT `cases_ibfk_1` FOREIGN KEY (`reported_by_citizen_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cases_ibfk_2` FOREIGN KEY (`recorded_by_officer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cases_ibfk_3` FOREIGN KEY (`assigned_officer_id`) REFERENCES `officers` (`id`),
  ADD CONSTRAINT `cases_ibfk_4` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`);

--
-- Constraints for table `case_evidence`
--
ALTER TABLE `case_evidence`
  ADD CONSTRAINT `case_evidence_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `case_evidence_ibfk_2` FOREIGN KEY (`uploaded_by_officer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `case_updates`
--
ALTER TABLE `case_updates`
  ADD CONSTRAINT `case_updates_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `case_updates_ibfk_2` FOREIGN KEY (`officer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `crime_statistics`
--
ALTER TABLE `crime_statistics`
  ADD CONSTRAINT `crime_statistics_ibfk_1` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`);



--
-- Constraints for table `officers`
--
ALTER TABLE `officers`
  ADD CONSTRAINT `officers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stations`
--
ALTER TABLE `stations`
  ADD CONSTRAINT `stations_ibfk_1` FOREIGN KEY (`commander_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
