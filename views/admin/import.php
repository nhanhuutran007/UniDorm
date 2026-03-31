<?php
/**
 * UniDorm – Admin: Import sinh viên từ CSV (import.php)
 * Hỗ trợ import hàng loạt sinh viên từ file CSV
 * Cột CSV: student_code,fullname,gender,date_of_birth,phone_personal,phone_family,hometown,room_code,bed_label
 */
$pageTitle   = 'Import sinh viên từ CSV';
$breadcrumbs = [
    ['label' => 'Quản lý sinh viên', 'url' => '/UniDorm/views/admin/students.php'],
    ['label' => 'Import CSV', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

$result  = null;
$preview = [];
$errors  = [];
$success = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK || $ext !== 'csv') {
        $errors[] = 'Vui lòng upload file CSV hợp lệ.';
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        // Đọc header
        $header = fgetcsv($handle);
        $header = array_map('strtolower', array_map('trim', $header));

        $required = ['student_code', 'fullname'];
        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                $errors[] = "CSV thiếu cột bắt buộc: <strong>$col</strong>";
            }
        }

        if (empty($errors)) {
            $rowNum = 1;
            while (($data = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (count($data) < count($header)) continue;
                $row = array_combine($header, array_map('trim', $data));

                $code     = strtoupper($row['student_code'] ?? '');
                $fullname = $row['fullname'] ?? '';

                if (!$code || !$fullname) {
                    $errors[] = "Dòng $rowNum: thiếu MSSV hoặc họ tên → bỏ qua.";
                    continue;
                }
                if (!preg_match('/^[A-Z0-9]{5,12}$/', $code)) {
                    $errors[] = "Dòng $rowNum: MSSV '$code' không hợp lệ → bỏ qua.";
                    continue;
                }

                $email = $code . '@student.tdtu.edu.vn';

                // Kiểm tra trùng
                $chk = $conn->prepare("SELECT user_id FROM users WHERE student_code = ?");
                $chk->bind_param('s', $code);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $errors[] = "Dòng $rowNum: MSSV '$code' đã tồn tại → bỏ qua.";
                    continue;
                }

                // Tìm bed nếu có room_code + bed_label
                $bedId   = null;
                $roomCode = $row['room_code'] ?? '';
                $bedLabel = $row['bed_label'] ?? '';
                if ($roomCode && $bedLabel) {
                    $bedStmt = $conn->prepare("
                        SELECT b.id FROM beds b
                        JOIN rooms r ON b.room_id = r.id
                        WHERE r.room_code = ? AND b.bed_label = ? AND b.is_occupied = 0
                    ");
                    $bedStmt->bind_param('ss', $roomCode, $bedLabel);
                    $bedStmt->execute();
                    $bedRow = $bedStmt->get_result()->fetch_assoc();
                    $bedId  = $bedRow['id'] ?? null;
                    if (!$bedId && $bedLabel) {
                        $errors[] = "Dòng $rowNum: Giường $bedLabel/$roomCode không tồn tại hoặc đã có người → bỏ qua gán giường.";
                    }
                }

                // Insert user
                $gender  = $row['gender'] ?? null;
                $dob     = $row['date_of_birth'] ?: null;
                $phonePers  = $row['phone_personal'] ?? '';
                $phoneFamily = $row['phone_family'] ?? '';
                $hometown = $row['hometown'] ?? '';

                $ins = $conn->prepare("
                    INSERT INTO users (student_code, username, fullname, email, gender, date_of_birth,
                                       phone_personal, phone_family, hometown, role, status, bed_id, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'student', 'pending', ?, ?)
                ");
                $ins->bind_param('sssssssssii',
                    $code, $code, $fullname, $email, $gender, $dob,
                    $phonePers, $phoneFamily, $hometown, $bedId, $userId
                );
                if ($ins->execute()) {
                    $newId = $conn->insert_id;
                    // auth_account
                    $conn->prepare("INSERT INTO auth_accounts (user_id, password, is_active, must_change_password) VALUES (?, NULL, 0, 1)")->execute() || true;
                    $aa = $conn->prepare("INSERT INTO auth_accounts (user_id, password, is_active, must_change_password) VALUES (?, NULL, 0, 1)");
                    $aa->bind_param('i', $newId); $aa->execute();
                    // Mark bed occupied
                    if ($bedId) {
                        $oc = $conn->prepare("UPDATE beds SET is_occupied = 1 WHERE id = ?");
                        $oc->bind_param('i', $bedId); $oc->execute();
                    }
                    $success++;
                    $preview[] = ['MSSV' => $code, 'Họ tên' => $fullname, 'Email' => $email, 'Phòng' => $roomCode ?: '—', 'Giường' => $bedLabel ?: '—'];
                } else {
                    $errors[] = "Dòng $rowNum: Lỗi DB khi thêm '$code'.";
                }
            }
            fclose($handle);
        }
    }
}
?>

