SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `auth_accounts` (
  `auth_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `password` varchar(255) DEFAULT NULL COMMENT 'NULL khi chưa đặt mật khẩu lần đầu',
  `is_active` tinyint(1) DEFAULT 0,
  `must_change_password` tinyint(1) DEFAULT 0 COMMENT 'Yêu cầu đổi pass khi đăng nhập lần đầu',
  `activation_token` varchar(255) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `last_password_change` timestamp NOT NULL DEFAULT current_timestamp(),
  `failed_login_attempts` int(11) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `beds` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `bed_label` varchar(5) NOT NULL COMMENT 'G1, G2, G3, G4, G5, G6 (cột F GG Sheet)',
  `is_occupied` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `buildings` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL COMMENT 'VD: Tòa L',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `device_name` varchar(100) NOT NULL COMMENT 'VD: Máy lạnh, Đèn phòng, Quạt trần',
  `device_type` varchar(50) DEFAULT NULL COMMENT 'VD: Điện lạnh, Chiếu sáng, Điện dân dụng',
  `status` enum('good','broken','maintenance') NOT NULL DEFAULT 'good',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `device_reports` (
  `id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL COMMENT 'NULL nếu thiết bị chưa có trong danh sách',
  `room_id` int(11) NOT NULL COMMENT 'Phòng xảy ra sự cố',
  `reporter_id` int(11) NOT NULL COMMENT 'Sinh viên báo cáo (users.user_id)',
  `title` varchar(255) NOT NULL COMMENT 'Tiêu đề mô tả sự cố ngắn gọn',
  `description` text DEFAULT NULL COMMENT 'Mô tả chi tiết sự cố',
  `status` enum('pending','in_progress','resolved','rejected') NOT NULL DEFAULT 'pending',
  `resolved_by` int(11) DEFAULT NULL COMMENT 'Admin xử lý (users.user_id)',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_verification_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `floors` (
  `id` int(11) NOT NULL,
  `building_id` int(11) NOT NULL,
  `floor_number` int(11) NOT NULL COMMENT 'Số lầu (1, 2, 3... 10)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL COMMENT 'NULL = thông báo hệ thống tự động',
  `target_user_id` int(11) DEFAULT NULL COMMENT 'NULL = gửi đến tất cả sinh viên (broadcast)',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('general','room','maintenance','system','message') NOT NULL DEFAULT 'general',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiry_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `floor_id` int(11) NOT NULL,
  `room_code` varchar(20) NOT NULL COMMENT 'Mã phòng tự động: L.0810 (Tòa + 2 số Lầu + 2 số Phòng)',
  `max_capacity` int(11) NOT NULL DEFAULT 6 COMMENT 'Tối đa 6 sinh viên/phòng',
  `status` enum('available','full','maintenance') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `student_code` varchar(10) DEFAULT NULL COMMENT 'MSSV – chỉ dùng cho role student (VD: 42300276)',
  `username` varchar(50) NOT NULL COMMENT 'Với student: = student_code (MSSV)',
  `fullname` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL COMMENT 'Student: auto = MSSV@student.tdtu.edu.vn',
  `phone_personal` varchar(15) DEFAULT NULL COMMENT 'SĐT cá nhân',
  `phone_family` varchar(15) DEFAULT NULL COMMENT 'SĐT gia đình (cột H GG Sheet)',
  `hometown` varchar(255) DEFAULT NULL COMMENT 'Hộ khẩu thường trú (cột I GG Sheet)',
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `role` enum('admin','student') NOT NULL DEFAULT 'student',
  `bed_id` int(11) DEFAULT NULL COMMENT 'FK → beds.id – giường sinh viên đang ở',
  `is_room_leader` tinyint(1) DEFAULT 0 COMMENT 'Trưởng phòng (cột J GG Sheet: TP)',
  `profile_picture` varchar(255) DEFAULT 'assets/images/default-avatar.jpg',
  `status` enum('pending','active','inactive','banned') NOT NULL DEFAULT 'pending' COMMENT 'pending: chờ kích hoạt email lần đầu',
  `created_by` int(11) DEFAULT NULL COMMENT 'Admin tạo tài khoản (NULL nếu tự đăng ký)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `auth_accounts`
  ADD PRIMARY KEY (`auth_id`),
  ADD UNIQUE KEY `uq_user_id` (`user_id`);

ALTER TABLE `beds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_room_bed` (`room_id`,`bed_label`);

ALTER TABLE `buildings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_building_name` (`name`);

ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_id` (`room_id`);

ALTER TABLE `device_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_reporter_id` (`reporter_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_reports_device` (`device_id`),
  ADD KEY `fk_reports_resolver` (`resolved_by`);

ALTER TABLE `email_verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_token` (`token`),
  ADD UNIQUE KEY `uk_user_id` (`user_id`);

ALTER TABLE `floors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_floor` (`building_id`,`floor_number`);

ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_recipient` (`recipient_id`),
  ADD KEY `idx_conv` (`sender_id`,`recipient_id`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_target_user` (`target_user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `fk_notif_sender` (`sender_id`);

ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_room_code` (`room_code`),
  ADD KEY `idx_floor_id` (`floor_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_username` (`username`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD UNIQUE KEY `uq_student_code` (`student_code`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_users_bed` (`bed_id`);


ALTER TABLE `auth_accounts`
  MODIFY `auth_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `beds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `buildings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `device_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `email_verification_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `floors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `auth_accounts`
  ADD CONSTRAINT `fk_auth_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `beds`
  ADD CONSTRAINT `fk_beds_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

ALTER TABLE `devices`
  ADD CONSTRAINT `fk_devices_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

ALTER TABLE `device_reports`
  ADD CONSTRAINT `fk_reports_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reports_resolver` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reports_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

ALTER TABLE `email_verification_tokens`
  ADD CONSTRAINT `fk_evtoken_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `floors`
  ADD CONSTRAINT `fk_floors_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE;

ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notif_target` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_prt_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `rooms`
  ADD CONSTRAINT `fk_rooms_floor` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`id`) ON DELETE CASCADE;

ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_bed` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`) ON DELETE SET NULL;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
