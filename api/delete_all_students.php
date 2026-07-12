<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện hành động này.']);
    exit;
}

// Check POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mật khẩu.']);
    exit;
}

$adminId = $_SESSION['user_id'];

try {
    $conn->begin_transaction();

    // 1. Verify password
    $stmt = $conn->prepare("SELECT a.password FROM auth_accounts a WHERE a.user_id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Tài khoản admin không hợp lệ.']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    $hashedPassword = $row['password'];
    
    if (!password_verify($password, $hashedPassword)) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Mật khẩu không chính xác.']);
        exit;
    }
    
    // 2. Delete all students (ON DELETE CASCADE will handle related data)
    $stmtDelete = $conn->prepare("DELETE FROM users WHERE role = 'student'");
    if (!$stmtDelete->execute()) {
        throw new Exception("Lỗi khi xóa sinh viên: " . $stmtDelete->error);
    }
    
    $deletedCount = $stmtDelete->affected_rows;

    // Optional: update bed status to available where they were occupied, but since it's deleting users, maybe beds should be updated?
    // Wait, the bed is linked from users.bed_id -> beds.id. But `is_occupied` flag might be in `beds` table.
    // Let's reset all beds `is_occupied = 0`.
    $stmtBeds = $conn->prepare("UPDATE beds SET is_occupied = 0");
    $stmtBeds->execute();
    
    // Also rooms might have `status = 'available'` when beds are available.
    // But since it's all students, we can reset all rooms `status = 'available'`
    $stmtRooms = $conn->prepare("UPDATE rooms SET status = 'available'");
    $stmtRooms->execute();

    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Đã xóa thành công {$deletedCount} sinh viên và tài khoản liên quan.",
        'deleted_count' => $deletedCount
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
