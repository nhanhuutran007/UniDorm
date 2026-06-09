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
$targetIds = [];
if (isset($input['user_ids']) && is_array($input['user_ids'])) {
    $targetIds = array_map('intval', $input['user_ids']);
} elseif (isset($input['user_id'])) {
    $targetIds = [(int)$input['user_id']];
}

$selfId = (int)$_SESSION['user_id'];

if (empty($targetIds)) {
    echo json_encode(['success' => false, 'message' => 'ID người dùng không hợp lệ']);
    exit;
}

if (in_array($selfId, $targetIds)) {
    echo json_encode(['success' => false, 'message' => 'Bạn không thể tự xóa tài khoản của mình']);
    exit;
}

$conn->begin_transaction();
try {
    foreach ($targetIds as $targetId) {
        $chk = $conn->prepare("SELECT user_id, fullname, role, bed_id FROM users WHERE user_id = ?");
        $chk->bind_param('i', $targetId);
        $chk->execute();
        $target = $chk->get_result()->fetch_assoc();

        if (!$target || $target['role'] !== 'student') {
            continue; // Bỏ qua nếu không hợp lệ
        }

        // Release bed
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

        // Delete user
        $del = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'student'");
        $del->bind_param('i', $targetId);
        $del->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Đã xóa tài khoản sinh viên thành công']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
