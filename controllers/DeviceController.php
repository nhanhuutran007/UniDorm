<?php
// Path: /network-management/controllers/DeviceController.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../functions/device_value/DeviceValue.php';
require_once __DIR__ . '/../functions/device_management/DeviceManager.php';
require_once __DIR__ . '/../functions/device_log/DeviceLogManager.php';
require_once __DIR__ . '/../functions/device_assignment/DeviceAssignmentManager.php';
require_once __DIR__ . '/../functions/device_notification/DeviceNotificationManager.php';
require_once __DIR__ . '/../functions/device_maintenance/DeviceMaintenanceManager.php';

class DeviceController {
    private $db;
    private $deviceManager;
    private $deviceCount;
    private $deviceLogManager;
    private $deviceAssignmentManager;
    private $deviceNotificationManager;
    private $deviceMaintenanceManager;
    private $userId;
    private $role;

    public function __construct($userId, $role) {
        $this->db = $this->getDatabaseConnection();
        if (!$this->db instanceof mysqli) {
            throw new Exception("Database connection failed.");
        }
        $this->userId = $userId;
        $this->role = $role;
        $this->deviceManager = new DeviceManager($this->db, $userId, $role);
        $this->deviceCount = new DeviceValue($this->db);
        $this->deviceLogManager = new DeviceLogManager($this->db);
        $this->deviceAssignmentManager = new DeviceAssignmentManager($this->db);
        $this->deviceNotificationManager = new DeviceNotificationManager($this->db);
        $this->deviceMaintenanceManager = new DeviceMaintenanceManager($this->db, $userId);
    }

    private function getDatabaseConnection() {
        return require __DIR__ . '/../includes/db.php';
    }

