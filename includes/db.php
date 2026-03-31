<?php
// UniDorm – Database connection
// Chỉnh theo cấu hình môi trường local của bạn
$host   = "localhost";
$user   = "root";
$pass   = "";          // XAMPP default: empty, WAMP/Laragon: tùy cấu hình
$dbname = "unidorm";  // Đảm bảo đã import schema.sql + seed.sql vào DB này

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    // Trong production nên log lỗi thay vì die
    error_log("DB Connection failed: " . $conn->connect_error);
    http_response_code(503);
    die(json_encode(['error' => 'Service Unavailable – DB connection failed']));
}

$conn->set_charset("utf8mb4");
$conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

return $conn;