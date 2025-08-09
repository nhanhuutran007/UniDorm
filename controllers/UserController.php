<?php
// path: controllers/UserController.php
//Kiểm tra đã có phiên chưa, nếu chuaw thì bắt đầu phiên
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../models/UserModel.php';

class UserController {
    private $db;
    private $userModel;

    public function __construct() {
        $this->db = $this ->getDatabaseConnection();
        $this->userModel = new UserModel($this->db);
        
    }
    private function getDatabaseConnection() {
        // Dùng require để luôn thực thi db.php và trả về $conn
        return require __DIR__ . '/../includes/db.php';
    }
    // Xử lý khi người dùng submit form tạo mới người dùng
    public function handleFormSubmission() {
        $show_success_toast = false;
        $show_error_toast = false;
        $error_message = "";

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $data = [
                'username' => $_POST['username'],
                'password' => $_POST['password'] ?? '',
                'fullname' => $_POST['fullname'] ?: NULL,
                'email' => $_POST['email'] ?: NULL,
                'phone_number' => $_POST['phone_number'] ?: NULL,
                'role' => $_POST['role'],
                'birthday' => $_POST['birthday'] ?: NULL,
                'gender' => $_POST['gender'] ?: NULL,
                'status' => $_POST['status'],
                'profile_picture' => $this->handleProfilePictureUpload($_FILES['profile_picture'] ?? null, $_POST['username']) ?: 'images/default.jpg'
            ];

            if (empty($data['password'])) {
                $show_error_toast = true;
                $error_message = "Mật khẩu không được để trống.";
            } elseif (strlen($data['password']) < 6) {
                $show_error_toast = true;
                $error_message = "Mật khẩu phải có ít nhất 6 ký tự.";
            } elseif ($data['email'] && $this->userModel->checkEmailExists($data['email'])) {
                $show_error_toast = true;
                $error_message = "Email đã tồn tại! Vui lòng sử dụng email khác.";
            } else {
                $user_id = $this->userModel->createUser($data);

                $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
                $is_active = ($data['status'] === 'active') ? 1 : 0;

                if ($this->userModel->createAuthAccount($user_id, $hashed_password, $is_active)) {
                    $show_success_toast = true;
                } else {
                    $show_error_toast = true;
                    $error_message = "Lỗi khi tạo tài khoản xác thực.";
                }
            }
        }

        return [
            'show_success_toast' => $show_success_toast,
            'show_error_toast' => $show_error_toast,
            'error_message' => $error_message  
        ];
    }

    // Xử lý khi người dùng upload ảnh đại diện
    private function handleProfilePictureUpload($file, $username) {
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $file['tmp_name'];
            $fileName = $file['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = "profile_" . $username . "_" . time() . "." . $fileExtension;
                $uploadDir = __DIR__ . "/../assets/images/";
                $destPath = $uploadDir . $newFileName;

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    return "images/" . $newFileName;
                }
            }
        }
        return NULL;
    }

    // Lấy danh sách người dùng
    public function getUsers($filters = []) {
        return $this->userModel->getUsers($filters);
    }

    // Lấy thông tin người dùng theo username
    public function getUserByUsername($username) {
        return $this->userModel->getUserByUsername($username);
    }

    // Cập nhật thông tin người dùng
    public function editUser($username, $data) {
        return $this->userModel->updateUser($username, $data);
    }

    // Kích hoạt hoặc khóa tài khoản người dùng
    public function toggleStatus($username, $currentUser) {
        return $this->userModel->toggleUserStatus($username, $currentUser);
    }

    // Xóa người dùng
    public function deleteUser($username, $currentUser) {
        return $this->userModel->deleteUser($username, $currentUser);
    }

    // Đếm số lượng người dùng theo trạng thái
    public function countUsers($status = 'active') {
        try {
            return $this->userModel->countUsers($status);
        } catch (InvalidArgumentException $e) {
            error_log("Lỗi trạng thái: " . $e->getMessage());
            return ['error' => "Trạng thái không hợp lệ: " . $e->getMessage()];
        } catch (Exception $e) {
            error_log("Lỗi khi đếm người dùng: " . $e->getMessage());
            return ['error' => "Lỗi hệ thống khi đếm người dùng"];
        }
    }

}