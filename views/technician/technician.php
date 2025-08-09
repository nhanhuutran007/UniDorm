<?php
session_start();
// Path: http://localhost/network-management/views/admin/test.php
// Nạp file DeviceController và các thành phần giao diện
require_once __DIR__ . '/../../controllers/DeviceController.php';
require_once __DIR__ . '/../../controllers/UserController.php';

// var_dump($_SESSION) ;
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'technician') {
    header("Location: /network-management/index.php?route=login");
    exit();
}
// Khởi tạo
$DeviceController = new DeviceController($_SESSION['user_id'], $_SESSION['role']);
// Gọi hàm requestDeviceCount để lấy tổng số lượng thiết bị
$deviceCountResult = $DeviceController->requestDeviceCount('countDevice');
$deviceCount = $deviceCountResult['success'] ? $deviceCountResult['data']['device_count'] : 0;
// Gọi hàm requestDeviceCount để lấy tổng số lượng thiết bị Active
$totalActiveResult = $DeviceController->requestDeviceCount('totalActive');
$deviceActiveCount = $totalActiveResult['success'] ? $totalActiveResult['data']['device_count'] : 0;
// Gọi hàm requestDeviceCount để lấy tổng số lượng thiết bị Inactive
$totalInactiveResult = $DeviceController->requestDeviceCount('totalInactive');
$deviceInactiveCount = $totalInactiveResult['success'] ? $totalInactiveResult['data']['device_count'] : 0;
// Gọi hàm requestDeviceCount để lấy tổng số lượng thiết bị Maintenance
$totalMaintenanceResult = $DeviceController->requestDeviceCount('totalMaintenance');
$deviceMaintenanceCount = $totalMaintenanceResult['success'] ? $totalMaintenanceResult['data']['device_count'] : 0;
// Lấy danh sách thiết bị gần nhất
$searchParams = isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8') : '';
$result = $DeviceController->handleRequest('get_assigned_maintenance', [
    'search' => ['search' => $searchParams],
    'limit' => 100,
    'offset' => 0
]);
$devices = $result['success'] ? $result['data'] : [];

// Sắp xếp thiết bị theo updated_at (giảm dần) và lấy 5 thiết bị gần nhất
if (!empty($devices)) {
    usort($devices, function($a, $b) {
        return strtotime($b['updated_at']) - strtotime($a['updated_at']);
    });
    $devices = array_slice($devices, 0, 5); // Lấy 5 thiết bị đầu tiên
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Trang chủ Technician</title>

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
        <div class="whirly-loader"> </div>
    </div>

    <div class="main-wrapper">
        <?php include(__DIR__ . '/../../includes/header.php'); ?>
        <?php include(__DIR__ . '/../../includes/sidebarAll.php'); ?>
        <div class="page-wrapper">
            <div class="content">
                <div class="row">
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="dash-widget">
                            <div class="dash-widgetimg">
                                <span><img src="../../assets/img/icons/icon1.svg" alt="img" width="25px"
                                        height="25px"></span>
                            </div>
                            <div class="dash-widgetcontent">
                                <h5>
                                    <span class="counters" data-count="<?php echo $deviceCount; ?>">
                                        <span class="counter-value">0</span>
                                    </span>
                                </h5>
                                <h6>Tất cả thiết bị</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="dash-widget dash1">
                            <div class="dash-widgetimg">
                                <span><img src="../../assets/img/icons/icon2.svg" alt="img" width="18px"
                                        height="18px"></span>
                            </div>
                            <div class="dash-widgetcontent">
                                <h5>
                                    <span class="counters" data-count="<?php echo $deviceMaintenanceCount; ?>">
                                        <span class="counter-value">0</span>
                                    </span>
                                </h5>
                                <h6>Thiết bị đang bảo trì</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="dash-widget dash2">
                            <div class="dash-widgetimg">
                                <span><img src="../../assets/img/icons/icon3.svg" alt="img" width="25px"
                                        height="25px"></span>
                            </div>
                            <div class="dash-widgetcontent">
                                <h5>
                                    <span class="counters" data-count="<?php echo $deviceActiveCount; ?>">
                                        <span class="counter-value">0</span>
                                    </span>
                                </h5>
                                <h6>Thiết bị đang hoạt động</h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="dash-widget dash3">
                            <div class="dash-widgetimg">
                                <span><img src="../../assets/img/icons/icon4.svg" alt="img" width="25px"
                                        height="25px"></span>
                            </div>
                            <div class="dash-widgetcontent">
                                <h5>
                                    <span class="counters" data-count="<?php echo $deviceInactiveCount; ?>">
                                        <span class="counter-value">0</span>
                                    </span>
                                </h5>
                                <h6>Thiết bị lỗi </h6>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <!-- Biểu đồ -->
                    <div class="col-lg-7 col-sm-12 col-12 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Tổng quan</h5>
                            </div>
                            <div class="card-body">
                                <div id="sales_charts"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Kết thúc biểu đồ -->

                    <!-- Thiết bị gần đây -->
                    <div class="col-lg-5 col-sm-12 col-12 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                                <h4 class="card-title mb-0">Thiết bị được phân công</h4>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false"
                                        class="dropset">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </a>
                                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                        <li>
                                            <a href="maintenance.php" class="dropdown-item">Danh sách bảo trì</a>
                                        </li>
                                        <li>
                                            <a href="addmaintenance.php" class="dropdown-item">Thêm bảo trì</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive dataview">
                                    <table class="table datatable">
                                        <thead>
                                            <tr>
                                                <th>Id</th>
                                                <th>Mô tả</th>
                                                <th>Trạng thái</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($devices)): ?>
                                            <?php foreach ($devices as $device): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($device['device_id']) ?></td>
                                                <td><?= htmlspecialchars($device['description']) ?></td>
                                                <td><?= ucfirst(htmlspecialchars($device['status'])) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <tr>
                                                <td>-</td>
                                                <td>Không có thiết bị nào được phân công gần đây.</td>
                                                <td>-</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Kết thúc -->
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


    <!-- Kiểm tra lỗi tải và khởi tạo -->
    <script>
    $(document).ready(function() {
        // Xử lý biểu đồ
        var options = {
            series: [
                <?php echo $deviceActiveCount; ?>, // Thiết bị đang hoạt động
                <?php echo $deviceMaintenanceCount; ?>, // Thiết bị đang bảo trì
                <?php echo $deviceInactiveCount; ?> // Thiết bị lỗi
            ],
            chart: {
                type: 'pie',
                height: 350
            },
            labels: ['Đang hoạt động', 'Đang bảo trì', 'Lỗi'],
            colors: ['#28a745', '#ff9800', '#dc3545'], // Xanh, Cam, Đỏ
            legend: {
                position: 'bottom'
            },
            dataLabels: {
                enabled: true,
                formatter: function(val, opts) {
                    return opts.w.config.series[opts.seriesIndex] + " (" + Math.round(val) + "%)";
                }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 200
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }]
        };

        var chart = new ApexCharts(document.querySelector("#sales_charts"), options);
        chart.render();
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
    $(document).ready(function() {
        $('.counter-value').each(function() {
            var $this = $(this),
                countTo = $this.parent().data('count');
            $({
                countNum: $this.text()
            }).animate({
                countNum: countTo
            }, {
                duration: 10000,
                easing: 'swing',
                step: function() {
                    $this.text(Math.floor(this.countNum));
                },
                complete: function() {
                    $this.text(this.countNum);
                }
            });
        });
    });
    </script>
</body>

</html>