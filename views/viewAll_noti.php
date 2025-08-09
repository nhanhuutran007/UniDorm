<?php
// session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/DeviceController.php';

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

// Chuyển hướng admin đến notifications.php
if ($userRole === 'admin') {
    header("Location: /network-management/views/admin/notifications.php");
    exit();
}

// Khởi tạo DeviceController
$deviceController = new DeviceController($userId, $userRole);

// Lấy tất cả thông báo của user (technician/staff)
$notifications = [];
try {
    $result = $deviceController->handleRequest('get_notifications_by_user_id', [
        'target_user_id' => $userId,
        'filters' => ['is_read' => 0], // Lấy tất cả thông báo, không chỉ chưa đọc
        'limit' => 50, // Giới hạn 50 thông báo để tránh tải nặng
        'offset' => 0
    ]);

    if ($result['success'] && is_array($result['data'])) {
        $notifications = $result['data'];
    } else {
        error_log('Failed to fetch notifications in viewAll_noti.php: ' . ($result['message'] ?? 'No message'));
    }
} catch (Exception $e) {
    error_log('Error fetching notifications in viewAll_noti.php: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Xem tất cả thông báo </title>

    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicon.svg">


    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"
        integrity="sha512-c42qTSw/wiW5oaDSLFhn5z7mS0bIX7PB87LWBRH5iA/YB4iR8v+QYq5uTNkO5D3n4CW4S996zAqRpWIcLtYAiRw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- DataTables Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Thêm CSS tùy chỉnh -->
</head>
<body>
    <!-- Header -->

    <div class="main-wrapper">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <?php include __DIR__ . '/../includes/sidebarAll.php'; ?>
        <div class="page-wrapper">
            <div class="content">
                <div class="page-header">
                    <div class="page-title">
                        <h4>Tất cả thông báo</h4>
                        <h6>Xem tất cả hoạt động của bạn</h6>
                    </div>
                </div>
    
                <div class="activity">
                    <div class="activity-box">
                        <ul class="activity-list">
                            <?php if (!empty($notifications) && is_array($notifications)): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <li class="notification-item" data-notification-id="<?php echo isset($notification['notification_id']) &&is_numeric($notification['notification_id']) ? htmlspecialchars($notification['notification_id']) : ''; ?>"> 
                                        
                                        <div class="activity-user">
                                            <a href="javascript:void(0);" title="Hệ thống">
                                                <img alt="Hệ thống" src="/network-management/assets/img/logo1.png" class="img-fluid">
                                            </a>
                                        </div>
                                        <div class="activity-content">
                                            <div class="timeline-content">
                                                <span class="message"><?php echo htmlspecialchars($notification['message'] ?? 'Không có nội dung'); ?></span>
                                                <br>
                                                <span class="device-id">Thiết bị ID: <?php echo htmlspecialchars($notification['device_id'] ?? 'Unknown'); ?></span>
                                                <br>
                                                <span class="time"><?php echo isset($notification['created_at']) ? date('d/m/Y H:i', strtotime($notification['created_at'])) : 'N/A'; ?></span>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>
                                    <div class="activity-content">
                                        <div class="timeline-content">
                                            <span class="message">Không có thông báo nào.</span>
                                        </div>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Nội dung chính -->

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
        integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.1/feather.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
    <script src="assets/plugins/apexchart/chart-data.js"></script>
    <script src="/network-management/assets/js/script.js"></script>

    <script>
        $(document).ready(function() {
            // Gắn sự kiện click cho notification-item
            $('.notification-item').on('click', function() {
                const item = $(this);
                const notificationId = parseInt(item.data('notification-id')); // Chuyển thành số nguyên

                // Kiểm tra notificationId hợp lệ
                if (!notificationId || isNaN(notificationId)) {
                    console.error('Invalid notification ID:', notificationId);
                    alert('Lỗi: ID thông báo không hợp lệ');
                    return;
                }

                // Gọi hàm markNotificationAsRead
                markNotificationAsRead(notificationId, item);
            });

            function markNotificationAsRead(notificationId, item) {
                fetch('/network-management/api/mark_notification_as_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ notification_id: notificationId })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Network response was not ok: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Xóa thông báo với hiệu ứng fadeOut
                        $(item).fadeOut(300, function() {
                            $(this).remove();
                            // Kiểm tra danh sách trống
                            const notificationList = document.querySelector('.activity-list');
                            if (!notificationList.querySelector('.notification-item')) {
                                notificationList.innerHTML = `
                                    <li>
                                        <div class="activity-content">
                                            <div class="timeline-content">
                                                <span class="message">Không có thông báo nào.</span>
                                            </div>
                                        </div>
                                    </li>
                                `;
                            }
                        });
                        // Cập nhật badge chuông ngay lập tức
                        const badge = document.querySelector('.badge.rounded-pill');
                        if (badge) {
                            const currentCount = parseInt(badge.textContent) || 0;
                            if (currentCount > 0) {
                                badge.textContent = currentCount - 1;
                            }
                        }
                    } else {
                        alert('Lỗi: ' + (data.message || 'Không thể đánh dấu thông báo đã đọc'));
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error, 'Response:', error.message);
                    // Vẫn xóa thông báo nếu API thành công nhưng JSON lỗi (theo hành vi hiện tại)
                    $(item).fadeOut(300, function() {
                        $(this).remove();
                        const notificationList = document.querySelector('.activity-list');
                        if (!notificationList.querySelector('.notification-item')) {
                            notificationList.innerHTML = `
                                <li>
                                    <div class="activity-content">
                                        <div class="timeline-content">
                                            <span class="message">Không có thông báo nào.</span>
                                        </div>
                                    </div>
                                </li>
                            `;
                        }
                    });
                    // Cập nhật badge chuông
                    const badge = document.querySelector('.badge.rounded-pill');
                    if (badge) {
                        const currentCount = parseInt(badge.textContent) || 0;
                        if (currentCount > 0) {
                            badge.textContent = currentCount - 1;
                        }
                    }
                });
            }
        });
    </script>



</body>
</html>