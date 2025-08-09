<?php
// Path: /network-management/functions/device_management/DeviceManager.php

require_once __DIR__ . '/DevicePermission.php';
require_once __DIR__ . '/../../models/DeviceModel.php';

class DeviceManager {
    private $db;
    private $userId;
    private $role;
    private $permission;

    public function __construct($db, $userId, $role) {
        $this->db = $db; // Nhận $db từ DeviceController
        $this->userId = $userId;
        $this->role = $role;
        $this->permission = new DevicePermission($userId, $role, $db);
    }

    // 🔹 Phương thức duy nhất để xử lý tất cả hành động
    public function handleAction($action, $params = []) {
        try {
            switch (strtolower($action)) {
                case 'add':
                    $deviceData = $params['data'] ?? [];
                    if (!$this->permission->executeAction('add', ['device_data' => $deviceData])) {
                        throw new Exception("Bạn không có quyền thêm thiết bị.");
                    }
                    $device = new DeviceModel($this->db, $deviceData);
                    $deviceId = $device->save();
                    if (!$deviceId) {
                        throw new Exception("Không thể thêm thiết bị. Vui lòng thử lại.");
                    }
                    return [
                        'success' => true,
                        'message' => "Thành công: Thiết bị '{$device->getDeviceName()}' đã được thêm với ID $deviceId.",
                        'data' => ['device_id' => $deviceId]
                    ];

                    case 'update':
                        $deviceId = $params['device_id'] ?? null;
                        $newData = $params['data'] ?? [];
                        if (!$deviceId) {
                            throw new Exception("ID thiết bị là bắt buộc. Vui lòng cung cấp ID.");
                        }
                        if (!$this->permission->executeAction('update', ['device_id' => $deviceId, 'data' => $newData])) {
                            throw new Exception("Bạn không có quyền cập nhật thiết bị này.");
                        }
                        $device = DeviceModel::findById($this->db, $deviceId);
                        if (!$device) {
                            throw new Exception("Thiết bị với ID $deviceId không tồn tại hoặc đã bị xóa.");
                        }
                        foreach ($newData as $key => $value) {
                            $setter = 'set' . str_replace('_', '', ucwords($key, '_'));
                            if (method_exists($device, $setter)) {
                                $device->$setter($value);
                            }
                        }
                        $updatedId = $device->save();
                        if ($updatedId === false || $updatedId === null) {
                            throw new Exception("Không thể cập nhật thiết bị. Vui lòng thử lại.");
                        }
                        return [
                            'success' => true,
                            'message' => "Thành công: Thiết bị với ID $deviceId đã được cập nhật.",
                            'data' => ['device_id' => $updatedId]
                        ];

                case 'delete':
                    $deviceId = $params['device_id'] ?? null;
                    if (!$deviceId) {
                        throw new Exception("ID thiết bị là bắt buộc. Vui lòng cung cấp ID.");
                    }
                    if (!$this->permission->executeAction('delete', ['device_id' => $deviceId])) {
                        throw new Exception("Bạn không có quyền xóa thiết bị này.");
                    }
                    $device = DeviceModel::findById($this->db, $deviceId);
                    if (!$device) {
                        throw new Exception("Thiết bị với ID $deviceId không tồn tại hoặc đã bị xóa.");
                    }
                    if (!$device->delete()) {
                        throw new Exception("Không thể đánh dấu thiết bị là đã xóa. Vui lòng thử lại.");
                    }
                    return [
                        'success' => true,
                        'message' => "Thành công: Thiết bị với ID $deviceId đã được đánh dấu là xóa.",
                        'data' => null
                    ];

                case 'get':
                    $searchParams = $params['search'] ?? [];-
                    $limit = $params['limit'] ?? 10;
                    $offset = $params['offset'] ?? 0;
                    // Lấy danh sách thiết bị thô từ DeviceModel (mảng các đối tượng)
                    $devices = DeviceModel::findAll($this->db, $searchParams, $limit, $offset);
                    // Truyền danh sách thiết bị vào DevicePermission để lọc
                    $filteredDevices = $this->permission->executeAction('get', [
                        'search' => $searchParams,
                        'limit' => $limit,
                        'offset' => $offset,
                        'devices' => $devices
                    ]);
                    if ($filteredDevices === false) {
                        throw new Exception("Bạn không có quyền xem danh sách thiết bị.");
                    }
                    return [
                        'success' => true,
                        'message' => "Thành công: Đã lấy danh sách thiết bị.",
                        'data' => array_values($filteredDevices)
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