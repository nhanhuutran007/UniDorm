<?php
// Path: /network-management/repositories/DeviceAssignments.php

class DeviceAssignments {
    private $conn;
    private $assignmentId;
    private $deviceId;
    private $userId;
    private $assignedDate;
    private $expectedReturnDate;
    private $actualReturnDate;
    private $assignedByUserId;
    private $status;
    private $notes;
    private $createdAt;
    private $updatedAt;

    // Danh sách giá trị hợp lệ cho status
    private const VALID_STATUSES = ['active', 'returned', 'deleted', 'update'];

    /**
     * Constructor
     * @param mysqli $db Đối tượng kết nối cơ sở dữ liệu
     * @param array $data Dữ liệu khởi tạo (nếu có)
     */
    public function __construct($db, $data = []) {
        if (!($db instanceof mysqli)) {
            throw new Exception("Kết nối cơ sở dữ liệu phải là mysqli.");
        }
        $this->conn = $db;

        $this->assignmentId = $data['assignment_id'] ?? null;
        $this->setDeviceId($data['device_id'] ?? null);
        $this->setUserId($data['user_id'] ?? null);
        $this->setAssignedDate($data['assigned_date'] ?? null);
        $this->expectedReturnDate = $data['expected_return_date'] ?? null;
        $this->actualReturnDate = $data['actual_return_date'] ?? null;
        $this->setAssignedByUserId($data['assigned_by_user_id'] ?? null);
        $this->setStatus($data['status'] ?? 'active');
        $this->notes = $data['notes'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    // Getters
    public function getAssignmentId() { return $this->assignmentId; }
    public function getDeviceId() { return $this->deviceId; }
    public function getUserId() { return $this->userId; }
    public function getAssignedDate() { return $this->assignedDate; }
    public function getExpectedReturnDate() { return $this->expectedReturnDate; }
    public function getActualReturnDate() { return $this->actualReturnDate; }
    public function getAssignedByUserId() { return $this->assignedByUserId; }
    public function getStatus() { return $this->status; }
    public function getNotes() { return $this->notes; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }

    // Setters với kiểm tra
    public function setDeviceId($deviceId) {
        if ($deviceId !== null && (!is_int($deviceId) || $deviceId <= 0)) {
            throw new Exception("Device ID phải là số nguyên dương.");
        }
        $this->deviceId = $deviceId;
    }

    public function setUserId($userId) {
        if ($userId !== null && (!is_int($userId) || $userId <= 0)) {
            throw new Exception("User ID phải là số nguyên dương.");
        }
        $this->userId = $userId;
    }

    public function setAssignedDate($assignedDate) {
        if ($assignedDate !== null && !strtotime($assignedDate)) {
            throw new Exception("Ngày phân công không hợp lệ.");
        }
        $this->assignedDate = $assignedDate;
    }

    public function setAssignedByUserId($assignedByUserId) {
        if ($assignedByUserId !== null && (!is_int($assignedByUserId) || $assignedByUserId <= 0)) {
            throw new Exception("Assigned by User ID phải là số nguyên dương.");
        }
        $this->assignedByUserId = $assignedByUserId;
    }

    public function setStatus($status) {
        if ($status !== null && !in_array($status, self::VALID_STATUSES)) {
            throw new Exception("Trạng thái không hợp lệ. Giá trị cho phép: " . implode(", ", self::VALID_STATUSES));
        }
        $this->status = $status;
    }

    public function setExpectedReturnDate($date) { $this->expectedReturnDate = $date; }
    public function setActualReturnDate($date) { $this->actualReturnDate = $date; }
    public function setNotes($notes) { $this->notes = $notes; }

    /**
     * Thêm phân công vào cơ sở dữ liệu
     * @return int ID của bản ghi vừa thêm
     * @throws Exception Nếu dữ liệu không hợp lệ hoặc thiết bị đã được phân công
     */
    public function insert() {
        try {
            // Kiểm tra dữ liệu bắt buộc
            if (!$this->deviceId || !$this->userId || !$this->assignedDate || !$this->assignedByUserId) {
                throw new Exception("Thiếu thông tin bắt buộc: device_id, user_id, assigned_date, hoặc assigned_by_user_id.");
            }

            // Kiểm tra trùng lặp
            $queryCheckDuplicate = "SELECT COUNT(*) FROM device_assignments WHERE device_id = ? AND status = 'active'";
            $stmtCheckDuplicate = $this->conn->prepare($queryCheckDuplicate);
            $stmtCheckDuplicate->bind_param("i", $this->deviceId);
            $stmtCheckDuplicate->execute();
            if ($stmtCheckDuplicate->get_result()->fetch_row()[0] > 0) {
                throw new Exception("Thiết bị với ID {$this->deviceId} đã được phân công và đang ở trạng thái active.");
            }

            // Kiểm tra thiết bị có tồn tại
            if ($this->deviceId) {
                $queryCheckDevice = "SELECT COUNT(*) FROM devices WHERE device_id = ? AND status != 'inactive'";
                $stmtCheckDevice = $this->conn->prepare($queryCheckDevice);
                $stmtCheckDevice->bind_param("i", $this->deviceId);
                $stmtCheckDevice->execute();
                if ($stmtCheckDevice->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Thiết bị với ID {$this->deviceId} không tồn tại hoặc đã bị xóa.");
                }
            }

            // Kiểm tra người dùng có tồn tại
            if ($this->userId) {
                $queryCheckUser = "SELECT COUNT(*) FROM users WHERE user_id = ? AND status != 'ban'";
                $stmtCheckUser = $this->conn->prepare($queryCheckUser);
                $stmtCheckUser->bind_param("i", $this->userId);
                $stmtCheckUser->execute();
                if ($stmtCheckUser->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Người dùng với ID {$this->userId} không tồn tại hoặc đã bị xóa.");
                }
            }
            
            // Kiểm tra ngày phân công có hợp lệ
            if ($this->assignedDate && !strtotime($this->assignedDate)) {
                throw new Exception("Ngày phân công không hợp lệ.");
            }

            // Kiểm tra ngày trả dự kiến có hợp lệ
            if ($this->expectedReturnDate && !strtotime($this->expectedReturnDate)) {
                throw new Exception("Ngày trả dự kiến không hợp lệ.");
            }

            // Kiểm tra ngày trả thực tế có hợp lệ
            if ($this->actualReturnDate && !strtotime($this->actualReturnDate)) {
                throw new Exception("Ngày trả thực tế không hợp lệ.");
            }

            // Kiểm tra người dùng phân công có tồn tại
            if ($this->assignedByUserId) {
                $queryCheckUser = "SELECT COUNT(*) FROM users WHERE user_id = ? AND status != 'ban'";
                $stmtCheckUser = $this->conn->prepare($queryCheckUser);
                $stmtCheckUser->bind_param("i", $this->assignedByUserId);
                $stmtCheckUser->execute();
                if ($stmtCheckUser->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Người phân công với ID {$this->assignedByUserId} không tồn tại hoặc đã bị xóa.");
                }
            }


            // Thêm bản ghi
            $query = "INSERT INTO device_assignments (device_id, user_id, assigned_date, expected_return_date, assigned_by_user_id, status, notes) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $this->conn->error);
            }

            $stmt->bind_param(
                "iississ",
                $this->deviceId,
                $this->userId,
                $this->assignedDate,
                $this->expectedReturnDate,
                $this->assignedByUserId,
                $this->status,
                $this->notes
            );

            if (!$stmt->execute()) {
                throw new Exception("Không thể thêm phân công: " . $stmt->error);
            }

            $this->assignmentId = $this->conn->insert_id;
            $this->createdAt = date('Y-m-d H:i:s');
            return $this->assignmentId;
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }

    /**
     * Cập nhật thông tin phân công
     * @return bool True nếu cập nhật thành công
     * @throws Exception Nếu có lỗi xảy ra
     */
    public function update() {
        try {
            // Kiểm tra assignment_id
            if (!$this->assignmentId) {
                throw new Exception("Assignment ID không được thiết lập.");
            }

            // Kiểm tra bản ghi có tồn tại và chưa bị xóa mềm
            $queryCheck = "SELECT COUNT(*) FROM device_assignments WHERE assignment_id = ? AND status != 'deleted'";
            $stmtCheck = $this->conn->prepare($queryCheck);
            $stmtCheck->bind_param("i", $this->assignmentId);
            $stmtCheck->execute();
            if ($stmtCheck->get_result()->fetch_row()[0] == 0) {
                throw new Exception("Bản ghi với ID {$this->assignmentId} không tồn tại hoặc đã bị xóa mềm.");
            }

            // Kiểm tra thiết bị có tồn tại
            if ($this->deviceId) {
                $queryCheckDevice = "SELECT COUNT(*) FROM devices WHERE device_id = ? AND status != 'inactive'";
                $stmtCheckDevice = $this->conn->prepare($queryCheckDevice);
                $stmtCheckDevice->bind_param("i", $this->deviceId);
                $stmtCheckDevice->execute();
                if ($stmtCheckDevice->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Thiết bị với ID {$this->deviceId} không tồn tại hoặc đã bị xóa.");
                }
            }

            // Kiểm tra người dùng có tồn tại
            if ($this->userId) {
                $queryCheckUser = "SELECT COUNT(*) FROM users WHERE user_id = ? AND status != 'ban'";
                $stmtCheckUser = $this->conn->prepare($queryCheckUser);
                $stmtCheckUser->bind_param("i", $this->userId);
                $stmtCheckUser->execute();
                if ($stmtCheckUser->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Người dùng với ID {$this->userId} không tồn tại hoặc đã bị xóa.");
                }
            }
            
            // Kiểm tra ngày phân công có hợp lệ
            if ($this->assignedDate && !strtotime($this->assignedDate)) {
                throw new Exception("Ngày phân công không hợp lệ.");
            }

            // Kiểm tra ngày trả dự kiến có hợp lệ
            if ($this->expectedReturnDate && !strtotime($this->expectedReturnDate)) {
                throw new Exception("Ngày trả dự kiến không hợp lệ.");
            }

            // Kiểm tra ngày trả thực tế có hợp lệ
            if ($this->actualReturnDate && !strtotime($this->actualReturnDate)) {
                throw new Exception("Ngày trả thực tế không hợp lệ.");
            }

            // Kiểm tra người dùng phân công có tồn tại
            if ($this->assignedByUserId) {
                $queryCheckUser = "SELECT COUNT(*) FROM users WHERE user_id = ? AND status != 'ban'";
                $stmtCheckUser = $this->conn->prepare($queryCheckUser);
                $stmtCheckUser->bind_param("i", $this->assignedByUserId);
                $stmtCheckUser->execute();
                if ($stmtCheckUser->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Người phân công với ID {$this->assignedByUserId} không tồn tại hoặc đã bị xóa.");
                }
            }

            // Chuẩn bị truy vấn cập nhật
            $query = "UPDATE device_assignments
                      SET expected_return_date = ?, actual_return_date = ?, status = ?, notes = ?, updated_at = NOW()
                      WHERE assignment_id = ?";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $this->conn->error);
            }

            $stmt->bind_param(
                "ssssi",
                $this->expectedReturnDate,
                $this->actualReturnDate,
                $this->status,
                $this->notes,
                $this->assignmentId
            );

            if (!$stmt->execute()) {
                throw new Exception("Không thể cập nhật phân công: " . $stmt->error);
            }

            // Cập nhật giá trị updated_at trong đối tượng
            $this->updatedAt = date('Y-m-d H:i:s');

            return $stmt->affected_rows > 0;
        } catch (Exception $e) {
            error_log("Lỗi trong update: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lấy toàn bộ danh sách phân công
     * @param mysqli $db Đối tượng kết nối cơ sở dữ liệu
     * @param array $filters Bộ lọc (status)
     * @param int $limit Giới hạn số bản ghi
     * @param int $offset Vị trí bắt đầu
     * @return DeviceAssignments[] Mảng các đối tượng DeviceAssignments
     */
    public static function getAllAssignments($db, $filters = [], $limit = 100, $offset = 0) {
        try {
            $query = "SELECT assignment_id, device_id, user_id, assigned_date, expected_return_date, 
                            actual_return_date, assigned_by_user_id, status, notes, created_at, updated_at 
                    FROM device_assignments 
                    WHERE status != 'deleted'";
            $params = [];
            $types = "";

            if (!empty($filters['status']) && in_array($filters['status'], self::VALID_STATUSES)) {
                $query .= " AND status = ?";
                $params[] = $filters['status'];
                $types .= "s";
            }


            $query .= " ORDER BY assigned_date DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            
            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $db->error);
            }

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result === false) {
                throw new Exception("Không thể thực thi truy vấn: " . $stmt->error);
            }
            // Kiểm tra số lượng bản ghi trả về
            if ($result->num_rows === 0) {
                throw new Exception("Không có bản ghi nào được tìm thấy.");
            }
            
            $assignments = [];
            while ($row = $result->fetch_assoc()) {
                try {
                    $assignments[] = new DeviceAssignments($db, $row);
                } catch (Exception $e) {
                    // Nếu có lỗi khi tạo đối tượng, ghi log và bỏ qua bản ghi này
                    error_log("Lỗi khi tạo đối tượng DeviceAssignments cho bản ghi ID {$row['assignment_id']}: " . $e->getMessage());
                    continue;
                }
            }
    
            return $assignments;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    /**
     * Lấy phân công theo ID
     * @param mysqli $db Đối tượng kết nối cơ sở dữ liệu
     * @param int $assignmentId ID phân công
     * @return DeviceAssignments|null Đối tượng DeviceAssignments hoặc null
     */
    public static function getAssignmentById($db, $assignmentId) {
        try {
            if (!is_int($assignmentId) || $assignmentId <= 0) {
                throw new Exception("Assignment ID phải là số nguyên dương.");
            }
            $query = "SELECT assignment_id, device_id, user_id, assigned_date, expected_return_date, 
                            actual_return_date, assigned_by_user_id, status, notes, created_at, updated_at 
                      FROM device_assignments 
                      WHERE assignment_id = ? AND status != 'deleted'";
            $stmt = $db->prepare($query);
            $stmt->bind_param("i", $assignmentId);
            $stmt->execute();

            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            return $row ? new DeviceAssignments($db, $row) : null;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    /**
     * Xóa mềm phân công
     * @return bool True nếu xóa thành công
     */
    public function delete() {
        try {
            if (!$this->assignmentId) {
                throw new Exception("Assignment ID không được thiết lập.");
            }

            $query = "UPDATE device_assignments SET status = 'deleted', updated_at = NOW() WHERE assignment_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $this->assignmentId);
            $stmt->execute();

            $this->status = 'deleted';
            $this->updatedAt = date('Y-m-d H:i:s');

            return $stmt->affected_rows > 0;
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }
}