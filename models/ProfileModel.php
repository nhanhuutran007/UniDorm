<?php
require_once __DIR__ . '/../includes/db.php';

class ProfileModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getUserById($userId) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function updateUser($userId, $data) {
        $query = "UPDATE users SET fullname = ?, email = ?, phone_number = ?, birthday = ?, gender = ?"
                . (isset($data['profile_picture']) ? ", profile_picture = ?" : "")
                . " WHERE user_id = ?";
        
        $stmt = $this->conn->prepare($query);
        
        $params = [
            $data['fullname'],
            $data['email'],
            $data['phone_number'],
            $data['birthday'],
            $data['gender'],
        ];
        if (isset($data['profile_picture'])) {
            $params[] = $data['profile_picture'];
        }
        $params[] = $userId;

        $stmt->bind_param(str_repeat("s", count($params) - 1) . "i", ...$params);
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("Error updating user: " . $stmt->error);
        }
        return $result;
    }

    public function getPasswordByUserId($userId) {
        $stmt = $this->conn->prepare("SELECT password FROM auth_accounts WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['password'] : null;
    }

    public function updatePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("UPDATE auth_accounts SET password = ?, last_password_change = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);
        return $stmt->execute();
    }
}