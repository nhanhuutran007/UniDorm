CREATE TABLE `messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` INT(11) NOT NULL,
  `recipient_id` INT(11) NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_sender_recipient` (`sender_id`, `recipient_id`),
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`recipient_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE device_notifications MODIFY COLUMN notification_type ENUM('inspection_due', 'incident_report', 'maintenance_due', 'status_change', 'assignment', 'message');

ALTER TABLE device_notifications MODIFY COLUMN device_id int(5) NULL;