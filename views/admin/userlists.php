<?php
//path: views/admin/userlists.php
require_once(__DIR__ . '/../../includes/db.php');
require_once(__DIR__ . '/../../controllers/UserController.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: /network-management/auth/login.php");
    exit();
}

// Kiểm tra và xử lý thay đổi trạng thái người dùng
if (isset($_GET['toggle_status']) && isset($_GET['username']) && $_SESSION['role'] === 'admin') {
    $username = $_GET['username'];
    $currentUser = $_SESSION['username'];
    $controller = new UserController();
    $result = $controller->toggleStatus($username, $currentUser);

    if (isset($result['success'])) {
        $_SESSION['success_message'] = $result['success'];
    } else {
        $_SESSION['error_message'] = $result['error'] ?? "Lỗi khi thay đổi trạng thái người dùng.";
    }
    
    // Chuyển hướng về userlists.php để tránh lặp lại hành động khi làm mới trang
    header("Location: userlists.php");
    exit();
}

$userController = new UserController();
$users = $userController->getUsers($_GET); // Lấy danh sách người dùng thông qua controller
// Sắp xếp theo số phòng (room) alphabet
usort($users, function($a, $b) {
    // Tách phần chữ và số
    preg_match('/^([A-Za-z]+)(\d+)/', $a['room'], $matchesA);
    preg_match('/^([A-Za-z]+)(\d+)/', $b['room'], $matchesB);

    $prefixA = $matchesA[1] ?? $a['room'];
    $prefixB = $matchesB[1] ?? $b['room'];
    $numA = isset($matchesA[2]) ? intval($matchesA[2]) : 0;
    $numB = isset($matchesB[2]) ? intval($matchesB[2]) : 0;

    // So sánh phần chữ trước
    if ($prefixA === $prefixB) {
        // Nếu cùng phòng, so sánh số
        return $numA - $numB;
    }
    return strcmp($prefixA, $prefixB);
});
// Xử lý thông báo toast
$show_success_toast = isset($_SESSION['success_message']);
$show_error_toast = isset($_SESSION['error_message']);
$success_message = $show_success_toast ? htmlspecialchars($_SESSION['success_message']) : '';
$error_message = $show_error_toast ? htmlspecialchars($_SESSION['error_message']) : '';

if ($show_success_toast) unset($_SESSION['success_message']);
if ($show_error_toast) unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <link rel="shortcut icon" type="image/x-icon" href="../../assets/img/favicon.svg">
    <title>Trang quản lý sinh viên</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"
        integrity="sha512-c42qTSw/wiW5oaDSLFhn5z7mS0bIX7PB87LWBRH5iA/YB4iR8v+QYq5uTNkO5D3n4CW4S996zAqRpWIcLtYAiRw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- DataTables Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/style.css">
    <!-- Custom Toast CSS -->
    <style>
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1055;
    }

    .toast {
        min-width: 300px;
    }
    </style>
</head>

