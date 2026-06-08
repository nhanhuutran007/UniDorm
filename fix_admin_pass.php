<?php
/**
 * Script tạm thời để fix mật khẩu admin
 * Xóa file này sau khi chạy xong!
 */
require_once __DIR__ . '/includes/db.php';

$newPassword = 'Admin@123';
$hash = password_hash($newPassword, PASSWORD_BCRYPT);

$stmt = $conn->prepare("UPDATE auth_accounts SET password = ?, is_active = 1 WHERE user_id = 1");
$stmt->bind_param('s', $hash);
if ($stmt->execute()) {
    echo "<h2 style='color:green'>✅ Cập nhật mật khẩu thành công!</h2>";
    echo "<p>Hash mới: <code>" . htmlspecialchars($hash) . "</code></p>";
    echo "<p>Độ dài: " . strlen($hash) . " ký tự (phải là 60)</p>";

    // Verify lại
    $check = $conn->query("SELECT password FROM auth_accounts WHERE user_id=1")->fetch_assoc();
    $ok = password_verify($newPassword, $check['password']);
    echo "<p>Verify lại: <strong style='color:" . ($ok ? 'green' : 'red') . "'>" . ($ok ? '✅ ĐÚNG' : '❌ SAI') . "</strong></p>";
    echo "<p><a href='views/auth/login.php'>→ Đến trang đăng nhập</a> (username: <b>admin</b>, password: <b>Admin@123</b>)</p>";
    echo "<p style='color:red'>⚠️ Xóa file <code>fix_admin_pass.php</code> sau khi xong!</p>";
} else {
    echo "<h2 style='color:red'>❌ Lỗi: " . htmlspecialchars($conn->error) . "</h2>";
}
?>
