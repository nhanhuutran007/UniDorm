<?php
//path: views/admin/updateuser.php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/UserController.php';

// Kiểm tra quyền truy cập (chỉ admin mới có quyền)
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: /network-management/auth/login.php"); 
    exit();
}

// Khởi tạo UserController
$userController = new UserController();

// Lấy username từ URL (GET)
$usernameToEdit = $_GET['username'] ?? null;

// Lấy thông tin người dùng đang đăng nhập
$loggedInUsername = $_SESSION['username'] ?? null;
$loggedInRole = $_SESSION['role'] ?? null;

// Xử lý yêu cầu cập nhật thông tin người dùng qua POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header('Content-Type: application/json');

    try {
        $usernameToUpdate = $_POST['username'] ?? null;
        $student_id = $_POST['student_id'] ?? null; // Mã sinh viên
        $fullname = $_POST['fullname'] ?? null;
        $email = $_POST['email'] ?? null;
        $user_id = $_POST['user_id'] ?? null;
        $room = $_POST['room'] ?? null; 

        // $newRole = $_POST['role'] ?? 'staff'; // Mặc định là nhân viên

        if (!$usernameToUpdate) {
            throw new Exception("Không tìm thấy username người dùng để cập nhật.");
        }

        // Lấy thông tin người dùng đang được chỉnh sửa
        $userToEdit = $userController->getUserByUsername($usernameToUpdate);
        if (!$userToEdit) {
            throw new Exception("Không tìm thấy thông tin người dùng với username: " . htmlspecialchars($usernameToUpdate));
        }

        $currentRoleOfUserToEdit = $userToEdit['role'] ?? 'staff';

        // Kiểm tra logic: Admin không được phép sửa role của Admin khác
        if ($loggedInRole === 'admin' && $currentRoleOfUserToEdit === 'admin' && $usernameToUpdate !== $loggedInUsername) {
            throw new Exception("Quản trị viên không được phép thay đổi vai trò của quản trị viên khác.");
        }

        $data = [];
        if ($fullname !== null && $fullname !== '') {
            $data['fullname'] = $fullname;
        }
        if ($student_id = $_POST['student_id'] ?? null) {
            $data['student_id'] = (int)$student_id; 
        }
        if ($email !== null && $email !== '') {
            $data['email'] = $email;
        }
        if ($room = $_POST['room'] ?? null) {
            $data['room'] = $room; 
        }
        if ($num_bed = $_POST['num_bed'] ?? null) {
            $data['num_bed'] = (int)$num_bed; // Chuyển đổi sang số nguyên
        }
        if ($hometown = $_POST['hometown'] ?? null) {
            $data['hometown'] = $hometown;
        }

        if (empty($data)) {
            throw new Exception("Không có thông tin nào được gửi để cập nhật.");
        }

        // Gọi trực tiếp phương thức editUser
        if ($userController->editUser($usernameToUpdate, $data)) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Cập nhật thông tin thành công!'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Lỗi khi cập nhật thông tin!'
            ]);
        }

    } catch (Exception $e) {
        error_log("Lỗi trong updateUser.php: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Lỗi hệ thống: ' . $e->getMessage()
        ]);
    }

    ob_end_flush();
    exit();
}

// Lấy thông tin người dùng để hiển thị trong form
if (!$usernameToEdit) {
    die("<div class='alert alert-danger'>Không tìm thấy username người dùng!</div>");
}

$user = $userController->getUserByUsername($usernameToEdit);

if (!$user) {
    die("<div class='alert alert-danger'>Không tìm thấy thông tin người dùng với username: " . htmlspecialchars($usernameToEdit) . "</div>");
}

$user_id = htmlspecialchars($user['user_id'] ?? '');
$student_id = htmlspecialchars($user['student_id'] ?? ''); // Mã sinh viên
$fullname = htmlspecialchars($user['fullname'] ?? '');
$email = htmlspecialchars($user['email'] ?? '');
$num_bed = (int)($user['num_bed'] ?? 1); 
$hometown = htmlspecialchars($user['hometown'] ?? '');
$room = htmlspecialchars($user['room'] ?? '');

