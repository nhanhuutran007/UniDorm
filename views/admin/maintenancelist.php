<?php
// Path: views/admin/maintenancelist.php
require_once __DIR__ . '/../../controllers/DeviceController.php';
require_once __DIR__ . '/../../controllers/UserController.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: /network-management/auth/login.php");
    exit();
}

// Khởi tạo DeviceController
$DeviceController = new DeviceController($_SESSION['user_id'], $_SESSION['role']);

// Khởi tạo UserController và lấy danh sách nhân viên
$userController = new UserController();
$users = $userController->getUsers($_GET);
// Lọc chỉ nhân viên có vai trò technician
$technicians = array_filter($users, function($user) {
    return strtolower($user['role']) === 'technician';
});

// Xử lý POST request
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'delete_maintenance') {
            if (!isset($_POST['record_id']) || empty($_POST['record_id'])) {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy bản ghi để xử lý!']);
                exit();
            }

            $recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
            if ($recordId === false || $recordId === null) {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'ID bản ghi không hợp lệ!']);
                exit();
            }

            $params = ['record_id' => $recordId];
            $result = $DeviceController->handleRequest('delete_maintenance', $params);
            ob_clean();
            echo json_encode([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message']
            ]);
            exit();
        } elseif ($action === 'update_maintenance') {
            if (!isset($_POST['record_id']) || empty($_POST['record_id'])) {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy bản ghi để cập nhật!']);
                exit();
            }

            $recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
            if ($recordId === false || $recordId === null) {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'ID bản ghi không hợp lệ!']);
                exit();
            }

            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
            $cost = filter_input(INPUT_POST, 'cost', FILTER_VALIDATE_FLOAT);
            $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

            if (!$description || $cost === false || !$status) {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Dữ liệu đầu vào không hợp lệ!']);
                exit();
            }

            $updateMaintenanceData = [
                'description' => $description,
                'cost' => $cost,
                'status' => $status
            ];

            $result = $DeviceController->handleRequest('update_maintenance', [
                'record_id' => $recordId,
                'data' => $updateMaintenanceData
            ]);

            ob_clean();
            echo json_encode([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message'],
                'data' => $result['success'] ? $updateMaintenanceData : null
            ]);
            exit();
        } elseif ($action === 'assign_technician_maintenance') {
            if (!isset($_POST['record_id']) || empty($_POST['record_id'])) {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy bản ghi để phân công!']);
                exit();
            }

            $recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
            $technicianId = filter_input(INPUT_POST, 'technician_id', FILTER_VALIDATE_INT);

            if ($recordId === false || $recordId === null || $technicianId === false || $technicianId === null) {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'ID bản ghi hoặc ID nhân viên không hợp lệ!']);
                exit();
            }

            $result = $DeviceController->handleRequest('assign_technician_maintenance', [
                'record_id' => $recordId,
                'technician_id' => $technicianId
            ]);

            ob_clean();
            echo json_encode([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message'],
                'data' => $result['success'] ? ['technician_id' => $technicianId] : null
            ]);
            exit();
        } else {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
            exit();
        }
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi server: ' . $e->getMessage()]);
        exit();
    }
}

