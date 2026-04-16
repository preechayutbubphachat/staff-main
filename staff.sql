-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 02, 2026 at 11:21 AM
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
-- Database: `staff`
--

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `department_code` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `department_code`, `created_at`) VALUES
(1, 'ห้องฉุกเฉิน', NULL, '2026-04-01 07:49:06'),
(2, 'OPD', NULL, '2026-04-01 07:49:06'),
(3, 'IPD', NULL, '2026-04-01 07:49:22'),
(4, 'ห้องผ่าตัด', NULL, '2026-04-01 07:49:22'),
(5, 'ICU', NULL, '2026-04-01 07:49:28');

-- --------------------------------------------------------

--
-- Table structure for table `report_exports`
--

CREATE TABLE `report_exports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `filters_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters_json`)),
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `time_logs`
--

CREATE TABLE `time_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `work_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `work_hours` decimal(6,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected') NOT NULL DEFAULT 'submitted',
  `checked_by` int(11) DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `approval_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `time_logs`
--

INSERT INTO `time_logs` (`id`, `user_id`, `department_id`, `work_date`, `time_in`, `time_out`, `work_hours`, `note`, `status`, `checked_by`, `checked_at`, `signature`, `approval_note`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-04-01', '2026-04-01 16:30:00', '2026-04-02 00:30:00', 8.00, 'ทดสอบการลงเวลา', 'submitted', NULL, NULL, NULL, NULL, '2026-04-01 08:14:27', '2026-04-02 07:20:37'),
(2, 1, 1, '2026-04-01', '2026-04-01 00:30:00', '2026-04-01 08:30:00', 8.00, 'ghfghfghfghfgh', 'submitted', NULL, NULL, NULL, NULL, '2026-04-01 08:26:44', '2026-04-02 07:20:26'),
(3, 7, 1, '2026-04-02', '2026-04-02 08:30:00', '2026-04-02 16:30:00', 8.00, '', 'submitted', 9, '2026-04-02 11:46:54', 'uploads/signatures/sig_1775105060_test2.png', NULL, '2026-04-02 04:25:03', '2026-04-02 04:46:54'),
(4, 8, 1, '2026-04-02', '2026-04-02 08:30:00', '2026-04-02 16:30:00', 8.00, '', 'submitted', 9, '2026-04-02 11:46:48', 'uploads/signatures/sig_1775105060_test2.png', NULL, '2026-04-02 04:29:47', '2026-04-02 04:46:48'),
(5, 8, 1, '2026-04-02', '2026-04-02 16:30:00', '2026-04-03 00:30:00', 8.00, '', 'submitted', 9, '2026-04-02 11:46:47', 'uploads/signatures/sig_1775105060_test2.png', NULL, '2026-04-02 04:37:27', '2026-04-02 04:46:47'),
(6, 8, 1, '2026-04-02', '2026-04-02 00:30:00', '2026-04-02 08:30:00', 8.00, '', 'submitted', 9, '2026-04-02 11:46:43', 'uploads/signatures/sig_1775105060_test2.png', NULL, '2026-04-02 04:37:38', '2026-04-02 04:46:43'),
(7, 7, 1, '2026-04-02', '2026-04-02 00:30:00', '2026-04-02 08:30:00', 8.00, '', 'submitted', 2, '2026-04-02 14:21:48', 'uploads/signatures/sig_1775031058_้higthUser.png', NULL, '2026-04-02 07:09:46', '2026-04-02 07:21:48'),
(8, 2, 5, '2026-04-02', '2026-04-02 16:30:00', '2026-04-03 00:30:00', 8.00, '', 'submitted', NULL, NULL, NULL, NULL, '2026-04-02 07:36:46', NULL),
(9, 7, 1, '2026-04-02', '2026-04-02 16:30:00', '2026-04-03 00:30:00', 8.00, '', 'submitted', NULL, NULL, NULL, NULL, '2026-04-02 09:11:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `time_log_audit_trails`
--

CREATE TABLE `time_log_audit_trails` (
  `id` int(11) NOT NULL,
  `time_log_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `old_values_json` longtext DEFAULT NULL,
  `new_values_json` longtext DEFAULT NULL,
  `actor_user_id` int(11) NOT NULL,
  `actor_name_snapshot` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `time_log_audit_trails`
--

INSERT INTO `time_log_audit_trails` (`id`, `time_log_id`, `action_type`, `old_values_json`, `new_values_json`, `actor_user_id`, `actor_name_snapshot`, `created_at`, `note`) VALUES
(1, 7, 'bulk_approve', '{\"id\":7,\"user_id\":7,\"department_id\":1,\"work_date\":\"2026-04-02\",\"time_in\":\"2026-04-02 00:30:00\",\"time_out\":\"2026-04-02 08:30:00\",\"work_hours\":\"8.00\",\"note\":\"\",\"status\":\"submitted\",\"checked_by\":null,\"checked_at\":null,\"signature\":null,\"approval_note\":null,\"created_at\":\"2026-04-02 14:09:46\",\"updated_at\":null,\"fullname\":\"ปรีชายุทธ บุปผาชาติ\",\"position_name\":\"tester\",\"department_name\":\"ห้องฉุกเฉิน\",\"checker_name\":null}', '{\"id\":7,\"user_id\":7,\"department_id\":1,\"work_date\":\"2026-04-02\",\"time_in\":\"2026-04-02 00:30:00\",\"time_out\":\"2026-04-02 08:30:00\",\"work_hours\":\"8.00\",\"note\":\"\",\"status\":\"submitted\",\"checked_by\":2,\"checked_at\":\"2026-04-02 14:21:48\",\"signature\":\"uploads/signatures/sig_1775031058_้higthUser.png\",\"approval_note\":null,\"created_at\":\"2026-04-02 14:09:46\",\"updated_at\":\"2026-04-02 14:21:48\",\"fullname\":\"ปรีชายุทธ บุปผาชาติ\",\"position_name\":\"tester\",\"department_name\":\"ห้องฉุกเฉิน\",\"checker_name\":\"ทดสอบ ผู้ตรวจสอบ\"}', 2, 'ทดสอบ ผู้ตรวจสอบ', '2026-04-02 07:21:48', 'อนุมัติรายการจากหน้าคิวตรวจสอบแบบเลือกหลายรายการ');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `position_name` varchar(100) DEFAULT NULL,
  `phone_number` varchar(30) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `role` enum('admin','staff','finance','checker') NOT NULL DEFAULT 'staff',
  `can_view_all_staff` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_department_reports` tinyint(1) NOT NULL DEFAULT 0,
  `can_export_reports` tinyint(1) NOT NULL DEFAULT 0,
  `can_approve_logs` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_time_logs` tinyint(1) NOT NULL DEFAULT 0,
  `can_edit_locked_time_logs` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_user_permissions` tinyint(1) NOT NULL DEFAULT 0,
  `signature_path` varchar(255) DEFAULT NULL,
  `profile_image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `position_name`, `phone_number`, `department_id`, `role`, `can_view_all_staff`, `can_view_department_reports`, `can_export_reports`, `can_approve_logs`, `can_manage_time_logs`, `can_edit_locked_time_logs`, `can_manage_user_permissions`, `signature_path`, `profile_image_path`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$gxeJBjGDcYVX6qVkQ8Uciuz2U1er3P47sTNT3FVlvs6TTN5GMFwDy', 'ปรีชายุทธ บุปผาชาติ', NULL, NULL, 1, 'admin', 1, 1, 1, 1, 1, 1, 1, 'sig_1775030486_admin.png', 'profile_1775119508_69ce2c946848e1.14449180.png', 1, '2026-04-01 08:01:26', '2026-04-02 08:45:08'),
(2, '้higthUser', '$2y$10$Ab1NxDHAV.INWzUwM7.BgeTVDTjXxS327BUf6Zju0R3nibhmZyp4u', 'ทดสอบ ผู้ตรวจสอบ', NULL, NULL, 5, 'checker', 1, 1, 1, 1, 1, 0, 0, 'sig_1775031058_้higthUser.png', NULL, 1, '2026-04-01 08:10:58', '2026-04-02 07:08:08'),
(3, 'test02', '$2y$10$AXekCURpoPuNv/T2ByihSuVifWFlBHnuJYwoHEsu7PA0rQW8JifEO', 'ปรีชายุทธ บุปผาชาติ', NULL, NULL, 1, 'finance', 1, 1, 0, 0, 0, 0, 0, 'sig_1775031620_test02.png', NULL, 1, '2026-04-01 08:20:20', NULL),
(4, '้test03', '$2y$10$N/gIGt3vYTjdjWrM9HMNnOtFCrYr66mSQ9Bd/TLb2aX4y2lge6Rmi', 'ปรีชายุทธ บุปผาชาติ', NULL, NULL, 3, 'finance', 1, 1, 0, 0, 0, 0, 0, 'sig_1775031836_้test03.png', NULL, 1, '2026-04-01 08:23:56', NULL),
(7, 'test01', '$2y$10$noOADQz3ATP25lk4a1ZIGeRMlhW0csNWiH1BhJiQAIGDxxwdBzIRu', 'ปรีชายุทธ เทส02', 'tester', '0971284459', 1, 'staff', 0, 0, 0, 0, 0, 0, 0, 'sig_1775103844_twestttstest.png', NULL, 1, '2026-04-02 04:24:04', '2026-04-02 08:55:57'),
(8, 'test', '$2y$10$j5mUq94bfM.jQevWv9sLS.BJ7NifK2frSbfZkr2ENhZwqqos0fFsy', 'tester', 'testpoint', '0000000', 1, 'staff', 0, 0, 0, 0, 0, 0, 0, 'sig_1775103960_test.png', NULL, 1, '2026-04-02 04:26:00', NULL),
(9, 'test2', '$2y$10$H3ehVO7OAhyiAJd15WhFouHaJk4.o1JGTcYDIV.717Elac31bzNaS', 'testt', '123456', '00000', 3, 'checker', 1, 1, 1, 1, 1, 0, 0, 'sig_1775105060_test2.png', NULL, 1, '2026-04-02 04:44:20', '2026-04-02 07:08:08'),
(10, 'user02', '$2y$10$emb9jJTn7V2pnSWHBrsD2.KvJKg0cj.kejF5hGNZg0sfmEngEawH2', 'ปรีชายุทธ บุปผาชาติ', 'tester10101010', '0520486', 1, 'staff', 0, 0, 0, 0, 0, 0, 0, 'sig_1775121292_user02.png', NULL, 1, '2026-04-02 09:14:52', NULL),
(11, 'test10', '$2y$10$V94fwgfV8ai83xaOCDeZ6eUeriWXxOVNatI5/4NznXNx2zazcP9ja', 'หกดเวหกเาวสหกา', 'การคลัง', '101010101010', 3, 'finance', 1, 0, 1, 0, 0, 0, 0, NULL, NULL, 1, '2026-04-02 09:21:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_permission_audit_trails`
--

CREATE TABLE `user_permission_audit_trails` (
  `id` int(11) NOT NULL,
  `target_user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `old_values_json` longtext DEFAULT NULL,
  `new_values_json` longtext DEFAULT NULL,
  `actor_user_id` int(11) NOT NULL,
  `actor_name_snapshot` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `report_exports`
--
ALTER TABLE `report_exports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_report_exports_user` (`user_id`);

--
-- Indexes for table `time_logs`
--
ALTER TABLE `time_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_time_logs_user` (`user_id`),
  ADD KEY `fk_time_logs_checked_by` (`checked_by`),
  ADD KEY `fk_time_logs_department` (`department_id`),
  ADD KEY `idx_time_logs_work_date` (`work_date`);

--
-- Indexes for table `time_log_audit_trails`
--
ALTER TABLE `time_log_audit_trails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_time_log_audit_time_log_id` (`time_log_id`),
  ADD KEY `idx_time_log_audit_actor_user_id` (`actor_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_users_department` (`department_id`),
  ADD KEY `idx_users_fullname` (`fullname`),
  ADD KEY `idx_users_position_name` (`position_name`),
  ADD KEY `idx_users_role` (`role`);

--
-- Indexes for table `user_permission_audit_trails`
--
ALTER TABLE `user_permission_audit_trails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_permission_audit_target_user_id` (`target_user_id`),
  ADD KEY `idx_user_permission_audit_actor_user_id` (`actor_user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `report_exports`
--
ALTER TABLE `report_exports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `time_logs`
--
ALTER TABLE `time_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `time_log_audit_trails`
--
ALTER TABLE `time_log_audit_trails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_permission_audit_trails`
--
ALTER TABLE `user_permission_audit_trails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `report_exports`
--
ALTER TABLE `report_exports`
  ADD CONSTRAINT `fk_report_exports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `time_logs`
--
ALTER TABLE `time_logs`
  ADD CONSTRAINT `fk_time_logs_checked_by` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_time_logs_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_time_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `time_log_audit_trails`
--
ALTER TABLE `time_log_audit_trails`
  ADD CONSTRAINT `fk_time_log_audit_actor_user` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_time_log_audit_time_log` FOREIGN KEY (`time_log_id`) REFERENCES `time_logs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `user_permission_audit_trails`
--
ALTER TABLE `user_permission_audit_trails`
  ADD CONSTRAINT `fk_user_permission_audit_actor_user` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_user_permission_audit_target_user` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- --------------------------------------------------------
--
-- Back-office database admin module support
--
ALTER TABLE users
  ADD COLUMN can_manage_database tinyint(1) NOT NULL DEFAULT 0 AFTER can_manage_user_permissions;

UPDATE users
SET can_manage_database = 1
WHERE ole = 'admin';

ALTER TABLE users
  ADD KEY idx_users_can_manage_database (can_manage_database);

CREATE TABLE db_admin_audit_logs (
  id int(11) NOT NULL,
  	able_name varchar(100) NOT NULL,
  ow_primary_key varchar(100) NOT NULL,
  ction_type varchar(20) NOT NULL,
  old_values_json longtext DEFAULT NULL,
  
ew_values_json longtext DEFAULT NULL,
  ctor_user_id int(11) NOT NULL,
  ctor_name_snapshot varchar(100) NOT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  
ote varchar(255) DEFAULT NULL,
  equest_context longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE db_admin_audit_logs
  ADD PRIMARY KEY (id),
  ADD KEY idx_db_admin_audit_table_name (	able_name),
  ADD KEY idx_db_admin_audit_actor_user_id (ctor_user_id),
  ADD KEY idx_db_admin_audit_created_at (created_at);

ALTER TABLE db_admin_audit_logs
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE db_admin_audit_logs
  ADD CONSTRAINT k_db_admin_audit_actor_user FOREIGN KEY (ctor_user_id) REFERENCES users (id);

-- --------------------------------------------------------
--
-- Name split compatibility support
--
ALTER TABLE users
  ADD COLUMN first_name varchar(100) DEFAULT NULL AFTER fullname,
  ADD COLUMN last_name varchar(100) DEFAULT NULL AFTER first_name;

ALTER TABLE users
  ADD KEY idx_users_first_name (first_name),
  ADD KEY idx_users_last_name (last_name);

-- --------------------------------------------------------
--
-- Notifications support
--
CREATE TABLE notifications (
  id INT(11) NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(150) NOT NULL,
  message VARCHAR(255) NOT NULL,
  target_url VARCHAR(255) DEFAULT NULL,
  target_entity_type VARCHAR(50) DEFAULT NULL,
  target_entity_id INT(11) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME DEFAULT NULL,
  metadata_json LONGTEXT DEFAULT NULL,
  source_type VARCHAR(30) NOT NULL DEFAULT 'system',
  actor_user_id INT(11) DEFAULT NULL,
  priority VARCHAR(20) NOT NULL DEFAULT 'normal',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user_read_created (user_id, is_read, created_at),
  KEY idx_notifications_type_target (type, target_entity_type, target_entity_id),
  KEY idx_notifications_actor_user (actor_user_id),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_actor_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
