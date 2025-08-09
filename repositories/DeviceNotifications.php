<?php
// Path: /network-management/repositories/DeviceNotifications.php

class DeviceNotifications {
    private $conn;
    private $notificationId;
    private $createBy; // Thêm thuộc tính createBy
    private $deviceId;
    private $notificationType;
    private $message;
    private $createdAt;
    private $isRead;
    private $targetUserId;
    private $creatorAvatar; // Thêm thuộc tính creator_avatar
    private $creatorName;   // Thêm thuộc tính creator_name

    // Danh sách giá trị hợp lệ cho notification_type
    private const VALID_NOTIFICATION_TYPES = [
        'inspection_due',
        'incident_report',
        'maintenance_due',
        'status_change',
        'assignment',
        'message'
    ];

    private const DEFAULT_CREATOR_AVATAR = '../assets/img/logo1.png'; // Giá trị mặc định cho avatar
    private const DEFAULT_CREATOR_NAME = 'JHTs Team';                  // Giá trị mặc định cho tên người tạo

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

        $this->notificationId = $data['notification_id'] ?? null;
        $this->setDeviceId($data['device_id'] ?? null);
        $this->setNotificationType($data['notification_type'] ?? null);
        $this->setMessage($data['message'] ?? null);
        $this->createdAt = $data['created_at'] ?? null;
        $this->isRead = $data['is_read'] ?? false;
        $this->setTargetUserId($data['target_user_id'] ?? null);
        $this->createBy = $data['created_by'] ?? null; // Thêm createBy vào constructor
        $this->creatorAvatar = $data['creator_avatar'] ?? self::DEFAULT_CREATOR_AVATAR; // Sử dụng hằng số DEFAULT_CREATOR_AVATAR
        $this->creatorName = $data['creator_name'] ?? self::DEFAULT_CREATOR_NAME;       // Sử dụng hằng số DEFAULT_CREATOR_NAME
    }

    // Getters
    public function getNotificationId() { return $this->notificationId; }
    public function getDeviceId() { return $this->deviceId; }
    public function getNotificationType() { return $this->notificationType; }
    public function getMessage() { return $this->message; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getIsRead() { return $this->isRead; }
    public function getTargetUserId() { return $this->targetUserId; }
    public function getCreateBy() { return $this->createBy; }
    public function getCreatorAvatar() { return $this->creatorAvatar; }
    public function getCreatorName() { return $this->creatorName; }

    // Setters với kiểm tra
    public function setDeviceId($deviceId) {
        if ($deviceId !== null && (!is_int($deviceId) || $deviceId <= 0)) {
            throw new Exception("Device ID phải là số nguyên dương hoặc null.");
        }
        $this->deviceId = $deviceId;
    }

    public function setNotificationType($type) {
        if ($type !== null && !in_array($type, self::VALID_NOTIFICATION_TYPES)) {
            throw new Exception("Loại thông báo không hợp lệ. Giá trị cho phép: " . implode(", ", self::VALID_NOTIFICATION_TYPES));
        }
        $this->notificationType = $type;
    }

    public function setMessage($message) {
        if ($message !== null && empty(trim($message))) {
            throw new Exception("Tin nhắn không được để trống.");
        }
        $this->message = $message;
    }

    public function setTargetUserId($userId) {
        if ($userId !== null && (!is_int($userId) || $userId <= 0)) {
            throw new Exception("Target User ID phải là số nguyên dương.");
        }
        $this->targetUserId = $userId;
    }

    public function setIsRead($isRead) {
        $this->isRead = (bool)$isRead;
    }

    public function setCreateBy($createBy) {
        if ($createBy !== null && (!is_int($createBy) || $createBy <= 0)) {
            throw new Exception("Create By phải là số nguyên dương.");
        }
        $this->createBy = $createBy;
    }

    /**
     * Thêm thông báo vào cơ sở dữ liệu
     * @return int ID của bản ghi vừa thêm
     * @throws Exception Nếu dữ liệu không hợp lệ
     */
    public function insert() {
        try {
            // Kiểm tra dữ liệu bắt buộc
            if (!$this->notificationType || !$this->message) {
                throw new Exception("Thiếu thông tin bắt buộc: notification_type hoặc message.");
            }

            // Kiểm tra thiết bị có tồn tại (nếu có)
            if ($this->deviceId !== null) {
                $queryCheckDevice = "SELECT COUNT(*) FROM devices WHERE device_id = ? AND status != 'inactive'";
                $stmtCheckDevice = $this->conn->prepare($queryCheckDevice);
                $stmtCheckDevice->bind_param("i", $this->deviceId);
                $stmtCheckDevice->execute();
                if ($stmtCheckDevice->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Thiết bị với ID {$this->deviceId} không tồn tại hoặc đã bị xóa.");
                }
            }

            // Kiểm tra target_user_id có tồn tại (nếu có)
            if ($this->targetUserId) {
                $queryCheckUser = "SELECT COUNT(*) FROM users WHERE user_id = ? AND status != 'ban'";
                $stmtCheckUser = $this->conn->prepare($queryCheckUser);
                $stmtCheckUser->bind_param("i", $this->targetUserId);
                $stmtCheckUser->execute();
                if ($stmtCheckUser->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Người nhận thông báo với ID {$this->targetUserId} không tồn tại hoặc đã bị khóa.");
                }
            }

            // Kiểm tra createBy có tồn tại (nếu có)
            if ($this->createBy) {
                $queryCheckUser = "SELECT COUNT(*) FROM users WHERE user_id = ? AND status != 'ban'";
                $stmtCheckUser = $this->conn->prepare($queryCheckUser);
                $stmtCheckUser->bind_param("i", $this->createBy);
                $stmtCheckUser->execute();
                if ($stmtCheckUser->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Người tạo thông báo với ID {$this->createBy} không tồn tại hoặc đã bị khóa.");
                }
            }

            // Thêm bản ghi
            $query = "INSERT INTO device_notifications (device_id, notification_type, message, is_read, target_user_id, created_by) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $this->conn->error);
            }

            $stmt->bind_param(
                "issiii",
                $this->deviceId,
                $this->notificationType,
                $this->message,
                $this->isRead,
                $this->targetUserId,
                $this->createBy
            );

            if (!$stmt->execute()) {
                throw new Exception("Không thể thêm thông báo: " . $stmt->error);
            }

            $this->notificationId = $this->conn->insert_id;
            $this->createdAt = date('Y-m-d H:i:s');
            return $this->notificationId;
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }

    /**
     * Cập nhật thông báo
     * @return bool True nếu cập nhật thành công
     * @throws Exception Nếu có lỗi xảy ra
     */
    public function update() {
        try {
            if (!$this->notificationId) {
                throw new Exception("Notification ID không được thiết lập.");
            }

            // Kiểm tra bản ghi có tồn tại
            $queryCheck = "SELECT COUNT(*) FROM device_notifications WHERE notification_id = ?";
            $stmtCheck = $this->conn->prepare($queryCheck);
            $stmtCheck->bind_param("i", $this->notificationId);
            $stmtCheck->execute();
            if ($stmtCheck->get_result()->fetch_row()[0] == 0) {
                throw new Exception("Thông báo với ID {$this->notificationId} không tồn tại.");
            }

            // Kiểm tra thiết bị có tồn tại (nếu có)
            if ($this->deviceId !== null) {
                $queryCheckDevice = "SELECT COUNT(*) FROM devices WHERE device_id = ? AND status != 'inactive'";
                $stmtCheckDevice = $this->conn->prepare($queryCheckDevice);
                $stmtCheckDevice->bind_param("i", $this->deviceId);
                $stmtCheckDevice->execute();
                if ($stmtCheckDevice->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Thiết bị với ID {$this->deviceId} không tồn tại hoặc đã bị xóa.");
                }
            }

            // Kiểm tra target_user_id có tồn tại (nếu có)
            if ($this->targetUserId) {
                $queryCheckUser = "SELECT COUNT(*) FROM users WHERE user_id = ? AND status != 'ban'";
                $stmtCheckUser = $this->conn->prepare($queryCheckUser);
                $stmtCheckUser->bind_param("i", $this->targetUserId);
                $stmtCheckUser->execute();
                if ($stmtCheckUser->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Người dùng với ID {$this->targetUserId} không tồn tại hoặc đã bị khóa.");
                }
            }

            // Kiểm tra createBy có tồn tại (nếu có)
            if ($this->createBy) {
                $queryCheckUser = "SELECT COUNT(*) FROM users WHERE user_id = ? AND status != 'ban'";
                $stmtCheckUser = $this->conn->prepare($queryCheckUser);
                $stmtCheckUser->bind_param("i", $this->createBy);
                $stmtCheckUser->execute();
                if ($stmtCheckUser->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Người dùng với ID {$this->createBy} không tồn tại hoặc đã bị khóa.");
                }
            }

            // Chuẩn bị truy vấn cập nhật
            $query = "UPDATE device_notifications 
                      SET device_id = ?, notification_type = ?, message = ?, is_read = ?, target_user_id = ?, created_by = ?
                      WHERE notification_id = ?";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $this->conn->error);
            }

            $stmt->bind_param(
                "issiiii",
                $this->deviceId,
                $this->notificationType,
                $this->message,
                $this->isRead,
                $this->targetUserId,
                $this->createBy,
                $this->notificationId
            );

            if (!$stmt->execute()) {
                throw new Exception("Không thể cập nhật thông báo: " . $stmt->error);
            }

            return $stmt->affected_rows > 0;
        } catch (Exception $e) {
            error_log("Lỗi trong update: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lấy tất cả thông báo
     * @param mysqli $db Đối tượng kết nối cơ sở dữ liệu
     * @param array $filters Bộ lọc (notification_type, is_read)
     * @param int $limit Giới hạn số bản ghi
     * @param int $offset Vị trí bắt đầu
     * @return DeviceNotifications[] Mảng các đối tượng DeviceNotifications
     */
    public static function getAllNotifications($db, $filters = [], $limit = 100, $offset = 0) {
        try {
            // Kiểm tra $limit và $offset
            if (!is_int($limit) || $limit < 0) {
                throw new Exception("Limit phải là số nguyên không âm.");
            }
            if (!is_int($offset) || $offset < 0) {
                throw new Exception("Offset phải là số nguyên không âm.");
            }

            // Truy vấn SQL với LEFT JOIN để lấy thông tin người tạo
            $query = "SELECT dn.notification_id, dn.device_id, dn.notification_type, dn.message, dn.created_at, 
                            dn.is_read, dn.target_user_id, dn.created_by, 
                            COALESCE(u.profile_picture, '" . self::DEFAULT_CREATOR_AVATAR . "') AS creator_avatar, 
                            COALESCE(u.fullname, '" . self::DEFAULT_CREATOR_NAME . "') AS creator_name
                    FROM device_notifications dn
                    LEFT JOIN users u ON dn.created_by = u.user_id AND u.status != 'ban'";
            $params = [];
            $types = "";
            $whereClauses = [];

            // Bộ lọc theo notification_type
            if (!empty($filters['notification_type']) && in_array($filters['notification_type'], self::VALID_NOTIFICATION_TYPES)) {
                $whereClauses[] = "dn.notification_type = ?";
                $params[] = $filters['notification_type'];
                $types .= "s";
            }

            // Bộ lọc theo is_read
            if (isset($filters['is_read'])) {
                $whereClauses[] = "dn.is_read = ?";
                $params[] = (int)$filters['is_read'];
                $types .= "i";
            }

            // Thêm các điều kiện WHERE
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }

            // Thêm phân trang
            $query .= " ORDER BY dn.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";

            // Chuẩn bị truy vấn
            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $db->error);
            }
            
            // Gán tham số
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            // Thực thi truy vấn
            $stmt->execute();

            // Lấy kết quả
            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                throw new Exception("Không thể thực thi truy vấn: " . $stmt->error);
            }

            // Xử lý kết quả
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                try {
                    $notifications[] = new DeviceNotifications($db, $row);

                } catch (Exception $e) {
                    error_log("Lỗi khi tạo đối tượng DeviceNotifications cho bản ghi ID {$row['notification_id']}: " . $e->getMessage());
                    continue;
                }
            }

            // Đóng tài nguyên
            $result->close();
            $stmt->close();

            return $notifications;
        } catch (Exception $e) {
            // Ghi log chi tiết hơn
            error_log("Lỗi trong getAllNotifications: " . $e->getMessage() . " | Query: $query | Params: " . json_encode($params));
            return [];
        }
    }

    /**
     * Lấy thông báo theo ID
     * @param mysqli $db Đối tượng kết nối cơ sở dữ liệu
     * @param int $notificationId ID thông báo
     * @return DeviceNotifications|null Đối tượng DeviceNotifications hoặc null
     */
    public static function getNotificationById($db, $notificationId) {
        try {
            if (!is_int($notificationId) || $notificationId <= 0) {
                throw new Exception("Notification ID phải là số nguyên dương.");
            }
            // Kiểm tra bản ghi có tồn tại
            if ($notificationId) {
                $queryCheck = "SELECT COUNT(*) FROM device_notifications WHERE notification_id = ?";
                $stmtCheck = $db->prepare($queryCheck);
                $stmtCheck->bind_param("i", $notificationId);
                $stmtCheck->execute();
                if ($stmtCheck->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Thông báo với ID {$notificationId} không tồn tại.");
                }
            }

            // Lấy thông báo
            $query = "SELECT dn.notification_id, dn.device_id, dn.notification_type, dn.message, dn.created_at, 
                             dn.is_read, dn.target_user_id, dn.created_by, 
                             COALESCE(u.profile_picture, '" . self::DEFAULT_CREATOR_AVATAR . "') AS creator_avatar, 
                             COALESCE(u.fullname, '" . self::DEFAULT_CREATOR_NAME . "') AS creator_name
                      FROM device_notifications dn
                      LEFT JOIN users u ON dn.created_by = u.user_id
                      WHERE dn.notification_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("i", $notificationId);
            $stmt->execute();

            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            return $row ? new DeviceNotifications($db, $row) : null;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    /**
     * Xóa thông báo
     * @return bool True nếu xóa thành công
     */
    public function delete() {
        try {
            if (!$this->notificationId) {
                throw new Exception("Notification ID không được thiết lập.");
            }

            $query = "DELETE FROM device_notifications WHERE notification_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $this->notificationId);
            $stmt->execute();

            return $stmt->affected_rows > 0;
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }

    /**
     * Lấy thông báo theo user_id (target_user_id)
     * @param mysqli $db Đối tượng kết nối cơ sở dữ liệu
     * @param int $userId ID của người dùng (target_user_id)
     * @param array $filters Bộ lọc (notification_type, is_read)
     * @param int $limit Giới hạn số bản ghi
     * @param int $offset Vị trí bắt đầu
     * @return DeviceNotifications[] Mảng các đối tượng DeviceNotifications
     */
    public static function getNotificationsByUserId($db, $userId, $filters = [], $limit = 100, $offset = 0) {
        try {
            // Kiểm tra userId
            if (!is_int($userId) || $userId <= 0) {
                throw new Exception("User ID phải là số nguyên dương.");
            }

            // Kiểm tra $limit và $offset
            if (!is_int($limit) || $limit < 0) {
                throw new Exception("Limit phải là số nguyên không âm.");
            }
            if (!is_int($offset) || $offset < 0) {
                throw new Exception("Offset phải là số nguyên không âm.");
            }

            // Kiểm tra người nhận có tồn tại
            if ($userId) {
                $queryCheckUser = "SELECT COUNT(*) FROM users WHERE user_id = ? AND status != 'ban'";
                $stmtCheckUser = $db->prepare($queryCheckUser);
                $stmtCheckUser->bind_param("i", $userId);
                $stmtCheckUser->execute();
                if ($stmtCheckUser->get_result()->fetch_row()[0] == 0) {
                    throw new Exception("Người dùng với ID {$userId} không tồn tại hoặc đã bị khóa.");
                }
            }

            // Khởi tạo truy vấn
            $query = "SELECT dn.notification_id, dn.device_id, dn.notification_type, dn.message, dn.created_at, 
                            dn.is_read, dn.target_user_id, dn.created_by, 
                            COALESCE(u.profile_picture, '" . self::DEFAULT_CREATOR_AVATAR . "') AS creator_avatar, 
                            COALESCE(u.fullname, '" . self::DEFAULT_CREATOR_NAME . "') AS creator_name
                    FROM device_notifications dn
                    LEFT JOIN users u ON dn.created_by = u.user_id AND u.status != 'ban'
                    WHERE dn.target_user_id = ?";

            $params = [$userId];
            $types = "i";
            $whereClauses = [];

            // Bộ lọc theo notification_type
            if (!empty($filters['notification_type']) && in_array($filters['notification_type'], self::VALID_NOTIFICATION_TYPES)) {
                $whereClauses[] = "dn.notification_type = ?";
                $params[] = $filters['notification_type'];
                $types .= "s";
            }

            // Bộ lọc theo is_read
            if (isset($filters['is_read'])) {
                $whereClauses[] = "dn.is_read = ?";
                $params[] = (int)$filters['is_read'];
                $types .= "i";
            }

            // Thêm các điều kiện WHERE
            if (!empty($whereClauses)) {
                $query .= " AND " . implode(" AND ", $whereClauses);
            }

            // Thêm phân trang
            $query .= " ORDER BY dn.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";

            // Chuẩn bị truy vấn
            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn: " . $db->error);
            }

            // Gán tham số
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            // Thực thi truy vấn
            $stmt->execute();

            // Lấy kết quả
            $result = $stmt->get_result();
            if ($result === false) {
                $stmt->close();
                throw new Exception("Không thể thực thi truy vấn: " . $stmt->error);
            }

            // Xử lý kết quả
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                try {
                    $notifications[] = new DeviceNotifications($db, $row);
                } catch (Exception $e) {
                    error_log("Lỗi khi tạo đối tượng DeviceNotifications cho bản ghi ID {$row['notification_id']}: " . $e->getMessage());
                    continue;
                }
            }

            // Đóng tài nguyên
            $result->close();
            $stmt->close();

            return $notifications;
        } catch (Exception $e) {
            error_log("Lỗi trong getNotificationsByUserId: " . $e->getMessage());
            return [];
        }
    }   
}