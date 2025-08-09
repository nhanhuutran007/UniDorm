<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/DeviceController.php';

// Kiểm tra session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = strtolower($_SESSION['role']);

// Nhận dữ liệu từ request
$input = json_decode(file_get_contents('php://input'), true);
$notificationId = isset($input['notification_id']) ? (int)$input['notification_id'] : null;

if (!$notificationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing notification_id']);
    exit();
}

try {
    $deviceController = new DeviceController($userId, $userRole);
    $result = $deviceController->handleRequest('update_notification', [
        'notification_id' => $notificationId,
        'data' => ['is_read' => 1]
    ]);

    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to mark notification as read']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>