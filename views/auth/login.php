<?php
/**
 * UniDorm – Auth: Login
 * path: views/auth/login.php
 * Hỗ trợ cả admin (username) và student (MSSV)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}

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
            $authStmt = $conn->prepare("SELECT password, is_active FROM auth_accounts WHERE user_id = ?");
            $authStmt->bind_param('i', $user['user_id']);
            $authStmt->execute();
            $auth = $authStmt->get_result()->fetch_assoc();

            if (!$auth) {
                $error = 'Tài khoản chưa được cấu hình bảo mật. Vui lòng liên hệ BQL.';
            } elseif ($auth['is_active'] == 0 || empty($auth['password'])) {
                $error = 'Tài khoản chưa được tạo mật khẩu. Vui lòng chọn <a href="forgot_password.php" class="alert-link fw-bold">Quên mật khẩu</a> để đặt mật khẩu mới.';
            } elseif (!password_verify($password, $auth['password'])) {
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
                header('Location: ' . BASE_URL . '/dashboard');
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
    <link rel="icon" type="image/svg+xml" href="../../assets/img/favicon.svg">
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background: url('../../assets/img/KTX-TDT-KL-1024x525.png') no-repeat center center fixed;
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
    
    body::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: transparent;
        z-index: 1;
    }
    
    /* Main Container */
    .login-container {
        position: relative;
        z-index: 10;
        width: 100%;
        max-width: 450px;
        text-align: center;
        margin-top: 20px;
    }
    
    /* Logo */
    .logo-section {
        margin-bottom: 30px;
    }
    
    .logo-wrapper {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 12px 24px;
        border-radius: 50px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }
    
    .logo-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
    }
    
    .logo-text {
        font-size: 24px;
        font-weight: 800;
        color: #2d3748;
    }
    
    /* Main Title */
    .main-title {
        font-size: 32px;
        font-weight: 800;
        color: #ffffff;
        margin-bottom: 35px;
        letter-spacing: -0.5px;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.6s ease;
    }
    
    /* Login Card */
    .login-card {
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(20px);
        border-radius: 24px;
        padding: 45px 40px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        position: relative;
        animation: slideUp 0.6s ease-out;
        border: 1px solid rgba(255, 255, 255, 0.5);
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Decorative Blob on Card */
    .card-blob {
        position: absolute;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        filter: blur(40px);
        opacity: 0.3;
        top: -40px;
        right: -30px;
        z-index: -1;
    }
    
    /* Form Groups */
    .form-group {
        margin-bottom: 24px;
        text-align: left;
    }
    
    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 8px;
    }
    
    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }
    
    .input-icon {
        position: absolute;
        left: 18px;
        color: #a0aec0;
        font-size: 18px;
        z-index: 10;
    }
    
    .form-input {
        width: 100%;
        padding: 16px 18px 16px 50px;
        border: 2px solid #e2e8f0;
        border-radius: 14px;
        font-size: 15px;
        color: #2d3748;
        background: #f7fafc;
        transition: all 0.3s ease;
        outline: none;
    }
    
    .form-input::placeholder {
        color: #cbd5e0;
    }
    
    .form-input:focus {
        background: white;
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }
    
    .toggle-password {
        position: absolute;
        right: 18px;
        background: none;
        border: none;
        color: #a0aec0;
        cursor: pointer;
        padding: 5px;
        font-size: 18px;
        transition: color 0.3s;
    }
    
    .toggle-password:hover {
        color: #667eea;
    }
    
    /* Sign In Button */
    .btn-signin {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        font-size: 16px;
        font-weight: 700;
        border: none;
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
        margin-top: 10px;
    }
    
    .btn-signin:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 32px rgba(16, 185, 129, 0.4);
    }
    
    .btn-signin:active {
        transform: translateY(0);
    }
    
    .btn-signin:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    /* Links */
    .links-wrapper {
        display: flex;
        justify-content: space-between;
        margin-top: 24px;
        font-size: 14px;
    }
    
    .link {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s;
    }
    
    .link:hover {
        color: #764ba2;
        text-decoration: underline;
    }
    
    /* Alert */
    .alert {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 24px;
        font-size: 14px;
        display: flex;
        align-items: start;
        gap: 10px;
    }
    
    .alert-danger {
        background: #fee;
        color: #c53030;
        border-left: 4px solid #fc8181;
    }
    
    .alert-success {
        background: #f0fdf4;
        color: #166534;
        border-left: 4px solid #86efac;
    }
    
    /* Footer Badges */
    .footer-badges {
        margin-top: 30px;
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0;
    }
    
    .badge-item {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #ffffff;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.25);
        backdrop-filter: blur(15px);
        padding: 10px 20px;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        letter-spacing: 0.5px;
        border-radius: 50px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.3);
        white-space: nowrap;
    }
    
    .badge-dot {
        display: none;
    }
    
    /* Responsive */
    @media (max-width: 576px) {
        body {
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            padding: 35px 25px;
        }
        
        .main-title {
            font-size: 26px;
            margin-bottom: 30px;
        }
        
        .links-wrapper {
            flex-direction: column;
            gap: 12px;
            text-align: center;
        }
        
        .badge-item {
            font-size: 9px;
            padding: 8px 14px;
            letter-spacing: 0.3px;
        }
    }
    
    @media (max-width: 768px) {
        .badge-item {
            font-size: 10px;
            padding: 9px 16px;
        }
    }
    
    @media (max-width: 992px) {
        body {
            justify-content: center;
            padding-right: 40px;
        }
    }
    </style>
