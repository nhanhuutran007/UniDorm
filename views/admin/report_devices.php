<?php
session_start();
// Path: http://localhost/network-management/views/admin/report_devices.php
require_once __DIR__ . '/../../controllers/DeviceController.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: /network-management/index.php?route=login");
    exit();
}

// Khởi tạo
$DeviceController = new DeviceController($_SESSION['user_id'], $_SESSION['role']);

// Lấy tham số lọc
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$timeFrame = isset($_GET['time_frame']) ? $_GET['time_frame'] : 'month';

// Lấy số liệu lịch sử từ devicestatusstats
$deviceStats = $DeviceController->getDeviceStatusStats($startDate, $endDate);
$eventTotalDevices = $deviceStats['success'] ? ($deviceStats['data']['total'] ?? 0) : 0;
$eventCreatedCount = $deviceStats['success'] ? ($deviceStats['data']['created'] ?? 0) : 0;
$eventUpdatedCount = $deviceStats['success'] ? ($deviceStats['data']['updated'] ?? 0) : 0;
$eventMaintenanceCount = $deviceStats['success'] ? ($deviceStats['data']['maintenance'] ?? 0) : 0;
$eventAssignedCount = $deviceStats['success'] ? ($deviceStats['data']['assigned'] ?? 0) : 0;
$eventReturnedCount = $deviceStats['success'] ? ($deviceStats['data']['returned'] ?? 0) : 0;
$eventStatusChangedCount = $deviceStats['success'] ? ($deviceStats['data']['status_changed'] ?? 0) : 0;

