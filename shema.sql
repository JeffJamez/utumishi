-- Utumishi - Digital OB System Database Schema
-- Kenya Police Service Occurrence Book System

CREATE TABLE `stations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `station_code` varchar(10) NOT NULL,
  `county` varchar(50) NOT NULL,
  `constituency` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `contact_phone` varchar(15) DEFAULT NULL,
  `commander_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `station_code` (`station_code`),
  KEY `idx_county` (`county`),
  KEY `idx_constituency` (`constituency`),
  KEY `idx_commander` (`commander_id`),
  CONSTRAINT `stations_ibfk_1` FOREIGN KEY (`commander_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `national_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','ocs','officer','citizen','county_commander') NOT NULL,
  `station_id` int(11) DEFAULT NULL,
  `county_in_charge` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `id_document_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `national_id` (`national_id`),
  KEY `idx_national_id` (`national_id`),
  KEY `idx_role` (`role`),
  KEY `idx_station` (`station_id`),
  KEY `idx_users_role_station` (`role`,`station_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `officers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `badge_number` varchar(20) NOT NULL,
  `expertise_categories` text DEFAULT NULL,
  `current_case_load` int(11) DEFAULT 0,
  `total_cases_resolved` int(11) DEFAULT 0,
  `avg_resolution_time_hours` decimal(5,2) DEFAULT 0.00,
  `joined_date` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `badge_number` (`badge_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_badge` (`badge_number`),
  KEY `idx_officers_case_load` (`current_case_load`),
  CONSTRAINT `officers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `cases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ob_number` varchar(30) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(50) NOT NULL,
  `incident_location_county` varchar(50) NOT NULL,
  `incident_location_constituency` varchar(50) NOT NULL,
  `incident_local_area` varchar(100) DEFAULT NULL,
  `reported_by_citizen_id` int(11) NOT NULL,
  `reporter_county` varchar(50) NOT NULL DEFAULT '',
  `reporter_constituency` varchar(50) NOT NULL DEFAULT '',
  `reporter_local_area` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'GPS Latitude from Google Places',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'GPS Longitude from Google Places',
  `recorded_by_officer_id` int(11) NOT NULL,
  `assigned_officer_id` int(11) DEFAULT NULL,
  `station_id` int(11) NOT NULL,
  `status` enum('reported','assigned','in_progress','resolved','closed') DEFAULT 'reported',
  `estimated_resolution_hours` int(11) DEFAULT 72,
  `actual_resolution_hours` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `occurred_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ob_number` (`ob_number`),
  KEY `idx_ob_number` (`ob_number`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category`),
  KEY `idx_station` (`station_id`),
  KEY `idx_citizen` (`reported_by_citizen_id`),
  KEY `idx_assigned` (`assigned_officer_id`),
  KEY `recorded_by_officer_id` (`recorded_by_officer_id`),
  KEY `idx_cases_created_at` (`created_at`),
  KEY `idx_cases_county_category` (`incident_location_county`,`category`),
  KEY `idx_cases_assigned_at` (`assigned_at`),
  KEY `idx_cases_occurred_at` (`occurred_at`),
  KEY `idx_cases_coordinates` (`latitude`,`longitude`),
  KEY `idx_cases_incident_location` (`incident_location_county`,`incident_location_constituency`),
  CONSTRAINT `cases_ibfk_1` FOREIGN KEY (`reported_by_citizen_id`) REFERENCES `users` (`id`),
  CONSTRAINT `cases_ibfk_2` FOREIGN KEY (`recorded_by_officer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `cases_ibfk_3` FOREIGN KEY (`assigned_officer_id`) REFERENCES `officers` (`id`),
  CONSTRAINT `cases_ibfk_4` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `case_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_id` int(11) NOT NULL,
  `officer_id` int(11) NOT NULL,
  `update_text` text NOT NULL,
  `status_before` varchar(20) NOT NULL,
  `status_after` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_case` (`case_id`),
  KEY `idx_officer` (`officer_id`),
  CONSTRAINT `case_updates_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `case_updates_ibfk_2` FOREIGN KEY (`officer_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `case_evidence` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_type` varchar(10) NOT NULL,
  `uploaded_by_officer_id` int(11) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_case` (`case_id`),
  KEY `uploaded_by_officer_id` (`uploaded_by_officer_id`),
  CONSTRAINT `case_evidence_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `case_evidence_ibfk_2` FOREIGN KEY (`uploaded_by_officer_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `closure_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `requested_at` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `case_id` (`case_id`),
  KEY `requested_by` (`requested_by`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `closure_requests_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`),
  CONSTRAINT `closure_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `closure_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
