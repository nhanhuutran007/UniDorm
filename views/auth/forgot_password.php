<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/network-management/assets/css/auth.css">
    <title>Quên mật khẩu</title>
</head>
<body>
    <div class="forgot_password_wrapper">
        <div class="forgot_password_form">
            <h2 class="text-center text-uppercase mb-4">Quên mật khẩu</h2>
            <?php if (!empty($error)) : ?>
                <div class="alert alert-danger text-center"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!empty($success)) : ?>
                <div class="alert alert-success text-center"><?php echo $success; ?></div>
            <?php endif; ?>
            <form action="/network-management/index.php?route=forgot" method="POST">
                <div class="form-group mb-3">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-primary">Gửi yêu cầu</button>
                </div>
                <div class="text-center mt-3">
                    <a href="/network-management/index.php?route=login">Quay lại đăng nhập</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>