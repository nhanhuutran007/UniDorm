<?php
/**
 * UniDorm – Header Component (Role-aware)
 * path: views/layout/header.php
 * Dùng chung cho cả Admin và Student
 * Yêu cầu: $conn, $_SESSION đã được khởi tạo
 */
?>
<div class="header">

    <!-- Logo + Toggle -->
    <div class="header-left active">
        <a href="<?php echo $dashboardUrl; ?>" class="logo">
            <div class="logo-inner d-flex align-items-center gap-2 px-3">
                <i class="bi bi-building-fill text-primary fs-5"></i>
                <span class="fw-bold fs-6 text-dark">UniDorm</span>
            </div>
        </a>
        <a id="toggle_btn" href="javascript:void(0);"></a>
    </div>

    <a id="mobile_btn" class="mobile_btn" href="#sidebar">
        <span class="bar-icon">
            <span></span><span></span><span></span>
        </span>
    </a>

    <ul class="nav user-menu align-items-center gap-3 ms-auto pe-3">

        <!-- Thanh tìm kiếm -->
        <li class="nav-item d-none d-md-flex">
            <div class="header-search-bar">
                <form action="" method="GET" class="d-flex align-items-center">
                    <input type="text" name="q" id="header-search"
                           class="form-control form-control-sm bg-light border-0 rounded-pill ps-3"
                           placeholder="Tìm kiếm sinh viên, phòng..."
                           value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
                           style="min-width:260px;">
                </form>
            </div>
        </li>

        <!-- Thông báo -->
        <li class="nav-item dropdown" id="notif-dropdown">
            <a href="javascript:void(0);" class="nav-link header-icon-btn position-relative" data-bs-toggle="dropdown" id="notif-btn">
                <i class="bi bi-bell fs-5"></i>
                <span class="badge bg-danger rounded-pill badge-notif" id="notif-count" style="display:none;">0</span>
            </a>
            <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-0" style="min-width:340px; border-radius:12px; overflow:hidden;">
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                    <span class="fw-semibold text-dark small">Thông báo</span>
                    <button class="btn btn-link btn-sm text-danger p-0 text-decoration-none" onclick="markAllNotifRead()">Đọc tất cả</button>
                </div>
                <ul class="list-unstyled m-0" id="notif-list" style="max-height:320px;overflow-y:auto;">
                    <li class="px-3 py-3 text-center text-muted small">Đang tải...</li>
                </ul>
                <div class="border-top text-center py-2">
                    <a href="<?php echo $notifUrl ?? '#'; ?>" class="text-primary small text-decoration-none">Xem tất cả thông báo</a>
                </div>
            </div>
        </li>

        <!-- Cài đặt (chỉ hiển thị với Admin) -->
        <?php if (isset($userRole) && $userRole === 'admin'): ?>
        <li class="nav-item">
            <a href="javascript:void(0);" class="nav-link header-icon-btn" title="Xóa toàn bộ sinh viên" data-bs-toggle="modal" data-bs-target="#deleteAllStudentsModal">
                <i class="bi bi-gear fs-5 text-danger"></i>
            </a>
        </li>
        <?php endif; ?>

        <!-- Avatar + Dropdown tài khoản -->
        <li class="nav-item dropdown has-arrow main-drop d-flex align-items-center">
            <a href="javascript:void(0);" class="dropdown-toggle nav-link userset d-flex align-items-center gap-2 p-0" data-bs-toggle="dropdown">
                <div class="avatar-wrap position-relative" style="width: 36px; height: 36px;">
                    <?php
                    $avatarSrc = (!empty($profilePicture) && $profilePicture != BASE_URL . '/') ? $profilePicture : BASE_URL . '/assets/images/default.jpg';
                    ?>
                    <img src="<?php echo $avatarSrc; ?>"
                         alt="Avatar"
                         class="rounded-circle object-fit-cover border border-2 border-light shadow-sm bg-white"
                         width="36" height="36"
                         onerror="if (this.src != '<?php echo BASE_URL; ?>/assets/images/default.jpg') this.src='<?php echo BASE_URL; ?>/assets/images/default.jpg';">
                    <span class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-2 border-white"
                          style="width:10px;height:10px; transform: translate(10%, 10%);"></span>
                </div>
            </a>

            <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="min-width:220px; border-radius:12px; overflow:hidden;">
                <!-- User info block -->
                <div class="px-3 py-3 bg-light border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <?php
                        $avatarSrc = (!empty($profilePicture) && $profilePicture != BASE_URL . '/') ? $profilePicture : BASE_URL . '/assets/images/default.jpg';
                        ?>
                        <img src="<?php echo $avatarSrc; ?>"
                             class="rounded-circle border bg-white" width="42" height="42" style="object-fit:cover;"
                             onerror="if (this.src != '<?php echo BASE_URL; ?>/assets/images/default.jpg') this.src='<?php echo BASE_URL; ?>/assets/images/default.jpg';">
                        <div class="lh-sm overflow-hidden">
                            <p class="mb-0 fw-semibold text-dark small text-truncate" style="max-width:140px;">
                                <?php echo htmlspecialchars($userData['fullname'] ?? 'Người dùng'); ?>
                            </p>
                            <small class="text-muted" style="font-size:11px;">
                                <?php echo htmlspecialchars($userData['email'] ?? ''); ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Menu items -->
                <div class="py-1">
                    <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="<?php echo $profileUrl ?? '#'; ?>">
                        <i class="bi bi-person text-muted"></i> Hồ sơ cá nhân
                    </a>
                    <?php if (isset($userRole) && $userRole === 'student'): ?>
                    <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="<?php echo BASE_URL; ?>/room">
                        <i class="bi bi-door-open text-muted"></i> Thông tin phòng
                    </a>
                    <?php endif; ?>
                    <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="<?php echo BASE_URL; ?>/chat">
                        <i class="bi bi-chat-dots text-muted"></i> Tin nhắn
                    </a>
                </div>
                <div class="border-top py-1">
                    <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" href="<?php echo BASE_URL; ?>/views/auth/logout.php">
                        <i class="bi bi-box-arrow-right"></i> Đăng xuất
                    </a>
                </div>
            </div>
        </li>
    </ul>
