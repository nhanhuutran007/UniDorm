-- UniDorm Seed Data
-- Chay SAU schema.sql. An toan de chay nhieu lan (dung ON DUPLICATE KEY UPDATE).

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- 1. buildings (khong co FK)
INSERT INTO `buildings` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Tòa L', 'Khu nhà ở sinh viên Tòa L – Trường Đại học Tôn Đức Thắng', '2026-03-31 05:44:36')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- 2. floors (FK -> buildings)
INSERT INTO `floors` (`id`, `building_id`, `floor_number`) VALUES
(8, 1, 8)
ON DUPLICATE KEY UPDATE `building_id` = VALUES(`building_id`);

-- 3. rooms (FK -> floors)
INSERT INTO `rooms` (`id`, `floor_id`, `room_code`, `max_capacity`, `status`, `created_at`) VALUES
(99, 8, 'L.0801', 6, 'available', '2026-03-31 05:44:37'),
(100, 8, 'L.0802', 6, 'available', '2026-03-31 05:44:37'),
(101, 8, 'L.0803', 6, 'available', '2026-03-31 05:44:37'),
(102, 8, 'L.0804', 6, 'available', '2026-03-31 05:44:37'),
(103, 8, 'L.0805', 6, 'available', '2026-03-31 05:44:37'),
(104, 8, 'L.0806', 6, 'available', '2026-03-31 05:44:37'),
(105, 8, 'L.0807', 6, 'available', '2026-03-31 05:44:37'),
(106, 8, 'L.0808', 6, 'available', '2026-03-31 05:44:37'),
(107, 8, 'L.0809', 6, 'available', '2026-03-31 05:44:37'),
(108, 8, 'L.0810', 6, 'available', '2026-03-31 05:44:37'),
(109, 8, 'L.0811', 6, 'available', '2026-03-31 05:44:37'),
(110, 8, 'L.0812', 6, 'available', '2026-03-31 05:44:37'),
(111, 8, 'L.0813', 6, 'available', '2026-03-31 05:44:37'),
(112, 8, 'L.0814', 6, 'available', '2026-03-31 05:44:37')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 4. beds (FK -> rooms)
INSERT INTO `beds` (`id`, `room_id`, `bed_label`, `is_occupied`) VALUES
(614, 103, 'G2', 0), (615, 103, 'G3', 0), (616, 103, 'G4', 0),
(617, 103, 'G5', 0), (618, 103, 'G6', 0),
(619, 104, 'G1', 0), (620, 104, 'G2', 0), (621, 104, 'G3', 0),
(622, 104, 'G4', 0), (623, 104, 'G5', 0), (624, 104, 'G6', 0),
(625, 105, 'G1', 0), (626, 105, 'G2', 0), (627, 105, 'G3', 0),
(628, 105, 'G4', 0), (629, 105, 'G5', 0), (630, 105, 'G6', 0),
(631, 106, 'G1', 0), (632, 106, 'G2', 0), (633, 106, 'G3', 0),
(634, 106, 'G4', 0), (635, 106, 'G5', 0), (636, 106, 'G6', 0),
(637, 107, 'G1', 0), (638, 107, 'G2', 0), (639, 107, 'G3', 0),
(640, 107, 'G4', 0), (641, 107, 'G5', 0), (642, 107, 'G6', 0),
(643, 108, 'G1', 0), (644, 108, 'G2', 0), (645, 108, 'G3', 0),
(646, 108, 'G4', 0), (647, 108, 'G5', 0), (648, 108, 'G6', 0),
(649, 109, 'G1', 0), (650, 109, 'G2', 0), (651, 109, 'G3', 0),
(652, 109, 'G4', 0), (653, 109, 'G5', 0), (654, 109, 'G6', 0),
(655, 110, 'G1', 0), (656, 110, 'G2', 0), (657, 110, 'G3', 0),
(658, 110, 'G4', 0), (659, 110, 'G5', 0), (660, 110, 'G6', 0),
(661, 111, 'G1', 0), (662, 111, 'G2', 0), (663, 111, 'G3', 0),
(664, 111, 'G4', 0), (665, 111, 'G5', 0), (666, 111, 'G6', 0),
(667, 112, 'G1', 0), (668, 112, 'G2', 0), (669, 112, 'G3', 0),
(670, 112, 'G4', 0), (671, 112, 'G5', 0), (672, 112, 'G6', 0)
ON DUPLICATE KEY UPDATE `is_occupied` = VALUES(`is_occupied`);

-- 5. users (FK -> beds)
INSERT INTO `users` (`user_id`, `student_code`, `username`, `fullname`, `email`, `phone_personal`, `phone_family`, `hometown`, `gender`, `date_of_birth`, `role`, `bed_id`, `is_room_leader`, `profile_picture`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, NULL, 'admin', 'Quản trị viên', 'admin@unidorm.edu.vn', '', '', '', '', '0000-00-00', 'admin', NULL, 0, 'assets/img/user/avatar_1_1775323978.jpeg', 'active', NULL, '2026-03-31 05:44:36', '2026-04-04 17:32:58')
ON DUPLICATE KEY UPDATE `fullname` = VALUES(`fullname`), `status` = VALUES(`status`);

-- 6. auth_accounts (FK -> users)
INSERT INTO `auth_accounts` (`auth_id`, `user_id`, `password`, `is_active`, `must_change_password`, `activation_token`, `token_expires_at`, `last_password_change`, `failed_login_attempts`, `last_login`) VALUES
(1, 1, '$2y$10$oA7blHDrrJLg1/3XlkAcDeXcZOqGMkeewxKoch4VhxzsNfzPqROTu', 1, 0, NULL, NULL, '2026-03-31 05:44:36', 0, NULL)
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `is_active` = VALUES(`is_active`);

-- 7. devices (FK -> rooms)
INSERT INTO `devices` (`id`, `room_id`, `device_name`, `device_type`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 99, 'Máy lạnh', 'Điện lạnh', 'good', NULL, '2026-03-31 05:44:38', NULL),
(2, 99, 'Đèn phòng', 'Chiếu sáng', 'good', NULL, '2026-03-31 05:44:38', NULL),
(3, 99, 'Quạt trần', 'Điện dân dụng', 'good', NULL, '2026-03-31 05:44:38', NULL),
(4, 99, 'Bóng đèn toilet', 'Chiếu sáng', 'broken', NULL, '2026-03-31 05:44:38', NULL),
(5, 99, 'Vòi nước', 'Nước', 'good', NULL, '2026-03-31 05:44:38', NULL)
ON DUPLICATE KEY UPDATE `device_name` = VALUES(`device_name`), `status` = VALUES(`status`);

SET FOREIGN_KEY_CHECKS = 1;