// Xử lý tham số tìm kiếm
$searchParams = isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8') : '';
$result = $DeviceController->handleRequest('get_all_maintenance', [
    'search' => ['search' => $searchParams],
    'limit' => 100,
    'offset' => 0
]);
$maintenance = $result['success'] ? $result['data'] : [];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Trang quản lý bảo trì thiết bị</title>

    <link rel="shortcut icon" type="image/x-icon" href="../../assets/img/favicon.svg">

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
                        <h4>Danh sách bảo trì</h4>
                        <h6>Quản lý lịch bảo trì thiết bị</h6>
                    </div>
                    <div class="page-btn">
                        <a href="addmaintenance.php" class="btn btn-added">
                            <img src="../../assets/img/icons/plus.svg" alt="img"> Thêm yêu cầu bảo trì
                        </a>
                    </div>
                </div>

                <?php if (!$result['success']): ?>
                <div class="alert alert-danger">
                    Không thể tải dữ liệu bảo trì:
                    <?php echo htmlspecialchars($result['message'] ?? 'Lỗi không xác định', ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>

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
                                            data-data="maintenance" href="javascript:void(0);">
                                            <img src="../../assets/img/icons/pdf.svg" alt="pdf">
                                        </a>
                                    </li>
                                    <li><a data-excel-button="true" data-data="maintenance" data-bs-toggle="tooltip"
                                            data-bs-placement="top" title="Xuất Excel"><img
                                                src="../../assets/img/icons/excel.svg" alt="excel"></a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="maintenanceTable" class="table datanew">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>ID Thiết bị</th>
                                        <th>ID Người báo cáo</th>
                                        <th>ID Người bảo trì</th>
                                        <th>Ngày bảo trì</th>
                                        <th>Ngày hoàn thành</th>
                                        <th>Mô tả</th>
                                        <th>Ghi chú</th>
                                        <th>Giá tiền</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th>Ngày cập nhật</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maintenance as $maintenances): ?>
                                    <tr
                                        data-record-id="<?php echo htmlspecialchars($maintenances['record_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <td><?php echo htmlspecialchars($maintenances['record_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($maintenances['device_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($maintenances['reported_by_user_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($maintenances['performed_by_user_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($maintenances['maintenance_date'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($maintenances['completion_date'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($maintenances['description'] ?? 'Không có mô tả', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($maintenances['notes'] ?? 'Không có ghi chú', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($maintenances['cost'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($maintenances['status'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($maintenances['created_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($maintenances['updated_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td>
                                            <a class="me-3 edit-maintenances" href="javascript:void(0);"
                                                data-maintenances-id="<?php echo htmlspecialchars($maintenances['record_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-description="<?php echo htmlspecialchars($maintenances['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-cost="<?php echo htmlspecialchars($maintenances['cost'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-status="<?php echo htmlspecialchars($maintenances['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <img src="../../assets/img/icons/edit.svg" alt="edit">
                                            </a>
                                            <a class="me-3 schedule_maintenance"
                                                data-record-id="<?php echo htmlspecialchars($maintenances['record_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                href="schedulemaintenance.php">
                                                <img src="../../assets/img/icons/toggleon.svg"
                                                    alt="schedule maintenance" width="29" height="29">
                                            </a>
                                            <a class="me-3 delete_maintenances"
                                                data-record-id="<?php echo htmlspecialchars($maintenances['record_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                href="javascript:void(0);">
                                                <img src="../../assets/img/icons/delete.svg" alt="delete_maintenances">
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Modal chỉnh sửa bảo trì -->
                <div class="modal fade" id="editMaintenanceModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Chỉnh sửa bản ghi bảo trì</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="editMaintenanceForm" method="POST">
                                    <input type="hidden" name="action" value="update_maintenance">
                                    <input type="hidden" name="record_id" id="edit_record_id">
                                    <div class="form-group mb-3">
                                        <label>Mô tả <span class="text-danger">*</span></label>
                                        <textarea name="description" id="edit_description" class="form-control" required
                                            rows="3"></textarea>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label>Chi phí <span class="text-danger">*</span></label>
                                        <input type="number" name="cost" id="edit_cost" class="form-control" step="0.01"
                                            required>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label>Trạng thái <span class="text-danger">*</span></label>
                                        <select name="status" id="edit_status" class="form-select" required>
                                            <option value="pending">Đang chờ</option>
                                            <option value="completed">Hoàn thành</option>
                                            <option value="cancelled">Hủy</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Lưu</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal phân công bảo trì -->
                <div class="modal fade" id="assignMaintenanceModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Phân công bảo trì</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="assignMaintenanceForm" method="POST">
                                    <input type="hidden" name="action" value="assign_technician_maintenance">
                                    <input type="hidden" name="record_id" id="assign_record_id">
                                    <div class="form-group mb-3">
                                        <label>Kỹ thuật viên <span class="text-danger">*</span></label>
                                        <select name="technician_id" id="assign_technician_id" class="form-select"
                                            required>
                                            <option value="">Chọn kỹ thuật viên</option>
                                            <?php if (!empty($technicians)): ?>
                                            <?php foreach ($technicians as $technician): ?>
                                            <option
                                                value="<?php echo htmlspecialchars($technician['user_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                ID:
                                                <?php echo htmlspecialchars($technician['user_id'], ENT_QUOTES, 'UTF-8'); ?>
                                                -
                                                <?php echo htmlspecialchars($technician['fullname'], ENT_QUOTES, 'UTF-8'); ?>
                                                -
                                                Vai trò:
                                                <?php echo htmlspecialchars($technician['role'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <option value="" disabled>Không có kỹ navigator viên nào</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Phân công</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
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
        // Ẩn loader
        $("#global-loader").fadeOut("slow");

        // Khởi tạo DataTable
        let table = $('#maintenanceTable');
        if (!$.fn.DataTable.isDataTable(table)) {
            table.DataTable({
                "processing": true,
                "language": {
                    "processing": "Đang tải dữ liệu...",
                    "emptyTable": "Không có dữ liệu để hiển thị",
                    "lengthMenu": "Hiển thị _MENU_ bản ghi",
                    "zeroRecords": "Không tìm thấy bản ghi nào",
                    "info": "Hiển thị _START_ đến _END_ của _TOTAL_ bản ghi",
                    "infoEmpty": "Hiển thị 0 đến 0 của 0 bản ghi",
                    "infoFiltered": "(lọc từ _MAX_ bản ghi)",
                    "search": "Tìm kiếm:",
                    "paginate": {
                        "first": "Đầu",
                        "last": "Cuối",
                        "next": "Tiếp",
                        "previous": "Trước"
                    }
                },
                "pageLength": 10,
                "order": [
                    [0, "desc"]
                ]
            });
        }

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
                        link.download = 'bao_tri_thiet_bi.pdf';
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
                        link.download = 'bao_tri_thiet_bi.xlsx';
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



        // Xử lý xóa bản ghi bảo trì
        $(document).on('click', '.delete_maintenances', function(e) {
            e.stopPropagation(); // Ngăn sự kiện nhấp dòng
            var recordId = $(this).data('record-id');
            if (confirm('Bạn có muốn xóa bản ghi bảo trì này?')) {
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        record_id: recordId,
                        action: 'delete_maintenance'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            table.DataTable().row($(`tr[data-record-id="${recordId}"]`))
                                .remove().draw(false);
                        } else {
                            alert('Lỗi: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Lỗi AJAX:', xhr.responseText);
                        alert('Lỗi: ' + error);
                    }
                });
            }
        });

        // Xử lý chỉnh sửa bản ghi bảo trì
        $(document).on('click', '.edit-maintenances', function(e) {
            e.stopPropagation(); // Ngăn sự kiện nhấp dòng
            var recordId = $(this).data('maintenances-id');
            var description = $(this).data('description');
            var cost = $(this).data('cost');
            var status = $(this).data('status');

            // Điền dữ liệu vào modal
            $('#edit_record_id').val(recordId);
            $('#edit_description').val(description);
            $('#edit_cost').val(cost);
            $('#edit_status').val(status);

            // Hiển thị modal
            $('#editMaintenanceModal').modal('show');
        });

        // // Xử lý schedule maintenance
        // $(document).on('click', '.schedule_maintenance', function(e) {
        //     e.stopPropagation(); // Ngăn sự kiện nhấp dòng
        //     var recordId = $(this).data('record-id');
        //     // Thêm logic schedule maintenance nếu cần
        // });

        // Xử lý gửi form chỉnh sửa
        $('#editMaintenanceForm').on('submit', function(e) {
            e.preventDefault();
            var formData = $(this).serialize();

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        // Cập nhật hàng trong DataTable
                        var row = table.DataTable().row($(
                            `tr[data-record-id="${$('#edit_record_id').val()}"]`));
                        var rowData = row.data();
                        rowData.description = response.data.description;
                        rowData.cost = response.data.cost;
                        rowData.status = response.data.status;
                        row.data(rowData).draw(false);
                        $('#editMaintenanceModal').modal('hide');
                    } else {
                        alert('Lỗi: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Lỗi AJAX:', xhr.responseText);
                    alert('Lỗi: ' + error);
                }
            });
        });

        // Xử lý nhấp vào dòng để phân công
        $(document).on('click', '#maintenanceTable tbody tr', function() {
            var recordId = $(this).data('record-id');
            if (recordId) {
                // Điền dữ liệu vào modal
                $('#assign_record_id').val(recordId);
                // Hiển thị modal
                $('#assignMaintenanceModal').modal('show');
            }
        });

        // Xử lý gửi form phân công
        $('#assignMaintenanceForm').on('submit', function(e) {
            e.preventDefault();
            var formData = $(this).serialize();

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        // Làm mới toàn bộ bảng để phản ánh dữ liệu mới nhất
                        window.location.reload();
                        $('#assignMaintenanceModal').modal('hide');
                    } else {
                        alert('Lỗi: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Lỗi AJAX:', xhr.responseText);
                    alert('Lỗi: ' + error);
                }
            });
        });
    });
    </script>
</body>

</html>