-- =============================================================
-- UniDorm – Schema hoàn chỉnh (v2.0)
-- Hợp nhất schema.sql + refactor_schema.sql + messages.sql
-- Mô hình: Building → Floor → Room → Bed ← Student (users)
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- =============================================================
-- PHẦN 1: CÁC BẢNG AUTH & TÀI KHOẢN
-- =============================================================

-- Bảng người dùng hệ thống (admin, student)
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`         INT(11)           NOT NULL AUTO_INCREMENT,
  `student_code`    VARCHAR(10)       DEFAULT NULL COMMENT 'MSSV – chỉ dùng cho role student (VD: 42300276)',
  `username`        VARCHAR(50)       NOT NULL COMMENT 'Với student: = student_code (MSSV)',
  `fullname`        VARCHAR(255)      NOT NULL,
  `email`           VARCHAR(100)      NOT NULL COMMENT 'Student: auto = MSSV@student.tdtu.edu.vn',
  `phone_personal`  VARCHAR(15)       DEFAULT NULL COMMENT 'SĐT cá nhân',
  `phone_family`    VARCHAR(15)       DEFAULT NULL COMMENT 'SĐT gia đình (cột H GG Sheet)',
  `hometown`        VARCHAR(255)      DEFAULT NULL COMMENT 'Hộ khẩu thường trú (cột I GG Sheet)',
  `gender`          ENUM('male','female','other') DEFAULT NULL,
  `date_of_birth`   DATE              DEFAULT NULL,
  `role`            ENUM('admin','student') NOT NULL DEFAULT 'student',
  `bed_id`          INT(11)           DEFAULT NULL COMMENT 'FK → beds.id – giường sinh viên đang ở',
  `is_room_leader`  TINYINT(1)        DEFAULT 0 COMMENT 'Trưởng phòng (cột J GG Sheet: TP)',
  `profile_picture` VARCHAR(255)      DEFAULT 'assets/images/default.jpg',
  `status`          ENUM('pending','active','inactive','banned') NOT NULL DEFAULT 'pending'
                                      COMMENT 'pending: chờ kích hoạt email lần đầu',
  `created_by`      INT(11)           DEFAULT NULL COMMENT 'Admin tạo tài khoản (NULL nếu tự đăng ký)',
  `created_at`      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP         NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_username`     (`username`),
  UNIQUE KEY `uq_email`        (`email`),
  UNIQUE KEY `uq_student_code` (`student_code`),
  KEY `idx_role`               (`role`),
  KEY `idx_status`             (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng tài khoản xác thực (tách riêng password khỏi users)
CREATE TABLE IF NOT EXISTS `auth_accounts` (
  `auth_id`               INT(11)     NOT NULL AUTO_INCREMENT,
  `user_id`               INT(11)     NOT NULL,
  `password`              VARCHAR(255) DEFAULT NULL COMMENT 'NULL khi chưa đặt mật khẩu lần đầu',
  `is_active`             TINYINT(1)  DEFAULT 0,
  `must_change_password`  TINYINT(1)  DEFAULT 0 COMMENT 'Yêu cầu đổi pass khi đăng nhập lần đầu',
  `activation_token`      VARCHAR(255) DEFAULT NULL,
  `token_expires_at`      DATETIME    DEFAULT NULL,
  `last_password_change`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `failed_login_attempts` INT(11)     DEFAULT 0,
  `last_login`            TIMESTAMP   NULL DEFAULT NULL,
  PRIMARY KEY (`auth_id`),
  UNIQUE KEY `uq_user_id` (`user_id`),
  CONSTRAINT `fk_auth_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng token đặt lại mật khẩu
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id`          INT(11)     NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)     NOT NULL,
  `email`       VARCHAR(100) NOT NULL,
  `token`       VARCHAR(255) NOT NULL,
  `expiry_time` DATETIME    NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_prt_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- PHẦN 2: CẤU TRÚC PHÒNG Ở (Building → Floor → Room → Bed)
-- =============================================================

-- Tòa nhà / Dãy (VD: Tòa L, Tòa A...)
CREATE TABLE IF NOT EXISTS `buildings` (
  `id`          INT(11)     NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50) NOT NULL COMMENT 'VD: Tòa L',
  `description` TEXT        DEFAULT NULL,
  `created_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_building_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lầu / Tầng trong tòa nhà
CREATE TABLE IF NOT EXISTS `floors` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `building_id`   INT(11) NOT NULL,
  `floor_number`  INT(11) NOT NULL COMMENT 'Số lầu (1, 2, 3... 10)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_floor` (`building_id`, `floor_number`),
  CONSTRAINT `fk_floors_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phòng trong lầu (mỗi lầu 14 phòng)
CREATE TABLE IF NOT EXISTS `rooms` (
  `id`            INT(11)     NOT NULL AUTO_INCREMENT,
  `floor_id`      INT(11)     NOT NULL,
  `room_code`     VARCHAR(20) NOT NULL COMMENT 'Mã phòng tự động: L.0810 (Tòa + 2 số Lầu + 2 số Phòng)',
  `max_capacity`  INT(11)     NOT NULL DEFAULT 6 COMMENT 'Tối đa 6 sinh viên/phòng',
  `status`        ENUM('available','full','maintenance') NOT NULL DEFAULT 'available',
  `created_at`    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_code` (`room_code`),
  KEY `idx_floor_id` (`floor_id`),
  CONSTRAINT `fk_rooms_floor` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Giường trong phòng (mỗi phòng 6 giường: G1 → G6)
CREATE TABLE IF NOT EXISTS `beds` (
  `id`          INT(11)     NOT NULL AUTO_INCREMENT,
  `room_id`     INT(11)     NOT NULL,
  `bed_label`   VARCHAR(5)  NOT NULL COMMENT 'G1, G2, G3, G4, G5, G6 (cột F GG Sheet)',
  `is_occupied` TINYINT(1)  DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_bed` (`room_id`, `bed_label`),
  CONSTRAINT `fk_beds_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm FK từ users.bed_id → beds.id (sau khi beds đã tạo)
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_bed` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`) ON DELETE SET NULL;

-- =============================================================
-- PHẦN 3: THIẾT BỊ & BÁO CÁO HƯ HỎNG
-- =============================================================

-- Danh mục thiết bị trong phòng (máy lạnh, đèn, quạt...)
CREATE TABLE IF NOT EXISTS `devices` (
  `id`           INT(11)     NOT NULL AUTO_INCREMENT,
  `room_id`      INT(11)     NOT NULL,
  `device_name`  VARCHAR(100) NOT NULL COMMENT 'VD: Máy lạnh, Đèn phòng, Quạt trần',
  `device_type`  VARCHAR(50)  DEFAULT NULL COMMENT 'VD: Điện lạnh, Chiếu sáng, Điện dân dụng',
  `status`       ENUM('good','broken','maintenance') NOT NULL DEFAULT 'good',
  `notes`        TEXT         DEFAULT NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room_id` (`room_id`),
  CONSTRAINT `fk_devices_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Báo cáo thiết bị hỏng từ sinh viên
CREATE TABLE IF NOT EXISTS `device_reports` (
  `id`          INT(11)     NOT NULL AUTO_INCREMENT,
  `device_id`   INT(11)     DEFAULT NULL COMMENT 'NULL nếu thiết bị chưa có trong danh sách',
  `room_id`     INT(11)     NOT NULL COMMENT 'Phòng xảy ra sự cố',
  `reporter_id` INT(11)     NOT NULL COMMENT 'Sinh viên báo cáo (users.user_id)',
  `title`       VARCHAR(255) NOT NULL COMMENT 'Tiêu đề mô tả sự cố ngắn gọn',
  `description` TEXT         DEFAULT NULL COMMENT 'Mô tả chi tiết sự cố',
  `status`      ENUM('pending','in_progress','resolved','rejected') NOT NULL DEFAULT 'pending',
  `resolved_by` INT(11)     DEFAULT NULL COMMENT 'Admin xử lý (users.user_id)',
  `resolved_at` TIMESTAMP   NULL DEFAULT NULL,
  `created_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP   NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room_id`     (`room_id`),
  KEY `idx_reporter_id` (`reporter_id`),
  KEY `idx_status`      (`status`),
  CONSTRAINT `fk_reports_device`   FOREIGN KEY (`device_id`)   REFERENCES `devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_room`     FOREIGN KEY (`room_id`)     REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_resolver` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- PHẦN 4: THÔNG BÁO (Admin → Student)
-- =============================================================

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`             INT(11)     NOT NULL AUTO_INCREMENT,
  `sender_id`      INT(11)     DEFAULT NULL COMMENT 'NULL = thông báo hệ thống tự động',
  `target_user_id` INT(11)     DEFAULT NULL COMMENT 'NULL = gửi đến tất cả sinh viên (broadcast)',
  `title`          VARCHAR(255) NOT NULL,
  `message`        TEXT         NOT NULL,
  `type`           ENUM('general','room','maintenance','system','message') NOT NULL DEFAULT 'general',
  `is_read`        TINYINT(1)  DEFAULT 0,
  `created_at`     TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_target_user`  (`target_user_id`),
  KEY `idx_is_read`      (`is_read`),
  CONSTRAINT `fk_notif_sender` FOREIGN KEY (`sender_id`)      REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notif_target` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- PHẦN 5: CHAT RIÊNG TƯ (Admin ↔ Student)
-- =============================================================

CREATE TABLE IF NOT EXISTS `messages` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id`    INT(11)         NOT NULL,
  `recipient_id` INT(11)         NOT NULL,
  `content`      TEXT            NOT NULL,
  `is_read`      TINYINT(1)      DEFAULT 0,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sender`    (`sender_id`),
  KEY `idx_recipient` (`recipient_id`),
  KEY `idx_conv`      (`sender_id`, `recipient_id`),
  CONSTRAINT `fk_msg_sender`    FOREIGN KEY (`sender_id`)    REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────
-- 11. EMAIL VERIFICATION TOKENS
-- Dùng cho flow đặt mật khẩu lần đầu (student)
-- ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      NOT NULL,
  `token`      VARCHAR(128) NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token`   (`token`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  CONSTRAINT `fk_evtoken_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────
-- 12. PASSWORD RESET TOKENS (forgot password)
-- ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      NOT NULL,
  `token`      VARCHAR(128) NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `used`       TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_prt_token` (`token`),
  KEY `idx_prt_user` (`user_id`),
  CONSTRAINT `fk_prt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
