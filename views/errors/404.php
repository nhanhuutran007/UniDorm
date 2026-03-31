<?php
/**
 * UniDorm – 404 Error Page
 * path: views/errors/404.php
 */
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 – Không tìm thấy trang | UniDorm</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #f0f4ff, #e8f0fe);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        margin: 0;
    }
    .wrap { text-align: center; max-width: 440px; }
    .code {
        font-size: 120px;
        font-weight: 700;
        line-height: 1;
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 8px;
    }
    h2 { font-size: 22px; font-weight: 700; color: #1f2937; margin-bottom: 8px; }
    p  { color: #6b7280; font-size: 14px; margin-bottom: 28px; }
    .btn-home {
        display: inline-block;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff; text-decoration: none;
        border-radius: 10px; padding: 12px 28px;
        font-weight: 600; font-size: 14px;
        transition: opacity .2s;
    }
    .btn-home:hover { opacity: .9; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="code">404</div>
    <h2>Trang không tồn tại</h2>
    <p>Trang bạn tìm kiếm có thể đã bị di chuyển, xóa, hoặc bạn chưa có quyền truy cập.</p>
    <a href="/UniDorm/" class="btn-home">⬅ Về trang chủ</a>
</div>
</body>
</html>