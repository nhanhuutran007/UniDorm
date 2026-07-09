<?php
/**
 * UniDorm – Admin: Thông báo (gửi và quản lý)
 * path: views/admin/notifications.php
 */
$pageTitle   = 'Thông báo';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => BASE_URL . '/dashboard'],
    ['label' => 'Thông báo', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/services/MailService.php';
$mailService = new MailService();

$successMsg = $errorMsg = '';

if (session_status() === PHP_SESSION_NONE) session_start();
$userId = (int)($_SESSION['user_id'] ?? 0);

// Kiểm tra quyền (sinh viên thăng cấp admin bị hạn chế gửi thông báo)
$currUsr = $conn->query("SELECT role, student_code FROM users WHERE user_id = $userId")->fetch_assoc();
$isPromotedAdmin = ($currUsr && $currUsr['role'] === 'admin' && $currUsr['student_code'] !== null && $currUsr['student_code'] !== 'admin');

// Xử lý gửi thông báo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notif'])) {
    $title        = trim($_POST['title'] ?? '');
    $message      = trim($_POST['message'] ?? '');
    $type         = $_POST['type'] ?? 'general';
    $targetUserId = !empty($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : null;

    if ($isPromotedAdmin) {
        $errorMsg = 'Tài khoản của bạn bị hạn chế quyền thực hiện chức năng này.';
    } elseif (empty($title) || empty($message)) {
        $errorMsg = 'Vui lòng nhập đầy đủ tiêu đề và nội dung.';
    } else {
        $stmt = $conn->prepare("INSERT INTO notifications (sender_id, target_user_id, title, message, type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iisss', $userId, $targetUserId, $title, $message, $type);
        if ($stmt->execute()) {
            $successMsg = $targetUserId
                ? "Đã gửi thông báo đến sinh viên được chọn."
                : "Đã gửi thông báo đến <strong>tất cả sinh viên</strong>.";

            // --- Gửi Email ---
            if ($targetUserId) {
                $uStmt = $conn->prepare("SELECT fullname, email, student_code FROM users WHERE user_id = ?");
                $uStmt->bind_param('i', $targetUserId);
                $uStmt->execute();
                $targetUser = $uStmt->get_result()->fetch_assoc();
                if ($targetUser) {
                    $targetEmail = $targetUser['email'] ?? ($targetUser['student_code'] . '@student.tdtu.edu.vn');
                    $mailService->sendNotification($targetEmail, $targetUser['fullname'], $title, $message);
                }
            } else {
                $allStudents = $conn->query("SELECT fullname, email, student_code FROM users WHERE role='student' AND status='active'");
                while($s = $allStudents->fetch_assoc()){
                    $sEmail = $s['email'] ?? ($s['student_code'] . '@student.tdtu.edu.vn');
                    $mailService->sendNotification($sEmail, $s['fullname'], $title, $message);
                }
            }
        } else {
            $errorMsg = 'Có lỗi xảy ra khi gửi thông báo. Vui lòng thử lại.';
        }
    }
}

// Lấy danh sách sinh viên để gửi trực tiếp
$svStmt = $conn->query("SELECT user_id, fullname, student_code FROM users WHERE role='student' AND status='active' ORDER BY fullname ASC");
$studentList = $svStmt->fetch_all(MYSQLI_ASSOC);

// Lịch sử thông báo đã gửi
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$histStmt = $conn->prepare("
    SELECT n.id, n.title, n.message, n.type, n.is_read, n.created_at,
           u.fullname as target_name, u.student_code as target_code
    FROM notifications n
    LEFT JOIN users u ON n.target_user_id = u.user_id
    ORDER BY n.created_at DESC
    LIMIT ? OFFSET ?
");
$histStmt->bind_param('ii', $perPage, $offset);
$histStmt->execute();
$history = $histStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$cntTotal = $conn->query("SELECT COUNT(*) as c FROM notifications")->fetch_assoc()['c'] ?? 0;
$totalPages = max(1, ceil($cntTotal / $perPage));

$typeConfig = [
    'general'     => ['primary',   'Thông báo chung'],
    'room'        => ['info',      'Phòng ở'],
    'maintenance' => ['warning',   'Bảo trì'],
    'system'      => ['secondary', 'Hệ thống'],
    'message'     => ['success',   'Tin nhắn'],
];
?>

<?php if ($successMsg): ?>
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i><?php echo $successMsg; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif ($errorMsg): ?>
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($errorMsg); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form gửi thông báo -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius:14px; position:sticky; top:80px;">
            <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                <h6 class="fw-bold mb-0"><i class="bi bi-megaphone-fill text-primary me-2"></i>Gửi thông báo mới</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="send_notif" value="1">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Gửi đến <span class="text-danger">*</span></label>
                        <select name="target_user_id" class="form-select form-select-sm">
                            <option value="">📢 Tất cả sinh viên (Broadcast)</option>
                            <?php foreach ($studentList as $sv): ?>
                            <option value="<?php echo $sv['user_id']; ?>" <?php echo ($_POST['target_user_id'] ?? '') == $sv['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sv['fullname']); ?> (<?php echo $sv['student_code']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Để trống = gửi cho toàn bộ sinh viên</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Loại thông báo</label>
                        <select name="type" class="form-select form-select-sm">
                            <?php foreach ($typeConfig as $v => [$c, $l]): ?>
                            <option value="<?php echo $v; ?>" <?php echo ($_POST['type'] ?? 'general') === $v ? 'selected' : ''; ?>>
                                <?php echo $l; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tiêu đề <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-sm"
                               placeholder="Nhập tiêu đề thông báo..."
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small">Nội dung <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control form-control-sm" rows="5"
                                  placeholder="Nhập nội dung thông báo chi tiết..."
                                  required <?php echo $isPromotedAdmin ? 'disabled' : ''; ?>><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>

                    <?php if ($isPromotedAdmin): ?>
                    <div class="alert alert-warning p-2 small mb-3 text-center border-warning bg-opacity-10">
                        <i class="bi bi-shield-lock-fill me-1"></i> Tài khoản của bạn bị hạn chế chức năng này.
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2" <?php echo $isPromotedAdmin ? 'disabled' : ''; ?>>
                        <i class="bi bi-send-fill"></i> Gửi thông báo
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Lịch sử thông báo -->
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0"><i class="bi bi-clock-history text-secondary me-2"></i>Lịch sử thông báo</h6>
            <span class="badge bg-secondary bg-opacity-75"><?php echo $cntTotal; ?> thông báo</span>
        </div>

        <?php if (empty($history)): ?>
        <div class="card border-0 shadow-sm text-center py-5" style="border-radius:14px;">
            <i class="bi bi-inbox fs-2 text-muted d-block mb-2"></i>
            <p class="text-muted small mb-0">Chưa có thông báo nào được gửi.</p>
        </div>
        <?php else: ?>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($history as $n):
                [$tc, $tl] = $typeConfig[$n['type']] ?? ['secondary', 'Khác'];
            ?>
            <div class="card border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1 me-3">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-<?php echo $tc; ?> bg-opacity-75" style="font-size:10px;"><?php echo $tl; ?></span>
                                <?php if ($n['target_name']): ?>
                                <span class="badge bg-light text-dark border" style="font-size:10px;">
                                    <i class="bi bi-person-fill me-1"></i><?php echo htmlspecialchars($n['target_name']); ?>
                                </span>
                                <?php else: ?>
                                <span class="badge bg-light text-dark border" style="font-size:10px;">
                                    <i class="bi bi-broadcast me-1"></i>Broadcast
                                </span>
                                <?php endif; ?>
                            </div>
                            <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($n['title']); ?></h6>
                            <p class="mb-0 text-muted small"><?php echo nl2br(htmlspecialchars(mb_strimwidth($n['message'], 0, 150, '...'))); ?></p>
                        </div>
                        <div class="text-end flex-shrink-0 d-flex flex-column align-items-end">
                            <small class="text-muted mb-2" style="font-size:11px;"><?php echo date('d/m/Y H:i', strtotime($n['created_at'])); ?></small>
                            <a href="<?php echo BASE_URL; ?>/api/notifications.php?delete=<?php echo $n['id']; ?>" class="btn btn-sm btn-outline-danger p-1" style="line-height: 1;" title="Xóa thông báo" onclick="return confirm('Bạn có chắc chắn muốn xóa thông báo này (bao gồm cả phía sinh viên)?');"><i class="bi bi-trash" style="font-size: 12px;"></i></a>
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
                <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                <li class="page-item <?php echo $p===$page?'active':''; ?>">
                    <a class="page-link rounded" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
