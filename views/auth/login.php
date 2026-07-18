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
            $error = 'Tài khoản chưa được kích hoạt. Kiểm tra email sinh viên để đặt mật khẩu.';
        } elseif (!in_array($user['status'], ['active'])) {
            $error = 'Tài khoản đã bị khóa hoặc vô hiệu hóa. Liên hệ Ban quản lý.';
        } else {
            // Kiểm tra mật khẩu từ auth_accounts
            $authStmt = $conn->prepare("SELECT password, is_active, must_change_password FROM auth_accounts WHERE user_id = ?");
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
                if ($auth['must_change_password'] == 1) {
                    header('Location: ' . BASE_URL . '/views/auth/force_change_password.php');
                    exit;
                }

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Đăng nhập – UniDorm</title>
    <meta name="description" content="Đăng nhập hệ thống quản lý ký túc xá UniDorm">
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
    
    /* Main Container */
    .login-container {
        position: relative;
        z-index: 10;
        width: 100%;
        max-width: 450px;
        text-align: center;
        margin: 0 auto;
    }
    
    /* Logo */
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
    
    /* Main Title */
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
            font-size: 30px;
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
    <!-- Logo -->
    <div class="logo-section">
        <div class="logo-wrapper">
            <div class="logo-icon">
                <img src="../../assets/img/favicon.svg" alt="UniDorm">
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
            <div class="links-wrapper" style="justify-content: center;">
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

        // Draw connections first (behind particles)
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

        // Update and draw particles
        for (let i = 0; i < particles.length; i++) {
            const p = particles[i];

            // Mouse interaction - gentle repulsion with flow
            if (mouse.x !== null) {
                const dx = p.x - mouse.x;
                const dy = p.y - mouse.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                const mouseRange = 160;

                if (dist < mouseRange && dist > 0) {
                    const t = 1 - dist / mouseRange;
                    // Repulsion
                    p.vx += (dx / dist) * t * 0.15;
                    p.vy += (dy / dist) * t * 0.15;
                    // Flow with mouse movement
                    p.vx += mouse.vx * t * 0.02;
                    p.vy += mouse.vy * t * 0.02;
                }
            }

            // Apply velocity with damping
            p.vx *= 0.985;
            p.vy *= 0.985;

            // Minimum drift so particles never stop completely
            const speed = Math.sqrt(p.vx * p.vx + p.vy * p.vy);
            if (speed < 0.08) {
                p.vx += (Math.random() - 0.5) * 0.06;
                p.vy += (Math.random() - 0.5) * 0.06;
            }

            p.x += p.vx;
            p.y += p.vy;

            // Wrap around edges
            if (p.x < -10) p.x = width + 10;
            if (p.x > width + 10) p.x = -10;
            if (p.y < -10) p.y = height + 10;
            if (p.y > height + 10) p.y = -10;

            // Pulsing alpha
            const pulse = Math.sin(time * p.pulseSpeed + p.pulseOffset) * 0.15;
            const alpha = Math.max(0.1, p.baseAlpha + pulse);

            // Mouse proximity glow boost
            let glowBoost = 0;
            if (mouse.x !== null) {
                const dx = p.x - mouse.x;
                const dy = p.y - mouse.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < 200) {
                    glowBoost = (1 - dist / 200) * 0.5;
                }
            }

            // Outer glow
            const glowRadius = p.radius * 4 + glowBoost * 6;
            const grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, glowRadius);
            grad.addColorStop(0, 'rgba(147, 197, 253, ' + ((alpha + glowBoost) * 0.3).toFixed(4) + ')');
            grad.addColorStop(1, 'rgba(147, 197, 253, 0)');
            ctx.beginPath();
            ctx.arc(p.x, p.y, glowRadius, 0, Math.PI * 2);
            ctx.fillStyle = grad;
            ctx.fill();

            // Core dot
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(200, 225, 255, ' + Math.min(alpha + glowBoost + 0.2, 1).toFixed(4) + ')';
            ctx.fill();
        }

        // Subtle mouse glow
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

// ===== Toggle Password =====
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
    const links = document.querySelectorAll('a[href*="forgot_password.php"]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetUrl = this.href;
            
            // Start background transition immediately
            document.body.style.transition = 'opacity 0.6s ease';
            document.body.style.opacity = '0';
            
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

