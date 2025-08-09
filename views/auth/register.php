<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/network-management/assets/css/auth.css">
    <title>Đăng kí</title>
</head>
<body>
    <div class="register_wrapper">
        <div class="register_container">
            <!-- SVG bên trái -->
            <div class="register_background_image">
                <img src="/network-management/assets/svg/background_logo.svg" alt="Register Illustration">
            </div>
            <!-- Form đăng ký bên phải -->
            <div class="register_form">
                <div class="text-center mb-4">
                    <img src="/network-management/assets/img/logo1.png" alt="Logo" style="max-width: 100px;">
                </div>
                <h2 class="text-center text-uppercase mb-4">Đăng kí</h2>
                <?php if (!empty($success)) : ?>
                    <div class="alert alert-success text-center"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (!empty($error)) : ?>
                    <div class="alert alert-danger text-center"><?php echo $error; ?></div>
                <?php endif; ?>
                <form action="/network-management/index.php?route=register" method="POST" novalidate>
                    <div class="form-group mb-3">
                        <label for="fullname">Họ và tên</label>
                        <input type="text" name="fullname" id="fullname" class="form-control" placeholder="Nhập họ và tên của bạn" required>
                        <div class="invalid-feedback">Vui lòng nhập họ và tên của bạn.</div>
                    </div>
                    <div class="form-group mb-3">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" class="form-control" placeholder="Nhập địa chỉ email của bạn" required oninput="autoFillUsername()">
                        <div class="invalid-feedback">Vui lòng nhập địa chỉ email hợp lệ.</div>
                    </div>
                    <div class="form-group mb-3">
                        <label for="username">Tên tài khoản</label>
                        <input type="text" id="username" class="form-control" placeholder="Hệ thống sử dụng tên đăng nhập là phần đầu email" readonly>
                    </div>
                    <div class="form-group mb-3">
                        <label>Loại nhân viên</label>
                        <div class="form-check form-check-inline ms-3">
                            <input type="radio" name="role" id="staff" value="staff" class="form-check-input" required>
                            <label for="staff" class="form-check-label">Nhân viên</label>
                        </div>
                        <div class="form-check form-check-inline ps-3">
                            <input type="radio" name="role" id="it" value="technician" class="form-check-input" required>
                            <label for="it" class="form-check-label">Nhân viên kỹ thuật</label>
                        </div>
                        <div class="invalid-feedback">Vui lòng chọn loại nhân viên.</div>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary" id="registerBtn">Đăng kí</button>
                    </div>
                    <div class="text-center mt-3">
                        <span>Đã có tài khoản? <a href="/network-management/index.php?route=login">Đăng nhập</a></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function autoFillUsername() {
            const emailInput = document.getElementById('email').value;
            const usernameInput = document.getElementById('username');
            const username = emailInput.split('@')[0];
            usernameInput.value = username || '';
        }

        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.getElementById('registerBtn');
            btn.disabled = true;
            btn.innerHTML = 'Đang đăng kí...';

            let isValid = true;

            const fullname = document.getElementById('fullname');
            if (!fullname.value.trim()) {
                fullname.classList.add('is-invalid');
                isValid = false;
            } else {
                fullname.classList.remove('is-invalid');
            }

            const email = document.getElementById('email');
            if (!email.value.trim() || !email.value.includes('@')) {
                email.classList.add('is-invalid');
                isValid = false;
            } else {
                email.classList.remove('is-invalid');
            }

            const role = document.querySelector('input[name="role"]:checked');
            if (!role) {
                document.querySelectorAll('input[name="role"]').forEach(input => {
                    input.classList.add('is-invalid');
                });
                isValid = false;
            } else {
                document.querySelectorAll('input[name="role"]').forEach(input => {
                    input.classList.remove('is-invalid');
                });
            }

            if (!isValid) {
                btn.disabled = false;
                btn.innerHTML = 'Đăng kí';
            }
            return isValid;
        });
    </script>
</body>
</html>