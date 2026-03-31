<?php
/**
 * UniDorm – Admin: Thêm sinh viên mới (newuser.php)
 * Admin tạo tài khoản sinh viên thủ công + gán phòng/giường
 */
$pageTitle   = 'Thêm sinh viên mới';
$breadcrumbs = [
    ['label' => 'Quản lý sinh viên', 'url' => '/UniDorm/views/admin/students.php'],
    ['label' => 'Thêm mới', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

$successMsg = $errorMsg = '';

// Lấy danh sách phòng + giường trống để chọn
$roomsForSelect = $conn->query("
    SELECT r.id, r.room_code, f.floor_number,
           (SELECT COUNT(*) FROM beds b WHERE b.room_id = r.id AND b.is_occupied = 0) as free_beds
    FROM rooms r
    JOIN floors f ON r.floor_id = f.id
    WHERE r.status != 'maintenance' AND r.status != 'closed'
    ORDER BY f.floor_number ASC, r.room_code ASC
")->fetch_all(MYSQLI_ASSOC);

// Lấy giường trống theo phòng (JSON cho JS)
$bedsStmt = $conn->query("
    SELECT b.id, b.bed_label, b.room_id
    FROM beds b
    WHERE b.is_occupied = 0
    ORDER BY b.bed_label ASC
");
$allFreeBeds = $bedsStmt->fetch_all(MYSQLI_ASSOC);
$bedsByRoom  = [];
foreach ($allFreeBeds as $bed) {
    $bedsByRoom[$bed['room_id']][] = $bed;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentCode  = strtoupper(trim($_POST['student_code'] ?? ''));
    $fullname     = trim($_POST['fullname'] ?? '');
    $gender       = $_POST['gender'] ?? null;
    $dob          = $_POST['date_of_birth'] ?: null;
    $phonePers    = trim($_POST['phone_personal'] ?? '');
    $phoneFamily  = trim($_POST['phone_family'] ?? '');
    $hometown     = trim($_POST['hometown'] ?? '');
    $selectedRoom = (int)($_POST['room_id'] ?? 0);
    $selectedBed  = (int)($_POST['bed_id']  ?? 0);
    $isLeader     = isset($_POST['is_room_leader']) ? 1 : 0;

    // Validate
    if (!$studentCode || !$fullname) {
        $errorMsg = 'Vui lòng nhập MSSV và họ tên.';
    } elseif (!preg_match('/^[A-Z0-9]{6,12}$/', $studentCode)) {
        $errorMsg = 'MSSV không hợp lệ (chỉ gồm chữ hoa/số, 6–12 ký tự).';
    } else {
        $email = $studentCode . '@student.tdtu.edu.vn';
        // Check trùng
        $chk = $conn->prepare("SELECT user_id FROM users WHERE student_code = ? OR email = ?");
        $chk->bind_param('ss', $studentCode, $email);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $errorMsg = 'MSSV hoặc email này đã tồn tại trong hệ thống.';
        } else {
            $conn->begin_transaction();
            try {
                // Insert user
                $ins = $conn->prepare("
                    INSERT INTO users (student_code, username, fullname, email, gender, date_of_birth,
                                       phone_personal, phone_family, hometown, role, status,
                                       bed_id, is_room_leader, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'student', 'pending', ?, ?, ?)
                ");
                $bedIdVal = $selectedBed ?: null;
                $ins->bind_param('sssssssssiii',
                    $studentCode, $studentCode, $fullname, $email, $gender, $dob,
                    $phonePers, $phoneFamily, $hometown, $bedIdVal, $isLeader, $userId
                );
                $ins->execute();
                $newUserId = $conn->insert_id;

                // Update bed if selected
                if ($selectedBed) {
                    $upBed = $conn->prepare("UPDATE beds SET is_occupied = 1 WHERE id = ?");
                    $upBed->bind_param('i', $selectedBed);
                    $upBed->execute();
                    // Auto-update room status if full
                    $freeCheck = $conn->prepare("SELECT COUNT(*) as c FROM beds WHERE room_id = ? AND is_occupied = 0");
                    $freeCheck->bind_param('i', $selectedRoom);
                    $freeCheck->execute();
                    $freeLeft = $freeCheck->get_result()->fetch_assoc()['c'];
                    if ($freeLeft == 0 && $selectedRoom) {
                        $conn->prepare("UPDATE rooms SET status='full' WHERE id = ?")->execute() || true;
                        $rs = $conn->prepare("UPDATE rooms SET status='full' WHERE id = ?");
                        $rs->bind_param('i', $selectedRoom); $rs->execute();
                    }
                }

                // Tạo auth_account (pending – chưa đặt pass)
                $aa = $conn->prepare("INSERT INTO auth_accounts (user_id, password, is_active, must_change_password) VALUES (?, NULL, 0, 1)");
                $aa->bind_param('i', $newUserId);
                $aa->execute();

                // Tạo verification token
                $token     = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $tkStmt    = $conn->prepare("INSERT INTO email_verification_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                $tkStmt->bind_param('iss', $newUserId, $token, $expiresAt);
                $tkStmt->execute();

                $conn->commit();

                // Gửi email
                $setUrl = "https://{$_SERVER['HTTP_HOST']}/UniDorm/views/auth/set_password.php?token=$token";
                @mail($email, '[UniDorm] Tạo mật khẩu cho tài khoản',
                    "Xin chào $fullname,\n\nTài khoản sinh viên tại UniDorm đã được tạo bởi Ban quản lý.\n"
                    . "MSSV: $studentCode\n\nHãy vào link sau để đặt mật khẩu:\n$setUrl\n\n(Hiệu lực 24 giờ)",
                    "From: noreply@unidorm.tdtu.edu.vn\r\nContent-Type: text/plain; charset=UTF-8\r\n"
                );

                $successMsg = "Tạo tài khoản thành công cho sinh viên <strong>$fullname</strong> ($studentCode)."
                    . " Email kích hoạt đã gửi đến <strong>$email</strong>."
                    . "<br><small>Dev: <a href='$setUrl'>Set Password Link</a></small>";

            } catch (Exception $e) {
                $conn->rollback();
                $errorMsg = 'Lỗi hệ thống: ' . $e->getMessage();
            }
        }
    }
}
?>

<?php if ($successMsg): ?>
<div class="alert alert-success rounded-3 mb-4 d-flex gap-2">
    <i class="bi bi-check-circle-fill mt-1 flex-shrink-0"></i>
    <div><?php echo $successMsg; ?></div>
</div>
<?php elseif ($errorMsg): ?>
<div class="alert alert-danger rounded-3 mb-4 d-flex gap-2">
    <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
    <div><?php echo htmlspecialchars($errorMsg); ?></div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
        <h6 class="fw-bold mb-0"><i class="bi bi-person-plus-fill me-2 text-primary"></i>Thông tin sinh viên mới</h6>
        <p class="text-muted small mb-0">Hệ thống sẽ gửi email xác nhận đến địa chỉ <strong>MSSV@student.tdtu.edu.vn</strong></p>
    </div>
    <div class="card-body p-4">
        <form method="POST" id="newUserForm">
            <div class="row g-4">
                <!-- Personal Info -->
                <div class="col-lg-6">
                    <h6 class="fw-semibold text-muted text-uppercase mb-3" style="font-size:11px;letter-spacing:.8px;">Thông tin cá nhân</h6>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">MSSV <span class="text-danger">*</span></label>
                        <input type="text" name="student_code" id="studentCode" class="form-control rounded-3"
                               placeholder="VD: 52100001" maxlength="12"
                               value="<?php echo htmlspecialchars($_POST['student_code'] ?? ''); ?>"
                               oninput="updateEmailPreview()" required>
                        <small class="text-muted">Email đăng nhập: <span id="emailPreview" class="text-primary fw-semibold">MSSV@student.tdtu.edu.vn</span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" name="fullname" class="form-control rounded-3"
                               placeholder="Nguyễn Văn A"
                               value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Ngày sinh</label>
                            <input type="date" name="date_of_birth" class="form-control rounded-3"
                                   value="<?php echo $_POST['date_of_birth'] ?? ''; ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Giới tính</label>
                            <select name="gender" class="form-select rounded-3">
                                <option value="">Chưa chọn</option>
                                <option value="male"   <?php echo ($_POST['gender']??'')==='male'  ?'selected':''; ?>>Nam</option>
                                <option value="female" <?php echo ($_POST['gender']??'')==='female'?'selected':''; ?>>Nữ</option>
                                <option value="other"  <?php echo ($_POST['gender']??'')==='other' ?'selected':''; ?>>Khác</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Contact & Room -->
                <div class="col-lg-6">
                    <h6 class="fw-semibold text-muted text-uppercase mb-3" style="font-size:11px;letter-spacing:.8px;">Liên hệ & Phòng ở</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">SĐT cá nhân</label>
                            <input type="tel" name="phone_personal" class="form-control rounded-3"
                                   placeholder="0912 345 678"
                                   value="<?php echo htmlspecialchars($_POST['phone_personal'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">SĐT gia đình</label>
                            <input type="tel" name="phone_family" class="form-control rounded-3"
                                   placeholder="0912 345 678"
                                   value="<?php echo htmlspecialchars($_POST['phone_family'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Quê quán / Hộ khẩu</label>
                        <input type="text" name="hometown" class="form-control rounded-3"
                               placeholder="VD: Bình Dương"
                               value="<?php echo htmlspecialchars($_POST['hometown'] ?? ''); ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-7">
                            <label class="form-label fw-semibold small">Phòng</label>
                            <select name="room_id" id="roomSelect" class="form-select rounded-3" onchange="updateBedSelect()">
                                <option value="">-- Chưa gán phòng --</option>
                                <?php foreach ($roomsForSelect as $r): ?>
                                <?php if ($r['free_beds'] > 0): ?>
                                <option value="<?php echo $r['id']; ?>" <?php echo ($_POST['room_id']??'')==$r['id']?'selected':''; ?>>
                                    <?php echo $r['room_code']; ?> (<?php echo $r['free_beds']; ?> chỗ trống)
                                </option>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-semibold small">Giường</label>
                            <select name="bed_id" id="bedSelect" class="form-select rounded-3">
                                <option value="">-- Chọn giường --</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_room_leader" id="isLeader"
                                   <?php echo isset($_POST['is_room_leader']) ? 'checked' : ''; ?>>
                            <label class="form-check-label small" for="isLeader">
                                <i class="bi bi-star-fill text-warning me-1"></i>Đặt làm trưởng phòng
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-4">
            <div class="d-flex justify-content-end gap-2">
                <a href="/UniDorm/views/admin/students.php" class="btn btn-outline-secondary rounded-3">Hủy</a>
                <button type="submit" class="btn btn-primary px-4 rounded-3" id="submitBtn">
                    <i class="bi bi-person-plus-fill me-2"></i>Tạo tài khoản
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const bedsByRoom = <?php echo json_encode($bedsByRoom); ?>;

function updateEmailPreview() {
    const code = document.getElementById('studentCode').value.trim().toUpperCase();
    document.getElementById('emailPreview').textContent = code ? code+'@student.tdtu.edu.vn' : 'MSSV@student.tdtu.edu.vn';
}

function updateBedSelect() {
    const roomId  = document.getElementById('roomSelect').value;
    const bedSel  = document.getElementById('bedSelect');
    bedSel.innerHTML = '<option value="">-- Chọn giường --</option>';
    if (roomId && bedsByRoom[roomId]) {
        bedsByRoom[roomId].forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = b.bed_label;
            bedSel.appendChild(opt);
        });
    }
}

document.getElementById('newUserForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang tạo...';
});
// Pre-select phòng hiện tại nếu POST
<?php if (!empty($_POST['room_id'])): ?> 
document.addEventListener('DOMContentLoaded', () => { updateBedSelect(); <?php if (!empty($_POST['bed_id'])): ?> document.getElementById('bedSelect').value = '<?php echo (int)$_POST['bed_id']; ?>'; <?php endif; ?> });
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
