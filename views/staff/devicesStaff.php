<?php
// path: views/staff/devicesStaff.php
require_once __DIR__ . '/../../controllers/DeviceController.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /network-management/auth/login.php");
    exit();
}

$staffDeviceController = new DeviceController($_SESSION['user_id'], $_SESSION['role']);
$result = $staffDeviceController->handleRequest('get', [
    'search' => ['status' => 'active'],
    'limit' => 100, 
    'offset' => 0
]);

$devices = [];
$error_message = '';
if (isset($result['success']) && $result['success']) {
    $devices = $result['data'] ?? [];
    foreach ($devices as &$device) {
        $logResult = $staffDeviceController->handleRequest('get_log', [
            'device_id' => $device['device_id'],
            'limit' => 1,
            'offset' => 0
        ]);
        if (isset($logResult['success']) && $logResult['success'] && !empty($logResult['data'])) {
            $device['latest_log'] = $logResult['data'][0] ?? null; // Lấy bản ghi đầu tiên
        } else {
            $device['latest_log'] = null; // Không có log
        }
    }
    unset($device); // Hủy tham chiếu
} else {
    $error_message = $result['message'] ?? 'Failed to retrieve devices.';
    $_SESSION['error_message'] = $error_message;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <link rel="shortcut icon" type="image/x-icon" href="../../assets/img/favicon.svg">
    <title>Trang thiết bị cho Staff</title>
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
    <div class="main-wrapper">
        <?php
        require_once(__DIR__ . '/../../includes/header.php');
        require_once(__DIR__ . '/../../includes/sidebarAll.php');

        ?>
        <div class="page-wrapper">
            <div class="content">
                <div class="page-header">
                    <div class="page-title">
                        <h4>Danh sách thiết bị</h4>
                        <h6>Xem các thiết bị được gán cho bạn</h6>
                    </div>
                    <div class="page-btn">
                        <a href="addmaintenance.php" class="btn btn-added">
                            <img src="../../assets/img/icons/plus.svg" alt="img"> Thêm yêu cầu bảo trì
                        </a>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table" id="staffDevicesTable">
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
                                        <th>Lần bảo trì cuối</th>
                                        <!-- <th>Xem thiết bị</th> -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($devices)): ?>
                                    <?php foreach ($devices as $device): ?>
                                    <tr onclick="showDeviceModal(
                                                '<?= htmlspecialchars($device['device_id']) ?>',
                                                '<?= htmlspecialchars($device['device_name']) ?>',
                                                '<?= htmlspecialchars($device['latest_log']['new_status'] ?? 'N/A') ?>',
                                                '<?= htmlspecialchars($device['latest_log']['event_type'] ?? 'N/A') ?>',
                                                '<?= htmlspecialchars($device['device_type']) ?>',
                                                '<?= htmlspecialchars($device['image'] ?? '../../assets/images/devices/laptop-solid.svg') ?>',
                                                '<?= htmlspecialchars($device['latest_log']['details'] ?? 'N/A') ?>'
                                            )">
                                        <td><?= htmlspecialchars($device['device_id']) ?></td>
                                        <td><img src="<?= htmlspecialchars($device['image'] ?? '../../assets/img/default_device.jpg') ?>"
                                                class="avatar"></td>
                                        <td><?= htmlspecialchars($device['device_name']) ?></td>
                                        <td><?= htmlspecialchars($device['device_type']) ?></td>
                                        <td><?= htmlspecialchars($device['ip_address']) ?></td>
                                        <td><?= htmlspecialchars($device['mac_address']) ?></td>
                                        <td><?= ucfirst(htmlspecialchars($device['status'])) ?></td>
                                        <td><?= htmlspecialchars($device['location']) ?></td>
                                        <td><?= htmlspecialchars($device['last_maintenance']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
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
                    <p><strong>Hiệu suất sử dụng mạng:</strong> <span id="devicePerformance">Chưa cập nhật</span></p>
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
        if ($.fn.DataTable) {
            if ($.fn.DataTable.isDataTable('#staffDevicesTable')) {
                $('#staffDevicesTable').DataTable().destroy();
            }
            $('#staffDevicesTable').DataTable({
                "language": {
                    "emptyTable": "Không tìm thấy thiết bị nào."
                }
            });
        } else {
            console.error('DataTables không tải được');
        }

        $('.submenu-toggle').click(function() {
            $(this).next('.submenu-list').slideToggle();
            $(this).find('.menu-arrow').toggleClass('rotate');
        });

        if (typeof ApexCharts === 'undefined') {
            console.error('ApexCharts không tải được');
        } else {
            console.log('ApexCharts đã tải thành công');
        }
    });

    function showDeviceModal(deviceId, deviceName, currentStatus, latestEvent, deviceType, imagePath,logDetails) {
        $('#deviceImage').attr('src', imagePath);
        $('#deviceName').text(deviceName);
        $('#deviceType').text(deviceType);
        $('#currentStatus').text(currentStatus);
        $('#logDetails').text(logDetails || 'N/A');
        $('#devicePerformance').text('Chưa cập nhật'); 
        $('#latestEvent').text(latestEvent);
        $('#viewDetailsBtn').attr('href', '/network-management/views/devicelog.php?device_id=' + deviceId);
        $('#deviceModal').modal('show');
    }
    </script>
</body>

</html>