<?php if ($success > 0): ?>
<div class="alert alert-success rounded-3 mb-4">
    <i class="bi bi-check-circle-fill me-2"></i>
    Import thành công <strong><?php echo $success; ?></strong> sinh viên!
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-warning rounded-3 mb-4 small">
    <strong><i class="bi bi-exclamation-triangle me-2"></i>Có <?php echo count($errors); ?> cảnh báo:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errors as $e): ?>
        <li><?php echo $e; ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Upload form -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Upload file CSV</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data" id="csvForm">
                    <div class="mb-4">
                        <label class="form-label fw-semibold small">Chọn file CSV</label>
                        <input type="file" name="csv_file" id="csvFile" class="form-control rounded-3" accept=".csv" required>
                        <div class="form-text">Tối đa 500 sinh viên / lần import.</div>
                    </div>
                    <button type="submit" class="btn btn-success w-100 rounded-3" id="importBtn">
                        <i class="bi bi-cloud-upload me-2"></i>Import
                    </button>
                </form>
            </div>
        </div>

        <!-- CSV template -->
        <div class="card border-0 shadow-sm mt-4" style="border-radius:14px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-info-circle me-2 text-info"></i>Cấu trúc file CSV</h6>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-2">Header bắt buộc (theo thứ tự):</p>
                <code class="d-block bg-light p-3 rounded-3 small">student_code, fullname, gender, date_of_birth, phone_personal, phone_family, hometown, room_code, bed_label</code>
                <p class="text-muted small mt-3 mb-2">Ví dụ dòng dữ liệu:</p>
                <code class="d-block bg-light p-3 rounded-3 small">52100001, Nguyễn Văn A, male, 2003-05-10, 0912000001, 0912000002, Bình Dương, L.0801, G3</code>
                <p class="text-muted small mt-2">
                    <i class="bi bi-asterisk text-danger me-1" style="font-size:9px;"></i> <em>room_code</em> và <em>bed_label</em> là tuỳ chọn.
                </p>
                <a href="/UniDorm/assets/templates/import_students_template.csv" class="btn btn-sm btn-outline-secondary mt-2" download>
                    <i class="bi bi-download me-1"></i>Tải file mẫu
                </a>
            </div>
        </div>
    </div>

    <!-- Preview table -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm" style="border-radius:14px; min-height:200px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-table me-2 text-primary"></i>Kết quả import</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($preview)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-x fs-2 d-block mb-2"></i>
                    <p class="small">Upload file CSV để xem kết quả tại đây.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle" style="font-size:12px;">
                        <thead class="table-light">
                            <tr><?php foreach (array_keys($preview[0]) as $h): ?>
                                <th class="ps-3"><?php echo $h; ?></th>
                            <?php endforeach; ?></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview as $row): ?>
                            <tr>
                                <?php foreach ($row as $val): ?>
                                <td class="ps-3"><?php echo htmlspecialchars($val); ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('csvForm').addEventListener('submit', function() {
    const btn = document.getElementById('importBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang import...';
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
