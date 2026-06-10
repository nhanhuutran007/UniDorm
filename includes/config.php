<?php
/**
 * UniDorm – Project Configuration
 * path: includes/config.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// ====== BASE_URL AUTO DETECTION ======
if (!defined('BASE_URL')) {
    // Tự động nhận diện BASE_URL
    $baseUrl = '';
    
    // Nếu đang chạy trên localhost (XAMPP)
    if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
        $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
        $projectRoot = str_replace('\\', '/', dirname(__DIR__));
        $baseUrl = str_replace($docRoot, '', $projectRoot);
    } else {
        // Khi deploy lên hosting/cPanel và dùng .htaccess trỏ domain vào thư mục
        // thì BASE_URL phải là rỗng để domain chính hiển thị trực tiếp.
        $baseUrl = ''; 
    }
    
    // Đảm bảo có dấu '/' ở đầu nếu không rỗng
    if (!empty($baseUrl) && $baseUrl[0] !== '/') {
        $baseUrl = '/' . $baseUrl;
    }
    
    // Đảm bảo không có dấu '/' ở cuối
    $baseUrl = rtrim($baseUrl, '/');
    
    define('BASE_URL', $baseUrl);
}

// ====== PATH CONSTANTS ======
if (!defined('ROOT_PATH')) define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));
if (!defined('LAYOUT_PATH')) define('LAYOUT_PATH', ROOT_PATH . '/views/layout');
if (!defined('MODELS_PATH')) define('MODELS_PATH', ROOT_PATH . '/app/models');