    private function handlePostAction($action, $params, $response) {
        // Loại bỏ log/thông báo cho tất cả hành động get
        if (strncasecmp($action, 'get', 3) === 0) {
            return;
        }
        try {
            $deviceId = $params['data']['device_id'] ?? null;
            // Ánh xạ action sang event_type hợp lệ
            $eventTypeMap = [
                'add' => 'created',
                'update' => 'updated',
                'delete' => 'deleted',
                'insert_assignment' => 'assigned',
                'add_maintenance' => 'maintenance',
                'insert_notification' => 'status_changed'
            ];
            $eventType = $eventTypeMap[strtolower($action)] ?? 'status_changed';

            // Ánh xạ action sang notification_type hợp lệ
            $notificationTypeMap = [
                'add' => 'status_change',
                'update' => 'status_change',
                'delete' => 'status_change',
                'insert_assignment' => 'assignment',
                'add_maintenance' => 'maintenance_due',
                'insert_notification' => 'status_change'
            ];
            $notificationType = $notificationTypeMap[strtolower($action)] ?? 'status_change';

            // Xác định log params
            $logParams = [
                'device_id' => $deviceId,
                'user_id' => $this->userId,
                'event_type' => $eventType,
                'previous_status' => 'status_changed',
                'new_status' => 'status_changed',
                'details' => "Thực hiện '$eventType' trên thiết bị ID $deviceId."
            ];

            // Xác định notification data
            $notificationData = [
                'device_id' => $deviceId,
                'notification_type' => $notificationType,
                'message' => "Thực hiện '$notificationType' trên thiết bị ID $deviceId bởi người dùng ID {$this->userId}.",
                'is_read' => false,
                'target_user_id' => $params['target_user_id'] ?? null,
                'created_by' => $this->userId
            ];

            // Tùy chỉnh cho từng hành động
            switch (strtolower($action)) {
                case 'add':
                    $logParams['new_status'] = $response['data']['status'] ?? 'active';
                    $logParams['details'] = "Tạo thiết bị với ID $deviceId.";
                    $notificationData['message'] = "Tạo thiết bị với ID $deviceId bởi người dùng ID {$this->userId}.";
                    break;
                case 'update':
                    $logParams['previous_status'] = $response['data']['previous_status'] ?? 'status_changed';
                    $logParams['new_status'] = $response['data']['status'] ?? 'active';
                    $logParams['details'] = "Cập nhật thiết bị với ID $deviceId.";
                    $notificationData['message'] = "Cập nhật thiết bị với ID $deviceId bởi người dùng ID {$this->userId}.";
                    break;
                case 'delete':
                    $logParams['previous_status'] = $response['data']['previous_status'] ?? 'status_changed';
                    $logParams['new_status'] = 'deleted';
                    $logParams['details'] = "Xóa thiết bị với ID $deviceId.";
                    $notificationData['message'] = "Xóa thiết bị với ID $deviceId bởi người dùng ID {$this->userId}.";
                    break;
                case 'insert_assignment':
                    echo "Vừa thực hiện hành động insert_assignment";
                    $userId = $params['user_id'] ?? 'status_changed';
                    $logParams['details'] = "Gán thiết bị từ người dùng ID $userId.";
                    $notificationData['message'] = "Gán thiết bị bởi người dùng ID {$this->userId}.";
                    break;
                case 'add_maintenance':
                    $logParams['details'] = "Thêm lịch bảo trì cho thiết bị ID $deviceId.";
                    $notificationData['message'] = "Thêm lịch bảo trì cho thiết bị ID $deviceId bởi người dùng ID {$this->userId}.";
                    break;
                case 'insert_notification':
                    $notificationId = $response['data']['notification_id'] ?? 'status_changed';
                    $logParams['details'] = "Tạo thông báo với ID $notificationId.";
                    $notificationData['message'] = "Tạo thông báo với ID $notificationId bởi người dùng ID {$this->userId}.";
                    break;           
                }

            // Ghi log
            $logResult = $this->deviceLogManager->handleAction('add_log', $logParams);
            if (!$logResult['success']) {
                error_log("Không thể ghi log cho hành động '$action': " . $logResult['message']);
            }
            // Gửi thông báo
            $notificationResult = $this->deviceNotificationManager->handleAction('insert_notification', [
                'user_id' => $this->userId,
                'data' => $notificationData
            ]);
            if (!$notificationResult['success']) {
                error_log("Không thể gửi thông báo cho hành động '$action': " . $notificationResult['message']);
            }
        } catch (Exception $e) {
            error_log("Lỗi khi xử lý post-action cho hành động '$action': " . $e->getMessage());
        }
    }

    public function requestDeviceCount($action) {
        $result = $this->deviceCount->handleAction($action);
        if ($result['success']) {
            $result['message'] = "Thành công: Có tổng cộng {$result['data']['device_count']} thiết bị.";
            $this->handlePostAction($action, [], $result);
        } else {
            $result['message'] = "Lỗi: Không thể lấy số lượng thiết bị.";
        }
        return $result;
    }


   public function getDeviceStatusStats($startDate, $endDate) {
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        // error_log("DeviceController params: " . print_r($params, true));
        $result = $this->deviceCount->handleAction('devicestatusstats', $params);
        // error_log("DeviceController result: " . print_r($result, true));
        if ($result['success']) {
            $result['message'] = "Thành công: Lấy thống kê trạng thái thiết bị.";
        } else {
            $result['message'] = "Lỗi: Không thể lấy thống kê trạng thái.";
        }
        return $result;
    }

