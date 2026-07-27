<?php
/**
 * Test gửi email – chỉ admin
 * Truy cập: /test_mail
 */
$pageTitle   = 'Test gửi Email';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => BASE_URL . '/dashboard'],
    ['label' => 'Test Email', 'url' => '#'],
];
ob_start();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/services/MailService.php';

$testResult = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toEmail = trim($_POST['email'] ?? '');
    $toName  = trim($_POST['name'] ?? 'Test User');

    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $testResult = '<div class="alert alert-danger">Email không hợp lệ.</div>';
    } else {
        $mailService = new MailService();
        $subject = '[UniDorm] Test Email – ' . date('H:i:s d/m/Y');
        $body = '<h3>Test Email thành công!</h3>'
            . '<p>Email này được gửi từ trang Test Email của UniDorm.</p>'
            . '<p>Thời gian: ' . date('H:i:s d/m/Y') . '</p>';

        $ok = $mailService->send($toEmail, $toName, $subject, $body);

        if ($ok) {
            $testResult = '<div class="alert alert-success"><strong>✅ Gửi thành công!</strong> Kiểm tra hộp mail (và thư mục Spam/Junk) của <code>' . htmlspecialchars($toEmail) . '</code></div>';
        } else {
            $errorInfo = htmlspecialchars($mailService->lastError ?: 'Không rõ lỗi');
            $testResult = '<div class="alert alert-danger"><strong>❌ Gửi thất bại!</strong><br><br>'
                . '<strong>Chi tiết lỗi:</strong><br><pre class="bg-light p-3 rounded" style="font-size:12px; white-space:pre-wrap;">' . $errorInfo . '</pre>'
                . '<br><strong>Nguyên nhân phổ biến:</strong>'
                . '<ul><li>Gmail App Password đã hết hạn → tạo lại tại <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a></li>'
                . '<li>Hosting chặn SMTP port 587 → liên hệ nhà cung cấp hosting</li>'
                . '<li>PHPMailer chưa cài đủ → chạy <code>composer install</code> trên hosting</li></ul>'
                . '</div>';
        }
    }
}

// Lấy email admin hiện tại
$adminEmail = '';
if (session_status() === PHP_SESSION_NONE) session_start();
$adminId = $_SESSION['user_id'] ?? 0;
if ($adminId) {
    $adm = $conn->query("SELECT email, fullname FROM users WHERE user_id = $adminId")->fetch_assoc();
    $adminEmail = $adm['email'] ?? '';
    $adminName  = $adm['fullname'] ?? 'Admin';
}
?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm" style="border-radius:14px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-envelope-paper me-2 text-primary"></i>Gửi test email</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Gửi đến email</label>
                        <input type="email" name="email" class="form-control rounded-3" value="<?php echo htmlspecialchars($adminEmail); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tên người nhận</label>
                        <input type="text" name="name" class="form-control rounded-3" value="<?php echo htmlspecialchars($adminName); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-3 fw-semibold">
                        <i class="bi bi-send me-2"></i>Gửi test email
                    </button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4" style="border-radius:14px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-2 text-info"></i>Thông tin SMTP</h6>
                <table class="table table-sm mb-0" style="font-size:12px;">
                    <tr><td class="text-muted">SMTP Host</td><td><code>smtp.gmail.com</code></td></tr>
                    <tr><td class="text-muted">Port</td><td><code>587</code> (STARTTLS)</td></tr>
                    <tr><td class="text-muted">Sender</td><td><code>unidorm.tdtu@gmail.com</code></td></tr>
                    <tr><td class="text-muted">PHPMailer</td><td><?php echo class_exists('PHPMailer\PHPMailer\PHPMailer') ? '<span class="text-success">✓ Đã cài</span>' : '<span class="text-danger">✗ Chưa cài</span>'; ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <?php echo $testResult; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layout/main.php';