// Xử lý thông báo toast 
$show_success_toast = isset($_SESSION['success_message']);
$show_error_toast = isset($_SESSION['error_message']);
$success_message = $show_success_toast ? $_SESSION['success_message'] : '';
$error_message = $show_error_toast ? $_SESSION['error_message'] : '';

if ($show_success_toast) unset($_SESSION['success_message']);
if ($show_error_toast) unset($_SESSION['error_message']);

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật thông tin người dùng</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../assets/img/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="/network-management/assets/css/style.css">
    <style>
    </style>
</head>

<body>
    <div id="global-loader">
        <div class="whirly-loader"></div>
    </div>

    <div class="main-wrapper">
        <?php include(__DIR__ . '/../../includes/header.php'); ?>
        <?php include(__DIR__ . '/../../includes/sidebarAll.php'); ?>

        <div class="page-wrapper">
            <div class="content">
                <div class="page-header">
                    <div class="page-title">
                        <h4>Cập nhật thông tin sinh viên</h4>
                        <h6>Trang cập nhật thông tin sinh viên cho Admin</h6>
                    </div>
                </div>

                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body"></div> <button type="button"
                                class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                    <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body"></div> <button type="button"
                                class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form id="updateUserForm" method="POST">
                            <div class="row">
                                <div class="col-lg-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label>Username <span class="text-danger">*</span></label>
                                        <input type="text" name="username" class="form-control" readonly
                                            value="<?php echo htmlspecialchars($usernameToEdit ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label>Mã HV</label>
                                        <input type="text" name="student_id" class="form-control"
                                            value="<?php echo $student_id; ?>">
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label>Họ và tên</label>
                                        <input type="text" name="fullname" class="form-control"
                                            value="<?php echo $fullname; ?>">
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="text" name="email" class="form-control"
                                            value="<?php echo $email; ?>">
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label>Số phòng</label>
                                        <input type="text" name="room" class="form-control"
                                            value="<?php echo $room; ?>">

                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label>Số giường</label>
                                        <select class="form-select" name="num_bed">
                                            <?php
                                            for ($i = 1; $i <= 6; $i++) {
                                                echo '<option value="' . $i . '" ' . ($num_bed === $i ? 'selected' : '') . '>' . $i . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12 col-12">
                                    <div class="form-group">
                                        <label>Quê quán</label>
                                        <input type="text" name="hometown" class="form-control"
                                            value="<?php echo $hometown; ?>">
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <button type="submit" class="btn btn-primary me-2">Cập nhật</button>
                                    <a href="userlists.php" class="btn btn-secondary">Hủy</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.1/feather.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
    <script src="../../assets/js/script.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loader = document.getElementById('global-loader');
        setTimeout(() => {
            loader.classList.add('hidden');
        }, 500);

        const successToast = new bootstrap.Toast(document.getElementById('successToast'), {
            delay: 4000
        });
        const errorToast = new bootstrap.Toast(document.getElementById('errorToast'), {
            delay: 4000
        });

        $("#updateUserForm").on('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            $.ajax({
                url: window.location.pathname +
                    '?username=<?php echo urlencode($usernameToEdit ?? ''); ?>',
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                dataType: "json",
                success: function(response) {
                    submitButton.disabled = false;
                    if (response.status === 'success') {
                        successToast._element.querySelector('.toast-body').textContent =
                            response.message;
                        successToast.show();
                        // Thêm logic chuyển hướng sau khi hiển thị toast
                        setTimeout(() => {
                            window.location.href = 'userlists.php';
                        }, 500); // Chuyển hướng sau 2 giây để người dùng thấy thông báo
                    } else {
                        errorToast._element.querySelector('.toast-body').textContent =
                            response.message;
                        errorToast.show();
                    }
                },
                error: function(xhr, status, error) {
                    submitButton.disabled = false;
                    console.error('AJAX Error:', xhr.responseText);
                    errorToast._element.querySelector('.toast-body').textContent =
                        "Lỗi hệ thống: " + (xhr.responseJSON?.message ||
                            'Không thể xử lý yêu cầu');
                    errorToast.show();
                }
            });
        });
    });
    </script>
</body>

</html>