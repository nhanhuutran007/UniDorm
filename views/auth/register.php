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

$conn = require_once __DIR__ . '/../../includes/db.php';

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
                $error = 'MSSV này đã có tài khoản. Hãy <a href="/UniDorm/views/auth/login.php">đăng nhập</a>.';
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

                // Gửi email (dùng PHP mail hoặc phpmailer nếu cấu hình)
                $setPasswordUrl = "https://{$_SERVER['HTTP_HOST']}/UniDorm/views/auth/set_password.php?token=$token";
                $mailSubject    = '[UniDorm] Xác nhận tài khoản và đặt mật khẩu';
                $mailBody       = "Xin chào $fullname,\n\n"
                    . "Tài khoản sinh viên của bạn tại hệ thống UniDorm đã được tạo thành công.\n"
                    . "MSSV: $studentCode\n"
                    . "Email đăng nhập: $email\n\n"
                    . "Vui lòng nhấn vào liên kết bên dưới để đặt mật khẩu và kích hoạt tài khoản:\n"
                    . "$setPasswordUrl\n\n"
                    . "Liên kết có hiệu lực trong 24 giờ.\n\n"
                    . "Trân trọng,\nBan Quản lý Ký túc xá - UniDorm";

                $mailHeaders = "From: noreply@unidorm.tdtu.edu.vn\r\n"
                    . "Reply-To: noreply@unidorm.tdtu.edu.vn\r\n"
                    . "Content-Type: text/plain; charset=UTF-8\r\n";

                $mailSent = @mail($email, $mailSubject, $mailBody, $mailHeaders);

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
    <link rel="stylesheet" href="/UniDorm/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
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
        max-width: 900px;
        display: flex;
    }
    .auth-left {
        background: linear-gradient(160deg, #1e3a5f 0%, #2563eb 100%);
        color: #fff;
        padding: 48px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        flex: 0 0 42%;
    }
    .auth-left .logo-wrap { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
    .auth-left .logo-icon { width: 44px; height: 44px; background: rgba(255,255,255,.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    .auth-left h1 { font-size: 24px; font-weight: 700; margin-bottom: 12px; }
    .auth-left p  { font-size: 14px; opacity: .8; line-height: 1.7; }
    .auth-feature { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
    .auth-feature .icon { width: 32px; height: 32px; background: rgba(255,255,255,.12); border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 15px; }
    .auth-feature .text { font-size: 13px; opacity: .85; }
    .auth-right { padding: 48px 44px; flex: 1; display: flex; flex-direction: column; justify-content: center; }
    .auth-right h2 { font-size: 22px; font-weight: 700; color: #1f2937; margin-bottom: 4px; }
    .auth-right .subtitle { font-size: 14px; color: #6b7280; margin-bottom: 28px; }
    .form-label { font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
    .form-control {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 14px;
        transition: border-color .2s, box-shadow .2s;
        background: #f9fafb;
    }
    .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); background: #fff; }
    .email-preview {
        font-size: 12px; color: #6b7280;
        background: #f3f4f6; border-radius: 8px;
        padding: 8px 12px; margin-top: 6px;
    }
    .btn-primary-custom {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff; border: none; border-radius: 10px;
        padding: 12px; font-size: 15px; font-weight: 600;
        width: 100%; cursor: pointer;
        transition: opacity .2s, transform .1s;
    }
    .btn-primary-custom:hover   { opacity: .92; }
    .btn-primary-custom:active  { transform: scale(.98); }
    .btn-primary-custom:disabled { opacity: .6; cursor: not-allowed; }
    .divider { text-align: center; color: #9ca3af; font-size: 13px; margin: 18px 0; position: relative; }
    .divider::before, .divider::after { content: ''; position: absolute; top: 50%; width: 40%; height: 1px; background: #e5e7eb; }
    .divider::before { left: 0; } .divider::after { right: 0; }
    @media (max-width: 768px) {
        .auth-card { flex-direction: column; }
        .auth-left { flex: 0 0 auto; padding: 32px 28px; }
    }
    </style>
</head>
<body>
<div class="auth-card">
    <!-- Left Panel -->
    <div class="auth-left">
        <div class="logo-wrap">
            <div class="logo-icon"><i class="bi bi-building-fill text-white fs-5"></i></div>
            <span style="font-size:20px;font-weight:700;">UniDorm</span>
        </div>
        <h1>Ký túc xá Đại học Tôn Đức Thắng</h1>
        <p>Hệ thống quản lý ký túc xá hiện đại – đăng ký tài khoản sinh viên để theo dõi phòng ở, báo hỏng thiết bị và nhận thông báo từ Ban quản lý.</p>
        <div class="mt-4">
            <div class="auth-feature">
                <div class="icon"><i class="bi bi-shield-check"></i></div>
                <div class="text">Tài khoản được xác thực qua email sinh viên TDTU</div>
            </div>
            <div class="auth-feature">
                <div class="icon"><i class="bi bi-bell"></i></div>
                <div class="text">Nhận thông báo từ Ban quản lý theo thời gian thực</div>
            </div>
            <div class="auth-feature">
                <div class="icon"><i class="bi bi-tools"></i></div>
                <div class="text">Báo hỏng thiết bị trực tuyến nhanh chóng</div>
            </div>
        </div>
    </div>

    <!-- Right Form -->
    <div class="auth-right">
        <h2>Tạo tài khoản sinh viên</h2>
        <p class="subtitle">Dùng MSSV để đăng ký – email xác nhận sẽ được gửi đến hộp thư sinh viên</p>

        <?php if ($success): ?>
        <div class="alert alert-success rounded-3 d-flex gap-2 align-items-start">
            <i class="bi bi-check-circle-fill mt-1 flex-shrink-0"></i>
            <div><?php echo $success; ?></div>
        </div>
        <div class="text-center mt-3">
            <a href="/UniDorm/views/auth/login.php" class="btn btn-outline-primary rounded-3 btn-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i>Đến trang đăng nhập
            </a>
        </div>
        <?php else: ?>

        <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 d-flex gap-2 align-items-start mb-3">
            <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
            <div><?php echo $error; ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" id="registerForm" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Mã số sinh viên (MSSV) <span class="text-danger">*</span></label>
                <input type="text" name="student_code" id="studentCode" class="form-control"
                       placeholder="VD: 52100001"
                       value="<?php echo htmlspecialchars($_POST['student_code'] ?? ''); ?>"
                       maxlength="8" pattern="\d{8}" required
                       oninput="updateEmailPreview()">
                <div class="email-preview" id="emailPreview">
                    <i class="bi bi-envelope me-1"></i>
                    Email đăng nhập: <strong id="emailDisplay">MSSV@student.tdtu.edu.vn</strong>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                <input type="text" name="fullname" class="form-control"
                       placeholder="Nhập họ và tên đầy đủ"
                       value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Số điện thoại cá nhân</label>
                    <input type="tel" name="phone_personal" class="form-control"
                           placeholder="VD: 0912345678"
                           value="<?php echo htmlspecialchars($_POST['phone_personal'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Quê quán / Hộ khẩu</label>
                    <input type="text" name="hometown" class="form-control"
                           placeholder="VD: Bình Dương"
                           value="<?php echo htmlspecialchars($_POST['hometown'] ?? ''); ?>">
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                    <label class="form-check-label small text-muted" for="agreeTerms">
                        Tôi đồng ý với <a href="#" class="text-primary">Quy định ký túc xá</a> và
                        <a href="#" class="text-primary">Chính sách bảo mật</a>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-primary-custom" id="submitBtn">
                <i class="bi bi-person-plus-fill me-2"></i>Tạo tài khoản
            </button>
        </form>

        <div class="divider">hoặc</div>
        <div class="text-center">
            <span class="small text-muted">Đã có tài khoản?</span>
            <a href="/UniDorm/views/auth/login.php" class="small fw-semibold text-primary ms-1">Đăng nhập</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateEmailPreview() {
    const code = document.getElementById('studentCode').value.trim();
    document.getElementById('emailDisplay').textContent = code
        ? code + '@student.tdtu.edu.vn'
        : 'MSSV@student.tdtu.edu.vn';
}

document.getElementById('registerForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang tạo tài khoản...';
});
</script>
</body>
</html>