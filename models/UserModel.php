<?php
// path: models/UserModel.php

class UserModel {
    private $db;

    public function __construct($db) {
        // Kiểm tra $db có phải là đối tượng mysqli
        if (!($db instanceof mysqli)) {
            throw new InvalidArgumentException("Đối số db phải là một đối tượng mysqli hợp lệ.");
        }
        $this->db = $db;
    }

    public function checkEmailExists($email) {
        $sql = "SELECT email FROM users WHERE email = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result->num_rows > 0;
    }

    public function createUser($data) {
        $sql = "INSERT INTO users (username, room, fullname, email, phone_number, profile_picture, num_bed, hometown, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            "ssssssiss",
            $data['username'],
            $data['room'],
            $data['fullname'],
            $data['email'],
            $data['phone_number'],
            $data['profile_picture'],
            $data['num_bed'],
            $data['hometown'],
            $data['created_by']
        );
        $stmt->execute();
        $user_id = $this->db->insert_id;
        $stmt->close();
        return $user_id;
    }

    public function importUsersFromCSV($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        throw new Exception("Không thể đọc file CSV: " . $filePath);
    }

    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        throw new Exception("Không thể mở file CSV.");
    }

    // Bỏ qua header
    $header = fgetcsv($handle);

    $sql = "INSERT INTO users (room, username, fullname, email, phone_number, role, profile_picture, num_bed, hometown, status, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
        fclose($handle);
        throw new Exception("Lỗi prepare statement: " . $this->db->error);
    }

    while (($row = fgetcsv($handle)) !== false) {
        // Bỏ qua user_id => lấy từ cột thứ 2 trở đi
        $room           = $row[1];
        $username       = $row[2];
        $fullname       = $row[3];
        $email          = $row[4];
        $phone_number   = $row[5];
        $role           = $row[6];
        $profile_picture= $row[7];
        $num_bed        = (int)$row[8];
        $hometown       = $row[9];
        $status         = $row[10];
        $created_by     = (int)$row[11];

        $stmt->bind_param(
            "sssssssissi", // chú ý thứ tự kiểu dữ liệu
            $room,
            $username,
            $fullname,
            $email,
            $phone_number,
            $role,
            $profile_picture,
            $num_bed,
            $hometown,
            $status,
            $created_by
        );

        if (!$stmt->execute()) {
            error_log("Lỗi insert user: " . $stmt->error);
        }
    }

    $stmt->close();
    fclose($handle);
}



    public function createAuthAccount($user_id, $hashed_password, $is_active) {
        $sql = "INSERT INTO auth_accounts (user_id, password, is_active, last_password_change) 
                VALUES (?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("isi", $user_id, $hashed_password, $is_active);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function getUsers($searchParams = []) {
        try {
            $query = "SELECT user_id, room, num_bed, hometown, username, profile_picture, fullname, email, status, role 
                     FROM users WHERE username IS NOT NULL AND username != ''";
            $params = [];
            $types = '';

            if (!empty($searchParams['id'])) {
                $query .= " AND user_id = ?";
                $params[] = $searchParams['id'];
                $types .= 'i';
            }
            if (!empty($searchParams['room'])) {
                $query .= " AND room = ?";
                $params[] = $searchParams['room'];
                $types .= 's';
            }
            if (!empty($searchParams['num_bed'])) {
                $query .= " AND num_bed = ?";
                $params[] = $searchParams['num_bed'];
                $types .= 's';
            }
            if (!empty($searchParams['hometown'])) {
                $query .= " AND hometown LIKE ?";
                $params[] = '%' . $searchParams['hometown'] . '%';
                $types .= 's';
            }

            if (!empty($searchParams['username'])) {
                $query .= " AND username = ?";
                $params[] = $searchParams['username'];
                $types .= 's';
            }
          
            if (!empty($searchParams['fullname'])) {
                $query .= " AND fullname LIKE ?";
                $params[] = '%' . $searchParams['fullname'] . '%';
                $types .= 's';
            }
            if (!empty($searchParams['status'])) {
                $query .= " AND status = ?";
                $params[] = $searchParams['status'];
                $types .= 's';
            }
            if (!empty($searchParams['role'])) {
                $query .= " AND role = ?";
                $params[] = $searchParams['role'];
                $types .= 's';
            }

            $query .= " ORDER BY user_id ASC";

            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $this->db->error);
            }

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute(); 
            $result = $stmt->get_result();
            $users = [];
            
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            
            $stmt->close();
            return $users;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function updateUser($username, $data) {
        try {
            // Kiểm tra dữ liệu đầu vào
            $requiredFields = ['fullname', 'room', 'user_id', 'email', 'num_bed', 'hometown'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new InvalidArgumentException("Thiếu trường dữ liệu: $field");
                }
            }

            // Sắp xếp lại thứ tự truyền vào cho đúng với câu lệnh SQL
            $query = "UPDATE users SET fullname = ?, room = ?, email = ?, num_bed = ?, hometown = ? WHERE username = ?";
            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                throw new Exception("Lỗi prepare: " . $this->db->error);
            }

            // fullname (s), room (s), email (s), num_bed (s), hometown (s), username (s)
           $stmt->bind_param(
            "ssssss",
            $data['fullname'],
            $data['room'],
            $data['email'],
            $data['num_bed'],
            $data['hometown'],
            $username
        );

            $result = $stmt->execute();
            if (!$result) {
                throw new Exception("Lỗi execute: " . $stmt->error);
            }
            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Error updating user: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUserByUsername($username) {
        try {
            $query = "SELECT * FROM users WHERE username = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            return $user;
        } catch (Exception $e) {
            error_log("Error fetching user: " . $e->getMessage());
            return null;
        }
    }

    public function toggleUserStatus($username, $currentUser) {
        try {
            $query = "SELECT user_id, status, role FROM users WHERE username = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
    
            if ($result->num_rows === 0) {
                return ['error' => "Không tìm thấy người dùng!"];
            }
    
            $user = $result->fetch_assoc();
            $user_id = $user['user_id'];
            $current_status = $user['status'];
            $user_role = $user['role'];
    
            if ($username === $currentUser) {
                return ['error' => "Bạn không thể tự khóa tài khoản của mình!"];
            }
    
            if ($user_role === 'admin') {
                return ['error' => "Bạn không thể khóa tài khoản admin khác!"];
            }
    
            $new_status = ($current_status === 'active') ? 'inactive' : 'active';
    
            $update_query = "UPDATE users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bind_param("si", $new_status, $user_id);
    
            if ($update_stmt->execute()) {
                $update_stmt->close();
                return ['success' => "Đã " . ($new_status === 'active' ? 'kích hoạt' : 'khóa') . " tài khoản thành công!"];
            } else {
                $update_stmt->close();
                return ['error' => "Không thể cập nhật trạng thái tài khoản: " . $this->db->error];
            }
        } catch (Exception $e) {
            return ['error' => "Lỗi: " . $e->getMessage()];
        }
    }

    public function deleteUser($username, $currentUser) {
        try {
            $check_query = "SELECT user_id, role FROM users WHERE username = ?";
            $stmt = $this->db->prepare($check_query);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
    
            if (!$user) {
                return ['error' => "Người dùng không tồn tại."];
            }
    
            if ($user['role'] === 'admin') {
                return ['error' => "Không thể xóa tài khoản có vai trò admin!"];
            }
    
            if ($username === $currentUser) {
                return ['error' => "Bạn không thể xóa tài khoản của chính mình!"];
            }
    
            $user_id = $user['user_id'];
    
            $delete_query = "UPDATE users SET username = NULL, email = NULL, phone_number = NULL, profile_picture = NULL, num_bed = NULL, hometown = NULL, status = 'ban' WHERE username = ?";
            $stmt = $this->db->prepare($delete_query);
            $stmt->bind_param("s", $username);
            $delete_success = $stmt->execute();
            $stmt->close();
    
            if ($delete_success) {
                $auth_query = "UPDATE auth_accounts SET password = NULL WHERE user_id = ?";
                $stmt = $this->db->prepare($auth_query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
    
                $update_query = "UPDATE users SET status = 'ban' WHERE username IS NULL";
                $this->db->query($update_query);
    
                return ['success' => "Xóa người dùng thành công."];
            } else {
                return ['error' => "Lỗi khi xóa người dùng."];
            }
        } catch (Exception $e) {
            return ['error' => "Lỗi: " . $e->getMessage()];
        }
    }

    public function countUsers($status) {
        try {
            // Kiểm tra $this->db có phải là đối tượng mysqli
            if (!($this->db instanceof mysqli)) {
            throw new Exception("Đối tượng cơ sở dữ liệu không hợp lệ. Kiểm tra kết nối DB.");
            }

            // Kiểm tra trạng thái hợp lệ
            $validStatuses = ['active', 'inactive', 'all'];
            if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException("Trạng thái không hợp lệ: $status. Phải là một trong: " . implode(', ', $validStatuses));
            }

            if ($status === 'all') {
            $query = "SELECT COUNT(*) as total FROM users WHERE status IN ('active', 'inactive')";
            $stmt = $this->db->prepare($query);
            } else {
            $query = "SELECT COUNT(*) as total FROM users WHERE status = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("s", $status);
            }

            if (!$stmt) {
            throw new Exception("Không thể chuẩn bị câu lệnh: " . $this->db->error);
            }

            if (!$stmt->execute()) {
            throw new Exception("Không thể thực thi câu lệnh: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            return (int) $row['total'];
        } catch (Exception $e) {
            error_log("Lỗi khi đếm người dùng: " . $e->getMessage());
            throw $e;
        }
    }
    ///** Lấy danh sách người dùng theo trạng thái **//
   public function getLastMessage($userId, $recipientId)
    {
        try {
            $query = "
                SELECT content, created_at
                FROM messages
                WHERE (sender_id = ? AND recipient_id = ?)
                   OR (sender_id = ? AND recipient_id = ?)
                ORDER BY created_at DESC
                LIMIT 1";
            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                error_log("Lỗi prepare getLastMessage: " . $this->db->error);
                return null;
            }
            $stmt->bind_param("iiii", $userId, $recipientId, $recipientId, $userId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Error fetching last message: " . $e->getMessage());
            return null;
        }
    }
}