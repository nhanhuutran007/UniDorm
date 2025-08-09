<?php
// Path: /network-management/functions/device_maintenance/DeviceMaintenanceManager.php

require_once __DIR__ . '/../../repositories/DeviceMaintenanceRecords.php';

class DeviceMaintenanceManager {
    private $db;
    private $userId;

    /**
     * Constructor
     * @param mysqli|PDO $db Đối tượng kết nối cơ sở dữ liệu
     * @param int $userId ID người dùng
     */
    public function __construct($db, $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }

    /**
     * Lấy vai trò người dùng từ bảng Users
     * @param int $userId ID người dùng
     * @return string Vai trò (admin, technician, staff)
     * @throws Exception Nếu người dùng không tồn tại hoặc không hoạt động
     */
    private function getUserRole($userId): string {
        $query = 'SELECT role, status FROM users WHERE user_id = ?';
        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            throw new Exception('Không thể chuẩn bị truy vấn kiểm tra người dùng.');
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            throw new Exception("Người dùng với ID $userId không tồn tại.");
        }
        if ($result['status'] !== 'active') {
            throw new Exception("Người dùng với ID $userId không hoạt động.");
        }
        return $result['role'];
    }

    /**
     * Kiểm tra quyền người dùng dựa trên vai trò
     * @param string $action Hành động cần kiểm tra
     * @param string $role Vai trò người dùng
     * @return bool True nếu có quyền, False nếu không
     */
    private function checkUserPermission($action, $role): bool {
        $permissions = [
            'admin' => [
                'add_maintenance',
                'update_maintenance',
                'delete_maintenance',
                'get_all_maintenance',
                'schedule_maintenance',
                'assign_technician_maintenance'
            ],
            'technician' => [
                'request_maintenance',
                'update_status_maintenance',
                'get_assigned_maintenance'
            ],
            'staff' => [
                'request_maintenance'
            ]
        ];

        return in_array($action, $permissions[$role] ?? []);
    }

    /**
     * Xử lý tất cả hành động bảo trì thiết bị
     * @param string $action Hành động (theo permissions)
     * @param array $params Tham số bổ sung
     * @return array Kết quả theo định dạng ['success' => bool, 'message' => string, 'data' => mixed]
     */
    public function handleAction($action, $params = []) {
        try {
            // Lấy vai trò người dùng
            $role = $this->getUserRole($this->userId);

            switch (strtolower($action)) {
                case 'add_maintenance':
                case 'request_maintenance':
                    // Tất cả vai trò (admin, technician, staff) đều có thể yêu cầu bảo trì
                    if (!$this->checkUserPermission('add_maintenance', $role) && 
                        !$this->checkUserPermission('request_maintenance', $role)) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: Bạn không có quyền yêu cầu bảo trì.",
                            'data' => null
                        ];
                    }
                    $data = $params['data'] ?? [];
                    if (empty($data['device_id']) || empty($data['description']) || empty($data['maintenance_date'])) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: Thiếu thông tin bắt buộc: device_id, description, maintenance_date.",
                            'data' => null
                        ];
                    }
                    $recordId = DeviceMaintenanceRecords::requestMaintenance(
                        $this->db,
                        $this->userId,
                        $data['device_id'],
                        $data['description'],
                        $data['maintenance_date']
                    );
                    return [
                        'success' => true,
                        'message' => "Thành công: Yêu cầu bảo trì đã được tạo với ID $recordId.",
                        'data' => ['record_id' => $recordId]
                    ];

                case 'update_maintenance':
                case 'update_status_maintenance':
                    $recordId = $params['record_id'] ?? null;
                    $data = $params['data'] ?? [];
                    if (!$recordId) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: ID bản ghi là bắt buộc.",
                            'data' => null
                        ];
                    }
                    $record = DeviceMaintenanceRecords::getMaintenanceById($this->db, $recordId);
                    if (!$record) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: Bản ghi với ID $recordId không tồn tại.",
                            'data' => null
                        ];
                    }
                    if ($role === 'technician') {
                        // Technician chỉ có thể cập nhật trạng thái, ngày hoàn thành và ghi chú
                        if (!$this->checkUserPermission('update_status_maintenance', $role)) {
                            return [
                                'success' => false,
                                'message' => "Thất bại: Bạn không có quyền cập nhật trạng thái bản ghi.",
                                'data' => null
                            ];
                        }
                        if (!isset($data['status'])) {
                            return [
                                'success' => false,
                                'message' => "Thất bại: Trạng thái là bắt buộc khi cập nhật bởi kỹ thuật viên.",
                                'data' => null
                            ];
                        }
                        // Kiểm tra xem record có thuộc về technician này không
                        if ($record->getPerformedByUserId() !== $this->userId) {
                            return [
                                'success' => false,
                                'message' => "Thất bại: Bạn không được phân công cho bản ghi này.",
                                'data' => null
                            ];
                        }
                        $success = $record->updateStatus(
                            $data['status'],
                            $data['completion_date'] ?? null,
                            $data['notes'] ?? null
                        );
                        if (!$success) {
                            return [
                                'success' => false,
                                'message' => "Thất bại: Không thể cập nhật trạng thái bản ghi.",
                                'data' => null
                            ];
                        }
                        return [
                            'success' => true,
                            'message' => "Thành công: Trạng thái bản ghi với ID $recordId đã được cập nhật.",
                            'data' => ['record_id' => $recordId]
                        ];
                    } elseif ($role === 'staff') {
                        // Staff không có quyền cập nhật
                        return [
                            'success' => false,
                            'message' => "Thất bại: Nhân viên không có quyền cập nhật bản ghi.",
                            'data' => null
                        ];
                    } else {
                        // Admin có thể cập nhật toàn bộ thông tin
                        if (!$this->checkUserPermission('update_maintenance', $role)) {
                            return [
                                'success' => false,
                                'message' => "Thất bại: Bạn không có quyền cập nhật bản ghi này.",
                                'data' => null
                            ];
                        }
                        foreach ($data as $key => $value) {
                            $setter = 'set' . str_replace('_', '', ucwords($key, '_'));
                            if (method_exists($record, $setter)) {
                                $record->$setter($value);
                            }
                        }
                        $success = $record->save();
                        if (!$success) {
                            return [
                                'success' => false,
                                'message' => "Thất bại: Không thể cập nhật bản ghi.",
                                'data' => null
                            ];
                        }
                        return [
                            'success' => true,
                            'message' => "Thành công: Bản ghi với ID $recordId đã được cập nhật.",
                            'data' => ['record_id' => $recordId]
                        ];
                    }

                case 'delete_maintenance':
                    $recordId = $params['record_id'] ?? null;
                    if (!$recordId) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: ID bản ghi là bắt buộc.",
                            'data' => null
                        ];
                    }
                    if (!$this->checkUserPermission('delete_maintenance', $role)) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: Bạn không có quyền xóa bản ghi này.",
                            'data' => null
                        ];
                    }
                    $record = DeviceMaintenanceRecords::getMaintenanceById($this->db, $recordId);
                    if (!$record) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: Bản ghi với ID $recordId không tồn tại.",
                            'data' => null
                        ];
                    }
                    if (!$record->delete()) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: Không thể xóa bản ghi.",
                            'data' => null
                        ];
                    }
                    return [
                        'success' => true,
                        'message' => "Thành công: Bản ghi với ID $recordId đã được xóa.",
                        'data' => null
                    ];

                case 'get_all_maintenance':
                case 'get_assigned_maintenance':
                    $searchParams = $params['search'] ?? [];
                    $limit = $params['limit'] ?? 10;
                    $offset = $params['offset'] ?? 0;
                    if ($role === 'technician') {
                        // Technician chỉ xem các bản ghi được phân công
                        if (!$this->checkUserPermission('get_assigned_maintenance', $role)) {
                            return [
                                'success' => false,
                                'message' => "Thất bại: Bạn không có quyền xem danh sách bản ghi được phân công.",
                                'data' => null
                            ];
                        }
                        $records = DeviceMaintenanceRecords::getAssignedRecords(
                            $this->db,
                            $this->userId,
                            $searchParams,
                            $limit,
                            $offset
                        );
                    } elseif ($role === 'staff') {
                        // Staff chỉ xem các bản ghi mà họ đã báo cáo
                        $records = DeviceMaintenanceRecords::getAllRecords(
                            $this->db,
                            array_merge($searchParams, ['reported_by_user_id' => $this->userId]),
                            $limit,
                            $offset
                        );
                    } else {
                        // Admin xem tất cả bản ghi
                        if (!$this->checkUserPermission('get_all_maintenance', $role)) {
                            return [
                                'success' => false,
                                'message' => "Thất bại: Bạn không có quyền xem danh sách bản ghi bảo trì.",
                                'data' => null
                            ];
                        }
                        $records = DeviceMaintenanceRecords::getAllRecords(
                            $this->db,
                            $searchParams,
                            $limit,
                            $offset
                        );
                    }
                    $recordData = array_map(function($record) {
                        return [
                            'record_id' => $record->getRecordId(),
                            'device_id' => $record->getDeviceId(),
                            'reported_by_user_id' => $record->getReportedByUserId(),
                            'performed_by_user_id' => $record->getPerformedByUserId(),
                            'maintenance_date' => $record->getMaintenanceDate(),
                            'completion_date' => $record->getCompletionDate(),
                            'description' => $record->getDescription(),
                            'notes' => $record->getNotes(),
                            'cost' => $record->getCost(),
                            'status' => $record->getStatus(),
                            'created_at' => $record->getCreatedAt(),
                            'updated_at' => $record->getUpdatedAt()
                        ];
                    }, $records);
                    return [
                        'success' => true,
                        'message' => "Thành công: Đã lấy được " . count($recordData) . " bản ghi bảo trì.",
                        'data' => array_values($recordData)
                    ];

                case 'schedule_maintenance':
                    // Chỉ admin được đặt lịch bảo trì
                    if (!$this->checkUserPermission('schedule_maintenance', $role)) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: Bạn không có quyền đặt lịch bảo trì.",
                            'data' => null
                        ];
                    }
                    $data = $params['data'] ?? [];
                    if (empty($data['device_id']) || empty($data['reported_by_user_id']) || 
                        empty($data['maintenance_date']) || empty($data['description'])) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: Thiếu thông tin bắt buộc: device_id, reported_by_user_id, maintenance_date, description.",
                            'data' => null
                        ];
                    }
                    // Kiểm tra reported_by_user_id
                    try {
                        $this->getUserRole($data['reported_by_user_id']);
                    } catch (Exception $e) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: Người dùng báo cáo với ID {$data['reported_by_user_id']} không tồn tại hoặc không hoạt động.",
                            'data' => null
                        ];
                    }
                    $record = new DeviceMaintenanceRecords($this->db);
                    $recordId = $record->scheduleMaintenance(
                        $this->userId,
                        $data['device_id'],
                        $data['reported_by_user_id'],
                        $data['maintenance_date'],
                        $data['description'],
                        $data['notes'] ?? null
                    );
                    return [
                        'success' => true,
                        'message' => "Thành công: Lịch bảo trì đã được tạo với ID $recordId.",
                        'data' => ['record_id' => $recordId]
                    ];

                case 'assign_technician_maintenance':
                    // Chỉ admin được phân công kỹ thuật viên
                    $recordId = $params['record_id'] ?? null;
                    $technicianId = $params['technician_id'] ?? null;
                    if (!$recordId || !$technicianId) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: record_id và technician_id là bắt buộc.",
                            'data' => null
                        ];
                    }
                    if (!$this->checkUserPermission('assign_technician_maintenance', $role)) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: Bạn không có quyền phân công kỹ thuật viên.",
                            'data' => null
                        ];
                    }
                    // Kiểm tra performed_by_user_id (technician_id)
                    try {
                        $technicianRole = $this->getUserRole($technicianId);
                        if ($technicianRole !== 'technician') {
                            return [
                                'success' => false,
                                'message' => "Thất bại: Người dùng với ID $technicianId không phải là kỹ thuật viên.",
                                'data' => null
                            ];
                        }
                    } catch (Exception $e) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: Kỹ thuật viên với ID $technicianId không tồn tại hoặc không hoạt động.",
                            'data' => null
                        ];
                    }
                    $record = DeviceMaintenanceRecords::getMaintenanceById($this->db, $recordId);
                    if (!$record) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: Bản ghi với ID $recordId không tồn tại.",
                            'data' => null
                        ];
                    }
                    if (!$record->assignTechnician($technicianId)) {
                        return [
                            'success' => false,
                            'message' => "Thất bại: Không thể phân công kỹ thuật viên.",
                            'data' => null
                        ];
                    }
                    return [
                        'success' => true,
                        'message' => "Thành công: Kỹ thuật viên đã được phân công cho bản ghi ID $recordId.",
                        'data' => ['record_id' => $recordId, 'technician_id' => $technicianId]
                    ];

                default:
                    return [
                        'success' => false,
                        'message' => "Thất bại: Hành động '$action' không hợp lệ.",
                        'data' => null
                    ];
            }
        } catch (Exception $e) {
            error_log("Lỗi trong handleAction ($action): " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Thất bại: " . $e->getMessage(),
                'data' => null
            ];
        }
    }
}