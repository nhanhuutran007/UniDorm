<?php
require_once __DIR__ . '/../controllers/DeviceController.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kiểm tra đăng nhập
session_start();
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'error', 'message' => 'Bạn cần đăng nhập để xuất file!']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ!']);
    exit();
}

$type = $_POST['type'] ?? '';
$dataType = $_POST['data'] ?? '';
$statusFilter = $_POST['statusFilter'] ?? '';
$deviceId = $_POST['device_id'] ?? '';

if ($type !== 'excel') {
    echo json_encode(['status' => 'error', 'message' => 'Loại file không hợp lệ!']);
    exit();
}

// Cấu hình cột cho từng loại dữ liệu
$configs = [
    'devices' => [
        'title' => 'Danh sách thiết bị',
        'columns' => [
            ['key' => 'device_id', 'label' => 'Mã Thiết bị', 'width' => 15, 'align' => 'C'],
            ['key' => 'device_name', 'label' => 'Tên Thiết bị', 'width' => 50, 'align' => 'L', 'max_length' => 30],
            ['key' => 'device_type', 'label' => 'Loại Thiết bị', 'width' => 40, 'align' => 'L', 'max_length' => 20],
            ['key' => 'ip_address', 'label' => 'Địa chỉ IP', 'width' => 35, 'align' => 'C'],
            ['key' => 'mac_address', 'label' => 'Địa chỉ MAC', 'width' => 35, 'align' => 'C'],
            ['key' => 'status', 'label' => 'Trạng Thái', 'width' => 30, 'align' => 'C', 'format' => 'ucfirst'],
            ['key' => 'location', 'label' => 'Vị Trí', 'width' => 40, 'align' => 'L', 'max_length' => 25],
            ['key' => 'price', 'label' => 'Giá Tiền', 'width' => 30, 'align' => 'R', 'format' => function($value) {
                return number_format($value ?? 0, 0, ',', '.') . ' VND';
            }],
        ],
        'controller' => function($params) {
            $controller = new DeviceController($_SESSION['user_id'], $_SESSION['role']);
            $searchParams = ['status' => ['active', 'maintenance']];
            if (!empty($params['statusFilter'])) {
                $searchParams = ['status' => [$params['statusFilter']]];
            }
            $result = $controller->handleRequest('get', ['search' => $searchParams, 'limit' => 200, 'offset' => 0]);
            return $result;
        },
    ],
    'devicelog' => [
        'title' => 'Nhật ký thiết bị',
        'columns' => [
            ['key' => 'log_id', 'label' => 'Log ID ', 'width' => 15, 'align' => 'C'],
            ['key' => 'device_id', 'label' => 'Device ID', 'width' => 20, 'align' => 'C'],
            ['key' => 'user_id', 'label' => 'User ID', 'width' => 20, 'align' => 'C'],
            ['key' => 'event_type', 'label' => 'Event Type', 'width' => 40, 'align' => 'L', 'max_length' => 30],
            ['key' => 'event_date', 'label' => 'Event Date', 'width' => 40, 'align' => 'C', 'format' => function($value) {
                return $value ? date('d/m/Y H:i:s', strtotime($value)) : 'N/A';
            }],
            ['key' => 'previous_status', 'label' => 'Previous Status', 'width' => 30, 'align' => 'C', 'format' => 'ucfirst'],
            ['key' => 'new_status', 'label' => 'New Status', 'width' => 30, 'align' => 'C', 'format' => 'ucfirst'],
            ['key' => 'details', 'label' => 'Details', 'width' => 60, 'align' => 'L', 'max_length' => 40],
        ],
        'controller' => function($params) {
            $controller = new DeviceController($_SESSION['user_id'], $_SESSION['role']);
            $deviceId = $params['device_id'] ?? null;
            if (!$deviceId) {
                return ['success' => false, 'message' => 'Thiếu ID thiết bị'];
            }
            $result = $controller->handleRequest('get_log', ['device_id' => $deviceId, 'limit' => 200, 'offset' => 0]);
            error_log('Devicelog data: ' . print_r($result, true));
            return $result;
        },
    ],
    'userlist' => [
        'title' => 'Danh sách người dùng',
        'columns' => [
            ['key' => 'user_id', 'label' => 'Mã Người dùng', 'width' => 15, 'align' => 'C'],
            ['key' => 'username', 'label' => 'Tên Đăng Nhập', 'width' => 40, 'align' => 'L', 'max_length' => 20],
            ['key' => 'fullname', 'label' => 'Họ Tên', 'width' => 50, 'align' => 'L', 'max_length' => 30],
            ['key' => 'email', 'label' => 'Email', 'width' => 60, 'align' => 'L', 'max_length' => 40],
            ['key' => 'role', 'label' => 'Vai Trò', 'width' => 30, 'align' => 'C', 'format' => 'ucfirst'],
            ['key' => 'status', 'label' => 'Trạng Thái', 'width' => 30, 'align' => 'C', 'format' => 'ucfirst'],
            ['key' => 'created_at', 'label' => 'Ngày Tạo', 'width' => 40, 'align' => 'C', 'format' => function($value) {
                return $value ? date('d/m/Y H:i:s', strtotime($value)) : 'N/A';
            }],
        ],
        'controller' => function($params) {
            $controller = new UserController();
            $searchParams = [];
            if (!empty($params['statusFilter'])) {
                $searchParams['status'] = $params['statusFilter'];
            }
            $result = $controller->getUsers($searchParams);
            error_log('Userlist data: ' . print_r($result, true));
            return ['success' => true, 'data' => $result];
        },
    ],

    'maintenance' => [
        'title' => 'Danh sách thiết bị bảo trì',
        'columns' => [
            ['key' => 'record_id', 'label' => 'ID', 'width' => 15, 'align' => 'C'],
            ['key' => 'device_id', 'label' => 'Thiết bị', 'width' => 20, 'align' => 'C'],
            ['key' => 'reported_by_user_id', 'label' => 'Người báo cáo', 'width' => 30, 'align' => 'L', 'max_length' => 20],
            ['key' => 'performed_by_user_id', 'label' => 'Người bảo trì', 'width' => 30, 'align' => 'L', 'max_length' => 20],
            ['key' => 'maintenance_date', 'label' => 'Ngày bảo trì', 'width' => 35, 'align' => 'C', 'format' => function($value) {
                
                return $value && strtotime($value) ? date('d/m/Y H:i:s', strtotime($value)) : 'N/A';
            }],
            ['key' => 'completion_date', 'label' => 'Ngày hoàn thành', 'width' => 35, 'align' => 'C', 'format' => function($value) {
                
                return $value && strtotime($value) ? date('d/m/Y H:i:s', strtotime($value)) : 'N/A';
            }],
            ['key' => 'description', 'label' => 'Mô tả', 'width' => 40, 'align' => 'L', 'max_length' => 25],
            ['key' => 'note', 'label' => 'Ghi chú', 'width' => 40, 'align' => 'L', 'max_length' => 25],
            ['key' => 'cost', 'label' => 'Giá tiền', 'width' => 30, 'align' => 'R', 'format' => function($value) {
            
                return is_numeric($value) ? number_format($value, 0, ',', '.') . ' VND' : '0 VND';
            }],
            ['key' => 'status', 'label' => 'Trạng thái', 'width' => 25, 'align' => 'C', 'format' => function($value) {
                // Kiểm tra trạng thái hợp lệ
                return $value ? ucfirst($value) : 'N/A';
            }],
            ['key' => 'created_at', 'label' => 'Ngày tạo', 'width' => 35, 'align' => 'C', 'format' => function($value) {
                
                return $value && strtotime($value) ? date('d/m/Y H:i:s', strtotime($value)) : 'N/A';
            }],
            ['key' => 'updated_at', 'label' => 'Ngày cập nhật', 'width' => 35, 'align' => 'C', 'format' => function($value) {
                
                return $value && strtotime($value) ? date('d/m/Y H:i:s', strtotime($value)) : 'N/A';
            }],
        ],
        'controller' => function($params) {
            $controller = new DeviceController($_SESSION['user_id'], $_SESSION['role']);
            $searchParams = [];
            if (!empty($params['statusFilter'])) {
                $searchParams['status'] = $params['statusFilter'];
            }
            // Thêm kiểm tra vai trò
            $role = strtolower($_SESSION['role']);
            if ($role !== 'admin') {
                return ['success' => false, 'message' => 'Chỉ admin có quyền xuất danh sách bảo trì'];
            }
            $result = $controller->handleRequest('get_all_maintenance', [
                'search' => $searchParams,
                'limit' => 200,
                'offset' => 0
            ]);
            error_log('Maintenance data: ' . print_r($result, true));
            return $result;
        },
    ],

    'assignment' => [
        'title' => 'Danh sách phân quyền thiết bị',
        'columns' => [
            ['key' => 'assignment_id', 'label' => 'ID', 'width' => 15, 'align' => 'C'],
            ['key' => 'device_id', 'label' => 'Thiết bị', 'width' => 20, 'align' => 'C'],
            ['key' => 'user_id', 'label' => 'Người dùng', 'width' => 30, 'align' => 'L', 'max_length' => 20],
            ['key' => 'assigned_date', 'label' => 'Ngày phân quyền', 'width' => 35, 'align' => 'C', 'format' => function($value) {
                
                return $value && strtotime($value) ? date('d/m/Y H:i:s', strtotime($value)) : 'N/A';
            }],
            ['key' => 'expected_return_date', 'label' => 'Ngày trả dự kiến', 'width' => 35, 'align' => 'C', 'format' => function($value) {
                
                return $value && strtotime($value) ? date('d/m/Y H:i:s', strtotime($value)) : 'N/A';
            }],

            ['key' => 'actual_return_date', 'label' => 'Ngày trả thiết bị', 'width' => 35, 'align' => 'C', 'format' => function($value) {
                
                return $value && strtotime($value) ? date('d/m/Y H:i:s', strtotime($value)) : 'N/A';
            }],

            ['key' => 'status', 'label' => 'Trạng thái', 'width' => 25, 'align' => 'C', 'format' => function($value) {
                // Kiểm tra trạng thái hợp lệ
                return $value ? ucfirst($value) : 'N/A';
            }],

            ['key' => 'assigned_by_user_id', 'label' => 'Người phân quyền', 'width' => 40, 'align' => 'L', 'max_length' => 25],
            ['key' => 'note', 'label' => 'Ghi chú', 'width' => 40, 'align' => 'L', 'max_length' => 25],
            ['key' => 'created_at', 'label' => 'Ngày tạo', 'width' => 35, 'align' => 'C', 'format' => function($value) {
                
                return $value && strtotime($value) ? date('d/m/Y H:i:s', strtotime($value)) : 'N/A';
            }],
            ['key' => 'updated_at', 'label' => 'Ngày cập nhật', 'width' => 35, 'align' => 'C', 'format' => function($value) {
                
                return $value && strtotime($value) ? date('d/m/Y H:i:s', strtotime($value)) : 'N/A';
            }],
        ],
        'controller' => function($params) {
            $controller = new DeviceController($_SESSION['user_id'], $_SESSION['role']);
            $searchParams = [];
            if (!empty($params['statusFilter'])) {
                $searchParams['status'] = $params['statusFilter'];
            }
            // Kiểm tra vai trò
            $role = strtolower($_SESSION['role']);
            if ($role !== 'admin') {
                return ['success' => false, 'message' => 'Chỉ admin có quyền xuất danh sách phân quyền'];
            }
            // Gọi action get_all_assignment thay vì get_all_maintenance
            $result = $controller->handleRequest('get_all_assignment', [
                'search' => $searchParams,
                'limit' => 200,
                'offset' => 0,
                'user_id' => $_SESSION['user_id'] // Truyền user_id để lọc theo người dùng nếu cần
            ]);
            error_log('Assignment data: ' . print_r($result, true));
            return $result;
        },
    ],



];

