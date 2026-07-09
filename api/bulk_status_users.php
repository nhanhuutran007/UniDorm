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
    if ($status === 'active') {
        // Generate passwords and send emails for all activated users
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        $all = $upper . $lower . $numbers . $special;
        
        $authUpd = $conn->prepare("
            INSERT INTO auth_accounts (user_id, password, is_active, must_change_password, last_password_change)
            VALUES (?, ?, 1, 1, NOW())
            ON DUPLICATE KEY UPDATE password = VALUES(password), is_active = 1, must_change_password = 1, last_password_change = NOW()
        ");
        
        $emQuery = $conn->prepare("SELECT email, student_code, fullname FROM users WHERE user_id = ?");

        foreach ($targetIds as $targetId) {
            $password = '';
            $password .= $upper[random_int(0, strlen($upper) - 1)];
            $password .= $lower[random_int(0, strlen($lower) - 1)];
            $password .= $numbers[random_int(0, strlen($numbers) - 1)];
            $password .= $special[random_int(0, strlen($special) - 1)];
            for ($i = 4; $i < 8; $i++) {
                $password .= $all[random_int(0, strlen($all) - 1)];
            }
            $newPassword = str_shuffle($password);
            $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $authUpd->bind_param('is', $targetId, $hashed);
            $authUpd->execute();
            
            $_SESSION['new_password'][$targetId] = $newPassword;
            
            // Gửi email
            $emQuery->bind_param('i', $targetId);
            $emQuery->execute();
            $uData = $emQuery->get_result()->fetch_assoc();
            
            if ($uData) {
                $email = $uData['email'] ?? $uData['student_code'].'@student.tdtu.edu.vn';
                $subject = '[UniDorm] Kích hoạt tài khoản Ký túc xá';
                $body = "Xin chào {$uData['fullname']},\n\n"
                      . "Tài khoản Ký túc xá UniDorm của bạn đã được kích hoạt thành công.\n\n"
                      . "Thông tin đăng nhập:\n"
                      . "- Tên đăng nhập (MSSV): {$uData['student_code']}\n"
                      . "- Mật khẩu: $newPassword\n\n"
                      . "Lưu ý: Vì lý do bảo mật, bạn sẽ được yêu cầu đổi lại mật khẩu này ngay trong lần đăng nhập đầu tiên.\n\n"
                      . "Trân trọng,\nBan Quản lý Ký túc xá UniDorm.";
                @mail($email, $subject, $body, "From: noreply@unidorm.tdtu.edu.vn\r\nContent-Type: text/plain; charset=utf-8\r\n");
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Đã cập nhật trạng thái thành công']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $conn->error]);
}
