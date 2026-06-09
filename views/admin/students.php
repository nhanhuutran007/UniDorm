<?php
/**
 * UniDorm – Admin: Quản lý sinh viên
 * path: views/admin/students.php
 */
$pageTitle   = 'Quản lý sinh viên';
$breadcrumbs = [
    ['label' => 'Dashboard',         'url' => BASE_URL . '/dashboard'],
    ['label' => 'Quản lý sinh viên', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/models/RoomModel.php';
require_once __DIR__ . '/../../app/models/UserModel.php';

$roomModel = new RoomModel($conn);
$userModel = new UserModel($conn);

// Lọc
$filterFloor  = $_GET['floor_id']   ?? '';
$filterRoom   = $_GET['room_code']  ?? '';
$filterStatus = $_GET['status']     ?? '';
$filterSearch = trim($_GET['q']     ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

// Build query
$where    = ["u.role = 'student'"];
$params   = [];
$types    = '';

if ($filterSearch) {
    $where[]  = "(u.fullname LIKE ? OR u.student_code LIKE ? OR u.phone_personal LIKE ?)";
    $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%";
    $types   .= 'sss';
}
if ($filterFloor) {
    $where[]  = "f.id = ?";
    $params[] = $filterFloor; $types .= 'i';
}
if ($filterRoom) {
    $where[]  = "r.room_code = ?";
    $params[] = $filterRoom; $types .= 's';
}
if ($filterStatus) {
    $where[]  = "u.status = ?";
    $params[] = $filterStatus; $types .= 's';
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Count total
$countSQL  = "SELECT COUNT(*) as total FROM users u
              LEFT JOIN beds b ON u.bed_id = b.id
              LEFT JOIN rooms r ON b.room_id = r.id
              LEFT JOIN floors f ON r.floor_id = f.id
              $whereSQL";
$stmtCount = $conn->prepare($countSQL);
if ($types) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalRows = $stmtCount->get_result()->fetch_assoc()['total'] ?? 0;
$totalPages = max(1, ceil($totalRows / $perPage));

// Fetch rows
$dataSQL = "SELECT u.user_id, u.student_code, u.fullname, u.phone_personal, u.phone_family,
                   u.hometown, u.status, u.is_room_leader, u.created_at,
                   r.room_code, b.bed_label, f.floor_number, bld.name as building_name
            FROM users u
            LEFT JOIN beds b ON u.bed_id = b.id
            LEFT JOIN rooms r ON b.room_id = r.id
            LEFT JOIN floors f ON r.floor_id = f.id
            LEFT JOIN buildings bld ON f.building_id = bld.id
            $whereSQL
            ORDER BY r.room_code ASC, b.bed_label ASC
            LIMIT ? OFFSET ?";
$stmtData = $conn->prepare($dataSQL);
$allTypes  = $types . 'ii';
$allParams = array_merge($params, [$perPage, $offset]);
$stmtData->bind_param($allTypes, ...$allParams);
$stmtData->execute();
$students = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);

// Meta cho select filters
$buildings = $roomModel->getBuildings();
$floors    = !empty($buildings) ? $roomModel->getFloorsByBuilding($buildings[0]['id']) : [];
if ($filterFloor) $floors = $roomModel->getFloorsByBuilding(
    (function() use ($conn, $filterFloor) {
        $r = $conn->query("SELECT building_id FROM floors WHERE id = $filterFloor");
        return $r->fetch_assoc()['building_id'] ?? 1;
    })()
);

// Lấy tất cả floors để filter
$allFloors = $conn->query("SELECT f.id, f.floor_number, b.name as bname FROM floors f JOIN buildings b ON f.building_id = b.id ORDER BY b.name, f.floor_number");
$allFloors = $allFloors->fetch_all(MYSQLI_ASSOC);

$statusMap = ['active'=>['success','Hoạt động'],'pending'=>['warning','Chờ kích hoạt'],'inactive'=>['secondary','Không hoạt động'],'banned'=>['danger','Bị khoá']];
?>

<style>
.stt-cell .form-check-input { display: none; margin: 0 auto; cursor: pointer; }
.stt-cell:hover .stt-text { display: none; }
.stt-cell:hover .form-check-input { display: block; }
.stt-cell.checked .stt-text { display: none; }
.stt-cell.checked .form-check-input { display: block; }
</style>

<!-- Toolbar -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
    <div class="card-body p-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted mb-1">Tìm kiếm</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 bg-light"
                           placeholder="Họ tên, MSSV, số điện thoại..."
                           value="<?php echo htmlspecialchars($filterSearch); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Lầu</label>
                <select name="floor_id" class="form-select">
                    <option value="">Tất cả lầu</option>
                    <?php foreach ($allFloors as $f): ?>
                    <option value="<?php echo $f['id']; ?>" <?php echo $filterFloor == $f['id'] ? 'selected' : ''; ?>>
                        <?php echo $f['bname']; ?> – Lầu <?php echo $f['floor_number']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Phòng</label>
                <input type="text" name="room_code" class="form-control"
                       placeholder="VD: L.0801" value="<?php echo htmlspecialchars($filterRoom); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="">Tất cả</option>
                    <?php foreach ($statusMap as $v => [$c, $l]): ?>
                    <option value="<?php echo $v; ?>" <?php echo $filterStatus === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Lọc</button>
                <a href="<?php echo BASE_URL; ?>/students" class="btn btn-outline-secondary" title="Xóa bộ lọc"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Header hành động -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="mb-0 text-muted small">Tìm thấy <strong><?php echo $totalRows; ?></strong> sinh viên</p>
    <div class="d-flex gap-2">
        <button id="bulkDeleteBtn" class="btn btn-danger btn-sm d-none align-items-center gap-1" onclick="confirmBulkDelete()">
            <i class="bi bi-trash-fill"></i> Xóa đã chọn (<span id="selectedCount">0</span>)
        </button>
        <a href="<?php echo BASE_URL; ?>/newuser" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-person-plus-fill"></i> Thêm sinh viên
        </a>
        <a href="<?php echo BASE_URL; ?>/import" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-upload"></i> Import CSV
        </a>
    </div>
</div>

<!-- Bảng sinh viên -->
<div class="card border-0 shadow-sm" style="border-radius:14px;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3 small text-center" style="width:40px;">
                            <input type="checkbox" class="form-check-input" id="checkAll" onchange="toggleAll(this)" style="cursor: pointer;" title="Chọn tất cả">
                        </th>
                        <th class="py-3 small">Họ tên & MSSV</th>
                        <th class="py-3 small">Phòng / Giường</th>
                        <th class="py-3 small">SĐT Cá nhân</th>
                        <th class="py-3 small">SĐT Gia đình</th>
                        <th class="py-3 small">Hộ khẩu</th>
                        <th class="py-3 small text-center">Trạng thái</th>
                        <th class="py-3 small text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            Không tìm thấy sinh viên nào.
                            <?php if ($filterSearch || $filterFloor || $filterRoom || $filterStatus): ?>
                            <a href="<?php echo BASE_URL; ?>/students" class="d-block mt-2 small">Xóa bộ lọc</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($students as $i => $sv):
                        [$sc, $sl] = $statusMap[$sv['status']] ?? ['secondary', '?'];
                    ?>
                    <tr>
                        <td class="ps-4 py-3 text-muted small text-center stt-cell" style="vertical-align: middle;">
                            <span class="stt-text"><?php echo $offset + $i + 1; ?></span>
                            <input type="checkbox" class="form-check-input student-checkbox" value="<?php echo $sv['user_id']; ?>" onchange="updateRowState(this)">
                        </td>
                        <td class="py-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width:36px;height:36px;font-size:13px;">
                                    <?php echo mb_substr($sv['fullname'], 0, 1, 'UTF-8'); ?>
                                </div>
                                <div>
                                    <p class="mb-0 fw-semibold small text-dark">
                                        <?php echo htmlspecialchars($sv['fullname']); ?>
                                        <?php if ($sv['is_room_leader']): ?>
                                        <span class="badge bg-primary bg-opacity-75 ms-1" style="font-size:9px;">TP</span>
                                        <?php endif; ?>
                                    </p>
                                    <small class="text-muted"><?php echo htmlspecialchars($sv['student_code'] ?? '—'); ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="py-3">
                            <?php if ($sv['room_code']): ?>
                            <code class="bg-light text-dark px-2 py-1 rounded small">
                                <?php echo $sv['room_code']; ?> / <?php echo $sv['bed_label']; ?>
                            </code>
                            <div class="text-muted" style="font-size:10px;">Lầu <?php echo $sv['floor_number']; ?></div>
                            <?php else: ?>
                            <span class="text-muted small">Chưa xếp phòng</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 small text-muted"><?php echo htmlspecialchars($sv['phone_personal'] ?? '—'); ?></td>
                        <td class="py-3 small text-muted"><?php echo htmlspecialchars($sv['phone_family'] ?? '—'); ?></td>
                        <td class="py-3 small text-muted" style="max-width:140px;">
                            <span title="<?php echo htmlspecialchars($sv['hometown'] ?? ''); ?>">
                                <?php echo htmlspecialchars(mb_strimwidth($sv['hometown'] ?? '—', 0, 20, '...')); ?>
                            </span>
                        </td>
                        <td class="py-3 text-center">
                            <span class="badge bg-<?php echo $sc; ?> bg-opacity-75" style="font-size:10px;"><?php echo $sl; ?></span>
                        </td>
                        <td class="py-3 text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <a href="<?php echo BASE_URL; ?>/updateuser?id=<?php echo $sv['user_id']; ?>"
                                   class="btn btn-sm btn-outline-primary p-1" title="Sửa" style="width:28px;height:28px;line-height:1;">
                                    <i class="bi bi-pencil-fill" style="font-size:11px;"></i>
                                </a>
                                <a href="<?php echo BASE_URL; ?>/chat?with=<?php echo $sv['user_id']; ?>"
                                   class="btn btn-sm btn-outline-success p-1" title="Nhắn tin" style="width:28px;height:28px;line-height:1;">
                                    <i class="bi bi-chat-dots-fill" style="font-size:11px;"></i>
                                </a>
                                <button type="button" onclick="confirmDelete(<?php echo $sv['user_id']; ?>, '<?php echo htmlspecialchars($sv['fullname']); ?>')"
                                        class="btn btn-sm btn-outline-danger p-1" title="Xoá" style="width:28px;height:28px;line-height:1;">
                                    <i class="bi bi-trash-fill" style="font-size:11px;"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white border-top-0 py-3 px-4 d-flex justify-content-between align-items-center" style="border-radius:0 0 14px 14px;">
        <small class="text-muted">Trang <?php echo $page; ?> / <?php echo $totalPages; ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0 gap-1">
                <?php for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++): ?>
                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                    <a class="page-link rounded" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$p])); ?>"><?php echo $p; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Confirm delete modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:14px;">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">Xác nhận xóa tài khoản</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-0">Bạn có chắc muốn xóa tài khoản của <strong id="deleteUserName"></strong>?
                <br><small class="text-danger">Hành động này không thể hoàn tác.</small></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-light" data-bs-dismiss="modal">Hủy</button>
                <button id="deleteConfirmBtn" class="btn btn-danger" onclick="executeDelete()">Xác nhận xóa</button>
            </div>
        </div>
    </div>
