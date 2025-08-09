<?php
ob_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/AuthResetModel.php';

class AuthForgotController {
    private $authResetModel;

    public function __construct($db) {
        $this->authResetModel = new AuthResetModel($db);
    }

    public function forgot() {
        $error = '';
        $success = '';

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $email = trim($_POST['email']);
            $user = $this->authResetModel->getUserByEmail($email);

            if ($user) {
                $user_id = $user['user_id'];
                $token = bin2hex(random_bytes(16));

                if ($this->authResetModel->saveResetToken($user_id, $email, $token)) {
                    if ($this->authResetModel->sendResetEmail($email, $token)) {
                        $success = "Vui lòng kiểm tra email để đặt lại mật khẩu.";
                    } else {
                        $error = "Không thể gửi email. Vui lòng thử lại.";
                    }
                } else {
                    $error = "Lỗi khi lưu token.";
                }
            } else {
                $error = "Email không tồn tại trong hệ thống!";
            }
        }

        require_once __DIR__ . '/../views/auth/forgot_password.php';
    }
}
?>
