<?php
//AuthLoginModel.php
require_once __DIR__ . '/../includes/db.php'; 

class AuthLoginModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getUserByUsername($username) {
        $query = "SELECT u.user_id, u.username, u.role, u.status, a.password, a.is_active 
                  FROM users u 
                  JOIN auth_accounts a ON u.user_id = a.user_id 
                  WHERE u.username = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $username);
        
        if (!$stmt->execute()) {
            die("Lỗi truy vấn SQL: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        return $result->num_rows == 1 ? $result->fetch_assoc() : null;
    }

    public function updateLastLogin($userId) {
        $query = "UPDATE auth_accounts SET last_login = NOW(), failed_login_attempts = 0 WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $userId);
        return $stmt->execute();
    }

    public function incrementFailedAttempts($userId) {
        $query = "UPDATE auth_accounts SET failed_login_attempts = failed_login_attempts + 1 WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $userId);
        return $stmt->execute();
    }
}
?>