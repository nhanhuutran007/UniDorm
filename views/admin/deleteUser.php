<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/UserController.php';
// Kiểm tra quyền truy cập
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "Bạn không có quyền truy cập!";
    header("Location: userlists.php");
    exit();
}

if (!isset($_GET['username']) || empty($_GET['username'])) {
    $_SESSION['error_message'] = "Không tìm thấy người dùng để xóa.";
    header("Location: userlists.php");
    exit();
}

$username = $_GET['username'];
$currentUser = $_SESSION['username'];
// Xóa người dùng
$controller = new UserController(); 
$result = $controller->deleteUser($username, $currentUser); // Gọi phương thức xóa người dùng

if (isset($result['success'])) {
    $_SESSION['success_message'] = $result['success'];
} else {
    $_SESSION['error_message'] = $result['error'];
}

header("Location: userlists.php");
exit();
?>