<?php
require_once __DIR__ . '/../../controllers/UserController.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: /QuanLySV/auth/login.php");
    exit();
}

$userController = new UserController();

// Gọi đúng phương thức xử lý CSV upload
$response = $userController->handleCSVUpload();

$show_success_toast = $response['show_success_toast'];
$show_error_toast = $response['show_error_toast'];
$error_message = $response['error_message'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Trang import danh sách sinh viên</title>

    <link rel="shortcut icon" type="image/x-icon" href="../../assets/img/favicon.svg">

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
    <link rel="stylesheet" href="../../assets/css/style.css">
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
                <!-- Toast Container -->
                <div class="position-fixed top-0 end-0 p-3" style="z-index: 1050">
                    <!-- Success Toast -->
                    <?php if ($show_success_toast): ?>
                    <div id="successToast" class="toast align-items-center text-white bg-success border-0 show"
                        role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-check-circle me-2"></i> Thêm sinh viên mới thành công!
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                                aria-label="Close"></button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <!-- Error Toast -->
                    <?php if ($show_error_toast): ?>
                    <div id="errorToast" class="toast align-items-center text-white bg-danger border-0 show"
                        role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-exclamation-circle me-2"></i> <span
                                    id="errorMessage"><?php echo htmlspecialchars($error_message); ?></span>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                                aria-label="Close"></button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="page-header">
                    <div class="page-title">
                        <h4>Quản lý sinh viên</h4>
                        <h6>Thêm import danh sách sinh viên </h6>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="row justify-content-center">
                                <div class="col-lg-6 col-sm-12 mb-3">
                                    <div class="form-group mb-3 text-center">
                                        <label for="csv_file" class="form-label">Chọn file CSV để import danh sách sinh
                                            viên</label>
                                        <div class="image-upload image-upload-new">
                                            <input type="file" name="csv_file" id="csv_file" class="form-control"
                                                accept=".csv" required>
                                            <div class="image-uploads mt-2">
                                                <img src="../../assets/img/icons/upload.svg" alt="img"
                                                    style="width:40px;">
                                                <h6 class="mt-2">Kéo và thả file CSV vào đây hoặc nhấn để chọn file</h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-center mt-3">
                                        <button type="submit" class="btn btn-primary me-2">Import</button>
                                        <a href="userlists.php" class="btn btn-secondary">Hủy</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
        integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.1/feather.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
    <script src="../../assets/js/script.js"></script>

    <!-- JavaScript để hiển thị Toast -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Khởi tạo Success Toast
        var successToast = document.getElementById('successToast');
        var successToastInstance = new bootstrap.Toast(successToast, {
            autohide: true,
            delay: 4000 // Toast sẽ tự động ẩn sau 3 giây
        });

        // Khởi tạo Error Toast
        var errorToast = document.getElementById('errorToast');
        var errorToastInstance = new bootstrap.Toast(errorToast, {
            autohide: true,
            delay: 4000 // Toast sẽ tự động ẩn sau 3 giây
        });

        // Hiển thị Success Toast nếu thành công
        <?php if ($show_success_toast): ?>
        successToastInstance.show();
        <?php endif; ?>

        // Hiển thị Error Toast nếu có lỗi
        <?php if ($show_error_toast): ?>
        errorToastInstance.show();
        <?php endif; ?>
    });
    </script>


</body>

</html>