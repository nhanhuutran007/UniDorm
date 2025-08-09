<?php
//AuthLoginController.php
// session_start();
ob_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/AuthLoginModel.php';

class AuthLoginController {
    private $authLoginModel;

    public function __construct($db) {
        $this->authLoginModel = new AuthLoginModel($db);
    }

    public function login() {
        $error = '';
        $success = '';

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (!empty($_POST['username']) && !empty($_POST['password'])) {
                $username = trim($_POST['username']);
                $password = trim($_POST['password']);

                $user = $this->authLoginModel->getUserByUsername($username);

                if ($user) {
                    if ($user['is_active'] != 1) { 
                        $error = "Tài khoản chưa được kích hoạt!";
                    } elseif ($user['status'] == 'inactive') {
                        $error = "Tài khoản của bạn đã bị tạm khóa!";
                    } elseif ($user['status'] == 'ban') {
                        $error = "Tài khoản không có quyền truy cập!";
                    } elseif (password_verify($password, $user['password'])) {
                        $this->authLoginModel->updateLastLogin($user['user_id']);
                        
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];

                        $redirectPages = [
                            'admin' => '/network-management/views/admin/dashboard.php',
                            'staff' => '/network-management/views/staff/staff.php',
                            'technician' => '/network-management/views/technician/technician.php'
                        ];

                        if (array_key_exists($user['role'], $redirectPages)) {
                            header("Location: " . $redirectPages[$user['role']]);
                            exit();
                        } else {
                            $error = "Tài khoản không có quyền truy cập!";
                        }
                    } else {
                        $this->authLoginModel->incrementFailedAttempts($user['user_id']);
                        $error = "Mật khẩu không chính xác!";
                    }
                } else {
                    $error = "Tên đăng nhập không tồn tại!";
                }
            } else {
                $error = "Vui lòng nhập đầy đủ thông tin!";
            }
        }

        if (isset($_GET['success']) && $_GET['success'] == "activated") {
            $success = "Tài khoản của bạn đã được kích hoạt. Vui lòng đăng nhập.";
        }
        if (isset($_GET['error'])) {
            $errorMessages = [
                "invalid_token" => "Token không hợp lệ hoặc đã hết hạn.",
                "no_token" => "Không tìm thấy token kích hoạt."
            ];
            $error = $errorMessages[$_GET['error']] ?? '';
        }

        require_once __DIR__ . '/../views/auth/login.php';
    }
}

// require_once __DIR__ . '/../includes/db.php';
// $controller = new AuthLoginController($conn);
// $controller->login();
?>