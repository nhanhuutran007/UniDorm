<?php
// Path: /network-management/functions/device_value/DeviceValue.php

require_once __DIR__ . '/../../models/DeviceModel.php';

class DeviceValue {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function handleAction($action , $params = []) {
        try {
            switch (strtolower($action)) {
                case 'countdevice':
                    $deviceModel = new DeviceModel($this->db, []);
                    $count = $deviceModel->countDevice($this->db);
                    return [
                        'success' => true,
                        'message' => "Thành công: Có tổng cộng $count thiết bị.",
                        'data' => ['device_count' => $count]
                    ];
                    
                case 'totalactive':
                    $deviceModel = new DeviceModel($this->db, []);
                    $totalActive = $deviceModel->countByStatus($this->db, 'active');
                    return [
                        'success' => true,
                        'message' => "Thành công: Có tổng cộng $totalActive thiết bị.",
                        'data' => ['device_count' => $totalActive]
                    ];
                case 'totalinactive':
                    $deviceModel = new DeviceModel($this->db, []);
                    $totalInactive = $deviceModel->countByStatus($this->db, 'inactive');
                    return [
                        'success' => true,
                        'message' => "Thành công: Có tổng cộng $totalInactive thiết bị.",
                        'data' => ['device_count' => $totalInactive]
                    ];
                case 'totalmaintenance':
                    $deviceModel = new DeviceModel($this->db, []);  
                    $totalMaintenance = $deviceModel->countByStatus($this->db, 'maintenance');
                    return [
                        'success' => true,
                        'message' => "Thành công: Có tổng cộng $totalMaintenance thiết bị.",
                        'data' => ['device_count' => $totalMaintenance]
                    ];

                case 'devicestatusstats':
                    // Lấy ngày từ params, không dùng mặc định
                    $startDate = isset($params['start_date']) ? $params['start_date'] : null;
                    $endDate = isset($params['end_date']) ? $params['end_date'] : null;

                    // Kiểm tra nếu thiếu ngày
                    if (!$startDate || !$endDate) {
                        error_log("Missing start_date or end_date in params: start_date=$startDate, end_date=$endDate");
                        throw new Exception("Thiếu start_date hoặc end_date trong params.");
                    }

                    error_log("devicestatusstats: startDate=$startDate, endDate=$endDate");

                    // Khởi tạo $totalDevices
                    $totalDevices = 0;

                    // Đếm tổng thiết bị từ devices
                    $sqlTotal = "SELECT COUNT(*) AS total FROM devices";
                    $stmtTotal = $this->db->prepare($sqlTotal);
                    if (!$stmtTotal) {
                        throw new Exception("Lỗi chuẩn bị truy vấn: " . $this->db->error);
                    }
                    $stmtTotal->execute();
                    $stmtTotal->bind_result($totalDevices);
                    if ($stmtTotal->fetch()) {
                        $totalDevices = (int)$totalDevices;
                    }
                    $stmtTotal->close();

                    // Truy vấn device_logs để đếm số sự kiện theo event_type
                    $sql = "
                        SELECT 
                            event_type,
                            COUNT(*) AS event_count
                        FROM device_logs
                        WHERE event_date BETWEEN ? AND ?
                        GROUP BY event_type
                    ";
                    $stmt = $this->db->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Lỗi chuẩn bị truy vấn: " . $this->db->error);
                    }
                    $startDateTime = $startDate . ' 00:00:00';
                    $endDateTime = $endDate . ' 23:59:59';
                    error_log("SQL: $sql");
                    error_log("startDateTime: $startDateTime, endDateTime: $endDateTime");
                    $stmt->bind_param('ss', $startDateTime, $endDateTime);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $results = $result->fetch_all(MYSQLI_ASSOC);
                    error_log("Query Results: " . print_r($results, true));
                    $stmt->close();

                    // Khởi tạo số đếm cho các event_type
                    $eventCounts = [
                        'created' => 0,
                        'updated' => 0,
                        'maintenance' => 0,
                        'assigned' => 0,
                        'returned' => 0,
                        'status_changed' => 0
                    ];

                    // Gán số đếm từ kết quả truy vấn
                    foreach ($results as $row) {
                        $eventType = strtolower(trim($row['event_type']));
                        if ($eventType === 'status changed') {
                            $eventType = 'status_changed';
                        }
                        if (array_key_exists($eventType, $eventCounts)) {
                            $eventCounts[$eventType] = (int)$row['event_count'];
                        } else {
                            error_log("Unknown event_type: $eventType");
                        }
                    }

                    return [
                        'success' => true,
                        'message' => "Thành công: Lấy thống kê sự kiện thiết bị.",
                        'data' => [
                            'total' => $totalDevices,
                            'created' => $eventCounts['created'],
                            'updated' => $eventCounts['updated'],
                            'maintenance' => $eventCounts['maintenance'],
                            'assigned' => $eventCounts['assigned'],
                            'returned' => $eventCounts['returned'],
                            'status_changed' => $eventCounts['status_changed']
                        ]
                    ];
                default:
                    throw new Exception("Hành động '$action' không hợp lệ. Vui lòng kiểm tra lại.");
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Lỗi: " . $e->getMessage(),
                'data' => []
            ];
        }
    }
}

// Ví dụ sử dụng
// $db = require __DIR__ . '/../../includes/db.php'; // Kết nối cơ sở dữ liệu
// $deviceValue = new DeviceValue($db);
// $result = $deviceValue->handleAction("totalinactive"); // Đếm số lượng thiết bị
// echo "Tổng số thiết bị: " . $result['data']['device_count']; // In ra tổng số thiết bị
?>