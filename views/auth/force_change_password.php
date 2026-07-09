<?php
/**
 * UniDorm – Auth: Force Change Password
 * path: views/auth/force_change_password.php
 *
 * Yêu cầu đổi mật khẩu khi đăng nhập lần đầu
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/views/auth/login.php');
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
$userId = $_SESSION['user_id'];

// Kiểm tra xem có thực sự cần đổi không
$authStmt = $conn->prepare("SELECT must_change_password FROM auth_accounts WHERE user_id = ?");
$authStmt->bind_param('i', $userId);
$authStmt->execute();
$auth = $authStmt->get_result()->fetch_assoc();

if (!$auth || $auth['must_change_password'] == 0) {
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } elseif ($password !== $password2) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Mật khẩu phải chứa ít nhất một chữ hoa và một chữ số.';
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $conn->begin_transaction();
        try {
            $updateStmt = $conn->prepare("
                UPDATE auth_accounts 
                SET password = ?, must_change_password = 0, last_password_change = NOW() 
                WHERE user_id = ?
            ");
            $updateStmt->bind_param('si', $hashed, $userId);
            $updateStmt->execute();

            $conn->commit();
            $success = true;
            
            // Redirect sau khi thông báo thành công
            header("refresh:2;url=" . BASE_URL . "/dashboard");
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Có lỗi xảy ra: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đổi mật khẩu bắt buộc – UniDorm</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f0f4ff, #e8f0fe); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .card-wrap { background: #fff; border-radius: 20px; box-shadow: 0 20px 60px rgba(37,99,235,.12); padding: 48px 44px; width: 100%; max-width: 460px; }
    .form-control { border: 1px solid #e5e7eb; border-radius: 10px; padding: 11px 14px; font-size: 14px; background: #f9fafb; transition: border-color .2s, box-shadow .2s; }
    .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); background: #fff; }
    .btn-set { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border: none; border-radius: 10px; padding: 12px; font-size: 15px; font-weight: 600; width: 100%; cursor: pointer; transition: opacity .2s; }
    .btn-set:hover { opacity: .92; }
    </style>
</head>
<body>
<div class="card-wrap">
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 rounded-3 mb-3" style="width:56px;height:56px;">
            <i class="bi bi-shield-lock text-warning fs-3"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Đổi mật khẩu</h4>
        <p class="text-muted small mb-0">Bạn cần đổi mật khẩu trong lần đăng nhập đầu tiên để bảo mật tài khoản.</p>
    </div>

    <?php if ($success): ?>
    <div class="text-center py-2">
        <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle mb-3" style="width:64px;height:64px;">
            <i class="bi bi-check2-circle text-success fs-2"></i>
        </div>
        <h5 class="fw-bold text-dark">Đổi mật khẩu thành công!</h5>
        <p class="text-muted small">Đang chuyển hướng tới trang chủ...</p>
    </div>
    <?php else: ?>
    <?php if ($error): ?>
    <div class="alert alert-danger rounded-3 d-flex gap-2 mb-3">
        <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
        <div><?php echo htmlspecialchars($error); ?></div>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-semibold small">Mật khẩu mới <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control"
                       placeholder="Tối thiểu 8 ký tự, có chữ hoa và số" required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold small">Nhập lại mật khẩu <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="password" name="password2" id="password2" class="form-control"
                       placeholder="Xác nhận mật khẩu mới" required>
            </div>
        </div>

        <button type="submit" class="btn-set">
            <i class="bi bi-save me-2"></i>Lưu mật khẩu & Tiếp tục
        </button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
