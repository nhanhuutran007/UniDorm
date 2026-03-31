<?php
/**
 * UniDorm – Admin: Cập nhật thông tin sinh viên (updateuser.php)
 * ?id=<user_id>
 */
$pageTitle   = 'Chỉnh sửa sinh viên';
$breadcrumbs = [
    ['label' => 'Quản lý sinh viên', 'url' => '/UniDorm/views/admin/students.php'],
    ['label' => 'Chỉnh sửa', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

$targetId = (int)($_GET['id'] ?? 0);
if (!$targetId) {
    header('Location: /UniDorm/views/admin/students.php');
    exit;
}

// Lấy thông tin user
$uStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$uStmt->bind_param('i', $targetId);
$uStmt->execute();
$target = $uStmt->get_result()->fetch_assoc();

if (!$target) {
    header('Location: /UniDorm/views/admin/students.php?error=not_found');
    exit;
}

$successMsg = $errorMsg = '';

// Rooms + beds
$roomsRaw = $conn->query("
    SELECT r.id, r.room_code, f.floor_number, bld.name as building_name
    FROM rooms r 
    JOIN floors f ON r.floor_id = f.id
    JOIN buildings bld ON f.building_id = bld.id
    WHERE r.status NOT IN ('maintenance','closed')
    ORDER BY bld.name ASC, f.floor_number ASC, r.room_code ASC
")->fetch_all(MYSQLI_ASSOC);

$roomsByFloor = [];
foreach ($roomsRaw as $r) {
    $label = $r['building_name'] . ' - Lầu ' . $r['floor_number'];
    $roomsByFloor[$label][] = $r;
}

$allBeds = $conn->query("
    SELECT b.id, b.bed_label, b.room_id, b.is_occupied,
           u.user_id as occupant_id
    FROM beds b
    LEFT JOIN users u ON u.bed_id = b.id AND u.status = 'active'
    ORDER BY b.room_id, b.bed_label
")->fetch_all(MYSQLI_ASSOC);

$bedsByRoom = [];
foreach ($allBeds as $b) {
    if (!$b['is_occupied'] || $b['occupant_id'] == $targetId) {
        $bedsByRoom[$b['room_id']][] = $b;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname    = trim($_POST['fullname']     ?? '');
    $gender      = $_POST['gender']            ?? null;
    $dob         = $_POST['date_of_birth']     ?: null;
    $phonePers   = trim($_POST['phone_personal']  ?? '');
    $phoneFamily = trim($_POST['phone_family']    ?? '');
    $hometown    = trim($_POST['hometown']        ?? '');
    $newRoom     = (int)($_POST['room_id']        ?? 0);
    $newBed      = (int)($_POST['bed_id']         ?? 0);
    $isLeader    = isset($_POST['is_room_leader']) ? 1 : 0;
    $newStatus   = $_POST['status'] ?? $target['status'];

    if (!$fullname) {
        $errorMsg = 'Họ và tên không được để trống.';
    } else {
        $conn->begin_transaction();
        try {
            // Nếu giường thay đổi
            $oldBed = (int)$target['bed_id'];
            if ($oldBed && $oldBed !== $newBed) {
                // Giải phóng giường cũ
                $conn->prepare("UPDATE beds SET is_occupied = 0 WHERE id = ?")->execute() || true;
                $free = $conn->prepare("UPDATE beds SET is_occupied = 0 WHERE id = ?");
                $free->bind_param('i', $oldBed); $free->execute();
                // Cập nhật phòng cũ về available
                $oldRoom = $conn->query("SELECT room_id FROM beds WHERE id = $oldBed")->fetch_assoc()['room_id'] ?? null;
                if ($oldRoom) {
                    $conn->prepare("UPDATE rooms SET status='available' WHERE id = ? AND status='full'")->execute() || true;
                    $ar = $conn->prepare("UPDATE rooms SET status='available' WHERE id = ? AND status='full'");
                    $ar->bind_param('i', $oldRoom); $ar->execute();
                }
            }
            if ($newBed && $newBed !== $oldBed) {
                // Chiếm giường mới
                $oc = $conn->prepare("UPDATE beds SET is_occupied = 1 WHERE id = ?");
                $oc->bind_param('i', $newBed); $oc->execute();
                // Kiểm tra phòng mới có đầy không
                $freeCheck = $conn->prepare("SELECT COUNT(*) as c FROM beds WHERE room_id = ? AND is_occupied = 0");
                $freeCheck->bind_param('i', $newRoom); $freeCheck->execute();
                if ($freeCheck->get_result()->fetch_assoc()['c'] == 0 && $newRoom) {
                    $fc = $conn->prepare("UPDATE rooms SET status='full' WHERE id = ?");
                    $fc->bind_param('i', $newRoom); $fc->execute();
                }
            }
            $bedIdVal = $newBed ?: null;
            // Update user
            $upd = $conn->prepare("
                UPDATE users SET fullname=?, gender=?, date_of_birth=?, phone_personal=?,
                                 phone_family=?, hometown=?, status=?, bed_id=?, is_room_leader=?
                WHERE user_id=?
            ");
            $upd->bind_param('sssssssiii',
                $fullname, $gender, $dob, $phonePers, $phoneFamily, $hometown,
                $newStatus, $bedIdVal, $isLeader, $targetId
            );
            $upd->execute();

            if (isset($_POST['reset_password'])) {
                $token     = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $rt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token=VALUES(token),expires_at=VALUES(expires_at),used=0");
                $rt->bind_param('iss', $targetId, $token, $expiresAt);
                $rt->execute();
                $resetUrl = "https://{$_SERVER['HTTP_HOST']}/UniDorm/views/auth/forgot_password.php?token=$token";
                $email    = $target['email'] ?? $target['student_code'].'@student.tdtu.edu.vn';
                @mail($email, '[UniDorm] Yêu cầu đặt lại mật khẩu',
                    "Xin chào {$target['fullname']},\n\nAdmin đã yêu cầu đặt lại mật khẩu cho tài khoản của bạn.\n\nLink: $resetUrl\n\n(Hiệu lực 1 giờ)",
                    "From: noreply@unidorm.tdtu.edu.vn\r\n"
                );
            }

            $conn->commit();
            // Reload
            $uStmt->execute();
            $target     = $uStmt->get_result()->fetch_assoc();
            $successMsg = "Cập nhật thông tin sinh viên <strong>" . htmlspecialchars($fullname) . "</strong> thành công!";
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = 'Lỗi hệ thống: ' . $e->getMessage();
        }
    }
}

// Giường hiện tại của SV
$curBedInfo = null;
if ($target['bed_id']) {
    $bedRow = $conn->query("SELECT b.id, b.bed_label, b.room_id, r.room_code, f.floor_number FROM beds b JOIN rooms r ON b.room_id=r.id JOIN floors f ON r.floor_id=f.id WHERE b.id={$target['bed_id']}")->fetch_assoc();
    $curBedInfo = $bedRow;
}
?>

<?php if ($successMsg): ?>
<div class="alert alert-success rounded-3 mb-4 d-flex gap-2 alert-dismissible fade show">
    <i class="bi bi-check-circle-fill mt-1 flex-shrink-0"></i>
    <div><?php echo $successMsg; ?></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif ($errorMsg): ?>
<div class="alert alert-danger rounded-3 mb-4 d-flex gap-2">
    <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
    <div><?php echo htmlspecialchars($errorMsg); ?></div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
        <div>
            <h6 class="fw-bold mb-0"><i class="bi bi-person-gear me-2 text-primary"></i>Cập nhật thông tin</h6>
            <p class="text-muted small mb-0">MSSV: <code><?php echo htmlspecialchars($target['student_code'] ?? '—'); ?></code>
            &nbsp;|&nbsp; Email: <code><?php echo htmlspecialchars($target['email'] ?? '—'); ?></code></p>
        </div>
        <div class="text-end">
            <?php $stMap = ['active'=>['success','Hoạt động'],'pending'=>['warning','Chờ kích hoạt'],'inactive'=>['danger','Bị khoá']]; ?>
            <span class="badge bg-<?php echo $stMap[$target['status']][0] ?? 'secondary'; ?>">
                <?php echo $stMap[$target['status']][1] ?? $target['status']; ?>
            </span>
        </div>
    </div>
    <div class="card-body p-4">
        <form method="POST" id="updateForm">
            <div class="row g-4">
                <!-- Left: Personal -->
                <div class="col-lg-6">
                    <h6 class="fw-semibold text-muted text-uppercase mb-3" style="font-size:11px;letter-spacing:.8px;">Thông tin cá nhân</h6>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" name="fullname" class="form-control rounded-3"
                               value="<?php echo htmlspecialchars($target['fullname']); ?>" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Ngày sinh</label>
                            <input type="date" name="date_of_birth" class="form-control rounded-3"
                                   value="<?php echo $target['date_of_birth'] ?? ''; ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Giới tính</label>
                            <select name="gender" class="form-select rounded-3">
                                <option value="">Chưa chọn</option>
                                <?php foreach (['male'=>'Nam','female'=>'Nữ','other'=>'Khác'] as $v=>$l): ?>
                                <option value="<?php echo $v; ?>" <?php echo $target['gender']===$v?'selected':''; ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">SĐT cá nhân</label>
                            <input type="tel" name="phone_personal" class="form-control rounded-3"
                                   value="<?php echo htmlspecialchars($target['phone_personal'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">SĐT gia đình</label>
                            <input type="tel" name="phone_family" class="form-control rounded-3"
                                   value="<?php echo htmlspecialchars($target['phone_family'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Quê quán</label>
                        <input type="text" name="hometown" class="form-control rounded-3"
                               value="<?php echo htmlspecialchars($target['hometown'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Right: Room + Account -->
                <div class="col-lg-6">
                    <h6 class="fw-semibold text-muted text-uppercase mb-3" style="font-size:11px;letter-spacing:.8px;">Phòng ở & Tài khoản</h6>

                    <?php if ($curBedInfo): ?>
                    <div class="alert alert-info py-2 px-3 rounded-3 mb-3 small">
                        <i class="bi bi-door-open me-1"></i>
                        Hiện đang ở: <strong><?php echo $curBedInfo['room_code']; ?></strong> – <?php echo $curBedInfo['bed_label']; ?>
                        (Lầu <?php echo $curBedInfo['floor_number']; ?>)
                    </div>
                    <?php endif; ?>

                    <div class="row g-3 mb-3">
                        <div class="col-7">
                            <label class="form-label fw-semibold small">Phòng</label>
                            <select name="room_id" id="roomSelect" class="form-select rounded-3" onchange="updateBedSelect()">
                                <option value="">-- Chưa gán --</option>
                                <?php foreach ($roomsByFloor as $groupLabel => $rooms): ?>
                                <optgroup label="<?php echo htmlspecialchars($groupLabel); ?>">
                                    <?php foreach ($rooms as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"
                                        <?php echo ($curBedInfo && $curBedInfo['room_id']==$r['id']) ? 'selected' : ''; ?>>
                                        <?php echo $r['room_code']; ?> (Lầu <?php echo $r['floor_number']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-semibold small">Giường</label>
                            <select name="bed_id" id="bedSelect" class="form-select rounded-3">
                                <option value="">-- Chọn giường --</option>
                                <?php if ($curBedInfo): ?>
                                <option value="<?php echo $curBedInfo['id']; ?>" selected><?php echo $curBedInfo['bed_label']; ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Trạng thái tài khoản</label>
                        <select name="status" class="form-select rounded-3">
                            <option value="active"   <?php echo $target['status']==='active'  ?'selected':''; ?>>Hoạt động</option>
                            <option value="pending"  <?php echo $target['status']==='pending' ?'selected':''; ?>>Chờ kích hoạt</option>
                            <option value="inactive" <?php echo $target['status']==='inactive'?'selected':''; ?>>Bị khoá</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_room_leader" id="isLeader"
                                   <?php echo $target['is_room_leader'] ? 'checked' : ''; ?>>
                            <label class="form-check-label small" for="isLeader">
                                <i class="bi bi-star-fill text-warning me-1"></i>Đặt làm trưởng phòng
                            </label>
                        </div>
                    </div>

                    <hr>
                    <div class="mb-0">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="reset_password" id="resetPw">
                            <label class="form-check-label small" for="resetPw">
                                <i class="bi bi-key-fill text-warning me-1"></i>Gửi email yêu cầu đặt lại mật khẩu
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-4">
            <div class="d-flex justify-content-between">
                <a href="/UniDorm/views/admin/students.php" class="btn btn-outline-secondary rounded-3">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại
                </a>
                <button type="submit" class="btn btn-primary px-4 rounded-3" id="submitBtn">
                    <i class="bi bi-check2 me-2"></i>Lưu thay đổi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const bedsByRoom = <?php echo json_encode($bedsByRoom); ?>;
const currentBedId = <?php echo (int)($target['bed_id'] ?? 0); ?>;

function updateBedSelect() {
    const roomId = document.getElementById('roomSelect').value;
    const bedSel = document.getElementById('bedSelect');
    bedSel.innerHTML = '<option value="">-- Chọn giường --</option>';
    if (roomId && bedsByRoom[roomId]) {
        bedsByRoom[roomId].forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = b.bed_label;
            if (parseInt(b.id) === currentBedId) opt.selected = true;
            bedSel.appendChild(opt);
        });
    }
}
document.addEventListener('DOMContentLoaded', updateBedSelect);

document.getElementById('updateForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang lưu...';
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
