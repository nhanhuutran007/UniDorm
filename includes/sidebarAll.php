<?php
// Path: /network-management/includes/sidebarAll.php

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: /network-management/views/auth/login.php");
    exit();
}

$role = strtolower(trim($_SESSION['role'] ?? ''));

function renderMenuItem($item, $isSubmenu = false) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    $class = $isSubmenu ? '' : ($currentPage === basename($item['url']) ? 'active' : '');
    $html = $isSubmenu
        ? "<li><a href=\"{$item['url']}\">{$item['title']}</a></li>"
        : "<li class=\"$class\"><a href=\"{$item['url']}\"><img src=\"/network-management/assets/img/icons/{$item['icon']}\" alt=\"{$item['title']} Icon\"><span>{$item['title']}</span></a></li>";
    return $html;
}

function renderSubmenu($submenu) {
    $html = "<li class=\"submenu\">";
    $html .= "<a href=\"javascript:void(0);\"><img src=\"/network-management/assets/img/icons/{$submenu['icon']}\" alt=\"{$submenu['title']} Icon\"><span>{$submenu['title']}</span><span class=\"menu-arrow\"></span></a>";
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
                'url' => '/network-management/views/admin/dashboard.php',
                'icon' => 'dashboard.svg'
            ],
            'submenu' => [
                [
                    'title' => 'Người dùng',
                    'icon' => 'users1.svg',
                    'items' => [
                        ['title' => 'Thêm người dùng mới', 'url' => '/network-management/views/admin/newuser.php'],
                        ['title' => 'Danh sách người dùng', 'url' => '/network-management/views/admin/userlists.php']
                    ]
                ],
                [
                    'title' => 'Thông báo',
                    'icon' => 'notification-bing.svg',
                    'items' => [
                        ['title' => 'Danh sách thông báo', 'url' => '/network-management/views/admin/notifications.php']
                    ]
                ],
                [
                    'title' => 'Thiết bị',
                    'icon' => 'product.svg',
                    'items' => [
                        ['title' => 'Danh sách thiết bị', 'url' => '/network-management/views/admin/devicelist.php'],
                        ['title' => 'Thêm thiết bị', 'url' => '/network-management/views/admin/addDevice.php'],
                        ['title' => 'Phân quyền thiết bị', 'url' => '/network-management/views/admin/assignmentlist.php'],
                        ['title' => 'Bảo trì thiết bị', 'url' => '/network-management/views/admin/maintenancelist.php'],
                    ]
                ],
                [
                    'title' => 'Báo cáo',
                    'icon' => 'time.svg',
                    'items' => [
                        ['title' => 'Sự Kiện Thiết Bị', 'url' => '/network-management/views/admin/report_devices.php'],
                    ]
                ],
                [
                    'title' => 'Tin nhắn',
                    'icon' => 'purchase1.svg',
                    'items' => [
                        ['title' => 'Tin nhắn', 'url' => '/network-management/views/chat.php']
                    ]
                ]
            ]
        ];
        break;

    case 'technician':
        $menuItems = [
            'main' => [
                'title' => 'Trang chủ',
                'url' => '/network-management/views/technician/technician.php',
                'icon' => 'dashboard.svg'
            ],
            'submenu' => [
                [
                    'title' => 'Thiết bị',
                    'icon' => 'product.svg',
                    'items' => [
                        ['title' => 'Danh sách thiết bị', 'url' => '/network-management/views/technician/devicesTech.php'],
                        ['title' => 'Bảo trì thiết bị', 'url' => '/network-management/views/technician/maintenance.php'],
                        ['title' => 'Thêm trì thiết bị', 'url' => '/network-management/views/technician/addmaintenance.php']
                    ]
                ],
                [
                    'title' => 'Tin nhắn',
                    'icon' => 'purchase1.svg',
                    'items' => [
                        ['title' => 'Tin nhắn', 'url' => '/network-management/views/chat.php']
                    ]
                ]
            ]
        ];
        // Đối với technician không có Người dùng, Thông báo, Báo cáo nên chỉ sắp xếp lại như trên.
        break;

    case 'staff':
        $menuItems = [
            'main' => [
                'title' => 'Trang chủ',
                'url' => '/network-management/views/staff/staff.php',
                'icon' => 'dashboard.svg'
            ],
            'submenu' => [
                [
                    'title' => 'Thiết bị',
                    'icon' => 'product.svg',
                    'items' => [
                        ['title' => 'Danh sách thiết bị', 'url' => '/network-management/views/staff/devicesStaff.php'],
                    ]
                ],
                [
                    'title' => 'Tin nhắn',
                    'icon' => 'purchase1.svg',
                    'items' => [
                        ['title' => 'Tin nhắn', 'url' => '/network-management/views/chat.php']
                    ]
                ]
            ]
        ];
        // Đối với staff không có Người dùng, Thông báo, Báo cáo nên chỉ sắp xếp lại như trên.
        break;

    default:
        echo '<div class="alert alert-danger m-3">Vai trò không hợp lệ. Vui lòng đăng nhập lại.</div>';
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