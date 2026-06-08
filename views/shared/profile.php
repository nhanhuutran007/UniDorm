<?php
/**
 * UniDorm – Hồ sơ cá nhân (dùng chung admin + student)
 * path: views/shared/profile.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
$userId = (int)$_SESSION['user_id'];
$userRole = strtolower($_SESSION['role'] ?? 'student');

$pageTitle   = 'Hồ sơ cá nhân';
$breadcrumbs = [['label' => 'Hồ sơ cá nhân', 'url' => '#']];
ob_start();

$successMsg = $errorMsg = '';

// Lấy thông tin đầy đủ từ DB
$stmt = $conn->prepare("
    SELECT u.*, aa.last_password_change
    FROM users u
    LEFT JOIN auth_accounts aa ON u.user_id = aa.user_id
    WHERE u.user_id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

if (!$profile) {
    header('Location: ../auth/logout.php');
    exit;
}

// --- Xử lý cập nhật thông tin ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload_avatar' && isset($_FILES['avatar_file'])) {
        $uploadDir = __DIR__ . '/../../assets/img/user/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file = $_FILES['avatar_file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowedExts)) {
                $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                $dest = $uploadDir . $filename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $relPath = 'assets/img/user/' . $filename;
                    $updStmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                    $updStmt->bind_param('si', $relPath, $userId);
                    if ($updStmt->execute()) {
                        $successMsg = 'Cập nhật ảnh đại diện thành công!';
                        // Update current profile data
                        $stmt->execute();
                        $profile = $stmt->get_result()->fetch_assoc();
                        $_SESSION['profile_picture'] = $relPath; // Cho header
                    } else {
                        $errorMsg = 'Lỗi lưu vào CSDL: ' . $conn->error;
                    }
                } else {
                    $errorMsg = 'Lỗi di chuyển file tải lên.';
                }
            } else {
                $errorMsg = 'Chỉ hỗ trợ file ảnh: ' . implode(', ', $allowedExts);
            }
        } else {
            $errorMsg = 'Có lỗi xảy ra khi tải lên file.';
        }
    }

    if ($action === 'update_info') {
        $fullname     = trim($_POST['fullname'] ?? '');
        $phonePers    = trim($_POST['phone_personal'] ?? '');
        $phoneFamily  = trim($_POST['phone_family'] ?? '');
        $hometown     = trim($_POST['hometown'] ?? '');
        $dob          = $_POST['date_of_birth'] ?? null;
        $gender       = $_POST['gender'] ?? null;

        if (empty($fullname)) {
            $errorMsg = 'Họ và tên không được để trống.';
        } else {
            $updStmt = $conn->prepare("
                UPDATE users SET fullname = ?, phone_personal = ?, phone_family = ?,
                                 hometown = ?, date_of_birth = ?, gender = ?
                WHERE user_id = ?
            ");
            $updStmt->bind_param('ssssssi', $fullname, $phonePers, $phoneFamily, $hometown, $dob, $gender, $userId);
            if ($updStmt->execute()) {
                $successMsg = 'Cập nhật thông tin thành công!';
                // Refresh profile data
                $stmt->execute();
                $profile = $stmt->get_result()->fetch_assoc();
                $_SESSION['fullname'] = $fullname;
            } else {
                $errorMsg = 'Lỗi khi cập nhật: ' . $conn->error;
            }
        }
    }

    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        // Lấy mật khẩu hiện tại
        $authStmt = $conn->prepare("SELECT password FROM auth_accounts WHERE user_id = ? AND is_active = 1");
        $authStmt->bind_param('i', $userId);
        $authStmt->execute();
        $auth = $authStmt->get_result()->fetch_assoc();

        if (!$auth || !password_verify($currentPass, $auth['password'])) {
            $errorMsg = 'Mật khẩu hiện tại không chính xác.';
        } elseif (strlen($newPass) < 8) {
            $errorMsg = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
        } elseif (!preg_match('/[A-Z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
            $errorMsg = 'Mật khẩu mới phải có ít nhất 1 chữ hoa và 1 chữ số.';
        } elseif ($newPass !== $confirmPass) {
            $errorMsg = 'Mật khẩu xác nhận không khớp.';
        } else {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT);
            $pwStmt = $conn->prepare("UPDATE auth_accounts SET password = ?, last_password_change = NOW() WHERE user_id = ?");
            $pwStmt->bind_param('si', $hashed, $userId);
            if ($pwStmt->execute()) {
                $successMsg = 'Đổi mật khẩu thành công!';
            } else {
                $errorMsg = 'Lỗi khi đổi mật khẩu.';
            }
        }
    }
}

// Lấy thêm info phòng nếu là sinh viên
$roomInfo = null;
if ($userRole === 'student' && !empty($profile['bed_id'])) {
    $roomStmt = $conn->prepare("
        SELECT r.room_code, f.floor_number, b.bed_label, bld.name as building_name
        FROM beds b
        JOIN rooms r ON b.room_id = r.id
        JOIN floors f ON r.floor_id = f.id
        JOIN buildings bld ON f.building_id = bld.id
        WHERE b.id = ?
    ");
    $roomStmt->bind_param('i', $profile['bed_id']);
    $roomStmt->execute();
    $roomInfo = $roomStmt->get_result()->fetch_assoc();
}

$genderMap = ['male' => 'Nam', 'female' => 'Nữ', 'other' => 'Khác'];
?>

<?php if ($successMsg): ?>
<div class="alert alert-success alert-dismissible fade show mb-4 rounded-3" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($successMsg); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif ($errorMsg): ?>
<div class="alert alert-danger alert-dismissible fade show mb-4 rounded-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($errorMsg); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Avatar & Quick Info -->
    <div class="col-lg-4">
        <!-- Avatar card -->
        <div class="card border-0 shadow-sm text-center" style="border-radius:14px;">
            <div class="card-body p-4">
                <div class="position-relative d-inline-block mb-3">
                    <?php
                    $prSrc = !empty($profile['profile_picture']) ? BASE_URL . '/' . $profile['profile_picture'] : BASE_URL . '/assets/images/default.jpg';
                    ?>
                    <img id="avatarPreview" src="<?php echo $prSrc; ?>"
                         onerror="if (this.src != '<?php echo BASE_URL; ?>/assets/images/default.jpg') this.src='<?php echo BASE_URL; ?>/assets/images/default.jpg';"
                         alt="Avatar" class="rounded-circle border border-3 border-primary bg-white shadow-sm"
                         style="width:100px;height:100px;object-fit:cover;">
                         
                    <form method="POST" enctype="multipart/form-data" id="avatarForm" style="display:none;">
                        <input type="hidden" name="action" value="upload_avatar">
                        <input type="file" name="avatar_file" id="avatarInput" accept="image/*" onchange="document.body.style.cursor='wait'; document.getElementById('avatarForm').submit();">
                    </form>
                    
                    <label for="avatarInput" class="position-absolute bottom-0 end-0 bg-white border border-2 border-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm"
                           style="width:32px;height:32px;cursor:pointer;transition:transform 0.2s;" title="Đổi ảnh"
                           onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                        <i class="bi bi-camera-fill text-primary" style="font-size:14px;"></i>
                    </label>
                </div>
                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($profile['fullname']); ?></h5>
                <span class="badge bg-<?php echo $userRole === 'admin' ? 'danger' : 'primary'; ?> mb-2">
                    <?php echo $userRole === 'admin' ? 'Quản trị viên' : 'Sinh viên'; ?>
                </span>
                <?php if ($userRole === 'student' && $profile['student_code']): ?>
                <p class="text-muted small mb-0"><i class="bi bi-card-text me-1"></i><?php echo htmlspecialchars($profile['student_code']); ?></p>
                <p class="text-muted small mb-0"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($profile['email'] ?? $profile['student_code'].'@student.tdtu.edu.vn'); ?></p>
                <?php elseif ($profile['email']): ?>
                <p class="text-muted small mb-0"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($profile['email']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Thống kê nhanh -->
        <div class="card border-0 shadow-sm mt-3" style="border-radius:14px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 text-muted text-uppercase" style="font-size:11px;letter-spacing:.8px;">Thông tin tài khoản</h6>
                <table class="table table-borderless mb-0 small">
                    <tr>
                        <td class="text-muted ps-0 py-2">Ngày tạo</td>
                        <td class="fw-semibold py-2"><?php echo date('d/m/Y', strtotime($profile['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0 py-2">Đổi MK lần cuối</td>
                        <td class="fw-semibold py-2"><?php echo $profile['last_password_change'] ? date('d/m/Y', strtotime($profile['last_password_change'])) : 'Chưa đổi'; ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0 py-2">Trạng thái</td>
                        <td class="py-2">
                            <?php $stMap = ['active'=>['success','Hoạt động'],'pending'=>['warning','Chờ kích hoạt'],'inactive'=>['secondary','Không hoạt động'],'banned'=>['danger','Bị khoá']];
                            [$sc,$sl] = $stMap[$profile['status']] ?? ['secondary','?']; ?>
                            <span class="badge bg-<?php echo $sc; ?> bg-opacity-75"><?php echo $sl; ?></span>
                        </td>
                    </tr>
                    <?php if ($roomInfo): ?>
                    <tr>
                        <td class="text-muted ps-0 py-2">Phòng ở</td>
                        <td class="fw-semibold py-2">
                            <code class="bg-light px-2 py-1 rounded"><?php echo $roomInfo['room_code']; ?> / <?php echo $roomInfo['bed_label']; ?></code>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0 py-2">Lầu</td>
                        <td class="py-2">Lầu <?php echo $roomInfo['floor_number']; ?> – <?php echo htmlspecialchars($roomInfo['building_name']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Right col: forms -->
    <div class="col-lg-8">
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="info-tab" data-bs-toggle="tab" data-bs-target="#infoPane" type="button" role="tab">
                    <i class="bi bi-person me-1"></i>Thông tin cá nhân
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="pass-tab" data-bs-toggle="tab" data-bs-target="#passPane" type="button" role="tab">
                    <i class="bi bi-shield-lock me-1"></i>Đổi mật khẩu
                </button>
            </li>
        </ul>

        <div class="tab-content" id="profileTabsContent">
            <!-- Tab: Thông tin -->
            <div class="tab-pane fade show active" id="infoPane" role="tabpanel">
                <div class="card border-0 shadow-sm" style="border-radius:14px;">
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_info">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Họ và tên <span class="text-danger">*</span></label>
                                    <input type="text" name="fullname" class="form-control rounded-3"
                                           value="<?php echo htmlspecialchars($profile['fullname']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">MSSV / Tên đăng nhập</label>
                                    <input type="text" class="form-control rounded-3 bg-light"
                                           value="<?php echo htmlspecialchars($profile['student_code'] ?? $profile['username'] ?? '—'); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Ngày sinh</label>
                                    <input type="date" name="date_of_birth" class="form-control rounded-3"
                                           value="<?php echo $profile['date_of_birth'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Giới tính</label>
                                    <select name="gender" class="form-select rounded-3">
                                        <option value="">Chưa chọn</option>
                                        <option value="male"   <?php echo $profile['gender'] === 'male'   ? 'selected' : ''; ?>>Nam</option>
                                        <option value="female" <?php echo $profile['gender'] === 'female' ? 'selected' : ''; ?>>Nữ</option>
                                        <option value="other"  <?php echo $profile['gender'] === 'other'  ? 'selected' : ''; ?>>Khác</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">SĐT cá nhân</label>
                                    <input type="tel" name="phone_personal" class="form-control rounded-3"
                                           placeholder="0912 345 678"
                                           value="<?php echo htmlspecialchars($profile['phone_personal'] ?? ''); ?>">
                                </div>
                                <?php if ($userRole === 'student'): ?>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">SĐT gia đình</label>
                                    <input type="tel" name="phone_family" class="form-control rounded-3"
                                           placeholder="0912 345 678"
                                           value="<?php echo htmlspecialchars($profile['phone_family'] ?? ''); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold small">Hộ khẩu / Quê quán</label>
                                    <input type="text" name="hometown" class="form-control rounded-3"
                                           placeholder="VD: Bình Dương, TP.HCM..."
                                           value="<?php echo htmlspecialchars($profile['hometown'] ?? ''); ?>">
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary px-4 rounded-3">
                                    <i class="bi bi-check2 me-1"></i>Lưu thay đổi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab: Mật khẩu -->
            <div class="tab-pane fade" id="passPane" role="tabpanel">
                <div class="card border-0 shadow-sm" style="border-radius:14px;">
                    <div class="card-body p-4">
                        <form method="POST" id="changePassForm">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="current_password" id="curPass" class="form-control rounded-start-3" required>
                                    <button type="button" class="btn btn-outline-secondary rounded-end-3" onclick="togglePass('curPass','eyeCur')">
                                        <i class="bi bi-eye" id="eyeCur"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Mật khẩu mới <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="new_password" id="newPass" class="form-control rounded-start-3"
                                           required oninput="checkStrength(this.value)">
                                    <button type="button" class="btn btn-outline-secondary rounded-end-3" onclick="togglePass('newPass','eyeNew')">
                                        <i class="bi bi-eye" id="eyeNew"></i>
                                    </button>
                                </div>
                                <div class="progress mt-2" style="height:4px;border-radius:4px;">
                                    <div id="strengthBar" class="progress-bar bg-danger" style="width:0%;transition:width .3s, background .3s;"></div>
                                </div>
                                <small id="strengthText" class="text-muted">Phải có ít nhất 8 ký tự, 1 chữ hoa và 1 chữ số</small>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold small">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="confPass" class="form-control rounded-start-3" required>
                                    <button type="button" class="btn btn-outline-secondary rounded-end-3" onclick="togglePass('confPass','eyeConf')">
                                        <i class="bi bi-eye" id="eyeConf"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-warning px-4 rounded-3 text-dark fw-semibold">
                                    <i class="bi bi-lock-fill me-1"></i>Đổi mật khẩu
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePass(inputId, eyeId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(eyeId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
function checkStrength(val) {
    let score = 0;
    if (val.length >= 8)           score++;
    if (/[A-Z]/.test(val))         score++;
    if (/[0-9]/.test(val))         score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const bar = document.getElementById('strengthBar');
    const txt = document.getElementById('strengthText');
    const map = [[0,'0%','bg-danger','Quá ngắn'],[25,'25%','bg-danger','Yếu'],[50,'50%','bg-warning','Trung bình'],[75,'75%','bg-info','Khá mạnh'],[100,'100%','bg-success','Mạnh']];
    const [_,w,cls,label] = map[Math.min(score,4)];
    bar.className = 'progress-bar ' + cls;
    bar.style.width = w;
    txt.textContent = label;
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';