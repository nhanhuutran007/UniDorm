<?php
// Path: /network-management/repositories/DeviceLogs.php

class DeviceLogs {
    private $conn;
    private $logId;
    private $deviceId;
    private $userId;
    private $eventType;
    private $eventDate;
    private $previousStatus;
    private $newStatus;
    private $details;

    // Danh sách giá trị hợp lệ cho event_type
    private const VALID_EVENT_TYPES = [
        'created',
        'updated',
        'maintenance',
        'assigned',
        'deleted',
        'status_changed'
    ];

    /**
     * Constructor
     * @param mysqli $db Đối tượng kết nối cơ sở dữ liệu
     * @param array $data Dữ liệu khởi tạo (nếu có)
     */
    public function __construct($db, $data = []) {
        if ($db instanceof mysqli) {
            $this->conn = $db;
        } else {
            throw new Exception("Kết nối cơ sở dữ liệu không hợp lệ.");
        }

        $this->logId = $data['log_id'] ?? null;
        $this->deviceId = $data['device_id'] ?? null;
        $this->userId = $data['user_id'] ?? null;
        $this->setEventType($data['event_type'] ?? null); // Sử dụng setter để kiểm tra
        $this->eventDate = $data['event_date'] ?? null;
        $this->previousStatus = $data['previous_status'] ?? null;
        $this->newStatus = $data['new_status'] ?? null;
        $this->details = $data['details'] ?? null;
    }

    // Getters
    public function getLogId() { return $this->logId; }
    public function getDeviceId() { return $this->deviceId; }
    public function getUserId() { return $this->userId; }
    public function getEventType() { return $this->eventType; }
    public function getEventDate() { return $this->eventDate; }
    public function getPreviousStatus() { return $this->previousStatus; }
    public function getNewStatus() { return $this->newStatus; }
    public function getDetails() { return $this->details; }

    // Setters
    public function setDeviceId($deviceId) { $this->deviceId = $deviceId; }
    public function setUserId($userId) { $this->userId = $userId; }

    public function setEventType($eventType) {
        if ($eventType !== null && !in_array($eventType, self::VALID_EVENT_TYPES)) {
            throw new Exception("Loại sự kiện không hợp lệ. Các giá trị cho phép: " . implode(", ", self::VALID_EVENT_TYPES));
        }
        $this->eventType = $eventType;
    }

    public function setEventDate($eventDate) { $this->eventDate = $eventDate; }
    public function setPreviousStatus($previousStatus) { $this->previousStatus = $previousStatus; }
    public function setNewStatus($newStatus) { $this->newStatus = $newStatus; }
    public function setDetails($details) { $this->details = $details; }

    /**
     * Lưu log (thêm mới hoặc cập nhật)
     * @return mixed ID của log nếu thêm mới, true nếu cập nhật thành công, false nếu thất bại
     */
    public function save() {
        return $this->insert();
    }

    /**
     * Thêm log mới vào cơ sở dữ liệu
     * @return int ID của log vừa thêm
     * @throws Exception Nếu có lỗi xảy ra
     */
    private function insert() {
        try {    
            // Thêm log vào bảng device_logs
            $query = "INSERT INTO device_logs (device_id, user_id, event_type, event_date, previous_status, new_status, details) 
                      VALUES (?, ?, ?, NOW(), ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn thêm log.");
            }
    
            $stmt->bind_param(
                "iissss",
                $this->deviceId,
                $this->userId,
                $this->eventType,
                $this->previousStatus,
                $this->newStatus,
                $this->details
            );
    
            if (!$stmt->execute()) {
                if ($this->conn->errno === 1452) { // Lỗi khóa ngoại
                    throw new Exception("Dữ liệu không hợp lệ: Thiết bị hoặc người dùng không tồn tại.");
                }
                throw new Exception("Không thể thêm log vào hệ thống.");
            }
    
            $this->logId = $this->conn->insert_id;
            $this->eventDate = date('Y-m-d H:i:s');
            return $this->logId;
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }

    /**
     * Lấy tất cả nhật ký của một thiết bị với tùy chọn tìm kiếm và phân trang
     * @param mysqli $db Đối tượng kết nối cơ sở dữ liệu
     * @param int $deviceId ID của thiết bị cần lấy nhật ký
     * @param array $searchParams Tham số tìm kiếm bổ sung
     * @param int $limit Giới hạn số bản ghi
     * @param int $offset Vị trí bắt đầu
     * @return DeviceLogs[] Mảng các đối tượng DeviceLogs
     */
    public static function getAllLogs($db, $deviceId, $filters = [], $limit = 100, $offset = 0) {
        try {
            // Validate deviceId
            if (empty($deviceId) || !is_numeric($deviceId)) {
                throw new InvalidArgumentException("Device ID không hợp lệ");
            }
    
            $query = "SELECT log_id, device_id, user_id, event_type, event_date, 
                             previous_status, new_status, details 
                      FROM device_logs 
                      WHERE device_id = ? AND event_type != 'deleted'";
            
            $params = [(int)$deviceId];
            $types = "i";
    
            // Thêm điều kiện lọc theo event_type nếu có
            if (!empty($filters['event_type']) && in_array($filters['event_type'], self::VALID_EVENT_TYPES)) {
                $query .= " AND event_type = ?";
                $params[] = $filters['event_type'];
                $types .= "s";
            }
    
            // Thêm phân trang
            $query .= " ORDER BY event_date DESC LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
            $types .= "ii";
    
            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $db->error);
            }
    
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception("Không thể thực thi truy vấn: " . $stmt->error);
            }
    
            $result = $stmt->get_result();
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row; 
            }

            // // In ra dữ liệu thô ngay tại hàm
            // echo "<pre>";
            // print_r($logs);
            // echo "</pre>";
    
            return $logs;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return []; // Vẫn trả về mảng rỗng khi có lỗi
        }
    }

    /**
     * Đóng kết nối cơ sở dữ liệu (nếu cần)
     */
    public function closeConnection() {
        if ($this->conn && method_exists($this->conn, 'close')) {
            $this->conn->close();
        }
    }

    /**
     * Đếm số lượng tất cả các log trong bảng device_logs
     * @param mysqli $db Đối tượng kết nối cơ sở dữ liệu
     * @return int Số lượng log
     */
    public static function countLogs($db) {
        try {
            $query = "SELECT COUNT(*) as total FROM device_logs WHERE event_type != 'deleted'";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $db->error);
            }

            if (!$stmt->execute()) {
                throw new Exception("Không thể thực thi truy vấn: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            return (int)$row['total'];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return 0;
        }
    }
}