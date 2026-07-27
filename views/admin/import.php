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
    ['label' => 'Quản lý sinh viên', 'url' => BASE_URL . '/students'],
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
                // Loại bỏ BOM, khoảng trắng đầu cuối
                $h = trim($h);
                // Loại bỏ CHÍNH XÁC ký tự điều khiển (0x00-0x1F, 0x7F), GIỮ NGUYÊN ký tự Unicode Vietnamese
                $h = preg_replace('/[\x00-\x1F\x7F]/', '', $h);
                // Chuyển về lowercase (quan trọng: dùng mb_strtolower để xử lý Unicode)
                $h = mb_strtolower($h, 'UTF-8');
                // Thay khoảng trắng bằng dấu _
                $h = preg_replace('/\s+/', '_', $h);
                return $h;
            }, $allRows[0]);

            // Debug: Log header để kiểm tra
            error_log("CSV Headers detected: " . json_encode($header, JSON_UNESCAPED_UNICODE));

            // Phát hiện vị trí cột theo tên (hỗ trợ nhiều alias)
            $colMap = [];
            $knownCols = [
                'room_code'      => ['phòng', 'room_code', 'phong', 'mã_phòng', 'ma_phong'],
                'student_code'   => ['mã_hv', 'ma_hv', 'student_code', 'mssv', 'mã_sv', 'ma_sv'],
                'fullname'       => ['họ_tên', 'ho_ten', 'fullname', 'họ_và_tên', 'ho_va_ten', 'tên', 'ten', 'họ_tên_sv', 'ho_ten_sv'],
                'bed_label'      => ['giường', 'số_giường', 'so_giuong', 'bed_label', 'giuong', 'số_gường', 'so_guong'],
                'gender'         => ['giới_tính', 'gioi_tinh', 'gender', 'giới_tinh', 'gioi_tinh', 'gt'],
                'date_of_birth'  => ['ngày_sinh', 'ngay_sinh', 'date_of_birth', 'ngày_sinh', 'ns', 'sinh_nhật', 'sinh_nhat'],
                'phone_personal' => ['sđt_cá_nhân', 'sdt_ca_nhan', 'phone_personal', 'sđt', 'sdt', 'điện_thoại', 'dien_thoai', 'dt_cá_nhân', 'dt_ca_nhan'],
                'phone_family'   => ['sđt_gia_đình', 'sdt_gia_dinh', 'phone_family', 'dt_gia_đình', 'dt_gia_dinh'],
                'hometown'       => ['hộ_khẩu', 'ho_khau', 'hometown', 'quê_quán', 'que_quan', 'địa_chỉ', 'dia_chi', 'hktt', 'hk', 'hộ_khẩu_thường_trú', 'ho_khau_thuong_tru', 'nơi_sinh', 'noi_sinh', 'quê_hương', 'que_huong'],
                'is_room_leader' => ['trưởng_phòng', 'truong_phong', 'is_room_leader', 'tp'],
            ];

            foreach ($knownCols as $field => $aliases) {
                foreach ($header as $idx => $h) {
                    if (in_array($h, $aliases)) {
                        $colMap[$field] = $idx;
                        error_log("Mapped field '$field' to column $idx: '$h'");
                        break;
                    }
                }
            }

            // Log các cột đã map được
            error_log("Column mapping result: " . json_encode($colMap, JSON_UNESCAPED_UNICODE));

            // Bắt buộc phải có student_code và fullname
            if (!isset($colMap['student_code'])) {
                $errors[] = "CSV thiếu cột bắt buộc: <strong>Mã HV</strong> hoặc <strong>student_code</strong>";
            }
            if (!isset($colMap['fullname'])) {
                $errors[] = "CSV thiếu cột bắt buộc: <strong>Họ tên</strong> hoặc <strong>fullname</strong>";
            }

            if (empty($errors)) {
                // Hiển thị thông tin column mapping để debug
                $mappingInfo = [];
                foreach ($colMap as $field => $idx) {
                    $mappingInfo[] = "$field => Cột " . ($idx + 1) . " (" . $allRows[0][$idx] . ")";
                }
                
                $conn->begin_transaction();
                try {
                    // Lấy tất cả giường thực sự trống (không có SV 'active' hoặc 'pending' đang ở)
                    $freeBedCache = [];
                    $bRes = $conn->query("
                        SELECT b.id AS bed_id, b.bed_label, b.room_id, r.room_code
                        FROM beds b
                        JOIN rooms r ON b.room_id = r.id
                        WHERE b.id NOT IN (
                            SELECT bed_id FROM users WHERE bed_id IS NOT NULL AND status IN ('active', 'pending')
                        )
                        ORDER BY r.room_code ASC, b.bed_label ASC
                    ");
                    while ($br = $bRes->fetch_assoc()) {
                        $key = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $br['room_code']));
                        $freeBedCache[$key][] = $br;
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
                        
                        // Debug first row
                        if ($rowNum === 2) {
                            error_log("First data row: " . json_encode($data, JSON_UNESCAPED_UNICODE));
                            error_log("Extracted - Code: '$code', Fullname: '$fullname'");
                            if (isset($colMap['room_code'])) {
                                error_log("Room code raw: '" . ($data[$colMap['room_code']] ?? 'N/A') . "'");
                            }
                            if (isset($colMap['phone_personal'])) {
                                error_log("Phone raw: '" . ($data[$colMap['phone_personal']] ?? 'N/A') . "'");
                            }
                        }

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

                        // Kiểm tra MSSV đã tồn tại chưa
                        $chk = $conn->prepare("SELECT user_id, bed_id, fullname, email, gender, date_of_birth, phone_personal, phone_family, hometown, is_room_leader FROM users WHERE student_code = ?");
                        $chk->bind_param('s', $code);
                        $chk->execute();
                        $existingUser = $chk->get_result()->fetch_assoc();

                        // Xác định phòng từ cột Phòng (Chuẩn hóa thành Chữ Hoa)
                        $roomCodeRaw  = isset($colMap['room_code']) ? trim($data[$colMap['room_code']] ?? '') : '';
                        $roomCode     = strtoupper($roomCodeRaw);
                        $roomCodeKey  = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $roomCodeRaw));
                        $bedLabelRaw  = isset($colMap['bed_label']) ? trim($data[$colMap['bed_label']] ?? '') : '';
                        $bedLabelClean = preg_replace('/[^A-Z0-9]/i', '', preg_replace('/giường|giuong/i', '', $bedLabelRaw));
                        if ($bedLabelClean !== '' && is_numeric($bedLabelClean)) {
                            $bedLabel = 'G' . $bedLabelClean;
                        } else if ($bedLabelClean !== '' && preg_match('/^G\d+$/i', $bedLabelClean)) {
                            $bedLabel = strtoupper($bedLabelClean);
                        } else {
                            $bedLabel = $bedLabelClean !== '' ? strtoupper($bedLabelClean) : '';
                        }
                        $bedId        = null;
                        $assignedBed  = '—';

                        if ($roomCodeKey && isset($freeBedCache[$roomCodeKey]) && !empty($freeBedCache[$roomCodeKey])) {
                            if ($bedLabel) {
                                // Tìm đúng giường theo nhãn trong cache giường trống
                                foreach ($freeBedCache[$roomCodeKey] as $idx => $bed) {
                                    if ($bed['bed_label'] === $bedLabel) {
                                        $bedId = $bed['bed_id'];
                                        $assignedBed = $bedLabel;
                                        unset($freeBedCache[$roomCodeKey][$idx]);
                                        $freeBedCache[$roomCodeKey] = array_values($freeBedCache[$roomCodeKey]);
                                        break;
                                    }
                                }
                                if (!$bedId) {
                                    $errors[] = "Dòng $rowNum: Giường '$bedLabel' tại phòng '$roomCode' không trống → tự gán giường khác.";
                                    // Fallback: tự gán giường trống khi giường chỉ định đã có người
                                    if (!empty($freeBedCache[$roomCodeKey])) {
                                        $bed = array_shift($freeBedCache[$roomCodeKey]);
                                        $bedId = $bed['bed_id'];
                                        $assignedBed = $bed['bed_label'];
                                    }
                                }
                            } else if (!$existingUser) {
                                // Chỉ tự gán giường cho SINH VIÊN MỚI khi CSV không ghi số giường
                                $bed = array_shift($freeBedCache[$roomCodeKey]);
                                $bedId = $bed['bed_id'];
                                $assignedBed = $bed['bed_label'];
                            }
                            // Sinh viên CŨ mà CSV không ghi số giường → giữ nguyên giường cũ (xử lý bên dưới)
                        }

                        if ($roomCode && !$bedId && !$existingUser) {
                            if (!isset($freeBedCache[$roomCodeKey])) {
                                $errors[] = "Dòng $rowNum: Phòng '$roomCode' không tồn tại trong hệ thống → sinh viên '$code' chưa được gán giường.";
                            } else {
                                $errors[] = "Dòng $rowNum: Phòng '$roomCode' đã hết giường trống → sinh viên '$code' chưa được gán giường.";
                            }
                        }

                        // Lấy thông tin tùy chọn từ CSV (null nếu cột không tồn tại, '' nếu cột có nhưng ô trống)
                        $genderVal    = isset($colMap['gender'])        ? trim($data[$colMap['gender']] ?? '')         : null;
                        $dobVal       = isset($colMap['date_of_birth'])  ? trim($data[$colMap['date_of_birth']] ?? '')  : null;
                        $phonePers    = isset($colMap['phone_personal']) ? trim($data[$colMap['phone_personal']] ?? '') : null;
                        $phoneFamily  = isset($colMap['phone_family'])   ? trim($data[$colMap['phone_family']] ?? '')   : null;
                        
                        if ($phonePers !== null && $phonePers !== '') {
                            $phonePers = ltrim($phonePers, "'");
                            $phonePers = preg_replace('/[^0-9]/', '', $phonePers);
                            if (strlen($phonePers) === 9 && !str_starts_with($phonePers, '0')) {
                                $phonePers = '0' . $phonePers;
                            }
                        }
                        if ($phoneFamily !== null && $phoneFamily !== '') {
                            $phoneFamily = ltrim($phoneFamily, "'");
                            $phoneFamily = preg_replace('/[^0-9]/', '', $phoneFamily);
                            if (strlen($phoneFamily) === 9 && !str_starts_with($phoneFamily, '0')) {
                                $phoneFamily = '0' . $phoneFamily;
                            }
                        }

                        $hometown     = isset($colMap['hometown'])       ? trim($data[$colMap['hometown']] ?? '')       : null;
                        $isLeader     = 0;
                        if (isset($colMap['is_room_leader'])) {
                            $leaderVal = mb_strtolower(trim($data[$colMap['is_room_leader']] ?? ''), 'UTF-8');
                            $isLeader = ($leaderVal === 'tp' || $leaderVal === '1' || $leaderVal === 'x') ? 1 : 0;
                        }

                        // Chuẩn hóa gender (null nếu ô trống)
                        $genderMap = ['nam' => 'male', 'nữ' => 'female', 'nu' => 'female', 'khác' => 'other'];
                        $gender = ($genderVal !== null && $genderVal !== '')
                            ? ($genderMap[mb_strtolower($genderVal, 'UTF-8')] ?? $genderVal)
                            : null;

                        // Chuẩn hóa ngày sinh (null nếu ô trống)
                        $dob = null;
                        if ($dobVal !== null && $dobVal !== '') {
                            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dobVal, $m)) {
                                $dob = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobVal)) {
                                $dob = $dobVal;
                            }
                        }

                        if ($existingUser) {
                            // TRƯỜNG HỢP 1: CẬP NHẬT SINH VIÊN ĐÃ TỒN TẠI
                            $userIdToUpd = $existingUser['user_id'];
                            $oldBedId    = (int)$existingUser['bed_id'];

                            // Xử lý chuyển phòng nếu bedId mới khác bedId cũ
                            if ($bedId && $bedId !== $oldBedId) {
                                // Giải phóng giường cũ
                                if ($oldBedId) {
                                    $conn->query("UPDATE beds SET is_occupied = 0 WHERE id = $oldBedId");
                                }
                                // Chiếm giường mới
                                $conn->query("UPDATE beds SET is_occupied = 1 WHERE id = $bedId");
                            } else {
                                // Nếu không có giường mới trong CSV, giữ giường cũ
                                $bedId = $bedId ?: ($oldBedId ?: null);
                                if ($bedId && $assignedBed === '—') {
                                    // Lấy lại label giường cũ để hiển thị
                                    $bLabel = $conn->query("SELECT bed_label FROM beds WHERE id = $bedId")->fetch_assoc();
                                    $assignedBed = $bLabel['bed_label'] ?? '—';
                                }
                            }

                            // Cập nhật đầy đủ nếu CSV có cột VÀ ô đó không trống
                            // Nếu cột có trong CSV nhưng ô trống → giữ nguyên giá trị cũ trong DB
                            $updGender  = (isset($colMap['gender'])        && $gender      !== null && $gender      !== '') ? $gender      : $existingUser['gender'];
                            $updDob     = (isset($colMap['date_of_birth']) && $dob         !== null)                        ? $dob         : $existingUser['date_of_birth'];
                            $updPhone   = (isset($colMap['phone_personal'])&& $phonePers   !== null && $phonePers   !== '') ? $phonePers   : $existingUser['phone_personal'];
                            $updPFamily = (isset($colMap['phone_family'])  && $phoneFamily !== null && $phoneFamily !== '') ? $phoneFamily : $existingUser['phone_family'];
                            $updHome    = (isset($colMap['hometown'])      && $hometown    !== null && $hometown    !== '') ? $hometown    : $existingUser['hometown'];
                            $updLeader  = isset($colMap['is_room_leader'])                                                  ? $isLeader    : (int)$existingUser['is_room_leader'];

                            $upd = $conn->prepare("UPDATE users SET fullname = ?, bed_id = ?, gender = ?, date_of_birth = ?, phone_personal = ?, phone_family = ?, hometown = ?, is_room_leader = ? WHERE user_id = ?");
                            $upd->bind_param('sissssiii', $fullname, $bedId, $updGender, $updDob, $updPhone, $updPFamily, $updHome, $updLeader, $userIdToUpd);

                            if ($upd->execute()) {
                                $success++;
                                $preview[] = [
                                    'MSSV'   => $code,
                                    'Họ tên' => $fullname,
                                    'Email'  => $existingUser['email'],
                                    'Phòng'  => $roomCode ?: '(Cũ)',
                                    'Giường' => $assignedBed,
                                    'Trạng thái' => 'Cập nhật',
                                ];
                            } else {
                                $errors[] = "Dòng $rowNum: Lỗi khi cập nhật '$code'.";
                                $skipped++;
                            }

                        } else {
                            // TRƯỜNG HỢP 2: THÊM MỚI SINH VIÊN
                            // Với sinh viên mới, ta dùng các thông tin có trong CSV (nếu có)
                            $ins = $conn->prepare("
                                INSERT INTO users
                                    (student_code, username, fullname, email, gender, date_of_birth,
                                     phone_personal, phone_family, hometown, role, status, bed_id, is_room_leader, created_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'student', 'pending', ?, ?, ?)
                            ");
                            $currAdminId = $_SESSION['user_id'] ?? null;
                            $ins->bind_param('sssssssssiii',
                                $code, $code, $fullname, $email, $gender, $dob,
                                $phonePers, $phoneFamily, $hometown, $bedId, $isLeader, $currAdminId
                            );

                            if ($ins->execute()) {
                                $newId = $conn->insert_id;
                                // Tạo auth_account
                                $aa = $conn->prepare("INSERT INTO auth_accounts (user_id, password, is_active, must_change_password) VALUES (?, NULL, 0, 1)");
                                $aa->bind_param('i', $newId);
                                $aa->execute();

                                // Chiếm giường
                                if ($bedId) {
                                    $conn->query("UPDATE beds SET is_occupied = 1 WHERE id = $bedId");
                                }

                                $success++;
                                $preview[] = [
                                    'MSSV'   => $code,
                                    'Họ tên' => $fullname,
                                    'Email'  => $email,
                                    'Phòng'  => $roomCode ?: '—',
                                    'Giường' => $assignedBed,
                                    'Trạng thái' => 'Thêm mới',
                                ];
                            } else {
                                $errors[] = "Dòng $rowNum: Lỗi khi thêm mới '$code'.";
                                $skipped++;
                            }
                        }
                    }

                    $conn->commit();

                    // CẬP NHẬT TRẠNG THÁI PHÒNG SAU KHI IMPORT
                    // Logic: So sánh số giường có người ở vs tổng số giường trong phòng
                    
                    // 1. Đánh dấu phòng là 'full' nếu TẤT CẢ giường đều có người ở
                    $roomsToFull = $conn->query("
                        SELECT r.id, r.room_code,
                               COUNT(b.id) AS total_beds,
                               COUNT(u.bed_id) AS occupied_beds
                        FROM rooms r
                        JOIN beds b ON b.room_id = r.id
                        LEFT JOIN users u ON u.bed_id = b.id AND u.status IN ('active', 'pending')
                        WHERE r.status != 'maintenance'
                        GROUP BY r.id
                        HAVING total_beds = occupied_beds AND total_beds > 0
                    ");
                    while ($rm = $roomsToFull->fetch_assoc()) {
                        $rid = $rm['id'];
                        $conn->query("UPDATE rooms SET status = 'full' WHERE id = $rid");
                        error_log("Room {$rm['room_code']} marked as FULL ({$rm['occupied_beds']}/{$rm['total_beds']} beds)");
                    }
                    
                    // 2. Đánh dấu phòng là 'available' nếu CÒN giường trống
                    $roomsToAvailable = $conn->query("
                        SELECT r.id, r.room_code,
                               COUNT(b.id) AS total_beds,
                               COUNT(u.bed_id) AS occupied_beds
                        FROM rooms r
                        JOIN beds b ON b.room_id = r.id
                        LEFT JOIN users u ON u.bed_id = b.id AND u.status IN ('active', 'pending')
                        WHERE r.status != 'maintenance'
                        GROUP BY r.id
                        HAVING total_beds > occupied_beds
                    ");
                    while ($rm = $roomsToAvailable->fetch_assoc()) {
                        $rid = $rm['id'];
                        $conn->query("UPDATE rooms SET status = 'available' WHERE id = $rid");
                        error_log("Room {$rm['room_code']} marked as AVAILABLE ({$rm['occupied_beds']}/{$rm['total_beds']} beds)");
                    }

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
    
    <?php if (!empty($mappingInfo)): ?>
    <details class="mt-2">
        <summary class="small" style="cursor:pointer;">🔍 Xem mapping cột CSV</summary>
        <div class="small mt-2 bg-light p-2 rounded">
            <?php foreach ($mappingInfo as $info): ?>
            <div>• <?php echo $info; ?></div>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>
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
                <p class="text-muted small mb-2">Hệ thống nhận dạng file theo <strong>dòng header</strong>. Hỗ trợ 2 định dạng:</p>

                <p class="small fw-semibold text-dark mb-1">📋 Định dạng Thông tin (đầy đủ):</p>
                <div class="bg-light p-2 rounded-3 mb-2" style="font-size:10px; font-family:monospace; overflow-x:auto; white-space:nowrap;">
                    STT, Phòng, Mã HV, Họ tên, Số giường, Giới tính, Ngày sinh, SĐT cá nhân, SĐT gia đình, Hộ khẩu, Trưởng phòng
                </div>

                <p class="small fw-semibold text-dark mb-1 mt-3">📋 Định dạng DiemDanh (rút gọn):</p>
                <div class="bg-light p-2 rounded-3 mb-2" style="font-size:10px; font-family:monospace; overflow-x:auto; white-space:nowrap;">
                    STT, Phòng, Mã HV, Họ tên, Số giường, Ngày, Trạng thái, Lý do
                </div>

                <div class="alert alert-light border small p-2 mb-3 mt-3">
                    <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                    <strong>Cột bắt buộc:</strong> Mã HV, Họ tên<br>
                    <strong>Cột tuỳ chọn:</strong> Phòng, Số giường, Giới tính, Ngày sinh, SĐT cá nhân, SĐT gia đình, Hộ khẩu, Trưởng phòng<br>
                    <strong>Trưởng phòng:</strong> Điền <code>TP</code> hoặc <code>X</code> để đánh dấu
                </div>

                <div class="d-grid gap-2">
                    <a href="<?= BASE_URL ?>/assets/templates/ThongTin_mau.csv" class="btn btn-sm btn-outline-primary rounded-3" download>
                        <i class="bi bi-download me-1"></i>Tải mẫu Thông tin (đầy đủ)
                    </a>
                    <a href="<?= BASE_URL ?>/assets/templates/DiemDanh_mau.csv" class="btn btn-sm btn-outline-secondary rounded-3" download>
                        <i class="bi bi-download me-1"></i>Tải mẫu DiemDanh (rút gọn)
                    </a>
                </div>
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
