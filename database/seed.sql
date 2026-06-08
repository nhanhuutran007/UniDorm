-- =============================================================
-- UniDorm – Dữ liệu mẫu (seed.sql)
-- Tòa L: 10 lầu × 14 phòng × 6 giường (G1–G6)
-- Mã phòng: L.LLPP (LL = số lầu 2 chữ số, PP = số phòng 2 chữ số)
-- VD: Tòa L, Lầu 8, Phòng 10 → L.0810
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- -------------------------------------------------------
-- 1. TÀI KHOẢN ADMIN MẶC ĐỊNH
-- -------------------------------------------------------
INSERT IGNORE INTO `users` (`student_code`, `username`, `fullname`, `email`, `role`, `status`, `created_by`) VALUES
(NULL, 'admin', 'Quản trị viên', 'admin@unidorm.edu.vn', 'admin', 'active', NULL);

-- Auth cho admin (pass: Admin@123 – đã hash bằng bcrypt)
INSERT IGNORE INTO `auth_accounts` (`user_id`, `password`, `is_active`, `must_change_password`) VALUES
(1, '$2y$10$H/QTSR89DMmT4.rh8Iw68.IPPOPn/1zSV4LJ0mrJNbbOIbGNaGlQ5i', 1, 0);
-- NOTE: Hash trên = 'Admin@123'. Đổi ngay sau khi deploy!

-- -------------------------------------------------------
-- 2. SINH VIÊN MẪU (dựa trên GG Sheet Tòa L, Lầu 8)
-- -------------------------------------------------------
INSERT IGNORE INTO `users` (`student_code`, `username`, `fullname`, `email`, `phone_personal`, `phone_family`, `hometown`, `role`, `status`, `is_room_leader`) VALUES
('42300276', '42300276', 'Đặng Đình Đức',          '42300276@student.tdtu.edu.vn', '0815339238', '0909179238', 'Bà Rịa Vũng Tàu', 'student', 'active', 0),
('42400284', '42400284', 'Trần Minh Huấn',          '42400284@student.tdtu.edu.vn', '0388433143', '0349066224', 'An Giang',         'student', 'active', 0),
('42400301', '42400301', 'Nguyễn Hồ Hồng Kỳ',       '42400301@student.tdtu.edu.vn', '0328250283', '0774551979', 'Bình Định',        'student', 'active', 0),
('42400299', '42400299', 'Nguyễn Hồ Đăng Khoa',     '42400299@student.tdtu.edu.vn', '0768860126', '0988187786', 'Đồng Tháp',        'student', 'active', 0),
('52500238', '52500238', 'Võ Quốc Trung',            '52500238@student.tdtu.edu.vn', '0355501716', '0140408488', 'Đồng Tháp',        'student', 'active', 0),
('824H0124', '824H0124', 'Lê Kế Khương',             '824H0124@student.tdtu.edu.vn', '0363410045', '0979461240', 'Bình Định',        'student', 'active', 1),
-- Lầu 8, Phòng 02
('52300049', '52300049', 'Nguyễn Văn Phát',          '52300049@student.tdtu.edu.vn', '0835383638', '0333609998', 'Tây Ninh',         'student', 'active', 0),
('52300164', '52300164', 'Lương Lê Nhân Trí',        '52300164@student.tdtu.edu.vn', '0975767036', '0947992839', 'Bến Tre',          'student', 'active', 0),
('52300169', '52300169', 'Lê Phú Vinh',              '52300169@student.tdtu.edu.vn', '0835651489', '0949157776', 'Đồng Tháp',        'student', 'active', 0),
('52300191', '52300191', 'Phạm Tiến Dũng',           '52300191@student.tdtu.edu.vn', '0837721777', '0834226007', 'Đồng Tháp',        'student', 'active', 1),
('92300111', '92300111', 'Cao Văn Khởi',             '92300111@student.tdtu.edu.vn', '0379031627', '0379031627', 'Quảng Ngãi',       'student', 'active', 0),
('824H0100', '824H0100', 'Hà Phi Hiếu',              '824H0100@student.tdtu.edu.vn', '0327357073', '0332443119', 'Bình Thuận',       'student', 'active', 0);