</head>
<body>

<div class="login-container">
    <!-- Logo -->
    <div class="logo-section">
        <div class="logo-wrapper">
            <div class="logo-icon">
                <i class="bi bi-building-fill"></i>
            </div>
            <span class="logo-text">UniDorm</span>
        </div>
    </div>
    
    <!-- Main Title -->
    <h1 class="main-title">Chào mừng trở lại</h1>
    
    <!-- Login Card -->
    <div class="login-card">
        <div class="card-blob"></div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><?php echo $error; ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($_GET['registered'])): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <div>Đăng ký thành công! Kiểm tra email để kích hoạt tài khoản.</div>
        </div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm" autocomplete="off">
            <!-- Email/Username Field -->
            <div class="form-group">
                <label class="form-label">MSSV hoặc tên đăng nhập</label>
                <div class="input-wrapper">
                    <i class="bi bi-person input-icon"></i>
                    <input 
                        type="text" 
                        name="username" 
                        id="username"
                        class="form-input"
                        placeholder="VD: 52100001 hoặc admin"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        autocomplete="username"
                        required
                    >
                </div>
            </div>
            
            <!-- Password Field -->
            <div class="form-group">
                <label class="form-label">Mật khẩu</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock input-icon"></i>
                    <input 
                        type="password" 
                        name="password" 
                        id="password"
                        class="form-input"
                        placeholder="Nhập mật khẩu của bạn"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="btn-signin" id="loginBtn">
                Đăng nhập
            </button>
            
            <!-- Links -->
            <div class="links-wrapper">
                <a href="register.php" class="link">Chưa có tài khoản?</a>
                <a href="forgot_password.php" class="link">Quên mật khẩu?</a>
            </div>
        </form>
    </div>
    
    <!-- Footer Badges -->
    <div class="footer-badges">
        <div class="badge-item">
            Kỷ luật - Lễ phép - Chuyên nghiệp - Sáng tạo - Phụng sự
        </div>
    </div>
</div>

<script>
// Toggle Password
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.className = 'bi bi-eye-slash';
    } else {
        passwordInput.type = 'password';
        eyeIcon.className = 'bi bi-eye';
    }
}

// Form Submit
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang đăng nhập...';
});

// Smooth continuous slide transition
window.addEventListener('load', function() {
    const logo = document.querySelector('.logo-section');
    const title = document.querySelector('.main-title');
    const card = document.querySelector('.login-card');
    const footer = document.querySelector('.footer-badges');
    
    if (!title || !card) return;
    
    // Intercept navigation for continuous slide
    const links = document.querySelectorAll('a[href*="register.php"], a[href*="forgot_password.php"]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetUrl = this.href;
            const isRegister = targetUrl.includes('register.php');
            
            // Start background transition immediately
            const newBgImage = isRegister 
                ? "url('../../assets/img/ktx-layout-1_0.png')" 
                : "url('../../assets/img/ktx-layout-1_0.png')";
            
            document.body.style.transition = 'background-image 0.6s ease';
            document.body.style.backgroundImage = newBgImage;
            
            // Slide out to left
            if (logo) logo.style.transition = 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            title.style.transition = 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1) 0.05s';
            card.style.transition = 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1) 0.1s';
            if (footer) footer.style.transition = 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1) 0.15s';
            
            if (logo) logo.style.transform = 'translateX(-100vw)';
            title.style.transform = 'translateX(-100vw)';
            card.style.transform = 'translateX(-100vw)';
            if (footer) footer.style.transform = 'translateX(-100vw)';
            
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 600);
        });
    });
});
</script>
</body>
</html>

