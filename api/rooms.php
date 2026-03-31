<?php
// path: api/rooms.php – Returns room list (JSON)
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập']);
    exit;
}

$conn = require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../app/models/RoomModel.php';

try {
    $roomModel = new RoomModel($conn);
    $filters   = [];
    if (!empty($_GET['floor_id'])) $filters['floor_id'] = (int)$_GET['floor_id'];
    if (!empty($_GET['status']))   $filters['status']   = $_GET['status'];

    $rooms = $roomModel->getAllRooms($filters);
    echo json_encode(['status' => 'success', 'data' => $rooms]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
