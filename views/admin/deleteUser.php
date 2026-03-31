<?php
/**
 * UniDorm – Admin: Xoá sinh viên (deleteUser.php)
 * Xử lý DELETE và redirect về userlists, không render UI.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// Auth guard
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /UniDorm/views/auth/login.php');
    exit;
}

$conn = require_once __DIR__ . '/../../includes/db.php';

$targetId  = (int)($_GET['id'] ?? 0);
$selfId    = (int)$_SESSION['user_id'];
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === '1';

if (!$targetId) {
    header('Location: /UniDorm/views/admin/userlists.php?error=missing_id');
    exit;
}

if ($targetId === $selfId) {
    header('Location: /UniDorm/views/admin/userlists.php?error=cannot_delete_self');
    exit;
}

// Lấy thông tin user trước khi xoá
$chk = $conn->prepare("SELECT user_id, fullname, role, bed_id FROM users WHERE user_id = ?");
$chk->bind_param('i', $targetId);
$chk->execute();
$target = $chk->get_result()->fetch_assoc();

if (!$target) {
    header('Location: /UniDorm/views/admin/userlists.php?error=not_found');
    exit;
}

if ($target['role'] !== 'student') {
    header('Location: /UniDorm/views/admin/userlists.php?error=cannot_delete_admin');
    exit;
}

// Hiển thị xác nhận nếu chưa confirm
if (!$confirmed) {
    $name = htmlspecialchars($target['fullname']);
    echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>Xác nhận xoá</title>
    <link rel='stylesheet' href='/UniDorm/assets/css/bootstrap.min.css'></head>
    <body class='bg-light d-flex align-items-center justify-content-center' style='min-height:100vh;'>
    <div class='card shadow-sm border-0 p-4' style='max-width:420px;border-radius:14px;'>
        <h5 class='fw-bold mb-1 text-danger'>⚠️ Xác nhận xoá tài khoản</h5>
        <p class='text-muted mb-4'>Bạn có chắc muốn xoá tài khoản sinh viên <strong>{$name}</strong>? Thao tác này không thể hoàn tác.</p>
        <div class='d-flex gap-2'>
            <a href='/UniDorm/views/admin/deleteUser.php?id={$target['user_id']}&confirm=1' class='btn btn-danger flex-grow-1'>Xoá</a>
            <a href='/UniDorm/views/admin/userlists.php' class='btn btn-outline-secondary flex-grow-1'>Huỷ</a>
        </div>
    </div></body></html>";
    exit;
}

// Thực hiện xoá
$conn->begin_transaction();
try {
    // Giải phóng giường
    if ($target['bed_id']) {
        $freeBed = $conn->prepare("UPDATE beds SET is_occupied = 0 WHERE id = ?");
        $freeBed->bind_param('i', $target['bed_id']);
        $freeBed->execute();

        // Reset phòng về available nếu đang full
        $roomId = $conn->query("SELECT room_id FROM beds WHERE id = {$target['bed_id']}")->fetch_assoc()['room_id'] ?? null;
        if ($roomId) {
            $conn->prepare("UPDATE rooms SET status='available' WHERE id = ? AND status='full'")->execute() || true;
            $ar = $conn->prepare("UPDATE rooms SET status='available' WHERE id = ? AND status='full'");
            $ar->bind_param('i', $roomId); $ar->execute();
        }
    }
    // Xoá user (CASCADE sẽ xoá auth_accounts, tokens nếu đã có FK)
    $del = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'student'");
    $del->bind_param('i', $targetId);
    $del->execute();
    $conn->commit();
    header('Location: /UniDorm/views/admin/userlists.php?deleted=1');
} catch (Exception $e) {
    $conn->rollback();
    header('Location: /UniDorm/views/admin/userlists.php?error=delete_failed');
}
exit;
