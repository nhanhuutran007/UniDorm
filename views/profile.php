<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/ProfileModel.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /network-management/auth/login.php");
    exit();
}

$profileModel = new ProfileModel($conn);
$userId = $_SESSION['user_id'];
$userData = $profileModel->getUserById($userId);

if (!$userData) {
    session_destroy();
    header("Location: /network-management/auth/login.php?error=user_not_found");
    exit();
}

// Lấy thông báo từ session nếu có
$success = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;

// Xóa thông báo sau khi lấy
unset($_SESSION['success']);
unset($_SESSION['error']);

// Xác định trang dashboard dựa trên vai trò
$dashboardUrl = '';
switch (strtolower($userData['role'])) {
    case 'admin':
        $dashboardUrl = '/network-management/views/admin/dashboard.php';
        break;
    case 'staff':
        $dashboardUrl = '/network-management/views/staff/staff.php';
        break;
    case 'technician':
        $dashboardUrl = '/network-management/views/technician/technician.php';
        break;
}

$deviceListUrl = '';
switch (strtolower($userData['role'])) {
    case 'admin':
        $deviceListUrl = '/network-management/views/admin/devices.php';
        break;
    case 'staff':
        $deviceListUrl = '/network-management/views/staff/devicesStaff.php';
        break;
    case 'technician':
        $deviceListUrl = '/network-management/views/technician/devicesTech.php';
        break;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <meta name="description" content="POS - Bootstrap Admin Template">
    <meta name="keywords"
        content="admin, estimates, bootstrap, business, corporate, creative, invoice, html5, responsive, Projects">
    <meta name="author" content="Dreamguys - Bootstrap Admin Template">
    <meta name="robots" content="noindex, nofollow">
    <title>Profile - Network Management</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"
        integrity="sha512-c42qTSw/wiW5oaDSLFhn5z7mS0bIX7PB87LWBRH5iA/YB4iR8v+QYq5uTNkO5D3n4CW4S996zAqRpWIcLtYAiRw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/network-management/assets/css/style.css">
</head>

<body>
    <div id="global-loader">
        <div class="whirly-loader"></div>
    </div>

    <div class="main-wrapper">
        <?php
        require_once __DIR__ . '/../includes/sidebarAll.php';
        ?>
        <div class="header">
            <div class="header-left active">
                <a href="<?php echo $dashboardUrl; ?>" class="logo">
                    <img src="/network-management/assets/img/logo1.png" alt="Logo">
                </a>
                <a href="<?php echo $dashboardUrl; ?>" class="logo-small">
                    <img src="/network-management/assets/img/logo-small.png" alt="Logo">
                </a>
                <a id="toggle_btn" href="javascript:void(0);"></a>
            </div>

            <a id="mobile_btn" class="mobile_btn" href="#sidebar">
                <span class="bar-icon">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </a>

            <ul class="nav user-menu">
                <li class="nav-item">
                    <!-- <div class="top-nav-search">
                        <a href="javascript:void(0);" class="responsive-search">
                            <i class="fa fa-search"></i>
                        </a>
                        <form action="#">
                            <div class="searchinputs">
                                <input type="text" placeholder="Search Here ...">
                                <div class="search-addon">
                                    <span><img src="/network-management/assets/img/icons/closes.svg" alt="img"></span>
                                </div>
                            </div>
                            <a class="btn" id="searchdiv"><img src="/network-management/assets/img/icons/search.svg"
                                    alt="img"></a>
                        </form>
                    </div> -->
                </li>

                <li class="nav-item dropdown has-arrow flag-nav">
                    <!-- <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="javascript:void(0);"
                        role="button">
                        <img src="/network-management/assets/img/flags/us1.png" alt="" height="20">
                    </a> -->
                    <!-- <div class="dropdown-menu dropdown-menu-right">
                        <a href="javascript:void(0);" class="dropdown-item">
                            <img src="/network-management/assets/img/flags/us.png" alt="" height="16"> English
                        </a>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <img src="/network-management/assets/img/flags/vn.png" alt="" height="16"> Vietnamese
                        </a>
                    </div> -->
                </li>

                <li class="nav-item dropdown">
                    <!-- <a href="javascript:void(0);" class="dropdown-toggle nav-link" data-bs-toggle="dropdown">
                        <img src="/network-management/assets/img/icons/notification-bing.svg" alt="img">
                        <span class="badge rounded-pill">4</span>
                    </a> -->
                    <!-- <div class="dropdown-menu notifications">
                        <div class="topnav-dropdown-header">
                            <span class="notification-title">Notifications</span>
                            <a href="javascript:void(0)" class="clear-noti"> Clear All </a>
                        </div>
                        <div class="noti-content">
                            <ul class="notification-list">
                                <li class="notification-message">
                                    <a href="activities.html">
                                        <div class="media d-flex">
                                            <span class="avatar flex-shrink-0">
                                                <img alt="" src="/network-management/assets/img/profiles/avatar-02.jpg">
                                            </span>
                                            <div class="media-body flex-grow-1">
                                                <p class="noti-details"><span class="noti-title">John Doe</span> added
                                                    new task <span class="noti-title">Device check</span></p>
                                                <p class="noti-time"><span class="notification-time">4 mins ago</span>
                                                </p>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="topnav-dropdown-footer">
                            <a href="activities.html">View all Notifications</a>
                        </div>
                    </div> -->
                </li>

                <li class="nav-item dropdown has-arrow main-drop">
                    <a href="javascript:void(0);" class="dropdown-toggle nav-link userset" data-bs-toggle="dropdown">
                        <span class="user-img"><img
                                src="/network-management/assets/<?php echo htmlspecialchars($userData['profile_picture'] ?? 'images/default.jpg'); ?>"
                                alt="">
                            <span class="status online"></span></span>
                    </a>
                    <div class="dropdown-menu menu-drop-user">
                        <div class="profilename">
                            <div class="profileset">
                                <span class="user-img"><img
                                        src="/network-management/assets/<?php echo htmlspecialchars($userData['profile_picture'] ?? 'images/default.jpg'); ?>"
                                        alt="">
                                    <span class="status online"></span></span>
                                <div class="profilesets">
                                    <h6><?php echo htmlspecialchars($userData['fullname'] ?? 'User'); ?></h6>
                                    <h5><?php echo htmlspecialchars($userData['role'] ?? 'Unknown Role'); ?></h5>
                                </div>
                            </div>
                            <hr class="m-0">
                            <a class="dropdown-item" href="/network-management/views/profile.php"> <i class="me-2"
                                    data-feather="user"></i> My Profile</a>

                            <hr class="m-0">
                            <a class="dropdown-item logout pb-0" href="/network-management/views/auth/logout.php"><img
                                    src="/network-management/assets/img/icons/log-out.svg" class="me-2"
                                    alt="img">Logout</a>
                        </div>
                    </div>
                </li>
            </ul>

            <div class="dropdown mobile-user-menu">
                <a href="javascript:void(0);" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
                    aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="/network-management/views/profile.php">My Profile</a>
                    <a class="dropdown-item" href="#">Settings</a>
                    <a class="dropdown-item" href="/network-management/views/auth/logout.php">Logout</a>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-wrapper">
            <div class="content">
                <div class="page-header">
                    <div class="page-title">
                        <h4>Hồ sơ cá nhân</h4>
                        <h6>Cập nhật thông tin cá nhân và mật khẩu của bạn</h6>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="profile-set">
                            <div class="profile-head"></div>
                            <div class="profile-top">
                                <div class="profile-content">
                                    <div class="profile-contentimg">
                                        <img src="/network-management/assets/<?php echo htmlspecialchars($userData['profile_picture'] ?? 'images/default.jpg'); ?>"
                                            alt="Profile Picture" id="blah">
                                        <div class="profileupload">
                                            <input type="file" id="imgInp" name="profile_picture" form="profileForm">
                                            <a href="javascript:void(0);">
                                                <img src="/network-management/assets/img/icons/edit-set.svg" alt="edit">
                                            </a>
                                        </div>
                                    </div>
                                    <div class="profile-contentname">
                                        <h2><?php echo htmlspecialchars($userData['fullname'] ?? 'User'); ?></h2>
                                        <h4><?php echo htmlspecialchars($userData['role'] ?? 'Unknown Role'); ?> - Cập
                                            nhật thông tin cá nhân</h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <!-- Form duy nhất cho cả thông tin cá nhân và mật khẩu -->
                        <form id="profileForm" method="POST" enctype="multipart/form-data"
                            action="/network-management/controllers/ProfileController.php">
                            <div class="row">
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group">
                                        <label>Họ và tên</label>
                                        <input type="text" name="fullname" class="form-control"
                                            value="<?php echo htmlspecialchars($userData['fullname']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" name="email" class="form-control"
                                            value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group">
                                        <label>Số điện thoại</label>
                                        <input type="text" name="phone_number" class="form-control"
                                            value="<?php echo htmlspecialchars($userData['phone_number']); ?>">
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group">
                                        <label>Ngày sinh</label>
                                        <input type="date" name="birthday" class="form-control"
                                            value="<?php echo htmlspecialchars($userData['birthday']); ?>">
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group">
                                        <label>Giới tính</label>
                                        <select name="gender" class="form-select">
                                            <option value="male"
                                                <?php if ($userData['gender'] == 'male') echo 'selected'; ?>>Nam
                                            </option>
                                            <option value="female"
                                                <?php if ($userData['gender'] == 'female') echo 'selected'; ?>>Nữ
                                            </option>
                                            <option value="other"
                                                <?php if ($userData['gender'] == 'other') echo 'selected'; ?>>Khác
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <h5 class="mt-4">Cập nhật mật khẩu</h5>
                            <div class="row">
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group">
                                        <label>Mật khẩu cũ</label>
                                        <div class="input-group pass-group">
                                            <input type="password" name="old_password" id="old_password"
                                                class="form-control pass-input" placeholder="Nhập mật khẩu cũ">
                                            <span class="input-group-text toggle-password-old" style="cursor: pointer;">
                                                <i class="fas fa-eye-slash" id="toggleOldPassword"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mật khẩu mới (CSS template, script tự viết) -->
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group">
                                        <label>Mật khẩu mới</label>
                                        <div class="input-group pass-group">
                                            <input type="password" name="new_password" id="new_password"
                                                class="form-control pass-input" placeholder="Nhập mật khẩu mới">
                                            <span class="input-group-text toggle-password-new" style="cursor: pointer;">
                                                <i class="fas fa-eye-slash" id="toggleNewPassword"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-12">
                                    <div class="form-group">
                                        <label>Xác nhận mật khẩu mới</label>
                                        <div class="input-group pass-group">
                                            <input type="password" name="confirm_password" id="confirm_password"
                                                class="form-control pass-input">
                                            <span class="input-group-text toggle-password" style="cursor: pointer;">
                                                <i class="fas fa-eye-slash" id="toggleConfirmPassword"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="update_profile" value="1"
                                        class="btn btn-submit me-2">Cập nhật Hồ sơ</button>
                                    <a href="#" class="btn btn-cancel" id="cancelBtn">Hủy</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
        integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.1/feather.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous">
    </script>
    <script src="/network-management/assets/js/script.js"></script>
    <script>
    $(window).on('load', function() {
        $('#global-loader').fadeOut();
    });

    $(document).ready(function() {
        feather.replace(); // Khởi tạo Feather Icons

        // Toggle sidebar
        $('#toggle_btn, #mobile_btn').on('click', function() {
            $('#sidebar').toggleClass('active');
            $('.page-wrapper').toggleClass('expanded');
        });

        // Preview uploaded image
        $("#imgInp").change(function() {
            readURL(this);
        });

        function readURL(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#blah').attr('src', e.target.result);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Script tự viết cho "Mật khẩu cũ"
        $('.toggle-password-old').click(function() {
            var input = $('#old_password');
            var icon = $(this).find('i');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            } else {
                input.attr('type', 'password');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            }
        });

        // Script tự viết cho "Mật khẩu mới"
        $('.toggle-password-new').click(function() {
            var input = $('#new_password');
            var icon = $(this).find('i');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            } else {
                input.attr('type', 'password');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            }
        });

        if ($(".toggle-password").length > 0) {
            $(document).on("click", ".toggle-password", function() {
                var input = $("#confirm_password"); // Chỉ áp dụng cho confirm_password
                var icon = $(this).find('i');
                if (input.attr("type") === "password") {
                    input.attr("type", "text");
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                } else {
                    input.attr("type", "password");
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                }
            });
        }

        // Hàm quay lại trang trước
        function goBack() {
            console.log("goBack called"); // Debug
            if (window.history.length > 1) {
                console.log("Navigating back"); // Debug
                window.history.back();
            } else {
                console.log("Redirecting to dashboard: <?php echo $dashboardUrl; ?>"); // Debug
                window.location.href = '<?php echo $dashboardUrl; ?>';
            }
        }

        // Sự kiện jQuery bổ sung cho nút Hủy
        $('#cancelBtn').on('click', function(e) {
            e.preventDefault(); // Ngăn hành vi mặc định của href
            console.log("Cancel button clicked"); // Debug
            goBack();
        });
    });
    </script>
</body>

</html>