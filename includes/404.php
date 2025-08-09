<?php
header("HTTP/1.0 404 Not Found");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Trang Không Tìm Thấy</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.svg">
    <style>
    html,
    body {
        margin: 0;
        padding: 0;
        height: 100%;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #333;
    }

    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    }

    .container {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 20px;
        max-width: 600px;
        margin: auto;
        animation: fadeIn 1s ease-in-out;
    }

    h1 {
        font-size: 6rem;
        margin: 0;
        color: #ff6b6b;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
    }

    p {
        font-size: 1.2rem;
        margin: 20px 0;
        line-height: 1.6;
        color: #555;
    }

    .btn-home {
        display: inline-block;
        padding: 12px 30px;
        background-color: #007bff;
        color: #fff;
        text-decoration: none;
        border-radius: 50px;
        font-size: 1rem;
        font-weight: 600;
        transition: background-color 0.3s, transform 0.2s;
    }

    .btn-home:hover {
        background-color: #0056b3;
        transform: translateY(-3px);
    }

    .illustration {
        max-width: 100%;
        height: auto;
        margin-bottom: 20px;
    }

    footer {
        background-color: #333;
        color: #fff;
        text-align: center;
        padding: 20px 0;
        width: 100%;
        font-size: 0.9rem;
        line-height: 1.6;
    }

    footer p {
        margin: 5px 0;
        color: #ccc;
    }

    footer a {
        color: #007bff;
        text-decoration: none;
        font-weight: 600;
    }

    footer a:hover {
        color: #0056b3;
        text-decoration: underline;
    }

    @keyframes fadeIn {
        0% {
            opacity: 0;
            transform: translateY(20px);
        }

        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 600px) {
        h1 {
            font-size: 4rem;
        }

        p {
            font-size: 1rem;
        }

        .illustration {
            width: 80%;
        }
    }
    </style>
</head>

<body>
    <div class="container">
        <div>
            <h1>404</h1>
            <p>Xin lỗi, trang bạn đang tìm kiếm không tồn tại hoặc đã bị di chuyển. Hãy quay lại trang chủ của JHTs!</p>
            <a href="index.php" class="btn-home">Quay lại trang chủ</a>
        </div>
    </div>
    <footer>
        <p>© Copyright <strong>JHTs</strong>. All Rights Reserved.</p>
        <p>Designed by <a href="#">JHTs</a></p>
    </footer>

    <script>
    // Thêm hiệu ứng nhẹ khi tải trang
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.querySelector('.container');
        container.style.opacity = '0';
        setTimeout(() => {
            container.style.opacity = '1';
        }, 100);
    });
    </script>
</body>

</html>