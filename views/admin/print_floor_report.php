<?php
/**
 * UniDorm – In Mẫu Báo Cáo Lầu (Trang trước & Trang sau)
 * Path: views/admin/print_floor_report.php
 * Cấu hình: Cả 2 trang đều là A4 Đứng (Portrait), Trang 2 xoay dọc nội dung (lật 180 độ)
 * Giống 100% mẫu ảnh:
 * - Tiêu ngữ và Tên trường khoảng cách thông thoáng, font 11.5pt chuẩn hành chính
 * - Cột đầu tiên bảng Trang 1 là "TT" chuẩn mẫu ảnh
 * - In đậm Tên trường, Tiêu ngữ, TT, Số phòng, chữ ký
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/db.php';

// Kiểm tra quyền truy cập admin
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Bạn không có quyền truy cập trang này.');
}

// Lấy tham số lầu
$floorId = (int)($_GET['floor_id'] ?? 0);
$floorNumberInput = isset($_GET['floor_number']) ? (int)$_GET['floor_number'] : null;

$floorData = null;

if ($floorId > 0) {
    $stmt = $conn->prepare("
        SELECT f.id, f.floor_number, b.name as building_name 
        FROM floors f 
        JOIN buildings b ON f.building_id = b.id 
        WHERE f.id = ?
    ");
    $stmt->bind_param('i', $floorId);
    $stmt->execute();
    $floorData = $stmt->get_result()->fetch_assoc();
} elseif ($floorNumberInput !== null && $floorNumberInput > 0) {
    $stmt = $conn->prepare("
        SELECT f.id, f.floor_number, b.name as building_name 
        FROM floors f 
        JOIN buildings b ON f.building_id = b.id 
        WHERE f.floor_number = ? 
        LIMIT 1
    ");
    $stmt->bind_param('i', $floorNumberInput);
    $stmt->execute();
    $floorData = $stmt->get_result()->fetch_assoc();
}

// Mặc định lầu đầu tiên nếu không tìm thấy
if (!$floorData) {
    $res = $conn->query("
        SELECT f.id, f.floor_number, b.name as building_name 
        FROM floors f 
        JOIN buildings b ON f.building_id = b.id 
        ORDER BY f.floor_number ASC LIMIT 1
    ");
    if ($res && $res->num_rows > 0) {
        $floorData = $res->fetch_assoc();
    } else {
        $floorData = ['id' => 8, 'floor_number' => 8, 'building_name' => 'Tòa K'];
    }
}

$floorNum = (int)$floorData['floor_number'];
$buildingName = $floorData['building_name'] ?? 'Tòa K';

// Xác định ký hiệu tòa nhà (VD: "Tòa K" -> "K", "Tòa L" -> "L")
if (preg_match('/Tòa\s*([A-Za-z0-9]+)/ui', $buildingName, $matches)) {
    $bldPrefix = strtoupper($matches[1]);
} else {
    $bldPrefix = 'K';
}

$floorCodeStr = $bldPrefix . '.' . sprintf('%02d', $floorNum);

// Danh sách lầu để chọn nhanh
$allFloors = $conn->query("
    SELECT f.id, f.floor_number, b.name as building_name 
    FROM floors f 
    JOIN buildings b ON f.building_id = b.id 
    ORDER BY f.floor_number ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo lầu <?php echo $floorCodeStr; ?> - UniDorm</title>
    <!-- Bootstrap 5 CSS cho thanh điều hướng màn hình -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        /* Typography chuẩn văn bản hành chính Việt Nam (Times New Roman) */
        body {
            background-color: #525659;
            font-family: "Times New Roman", Times, serif;
            color: #000;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .font-bold {
            font-weight: bold !important;
        }

        /* Control Bar khi xem trên màn hình */
        .control-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: #1e293b;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .document-wrapper {
            margin-top: 80px;
            margin-bottom: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
        }

        /* Mô phỏng trang giấy A4 trên màn hình */
        .paper-page {
            background: #fff;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
            box-sizing: border-box;
            position: relative;
            page-break-after: always;
            page-break-inside: avoid;
        }

        /* Cả Trang 1 và Trang 2 đều là A4 Đứng (210mm x 297mm) */
        .page-portrait {
            width: 210mm;
            height: 297mm;
            padding: 18mm 18mm 18mm 18mm;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
            background: #fff;
        }

        /* ==================== TRANG 1 CSS ==================== */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
            width: 100%;
        }

        /* Tên trường & Tiêu ngữ: Font 11.5pt chuẩn, khoảng cách 2 bên thông thoáng không bị đè sát nhau */
        .header-left {
            width: 44%;
            text-align: center;
            font-size: 11.5pt;
            font-weight: bold;
            line-height: 1.35;
        }
        .header-right {
            width: 52%;
            text-align: center;
            font-size: 11.5pt;
            font-weight: bold;
            line-height: 1.35;
        }
        .no-wrap {
            white-space: nowrap !important;
            display: block;
        }

        .header-underline-short {
            width: 85px;
            height: 1.2px;
            background-color: #000;
            margin: 4px auto 0 auto;
        }
        .header-underline-long {
            width: 160px;
            height: 1.2px;
            background-color: #000;
            margin: 4px auto 0 auto;
        }

        .date-line {
            text-align: right;
            font-size: 12pt;
            font-style: italic;
            margin-top: 6px;
            margin-bottom: 18px;
        }

        .title-section {
            text-align: center;
            margin-bottom: 18px;
        }
        .title-main {
            font-size: 15.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .title-sub {
            font-size: 12pt;
            font-style: italic;
        }

        /* Bảng Trang 1 */
        .table-p1 {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 12pt;
        }
        .table-p1 th, .table-p1 td {
            border: 1.5px solid #000;
            padding: 8px 10px;
            vertical-align: top;
        }
        .table-p1 th {
            background-color: #c0c0c0 !important;
            font-weight: bold;
            text-align: center;
            height: 30px;
        }
        /* Cột TT (STT) Trang 1 */
        .table-p1 td.col-tt {
            text-align: center;
            width: 45px;
            font-weight: bold;
        }
        .table-p1 td.col-content {
            width: 63%;
        }
        .table-p1 td.col-notes {
            width: 32%;
        }
        .section-heading {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 4px;
        }
        .row-space {
            min-height: 90px;
        }

        /* Chữ ký Trang 1 (In đậm) */
        .signatures-p1 {
            display: flex;
            justify-content: space-between;
            text-align: center;
            font-size: 12.5pt;
            font-weight: bold;
            margin-top: 24px;
        }
        .sig-col {
            width: 30%;
        }

        /* ==================== TRANG 2 CSS (XOAY -90 ĐỘ / LẬT 180 ĐỘ) ==================== */
        .page-p2-portrait {
            width: 210mm;
            height: 297mm;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
            background: #fff;
            page-break-before: always;
            page-break-inside: avoid;
        }

        /* Xoay -90 độ (lật 180 độ) đúng chuẩn mặt sau A4 */
        .p2-rotated-wrapper {
            width: 265mm;
            height: 180mm;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-90deg);
            transform-origin: center center;
            box-sizing: border-box;
        }

        .p2-header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            width: 100%;
        }
        /* Đơn vị Trang 2: KÝ TÚC XÁ - ĐỘI SINH VIÊN TỰ QUẢN in đậm góc trái thông thoáng */
        .p2-header-left {
            font-size: 12pt;
            font-weight: bold;
            line-height: 1.35;
            text-align: center;
            white-space: nowrap;
        }
        .p2-header-right {
            font-size: 12pt;
            font-style: italic;
            text-align: right;
            white-space: nowrap;
        }

        .p2-title {
            text-align: center;
            font-size: 14.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 4px;
            margin-bottom: 14px;
        }

        /* Bảng Trang 2 (14 phòng x 7 ngày) */
        .table-p2 {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 11.5pt;
        }
        .table-p2 th, .table-p2 td {
            border: 1.5px solid #000;
            padding: 5px 6px;
            text-align: center;
            height: 24px;
        }
        .table-p2 th {
            font-weight: bold;
        }
        /* STT / Mã phòng Trang 2 */
        .table-p2 td.room-code {
            width: 12%;
            font-size: 11.5pt;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .p2-signature {
            text-align: right;
            font-size: 12.5pt;
            font-weight: bold;
            margin-top: 14px;
            padding-right: 50px;
        }

        /* Style dành riêng cho In ấn (Media Print) */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            body {
                background: #fff !important;
            }
            .control-bar {
                display: none !important;
            }
            .document-wrapper {
                margin: 0 !important;
                gap: 0 !important;
            }
            .paper-page {
                box-shadow: none !important;
                margin: 0 !important;
                width: 210mm !important;
                height: 297mm !important;
                float: none !important;
            }
            .page-portrait {
                padding: 15mm 15mm 15mm 15mm !important;
                page-break-after: always !important;
                page-break-inside: avoid !important;
            }
            .page-p2-portrait {
                padding: 0 !important;
                page-break-before: always !important;
                page-break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

    <!-- Thanh công cụ màn hình (Tự động ẩn khi In) -->
    <div class="control-bar no-print">
        <div class="d-flex align-items-center gap-3">
            <a href="<?php echo BASE_URL; ?>/floors" class="btn btn-outline-light btn-sm rounded-2">
                <i class="bi bi-arrow-left me-1"></i> Quay lại Quản lý Lầu
            </a>
            <span class="fw-bold text-white fs-6">Mẫu Báo Cáo Lầu <?php echo $floorCodeStr; ?> (2 trang A4 đứng)</span>
        </div>

        <div class="d-flex align-items-center gap-3">
            <!-- Chọn Lầu Khác -->
            <form method="GET" action="" class="d-flex align-items-center gap-2">
                <select name="floor_id" class="form-select form-select-sm bg-dark text-white border-secondary rounded-2" onchange="this.form.submit()" style="width: 180px;">
                    <?php foreach ($allFloors as $f): 
                        $fNum = (int)$f['floor_number'];
                        $bName = $f['building_name'] ?? 'Tòa K';
                        $bCode = preg_match('/Tòa\s*([A-Za-z0-9]+)/ui', $bName, $m) ? strtoupper($m[1]) : 'K';
                        $code = $bCode . '.' . sprintf('%02d', $fNum);
                    ?>
                    <option value="<?php echo $f['id']; ?>" <?php echo $f['id'] == $floorData['id'] ? 'selected' : ''; ?>>
                        Lầu <?php echo $code; ?> (<?php echo htmlspecialchars($bName); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <button onclick="window.print()" class="btn btn-primary btn-sm rounded-2 px-3 fw-bold">
                <i class="bi bi-printer-fill me-1"></i> In báo cáo (Ctrl+P)
            </button>
        </div>
    </div>

    <!-- Container chứa tài liệu in -->
    <div class="document-wrapper">

        <!-- ============ TRANG 1: TRANG TRƯỚC (A4 ĐỨNG) ============ -->
        <div class="paper-page page-portrait">
            
            <!-- Top Header (Tên trường bên trái & Tiêu ngữ bên phải có khoảng cách thông thoáng) -->
            <div class="header-section">
                <div class="header-left">
                    <span class="font-bold no-wrap">TRƯỜNG ĐẠI HỌC TÔN ĐỨC THẮNG</span>
                    <span class="font-bold no-wrap">KÝ TÚC XÁ</span>
                    <div class="header-underline-short"></div>
                </div>
                <div class="header-right">
                    <span class="font-bold no-wrap">CỘNG HOÀ XÃ HỘI CHỦ NGHĨA VIỆT NAM</span>
                    <span class="font-bold no-wrap">Độc lập - Tự do - Hạnh phúc</span>
                    <div class="header-underline-long"></div>
                </div>
            </div>

            <!-- Ngày tháng -->
            <div class="date-line">
                <?php
                $day   = date('d');
                $month = date('m');
                $year  = date('Y');
                ?>
                TP. Hồ Chí Minh, ngày <?php echo $day; ?> tháng <?php echo $month; ?> năm <?php echo $year; ?>
            </div>

            <!-- Tiêu đề báo cáo động theo lầu được chọn -->
            <div class="title-section">
                <div class="title-main font-bold">BÁO CÁO LẦU <?php echo $floorCodeStr; ?></div>
                <div class="title-sub">( Từ ngày ..............đến ngày ............. )</div>
            </div>

            <!-- Bảng nội dung báo cáo (Cột đầu là TT chuẩn ảnh) -->
            <table class="table-p1">
                <thead>
                    <tr>
                        <th style="width: 45px;" class="font-bold">STT</th>
                        <th class="font-bold">NỘI DUNG</th>
                        <th style="width: 28%;" class="font-bold">GHI CHÚ</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Mục 1 -->
                    <tr>
                        <td class="col-tt font-bold">1.</td>
                        <td class="col-content">
                            <div class="section-heading font-bold">Tình hình tạm trú – tạm vắng:</div>
                            <div class="row-space" style="height: 110px;"></div>
                        </td>
                        <td class="col-notes"></td>
                    </tr>
                    <!-- Mục 2 -->
                    <tr>
                        <td class="col-tt font-bold">2.</td>
                        <td class="col-content">
                            <div class="section-heading font-bold">Tình hình Vệ sinh:</div>
                            <div class="row-space" style="height: 100px;"></div>
                        </td>
                        <td class="col-notes"></td>
                    </tr>
                    <!-- Mục 3 -->
                    <tr>
                        <td class="col-tt font-bold">3.</td>
                        <td class="col-content">
                            <div class="section-heading font-bold">Tình hình an ninh trật tự:</div>
                            <div class="row-space" style="height: 100px;"></div>
                        </td>
                        <td class="col-notes"></td>
                    </tr>
                    <!-- Mục 4 -->
                    <tr>
                        <td class="col-tt font-bold">4.</td>
                        <td class="col-content">
                            <div class="section-heading font-bold">Tình hình cơ sở vật chất:</div>
                            <div class="row-space" style="height: 100px;"></div>
                        </td>
                        <td class="col-notes"></td>
                    </tr>
                    <!-- Mục 5 -->
                    <tr>
                        <td class="col-tt font-bold">5.</td>
                        <td class="col-content">
                            <div class="section-heading font-bold">Các công việc khác trong tuần:</div>
                            <div class="row-space" style="height: 100px;"></div>
                        </td>
                        <td class="col-notes"></td>
                    </tr>
                </tbody>
            </table>

            <!-- Phần chữ ký -->
            <div class="signatures-p1 font-bold">
                <div class="sig-col">TRƯỜNG DÃY</div>
                <div class="sig-col">KIỂM SOÁT</div>
                <div class="sig-col">KÝ TÚC XÁ</div>
            </div>

        </div>


        <!-- ============ TRANG 2: TRANG SAU (A4 ĐỨNG, NỘI DUNG XOAY DỌC LẬT 180 ĐỘ) ============ -->
        <div class="paper-page page-p2-portrait">

            <div class="p2-rotated-wrapper">
                <!-- Header Đơn vị & Ngày tháng Trang 2 -->
                <div class="p2-header-section">
                    <div class="p2-header-left font-bold">
                        <div class="no-wrap font-bold">KÝ TÚC XÁ</div>
                        <div class="no-wrap font-bold">ĐỘI SINH VIÊN TỰ QUẢN</div>
                    </div>
                    <div class="p2-header-right">
                        Tp. Hồ Chí Minh, ngày <?php echo $day; ?> tháng <?php echo $month; ?> năm <?php echo $year; ?>
                    </div>
                </div>

                <!-- Tiêu đề Trang 2 động theo lầu -->
                <div class="p2-title font-bold">
                    THEO DÕI TẠM TRÚ, TẠM VẮNG, LẦU <?php echo $floorCodeStr; ?>
                </div>

                <!-- Bảng điểm danh Trang 2 (14 phòng x 7 ngày) -->
                <table class="table-p2">
                    <thead>
                        <tr>
                            <th style="width: 12%;"></th>
                            <th style="width: 12.5%;" class="font-bold">Chủ nhật</th>
                            <th style="width: 12.5%;" class="font-bold">Thứ 2</th>
                            <th style="width: 12.5%;" class="font-bold">Thứ 3</th>
                            <th style="width: 12.5%;" class="font-bold">Thứ 4</th>
                            <th style="width: 12.5%;" class="font-bold">Thứ 5</th>
                            <th style="width: 12.5%;" class="font-bold">Thứ 6</th>
                            <th style="width: 12.5%;" class="font-bold">Thứ 7</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 1; $i <= 14; $i++): ?>
                        <tr>
                            <td class="room-code font-bold">......<?php echo sprintf('%02d', $i); ?></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>

                <!-- Chữ ký Trang 2 -->
                <div class="p2-signature font-bold">
                    TRƯỜNG DÃY
                </div>
            </div>

        </div>

    </div>

</body>
</html>
