<?php
// Path: /network-management/functions/device_notification/DeviceNotificationManager.php

require_once __DIR__ . '/../../repositories/DeviceNotifications.php';

class DeviceNotificationManager {
    private $db;

    public function __construct($db) {
        $this->db = $db; // Nhận $db từ Controller
    }

    // Phương thức duy nhất để xử lý tất cả hành động
    public function handleAction($action, $params = []) {
        try {
            // Xử lý hành động
            switch (strtolower($action)) {
                case 'insert_notification':
                    $data = $params['data'] ?? [];
                    if (empty($data)) {
                        throw new Exception("Dữ liệu thông báo là bắt buộc. Vui lòng cung cấp data.");
                    }
                    // Nếu không có target_user_id, lấy từ device_assignments
                    if (!isset($data['target_user_id']) || $data['target_user_id'] === null) {
                        if (isset($data['device_id']) && is_numeric($data['device_id'])) {
                            $query = "SELECT user_id FROM device_assignments 
                                      WHERE device_id = ? AND status = 'active' AND deleted = 0 
                                      LIMIT 1";
                            $stmt = $this->db->prepare($query);
                            $stmt->bind_param("i", $data['device_id']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($row = $result->fetch_assoc()) {
                                $data['target_user_id'] = $row['user_id'];
                            }
                            $stmt->close();
                        }
                    }
                    $notification = new DeviceNotifications($this->db, $data);
                    $notificationId = $notification->insert();
                    return [
                        'success' => true,
                        'message' => "Thành công: Đã thêm thông báo mới với ID $notificationId.",
                        'data' => ['notification_id' => $notificationId]
                    ];

                case 'update_notification':
                    $notificationId = $params['notification_id'] ?? null;
                    $data = $params['data'] ?? [];
                    if (!$notificationId) {
                        throw new Exception("Notification ID là bắt buộc. Vui lòng cung cấp notification_id.");
                    }
                    if (empty($data)) {
                        throw new Exception("Dữ liệu cập nhật là bắt buộc. Vui lòng cung cấp data.");
                    }
                    $notification = DeviceNotifications::getNotificationById($this->db, $notificationId);
                    if (!$notification) {
                        throw new Exception("Không tìm thấy thông báo với ID $notificationId.");
                    }
                    // Cập nhật các trường
                    if (isset($data['device_id'])) {
                        $notification->setDeviceId($data['device_id']);
                    }
                    if (isset($data['notification_type'])) {
                        $notification->setNotificationType($data['notification_type']);
                    }
                    if (isset($data['message'])) {
                        $notification->setMessage($data['message']);
                    }
                    if (isset($data['is_read'])) {
                        $notification->setIsRead($data['is_read']);
                    }
                    if (isset($data['target_user_id'])) {
                        $notification->setTargetUserId($data['target_user_id']);
                    }
                    if (isset($data['created_by'])) {
                        $notification->setCreateBy($data['created_by']);
                    }
                    $updated = $notification->update();
                    return [
                        'success' => true,
                        'message' => $updated ? "Thành công: Đã cập nhật thông báo với ID $notificationId." : "Không có thay đổi nào được thực hiện.",
                        'data' => ['notification_id' => $notificationId]
                    ];

                    case 'mark_all_notifications_as_read':
                        $userId = $params['target_user_id'] ?? null;
                        if (!$userId) {
                            throw new Exception("User ID là bắt buộc. Vui lòng cung cấp user_id.");
                        }
    
                        // Lấy danh sách thông báo của user
                        $filters = $params['filters'] ?? [];
                        $limit = $params['limit'] ?? 100; // Giới hạn mặc định
                        $offset = $params['offset'] ?? 0;
                        $notifications = $this->handleAction('get_notifications_by_user_id', [
                            'target_user_id' => $userId,
                            'filters' => $filters,
                            'limit' => $limit,
                            'offset' => $offset
                        ]);
    
                        // Kiểm tra kỹ hơn trước khi sử dụng foreach
                        if (!$notifications['success'] || !isset($notifications['data']) || !is_array($notifications['data']) || empty($notifications['data'])) {
                            return [
                                'success' => false,
                                'message' => "Không tìm thấy thông báo nào để cập nhật.",
                                'data' => []
                            ];
                        }
    
                        $results = [];
                        foreach (is_array($notifications['data']) ? $notifications['data'] : [] as $notification) {
                            $response = $this->handleAction('update_notification', [
                                'notification_id' => $notification['notification_id'],
                                'data' => ['is_read' => true] // Chỉ đánh dấu là đã đọc
                            ]);
                            $results[] = [
                                'notification_id' => $notification['notification_id'],
                                'success' => $response['success'],
                                'message' => $response['message']
                            ];
                        }
    
                        return [
                            'success' => true,
                            'message' => "Đã xử lý đánh dấu nhiều thông báo là đã đọc.",
                            'data' => $results
                        ];
                        

                case 'delete_notification':
                    $notificationId = $params['notification_id'] ?? null;
                    if (!$notificationId) {
                        throw new Exception("Notification ID là bắt buộc. Vui lòng cung cấp notification_id.");
                    }
                    $notification = DeviceNotifications::getNotificationById($this->db, $notificationId);
                    if (!$notification) {
                        throw new Exception("Không tìm thấy thông báo với ID $notificationId.");
                    }
                    $deleted = $notification->delete();
                    return [
                        'success' => true,
                        'message' => $deleted ? "Thành công: Đã xóa thông báo với ID $notificationId." : "Không có thay đổi nào được thực hiện.",
                        'data' => ['notification_id' => $notificationId]
                    ];

                case 'get_notification':
                    $notificationId = $params['notification_id'] ?? null;
                    if (!$notificationId) {
                        throw new Exception("Notification ID là bắt buộc. Vui lòng cung cấp notification_id.");
                    }
                    $notification = DeviceNotifications::getNotificationById($this->db, $notificationId);
                    if (!$notification) {
                        throw new Exception("Không tìm thấy thông báo với ID $notificationId.");
                    }
                    return [
                        'success' => true,
                        'message' => "Thành công: Đã lấy thông tin thông báo với ID $notificationId.",
                        'data' => [
                            'notification_id' => $notification->getNotificationId(),
                            'device_id' => $notification->getDeviceId(),
                            'notification_type' => $notification->getNotificationType(),
                            'message' => $notification->getMessage(),
                            'created_at' => $notification->getCreatedAt(),
                            'is_read' => $notification->getIsRead(),
                            'target_user_id' => $notification->getTargetUserId(),
                            'created_by' => $notification->getCreateBy(),
                            'creator_avatar' => $notification->getCreatorAvatar(),
                            'creator_name' => $notification->getCreatorName()
                        ]
                    ];

                case 'get_all_notifications':
                    $filters = $params['filters'] ?? [];
                    $limit = $params['limit'] ?? 10;
                    $offset = $params['offset'] ?? 0;
                    $notifications = DeviceNotifications::getAllNotifications($this->db, $filters, $limit, $offset);
                    return [
                        'success' => true,
                        'message' => "Thành công: Đã lấy danh sách thông báo.",
                        'data' => array_map(function ($notification) {
                            return [
                                'notification_id' => $notification->getNotificationId(),
                                'device_id' => $notification->getDeviceId(),
                                'notification_type' => $notification->getNotificationType(),
                                'message' => $notification->getMessage(),
                                'created_at' => $notification->getCreatedAt(),
                                'is_read' => $notification->getIsRead(),
                                'target_user_id' => $notification->getTargetUserId(),
                                'created_by' => $notification->getCreateBy(),
                                'creator_avatar' => $notification->getCreatorAvatar(),
                                'creator_name' => $notification->getCreatorName()
                            ];
                        }, $notifications)
                    ];

                case 'get_notifications_by_user_id':
                    $targetUserId = $params['target_user_id'] ?? null;
                    if (!$targetUserId) {
                        throw new Exception("Target User ID là bắt buộc. Vui lòng cung cấp target_user_id.");
                    }
                    $filters = $params['filters'] ?? [];
                    $limit = $params['limit'] ?? 10;
                    $offset = $params['offset'] ?? 0;
                    $notifications = DeviceNotifications::getNotificationsByUserId($this->db, $targetUserId, $filters, $limit, $offset);
                    if (empty($notifications)) {
                        return [
                            'success' => true,
                            'message' => "Không tìm thấy thông báo nào cho user với ID $targetUserId.",
                            'data' => []
                        ];
                    }
                    return [
                        'success' => true,
                        'message' => "Thành công: Đã lấy danh sách thông báo cho user với ID $targetUserId.",
                        'data' => array_map(function ($notification) {
                            return [
                                'notification_id' => $notification->getNotificationId(),
                                'device_id' => $notification->getDeviceId(),
                                'notification_type' => $notification->getNotificationType(),
                                'message' => $notification->getMessage(),
                                'created_at' => $notification->getCreatedAt(),
                                'is_read' => $notification->getIsRead(),
                                'target_user_id' => $notification->getTargetUserId(),
                                'created_by' => $notification->getCreateBy(),
                                'creator_avatar' => $notification->getCreatorAvatar(),
                                'creator_name' => $notification->getCreatorName()
                            ];
                        }, $notifications)
                    ];

                default:
                    throw new Exception("Hành động '$action' không hợp lệ. Vui lòng kiểm tra lại.");
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [
                'success' => false,
                'message' => "Thất bại: " . $e->getMessage(),
                'data' => null
            ];
        }
    }
}