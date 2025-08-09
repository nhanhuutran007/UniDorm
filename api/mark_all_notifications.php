<?php
require __DIR__ . '/../controllers/DeviceController.php';

// Thiết lập header cho JSON response
header('Content-Type: application/json');

// Kiểm tra xem user_id có được gửi trong body không
$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? null;

// Kiểm tra session và user_id
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $userId !== $_SESSION['user_id']) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized or invalid user ID.'
    ]);
    exit();
}

try {
    // Khởi tạo DeviceController
    $deviceController = new DeviceController($_SESSION['user_id'], $_SESSION['role']);
    
    // Gọi phương thức mark_all_notifications_as_read
    $result = $deviceController->handleRequest('mark_all_notifications_as_read', [
        'target_user_id' => $userId,
        'filters' => ['is_read' => 0], // Chỉ xử lý thông báo chưa đọc
        'limit' => 100,
        'offset' => 0
    ]);

    // Trả về kết quả
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>