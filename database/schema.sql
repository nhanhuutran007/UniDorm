
CREATE TABLE `auth_accounts` (
  `auth_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `temporary_password` varchar(255) DEFAULT '52300232',
  `activation_token` varchar(255) DEFAULT NULL,
  `last_password_change` timestamp NOT NULL DEFAULT current_timestamp(),
  `failed_login_attempts` int(11) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `devices` (
  `device_id` int(5) NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `device_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `device_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mac_address` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('active','inactive','maintenance') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'active',
  `location` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `purchase_date` date NOT NULL,
  `last_maintenance` date DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `device_assignments` (
  `assignment_id` int(5) NOT NULL,
  `device_id` int(5) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_date` date NOT NULL,
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `status` enum('active','update','deleted','returned') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'active',
  `assigned_by_user_id` int(11) DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE `device_logs` (
  `log_id` int(11) NOT NULL,
  `device_id` int(5) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_type` enum('created','updated','maintenance','assigned','returned','status_changed') NOT NULL,
  `event_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `previous_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `device_maintenance_records` (
  `record_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `reported_by_user_id` int(11) NOT NULL,
  `performed_by_user_id` int(11) DEFAULT NULL,
  `maintenance_date` date NOT NULL,
  `completion_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled','schedule_maintenance') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `device_notifications` (
  `notification_id` int(11) NOT NULL,
  `device_id` int(5) NOT NULL,
  `notification_type` enum('inspection_due','incident_report','maintenance_due','status_change','assignment') NOT NULL,
  `message` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `target_user_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `email_verification_tokens` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(32) NOT NULL,
  `expiry_time` datetime NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('staff','technician') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiry_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `phone_number` varchar(10) DEFAULT NULL,
  `role` enum('admin','technician','staff') NOT NULL,
  `profile_picture` varchar(255) DEFAULT 'images/default.jpg',
  `birthday` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `status` enum('active','inactive','ban') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


ALTER TABLE `auth_accounts`
  ADD PRIMARY KEY (`auth_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

ALTER TABLE `devices`
  ADD PRIMARY KEY (`device_id`),
  ADD KEY `idx_device_status` (`status`),
  ADD KEY `idx_device_location` (`location`);

ALTER TABLE `device_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `assigned_by_user_id` (`assigned_by_user_id`);

ALTER TABLE `device_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `device_maintenance_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `reported_by_user_id` (`reported_by_user_id`),
  ADD KEY `performed_by_user_id` (`performed_by_user_id`);

ALTER TABLE `device_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `target_user_id` (`target_user_id`);

ALTER TABLE `email_verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`);

ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);


ALTER TABLE `auth_accounts`
  MODIFY `auth_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `devices`
  MODIFY `device_id` int(5) NOT NULL AUTO_INCREMENT;

ALTER TABLE `device_assignments`
  MODIFY `assignment_id` int(5) NOT NULL AUTO_INCREMENT;

ALTER TABLE `device_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `device_maintenance_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `device_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `email_verification_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `auth_accounts`
  ADD CONSTRAINT `fk_auth_accounts_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

ALTER TABLE `device_assignments`
  ADD CONSTRAINT `device_assignments_ibfk_3` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_auth_accounts_devices` FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`),
  ADD CONSTRAINT `fk_device_assignments_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

ALTER TABLE `device_logs`
  ADD CONSTRAINT `device_logs_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `device_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

ALTER TABLE `device_maintenance_records`
  ADD CONSTRAINT `device_maintenance_records_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`),
  ADD CONSTRAINT `device_maintenance_records_ibfk_2` FOREIGN KEY (`reported_by_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `device_maintenance_records_ibfk_3` FOREIGN KEY (`performed_by_user_id`) REFERENCES `users` (`user_id`);

ALTER TABLE `device_notifications`
  ADD CONSTRAINT `device_notifications_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `device_notifications_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

