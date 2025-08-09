<?php
// Path: /network-management/models/DeviceModel.php

class DeviceModel {
    private $conn;
    private $deviceId;
    private $image;
    private $deviceName;
    private $deviceType;
    private $ipAddress;
    private $macAddress;
    private $status;
    private $location;
    private $lastMaintenance;
    private $updatedAt;
    private $purchaseDate;  // Đã thêm
    private $price; // Đã thêm


    /**
     * Constructor
     * @param mysqli|PDO $db Đối tượng kết nối cơ sở dữ liệu
     * @param array $data Dữ liệu khởi tạo (nếu có)
     */
    public function __construct($db, $data = []) {
        if ($db instanceof mysqli || $db instanceof PDO) {
            $this->conn = $db;
        } else {
            throw new Exception("Kết nối cơ sở dữ liệu không hợp lệ.");
        }

        $this->deviceId = $data['device_id'] ?? null;
        $this->deviceName = $data['device_name'] ?? null;
        $this->deviceType = $data['device_type'] ?? null;
        $this->ipAddress = $data['ip_address'] ?? null;
        $this->macAddress = $data['mac_address'] ?? null;
        $this->status = $data['status'] ?? 'active'; // Giá trị mặc định từ schema
        $this->location = $data['location'] ?? null;
        $this->lastMaintenance = $data['last_maintenance'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
        $this->purchaseDate = $data['purchase_date'] ?? null;
        $this->price = $data['price'] ?? null;
        $this->image = $data['image'] ?? null;

    
    }

    // Getters
    public function getDeviceId() { return $this->deviceId; }
    public function getDeviceName() { return $this->deviceName; }
    public function getDeviceType() { return $this->deviceType; }
    public function getIpAddress() { return $this->ipAddress; }
    public function getMacAddress() { return $this->macAddress; }
    public function getStatus() { return $this->status; }
    public function getLocation() { return $this->location; }
    public function getLastMaintenance() { return $this->lastMaintenance; }
    public function getUpdatedAt() { return $this->updatedAt; }
    public function getImage() { return $this->image; }

    // Setters
    public function setDeviceName($deviceName) { $this->deviceName = $deviceName; }
    public function setDeviceType($deviceType) { $this->deviceType = $deviceType; }
    public function setIpAddress($ipAddress) { $this->ipAddress = $ipAddress; }
    public function setMacAddress($macAddress) { $this->macAddress = $macAddress; }
    public function setStatus($status) { $this->status = $status; }
    public function setLocation($location) { $this->location = $location; }
    public function setLastMaintenance($lastMaintenance) { $this->lastMaintenance = $lastMaintenance; }
    public function setImage($image) { $this->image = $image; }

    /**
     * Lưu thiết bị (thêm mới hoặc cập nhật)
     * @return mixed ID của thiết bị nếu thêm mới, true nếu cập nhật thành công, false nếu thất bại
     */
    public function save() { // Thêm mới hoặc cập nhật thiết bị
        if ($this->deviceId) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    /**
     * Thêm thiết bị mới vào cơ sở dữ liệu
     * @return int ID của thiết bị vừa thêm
     */
    private function insert() {
        try {
            $query = "INSERT INTO devices (device_name, device_type, ip_address, mac_address, status, location,purchase_date, last_maintenance,price, image) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $this->conn->error);
            }

            $stmt->bind_param(
                "ssssssssss", // Chuỗi định nghĩa kiểu phải có 10 ký tự
                $this->deviceName,
                $this->deviceType,
                $this->ipAddress,
                $this->macAddress,
                $this->status,
                $this->location,
                $this->purchaseDate,
                $this->lastMaintenance,
                $this->price,
                $this->image
            );

            if (!$stmt->execute()) {
                throw new Exception("Không thể thêm thiết bị: " . $stmt->error);
            }

            $this->deviceId = $this->conn->insert_id;
            return $this->deviceId;
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e; // Ném lại exception để xử lý ở nơi khác nếu cần
        }
    }

    /**
     * Cập nhật thông tin thiết bị
     * @return bool True nếu cập nhật thành công, false nếu thất bại
     */
    private function update() {
        try {
            // $query = "UPDATE devices SET device_name = ?, device_type = ?, ip_address = ?, mac_address = ?, status = ?, location = ?, last_maintenance = ?, updated_at = NOW()
            //           WHERE device_id = ?";
            //Cũ, cập nhật cả image khi không truyền vào image dẫn đến image tự động gán giá trị 0 thay vì là đường dẫn hình ảnh 
            $query = "UPDATE devices SET device_name = ?, device_type = ?, ip_address = ?, mac_address = ?, status = ?, location = ?, last_maintenance = ?, updated_at = NOW(), image = ? 
            WHERE device_id = ?";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $this->conn->error);
            }
    
            $stmt->bind_param(
                // "sssssssi",
                "sssssssii",
                $this->deviceName,
                $this->deviceType,
                $this->ipAddress,
                $this->macAddress,
                $this->status,
                $this->location,
                $this->lastMaintenance,
                $this->image,
                $this->deviceId


            );
    
            if (!$stmt->execute()) {
                throw new Exception("Không thể cập nhật thiết bị: " . $stmt->error);
            }
    
            return $this->deviceId; // Trả về device_id nếu thành công
        } catch (Exception $e) {
            error_log($e->getMessage()); // Ghi log lỗi
            throw $e; // Ném lại exception để xử lý ở nơi khác
        }
    }

