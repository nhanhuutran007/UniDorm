<?php
/**
 * UniDorm – Admin: Quản lý phòng ở
 * path: views/admin/rooms.php
 */
$pageTitle   = 'Quản lý phòng';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => BASE_URL . '/dashboard'],
    ['label' => 'Quản lý phòng', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

require_once __DIR__ . '/../../app/models/RoomModel.php';
$roomModel = new RoomModel($conn);

// ── Filters ───────────────────────────────────────────────────────────
$filterFloor  = $_GET['floor_id'] ?? '';
$filterStatus = $_GET['status']   ?? '';
$filterSearch = trim($_GET['q']   ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

// ── POST: cập nhật status phòng ───────────────────────────────────────
$updateMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_room_status'])) {
    $roomId    = (int)$_POST['room_id'];
    $newStatus = $_POST['new_status'];
    $validSt   = ['available','full','maintenance','closed'];
    if ($roomId && in_array($newStatus, $validSt)) {
        $st = $conn->prepare("UPDATE rooms SET status = ? WHERE id = ?");
        $st->bind_param('si', $newStatus, $roomId);
        $updateMsg = $st->execute() ? 'success' : 'error';
    }
}

// ── Build query ───────────────────────────────────────────────────────
$where  = ["1=1"];
$params = [];
$types  = '';

if ($filterSearch) {
    $where[]  = "(r.room_code LIKE ? OR bld.name LIKE ?)";
    $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%";
    $types   .= 'ss';
}
if ($filterFloor) {
    $where[]  = "r.floor_id = ?";
    $params[] = $filterFloor; $types .= 'i';
}
if ($filterStatus) {
    $where[]  = "r.status = ?";
    $params[] = $filterStatus; $types .= 's';
}
$whereSQL = implode(' AND ', $where);

// Count total
$cntStmt = $conn->prepare("
    SELECT COUNT(*) as c FROM rooms r
    JOIN floors f ON r.floor_id = f.id
    JOIN buildings bld ON f.building_id = bld.id
    WHERE $whereSQL
");
if ($types) $cntStmt->bind_param($types, ...$params);
$cntStmt->execute();
$totalRooms = $cntStmt->get_result()->fetch_assoc()['c'] ?? 0;
$totalPages = max(1, ceil($totalRooms / $perPage));

// Fetch rooms với số SV hiện tại
$dataStmt = $conn->prepare("
    SELECT r.id, r.room_code, r.status, r.max_capacity, r.created_at,
           f.floor_number, bld.name as building_name,
           COUNT(u.user_id) as current_count
    FROM rooms r
    JOIN floors f ON r.floor_id = f.id
    JOIN buildings bld ON f.building_id = bld.id
    LEFT JOIN beds b ON b.room_id = r.id
    LEFT JOIN users u ON u.bed_id = b.id AND u.status IN ('active', 'pending')
    WHERE $whereSQL
    GROUP BY r.id, r.room_code, r.status, r.max_capacity, r.created_at, f.floor_number, bld.name
    ORDER BY f.floor_number ASC, r.room_code ASC
    LIMIT ? OFFSET ?
");
$allTypes   = $types . 'ii';
$allParams  = array_merge($params, [$perPage, $offset]);
$dataStmt->bind_param($allTypes, ...$allParams);
$dataStmt->execute();
$rooms = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Tất cả floors cho filter
$floorsAll = $conn->query("SELECT f.id, f.floor_number, b.name as bname FROM floors f JOIN buildings b ON f.building_id = b.id ORDER BY b.name, f.floor_number")->fetch_all(MYSQLI_ASSOC);

// Stats
$stats = [
    'available'   => $conn->query("SELECT COUNT(*) as c FROM rooms WHERE status='available'")->fetch_assoc()['c'] ?? 0,
    'full'        => $conn->query("SELECT COUNT(*) as c FROM rooms WHERE status='full'")->fetch_assoc()['c'] ?? 0,
    'maintenance' => $conn->query("SELECT COUNT(*) as c FROM rooms WHERE status='maintenance'")->fetch_assoc()['c'] ?? 0,
    'total'       => $conn->query("SELECT COUNT(*) as c FROM rooms")->fetch_assoc()['c'] ?? 0,
];

$statusConfig = [
    'available'   => ['success', 'Còn chỗ',    'door-open-fill'],
    'full'        => ['warning', 'Đã đầy',     'door-closed-fill'],
    'maintenance' => ['danger',  'Bảo trì',    'tools'],
    'closed'      => ['secondary','Đóng cửa',  'lock-fill'],
];
?>

<?php if ($updateMsg === 'success'): ?>
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>Cập nhật trạng thái phòng thành công!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2"><i class="bi bi-building fs-4"></i></div>
                <div><h5 class="fw-black mb-0"><?php echo $stats['total']; ?></h5><small class="text-muted">Tổng số phòng</small></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <a href="?status=available" class="text-decoration-none">
        <div class="card border-0 shadow-sm <?php echo $filterStatus==='available'?'border-success border-2':''; ?>" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="bg-success bg-opacity-10 text-success rounded-3 p-2"><i class="bi bi-door-open-fill fs-4"></i></div>
                <div><h5 class="fw-black mb-0"><?php echo $stats['available']; ?></h5><small class="text-muted">Còn chỗ trống</small></div>
            </div>
        </div></a>
    </div>
    <div class="col-sm-6 col-lg-3">
        <a href="?status=full" class="text-decoration-none">
        <div class="card border-0 shadow-sm <?php echo $filterStatus==='full'?'border-warning border-2':''; ?>" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2"><i class="bi bi-door-closed-fill fs-4"></i></div>
                <div><h5 class="fw-black mb-0"><?php echo $stats['full']; ?></h5><small class="text-muted">Phòng đã đầy</small></div>
            </div>
        </div></a>
    </div>
    <div class="col-sm-6 col-lg-3">
        <a href="?status=maintenance" class="text-decoration-none">
        <div class="card border-0 shadow-sm <?php echo $filterStatus==='maintenance'?'border-danger border-2':''; ?>" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-2"><i class="bi bi-tools fs-4"></i></div>
                <div><h5 class="fw-black mb-0"><?php echo $stats['maintenance']; ?></h5><small class="text-muted">Đang bảo trì</small></div>
            </div>
        </div></a>
    </div>
</div>

<!-- Filter bar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted mb-1">Tìm kiếm phòng</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 bg-light"
                           placeholder="Mã phòng... (VD: L.0801)"
                           value="<?php echo htmlspecialchars($filterSearch); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Tầng</label>
                <select name="floor_id" class="form-select">
                    <option value="">Tất cả tầng</option>
                    <?php foreach ($floorsAll as $f): ?>
                    <option value="<?php echo $f['id']; ?>" <?php echo $filterFloor == $f['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($f['bname']); ?> – Lầu <?php echo $f['floor_number']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="">Tất cả</option>
                    <?php foreach ($statusConfig as $v => [$c, $l, $i]): ?>
                    <option value="<?php echo $v; ?>" <?php echo $filterStatus === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Lọc</button>
                <a href="<?php echo BASE_URL; ?>/rooms" class="btn btn-outline-secondary" title="Xóa lọc"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Kết quả -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="mb-0 text-muted small">Tìm thấy <strong><?php echo $totalRooms; ?></strong> phòng</p>
</div>

<!-- Room Grid -->
<?php if (empty($rooms)): ?>
<div class="card border-0 shadow-sm text-center py-5" style="border-radius:14px;">
    <i class="bi bi-inbox fs-2 text-muted d-block mb-2"></i>
    <p class="text-muted small mb-0">Không tìm thấy phòng nào.</p>
</div>
<?php else: ?>
<div class="row g-3" id="roomGrid">
    <?php foreach ($rooms as $room):
        // Tự động chuyển hiển thị sang 'Đã đầy' nếu số người đã đạt tối đa, 
        // kể cả khi trạng thái trong DB vẫn là 'available'
        $displayStatus = $room['status'];
        if ($displayStatus === 'available' && $room['current_count'] >= $room['max_capacity']) {
            $displayStatus = 'full';
        }
        [$sc,$sl,$si] = $statusConfig[$displayStatus] ?? ['secondary','?','question'];
        $pct = $room['max_capacity'] > 0 ? round(($room['current_count'] / $room['max_capacity']) * 100) : 0;
        $barColor = $pct >= 100 ? 'danger' : ($pct >= 80 ? 'warning' : 'success');
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px; border-top: 3px solid var(--bs-<?php echo $sc; ?>)!important;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($room['room_code']); ?></h5>
                        <small class="text-muted"><?php echo htmlspecialchars($room['building_name']); ?> · Lầu <?php echo $room['floor_number']; ?></small>
                    </div>
                    <span class="badge bg-<?php echo $sc; ?> bg-opacity-75" style="font-size:10px;">
                        <i class="bi bi-<?php echo $si; ?> me-1"></i><?php echo $sl; ?>
                    </span>
                </div>

                <!-- Occupancy progress -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Sĩ số</small>
                        <small class="fw-semibold"><?php echo $room['current_count']; ?>/<?php echo $room['max_capacity']; ?> người</small>
                    </div>
                    <div class="progress" style="height:6px;border-radius:4px;">
                        <div class="progress-bar bg-<?php echo $barColor; ?>" style="width:<?php echo $pct; ?>%;border-radius:4px;"></div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="d-flex gap-2">
                    <a href="<?php echo BASE_URL; ?>/rooms_detail?id=<?php echo $room['id']; ?>"
                       class="btn btn-sm btn-outline-primary flex-grow-1" style="font-size:11px;">
                        <i class="bi bi-eye me-1"></i>Chi tiết
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" style="font-size:11px;">
                            Trạng thái
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php foreach ($statusConfig as $st => [$c,$l,$i]): ?>
                            <?php if ($st !== $room['status']): ?>
                            <li>
                                <form method="POST">
                                    <input type="hidden" name="update_room_status" value="1">
                                    <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $st; ?>">
                                    <button type="submit" class="dropdown-item text-<?php echo $c; ?> small">
                                        <i class="bi bi-<?php echo $i; ?> me-1"></i><?php echo $l; ?>
                                    </button>
                                </form>
                            </li>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 px-4 pb-3">
                <small class="text-muted" style="font-size:10px;">
                    <i class="bi bi-clock me-1"></i>
                    Ngày tạo: <?php echo $room['created_at'] ? date('d/m/Y', strtotime($room['created_at'])) : 'N/A'; ?>
                </small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-center mt-4">
    <nav><ul class="pagination pagination-sm gap-1">
        <?php for ($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
        <li class="page-item <?php echo $p===$page?'active':''; ?>">
            <a class="page-link rounded" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$p])); ?>"><?php echo $p; ?></a>
        </li>
        <?php endfor; ?>
    </ul></nav>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
