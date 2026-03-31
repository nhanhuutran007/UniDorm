<?php
/**
 * UniDorm – Admin Dashboard
 * path: views/admin/dashboard.php
 */
$pageTitle   = 'Dashboard';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '#']];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

require_once __DIR__ . '/../../app/models/RoomModel.php';

$roomModel = new RoomModel($conn);

// Thống kê sinh viên
$stmtSV = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND status = 'active'");
$totalStudents = $stmtSV->fetch_assoc()['total'] ?? 0;

$stmtPending = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND status = 'pending'");
$pendingStudents = $stmtPending->fetch_assoc()['total'] ?? 0;

// Thống kê phòng
$totalRooms     = $roomModel->countRoomsByStatus('all');
$availableRooms = $roomModel->countRoomsByStatus('available');
$fullRooms      = $roomModel->countRoomsByStatus('full');
$maintenanceRooms = $roomModel->countRoomsByStatus('maintenance');

// Tổng giường
$stmtBeds       = $conn->query("SELECT COUNT(*) as total FROM beds");
$totalBeds      = $stmtBeds->fetch_assoc()['total'] ?? 0;
$occupancyRate  = $totalBeds > 0 ? round(($totalStudents / $totalBeds) * 100) : 0;

// Báo cáo thiết bị hỏng chưa xử lý
$stmtRpt = $conn->query("SELECT COUNT(*) as cnt FROM device_reports WHERE status = 'pending'");
$pendingReports = $stmtRpt->fetch_assoc()['cnt'] ?? 0;

// Thông báo chưa đọc của toàn hệ thống
$stmtNotif = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE is_read = 0");
$unreadNotifications = $stmtNotif->fetch_assoc()['cnt'] ?? 0;