// Kiểm tra $dataType hợp lệ
if (!isset($configs[$dataType])) {
    echo json_encode(['status' => 'error', 'message' => 'Loại dữ liệu không được hỗ trợ!']);
    exit();
}

$config = $configs[$dataType];

// Lấy dữ liệu
try {
    $params = ['statusFilter' => $statusFilter, 'device_id' => $deviceId];
    $result = $config['controller']($params);
    if (!$result['success']) {
        echo json_encode(['status' => 'error', 'message' => 'Không thể lấy dữ liệu: ' . $result['message']]);
        exit();
    }
    $data = $result['data'] ?? [];

    // Chuẩn bị dữ liệu cho Excel
    $excelData = [];

    // Thêm tiêu đề báo cáo
    $excelData[] = ['BÁO CÁO ' . strtoupper($config['title'])];
    $excelData[] = ['Ngày xuất: ' . date('d/m/Y H:i:s')];
    $excelData[] = []; // Dòng trống

    // Thêm tiêu đề cột
    $headerRow = array_column($config['columns'], 'label');
    $excelData[] = $headerRow;

    // Thêm dữ liệu
    foreach ($data as $row) {
        $excelRow = [];
        foreach ($config['columns'] as $column) {
            $value = $row[$column['key']] ?? '';
            if (isset($column['max_length'])) {
                $value = substr($value, 0, $column['max_length']);
            }
            if (isset($column['format'])) {
                if (is_callable($column['format'])) {
                    $value = $column['format']($value);
                } elseif ($column['format'] === 'ucfirst') {
                    $value = ucfirst($value);
                }
            }
            $excelRow[] = $value;
        }
        $excelData[] = $excelRow;
    }

    // Xuất file Excel
    $filename = strtolower(str_replace(' ', '_', $config['title'])) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    \Shuchkin\SimpleXLSXGen::fromArray($excelData)->downloadAs($filename);
    exit();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi xử lý dữ liệu: ' . $e->getMessage()]);
    exit();
}
?>