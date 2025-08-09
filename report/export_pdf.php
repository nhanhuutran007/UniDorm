<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();
require_once __DIR__ . '/../controllers/DeviceController.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kiểm tra đăng nhập
session_start();
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['status' => 'error', 'message' => 'Bạn cần đăng nhập để xuất file!']);
    ob_end_flush();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ!']);
    ob_end_flush();
    exit();
}

$type = $_POST['type'] ?? '';
$dataType = $_POST['data'] ?? '';
$statusFilter = $_POST['statusFilter'] ?? '';
$deviceId = $_POST['device_id'] ?? '';
if ($type !== 'pdf') {
    echo json_encode(['status' => 'error', 'message' => 'Loại file không hợp lệ!']);
    ob_end_flush();
    exit();
}

// Lớp CustomTCPDF
class CustomTCPDF extends TCPDF {
    public function Footer() {
        $this->SetY(-25);
        $pageWidth = $this->getPageWidth();
        $this->SetDrawColor(180, 180, 180);
        $this->Line(10, $this->GetY(), $pageWidth - 10, $this->GetY());
        $this->Ln(3);
        $this->SetFont('dejavusans', 'I', 9);
        $this->Cell(0, 6, 'Trang ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 1, 'C');
        $this->SetFont('dejavusans', '', 9);
        $this->Cell(0, 6, 'Hệ thống Quản lý Thiết bị và Người dùng trong Mạng Doanh Nghiệp', 0, 1, 'C');
        $this->SetFont('dejavusans', 'I', 8);
        $this->SetX(0);
        $this->Cell(0, 6, '© 2025 JHTs. All rights reserved.', 0, 1, 'C');
    }
}

// Khởi tạo PDF
try {
    $pdf = new CustomTCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Network Management');
    $pdf->SetMargins(15, 30, 15);
    $pdf->SetAutoPageBreak(true, 40);
    $pdf->SetFont('dejavusans', '', 12);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setHeaderMargin(10);
    $pdf->setFooterMargin(10);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi khởi tạo TCPDF: ' . $e->getMessage()]);
    ob_end_flush();
    exit();
}

// Cấu hình cột cho từng loại dữ liệu
$configs = [
    'devices' => [
        'title' => 'DANH SÁCH THIẾT BỊ',
        'columns' => [
            ['key' => 'device_id', 'label' => 'ID', 'width' => 15, 'align' => 'C'],
            ['key' => 'device_name', 'label' => 'Tên thiết bị', 'width' => 50, 'align' => 'L', 'max_length' => 30],
            ['key' => 'device_type', 'label' => 'Loại', 'width' => 40, 'align' => 'L', 'max_length' => 20],
            ['key' => 'ip_address', 'label' => 'Địa chỉ IP', 'width' => 35, 'align' => 'C'],
            ['key' => 'mac_address', 'label' => 'Địa chỉ MAC', 'width' => 35, 'align' => 'C'],
            ['key' => 'status', 'label' => 'Trạng thái', 'width' => 30, 'align' => 'C', 'format' => 'ucfirst'],
            ['key' => 'location', 'label' => 'Vị trí', 'width' => 40, 'align' => 'L', 'max_length' => 25],
            ['key' => 'price', 'label' => 'Giá tiền', 'width' => 30, 'align' => 'R', 'format' => function($value) {
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
        'title' => 'NHẬT KÍ CỦA THIẾT BỊ ' . $deviceId,
        'columns' => [
            ['key' => 'log_id', 'label' => 'Log ID', 'width' => 15, 'align' => 'C'],
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
        'title' => 'DANH SÁCH NGƯỜI DÙNG',
        'columns' => [
            ['key' => 'user_id', 'label' => 'ID', 'width' => 15, 'align' => 'C'],
            ['key' => 'username', 'label' => 'Tên đăng nhập', 'width' => 40, 'align' => 'L', 'max_length' => 20],
            ['key' => 'fullname', 'label' => 'Họ tên', 'width' => 50, 'align' => 'L', 'max_length' => 30],
            ['key' => 'email', 'label' => 'Email', 'width' => 60, 'align' => 'L', 'max_length' => 40],
            ['key' => 'role', 'label' => 'Vai trò', 'width' => 30, 'align' => 'C', 'format' => 'ucfirst'],
            ['key' => 'status', 'label' => 'Trạng thái', 'width' => 30, 'align' => 'C', 'format' => 'ucfirst'],
            ['key' => 'created_at', 'label' => 'Ngày tạo', 'width' => 40, 'align' => 'C', 'format' => function($value) {
                return date('d/m/Y H:i:s', strtotime($value));
            }],
        ],
        'controller' => function($params) {
            $controller = new UserController();
            $searchParams = [];
            if (!empty($params['statusFilter'])) {
                $searchParams['status'] = $params['statusFilter'];
            }
            $result = $controller->getUsers($searchParams);
            return ['success' => true, 'data' => $result];
        },
    ],
    'maintenance' => [
        'title' => 'DANH SÁCH THIẾT BỊ BẢO TRÌ',
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
            ['key' => 'cost', 'label' => 'Giá tiền', 'width' => 30, 'align' => 'R', 'format' => function($value) {
                return is_numeric($value) ? number_format($value, 0, ',', '.') . ' VND' : '0 VND';
            }],
            ['key' => 'status', 'label' => 'Trạng thái', 'width' => 25, 'align' => 'C', 'format' => function($value) {
                return $value ? ucfirst($value) : 'N/A';
            }],
        ],
        'controller' => function($params) {
            $controller = new DeviceController($_SESSION['user_id'], $_SESSION['role']);
            $searchParams = [];
            if (!empty($params['statusFilter'])) {
                $searchParams['status'] = $params['statusFilter'];
            }
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
        'title' => 'DANH SÁCH PHÂN QUYỀN THIẾT BỊ',
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
            ['key' => 'status', 'label' => 'Trạng thái', 'width' => 25, 'align' => 'C', 'format' => function($value) {
                return $value ? ucfirst($value) : 'N/A';
            }],
            ['key' => 'assigned_by_user_id', 'label' => 'Người phân quyền', 'width' => 40, 'align' => 'L', 'max_length' => 25],
        ],
        'controller' => function($params) {
            $controller = new DeviceController($_SESSION['user_id'], $_SESSION['role']);
            $searchParams = [];
            if (!empty($params['statusFilter'])) {
                $searchParams['status'] = $params['statusFilter'];
            }
            $role = strtolower($_SESSION['role']);
            if ($role !== 'admin') {
                return ['success' => false, 'message' => 'Chỉ admin có quyền xuất danh sách phân quyền'];
            }
            $result = $controller->handleRequest('get_all_assignment', [
                'search' => $searchParams,
                'limit' => 200,
                'offset' => 0,
                'user_id' => $_SESSION['user_id']
            ]);
            error_log('Assignment data: ' . print_r($result, true));
            return $result;
        },
    ],
    'device_stats' => [
        'title' => function($params) {
            $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $params['end_date'] ?? date('Y-m-d');
            $startDateObj = DateTime::createFromFormat('Y-m-d', $startDate);
            $endDateObj = DateTime::createFromFormat('Y-m-d', $endDate);
            if (!$startDateObj || !$endDateObj || $startDateObj->format('Y-m-d') !== $startDate || $endDateObj->format('Y-m-d') !== $endDate) {
                error_log("Invalid date format: start_date=$startDate, end_date=$endDate");
                return 'THỐNG KÊ SỰ KIỆN THIẾT BỊ (NGÀY KHÔNG HỢP LỆ)';
            }
            return 'SỰ KIỆN THIẾT BỊ NGÀY ' . $startDateObj->format('d/m/Y') . ' ĐẾN NGÀY ' . $endDateObj->format('d/m/Y');
        },
        'columns' => [
            ['key' => 'total', 'label' => 'Tổng thiết bị', 'width' => 30, 'align' => 'C'],
            ['key' => 'created', 'label' => 'Tạo mới', 'width' => 30, 'align' => 'C'],
            ['key' => 'updated', 'label' => 'Cập nhật', 'width' => 30, 'align' => 'C'],
            ['key' => 'maintenance', 'label' => 'Bảo trì', 'width' => 30, 'align' => 'C'],
            ['key' => 'assigned', 'label' => 'Gán thiết bị', 'width' => 30, 'align' => 'C'],
            ['key' => 'returned', 'label' => 'Trả thiết bị', 'width' => 30, 'align' => 'C'],
            ['key' => 'status_changed', 'label' => 'Thay đổi trạng thái', 'width' => 40, 'align' => 'C'],
        ],
        'controller' => function($params) {
            $controller = new DeviceController($_SESSION['user_id'], $_SESSION['role']);
            $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $params['end_date'] ?? date('Y-m-d');
            $result = $controller->getDeviceStatusStats($startDate, $endDate);
            if ($result['success']) {
                $data = [$result['data']];
                return ['success' => true, 'data' => $data];
            }
            return ['success' => false, 'message' => 'Không thể lấy thống kê sự kiện thiết bị'];
        },
    ],
];

// Kiểm tra $dataType hợp lệ
if (!isset($configs[$dataType])) {
    echo json_encode(['status' => 'error', 'message' => 'Loại dữ liệu không được hỗ trợ!']);
    ob_end_flush();
    exit();
}

$config = $configs[$dataType];

// Lấy dữ liệu
try {
    $params = [
        'statusFilter' => $statusFilter,
        'device_id' => $deviceId,
        'start_date' => $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
        'end_date' => $_POST['end_date'] ?? date('Y-m-d')
    ];
    error_log('export_pdf.php params: ' . print_r($params, true));
    $result = $config['controller']($params);
    if (!$result['success']) {
        echo json_encode(['status' => 'error', 'message' => 'Không thể lấy dữ liệu: ' . $result['message']]);
        ob_end_flush();
        exit();
    }
    $data = $result['data'] ?? [];

    $title = is_callable($config['title']) ? $config['title']($params) : $config['title'];
    error_log('PDF title: ' . $title);
    $pdf->SetTitle($title);
    $pdf->AddPage();

    // Logo + tiêu đề
    $logoPath = __DIR__ . '/logso.jpg';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 3, 0, 60, 0, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    } else {
        $pdf->SetFont('dejavusans', 'I', 10);
        $pdf->SetXY(10, 10);
        $pdf->Cell(0, 10, 'Không tìm thấy logo tại: ' . $logoPath, 0, 1, 'L');
    }
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->SetXY(0, 15);
    $pdf->Cell(0, 10, 'BÁO CÁO ' . strtoupper($title), 0, 1, 'C');
    $pdf->SetFont('dejavusans', '', 12);
    $pdf->Cell(0, 8, 'Ngày xuất: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Ln(10);

    // Bảng
    $tableWidth = array_sum(array_column($config['columns'], 'width'));
    $pageWidth = $pdf->getPageWidth();
    $leftMargin = ($pageWidth - $tableWidth) / 2;
    $pdf->SetX($leftMargin);

    // Tiêu đề bảng
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetFillColor(200, 220, 255);
    foreach ($config['columns'] as $column) {
        $pdf->Cell($column['width'], 8, $column['label'], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    $pdf->SetX($leftMargin);

    // Dữ liệu
    $pdf->SetFont('dejavusans', '', 9);
    foreach ($data as $index => $row) {
        $pdf->SetFillColor(245, 245, 245);
        $fill = ($index % 2 == 0) ? 1 : 0;
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
            $pdf->Cell($column['width'], 8, $value, 1, 0, $column['align'], $fill);
        }
        $pdf->Ln();
        $pdf->SetX($leftMargin);
    }

    $pdf->Ln(15);

    if (ob_get_length()) {
        error_log('Unexpected output buffer content: ' . ob_get_contents());
    }
    ob_end_clean();
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($title));
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    $pdf->Output($filename . '.pdf', 'D');
    exit();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi xử lý dữ liệu: ' . $e->getMessage()]);
    ob_end_flush();
    exit();
}
?>