<?php
/**
 * UniDorm – Export PDF API
 * Xuất danh sách sinh viên hoặc báo cáo thống kê ra file PDF
 * GET: ?type=students hoặc ?type=report
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// Kiểm tra đăng nhập & quyền admin
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Không có quyền truy cập.');
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';

$type = $_GET['type'] ?? '';

if (!in_array($type, ['students', 'report'])) {
    http_response_code(400);
    die('Tham số type không hợp lệ.');
}

// ─── Tạo PDF ─────────────────────────────────────────────────────────
class UniDormPDF extends TCPDF {
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('dejavusans', '', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Trang ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new UniDormPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('UniDorm');
$pdf->SetAuthor('UniDorm Admin');
$pdf->SetTitle($type === 'students' ? 'Danh sach sinh vien' : 'Bao cao thong ke');

// Header
$pdf->setHeaderFont(['dejavusans', '', 8]);
$pdf->setHeaderData('', 0, 'UniDorm - Ky tac xa', 'Ngay xuat: ' . date('d/m/Y H:i'));
$pdf->setFooterFont(['dejavusans', '', 8]);
$pdf->setFooterData(array(64, 64, 64), array(128, 128, 128));
$pdf->setFooterMargin(10);

// Margin
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetAutoPageBreak(true, 20);

// Font hỗ trợ tiếng Việt
$fontDir = __DIR__ . '/../vendor/tecnickcom/tcpdf/fonts/';
$pdf->addFont('dejavusans', '', $fontDir . 'dejavusans.php', true);
$pdf->addFont('dejavusans', 'B', $fontDir . 'dejavusansb.php', true);

$pdf->SetFont('dejavusans', '', 10);

if ($type === 'students') {
    // ─── DANH SÁCH SINH VIÊN ────────────────────────────────────────
    $pdf->AddPage();

    // Tiêu đề
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->Cell(0, 12, iconv('UTF-8', 'UTF-8//IGNORE', 'DANH SACH SINH VIEN KY TUC XA'), 0, 1, 'C');
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(0, 8, iconv('UTF-8', 'UTF-8//IGNORE', 'Ngay xuat: ' . date('d/m/Y H:i')), 0, 1, 'C');
    $pdf->Ln(5);

    // Lấy danh sách SV
    $students = $conn->query("
        SELECT u.student_code, u.full_name, u.gender, u.dob, u.phone, u.hometown,
               r.room_code, b.bed_label, u.status,
               u.role AS user_role
        FROM users u
        LEFT JOIN beds b ON u.bed_id = b.id
        LEFT JOIN rooms r ON b.room_id = r.id
        WHERE (u.role = 'student' OR (u.role = 'admin' AND u.student_code IS NOT NULL AND u.student_code != 'admin'))
        ORDER BY r.room_code ASC, b.bed_label ASC
    ")->fetch_all(MYSQLI_ASSOC);

    // Table header
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->SetFillColor(37, 99, 235);
    $pdf->SetTextColor(255, 255, 255);

    $colW = [12, 22, 42, 15, 22, 25, 32, 22];
    $headers = ['STT', 'Ma HV', 'Ho ten', 'Gioi tinh', 'Ngay sinh', 'So DT', 'Que quan', 'Phong'];

    foreach ($headers as $i => $h) {
        $pdf->Cell($colW[$i], 8, iconv('UTF-8', 'UTF-8//IGNORE', $h), 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Table body
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;

    foreach ($students as $i => $s) {
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
            // Re-draw header
            $pdf->SetFont('dejavusans', 'B', 8);
            $pdf->SetFillColor(37, 99, 235);
            $pdf->SetTextColor(255, 255, 255);
            foreach ($headers as $h) {
                $pdf->Cell($colW[array_search($h, $headers)], 8, iconv('UTF-8', 'UTF-8//IGNORE', $h), 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('dejavusans', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $fill = false;
        }

        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 245 : 255, $fill ? 255 : 255);
        $row = [
            ($i + 1),
            $s['student_code'] ?? '',
            $s['full_name'] ?? '',
            $s['gender'] ?? '',
            $s['dob'] ?? '',
            $s['phone'] ?? '',
            $s['hometown'] ?? '',
            $s['room_code'] ?? 'Chua phong',
        ];

        foreach ($row as $j => $cell) {
            $txt = iconv('UTF-8', 'UTF-8//IGNORE', (string)$cell);
            $pdf->Cell($colW[$j], 7, $txt, 1, 0, 'C', $fill);
        }
        $pdf->Ln();
        $fill = !$fill;
    }

    // Tổng cộng
    $pdf->Ln(3);
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell(0, 8, iconv('UTF-8', 'UTF-8//IGNORE', 'Tong so: ' . count($students) . ' sinh vien'), 0, 1);

    $filename = 'DanhSachSinhVien_' . date('Ymd_His') . '.pdf';

} else {
    // ─── BÁO CÁO THỐNG KÊ ────────────────────────────────────────────
    $pdf->AddPage();

    // Tiêu đề
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->Cell(0, 12, iconv('UTF-8', 'UTF-8//IGNORE', 'BAO CAO THONG KE KÝ TÚC XÁ'), 0, 1, 'C');
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(0, 8, iconv('UTF-8', 'UTF-8//IGNORE', 'Ngay xuat: ' . date('d/m/Y H:i')), 0, 1, 'C');
    $pdf->Ln(5);

    // Lấy thống kê
    $stats = [];
    $stats['students_total']   = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE (role='student' OR (role='admin' AND student_code IS NOT NULL AND student_code != 'admin'))")->fetch_assoc()['c'];
    $stats['students_active']  = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE (role='student' OR (role='admin' AND student_code IS NOT NULL AND student_code != 'admin')) AND status IN ('active', 'pending')")->fetch_assoc()['c'];
    $stats['rooms_total']      = (int)$conn->query("SELECT COUNT(*) as c FROM rooms")->fetch_assoc()['c'];
    $stats['rooms_avail']      = (int)$conn->query("SELECT COUNT(*) as c FROM rooms WHERE status='available'")->fetch_assoc()['c'];
    $stats['rooms_full']       = (int)$conn->query("SELECT COUNT(*) as c FROM rooms WHERE status='full'")->fetch_assoc()['c'];
    $stats['rooms_maint']      = (int)$conn->query("SELECT COUNT(*) as c FROM rooms WHERE status='maintenance'")->fetch_assoc()['c'];

    // Tổng giường
    $beds = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN is_occupied = 1 THEN 1 ELSE 0 END) as occupied FROM beds")->fetch_assoc();
    $stats['beds_total']   = (int)$beds['total'];
    $stats['beds_occupied'] = (int)$beds['occupied'];
    $stats['beds_empty']   = $stats['beds_total'] - $stats['beds_occupied'];

    // Section 1: Tổng quan
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetFillColor(37, 99, 235);
    $pdf->SetTextColor(255);
    $pdf->Cell(0, 10, iconv('UTF-8', 'UTF-8//IGNORE', '  1. TONG QUAN'), 0, 1, 'L', true);
    $pdf->SetTextColor(0);
    $pdf->Ln(3);

    $pdf->SetFont('dejavusans', '', 10);
    $items = [
        ['Tong so sinh vien', $stats['students_total']],
        ['Sinh vien dang hoat dong', $stats['students_active']],
        ['Tong so phong', $stats['rooms_total']],
        ['Phong con trong', $stats['rooms_avail']],
        ['Phong da day', $stats['rooms_full']],
        ['Phong bao tri', $stats['rooms_maint']],
    ];

    foreach ($items as [$label, $val]) {
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell(100, 8, iconv('UTF-8', 'UTF-8//IGNORE', $label), 'B', 0, 'L', true);
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell(80, 8, (string)$val, 'B', 1, 'C', true);
        $pdf->SetFont('dejavusans', '', 10);
    }

    $pdf->Ln(5);

    // Section 2: Giường
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetFillColor(37, 99, 235);
    $pdf->SetTextColor(255);
    $pdf->Cell(0, 10, iconv('UTF-8', 'UTF-8//IGNORE', '  2. TINH TRANG GIUONG'), 0, 1, 'L', true);
    $pdf->SetTextColor(0);
    $pdf->Ln(3);

    $pdf->SetFont('dejavusans', '', 10);
    $bedItems = [
        ['Tong so giuong', $stats['beds_total']],
        ['Giuong da co nguoi', $stats['beds_occupied']],
        ['Giuong trong', $stats['beds_empty']],
        ['Ty le lap day', $stats['beds_total'] > 0 ? round($stats['beds_occupied'] / $stats['beds_total'] * 100, 1) . '%' : '0%'],
    ];

    foreach ($bedItems as [$label, $val]) {
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell(100, 8, iconv('UTF-8', 'UTF-8//IGNORE', $label), 'B', 0, 'L', true);
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell(80, 8, (string)$val, 'B', 1, 'C', true);
        $pdf->SetFont('dejavusans', '', 10);
    }

    $pdf->Ln(5);

    // Section 3: Sĩ số theo lầu
    $floorData = $conn->query("
        SELECT f.floor_number, b.name as bname,
               (SELECT COUNT(DISTINCT u.user_id) 
                FROM users u 
                JOIN beds bd ON u.bed_id = bd.id 
                JOIN rooms r2 ON bd.room_id = r2.id 
                WHERE r2.floor_id = f.id 
                  AND (u.role = 'student' OR (u.role = 'admin' AND u.student_code IS NOT NULL AND u.student_code != 'admin')) 
                  AND u.status IN ('active', 'pending')) as students,
               (SELECT SUM(r3.max_capacity) 
                FROM rooms r3 
                WHERE r3.floor_id = f.id) as capacity
        FROM floors f
        JOIN buildings b ON f.building_id = b.id
        ORDER BY f.floor_number ASC
    ")->fetch_all(MYSQLI_ASSOC);

    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetFillColor(37, 99, 235);
    $pdf->SetTextColor(255);
    $pdf->Cell(0, 10, iconv('UTF-8', 'UTF-8//IGNORE', '  3. SI SO THEO LAU'), 0, 1, 'L', true);
    $pdf->SetTextColor(0);
    $pdf->Ln(3);

    // Table header
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(230, 235, 250);
    $pdf->Cell(15, 8, iconv('UTF-8', 'UTF-8//IGNORE', 'STT'), 1, 0, 'C', true);
    $pdf->Cell(50, 8, iconv('UTF-8', 'UTF-8//IGNORE', 'Tang'), 1, 0, 'C', true);
    $pdf->Cell(40, 8, iconv('UTF-8', 'UTF-8//IGNORE', 'Sinh vien'), 1, 0, 'C', true);
    $pdf->Cell(40, 8, iconv('UTF-8', 'UTF-8//IGNORE', 'Suc chua'), 1, 0, 'C', true);
    $pdf->Cell(40, 8, iconv('UTF-8', 'UTF-8//IGNORE', 'Con trong'), 1, 1, 'C', true);

    $pdf->SetFont('dejavusans', '', 9);
    $fill = false;
    foreach ($floorData as $i => $f) {
        $students = (int)$f['students'];
        $capacity = (int)$f['capacity'];
        $empty = $capacity - $students;

        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 245 : 255, $fill ? 255 : 255);
        $pdf->Cell(15, 7, (string)($i + 1), 1, 0, 'C', $fill);
        $pdf->Cell(50, 7, iconv('UTF-8', 'UTF-8//IGNORE', 'Lau ' . $f['floor_number']), 1, 0, 'C', $fill);
        $pdf->Cell(40, 7, (string)$students, 1, 0, 'C', $fill);
        $pdf->Cell(40, 7, (string)$capacity, 1, 0, 'C', $fill);
        $pdf->Cell(40, 7, (string)$empty, 1, 1, 'C', $fill);
        $fill = !$fill;
    }

    $filename = 'BaoCaoThongKe_' . date('Ymd_His') . '.pdf';
}

// Xuất PDF
$pdf->Output($filename, 'D');
