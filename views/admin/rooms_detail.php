<?php
/**
 * UniDorm – Admin: Chi tiết phòng (rooms_detail.php)
 * ?id=<room_id>
 */
$pageTitle   = 'Chi tiết phòng';
$breadcrumbs = [
    ['label' => 'Dashboard',     'url' => '/UniDorm/views/admin/dashboard.php'],
    ['label' => 'Quản lý phòng', 'url' => '/UniDorm/views/admin/rooms.php'],
    ['label' => 'Chi tiết',      'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

$roomId = (int)($_GET['id'] ?? 0);
if (!$roomId) {
    header('Location: /UniDorm/views/admin/rooms.php');
    exit;
}

// Room info
$rStmt = $conn->prepare("
    SELECT r.*, f.floor_number, b.name as building_name
    FROM rooms r
    JOIN floors f  ON r.floor_id  = f.id
    JOIN buildings b ON f.building_id = b.id
    WHERE r.id = ?
");
$rStmt->bind_param('i', $roomId);
$rStmt->execute();
$room = $rStmt->get_result()->fetch_assoc();

if (!$room) {
    header('Location: /UniDorm/views/admin/rooms.php?error=room_not_found');
    exit;
}

// Beds + sinh viên đang ở
$bStmt = $conn->prepare("
    SELECT b.id as bed_id, b.bed_label, b.is_occupied,
           u.user_id, u.fullname, u.student_code, u.phone_personal, u.hometown,
           u.is_room_leader, u.status as user_status, u.profile_picture
    FROM beds b
    LEFT JOIN users u ON u.bed_id = b.id AND u.status = 'active'
    WHERE b.room_id = ?
    ORDER BY b.bed_label ASC
");
$bStmt->bind_param('i', $roomId);
$bStmt->execute();
$beds = $bStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Devices trong phòng
$dStmt = $conn->prepare("
    SELECT d.id, d.device_name, d.device_type, d.status, d.notes
    FROM devices d
    WHERE d.room_id = ?
    ORDER BY d.device_type, d.device_name
");
$dStmt->bind_param('i', $roomId);
$dStmt->execute();
$devices = $dStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Device reports gần đây
$drStmt = $conn->prepare("
    SELECT dr.id, dr.title, dr.status, dr.created_at, u.fullname
    FROM device_reports dr
    JOIN users u ON dr.reporter_id = u.user_id
    WHERE dr.room_id = ?
    ORDER BY dr.created_at DESC
    LIMIT 5
");
$drStmt->bind_param('i', $roomId);
$drStmt->execute();
$roomReports = $drStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$occupiedCount = count(array_filter($beds, fn($b) => !empty($b['user_id'])));
$occupancyPct  = $room['max_capacity'] > 0 ? round($occupiedCount / $room['max_capacity'] * 100) : 0;

$statusConfig = [
    'available'   => ['success', 'Còn chỗ'],
    'full'        => ['warning', 'Đầy'],
    'maintenance' => ['danger',  'Bảo trì'],
    'closed'      => ['secondary','Đóng'],
];
[$roomSC, $roomSL] = $statusConfig[$room['status']] ?? ['secondary', '?'];

$deviceStatusMap = ['good'=>['success','Tốt'],'broken'=>['danger','Hỏng'],'maintenance'=>['warning','Đang sửa']];
$reportStatusMap = ['pending'=>['warning','Chờ'],'in_progress'=>['info','Đang xử lý'],'resolved'=>['success','Xong'],'rejected'=>['secondary','Từ chối']];
?>

<!-- Room header -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                        <i class="bi bi-door-open-fill fs-3"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0">Phòng <?php echo htmlspecialchars($room['room_code']); ?></h3>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($room['building_name']); ?> – Lầu <?php echo $room['floor_number']; ?></p>
                    </div>
                    <span class="badge bg-<?php echo $roomSC; ?> bg-opacity-75 ms-2"><?php echo $roomSL; ?></span>
                </div>
            </div>
            <div class="col-auto">
                <a href="/UniDorm/views/admin/rooms.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại
                </a>
            </div>
        </div>

        <!-- Occupancy bar -->
        <div class="mt-4">
            <div class="d-flex justify-content-between mb-1">
                <small class="text-muted fw-semibold">Sĩ số hiện tại</small>
                <small class="fw-bold"><?php echo $occupiedCount; ?> / <?php echo $room['max_capacity']; ?> giường</small>
            </div>
            <div class="progress" style="height:10px; border-radius:8px;">
                <div class="progress-bar bg-<?php echo $occupancyPct>=100?'danger':($occupancyPct>=80?'warning':'success'); ?>"
                     style="width:<?php echo $occupancyPct; ?>%; border-radius:8px; transition:width .4s;"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Danh sách giường -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-bounding-box me-2 text-primary"></i>Sơ đồ giường</h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <?php foreach ($beds as $bed):
                        $hasUser = !empty($bed['user_id']);
                        $isLeader = $hasUser && $bed['is_room_leader'] == 1;
                    ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="border rounded-3 p-3 h-100 <?php echo $hasUser ? 'border-primary' : 'border-dashed'; ?>"
                             style="border-style:<?php echo $hasUser?'solid':'dashed'; ?>!important; background:<?php echo $hasUser?'#eff6ff':'#f9fafb'; ?>;">

                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="bg-<?php echo $hasUser?'primary':'secondary'; ?> bg-opacity-<?php echo $hasUser?'15':'10'; ?> text-<?php echo $hasUser?'primary':'secondary'; ?> rounded-2 px-2 py-1">
                                    <strong style="font-size:13px;"><?php echo $bed['bed_label']; ?></strong>
                                </div>
                                <?php if ($isLeader): ?>
                                <span class="badge bg-warning text-dark" style="font-size:9px;"><i class="bi bi-star-fill me-1"></i>Trưởng phòng</span>
                                <?php elseif (!$hasUser): ?>
                                <span class="badge bg-light text-secondary border" style="font-size:9px;">Trống</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($hasUser): ?>
                            <div class="d-flex align-items-center gap-2">
                                <?php
                                $bedPrSrc = !empty($bed['profile_picture']) ? '/UniDorm/'.htmlspecialchars($bed['profile_picture']) : '/UniDorm/assets/images/default.jpg';
                                ?>
                                <img src="<?php echo $bedPrSrc; ?>"
                                     onerror="this.onerror=null; this.src='/UniDorm/assets/images/default.jpg';"
                                     class="rounded-circle bg-white" width="36" height="36" style="object-fit:cover;">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate" style="font-size:12px;" title="<?php echo htmlspecialchars($bed['fullname']); ?>">
                                        <?php echo htmlspecialchars($bed['fullname']); ?>
                                    </div>
                                    <small class="text-muted" style="font-size:10px;"><?php echo $bed['student_code']; ?></small>
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-top">
                                <small class="text-muted" style="font-size:10px;">
                                    <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($bed['hometown'] ?? '—'); ?>
                                </small>
                                <?php if ($bed['phone_personal']): ?>
                                <br><small class="text-muted" style="font-size:10px;"><i class="bi bi-phone me-1"></i><?php echo $bed['phone_personal']; ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2">
                                <a href="/UniDorm/views/admin/updateuser.php?id=<?php echo $bed['user_id']; ?>" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:10px;">
                                    <i class="bi bi-pencil me-1"></i>Sửa
                                </a>
                            </div>
                            <?php else: ?>
                            <p class="text-muted small mb-0 fst-italic mt-1">Chưa có sinh viên</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right sidebar: Thiết bị + Báo cáo gần đây -->
    <div class="col-lg-4">
        <!-- Thiết bị -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-cpu me-2 text-warning"></i>Thiết bị phòng</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($devices)): ?>
                <p class="text-muted small text-center py-3">Chưa có thiết bị nào</p>
                <?php else: ?>
                <ul class="list-group list-group-flush rounded-3">
                    <?php foreach ($devices as $d):
                        [$dc,$dl] = $deviceStatusMap[$d['status']] ?? ['secondary','?'];
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-4">
                        <div>
                            <div class="fw-semibold small"><?php echo htmlspecialchars($d['device_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($d['device_type']); ?></small>
                        </div>
                        <span class="badge bg-<?php echo $dc; ?> bg-opacity-75" style="font-size:10px;"><?php echo $dl; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Báo cáo hỏng gần đây -->
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between">
                <h6 class="fw-bold mb-0"><i class="bi bi-tools me-2 text-danger"></i>Báo cáo hỏng gần đây</h6>
                <a href="/UniDorm/views/admin/device_reports.php?room_id=<?php echo $roomId; ?>" class="text-primary small">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($roomReports)): ?>
                <p class="text-muted small text-center py-3">Chưa có báo cáo nào</p>
                <?php else: ?>
                <ul class="list-group list-group-flush rounded-3">
                    <?php foreach ($roomReports as $rp):
                        [$rc,$rl] = $reportStatusMap[$rp['status']] ?? ['secondary','?'];
                    ?>
                    <li class="list-group-item px-4 py-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="fw-semibold small text-truncate" style="max-width:160px;" title="<?php echo htmlspecialchars($rp['title']); ?>">
                                <?php echo htmlspecialchars($rp['title']); ?>
                            </div>
                            <span class="badge bg-<?php echo $rc; ?> bg-opacity-75 ms-2" style="font-size:9px;"><?php echo $rl; ?></span>
                        </div>
                        <small class="text-muted">
                            <?php echo htmlspecialchars($rp['fullname']); ?> · <?php echo date('d/m', strtotime($rp['created_at'])); ?>
                        </small>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
