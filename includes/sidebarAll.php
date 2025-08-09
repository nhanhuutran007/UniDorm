<?php
// Path: /QuanLySV/includes/sidebarAll.php

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: /QuanLySV/views/auth/login.php");
    exit();
}

$role = strtolower(trim($_SESSION['role'] ?? ''));

function renderMenuItem($item, $isSubmenu = false) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    $class = $isSubmenu ? '' : ($currentPage === basename($item['url']) ? 'active' : '');
    $html = $isSubmenu
        ? "<li><a href=\"{$item['url']}\">{$item['title']}</a></li>"
        : "<li class=\"$class\"><a href=\"{$item['url']}\"><img src=\"/QuanLySV/assets/img/icons/{$item['icon']}\" alt=\"{$item['title']} Icon\"><span>{$item['title']}</span></a></li>";
    return $html;
}

function renderSubmenu($submenu) {
    $html = "<li class=\"submenu\">";
    $html .= "<a href=\"javascript:void(0);\"><img src=\"/QuanLySV/assets/img/icons/{$submenu['icon']}\" alt=\"{$submenu['title']} Icon\"><span>{$submenu['title']}</span><span class=\"menu-arrow\"></span></a>";
    $html .= "<ul>";
    foreach ($submenu['items'] as $item) {
        $html .= renderMenuItem($item, true);
    }
    $html .= "</ul>";
    $html .= "</li>";
    return $html;
}

$menuItems = [];
switch ($role) {
    case 'admin':
        $menuItems = [
            'main' => [
                'title' => 'Trang chủ',
                'url' => '/QuanLySV/views/admin/dashboard.php',
                'icon' => 'dashboard.svg'
            ],
            'submenu' => [
                [
                    'title' => 'Sinh viên',
                    'icon' => 'users1.svg',
                    'items' => [
                        ['title' => 'Thêm sinh viên mới', 'url' => '/QuanLySV/views/admin/newuser.php'],
                        ['title' => 'Danh sách sinh viên', 'url' => '/QuanLySV/views/admin/userlists.php']
                    ]
                ],
                [
                    'title' => 'Thông báo',
                    'icon' => 'notification-bing.svg',
                    'items' => [
                        ['title' => 'Danh sách thông báo', 'url' => '/QuanLySV/views/admin/notifications.php']
                    ]
                ],
                [
                    'title' => 'Báo cáo',
                    'icon' => 'time.svg',
                    'items' => [
                        ['title' => 'Sự Kiện Thiết Bị', 'url' => '/QuanLySV/views/admin/report_devices.php'],
                    ]
                ],
                [
                    'title' => 'Tin nhắn',
                    'icon' => 'purchase1.svg',
                    'items' => [
                        ['title' => 'Tin nhắn', 'url' => '/QuanLySV/views/chat.php']
                    ]
                ]
            ]
        ];
        break;
    default:
        echo '<div class="alert alert-danger m-3">Chỉ cho phép admin đăng nhập. Vui lòng đăng nhập lại.</div>';
        exit();
}
?>

<div class="sidebar" id="sidebar">
    <div class="slimScrollDiv" style="position: relative; overflow: hidden; width: 100%; height: 1123px;">
        <div class="sidebar-inner slimscroll" style="overflow: hidden; width: 100%; height: 1123px;">
            <div id="sidebar-menu" class="sidebar-menu">
                <ul>
                    <?php if (!empty($menuItems)): ?>
                    <?php echo renderMenuItem($menuItems['main']); ?>
                    <?php foreach ($menuItems['submenu'] as $submenu): ?>
                    <?php echo renderSubmenu($submenu); ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="slimScrollBar"
            style="background: rgb(204, 204, 204); width: 7px; position: absolute; top: 0px; opacity: 0.4; display: block; border-radius: 7px; z-index: 99; right: 1px; height: 1123.5px;">
        </div>
        <div class="slimScrollRail"
            style="width: 7px; height: 100%; position: absolute; top: 0px; display: none; border-radius: 7px; background: rgb(51, 51, 51); opacity: 0.2; z-index: 90; right: 1px;">
        </div>
    </div>
</div>