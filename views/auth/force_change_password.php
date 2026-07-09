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
    <link rel="icon" type="image/svg+xml" href="<?php echo BASE_URL; ?>/assets/img/favicon.svg">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
        font-family: 'Inter', sans-serif;
        background: url('<?php echo BASE_URL; ?>/assets/img/KTX-TDT-KL-1024x525.png') no-repeat center center fixed;
        background-size: cover;
        min-height: 100vh;
        display: flex;
        align-items: flex-start;
        justify-content: flex-end;
        padding: 40px;
        padding-right: calc(5% + 40px);
        position: relative;
        overflow: hidden;
    }
    
    body::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: transparent; z-index: 1; }
    
    .login-container { position: relative; z-index: 10; width: 100%; max-width: 450px; text-align: center; margin-top: 20px; }
    
    .logo-section { margin-bottom: 30px; }
    .logo-wrapper { display: inline-flex; align-items: center; gap: 12px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 12px 24px; border-radius: 50px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; }
    .logo-text { font-size: 24px; font-weight: 800; color: #2d3748; }
    
    .main-title { font-size: 32px; font-weight: 800; color: #ffffff; margin-bottom: 35px; letter-spacing: -0.5px; text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3); transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.6s ease; }
    
    .login-card { background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(20px); border-radius: 24px; padding: 45px 40px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25); position: relative; animation: slideUp 0.6s ease-out; border: 1px solid rgba(255, 255, 255, 0.5); }
    @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    
    .card-blob { position: absolute; width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); filter: blur(40px); opacity: 0.3; top: -40px; right: -30px; z-index: -1; }
    
    .form-group { margin-bottom: 24px; text-align: left; }
    .form-label { display: block; font-size: 14px; font-weight: 600; color: #4a5568; margin-bottom: 8px; }
    .input-wrapper { position: relative; display: flex; align-items: center; }
    .input-icon { position: absolute; left: 18px; color: #a0aec0; font-size: 18px; z-index: 10; }
    .form-input { width: 100%; padding: 16px 18px 16px 50px; border: 2px solid #e2e8f0; border-radius: 14px; font-size: 15px; color: #2d3748; background: #f7fafc; transition: all 0.3s ease; outline: none; }
    .form-input::placeholder { color: #cbd5e0; }
    .form-input:focus { background: white; border-color: #667eea; box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1); }
    
    .toggle-password { position: absolute; right: 18px; background: none; border: none; color: #a0aec0; cursor: pointer; padding: 5px; font-size: 18px; transition: color 0.3s; }
    .toggle-password:hover { color: #667eea; }
    
    .btn-signin { width: 100%; padding: 16px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; font-size: 16px; font-weight: 700; border: none; border-radius: 14px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3); margin-top: 10px; }
    .btn-signin:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(16, 185, 129, 0.4); }
    .btn-signin:active { transform: translateY(0); }
    
    .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; display: flex; align-items: start; gap: 10px; }
    .alert-danger { background: #fee; color: #c53030; border-left: 4px solid #fc8181; }
    .alert-success { background: #f0fdf4; color: #166534; border-left: 4px solid #86efac; }
    
    .footer-badges { margin-top: 30px; display: flex; justify-content: center; flex-wrap: wrap; gap: 0; }
    .badge-item { display: inline-flex; align-items: center; justify-content: center; font-size: 11px; color: #ffffff; font-weight: 600; background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(15px); padding: 10px 20px; text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2); letter-spacing: 0.5px; border-radius: 50px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15); border: 1px solid rgba(255, 255, 255, 0.3); white-space: nowrap; }
    
    @media (max-width: 576px) {
        body { align-items: center; justify-content: center; padding: 20px; }
        .login-card { padding: 35px 25px; }
        .main-title { font-size: 26px; margin-bottom: 30px; }
        .badge-item { font-size: 9px; padding: 8px 14px; letter-spacing: 0.3px; }
    }
    @media (max-width: 992px) { body { justify-content: center; padding-right: 40px; } }
    </style>
</head>
<body>

<div class="login-container">
    <!-- Logo -->
    <div class="logo-section">
        <div class="logo-wrapper">
            <div class="logo-icon">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <span class="logo-text">Bảo mật</span>
        </div>
    </div>
    
    <!-- Main Title -->
    <h1 class="main-title">Đổi mật khẩu mới</h1>
    
    <!-- Login Card -->
    <div class="login-card">
        <div class="card-blob"></div>
        <p class="text-muted small mb-4" style="color: #4a5568 !important;">Bạn cần đổi mật khẩu trong lần đăng nhập đầu tiên để bảo mật tài khoản.</p>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <div>Đổi mật khẩu thành công! Đang chuyển hướng...</div>
        </div>
        <?php else: ?>
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" id="changePassForm" autocomplete="off">
            <!-- Password 1 Field -->
            <div class="form-group">
                <label class="form-label">Mật khẩu mới</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock input-icon"></i>
                    <input 
                        type="password" 
                        name="password" 
                        id="password"
                        class="form-input"
                        placeholder="Tối thiểu 8 ký tự, có chữ hoa và số"
                        required
                    >
                    <button type="button" class="toggle-password" onclick="togglePassword('password', 'eyeIcon1')">
                        <i class="bi bi-eye" id="eyeIcon1"></i>
                    </button>
                </div>
            </div>
            
            <!-- Password 2 Field -->
            <div class="form-group">
                <label class="form-label">Nhập lại mật khẩu</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock-fill input-icon"></i>
                    <input 
                        type="password" 
                        name="password2" 
                        id="password2"
                        class="form-input"
                        placeholder="Xác nhận mật khẩu mới"
                        required
                    >
                    <button type="button" class="toggle-password" onclick="togglePassword('password2', 'eyeIcon2')">
                        <i class="bi bi-eye" id="eyeIcon2"></i>
                    </button>
                </div>
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="btn-signin" id="submitBtn">
                Lưu mật khẩu & Tiếp tục
            </button>
        </form>
        <?php endif; ?>
    </div>
    
    <!-- Footer Badges -->
    <div class="footer-badges">
        <div class="badge-item">
            Kỷ luật - Lễ phép - Chuyên nghiệp - Sáng tạo - Phụng sự
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, iconId) {
    const passwordInput = document.getElementById(inputId);
    const eyeIcon = document.getElementById(iconId);
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.className = 'bi bi-eye-slash';
    } else {
        passwordInput.type = 'password';
        eyeIcon.className = 'bi bi-eye';
    }
}

const form = document.getElementById('changePassForm');
if (form) {
    form.addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang lưu...';
    });
}
</script>
</body>
</html>
