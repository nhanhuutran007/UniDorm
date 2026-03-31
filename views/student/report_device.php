<?php
/**
 * UniDorm – Student: Báo hỏng thiết bị
 * path: views/student/report_device.php
 */
$pageTitle   = 'Báo hỏng thiết bị';
$breadcrumbs = [
    ['label' => 'Trang chủ',        'url' => '/UniDorm/views/student/dashboard.php'],
    ['label' => 'Báo hỏng thiết bị','url' => '#']
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

require_once __DIR__ . '/../../app/models/RoomModel.php';
require_once __DIR__ . '/../../app/models/BedModel.php';

$bedModel   = new BedModel($conn);
$roomModel  = new RoomModel($conn);
$myBed      = $userData['bed_id'] ? $bedModel->getBedById($userData['bed_id']) : null;
$myRoom     = $myBed ? $roomModel->getRoomById($myBed['room_id']) : null;

// Lấy danh sách thiết bị trong phòng
$devices = [];
if ($myRoom) {
    $stmt = $conn->prepare("SELECT * FROM devices WHERE room_id = ? ORDER BY device_name ASC");
    $stmt->bind_param("i", $myRoom['id']);
    $stmt->execute();
    $devices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Lấy lịch sử báo cáo của sinh viên
$stmt2 = $conn->prepare("
    SELECT dr.*, d.device_name, r.room_code
    FROM device_reports dr
    LEFT JOIN devices d ON dr.device_id = d.id
    JOIN rooms r ON dr.room_id = r.id
    WHERE dr.reporter_id = ?
    ORDER BY dr.created_at DESC
    LIMIT 20
");
$stmt2->bind_param("i", $userId);
$stmt2->execute();
$myReports = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// Xử lý POST (gửi báo cáo)
$successMsg = $errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deviceId   = !empty($_POST['device_id']) ? (int)$_POST['device_id'] : null;
    $title      = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!$myRoom) {
        $errorMsg = 'Bạn chưa được xếp phòng. Không thể gửi báo cáo.';
    } elseif (empty($title)) {
        $errorMsg = 'Vui lòng nhập tiêu đề mô tả sự cố.';
    } else {
        $stmt3 = $conn->prepare("INSERT INTO device_reports (device_id, room_id, reporter_id, title, description) VALUES (?, ?, ?, ?, ?)");
        $stmt3->bind_param("iiiss", $deviceId, $myRoom['id'], $userId, $title, $description);
        if ($stmt3->execute()) {
            $successMsg = 'Báo cáo của bạn đã được gửi thành công! Ban quản lý sẽ xử lý sớm nhất.';
        } else {
            $errorMsg = 'Có lỗi xảy ra. Vui lòng thử lại.';
        }
    }
}

$statusMap  = ['pending' => ['Đang chờ xử lý', 'warning'], 'in_progress' => ['Đang xử lý', 'info'], 'resolved' => ['Đã xử lý', 'success'], 'rejected' => ['Đã từ chối', 'danger']];
?>

<?php if ($successMsg): ?>
<div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($successMsg); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif ($errorMsg): ?>
<div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($errorMsg); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form báo cáo -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Gửi báo cáo hỏng hóc</h6>
                <p class="small text-muted mt-1 mb-0">
                    <?php if ($myRoom): ?>
                    Phòng: <strong class="text-primary"><?php echo htmlspecialchars($myRoom['room_code']); ?></strong>
                    <?php else: ?>
                    <span class="text-warning">Bạn chưa được xếp phòng</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="card-body p-4">
                <?php if (!$myRoom): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-door-closed fs-2 d-block mb-2"></i>
                    <p>Bạn chưa được xếp phòng. Liên hệ BQL để được hỗ trợ.</p>
                    <a href="/UniDorm/views/shared/chat.php" class="btn btn-sm btn-primary">Nhắn tin BQL</a>
                </div>
                <?php else: ?>
                <form method="POST" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Thiết bị liên quan <span class="text-muted fw-normal">(tuỳ chọn)</span></label>
                        <select name="device_id" class="form-select">
                            <option value="">-- Chọn thiết bị (nếu có) --</option>
                            <?php foreach ($devices as $dev): ?>
                            <option value="<?php echo $dev['id']; ?>" <?php echo $dev['status'] === 'broken' ? 'style="color:red;"' : ''; ?>>
                                <?php echo htmlspecialchars($dev['device_name']); ?>
                                <?php if ($dev['status'] === 'broken'): ?> (Đã báo hỏng)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="">-- Thiết bị khác (nhập mô tả bên dưới) --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tiêu đề sự cố <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control"
                               placeholder="VD: Bóng đèn toilet bị cháy, máy lạnh không chạy..."
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Mô tả chi tiết</label>
                        <textarea name="description" class="form-control" rows="4"
                                  placeholder="Mô tả thêm về tình trạng hỏng hóc, thời gian xảy ra..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-send-fill"></i> Gửi báo cáo
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Lịch sử báo cáo -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-header bg-white border-0 pt-4 pb-0 px-4 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Lịch sử báo cáo của bạn</h6>
                <span class="badge bg-secondary bg-opacity-75"><?php echo count($myReports); ?> báo cáo</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($myReports)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                    <p class="mb-0 small">Bạn chưa gửi báo cáo nào.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 py-3 small">Tiêu đề</th>
                                <th class="py-3 small text-center">Thiết bị</th>
                                <th class="py-3 small text-center">Trạng thái</th>
                                <th class="py-3 small text-muted">Ngày gửi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myReports as $rpt): ?>
                            <?php [$stLabel, $stColor] = $statusMap[$rpt['status']] ?? ['Không rõ', 'secondary']; ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <p class="mb-0 fw-semibold small text-dark"><?php echo htmlspecialchars($rpt['title']); ?></p>
                                    <?php if ($rpt['description']): ?>
                                    <p class="mb-0 text-muted" style="font-size:11px;"><?php echo htmlspecialchars(mb_strimwidth($rpt['description'], 0, 60, '...')); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 text-center small"><?php echo htmlspecialchars($rpt['device_name'] ?? 'Khác'); ?></td>
                                <td class="py-3 text-center">
                                    <span class="badge bg-<?php echo $stColor; ?> bg-opacity-75"><?php echo $stLabel; ?></span>
                                </td>
                                <td class="py-3 text-muted" style="font-size:11px;">
                                    <?php echo date('d/m/Y H:i', strtotime($rpt['created_at'])); ?>
                                </td>
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

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
