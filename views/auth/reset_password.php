<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/network-management/assets/css/auth.css">
    <title>Đặt lại mật khẩu</title>
</head>
<body>
    <div class="reset_password_wrapper">
        <div class="reset_password_form">
            <h2 class="text-center text-uppercase mb-4">Đặt lại mật khẩu</h2>
            <?php if (!empty($error)) : ?>
                <div class="alert alert-danger text-center"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!empty($success)) : ?>
                <div class="alert alert-success text-center"><?php echo $success; ?></div>
            <?php else : ?>
                <form action="/network-management/index.php?route=reset&token=<?php echo urlencode($_GET['token']); ?>" method="POST">
                    <div class="form-group mb-3">
                        <label for="password">Mật khẩu mới</label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control" required>
                            <span class="input-group-text reset_password_toggle_password" style="cursor: pointer;" data-target="password">
                                <i class="bi bi-eye" id="togglePasswordIcon"></i>
                            </span>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label for="confirm_password">Xác nhận mật khẩu</label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                            <span class="input-group-text reset_password_toggle_password" style="cursor: pointer;" data-target="confirm_password">
                                <i class="bi bi-eye" id="toggleConfirmPasswordIcon"></i>
                            </span>
                        </div>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">Cập nhật mật khẩu</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.querySelectorAll('.reset_password_toggle_password').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const toggleIcon = this.querySelector('i');
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleIcon.classList.remove('bi-eye');
                    toggleIcon.classList.add('bi-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    toggleIcon.classList.remove('bi-eye-slash');
                    toggleIcon.classList.add('bi-eye');
                }
            });
        });
    </script>
</body>
</html>