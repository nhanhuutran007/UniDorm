<?php
// Path: /network-management/views/admin/assignmentlist.php
require_once __DIR__ . '/../../controllers/DeviceController.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /network-management/auth/login.php");
    exit();
}

// Khởi tạo DeviceController
$DeviceController = new DeviceController($_SESSION['user_id'], $_SESSION['role']);

// Lấy danh sách phân quyền thiết bị
$result = $DeviceController->handleRequest('get_all_assignment', [ 'limit' => 100, 'offset' => 0]);
$assign = $result['success'] ? $result['data'] : [];




// Xử lý POST request
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['assignment_id']) || empty($_POST['assignment_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thiết bị để xử lý!']);
        ob_end_flush();
        exit();
    }

    $assignsId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
    if ($assignsId === false || $assignsId === null) {
        echo json_encode(['status' => 'error', 'message' => 'ID phân quyền không hợp lệ!']);
        ob_end_flush();
        exit();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $assigns = null;
        foreach ($assign as $d) {
            if ($d['assignment_id'] == $assignsId) {
                $assigns = $d;
                break;
            }
        }
        if (!$assigns) {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thiết bị trong danh sách!']);
            ob_end_flush();
            exit();
        }
        $current_status = $assigns['status'];
        $new_status = ($current_status === 'active') ? 'returned' : 'active';
        $params = [
            'assignment_id' => $assignsId,
            'data' => [
                'status' => $new_status,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
    
        $result = $DeviceController->handleRequest('update_assignment', $params);
    
        if ($result['success']) {
            $message = "Đã cập nhật trạng thái thiết bị thành công!";
            echo json_encode(['status' => 'success', 'message' => $message]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không thể cập nhật trạng thái: ' . $result['message']]);
        }
        ob_end_flush();
        exit();
    } elseif ($action === 'delete_assignment') {
        $params = ['assignment_id' => $assignsId];
        $result = $DeviceController->handleRequest('delete_assignment', $params);
        echo json_encode([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['message']
        ]);
        ob_end_flush();
        exit();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
        ob_end_flush(); // Xả buffer cho phần HTML nếu không phải POST
        exit();
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Trang phân quyền thiết bị</title>

    <link rel="shortcut icon" type="image/x-icon" href="../../assets/img/favicon.svg">

    <!-- Bootstrap yCSS -->
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
                        <h4>Phân quyền thiết bị</h4>
                        <h6>Trang phân quyền thiết bị dành cho Admin</h6>
                    </div>
                    <div class="page-btn">
                        <a href="addassign.php" class="btn btn-added">
                            <img src="../../assets/img/icons/plus.svg" alt="img" class="me-1">Thêm phân quyền
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
                                        <a data-bs-toggle="tooltip" title="PDF" data-pdf-button="true" data-type="pdf"
                                            data-data="assignment" href="javascript:void(0);">
                                            <img src="../../assets/img/icons/pdf.svg" alt="pdf">
                                        </a>
                                    </li>
                                    <li><a data-excel-button="true" data-data="assignment" data-bs-toggle="tooltip"
                                            data-bs-placement="top" title="Xuất Excel"><img
                                                src="../../assets/img/icons/excel.svg" alt="excel"></a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table datanew">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>ID thiết bị</th>
                                        <th>ID người dùng</th>
                                        <th>Ngày phân quyền</th>
                                        <th>Ngày dự kiến</th>
                                        <th>Ngày thực tế</th>
                                        <th>Trạng thái</th>
                                        <th>Người phân quyền</th>
                                        <th>Ghi chú</th>
                                        <th>Ngày tạo</th>
                                        <th>Ngày cập nhật</th>
                                        <th>Chức năng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assign as $assigns): ?>
                                    <?php if (!empty($assigns['assigned_date'])): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($assigns['assignment_id']) ?></td>
                                        <td><?= htmlspecialchars($assigns['device_id']) ?></td>
                                        <td><?= htmlspecialchars($assigns['user_id']) ?></td>
                                        <td><?= htmlspecialchars($assigns['assigned_date']) ?></td>
                                        <td><?= htmlspecialchars($assigns['expected_return_date']) ?></td>
                                        <td><?= htmlspecialchars($assigns['actual_return_date']) ?></td>
                                        <td><?= ucfirst(htmlspecialchars($assigns['status'])) ?></td>
                                        <td><?= htmlspecialchars($assigns['assigned_by_user_id']) ?></td>
                                        <td><?= htmlspecialchars($assigns['notes']) ?></td>
                                        <td><?= htmlspecialchars($assigns['created_at']) ?></td>
                                        <td><?= htmlspecialchars($assigns['updated_at']) ?></td>
                                        <td>
                                            <a class="me-3 edit-assignment" href="javascript:void(0);"
                                                data-assignment-id="<?= htmlspecialchars($assigns['assignment_id']) ?>"
                                                data-user-id="<?= htmlspecialchars($assigns['user_id']) ?>"
                                                data-device-id="<?= htmlspecialchars($assigns['device_id']) ?>">
                                                <img src="../../assets/img/icons/edit.svg" alt="edit">
                                            </a>
                                            <a class="me-3 toggle-status"
                                                data-device-id="<?= $assigns['assignment_id'] ?>"
                                                href="javascript:void(0);">
                                                <img src="../../assets/img/icons/toggleon.svg" alt="toggle status"
                                                    width="29" height="29">
                                            </a>
                                            <a class="me-3 delete_assignment"
                                                data-device-id="<?= $assigns['assignment_id'] ?>"
                                                href="javascript:void(0);">
                                                <img src="../../assets/img/icons/delete.svg" alt="delete_assignment">
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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

    <!-- Custom Scripts -->
    <script src="../../assets/js/script.js"></script>

    <script>
    $(document).ready(function() {
        // Kiểm tra thư viện
        if (typeof jQuery === 'undefined') console.error('jQuery không tải được');
        else console.log('jQuery đã tải thành công');

        if (typeof feather !== 'undefined') feather.replace();
        else console.error('Feather Icons không tải được');

        if ($.fn.DataTable) $('.datanew').DataTable();
        else console.error('DataTables không tải được');

        // Xử lý toggle status
        $('.toggle-status').click(function() {
            var assignmentId = $(this).data('device-id');
            if (confirm('Bạn có muốn thay đổi trạng thái thiết bị ?')) {
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        assignment_id: assignmentId,
                        action: 'toggle_status'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error: ' + error);
                    }
                });
            }
        });


        function bindPdfEvent() {
            const selector = '.wordset a[data-pdf-button="true"]';
            console.log('Binding PDF event, button count:', $(selector).length);
            $(document).off('click', selector).on('click', selector, function(e) {
                e.preventDefault();
                var dataType = $(this).data('data');
                var statusFilter = $('#statusFilter').val();
                console.log('PDF button clicked, data:', dataType, 'status:', statusFilter);
                $.ajax({
                    url: '/network-management/report/export_pdf.php',
                    type: 'POST',
                    data: {
                        type: 'pdf',
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
                        link.download = 'danh_sach_phan_quyen.pdf';
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
                        link.download = 'danh_sach_phan_quyen.xlsx';
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




        // Xử lý xóa phân quyền
        $('.delete_assignment').click(function() {
            var assignmentId = $(this).data('device-id'); // Sửa lại để lấy đúng assignment_id
            if (confirm('Bạn có muốn xóa phân quyền thiết bị này ?')) {
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        assignment_id: assignmentId, // Truyền assignment_id vào đây
                        action: 'delete_assignment'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error: ' + error);
                    }
                });
            }
        });

        // Xử lý sự kiện click vào nút "edit"
        $('.edit-assignment').click(function(event) {
            event.preventDefault(); // Ngăn chặn hành động mặc định của liên kết

            // Lấy các giá trị từ thuộc tính data-*
            var assignmentId = $(this).data('assignment-id');

            if (!assignmentId) {
                alert('Không thể lấy đủ thông tin để chỉnh sửa.');
                return;
            }

            // Xây dựng URL với các tham số
            var url =
                `updateassign.php?assignment_id=${assignmentId}`;
            console.log('Redirecting to:', url);

            // Chuyển hướng đến URL mới
            window.location.href = url;
        });
    });
    </script>
</body>

</html>