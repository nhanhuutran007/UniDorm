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
$stmtSV = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND status IN ('active', 'pending')");
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
<div class="row g-4 mb-4 fade-in-up stagger-1">
    <?php
    $stats = [
        ['icon'=>'people-fill',     'color'=>'primary',   'value'=>$totalStudents,    'label'=>'Sinh viên đang ở', 'sub'=>"{$pendingStudents} chờ kích hoạt", 'link'=>BASE_URL.'/students'],
        ['icon'=>'door-open-fill',  'color'=>'success',   'value'=>$availableRooms,   'label'=>'Phòng còn chỗ',    'sub'=>"{$fullRooms} đã đầy", 'link'=>BASE_URL.'/rooms?status=available'],
        ['icon'=>'bar-chart-fill',  'color'=>'info',      'value'=>"{$occupancyRate}%",'label'=>'Tỉ lệ lấp đầy',   'sub'=>"{$totalStudents}/{$totalBeds} giường", 'link'=>'#'],
        ['icon'=>'tools',           'color'=>'danger',    'value'=>$pendingReports,   'label'=>'Báo cáo hỏng',     'sub'=>'Chờ xử lý', 'link'=>BASE_URL.'/device_reports'],
    ];
    foreach ($stats as $s):
    ?>
    <div class="col-sm-6 col-xl-3">
        <a href="<?php echo $s['link']; ?>" class="text-decoration-none">
            <div class="modern-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="stat-icon-wrap bg-gradient-<?php echo $s['color']; ?> shadow-sm">
                            <i class="bi bi-<?php echo $s['icon']; ?>"></i>
                        </div>
                        <i class="bi bi-arrow-up-right text-muted opacity-50 small"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-1" style="font-size: 2.2rem;"><?php echo $s['value']; ?></h3>
                    <p class="fw-semibold text-secondary small mb-1"><?php echo $s['label']; ?></p>
                    <p class="text-muted mb-0" style="font-size:11px; opacity: 0.8;"><?php echo $s['sub']; ?></p>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Occupancy Bar -->
