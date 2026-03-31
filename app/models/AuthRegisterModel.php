<?php
//AuthRegisterModel.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthRegisterModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function emailExistsInUsers($email) {
        $sql = "SELECT user_id FROM users WHERE email = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function emailExistsInTokens($email) {
        $sql = "SELECT id FROM email_verification_tokens WHERE email = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function saveVerificationToken($email, $token,  $fullname, $role) {
        $token_expiry = date('Y-m-d H:i:s', strtotime('+3 minutes'));
        $default_password = password_hash('52300232', PASSWORD_DEFAULT);
        $sql = "INSERT INTO email_verification_tokens (email, token, expiry_time, fullname, role,password) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssssss", $email, $token, $token_expiry,  $fullname, $role, $default_password);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function sendVerificationEmail($email, $token) {
        // Xác định domain động
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $domain = $_SERVER['HTTP_HOST']; // Lấy domain hiện tại 
        $base_url = $protocol . $domain; 
    
        // Tạo activation link với đường dẫn tuyệt đối
        $activation_link = "$base_url/network-management/index.php?route=activate&token=" . urlencode($token);
    
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
    
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'dhzhhxhddgh@gmail.com';
            $mail->Password = 'jxsurwnptulnqgrh';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
    
            $mail->setFrom('dhzhhxhddgh@gmail.com', 'Admin');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = '[JHTs - Hệ thống Mạng Doanh Nghiệp] Kích hoạt tài khoản của bạn';
    
            $sql = "SELECT fullname FROM email_verification_tokens WHERE email = ? AND token = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ss", $email, $token);
            $stmt->execute();
            $result = $stmt->get_result();
            $fullname = $result->num_rows > 0 ? $result->fetch_assoc()['fullname'] : 'Người dùng';
            $stmt->close();
    
            $mail->Body = "
                <h1 style='color: #2c3e50; font-family: Arial, sans-serif;'>Xác nhận đăng ký tài khoản</h1>
                <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'>Hệ thống Quản lý Thiết bị và Người dùng trong Mạng Doanh Nghiệp JHTs xin thông báo:</p>
                <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'>Bạn vừa thực hiện đăng ký tài khoản trên hệ thống quản lý mạng doanh nghiệp.</p>
                
                <h3 style='color: #2980b9; font-family: Arial, sans-serif;'>Thông tin tài khoản:</h3>
                <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'><strong>Họ tên người dùng:</strong> $fullname</p>
                <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'><strong>Email đăng nhập:</strong> $email</p>
                
                <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'>Tài khoản của bạn hiện đang chờ kích hoạt. Để hoàn tất quá trình đăng ký và bắt đầu sử dụng hệ thống, vui lòng nhấn nút <strong>Kích hoạt</strong> dưới đây :</p>
                
                <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'><br>
                <a href='$activation_link' style='color: #ffffff; background-color: #2980b9; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Kích hoạt</a></p>
                
                <p style='font-family: Arial, sans-serif; font-size: 14px; color: #e74c3c;'><strong>Lưu ý:</strong></p>
                <ul style='font-family: Arial, sans-serif; font-size: 14px; color: #34495e;'>
                    <li>Liên kết chỉ có hiệu lực trong vòng <strong>3 phút</strong> kể từ thời điểm gửi.</li>
                    <li>Sau thời gian trên, tài khoản sẽ bị hủy nếu chưa được kích hoạt.</li>
                </ul>
                
                <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'>Trân trọng,<br><strong>Nhóm phát triển JHTs<br> Hệ thống Quản lý Thiết bị và Người dùng trong Mạng Doanh Nghiệp</strong></p>
                ";
    
            return $mail->send();
        } catch (Exception $e) {
            return false;
        }
    }
    public function activateAccount($token) {
        $sql = "SELECT email, expiry_time, password, fullname, role FROM email_verification_tokens WHERE token = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    
        if ($result->num_rows !== 1) {
            return ['success' => false, 'message' => 'Token không hợp lệ hoặc đã hết hạn.'];
        }
    
        $row = $result->fetch_assoc();
        $expiry_time = strtotime($row['expiry_time']);
        $current_time = time();
    
        if ($expiry_time < $current_time) {
            $this->deleteToken($token);
            return ['success' => false, 'message' => 'Token đã hết hạn!'];
        }
    
        $email = $row['email'];
        $fullname = $row['fullname'];
        $username = explode("@", $email)[0];
        $password = $row['password'];
        $role = $row['role'];
    
        $check_sql = "SELECT user_id FROM users WHERE username = ?";
        $check_stmt = $this->conn->prepare($check_sql);
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_stmt->close();
    
        if ($check_result->num_rows > 0) {
            return ['success' => false, 'message' => "Username '$username' đã được sử dụng. Vui lòng liên hệ quản trị viên."];
        }
    
        $this->conn->begin_transaction();
        try {
            $insert_user_sql = "INSERT INTO users (fullname, username, email, role, status, created_at, profile_picture) VALUES (?, ?, ?, ?, 'active', NOW(), ?)";
            $stmt_insert_user = $this->conn->prepare($insert_user_sql);
            $defaultProfilePicture = 'images/default.jpg';
            $stmt_insert_user->bind_param("sssss", $fullname, $username, $email, $role, $defaultProfilePicture);
            $stmt_insert_user->execute();
            $user_id = $this->conn->insert_id;
    
            $insert_auth_sql = "INSERT INTO auth_accounts (user_id, password, is_active, last_password_change) VALUES (?, ?, 1, NOW())";
            $stmt_insert_auth = $this->conn->prepare($insert_auth_sql);
            $stmt_insert_auth->bind_param("is", $user_id, $password);
            $stmt_insert_auth->execute();
    
            $this->deleteToken($token);
            $this->conn->commit();
    
            $stmt_insert_user->close();
            $stmt_insert_auth->close();
            return ['success' => true, 'message' => 'Tài khoản đã được kích hoạt thành công!'];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Lỗi tạo tài khoản: ' . $e->getMessage()];
        }
    }
    
    private function deleteToken($token) {
        $sql = "DELETE FROM email_verification_tokens WHERE token = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
    }
}
?>