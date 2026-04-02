<?php
/**
 * UniDorm – Student: Thông tin phòng của tôi
 * path: views/student/room_info.php
 */
$pageTitle   = 'Thông tin phòng';
$breadcrumbs = [
    ['label' => 'Trang chủ',    'url' => BASE_URL . '/dashboard'],
    ['label' => 'Thông tin phòng', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

require_once __DIR__ . '/../../app/models/RoomModel.php';
require_once __DIR__ . '/../../app/models/BedModel.php';

$bedModel  = new BedModel($conn);
$roomModel = new RoomModel($conn);

$myBed  = $userData['bed_id'] ? $bedModel->getBedById($userData['bed_id']) : null;
$myRoom = $myBed ? $roomModel->getRoomById($myBed['room_id']) : null;

// Danh sách bạn cùng phòng
$roommates = [];
$devices   = [];
if ($myRoom) {
    $rmStmt = $conn->prepare("
        SELECT u.fullname, u.student_code, u.phone_personal, u.hometown, u.is_room_leader,
               b.bed_label
        FROM users u
        JOIN beds b ON u.bed_id = b.id
        WHERE b.room_id = ? AND u.user_id != ? AND u.status = 'active'
        ORDER BY b.bed_label ASC
    ");
    $rmStmt->bind_param('ii', $myRoom['id'], $userId);
    $rmStmt->execute();
    $roommates = $rmStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Thiết bị trong phòng
    $devStmt = $conn->prepare("SELECT * FROM devices WHERE room_id = ? ORDER BY device_name ASC");
    $devStmt->bind_param('i', $myRoom['id']);
    $devStmt->execute();
    $devices = $devStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<?php if (!$myRoom): ?>
<!-- Chưa được xếp phòng -->
<div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-body text-center py-5">
        <i class="bi bi-door-closed fs-1 text-muted d-block mb-3"></i>
        <h5 class="fw-bold text-dark">Bạn chưa được xếp phòng</h5>
        <p class="text-muted mb-4">Vui lòng liên hệ Ban quản lý ký túc xá để được xếp phòng.</p>
        <a href="<?php echo BASE_URL; ?>/chat" class="btn btn-primary">
            <i class="bi bi-chat-dots-fill me-2"></i>Nhắn tin với BQL
        </a>
    </div>
</div>
<?php else: ?>

<div class="row g-4">
    <!-- Thông tin phòng -->
    <div class="col-lg-4">
        <!-- Card phòng -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius:14px; overflow:hidden;">
            <div class="card-header border-0 p-4 text-white" style="background:linear-gradient(135deg,#1e3a5f,#2563eb);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-20 rounded-3 p-3">
                        <i class="bi bi-door-open-fill fs-3 text-white"></i>
                    </div>
                    <div>
                        <h3 class="fw-black mb-0 text-white"><?php echo htmlspecialchars($myRoom['room_code']); ?></h3>
                        <small class="opacity-75"><?php echo htmlspecialchars($myRoom['building_name']); ?> · Lầu <?php echo $myRoom['floor_number']; ?></small>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-borderless mb-0">
                    <tr class="border-bottom">
                        <td class="ps-4 py-3 text-muted small">Giường của tôi</td>
                        <td class="py-3 fw-bold text-primary"><?php echo htmlspecialchars($myBed['bed_label']); ?></td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="ps-4 py-3 text-muted small">Sức chứa</td>
                        <td class="py-3 small"><?php echo $myRoom['max_capacity']; ?> sinh viên</td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="ps-4 py-3 text-muted small">Hiện có</td>
                        <td class="py-3 small"><?php echo count($roommates) + 1; ?> sinh viên</td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="ps-4 py-3 text-muted small">Trạng thái</td>
                        <td class="py-3">
                            <?php $stMap = ['available'=>['success','Còn chỗ'],'full'=>['warning','Đã đầy'],'maintenance'=>['danger','Bảo trì']]; [$sc,$sl] = $stMap[$myRoom['status']] ?? ['secondary','?']; ?>
                            <span class="badge bg-<?php echo $sc; ?> bg-opacity-75"><?php echo $sl; ?></span>
                        </td>
                    </tr>
                    <?php if ($userData['is_room_leader']): ?>
                    <tr>
                        <td class="ps-4 py-3 text-muted small">Vai trò</td>
                        <td class="py-3"><span class="badge bg-primary"><i class="bi bi-star-fill me-1"></i>Trưởng phòng</span></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Thiết bị trong phòng -->
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                <h6 class="fw-bold mb-0"><i class="bi bi-plug-fill text-warning me-2"></i>Thiết bị trong phòng</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($devices)): ?>
                <p class="text-muted small text-center py-3">Chưa có thông tin thiết bị.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($devices as $dev):
                        $dMap = ['good'=>['success','Tốt'],'broken'=>['danger','Hỏng'],'maintenance'=>['warning','Bảo trì']];
                        [$dc,$dl] = $dMap[$dev['status']] ?? ['secondary','?'];
                    ?>
                    <li class="list-group-item border-0 px-4 py-2 d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-0 small fw-semibold"><?php echo htmlspecialchars($dev['device_name']); ?></p>
                            <small class="text-muted"><?php echo htmlspecialchars($dev['device_type'] ?? ''); ?></small>
                        </div>
                        <span class="badge bg-<?php echo $dc; ?> bg-opacity-75" style="font-size:10px;"><?php echo $dl; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <div class="px-4 pb-3 pt-2">
                    <a href="<?php echo BASE_URL; ?>/report_device" class="btn btn-sm btn-outline-danger w-100">
                        <i class="bi bi-exclamation-triangle me-1"></i>Báo hỏng thiết bị
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Danh sách bạn cùng phòng -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                <h6 class="fw-bold mb-0"><i class="bi bi-people-fill text-success me-2"></i>Bạn cùng phòng</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($roommates)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-person-x fs-2 d-block mb-2"></i>
                    <small>Phòng hiện chưa có bạn cùng phòng khác.</small>
                </div>
                <?php else: ?>

                <!-- Sơ đồ giường -->
                <div class="px-4 pt-4 pb-2">
                    <p class="small fw-semibold text-muted mb-3">Sơ đồ giường phòng <?php echo htmlspecialchars($myRoom['room_code']); ?></p>
                    <div class="row g-2">
                        <?php
                        // Fetch all beds for this room
                        $allBeds = $bedModel->getBedsByRoomId($myRoom['id']);
                        foreach ($allBeds as $bed):
                            $isMe = ($bed['id'] == $userData['bed_id']);
                            $occupied = !empty($bed['student_name']);
                            $color = $isMe ? 'primary' : ($occupied ? 'success' : 'light');
                            $textColor = ($color === 'light') ? 'text-muted' : 'text-white';
                        ?>
                        <div class="col-4 col-sm-2">
                            <div class="card border-0 bg-<?php echo $color; ?> <?php echo $color === 'light' ? 'border border-2 border-dashed' : ''; ?> text-center p-2"
                                 style="border-radius:10px; min-height:80px;">
                                <div class="<?php echo $textColor; ?>">
                                    <i class="bi bi-hospital fs-5 d-block mb-1"></i>
                                    <small class="fw-bold"><?php echo htmlspecialchars($bed['bed_label']); ?></small>
                                    <?php if ($isMe): ?><div style="font-size:9px;" class="opacity-75">Của tôi</div>
                                    <?php elseif ($occupied): ?><div style="font-size:9px;" class="opacity-75"><?php echo mb_substr($bed['student_name'],0,8,'UTF-8'); ?>...</div>
                                    <?php else: ?><div style="font-size:9px;" class="opacity-50">Trống</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr class="mx-4 my-3">

                <!-- Danh sách chi tiết -->
                <div class="table-responsive px-0 pb-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 py-3 small">Họ tên</th>
                                <th class="py-3 small">MSSV</th>
                                <th class="py-3 small">Giường</th>
                                <th class="py-3 small">SĐT</th>
                                <th class="py-3 small">Hộ khẩu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roommates as $rm): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-success bg-opacity-10 text-success fw-bold d-flex align-items-center justify-content-center"
                                             style="width:32px;height:32px;font-size:12px">
                                            <?php echo mb_substr($rm['fullname'],0,1,'UTF-8'); ?>
                                        </div>
                                        <div>
                                            <p class="mb-0 small fw-semibold">
                                                <?php echo htmlspecialchars($rm['fullname']); ?>
                                                <?php if ($rm['is_room_leader']): ?><span class="badge bg-primary ms-1" style="font-size:9px;">TP</span><?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 small text-muted"><?php echo htmlspecialchars($rm['student_code'] ?? '—'); ?></td>
                                <td class="py-3"><code class="bg-light text-dark px-2 py-1 rounded small"><?php echo htmlspecialchars($rm['bed_label']); ?></code></td>
                                <td class="py-3 small text-muted"><?php echo htmlspecialchars($rm['phone_personal'] ?? '—'); ?></td>
                                <td class="py-3 small text-muted"><?php echo htmlspecialchars(mb_strimwidth($rm['hometown'] ?? '—',0,20,'...')); ?></td>
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
