<?php
// views/admin/maintenancelist.php
// Gán quyền admin để kiểm tra (thay vì dựa vào session)

// Bật hiển thị lỗi để debug (chỉ dùng khi phát triển)
ini_set('display_errors', 0); // Tắt hiển thị lỗi trên giao diện
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Xử lý yêu cầu thêm bảo trì qua POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        // Kiểm tra các trường bắt buộc
        $requiredFields = ['device_id', 'maintenance_date'];
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                throw new Exception("Trường $field là bắt buộc");
            }
        }

        // Kiểm tra và làm sạch dữ liệu
        // Device ID: Phải là số nguyên dương
        $deviceId = filter_input(INPUT_POST, 'device_id', FILTER_VALIDATE_INT);
        if ($deviceId === false || $deviceId < 1) {
            throw new Exception("ID thiết bị phải là số nguyên dương hợp lệ");
        }

        // Maintenance Date: Phải có định dạng Y-m-d
        $maintenanceDate = trim($_POST['maintenance_date']);
        $date = DateTime::createFromFormat('Y-m-d', $maintenanceDate);
        if ($date === false || $date->format('Y-m-d') !== $maintenanceDate) {
            throw new Exception("Ngày bảo trì phải có định dạng YYYY-MM-DD và hợp lệ");
        }

        // Description: Không bắt buộc, làm sạch để tránh XSS
        $description = isset($_POST['description']) && trim($_POST['description']) !== ''
            ? htmlspecialchars(trim($_POST['description']), ENT_QUOTES, 'UTF-8')
            : null;

        // Tạo $maintenanceData với cấu trúc giống đoạn code test
        $maintenanceData = [
            'device_id' => $deviceId,
            'maintenance_date' => $maintenanceDate,
            'description' => $description,
        ];

        // Kiểm tra và include các file cần thiết
        $dbFile = __DIR__ . '/../../includes/db.php';
        $controllerFile = __DIR__ . '/../../controllers/DeviceController.php';

        if (!file_exists($dbFile)) {
            throw new Exception("File db.php không tồn tại");
        }
        if (!file_exists($controllerFile)) {
            throw new Exception("File DeviceController.php không tồn tại");
        }

        require_once $dbFile;
        require_once $controllerFile;

        // Khởi tạo controller
        $controller = new DeviceController($_SESSION['user_id'], $_SESSION['role']);

        // Gọi handleRequest
        $response = $controller->handleRequest('request_maintenance', ['data' => $maintenanceData]);

        // Phản hồi JSON
        echo json_encode([
            'status' => isset($response['success']) && $response['success'] ? 'success' : 'error',
            'message' => $response['message'] ?? 'Không có thông báo từ handleRequest',
            'data' => $response['data'] ?? null
        ]);
    } catch (Exception $e) {
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
$success_message = $show_success_toast && !empty($_SESSION['success_message']) ? htmlspecialchars($_SESSION['success_message']) : 'Thành công!';
$error_message = $show_error_toast && !empty($_SESSION['error_message']) ? htmlspecialchars($_SESSION['error_message']) : 'Có lỗi xảy ra!';

if ($show_success_toast) unset($_SESSION['success_message']);
if ($show_error_toast) unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm bảo trì thiết bị</title>
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
            $headerFile = __DIR__ . '/../../includes/header.php';
            $sidebarFile = __DIR__ . '/../../includes/sidebarAll.php';
            if (!file_exists($headerFile)) {
                throw new Exception("File header.php không tồn tại");
            }
            if (!file_exists($sidebarFile)) {
                throw new Exception("File sidebarAll.php không tồn tại");
            }
            include $headerFile;
            include $sidebarFile;
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Lỗi: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>

        <div class="page-wrapper">
            <div class="content">
                <div class="page-header">
                    <div class="page-title">
                        <h4>Thêm bảo trì thiết bị</h4>
                        <h6>Trang thêm bảo trì thiết bị dành cho Technician</h6>
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
                                    <button type="submit" class="btn btn-primary me-2">Thêm bảo trì</button>
                                    <a href="/network-management/views/technician/maintenance.php"
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
    if (typeof window.jQuery === 'undefined') {
        document.write('<script src="/network-management/assets/js/jquery-3.7.1.min.js"><\/script>');
        setTimeout(() => {
            if (typeof window.jQuery === 'undefined') {
                alert('Không thể tải jQuery. Vui lòng kiểm tra kết nối mạng.');
            }
        }, 1000);
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    if (typeof window.bootstrap === 'undefined') {
        document.write('<script src="/network-management/assets/js/bootstrap.bundle.min.js"><\/script>');
        setTimeout(() => {
            if (typeof window.bootstrap === 'undefined') {
                alert('Không thể tải Bootstrap. Vui lòng kiểm tra kết nối mạng.');
            }
        }, 1000);
    }
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

        // Lấy phần tử DOM của toast
        const successToastElement = document.getElementById('successToast');
        const errorToastElement = document.getElementById('errorToast');

        // Khởi tạo Bootstrap Toast
        const successToast = new bootstrap.Toast(successToastElement, {
            delay: 4000
        });
        const errorToast = new bootstrap.Toast(errorToastElement, {
            delay: 4000
        });

        function showToast(toast, message) {
            // Cập nhật nội dung toast-body
            toast._element.querySelector('.toast-body').textContent = message;
            toast.show();
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
                    submitButton.innerHTML = 'Thêm bảo trì';
                    if (response.status === 'success') {
                        showToast(successToast, response.message +
                            ' Sẽ chuyển hướng sau 2 giây...');
                        $("#addMaintenanceForm")[0].reset();
                        setTimeout(() => window.location.href =
                            'maintenance.php', 1000);
                    } else {
                        showToast(errorToast, response.message);
                    }
                },
                error: function(xhr) {
                    loader.classList.add('hidden');
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Thêm bảo trì';
                    let response;
                    try {
                        response = JSON.parse(xhr.responseText);
                        showToast(errorToast, "Lỗi hệ thống: " + (response.message ||
                            'Không thể xử lý yêu cầu'));
                    } catch (e) {
                        showToast(errorToast,
                            "Lỗi hệ thống: Phản hồi từ server không hợp lệ - " + xhr
                            .responseText);
                    }
                }
            });
        });
    });
    </script>
</body>

</html>