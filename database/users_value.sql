
INSERT INTO `users` (`user_id`, `room`,  `student_id`,`username`, `fullname`, `email`, `phone_number`, `role`, `profile_picture`, `num_bed`, `hometown`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'A101',52300235 ,'nguyen.van.an', 'Nguyễn Văn An', 'nguyen.van.an@gmail.com', '0912345671', 'admin', 'images/default.jpg', 3, 'Hà Nội', 'active', NULL, '2025-04-26 10:39:50', '2025-05-04 10:19:20'),
(2, 'B202',52300231,'', 'Trần Thị Biển', NULL, NULL, 'staff', 'images/default.jpg', 1, 'Đà Nẵng', 'ban', 1, '2025-04-26 10:39:50', '2025-05-04 10:19:20'),
(3, 'C303',52300232 ,'le.van.cuong', 'Lê Văn Cường', 'le.van.cuong@gmail.com', '0901234567', 'staff', 'images/default.jpg', 5, 'Hải Phòng', 'active', 1, '2025-04-26 10:39:50', '2025-05-04 10:19:20'),
(4, 'D404',52300225 ,'pham.thi.duyen', 'Phạm Thị Duyên', 'pham.thi.duyen@gmail.com', '0932145678', 'staff', 'images/default.jpg', 2, 'Quảng Ninh', 'active', 1, '2025-04-26 10:39:50', '2025-05-04 10:19:20'),
(5, 'E505',52400234 ,'hoang.van.em', 'Hoàng Văn Em', 'hoang.van.em@gmail.com', '0923456789', 'technician', 'images/profile_5_1746286233.jpg', 4, 'Nam Định', 'active', 1, '2025-04-26 10:39:50', '2025-05-03 08:30:33'),
(6, 'A102',53400345 ,'ngo.thi.phuong', 'Ngô Thị Phương', 'ngo.thi.phuong@gmail.com', '0945678901', 'staff', 'images/default.jpg', 6, 'Thanh Hóa', 'active', 1, '2025-04-26 10:39:50', '2025-05-04 10:19:20'),
(7, 'B203',53400334 ,'vu.van.giang', 'Vũ Văn Giang', 'vu.van.giang@gmail.com', '0956789012', 'admin', 'images/default.jpg', 2, 'Bắc Giang', 'active', 1, '2025-04-26 10:39:50', '2025-05-04 10:19:20'),
(8, 'C304',53400345 ,'dang.thi.hong', 'Đặng Thị Hồng', 'dang.thi.hong@gmail.com', '0967890123', 'staff', 'images/default.jpg', 1, 'Thái Bình', 'active', 1, '2025-04-26 10:39:50', '2025-05-04 10:19:20'),
(9, 'D405',52300235 ,'bui.van.hung', 'Bùi Văn Hùng', 'bui.van.hung@gmail.com', '0978901234', 'staff', 'images/default.jpg', 4, 'Nghệ An', 'active', 1, '2025-04-26 10:39:50', '2025-05-04 10:19:20'),
(10, 'E506',52400677 ,'do.thi.linh', 'Đỗ Thị Linh', 'do.thi.linh@gmail.com', '0989012345', 'technician', 'images/default.jpg', 5, 'Huế', 'active', 1, '2025-04-26 10:39:50', '2025-05-04 10:19:20');


INSERT INTO `auth_accounts` (`auth_id`, `user_id`, `password`, `is_active`, `temporary_password`, `activation_token`, `last_password_change`, `failed_login_attempts`, `last_login`) VALUES
(1, 1, '$2y$10$VxE94JItgFXSSF83Nd0/9.7BpGNgHG0ebec8a6/OTnuD1gY7iDN6e', 1, '52300232', NULL, '2025-04-26 17:42:14', 0, '2025-05-03 14:24:49'),
(2, 2, '', 1, '52300232', NULL, '2025-04-26 17:42:14', 0, '2025-04-19 02:15:00'),
(3, 3, '$2y$10$VxE94JItgFXSSF83Nd0/9.7BpGNgHG0ebec8a6/OTnuD1gY7iDN6e', 1, '52300232', NULL, '2025-04-26 17:42:14', 1, '2025-04-18 07:30:00'),
(4, 4, '$2y$10$VxE94JItgFXSSF83Nd0/9.7BpGNgHG0ebec8a6/OTnuD1gY7iDN6e', 0, '52300232', 'token_4_abcdef123456', '2025-04-26 17:42:14', 0, NULL),
(5, 5, '$2y$10$VxE94JItgFXSSF83Nd0/9.7BpGNgHG0ebec8a6/OTnuD1gY7iDN6e', 1, '52300232', NULL, '2025-04-26 17:42:14', 0, '2025-05-03 15:30:23'),
(6, 6, '$2y$10$VxE94JItgFXSSF83Nd0/9.7BpGNgHG0ebec8a6/OTnuD1gY7iDN6e', 1, '52300232', NULL, '2025-04-26 17:42:14', 2, '2025-04-17 09:00:00'),
(7, 7, '$2y$10$VxE94JItgFXSSF83Nd0/9.7BpGNgHG0ebec8a6/OTnuD1gY7iDN6e', 1, '52300232', NULL, '2025-04-26 17:42:14', 0, '2025-04-20 04:20:00'),
(8, 8, '$2y$10$VxE94JItgFXSSF83Nd0/9.7BpGNgHG0ebec8a6/OTnuD1gY7iDN6e', 1, '52300232', NULL, '2025-04-26 17:42:14', 0, '2025-04-19 06:10:00'),
(9, 9, '$2y$10$VxE94JItgFXSSF83Nd0/9.7BpGNgHG0ebec8a6/OTnuD1gY7iDN6e', 0, '52300232', 'token_9_ghijk789012', '2025-04-26 17:42:14', 0, NULL),
(10, 10, '$2y$10$VxE94JItgFXSSF83Nd0/9.7BpGNgHG0ebec8a6/OTnuD1gY7iDN6e', 1, '52300232', NULL, '2025-04-26 17:42:14', 1, '2025-04-18 02:00:00');

