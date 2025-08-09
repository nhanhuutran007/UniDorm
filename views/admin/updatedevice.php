<?php
//path: views/admin/updatedevice.php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/DeviceController.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: /network-management/auth/login.php");
    exit();
}

// Khởi tạo DeviceController với thông tin từ session
$deviceController = new DeviceController($_SESSION['user_id'], $_SESSION['role']);

// Lấy device_id từ URL (GET)
$device_id = $_GET['device_id'] ?? null;

// Xử lý yêu cầu cập nhật thiết bị qua POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header('Content-Type: application/json');

    try {
         // Lấy device_name từ POST để sử dụng cho tên file
         $device_name = $_POST['device_name'] ?? 'unnamed_device';
        
         // Xử lý upload ảnh trực tiếp
         $imagePath = '../../assets/images/devices/laptop-solid.svg'; // Default image
         if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
             if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                 $fileTmpPath = $_FILES['image']['tmp_name'];
                 $fileName = $_FILES['image']['name'];
                 $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                 $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
 
                 if (!in_array($fileExtension, $allowedExtensions)) {
                     throw new Exception("Định dạng file không được phép. Chỉ chấp nhận: jpg, jpeg, png, gif");
                 }
 
                 // Sanitize device_name để đảm bảo tên file hợp lệ
                 $sanitizedDeviceName = preg_replace("/[^a-zA-Z0-9_-]/", "_", $device_name);
                 $newFileName = $sanitizedDeviceName . "." . $fileExtension;
                 $uploadDir = __DIR__ . "/../../assets/images/devices/";
                 $destPath = $uploadDir . $newFileName;
 
                 if (!is_dir($uploadDir)) {
                     mkdir($uploadDir, 0777, true);
                 }
 
                 // Kiểm tra xem file đã tồn tại chưa
                 if (file_exists($destPath)) {
                     // Nếu file đã tồn tại, thêm timestamp để tránh ghi đè
                     $newFileName = $sanitizedDeviceName . "_" . time() . "." . $fileExtension;
                     $destPath = $uploadDir . $newFileName;
                 }
 
                 if (!move_uploaded_file($fileTmpPath, $destPath)) {
                     throw new Exception("Không thể di chuyển file upload");
                 }
 
                 $imagePath = "../../assets/images/devices/" . $newFileName;
             } else {
                 throw new Exception("Lỗi khi upload file: " . $_FILES['image']['error']);
             }
         }
 
        $device_id = $_POST['device_id'] ?? $_GET['device_id'] ?? null;

        if (!$device_id) {
            throw new Exception("Không tìm thấy ID thiết bị để cập nhật.");
        }

        $data = [];
        // Thay đổi các trường cần thiết từ $_POST
        // Chỉ lấy các trường cần thiết từ $_POST và loại bỏ các trường không cần thiết
        $fields = [
            'device_name' => $_POST['device_name'] ?? null,
            'device_type' => $_POST['device_type'] ?? null,
            'ip_address' => $_POST['ip_address'] ?? null,
            'mac_address' => $_POST['mac_address'] ?? null,
            'status' => $_POST['status'] ?? null,
            'location' => $_POST['location'] ?? null,
            'purchase_date' => $_POST['purchase_date'] ?? null,
            'last_maintenance' => $_POST['maintenance_date'] ?? null,
            'price' => $_POST['price'] ?? null,
            'image' => $imagePath ?? "../../assets/images/devices/laptop-solid.svg"
        ];

        foreach ($fields as $key => $value) {
            if ($value !== null && $value !== '') { // Chỉ thêm nếu giá trị không rỗng
                $data[$key] = $value;
            }
        }

        $params = [
            'device_id' => $device_id,
            'data' => $data
        ];

        $result = $deviceController->handleRequest('update', $params);

        echo json_encode([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['message']
        ]);
    } catch (Exception $e) {
        error_log("Lỗi trong updateDevice.php: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Lỗi hệ thống: ' . $e->getMessage()
        ]);
    }

    ob_end_flush();
    exit();
}
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật thông tin thiết bị</title>
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
        <?php include(__DIR__ . '/../../includes/header.php'); ?>
        <?php include(__DIR__ . '/../../includes/sidebarAll.php'); ?>

        <div class="page-wrapper">
            <div class="content">
                <div class="page-header">
                    <div class="page-title">
                        <h4>Cập nhật thông tin thiết bị</h4>
                        <h6>Trang cập nhật thông tin thiết bị cho Admin</h6>
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
                        <form id="updateDeviceForm" enctype="multipart/form-data" method="POST">
                            <div class="row">
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>ID Thiết Bị <span class="text-danger">*</span></label>
                                        <input type="text" name="device_id" class="form-control" readonly required
                                            value="<?php echo htmlspecialchars($device_id ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>Tên thiết bị</label>
                                        <input type="text" name="device_name" class="form-control">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>Loại thiết bị</label>
                                        <select class="form-select" name="device_type">
                                            <option value="">Chọn loại thiết bị</option>
                                            <option value="printer">Máy in</option>
                                            <option value="computer">Máy tính</option>
                                            <option value="router">Router</option>
                                            <option value="switch">Switch</option>
                                            <option value="server">Server</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>Địa chỉ IP</label>
                                        <input type="text" name="ip_address" class="form-control"
                                            pattern="^(\d{1,3}\.){3}\d{1,3}$" placeholder="VD: 192.168.1.1">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>Địa chỉ MAC</label>
                                        <input type="text" name="mac_address" class="form-control"
                                            pattern="^([0-9A-Fa-f]{2}[-:]){5}[0-9A-Fa-f]{2}$"
                                            placeholder="VD: 00:1A:2B:3C:4D:5E">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>Trạng thái</label>
                                        <select class="form-select" name="status">
                                            <option value="active">Hoạt động</option>
                                            <option value="inactive">Không hoạt động</option>
                                            <option value="maintenance">Bảo trì</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>Vị trí</label>
                                        <input type="text" name="location" class="form-control">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>Ngày mua</label>
                                        <input type="date" name="purchase_date" class="form-control">
                                    </div>
                                </div>
                                <div class="col-lg-3 col-sm-6 col-12">
                                    <div class="form-group">
                                        <label>Giá (VNĐ)</label>
                                        <input type="number" name="price" step="0.01" min="0" class="form-control">
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <label>Ảnh thiết bị</label>
                                        <div class="image-upload image-upload-new">
                                            <input type="file" name="image" class="form-control"
                                                accept="image/jpeg,image/png,image/gif">
                                            <div class="image-uploads">
                                                <img src="../../assets/img/icons/upload.svg" alt="img">
                                                <h4>Kéo và thả hoặc chọn file để upload</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <button type="submit" class="btn btn-primary me-2">Cập nhật</button>
                                    <a href="/network-management/views/admin/devicelist.php"
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.1/feather.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>

    <!-- Custom Scripts -->
    <script src="../../assets/js/script.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loader = document.getElementById('global-loader');
        setTimeout(() => {
            loader.classList.add('hidden');
        }, 500);

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

        $("#updateDeviceForm").on('submit', function(event) {
            const fileInput = $('input[name="image"]')[0];
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    event.preventDefault();
                    alert('Chỉ chấp nhận file ảnh định dạng JPG, PNG hoặc GIF');
                    return false;
                }
                if (file.size > 5 * 1024 * 1024) { // Giới hạn 5MB
                    event.preventDefault();
                    alert('Kích thước file ảnh không được vượt quá 5MB');
                    return false;
                }
            }
            event.preventDefault();
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            $.ajax({
                url: window.location.pathname +
                    '?device_id=<?php echo urlencode($device_id ?? ''); ?>',
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                dataType: "json",
                success: function(response) {
                    submitButton.disabled = false;
                    if (response.status === 'success') {
                        successToast._element.querySelector('.toast-body').textContent =
                            response.message;
                        successToast.show();
                    } else {
                        errorToast._element.querySelector('.toast-body').textContent =
                            response.message;
                        errorToast.show();
                    }
                },
                error: function(xhr, status, error) {
                    submitButton.disabled = false;
                    console.error('AJAX Error:', xhr.responseText);
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