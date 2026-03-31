<?php
/**
 * UniDorm – Main Layout Wrapper
 * path: views/layout/main.php
 *
 * Sử dụng:
 *   $pageTitle   = 'Tên trang';
 *   $extraCss    = ['assets/css/custom.css'];  // (tuỳ chọn)
 *   $extraJs     = ['assets/js/page.js'];       // (tuỳ chọn)
 *   require LAYOUT_PATH . '/main.php';
 *   // Sau đó echo $content (biến chứa nội dung trang)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// Guard: Chưa đăng nhập → về trang login
if (!isset($_SESSION['user_id'])) {
    header('Location: /UniDorm/views/auth/login.php');
    exit;
}

// Guard: Tài khoản bị khoá hoặc chưa kích hoạt
if (!isset($_SESSION['status']) || $_SESSION['status'] === 'pending') {
    session_destroy();
    header('Location: /UniDorm/views/auth/login.php?error=not_activated');
    exit;
}

// Cập nhật thời gian hoạt động (session timeout 2 giờ)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    session_destroy();
    header('Location: /UniDorm/views/auth/login.php?error=session_expired');
    exit;
}
$_SESSION['last_activity'] = time();

// Lấy thông tin người dùng từ DB
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/models/UserModel.php';

$userModel  = new UserModel($conn);
$userData   = $userModel->getUserById($_SESSION['user_id']);

if (!$userData) {
    session_destroy();
    header('Location: /UniDorm/views/auth/login.php?error=user_not_found');
    exit;
}

$userRole       = strtolower($userData['role']);
$userId         = $userData['user_id'];
$profilePicture = '/UniDorm/' . htmlspecialchars($userData['profile_picture'] ?? 'assets/images/default-avatar.jpg');

// URL động theo role
$dashboardUrl = match($userRole) {
    'admin' => '/UniDorm/views/admin/dashboard.php',
    'student'        => '/UniDorm/views/student/dashboard.php',
    default          => '/UniDorm/'
};
$profileUrl  = '/UniDorm/views/shared/profile.php';
$notifUrl    = match($userRole) {
    'student' => '/UniDorm/views/student/notifications.php',
    default   => '/UniDorm/views/admin/notifications.php'
};
$pageTitle = ($pageTitle ?? 'UniDorm') . ' | UniDorm';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Hệ thống quản lý ký túc xá UniDorm – Trường Đại học Tôn Đức Thắng">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="/UniDorm/assets/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Template CSS (giữ lại từ cũ) -->
    <link rel="stylesheet" href="/UniDorm/assets/css/style.css">
    <!-- UniDorm Custom CSS -->
    <link rel="stylesheet" href="/UniDorm/assets/css/unidorm.css">

    <?php if (!empty($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="/UniDorm/<?php echo htmlspecialchars($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>

<!-- Wrapper chính -->
<div class="main-wrapper">

    <!-- Header -->
    <?php require_once __DIR__ . '/header.php'; ?>

    <!-- Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Nội dung trang -->
    <div class="page-wrapper">
        <div class="content container-fluid">

            <?php if (!empty($pageTitle)): ?>
            <div class="page-header mb-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h3 class="page-title mb-0"><?php echo htmlspecialchars(explode(' | ', $pageTitle)[0]); ?></h3>
                        <?php if (!empty($breadcrumbs)): ?>
                        <ul class="breadcrumb mt-1 mb-0">
                            <?php foreach ($breadcrumbs as $i => $crumb): ?>
                            <li class="breadcrumb-item <?php echo $i === array_key_last($breadcrumbs) ? 'active' : ''; ?>">
                                <?php if ($i === array_key_last($breadcrumbs)): ?>
                                    <?php echo htmlspecialchars($crumb['label']); ?>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($crumb['url']); ?>"><?php echo htmlspecialchars($crumb['label']); ?></a>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Nội dung được inject bởi từng trang -->
            <?php echo $content ?? ''; ?>

        </div>
    </div>

</div><!-- /.main-wrapper -->

<!-- Scripts -->
<script src="/UniDorm/assets/js/jquery.min.js"></script>
<script src="/UniDorm/assets/js/bootstrap.bundle.min.js"></script>
<script src="/UniDorm/assets/js/app.js"></script>

<?php if (!empty($extraJs)): ?>
    <?php foreach ($extraJs as $js): ?>
    <script src="/UniDorm/<?php echo htmlspecialchars($js); ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
