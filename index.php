<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/controllers/AuthLoginController.php';
require_once __DIR__ . '/controllers/AuthRegisterController.php';
require_once __DIR__ . '/controllers/AuthActivateController.php';
require_once __DIR__ . '/controllers/AuthForgotController.php';
require_once __DIR__ . '/controllers/AuthResetController.php';
$route = $_GET['route'] ?? 'home';
switch ($route) {
    case 'home':
        header('Location: home.html');
        exit;
    case 'login':
        $controller = new AuthLoginController($conn);
        $controller->login();
        break;
    case 'register':
        $controller = new AuthRegisterController($conn);
        $controller->register();
        break;
    case 'activate':
        $controller = new AuthActivateController($conn);
        $controller->activate();
        break;
    case 'forgot':
        $controller = new AuthForgotController($conn);
        $controller->forgot();
        break;
    case 'reset':
        $controller = new AuthResetController($conn);
        $controller->reset();
        break;                    
    default:
        header("HTTP/1.0 404 Not Found");
        include __DIR__ . '/includes/404.php'; 
        exit;
}
?>