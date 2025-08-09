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
        } elseif ($action === 'update_status_maintenance') {
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

            $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
            $completion_date = filter_input(INPUT_POST, 'completion_date', FILTER_VALIDATE_FLOAT);
            $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

            if (!$notes || $completion_date === false || !$status) {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Dữ liệu đầu vào không hợp lệ!']);
                exit();
            }

            $statusUpdateData = [
                'notes' => $notes,
                'completion_date' => date('Y-m-d'),
                'status' => $status
            ];
            $result = $DeviceController->handleRequest('update_status_maintenance', [
                'record_id' => $recordId,
                'data' => $statusUpdateData
            ]);

            ob_clean();
            echo json_encode([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message'],
                'data' => $result['success'] ? $statusUpdateData : null
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
$result = $DeviceController->handleRequest('get_assigned_maintenance', [
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
    <title>Trang bảo trì thiết bị</title>

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
                        <h6>Trang thiết bị được phân công bảo trì</h6>
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
                                <!-- <ul>
                                    <li><a data-bs-toggle="tooltip" title="pdf"><img
                                                src="../../assets/img/icons/pdf.svg" alt="pdf"></a></li>
                                    <li><a data-bs-toggle="tooltip" title="excel"><img
                                                src="../../assets/img/icons/excel.svg" alt="excel"></a></li>
                                    <li><a data-bs-toggle="tooltip" title="print"><img
                                                src="../../assets/img/icons/printer.svg" alt="print"></a></li>
                                </ul> -->
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
                                        <!-- <th>Giá tiền</th> -->
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
                                        <!-- <td><?php echo htmlspecialchars($maintenances['cost'] ?? '0', ENT_QUOTES, 'UTF-8'); ?> -->
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
                                                data-notes="<?php echo htmlspecialchars($maintenances['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-cost="<?php echo htmlspecialchars($maintenances['completion_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-status="<?php echo htmlspecialchars($maintenances['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <img src="../../assets/img/icons/edit.svg" alt="edit">
                                            </a>
                                            <!-- <a class="me-3 delete_maintenances"
                                                data-record-id="<?php echo htmlspecialchars($maintenances['record_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                href="javascript:void(0);">
                                                <img src="../../assets/img/icons/delete.svg" alt="delete_maintenances">
                                            </a> -->
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
                                    <input type="hidden" name="action" value="update_status_maintenance">
                                    <input type="hidden" name="record_id" id="edit_record_id">
                                    <div class="form-group mb-3">
                                        <label>Ghi chú bảo trì <span class="text-danger">*</span></label>
                                        <textarea name="notes" id="edit_notes" class="form-control" required
                                            rows="3"></textarea>
                                    </div>
                                    <!-- <div class="form-group mb-3">
                                        <label>Chi phí <span class="text-danger">*</span></label>
                                        <input type="number" name="cost" id="edit_cost" class="form-control" step="0.01"
                                            required>
                                    </div> -->
                                    <div class="form-group mb-3">
                                        <label>Trạng thái <span class="text-danger">*</span></label>
                                        <select name="status" id="edit_status" class="form-select" required>
                                            <option value="completed">Hoàn thành</option>
                                            <option value="pending">Đang chờ</option>
                                            <option value="cancelled">Hủy</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Lưu</button>
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
            var notes = $(this).data('notes');
            var cost = $(this).data('cost');
            var status = $(this).data('status');

            // Điền dữ liệu vào modal
            $('#edit_record_id').val(recordId);
            $('#edit_notes').val(notes);
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
                        rowData.notes = response.data.notes;
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