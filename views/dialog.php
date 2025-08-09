<?php
// Path: /network-management/views/dialog.php
session_start();
require_once __DIR__ . '/../controllers/DeviceController.php';

// Khởi tạo controller
$controller = new DeviceController(1, 'admin');

// Dữ liệu mẫu
$updateData = [
    'device_name' => 'Printer HP',
    'device_type' => 'printer',
    'ip_address' => '192.168.1.100',
    'mac_address' => '00:14:22:01:23:45',
    'status' => 'active',
    'location' => 'Room 102',
    'last_maintenance' => '2025-04-02'
];

// Xử lý yêu cầu
$result = $controller->handleRequest('add', ['data' => $updateData]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Device Management</title>
    <style>
        .dialog {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
            z-index: 1000;
        }
        .dialog.success { border-color: green; }
        .dialog.error { border-color: red; }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
    </style>
</head>
<body>
    <div id="overlay" class="overlay"></div>
    <div id="dialog" class="dialog <?php echo $result['success'] ? 'success' : 'error'; ?>">
        <h3>Thông báo</h3>
        <p><?php echo $result['message']; ?></p>
        <button onclick="closeDialog()">Đóng</button>
    </div>

    <script>
        // Hiển thị dialog khi có kết quả
        <?php if ($result) { ?>
            document.getElementById('dialog').style.display = 'block';
            document.getElementById('overlay').style.display = 'block';
        <?php } ?>

        function closeDialog() {
            document.getElementById('dialog').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        }
    </script>
</body>
</html>