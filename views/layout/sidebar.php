<?php
/**
 * UniDorm – Sidebar Navigation (Role-aware)
 * path: views/layout/sidebar.php
 * Dùng chung: hiển thị menu khác nhau theo role (admin/student)
 */
$currentPath = $_SERVER['PHP_SELF'] ?? '';
function sidebarActive(string $path, string $current): string {
    return (strpos($current, $path) !== false) ? 'active' : '';
}
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-inner slimscroll">
        <div id="sidebar-menu" class="sidebar-menu">

            <?php if (isset($userRole) && $userRole === 'admin'): ?>
            <!-- ============ MENU ADMIN ============ -->
            <ul class="mb-0">

                <li class="menu-title"><span>Tổng quan</span></li>

                <li class="<?php echo sidebarActive('dashboard', $currentPath); ?>">
                    <a href="/UniDorm/views/admin/dashboard.php">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="menu-title"><span>Quản lý KTX</span></li>

                <li class="<?php echo sidebarActive('students', $currentPath); ?>">
                    <a href="/UniDorm/views/admin/students.php">
                        <i class="bi bi-people-fill"></i>
                        <span>Quản lý sinh viên</span>
                    </a>
                </li>

                <li class="<?php echo sidebarActive('rooms', $currentPath); ?>">
                    <a href="/UniDorm/views/admin/rooms.php">
                        <i class="bi bi-door-open-fill"></i>
                        <span>Quản lý phòng</span>
                    </a>
                </li>

                <li class="<?php echo sidebarActive('floors', $currentPath); ?>">
                    <a href="/UniDorm/views/admin/floors.php">
                        <i class="bi bi-layers-fill"></i>
                        <span>Quản lý lầu</span>
                    </a>
                </li>

                <li class="menu-title"><span>Tiện ích</span></li>

                <li class="<?php echo sidebarActive('device_reports', $currentPath); ?> subsection">
                    <a href="/UniDorm/views/admin/device_reports.php">
                        <i class="bi bi-tools"></i>
                        <span>Báo cáo thiết bị hỏng</span>
                        <?php
                        // Badge số báo cáo chưa xử lý
                        if (isset($pendingReportsCount) && $pendingReportsCount > 0):
                        ?>
                        <span class="badge badge-danger ms-auto"><?php echo $pendingReportsCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="<?php echo sidebarActive('notifications', $currentPath); ?>">
                    <a href="/UniDorm/views/admin/notifications.php">
                        <i class="bi bi-megaphone-fill"></i>
                        <span>Thông báo</span>
                    </a>
                </li>

                <li class="<?php echo sidebarActive('chat', $currentPath); ?>">
                    <a href="/UniDorm/views/shared/chat.php">
                        <i class="bi bi-chat-dots-fill"></i>
                        <span>Tin nhắn</span>
                    </a>
                </li>

                <li class="menu-title"><span>Hệ thống</span></li>

                <li class="<?php echo sidebarActive('userlists', $currentPath); ?>">
                    <a href="/UniDorm/views/admin/userlists.php">
                        <i class="bi bi-person-badge-fill"></i>
                        <span>Danh sách tài khoản</span>
                    </a>
                </li>

                <li class="<?php echo sidebarActive('reports', $currentPath); ?>">
                    <a href="/UniDorm/views/admin/reports.php">
                        <i class="bi bi-bar-chart-fill"></i>
                        <span>Thống kê & Báo cáo</span>
                    </a>
                </li>

            </ul>

            <?php elseif (isset($userRole) && $userRole === 'student'): ?>
            <!-- ============ MENU STUDENT ============ -->
            <ul class="mb-0">

                <li class="menu-title"><span>Trang chủ</span></li>

                <li class="<?php echo sidebarActive('student/dashboard', $currentPath); ?>">
                    <a href="/UniDorm/views/student/dashboard.php">
                        <i class="bi bi-house-fill"></i>
                        <span>Tổng quan</span>
                    </a>
                </li>

                <li class="menu-title"><span>Phòng của tôi</span></li>

                <li class="<?php echo sidebarActive('room_info', $currentPath); ?>">
                    <a href="/UniDorm/views/student/room_info.php">
                        <i class="bi bi-door-open-fill"></i>
                        <span>Thông tin phòng</span>
                    </a>
                </li>

                <li class="<?php echo sidebarActive('report_device', $currentPath); ?>">
                    <a href="/UniDorm/views/student/report_device.php">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Báo hỏng thiết bị</span>
                    </a>
                </li>

                <li class="menu-title"><span>Liên lạc</span></li>

                <li class="<?php echo sidebarActive('notifications', $currentPath); ?>">
                    <a href="/UniDorm/views/student/notifications.php">
                        <i class="bi bi-bell-fill"></i>
                        <span>Thông báo</span>
                        <?php if (isset($unreadNotifCount) && $unreadNotifCount > 0): ?>
                        <span class="badge badge-danger ms-auto"><?php echo $unreadNotifCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="<?php echo sidebarActive('chat', $currentPath); ?>">
                    <a href="/UniDorm/views/shared/chat.php">
                        <i class="bi bi-chat-dots-fill"></i>
                        <span>Nhắn tin với BQL</span>
                    </a>
                </li>

                <li class="menu-title"><span>Tài khoản</span></li>

                <li class="<?php echo sidebarActive('profile', $currentPath); ?>">
                    <a href="/UniDorm/views/shared/profile.php">
                        <i class="bi bi-person-fill"></i>
                        <span>Hồ sơ cá nhân</span>
                    </a>
                </li>

            </ul>
            <?php endif; ?>

        </div>
    </div>
</div>
