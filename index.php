<?php
/**
 * UniDorm – index.php (Front Controller / Router)
 * Xử lý tất cả routes và redirect đúng trang theo role
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';

// Nếu chưa đăng nhập → login
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/views/auth/login.php');
    exit;
}

$role   = strtolower($_SESSION['role'] ?? '');
$route  = trim($_GET['page'] ?? '');

// Map route → file
$adminRoutes = [
    ''                => 'views/admin/dashboard.php',
    'dashboard'       => 'views/admin/dashboard.php',
    'students'        => 'views/admin/students.php',
    'rooms'           => 'views/admin/rooms.php',
    'rooms_detail'    => 'views/admin/rooms_detail.php',
    'floors'          => 'views/admin/floors.php',
    'device_reports'  => 'views/admin/device_reports.php',
    'notifications'   => 'views/admin/notifications.php',
    'userlists'       => 'views/admin/userlists.php',
    'reports'         => 'views/admin/reports.php',
    'newuser'         => 'views/admin/newuser.php',
    'updateuser'      => 'views/admin/updateuser.php',
    'import'          => 'views/admin/import.php',
    'chat'            => 'views/shared/chat.php',
    'profile'         => 'views/shared/profile.php',
    'settings'        => 'views/admin/settings.php',
];

$studentRoutes = [
    ''                => 'views/student/dashboard.php',
    'dashboard'       => 'views/student/dashboard.php',
    'room'            => 'views/student/room_info.php',
    'report'          => 'views/student/report_device.php',
    'notifications'   => 'views/student/notifications.php',
    'chat'            => 'views/shared/chat.php',
    'profile'         => 'views/shared/profile.php',
];

$baseDir = __DIR__ . '/';

if ($role === 'admin') {
    $target = $adminRoutes[$route] ?? null;
} elseif ($role === 'student') {
    $target = $studentRoutes[$route] ?? null;
} else {
    header('Location: ' . BASE_URL . '/views/auth/login.php');
    exit;
}

// 404
if (!$target || !file_exists($baseDir . $target)) {
    require_once $baseDir . 'views/errors/404.php';
    exit;
}

// Route match → include file
require_once $baseDir . $target;