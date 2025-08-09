<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/network-management/assets/css/auth.css">
    <title>Xác nhận mật khẩu</title>
</head>
<body>
    <div class="activate_email_wrapper">
        <div class="activate_email_container">
            <div class="activate_email_content">
                <div class="text-center mb-4">
                    <img src="/network-management/assets/img/logo1.png" alt="Logo" style="max-width: 100px;">
                </div>
                <h2 class="text-center text-uppercase mb-4">Xác nhận mật khẩu</h2>
                <div class="text-center">
                    <p class="mb-3">Mật khẩu mặc định là <strong>52300232</strong> - Vui lòng đặt lại mật khẩu sau khi đăng nhập.</p>
                    <?php if (!empty($message)): ?>
                        <?php if (strpos($message, "thành công") !== false): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                            <a href="/network-management/index.php?route=login" class="btn btn-primary mt-3">Đăng nhập ngay</a>
                        <?php else: ?>
                            <div class="alert alert-danger"><?php echo $message; ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>