<?php
/**
 * UniDorm – Student: Xem thông báo
 * path: views/student/notifications.php
 */
$pageTitle   = 'Thông báo';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => BASE_URL . '/dashboard'],
    ['label' => 'Thông báo', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

// Đánh dấu tất cả đã đọc khi vào trang
$conn->prepare("UPDATE notifications SET is_read = 1 WHERE (target_user_id = ? OR target_user_id IS NULL) AND is_read = 0")
     ->bind_param("i", $userId) || true;
$stmtMark = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE (target_user_id = ? OR target_user_id IS NULL) AND is_read = 0");
$stmtMark->bind_param("i", $userId);
$stmtMark->execute();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;
$type    = $_GET['type'] ?? '';

$where  = ["(n.target_user_id = ? OR n.target_user_id IS NULL)"];
$params = [$userId];
$types  = 'i';

if ($type) {
    $where[]  = "n.type = ?";
    $params[] = $type;
    $types   .= 's';
}
$whereSQL = implode(' AND ', $where);

// Count
$cntStmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications n WHERE $whereSQL");
$cntStmt->bind_param($types, ...$params);
$cntStmt->execute();
$total      = $cntStmt->get_result()->fetch_assoc()['c'] ?? 0;
$totalPages = max(1, ceil($total / $perPage));

// Fetch
$dataStmt = $conn->prepare("
    SELECT n.id, n.title, n.message, n.type, n.is_read, n.created_at,
           u.fullname as sender_name
    FROM notifications n
    LEFT JOIN users u ON n.sender_id = u.user_id
    WHERE $whereSQL
    ORDER BY n.created_at DESC
    LIMIT ? OFFSET ?
");
$dataStmt->bind_param($types.'ii', ...array_merge($params, [$perPage, $offset]));
$dataStmt->execute();
$notifs = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$typeConfig = [
    'general'     => ['primary',   'bi-megaphone-fill',    'Thông báo chung'],
    'room'        => ['info',      'bi-door-open-fill',    'Phòng ở'],
    'maintenance' => ['warning',   'bi-tools',             'Bảo trì'],
    'system'      => ['secondary', 'bi-gear-fill',         'Hệ thống'],
    'message'     => ['success',   'bi-chat-dots-fill',    'Tin nhắn'],
];
?>

<!-- Filter tabs -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <a href="?" class="btn btn-sm <?php echo $type === '' ? 'btn-primary' : 'btn-outline-secondary'; ?> rounded-pill">
        Tất cả <span class="badge bg-white text-dark ms-1"><?php echo $total; ?></span>
    </a>
    <?php foreach ($typeConfig as $t => [$c, $ico, $l]): ?>
    <a href="?type=<?php echo $t; ?>" class="btn btn-sm <?php echo $type === $t ? "btn-$c" : 'btn-outline-secondary'; ?> rounded-pill">
        <i class="bi <?php echo $ico; ?> me-1"></i><?php echo $l; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Notifications List -->
<?php if (empty($notifs)): ?>
<div class="card border-0 shadow-sm text-center py-5" style="border-radius:14px;">
    <i class="bi bi-bell-slash fs-2 text-muted d-block mb-3"></i>
    <p class="text-muted mb-0">Không có thông báo nào.</p>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($notifs as $n):
        [$tc, $tico, $tl] = $typeConfig[$n['type']] ?? ['secondary','bi-info-circle','Khác'];
    ?>
    <div class="card border-0 shadow-sm" style="border-radius:12px; <?php echo !$n['is_read'] ? "border-left: 4px solid var(--bs-$tc, #0d6efd);" : ''; ?>">
        <div class="card-body p-4">
            <div class="d-flex gap-3">
                <!-- Icon -->
                <div class="flex-shrink-0">
                    <div class="bg-<?php echo $tc; ?> bg-opacity-10 text-<?php echo $tc; ?> rounded-3 d-flex align-items-center justify-content-center"
                         style="width:44px;height:44px;">
                        <i class="bi <?php echo $tico; ?> fs-5"></i>
                    </div>
                </div>
                <!-- Content -->
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($n['title']); ?></h6>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-<?php echo $tc; ?> bg-opacity-75 ms-2 flex-shrink-0" style="font-size:10px;"><?php echo $tl; ?></span>
                            <a href="<?php echo BASE_URL; ?>/api/notifications.php?delete=<?php echo $n['id']; ?>" class="btn btn-sm btn-link text-danger p-0 ms-2 text-decoration-none" title="Xóa thông báo" onclick="return confirm('Bạn có chắc chắn muốn xóa thông báo này?');"><i class="bi bi-trash"></i></a>
                        </div>
                    </div>
                    <p class="mb-2 text-muted small"><?php echo nl2br(htmlspecialchars($n['message'])); ?></p>
                    <div class="d-flex align-items-center gap-3">
                        <small class="text-muted">
                            <i class="bi bi-clock me-1"></i>
                            <?php echo date('d/m/Y H:i', strtotime($n['created_at'])); ?>
                        </small>
                        <small class="text-muted">
                            <i class="bi bi-person me-1"></i>
                            <?php echo $n['sender_name'] ? htmlspecialchars($n['sender_name']) : 'Hệ thống'; ?>
                        </small>
                    </div>
                </div>
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
