<?php
/**
 * UniDorm – Admin: Login Logs (Lich su dang nhap)
 * path: views/admin/login_logs.php
 */
$pageTitle   = 'Lịch sử đăng nhập';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => BASE_URL . '/dashboard'],
    ['label' => 'Lịch sử đăng nhập', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Filters ──────────────────────────────────────────────────
$search  = trim($_GET['q'] ?? '');
$roleF   = $_GET['role'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// ── Build WHERE ──────────────────────────────────────────────
$where   = [];
$params  = [];
$types   = '';

if ($search !== '') {
    $where[]  = "(u.fullname LIKE ? OR u.student_code LIKE ? OR u.username LIKE ?)";
    $like     = "%{$search}%";
    $params  = array_merge($params, [$like, $like, $like]);
    $types  .= 'sss';
}
if ($roleF !== '') {
    $where[]  = "u.role = ?";
    $params[] = $roleF;
    $types  .= 's';
}
if ($dateFrom !== '') {
    $where[]  = "ll.login_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
    $types  .= 's';
}
if ($dateTo !== '') {
    $where[]  = "ll.login_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
    $types  .= 's';
}

$whereSQL = $where ? implode(' AND ', $where) : '1=1';

// ── Stats ────────────────────────────────────────────────────
$statsTotal = $conn->query("SELECT COUNT(*) as c FROM login_logs")->fetch_assoc()['c'] ?? 0;
$statsToday = $conn->query("SELECT COUNT(*) as c FROM login_logs WHERE DATE(login_at) = CURDATE()")->fetch_assoc()['c'] ?? 0;
$statsUniqueToday = $conn->query("SELECT COUNT(DISTINCT user_id) as c FROM login_logs WHERE DATE(login_at) = CURDATE()")->fetch_assoc()['c'] ?? 0;

// ── Data ─────────────────────────────────────────────────────
$data = $conn->prepare("
    SELECT ll.id, ll.ip_address, ll.user_agent, ll.login_at,
           u.fullname, u.student_code, u.username, u.role
    FROM login_logs ll
    JOIN users u ON ll.user_id = u.user_id
    WHERE $whereSQL
    ORDER BY ll.login_at DESC
    LIMIT ? OFFSET ?
");
$types .= 'ii';
$params = array_merge($params, [$perPage, $offset]);
$data->bind_param($types, ...$params);
$data->execute();
$logs = $data->get_result()->fetch_all(MYSQLI_ASSOC);

// Total count for pagination
$cntStmt = $conn->prepare("SELECT COUNT(*) as c FROM login_logs ll JOIN users u ON ll.user_id = u.user_id WHERE $whereSQL");
if ($types !== 'ii') {
    $cntTypes = substr($types, 0, -2);
    $cntParams = array_slice($params, 0, -2);
    $cntStmt->bind_param($cntTypes, ...$cntParams);
}
$cntStmt->execute();
$cntTotal = $cntStmt->get_result()->fetch_assoc()['c'] ?? 0;
$totalPages = max(1, ceil($cntTotal / $perPage));

// ── Detect browser from user_agent ───────────────────────────
function detectBrowser(string $ua): string {
    if (preg_match('/Edg\/(\d+)/', $ua, $m))       return 'Edge ' . $m[1];
    if (preg_match('/Chrome\/(\d+)/', $ua, $m))     return 'Chrome ' . $m[1];
    if (preg_match('/Firefox\/(\d+)/', $ua, $m))    return 'Firefox ' . $m[1];
    if (preg_match('/Version\/(\d+).*Safari/', $ua, $m)) return 'Safari ' . $m[1];
    if (preg_match('/OPR\/(\d+)/', $ua, $m))        return 'Opera ' . $m[1];
    return 'Trình duyệt khác';
}

function detectOS(string $ua): string {
    if (preg_match('/Windows NT 10/', $ua))    return 'Windows 10/11';
    if (preg_match('/Windows NT 6.3/', $ua))   return 'Windows 8.1';
    if (preg_match('/Windows NT 6.1/', $ua))   return 'Windows 7';
    if (preg_match('/Mac OS X/', $ua))         return 'macOS';
    if (preg_match('/Linux/', $ua))            return 'Linux';
    if (preg_match('/Android/', $ua))          return 'Android';
    if (preg_match('/iPhone|iPad/', $ua))      return 'iOS';
    return 'Không xác định';
}
?>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Tổng lượt đăng nhập', $statsTotal, 'primary', 'box-arrow-in-right'],
        ['Đăng nhập hôm nay',   $statsToday, 'success', 'clock-history'],
        ['User hoạt động hôm nay', $statsUniqueToday, 'info', 'person-check-fill'],
    ] as [$label, $count, $color, $icon]): ?>
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> rounded-3 p-2 flex-shrink-0">
                    <i class="bi bi-<?php echo $icon; ?> fs-4"></i>
                </div>
                <div><h4 class="fw-black mb-0"><?php echo number_format($count); ?></h4><small class="text-muted"><?php echo $label; ?></small></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter bar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-3">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Tìm kiếm</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 bg-light"
                           placeholder="Họ tên, MSSV, username..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Vai trò</label>
                <select name="role" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="admin"   <?php echo $roleF==='admin'?'selected':''; ?>>Admin</option>
                    <option value="student" <?php echo $roleF==='student'?'selected':''; ?>>Sinh viên</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Từ ngày</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Đến ngày</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Lọc</button>
                <a href="<?php echo BASE_URL; ?>/login_logs" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Log table -->
<div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-header bg-white border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="bi bi-shield-lock-fill text-secondary me-2"></i>Lịch sử đăng nhập</h6>
        <span class="badge bg-secondary bg-opacity-75"><?php echo number_format($cntTotal); ?> bản ghi</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox fs-2 text-muted d-block mb-2"></i>
            <p class="text-muted small mb-0">Không có bản ghi nào<?php echo ($search || $roleF || $dateFrom || $dateTo) ? ' khớp bộ lọc.' : '.'; ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3" style="width:50px;">#</th>
                        <th class="py-3">Người dùng</th>
                        <th class="py-3">Vai trò</th>
                        <th class="py-3">Thời gian</th>
                        <th class="py-3">Địa chỉ IP</th>
                        <th class="py-3">Trình duyệt / OS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $i => $log): ?>
                    <tr>
                        <td class="ps-4 text-muted"><?php echo $offset + $i + 1; ?></td>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($log['fullname']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($log['student_code'] ?: $log['username']); ?></small>
                        </td>
                        <td>
                            <?php if ($log['role'] === 'admin'): ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger">Admin</span>
                            <?php else: ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary">Sinh viên</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?php echo date('d/m/Y', strtotime($log['login_at'])); ?></div>
                            <small class="text-muted"><?php echo date('H:i:s', strtotime($log['login_at'])); ?></small>
                        </td>
                        <td><code class="small"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></code></td>
                        <td>
                            <div class="small"><?php echo detectBrowser($log['user_agent'] ?? ''); ?></div>
                            <small class="text-muted"><?php echo detectOS($log['user_agent'] ?? ''); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-between align-items-center mt-3">
    <small class="text-muted">
        Hiển thị <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $cntTotal); ?> trong <?php echo number_format($cntTotal); ?> bản ghi
    </small>
    <nav>
        <ul class="pagination pagination-sm gap-1 mb-0">
            <?php
            $qs = $_GET;
            for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++):
                $qs['page'] = $p;
            ?>
            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                <a class="page-link rounded" href="?<?php echo http_build_query($qs); ?>"><?php echo $p; ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
