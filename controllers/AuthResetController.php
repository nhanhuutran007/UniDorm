<?php
ob_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/AuthResetModel.php';

class AuthResetController {
    private $authResetModel;

    public function __construct($db) {
        $this->authResetModel = new AuthResetModel($db);
    }

    public function reset() {
        $error = '';
        $success = '';

        if (!isset($_GET['token'])) {
            header("Location: /network-management/index.php?route=login&error=no_token");
            exit();
        }

        $token = $_GET['token'];
        $tokenData = $this->authResetModel->getResetToken($token);
        if (!$tokenData) {
            header("Location: /network-management/index.php?route=login&error=invalid_token");
            exit();
        }

        $expiry_time = strtotime($tokenData['expiry_time']);
        $current_time = time();

        if ($expiry_time < $current_time) {
            $error = "Token đã hết hạn!";
            $this->authResetModel->deleteResetToken($token);
        } elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
            $password = trim($_POST['password']);
            $confirm_password = trim($_POST['confirm_password']);
            $user_id = $tokenData['user_id'];

            if ($password !== $confirm_password) {
                $error = "Mật khẩu xác nhận không khớp!";
            } else {
                if ($this->authResetModel->updatePassword($user_id, $password)) {
                    $this->authResetModel->deleteResetToken($token);
                    $success = "Mật khẩu đã được đặt lại thành công! <a href='/network-management/index.php?route=login'>Đăng nhập</a>";
                } else {
                    $error = "Lỗi khi cập nhật mật khẩu!";
                }
            }
        }

        require_once __DIR__ . '/../views/auth/reset_password.php';
    }
}
?>
