<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/DeviceController.php';

// Nếu là POST => API xử lý (không render HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);

    try {
        // Kiểm tra đăng nhập admin
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập với quyền admin để thực hiện thao tác này.',
                'data' => null
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $controller = new DeviceController($_SESSION['user_id'], $_SESSION['role']);

        // Validate dữ liệu gửi lên
        $requiredFields = ['device_id', 'user_id', 'assigned_date'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Trường $field là bắt buộc");
            }
        }

        $deviceId = filter_input(INPUT_POST, 'device_id', FILTER_VALIDATE_INT);
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        if ($deviceId === false || $userId === false) {
            throw new Exception("ID thiết bị và ID người dùng phải là số nguyên hợp lệ");
        }

        // Chuẩn bị tham số cho Controller
        $params = [
            'data' => [
                'device_id' => $deviceId,
                'user_id' => $userId,
                'assigned_date' => $_POST['assigned_date'],
                'expected_return_date' => $_POST['expected_return_date'] ?? null,
                'assigned_by_user_id' => $_SESSION['user_id'],
                'status' => 'active',
                'notes' => $_POST['notes'] ?? ''
            ]
        ];

        // Gọi Controller xử lý
        $response = $controller->handleRequest('insert_assignment', $params);

        if (!isset($response['success']) || !isset($response['message'])) {
            throw new Exception("Phản hồi từ controller không hợp lệ");
        }

        // Phản hồi JSON
        ob_clean(); // Xóa buffer đầu ra
        echo json_encode([
            'status' => $response['success'] ? 'success' : 'error',
            'message' => $response['message'],
            'data' => $response['data'] ?? null
        ], JSON_UNESCAPED_UNICODE);
        exit();

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Lỗi: ' . $e->getMessage(),
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm phân quyền thiết bị</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../assets/img/favicon.svg">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/network-management/assets/css/style.css">

    <!-- Sửa lỗi loader vĩnh viễn -->
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
                        <h4>Thêm phân quyền thiết bị</h4>
                        <h6>Trang thêm phân quyền thiết bị dành cho Admin</h6>
                    </div>
                </div>

                <!-- Toast thông báo -->
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body"></div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                                data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                    <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body"></div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                                data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form id="addAssignmentForm" method="POST">
                            <div class="row">
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>ID Thiết bị <span class="text-danger">*</span></label>
                                        <input type="number" name="device_id" class="form-control" required min="1">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>ID Người dùng <span class="text-danger">*</span></label>
                                        <input type="number" name="user_id" class="form-control" required min="1">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>Ngày phân quyền <span class="text-danger">*</span></label>
                                        <input type="date" name="assigned_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>Ngày trả dự kiến</label>
                                        <input type="date" name="expected_return_date" class="form-control">
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <label>Ghi chú</label>
                                        <textarea name="notes" class="form-control"></textarea>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <button type="submit" class="btn btn-primary me-2">Thêm phân quyền</button>
                                    <a href="/network-management/views/admin/assignmentlist.php"
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        setTimeout(() => loader.classList.add('hidden'), 5000); // Ẩn loader sau 5s

        const successToast = new bootstrap.Toast(document.getElementById('successToast'), {
            delay: 4000
        });
        const errorToast = new bootstrap.Toast(document.getElementById('errorToast'), {
            delay: 4000
        });

        $("#addAssignmentForm").on('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML =
                '<span class="spinner-border spinner-border-sm"></span> Đang xử lý...';

            $.ajax({
                url: '',
                type: 'POST',
                data: formData,
                dataType: 'json',
                processData: false,
                contentType: false,
                timeout: 10000,
                success: function(response) {
                    console.log('Response:', response);
                    if (response.status === 'success') {
                        window.location.href =
                            '/network-management/views/admin/assignmentlist.php';
                    } else {
                        showToast('error', response.message || 'Có lỗi xảy ra.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', xhr, status, error);
                    let errMsg = 'Lỗi hệ thống không xác định.';
                    try {
                        const json = JSON.parse(xhr.responseText);
                        if (json && json.message) {
                            errMsg = json.message;
                        }
                    } catch (e) {
                        errMsg = xhr.responseText || 'Lỗi hệ thống không xác định.';
                    }
                    showToast('error', errMsg);
                },
                complete: function() {
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Thêm phân quyền';
                }
            });
        });

        function showToast(type, message) {
            const toast = type === 'success' ? successToast : errorToast;
            document.querySelector(`#${type}Toast .toast-body`).textContent = message;
            toast.show();
        }
    });
    </script>
</body>

</html>