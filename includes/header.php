<?php
$conn = require __DIR__ . '/db.php';
require __DIR__ . '/../models/ProfileModel.php';

// Kiểm tra session và timeout
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || 
    (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600))) {
    session_destroy();
    header("Location: /network-management/views/auth/login.php?error=session_expired");
    exit();
}

// Cập nhật thời gian hoạt động cuối
$_SESSION['last_activity'] = time();

$userRole = strtolower($_SESSION['role']);
$userId = $_SESSION['user_id'];
$dashboardUrl = '';
switch ($userRole) {
    case 'admin': 
        $dashboardUrl = '/network-management/views/admin/dashboard.php';
        break;
    case 'technician':
        $dashboardUrl = '/network-management/views/technician/technician.php';
        break;
    default:
        $dashboardUrl = '/network-management/views/staff/staff.php';
}

// Lấy thông tin người dùng
$profileModel = new ProfileModel($conn);
$userData = $profileModel->getUserById($userId);
if (!$userData) {
    session_destroy();
    header("Location: /network-management/views/auth/login.php?error=user_not_found");
    exit();
}

$profilePicture = "/network-management/assets/" . htmlspecialchars($userData['profile_picture'] ?? 'images/default-avatar.jpg');

// --- THÔNG BÁO: ĐÃ COMMENT LẠI ĐỂ SỬ DỤNG CHO MỤC ĐÍCH KHÁC ---
// $notification = [];
// $unreadCount = 0;
// try {
//     if ($userRole === 'admin') {
//         // Admin thấy tất cả thông báo
//         $result = $deviceController->handleRequest('get_all_notifications', [
//             'filters' => [],
//             'limit' => 5,
//             'offset' => 0
//         ]);
//     } else {
//         // Người dùng bình thường chỉ thấy thông báo của mình
//         $result = $deviceController->handleRequest('get_notifications_by_user_id', [
//             'target_user_id' => $userId,
//             'filters' => ['is_read' => 0],
//             'limit' => 5,
//             'offset' => 0
//         ]);
//     }

//     if ($result['success'] && is_array($result['data'])) {
//         $notification = $result['data'];
//         $unreadCount = count($notification ?? []);
//     } else {
//         error_log('Failed to fetch notifications: ' . ($result['message'] ?? 'No message'));
//     }
// } catch (Exception $e) {
//     error_log('Error fetching notifications: ' . $e->getMessage());
// }

// $endTime = microtime(true);
// error_log('Notification fetch time: ' . ($endTime - $startTime) . ' seconds');
?>

