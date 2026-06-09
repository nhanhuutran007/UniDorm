<?php
/**
 * UniDorm – API: Bulk Update User Status
 * path: api/bulk_status_users.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

// Auth check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$targetIds = $input['user_ids'] ?? [];
$status = $input['status'] ?? '';

if (empty($targetIds) || !in_array($status, ['active', 'inactive'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

$targetIds = array_map('intval', $targetIds);
$selfId = (int)$_SESSION['user_id'];
$targetIds = array_diff($targetIds, [$selfId]);

if (empty($targetIds)) {
    echo json_encode(['success' => false, 'message' => 'Không có tài khoản hợp lệ (không thể tự thay đổi trạng thái của mình)']);
    exit;
}

$idList = implode(',', $targetIds);
$stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id IN ($idList)");
$stmt->bind_param('s', $status);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Đã cập nhật trạng thái thành công']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $conn->error]);
}
