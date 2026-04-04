<?php
/**
 * UniDorm – Auth: Quên mật khẩu (Forgot Password)
 * path: views/auth/forgot_password.php
 *
 * Flow:
 *  1. Nhập MSSV (student) hoặc email (admin)
 *  2. Hệ thống gửi email reset link → password_reset_tokens
 *  3. User vào link → đặt mật khẩu mới → token bị xóa
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
$step  = 'request'; // 'request' | 'reset'

// ──────────────────────────────────────────────────────────────────────
// STEP 2: Process reset (từ link trong email)
// ──────────────────────────────────────────────────────────────────────
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

// ──────────────────────────────────────────────────────────────────────
// STEP 1: Request reset link
// ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$token) {
    $identifier = strtoupper(trim($_POST['identifier'] ?? ''));

    if (empty($identifier)) {
        $error = 'Vui lòng nhập MSSV hoặc email của bạn.';
    } else {
        // Tìm user bằng MSSV (student) hoặc email (admin)
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
            // Không tiết lộ thông tin – luôn báo success để tránh user enumeration
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
    <link rel="stylesheet" href="/UniDorm/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f0f4ff, #e8f0fe); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .card-wrap { background: #fff; border-radius: 20px; box-shadow: 0 20px 60px rgba(37,99,235,.12); padding: 48px 44px; width: 100%; max-width: 460px; }
    .form-control { border: 1px solid #e5e7eb; border-radius: 10px; padding: 11px 14px; font-size: 14px; background: #f9fafb; transition: border-color .2s, box-shadow .2s; }
    .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); background: #fff; }
    .btn-submit { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border: none; border-radius: 10px; padding: 12px; font-size: 15px; font-weight: 600; width: 100%; cursor: pointer; transition: opacity .2s; }
    .btn-submit:hover { opacity: .92; }
    .strength-bar { height: 4px; border-radius: 4px; width: 0%; transition: width .3s, background .3s; margin-top: 6px; }
    </style>
</head>
<body>
<div class="card-wrap">
    <!-- Back link -->
    <a href="/UniDorm/views/auth/login.php" class="text-muted text-decoration-none small d-flex align-items-center gap-1 mb-4">
        <i class="bi bi-arrow-left"></i> Quay về đăng nhập
    </a>

    <?php if ($success === 'reset_done'): ?>
    <!-- Success Reset -->
    <div class="text-center">
        <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle mb-3" style="width:64px;height:64px;">
            <i class="bi bi-check2-circle text-success fs-2"></i>
        </div>
        <h4 class="fw-bold">Mật khẩu đã được đặt lại!</h4>
        <p class="text-muted small">Bạn có thể đăng nhập với mật khẩu mới.</p>
        <a href="/UniDorm/views/auth/login.php" class="btn-submit d-block text-center text-decoration-none mt-4"
           style="padding:12px;font-size:15px;font-weight:600;border-radius:10px;">
            <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
        </a>
    </div>

    <?php elseif ($success === 'email_sent'): ?>
    <!-- Success Request -->
    <div class="text-center">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle mb-3" style="width:64px;height:64px;">
            <i class="bi bi-envelope-check-fill text-primary fs-2"></i>
        </div>
        <h4 class="fw-bold">Email đã được gửi!</h4>
        <p class="text-muted small mb-1">Nếu tài khoản tồn tại, bạn sẽ nhận được email đặt lại mật khẩu trong vài phút.</p>
        <p class="text-muted small">Kiểm tra cả thư mục <strong>Spam</strong> nếu không thấy.</p>
        <a href="/UniDorm/views/auth/login.php" class="btn btn-outline-primary rounded-3 mt-3 btn-sm">
            Quay về đăng nhập
        </a>
    </div>

    <?php elseif ($step === 'invalid'): ?>
    <!-- Invalid token -->
    <div class="text-center mb-4">
        <i class="bi bi-x-octagon-fill text-danger fs-1 d-block mb-2"></i>
        <h5 class="fw-bold">Liên kết không hợp lệ</h5>
        <p class="text-muted small"><?php echo htmlspecialchars($error); ?></p>
        <a href="/UniDorm/views/auth/forgot_password.php" class="btn btn-outline-primary rounded-3 btn-sm">
            Yêu cầu lại
        </a>
    </div>

    <?php elseif ($step === 'reset'): ?>
    <!-- Step 2: Reset form -->
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-3 mb-3" style="width:56px;height:56px;">
            <i class="bi bi-lock-fill text-primary fs-3"></i>
        </div>
        <h4 class="fw-bold mb-1">Đặt mật khẩu mới</h4>
        <p class="text-muted small mb-0">Xin chào <strong><?php echo htmlspecialchars($tokenUser['fullname']); ?></strong></p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger rounded-3 mb-3 small"><i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <div class="mb-3">
            <label class="form-label fw-semibold small">Mật khẩu mới <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="password" name="new_password" id="newPass" class="form-control" required oninput="checkStrength(this.value)">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('newPass','eye1')"><i class="bi bi-eye" id="eye1"></i></button>
            </div>
            <div class="strength-bar" id="strBar"></div>
            <small id="strTxt" class="text-muted">Ít nhất 8 ký tự, 1 chữ hoa, 1 chữ số</small>
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold small">Xác nhận mật khẩu <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="password" name="confirm_password" id="confPass" class="form-control" required>
                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('confPass','eye2')"><i class="bi bi-eye" id="eye2"></i></button>
            </div>
        </div>
        <button type="submit" class="btn-submit" id="submitBtn">
            <i class="bi bi-shield-check me-2"></i>Đặt lại mật khẩu
        </button>
    </form>

    <?php else: ?>
    <!-- Step 1: Request form -->
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 rounded-3 mb-3" style="width:56px;height:56px;">
            <i class="bi bi-key-fill text-warning fs-3"></i>
        </div>
        <h4 class="fw-bold mb-1">Quên mật khẩu?</h4>
        <p class="text-muted small mb-0">Nhập MSSV (sinh viên) hoặc email (quản trị viên) để nhận liên kết đặt lại mật khẩu</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger rounded-3 mb-3 small"><i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-4">
            <label class="form-label fw-semibold small">MSSV hoặc Email <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                <input type="text" name="identifier" class="form-control border-start-0"
                       placeholder="VD: 52100001 hoặc admin@tdtu.edu.vn"
                       value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>" required>
            </div>
        </div>
        <button type="submit" class="btn-submit" id="submitBtn">
            <i class="bi bi-send me-2"></i>Gửi liên kết đặt lại
        </button>
    </form>
    <?php endif; ?>
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
    if (val.length >= 8)           s++;
    if (/[A-Z]/.test(val))         s++;
    if (/[0-9]/.test(val))         s++;
    if (/[^A-Za-z0-9]/.test(val)) s++;
    const map = [[0,'0%','#ef4444','Quá ngắn'],[25,'25%','#ef4444','Yếu'],[50,'50%','#f59e0b','Trung bình'],[75,'75%','#3b82f6','Khá mạnh'],[100,'100%','#22c55e','Mạnh']];
    const [_, w, c, l] = map[Math.min(s, 4)];
    document.getElementById('strBar').style.cssText = `width:${w};background:${c}`;
    document.getElementById('strTxt').textContent = l;
}
document.querySelectorAll('form').forEach(f => f.addEventListener('submit', () => {
    const btn = document.getElementById('submitBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...'; }
}));
</script>
</body>
</html>