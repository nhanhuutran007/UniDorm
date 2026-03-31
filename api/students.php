<?php
/**
 * UniDorm – API: Students CRUD
 * path: api/students.php
 *
 * GET    ?action=list     → danh sách sinh viên với filter
 * GET    ?action=get&id=  → lấy 1 sinh viên
 * POST   action=update    → cập nhật thông tin
 * DELETE action=delete&id → xóa sinh viên
 */
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

// Auth guard
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$conn     = require_once __DIR__ . '/../includes/db.php';
$userId   = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'student';
$method   = $_SERVER['REQUEST_METHOD'];
$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$action   = $input['action'] ?? $_GET['action'] ?? '';

// ──────────────────────────────────────────────────────────────────────
// GET: list
// ──────────────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'list') {
    $q         = trim($_GET['q']        ?? '');
    $floorId   = (int)($_GET['floor_id'] ?? 0);
    $roomId    = (int)($_GET['room_id']  ?? 0);
    $status    = trim($_GET['status']    ?? '');
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $perPage   = min(100, (int)($_GET['per_page'] ?? 25));
    $offset    = ($page - 1) * $perPage;

    $where  = ["u.role = 'student'"];
    $params = [];
    $types  = '';

    if ($q) {
        $like      = "%$q%";
        $where[]   = "(u.student_code LIKE ? OR u.fullname LIKE ? OR u.email LIKE ?)";
        $params    = array_merge($params, [$like, $like, $like]);
        $types    .= 'sss';
    }
    if ($floorId) { $where[] = "f.id = ?"; $params[] = $floorId; $types .= 'i'; }
    if ($roomId)  { $where[] = "r.id = ?"; $params[] = $roomId;  $types .= 'i'; }
    if ($status)  { $where[] = "u.status = ?"; $params[] = $status; $types .= 's'; }

    $whereSQL = implode(' AND ', $where);

    $cnt = $conn->prepare("
        SELECT COUNT(*) as c FROM users u
        LEFT JOIN beds b ON u.bed_id = b.id
        LEFT JOIN rooms r ON b.room_id = r.id
        LEFT JOIN floors f ON r.floor_id = f.id
        WHERE $whereSQL
    ");
    if ($types) $cnt->bind_param($types, ...$params);
    $cnt->execute();
    $total = $cnt->get_result()->fetch_assoc()['c'] ?? 0;

    $stmt = $conn->prepare("
        SELECT u.user_id, u.student_code, u.fullname, u.email, u.gender,
               u.phone_personal, u.phone_family, u.hometown, u.status,
               u.is_room_leader, u.profile_picture, u.created_at,
               b.bed_label, r.room_code, r.id as room_id, f.floor_number
        FROM users u
        LEFT JOIN beds b  ON u.bed_id = b.id
        LEFT JOIN rooms r ON b.room_id = r.id
        LEFT JOIN floors f ON r.floor_id = f.id
        WHERE $whereSQL
        ORDER BY r.room_code ASC, b.bed_label ASC
        LIMIT ? OFFSET ?
    ");
    $allTypes  = $types . 'ii';
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success'     => true,
        'data'        => $students,
        'total'       => (int)$total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => max(1, ceil($total / $perPage)),
    ]);
    exit;
}

// ──────────────────────────────────────────────────────────────────────
// GET: single
// ──────────────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'get') {
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("
        SELECT u.*, b.bed_label, r.room_code, f.floor_number
        FROM users u
        LEFT JOIN beds b ON u.bed_id = b.id
        LEFT JOIN rooms r ON b.room_id = r.id
        LEFT JOIN floors f ON r.floor_id = f.id
        WHERE u.user_id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy sinh viên']);
        exit;
    }
    unset($student['password']); // Không trả về password
    echo json_encode(['success' => true, 'data' => $student]);
    exit;
}

// ──────────────────────────────────────────────────────────────────────
// Admin-only from here
// ──────────────────────────────────────────────────────────────────────
if ($userRole !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này']);
    exit;
}

// ──────────────────────────────────────────────────────────────────────
// POST: update
// ──────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'update') {
    $id        = (int)($input['user_id'] ?? 0);
    $fullname  = trim($input['fullname'] ?? '');
    $status    = $input['status'] ?? '';
    $isLeader  = (int)($input['is_room_leader'] ?? 0);
    $hometown  = trim($input['hometown'] ?? '');
    $phonePers = trim($input['phone_personal'] ?? '');

    if (!$id || !$fullname) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Thiếu thông tin bắt buộc']);
        exit;
    }

    $upd = $conn->prepare("
        UPDATE users SET fullname=?, status=?, is_room_leader=?, hometown=?, phone_personal=?
        WHERE user_id=? AND role='student'
    ");
    $upd->bind_param('ssissi', $fullname, $status, $isLeader, $hometown, $phonePers, $id);
    $upd->execute();

    echo json_encode(['success' => true, 'message' => 'Cập nhật thành công', 'affected' => $upd->affected_rows]);
    exit;
}

// ──────────────────────────────────────────────────────────────────────
// DELETE
// ──────────────────────────────────────────────────────────────────────
if ($method === 'DELETE' || ($method === 'POST' && $action === 'delete')) {
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Thiếu user_id']);
        exit;
    }
    $del = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'student'");
    $del->bind_param('i', $id);
    $del->execute();
    if ($del->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Đã xoá tài khoản sinh viên']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy hoặc không thể xoá']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Action không hợp lệ: ' . $action]);
