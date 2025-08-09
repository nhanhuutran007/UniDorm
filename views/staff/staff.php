<?php
session_start() ;

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'staff') {
    header("Location: /network-management/index.php?route=login");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <title>Trang quản lí của nhân viên</title>

    <!-- Favicon (Thêm hình ảnh favicon.jpg vào thư mục assets/img) -->
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
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <!-- Loader -->
    <div id="global-loader">
        <div class="whirly-loader"></div>
    </div>

    <div class="main-wrapper">
        <!-- Header -->
        <?php include(__DIR__ . '/../../includes/header.php'); ?>
        <?php include(__DIR__ . '/../../includes/sidebarAll.php'); ?>
        <!-- Sidebar -->
        <!-- <div class="sidebar-overlay"></div>
        <div class="sidebar" id="sidebar">
            <div class="sidebar-inner slimscroll">
                <div id="sidebar-menu" class="sidebar-menu">
                    <ul>
                        <li class="active">
                        
                            <a href="staff.php"><img src="/network-management/assets/img/icons/dashboard.svg"
                                    alt="Dashboard Icon"><span>Trang chủ </span></a>
                        </li>
                        <li class="submenu">
                           
                            <a href="javascript:void(0);"><img src="/network-management/assets/img/icons/product.svg"
                                    alt="Device Icon"><span>Thiết bị </span><span class="menu-arrow"></span></a>
                            <ul>
                                <li><a href="devicesStaff.php">Danh sách thiết bị </a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                       
                            <a href="javascript:void(0);"><img src="/network-management/assets/img/icons/time.svg"
                                    alt="Report Icon"><span>Báo cáo </span><span class="menu-arrow"></span></a>
                            <ul>
                                <li><a href="salesreport.html">Sales Report</a></li>

                            </ul>
                        </li>
                   
                    </ul>
                </div>
            </div>
        </div> -->

        <!-- Main Content -->
        <div class="page-wrapper">
            <div class="content container mt-4">
                <h2>Trang quản lí của nhân viên</h2>
                <p>Chào mừng bạn đến với dashboard!</p>
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
    <script src="../../assets/plugins/apexchart/chart-data.js"></script>
    <script src="../../assets/js/script.js"></script>

    <script>
    $(document).ready(function() {
        if (typeof jQuery === 'undefined') {
            console.error('jQuery không tải được');
        } else {
            console.log('jQuery đã tải thành công');
            feather.replace(); // Khởi tạo Feather Icons
            $('.datatable').DataTable(); // Khởi tạo DataTables
        }
        if (typeof ApexCharts === 'undefined') {
            console.error('ApexCharts không tải được');
        } else {
            console.log('ApexCharts đã tải thành công');
        }
    });
    </script>
</body>

</html>