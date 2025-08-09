<?php
// Path: /network-management/functions/device_log/DeviceLogManager.php

require_once __DIR__ . '/../../repositories/DeviceLogs.php';

class DeviceLogManager {
    private $db;

    public function __construct($db) {
        $this->db = $db; // Nhận $db từ Controller
    }

    // Phương thức duy nhất để xử lý tất cả hành động
    public function handleAction($action, $params = []) {
        try {
            switch (strtolower($action)) {
                case 'get_latest_log':
                    $deviceId = $params['device_id'] ?? null;
                    if (!$deviceId) {
                        throw new Exception("ID thiết bị là bắt buộc. Vui lòng cung cấp device_id.");
                    }
                    $latestLog = $this->getLatestLog($deviceId);
                    if (!$latestLog) {
                        throw new Exception("Không tìm thấy log nào cho thiết bị với ID $deviceId.");
                    }
                    return [
                        'success' => true,
                        'message' => "Thành công: Đã lấy log gần nhất của thiết bị với ID $deviceId.",
                        'data' => $latestLog
                    ];

                case 'get_log':
                    $deviceId = $params['device_id'] ?? null;
                    $searchParams = $params['search'] ?? [];
                    $limit = $params['limit'] ?? 10;
                    $offset = $params['offset'] ?? 0;
                    if (!$deviceId) {
                        throw new Exception("ID thiết bị là bắt buộc. Vui lòng cung cấp device_id.");
                    }
                    $logs = $this->getAllLogs($deviceId, $searchParams, $limit, $offset);
                    return [
                        'success' => true,
                        'message' => "Thành công: Đã lấy danh sách log của thiết bị với ID $deviceId.",
                        'data' => $logs 
                    ];
                    case 'add_log':
                        $deviceId = $params['device_id'] ?? null;
                        $userId = $params['user_id'] ?? null;
                        $eventType = $params['event_type'] ?? null;
                        $previousStatus = $params['previous_status'] ?? null;
                        $newStatus = $params['new_status'] ?? null;
                        $details = $params['details'] ?? null;
    
                        // Kiểm tra các tham số bắt buộc
                        if (!$deviceId) {
                            throw new Exception("ID thiết bị là bắt buộc. Vui lòng cung cấp device_id.");
                        }
                        if (!$userId) {
                            throw new Exception("ID người dùng là bắt forced. Vui lòng cung cấp user_id.");
                        }
                        if (!$eventType) {
                            throw new Exception("Loại sự kiện là bắt buộc. Vui lòng cung cấp event_type.");
                        }
    
                        // Tạo dữ liệu cho DeviceLogs
                        $logData = [
                            'device_id' => $deviceId,
                            'user_id' => $userId,
                            'event_type' => $eventType,
                            'previous_status' => $previousStatus,
                            'new_status' => $newStatus,
                            'details' => $details
                        ];
    
                        // Tạo instance DeviceLogs và lưu
                        $deviceLog = new DeviceLogs($this->db, $logData);
                        $logId = $deviceLog->save();
    
                        return [
                            'success' => true,
                            'message' => "Thành công: Đã thêm log mới với ID $logId cho thiết bị với ID $deviceId.",
                            'data' => ['log_id' => $logId]
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

    // Lấy log gần nhất của thiết bị (sử dụng getAllLogs với limit = 1)
    private function getLatestLog($deviceId) {
        $logs = DeviceLogs::getAllLogs($this->db, $deviceId, [], 1, 0);
        return !empty($logs) ? $logs[0] : null;
    }

    // Lấy tất cả log của thiết bị, sắp xếp theo ngày giảm dần
    private function getAllLogs($deviceId, $searchParams, $limit, $offset) {
        return DeviceLogs::getAllLogs($this->db, $deviceId, $searchParams, $limit, $offset);
    }
}