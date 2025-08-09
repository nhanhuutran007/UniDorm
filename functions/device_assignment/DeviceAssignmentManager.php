<?php
// Path: /network-management/functions/device_assignment/DeviceAssignmentManager.php

require_once __DIR__ . '/../../repositories/DeviceAssignments.php';

class DeviceAssignmentManager {
    private $db;

    public function __construct($db) {
        $this->db = $db; // Nhận $db từ Controller
    }

    // Phương thức kiểm tra user_id có phải admin không
    private function checkAdmin($userId) {
        try {
            if (!is_int($userId) || $userId <= 0) {
                throw new Exception("User ID phải là số nguyên dương.");
            }

            $query = "SELECT role FROM users WHERE user_id = ? AND status != 'ban'";
            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                throw new Exception("Không thể chuẩn bị truy vấn kiểm tra admin: " . $this->db->error);
            }

            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if (!$row) {
                throw new Exception("Không tìm thấy user với ID $userId hoặc user đã bị xóa.");
            }

            if ($row['role'] !== 'admin') {
                throw new Exception("User với ID $userId không có quyền admin.");
            }

            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    // Phương thức duy nhất để xử lý tất cả hành động
    public function handleAction($action, $params = []) {
        try {
            // Kiểm tra user_id có phải admin không
            $userId = $params['user_id'] ?? null;
            if (!$userId) {
                throw new Exception("User ID là bắt buộc. Vui lòng cung cấp user_id.");
            }
            $this->checkAdmin($userId);

            // Xử lý hành động
            switch (strtolower($action)) {
                case 'insert_assignment':
                    $data = $params['data'] ?? [];
                    if (empty($data)) {
                        throw new Exception("Dữ liệu phân công là bắt buộc. Vui lòng cung cấp data.");
                    }
                    $assignment = new DeviceAssignments($this->db, $data);
                    $assignmentId = $assignment->insert();
                    return [
                        'success' => true,
                        'message' => "Thành công: Đã thêm phân công mới với ID $assignmentId.",
                        'data' => ['assignment_id' => $assignmentId]
                    ];

                case 'update_assignment':
                    $assignmentId = $params['assignment_id'] ?? null;
                    $data = $params['data'] ?? [];
                    if (!$assignmentId) {
                        throw new Exception("Assignment ID là bắt buộc. Vui lòng cung cấp assignment_id.");
                    }
                    if (empty($data)) {
                        throw new Exception("Dữ liệu cập nhật là bắt buộc. Vui lòng cung cấp data.");
                    }
                    $assignment = DeviceAssignments::getAssignmentById($this->db, $assignmentId);
                    if (!$assignment) {
                        throw new Exception("Không tìm thấy phân công với ID $assignmentId.");
                    }
                    // Cập nhật các trường
                    if (isset($data['expected_return_date'])) {
                        $assignment->setExpectedReturnDate($data['expected_return_date']);
                    }
                    if (isset($data['actual_return_date'])) {
                        $assignment->setActualReturnDate($data['actual_return_date']);
                    }
                    if (isset($data['status'])) {
                        $assignment->setStatus($data['status']);
                    }
                    if (isset($data['notes'])) {
                        $assignment->setNotes($data['notes']);
                    }
                    $updated = $assignment->update();
                    return [
                        'success' => true,
                        'message' => $updated ? "Thành công: Đã cập nhật phân công với ID $assignmentId." : "Không có thay đổi nào được thực hiện.",
                        'data' => ['assignment_id' => $assignmentId]
                    ];

                case 'delete_assignment':
                    $assignmentId = $params['assignment_id'] ?? null;
                    if (!$assignmentId) {
                        throw new Exception("Assignment ID là bắt buộc. Vui lòng cung cấp assignment_id.");
                    }
                    $assignment = DeviceAssignments::getAssignmentById($this->db, $assignmentId);
                    if (!$assignment) {
                        throw new Exception("Không tìm thấy phân công với ID $assignmentId.");
                    }
                    $deleted = $assignment->delete();
                    return [
                        'success' => true,
                        'message' => $deleted ? "Thành công: Đã xóa mềm phân công với ID $assignmentId." : "Không có thay đổi nào được thực hiện.",
                        'data' => ['assignment_id' => $assignmentId]
                    ];

                case 'get_assignment':
                    $assignmentId = $params['assignment_id'] ?? null;
                    if (!$assignmentId) {
                        throw new Exception("Assignment ID là bắt buộc. Vui lòng cung cấp assignment_id.");
                    }
                    $assignment = DeviceAssignments::getAssignmentById($this->db, $assignmentId);
                    if (!$assignment) {
                        throw new Exception("Không tìm thấy phân công với ID $assignmentId.");
                    }
                    return [
                        'success' => true,
                        'message' => "Thành công: Đã lấy thông tin phân công với ID $assignmentId.",
                        'data' => [
                            'assignment_id' => $assignment->getAssignmentId(),
                            'device_id' => $assignment->getDeviceId(),
                            'user_id' => $assignment->getUserId(),
                            'assigned_date' => $assignment->getAssignedDate(),
                            'expected_return_date' => $assignment->getExpectedReturnDate(),
                            'actual_return_date' => $assignment->getActualReturnDate(),
                            'assigned_by_user_id' => $assignment->getAssignedByUserId(),
                            'status' => $assignment->getStatus(),
                            'notes' => $assignment->getNotes(),
                            'created_at' => $assignment->getCreatedAt(),
                            'updated_at' => $assignment->getUpdatedAt()
                        ]
                    ];

                case 'get_all_assignment':
                    $searchParams = $params['search'] ?? [];
                    $limit = $params['limit'] ?? 10;
                    $offset = $params['offset'] ?? 0;
                    $assignments = DeviceAssignments::getAllAssignments($this->db, $searchParams, $limit, $offset);
                    return [
                        'success' => true,
                        'message' => "Thành công: Đã lấy danh sách phân công.",
                        'data' => array_map(function ($assignment) {
                            return [
                                'assignment_id' => $assignment->getAssignmentId(),
                                'device_id' => $assignment->getDeviceId(),
                                'user_id' => $assignment->getUserId(),
                                'assigned_date' => $assignment->getAssignedDate(),
                                'expected_return_date' => $assignment->getExpectedReturnDate(),
                                'actual_return_date' => $assignment->getActualReturnDate(),
                                'assigned_by_user_id' => $assignment->getAssignedByUserId(),
                                'status' => $assignment->getStatus(),
                                'notes' => $assignment->getNotes(),
                                'created_at' => $assignment->getCreatedAt(),
                                'updated_at' => $assignment->getUpdatedAt()
                            ];
                        }, $assignments)
                    ];

                default:
                    throw new Exception("Hành động '$action' không hợp lệ. Vui lòng kiểm tra lại.");
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [
                'success' => false,
                'message' => "Thất bại: " . $e->getMessage(),
                'data' => null
            ];
        }
    }
}