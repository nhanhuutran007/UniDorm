<?php
// views/admin/maintenancelist.php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/DeviceController.php';

// Kiểm tra đăng nhập và quyền admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'Bạn cần đăng nhập với quyền admin để thực hiện thao tác này.';
    header("Location: /network-management/auth/login.php");
    exit();
}

// Khởi tạo controller với user_id và role từ session
$controller = new DeviceController($_SESSION['user_id'], $_SESSION['role']);

// Xử lý yêu cầu Thêm lịch bảo trì
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        // Kiểm tra các trường bắt buộc
        $requiredFields = ['device_id', 'maintenance_date'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Trường $field là bắt buộc");
            }
        }

        // Kiểm tra định dạng số
        $deviceId = filter_input(INPUT_POST, 'device_id', FILTER_VALIDATE_INT);
        if ($deviceId === false || $deviceId < 1) {
            throw new Exception("ID thiết bị phải là số nguyên hợp lệ");
        }

        // Kiểm tra ngày hợp lệ
        $maintenanceDate = $_POST['maintenance_date'];
        if (!DateTime::createFromFormat('Y-m-d', $maintenanceDate)) {
            throw new Exception("Ngày bảo trì không hợp lệ");
        }

        // Kiểm tra mô tả
        $description = $_POST['description'] ?? '';
        if (strlen($description) > 1000) {
            throw new Exception("Mô tả không được vượt quá 1000 ký tự");
        }

        // Kiểm tra ghi chú
        $notes = $_POST['notes'] ?? '';
        if (strlen($notes) > 1000) {
            throw new Exception("Ghi chú không được vượt quá 1000 ký tự");
        }

        // Chuẩn bị dữ liệu
        $scheduleData = [
            'device_id' => $deviceId,
            'reported_by_user_id' => $_SESSION['user_id'],
            'maintenance_date' => $maintenanceDate,
            'description' => $description ?: 'Bảo trì định kỳ máy chủ',
            'notes' => $notes ?: 'Kiểm tra phần cứng'
        ];

        // Xác định action dựa theo role
        $role = $_SESSION['role'];
        $action = ($role === 'admin') ? 'schedule_maintenance' : 'request_maintenance';

        // Gọi handleRequest với action phù hợp
        $response = $controller->handleRequest($action, ['data' => $scheduleData]);

        // Phản hồi JSON
        echo json_encode([
            'status' => $response['success'] ? 'success' : 'error',
            'message' => $response['message'],
            'data' => $response['data'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Lỗi khi Thêm lịch bảo trì: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Lỗi: ' . $e->getMessage(),
            'data' => null
        ]);
    }
    exit();
}

// Xử lý thông báo toast
$show_success_toast = isset($_SESSION['success_message']);
$show_error_toast = isset($_SESSION['error_message']);
$success_message = $show_success_toast ? htmlspecialchars($_SESSION['success_message']) : '';
$error_message = $show_error_toast ? htmlspecialchars($_SESSION['error_message']) : '';

if ($show_success_toast) unset($_SESSION['success_message']);
if ($show_error_toast) unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm lịch bảo trì thiết bị</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../assets/img/favicon.svg">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/network-management/assets/css/style.css">

    <!-- Sửa lỗi loader -->
    <style>
    #global-loader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        transition: opacity 0.3s;
    }

    #global-loader.hidden {
        opacity: 0;
        visibility: hidden;
    }

    .whirly-loader {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3498db;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
    </style>
</head>

<body>
    <div id="global-loader">
        <div class="whirly-loader"></div>
    </div>

    <div class="main-wrapper">
        <?php
        try {
            include __DIR__ . '/../../includes/header.php';
            include __DIR__ . '/../../includes/sidebarAll.php';
        } catch (Exception $e) {
            error_log("Lỗi khi include file: " . $e->getMessage());
            echo '<div class="alert alert-danger">Lỗi: Không thể tải giao diện</div>';
        }
        ?>

        <div class="page-wrapper">
            <div class="content">
                <div class="page-header">
                    <div class="page-title">
                        <h4>Thêm lịch bảo trì thiết bị</h4>
                        <h6>Trang lên lịch bảo trì thiết bị dành cho Admin</h6>
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

                <div class="card">
                    <div class="card-body">
                        <form id="addMaintenanceForm" method="POST">
                            <div class="row">
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>ID Thiết bị <span class="text-danger">*</span></label>
                                        <input type="number" name="device_id" class="form-control" required min="1">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>Ngày bảo trì <span class="text-danger">*</span></label>
                                        <input type="date" name="maintenance_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <label>Mô tả</label>
                                        <textarea name="description" class="form-control"></textarea>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <label>Ghi chú</label>
                                        <textarea name="notes" class="form-control"></textarea>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <button type="submit" class="btn btn-primary me-2">Thêm lịch bảo trì</button>
                                    <a href="/network-management/views/admin/maintenancelist.php"
                                        class="btn btn-secondary">Hủy</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
    window.jQuery || document.write('<script src="/network-management/assets/js/jquery-3.7.1.min.js"><\/script>');
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    window.bootstrap || document.write(
        '<script src="/network-management/assets/js/bootstrap.bundle.min.js"><\/script>');
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.1/feather.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
    <script src="/network-management/assets/js/script.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const loader = document.getElementById('global-loader');
        window.addEventListener('load', () => loader.classList.add('hidden'));

        const successToast = new bootstrap.Toast(document.getElementById('successToast'), {
            delay: 4000
        });
        const errorToast = new bootstrap.Toast(document.getElementById('errorToast'), {
            delay: 4000
        });

        function showToast(toastElement, message) {
            toastElement.querySelector('.toast-body').textContent = message;
            toastElement.show();
        }

        <?php if ($show_success_toast): ?>
        showToast(successToast, '<?php echo $success_message; ?>');
        <?php endif; ?>
        <?php if ($show_error_toast): ?>
        showToast(errorToast, '<?php echo $error_message; ?>');
        <?php endif; ?>

        $("#addMaintenanceForm").on('submit', function(event) {
            event.preventDefault();
            if (!confirm('Bạn có chắc muốn thêm yêu cầu bảo trì này?')) {
                return;
            }
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML =
                '<span class="spinner-border spinner-border-sm"></span> Đang xử lý...';
            loader.classList.remove('hidden');

            $.ajax({
                url: window.location.pathname,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                dataType: "json",
                success: function(response) {
                    loader.classList.add('hidden');
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Thêm lịch bảo trì';
                    showToast(response.status === 'success' ? successToast : errorToast,
                        response.message);
                    if (response.status === 'success') {
                        $("#addMaintenanceForm")[0].reset();
                        setTimeout(() => window.location.href =
                            '/network-management/views/admin/maintenancelist.php', 2000);
                    }
                },
                error: function(xhr) {
                    loader.classList.add('hidden');
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Thêm lịch bảo trì';
                    console.error('AJAX Error:', xhr.responseText);
                    showToast(errorToast, "Lỗi hệ thống: " + (xhr.responseJSON?.message ||
                        'Không thể xử lý yêu cầu'));
                }
            });
        });
    });
    </script>
</body>

</html>