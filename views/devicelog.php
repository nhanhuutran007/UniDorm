<?php
//path : network-management/views/devicelog.php
    require_once __DIR__ . '/../controllers/DeviceController.php';

    // Kiểm tra đăng nhập
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: /network-management/auth/login.php");
        exit();
    }

    // Khởi tạo controller
    $controller = new DeviceController($_SESSION['user_id'], $_SESSION['role']);

    // Lấy device_id từ query string
    $deviceId = filter_input(INPUT_GET, 'device_id', FILTER_VALIDATE_INT);
    if ($deviceId === false || $deviceId === null) {
        die("Device ID không hợp lệ hoặc thiếu trong URL.");
    }

    // Lấy danh sách nhật ký
    $result =  $controller->handleRequest('get_log', [
        'device_id' => $deviceId,
        'limit' => 10,
        'offset' => 0
    ]);

    $logs = [];
    $error_message = '';
    if (isset($result['success']) && $result['success']) {
        $logs = $result['data'] ?? [];
    } else {
        $error_message = $result['message'] ?? 'Failed to retrieve device logs.';
        $_SESSION['error_message'] = $error_message;
    }
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Nhật ký thiết bị #<?= htmlspecialchars($deviceId) ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicon.svg">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"
        integrity="sha512-c42qTSw/wiW5oaDSLFhn5z7mS0bIX7PB87LWBRH5iA/YB4iR8v+QYq5uTNkO5D3n4CW4S996zAqRpWIcLtYAiRw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <div class="main-wrapper">
        <?php require_once(__DIR__ . '/../includes/header.php'); ?>
        <?php require_once(__DIR__ . '/../includes/sidebarAll.php'); ?>

        <div class="page-wrapper">
            <div class="content">
                <div class="page-header">
                    <div class="page-title">
                        <h4>Nhật ký thiết bị #<?= htmlspecialchars($deviceId) ?></h4>
                        <h6>Xem lịch sử hoạt động của thiết bị</h6>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="table-top">
                            <div class="search-set">
                                <div class="search-path">
                                    <!-- Có thể thêm bộ lọc nếu cần, ví dụ: lọc theo loại sự kiện -->
                                </div>
                            </div>
                            <div class="wordset">
                                <ul>
                                    <li><a data-pdf-button="true" data-type="pdf" data-data="devicelog"
                                            data-bs-toggle="tooltip" data-bs-placement="top" title="Xuất PDF"><img
                                                src="../assets/img/icons/pdf.svg" alt="pdf"></a></li>
                                    <li><a data-excel-button="true" data-data="devicelog" data-bs-toggle="tooltip"
                                            data-bs-placement="top" title="Xuất Excel"><img
                                                src="../assets/img/icons/excel.svg" alt="excel"></a></li>
                                </ul>
                            </div>
                        </div>
                        <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table datanew">
                                <thead>
                                    <tr>
                                        <th>ID Log</th>
                                        <th>Thiết Bị</th>
                                        <th>Người Dùng</th>
                                        <th>Loại Sự Kiện</th>
                                        <th>Ngày Sự Kiện</th>
                                        <th>Trạng Thái Trước</th>
                                        <th>Trạng Thái Sau</th>
                                        <th>Chi Tiết</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($logs)): ?>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($log['log_id']) ?></td>
                                        <td><?= htmlspecialchars($log['device_id']) ?></td>
                                        <td><?= htmlspecialchars($log['user_id']) ?></td>
                                        <td><?= htmlspecialchars($log['event_type']) ?></td>
                                        <td><?= htmlspecialchars($log['event_date']) ?></td>
                                        <td><?= htmlspecialchars($log['previous_status'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($log['new_status'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($log['details'] ?? '') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td>Không có nhật ký nào được tìm thấy.</td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.1/feather.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
    <script src="../assets/js/script.js"></script>

    <script>
    $(document).ready(function() {
        if (typeof jQuery === 'undefined') {
            console.error('jQuery không tải được');
        } else {
            console.log('jQuery đã tải thành công');
        }

        if (typeof feather !== 'undefined') {
            feather.replace();
        } else {
            console.error('Feather Icons không tải được');
        }

        if ($.fn.DataTable) {
            if ($.fn.DataTable.isDataTable('.datanew')) {
                $('.datanew').DataTable().destroy();
            }
            $('.datanew').DataTable();
        } else {
            console.error('DataTables không tải được');
        }

        $('.submenu-toggle').click(function() {
            $(this).next('.submenu-list').slideToggle();
            $(this).find('.menu-arrow').toggleClass('rotate');
        });

        // Xử lý thay đổi bộ lọc loại sự kiện (nếu cần)
        $('#eventTypeFilter').on('change', function() {
            var eventType = $(this).val();
            // Có thể thêm logic để tải lại bảng với bộ lọc loại sự kiện
            console.log('Lọc theo loại sự kiện:', eventType);
        });

        function bindPdfEvent() {
            const selector = '.wordset a[data-pdf-button="true"]';
            console.log('Binding PDF event, button count:', $(selector).length);
            $(document).off('click', selector).on('click', selector, function(e) {
                e.preventDefault();
                e.stopPropagation();
                var type = $(this).data('type');
                var dataType = $(this).data('data');
                var statusFilter = $('select[name="status"]').val() || '';
                var deviceId = <?= json_encode($deviceId) ?>; // Lấy device_id từ PHP
                console.log('PDF button clicked, type:', type, 'data:', dataType, 'status:',
                    statusFilter, 'device_id:', deviceId);
                $.ajax({
                    url: '/network-management/report/export_pdf.php', // Sửa URL
                    type: 'POST',
                    data: {
                        type: type,
                        data: dataType,
                        statusFilter: statusFilter,
                        device_id: deviceId // Thêm device_id
                    },
                    xhrFields: {
                        responseType: 'blob'
                    },
                    success: function(response, status, xhr) {
                        console.log('PDF generated successfully');
                        var blob = new Blob([response], {
                            type: 'application/pdf'
                        });
                        var link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = 'nhat_ky_thiet_bi.pdf'; // Sửa tên file
                        link.click();
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', xhr.status, xhr.responseText);
                        try {
                            var response = JSON.parse(xhr.responseText);
                            alert('Lỗi khi xuất file PDF: ' + response.message);
                        } catch (e) {
                            alert('Lỗi khi xuất file PDF: ' + error + ' (Status: ' + xhr
                                .status + ')');
                        }
                    }
                });
            });
        }

        bindPdfEvent();

        function bindExcelEvent() {
            const selector = '.wordset a[data-excel-button="true"]';
            console.log('Binding Excel event, button count:', $(selector).length);
            $(document).off('click', selector).on('click', selector, function(e) {
                e.preventDefault();
                var dataType = $(this).data('data');
                var statusFilter = $('#statusFilter').val() || '';
                var deviceId = <?= json_encode($deviceId) ?>;
                console.log('Excel button clicked, data:', dataType, 'status:', statusFilter,
                    'device_id:', deviceId);
                $.ajax({
                    url: '/network-management/report/export_excel.php',
                    type: 'POST',
                    data: {
                        type: 'excel',
                        data: dataType,
                        statusFilter: statusFilter,
                        device_id: deviceId
                    },
                    xhrFields: {
                        responseType: 'blob'
                    },
                    success: function(response, status, xhr) {
                        console.log('Excel generated successfully');
                        var blob = new Blob([response], {
                            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        });
                        var link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = 'nhat_ky_thiet_bi.xlsx';
                        link.click();
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', xhr.status, xhr.responseText);
                        try {
                            var response = JSON.parse(xhr.responseText);
                            alert('Lỗi khi xuất file Excel: ' + response.message);
                        } catch (e) {
                            alert('Lỗi khi xuất file Excel: ' + error + ' (Status: ' + xhr
                                .status + ')');
                        }
                    }
                });
            });
        }
        bindExcelEvent();


        function bindActionEvents() {
            // Xử lý toggle status
            $('.toggle-status').off('click').on('click', function() {
                var deviceId = $(this).data('device-id');
                if (confirm('Bạn có muốn thay đổi trạng thái thiết bị?')) {
                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            device_id: deviceId,
                            action: 'toggle_status'
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                alert(response.message);
                                reloadPageWrapper($('#statusFilter').val());
                            } else {
                                alert('Lỗi: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            alert('Lỗi: ' + error);
                        }
                    });
                }
            });




            // Xử lý thay đổi statusFilter
            $('#statusFilter').off('change').on('change', function() {
                var statusFilter = $(this).val();
                reloadPageWrapper(statusFilter);
            });
        }


        bindActionEvents();

    });
    </script>
</body>

</html>