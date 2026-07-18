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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Quên mật khẩu – UniDorm</title>
    <meta name="description" content="Đặt lại mật khẩu hệ thống quản lý ký túc xá UniDorm">
    <link rel="icon" type="image/svg+xml" href="../../assets/img/favicon.svg">
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html {
        -webkit-text-size-adjust: 100%;
        -ms-text-size-adjust: 100%;
        overflow: hidden;
        height: 100%;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 30%, #2563eb 60%, #60a5fa 100%);
        min-height: 100vh;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
        position: relative;
        overflow: hidden;
        touch-action: manipulation;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        overscroll-behavior: none;
    }

    body::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle at 30% 50%, rgba(96, 165, 250, 0.15) 0%, transparent 50%),
                    radial-gradient(circle at 70% 80%, rgba(37, 99, 235, 0.2) 0%, transparent 40%);
        z-index: 1;
    }

    #meshCanvas {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 2;
        pointer-events: none;
    }

    .login-container {
        position: relative;
        z-index: 10;
        width: 100%;
        max-width: 450px;
        text-align: center;
        margin: 0 auto;
    }

    .logo-section {
        margin-bottom: 30px;
    }

    .logo-wrapper {
        display: inline-flex;
        align-items: center;
        gap: 12px;
    }

    .logo-icon {
        width: 36px;
        height: 36px;
    }

    .logo-icon img {
        width: 100%;
        height: 100%;
    }

    .logo-text {
        font-size: 24px;
        font-weight: 800;
        color: #ffffff;
    }

    .main-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 38px;
        font-weight: 800;
        color: #ffffff;
        margin-bottom: 35px;
        letter-spacing: -1.5px;
        line-height: 1.15;
        background: linear-gradient(135deg, #ffffff 0%, #93c5fd 50%, #60a5fa 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.6s ease;
    }

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

    .links-wrapper {
        display: flex;
        justify-content: space-between;
        margin-top: 24px;
        font-size: 14px;
    }

    .subtitle-text {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 25px;
        line-height: 1.6;
    }

    .subtitle-text strong {
        color: #2d3748;
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

    .success-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .success-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-weight: 700;
        font-size: 18px;
        color: #2d3748;
        margin-bottom: 10px;
    }

    .success-desc {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 25px;
        line-height: 1.6;
    }

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
            font-size: 30px;
            margin-bottom: 30px;
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

    @media (max-width: 400px) {
        body {
            padding: 16px 12px;
        }
        .login-card {
            padding: 30px 20px;
            border-radius: 20px;
        }
        .logo-text {
            font-size: 20px;
        }
    }
    </style>
</head>
<body>
<canvas id="meshCanvas"></canvas>