<div class="modern-card mb-4 fade-in-up stagger-2">
    <div class="card-body p-4 d-flex align-items-center justify-content-between">
        <div class="flex-grow-1 me-4">
            <h5 class="fw-bold mb-1">Tỉ lệ lấp đầy KTX</h5>
            <p class="text-muted small mb-3">Tổng <?php echo $totalBeds; ?> giường / <?php echo $totalRooms; ?> phòng</p>
            <div class="progress" style="height:14px; border-radius:20px; background: rgba(0,0,0,0.05); box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                <div class="progress-bar bg-gradient-<?php echo $occupancyRate >= 90 ? 'danger' : ($occupancyRate >= 70 ? 'warning' : 'primary'); ?>"
                     role="progressbar" style="width:<?php echo $occupancyRate; ?>%; border-radius:20px; transition: width 1.5s ease-out;" aria-valuenow="<?php echo $occupancyRate; ?>"></div>
            </div>
            <div class="d-flex justify-content-between mt-3 small">
                <span><span class="text-success fw-bold"><?php echo $availableRooms; ?></span> phòng còn chỗ</span>
                <span><span class="text-warning fw-bold"><?php echo $fullRooms; ?></span> phòng đầy</span>
                <span><span class="text-secondary fw-bold"><?php echo $maintenanceRooms; ?></span> bảo trì</span>
            </div>
        </div>
        <div class="flex-shrink-0 d-none d-md-block">
            <div class="position-relative" style="width: 100px; height: 100px;">
                <svg viewBox="0 0 36 36" class="circular-chart">
                    <path class="circle-bg" stroke="rgba(0,0,0,0.05)"
                      d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path class="circle circle-<?php echo $occupancyRate >= 90 ? 'danger' : ($occupancyRate >= 70 ? 'warning' : 'primary'); ?>"
                      stroke-dasharray="<?php echo $occupancyRate; ?>, 100"
                      d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <text x="18" y="20.35" class="percentage"><?php echo $occupancyRate; ?>%</text>
                </svg>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 fade-in-up stagger-3 mb-4">
    <!-- Sinh viên mới nhất -->
    <div class="col-lg-7">
        <div class="modern-card h-100">
            <div class="modern-card-header d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                    <span class="stat-icon-wrap bg-primary bg-opacity-10 text-primary rounded-circle me-2" style="width:36px; height:36px; font-size:16px;">
                        <i class="bi bi-people-fill"></i>
                    </span>Sinh viên mới
                </h6>
                <a href="<?php echo BASE_URL; ?>/students" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm border" style="font-weight: 500; font-size: 0.8rem;">Xem tất cả</a>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Họ tên</th>
                                <th>MSSV</th>
                                <th class="text-center">Phòng</th>
                                <th class="text-center">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStudents as $sv): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-initials bg-gradient-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width:36px;height:36px;font-size:14px; font-weight:600; color:#fff;">
                                            <?php echo mb_substr($sv['fullname'], 0, 1, 'UTF-8'); ?>
                                        </div>
                                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars(mb_strimwidth($sv['fullname'], 0, 25, '...')); ?></span>
                                    </div>
                                </td>
                                <td class="text-muted fw-medium"><?php echo htmlspecialchars($sv['student_code'] ?? '—'); ?></td>
                                <td class="text-center">
                                    <?php if ($sv['room_code']): ?>
                                    <span class="badge bg-white text-dark shadow-sm border px-2 py-1"><?php echo $sv['room_code']; ?>.<?php echo $sv['bed_label']; ?></span>
                                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php $sc = ['active'=>['success','Hoạt động'],'pending'=>['warning','Chờ'],'inactive'=>['secondary','Tắt'],'banned'=>['danger','Khoá']]; [$c,$l] = $sc[$sv['status']] ?? ['secondary','?']; ?>
                                    <span class="badge badge-soft-<?php echo $c; ?> rounded-pill px-3 py-1"><?php echo $l; ?></span>
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
        <div class="modern-card h-100">
            <div class="modern-card-header d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                    <span class="stat-icon-wrap bg-danger bg-opacity-10 text-danger rounded-circle me-2" style="width:36px; height:36px; font-size:16px;">
                        <i class="bi bi-tools"></i>
                    </span>Báo cáo hỏng
                </h6>
                <a href="<?php echo BASE_URL; ?>/device_reports" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm border" style="font-weight: 500; font-size: 0.8rem;">Xem tất cả</a>

            </div>
            <div class="card-body p-0">
                <?php if (empty($recentReports)): ?>
                <div class="text-center py-5 text-muted">
                    <div class="stat-icon-wrap bg-gradient-success mx-auto mb-3 shadow-sm text-white" style="width:50px; height:50px; border-radius: 50%;">
                        <i class="bi bi-check-lg" style="font-size: 24px;"></i>
                    </div>
                    <span class="fw-medium">Không có báo cáo lỗi mới</span>
                </div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentReports as $rpt):
                        $stMap = ['pending'=>['warning','Chờ'],'in_progress'=>['info','Xử lý'],'resolved'=>['success','Xong'],'rejected'=>['danger','Từ chối']];
                        [$sc, $sl] = $stMap[$rpt['status']] ?? ['secondary','?'];
                    ?>
                    <li class="list-group-item border-0 border-bottom px-4 py-3" style="transition: background 0.2s; cursor: pointer;" onmouseover="this.style.background='var(--primary-light)'" onmouseout="this.style.background='transparent'">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1 me-3">
                                <p class="mb-1 fw-bold text-dark" style="font-size:0.95rem;"><?php echo htmlspecialchars(mb_strimwidth($rpt['title'], 0, 40, '...')); ?></p>
                                <div class="d-flex align-items-center text-muted small mt-1 gap-2">
                                    <span class="badge bg-white text-dark shadow-sm border px-2 py-0 fw-medium"><i class="bi bi-door-open me-1"></i><?php echo htmlspecialchars($rpt['room_code']); ?></span>
                                    <span class="fw-medium" style="font-size: 13px;"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars(mb_strimwidth($rpt['reporter_name'], 0, 15, '...')); ?></span>
                                </div>
                            </div>
                            <span class="badge badge-soft-<?php echo $sc; ?> rounded-pill px-3 py-1 flex-shrink-0"><?php echo $sl; ?></span>
                        </div>
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
