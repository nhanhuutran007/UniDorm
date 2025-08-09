<?php
// File: views/admin/dashboard.php
// Phiên bản đã làm sạch: loại bỏ mọi tham chiếu tới DeviceController và loader

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../controllers/UserController.php';

// Chỉ admin được phép truy cập
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: /QuanLySV/index.php?route=login");
    exit();
}

$UserController = new UserController();
$userActive = 0;
$userCount = 0;
try {
    $userActive = (int) ($UserController->countUsers('active') ?? 0);
    $userCount = (int) ($UserController->countUsers('all') ?? 0);
} catch (Throwable $e) {
    error_log('UserController error on dashboard: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trang quản lý chính Admin</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../assets/img/favicon.svg">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <div class="main-wrapper">
        <?php include(__DIR__ . '/../../includes/header.php'); ?>
        <?php include(__DIR__ . '/../../includes/sidebarAll.php'); ?>

        <div class="page-wrapper">
            <div class="content">
                <div class="row">
                    <div class="col-lg-3 col-sm-6 col-12 d-flex">
                        <div class="dash-count">
                            <div class="dash-counts">
                                <h4><?php echo htmlspecialchars($userCount, ENT_QUOTES, 'UTF-8'); ?></h4>
                                <h5>Người dùng</h5>
                            </div>
                            <div class="dash-imgs">
                                <i data-feather="users"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 col-12 d-flex">
                        <div class="dash-count das1">
                            <div class="dash-counts">
                                <h4><?php echo htmlspecialchars($userActive, ENT_QUOTES, 'UTF-8'); ?></h4>
                                <h5>Người dùng đang hoạt động</h5>
                            </div>
                            <div class="dash-imgs">
                                <i data-feather="user-check"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 col-12 d-flex">
                        <div class="dash-count das2">
                            <div class="dash-counts">
                                <h4>-</h4>
                                <h5>Hiệu suất mạng</h5>
                            </div>
                            <div class="dash-imgs">
                                <i data-feather="activity"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 col-12 d-flex">
                        <div class="dash-count das3">
                            <div class="dash-counts">
                                <h4>-</h4>
                                <h5>Dữ liệu sử dụng</h5>
                            </div>
                            <div class="dash-imgs">
                                <i data-feather="database"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Có thể thêm các nội dung khác ở đây -->
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.1/feather.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/script.js"></script>

    <script>
    $(document).ready(function() {
        feather.replace();
        if ($('.datatable').length) {
            $('.datatable').DataTable();
        }
    });
    </script>
</body>

</html>