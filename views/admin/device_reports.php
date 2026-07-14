<?php
/**
 * UniDorm – Admin: Quản lý báo cáo thiết bị hỏng
 * path: views/admin/device_reports.php
 */
$pageTitle   = 'Báo cáo thiết bị hỏng';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => BASE_URL . '/dashboard'],
    ['label' => 'Báo cáo thiết bị hỏng', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

$filterStatus = $_GET['status'] ?? '';
$filterRoom   = trim($_GET['room_code'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

// Xử lý cập nhật trạng thái
$updateMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $rptId     = (int)($_POST['report_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $valid     = ['pending','in_progress','resolved','rejected'];
    if ($rptId && in_array($newStatus, $valid)) {
        $resolvedAt = in_array($newStatus, ['resolved','rejected']) ? 'NOW()' : 'NULL';
        $stmt = $conn->prepare("UPDATE device_reports SET status = ?, resolved_by = ?, resolved_at = $resolvedAt, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('sii', $newStatus, $userId, $rptId);
        $updateMsg = $stmt->execute() ? 'success' : 'error';
    }
}

// Build filters
$where  = ["1=1"];
$params = [];
$types  = '';

if ($filterStatus) { $where[] = "dr.status = ?"; $params[] = $filterStatus; $types .= 's'; }
if ($filterRoom)   { $where[] = "r.room_code = ?"; $params[] = $filterRoom;   $types .= 's'; }

$whereSQL = implode(' AND ', $where);

// Count
$cntStmt = $conn->prepare("SELECT COUNT(*) as c FROM device_reports dr JOIN rooms r ON dr.room_id = r.id WHERE $whereSQL");
if ($types) $cntStmt->bind_param($types, ...$params);
$cntStmt->execute();
$total      = $cntStmt->get_result()->fetch_assoc()['c'] ?? 0;
$totalPages = max(1, ceil($total / $perPage));

// Fetch
$dataStmt = $conn->prepare("
    SELECT dr.id, dr.title, dr.description, dr.status, dr.created_at, dr.updated_at,
           r.room_code, f.floor_number,
           u.fullname as reporter_name, u.student_code,
           d.device_name,
           res.fullname as resolver_name
    FROM device_reports dr
    JOIN rooms r ON dr.room_id = r.id
    JOIN floors f ON r.floor_id = f.id
    JOIN users u ON dr.reporter_id = u.user_id
    LEFT JOIN devices d ON dr.device_id = d.id
    LEFT JOIN users res ON dr.resolved_by = res.user_id
    WHERE $whereSQL
    ORDER BY FIELD(dr.status,'pending','in_progress','resolved','rejected'), dr.created_at DESC
    LIMIT ? OFFSET ?
");
$dataStmt->bind_param($types . 'ii', ...array_merge($params, [$perPage, $offset]));
$dataStmt->execute();
$reports = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
$stats = [];
foreach (['pending','in_progress','resolved','rejected'] as $st) {
    $r = $conn->query("SELECT COUNT(*) as c FROM device_reports WHERE status = '$st'");
    $stats[$st] = $r->fetch_assoc()['c'] ?? 0;
}

$statusConfig = [
    'pending'     => ['warning', 'Chờ xử lý',  'hourglass-split'],
    'in_progress' => ['info',    'Đang xử lý',  'arrow-repeat'],
    'resolved'    => ['success', 'Đã xử lý',    'check-circle-fill'],
    'rejected'    => ['danger',  'Đã từ chối',  'x-circle-fill'],
];
?>

<?php if ($updateMsg === 'success'): ?>
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i> Cập nhật trạng thái thành công!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stat Mini Cards -->
<div class="row g-3 mb-4">
    <?php foreach ($statusConfig as $st => [$color, $label, $icon]): ?>
    <div class="col-6 col-lg-3">
        <a href="?status=<?php echo $st; ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm <?php echo $filterStatus === $st ? "border-$color border-2" : ''; ?>" style="border-radius:12px;">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="text-<?php echo $color; ?> bg-<?php echo $color; ?> bg-opacity-10 rounded-3 p-2">
                        <i class="bi bi-<?php echo $icon; ?> fs-5"></i>
                    </div>
                    <div>
                        <h5 class="fw-black mb-0 text-dark"><?php echo $stats[$st]; ?></h5>
                        <small class="text-muted"><?php echo $label; ?></small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter bar -->
<div class="d-flex gap-3 mb-4 flex-wrap align-items-center">
    <form method="GET" class="d-flex gap-2 flex-grow-1">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
        <input type="text" name="room_code" class="form-control form-control-sm" style="max-width:160px;"
               placeholder="Mã phòng..." value="<?php echo htmlspecialchars($filterRoom); ?>">
        <button class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Lọc</button>
        <?php if ($filterStatus || $filterRoom): ?>
        <a href="<?php echo BASE_URL; ?>/device_reports" class="btn btn-sm btn-outline-secondary">Xóa lọc</a>
        <?php endif; ?>
    </form>
    <span class="text-muted small">Tìm thấy <strong><?php echo $total; ?></strong> báo cáo</span>
</div>

<!-- Reports Table -->
<div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3 small">#</th>
                        <th class="py-3 small">Tiêu đề sự cố</th>
                        <th class="py-3 small">Phòng</th>
                        <th class="py-3 small">Sinh viên</th>
                        <th class="py-3 small">Thiết bị</th>
                        <th class="py-3 small text-center">Trạng thái</th>
                        <th class="py-3 small">Ngày gửi</th>
                        <th class="py-3 small text-center">Xử lý</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có báo cáo nào.
                    </td></tr>
                    <?php else: foreach ($reports as $i => $rpt):
                        [$clr, $lbl, $ico] = $statusConfig[$rpt['status']] ?? ['secondary','?','question'];
                    ?>
                    <tr>
                        <td data-label="#" class="ps-4 py-3 text-muted small"><?php echo $offset + $i + 1; ?></td>
                        <td data-label="Tiêu đề sự cố" class="py-3">
                            <p class="mb-0 fw-semibold small text-dark"><?php echo htmlspecialchars(mb_strimwidth($rpt['title'], 0, 45, '...')); ?></p>
                            <?php if ($rpt['description']): ?>
                            <small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($rpt['description'], 0, 60, '...')); ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Phòng" class="py-3">
                            <code class="bg-light text-dark px-2 py-1 rounded small"><?php echo $rpt['room_code']; ?></code>
                            <div class="text-muted" style="font-size:10px;">Lầu <?php echo $rpt['floor_number']; ?></div>
                        </td>
                        <td data-label="Sinh viên" class="py-3 small">
                            <p class="mb-0 fw-semibold"><?php echo htmlspecialchars(mb_strimwidth($rpt['reporter_name'], 0, 20, '...')); ?></p>
                            <small class="text-muted"><?php echo htmlspecialchars($rpt['student_code'] ?? ''); ?></small>
                        </td>
                        <td data-label="Thiết bị" class="py-3 small text-muted"><?php echo htmlspecialchars($rpt['device_name'] ?? 'Khác'); ?></td>
                        <td data-label="Trạng thái" class="py-3 text-center">
                            <span class="badge bg-<?php echo $clr; ?> bg-opacity-75" style="font-size:10px;">
                                <i class="bi bi-<?php echo $ico; ?> me-1"></i><?php echo $lbl; ?>
                            </span>
                        </td>
                        <td data-label="Ngày gửi" class="py-3 text-muted" style="font-size:11px;"><?php echo date('d/m/Y H:i', strtotime($rpt['created_at'])); ?></td>
                        <td data-label="Xử lý" class="py-3 text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" style="font-size:11px;">
                                    Chuyển trạng thái
                                </button>
                                <ul class="dropdown-menu">
                                    <?php foreach ($statusConfig as $st => [$c, $l, $ico2]): ?>
                                    <?php if ($st !== $rpt['status']): ?>
                                    <li>
                                        <form method="POST">
                                            <input type="hidden" name="update_status" value="1">
                                            <input type="hidden" name="report_id" value="<?php echo $rpt['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo $st; ?>">
                                            <button type="submit" class="dropdown-item text-<?php echo $c; ?> small">
                                                <i class="bi bi-<?php echo $ico2; ?> me-1"></i><?php echo $l; ?>
                                            </button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center" style="border-radius:0 0 14px 14px;">
        <small class="text-muted">Trang <?php echo $page; ?> / <?php echo $totalPages; ?></small>
        <nav><ul class="pagination pagination-sm mb-0 gap-1">
            <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
            <li class="page-item <?php echo $p===$page?'active':''; ?>">
                <a class="page-link rounded" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$p])); ?>"><?php echo $p; ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