<div class="login-container">
    <div class="logo-section">
        <div class="logo-wrapper">
            <div class="logo-icon">
                <img src="../../assets/img/favicon.svg" alt="UniDorm">
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

    <div class="login-card">
        <div class="card-blob"></div>

        <?php if ($success === 'reset_done'): ?>
        <div class="text-center">
            <div class="success-icon" style="background:rgba(34,197,94,0.1);">
                <i class="bi bi-check2-circle" style="font-size:32px;color:#22c55e;"></i>
            </div>
            <h5 class="success-title">Mật khẩu đã được đặt lại!</h5>
            <p class="success-desc">Bạn có thể đăng nhập với mật khẩu mới.</p>
            <a href="login.php" class="btn-signin" style="display:inline-block;width:auto;padding:14px 30px;text-decoration:none;">
                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
            </a>
        </div>

        <?php elseif ($success === 'email_sent'): ?>
        <div class="text-center">
            <div class="success-icon" style="background:rgba(102,126,234,0.1);">
                <i class="bi bi-envelope-check-fill" style="font-size:32px;color:#667eea;"></i>
            </div>
            <h5 class="success-title">Email đã được gửi!</h5>
            <p class="success-desc">Nếu tài khoản tồn tại, bạn sẽ nhận được email đặt lại mật khẩu trong vài phút.<br>Kiểm tra cả thư mục <strong>Spam</strong> nếu không thấy.</p>
            <a href="login.php" class="link" style="display:inline-flex;align-items:center;gap:6px;">
                <i class="bi bi-arrow-left"></i>Quay về đăng nhập
            </a>
        </div>

        <?php elseif ($step === 'invalid'): ?>
        <div class="text-center">
            <div class="success-icon" style="background:rgba(239,68,68,0.1);">
                <i class="bi bi-x-octagon-fill" style="font-size:32px;color:#ef4444;"></i>
            </div>
            <h5 class="success-title">Liên kết không hợp lệ</h5>
            <p class="success-desc"><?php echo htmlspecialchars($error); ?></p>
            <a href="forgot_password.php" class="btn-signin" style="display:inline-block;width:auto;padding:14px 30px;text-decoration:none;">
                Yêu cầu lại
            </a>
        </div>

        <?php elseif ($step === 'reset'): ?>
        <div class="subtitle-text" style="text-align:center;">
            Xin chào <strong><?php echo htmlspecialchars($tokenUser['fullname']); ?></strong>
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

            <button type="submit" class="btn-signin" id="submitBtn">
                Đặt lại mật khẩu
            </button>

            <div class="links-wrapper" style="justify-content: center;">
                <a href="login.php" class="link"><i class="bi bi-arrow-left me-1"></i>Quay về đăng nhập</a>
            </div>
        </form>

        <?php else: ?>
        <div class="subtitle-text" style="text-align:center;">
            Nhập MSSV (sinh viên) hoặc email (quản trị) để nhận liên kết đặt lại mật khẩu
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

            <button type="submit" class="btn-signin" id="submitBtn">
                Gửi liên kết đặt lại
            </button>

            <div class="links-wrapper" style="justify-content: center;">
                <a href="login.php" class="link"><i class="bi bi-arrow-left me-1"></i>Quay về đăng nhập</a>
            </div>
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
// ===== Constellation Particles =====
(function() {
    const canvas = document.getElementById('meshCanvas');
    const ctx = canvas.getContext('2d');
    let width, height;
    let mouse = { x: null, y: null, vx: 0, vy: 0, px: 0, py: 0 };
    let particles = [];
    const PARTICLE_COUNT = 130;

    function resize() {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    }

    function createParticle(x, y) {
        return {
            x: x !== undefined ? x : Math.random() * width,
            y: y !== undefined ? y : Math.random() * height,
            vx: (Math.random() - 0.5) * 0.35,
            vy: (Math.random() - 0.5) * 0.35,
            radius: Math.random() * 1.6 + 0.8,
            baseAlpha: Math.random() * 0.4 + 0.2,
            pulseOffset: Math.random() * Math.PI * 2,
            pulseSpeed: Math.random() * 0.008 + 0.003,
        };
    }

    function initParticles() {
        particles = [];
        const count = Math.min(PARTICLE_COUNT, Math.floor((width * height) / 12000));
        for (let i = 0; i < count; i++) {
            particles.push(createParticle());
        }
    }

    function updateMouseTrail(e) {
        mouse.vx = e.clientX - mouse.px;
        mouse.vy = e.clientY - mouse.py;
        mouse.px = mouse.x = e.clientX;
        mouse.py = mouse.y = e.clientY;
    }

    document.addEventListener('mousemove', updateMouseTrail);
    document.addEventListener('mouseleave', function() {
        mouse.x = null;
        mouse.y = null;
    });

    function animate(time) {
        ctx.clearRect(0, 0, width, height);

        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const a = particles[i];
                const b = particles[j];
                const dx = a.x - b.x;
                const dy = a.y - b.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                const maxDist = 130;

                if (dist < maxDist) {
                    const t = 1 - dist / maxDist;
                    const alpha = t * t * 0.35;

                    ctx.beginPath();
                    ctx.moveTo(a.x, a.y);
                    ctx.lineTo(b.x, b.y);
                    ctx.strokeStyle = 'rgba(120, 180, 255, ' + alpha.toFixed(4) + ')';
                    ctx.lineWidth = t * 0.8;
                    ctx.stroke();
                }
            }
        }

        for (let i = 0; i < particles.length; i++) {
            const p = particles[i];

            if (mouse.x !== null) {
                const dx = p.x - mouse.x;
                const dy = p.y - mouse.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                const mouseRange = 160;

                if (dist < mouseRange && dist > 0) {
                    const t = 1 - dist / mouseRange;
                    p.vx += (dx / dist) * t * 0.15;
                    p.vy += (dy / dist) * t * 0.15;
                    p.vx += mouse.vx * t * 0.02;
                    p.vy += mouse.vy * t * 0.02;
                }
            }

            p.vx *= 0.985;
            p.vy *= 0.985;

            const speed = Math.sqrt(p.vx * p.vx + p.vy * p.vy);
            if (speed < 0.08) {
                p.vx += (Math.random() - 0.5) * 0.06;
                p.vy += (Math.random() - 0.5) * 0.06;
            }

            p.x += p.vx;
            p.y += p.vy;

            if (p.x < -10) p.x = width + 10;
            if (p.x > width + 10) p.x = -10;
            if (p.y < -10) p.y = height + 10;
            if (p.y > height + 10) p.y = -10;

            const pulse = Math.sin(time * p.pulseSpeed + p.pulseOffset) * 0.15;
            const alpha = Math.max(0.1, p.baseAlpha + pulse);

            let glowBoost = 0;
            if (mouse.x !== null) {
                const dx = p.x - mouse.x;
                const dy = p.y - mouse.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < 200) {
                    glowBoost = (1 - dist / 200) * 0.5;
                }
            }

            const glowRadius = p.radius * 4 + glowBoost * 6;
            const grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, glowRadius);
            grad.addColorStop(0, 'rgba(147, 197, 253, ' + ((alpha + glowBoost) * 0.3).toFixed(4) + ')');
            grad.addColorStop(1, 'rgba(147, 197, 253, 0)');
            ctx.beginPath();
            ctx.arc(p.x, p.y, glowRadius, 0, Math.PI * 2);
            ctx.fillStyle = grad;
            ctx.fill();

            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(200, 225, 255, ' + Math.min(alpha + glowBoost + 0.2, 1).toFixed(4) + ')';
            ctx.fill();
        }

        if (mouse.x !== null) {
            const grad = ctx.createRadialGradient(mouse.x, mouse.y, 0, mouse.x, mouse.y, 200);
            grad.addColorStop(0, 'rgba(96, 165, 250, 0.06)');
            grad.addColorStop(0.5, 'rgba(96, 165, 250, 0.02)');
            grad.addColorStop(1, 'rgba(96, 165, 250, 0)');
            ctx.beginPath();
            ctx.arc(mouse.x, mouse.y, 200, 0, Math.PI * 2);
            ctx.fillStyle = grad;
            ctx.fill();
        }

        requestAnimationFrame(animate);
    }

    window.addEventListener('resize', function() {
        resize();
        initParticles();
    });
    resize();
    initParticles();
    animate(0);
})();

