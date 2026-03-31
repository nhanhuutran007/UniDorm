<?php
// session_start();
ob_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/AuthRegisterModel.php';

class AuthRegisterController {
    private $authRegisterModel;

    public function __construct($db) {
        $this->authRegisterModel = new AuthRegisterModel($db);
    }

    public function register() {
        $error = '';
        $success = '';

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $email = trim($_POST['email'] ?? '');
            $fullname = trim($_POST['fullname'] ?? '');

            // Kiểm tra các trường bắt buộc
            if (empty($email)) {
                $error = "Vui lòng nhập email.";
            } elseif (empty($fullname)) {
                $error = "Vui lòng nhập họ và tên.";
            } elseif (!isset($_POST['role']) || empty($_POST['role'])) {
                $error = "Vui lòng chọn loại nhân viên.";
            } else {
                $role = $_POST['role'];

                // Kiểm tra định dạng email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Địa chỉ email không hợp lệ.";
                } elseif ($this->authRegisterModel->emailExistsInUsers($email)) {
                    $error = "Email này đã được đăng ký.";
                } elseif ($this->authRegisterModel->emailExistsInTokens($email)) {
                    $error = "Email này đã yêu cầu xác nhận, vui lòng kiểm tra hộp thư.";
                } else {
                    // Kiểm tra giá trị role hợp lệ
                    if (!in_array($role, ['staff', 'technician'])) {
                        $error = "Loại nhân viên không hợp lệ.";
                    } else {
                        $token = bin2hex(random_bytes(16));
                        if ($this->authRegisterModel->saveVerificationToken($email, $token, $fullname, $role)) {
                            if ($this->authRegisterModel->sendVerificationEmail($email, $token)) {
                                $success = "Vui lòng kiểm tra email để kích hoạt tài khoản.";
                            } else {
                                $error = "Không thể gửi email xác nhận. Vui lòng thử lại.";
                            }
                        } else {
                            $error = "Lỗi khi lưu token.";
                        }
                    }
                }
            }
        }

        require_once __DIR__ . '/../views/auth/register.php';
    }
}
?>