<div class="header">
    <div class="header-left active">
        <a href="<?php echo $dashboardUrl; ?>" class="logo">
            <img src="/network-management/assets/img/logo1.png" alt="Logo" width="140" height="60">
        </a>
        <a id="toggle_btn" href="javascript:void(0);"></a>
    </div>

    <a id="mobile_btn" class="mobile_btn" href="#sidebar">
        <span class="bar-icon">
            <span></span>
            <span></span>
            <span></span>
        </span>
    </a>

    <ul class="nav user-menu">
        <!-- Thanh tìm kiếm -->
        <!-- ... -->

        <!-- Thông báo -->
        <!--
        <li class="nav-item dropdown">
            <a href="javascript:void(0);" class="dropdown-toggle nav-link" data-bs-toggle="dropdown">
                <img src="/network-management/assets/img/icons/notification-bing.svg" alt="Notifications">
                <span class="badge rounded-pill"><?php echo $unreadCount; ?></span>
            </a>
            <div class="dropdown-menu notifications">
                <div class="topnav-dropdown-header">
                    <span class="notification-title">Thông báo</span>
                    <a href="javascript:void(0)" class="clear-noti" onclick="clearAllNotifications()"> Xóa</a>
                </div>
                <div class="noti-content">
                    <ul class="notification-list">
                        <?php if (!empty($notification) && is_array($notification)): ?>
                        <?php foreach ($notification as $noti): ?>
                        <li class="notification-message"
                            data-notification-id="<?php echo htmlspecialchars($noti['notification_id'] ?? ''); ?>">
                            <a href="javascript:void(0);"
                                onclick="markNotificationAsRead('<?php echo htmlspecialchars($noti['notification_id'] ?? ''); ?>', '/network-management/views/viewAll_noti.php?notification_id=<?php echo htmlspecialchars($noti['notification_id'] ?? ''); ?>')">
                                <div class="media d-flex">
                                    <span class="avatar flex-shrink-0">
                                        <img alt="Avatar"
                                            src="<?php echo htmlspecialchars('/network-management/assets/img/logo1.png'); ?>">
                                    </span>
                                    <div class="media-body flex-grow-1">
                                        <p class="noti-details">
                                            <?php if ($userRole === 'admin'): ?>
                                            <?php
                                                $targetUserId = htmlspecialchars($noti['target_user_id'] ?? 'Unknown');
                                                $prefix = "Gửi tới user $targetUserId : ";
                                                $message = $noti['message'] ?? 'No message';
                                                $maxMessageLength = 60 - mb_strlen($prefix, 'UTF-8');
                                                $maxMessageLength = max($maxMessageLength, 10);
                                                $displayMessage = mb_strlen($message, 'UTF-8') > $maxMessageLength
                                                    ? mb_substr($message, 0, $maxMessageLength, 'UTF-8') . '...'
                                                    : $message;
                                            ?>
                                            <span
                                                class="noti-title"><?php echo htmlspecialchars($prefix . $displayMessage); ?></span>
                                            <?php else: ?>
                                            <?php
                                                    $prefix = ($noti['created_by'] === null || $noti['created_by'] === '') 
                                                        ? "Hệ thống : " 
                                                        : (htmlspecialchars($noti['creator_name'] ?? 'Không xác định') . " : ");
                                                    $deviceId = htmlspecialchars($noti['device_id'] ?? 'Unknown');
                                                    $suffix = " cho thiết bị $deviceId";
                                                    $message = $noti['message'] ?? 'No message';
                                                    $maxMessageLength = 50 - strlen($prefix) - strlen($suffix);
                                                    $maxMessageLength = max($maxMessageLength, 20);
                                                    $displayMessage = strlen($message) > $maxMessageLength ? substr($message, 0, $maxMessageLength) . '...' : $message;
                                                    ?>
                                            <span
                                                class="noti-title"><?php echo htmlspecialchars($prefix . $displayMessage . $suffix); ?></span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="noti-time">
                                            <span
                                                class="notification-time"><?php echo isset($noti['created_at']) ? date('d/m/Y H:i', strtotime($noti['created_at'])) : 'N/A'; ?></span>
                                        </p>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <li class="notification-message">
                            <div class="media d-flex">
                                <div class="media-body flex-grow-1">
                                    <p class="noti-details">Không có thông báo mới</p>
                                </div>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="topnav-dropdown-footer">
                    <a href="/network-management/views/viewAll_noti.php">Xem tất cả</a>
                </div>
            </div>
        </li>
        -->

        <!-- User Profile Dropdown -->
        <li class="nav-item dropdown has-arrow main-drop">
            <a href="javascript:void(0);" class="dropdown-toggle nav-link userset" data-bs-toggle="dropdown">
                <span class="user-img">
                    <img src="<?php echo $profilePicture; ?>" alt="Profile Picture">
                    <span class="status online"></span>
                </span>
            </a>
            <div class="dropdown-menu menu-drop-user">
                <div class="profilename">
                    <div class="profileset">
                        <span class="user-img">
                            <img src="<?php echo $profilePicture; ?>" alt="Profile Picture">
                            <span class="status online"></span>
                        </span>
                        <div class="profilesets">
                            <h6><?php echo htmlspecialchars($userData['fullname'] ?? 'User'); ?></h6>
                            <h5><?php echo htmlspecialchars($userData['role'] ?? 'Unknown Role'); ?></h5>
                        </div>
                    </div>
                    <hr class="m-0">
                    <a class="dropdown-item" href="/network-management/views/profile.php"><i class="me-2"
                            data-feather="user"></i>Hồ sơ cá nhân</a>
                    <hr class="m-0">
                    <a class="dropdown-item logout pb-0" href="/network-management/views/auth/logout.php"><img
                            src="/network-management/assets/img/icons/log-out.svg" class="me-2" alt="Logout">Đăng
                        xuất</a>
                </div>
            </div>
        </li>
    </ul>

    <!-- Mobile User Menu -->
    <div class="dropdown mobile-user-menu">
        <a href="javascript:void(0);" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
            aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
        <div class="dropdown-menu dropdown-menu-right">
            <a class="dropdown-item" href="/network-management/views/profile.php">Hồ sơ cá nhân</a>
            <a class="dropdown-item" href="/network-management/views/auth/logout.php">Dăng xuất </a>
        </div>
    </div>
</div>

<!--
<script>
function clearAllNotifications() {
    fetch('/network-management/api/mark_all_notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: <?php echo $userId; ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notificationList = document.querySelector('.notification-list');
                notificationList.innerHTML = `
                <li class="notification-message">
                    <div class="media d-flex">
                        <div class="media-body flex-grow-1">
                            <p class="noti-details">Không có thông báo mới</p>
                        </div>
                    </div>
                </li>
            `;
                const badge = document.querySelector('.badge.rounded-pill');
                badge.textContent = '0';
                alert('Đã xóa tất cả thông báo!');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while clearing notifications.');
        });
}

function markNotificationAsRead(notificationId, redirectUrl) {
    window.location.href = redirectUrl;
}
</script>
-->