// ===== Utilities =====
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
    document.getElementById('strBar').style.cssText = 'width:' + w + ';background:' + c;
    document.getElementById('strTxt').textContent = l;
}

document.querySelectorAll('form').forEach(function(f) {
    f.addEventListener('submit', function() {
        var btn = document.getElementById('submitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';
        }
    });
});

// Smooth slide transition back to login
document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('a[href*="login.php"]');
    links.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var targetUrl = this.href;
            var logo = document.querySelector('.logo-section');
            var title = document.querySelector('.main-title');
            var card = document.querySelector('.login-card');
            var footer = document.querySelector('.footer-badges');

            document.body.style.transition = 'opacity 0.6s ease';
            document.body.style.opacity = '0';

            if (logo) { logo.style.transition = 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)'; logo.style.transform = 'translateX(-100vw)'; }
            title.style.transition = 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1) 0.05s';
            title.style.transform = 'translateX(-100vw)';
            card.style.transition = 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1) 0.1s';
            card.style.transform = 'translateX(-100vw)';
            if (footer) { footer.style.transition = 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1) 0.15s'; footer.style.transform = 'translateX(-100vw)'; }

            setTimeout(function() {
                window.location.href = targetUrl;
            }, 600);
        });
    });
});
</script>
</body>
</html>
