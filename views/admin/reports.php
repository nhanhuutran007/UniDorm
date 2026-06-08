<?php
/**
 * UniDorm – Admin: Thống kê & Báo cáo (reports.php)
 */
$pageTitle   = 'Thống kê & Báo cáo';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => BASE_URL . '/dashboard'],
    ['label' => 'Thống kê & Báo cáo', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

// ─── Aggregated stats ────────────────────────────────────────────────
$stats = [];
// Sinh viên
$stats['students_total']   = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE role='student'")->fetch_assoc()['c'];
$stats['students_active']  = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE role='student' AND status='active'")->fetch_assoc()['c'];
$stats['students_pending'] = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE role='student' AND status='pending'")->fetch_assoc()['c'];
// Phòng
$stats['rooms_total']   = (int)$conn->query("SELECT COUNT(*) as c FROM rooms")->fetch_assoc()['c'];
$stats['rooms_avail']   = (int)$conn->query("SELECT COUNT(*) as c FROM rooms WHERE status='available'")->fetch_assoc()['c'];
$stats['rooms_full']    = (int)$conn->query("SELECT COUNT(*) as c FROM rooms WHERE status='full'")->fetch_assoc()['c'];
$stats['rooms_maint']   = (int)$conn->query("SELECT COUNT(*) as c FROM rooms WHERE status='maintenance'")->fetch_assoc()['c'];
// Thiết bị & báo cáo
$stats['reports_pending']    = (int)$conn->query("SELECT COUNT(*) as c FROM device_reports WHERE status='pending'")->fetch_assoc()['c'];
$stats['reports_inprogress'] = (int)$conn->query("SELECT COUNT(*) as c FROM device_reports WHERE status='in_progress'")->fetch_assoc()['c'];
$stats['reports_resolved']   = (int)$conn->query("SELECT COUNT(*) as c FROM device_reports WHERE status='resolved'")->fetch_assoc()['c'];
$stats['reports_total']      = (int)$conn->query("SELECT COUNT(*) as c FROM device_reports")->fetch_assoc()['c'];
// Sĩ số theo lầu
$floorOccupancy = $conn->query("
    SELECT f.floor_number, b.name as bname,
           COUNT(u.user_id) as students,
           SUM(r.max_capacity) as capacity
    FROM floors f
    JOIN buildings b ON f.building_id = b.id
    LEFT JOIN rooms r ON r.floor_id = f.id
    LEFT JOIN beds bd ON bd.room_id = r.id
    LEFT JOIN users u ON u.bed_id = bd.id AND u.status='active' AND u.role='student'
    GROUP BY f.id ORDER BY f.floor_number ASC
")->fetch_all(MYSQLI_ASSOC);
// Quê quán top
$hometops = $conn->query("
    SELECT hometown, COUNT(*) as cnt FROM users
    WHERE role='student' AND hometown IS NOT NULL AND hometown != ''
    GROUP BY hometown ORDER BY cnt DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);
// Báo cáo hỏng theo tháng (6 tháng gần nhất)
$monthlyReports = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
           COUNT(*) as cnt
    FROM device_reports
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month ORDER BY month ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Row 1: Key metrics -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Tổng SV ở KTX', $stats['students_total'],   'primary', 'people-fill'],
        ['SV đang hoạt động', $stats['students_active'], 'success', 'person-check-fill'],
        ['Phòng còn chỗ',  $stats['rooms_avail'],      'info',    'door-open-fill'],
        ['BC hỏng chờ xử lý', $stats['reports_pending'], 'warning', 'exclamation-triangle-fill'],
    ] as [$label,$val,$color,$icon]): ?>
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

