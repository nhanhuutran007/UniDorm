<?php
/**
 * UniDorm – Admin: Danh sách tài khoản (userlists.php)
 * Hiển thị tất cả sinh viên (admin xem toàn bộ, có thể khoá/mở khoá)
 */
$pageTitle   = 'Danh sách tài khoản';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/UniDorm/views/admin/dashboard.php'],
    ['label' => 'Danh sách tài khoản', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$userId = (int)($_SESSION['user_id'] ?? 0);

// ── POST: toggle status ───────────────────────────────────────────────
$toast = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_status' && $targetId && $targetId !== $userId) {
        $cur = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
        $cur->bind_param('i', $targetId);
        $cur->execute();
        $curStatus = $cur->get_result()->fetch_assoc()['status'] ?? '';
        $newStatus = $curStatus === 'active' ? 'inactive' : 'active';
        $upd = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $upd->bind_param('si', $newStatus, $targetId);
        $toast = $upd->execute() ? 'success:Đã cập nhật trạng thái tài khoản!' : 'error:Lỗi cập nhật';
    }
    if ($action === 'delete_user' && $targetId && $targetId !== $userId) {
        $del = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'student'");
        $del->bind_param('i', $targetId);
        $toast = $del->execute() ? 'success:Đã xoá tài khoản sinh viên!' : 'error:Không thể xoá tài khoản này';
    }
}

// ── Filters ───────────────────────────────────────────────────────────
$q       = trim($_GET['q']      ?? '');
$status  = trim($_GET['status'] ?? '');
$role    = trim($_GET['role']   ?? 'student');   // Mặc định sinh viên
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where = ["1=1"];
$params = [];
$types  = '';

