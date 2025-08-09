<?php
//AuthActivateController.php
ob_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/AuthRegisterModel.php';

class AuthActivateController {
    private $authRegisterModel;

    public function __construct($db) {
        $this->authRegisterModel = new AuthRegisterModel($db);
    }

    public function activate() {
        $message = '';

        if (!isset($_GET['token'])) {
            $message = "Không có token được cung cấp!";
        } else {
            $token = $_GET['token'];
            $result = $this->authRegisterModel->activateAccount($token);
            $message = $result['message'];
        }

        require_once __DIR__ . '/../views/auth/activate_email.php';
    }
}

// require_once __DIR__ . '/../includes/db.php';
// $controller = new AuthActivateController($conn);
// $controller->activate();
?>