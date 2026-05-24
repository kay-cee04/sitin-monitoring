-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 24, 2026 at 02:01 PM
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
-- Database: `ccs_sitin`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '$2y$10$dK/GUGjafyRK1uWuHs8oQewYLnuN1Hsth4v8qT666Cf/XgTX93cRe', '2026-03-20 07:31:33');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `admin_name` varchar(100) NOT NULL DEFAULT 'CCS Admin',
  `content` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `admin_name`, `content`, `created_at`) VALUES
(3, 'admin', 'Important Announcement We are excited to announce the launch of our new website! 🎉 Explore our latest products and services now!', '2026-04-12 09:20:50'),
(5, 'admin', 'Please Observe Silence!', '2026-04-12 09:22:03'),
(6, 'admin', 'Thank you!', '2026-04-17 15:21:55'),
(7, 'admin', 'Hi', '2026-05-23 12:07:05');

-- --------------------------------------------------------

--
-- Table structure for table `ccs_labs`
--

CREATE TABLE `ccs_labs` (
  `id` int(11) NOT NULL,
  `lab_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `total_slots` int(11) DEFAULT 50,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ccs_labs`
--

INSERT INTO `ccs_labs` (`id`, `lab_name`, `is_active`, `total_slots`, `updated_at`) VALUES
(1, 'Lab 517', 1, 50, '2026-05-20 04:26:16'),
(2, 'Lab 524', 1, 50, '2026-05-20 04:26:16'),
(3, 'Lab 526', 1, 50, '2026-05-20 04:26:16'),
(4, 'Lab 528', 1, 50, '2026-05-20 04:26:16'),
(5, 'Lab 530', 1, 50, '2026-05-20 04:26:16'),
(6, 'Lab 542', 1, 50, '2026-05-20 04:26:16'),
(7, 'Lab 544', 1, 50, '2026-05-20 04:26:16');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `sitin_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `admin_feedback` text NOT NULL,
  `admin_name` varchar(100) DEFAULT 'CCS Admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_software`
--

CREATE TABLE `lab_software` (
  `id` int(11) NOT NULL,
  `lab_name` varchar(100) NOT NULL,
  `software` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT 'General',
  `is_available` tinyint(1) DEFAULT 1,
  `added_by` varchar(100) DEFAULT 'Admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_software`
--

INSERT INTO `lab_software` (`id`, `lab_name`, `software`, `category`, `is_available`, `added_by`, `created_at`) VALUES
(1, 'Lab 530', 'MAC Software', 'Programming IDE', 1, 'admin', '2026-05-16 16:05:19');

-- --------------------------------------------------------

--
-- Table structure for table `lab_status`
--

CREATE TABLE `lab_status` (
  `lab_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_status`
--

INSERT INTO `lab_status` (`lab_name`, `is_active`, `updated_at`) VALUES
('Lab 524', 1, '2026-05-23 11:49:19'),
('Lab 544', 1, '2026-05-23 11:49:31');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `student_id`, `message`, `is_read`, `created_at`) VALUES
(29, 8, '📤 Your sit-in session has been logged out by admin. Remaining sessions: 29', 1, '2026-05-17 14:51:39'),
(30, 8, '📤 Your sit-in session has been logged out by admin at 22:51', 1, '2026-05-17 14:51:39'),
(31, 7, '📢 New announcement from admin: Hi', 0, '2026-05-23 12:07:05'),
(32, 10, '📢 New announcement from admin: Hi', 0, '2026-05-23 12:07:05'),
(33, 9, '📢 New announcement from admin: Hi', 0, '2026-05-23 12:07:05'),
(34, 11, '📢 New announcement from admin: Hi', 0, '2026-05-23 12:07:05'),
(35, 12, '📢 New announcement from admin: Hi', 1, '2026-05-23 12:07:05'),
(36, 8, '📢 New announcement from admin: Hi', 1, '2026-05-23 12:07:05'),
(37, 13, '📤 You have been logged out of your sit-in session by the admin.', 0, '2026-05-24 10:56:21');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `laboratory` varchar(50) NOT NULL,
  `time_in` time DEFAULT NULL,
  `date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pc_number` varchar(20) DEFAULT NULL,
  `arrived` tinyint(1) DEFAULT 0,
  `arrived_at` datetime DEFAULT NULL,
  `absent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `student_id`, `id_number`, `purpose`, `laboratory`, `time_in`, `date`, `status`, `created_at`, `pc_number`, `arrived`, `arrived_at`, `absent`) VALUES
(2, 8, '23784630', 'Capstone', 'Lab 517', '13:00:00', '2026-05-27', 'approved', '2026-05-17 14:53:11', NULL, 0, NULL, 0),
(3, 9, '23770000', 'Research', 'Lab 544', '14:30:00', '2026-05-29', 'rejected', '2026-05-21 11:55:57', NULL, 0, NULL, 0),
(4, 10, '23700001', 'Networking', 'Lab 544', '15:40:00', '2026-05-30', 'pending', '2026-05-23 11:53:58', NULL, 0, NULL, 0),
(5, 9, '23770000', 'Activity submission', 'Lab 526', '16:00:00', '2026-05-24', 'pending', '2026-05-23 11:59:58', NULL, 0, NULL, 0),
(6, 12, '23770003', 'Capstone', 'Lab 530', '10:30:00', '2026-05-30', 'pending', '2026-05-23 12:08:15', NULL, 0, NULL, 0),
(7, 13, '23770004', 'Assignment', 'Lab 530', '09:30:00', '2026-05-28', 'pending', '2026-05-24 10:44:45', NULL, 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `sit_in_history`
--

CREATE TABLE `sit_in_history` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `id_number` varchar(20) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `sit_purpose` varchar(255) NOT NULL,
  `laboratory` varchar(50) NOT NULL,
  `login_time` time DEFAULT NULL,
  `logout_time` time DEFAULT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_at_login` int(11) DEFAULT NULL,
  `session_at_logout` int(11) DEFAULT NULL,
  `sessions_used` int(11) DEFAULT 1,
  `is_walkin` tinyint(4) DEFAULT 0,
  `pc_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sit_in_history`
--

INSERT INTO `sit_in_history` (`id`, `student_id`, `id_number`, `fullname`, `sit_purpose`, `laboratory`, `login_time`, `logout_time`, `date`, `created_at`, `session_at_login`, `session_at_logout`, `sessions_used`, `is_walkin`, `pc_number`) VALUES
(23, NULL, '23788938', 'Alber Torres', 'Php', '530', '23:00:50', '23:01:00', '2026-05-08', '2026-05-08 15:00:50', NULL, NULL, 1, 0, NULL),
(31, 8, '23784630', 'Kathleen Daclan Sarmiento', '', '', '22:37:39', '22:51:00', '2026-05-17', '2026-05-17 14:37:39', 30, NULL, 1, 0, 'PC-23'),
(32, 9, '23770000', 'Tamsin Gold Reed', 'Online Class', 'Lab 542', '11:56:05', '11:59:16', '2026-05-20', '2026-05-20 03:56:05', 30, NULL, 1, 0, 'PC-24'),
(33, 10, '23700001', 'Nate  Lim', 'C# Programming', 'Lab 544', '19:52:40', '19:56:01', '2026-05-23', '2026-05-23 11:52:40', 30, NULL, 1, 0, NULL),
(34, 11, '23770002', 'Nancy  Jones', 'Thesis / Capstone', 'Lab 528', '20:04:19', '18:45:57', '2026-05-23', '2026-05-23 12:04:19', 30, NULL, 1, 0, NULL),
(35, 12, '23770003', 'Lyra  Canines', '', 'Lab 528', '20:06:31', '20:10:01', '2026-05-23', '2026-05-23 12:06:31', 30, NULL, 1, 0, NULL),
(36, NULL, '23770004', 'Alex Maiks', 'C Programming', 'Lab 526', '20:09:32', '18:46:34', '2026-05-23', '2026-05-23 12:09:32', NULL, NULL, 1, 0, NULL),
(37, 13, '23770004', 'Lucas  Brody', 'C Programming', 'Lab 526', '18:43:01', '18:56:21', '2026-05-24', '2026-05-24 10:43:01', 30, NULL, 1, 0, 'PC-30');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT '',
  `course` varchar(20) NOT NULL,
  `year_level` tinyint(4) NOT NULL DEFAULT 1,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT '',
  `session` int(11) NOT NULL DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_photo` varchar(255) DEFAULT NULL,
  `sit_purpose` varchar(100) DEFAULT NULL,
  `laboratory` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `id_number`, `lastname`, `firstname`, `middlename`, `course`, `year_level`, `email`, `password`, `address`, `session`, `created_at`, `profile_photo`, `sit_purpose`, `laboratory`) VALUES
(7, '12345678', 'Diha', 'Padayon', '', 'BSCS', 4, 'padayondiha@gmail.com', '$2y$10$2SBAw8PbI9PEk1Adlenfu.5w7X1qg5N7mNYjiVYQWBd2qEFLeVwxK', 'Cebu City', 30, '2026-05-17 13:22:10', NULL, NULL, NULL),
(8, '23784630', 'Sarmiento', 'Kathleen', 'Daclan', 'BSIT', 3, 'daclankath.23@gmail.com', '$2y$10$yJm1xSvWsCMHmA9RMERNg.B/RPeOitnUvo0vRiaHGqrD04eNQ4Mae', 'Carcar City', 29, '2026-05-17 14:37:39', 'profile_8_1779029069.png', 'Thesis / Capstone', 'Lab 542'),
(9, '23770000', 'Reed', 'Tamsin', 'Gold', 'BSCS', 1, 'reed@gmail.com', '$2y$10$Bd8RM2tMMF9ANm.4AsteQuZOi1toOmYtqUF4iF3sRufgiMkIQqWCq', 'Lahug, Cebu City', 30, '2026-05-20 03:56:05', 'profile_9_1779251114.jpeg', 'Online Class', 'Lab 542'),
(10, '23700001', 'Lim', 'Nate', '', 'BSCS', 2, 'nate@example.com', '$2y$10$q9ztyxV5AA0NjM2vF/zzF.cto0yRMuCPvDwdwDiWFncqo8.664Yym', 'Mandaue City', 30, '2026-05-23 11:52:40', 'profile_10_1779537259.jpeg', 'C# Programming', 'Lab 544'),
(11, '23770002', 'Jones', 'Nancy', '', 'BSCS', 4, 'jones@example.com', '$2y$10$ygI3C7L.FR.zfRjlq3IIaumofb1xfwDAkGj7XGIeKF7KzeiObwEoe', 'Cebu City', 30, '2026-05-23 12:04:18', NULL, 'Thesis / Capstone', 'Lab 528'),
(12, '23770003', 'Canines', 'Lyra', '', 'BSCS', 4, 'canines@example.com', '$2y$10$YveDLowClPm2v7GS4WXfnu/UyZhoEuzwMy2ZZEnFGrgD2sokl5bne', 'Ceby City', 30, '2026-05-23 12:06:31', 'profile_12_1779538051.jpeg', 'Thesis / Capstone', 'Lab 528'),
(13, '23770004', 'Brody', 'Lucas', '', 'BSIT', 1, 'lucas@example.com', '$2y$10$5bR/fQzxZvtAOsRxvoyt8.jeCvm2wXp.Pw4HdFcfCiP/4DEWhPH8i', 'Tisa, Cebu City', 29, '2026-05-24 10:43:00', NULL, 'C Programming', 'Lab 526');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('reservations_open', '1', '2026-05-21 11:54:13'),
('slots_Lab_524', '50', '2026-05-23 11:31:31'),
('slots_Lab_528', '20', '2026-05-23 11:49:26');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `course` varchar(100) DEFAULT '',
  `message` text NOT NULL,
  `rating` tinyint(1) DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_photo` varchar(255) DEFAULT NULL,
  `is_hidden` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`id`, `student_id`, `fullname`, `course`, `message`, `rating`, `created_at`, `profile_photo`, `is_hidden`) VALUES
(6, 7, 'Padayon  Diha', 'BSCS', 'okay', 1, '2026-05-17 13:25:09', NULL, 0),
(7, 8, 'Kathleen Daclan Sarmiento', 'BSIT', 'Hello', 5, '2026-05-17 14:50:00', 'profile_8_1779029069.png', 0),
(8, 9, 'Tamsin Gold Reed', 'BSCS', 'Beautiful', 4, '2026-05-20 04:25:53', 'profile_9_1779251114.jpeg', 0),
(9, 12, 'Lyra  Canines', 'BSCS', 'amazing', 5, '2026-05-23 12:10:38', 'profile_12_1779538051.jpeg', 0);

-- --------------------------------------------------------

--
-- Table structure for table `walk_in_sessions`
--

CREATE TABLE `walk_in_sessions` (
  `id` int(11) NOT NULL,
  `total_sessions` int(11) NOT NULL DEFAULT 100,
  `used_sessions` int(11) NOT NULL DEFAULT 0,
  `remaining` int(11) NOT NULL DEFAULT 100,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `walk_in_sessions`
--

INSERT INTO `walk_in_sessions` (`id`, `total_sessions`, `used_sessions`, `remaining`, `updated_at`) VALUES
(1, 100, 0, 100, '2026-05-08 15:11:45');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ccs_labs`
--
ALTER TABLE `ccs_labs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lab_name` (`lab_name`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sitin_id` (`sitin_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `lab_software`
--
ALTER TABLE `lab_software`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lab_status`
--
ALTER TABLE `lab_status`
  ADD PRIMARY KEY (`lab_name`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `sit_in_history`
--
ALTER TABLE `sit_in_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `walk_in_sessions`
--
ALTER TABLE `walk_in_sessions`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `ccs_labs`
--
ALTER TABLE `ccs_labs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=196;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `lab_software`
--
ALTER TABLE `lab_software`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `sit_in_history`
--
ALTER TABLE `sit_in_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `walk_in_sessions`
--
ALTER TABLE `walk_in_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`sitin_id`) REFERENCES `sit_in_history` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sit_in_history`
--
ALTER TABLE `sit_in_history`
  ADD CONSTRAINT `sit_in_history_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD CONSTRAINT `testimonials_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
