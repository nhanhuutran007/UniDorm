<?php
/**
 * UniDorm – Admin: Quản lý lầu (floors.php)
 */
$pageTitle   = 'Quản lý lầu';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/UniDorm/views/admin/dashboard.php'],
    ['label' => 'Quản lý lầu', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

// Stats tổng hợp theo lầu
$floorsData = $conn->query("
    SELECT f.id, f.floor_number, b.name as building_name,
           COUNT(r.id)                                   as total_rooms,
           SUM(CASE WHEN r.status='available' THEN 1 ELSE 0 END) as avail_rooms,
           SUM(CASE WHEN r.status='full'      THEN 1 ELSE 0 END) as full_rooms,
           SUM(CASE WHEN r.status='maintenance' THEN 1 ELSE 0 END) as maint_rooms,
           SUM(r.max_capacity)                           as total_capacity,
           COUNT(u.user_id)                              as current_students
    FROM floors f
    JOIN buildings b   ON f.building_id = b.id
    LEFT JOIN rooms r  ON r.floor_id = f.id
    LEFT JOIN beds bd  ON bd.room_id = r.id
    LEFT JOIN users u  ON u.bed_id = bd.id AND u.status = 'active'
    GROUP BY f.id, f.floor_number, b.name
    ORDER BY b.name ASC, f.floor_number ASC
")->fetch_all(MYSQLI_ASSOC);

$totalFloors    = count($floorsData);
$totalRooms     = array_sum(array_column($floorsData, 'total_rooms'));
$totalStudents  = array_sum(array_column($floorsData, 'current_students'));
$totalCapacity  = array_sum(array_column($floorsData, 'total_capacity'));
?>

<!-- Tổng quan -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Tổng số lầu', $totalFloors,    'primary', 'layers-fill'],
        ['Tổng số phòng', $totalRooms,   'info',    'door-open-fill'],
        ['Sinh viên đang ở', $totalStudents, 'success', 'people-fill'],
        ['Tổng sức chứa', $totalCapacity, 'warning', 'database-fill'],
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
                    <a href="/UniDorm/views/admin/rooms.php?floor_id=<?php echo $floor['id']; ?>"
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
