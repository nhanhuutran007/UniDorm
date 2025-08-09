<?php
// Path: /network-management/functions/device_management/DevicePermission.php

class DevicePermission {
    private $userRole;
    private $userId;
    private $db;

    public function __construct($userId, $userRole, $db) {
        if (!$db instanceof mysqli) {
            throw new Exception("Invalid database connection provided.");
        }
        $this->userId = $userId;
        $this->userRole = $userRole;
        $this->db = $db;
    }

    private function hasPermission($allowedRoles) {
        if (!in_array($this->userRole, $allowedRoles)) {
            $_SESSION['error_message'] = "Access Denied: Insufficient permissions for this action.";
            return false;
        }
        return true;
    }

    private function canAccessDevice($deviceId) {
        if ($this->userRole === 'admin') {
            return true;
        }

        $query = "SELECT COUNT(*) FROM device_assignments WHERE user_id = ? AND device_id = ? AND status IN ('active', 'maintenance') AND deleted = 0";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $this->userId, $deviceId);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_row()[0];

        if ($count == 0) {
            $_SESSION['error_message'] = "Access Denied: You are not assigned to device ID $deviceId.";
            return false;
        }
        return true;
    }

    public function filterDevices($devices) {
        if ($this->userRole === 'admin' || $this->userRole === 'technician') {
            return $devices;
        }
    
        $query = "SELECT device_id FROM device_assignments WHERE user_id = ? AND status IN ('active', 'maintenance') AND deleted = 0";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $assignedIds = [];
        while ($row = $result->fetch_assoc()) {
            $assignedIds[] = $row['device_id'];
        }
    
        return array_filter($devices, function ($device) use ($assignedIds) {
            if (!is_array($device) || !isset($device['device_id'])) {
                return false;
            }
            return in_array($device['device_id'], $assignedIds);
        });
    }

    public function executeAction($action, $params = []) {
        $allowedActions = [
            'add' => ['admin'],
            'update' => ['admin', 'technician'],
            'delete' => ['admin'],
            'get' => ['admin', 'technician', 'staff']
        ];

        if (!array_key_exists($action, $allowedActions)) {
            $_SESSION['error_message'] = "Invalid action: $action";
            return false;
        }

        if (!$this->hasPermission($allowedActions[$action])) {
            return false;
        }

        switch ($action) {
            case 'add':
                return true; // Xử lý ở DeviceManager
            case 'update':
                return ($this->userRole === 'admin' || $this->canAccessDevice($params['device_id']));
            case 'delete':
                return true; // Xử lý ở DeviceManager
            case 'get':
                $devices = $params['devices'] ?? [];
                return $this->filterDevices($devices);
            // Các case khác (add, update, delete) giữ nguyên
            default:
                $_SESSION['error_message'] = "Action not implemented: $action";
                return false;
        }
    }
}