if ($q) {
    $where[] = "(u.student_code LIKE ? OR u.fullname LIKE ? OR u.email LIKE ?)";
    $like = "%$q%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}
if ($status) {
    $where[] = "u.status = ?";
    $params[] = $status; $types .= 's';
}
if ($role) {
    $where[] = "u.role = ?";
    $params[] = $role; $types .= 's';
}
$whereSQL = implode(' AND ', $where);

// Count
$cnt  = $conn->prepare("SELECT COUNT(*) as c FROM users u WHERE $whereSQL");
if ($types) $cnt->bind_param($types, ...$params);
$cnt->execute();
$total      = $cnt->get_result()->fetch_assoc()['c'] ?? 0;
$totalPages = max(1, ceil($total / $perPage));

// Fetch
$data = $conn->prepare("
    SELECT u.user_id, u.student_code, u.fullname, u.email, u.status, u.role,
           u.phone_personal, u.is_room_leader, u.created_at,
           r.room_code, f.floor_number,
           aa.is_active as account_active
    FROM users u
    LEFT JOIN beds b  ON u.bed_id = b.id
    LEFT JOIN rooms r ON b.room_id = r.id
    LEFT JOIN floors f ON r.floor_id = f.id
    LEFT JOIN auth_accounts aa ON u.user_id = aa.user_id
    WHERE $whereSQL
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
");
$allTypes  = $types . 'ii';
$allParams = array_merge($params, [$perPage, $offset]);
$data->bind_param($allTypes, ...$allParams);
$data->execute();
$users = $data->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
$statsAll     = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='student'")->fetch_assoc()['c'] ?? 0;
$statsActive  = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='student' AND status='active'")->fetch_assoc()['c'] ?? 0;
$statsPending = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='student' AND status='pending'")->fetch_assoc()['c'] ?? 0;
?>

<?php if ($toast): [$toastType, $toastMsg] = explode(':', $toast, 2); ?>
<div id="pageToast" class="position-fixed top-0 end-0 p-3" style="z-index:9999;">
    <div class="toast align-items-center text-white bg-<?php echo $toastType === 'success' ? 'success' : 'danger'; ?> border-0 show" role="alert">
        <div class="d-flex">
            <div class="toast-body"><i class="bi bi-<?php echo $toastType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>-fill me-2"></i><?php echo htmlspecialchars($toastMsg); ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button>
        </div>
    </div>
</div>
<script>setTimeout(() => document.getElementById('pageToast')?.remove(), 4000);</script>
<?php endif; ?>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Tổng tài khoản SV', $statsAll,     'primary',   'people-fill',       ''],
        ['Đang hoạt động',    $statsActive,  'success',   'person-check-fill', 'active'],
        ['Chờ kích hoạt',     $statsPending, 'warning',   'person-slash-fill', 'pending'],
        ['Quản trị viên',     $conn->query("SELECT COUNT(*) as c FROM users WHERE role='admin'")->fetch_assoc()['c'], 'danger', 'shield-fill', ''],
    ] as [$label, $count, $color, $icon, $qs]): ?>
    <div class="col-6 col-lg-3">
        <a href="?role=<?php echo $qs ? 'student&status='.$qs : ($label==='Quản trị viên' ? 'admin' : 'student'); ?>" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> rounded-3 p-2 flex-shrink-0">
                    <i class="bi bi-<?php echo $icon; ?> fs-4"></i>
                </div>
                <div><h4 class="fw-black mb-0"><?php echo $count; ?></h4><small class="text-muted"><?php echo $label; ?></small></div>
            </div>
        </div></a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter bar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted mb-1">Tìm kiếm</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 bg-light"
                           placeholder="MSSV, họ tên, email..."
                           value="<?php echo htmlspecialchars($q); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Vai trò</label>
                <select name="role" class="form-select">
                    <option value="student" <?php echo $role==='student'?'selected':''; ?>>Sinh viên</option>
                    <option value="admin"   <?php echo $role==='admin'  ?'selected':''; ?>>Quản trị viên</option>
                    <option value=""                                                    >Tất cả</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="active"   <?php echo $status==='active'  ?'selected':''; ?>>Hoạt động</option>
                    <option value="pending"  <?php echo $status==='pending' ?'selected':''; ?>>Chờ kích hoạt</option>
                    <option value="inactive" <?php echo $status==='inactive'?'selected':''; ?>>Bị khoá</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Lọc</button>
                <a href="/UniDorm/views/admin/userlists.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
            </div>
            <div class="col-md-2 text-end">
                <a href="/UniDorm/views/admin/newuser.php" class="btn btn-success w-100">
                    <i class="bi bi-person-plus-fill me-1"></i>Thêm sinh viên
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
        <p class="mb-0 text-muted small">Tìm thấy <strong><?php echo $total; ?></strong> tài khoản</p>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle" style="font-size:13px;">
                <thead class="table-light">
                    <tr>
                        <th class="px-4">MSSV / Username</th>
                        <th>Họ và tên</th>
                        <th>Phòng / Giường</th>
                        <th>Trạng thái TK</th>
                        <th>Email</th>
                        <th class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">Không có tài khoản nào.</td></tr>
                    <?php else: ?>
                    <?php foreach ($users as $u):
                        $stMap = ['active'=>['success','Hoạt động'],'pending'=>['warning','Chờ kích hoạt'],'inactive'=>['danger','Bị khoá'],'banned'=>['dark','Đã khoá']];
                        [$stColor, $stLabel] = $stMap[$u['status']] ?? ['secondary','?'];
                        $isLeader = $u['is_room_leader'] == 1;
                    ?>
                    <tr>
                        <td class="px-4">
                            <code class="bg-light px-2 py-1 rounded" style="font-size:12px;">
                                <?php echo htmlspecialchars($u['student_code'] ?? $u['role']); ?>
                            </code>
                            <?php if ($isLeader): ?>
                            <span class="badge bg-info bg-opacity-75 ms-1" style="font-size:9px;">TP</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($u['fullname']); ?></div>
                            <small class="text-muted"><?php echo $u['role'] === 'admin' ? 'Quản trị viên' : 'Sinh viên'; ?></small>
                        </td>
                        <td>
                            <?php if ($u['room_code']): ?>
                            <code class="bg-light px-2 py-1 rounded" style="font-size:12px;"><?php echo $u['room_code']; ?></code>
                            <small class="text-muted d-block" style="font-size:10px;">Lầu <?php echo $u['floor_number']; ?></small>
                            <?php else: ?>
                            <span class="text-muted small fst-italic">Chưa có phòng</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $stColor; ?> bg-opacity-75"><?php echo $stLabel; ?></span>
                            <?php if (!$u['account_active']): ?>
                            <span class="badge bg-secondary bg-opacity-50 ms-1" style="font-size:9px;">No PW</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?php echo htmlspecialchars($u['email'] ?? '—'); ?></td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <a href="/UniDorm/views/admin/updateuser.php?id=<?php echo $u['user_id']; ?>"
                                   class="btn btn-sm btn-outline-primary" title="Chỉnh sửa" style="font-size:11px;">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($u['user_id'] != $userId): ?>
                                <!-- Toggle status -->
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-<?php echo $u['status']==='active'?'warning':'success'; ?>"
                                            title="<?php echo $u['status']==='active'?'Khoá':'Mở khoá'; ?>" style="font-size:11px;"
                                            onclick="return confirm('Bạn muốn thay đổi trạng thái tài khoản này?')">
                                        <i class="bi bi-<?php echo $u['status']==='active'?'lock':'unlock'; ?>"></i>
                                    </button>
                                </form>
                                <?php if ($u['role'] === 'student'): ?>
                                <!-- Delete -->
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:11px;"
                                            title="Xoá tài khoản" onclick="return confirm('Xoá tài khoản sinh viên <?php echo addslashes($u['fullname']); ?>?')">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-transparent border-0 d-flex justify-content-center py-3">
        <nav><ul class="pagination pagination-sm gap-1 mb-0">
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