<body>
    <div id="global-loader">
        <div class="whirly-loader"></div>
    </div>

    <div class="main-wrapper">
        <?php include(__DIR__ . '/../../includes/header.php'); ?>
        <?php include(__DIR__ . '/../../includes/sidebarAll.php'); ?>

        <!-- Toast Container -->
        <div class="toast-container">
            <?php if ($show_success_toast): ?>
            <div class="toast align-items-center text-white bg-success border-0" id="successToast" role="alert"
                aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                        aria-label="Close"></button>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($show_error_toast): ?>
            <div class="toast align-items-center text-white bg-danger border-0" id="errorToast" role="alert"
                aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error_message; ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                        aria-label="Close"></button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="page-wrapper">
            <div class="content">
                <div class="page-header">
                    <div class="page-title">
                        <h4>Danh sách sinh viên</h4>
                        <h6>Quản lý sinh viên của bạn</h6>
                    </div>
                    <div class="page-btn">
                        <a href="./newuser.php" class="btn btn-added">
                            <img src="../../assets/img/icons/plus.svg" alt="img">Thêm sinh viên
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-top">
                            <div class="search-set">
                                <div class="search-path">
                                    <a class="btn btn-filter" id="filter_search">
                                        <img src="../../assets/img/icons/filter.svg" alt="img">
                                        <span><img src="../../assets/img/icons/closes.svg" alt="img"></span>
                                    </a>
                                </div>
                                <div class="search-input">
                                    <a class="btn btn-searchset"><img src="../../assets/img/icons/search-white.svg"
                                            alt="img"></a>
                                </div>
                            </div>
                            <div class="wordset">
                                <ul>
                                    <li>
                                        <a data-pdf-button="true" data-type="pdf" data-data="userlist"
                                            data-bs-toggle="tooltip" data-bs-placement="top" title="Xuất PDF"><img
                                                src="../../assets/img/icons/pdf.svg" alt="img"></a>
                                    </li>
                                    <li>
                                        <a data-excel-button="true" data-data="userlist" data-bs-toggle="tooltip"
                                            data-bs-placement="top" title="Xuất Excel"><img
                                                src="../../assets/img/icons/excel.svg" alt="excel"></a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Bộ lọc -->
                        <div class="card" id="filter_inputs">
                            <div class="card-body pb-0">
                                <form method="GET" action="">
                                    <div class="row">
                                        <div class="col-lg-2 col-sm-6 col-12">
                                            <div class="form-group">
                                                <input type="text" name="username" placeholder="Nhập Username"
                                                    value="<?php echo htmlspecialchars($_GET['username'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-lg-2 col-sm-6 col-12">
                                            <div class="form-group">
                                                <input type="text" name="fullname" placeholder="Nhập Họ tên"
                                                    value="<?php echo htmlspecialchars($_GET['fullname'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-lg-2 col-sm-6 col-12">
                                            <div class="form-group">
                                                <input type="text" name="email" placeholder="Nhập Email"
                                                    value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-lg-2 col-sm-6 col-12">
                                            <div class="form-group">
                                                <input type="text" name="role" placeholder="Nhập Vai trò"
                                                    value="<?php echo htmlspecialchars($_GET['role'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-lg-2 col-sm-6 col-12">
                                            <div class="form-group">
                                                <select class="select" name="status">
                                                    <option value="">Tất cả trạng thái</option>
                                                    <option value="active"
                                                        <?php echo (($_GET['status'] ?? '') == 'active') ? 'selected' : ''; ?>>
                                                        Kích hoạt</option>
                                                    <option value="inactive"
                                                        <?php echo (($_GET['status'] ?? '') == 'inactive') ? 'selected' : ''; ?>>
                                                        Khóa</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-lg-1 col-sm-6 col-12 ms-auto">
                                            <div class="form-group">
                                                <button type="submit" class="btn btn-filters ms-auto"><img
                                                        src="../../assets/img/icons/search-whites.svg"
                                                        alt="img"></button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Bảng dữ liệu -->
                        <div class="table-responsive">
                            <table class="table datanew">
                                <thead>
                                    <tr>
                                        <th>Phòng</th>
                                        <th>Mã HV</th>
                                        <th>Họ tên</th>
                                        <th>Số giường</th>
                                        <th>Quê quán</th>
                                        <th>Email</th>
                                        <th>Thao tác</th>

                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($users) && is_array($users)): ?>
                                    <?php foreach ($users as $user): 
                                        $is_disabled = empty($user['username']) ? 'disabled' : ''; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['room']); ?></td>
                                        <td><?php echo htmlspecialchars($user['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                        <td><?php echo htmlspecialchars($user['num_bed']); ?></td>
                                        <td><?php echo htmlspecialchars($user['hometown']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>

                                        <!-- <td>
                                            <span
                                                class="<?php echo ($user['status'] == 'active') ? 'bg-lightgreen badges' : 'bg-lightred badges'; ?>">
                                                <?php echo htmlspecialchars($user['status'] == 'active' ? 'Kích hoạt' : 'Khóa'); ?>
                                            </span>
                                        </td> -->
                                        <td>
                                            <a class="me-3"
                                                href="updateuser.php?username=<?php echo urlencode($user['username']); ?>">
                                                <img src="../../assets/img/icons/edit.svg" alt="img">
                                            </a>
                                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                            <!-- <a class="me-3"
                                                href="?toggle_status=1&username=<?php echo urlencode($user['username']); ?>">
                                                <img src="../../assets/img/icons/<?php echo ($user['status'] == 'active') ? 'lock' : 'unlock'; ?>.svg"
                                                    alt="img" width="24" height="24">
                                            </a> -->
                                            <?php endif; ?>
                                            <a class="me-3 confirm-text"
                                                href="deleteUser.php?username=<?php echo urlencode($user['username']); ?>"
                                                onclick="return confirm('Bạn có chắc chắn muốn xóa người dùng này?');">
                                                <img src="../../assets/img/icons/delete.svg" alt="img">
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Không có dữ liệu người dùng.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.1/feather.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jQuery-slimScroll/1.3.8/jquery.slimscroll.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>

    <!-- Custom Scripts -->
    <script src="../../assets/js/script.js"></script>

    <!-- Toast and Submenu Scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Ẩn loader khi trang tải xong
        const loader = document.getElementById('global-loader');
        window.addEventListener('load', () => loader.classList.add('hidden'));

        // Khởi tạo toast
        const successToast = new bootstrap.Toast(document.getElementById('successToast'), {
            delay: 4000 // Ẩn sau 4 giây
        });
        const errorToast = new bootstrap.Toast(document.getElementById('errorToast'), {
            delay: 4000 // Ẩn sau 4 giây
        });

        // Hiển thị toast nếu có thông báo
        <?php if ($show_success_toast): ?>
        successToast.show();
        <?php endif; ?>
        <?php if ($show_error_toast): ?>
        errorToast.show();
        <?php endif; ?>
    });

    $(document).ready(function() {
        // Kiểm tra thư viện
        if (typeof jQuery === 'undefined') {
            console.error('jQuery không tải được');
        } else {
            console.log('jQuery đã tải thành công');
        }

        if (typeof feather !== 'undefined') {
            feather.replace();
        } else {
            console.error('Feather Icons không tải được');
        }

        if ($.fn.DataTable) {
            $('.datanew').DataTable();
        } else {
            console.error('DataTables không tải được');
        }

        // Xử lý submenu toggle
        $('.submenu-toggle').click(function() {
            $(this).next('.submenu-list').slideToggle();
            $(this).find('.menu-arrow').toggleClass('rotate');
        });

        if (typeof ApexCharts === 'undefined') {
            console.error('ApexCharts không tải được');
        } else {
            console.log('ApexCharts đã tải thành công');
        }

        function bindPdfEvent() {
            const selector = '.wordset a[data-pdf-button="true"]';
            console.log('Binding PDF event, button count:', $(selector).length);
            $(document).off('click', selector).on('click', selector, function(e) {
                e.preventDefault();
                e.stopPropagation();
                var type = $(this).data('type');
                var dataType = $(this).data('data');
                var statusFilter = $('select[name="status"]').val() || '';
                console.log('PDF button clicked, type:', type, 'data:', dataType, 'status:',
                    statusFilter);
                $.ajax({
                    url: '/network-management/report/export_pdf.php',
                    type: 'POST',
                    data: {
                        type: type,
                        data: dataType,
                        statusFilter: statusFilter
                    },
                    xhrFields: {
                        responseType: 'blob'
                    },
                    success: function(response, status, xhr) {
                        console.log('PDF generated successfully');
                        var blob = new Blob([response], {
                            type: 'application/pdf'
                        });
                        var link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = 'danh_sach_nguoi_dung.pdf';
                        link.click();
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', xhr.status, xhr.responseText);
                        try {
                            var response = JSON.parse(xhr.responseText);
                            alert('Lỗi khi xuất file PDF: ' + response.message);
                        } catch (e) {
                            alert('Lỗi khi xuất file PDF: ' + error + ' (Status: ' + xhr
                                .status + ')');
                        }
                    }
                });
            });
        }

        bindPdfEvent();

        function bindExcelEvent() {
            const selector = '.wordset a[data-excel-button="true"]';
            console.log('Binding Excel event, button count:', $(selector).length);
            $(document).off('click', selector).on('click', selector, function(e) {
                e.preventDefault();
                var dataType = $(this).data('data');
                var statusFilter = $('#statusFilter').val();
                console.log('Excel button clicked, data:', dataType, 'status:', statusFilter);
                $.ajax({
                    url: '/network-management/report/export_excel.php',
                    type: 'POST',
                    data: {
                        type: 'excel',
                        data: dataType,
                        statusFilter: statusFilter
                    },
                    xhrFields: {
                        responseType: 'blob'
                    },
                    success: function(response, status, xhr) {
                        console.log('Excel generated successfully');
                        var blob = new Blob([response], {
                            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        });
                        var link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = 'danh_sach_nguoi_dung.xlsx';
                        link.click();
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', xhr.status, xhr.responseText);
                        try {
                            var response = JSON.parse(xhr.responseText);
                            alert('Lỗi khi xuất file Excel: ' + response.message);
                        } catch (e) {
                            alert('Lỗi khi xuất file Excel: ' + error + ' (Status: ' + xhr
                                .status + ')');
                        }
                    }
                });
            });
        }
        bindExcelEvent();

    });
    // Xử lý sự kiện click vào submenu
    $('.submenu > a').click(function(e) {
        e.preventDefault();
        const $submenu = $(this).next('ul');
        const $menuArrow = $(this).find('.menu-arrow');

        $('.submenu ul').not($submenu).slideUp();
        $('.menu-arrow').not($menuArrow).removeClass('rotate');

        $submenu.slideToggle();
        $menuArrow.toggleClass('rotate');
    });
    </script>
</body>

</html>