-- Auth mẫu cho sinh viên (tất cả chưa đặt pass, cần kích hoạt email)
INSERT IGNORE INTO `auth_accounts` (`user_id`, `password`, `is_active`, `must_change_password`) VALUES
(2,  NULL, 0, 1), (3,  NULL, 0, 1), (4,  NULL, 0, 1),
(5,  NULL, 0, 1), (6,  NULL, 0, 1), (7,  NULL, 0, 1),
(8,  NULL, 0, 1), (9,  NULL, 0, 1), (10, NULL, 0, 1),
(11, NULL, 0, 1), (12, NULL, 0, 1), (13, NULL, 0, 1);

-- -------------------------------------------------------
-- 3. TÒA NHÀ
-- -------------------------------------------------------
INSERT IGNORE INTO `buildings` (`id`, `name`, `description`) VALUES
(1, 'Tòa L', 'Khu nhà ở sinh viên Tòa L – Trường Đại học Tôn Đức Thắng');

-- -------------------------------------------------------
-- 4. LẦU (Lầu 1 → 10 trong Tòa L)
-- -------------------------------------------------------
INSERT IGNORE INTO `floors` (`id`, `building_id`, `floor_number`) VALUES
(1,  1, 1),  (2,  1, 2),  (3,  1, 3),  (4,  1, 4),  (5,  1, 5),
(6,  1, 6),  (7,  1, 7),  (8,  1, 8),  (9,  1, 9),  (10, 1, 10);

-- -------------------------------------------------------
-- 5. PHÒNG (14 phòng/lầu, mã L.LLPP)
-- Dùng stored procedure để tạo tự động
-- -------------------------------------------------------
-- Tạo procedure tạo phòng và giường hàng loạt
DROP PROCEDURE IF EXISTS `seed_rooms_and_beds`;
DELIMITER //
CREATE PROCEDURE `seed_rooms_and_beds`()
BEGIN
  DECLARE f_id   INT DEFAULT 1;  -- floor id
  DECLARE f_num  INT DEFAULT 1;  -- floor number
  DECLARE p_num  INT DEFAULT 1;  -- room number (1→14)
  DECLARE r_id   INT DEFAULT 1;  -- room id (auto-increment)
  DECLARE b_num  INT DEFAULT 1;  -- bed number (1→6)
  DECLARE r_code VARCHAR(20);
  DECLARE b_label VARCHAR(5);

  WHILE f_id <= 10 DO
    SELECT floor_number INTO f_num FROM floors WHERE id = f_id;
    SET p_num = 1;
    WHILE p_num <= 14 DO
      -- Mã phòng: L.FFPP (F = floor 2 chữ số, P = room 2 chữ số)
      SET r_code = CONCAT('L.', LPAD(f_num, 2, '0'), LPAD(p_num, 2, '0'));
      INSERT IGNORE INTO `rooms` (`floor_id`, `room_code`, `max_capacity`, `status`)
        VALUES (f_id, r_code, 6, 'available');
      SET r_id = LAST_INSERT_ID();
      -- Tạo 6 giường G1→G6 cho mỗi phòng
      SET b_num = 1;
      WHILE b_num <= 6 DO
        SET b_label = CONCAT('G', b_num);
        INSERT IGNORE INTO `beds` (`room_id`, `bed_label`, `is_occupied`) VALUES (r_id, b_label, 0);
        SET b_num = b_num + 1;
      END WHILE;
      SET p_num = p_num + 1;
    END WHILE;
    SET f_id = f_id + 1;
  END WHILE;
END //
DELIMITER ;

CALL `seed_rooms_and_beds`();
DROP PROCEDURE IF EXISTS `seed_rooms_and_beds`;

-- -------------------------------------------------------
-- 6. GÁN SINH VIÊN MẪU VÀO GIƯỜNG (Lầu 8, Phòng 01 → L.0801)
-- beds của L.0801: room_id tương ứng, G1→G6
-- -------------------------------------------------------
-- Lấy room_id của L.0801 và L.0802
SET @room_0801 = (SELECT id FROM rooms WHERE room_code = 'L.0801');
SET @room_0802 = (SELECT id FROM rooms WHERE room_code = 'L.0802');

-- Gán sinh viên phòng L.0801 vào giường G1→G6

-- Gán đúng: update từng giường
UPDATE beds SET is_occupied = 1 WHERE room_id = @room_0801 AND bed_label IN ('G1','G2','G3','G4','G5','G6');

