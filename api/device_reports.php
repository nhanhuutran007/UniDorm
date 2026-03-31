<?php
/**
 * UniDorm – API: Device Reports
 * path: api/device_reports.php
 *
 * GET  ?action=list        → danh sách báo cáo (admin: all, student: của mình)
 * POST action=create       → student tạo báo cáo mới
 * POST action=update_status → admin cập nhật trạng thái
 * POST action=delete       → admin xoá báo cáo
 */
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

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
// GET list
// ──────────────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'list') {
    $status  = trim($_GET['status']  ?? '');
    $roomId  = (int)($_GET['room_id'] ?? 0);
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    // Student chỉ xem báo cáo phòng mình
    if ($userRole === 'student') {
        $where[] = 'dr.reporter_id = ?';
        $params[] = $userId; $types .= 'i';
    }
    if ($status)  { $where[] = 'dr.status = ?'; $params[] = $status; $types .= 's'; }
    if ($roomId)  { $where[] = 'dr.room_id = ?'; $params[] = $roomId; $types .= 'i'; }

    $whereSQL = implode(' AND ', $where);

    $cnt = $conn->prepare("SELECT COUNT(*) as c FROM device_reports dr WHERE $whereSQL");
    if ($types) $cnt->bind_param($types, ...$params);
    $cnt->execute();
    $total = $cnt->get_result()->fetch_assoc()['c'] ?? 0;

    $stmt = $conn->prepare("
        SELECT dr.id, dr.title, dr.description, dr.status, dr.created_at, dr.resolved_at,
               d.device_name, d.device_type,
               r.room_code, f.floor_number,
               u.fullname as reporter_name, u.student_code,
               ua.fullname as resolver_name
        FROM device_reports dr
        LEFT JOIN devices d   ON dr.device_id = d.id
        LEFT JOIN rooms r     ON dr.room_id = r.id
        LEFT JOIN floors f    ON r.floor_id = f.id
        LEFT JOIN users u     ON dr.reporter_id = u.user_id
        LEFT JOIN users ua    ON dr.resolved_by = ua.user_id
        WHERE $whereSQL
        ORDER BY dr.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $allTypes  = $types . 'ii';
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success'     => true,
        'data'        => $reports,
        'total'       => (int)$total,
        'page'        => $page,
        'total_pages' => max(1, ceil($total / $perPage)),
    ]);
    exit;
}

// ──────────────────────────────────────────────────────────────────────
// POST: create (student)
// ──────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'create') {
    $deviceId   = (int)($input['device_id'] ?? 0);
    $title      = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');

    if (!$title) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tiêu đề báo cáo không được để trống']);
        exit;
    }

    // Lấy room_id từ user hiện tại
    $userInfo = $conn->prepare("SELECT u.bed_id, b.room_id FROM users u LEFT JOIN beds b ON u.bed_id = b.id WHERE u.user_id = ?");
    $userInfo->bind_param('i', $userId);
    $userInfo->execute();
    $ui = $userInfo->get_result()->fetch_assoc();

    $roomId = $ui['room_id'] ?? null;
    if (!$roomId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Bạn chưa được gán phòng. Không thể báo cáo.']);
        exit;
    }

    $ins = $conn->prepare("
        INSERT INTO device_reports (device_id, room_id, reporter_id, title, description, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $devId = $deviceId ?: null;
    $ins->bind_param('iiiss', $devId, $roomId, $userId, $title, $description);

    if ($ins->execute()) {
        $newId = $conn->insert_id;
        echo json_encode(['success' => true, 'message' => 'Báo cáo đã gửi thành công!', 'id' => $newId]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Lỗi khi tạo báo cáo: ' . $conn->error]);
    }
    exit;
}

// ──────────────────────────────────────────────────────────────────────
// Admin-only from here
// ──────────────────────────────────────────────────────────────────────
if ($userRole !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Không có quyền thực hiện thao tác này']);
    exit;
}

// ──────────────────────────────────────────────────────────────────────
// POST: update_status (admin)
// ──────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'update_status') {
    $reportId  = (int)($input['report_id'] ?? 0);
    $newStatus = trim($input['status'] ?? '');
    $validSt   = ['pending', 'in_progress', 'resolved', 'rejected'];

    if (!$reportId || !in_array($newStatus, $validSt)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }

    $resolvedAt = in_array($newStatus, ['resolved','rejected']) ? date('Y-m-d H:i:s') : null;
    $resolvedBy = in_array($newStatus, ['resolved','rejected']) ? $userId : null;

    $upd = $conn->prepare("
        UPDATE device_reports SET status=?, resolved_by=?, resolved_at=?, updated_at=NOW()
        WHERE id=?
    ");
    $upd->bind_param('siis', $newStatus, $resolvedBy, $resolvedAt, $reportId);

    if ($upd->execute()) {
        // Nếu resolved, cập nhật device status
        if ($newStatus === 'resolved') {
            $getDevice = $conn->prepare("SELECT device_id FROM device_reports WHERE id = ?");
            $getDevice->bind_param('i', $reportId);
            $getDevice->execute();
            $devId = $getDevice->get_result()->fetch_assoc()['device_id'] ?? null;
            if ($devId) {
                $conn->prepare("UPDATE devices SET status='good' WHERE id=?")->execute() || true;
                $ud = $conn->prepare("UPDATE devices SET status='good' WHERE id=?");
                $ud->bind_param('i', $devId); $ud->execute();
            }
        }
        echo json_encode(['success' => true, 'message' => 'Đã cập nhật trạng thái báo cáo']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật: ' . $conn->error]);
    }
    exit;
}

// ──────────────────────────────────────────────────────────────────────
// POST: delete (admin)
// ──────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'delete') {
    $reportId = (int)($input['report_id'] ?? 0);
    if (!$reportId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Thiếu report_id']);
        exit;
    }
    $del = $conn->prepare("DELETE FROM device_reports WHERE id = ?");
    $del->bind_param('i', $reportId);
    echo json_encode([
        'success' => $del->execute() && $del->affected_rows > 0,
        'message' => $del->affected_rows > 0 ? 'Đã xoá báo cáo' : 'Không tìm thấy báo cáo',
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Action không hợp lệ: ' . $action]);
