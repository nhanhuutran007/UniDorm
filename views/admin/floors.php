<?php
/**
 * UniDorm – Admin: Quản lý lầu (floors.php)
 */
$pageTitle   = 'Quản lý lầu';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => BASE_URL . '/dashboard'],
    ['label' => 'Quản lý lầu', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

$successMsg = $errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_floor') {
    $buildingId  = (int)($_POST['building_id'] ?? 1); 
    $floorNumber = (int)($_POST['floor_number'] ?? 0);

    if ($floorNumber < 1 || $floorNumber > 25) {
        $errorMsg = "Số lầu không hợp lệ (1-25).";
    } else {
        $chk = $conn->prepare("SELECT id FROM floors WHERE building_id = ? AND floor_number = ?");
        $chk->bind_param('ii', $buildingId, $floorNumber);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $errorMsg = "Lầu $floorNumber đã tồn tại trong tòa nhà này.";
        } else {
            $conn->begin_transaction();
            try {
                // Insert floor
                $ins = $conn->prepare("INSERT INTO floors (building_id, floor_number) VALUES (?, ?)");
                $ins->bind_param('ii', $buildingId, $floorNumber);
                $ins->execute();
                $floorId = $conn->insert_id;

                // Determine beds per room based on rules
                if ($floorNumber <= 11) {
                    $bedsPerRoom = 6;
                } elseif ($floorNumber <= 16) {
                    $bedsPerRoom = 4;
                } else {
                    $bedsPerRoom = 2;
                }

                $roomsPerFloor = 14;
                $roomStmt = $conn->prepare("INSERT INTO rooms (floor_id, room_code, max_capacity, status) VALUES (?, ?, ?, 'available')");
                $bedStmt  = $conn->prepare("INSERT INTO beds (room_id, bed_label, is_occupied) VALUES (?, ?, 0)");

                // Generate 14 rooms and beds
                for ($i = 1; $i <= $roomsPerFloor; $i++) {
                    $roomCode = 'L.' . sprintf('%02d', $floorNumber) . sprintf('%02d', $i);
                    $roomStmt->bind_param('isi', $floorId, $roomCode, $bedsPerRoom);
                    $roomStmt->execute();
                    $roomId = $conn->insert_id;

                    for ($j = 1; $j <= $bedsPerRoom; $j++) {
                        $bedLabel = 'G' . $j;
                        $bedStmt->bind_param('is', $roomId, $bedLabel);
                        $bedStmt->execute();
                    }
                }

                $conn->commit();
                $successMsg = "Đã khởi tạo thành công Lầu $floorNumber cùng 14 phòng và " . (14 * $bedsPerRoom) . " giường (chuẩn $bedsPerRoom giường/phòng).";
            } catch (Exception $e) {
                $conn->rollback();
                $errorMsg = "Lỗi hệ thống khi tạo lầu: " . $e->getMessage();
            }
        }
    }
}
$buildings = $conn->query("SELECT * FROM buildings")->fetch_all(MYSQLI_ASSOC);