    public function handleRequest($action, $params = []) {
        try {
            $result = null;

            if ($action === 'get_log') {
                $result = $this->deviceLogManager->handleAction('get_log', $params);
            } elseif (in_array($action, ['insert_assignment', 'update_assignment', 'delete_assignment', 'get_assignment', 'get_all_assignment'])) {
                $params['user_id'] = $_SESSION['user_id'] ?? null;
                $result = $this->deviceAssignmentManager->handleAction($action, $params);
            } elseif (in_array($action, ['insert_notification', 'update_notification', 'delete_notification', 'get_notification', 'get_all_notifications', 'get_notifications_by_user_id', 'mark_all_notifications_as_read'])) {
                $result = $this->deviceNotificationManager->handleAction($action, $params);
            } elseif (in_array($action, ['add_maintenance', 'request_maintenance', 'update_maintenance', 'update_status_maintenance', 'delete_maintenance', 'get_all_maintenance', 'get_assigned_maintenance', 'schedule_maintenance', 'assign_technician_maintenance'])) {
                $result = $this->deviceMaintenanceManager->handleAction($action, $params);
            } else {
                $result = $this->deviceManager->handleAction($action, $params);
            }

            if ($result['success']) {
                $this->handlePostAction($action, $params, $result);
            }

            return $result;
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
// Trong mảng results sẽ có ba thành phần:
// - success: true/false
// - message: thông báo thành công hoặc lỗi
// - data: dữ liệu trả về (nếu có)
// để sử dụng các dữ liệu anh em gọi result['data']
///////////////////////////
// // Ví dụ sử dụng hiệu suất thiết bị (device_value)
// $controller = new DeviceController(102, 'admin');
// $result = $controller->getDeviceStatusStats('2024-01-01', '2025-12-31'); 
// print_r($result['data']);


// ///////////////////////////////////////////////////////////////////

// // Ví dụ lấy countDevice (phục vụ cho Dashboard)
// $result = $controller->requestDeviceCount();
// print_r($result['data']);

// ////////////////////////////////////////////////////////////////////////

// // Ví dụ sử dụng quản lý thiết bị (device_management)
// $controller = new DeviceController(2, 'admin');

// $updateData = [
//     'device_name' => 'Printer HP',
//     'device_type' => 'printer',
//     'ip_address' => '192.168.1.100',
//     'mac_address' => '00:14:22:01:23:45',
//     'status' => 'active',
//     'location' => 'Room 102',
//     'last_maintenance' => '2025-04-02'
// ];

// // // Thêm thiết bị
// // $result = $controller->handleRequest('add', ['data' => $updateData]);
// // print_r($result);
// // echo "<br>";

// // Cập nhật thiết bị
// $result = $controller->handleRequest('update', ['device_id' => 28, 'data' => $updateData]);
// print_r($result);
// echo "<br>";

// // Xóa thiết bị
// $result = $controller->handleRequest('delete', ['device_id' => 10]);
// print_r($result);
// echo "<br>";

// Lấy danh sách
// $result = $controller->handleRequest('get', ['search' => ['status' => 'active']]);
// print_r($result['data']);
// echo "<br>";

// /////////////////////////////////////////////////////////////////////


// // Ví dụ sử dụng quản lý log thiết bị (device_log)
// $controller = new DeviceController(2, 'admin');
// // Lấy log gần nhất của thiết bị
// $result = $controller->handleRequest('get_log', ['device_id' => 1, 'limit' => 1]);
// print_r($result['data']);
// echo "<br>";
// // Lấy tất cả log của thiết bị
// $result = $controller->handleRequest('get_log', ['device_id' => 1]);
// print_r($result);


// ///////////////////////////////////////////////////////////////////////////////
// // Ví dụ sử dụng quản lý phân quyền thiết bị (device_assignment)
// $controller = new DeviceController(2, 'admin');

// // 1. Thêm phân quyền thiết bị
// echo "Thêm phân quyền thiết bị:\n";
// $result = $controller->handleRequest('insert_assignment', [
//     'data' => [
//         'device_id' => 1,
//         'user_id' => 2,
//         'assigned_date' => '2025-04-01',
//         'expected_return_date' => '2025-05-01',
//         'assigned_by_user_id' => 2,
//         'notes' => 'Phân công thiết bị cho dự án A'
//     ]
// ]);
// print_r($result);
// echo "<br>";

// // 2. Cập nhật phân quyền thiết bị
// echo "Cập nhật phân quyền thiết bị:\n";
// $result = $controller->handleRequest('update_assignment', [
//     'assignment_id' => 1,
//     'data' => [
//         'expected_return_date' => '2025-06-01',
//         'notes' => 'Cập nhật ngày trả dự kiến'
//     ]
// ]);
// print_r($result);
// echo "<br>";

// // 3. Lấy thông tin phân quyền thiết bị
// echo "Lấy thông tin phân quyền thiết bị:\n";
// $result = $controller->handleRequest('get_assignment', [
//     'assignment_id' => 1
// ]);
// print_r($result);
// echo "<br>";

// // 4. Lấy danh sách phân quyền thiết bị
// echo "Lấy danh sách phân quyền thiết bị:\n";
// $result = $controller->handleRequest('get_all_assignment', [
//     'search' => ['status' => 'active'],
//     'limit' => 5,
//     'offset' => 0
// ]);
// print_r($result);
// echo "<br>";

// // 5. Xóa phân quyền thiết bị (soft delete)
// echo "Xóa phân quyền thiết bị:\n";
// $result = $controller->handleRequest('delete_assignment', [
//     'assignment_id' => 1
// ]);
// print_r($result);
// echo "<br>";

// //////////////////////////////////////////////////////////////////////////////////
// // Ví dụ sử dụng quản lý thông báo thiết bị (device_notification)
// $controller = new DeviceController(2, 'admin');
// // 1. Thêm thông báo thiết bị
// echo "Thêm thông báo thiết bị:\n";
// $result = $controller->handleRequest('insert_notification', [
//     'data' => [
//     'device_id' => 1,
//     'notification_type' => 'maintenance_due',
//     'message' => 'Thiết bị cần bảo trì định kỳ.',
//     'is_read' => false,
//     'target_user_id' => 5,
//     'created_by' => 2
//     ]
// ]);
// print_r($result);
// echo "<br>";

// // 2. Cập nhật thông báo thiết bị
// echo "Cập nhật thông báo thiết bị:\n";
// $result = $controller->handleRequest('update_notification', [
//     'notification_id' => 5,
//     'data' => [
//         'status' => 'read',
//         'is_read' => false,
//     ]
// ]);
// print_r($result);
// echo "<br>";

// // 3. Lấy thông báo thiết bị theo ID
// echo "Lấy thông báo thiết bị theo ID:\n";
// $result = $controller->handleRequest('get_notification', [
//     'notification_id' => 6
// ]);
// print_r($result);
// echo "<br>";

// // 4. Lấy danh sách thông báo thiết bị
// echo "Lấy danh sách thông báo thiết bị:\n";
// $result = $controller->handleRequest('get_all_notifications', [
//     'search' => ['status' => 'unread'],
//     'limit' => 5,
//     'offset' => 0
// ]);
// print_r($result);
// echo "<br>";

// // 5. Lấy thông báo theo user_id
// echo "Lấy thông báo theo user_id:\n";
// $result = $controller->handleRequest('get_notifications_by_user_id', [
//     'target_user_id' => 5,
//     'limit' => 5,
//     'offset' => 0
// ]);
// print_r($result);
// echo "<br>";

// // 6. Xóa thông báo thiết bị (xóa hoàn toàn)
// echo "Xóa thông báo thiết bị:\n";
// $result = $controller->handleRequest('delete_notification', [
//     'notification_id' => 10
// ]);
// print_r($result);
// echo "<br>";

// // 7. Đánh dấu tất cả thông báo là đã đọc
// echo "Đánh dấu tất cả thông báo là đã đọc:\n";
// $result = $controller->handleRequest('mark_all_notifications_as_read', [
//     'target_user_id' => 2
// ]);
// print_r($result);
// echo "<br>";


// //////////////////////////////////////////////////////////////////////////////////
// // Ví dụ sử dụng quản lý bảo trì thiết bị (device_maintenance)

// Giả định các ID hợp lệ trong cơ sở dữ liệu:
// - Admin: user_id = 1
// - Technician: user_id = 2
// - Staff: user_id = 3
// - Device: device_id = 5
// - Maintenance record: record_id = 10
// - Technician khác: user_id = 4

//Dữ liệu mẫu cho yêu cầu bảo trì
// $maintenanceData = [
//     'device_id' => 5,
//     'description' => 'Máy in kẹt giấy nghiêm trọng',
//     'maintenance_date' => '2025-04-20'
// ];

// // Dữ liệu mẫu cho cập nhật trạng thái (technician)
// $statusUpdateData = [
//     'status' => 'completed',
//     'completion_date' => '2025-04-14',
//     'notes' => 'Đã thay mực in'
// ];

// // Dữ liệu mẫu cho cập nhật toàn bộ (admin)
// $updateMaintenanceData = [
//     'description' => 'Cập nhật: Router cần kiểm tra phần mềm',
//     'cost' => 200.50,
//     'status' => 'pending'
// ];

// // Dữ liệu mẫu cho đặt lịch bảo trì
// $scheduleData = [
//     'device_id' => 5,
//     'reported_by_user_id' => 3,
//     'maintenance_date' => '2025-05-01',
//     'description' => 'Bảo trì định kỳ máy chủ',
//     'notes' => 'Kiểm tra phần cứng'
// ];

// Ví dụ sử dụng quản lý bảo trì thiết bị

// // 1. request_maintenance (staff)
// $controller = new DeviceController(3, 'staff');
// $result = $controller->handleRequest('request_maintenance', ['data' => $maintenanceData]);
// print_r($result);
// echo "<br>";

// // 2. request_maintenance (technician)
// $controller = new DeviceController(2, 'technician');
// $result = $controller->handleRequest('request_maintenance', ['data' => $maintenanceData]);
// print_r($result);
// echo "<br>";

// 3. add_maintenance (admin)
// $controller = new DeviceController(2, 'admin');
// $result = $controller->handleRequest('add_maintenance', ['data' => $maintenanceData]);
// print_r($result);
// echo "<br>";

// // 4. update_status_maintenance (technician)
// $controller = new DeviceController(4, 'technician');
// $result = $controller->handleRequest('update_status_maintenance', ['record_id' => 103, 'data' => $statusUpdateData]);
// print_r($result);
// echo "<br>";

//5. update_maintenance (admin)
// $controller = new DeviceController(2, 'admin');
// $result = $controller->handleRequest('update_maintenance', ['record_id' => 130, 'data' => $updateMaintenanceData]);
// print_r($result);
// echo "<br>";

// // 6. delete_maintenance (admin)
// $controller = new DeviceController(2, 'admin');
// $result = $controller->handleRequest('delete_maintenance', ['record_id' => 103]);
// print_r($result);
// echo "<br>";

// // 7. get_all_maintenance (admin)
// $controller = new DeviceController(2, 'admin');
// $result = $controller->handleRequest('get_all_maintenance', ['search' => ['status' => 'pending']]);
// print_r($result['data']);
// echo "<br>";

// // 8. get_assigned_maintenance (technician)
// $controller = new DeviceController(2, 'technician');
// $result = $controller->handleRequest('get_assigned_maintenance', ['search' => ['status' => 'in_progress']]);
// print_r($result['data']);
// echo "<br>";

// 9. schedule_maintenance (admin)
// $controller = new DeviceController(2, 'admin');
// $result = $controller->handleRequest('schedule_maintenance', ['data' => $scheduleData]);
// print_r($result);
// echo "<br>";

// // 10. assign_technician_maintenance (admin)
// $controller = new DeviceController(2, 'admin');
// $result = $controller->handleRequest('assign_technician_maintenance', ['record_id' => 104, 'technician_id' => 4]);
// print_r($result);
// echo "<br>";