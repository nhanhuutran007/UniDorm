<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/network-management/assets/css/auth.css">
    <title>Đăng nhập</title>
</head>

<body>
    <div class="login_wrapper">
        <div class="login_container">
            <!-- SVG bên trái -->
            <div class="login_background_image">
                <img src="/network-management/assets/svg/background_logo.svg" alt="Login Illustration">
            </div>
            <!-- Form login bên phải -->
            <div class="login_form">
                <div class="text-center mb-4">
                    <img src="/network-management/assets/img/logo1.png" alt="Logo" style="max-width: 100px;">
                </div>
                <h2 class="text-center text-uppercase mb-4">Đăng nhập</h2>
                <?php if (!empty($success)) : ?>
                <div class="alert alert-success text-center"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (!empty($error)) : ?>
                <div class="alert alert-danger text-center"><?php echo $error; ?></div>
                <?php endif; ?>
                <form action="/network-management/index.php?route=login" method="POST">
                    <div class="form-group mb-3">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="password">Password</label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control" required>
                            <span class="input-group-text login_toggle_password" style="cursor: pointer;">
                                <i class="bi bi-eye" id="togglePasswordIcon"></i>
                            </span>
                        </div>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary" id="loginBtn">Đăng nhập</button>
                    </div>
                    <div class="text-center mt-3">
                        <span>Chưa có tài khoản? <a href="/network-management/index.php?route=register">Đăng
                                kí</a></span>
                    </div>
                    <div class="text-center mt-3">
                        <span><a href="/network-management/index.php?route=forgot">Quên mật khẩu?</a></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    document.querySelector('.login_toggle_password').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePasswordIcon');
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
    document.querySelector('form').addEventListener('submit', function() {
        const btn = document.getElementById('loginBtn');
        btn.disabled = true;
        btn.innerHTML = 'Đang đăng nhập...';
    });
    </script>
</body>

</html>