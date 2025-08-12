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

        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $username = trim($_POST['username'] ?? '');
            $room = trim($_POST['room'] ?? '');
            $fullname = trim($_POST['fullname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone_number = trim($_POST['phone_number'] ?? '');
            $num_bed = intval($_POST['num_bed'] ?? 0);
            $hometown = trim($_POST['hometown'] ?? '');
            $created_by = $_SESSION['username'] ?? null;

            // Validate required fields
            if (empty($username)) {
            $show_error_toast = true;
            $error_message = "Tên đăng nhập không được để trống.";
            } else {
            $profile_picture = $this->handleProfilePictureUpload($_FILES['profile_picture'] ?? null, $username) ?: 'images/default.jpg';

            $data = [
                'username' => $username,
                'room' => $room ?: NULL,
                'fullname' => $fullname ?: NULL,
                'email' => $email ?: NULL,
                'phone_number' => $phone_number ?: NULL,
                'profile_picture' => $profile_picture,
                'num_bed' => $num_bed,
                'hometown' => $hometown ?: NULL,
                'created_by' => $created_by
            ];

            try {
                $user_id = $this->userModel->createUser($data);
                if ($user_id) {
                $show_success_toast = true;
                } else {
                $show_error_toast = true;
                $error_message = "Tạo người dùng thất bại.";
                }
            } catch (Exception $e) {
                $show_error_toast = true;
                $error_message = "Lỗi: " . $e->getMessage();
            }
            }
        }

        return [
            'show_success_toast' => $show_success_toast,
            'show_error_toast' => $show_error_toast,
            'error_message' => $error_message
        ];
    }
    // Xử lý khi người dùng upload file CSV
    public function handleCSVUpload() {
        $show_success_toast = false;
        $show_error_toast = false;
        $error_message = "";

        if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['csv_file'])) {
            $file = $_FILES['csv_file'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $file['tmp_name'];
                $fileName = $file['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileExtension === 'csv') {
                    try {
                        $this->userModel->importUsersFromCSV($fileTmpPath);
                        $show_success_toast = true;
                    } catch (Exception $e) {
                        $show_error_toast = true;
                        $error_message = "Lỗi khi import: " . $e->getMessage();
                    }
                } else {
                    $show_error_toast = true;
                    $error_message = "Vui lòng chọn file CSV.";
                }
            } else {
                $show_error_toast = true;
                $error_message = "Lỗi khi upload file: " . $file['error'];
            }
        }

        return [
            'show_success_toast' => $show_success_toast,
            'show_error_toast' => $show_error_toast,
            'error_message' => htmlspecialchars($error_message)
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