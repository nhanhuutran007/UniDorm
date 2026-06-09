<?php
/**
 * CSV Debug Tool - Kiểm tra cấu trúc file CSV
 */
require_once __DIR__ . '/../../includes/db.php';

$debug = [];
$rawHeaders = [];
$firstRow = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'rb');
        $allRows = [];
        $rowIdx = 0;
        
        while (($line = fgets($handle)) !== false && $rowIdx < 3) {
            // Xử lý BOM UTF-8
            if ($rowIdx === 0 && str_starts_with($line, "\xEF\xBB\xBF")) {
                $debug[] = "✓ Phát hiện BOM UTF-8 ở dòng đầu";
                $line = substr($line, 3);
            }
            
            $line = rtrim($line, "\r\n");
            
            // Kiểm tra encoding
            if (!mb_check_encoding($line, 'UTF-8')) {
                $debug[] = "⚠ Dòng $rowIdx không phải UTF-8 hợp lệ - đã chuyển đổi tự động";
                $line = @mb_convert_encoding($line, 'UTF-8', 'auto');
            }
            
            $parsed = str_getcsv($line, ',', '"');
            $allRows[] = $parsed;
            
            if ($rowIdx === 0) {
                $rawHeaders = $parsed;
            } elseif ($rowIdx === 1) {
                $firstRow = $parsed;
            }
            
            $rowIdx++;
        }
        fclose($handle);
        
        $debug[] = "📊 Tổng số cột: " . count($rawHeaders);
        $debug[] = "📊 Số cột dữ liệu dòng 1: " . count($firstRow);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Debug Tool</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo BASE_URL; ?>/assets/img/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">🔍 CSV Debug Tool - Kiểm tra cấu trúc file</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Upload file CSV để kiểm tra:</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Phân tích file</button>
                        <a href="import.php" class="btn btn-outline-secondary">← Quay lại Import</a>
                    </form>
                    
                    <?php if (!empty($debug)): ?>
                    <hr>
                    <h6 class="fw-bold">Kết quả phân tích:</h6>
                    <div class="alert alert-info">
                        <?php foreach ($debug as $msg): ?>
                        <div><?php echo $msg; ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($rawHeaders)): ?>
                    <h6 class="fw-bold mt-4">Header Row (Dòng tiêu đề):</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Cột #</th>
                                    <th>Tên cột (Raw)</th>
                                    <th>Tên cột (Cleaned)</th>
                                    <th>Bytes (Hex)</th>
                                    <th>Độ dài</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rawHeaders as $idx => $h): ?>
                                <?php 
                                    // Sử dụng CHÍNH XÁC logic giống import.php
                                    $cleaned = trim($h);
                                    $cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $cleaned);
                                    $cleaned = mb_strtolower($cleaned, 'UTF-8');
                                    $cleaned = preg_replace('/\s+/', '_', $cleaned);
                                    $hexBytes = '';
                                    for ($i = 0; $i < min(strlen($h), 20); $i++) {
                                        $hexBytes .= sprintf('%02X ', ord($h[$i]));
                                    }
                                ?>
                                <tr>
                                    <td><strong><?php echo $idx + 1; ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($h); ?></code></td>
                                    <td><code class="text-primary"><?php echo htmlspecialchars($cleaned); ?></code></td>
                                    <td><small class="text-muted"><?php echo $hexBytes; ?></small></td>
                                    <td><?php echo strlen($h); ?> bytes</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($firstRow)): ?>
                    <h6 class="fw-bold mt-4">First Data Row (Dòng dữ liệu đầu tiên):</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Cột #</th>
                                    <th>Header</th>
                                    <th>Giá trị</th>
                                    <th>Kiểu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($firstRow as $idx => $val): ?>
                                <tr>
                                    <td><strong><?php echo $idx + 1; ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($rawHeaders[$idx] ?? 'N/A'); ?></code></td>
                                    <td><code><?php echo htmlspecialchars($val); ?></code></td>
                                    <td>
                                        <?php if (empty($val)): ?>
                                        <span class="badge bg-warning">Empty</span>
                                        <?php elseif (is_numeric($val)): ?>
                                        <span class="badge bg-info">Number</span>
                                        <?php else: ?>
                                        <span class="badge bg-success">Text</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-warning mt-3">
                        <strong>💡 Lưu ý:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Kiểm tra cột "Tên cột (Cleaned)" phải khớp với các alias trong code</li>
                            <li>Các cột bắt buộc: <code>mã_hv</code> (MSSV) và <code>họ_tên</code></li>
                            <li>Nếu header không khớp, hãy đổi tên cột trong Excel trước khi export CSV</li>
                            <li>Đảm bảo file CSV lưu dạng UTF-8</li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
