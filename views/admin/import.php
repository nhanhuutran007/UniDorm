<?php
/**
 * UniDorm – Admin: Import sinh viên từ CSV (import.php)
 * Hỗ trợ import hàng loạt sinh viên từ file CSV kiểu DiemDanh
 *
 * Cấu trúc CSV (từ file DiemDanh):
 *   Cột A (STT): số thứ tự – bỏ qua
 *   Cột B (Phòng): mã phòng, VD: L.0801
 *   Cột C (Mã HV): MSSV, VD: 42400284
 *   Cột D (Họ tên): họ và tên sinh viên (có dấu, UTF-8)
 *   Các cột sau: tuỳ chọn – bỏ qua
 *
 * Lưu ý: File CSV cần lưu ở định dạng UTF-8 (có hoặc không có BOM).
 */
$pageTitle   = 'Import sinh viên từ CSV';
$breadcrumbs = [
    ['label' => 'Quản lý sinh viên', 'url' => '/UniDorm/views/admin/students.php'],
    ['label' => 'Import CSV', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';

$errors  = [];
$success = 0;
$skipped = 0;
$preview = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK || $ext !== 'csv') {
        $errors[] = 'Vui lòng upload file CSV hợp lệ (.csv).';
    } else {
        $handle = fopen($file['tmp_name'], 'rb');
        $allRows = [];
        $rowIdx = 0;
        
        while (($line = fgets($handle)) !== false) {
            // Xử lý BOM UTF-8 (\xEF\xBB\xBF) ở ngay dòng đầu tiên
            if ($rowIdx === 0 && str_starts_with($line, "\xEF\xBB\xBF")) {
                $line = substr($line, 3);
            }
            
            $line = rtrim($line, "\r\n");
            if (trim($line) === '') continue;

            $decoded = $line;
            // Nếu không phải UTF-8 hợp lệ, thử convert tự động từ các bảng mã phổ biến
            if (!mb_check_encoding($line, 'UTF-8')) {
                // Thử nhận diện tự động (auto) để tránh truyền tham số encoding cứng gây lỗi ValueError
                $decoded = @mb_convert_encoding($line, 'UTF-8', 'auto');
            }

            // Parse CSV
            $allRows[] = str_getcsv($decoded, ',', '"');
            $rowIdx++;
        }
        fclose($handle);

        if (empty($allRows)) {
            $errors[] = 'File CSV trống hoặc không đọc được.';
        } else {
            // Xác định header (dòng đầu tiên)
            $header = array_map(function($h) {
                return mb_strtolower(trim(preg_replace('/\s+/', '_', $h)), 'UTF-8');
            }, $allRows[0]);

            // Phát hiện vị trí cột theo tên
            // Hỗ trợ cả format DiemDanh lẫn format chuẩn
            $colMap = [];
            $knownCols = [
                'room_code'    => ['phòng', 'room_code', 'phong'],
                'student_code' => ['mã_hv', 'ma_hv', 'student_code', 'mssv', 'mã_sv'],
                'fullname'     => ['họ_tên', 'ho_ten', 'fullname', 'họ_và_tên', 'ho_va_ten', 'tên', 'ten'],
                'bed_label'    => ['giường', 'số_giường', 'so_giuong', 'bed_label', 'giuong'],
            ];

            foreach ($knownCols as $field => $aliases) {
                foreach ($header as $idx => $h) {
                    if (in_array($h, $aliases)) {
                        $colMap[$field] = $idx;
                        break;
                    }
                }
            }

            // Bắt buộc phải có student_code và fullname
            if (!isset($colMap['student_code'])) {
                $errors[] = "CSV thiếu cột bắt buộc: <strong>Mã HV</strong> hoặc <strong>student_code</strong>";
            }
            if (!isset($colMap['fullname'])) {
                $errors[] = "CSV thiếu cột bắt buộc: <strong>Họ tên</strong> hoặc <strong>fullname</strong>";
            }

            if (empty($errors)) {
                $conn->begin_transaction();
                try {
                    // Lấy tất cả giường trống trước để cache, tránh query lặp
                    $freeBedCache = [];
                    $bRes = $conn->query("
                        SELECT b.id AS bed_id, b.bed_label, b.room_id, r.room_code
                        FROM beds b
                        JOIN rooms r ON b.room_id = r.id
                        WHERE b.is_occupied = 0
                        ORDER BY r.room_code ASC, b.bed_label ASC
                    ");
                    while ($br = $bRes->fetch_assoc()) {
                        $freeBedCache[$br['room_code']][] = $br;
                    }

                    $rowNum = 1;
                    foreach ($allRows as $rowIdx => $data) {
                        if ($rowIdx === 0) continue; // Bỏ qua header
                        $rowNum++;

                        // Bỏ qua dòng thiếu cột
                        $maxCol = 0;
                        if (!empty($colMap)) $maxCol = max(array_values($colMap));
                        
                        // Thêm cột trống nếu thiếu so với mapping hoặc thực tế
                        while (count($data) <= $maxCol) {
                            $data[] = '';
                        }

                        $code     = strtoupper(trim($data[$colMap['student_code']] ?? ''));
                        $fullname = trim($data[$colMap['fullname']] ?? '');

                        // Bỏ qua dòng trống
                        if (!$code && !$fullname) continue;

                        if (!$code) {
                            $errors[] = "Dòng $rowNum: Thiếu MSSV → bỏ qua.";
                            $skipped++;
                            continue;
                        }
                        if (!$fullname) {
                            $errors[] = "Dòng $rowNum: Thiếu họ tên ($code) → bỏ qua.";
                            $skipped++;
                            continue;
                        }
                        if (!preg_match('/^[A-Z0-9]{5,15}$/', $code)) {
                            $errors[] = "Dòng $rowNum: MSSV '$code' không hợp lệ → bỏ qua.";
                            $skipped++;
                            continue;
                        }

                        $email = $code . '@student.tdtu.edu.vn';

                        // Kiểm tra MSSV trùng
                        $chk = $conn->prepare("SELECT user_id FROM users WHERE student_code = ?");
                        $chk->bind_param('s', $code);
                        $chk->execute();
                        if ($chk->get_result()->num_rows > 0) {
                            $errors[] = "Dòng $rowNum: MSSV '$code' đã tồn tại → bỏ qua.";
                            $skipped++;
                            continue;
                        }

                        // Xác định phòng từ cột Phòng
                        $roomCode  = isset($colMap['room_code']) ? trim($data[$colMap['room_code']] ?? '') : '';
                        $bedLabel  = isset($colMap['bed_label']) ? trim($data[$colMap['bed_label']] ?? '') : '';
                        $bedId     = null;
                        $assignedBed = '—';

                        if ($roomCode && isset($freeBedCache[$roomCode]) && !empty($freeBedCache[$roomCode])) {
                            if ($bedLabel) {
                                // Tìm đúng giường theo nhãn
                                foreach ($freeBedCache[$roomCode] as $idx => $bed) {
                                    if ($bed['bed_label'] === $bedLabel) {
                                        $bedId = $bed['bed_id'];
                                        $assignedBed = $bedLabel;
                                        // Xoá khỏi cache để không gán 2 lần
                                        unset($freeBedCache[$roomCode][$idx]);
                                        $freeBedCache[$roomCode] = array_values($freeBedCache[$roomCode]);
                                        break;
                                    }
                                }
                                if (!$bedId) {
                                    $errors[] = "Dòng $rowNum: Giường '$bedLabel' trong phòng '$roomCode' không còn trống → tự gán giường trống khác.";
                                }
                            }

                            // Nếu chưa tìm được giường thì lấy giường trống đầu tiên
                            if (!$bedId && !empty($freeBedCache[$roomCode])) {
                                $bed = array_shift($freeBedCache[$roomCode]);
                                $bedId = $bed['bed_id'];
                                $assignedBed = $bed['bed_label'];
                            }
                        } elseif ($roomCode) {
                            $errors[] = "Dòng $rowNum: Phòng '$roomCode' không có giường trống → SV không được gán phòng.";
                        }

                        // Lấy thông tin tùy chọn
                        $gender      = isset($colMap['gender'])        ? trim($data[$colMap['gender']] ?? '')         : null;
                        $dob         = isset($colMap['date_of_birth'])  ? trim($data[$colMap['date_of_birth']] ?? '')  : null;
                        $phonePers   = isset($colMap['phone_personal']) ? trim($data[$colMap['phone_personal']] ?? '') : '';
                        $phoneFamily = isset($colMap['phone_family'])   ? trim($data[$colMap['phone_family']] ?? '')   : '';
                        $hometown    = isset($colMap['hometown'])       ? trim($data[$colMap['hometown']] ?? '')       : '';
                        $isLeader    = 0;
                        if (isset($colMap['is_room_leader'])) {
                            $leaderVal = mb_strtolower(trim($data[$colMap['is_room_leader']] ?? ''), 'UTF-8');
                            $isLeader = ($leaderVal === 'tp' || $leaderVal === '1' || $leaderVal === 'x') ? 1 : 0;
                        }

                        // Chuẩn hóa gender
                        $genderMap = ['nam' => 'male', 'nữ' => 'female', 'nu' => 'female', 'khác' => 'other'];
                        if ($gender) {
                            $genderKey = mb_strtolower($gender, 'UTF-8');
                            $gender = $genderMap[$genderKey] ?? ($gender ?: null);
                        } else {
                            $gender = null;
                        }

                        // Chuẩn hóa ngày sinh từ dd/mm/yyyy → yyyy-mm-dd
                        if ($dob && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dob, $m)) {
                            $dob = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                        } elseif (!$dob || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                            $dob = null;
                        }

                        // Insert sinh viên
                        $ins = $conn->prepare("
                            INSERT INTO users
                                (student_code, username, fullname, email, gender, date_of_birth,
                                 phone_personal, phone_family, hometown, role, status, bed_id, is_room_leader, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'student', 'pending', ?, ?, ?)
                        ");
                        $ins->bind_param('sssssssssiii',
                            $code, $code, $fullname, $email, $gender, $dob,
                            $phonePers, $phoneFamily, $hometown, $bedId, $isLeader, $userId
                        );

                        if ($ins->execute()) {
                            $newId = $conn->insert_id;

                            // Tạo auth_account
                            $aa = $conn->prepare("INSERT INTO auth_accounts (user_id, password, is_active, must_change_password) VALUES (?, NULL, 0, 1)");
                            $aa->bind_param('i', $newId);
                            $aa->execute();

                            // Đánh dấu giường đã có người
                            if ($bedId) {
                                $oc = $conn->prepare("UPDATE beds SET is_occupied = 1 WHERE id = ?");
                                $oc->bind_param('i', $bedId);
                                $oc->execute();
                            }

                            $success++;
                            $preview[] = [
                                'MSSV'   => $code,
                                'Họ tên' => $fullname,
                                'Email'  => $email,
                                'Phòng'  => $roomCode ?: '—',
                                'Giường' => $assignedBed,
                                'Trạng thái' => 'Chờ kích hoạt',
                            ];
                        } else {
                            $errors[] = "Dòng $rowNum: Lỗi DB khi thêm '$code'.";
                            $skipped++;
                        }
                    }

                    $conn->commit();

                } catch (Exception $e) {
                    $conn->rollback();
                    $errors[] = 'Lỗi hệ thống, đã rollback: ' . $e->getMessage();
                    $success = 0;
                    $preview = [];
                }
            }
        }
    }
}
?>

<?php if ($success > 0): ?>
<div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>
    Import thành công <strong><?php echo $success; ?></strong> sinh viên!
    <?php if ($skipped > 0): ?> <span class="text-muted small">(Bỏ qua <?php echo $skipped; ?> dòng lỗi)</span><?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-warning alert-dismissible fade show rounded-3 mb-4 small" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Có <?php echo count($errors); ?> cảnh báo:</strong>
    <ul class="mb-0 mt-2 ps-3">
        <?php foreach (array_slice($errors, 0, 15) as $e): ?>
        <li><?php echo $e; ?></li>
        <?php endforeach; ?>
        <?php if (count($errors) > 15): ?>
        <li>... và <?php echo count($errors) - 15; ?> cảnh báo khác.</li>
        <?php endif; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Upload form -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i>Upload file CSV</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data" id="csvForm">
                    <div class="mb-4">
                        <label class="form-label fw-semibold small">Chọn file CSV</label>
                        <input type="file" name="csv_file" id="csvFile" class="form-control rounded-3" accept=".csv" required>
                        <div class="form-text">Tối đa 1000 sinh viên / lần import. Định dạng UTF-8.</div>
                    </div>
                    <button type="submit" class="btn btn-success w-100 rounded-3 fw-semibold" id="importBtn">
                        <i class="bi bi-cloud-upload me-2"></i>Tiến hành Import
                    </button>
                </form>
            </div>
        </div>

        <!-- Hướng dẫn cấu trúc CSV -->
        <div class="card border-0 shadow-sm mt-4" style="border-radius:14px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-info-circle me-2 text-info"></i>Cấu trúc file CSV</h6>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-2">Hệ thống nhận dạng file theo <strong>dòng header</strong>. Hỗ trợ định dạng:</p>

                <p class="small fw-semibold text-dark mb-1">📋 Định dạng DiemDanh:</p>
                <div class="bg-light p-2 rounded-3 mb-3" style="font-size:11px; font-family:monospace; overflow-x:auto; white-space:nowrap;">
                    STT, Phòng, Mã HV, Họ tên, Số giường, Ngày, Trạng thái, Lý do
                </div>

                <div class="alert alert-light border small p-2 mb-2">
                    <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                    <strong>Cột bắt buộc:</strong> Mã HV (MSSV), Họ tên<br>
                    <strong>Cột tuỳ chọn:</strong> Phòng, Số giường
                </div>
                <div class="alert alert-light border small p-2 mb-3">
                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                    Đã hỗ trợ file CSV lưu ở định dạng <strong>UTF-8</strong>. Tự động gán giường nếu chỉ có phòng.
                </div>

                <a href="/UniDorm/assets/templates/DiemDanh_mau.csv" class="btn btn-sm btn-outline-secondary w-100 rounded-3" download>
                    <i class="bi bi-download me-1"></i>Tải file CSV mẫu
                </a>
            </div>
        </div>
    </div>

    <!-- Kết quả -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100" style="border-radius:14px; min-height:300px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-table me-2 text-primary"></i>Kết quả Import</h6>
                <?php if ($success > 0): ?>
                <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill small fw-semibold">
                    ✓ <?php echo $success; ?> sinh viên được thêm
                </span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($preview)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-spreadsheet fs-2 d-block mb-3 opacity-40"></i>
                    <p class="small mb-0">Upload file CSV để xem kết quả tại đây.</p>
                    <p class="small text-muted opacity-75">Hệ thống sẽ tự động nhận dạng cấu trúc file.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle" style="font-size:12px;">
                        <thead class="table-light">
                            <tr>
                                <?php foreach (array_keys($preview[0]) as $h): ?>
                                <th class="ps-3 py-2 small fw-semibold"><?php echo $h; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview as $row): ?>
                            <tr>
                                <?php foreach ($row as $key => $val): ?>
                                <td class="ps-3 py-2">
                                    <?php if ($key === 'Trạng thái'): ?>
                                    <span class="badge bg-warning-subtle text-warning px-2 py-1 rounded-pill"><?php echo htmlspecialchars($val); ?></span>
                                    <?php elseif ($key === 'Phòng' || $key === 'Giường'): ?>
                                    <code class="small"><?php echo htmlspecialchars($val); ?></code>
                                    <?php else: ?>
                                    <?php echo htmlspecialchars($val); ?>
                                    <?php endif; ?>
                                </td>
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
    const fileInput = document.getElementById('csvFile');
    if (!fileInput.files.length) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';
});

// Preview tên file khi chọn
document.getElementById('csvFile').addEventListener('change', function() {
    const name = this.files[0]?.name || '';
    const hint = this.closest('.mb-4').querySelector('.form-text');
    if (name) hint.innerHTML = '<i class="bi bi-file-check text-success me-1"></i>Đã chọn: <strong>' + name + '</strong>';
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
