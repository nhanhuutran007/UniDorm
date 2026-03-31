<?php
/**
 * UniDorm – API: Notifications
 * path: api/notifications.php
 * GET  → lấy danh sách thông báo của user hiện tại
 * POST → tạo thông báo mới (chỉ admin)
 */
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$conn = require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'student';
$method   = $_SERVER['REQUEST_METHOD'];

// -------------------------------------------------------
// Đánh dấu đã đọc 1 thông báo (GET với mark_read param)
// -------------------------------------------------------
if ($method === 'GET' && isset($_GET['mark_read'])) {
    $notifId = (int)$_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (target_user_id = ? OR target_user_id IS NULL)");
    $stmt->bind_param("ii", $notifId, $userId);
    $stmt->execute();
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/UniDorm/views/student/notifications.php'));
    exit;
}

// -------------------------------------------------------
// GET → Lấy notifications của user
// -------------------------------------------------------
if ($method === 'GET') {
    $limit  = min((int)($_GET['limit'] ?? 20), 50);
    $offset = (int)($_GET['offset'] ?? 0);

    $stmt = $conn->prepare("
        SELECT n.id, n.title, n.message, n.type, n.is_read, n.created_at,
               u.fullname as sender_name
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.user_id
        WHERE n.target_user_id = ? OR n.target_user_id IS NULL
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $userId, $limit, $offset);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// -------------------------------------------------------
// POST → Tạo thông báo (chỉ admin)
// -------------------------------------------------------
if ($method === 'POST') {
    if ($userRole !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Không có quyền gửi thông báo']);
        exit;
    }

    $input         = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $title         = trim($input['title'] ?? '');
    $message       = trim($input['message'] ?? '');
    $type          = $input['type'] ?? 'general';
    $targetUserId  = !empty($input['target_user_id']) ? (int)$input['target_user_id'] : null;

    if (empty($title) || empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Thiếu tiêu đề hoặc nội dung']);
        exit;
    }

    $validTypes = ['general','room','maintenance','system','message'];
    if (!in_array($type, $validTypes)) $type = 'general';

    $stmt = $conn->prepare("INSERT INTO notifications (sender_id, target_user_id, title, message, type) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $userId, $targetUserId, $title, $message, $type);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Đã gửi thông báo', 'id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Lỗi DB: ' . $conn->error]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
