<?php
// Path: /network-management/repositories/DeviceMaintenanceRecords.php

class DeviceMaintenanceRecords {
    private $conn;
    private $recordId;
    private $deviceId;
    private $reportedByUserId;
    private $performedByUserId;
    private $maintenanceDate;
    private $completionDate;
    private $description;
    private $notes;
    private $cost;
    private $status;
    private $createdAt;
    private $updatedAt;

    private const VALID_STATUSES = ['pending', 'in_progress', 'completed', 'cancelled', 'schedule_maintenance'];

    /**
     * Constructor
     * @param mysqli|PDO $db Đối tượng kết nối cơ sở dữ liệu
     * @param array $data Dữ liệu khởi tạo (nếu có)
     */
    public function __construct($db, array $data = []) {
        if (!($db instanceof mysqli) && !($db instanceof PDO)) {
            throw new Exception('Kết nối cơ sở dữ liệu không hợp lệ.');
        }
        $this->conn = $db;

        $this->recordId = $data['record_id'] ?? null;
        $this->setDeviceId($data['device_id'] ?? null);
        $this->setReportedByUserId($data['reported_by_user_id'] ?? null);
        $this->setPerformedByUserId($data['performed_by_user_id'] ?? null);
        $this->setMaintenanceDate($data['maintenance_date'] ?? null);
        $this->setCompletionDate($data['completion_date'] ?? null);
        $this->setDescription($data['description'] ?? null);
        $this->setNotes($data['notes'] ?? null);
        $this->setCost($data['cost'] ?? null);
        $this->setStatus($data['status'] ?? 'pending');
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    // Getters
    public function getRecordId(): ?int { return $this->recordId; }
    public function getDeviceId(): ?int { return $this->deviceId; }
    public function getReportedByUserId(): ?int { return $this->reportedByUserId; }
    public function getPerformedByUserId(): ?int { return $this->performedByUserId; }
    public function getMaintenanceDate(): ?string { return $this->maintenanceDate; }
    public function getCompletionDate(): ?string { return $this->completionDate; }
    public function getDescription(): ?string { return $this->description; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCost(): ?float { return $this->cost; }
    public function getStatus(): ?string { return $this->status; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }

    // Setters với kiểm tra hợp lệ
    public function setDeviceId($deviceId): void {
        if ($deviceId !== null && (!is_int($deviceId) || $deviceId <= 0)) {
            throw new Exception('Device ID phải là số nguyên dương.');
        }
        $this->deviceId = $deviceId;
    }

    public function setReportedByUserId($userId): void {
        if ($userId !== null && (!is_int($userId) || $userId <= 0)) {
            throw new Exception('Reported By User ID phải là số nguyên dương.');
        }
        $this->reportedByUserId = $userId;
    }

    public function setPerformedByUserId($userId): void {
        if ($userId !== null && (!is_int($userId) || $userId <= 0)) {
            throw new Exception('Performed By User ID phải là số nguyên dương.');
        }
        $this->performedByUserId = $userId;
    }

    public function setMaintenanceDate($date): void {
        if ($date !== null) {
            if (!strtotime($date)) {
                throw new Exception("Ngày bảo trì không hợp lệ. Định dạng phải là 'YYYY-MM-DD'.");
            }
        }
        $this->maintenanceDate = $date;
    }

    public function setCompletionDate($date): void {
        if ($date !== null) {
            if (!strtotime($date)) {
                throw new Exception("Ngày hoàn thành không hợp lệ. Định dạng phải là 'YYYY-MM-DD'.");
            }
            if ($this->maintenanceDate && strtotime($date) < strtotime($this->maintenanceDate)) {
                throw new Exception('Ngày hoàn thành không được nhỏ hơn ngày bảo trì.');
            }
        }
        $this->completionDate = $date;
    }

    public function setDescription($description): void {
        if ($description !== null && empty(trim($description))) {
            throw new Exception('Mô tả không được để trống.');
        }
        $this->description = $description;
    }

    public function setNotes($notes): void {
        $this->notes = $notes;
    }

    public function setCost($cost): void {
        if ($cost !== null && (!is_numeric($cost) || $cost < 0)) {
            throw new Exception('Chi phí phải là số không âm.');
        }
        $this->cost = $cost;
    }

    public function setStatus($status): void {
        if ($status !== null && !in_array($status, self::VALID_STATUSES)) {
            throw new Exception('Trạng thái không hợp lệ. Giá trị cho phép: ' . implode(', ', self::VALID_STATUSES));
        }
        $this->status = $status;
    }

    /**
     * Lưu bản ghi bảo trì (thêm mới hoặc cập nhật)
     * @return int|bool ID nếu thêm mới, true nếu cập nhật thành công
     */
    public function save() {
        if ($this->recordId) {
            return $this->update();
        }
        return $this->insert();
    }

    /**
     * Thêm mới bản ghi bảo trì
     * @return int ID của bản ghi vừa tạo
     */
    public function insert(): int {
        try {
            $this->validateRequiredFields();
            $this->validateDeviceExists();

            $query = 'INSERT INTO device_maintenance_records (
                device_id, reported_by_user_id, performed_by_user_id, maintenance_date, 
                completion_date, description, notes, cost, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Không thể chuẩn bị truy vấn: ' . $this->conn->error);
            }

            $stmt->bind_param(
                'iiissssds',
                $this->deviceId,
                $this->reportedByUserId,
                $this->performedByUserId,
                $this->maintenanceDate,
                $this->completionDate,
                $this->description,
                $this->notes,
                $this->cost,
                $this->status
            );

            if (!$stmt->execute()) {
                throw new Exception('Không thể thêm bản ghi: ' . $stmt->error);
            }

            $this->recordId = $this->conn->insert_id;
            $this->createdAt = date('Y-m-d H:i:s');
            $this->updatedAt = $this->createdAt;
            $stmt->close();
            return $this->recordId;
        } catch (Exception $e) {
            error_log('Lỗi trong insert: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cập nhật bản ghi bảo trì
     * @return bool True nếu thành công
     */
    public function update(): bool {
        try {
            // Kiểm tra record_id
            if (!$this->recordId) {
                throw new Exception('Record ID không được thiết lập.');
            }
    
            // Kiểm tra sự tồn tại của bản ghi
            $this->validateRecordExists();
    
            // Kiểm tra sự tồn tại của thiết bị nếu device_id được cung cấp
            if ($this->deviceId !== null) {
                $this->validateDeviceExists();
            }
    
            // Kiểm tra các trường bắt buộc
            if ($this->deviceId === null) {
                throw new Exception('Thiếu thông tin bắt buộc: device_id.');
            }
            if ($this->reportedByUserId === null) {
                throw new Exception('Thiếu thông tin bắt buộc: reported_by_user_id.');
            }
            if ($this->maintenanceDate === null) {
                throw new Exception('Thiếu thông tin bắt buộc: maintenance_date.');
            }
            if ($this->description === null) {
                throw new Exception('Thiếu thông tin bắt buộc: description.');
            }
    
            // Bắt đầu giao dịch
            $this->conn->begin_transaction();
    
            // Xây dựng truy vấn động dựa trên các trường đã thay đổi
            $fields = [];
            $params = [];
            $types = '';
    
            // Thêm các cột chỉ khi chúng không phải là null
            if ($this->deviceId !== null) {
                $fields[] = 'device_id = ?';
                $params[] = $this->deviceId;
                $types .= 'i';
            }
            if ($this->reportedByUserId !== null) {
                $fields[] = 'reported_by_user_id = ?';
                $params[] = $this->reportedByUserId;
                $types .= 'i';
            }
            if ($this->performedByUserId !== null) {
                $fields[] = 'performed_by_user_id = ?';
                $params[] = $this->performedByUserId;
                $types .= 'i';
            }
            if ($this->maintenanceDate !== null) {
                $fields[] = 'maintenance_date = ?';
                $params[] = $this->maintenanceDate;
                $types .= 's';
            }
            if ($this->completionDate !== null) {
                $fields[] = 'completion_date = ?';
                $params[] = $this->completionDate;
                $types .= 's';
            }
            if ($this->description !== null) {
                $fields[] = 'description = ?';
                $params[] = $this->description;
                $types .= 's';
            }
            if ($this->notes !== null) {
                $fields[] = 'notes = ?';
                $params[] = $this->notes;
                $types .= 's';
            }
            if ($this->cost !== null) {
                $fields[] = 'cost = ?';
                $params[] = $this->cost;
                $types .= 'd';
            }
            if ($this->status !== null) {
                $fields[] = 'status = ?';
                $params[] = $this->status;
                $types .= 's';
            }
    
            // Luôn cập nhật updated_at
            $fields[] = 'updated_at = NOW()';
    
            // Kiểm tra xem có trường nào để cập nhật không
            if (empty($fields)) {
                throw new Exception('Không có trường nào để cập nhật.');
            }
    
            // Tạo truy vấn
            $query = 'UPDATE device_maintenance_records SET ' . implode(', ', $fields) . ' WHERE record_id = ?';
            $params[] = $this->recordId;
            $types .= 'i';
    
            // Ghi log để gỡ lỗi
            error_log("Updating record_id: $this->recordId, query: $query, params: " . json_encode($params));
    
            // Chuẩn bị truy vấn
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Không thể chuẩn bị truy vấn: ' . $this->conn->error);
            }
    
            // Gán tham số
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
    
            // Thực thi truy vấn
            if (!$stmt->execute()) {
                throw new Exception('Không thể cập nhật bản ghi: ' . $stmt->error);
            }
    
            // Kiểm tra số hàng bị ảnh hưởng
            $affected = $stmt->affected_rows > 0;
            if ($affected) {
                $this->updatedAt = date('Y-m-d H:i:s');
                error_log("Update successful for record_id: $this->recordId, affected rows: $stmt->affected_rows");
            } else {
                error_log("No rows updated for record_id: $this->recordId");
            }
    
            // Đóng statement
            $stmt->close();
    
            // Commit giao dịch
            $this->conn->commit();
    
            return $affected;
        } catch (Exception $e) {
            // Rollback giao dịch nếu có lỗi
            $this->conn->rollback();
            error_log('Lỗi trong update: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Xóa bản ghi bảo trì (soft delete)
     * @return bool True nếu thành công
     */
    public function delete(): bool {
        try {
            if (!$this->recordId) {
                throw new Exception('Record ID không được thiết lập.');
            }
            $this->validateRecordExists();

            $query = 'UPDATE device_maintenance_records 
                      SET status = "cancelled", performed_by_user_id = NULL, completion_date = NULL, 
                          description = NULL, notes = NULL, cost = NULL, updated_at = NOW()
                      WHERE record_id = ?';
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Không thể chuẩn bị truy vấn: ' . $this->conn->error);
            }

            $stmt->bind_param('i', $this->recordId);
            if (!$stmt->execute()) {
                throw new Exception('Không thể xóa bản ghi: ' . $stmt->error);
            }

            $affected = $stmt->affected_rows > 0;
            if ($affected) {
                $this->setStatus('cancelled');
                $this->setPerformedByUserId(null);
                $this->setCompletionDate(null);
                $this->setDescription(null);
                $this->setNotes(null);
                $this->setCost(null);
                $this->updatedAt = date('Y-m-d H:i:s');
            }
            $stmt->close();
            return $affected;
        } catch (Exception $e) {
            error_log('Lỗi trong delete: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Phân công kỹ thuật viên
     * @param int $technicianId ID kỹ thuật viên
     * @return bool True nếu thành công
     */
    public function assignTechnician(int $technicianId): bool {
        try {
            if (!$this->recordId) {
                throw new Exception('Record ID không được thiết lập.');
            }
            $this->validateRecordExists();

            $query = 'SELECT status FROM device_maintenance_records WHERE record_id = ?';
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Không thể chuẩn bị truy vấn: ' . $this->conn->error);
            }
            $stmt->bind_param('i', $this->recordId);
            $stmt->execute();
            $status = $stmt->get_result()->fetch_assoc()['status'];
            $stmt->close();

            if ($status === 'cancelled') {
                throw new Exception('Không thể phân công cho bản ghi đã bị hủy.');
            }
            if ($status === 'completed') {
                throw new Exception('Không thể phân công cho bản ghi đã hoàn thành.');
            }

            $this->setPerformedByUserId($technicianId);

            $query = 'UPDATE device_maintenance_records 
                      SET performed_by_user_id = ?, updated_at = NOW() 
                      WHERE record_id = ?';
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Không thể chuẩn bị truy vấn: ' . $this->conn->error);
            }

            $stmt->bind_param('ii', $this->performedByUserId, $this->recordId);
            if (!$stmt->execute()) {
                throw new Exception('Không thể phân công kỹ thuật viên: ' . $stmt->error);
            }

            $affected = $stmt->affected_rows > 0;
            if ($affected) {
                $this->updatedAt = date('Y-m-d H:i:s');
            }
            $stmt->close();
            return $affected;
        } catch (Exception $e) {
            error_log('Lỗi trong assignTechnician: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cập nhật trạng thái bản ghi
     * @param string $status Trạng thái mới
     * @param string|null $completionDate Ngày hoàn thành (tùy chọn)
     * @param string|null $notes Ghi chú (tùy chọn)
     * @return bool True nếu thành công
     */
    public function updateStatus(string $status, ?string $completionDate = null, ?string $notes = null): bool {
        try {
            if (!$this->recordId) {
                throw new Exception('Record ID không được thiết lập.');
            }
            $this->validateRecordExists();

            $this->setStatus($status);
            $this->setCompletionDate($completionDate);
            $this->setNotes($notes);

            $query = 'UPDATE device_maintenance_records 
                      SET status = ?, completion_date = ?, notes = ?, updated_at = NOW() 
                      WHERE record_id = ?';
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Không thể chuẩn bị truy vấn: ' . $this->conn->error);
            }

            $stmt->bind_param('sssi', $this->status, $this->completionDate, $this->notes, $this->recordId);
            if (!$stmt->execute()) {
                throw new Exception('Không thể cập nhật trạng thái: ' . $stmt->error);
            }

            $affected = $stmt->affected_rows > 0;
            if ($affected) {
                $this->updatedAt = date('Y-m-d H:i:s');
            }
            $stmt->close();
            return $affected;
        } catch (Exception $e) {
            error_log('Lỗi trong updateStatus: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Yêu cầu bảo trì mới
     * @param mysqli|PDO $db Đối tượng kết nối cơ sở dữ liệu
     * @param int $userId ID người yêu cầu
     * @param int $deviceId ID thiết bị
     * @param string $description Mô tả
     * @param string $maintenanceDate Ngày bảo trì
     * @return int ID của bản ghi vừa tạo
     */
    public static function requestMaintenance($db, int $userId, int $deviceId, string $description, string $maintenanceDate): int {
        try {
            $record = new DeviceMaintenanceRecords($db, [
                'device_id' => $deviceId,
                'reported_by_user_id' => $userId,
                'maintenance_date' => $maintenanceDate,
                'description' => $description,
                'status' => 'pending'
            ]);
            return $record->insert();
        } catch (Exception $e) {
            error_log('Lỗi trong requestMaintenance: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Đặt lịch bảo trì
     * @param int $userId ID người thực hiện
     * @param int $deviceId ID thiết bị
     * @param int $reportedByUserId ID người báo cáo
     * @param string $maintenanceDate Ngày bảo trì
     * @param string $description Mô tả
     * @param string|null $notes Ghi chú
     * @return int ID của bản ghi vừa tạo
     */
    public function scheduleMaintenance(int $userId, int $deviceId, int $reportedByUserId, string $maintenanceDate, string $description, ?string $notes = null): int {
        try {
            $this->setDeviceId($deviceId);
            $this->setReportedByUserId($reportedByUserId);
            $this->setMaintenanceDate($maintenanceDate);
            $this->setDescription($description);
            $this->setNotes($notes);
            $this->setStatus('schedule_maintenance');

            $this->validateDeviceExists();
            return $this->insert();
        } catch (Exception $e) {
            error_log('Lỗi trong scheduleMaintenance: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lấy bản ghi bảo trì theo ID
     * @param mysqli|PDO $db Đối tượng kết nối cơ sở dữ liệu
     * @param int $recordId ID bản ghi
     * @return DeviceMaintenanceRecords|null
     */
    public static function getMaintenanceById($db, int $recordId): ?DeviceMaintenanceRecords {
        try {
            if ($recordId <= 0) {
                throw new Exception('Record ID phải là số nguyên dương.');
            }

            $query = 'SELECT * FROM device_maintenance_records WHERE record_id = ? AND status != "cancelled"';
            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception('Không thể chuẩn bị truy vấn: ' . $db->error);
            }

            $stmt->bind_param('i', $recordId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            return $row ? new DeviceMaintenanceRecords($db, $row) : null;
        } catch (Exception $e) {
            error_log('Lỗi trong getMaintenanceById: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Lấy tất cả bản ghi bảo trì
     * @param mysqli|PDO $db Đối tượng kết nối cơ sở dữ liệu
     * @param array $filters Bộ lọc
     * @param int $limit Giới hạn
     * @param int $offset Offset
     * @return DeviceMaintenanceRecords[]
     */
    public static function getAllRecords($db, array $filters = [], int $limit = 100, int $offset = 0): array {
        try {
            if ($limit < 0) {
                throw new Exception('Limit phải không âm.');
            }
            if ($offset < 0) {
                throw new Exception('Offset phải không âm.');
            }

            $query = 'SELECT * FROM device_maintenance_records WHERE status != "cancelled"'; //Thêm điều kiện khác cancelled
            $params = [];
            $types = '';
            $conditions = [];

            // Chỉ lọc nếu có tham số cụ thể
            if (!empty($filters['status'])) {
                if (!in_array($filters['status'], self::VALID_STATUSES)) {
                    throw new Exception('Trạng thái bộ lọc không hợp lệ: ' . $filters['status'] . '. Giá trị cho phép: ' . implode(', ', self::VALID_STATUSES));
                }
                $conditions[] = 'status = ?';
                $params[] = $filters['status'];
                $types .= 's';
            }

            if (!empty($filters['device_id']) && is_int($filters['device_id']) && $filters['device_id'] > 0) {
                $conditions[] = 'device_id = ?';
                $params[] = $filters['device_id'];
                $types .= 'i';
            }

            if ($conditions) {
                $query .= ' WHERE ' . implode(' AND ', $conditions);
            }

            $query .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';

            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception('Không thể chuẩn bị truy vấn: ' . $db->error);
            }

            if ($params) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $records = [];
            while ($row = $result->fetch_assoc()) {
                $records[] = new DeviceMaintenanceRecords($db, $row);
            }

            $stmt->close();
            return $records;
        } catch (Exception $e) {
            error_log('Lỗi trong getAllRecords (filters: ' . json_encode($filters) . '): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Lấy bản ghi được phân công
     * @param mysqli|PDO $db Đối tượng kết nối cơ sở dữ liệu
     * @param int $userId ID kỹ thuật viên
     * @param array $filters Bộ lọc
     * @param int $limit Giới hạn
     * @param int $offset Offset
     * @return DeviceMaintenanceRecords[]
     */
    public static function getAssignedRecords($db, int $userId, array $filters = [], int $limit = 100, int $offset = 0): array {
        try {
            if ($limit < 0) {
                throw new Exception('Limit phải không âm.');
            }
            if ($offset < 0) {
                throw new Exception('Offset phải không âm.');
            }

            $query = 'SELECT * FROM device_maintenance_records WHERE performed_by_user_id = ?';
            $params = [$userId];
            $types = 'i';
            $conditions = [];

            // Chỉ lọc nếu có tham số cụ thể
            if (!empty($filters['status'])) {
                if (!in_array($filters['status'], self::VALID_STATUSES)) {
                    throw new Exception('Trạng thái bộ lọc không hợp lệ: ' . $filters['status'] . '. Giá trị cho phép: ' . implode(', ', self::VALID_STATUSES));
                }
                $conditions[] = 'status = ?';
                $params[] = $filters['status'];
                $types .= 's';
            }

            if ($conditions) {
                $query .= ' AND ' . implode(' AND ', $conditions);
            }

            $query .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';

            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception('Không thể chuẩn bị truy vấn: ' . $db->error);
            }

            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $records = [];
            while ($row = $result->fetch_assoc()) {
                $records[] = new DeviceMaintenanceRecords($db, $row);
            }

            $stmt->close();
            return $records;
        } catch (Exception $e) {
            error_log('Lỗi trong getAssignedRecords (user_id: ' . $userId . ', filters: ' . json_encode($filters) . '): ' . $e->getMessage());
            return [];
        }
    }

    // Các phương thức kiểm tra hợp lệ
    private function validateRequiredFields(): void {
        if (!$this->deviceId) {
            throw new Exception('Thiếu thông tin bắt buộc: device_id.');
        }
        if (!$this->reportedByUserId) {
            throw new Exception('Thiếu thông tin bắt buộc: reported_by_user_id.');
        }
        if (!$this->maintenanceDate) {
            throw new Exception('Thiếu thông tin bắt buộc: maintenance_date.');
        }
        if (!$this->description) {
            throw new Exception('Thiếu thông tin bắt buộc: description.');
        }
    }

    private function validateDeviceExists(): void {
        try {
            $query = 'SELECT COUNT(*) FROM devices WHERE device_id = ? AND status != "deleted"';
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Không thể chuẩn bị truy vấn kiểm tra thiết bị: ' . $this->conn->error);
            }
            $stmt->bind_param('i', $this->deviceId);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_row()[0];
            $stmt->close();

            if ($count == 0) {
                throw new Exception("Thiết bị với ID {$this->deviceId} không tồn tại hoặc đã bị xóa.");
            }
        } catch (mysqli_sql_exception $e) {
            throw new Exception('Lỗi khi kiểm tra thiết bị: ' . $e->getMessage());
        }
    }

    private function validateRecordExists(): void {
        try {
            if (!$this->recordId) {
                throw new Exception('Record ID không được thiết lập.');
            }


            $query = 'SELECT COUNT(*) FROM device_maintenance_records WHERE record_id = ? AND status != "cancelled"';
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Không thể chuẩn bị truy vấn kiểm tra bản ghi: ' . $this->conn->error);
            }
            $stmt->bind_param('i', $this->recordId);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_row()[0];
            $stmt->close();

            if ($count == 0) {
                throw new Exception("Bản ghi với ID {$this->recordId} không tồn tại hoặc đã bị hủy.");
            }
        } catch (mysqli_sql_exception $e) {
            throw new Exception('Lỗi khi kiểm tra bản ghi: ' . $e->getMessage());
        }
    }
}