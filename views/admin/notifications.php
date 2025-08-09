<?php
// Path: /network-management/views/admin/notifications.php
require_once __DIR__ . '/../../controllers/DeviceController.php';
require_once __DIR__ . '/../../controllers/UserController.php';

// Kiểm tra đăng nhập và quyền admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: /network-management/auth/login.php");
    exit();
}

// Khởi tạo các controller
$deviceController = new DeviceController($_SESSION['user_id'], $_SESSION['role']);
$userController = new UserController();
$users = array_filter($userController->getUsers(), function($user) {
    return isset($user['status']) && strtolower($user['status']) === 'active';
});

// Lấy danh sách thiết bị
$devices = $deviceController->handleRequest('get', [
    'search' => ['status' => ['active', 'maintenance']],
    'limit' => 1000
])['data'] ?? [];;

// Xử lý yêu cầu POST và GET
if ($_SERVER["REQUEST_METHOD"] === "POST" || isset($_GET['action'])) {
    header('Content-Type: application/json');
    try {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        if ($action === 'insert_notification') {
            $device_id = $_POST['device_id'] ?? '';
            $target_user_id = $_POST['target_user_id'] ?? '';
            if (!is_numeric($device_id) || (int)$device_id <= 0) {
                throw new Exception('Vui lòng chọn một thiết bị hợp lệ.');
            }
            if (!is_numeric($target_user_id) || (int)$target_user_id <= 0) {
                throw new Exception('Vui lòng chọn một người nhận hợp lệ.');
            }
            $params = [
                'data' => [
                    'device_id' => (int)$device_id,
                    'notification_type' => $_POST['notification_type'] ?? '',
                    'message' => trim($_POST['message'] ?? ''),
                    'target_user_id' => (int)$target_user_id,
                    'is_read' => false,
                    'created_by' => isset($_POST['created_by']) && $_POST['created_by'] !== '' ? (int)$_POST['created_by'] : null
                ]
            ];
            if (empty($params['data']['notification_type'])) {
                throw new Exception('Vui lòng chọn loại thông báo.');
            }
            if (empty($params['data']['message'])) {
                throw new Exception('Nội dung thông báo không được để trống.');
            }
            $result = $deviceController->handleRequest('insert_notification', $params);
        } elseif ($action === 'update_notification') {
            $notification_id = $_POST['notification_id'] ?? '';
            if (!is_numeric($notification_id) || (int)$notification_id <= 0) {
                throw new Exception('ID thông báo không hợp lệ.');
            }
            $params = [
                'notification_id' => (int)$notification_id,
               'data' => [
                    'device_id' => isset($_POST['device_id']) && is_numeric($_POST['device_id']) ? (int)$_POST['device_id'] : null,
                    'notification_type' => trim($_POST['notification_type'] ?? ''),
                    'message' => trim($_POST['message'] ?? ''),
                    'target_user_id' => isset($_POST['target_user_id']) && is_numeric($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : null,
                    'is_read' => filter_var($_POST['is_read'] ?? false, FILTER_VALIDATE_BOOLEAN)
                ]
            ];
            $result = $deviceController->handleRequest('update_notification', $params);
        } elseif ($action === 'delete_notification') {
            $notification_id = $_POST['notification_id'] ?? '';
            if (!is_numeric($notification_id) || (int)$notification_id <= 0) {
                throw new Exception('ID thông báo không hợp lệ.');
            }
            $result = $deviceController->handleRequest('delete_notification', ['notification_id' => (int)$notification_id]);
        } elseif ($action === 'get_notification') {
            $notification_id = $_GET['notification_id'] ?? '';
            if (!is_numeric($notification_id) || (int)$notification_id <= 0) {
                throw new Exception('ID thông báo không hợp lệ.');
            }
            $result = $deviceController->handleRequest('get_notification', ['notification_id' => (int)$notification_id]);
        } else {
            $result = ['success' => false, 'message' => 'Hành động không hợp lệ.'];
        }

        echo json_encode([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result['data'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Lỗi trong notifications.php: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

// Lấy danh sách thông báo
$limit = 10;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$filters = [];
if (isset($_GET['notification_type']) && !empty($_GET['notification_type'])) {
    $filters['notification_type'] = $_GET['notification_type'];
}
if (isset($_GET['is_read']) && $_GET['is_read'] !== '') {
    $filters['is_read'] = $_GET['is_read'] === '1' ? 1 : 0;
}
$notifications = $deviceController->handleRequest('get_all_notifications', [
    'filters' => $filters,
    'limit' => $limit,
    'offset' => $offset
])['data'] ?? [];

// Lấy tổng số thông báo để phân trang
$totalNotifications = count($deviceController->handleRequest('get_all_notifications', ['filters' => $filters, 'limit' => 1000])['data'] ?? []);
$totalPages = ceil($totalNotifications / $limit);

// Xử lý thông báo toast
$show_success_toast = isset($_SESSION['success_message']);
$show_error_toast = isset($_SESSION['error_message']);
$success_message = $show_success_toast ? $_SESSION['success_message'] : '';
$error_message = $show_error_toast ? $_SESSION['error_message'] : '';
if ($show_success_toast) unset($_SESSION['success_message']);
if ($show_error_toast) unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Quản lý thông báo - Admin</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../assets/img/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <div id="global-loader">
        <div class="whirly-loader"></div>
    </div>

    <div class="main-wrapper">
        <?php 
        include __DIR__ . '/../../includes/header.php'; 
        include __DIR__ . '/../../includes/sidebarAll.php'; 
        ?>

        <div class="page-wrapper">
            <div class="content">
                <div class="page-header">
                    <div class="page-title">
                        <h4>Quản lý thông báo</h4>
                        <h6>Tạo, xóa và xem thông báo</h6>
                    </div>
                    <div class="page-btn">
                        <button class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#addNotificationModal">Thêm thông báo mới</button>
                    </div>
                </div>

                <!-- Toast thông báo -->
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body"><?php echo htmlspecialchars($success_message); ?></div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                                data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                    <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body"><?php echo htmlspecialchars($error_message); ?></div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                                data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                </div>

                <!-- Danh sách thông báo -->
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                        <p>Không có thông báo nào để hiển thị.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped" id="notificationTable">
                                <thead>
                                    <tr>
                                        <!-- <th>ID</th> -->
                                        <th>Thiết bị</th>
                                        <th>Loại thông báo</th>
                                        <th>Nội dung</th>
                                        <th>Người gửi</th>
                                        <th>Người nhận</th>
                                        <th>Thời gian</th>
                                        <th>Trạng thái</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notifications as $noti): ?>
                                    <?php
                                                $deviceName = 'Không xác định';
                                                foreach ($devices as $device) {
                                                    if ($device['device_id'] == $noti['device_id']) {
                                                        $deviceName = htmlspecialchars($device['device_name']);
                                                        break;
                                                    }
                                                }
                                                $userName = 'Không xác định';
                                                foreach ($users as $user) {
                                                    if ($user['user_id'] == $noti['target_user_id']) {
                                                        $userName = htmlspecialchars($user['fullname']);
                                                        break;
                                                    }
                                                }
                                                $creatorName = $noti['creator_name'] ?? 'Không xác định';
                                                ?>
                                    <tr data-id="<?php echo $noti['notification_id']; ?>">
                                        <td><?php echo $deviceName; ?></td>
                                        <td><?php echo htmlspecialchars($noti['notification_type']); ?></td>
                                        <td><?php echo htmlspecialchars(mb_strlen($noti['message'], 'UTF-8') > 20 ? mb_substr($noti['message'], 0, 20, 'UTF-8') . '...' : $noti['message'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo ($noti['created_by'] ? ($noti['creator_name'] ?? 'Không xác định') . " (ID: {$noti['created_by']})" : 'Hệ thống (ID: N/A)'); ?>
                                        </td>
                                        <td><?php echo $userName; ?></td>
                                        <td><?php echo $noti['created_at']; ?></td>
                                        <td><?php echo $noti['is_read'] ? 'Đã đọc' : 'Chưa đọc'; ?></td>
                                        <td>
                                            <a class="me-3 edit-noti" href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#editNotificationModal"
                                                data-id="<?php echo $noti['notification_id']; ?>"
                                                data-device-id="<?php echo $noti['device_id']; ?>"
                                                data-notification-type="<?php echo htmlspecialchars($noti['notification_type']); ?>"
                                                data-message="<?php echo htmlspecialchars($noti['message']); ?>"
                                                data-target-user-id="<?php echo $noti['target_user_id']; ?>"
                                                data-is-read="<?php echo $noti['is_read'] ? 'true' : 'false'; ?>"
                                                data-created-by="<?php echo htmlspecialchars($noti['created_by'] ?? ''); ?>">
                                                <img src="../../assets/img/icons/edit.svg" alt="edit">
                                            </a>
                                            <a class="me-3 delete-noti" href="javascript:void(0);"
                                                data-id="<?php echo $noti['notification_id']; ?>">
                                                <img src="../../assets/img/icons/delete.svg" alt="delete">
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                        </div>
                        <?php endif; ?>
                        <!-- Phân trang -->
                        <div class="pagination mt-3 d-flex justify-content-between align-items-center">
                            <div>
                                <span>Hiển thị <?php echo count($notifications); ?> trong tổng số
                                    <?php echo $totalNotifications; ?> thông báo</span>
                            </div>
                            <div>
                                <?php if ($offset > 0): ?>
                                <a href="?offset=<?php echo $offset - $limit; ?>&limit=<?php echo $limit; ?>¬ification_type=<?php echo $_GET['notification_type'] ?? ''; ?>&is_read=<?php echo $_GET['is_read'] ?? ''; ?>"
                                    class="btn btn-secondary me-2">Trước</a>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?offset=<?php echo ($i - 1) * $limit; ?>&limit=<?php echo $limit; ?>¬ification_type=<?php echo $_GET['notification_type'] ?? ''; ?>&is_read=<?php echo $_GET['is_read'] ?? ''; ?>"
                                    class="btn btn-<?php echo (($i - 1) * $limit == $offset) ? 'primary' : 'secondary'; ?> me-1"><?php echo $i; ?></a>
                                <?php endfor; ?>
                                <?php if ($offset + $limit < $totalNotifications): ?>
                                <a href="?offset=<?php echo $offset + $limit; ?>&limit=<?php echo $limit; ?>¬ification_type=<?php echo $_GET['notification_type'] ?? ''; ?>&is_read=<?php echo $_GET['is_read'] ?? ''; ?>"
                                    class="btn btn-secondary">Sau</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal thêm thông báo -->
        <div class="modal fade" id="addNotificationModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Thêm thông báo mới</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addNotificationForm" method="POST">
                            <input type="hidden" name="action" value="insert_notification">
                            <div class="form-group mb-3">
                                <label>Thiết bị <span class="text-danger">*</span></label>
                                <?php if (empty($devices)): ?>
                                <p class="text-danger">Không có thiết bị nào để chọn. Vui lòng thêm thiết bị trước.</p>
                                <input type="hidden" name="device_id" value="">
                                <?php else: ?>
                                <select name="device_id" class="form-select" required>
                                    <option value="">Chọn thiết bị</option>
                                    <?php foreach ($devices as $device): ?>
                                    <option value="<?php echo $device['device_id']; ?>">
                                        <?php echo htmlspecialchars($device['device_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                            </div>
                            <div class="form-group mb-3">
                                <label>Loại thông báo <span class="text-danger">*</span></label>
                                <select name="notification_type" class="form-select" required>
                                    <option value="">Chọn loại thông báo</option>
                                    <option value="maintenance_due">Bảo trì</option>
                                    <option value="incident_report">Sự cố</option>
                                    <option value="status_change">Thay đổi trạng thái</option>
                                    <option value="assignment">Phân công</option>
                                    <option value="inspection_due">Kiểm tra</option>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label>Nội dung <span class="text-danger">*</span></label>
                                <textarea name="message" class="form-control" required rows="3"></textarea>
                            </div>
                            <div class="form-group mb-3">
                                <label>Người nhận <span class="text-danger">*</span></label>
                                <select name="target_user_id" class="form-select" required>
                                    <option value="">Chọn người nhận</option>
                                    <?php foreach ($users as $user): ?>
                                    <?php if (strtolower($user['role']) !== 'admin'): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['fullname']) . " ({$user['role']})"; ?>
                                    </option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label">Hiển thị người gửi</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_anonymous"
                                        id="is_anonymous" value="1">
                                    <label class="form-check-label" for="is_anonymous">Gửi ẩn danh</label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Gửi</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal sửa thông báo -->
        <div class="modal fade" id="editNotificationModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Chỉnh sửa thông báo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editNotificationForm" method="POST">
                            <input type="hidden" name="action" value="update_notification">
                            <input type="hidden" name="notification_id" id="edit_notification_id">
                            <div class="form-group mb-3">
                                <label>Thiết bị <span class="text-danger">*</span></label>
                                <?php if (empty($devices)): ?>
                                <p class="text-danger">Không có thiết bị nào để chọn.</p>
                                <input type="hidden" name="device_id" value="">
                                <?php else: ?>
                                <select name="device_id" id="edit_device_id" class="form-select" required>
                                    <option value="">Chọn thiết bị</option>
                                    <?php foreach ($devices as $device): ?>
                                    <option value="<?php echo $device['device_id']; ?>">
                                        <?php echo htmlspecialchars($device['device_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                            </div>
                            <div class="form-group mb-3">
                                <label>Loại thông báo <span class="text-danger">*</span></label>
                                <select name="notification_type" id="edit_notification_type" class="form-select"
                                    required>
                                    <option value="">Chọn loại thông báo</option>
                                    <option value="maintenance_due">Bảo trì</option>
                                    <option value="incident_report">Sự cố</option>
                                    <option value="status_change">Thay đổi trạng thái</option>
                                    <option value="assignment">Phân công</option>
                                    <option value="inspection_due">Kiểm tra</option>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label>Nội dung <span class="text-danger">*</span></label>
                                <textarea name="message" id="edit_message" class="form-control" required
                                    rows="3"></textarea>
                            </div>
                            <div class="form-group mb-3">
                                <label>Người nhận <span class="text-danger">*</span></label>
                                <select name="target_user_id" id="edit_target_user_id" class="form-select" required>
                                    <option value="">Chọn người nhận</option>
                                    <?php foreach ($users as $user): ?>
                                    <?php if (strtolower($user['role']) !== 'admin'): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['fullname']) . " ({$user['role']})"; ?>
                                    </option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label>Trạng thái <span class="text-danger">*</span></label>
                                <select name="is_read" id="edit_is_read" class="form-select" required>
                                    <option value="0">Chưa đọc</option>
                                    <option value="1">Đã đọc</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Cập nhật</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.1/feather.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
    <script src="../../assets/js/script.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loader = document.getElementById('global-loader');
        setTimeout(() => loader.classList.add('hidden'), 500);

        const successToast = new bootstrap.Toast(document.getElementById('successToast'), {
            delay: 4000
        });
        const errorToast = new bootstrap.Toast(document.getElementById('errorToast'), {
            delay: 4000
        });

        <?php if ($show_success_toast): ?>
        successToast.show();
        <?php endif; ?>
        <?php if ($show_error_toast): ?>
        errorToast.show();
        <?php endif; ?>

        // DataTable cho bảng thông báo
        const table = $('#notificationTable').DataTable({
            paging: false,
            searching: true,
            info: false,
            language: {
                search: "Tìm kiếm:",
                emptyTable: "Không có dữ liệu để hiển thị"
            }
        });

        // Xử lý form thêm thông báo (không reload)
        $("#addNotificationForm").on('submit', function(event) {
            event.preventDefault();
            const form = this;
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            const isAnonymous = formData.get('is_anonymous') === '1';
            formData.set('created_by', isAnonymous ? '' : '<?php echo $_SESSION['user_id']; ?>');
            submitButton.disabled = true;
            submitButton.innerHTML =
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang gửi...';

            $.ajax({
                url: window.location.pathname,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                dataType: "json",
                success: function(response) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Gửi';
                    if (response.status === 'success') {
                        successToast._element.querySelector('.toast-body').textContent =
                            response.message;
                        successToast.show();
                        form.reset();
                        $('#addNotificationModal').modal('hide');

                        // Chèn dòng mới vào bảng
                        const deviceId = formData.get('device_id');
                        const deviceName = $(
                            `#addNotificationForm select[name="device_id"] option[value="${deviceId}"]`
                        ).text();
                        const notificationType = formData.get('notification_type');
                        const message = formData.get('message').length > 20 ? formData.get(
                            'message').substring(0, 20) + '...' : formData.get(
                            'message');
                        const targetUserId = formData.get('target_user_id');
                        const targetUserName = $(
                            `#addNotificationForm select[name="target_user_id"] option[value="${targetUserId}"]`
                        ).text();
                        const creatorName = isAnonymous ? 'Hệ thống (ID: N/A)' :
                            '<?php echo htmlspecialchars($users[array_search($_SESSION['user_id'], array_column($users, 'user_id'))]['fullname'] ?? 'Không xác định'); ?> (ID: <?php echo $_SESSION['user_id']; ?>)';
                        const createdBy = isAnonymous ? '' :
                            '<?php echo $_SESSION['user_id']; ?>';
                        const createdAt = response.data?.created_at ? new Date(response.data
                                .created_at).toLocaleString('vi-VN') : new Date()
                            .toLocaleString('vi-VN');
                        const newRow = `
                        <tr data-id="${response.data.notification_id}">
                            <td>${deviceName}</td>
                            <td>${notificationType}</td>
                            <td>${message}</td>
                            <td>${creatorName}</td>
                            <td>${targetUserName}</td>
                            <td>${createdAt}</td>
                            <td>Chưa đọc</td>
                            <td>
                                <a class="me-3 edit-noti" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editNotificationModal" 
                                    data-id="${response.data.notification_id}" 
                                    data-device-id="${deviceId}" 
                                    data-notification-type="${notificationType}" 
                                    data-message="${formData.get('message')}" 
                                    data-target-user-id="${targetUserId}" 
                                    data-is-read="false"
                                    data-created-by="${createdBy}">
                                    <img src="../../assets/img/icons/edit.svg" alt="edit">
                                </a>
                                <a class="me-3 delete-noti" href="javascript:void(0);" data-id="${response.data.notification_id}">
                                    <img src="../../assets/img/icons/delete.svg" alt="delete">
                                </a>
                            </td>
                        </tr>`;
                        table.row.add($(newRow)).draw(false);
                    } else {
                        errorToast._element.querySelector('.toast-body').textContent =
                            response.message;
                        errorToast.show();
                    }
                },
                error: function(xhr) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Gửi';
                    errorToast._element.querySelector('.toast-body').textContent =
                        "Lỗi hệ thống: " + (xhr.responseJSON?.message ||
                            'Không thể xử lý yêu cầu');
                    errorToast.show();
                }
            });
        });

        // Xử lý nút đánh dấu trạng thái
        $('#notificationTable').on('click', '.edit-noti', function() {
            const notificationId = $(this).data('id');
            const deviceId = $(this).data('device-id');
            const notificationType = $(this).data('notification-type');
            const message = $(this).data('message');
            const targetUserId = $(this).data('target-user-id');
            const isRead = $(this).data('is-read') === true ? 1 : 0;

            $('#edit_notification_id').val(notificationId);
            $('#edit_device_id').val(deviceId);
            $('#edit_notification_type').val(notificationType);
            $('#edit_message').val(message);
            $('#edit_target_user_id').val(targetUserId);
            $('#edit_is_read').val(isRead);
        });

        // Xử lý form cập nhật thông báo (không reload)
        $("#editNotificationForm").on('submit', function(event) {
            event.preventDefault();
            const form = this;
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML =
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang cập nhật...';

            $.ajax({
                url: window.location.pathname,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                dataType: "json",
                success: function(response) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Cập nhật';
                    if (response.status === 'success') {
                        successToast._element.querySelector('.toast-body').textContent =
                            response.message;
                        successToast.show();
                        $('#editNotificationModal').modal('hide');
                        // Cập nhật dòng trong bảng
                        const notificationId = formData.get('notification_id');
                        const deviceId = formData.get('device_id');
                        const deviceName = $(
                            `#editNotificationForm select[name="device_id"] option[value="${deviceId}"]`
                        ).text() || 'Không xác định';
                        const notificationType = formData.get('notification_type');
                        const message = formData.get('message').length > 20 ? formData.get(
                            'message').substring(0, 20) + '...' : formData.get(
                            'message');
                        const targetUserId = formData.get('target_user_id');
                        const targetUserName = $(
                            `#editNotificationForm select[name="target_user_id"] option[value="${targetUserId}"]`
                        ).text() || 'Không xác định';
                        const isRead = formData.get('is_read') === '1' ? 'Đã đọc' :
                            'Chưa đọc';
                        // Lấy creatorName và createdBy từ dòng hiện tại
                        const currentRow = $(`tr[data-id="${notificationId}"]`);
                        const creatorName = currentRow.find('td:eq(3)')
                            .text(); // Lấy từ cột Người gửi
                        const createdBy = currentRow.find('.edit-noti').data(
                            'created-by') || '';
                        const createdAt = response.data?.created_at ? new Date(response.data
                            .created_at).toLocaleString('vi-VN') : currentRow.find(
                            'td:eq(5)').text();
                        const row = $(`tr[data-id="${notificationId}"]`);
                        row.html(`
                            <td>${deviceName}</td>
                            <td>${notificationType}</td>
                            <td>${message}</td>
                            <td>${creatorName}</td>
                            <td>${targetUserName}</td>
                            <td>${createdAt}</td>
                            <td>${isRead}</td>
                            <td>
                                <a class="me-3 edit-noti" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editNotificationModal" 
                                    data-id="${notificationId}" 
                                    data-device-id="${deviceId}" 
                                    data-notification-type="${notificationType}" 
                                    data-message="${formData.get('message')}" 
                                    data-target-user-id="${targetUserId}" 
                                    data-is-read="${formData.get('is_read') === '1'}" 
                                    data-created-by="${createdBy}">
                                    <img src="../../assets/img/icons/edit.svg" alt="edit">
                                </a>
                                <a class="me-3 delete-noti" href="javascript:void(0);" data-id="${notificationId}">
                                    <img src="../../assets/img/icons/delete.svg" alt="delete">
                                </a>
                            </td>
                        `);
                    } else {
                        errorToast._element.querySelector('.toast-body').textContent =
                            response.message;
                        errorToast.show();
                    }
                },
                error: function(xhr) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Cập nhật';
                    errorToast._element.querySelector('.toast-body').textContent =
                        "Lỗi hệ thống: " + (xhr.responseJSON?.message ||
                            'Không thể xử lý yêu cầu');
                    errorToast.show();
                }
            });
        });
        // Xử lý xóa thông báo
        $('#notificationTable').on('click', '.delete-noti', function() {
            const notificationId = $(this).data('id');
            if (!confirm('Bạn có chắc muốn xóa thông báo này?')) {
                return;
            }

            const deleteButton = this;
            deleteButton.disabled = true;
            $(deleteButton).html(
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang xóa...'
            );

            $.ajax({
                url: window.location.pathname,
                type: 'POST',
                data: {
                    action: 'delete_notification',
                    notification_id: notificationId
                },
                dataType: 'json',
                success: function(response) {
                    deleteButton.disabled = false;
                    $(deleteButton).html(
                        '<img src="../../assets/img/icons/delete.svg" alt="delete">');
                    if (response.status === 'success') {
                        successToast._element.querySelector('.toast-body').textContent =
                            response.message;
                        successToast.show();
                        table.row($(deleteButton).closest('tr')).remove().draw(false);
                    } else {
                        errorToast._element.querySelector('.toast-body').textContent =
                            response.message;
                        errorToast.show();
                    }
                },
                error: function(xhr) {
                    deleteButton.disabled = false;
                    $(deleteButton).html(
                        '<img src="../../assets/img/icons/delete.svg" alt="delete">');
                    errorToast._element.querySelector('.toast-body').textContent =
                        "Lỗi hệ thống: " + (xhr.responseJSON?.message ||
                            'Không thể xử lý yêu cầu');
                    errorToast.show();
                }
            });
        });
    });
    </script>
</body>

</html>