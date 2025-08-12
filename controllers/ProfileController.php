<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/ProfileModel.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /QuanLySV/auth/login.php");
    exit();
}

$profileModel = new ProfileModel($conn);
$userId = $_SESSION['user_id'];

// Xử lý khi form được submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Lấy dữ liệu từ form
    $fullname = filter_input(INPUT_POST, 'fullname', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING) ?: null;
    $birthday = filter_input(INPUT_POST, 'birthday', FILTER_SANITIZE_STRING) ?: null;
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Xử lý upload ảnh hồ sơ
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
        $fileName = $_FILES['profile_picture']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = "profile_" . $userId . "_" . time() . "." . $fileExtension;
            $uploadDir = __DIR__ . '/../assets/images/';
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $profile_picture = "images/" . $newFileName;
            } else {
                $_SESSION['error'] = "Lỗi khi tải ảnh hồ sơ lên.";
                header("Location: /QuanLySV/views/profile.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Định dạng ảnh không được hỗ trợ.";
            header("Location: /QuanLySV/views/profile.php");
            exit();
        }
    }

    // Chuẩn bị dữ liệu để cập nhật thông tin cá nhân
    $updateData = [
        'fullname' => $fullname,
        'email' => $email,
        'phone_number' => $phone_number,
        'birthday' => $birthday,
        'gender' => $gender,
    ];
    if ($profile_picture) {
        $updateData['profile_picture'] = $profile_picture;
    }

    // Cập nhật thông tin cá nhân
    $profileUpdated = $profileModel->updateUser($userId, $updateData);
    $passwordUpdated = false;

    // Xử lý cập nhật mật khẩu (nếu có dữ liệu)
    if (!empty($old_password) && !empty($new_password) && !empty($confirm_password)) {
        if ($new_password === $confirm_password) {
            $currentPasswordHash = $profileModel->getPasswordByUserId($userId);
            if ($currentPasswordHash && password_verify($old_password, $currentPasswordHash)) {
                $passwordUpdated = $profileModel->updatePassword($userId, $new_password);
                if (!$passwordUpdated) {
                    $_SESSION['error'] = "Lỗi khi cập nhật mật khẩu.";
                    header("Location: /QuanLySV/views/profile.php");
                    exit();
                }
            } else {
                $_SESSION['error'] = "Mật khẩu cũ không đúng.";
                header("Location: /QuanLySV/views/profile.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Mật khẩu mới và xác nhận không khớp.";
            header("Location: /QuanLySV/views/profile.php");
            exit();
        }
    } elseif (!empty($old_password) || !empty($new_password) || !empty($confirm_password)) {
        // Nếu chỉ nhập một phần của mật khẩu thì báo lỗi
        $_SESSION['error'] = "Vui lòng điền đầy đủ thông tin mật khẩu để thay đổi.";
        header("Location: /QuanLySV/views/profile.php");
        exit();
    }

    // Thông báo kết quả
    if ($profileUpdated || $passwordUpdated) {
        $_SESSION['success'] = "Cập nhật hồ sơ thành công" . ($passwordUpdated ? " (bao gồm mật khẩu)." : ".");
    } else {
        $_SESSION['error'] = "Không có thay đổi nào được thực hiện.";
    }
    header("Location: /QuanLySV/views/profile.php");
    exit();
} 
// else {
//     // Nếu không phải POST request hoặc không có update_profile
//     header("Location: /QuanLySV/views/profile.php");
//     exit();
// }
?>