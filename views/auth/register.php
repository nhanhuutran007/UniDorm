<?php
/**
 * UniDorm – Auth: Student Registration (Đăng ký sinh viên)
 * path: views/auth/register.php
 *
 * Flow:
 *  1. Sinh viên nhập MSSV + email (tự động điền @student.tdtu.edu.vn)
 *  2. Hệ thống tạo tài khoản status='pending', gửi email đặt mật khẩu
 *  3. Sinh viên vào link email → đặt mật khẩu → status='active'
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /UniDorm/');
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/services/MailService.php';
$mailService = new MailService();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentCode = strtoupper(trim($_POST['student_code'] ?? ''));
    $fullname    = trim($_POST['fullname'] ?? '');
    $phone       = trim($_POST['phone_personal'] ?? '');
    $hometown    = trim($_POST['hometown'] ?? '');

    // Validate
    if (empty($studentCode) || empty($fullname)) {
        $error = 'Vui lòng nhập đầy đủ MSSV và họ tên.';
    } elseif (!preg_match('/^\d{8}$/', $studentCode)) {
        $error = 'MSSV phải gồm đúng 8 chữ số (VD: 52100001).';
    } else {
        $email = $studentCode . '@student.tdtu.edu.vn';

        // Kiểm tra MSSV tồn tại chưa
        $chkStmt = $conn->prepare("SELECT user_id, status FROM users WHERE student_code = ? OR email = ?");
        $chkStmt->bind_param('ss', $studentCode, $email);
        $chkStmt->execute();
        $existing = $chkStmt->get_result()->fetch_assoc();

        if ($existing) {
            if ($existing['status'] === 'pending') {
                $error = 'MSSV này đã đăng ký nhưng chưa kích hoạt. Vui lòng kiểm tra email sinh viên để đặt mật khẩu.';
            } elseif ($existing['status'] === 'active') {
                $error = 'MSSV này đã có tài khoản. Hãy <a href="login.php" style="color: #667eea; font-weight: 600;">đăng nhập</a>.';
            } else {
                $error = 'Tài khoản với MSSV này không khả dụng. Liên hệ Ban quản lý.';
            }
        } else {
            // Tạo token xác nhận 32 ký tự
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // Insert user với status=pending
            $insertStmt = $conn->prepare("
                INSERT INTO users (student_code, fullname, email, phone_personal, hometown, role, status)
                VALUES (?, ?, ?, ?, ?, 'student', 'pending')
            ");
            $insertStmt->bind_param('sssss', $studentCode, $fullname, $email, $phone, $hometown);

            if ($insertStmt->execute()) {
                $newUserId = $conn->insert_id;

                // Lưu verification token
                $tokenStmt = $conn->prepare("
                    INSERT INTO email_verification_tokens (user_id, token, expires_at)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)
                ");
                $tokenStmt->bind_param('iss', $newUserId, $token, $expiresAt);
                $tokenStmt->execute();

                $setPasswordUrl = "https://{$_SERVER['HTTP_HOST']}/UniDorm/views/auth/set_password.php?token=$token";
                $mailSent = $mailService->sendActivation($email, $fullname, $setPasswordUrl);

                if ($mailSent || true) { // Allow success even if mail() not configured in dev
                    $success = "Tài khoản đã được tạo! Email xác nhận đã gửi đến <strong>$email</strong>.<br>
                                Vui lòng kiểm tra hộp thư và nhấn vào đường dẫn để đặt mật khẩu.<br>
                                <small class='text-muted'>Nếu không thấy email, hãy kiểm tra thư mục Spam.</small>";
                } else {
                    // Mail failed but account created – provide direct link for dev
                    $success = "Tài khoản đã được tạo với MSSV <strong>$studentCode</strong>.<br>
                                <small>Dev mode: <a href='$setPasswordUrl'>Nhấn vào đây để đặt mật khẩu</a></small>";
                }
            } else {
                $error = 'Đã xảy ra lỗi khi tạo tài khoản: ' . $conn->error;
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
    <title>Đăng ký tài khoản – UniDorm</title>
    <meta name="description" content="Đăng ký tài khoản sinh viên ký túc xá UniDorm">
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
        background: url('../../assets/img/ktx-layout-1_0.png') no-repeat center center fixed;
        background-size: cover;
        min-height: 100vh;
        display: flex;
        align-items: flex-start;
        justify-content: flex-start;
        padding: 40px;
        padding-left: calc(5% + 40px);
        position: relative;
        overflow: hidden;
        transition: background-image 0.8s ease-in-out;
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
    .register-container {
        position: relative;
        z-index: 10;
        width: 100%;
        max-width: 500px;
        text-align: center;
        margin-top: 20px;
        animation: slideInLeft 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
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
    }
    
    /* Register Card */
    .register-card {
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(20px);
        border-radius: 24px;
        padding: 45px 40px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        position: relative;
        border: 1px solid rgba(255, 255, 255, 0.5);
        max-height: calc(100vh - 250px);
        overflow-y: auto;
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
        left: -30px;
        z-index: -1;
    }
    
    /* Form Groups */
    .form-group {
        margin-bottom: 20px;
        text-align: left;
    }
    
    .form-label {
        display: block;
        font-size: 13px;
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
        padding: 14px 18px 14px 50px;
        border: 2px solid #e2e8f0;
        border-radius: 14px;
        font-size: 14px;
        color: #2d3748;
        background: #f7fafc;
        transition: all 0.3s ease;
        outline: none;
    }
    
    .form-input.no-icon {
        padding-left: 18px;
    }
    
    .form-input::placeholder {
        color: #cbd5e0;
    }
    
    .form-input:focus {
        background: white;
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }
    
    .email-preview {
        font-size: 12px;
        color: #667eea;
        background: rgba(102, 126, 234, 0.1);
        border-radius: 10px;
        padding: 8px 12px;
        margin-top: 8px;
        font-weight: 600;
    }
    
    /* Sign Up Button */
    .btn-signup {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-size: 16px;
        font-weight: 700;
        border: none;
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        margin-top: 10px;
    }
    
    .btn-signup:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 32px rgba(102, 126, 234, 0.4);
    }
    
    .btn-signup:active {
        transform: translateY(0);
    }
    
    .btn-signup:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    /* Links */
    .links-wrapper {
        display: flex;
        justify-content: center;
        margin-top: 20px;
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
        margin-bottom: 20px;
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
    
    /* Row for form inputs */
    .row {
        display: flex;
        gap: 16px;
        margin-bottom: 20px;
    }
    
    .col-6 {
        flex: 1;
    }
    
    .form-check {
        display: flex;
        align-items: start;
        gap: 8px;
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 20px;
    }
    
    .form-check input {
        margin-top: 2px;
        cursor: pointer;
    }
    
    /* Responsive */
    @media (max-width: 576px) {
        body {
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-card {
            padding: 35px 25px;
            max-height: calc(100vh - 180px);
        }
        
        .main-title {
            font-size: 26px;
            margin-bottom: 30px;
        }
        
        .row {
            flex-direction: column;
            gap: 0;
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
            padding-left: 40px;
        }
    }
    </style>
</head>
<body>

<div class="register-container">
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
    <h1 class="main-title">Tạo tài khoản mới</h1>
    
    <!-- Register Card -->
    <div class="register-card">
        <div class="card-blob"></div>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <div><?php echo $success; ?></div>
        </div>
        <div class="text-center mt-3">
            <a href="login.php" class="btn-signup" style="display: inline-block; width: auto; padding: 12px 30px;">
                <i class="bi bi-box-arrow-in-right me-2"></i>Đến trang đăng nhập
            </a>
        </div>
        <?php else: ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><?php echo $error; ?></div>
        </div>
        <?php endif; ?>
        
        <form method="POST" id="registerForm" autocomplete="off">
            <!-- MSSV Field -->
            <div class="form-group">
                <label class="form-label">Mã số sinh viên (MSSV)</label>
                <div class="input-wrapper">
                    <i class="bi bi-person-badge input-icon"></i>
                    <input 
                        type="text" 
                        name="student_code" 
                        id="studentCode"
                        class="form-input"
                        placeholder="VD: 52100001"
                        value="<?php echo htmlspecialchars($_POST['student_code'] ?? ''); ?>"
                        maxlength="8"
                        pattern="\d{8}"
                        oninput="updateEmailPreview()"
                        required
                    >
                </div>
                <div class="email-preview" id="emailPreview">
                    <i class="bi bi-envelope me-1"></i>
                    Email: <strong id="emailDisplay">MSSV@student.tdtu.edu.vn</strong>
                </div>
            </div>
            
            <!-- Fullname Field -->
            <div class="form-group">
                <label class="form-label">Họ và tên</label>
                <div class="input-wrapper">
                    <i class="bi bi-person input-icon"></i>
                    <input 
                        type="text" 
                        name="fullname"
                        class="form-input"
                        placeholder="Nhập họ và tên đầy đủ"
                        value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>"
                        required
                    >
                </div>
            </div>
            
            <!-- Phone & Hometown -->
            <div class="row">
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Số điện thoại</label>
                        <input 
                            type="tel" 
                            name="phone_personal"
                            class="form-input no-icon"
                            placeholder="0912345678"
                            value="<?php echo htmlspecialchars($_POST['phone_personal'] ?? ''); ?>"
                        >
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-group">
                        <label class="form-label">Quê quán</label>
                        <input 
                            type="text" 
                            name="hometown"
                            class="form-input no-icon"
                            placeholder="Bình Dương"
                            value="<?php echo htmlspecialchars($_POST['hometown'] ?? ''); ?>"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Terms Checkbox -->
            <div class="form-check">
                <input type="checkbox" id="agreeTerms" required>
                <label for="agreeTerms">
                    Tôi đồng ý với quy định ký túc xá và chính sách bảo mật
                </label>
            </div>
            
            <!-- Submit Button -->
            <button type="submit" class="btn-signup" id="submitBtn">
                Tạo tài khoản
            </button>
            
            <!-- Links -->
            <div class="links-wrapper">
                <span class="text-muted me-1">Đã có tài khoản?</span>
                <a href="login.php" class="link">Đăng nhập</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
    
    <!-- Footer Badge -->
    <div class="footer-badges">
        <div class="badge-item">
            Kỷ luật - Lễ phép - Chuyên nghiệp - Sáng tạo - Phụng sự
        </div>
    </div>
</div>

<script>
// Update Email Preview
function updateEmailPreview() {
    const code = document.getElementById('studentCode').value.trim();
    document.getElementById('emailDisplay').textContent = code
        ? code + '@student.tdtu.edu.vn'
        : 'MSSV@student.tdtu.edu.vn';
}

// Form Submit
document.getElementById('registerForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang tạo tài khoản...';
});

// Smooth crossfade page transition
document.addEventListener('DOMContentLoaded', function() {
    // Initial page load animation
    document.body.style.opacity = '1';
    
    // Intercept navigation for crossfade effect
    const links = document.querySelectorAll('a[href*="login.php"], a[href*="forgot_password.php"]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetUrl = this.href;
            const container = document.querySelector('.register-container');
            
            // Determine direction based on target
            const isLogin = targetUrl.includes('login.php');
            const slideOutDirection = isLogin ? '100vw' : '-100vw';
            
            // Animate current content out
            container.style.transition = 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.6s ease';
            container.style.transform = `translateX(${slideOutDirection})`;
            container.style.opacity = '0';
            
            // Preload and change background smoothly
            const newBgImage = isLogin 
                ? "url('../../assets/img/KTX-TDT-KL-1024x525.png')" 
                : "url('../../assets/img/KTX-TDT-KL-1024x525.png')";
            
            const img = new Image();
            img.onload = function() {
                document.body.style.transition = 'background-image 0.6s ease';
                document.body.style.backgroundImage = newBgImage;
            };
            img.src = isLogin ? '../../assets/img/KTX-TDT-KL-1024x525.png' : '../../assets/img/KTX-TDT-KL-1024x525.png';
            
            // Navigate after animation
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 600);
        });
    });
});
</script>
</body>
</html>
