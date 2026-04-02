<?php
/**
 * UniDorm – API: Delete student account
 * path: api/delete_student.php
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
$targetId = (int)($input['user_id'] ?? 0);
$selfId   = (int)$_SESSION['user_id'];

if (!$targetId) {
    echo json_encode(['success' => false, 'message' => 'ID người dùng không hợp lệ']);
    exit;
}

if ($targetId === $selfId) {
    echo json_encode(['success' => false, 'message' => 'Bạn không thể tự xóa tài khoản của mình']);
    exit;
}

// 1. Fetch user data before deleting
$chk = $conn->prepare("SELECT user_id, fullname, role, bed_id FROM users WHERE user_id = ?");
$chk->bind_param('i', $targetId);
$chk->execute();
$target = $chk->get_result()->fetch_assoc();

if (!$target) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
    exit;
}

if ($target['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Chỉ có thể xóa tài khoản sinh viên']);
    exit;
}

$conn->begin_transaction();
try {
    // 2. Release bed
    if ($target['bed_id']) {
        $freeBed = $conn->prepare("UPDATE beds SET is_occupied = 0 WHERE id = ?");
        $freeBed->bind_param('i', $target['bed_id']);
        $freeBed->execute();

        // Check room status
        $qRoom = $conn->query("SELECT room_id FROM beds WHERE id = {$target['bed_id']}");
        $roomId = $qRoom ? $qRoom->fetch_assoc()['room_id'] : null;
        if ($roomId) {
            $ar = $conn->prepare("UPDATE rooms SET status='available' WHERE id = ? AND status='full'");
            $ar->bind_param('i', $roomId);
            $ar->execute();
        }
    }

    // 3. Delete user
    $del = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'student'");
    $del->bind_param('i', $targetId);
    $del->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Đã xóa tài khoản sinh viên ' . $target['fullname']]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