</div>

<script>
// Load notifications realtime
(function loadNotifications() {
    fetch('<?php echo BASE_URL; ?>/api/notifications.php?limit=5&unread_only=1')
        .then(r => r.json())
        .then(res => {
            if (!res.data) return;
            const list = document.getElementById('notif-list');
            const badge = document.getElementById('notif-count');
            const unread = res.data.filter(n => !n.is_read).length;

            if (unread > 0) {
                badge.textContent = unread;
                badge.style.display = 'flex';
            }

            if (res.data.length === 0) {
                list.innerHTML = '<li class="px-3 py-3 text-center text-muted small">Không có thông báo mới</li>';
                return;
            }

            list.innerHTML = res.data.map(n => `
                <li class="border-bottom">
                    <a href="<?php echo BASE_URL; ?>/api/notifications.php?mark_read=${n.id}" 
                       class="d-flex gap-2 px-3 py-2 text-decoration-none ${n.is_read ? '' : 'bg-primary bg-opacity-5'}">
                        <div class="flex-shrink-0 mt-1">
                            <span class="badge rounded-circle bg-${n.is_read ? 'secondary' : 'primary'} p-1" style="width:8px;height:8px;display:block;"></span>
                        </div>
                        <div class="flex-grow-1">
                            <p class="mb-0 small fw-semibold text-dark">${n.title}</p>
                            <p class="mb-0 text-muted" style="font-size:11px;">${n.message.substring(0,60)}…</p>
                            <small class="text-muted" style="font-size:10px;">${n.created_at}</small>
                        </div>
                    </a>
                </li>
            `).join('');
        }).catch(() => {});
})();

function markAllNotifRead() {
    fetch('<?php echo BASE_URL; ?>/api/mark_all_notifications.php', { method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({user_id: <?php echo (int)($userId ?? 0); ?>})
    }).then(() => {
        document.getElementById('notif-count').style.display = 'none';
        document.getElementById('notif-list').querySelectorAll('.bg-primary').forEach(el => el.classList.remove('bg-primary','bg-opacity-5'));
    });
}
</script>

<?php if (isset($userRole) && $userRole === 'admin'): ?>
<!-- Modal Xóa Toàn Bộ Sinh Viên -->
<div class="modal fade" id="deleteAllStudentsModal" tabindex="-1" aria-labelledby="deleteAllStudentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title" id="deleteAllStudentsModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Cảnh Báo Nguy Hiểm
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteAllStudentsForm">
                <div class="modal-body p-4">
                    <p class="text-danger fw-bold">Hành động này sẽ XÓA TOÀN BỘ dữ liệu sinh viên khỏi hệ thống!</p>
                    <p class="small text-muted mb-4">Bao gồm toàn bộ danh sách sinh viên, tài khoản đăng nhập, tin nhắn, thông báo, và các báo cáo liên quan. Dữ liệu <strong class="text-dark">không thể khôi phục</strong> sau khi xóa.</p>
                    
                    <div class="mb-3">
                        <label for="admin_password" class="form-label fw-semibold">Xác nhận mật khẩu Admin gốc <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="admin_password" name="admin_password" required placeholder="Nhập mật khẩu của bạn để xác nhận">
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-danger" id="btnConfirmDeleteAll">
                        <i class="bi bi-trash"></i> Đồng ý xóa toàn bộ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('deleteAllStudentsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnConfirmDeleteAll');
    const password = document.getElementById('admin_password').value;
    
    if(!password) {
        alert('Lỗi: Vui lòng nhập mật khẩu xác nhận');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang xử lý...';

    fetch('<?php echo BASE_URL; ?>/api/delete_all_students.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ password: password })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Thành công! Toàn bộ sinh viên và tài khoản liên quan đã được xóa.');
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteAllStudentsModal'));
            if (modal) modal.hide();
            window.location.reload();
        } else {
            alert('Lỗi: ' + (data.message || 'Có lỗi xảy ra'));
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash"></i> Đồng ý xóa toàn bộ';
        }
    })
    .catch(err => {
        console.error(err);
        alert('Lỗi: Không thể kết nối đến máy chủ');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash"></i> Đồng ý xóa toàn bộ';
    });
});
</script>
<?php endif; ?>