-- Cập nhật users.bed_id cho sinh viên phòng L.0801
UPDATE users SET bed_id = (SELECT id FROM beds WHERE room_id = @room_0801 AND bed_label = 'G6') WHERE username = '42300276';
UPDATE users SET bed_id = (SELECT id FROM beds WHERE room_id = @room_0801 AND bed_label = 'G5') WHERE username = '42400284';
UPDATE users SET bed_id = (SELECT id FROM beds WHERE room_id = @room_0801 AND bed_label = 'G3') WHERE username = '42400301';
UPDATE users SET bed_id = (SELECT id FROM beds WHERE room_id = @room_0801 AND bed_label = 'G4') WHERE username = '42400299';
UPDATE users SET bed_id = (SELECT id FROM beds WHERE room_id = @room_0801 AND bed_label = 'G1') WHERE username = '52500238';
UPDATE users SET bed_id = (SELECT id FROM beds WHERE room_id = @room_0801 AND bed_label = 'G2') WHERE username = '824H0124';

-- Cập nhật trạng thái phòng L.0801 → full
UPDATE rooms SET status = 'full' WHERE room_code = 'L.0801';

-- Gán sinh viên phòng L.0802 (6 sinh viên)
UPDATE beds SET is_occupied = 1 WHERE room_id = @room_0802 AND bed_label IN ('G1','G2','G3','G4','G5','G6');
UPDATE rooms SET status = 'full' WHERE room_code = 'L.0802';

UPDATE users SET bed_id = (SELECT id FROM beds WHERE room_id = @room_0802 AND bed_label = 'G6') WHERE username = '52300049';
UPDATE users SET bed_id = (SELECT id FROM beds WHERE room_id = @room_0802 AND bed_label = 'G3') WHERE username = '52300164';
UPDATE users SET bed_id = (SELECT id FROM beds WHERE room_id = @room_0802 AND bed_label = 'G2') WHERE username = '52300169';
UPDATE users SET bed_id = (SELECT id FROM beds WHERE room_id = @room_0802 AND bed_label = 'G5') WHERE username = '52300191';
UPDATE users SET bed_id = (SELECT id FROM beds WHERE room_id = @room_0802 AND bed_label = 'G4') WHERE username = '92300111';
UPDATE users SET bed_id = (SELECT id FROM beds WHERE room_id = @room_0802 AND bed_label = 'G1') WHERE username = '824H0100';

-- -------------------------------------------------------
-- 7. THIẾT BỊ MẪU TRONG PHÒNG L.0801
-- -------------------------------------------------------
INSERT IGNORE INTO `devices` (`room_id`, `device_name`, `device_type`, `status`) VALUES
(@room_0801, 'Máy lạnh',       'Điện lạnh',     'good'),
(@room_0801, 'Đèn phòng',      'Chiếu sáng',    'good'),
(@room_0801, 'Quạt trần',      'Điện dân dụng', 'good'),
(@room_0801, 'Bóng đèn toilet','Chiếu sáng',    'broken'),
(@room_0801, 'Vòi nước',       'Nước',          'good');

-- -------------------------------------------------------
-- 8. BÁO CÁO HỎng MẪU từ sinh viên 42300276
-- -------------------------------------------------------
SET @sv_duc = (SELECT user_id FROM users WHERE username = '42300276');
SET @den_toilet = (SELECT id FROM devices WHERE room_id = @room_0801 AND device_name = 'Bóng đèn toilet');

INSERT IGNORE INTO `device_reports` (`device_id`, `room_id`, `reporter_id`, `title`, `description`, `status`) VALUES
(@den_toilet, @room_0801, @sv_duc, 'Bóng đèn toilet bị cháy', 'Bóng đèn trong nhà vệ sinh phòng không sáng từ tối qua, cần thay mới.', 'pending');

-- -------------------------------------------------------
-- 9. THÔNG BÁO MẪU từ Admin đến toàn bộ sinh viên
-- -------------------------------------------------------
SET @admin_id = (SELECT user_id FROM users WHERE username = 'admin');

INSERT IGNORE INTO `notifications` (`sender_id`, `target_user_id`, `title`, `message`, `type`) VALUES
(@admin_id, NULL, 'Thông báo đóng phí ký túc xá tháng 4/2026',
 'Các bạn sinh viên vui lòng đóng phí ký túc xá tháng 4/2026 trước ngày 10/04/2026 tại phòng Quản lý KTX. Chi tiết liên hệ số điện thoại 0123456789.',
 'general');

SET FOREIGN_KEY_CHECKS = 1;