// Stats tổng hợp theo lầu (đã sửa lỗi đếm lặp và tính toán trạng thái phòng chính xác)
$floorsData = $conn->query("
    SELECT f.id, f.floor_number, b.name as building_name,
           COUNT(DISTINCT r.id) as total_rooms,
           SUM(CASE WHEN r.status = 'maintenance' THEN 1 ELSE 0 END) as maint_rooms,
           SUM(CASE WHEN (r.status = 'full' OR (r.status = 'available' AND count_active >= r.max_capacity)) THEN 1 ELSE 0 END) as full_rooms,
           SUM(CASE WHEN (r.status = 'available' AND count_active < r.max_capacity) THEN 1 ELSE 0 END) as avail_rooms,
           SUM(r.max_capacity) as total_capacity,
           SUM(count_active) as current_students
    FROM floors f
    JOIN buildings b ON f.building_id = b.id
    LEFT JOIN (
        SELECT r.id, r.floor_id, r.status, r.max_capacity, COUNT(u.user_id) as count_active
        FROM rooms r
        LEFT JOIN beds bd ON bd.room_id = r.id
        LEFT JOIN users u ON u.bed_id = bd.id AND u.status IN ('active', 'pending')
        GROUP BY r.id
    ) r ON r.floor_id = f.id
    GROUP BY f.id, f.floor_number, b.name
    ORDER BY b.name ASC, f.floor_number ASC
")->fetch_all(MYSQLI_ASSOC);

$totalFloors    = count($floorsData);
$totalRooms     = array_sum(array_column($floorsData, 'total_rooms'));
$totalStudents  = array_sum(array_column($floorsData, 'current_students'));
$totalCapacity  = array_sum(array_column($floorsData, 'total_capacity'));
?>

<?php if ($successMsg): ?>
<div class="alert alert-success alert-dismissible fade show mb-4 rounded-3" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i><?php echo $successMsg; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Modal Thêm Lầu -->
<div class="modal fade" id="addFloorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow" style="border-radius:14px;">
            <input type="hidden" name="action" value="add_floor">
            <div class="modal-header border-0 bg-light rounded-top-4 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-layers text-primary me-2"></i>Thêm Lầu Mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="alert alert-info border-0 bg-info bg-opacity-10 small mb-4">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    Hệ thống sẽ <b>tự động sinh ra 14 phòng</b> cho lầu được tạo:
                    <ul class="mb-0 mt-1">
                        <li>Từ lầu 1 đến 11: <b>6 giường/phòng</b></li>
                        <li>Từ lầu 12 đến 16: <b>4 giường/phòng</b></li>
                        <li>Từ lầu 17 trở lên: <b>2 giường/phòng</b></li>
                    </ul>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Thêm vào Tòa nhà</label>
                    <select name="building_id" class="form-select rounded-3" required>
                        <?php foreach($buildings as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-muted">Số lầu khởi tạo</label>
                    <input type="number" name="floor_number" class="form-control rounded-3" min="1" max="25" placeholder="VD: 8" required>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-primary rounded-3 px-4" onclick="this.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\'></span>Đang tạo...'; this.closest('form').submit(); this.disabled=true;">Tiến hành Khởi tạo</button>
            </div>
        </form>
    </div>
</div>

<?php if ($errorMsg): ?>
<div class="alert alert-danger alert-dismissible fade show mb-4 rounded-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $errorMsg; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold text-dark mb-0">Tổng quan Quản lý Lầu</h5>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addFloorModal">
        <i class="bi bi-plus-lg me-1"></i>Thêm Lầu Mới
    </button>
</div>

<!-- Tổng quan -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Tổng số lầu', $totalFloors,    'primary', 'layers-fill'],
        ['Tổng số phòng', $totalRooms,   'info',    'door-open-fill'],
        ['Sinh viên đang ở', $totalStudents, 'success', 'people-fill'],
        ['Tổng chỗ hiện có', $totalCapacity, 'warning', 'database-fill'],
    ] as [$label, $val, $color, $icon]): ?>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> rounded-3 p-2 flex-shrink-0">
                    <i class="bi bi-<?php echo $icon; ?> fs-4"></i>
                </div>
                <div><h4 class="fw-black mb-0"><?php echo $val; ?></h4><small class="text-muted"><?php echo $label; ?></small></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Danh sách lầu dạng accordion/card grid -->
<div class="row g-3">
    <?php foreach ($floorsData as $floor):
        $pct = $floor['total_capacity'] > 0 ? round($floor['current_students'] / $floor['total_capacity'] * 100) : 0;
        $barColor = $pct >= 95 ? 'danger' : ($pct >= 75 ? 'warning' : 'success');
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:14px; border-top:3px solid var(--bs-<?php echo $barColor; ?>)!important;">
            <div class="card-body p-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-bold mb-0">Lầu <?php echo $floor['floor_number']; ?></h5>
                        <small class="text-muted"><?php echo htmlspecialchars($floor['building_name']); ?></small>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/rooms?floor_id=<?php echo $floor['id']; ?>"
                       class="btn btn-sm btn-outline-primary" style="font-size:11px;">
                        <i class="bi bi-door-open me-1"></i>Xem phòng
                    </a>
                </div>

                <!-- Room breakdown -->
                <div class="row g-2 mb-3 text-center">
                    <div class="col-4">
                        <div class="bg-success bg-opacity-10 rounded-3 p-2">
                            <div class="fw-bold text-success"><?php echo $floor['avail_rooms']; ?></div>
                            <small class="text-muted" style="font-size:10px;">Có chỗ</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-warning bg-opacity-10 rounded-3 p-2">
                            <div class="fw-bold text-warning"><?php echo $floor['full_rooms']; ?></div>
                            <small class="text-muted" style="font-size:10px;">Đầy</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-danger bg-opacity-10 rounded-3 p-2">
                            <div class="fw-bold text-danger"><?php echo $floor['maint_rooms']; ?></div>
                            <small class="text-muted" style="font-size:10px;">Bảo trì</small>
                        </div>
                    </div>
                </div>

                <!-- Occupancy -->
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted">Sĩ số</small>
                        <small class="fw-semibold"><?php echo $floor['current_students']; ?> / <?php echo $floor['total_capacity']; ?></small>
                    </div>
                    <div class="progress" style="height:8px; border-radius:4px;">
                        <div class="progress-bar bg-<?php echo $barColor; ?>"
                             style="width:<?php echo $pct; ?>%; border-radius:4px; transition:width .4s;"></div>
                    </div>
                    <small class="text-<?php echo $barColor; ?> fw-semibold"><?php echo $pct; ?>% lấp đầy</small>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($floorsData)): ?>
<div class="card border-0 shadow-sm text-center py-5" style="border-radius:14px;">
    <i class="bi bi-layers fs-2 text-muted d-block mb-2"></i>
    <p class="text-muted small">Chưa có dữ liệu lầu nào. Hãy import seed.sql trước.</p>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
