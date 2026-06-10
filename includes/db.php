<?php
/**
 * UniDorm – Database connection
 * path: includes/db.php
 * Chỉnh theo cấu hình môi trường local của bạn
 */
require_once __DIR__ . '/config.php';

$host = "localhost";
$user = "githubio5524_githubio";
$pass = "nhanhuutran007@";
$dbname = "githubio5524_UniDorm";

try {
    $conn = new mysqli($host, $user, $pass, $dbname);

    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }
} catch (Exception $e) {
    // Trong production nên log lỗi thay vì die
    error_log("DB Connection failed: " . $e->getMessage());
    http_response_code(503);
    die('
        <div style="font-family: sans-serif; padding: 2rem; text-align: center;">
            <h2>Bảo trì hệ thống / Lỗi kết nối</h2>
            <p>Hệ thống không thể kết nối đến Cơ sở dữ liệu. Vui lòng kiểm tra lại thông tin cấu hình trong <code>includes/db.php</code>.</p>
        </div>
    ');
}

$conn->set_charset("utf8mb4");
$conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

return $conn;