<div class="row g-4">
    <!-- Chart 1: Sĩ số theo lầu (bar) -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100" style="border-radius:14px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-1">Sĩ số theo lầu</h6>
                <p class="text-muted small mb-4">So sánh số sinh viên hiện tại vs sức chứa</p>
                <div style="height: 250px;">
                    <canvas id="floorChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart 2: Trạng thái phòng (doughnut) -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100" style="border-radius:14px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-1">Trạng thái phòng</h6>
                <p class="text-muted small mb-4">Phân bổ <?php echo $stats['rooms_total']; ?> phòng</p>
                <div style="height: 250px;">
                    <canvas id="roomPieChart"></canvas>
                </div>
                <div class="d-flex justify-content-center gap-3 mt-3 flex-wrap">
                    <?php foreach ([['Còn chỗ',$stats['rooms_avail'],'#22c55e'],['Đầy',$stats['rooms_full'],'#f59e0b'],['Bảo trì',$stats['rooms_maint'],'#ef4444']] as [$l,$v,$c]): ?>
                    <div class="text-center">
                        <div class="fw-bold" style="color:<?php echo $c; ?>;"><?php echo $v; ?></div>
                        <small class="text-muted"><?php echo $l; ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Quê quán + Báo cáo trạng thái -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius:14px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Top quê quán sinh viên</h6>
                <?php $maxHt = max(array_column($hometops ?: [['cnt'=>1]], 'cnt')); ?>
                <?php foreach ($hometops as $ht): $pct = round($ht['cnt']/$maxHt*100); ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted"><?php echo htmlspecialchars($ht['hometown']); ?></small>
                        <small class="fw-semibold"><?php echo $ht['cnt']; ?> SV</small>
                    </div>
                    <div class="progress" style="height:6px; border-radius:4px;">
                        <div class="progress-bar bg-primary" style="width:<?php echo $pct; ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($hometops)): ?>
                <p class="text-muted small text-center py-3">Chưa có dữ liệu</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Báo cáo hỏng theo tháng -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius:14px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-1">Báo cáo hỏng theo tháng</h6>
                <p class="text-muted small mb-4">6 tháng gần nhất</p>
                <div style="height: 200px;">
                    <canvas id="reportLineChart"></canvas>
                </div>

                <div class="row g-2 mt-3 text-center">
                    <?php foreach ([
                        ['Chờ xử lý', $stats['reports_pending'],    'warning'],
                        ['Đang xử lý', $stats['reports_inprogress'],'info'],
                        ['Đã xong',    $stats['reports_resolved'],    'success'],
                    ] as [$l,$v,$c]): ?>
                    <div class="col-4">
                        <div class="bg-<?php echo $c; ?> bg-opacity-10 rounded-3 py-2">
                            <div class="fw-bold text-<?php echo $c; ?>"><?php echo $v; ?></div>
                            <small class="text-muted" style="font-size:10px;"><?php echo $l; ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN if not already loaded -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const floorLabels   = <?php echo json_encode(array_map(fn($f)=>'Lầu '.$f['floor_number'], $floorOccupancy)); ?>;
const floorStudents = <?php echo json_encode(array_map(fn($f)=>(int)$f['students'], $floorOccupancy)); ?>;
const floorCapacity = <?php echo json_encode(array_map(fn($f)=>(int)$f['capacity'], $floorOccupancy)); ?>;

new Chart(document.getElementById('floorChart'), {
    type: 'bar',
    data: {
        labels: floorLabels,
        datasets: [
            { label: 'Sinh viên', data: floorStudents, backgroundColor: 'rgba(37,99,235,.7)', borderRadius:4 },
            { label: 'Sức chứa', data: floorCapacity, backgroundColor: 'rgba(209,213,219,.5)', borderRadius:4 },
        ]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false,
        plugins: { 
            legend: { 
                position: 'top',
                labels: { 
                    boxWidth: 15,
                    padding: 10,
                    font: { size: 12 }
                }
            }
        }, 
        scales: { 
            y: { 
                beginAtZero: true,
                ticks: { font: { size: 11 } }
            },
            x: { 
                ticks: { font: { size: 11 } }
            }
        } 
    }
});

new Chart(document.getElementById('roomPieChart'), {
    type: 'doughnut',
    data: {
        labels: ['Còn chỗ', 'Đầy', 'Bảo trì'],
        datasets: [{ data: [<?php echo $stats['rooms_avail'].','.$stats['rooms_full'].','.$stats['rooms_maint']; ?>],
                     backgroundColor: ['#22c55e','#f59e0b','#ef4444'], borderWidth:0, hoverOffset:6 }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false,
        plugins: { 
            legend: { display: false }
        }, 
        cutout: '70%' 
    }
});

const monthlyLabels = <?php echo json_encode(array_map(fn($r)=>$r['month'], $monthlyReports)); ?>;
const monthlyCounts = <?php echo json_encode(array_map(fn($r)=>(int)$r['cnt'], $monthlyReports)); ?>;
new Chart(document.getElementById('reportLineChart'), {
    type: 'line',
    data: {
        labels: monthlyLabels,
        datasets: [{ label: 'Số báo cáo', data: monthlyCounts,
                     borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,.1)',
                     fill:true, tension:.4, pointRadius:4 }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false,
        plugins: { 
            legend: { display: false }
        }, 
        scales: { 
            y: { 
                beginAtZero: true,
                ticks: { 
                    stepSize: 1,
                    font: { size: 11 }
                }
            },
            x: { 
                ticks: { font: { size: 11 } }
            }
        } 
    }
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