// Lấy số liệu hiện tại cho widget và biểu đồ tổng quan
$deviceCountResult = $DeviceController->requestDeviceCount('countdevice');
$deviceCountChart = $deviceCountResult['success'] ? ($deviceCountResult['data']['device_count'] ?? 0) : 0;
$totalActiveResult = $DeviceController->requestDeviceCount('totalactive');
$deviceActiveCountChart = $totalActiveResult['success'] ? ($totalActiveResult['data']['device_count'] ?? 0) : 0;
$totalInactiveResult = $DeviceController->requestDeviceCount('totalinactive');
$deviceInactiveCountChart = $totalInactiveResult['success'] ? ($totalInactiveResult['data']['device_count'] ?? 0) : 0;
$totalMaintenanceResult = $DeviceController->requestDeviceCount('totalmaintenance');
$deviceMaintenanceCountChart = $totalMaintenanceResult['success'] ? ($totalMaintenanceResult['data']['device_count'] ?? 0) : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Báo cáo thiết bị</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../assets/img/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"
        integrity="sha512-c42qTSw/wiW5oaDSLFhn5z7mS0bIX7PB87LWBRH5iA/YB4iR8v+QYq5uTNkO5D3n4CW4S996zAqRpWIcLtYAiRw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .table th, .table td {
            text-align: center;
            vertical-align: middle;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .table tbody tr:hover {
            background-color: #f1f1f1;
        }
        .filter-form .form-control, .filter-form .form-select {
            font-size: 14px;
        }
        .filter-form .btn {
            font-size: 14px;
            padding: 6px 12px;
        }
        .dash-widget {
            transition: transform 0.3s;
        }
        .dash-widget:hover {
            transform: translateY(-5px);
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
                <div class="row">
                    <!-- Widget Tổng quan -->
                    <div class="col-lg-3 col-sm-6 col-12">
                        <div class="dash-widget">
                            <div class="dash-widgetimg">
                                <span><img src="../../assets/img/icons/icon1.svg" alt="img" width="25px" height="25px"></span>
                            </div>
                            <div class="dash-widgetcontent">
                                <h5>
                                    <span class="counters" data-count="<?php echo $deviceCountChart; ?>">
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
                                <span><img src="../../assets/img/icons/icon2.svg" alt="img" width="18px" height="18px"></span>
                            </div>
                            <div class="dash-widgetcontent">
                                <h5>
                                    <span class="counters" data-count="<?php echo $deviceMaintenanceCountChart; ?>">
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
                                <span><img src="../../assets/img/icons/icon3.svg" alt="img" width="25px" height="25px"></span>
                            </div>
                            <div class="dash-widgetcontent">
                                <h5>
                                    <span class="counters" data-count="<?php echo $deviceActiveCountChart; ?>">
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
                                <span><img src="../../assets/img/icons/icon4.svg" alt="img" width="25px" height="25px"></span>
                            </div>
                            <div class="dash-widgetcontent">
                                <h5>
                                    <span class="counters" data-count="<?php echo $deviceInactiveCountChart; ?>">
                                        <span class="counter-value">0</span>
                                    </span>
                                </h5>
                                <h6>Thiết bị lỗi</h6>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Biểu đồ Pie Chart -->
                    <div class="col-lg-7 col-sm-12 col-12 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title mb-0">Thống Kê Sự Kiện Thiết Bị</h5>
                                    <p class="description">Phân bố các loại sự kiện thiết bị trong khoảng thời gian đã chọn.</p>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="device_stats_chart"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Bảng Tổng Quan Thiết Bị -->
                    <div class="col-lg-5 col-sm-12 col-12 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="card-title mb-0">Tổng Quan Thiết Bị</h4>
                                    <p class="description">Trạng thái hiện tại của các thiết bị trong hệ thống.</p>
                                </div>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false" class="dropset">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li><a href="maintenance.php" class="dropdown-item">Danh sách bảo trì</a></li>
                                        <li><a href="addmaintenance.php" class="dropdown-item">Thêm bảo trì</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive dataview">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Tổng thiết bị</th>
                                                <th>Đang hoạt động</th>
                                                <th>Đang bảo trì</th>
                                                <th>Lỗi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $totalPercent = 100;
                                            $activePercent = ($deviceCountChart > 0) ? round(($deviceActiveCountChart / $deviceCountChart) * 100, 2) : 0;
                                            $maintenancePercent = ($deviceCountChart > 0) ? round(($deviceMaintenanceCountChart / $deviceCountChart) * 100, 2) : 0;
                                            $inactivePercent = ($deviceCountChart > 0) ? round(($deviceInactiveCountChart / $deviceCountChart) * 100, 2) : 0;
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($deviceCountChart) ?> (<?= $totalPercent ?>%)</td>
                                                <td><?= htmlspecialchars($deviceActiveCountChart) ?> (<?= $activePercent ?>%)</td>
                                                <td><?= htmlspecialchars($deviceMaintenanceCountChart) ?> (<?= $maintenancePercent ?>%)</td>
                                                <td><?= htmlspecialchars($deviceInactiveCountChart) ?> (<?= $inactivePercent ?>%)</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bảng Thống Kê Sự Kiện Thiết Bị -->
                    <div class="col-lg-12 col-sm-12 col-12 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="card-title mb-0">Thống Kê Sự Kiện Thiết Bị</h4>
                                    <p class="description">Tổng hợp các sự kiện (tạo mới, cập nhật, bảo trì, v.v.) của thiết bị trong khoảng thời gian đã chọn.</p>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-top">
                                    <div class="search-set">
                                        <form method="GET" action="" class="d-flex align-items-center">
                                            <input type="date" name="start_date" class="form-control me-2" value="<?= htmlspecialchars($startDate) ?>">
                                            <input type="date" name="end_date" class="form-control me-2" value="<?= htmlspecialchars($endDate) ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">Lọc</button>
                                        </form>
                                    </div>
                                    <div class="wordset">
                                        <ul>
                                            <li>
                                                <a data-pdf-button="true" data-data="device_stats" data-bs-toggle="tooltip"
                                                data-bs-placement="top" title="Xuất PDF"><img
                                                src="../../assets/img/icons/pdf.svg" alt="pdf">
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table datanew">
                                        <thead>
                                            <tr>
                                                <th title="Tổng số thiết bị trong hệ thống">Tổng thiết bị</th>
                                                <th title="Số lần tạo mới thiết bị">Tạo mới</th>
                                                <th title="Số lần cập nhật thông tin thiết bị">Cập nhật</th>
                                                <th title="Số lần bảo trì thiết bị">Bảo trì</th>
                                                <th title="Số lần gán thiết bị cho người dùng">Gán thiết bị</th>
                                                <th title="Số lần trả thiết bị">Trả thiết bị</th>
                                                <th title="Số lần thay đổi trạng thái thiết bị">Thay đổi trạng thái</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?= htmlspecialchars($eventTotalDevices) ?></td>
                                                <td><?= htmlspecialchars($eventCreatedCount) ?></td>
                                                <td><?= htmlspecialchars($eventUpdatedCount) ?></td>
                                                <td><?= htmlspecialchars($eventMaintenanceCount) ?></td>
                                                <td><?= htmlspecialchars($eventAssignedCount) ?></td>
                                                <td><?= htmlspecialchars($eventReturnedCount) ?></td>
                                                <td><?= htmlspecialchars($eventStatusChangedCount) ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
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
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
    <script src="../../assets/js/script.js"></script>

    <script>
        $(document).ready(function () {
            // Biểu đồ Pie Chart cho sự kiện thiết bị
            var options = {
                series: [
                    <?php echo $eventCreatedCount; ?>,
                    <?php echo $eventUpdatedCount; ?>,
                    <?php echo $eventMaintenanceCount; ?>,
                    <?php echo $eventAssignedCount; ?>,
                    <?php echo $eventReturnedCount; ?>,
                    <?php echo $eventStatusChangedCount; ?>
                ],
                chart: {
                    type: 'pie',
                    height: 350
                },
                labels: ['Tạo mới', 'Cập nhật', 'Bảo trì', 'Gán thiết bị', 'Trả thiết bị', 'Thay đổi trạng thái'],
                colors: ['#28a745', '#007bff', '#ff9800', '#17a2b8', '#dc3545', '#6c757d'],
                legend: {
                    position: 'bottom'
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val, opts) {
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
            var chart = new ApexCharts(document.querySelector("#device_stats_chart"), options);
            chart.render();
            // Hàm gắn sự kiện PDF, hỗ trợ nhiều thuộc tính
            function bindPdfEvent() {
                const selector = '.wordset a[data-pdf-button="true"]';
                console.log('Binding PDF event, button count:', $(selector).length);
                $(document).off('click', selector).on('click', selector, function(e) {
                    e.preventDefault();
                    var dataType = $(this).data('data');
                    var statusFilter = $('#statusFilter').val();
                    var startDate = $('input[name="start_date"]').val();
                    var endDate = $('input[name="end_date"]').val();
                    console.log('PDF button clicked, data:', dataType, 'status:', statusFilter, 'start_date:', startDate, 'end_date:', endDate);
                    $.ajax({
                        url: '/network-management/report/export_pdf.php',
                        type: 'POST',
                        data: {
                            type: 'pdf',
                            data: dataType,
                            statusFilter: statusFilter,
                            start_date: startDate,
                            end_date: endDate
                        },
                        xhrFields: {
                            responseType: 'blob'
                        },
                        success: function(response, status, xhr) {
                            console.log('PDF generated successfully');
                            var blob = new Blob([response], { type: 'application/pdf' });
                            var link = document.createElement('a');
                            link.href = window.URL.createObjectURL(blob);
                            link.download = 'thong_ke_su_kien_thiet_bi.pdf';
                            link.click();
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error:', xhr.status, xhr.responseText);
                            try {
                                var response = JSON.parse(xhr.responseText);
                                showToast(errorToast, 'Lỗi khi xuất file PDF: ' + response.message);
                            } catch (e) {
                                showToast(errorToast, 'Lỗi khi xuất file PDF: ' + error + ' (Status: ' + xhr.status + ')');
                            }
                        }
                    });
                });
            }
            bindPdfEvent();

            // Kiểm tra thư viện
            if (typeof jQuery === 'undefined') {
                console.error('jQuery không tải được');
            } else {
                console.log('jQuery đã tải thành công');
                feather.replace();
            }
            if (typeof ApexCharts === 'undefined') {
                console.error('ApexCharts không tải được');
            } else {
                console.log('ApexCharts đã tải thành công');
            }

            // Hiệu ứng đếm số
            $('.counter-value').each(function () {
                var $this = $(this),
                    countTo = $this.parent().data('count');
                $({ countNum: $this.text() }).animate({
                    countNum: countTo
                }, {
                    duration: 10000,
                    easing: 'swing',
                    step: function () {
                        $this.text(Math.floor(this.countNum));
                    },
                    complete: function () {
                        $this.text(this.countNum);
                    }
                });
            });

            // Khởi tạo tooltip của Bootstrap
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>

</html>