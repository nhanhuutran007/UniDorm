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
        <a href="<?php echo $dashboardUrl ?? '/'; ?>" class="logo">
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
            <a href="javascript:void(0);" class="nav-link position-relative" data-bs-toggle="dropdown" id="notif-btn">
                <i class="bi bi-bell fs-5 text-secondary"></i>
                <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" id="notif-count" style="display:none;font-size:10px;">0</span>
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
            <a href="/UniDorm/views/admin/settings.php" class="nav-link" title="Cài đặt hệ thống">
                <i class="bi bi-gear fs-5 text-secondary"></i>
            </a>
        </li>
        <?php endif; ?>

        <!-- Avatar + Dropdown tài khoản -->
        <li class="nav-item dropdown has-arrow main-drop">
            <a href="javascript:void(0);" class="dropdown-toggle nav-link userset d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="avatar-wrap position-relative">
                    <img src="<?php echo htmlspecialchars($profilePicture ?? '/UniDorm/assets/images/default-avatar.jpg'); ?>"
                         alt="Avatar"
                         class="rounded-circle object-fit-cover border border-2 border-light shadow-sm"
                         width="36" height="36"
                         onerror="this.src='/UniDorm/assets/images/default-avatar.jpg'">
                    <span class="position-absolute bottom-0 end-0 translate-middle-x bg-success rounded-circle border border-white"
                          style="width:9px;height:9px;"></span>
                </div>
                <div class="d-none d-lg-block text-start lh-sm">
                    <p class="mb-0 fw-semibold text-dark" style="font-size:13px; max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        <?php echo htmlspecialchars($userData['fullname'] ?? 'Người dùng'); ?>
                    </p>
                    <small class="text-muted" style="font-size:11px;">
                        <?php
                        $roleLabels = ['admin' => 'Quản trị viên', 'student' => 'Sinh viên'];
                        echo $roleLabels[$userRole] ?? ucfirst($userRole ?? '');
                        ?>
                    </small>
                </div>
                <i class="bi bi-chevron-down text-muted small ms-1"></i>
            </a>

            <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="min-width:220px; border-radius:12px; overflow:hidden;">
                <!-- User info block -->
                <div class="px-3 py-3 bg-light border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <img src="<?php echo htmlspecialchars($profilePicture ?? '/UniDorm/assets/images/default-avatar.jpg'); ?>"
                             class="rounded-circle border" width="42" height="42" style="object-fit:cover;"
                             onerror="this.src='/UniDorm/assets/images/default-avatar.jpg'">
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
                    <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="/UniDorm/views/student/room_info.php">
                        <i class="bi bi-door-open text-muted"></i> Thông tin phòng
                    </a>
                    <?php endif; ?>
                    <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="/UniDorm/views/shared/chat.php">
                        <i class="bi bi-chat-dots text-muted"></i> Tin nhắn
                    </a>
                </div>
                <div class="border-top py-1">
                    <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" href="/UniDorm/views/auth/logout.php">
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
    fetch('/UniDorm/api/notifications.php?limit=5')
        .then(r => r.json())
        .then(res => {
            if (!res.data) return;
            const list = document.getElementById('notif-list');
            const badge = document.getElementById('notif-count');
            const unread = res.data.filter(n => !n.is_read).length;

            if (unread > 0) {
                badge.textContent = unread;
                badge.style.display = 'inline';
            }

            if (res.data.length === 0) {
                list.innerHTML = '<li class="px-3 py-3 text-center text-muted small">Không có thông báo mới</li>';
                return;
            }

            list.innerHTML = res.data.map(n => `
                <li class="border-bottom">
                    <a href="/UniDorm/api/notifications.php?mark_read=${n.id}" 
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
    fetch('/UniDorm/api/mark_all_notifications.php', { method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({user_id: <?php echo (int)($userId ?? 0); ?>})
    }).then(() => {
        document.getElementById('notif-count').style.display = 'none';
        document.getElementById('notif-list').querySelectorAll('.bg-primary').forEach(el => el.classList.remove('bg-primary','bg-opacity-5'));
    });
}
</script>
