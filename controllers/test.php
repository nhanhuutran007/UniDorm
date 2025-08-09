<?php
// Path: /network-management/controllers/test.php

session_start();
require_once __DIR__ . '/../functions/device_management/DeviceManager.php';
require_once __DIR__ . '/../functions/device_value/DeviceValue.php';

class DeviceController {
    private $db;
    private $deviceManager;
    private $deviceCount;

    public function __construct($userId, $role) {
        $this->db = $this->getDatabaseConnection();
        if (!$this->db instanceof mysqli) {
            throw new Exception("Database connection failed.");
        }
        $this->deviceManager = new DeviceManager($this->db, $userId, $role);
        $this->deviceCount = new DeviceValue($this->db); 
    }

    private function getDatabaseConnection() {
        // Dùng require để luôn thực thi db.php và trả về $conn
        return require __DIR__ . '/../includes/db.php';
    }

    public function handleRequest($action, $params = []) {
        $result = $this->deviceManager->handleAction($action, $params);
        return $result;
    }

    public function requestDeviceCount() {
        $result = $this->deviceCount->handleAction('countDevice');
        if ($result['success']) {
            $result['message'] = "Thành công: Có tổng cộng {$result['data']['device_count']} thiết bị.";
        } else {
            $result['message'] = "Lỗi: Không thể lấy số lượng thiết bị.";
        }
        return $result;
    }
}

// Test trực tiếp thông qua DeviceController
try {
    // Khởi tạo controller với userId và role mẫu
    $controller = new DeviceController(16, 'staff');

    // Test requestDeviceCount
    echo "Test countDevice:\n";
    $result = $controller->requestDeviceCount();
    print_r($result['data']);
} catch (Exception $e) {
    echo "Test failed: " . $e->getMessage();
}