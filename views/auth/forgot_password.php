<?php
/**
 * UniDorm – Auth: Quên mật khẩu (Forgot Password)
 * path: views/auth/forgot_password.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /UniDorm/');
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/services/MailService.php';
$mailService = new MailService();
$error = $success = '';
$step  = 'request';

// Process reset token
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
if ($token) {
    $step = 'reset';
    $tokenStmt = $conn->prepare("
        SELECT prt.user_id, prt.expires_at, prt.used,
               u.fullname, u.email, u.student_code, u.role
        FROM password_reset_tokens prt
        JOIN users u ON prt.user_id = u.user_id
        WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used = 0
    ");
    $tokenStmt->bind_param('s', $token);
    $tokenStmt->execute();
    $tokenUser = $tokenStmt->get_result()->fetch_assoc();

    if (!$tokenUser) {
        $step  = 'invalid';
        $error = 'Liên kết không hợp lệ hoặc đã hết hạn (1 giờ). Vui lòng yêu cầu lại.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenUser) {
        $newPass     = $_POST['new_password']     ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (strlen($newPass) < 8) {
            $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
        } elseif (!preg_match('/[A-Z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
            $error = 'Mật khẩu phải có ít nhất 1 chữ hoa và 1 chữ số.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'Mật khẩu xác nhận không khớp.';
        } else {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT);
            $conn->begin_transaction();
            try {
                $authStmt = $conn->prepare("
                    INSERT INTO auth_accounts (user_id, password, is_active, last_password_change)
                    VALUES (?, ?, 1, NOW())
                    ON DUPLICATE KEY UPDATE password = VALUES(password), last_password_change = NOW()
                ");
                $authStmt->bind_param('is', $tokenUser['user_id'], $hashed);
                $authStmt->execute();

                $delStmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
                $delStmt->bind_param('s', $token);
                $delStmt->execute();

                $conn->commit();
                $success = 'reset_done';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Lỗi hệ thống: ' . $e->getMessage();
            }
        }
    }
}

// Request reset link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$token) {
    $identifier = strtoupper(trim($_POST['identifier'] ?? ''));

    if (empty($identifier)) {
        $error = 'Vui lòng nhập MSSV hoặc email của bạn.';
    } else {
        $findStmt = $conn->prepare("
            SELECT user_id, fullname, email, student_code, role, status
            FROM users
            WHERE student_code = ? OR email = ? OR username = ?
            LIMIT 1
        ");
        $findStmt->bind_param('sss', $identifier, $identifier, $identifier);
        $findStmt->execute();
        $foundUser = $findStmt->get_result()->fetch_assoc();

        if (!$foundUser || !in_array($foundUser['status'], ['active', 'pending'])) {
            $success = 'email_sent';
        } else {
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt  = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $saveStmt = $conn->prepare("
                INSERT INTO password_reset_tokens (user_id, token, expires_at)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), used = 0
            ");
            $saveStmt->bind_param('iss', $foundUser['user_id'], $resetToken, $expiresAt);
            $saveStmt->execute();

            $resetUrl = "https://{$_SERVER['HTTP_HOST']}/UniDorm/views/auth/forgot_password.php?token=$resetToken";
            $email    = $foundUser['email'] ?? ($foundUser['student_code'] . '@student.tdtu.edu.vn');
            $mailService->sendPasswordReset($email, $foundUser['fullname'], $resetUrl);
            $success = 'email_sent';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu – UniDorm</title>
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
        align-items: center;
        justify-content: center;
        padding: 40px;
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
    
    .forgot-container {
        position: relative;
        z-index: 10;
        width: 100%;
        max-width: 480px;
        text-align: center;
    }
    
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
    
    .main-title {
        font-size: 32px;
        font-weight: 800;
        color: #ffffff;
        margin-bottom: 35px;
        letter-spacing: -0.5px;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }
    
    .forgot-card {
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(20px);
        border-radius: 24px;
        padding: 45px 40px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        position: relative;
        border: 1px solid rgba(255, 255, 255, 0.5);
        text-align: left;
    }
    
    .card-blob {
        position: absolute;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
        filter: blur(40px);
        opacity: 0.3;
        top: -40px;
        right: -30px;
        z-index: -1;
    }
    
    .form-group {
        margin-bottom: 20px;
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
    
    .btn-submit {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
        color: white;
        font-size: 16px;
        font-weight: 700;
        border: none;
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 8px 24px rgba(245, 158, 11, 0.3);
        margin-top: 10px;
    }
    
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 32px rgba(245, 158, 11, 0.4);
    }
    
    .btn-submit:active {
        transform: translateY(0);
    }
    
    .btn-submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
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
    
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #667eea;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 20px;
        transition: color 0.3s;
    }
    
    .back-link:hover {
        color: #764ba2;
    }
    
    .strength-bar {
        height: 4px;
        border-radius: 4px;
        width: 0%;
        transition: width 0.3s, background 0.3s;
        margin-top: 8px;
    }
    
    .strength-text {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }
    
    .footer-badges {
        margin-top: 30px;
        display: flex;
        justify-content: center;
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
    
    @media (max-width: 576px) {
        body {
            padding: 20px;
        }
        
        .forgot-card {
            padding: 35px 25px;
        }
        
        .main-title {
            font-size: 26px;
            margin-bottom: 30px;
        }
        
        .badge-item {
            font-size: 9px;
            padding: 8px 14px;
        }
    }
    </style>
</head>
<body>

<div class="forgot-container">
    <div class="logo-section">
        <div class="logo-wrapper">
            <div class="logo-icon">
                <i class="bi bi-building-fill"></i>
            </div>
            <span class="logo-text">UniDorm</span>
        </div>
    </div>
    
    <h1 class="main-title">
        <?php if ($success === 'reset_done'): ?>
            Hoàn tất!
        <?php elseif ($success === 'email_sent'): ?>
            Email đã gửi
        <?php elseif ($step === 'reset'): ?>
            Đặt mật khẩu mới
        <?php else: ?>
            Quên mật khẩu?
        <?php endif; ?>
    </h1>
    
    <div class="forgot-card">
        <div class="card-blob"></div>
        
        <?php if ($success === 'reset_done'): ?>
        <!-- Success - Password Reset -->
        <div class="text-center">
            <div style="width:64px;height:64px;margin:0 auto 20px;background:rgba(34,197,94,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-check2-circle" style="font-size:32px;color:#22c55e;"></i>
            </div>
            <h5 style="font-weight:700;color:#2d3748;margin-bottom:10px;">Mật khẩu đã được đặt lại!</h5>
            <p style="color:#6b7280;font-size:14px;margin-bottom:25px;">Bạn có thể đăng nhập với mật khẩu mới.</p>
            <a href="login.php" class="btn-submit" style="display:inline-block;width:auto;padding:14px 30px;text-decoration:none;">
                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
            </a>
        </div>
        
        <?php elseif ($success === 'email_sent'): ?>
        <!-- Success - Email Sent -->
        <div class="text-center">
            <div style="width:64px;height:64px;margin:0 auto 20px;background:rgba(102,126,234,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-envelope-check-fill" style="font-size:32px;color:#667eea;"></i>
            </div>
            <h5 style="font-weight:700;color:#2d3748;margin-bottom:10px;">Email đã được gửi!</h5>
            <p style="color:#6b7280;font-size:14px;margin-bottom:8px;">Nếu tài khoản tồn tại, bạn sẽ nhận được email đặt lại mật khẩu trong vài phút.</p>
            <p style="color:#6b7280;font-size:13px;margin-bottom:25px;">Kiểm tra cả thư mục <strong>Spam</strong> nếu không thấy.</p>
            <a href="login.php" class="back-link" style="display:inline-flex;">
                <i class="bi bi-arrow-left"></i>Quay về đăng nhập
            </a>
        </div>
        
        <?php elseif ($step === 'invalid'): ?>
        <!-- Invalid Token -->
        <div class="text-center">
            <div style="width:64px;height:64px;margin:0 auto 20px;background:rgba(239,68,68,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-x-octagon-fill" style="font-size:32px;color:#ef4444;"></i>
            </div>
            <h5 style="font-weight:700;color:#2d3748;margin-bottom:10px;">Liên kết không hợp lệ</h5>
            <p style="color:#6b7280;font-size:14px;margin-bottom:25px;"><?php echo htmlspecialchars($error); ?></p>
            <a href="forgot_password.php" class="btn-submit" style="display:inline-block;width:auto;padding:14px 30px;text-decoration:none;">
                Yêu cầu lại
            </a>
        </div>
        
        <?php elseif ($step === 'reset'): ?>
        <!-- Reset Password Form -->
        <a href="login.php" class="back-link">
            <i class="bi bi-arrow-left"></i>Quay về đăng nhập
        </a>
        
        <div style="text-align:center;margin-bottom:25px;">
            <p style="color:#6b7280;font-size:14px;">Xin chào <strong style="color:#2d3748;"><?php echo htmlspecialchars($tokenUser['fullname']); ?></strong></p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label class="form-label">Mật khẩu mới</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock input-icon"></i>
                    <input 
                        type="password" 
                        name="new_password" 
                        id="newPass"
                        class="form-input"
                        placeholder="Nhập mật khẩu mới"
                        style="padding-right:50px;"
                        oninput="checkStrength(this.value)"
                        required
                    >
                    <button type="button" class="toggle-password" onclick="togglePass('newPass','eye1')">
                        <i class="bi bi-eye" id="eye1"></i>
                    </button>
                </div>
                <div class="strength-bar" id="strBar"></div>
                <div class="strength-text" id="strTxt">Ít nhất 8 ký tự, 1 chữ hoa, 1 chữ số</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Xác nhận mật khẩu</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock input-icon"></i>
                    <input 
                        type="password" 
                        name="confirm_password" 
                        id="confPass"
                        class="form-input"
                        placeholder="Nhập lại mật khẩu"
                        style="padding-right:50px;"
                        required
                    >
                    <button type="button" class="toggle-password" onclick="togglePass('confPass','eye2')">
                        <i class="bi bi-eye" id="eye2"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-submit" id="submitBtn">
                Đặt lại mật khẩu
            </button>
        </form>
        
        <?php else: ?>
        <!-- Request Reset Form -->
        <a href="login.php" class="back-link">
            <i class="bi bi-arrow-left"></i>Quay về đăng nhập
        </a>
        
        <div style="text-align:center;margin-bottom:25px;">
            <p style="color:#6b7280;font-size:14px;">Nhập MSSV (sinh viên) hoặc email (quản trị) để nhận liên kết đặt lại mật khẩu</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">MSSV hoặc Email</label>
                <div class="input-wrapper">
                    <i class="bi bi-person input-icon"></i>
                    <input 
                        type="text" 
                        name="identifier"
                        class="form-input"
                        placeholder="VD: 52100001 hoặc admin@tdtu.edu.vn"
                        value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>"
                        required
                    >
                </div>
            </div>
            
            <button type="submit" class="btn-submit" id="submitBtn">
                Gửi liên kết đặt lại
            </button>
        </form>
        <?php endif; ?>
    </div>
    
    <div class="footer-badges">
        <div class="badge-item">
            Kỷ luật - Lễ phép - Chuyên nghiệp - Sáng tạo - Phụng sự
        </div>
    </div>
</div>

<script>
function togglePass(id, eyeId) {
    const inp = document.getElementById(id);
    const ico = document.getElementById(eyeId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

function checkStrength(val) {
    let s = 0;
    if (val.length >= 8) s++;
    if (/[A-Z]/.test(val)) s++;
    if (/[0-9]/.test(val)) s++;
    if (/[^A-Za-z0-9]/.test(val)) s++;
    
    const map = [
        [0, '0%', '#ef4444', 'Quá ngắn'],
        [25, '25%', '#ef4444', 'Yếu'],
        [50, '50%', '#f59e0b', 'Trung bình'],
        [75, '75%', '#3b82f6', 'Khá mạnh'],
        [100, '100%', '#22c55e', 'Mạnh']
    ];
    const [_, w, c, l] = map[Math.min(s, 4)];
    document.getElementById('strBar').style.cssText = `width:${w};background:${c}`;
    document.getElementById('strTxt').textContent = l;
}

document.querySelectorAll('form').forEach(f => {
    f.addEventListener('submit', () => {
        const btn = document.getElementById('submitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';
        }
    });
});

// Smooth crossfade page transition
document.addEventListener('DOMContentLoaded', function() {
    document.body.style.opacity = '1';
    
    const links = document.querySelectorAll('a[href*="login.php"], a[href*="register.php"]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetUrl = this.href;
            const container = document.querySelector('.forgot-container');
            const isLogin = targetUrl.includes('login.php');
            
            container.style.transition = 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.6s ease';
            container.style.transform = isLogin ? 'translateX(100vw)' : 'translateX(-100vw)';
            container.style.opacity = '0';
            
            const newBgImage = isLogin 
                ? "url('../../assets/img/KTX-TDT-KL-1024x525.png')" 
                : "url('../../assets/img/ktx-layout-1_0.png')";
            
            const img = new Image();
            img.onload = function() {
                document.body.style.transition = 'background-image 0.6s ease';
                document.body.style.backgroundImage = newBgImage;
            };
            img.src = isLogin ? '../../assets/img/KTX-TDT-KL-1024x525.png' : '../../assets/img/ktx-layout-1_0.png';
            
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 600);
        });
    });
});
</script>
</body>
</html>