// Sinh viên mới nhất
$stmtNew = $conn->prepare("
    SELECT u.fullname, u.student_code, u.created_at, u.status,
           r.room_code, b.bed_label
    FROM users u
    LEFT JOIN beds b ON u.bed_id = b.id
    LEFT JOIN rooms r ON b.room_id = r.id
    WHERE u.role = 'student'
    ORDER BY u.created_at DESC
    LIMIT 8
");
$stmtNew->execute();
$recentStudents = $stmtNew->get_result()->fetch_all(MYSQLI_ASSOC);

// Báo cáo hỏng gần nhất
$stmtRptList = $conn->prepare("
    SELECT dr.title, dr.status, dr.created_at,
           r.room_code, u.fullname as reporter_name
    FROM device_reports dr
    JOIN rooms r ON dr.room_id = r.id
    JOIN users u ON dr.reporter_id = u.user_id
    ORDER BY dr.created_at DESC
    LIMIT 5
");
$stmtRptList->execute();
$recentReports = $stmtRptList->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!-- Stat Cards Row -->
<div class="row g-4 mb-4">

    <?php
    $stats = [
        ['icon'=>'people-fill',     'color'=>'primary',   'value'=>$totalStudents,    'label'=>'Sinh viên đang ở', 'sub'=>"{$pendingStudents} chờ kích hoạt", 'link'=>'/UniDorm/views/admin/students.php'],
        ['icon'=>'door-open-fill',  'color'=>'success',   'value'=>$availableRooms,   'label'=>'Phòng còn chỗ',    'sub'=>"{$fullRooms} đã đầy · {$maintenanceRooms} bảo trì", 'link'=>'/UniDorm/views/admin/rooms.php'],
        ['icon'=>'hospital-fill',   'color'=>'info',      'value'=>"{$occupancyRate}%",'label'=>'Tỉ lệ lấp đầy',   'sub'=>"{$totalStudents}/{$totalBeds} giường", 'link'=>'#'],
        ['icon'=>'tools',           'color'=>'danger',    'value'=>$pendingReports,   'label'=>'Báo cáo hỏng',     'sub'=>'Chờ xử lý', 'link'=>'/UniDorm/views/admin/device_reports.php'],
    ];
    foreach ($stats as $s):
    ?>
    <div class="col-sm-6 col-xl-3">
        <a href="<?php echo $s['link']; ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius:14px; transition:transform .2s;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="icon-wrap bg-<?php echo $s['color']; ?> bg-opacity-10 text-<?php echo $s['color']; ?> rounded-3 p-2">
                            <i class="bi bi-<?php echo $s['icon']; ?> fs-4"></i>
                        </div>
                        <i class="bi bi-arrow-up-right text-<?php echo $s['color']; ?> opacity-50 small"></i>
                    </div>
                    <h3 class="fw-black text-dark mb-0"><?php echo $s['value']; ?></h3>
                    <p class="fw-semibold text-dark small mb-0"><?php echo $s['label']; ?></p>
                    <p class="text-muted mb-0" style="font-size:11px;"><?php echo $s['sub']; ?></p>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Occupancy Bar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="fw-bold mb-0">Tỉ lệ lấp đầy KTX</h6>
                <small class="text-muted">Tổng <?php echo $totalBeds; ?> giường / <?php echo $totalRooms; ?> phòng</small>
            </div>
            <span class="badge bg-<?php echo $occupancyRate >= 90 ? 'danger' : ($occupancyRate >= 70 ? 'warning' : 'success'); ?> fs-6 px-3 py-2">
                <?php echo $occupancyRate; ?>%
            </span>
        </div>
        <div class="progress" style="height:12px; border-radius:10px;">
            <div class="progress-bar bg-<?php echo $occupancyRate >= 90 ? 'danger' : ($occupancyRate >= 70 ? 'warning' : 'primary'); ?>"
                 role="progressbar" style="width:<?php echo $occupancyRate; ?>%; border-radius:10px;" aria-valuenow="<?php echo $occupancyRate; ?>"></div>
        </div>
        <div class="d-flex justify-content-between mt-2 small text-muted">
            <span><span class="text-success fw-semibold"><?php echo $availableRooms; ?></span> phòng còn chỗ</span>
            <span><span class="text-warning fw-semibold"><?php echo $fullRooms; ?></span> phòng đầy</span>
            <span><span class="text-secondary fw-semibold"><?php echo $maintenanceRooms; ?></span> bảo trì</span>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Sinh viên mới nhất -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100" style="border-radius:14px;">
            <div class="card-header bg-white border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-people-fill text-primary me-2"></i>Sinh viên mới nhất</h6>
                <a href="/UniDorm/views/admin/students.php" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 py-3 small">Họ tên</th>
                                <th class="py-3 small">MSSV</th>
                                <th class="py-3 small text-center">Phòng</th>
                                <th class="py-3 small text-center">TT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStudents as $sv): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-initials bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:32px;height:32px;font-size:12px;">
                                            <?php echo mb_substr($sv['fullname'], 0, 1, 'UTF-8'); ?>
                                        </div>
                                        <span class="small fw-semibold"><?php echo htmlspecialchars(mb_strimwidth($sv['fullname'], 0, 25, '...')); ?></span>
                                    </div>
                                </td>
                                <td class="py-3 small text-muted"><?php echo htmlspecialchars($sv['student_code'] ?? '—'); ?></td>
                                <td class="py-3 text-center small">
                                    <?php if ($sv['room_code']): ?>
                                    <code class="bg-light px-2 py-1 rounded"><?php echo $sv['room_code']; ?>.<?php echo $sv['bed_label']; ?></code>
                                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                                <td class="py-3 text-center">
                                    <?php $sc = ['active'=>['success','Hoạt động'],'pending'=>['warning','Chờ'],'inactive'=>['secondary','Tắt'],'banned'=>['danger','Khoá']]; [$c,$l] = $sc[$sv['status']] ?? ['secondary','?']; ?>
                                    <span class="badge bg-<?php echo $c; ?> bg-opacity-75" style="font-size:10px;"><?php echo $l; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Báo cáo hỏng gần nhất -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100" style="border-radius:14px;">
            <div class="card-header bg-white border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-tools text-danger me-2"></i>Báo cáo hỏng gần nhất</h6>
                <a href="/UniDorm/views/admin/device_reports.php" class="btn btn-sm btn-outline-danger">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentReports)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-check-circle fs-2 text-success d-block mb-2"></i>
                    <small>Không có báo cáo mới</small>
                </div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentReports as $rpt):
                        $stMap = ['pending'=>['warning','Chờ'],'in_progress'=>['info','Xử lý'],'resolved'=>['success','Xong'],'rejected'=>['danger','Từ chối']];
                        [$sc, $sl] = $stMap[$rpt['status']] ?? ['secondary','?'];
                    ?>
                    <li class="list-group-item border-0 px-4 py-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1 me-3">
                                <p class="mb-0 small fw-semibold text-dark"><?php echo htmlspecialchars(mb_strimwidth($rpt['title'], 0, 40, '...')); ?></p>
                                <small class="text-muted"><?php echo htmlspecialchars($rpt['room_code']); ?> · <?php echo htmlspecialchars($rpt['reporter_name']); ?></small>
                            </div>
                            <span class="badge bg-<?php echo $sc; ?> bg-opacity-75 flex-shrink-0" style="font-size:10px;"><?php echo $sl; ?></span>
                        </div>
                        <div class="text-muted mt-1" style="font-size:10px;"><?php echo date('d/m/Y H:i', strtotime($rpt['created_at'])); ?></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.hover-lift:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.10) !important; }
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
