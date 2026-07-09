<?php
/**
 * UniDorm – Auth: Set Password (kích hoạt tài khoản lần đầu)
 * path: views/auth/set_password.php
 *
 * Sinh viên vào link từ email: ?token=<token>
 * Đặt mật khẩu → hash & lưu → status='active' → redirect login
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /UniDorm/');
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = $success = '';

// Validate token
$tokenUser = null;
if ($token) {
    $stmt = $conn->prepare("
        SELECT vt.user_id, vt.expires_at, u.fullname, u.email, u.student_code, u.status
        FROM email_verification_tokens vt
        JOIN users u ON vt.user_id = u.user_id
        WHERE vt.token = ? AND vt.expires_at > NOW()
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $tokenUser = $stmt->get_result()->fetch_assoc();
}

if (!$token || !$tokenUser) {
    $error = 'Liên kết không hợp lệ hoặc đã hết hạn. Vui lòng liên hệ BQL Ký túc xá để được cấp lại.';
}

// Xử lý POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenUser) {
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

        // Upsert auth_accounts
        $conn->begin_transaction();
        try {
            // Update/insert password vào bảng auth_accounts (hoặc users tùy schema)
            $authStmt = $conn->prepare("
                INSERT INTO auth_accounts (user_id, password, is_active, last_password_change)
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE password = VALUES(password), is_active = 1, last_password_change = NOW()
            ");
            $authStmt->bind_param('is', $tokenUser['user_id'], $hashed);
            $authStmt->execute();

            // Kích hoạt tài khoản
            $actStmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
            $actStmt->bind_param('i', $tokenUser['user_id']);
            $actStmt->execute();

            // Xóa token đã dùng
            $delStmt = $conn->prepare("DELETE FROM email_verification_tokens WHERE token = ?");
            $delStmt->bind_param('s', $token);
            $delStmt->execute();

            $conn->commit();
            $success = true;
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
    <title>Đặt mật khẩu – UniDorm</title>
    <link rel="stylesheet" href="/UniDorm/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f0f4ff, #e8f0fe); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .card-wrap { background: #fff; border-radius: 20px; box-shadow: 0 20px 60px rgba(37,99,235,.12); padding: 48px 44px; width: 100%; max-width: 460px; }
    .form-control { border: 1px solid #e5e7eb; border-radius: 10px; padding: 11px 14px; font-size: 14px; background: #f9fafb; transition: border-color .2s, box-shadow .2s; }
    .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); background: #fff; }
    .btn-set { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; border: none; border-radius: 10px; padding: 12px; font-size: 15px; font-weight: 600; width: 100%; cursor: pointer; transition: opacity .2s; }
    .btn-set:hover { opacity: .92; }
    .strength-bar { height: 4px; border-radius: 4px; margin-top: 6px; transition: width .3s, background .3s; }
    </style>
</head>
<body>
<div class="card-wrap">
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-3 mb-3" style="width:56px;height:56px;">
            <i class="bi bi-shield-lock-fill text-primary fs-3"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Đặt mật khẩu</h4>
        <?php if ($tokenUser && !$success): ?>
        <p class="text-muted small mb-0">Xin chào <strong><?php echo htmlspecialchars($tokenUser['fullname']); ?></strong>! Hãy đặt mật khẩu để kích hoạt tài khoản.</p>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
    <div class="text-center py-2">
        <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle mb-3" style="width:64px;height:64px;">
            <i class="bi bi-check2-circle text-success fs-2"></i>
        </div>
        <h5 class="fw-bold text-dark">Tài khoản đã kích hoạt!</h5>
        <p class="text-muted small">Mật khẩu đã được đặt thành công. Bạn có thể đăng nhập ngay bây giờ.</p>
        <a href="/UniDorm/views/auth/login.php" class="btn-set d-block text-center text-decoration-none mt-4"
           style="padding:12px;font-size:15px;font-weight:600;border-radius:10px;">
            <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
        </a>
    </div>

    <?php elseif ($error && !$tokenUser): ?>
    <div class="alert alert-danger rounded-3 d-flex gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
        <div><?php echo $error; ?></div>
    </div>
    <div class="text-center mt-3">
        <a href="/UniDorm/views/auth/login.php" class="btn btn-outline-primary rounded-3 btn-sm">Quay lại trang đăng nhập</a>
    </div>

    <?php else: ?>
    <?php if ($error): ?>
    <div class="alert alert-danger rounded-3 d-flex gap-2 mb-3">
        <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
        <div><?php echo htmlspecialchars($error); ?></div>
    </div>
    <?php endif; ?>

    <form method="POST" id="setPassForm">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <div class="mb-3">
            <label class="form-label fw-semibold small">Mật khẩu mới <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control"
                       placeholder="Tối thiểu 8 ký tự" required oninput="checkStrength(this.value)">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('password')">
                    <i class="bi bi-eye" id="eyePass"></i>
                </button>
            </div>
            <div class="strength-bar bg-danger mt-1" id="strengthBar" style="width:0%;"></div>
            <small class="text-muted" id="strengthText">Mật khẩu phải có ít nhất 8 ký tự, 1 chữ hoa và 1 chữ số</small>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold small">Xác nhận mật khẩu <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="password" name="password2" id="password2" class="form-control"
                       placeholder="Nhập lại mật khẩu" required>
                <button type="button" class="btn btn-outline-secondary" onclick="togglePass('password2')">
                    <i class="bi bi-eye" id="eyePass2"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-set" id="submitBtn">
            <i class="bi bi-lock-fill me-2"></i>Kích hoạt tài khoản
        </button>
    </form>
    <?php endif; ?>

    <div class="text-center mt-4">
        <a href="/UniDorm/views/auth/login.php" class="small text-muted text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>Quay về đăng nhập
        </a>
    </div>
</div>

<script>
function togglePass(id) {
    const input = document.getElementById(id);
    const eyeId = id === 'password' ? 'eyePass' : 'eyePass2';
    const eye   = document.getElementById(eyeId);
    if (input.type === 'password') {
        input.type = 'text';
        eye.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        eye.className = 'bi bi-eye';
    }
}

function checkStrength(val) {
    const bar  = document.getElementById('strengthBar');
    const txt  = document.getElementById('strengthText');
    let score  = 0;
    if (val.length >= 8)             score++;
    if (/[A-Z]/.test(val))           score++;
    if (/[0-9]/.test(val))           score++;
    if (/[^A-Za-z0-9]/.test(val))   score++;

    const map = [
        [0,   '0%',   'bg-danger',   'Quá ngắn'],
        [25,  '25%',  'bg-danger',   'Yếu – thêm chữ hoa và số'],
        [50,  '50%',  'bg-warning',  'Trung bình'],
        [75,  '75%',  'bg-info',     'Khá mạnh'],
        [100, '100%', 'bg-success',  'Mạnh'],
    ];
    const [_, w, cls, label] = map[Math.min(score, 4)];
    bar.style.width = w;
    bar.className   = 'strength-bar ' + cls + ' mt-1';
    txt.textContent = label;
}

document.getElementById('setPassForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang kích hoạt...';
});
</script>
</body>
</html>
