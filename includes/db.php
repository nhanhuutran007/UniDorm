<?php
$host = "localhost"; // MySQL Server
$user = "root";      // Default MySQL username (XAMPP)
$pass = "";          // Password (empty if using XAMPP)
$dbname = "network_management"; // Database name

$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Thêm dòng này để set charset UTF-8
$conn->set_charset("utf8mb4");

return $conn; // Trả về kết nối
?>