    /**
     * Đánh dấu thiết bị là đã xóa (soft delete) thay vì xóa hoàn toàn
     * @return bool True nếu cập nhật thành công, false nếu thất bại
     */
    public function delete() {
        try {
            if (!$this->deviceId) {
                throw new Exception("ID thiết bị không được thiết lập.");
            }

            // Giả định các trường không cần thiết sau khi xóa: ip_address, mac_address, last_maintenance
            $query = "UPDATE devices 
                    SET status = 'inactive', 
                        ip_address = NULL, 
                        mac_address = NULL, 
                        last_maintenance = NULL, 
                        updated_at = NOW() 
                    WHERE device_id = ?";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $this->conn->error);
            }

            $stmt->bind_param("i", $this->deviceId);
            if (!$stmt->execute()) {
                throw new Exception("Không thể đánh dấu thiết bị là đã xóa: " . $stmt->error);
            }

            // Cập nhật trạng thái trong đối tượng hiện tại
            // $this->status = 'deleted';
            $this->status = 'inactive';
            $this->ipAddress = null;
            $this->macAddress = null;
            $this->lastMaintenance = null;

            return $stmt->affected_rows > 0;
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e; // Ném lại exception để xử lý ở nơi khác nếu cần
        }
    }

    /**
     * Tìm tất cả thiết bị với tùy chọn tìm kiếm và phân trang, loại bỏ thiết bị đã bị xóa
     * @param mysqli|PDO $db Đối tượng kết nối cơ sở dữ liệu
     * @param array $searchParams Tham số tìm kiếm
     * @param int $limit Giới hạn số bản ghi
     * @param int $offset Vị trí bắt đầu
     * @return DeviceModel[] Mảng các đối tượng DeviceModel
     */
    public static function findAll($db, $searchParams = [], $limit = 100, $offset = 0) {
        try {
            $query = "SELECT device_id, device_name, device_type, ip_address, mac_address, status, location, price, last_maintenance, updated_at, image 
                      FROM devices";
            $params = [];
            $types = "";
            $conditions = [];

            // Loại bỏ thiết bị đã bị xóa
            $conditions[] = "status != 'inactive'";
            
            if (!empty($searchParams['device_name'])) {
                $conditions[] = "device_name LIKE ?";
                $params[] = '%' . trim($searchParams['device_name']) . '%';
                $types .= "s";
            }

            if (!empty($searchParams['device_type'])) {
                $conditions[] = "device_type = ?";
                $params[] = trim($searchParams['device_type']);
                $types .= "s";
            }
            // Sửa lại để đám bảo hiện cả thiết bị mantenance và active 
            if (!empty($searchParams['status'])) {
                if (is_array($searchParams['status'])) {
                    // Nếu status là mảng, sử dụng câu lệnh IN
                    $placeholders = implode(',', array_fill(0, count($searchParams['status']), '?'));
                    $conditions[] = "status IN ($placeholders)";
                    foreach ($searchParams['status'] as $status) {
                        $params[] = trim($status); // Đảm bảo từng phần tử là chuỗi
                        $types .= "s";
                    }
                } else {
                    // Nếu status là chuỗi, xử lý bình thường
                    $conditions[] = "status = ?";
                    $params[] = trim($searchParams['status']);
                    $types .= "s";
                }
            }

            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }

            $query .= " LIMIT ? OFFSET ?";
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

            if (!$stmt->execute()) {
                throw new Exception("Không thể thực thi truy vấn: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $devices = [];
            while ($row = $result->fetch_assoc()) {
                $devices[] = $row;
            }

            return $devices;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    /**
     * Tìm thiết bị theo ID, chỉ trả về nếu chưa bị xóa
     * @param mysqli|PDO $db Đối tượng kết nối cơ sở dữ liệu
     * @param int $deviceId ID của thiết bị
     * @return DeviceModel|null Đối tượng DeviceModel hoặc null nếu không tìm thấy hoặc đã bị xóa
     */
    public static function findById($db, $deviceId) {
        try {
            $query = "SELECT device_id, device_name, device_type, ip_address, mac_address, status, location, last_maintenance, updated_at, image 
                      FROM devices WHERE device_id = ? AND status != 'inactive'";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $db->error);
            }

            $stmt->bind_param("i", $deviceId);
            if (!$stmt->execute()) {
                throw new Exception("Không thể thực thi truy vấn: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            // return $row ? $row : null;
            // Nếu tìm thấy thiết bị, trả về instance của DeviceModel
            if ($row) {
                return new DeviceModel($db, $row);
            } else {
                return null;
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
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
     * Đếm số lượng tất cả các thiết bị trong bảng devices
     * @param mysqli|PDO $db Đối tượng kết nối cơ sở dữ liệu
     * @return int Số lượng thiết bị
     */
    public static function countDevice($db) {
        try {
            $query = "SELECT COUNT(*) as total FROM devices ";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $db->error);
            }

            if (!$stmt->execute()) {
                throw new Exception("Không thể thực thi truy vấn: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

        return (int) $row['total'];
    } catch (Exception $e) {
        error_log($e->getMessage());
        return 0;
    }
}
public static function countByStatus($db, $status) {
    try {
        $query = "SELECT COUNT(*) as total FROM devices WHERE status = ?";
        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $db->error);
        }

        $stmt->bind_param("s", $status);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute query: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc(); 

        return (int) $row['total'];
    } catch (Exception $e) {
        error_log($e->getMessage());
        return 0;
    }

}
}