</div>

<script>
let deleteUserId = null;
let deleteModalObj = null;

function confirmDelete(id, name) {
    deleteUserId = id;
    document.getElementById('deleteUserName').textContent = name;
    if (!deleteModalObj) {
        deleteModalObj = new bootstrap.Modal(document.getElementById('deleteModal'));
    }
    deleteModalObj.show();
}

function executeDelete() {
    if (!deleteUserId) return;
    
    const btn = document.getElementById('deleteConfirmBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xóa...';

    fetch('<?php echo BASE_URL; ?>/api/delete_student.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: deleteUserId })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            location.reload();
        } else {
            alert(res.message || 'Lỗi khi xóa sinh viên');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    })
    .catch(err => {
        console.error(err);
        alert('Lỗi kết nối máy chủ');
        btn.disabled = false;
        btn.textContent = originalText;
    });
}

function updateRowState(checkbox) {
    if(checkbox.checked) {
        checkbox.closest('.stt-cell').classList.add('checked');
        checkbox.closest('tr').classList.add('table-light');
    } else {
        checkbox.closest('.stt-cell').classList.remove('checked');
        checkbox.closest('tr').classList.remove('table-light');
        document.getElementById('checkAll').checked = false;
    }
    updateBulkDeleteBtn();
}

function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = source.checked;
        updateRowState(cb);
    });
}

function updateBulkDeleteBtn() {
    const checked = document.querySelectorAll('.student-checkbox:checked');
    const btn = document.getElementById('bulkDeleteBtn');
    if(checked.length > 0) {
        btn.classList.remove('d-none');
        btn.classList.add('d-flex');
        document.getElementById('selectedCount').textContent = checked.length;
    } else {
        btn.classList.add('d-none');
        btn.classList.remove('d-flex');
    }
}

function confirmBulkDelete() {
    const checked = document.querySelectorAll('.student-checkbox:checked');
    if(checked.length === 0) return;
    const ids = Array.from(checked).map(cb => cb.value);
    
    if(confirm('Bạn có chắc chắn muốn xóa ' + ids.length + ' sinh viên đã chọn? Hành động này không thể hoàn tác.')) {
        const btn = document.getElementById('bulkDeleteBtn');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang xóa...';

        fetch('<?php echo BASE_URL; ?>/api/delete_student.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_ids: ids })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                location.reload();
            } else {
                alert(res.message || 'Lỗi khi xóa sinh viên');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        })
        .catch(err => {
            console.error(err);
            alert('Lỗi kết nối máy chủ');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    }
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
