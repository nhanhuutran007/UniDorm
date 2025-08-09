<?php
// path: views/admin/test.php
require_once __DIR__ . '/../../controllers/DeviceController.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /network-management/auth/login.php");
    exit();
}

// Khởi tạo DeviceController
$DeviceController = new DeviceController($_SESSION['user_id'], $_SESSION['role']);

// Kiểm tra xem có phải là yêu cầu AJAX để lấy nội dung page-wrapper không
$isAjaxPartial = isset($_GET['partial']) && $_GET['partial'] === 'page-wrapper';

// Xử lý statusFilter
$statusFilter = isset($_GET['statusFilter']) ? trim($_GET['statusFilter']) : '';
$searchParams = ['status' => ['active', 'maintenance']];
if ($statusFilter === 'active' || $statusFilter === 'maintenance') {
    $searchParams = ['status' => [$statusFilter]];
} elseif ($statusFilter !== '') {
    $searchParams = ['status' => []]; // Không trả về thiết bị nào nếu statusFilter không hợp lệ
}

if (!$isAjaxPartial) {
    // Lấy danh sách thiết bị cho toàn bộ trang
    $result = $DeviceController->handleRequest('get', ['search' => $searchParams, 'limit' => 1000, 'offset' => 0]);
    $devices = $result['success'] ? $result['data'] : [];

    if ($result['success']) {
        $devices = $result['data'] ?? [];
        foreach ($devices as &$device) {
            $logResult = $DeviceController->handleRequest('get_log', [
                'device_id' => $device['device_id'],
                'limit' => 1,
                'offset' => 0
            ]);
            if (isset($logResult['success']) && $logResult['success'] && !empty($logResult['data'])) {
                $device['latest_log'] = $logResult['data'][0] ?? null;
            } else {
                $device['latest_log'] = null;
            }
        }
        unset($device);
    }

    // Xử lý POST request
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (!isset($_POST['device_id']) || empty($_POST['device_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thiết bị để xử lý!']);
            ob_end_flush();
            exit();
        }

        $deviceId = filter_input(INPUT_POST, 'device_id', FILTER_VALIDATE_INT);
        if ($deviceId === false || $deviceId === null) {
            echo json_encode(['status' => 'error', 'message' => 'ID thiết bị không hợp lệ!']);
            ob_end_flush();
            exit();
        }
        $action = $_POST['action'] ?? '';

        if ($action === 'toggle_status') {
            $device = null;
            foreach ($devices as $d) {
                if ($d['device_id'] == $deviceId) {
                    $device = $d;
                    break;
                }
            }
            if (!$device) {
                echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thiết bị trong danh sách!']);
                ob_end_flush();
                exit();
            }
            $current_status = $device['status'];
            $new_status = ($current_status === 'active') ? 'maintenance' : 'active';

            // Cập nhật trạng thái thiết bị
            $params = [
                'device_id' => $deviceId,
                'data' => [
                    'status' => $new_status,
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ];
            $statusResult = $DeviceController->handleRequest('update', $params);

            // Nếu chuyển sang trạng thái bảo trì, thêm yêu cầu bảo trì
            $maintenanceResult = ['success' => true, 'message' => 'Không cần thêm bảo trì'];
            if ($current_status === 'active' && $new_status === 'maintenance') {
                try {
                    $maintenanceDate = date('Y-m-d'); // Ngày hiện tại
                    $description = "N/A";

                    // Tạo dữ liệu bảo trì
                    $maintenanceData = [
                        'device_id' => $deviceId,
                        'maintenance_date' => $maintenanceDate,
                        'description' => htmlspecialchars($description, ENT_QUOTES, 'UTF-8')
                    ];

                    // Gọi handleRequest để thêm bảo trì
                    $maintenanceResult = $DeviceController->handleRequest('add_maintenance', ['data' => $maintenanceData]);

                    if (!$maintenanceResult['success']) {
                        // Nếu thêm bảo trì thất bại, có thể rollback trạng thái hoặc thông báo lỗi
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Cập nhật trạng thái thành công nhưng thêm bảo trì thất bại: ' . $maintenanceResult['message']
                        ]);
                        ob_end_flush();
                        exit();
                    }
                } catch (Exception $e) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Cập nhật trạng thái thành công nhưng thêm bảo trì thất bại: ' . $e->getMessage()
                    ]);
                    ob_end_flush();
                    exit();
                }
            }

            if ($statusResult['success']) {
                $message = "Đã " . ($new_status === 'active' ? 'kích hoạt' : 'bảo trì') . " thiết bị thành công!";
                if ($new_status === 'maintenance') {
                    $message .= " Yêu cầu bảo trì đã được thêm.";
                }
                echo json_encode(['status' => 'success', 'message' => $message]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Không thể cập nhật trạng thái: ' . $statusResult['message']]);
            }
            ob_end_flush();
            exit();
        } elseif ($action === 'delete') {
            $params = ['device_id' => $deviceId];
            $result = $DeviceController->handleRequest('delete', $params);
            echo json_encode([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message']
            ]);
            ob_end_flush();
            exit();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
            ob_end_flush();
            exit();
        }
    }
} else {
    // Yêu cầu AJAX để lấy nội dung page-wrapper
    $result = $DeviceController->handleRequest('get', ['search' => $searchParams, 'limit' => 100, 'offset' => 0]);
    $devices = $result['success'] ? $result['data'] : [];

    if ($result['success']) {
        $devices = $result['data'] ?? [];
        foreach ($devices as &$device) {
            $logResult = $DeviceController->handleRequest('get_log', [
                'device_id' => $device['device_id'],
                'limit' => 1,
                'offset' => 0
            ]);
            if (isset($logResult['success']) && $logResult['success'] && !empty($logResult['data'])) {
                $device['latest_log'] = $logResult['data'][0] ?? null;
            } else {
                $device['latest_log'] = null;
            }
        }
        unset($device);
    }
    ob_start();
    ?>
<div class="page-wrapper">
    <div class="content">
        <div class="page-header">
            <div class="page-title">
                <h4>Danh sách thiết bị</h4>
                <h6>Quản lý danh sách thiết bị</h6>
            </div>
            <div class="page-btn">
                <a href="addDevice.php" class="btn btn-added">
                    <img src="../../assets/img/icons/plus.svg" alt="img" class="me-1">Thêm thiết bị
                </a>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-top">
                    <div class="search-set">
                        <div class="search-path">
                            <select id="statusFilter" class="form-select">
                                <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Tất cả trạng thái
                                </option>
                                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Hoạt động
                                </option>
                                <option value="maintenance" <?= $statusFilter === 'maintenance' ? 'selected' : '' ?>>Bảo
                                    trì</option>
                            </select>
                        </div>
                    </div>
                    <div class="wordset">
                        <ul>
                            <li><a data-bs-toggle="tooltip" title="pdf"><img src="../../assets/img/icons/pdf.svg"
                                        alt="pdf"></a></li>
                            <li><a data-bs-toggle="tooltip" title="excel"><img src="../../assets/img/icons/excel.svg"
                                        alt="excel"></a></li>
                        </ul>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table datanew">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Hình ảnh</th>
                                <th>Tên thiết bị</th>
                                <th>Loại</th>
                                <th>Địa chỉ IP</th>
                                <th>Địa chỉ MAC</th>
                                <th>Trạng thái</th>
                                <th>Vị trí</th>
                                <th>Giá tiền</th>
                                <th>Chức năng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devices as $device): ?>
                            <?php if (!empty($device['device_type'])): ?>
                            <tr data-current-status="<?= htmlspecialchars($device['latest_log']['new_status'] ?? 'N/A') ?>"
                                data-latest-event="<?= htmlspecialchars($device['latest_log']['event_type'] ?? 'N/A') ?>"
                                data-latest-details="<?= htmlspecialchars($device['latest_log']['details'] ?? 'N/A') ?>">
                                <td><?= htmlspecialchars($device['device_id']) ?></td>
                                <td><img src="<?= htmlspecialchars($device['image'] ?? '../../assets/images/devices/laptop-solid.svg') ?>"
                                        class="avatar"></td>
                                <td><?= htmlspecialchars($device['device_name']) ?></td>
                                <td><?= htmlspecialchars($device['device_type']) ?></td>
                                <td><?= htmlspecialchars($device['ip_address']) ?></td>
                                <td><?= htmlspecialchars($device['mac_address']) ?></td>
                                <td><?= ucfirst(htmlspecialchars($device['status'])) ?></td>
                                <td><?= htmlspecialchars($device['location']) ?></td>
                                <td><?= number_format($device['price'] ?? 0, 2) ?> VND</td>
                                <td>
                                    <a class="me-3" href="updatedevice.php?device_id=<?= $device['device_id'] ?>">
                                        <img src="../../assets/img/icons/edit.svg" alt="edit">
                                    </a>
                                    <a class="me-3 toggle-status" data-device-id="<?= $device['device_id'] ?>"
                                        href="javascript:void(0);">
                                        <img src="../../assets/img/icons/toggleon.svg" alt="toggle status" width="29"
                                            height="29">
                                    </a>
                                    <a class="me-3 delete-device" data-device-id="<?= $device['device_id'] ?>"
                                        href="javascript:void(0);">
                                        <img src="../../assets/img/icons/delete.svg" alt="delete">
                                    </a>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    $content = ob_get_clean();
    echo $content;
    exit();
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Trang quản lý thiết bị</title>
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
                <!-- Toast container for notifications -->
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
                <div class="page-header">
                    <div class="page-title">
                        <h4>Danh sách thiết bị</h4>
                        <h6>Quản lý danh sách thiết bị</h6>
                    </div>
                    <div class="page-btn">
                        <a href="addDevice.php" class="btn btn-added">
                            <img src="../../assets/img/icons/plus.svg" alt="img" class="me-1">Thêm thiết bị
                        </a>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="table-top">
                            <div class="search-set">
                                <div class="search-path">
                                    <select id="statusFilter" class="form-select">
                                        <option value="">Tất cả trạng thái</option>
                                        <option value="active">Hoạt động</option>
                                        <option value="maintenance">Bảo trì</option>
                                    </select>
                                </div>
                            </div>
                            <div class="wordset">
                                <ul>
                                    <li>
                                        <a data-pdf-button="true" data-data="devices" data-bs-toggle="tooltip"
                                            data-bs-placement="top" title="Xuất PDF"><img
                                                src="../../assets/img/icons/pdf.svg" alt="pdf">
                                        </a>
                                    </li>
                                    <li><a data-excel-button="true" data-data="devices" data-bs-toggle="tooltip"
                                            data-bs-placement="top" title="Xuất Excel"><img
                                                src="../../assets/img/icons/excel.svg" alt="excel"></a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table datanew">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Hình ảnh</th>
                                        <th>Tên thiết bị</th>
                                        <th>Loại</th>
                                        <th>Địa chỉ IP</th>
                                        <th>Địa chỉ MAC</th>
                                        <th>Trạng thái</th>
                                        <th>Vị trí</th>
                                        <th>Giá tiền</th>
                                        <th>Chức năng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($devices as $device): ?>
                                    <?php if (!empty($device['device_type'])): ?>
                                    <tr data-current-status="<?= htmlspecialchars($device['latest_log']['new_status'] ?? 'N/A') ?>"
                                        data-latest-event="<?= htmlspecialchars($device['latest_log']['event_type'] ?? 'N/A') ?>"
                                        data-latest-details="<?= htmlspecialchars($device['latest_log']['details'] ?? 'N/A') ?>">
                                        <td><?= htmlspecialchars($device['device_id']) ?></td>
                                        <td><img src="<?= htmlspecialchars($device['image'] ?? '../../assets/images/devices/laptop-solid.svg') ?>"
                                                class="avatar"></td>
                                        <td><?= htmlspecialchars($device['device_name']) ?></td>
                                        <td><?= htmlspecialchars($device['device_type']) ?></td>
                                        <td><?= htmlspecialchars($device['ip_address']) ?></td>
                                        <td><?= htmlspecialchars($device['mac_address']) ?></td>
                                        <td><?= ucfirst(htmlspecialchars($device['status'])) ?></td>
                                        <td><?= htmlspecialchars($device['location']) ?></td>
                                        <td><?= number_format($device['price'] ?? 0, 2) ?> VND</td>
                                        <td>
                                            <a class="me-3"
                                                href="updatedevice.php?device_id=<?= $device['device_id'] ?>">
                                                <img src="../../assets/img/icons/edit.svg" alt="edit">
                                            </a>
                                            <a class="me-3 toggle-status" data-device-id="<?= $device['device_id'] ?>"
                                                href="javascript:void(0);">
                                                <img src="../../assets/img/icons/toggleon.svg" alt="toggle status"
                                                    width="29" height="29">
                                            </a>
                                            <a class="me-3 delete-device" data-device-id="<?= $device['device_id'] ?>"
                                                href="javascript:void(0);">
                                                <img src="../../assets/img/icons/delete.svg" alt="delete">
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="deviceModal" tabindex="-1" aria-labelledby="deviceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deviceModalLabel">Thông tin thiết bị</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <img id="deviceImage" src="" alt="Device Image" style="max-width: 150px; max-height: 150px;">
                    </div>
                    <p><strong>Tên thiết bị:</strong> <span id="deviceName"></span></p>
                    <p><strong>Loại thiết bị:</strong> <span id="deviceType"></span></p>
                    <p><strong>Trạng thái hiện tại:</strong> <span id="currentStatus"></span></p>
                    <p><strong>Chi tiết log thiết bị:</strong> <span id="logDetails"></span></p>
                    <p><strong>Hiệu suất thiết bị:</strong> <span id="devicePerformance">Chưa có</span></p>
                    <p><strong>Sự kiện gần nhất:</strong> <span id="latestEvent"></span></p>
                </div>
                <div class="modal-footer">
                    <a id="viewDetailsBtn" href="#" class="btn btn-primary">Xem chi tiết</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
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
    <script src="../../assets/js/script.js"></script>

    <script>
    $(document).ready(function() {
        // Kiểm tra jQuery
        if (typeof jQuery === 'undefined') {
            console.error('jQuery không tải được');
        } else {
            console.log('jQuery đã tải thành công');
        }

        // Initialize Bootstrap Toasts
        const successToastElement = document.getElementById('successToast');
        const errorToastElement = document.getElementById('errorToast');
        const successToast = new bootstrap.Toast(successToastElement, {
            delay: 4000
        });
        const errorToast = new bootstrap.Toast(errorToastElement, {
            delay: 4000
        });

        function showToast(toast, message) {
            toast._element.querySelector('.toast-body').textContent = message;
            toast.show();
        }

        // Vô hiệu hóa tooltip ngay lập tức
        $('[data-bs-toggle="tooltip"]').tooltip('dispose').removeAttr('data-bs-toggle').removeAttr(
            'data-bs-original-title').removeAttr('aria-label');

        // Hàm debug DOM chi tiết
        function debugDom() {
            console.log('Debug DOM: .wordset exists:', $('.wordset').length);
            console.log('Debug DOM: .wordset a[title="pdf"] exists:', $('.wordset a[title="pdf"]').length);
            console.log('Debug DOM: .wordset a[data-bs-original-title="pdf"] exists:', $(
                '.wordset a[data-bs-original-title="pdf"]').length);
            console.log('Debug DOM: .wordset a[aria-label="pdf"] exists:', $('.wordset a[aria-label="pdf"]')
                .length);
            console.log('Debug DOM: .wordset a exists:', $('.wordset a').length);
            if ($('.wordset a').length > 0) {
                console.log('Debug DOM: .wordset a titles:', $('.wordset a').map(function() {
                    return $(this).attr('title') || 'no-title';
                }).get());
                console.log('Debug DOM: .wordset a data-bs-original-titles:', $('.wordset a').map(function() {
                    return $(this).attr('data-bs-original-title') || 'no-data-bs-original-title';
                }).get());
                console.log('Debug DOM: .wordset a aria-labels:', $('.wordset a').map(function() {
                    return $(this).attr('aria-label') || 'no-aria-label';
                }).get());
                console.log('Debug DOM: .wordset a HTML:', $('.wordset').html());
            }
        }

        // Gọi debug DOM ban đầu
        debugDom();

        // Hàm gắn sự kiện PDF
        function bindPdfEvent() {
            const selector = '.wordset a[data-pdf-button="true"]';
            console.log('Binding PDF event, button count:', $(selector).length);
            $(document).off('click', selector).on('click', selector, function(e) {
                e.preventDefault();
                var dataType = $(this).data('data');
                var statusFilter = $('#statusFilter').val();
                console.log('PDF button clicked, data:', dataType, 'status:', statusFilter);
                $.ajax({
                    url: '/network-management/report/export_pdf.php',
                    type: 'POST',
                    data: {
                        type: 'pdf',
                        data: dataType,
                        statusFilter: statusFilter
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
                        link.download = 'danh_sach_thiet_bi.pdf';
                        link.click();
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', xhr.status, xhr.responseText);
                        try {
                            var response = JSON.parse(xhr.responseText);
                            showToast(errorToast, 'Lỗi khi xuất file PDF: ' + response
                                .message);
                        } catch (e) {
                            showToast(errorToast, 'Lỗi khi xuất file PDF: ' + error +
                                ' (Status: ' + xhr.status + ')');
                        }
                    }
                });
            });
        }
        bindPdfEvent();

        // Hàm gắn sự kiện Excel
        function bindExcelEvent() {
            const selector = '.wordset a[data-excel-button="true"]';
            console.log('Binding Excel event, button count:', $(selector).length);
            $(document).off('click', selector).on('click', selector, function(e) {
                e.preventDefault();
                var dataType = $(this).data('data');
                var statusFilter = $('#statusFilter').val();
                console.log('Excel button clicked, data:', dataType, 'status:', statusFilter);
                $.ajax({
                    url: '/network-management/report/export_excel.php',
                    type: 'POST',
                    data: {
                        type: 'excel',
                        data: dataType,
                        statusFilter: statusFilter
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
                        link.download = 'danh_sach_thiet_bi.xlsx';
                        link.click();
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', xhr.status, xhr.responseText);
                        try {
                            var response = JSON.parse(xhr.responseText);
                            showToast(errorToast, 'Lỗi khi xuất file Excel: ' + response
                                .message);
                        } catch (e) {
                            showToast(errorToast, 'Lỗi khi xuất file Excel: ' + error +
                                ' (Status: ' + xhr.status + ')');
                        }
                    }
                });
            });
        }
        bindExcelEvent();

        // Hàm khởi tạo DataTable
        function initializeDataTable() {
            if ($.fn.DataTable.isDataTable('.datanew')) {
                $('.datanew').DataTable().destroy();
            }
            var table = $('.datanew').DataTable({
                pageLength: 10, // Hiển thị 10 hàng mỗi trang
                drawCallback: function() {
                    debugDom();
                    bindPdfEvent();
                    bindActionEvents();
                    $('[data-bs-toggle="tooltip"]').tooltip('dispose').removeAttr('data-bs-toggle')
                        .removeAttr('data-bs-original-title').removeAttr('aria-label');
                }
            });

            // Gắn sự kiện click cho hàng để hiển thị modal
            $('.datanew tbody').off('click', 'tr').on('click', 'tr', function(e) {
                if ($(e.target).is('a') || $(e.target).is('img')) {
                    return;
                }
                var data = table.row(this).data();
                var deviceId = data[0];
                var deviceName = data[2];
                var deviceType = data[3];
                var imagePath = data[1].match(/src="([^"]+)"/)[1];
                var currentStatus = $(this).data('current-status');
                var latestEvent = $(this).data('latest-event');
                var logDetails = $(this).data('latest-details') || 'N/A';

                console.log('logDetails:', logDetails); // Debug giá trị details

                showDeviceModal(deviceId, deviceName, currentStatus, latestEvent, deviceType, imagePath,
                    logDetails);
            });
        }

        // Khởi tạo DataTable
        initializeDataTable();

        // Hàm tải lại nội dung page-wrapper
        function reloadPageWrapper(statusFilter = '') {
            $.ajax({
                url: window.location.pathname + '?partial=page-wrapper' + (statusFilter ?
                    '&statusFilter=' + encodeURIComponent(statusFilter) : ''),
                type: 'GET',
                success: function(response) {
                    $('.page-wrapper').replaceWith(response);
                    debugDom();
                    initializeDataTable();
                    bindActionEvents();
                    bindPdfEvent();
                    $('[data-bs-toggle="tooltip"]').tooltip('dispose').removeAttr('data-bs-toggle')
                        .removeAttr('data-bs-original-title').removeAttr('aria-label');
                    if (typeof feather !== 'undefined') feather.replace();
                },
                error: function(xhr, status, error) {
                    console.error('Lỗi khi tải lại page-wrapper:', error);
                    showToast(errorToast, 'Không thể tải lại nội dung. Vui lòng thử lại.');
                }
            });
        }

        // Hàm gắn sự kiện cho toggle-status, delete-device và statusFilter
        function bindActionEvents() {
            $(document).off('click', '.datanew .toggle-status').on('click', '.datanew .toggle-status',
                function() {
                    console.log('Toggle status clicked for device ID:', $(this).data('device-id'));
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
                                    showToast(successToast, response.message);
                                    reloadPageWrapper($('#statusFilter').val());
                                } else {
                                    showToast(errorToast, 'Lỗi: ' + response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                showToast(errorToast, 'Lỗi: ' + error);
                            }
                        });
                    }
                });

            $(document).off('click', '.datanew .delete-device').on('click', '.datanew .delete-device',
                function() {
                    console.log('Delete device clicked for device ID:', $(this).data('device-id'));
                    var deviceId = $(this).data('device-id');
                    if (confirm('Bạn muốn xóa thiết bị này?')) {
                        $.ajax({
                            url: window.location.href,
                            type: 'POST',
                            data: {
                                device_id: deviceId,
                                action: 'delete'
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.status === 'success') {
                                    showToast(successToast, response.message);
                                    reloadPageWrapper($('#statusFilter').val());
                                } else {
                                    showToast(errorToast, 'Lỗi: ' + response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                showToast(errorToast, 'Lỗi: ' + error);
                            }
                        });
                    }
                });

            $('#statusFilter').off('change').on('change', function() {
                var statusFilter = $(this).val();
                reloadPageWrapper(statusFilter);
            });
        }

        // Gắn sự kiện ban đầu
        bindActionEvents();

        // Xử lý sự kiện click vào nút "edit"
        $('a[href^="updatedevice.php"]').click(function(event) {
            var deviceId = $(this).attr('href').split('device_id=')[1];
            console.log('Device ID for edit:', deviceId);
        });
    });

    function showDeviceModal(deviceId, deviceName, currentStatus, latestEvent, deviceType, imagePath, logDetails) {
        $('#deviceImage').attr('src', imagePath);
        $('#deviceName').text(deviceName);
        $('#deviceType').text(deviceType);
        $('#currentStatus').text(currentStatus);
        $('#logDetails').text(logDetails);
        $('#devicePerformance').text('Chưa có');
        $('#latestEvent').text(latestEvent);
        $('#viewDetailsBtn').attr('href', '/network-management/views/devicelog.php?device_id=' + deviceId);
        $('#deviceModal').modal('show');
    }
    </script>
</body>

</html>