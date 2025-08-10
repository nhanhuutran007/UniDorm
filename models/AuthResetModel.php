<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthResetModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getUserByEmail($email) {
        $query = "SELECT user_id FROM users WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result->num_rows == 1 ? $result->fetch_assoc() : null;
    }

    public function saveResetToken($user_id, $email, $token) {
        $expiry_time = date('Y-m-d H:i:s', strtotime('+3 minutes'));
        $query = "INSERT INTO password_reset_tokens (user_id, email, token, expiry_time) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isss", $user_id, $email, $token, $expiry_time);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function sendResetEmail($email, $token) {
        // Xác định domain động
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $domain = $_SERVER['HTTP_HOST']; // Lấy domain hiện tại 
        $base_url = $protocol . $domain; 
        $reset_link = " $base_url/QuanLySV/index.php?route=reset&token=" . urlencode($token);
        
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
            $mail->Subject = '[JHTs - Hệ thống Mạng Doanh Nghiệp] Đặt lại mật khẩu của bạn';

            $sql = "SELECT fullname FROM users WHERE email = ?";
            $stmt= $this->conn->prepare($sql) ;
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result= $stmt->get_result();
            $fullname = $result->num_rows > 0 ? $result->fetch_assoc()['fullname'] : 'Người dùng';
            $stmt->close();
            $mail->Body = "
            <h1 style='color: #2c3e50; font-family: Arial, sans-serif;'>Đặt lại mật khẩu</h1>
            <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'>Hệ thống Quản lý Thiết bị và Người dùng trong Mạng Doanh Nghiệp JHTs xin thông báo:</p>
            <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'>Bạn vừa yêu cầu đặt lại mật khẩu cho tài khoản của mình.</p>
            
            <h3 style='color: #2980b9; font-family: Arial, sans-serif;'>Thông tin tài khoản:</h3>
            <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'><strong>Họ tên người dùng:</strong> $fullname</p>
            <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'><strong>Email đăng nhập:</strong> $email</p>
            
            <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'>Để đặt lại mật khẩu, vui lòng nhấn nút <strong>Đặt lại mật khẩu</strong> dưới đây:</p>
            
            <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'><br>
            <a href='$reset_link' style='color: #ffffff; background-color: #2980b9; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Đặt lại mật khẩu</a></p>
            
            <p style='font-family: Arial, sans-serif; font-size: 14px; color: #e74c3c;'><strong>Lưu ý:</strong></p>
            <ul style='font-family: Arial, sans-serif; font-size: 14px; color: #34495e;'>
                <li>Liên kết chỉ có hiệu lực trong vòng <strong>3 phút</strong> kể từ thời điểm gửi.</li>
                <li>Sau thời gian trên, bạn cần yêu cầu đặt lại mật khẩu mới nếu chưa thực hiện.</li>
            </ul>
            
            <p style='font-family: Arial, sans-serif; font-size: 16px; color: #34495e;'>Trân trọng,<br><strong>Nhóm phát triển JHTs<br>Hệ thống Quản lý Thiết bị và Người dùng trong Mạng Doanh Nghiệp</strong></p>
        ";

            return $mail->send();
        } catch (Exception $e) {
            return false;
        }
    }
    public function getResetToken($token) {
        $query = "SELECT user_id, email, expiry_time FROM password_reset_tokens WHERE token = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result->num_rows == 1 ? $result->fetch_assoc() : null;
    }

    public function updatePassword($user_id, $password) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "UPDATE auth_accounts SET password = ?, last_password_change = NOW() WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $hashed_password, $user_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function deleteResetToken($token) {
        $query = "DELETE FROM password_reset_tokens WHERE token = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $token);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    // Hàm cho reset_password.php sẽ được thêm sau
}
?>