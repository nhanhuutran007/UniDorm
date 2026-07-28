-- UniDorm Schema
-- Chi chua cau truc bang, du lieu seed trong seed.sql
-- Thu tu theo FK: buildings -> floors -> rooms -> beds -> users -> con lai

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. buildings (khong FK)
DROP TABLE IF EXISTS `buildings`;
CREATE TABLE `buildings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT 'VD: Toa L',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_building_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. floors (FK -> buildings)
DROP TABLE IF EXISTS `floors`;
CREATE TABLE `floors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `building_id` int(11) NOT NULL,
  `floor_number` int(11) NOT NULL COMMENT 'So luu (1, 2, 3... 10)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_floor` (`building_id`,`floor_number`),
  CONSTRAINT `fk_floors_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. rooms (FK -> floors)
DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `floor_id` int(11) NOT NULL,
  `room_code` varchar(20) NOT NULL COMMENT 'Ma phong tu dong: L.0810 (Toa + 2 so Luu + 2 so Phong)',
  `max_capacity` int(11) NOT NULL DEFAULT 6 COMMENT 'Toi da 6 sinh vien/phong',
  `status` enum('available','full','maintenance') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_code` (`room_code`),
  KEY `idx_floor_id` (`floor_id`),
  CONSTRAINT `fk_rooms_floor` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. beds (FK -> rooms)
DROP TABLE IF EXISTS `beds`;
CREATE TABLE `beds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `bed_label` varchar(5) NOT NULL COMMENT 'G1-G6 (cot F GG Sheet)',
  `is_occupied` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_bed` (`room_id`,`bed_label`),
  CONSTRAINT `fk_beds_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. users (FK -> beds)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_code` varchar(10) DEFAULT NULL COMMENT 'MSSV - chi dung cho role student (VD: 42300276)',
  `username` varchar(50) NOT NULL COMMENT 'Voi student: = student_code (MSSV)',
  `fullname` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL COMMENT 'Student: auto = MSSV@student.tdtu.edu.vn',
  `phone_personal` varchar(15) DEFAULT NULL COMMENT 'So DT ca nhan',
  `phone_family` varchar(15) DEFAULT NULL COMMENT 'So DT gia dinh (cot H GG Sheet)',
  `hometown` varchar(255) DEFAULT NULL COMMENT 'Ho khau thuong tru (cot I GG Sheet)',
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `role` enum('admin','student') NOT NULL DEFAULT 'student',
  `bed_id` int(11) DEFAULT NULL COMMENT 'FK -> beds.id - giuong sinh vien dang o',
  `is_room_leader` tinyint(1) DEFAULT 0 COMMENT 'Truong phong (cot J GG Sheet: TP)',
  `profile_picture` varchar(255) DEFAULT 'assets/images/default-avatar.jpg',
  `status` enum('pending','active','inactive','banned') NOT NULL DEFAULT 'pending' COMMENT 'pending: chua kich hoat email lan dau',
  `created_by` int(11) DEFAULT NULL COMMENT 'Admin tao tai khoan (NULL neu tu dang ky)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_student_code` (`student_code`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  KEY `fk_users_bed` (`bed_id`),
  CONSTRAINT `fk_users_bed` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. auth_accounts (FK -> users)
DROP TABLE IF EXISTS `auth_accounts`;
CREATE TABLE `auth_accounts` (
  `auth_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `password` varchar(255) DEFAULT NULL COMMENT 'NULL = chua dat mat khau lan dau',
  `is_active` tinyint(1) DEFAULT 0,
  `must_change_password` tinyint(1) DEFAULT 0 COMMENT 'Yeu cau doi pass khi dang nhap lan dau',
  `activation_token` varchar(255) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `last_password_change` timestamp NOT NULL DEFAULT current_timestamp(),
  `failed_login_attempts` int(11) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`auth_id`),
  UNIQUE KEY `uq_user_id` (`user_id`),
  CONSTRAINT `fk_auth_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. devices (FK -> rooms)
DROP TABLE IF EXISTS `devices`;
CREATE TABLE `devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `device_name` varchar(100) NOT NULL COMMENT 'VD: May lanh, Den phong, Quat tran',
  `device_type` varchar(50) DEFAULT NULL COMMENT 'VD: Dien lanh, Chieu sang, Dien don dung',
  `status` enum('good','broken','maintenance') NOT NULL DEFAULT 'good',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_room_id` (`room_id`),
  CONSTRAINT `fk_devices_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. device_reports (FK -> devices, rooms, users)
DROP TABLE IF EXISTS `device_reports`;
CREATE TABLE `device_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) DEFAULT NULL COMMENT 'NULL neu thiet bi chua co trong danh sach',
  `room_id` int(11) NOT NULL COMMENT 'Phong xay ra su co',
  `reporter_id` int(11) NOT NULL COMMENT 'Sinh vien bao cao (users.user_id)',
  `title` varchar(255) NOT NULL COMMENT 'Tieu de mo ta su co ngan gon',
  `description` text DEFAULT NULL COMMENT 'Mo ta chi tiet su co',
  `status` enum('pending','in_progress','resolved','rejected') NOT NULL DEFAULT 'pending',
  `resolved_by` int(11) DEFAULT NULL COMMENT 'Admin xuly (users.user_id)',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_room_id` (`room_id`),
  KEY `idx_reporter_id` (`reporter_id`),
  KEY `idx_status` (`status`),
  KEY `fk_reports_device` (`device_id`),
  KEY `fk_reports_resolver` (`resolved_by`),
  CONSTRAINT `fk_reports_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_resolver` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. email_verification_tokens (FK -> users)
DROP TABLE IF EXISTS `email_verification_tokens`;
CREATE TABLE `email_verification_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  CONSTRAINT `fk_evtoken_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. messages (FK -> users)
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_recipient` (`recipient_id`),
  KEY `idx_conv` (`sender_id`,`recipient_id`),
  CONSTRAINT `fk_msg_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. notifications (FK -> users)
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) DEFAULT NULL COMMENT 'NULL = thong bao he thong tu dong',
  `target_user_id` int(11) DEFAULT NULL COMMENT 'NULL = gui den tat ca sinh vien (broadcast)',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('general','room','maintenance','system','message') NOT NULL DEFAULT 'general',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_target_user` (`target_user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `fk_notif_sender` (`sender_id`),
  CONSTRAINT `fk_notif_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notif_target` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. password_reset_tokens (FK -> users)
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `token` varchar(255) NOT NULL,
  `expiry_time` datetime DEFAULT NULL COMMENT 'Legacy expiry (AuthResetModel)',
  `expires_at` datetime DEFAULT NULL COMMENT 'Reset expiry, 1 hour',
  `used` tinyint(1) DEFAULT 0 COMMENT '1 = token da su dung',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_prt_user` (`user_id`),
  UNIQUE KEY `uk_prt_token` (`token`),
  CONSTRAINT `fk_prt_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. login_logs (FK -> users)
DROP TABLE IF EXISTS `login_logs`;
CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IPv4 hoac IPv6',
  `user_agent` varchar(512) DEFAULT NULL COMMENT 'Trinh duyet / thiet bi',
  `login_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_login_at` (`login_at`),
  CONSTRAINT `fk_loginlogs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
