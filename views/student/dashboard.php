<?php
/**
 * UniDorm – Student Dashboard
 * path: views/student/dashboard.php
 */
$pageTitle   = 'Trang chủ sinh viên';
$breadcrumbs = [['label' => 'Trang chủ', 'url' => '#']];

ob_start();

require_once __DIR__ . '/../../includes/db.php';
?>

<?php
// Lấy thông tin user
require_once __DIR__ . '/../../app/models/UserModel.php';
$userModel = new UserModel($conn);
$userId = $_SESSION['user_id'] ?? 0;
$userData = $userModel->getUserById($userId);

// Lấy thông tin phòng của sinh viên
require_once __DIR__ . '/../../app/models/RoomModel.php';
require_once __DIR__ . '/../../app/models/BedModel.php';

$roomModel = new RoomModel($conn);
$bedModel  = new BedModel($conn);

$myBed  = $userData['bed_id'] ? $bedModel->getBedById($userData['bed_id']) : null;
$myRoom = $myBed ? $roomModel->getRoomById($myBed['room_id']) : null;

// Lấy danh sách bạn cùng phòng
$roommates = [];
if ($myRoom) {
    $rmStmt = $conn->prepare("
        SELECT u.fullname, u.student_code, b.bed_label
        FROM users u
        JOIN beds b ON u.bed_id = b.id
        WHERE b.room_id = ? AND u.user_id != ? AND u.status IN ('active', 'pending')
        ORDER BY b.bed_label ASC
    ");
    $rmStmt->bind_param('ii', $myRoom['id'], $userId);
    $rmStmt->execute();
    $roommates = $rmStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Thông báo chưa đọc
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE (target_user_id = ? OR target_user_id IS NULL) AND is_read = 0");
$stmt->bind_param("i", $userId);
$stmt->execute();
$unreadNotif = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;

// Báo cáo thiết bị đã gửi
$stmt2 = $conn->prepare("SELECT COUNT(*) as cnt FROM device_reports WHERE reporter_id = ?");
$stmt2->bind_param("i", $userId);
$stmt2->execute();
$myReportsCount = $stmt2->get_result()->fetch_assoc()['cnt'] ?? 0;
?>

<!-- Welcome Banner -->
<div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); border-radius: 16px;">
    <div class="card-body p-4 d-flex align-items-center justify-content-between">
        <div class="text-white">
            <p class="mb-1 opacity-75 small">Xin chào 👋</p>
            <h4 class="fw-bold mb-1 text-white"><?php echo htmlspecialchars($userData['fullname']); ?></h4>
            <p class="mb-0 opacity-75 small">MSSV: <strong><?php echo htmlspecialchars($userData['student_code'] ?? '—'); ?></strong></p>
        </div>
        <div class="text-white text-end d-none d-md-block">
            <i class="bi bi-mortarboard-fill" style="font-size: 4rem; opacity: 0.3;"></i>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-4 mb-4">

    <!-- Phòng đang ở -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="icon-wrap bg-primary bg-opacity-10 text-primary rounded-3 p-2">
                        <i class="bi bi-door-open-fill fs-4"></i>
                    </div>
                </div>
                <h5 class="fw-bold text-dark mb-0">
                    <?php echo $myRoom ? htmlspecialchars($myRoom['room_code']) : 'Chưa xếp phòng'; ?>
                </h5>
                <small class="text-muted">Phòng đang ở</small>
            </div>
        </div>
    </div>

    <!-- Số giường -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="icon-wrap bg-success bg-opacity-10 text-success rounded-3 p-2">
                        <i class="bi bi-hospital-fill fs-4"></i>
                    </div>
                </div>
                <h5 class="fw-bold text-dark mb-0">
                    <?php echo $myBed ? htmlspecialchars($myBed['bed_label']) : '—'; ?>
                </h5>
                <small class="text-muted">Giường của tôi</small>
            </div>
        </div>
    </div>

    <!-- Thông báo chưa đọc -->
    <div class="col-sm-6 col-xl-3">
        <a href="<?php echo BASE_URL; ?>/notifications" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="icon-wrap bg-warning bg-opacity-10 text-warning rounded-3 p-2">
                            <i class="bi bi-bell-fill fs-4"></i>
                        </div>
                        <?php if ($unreadNotif > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?php echo $unreadNotif; ?></span>
                        <?php endif; ?>
                    </div>
                    <h5 class="fw-bold text-dark mb-0"><?php echo $unreadNotif; ?></h5>
                    <small class="text-muted">Thông báo chưa đọc</small>
                </div>
            </div>
        </a>
    </div>

    <!-- Báo cáo đã gửi -->
    <div class="col-sm-6 col-xl-3">
        <a href="<?php echo BASE_URL; ?>/report_device" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="icon-wrap bg-danger bg-opacity-10 text-danger rounded-3 p-2">
                            <i class="bi bi-tools fs-4"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold text-dark mb-0"><?php echo $myReportsCount; ?></h5>
                    <small class="text-muted">Báo cáo đã gửi</small>
                </div>
            </div>
        </a>
    </div>

</div>

<!-- Quick Actions + Room Info -->
<div class="row g-4">
    <!-- Thông tin cá nhân -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-person-lines-fill me-2 text-info"></i>Thông tin cá nhân</h6>
            </div>
            <div class="card-body px-4 pb-4">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" style="width:45%">Ngày sinh</td><td class="fw-semibold"><?php echo $userData['date_of_birth'] ? date('d/m/Y', strtotime($userData['date_of_birth'])) : '—'; ?></td></tr>
                    <tr><td class="text-muted">Giới tính</td><td class="fw-semibold"><?php $gMap = ['male'=>'Nam','female'=>'Nữ','other'=>'Khác']; echo $gMap[$userData['gender']] ?? '—'; ?></td></tr>
                    <tr><td class="text-muted">SĐT cá nhân</td><td class="fw-semibold"><?php echo htmlspecialchars($userData['phone_personal'] ?? '—'); ?></td></tr>
                    <tr><td class="text-muted">SĐT gia đình</td><td class="fw-semibold"><?php echo htmlspecialchars($userData['phone_family'] ?? '—'); ?></td></tr>
                    <tr><td class="text-muted">Quê quán</td><td class="fw-semibold"><?php echo htmlspecialchars($userData['hometown'] ?? '—'); ?></td></tr>
                    <tr><td class="text-muted">Email</td><td class="fw-semibold text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>"><?php echo htmlspecialchars($userData['email'] ?? '—'); ?></td></tr>
                </table>
                <a href="<?php echo BASE_URL; ?>/profile" class="btn btn-sm btn-outline-info mt-3 w-100">
                    <i class="bi bi-pencil-square me-1"></i>Cập nhật hồ sơ
                </a>
            </div>
        </div>
    </div>

    <!-- Thông tin phòng nhanh -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-door-open me-2 text-primary"></i>Thông tin phòng</h6>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if ($myRoom): ?>
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" style="width:40%">Phòng</td><td class="fw-semibold"><?php echo htmlspecialchars($myRoom['room_code']); ?></td></tr>
                    <tr><td class="text-muted">Giường</td><td class="fw-semibold"><?php echo htmlspecialchars($myBed['bed_label']); ?></td></tr>
                    <tr><td class="text-muted">Sức chứa</td><td><?php echo $myRoom['max_capacity']; ?> sinh viên</td></tr>
                    <tr><td class="text-muted">Trạng thái</td>
                        <td>
                            <span class="badge bg-<?php echo $myRoom['status'] === 'available' ? 'success' : ($myRoom['status'] === 'full' ? 'warning' : 'secondary'); ?> bg-opacity-75">
                                <?php $stMap = ['available'=>'Còn chỗ','full'=>'Đã đầy','maintenance'=>'Bảo trì']; echo $stMap[$myRoom['status']] ?? $myRoom['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ($userData['is_room_leader']): ?>
                    <tr><td class="text-muted">Vai trò</td><td><span class="badge bg-primary bg-opacity-75"><i class="bi bi-star-fill me-1"></i>Trưởng phòng</span></td></tr>
                    <?php endif; ?>
                </table>
                <a href="<?php echo BASE_URL; ?>/room_info" class="btn btn-sm btn-outline-primary mt-3">
                    <i class="bi bi-arrow-right-circle me-1"></i>Xem chi tiết phòng
                </a>
                <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-door-closed fs-2 d-block mb-2"></i>
                    <p class="mb-0 small">Bạn chưa được xếp phòng.<br>Liên hệ Ban quản lý để được hỗ trợ.</p>
                    <a href="<?php echo BASE_URL; ?>/chat" class="btn btn-sm btn-primary mt-3">Nhắn tin BQL</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Thao tác nhanh -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-lightning-charge me-2 text-warning"></i>Thao tác nhanh</h6>
            </div>
            <div class="card-body px-4 pb-4 d-flex flex-column gap-3">
                <a href="<?php echo BASE_URL; ?>/report_device" class="btn btn-outline-danger d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Báo hỏng thiết bị trong phòng</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/chat" class="btn btn-outline-primary d-flex align-items-center gap-2">
                    <i class="bi bi-chat-dots-fill"></i>
                    <span>Nhắn tin với Ban quản lý</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/notifications" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                    <i class="bi bi-bell-fill"></i>
                    <span>Xem thông báo từ BQL</span>
                    <?php if ($unreadNotif > 0): ?>
                    <span class="badge bg-danger ms-auto"><?php echo $unreadNotif; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo BASE_URL; ?>/profile" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                    <i class="bi bi-person-fill"></i>
                    <span>Cập nhật hồ sơ cá nhân</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($myRoom): ?>
<!-- Roommates -->
<div class="row mt-2 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-people-fill me-2 text-success"></i>Bạn cùng phòng</h6>
            </div>
            <div class="card-body px-4 pb-4 mt-3">
                <?php if (empty($roommates)): ?>
                <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>Phòng hiện chưa có bạn cùng phòng khác.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-muted small py-2 rounded-start">Giường</th>
                                <th class="text-muted small py-2">Họ tên</th>
                                <th class="text-muted small py-2 rounded-end">MSSV</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roommates as $rm): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border"><i class="bi bi-hospital me-1"></i><?php echo htmlspecialchars($rm['bed_label']); ?></span></td>
                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($rm['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($rm['student_code']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
