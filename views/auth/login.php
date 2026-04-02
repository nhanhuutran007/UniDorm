<?php
/**
 * UniDorm – Auth: Login
 * path: views/auth/login.php
 * Hỗ trợ cả admin (username) và student (MSSV)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
$error = '';

if (isset($_GET['error'])) {
    $errorMap = [
        'not_activated'  => 'Tài khoản chưa được kích hoạt. Kiểm tra email sinh viên để đặt mật khẩu.',
        'session_expired'=> 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
        'user_not_found' => 'Tài khoản không tồn tại.',
    ];
    $error = $errorMap[$_GET['error']] ?? 'Có lỗi xảy ra. Vui lòng đăng nhập lại.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['username'] ?? ''); // MSSV hoặc username
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ MSSV/tên đăng nhập và mật khẩu.';
    } else {
        // Tìm user theo student_code (MSSV) hoặc username
        $stmt = $conn->prepare("
            SELECT u.user_id, u.fullname, u.role, u.status, u.profile_picture
            FROM users u
            WHERE u.student_code = ? OR u.username = ?
            LIMIT 1
        ");
        $stmt->bind_param('ss', $login, $login);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            $error = 'MSSV/tên đăng nhập không tồn tại trong hệ thống.';
        } elseif ($user['status'] === 'pending') {
            $error = 'Tài khoản chưa được kích hoạt. Kiểm tra email sinh viên để đặt mật khẩu.
                      <a href="register.php" class="alert-link">Đăng ký lại</a>';
        } elseif (!in_array($user['status'], ['active'])) {
            $error = 'Tài khoản đã bị khóa hoặc vô hiệu hóa. Liên hệ Ban quản lý.';
        } else {
            // Kiểm tra mật khẩu từ auth_accounts
            $authStmt = $conn->prepare("SELECT password FROM auth_accounts WHERE user_id = ? AND is_active = 1");
            $authStmt->bind_param('i', $user['user_id']);
            $authStmt->execute();
            $auth = $authStmt->get_result()->fetch_assoc();

            if (!$auth || !password_verify($password, $auth['password'])) {
                $error = 'Mật khẩu không chính xác.';
            } else {
                // Login thành công
                session_regenerate_id(true);
                $_SESSION['user_id']        = $user['user_id'];
                $_SESSION['role']           = $user['role'];
                $_SESSION['fullname']       = $user['fullname'];
                $_SESSION['status']         = $user['status'];
                $_SESSION['profile_picture']= $user['profile_picture'];
                $_SESSION['last_activity']  = time();

                // Redirect theo role
                if ($user['role'] === 'admin') {
                    header('Location: ../admin/dashboard.php');
                } else {
                    header('Location: ../student/dashboard.php');
                }
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập – UniDorm</title>
    <meta name="description" content="Đăng nhập hệ thống quản lý ký túc xá UniDorm">
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .auth-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(37,99,235,.12);
        overflow: hidden;
        width: 100%;
        max-width: 860px;
        display: flex;
    }
    .auth-left {
        background: linear-gradient(160deg, #1e3a5f 0%, #2563eb 100%);
        color: #fff;
        padding: 52px 44px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        flex: 0 0 44%;
    }
    .auth-left .logo-wrap { display: flex; align-items: center; gap: 12px; margin-bottom: 36px; }
    .auth-left .logo-icon { width: 48px; height: 48px; background: rgba(255,255,255,.15); border-radius: 14px; display: flex; align-items: center; justify-content: center; }
    .auth-left h1 { font-size: 22px; font-weight: 700; margin-bottom: 10px; line-height: 1.3; }
    .auth-left p  { font-size: 13px; opacity: .8; line-height: 1.8; }
    .role-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.12); border-radius: 20px; padding: 6px 14px; font-size: 12px; margin-top: 8px; }
    .auth-right { padding: 52px 48px; flex: 1; display: flex; flex-direction: column; justify-content: center; }
    .auth-right h2 { font-size: 24px; font-weight: 700; color: #1f2937; margin-bottom: 4px; }
    .auth-right .subtitle { font-size: 14px; color: #6b7280; margin-bottom: 32px; }
    .form-label { font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
    .form-control {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 11px 14px;
        font-size: 14px;
        transition: border-color .2s, box-shadow .2s;
        background: #f9fafb;
    }
    .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); background: #fff; }
    .btn-login {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff; border: none; border-radius: 10px;
        padding: 12px; font-size: 15px; font-weight: 600;
        width: 100%; cursor: pointer;
        transition: opacity .2s, transform .1s;
    }
    .btn-login:hover   { opacity: .92; }
    .btn-login:active  { transform: scale(.98); }
    .btn-login:disabled { opacity: .6; cursor: not-allowed; }
    @media (max-width: 768px) {
        .auth-card  { flex-direction: column; }
        .auth-left  { flex: 0 0 auto; padding: 28px 24px; }
        .auth-right { padding: 32px 24px; }
    }
    </style>
</head>
<body>
<div class="auth-card">
    <!-- Left -->
    <div class="auth-left">
        <div class="logo-wrap">
            <div class="logo-icon"><i class="bi bi-building-fill text-white fs-4"></i></div>
            <span style="font-size:22px;font-weight:700;">UniDorm</span>
        </div>
        <h1>Hệ thống Quản lý Ký túc xá</h1>
        <p>Đại học Tôn Đức Thắng<br>Đăng nhập để quản lý phòng ở, theo dõi thiết bị và liên lạc với Ban quản lý.</p>
        <div class="mt-4 d-flex flex-wrap gap-2">
            <span class="role-badge"><i class="bi bi-person-badge"></i>Sinh viên: đăng nhập bằng MSSV</span>
            <span class="role-badge"><i class="bi bi-shield-check"></i>Quản lý: đăng nhập bằng tên tài khoản</span>
        </div>
    </div>

    <!-- Right -->
    <div class="auth-right">
        <h2>Chào mừng trở lại! 👋</h2>
        <p class="subtitle">Nhập thông tin đăng nhập để tiếp tục</p>

        <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 d-flex gap-2 align-items-start mb-4">
            <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
            <div><?php echo $error; ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($_GET['registered'])): ?>
        <div class="alert alert-success rounded-3 d-flex gap-2 mb-4">
            <i class="bi bi-check-circle-fill mt-1 flex-shrink-0"></i>
            <div>Đăng ký thành công! Kiểm tra email sinh viên để kích hoạt tài khoản.</div>
        </div>
        <?php endif; ?>

        <form method="POST" id="loginForm" autocomplete="off">
            <div class="mb-3">
                <label class="form-label" for="username">MSSV hoặc tên đăng nhập</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                    <input type="text" name="username" id="username"
                           class="form-control border-start-0"
                           placeholder="VD: 52100001 (MSSV) hoặc admin"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           autocomplete="username" required>
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0" for="password">Mật khẩu</label>
                    <a href="forgot_password.php" class="text-primary text-decoration-none small">Quên mật khẩu?</a>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" name="password" id="password"
                           class="form-control border-start-0 border-end-0"
                           placeholder="Nhập mật khẩu" autocomplete="current-password" required>
                    <button type="button" class="btn btn-outline-secondary border-start-0" onclick="togglePass()" id="togglePassBtn">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <span class="small text-muted">Sinh viên chưa có tài khoản?</span>
            <a href="register.php" class="small fw-semibold text-primary ms-1">Đăng ký ngay</a>
        </div>
    </div>
</div>

<script>
function togglePass() {
    const inp = document.getElementById('password');
    const ico = document.getElementById('eyeIcon');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang đăng nhập...';
});
</script>
</body>
</html>