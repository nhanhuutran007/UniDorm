<?php
/**
 * UniDorm – Project Configuration
 * path: includes/config.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// ====== BASE_URL AUTO DETECTION ======
if (!defined('BASE_URL')) {
    // Tự động nhận diện BASE_URL dựa trên môi trường
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Phát hiện môi trường
    $isLocalhost = in_array($host, ['localhost', '127.0.0.1', 'localhost:8080', 'localhost:80']);
    
    if ($isLocalhost) {
        // LOCALHOST: Tự động tìm đường dẫn thư mục project bằng cách so sánh DOCUMENT_ROOT và thư mục gốc của project
        $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        $projRoot = str_replace('\\', '/', dirname(__DIR__));
        $baseUrl = rtrim(str_replace($docRoot, '', $projRoot), '/');
    } else {
        // HOSTING: Domain trỏ thẳng vào thư mục UniDorm qua .htaccess redirect
        // BASE_URL = rỗng (domain.com hiển thị trực tiếp)
        $baseUrl = '';
    }
    
    define('BASE_URL', $baseUrl);
}

// ====== PATH CONSTANTS ======
if (!defined('ROOT_PATH')) define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));
if (!defined('LAYOUT_PATH')) define('LAYOUT_PATH', ROOT_PATH . '/views/layout');
if (!defined('MODELS_PATH')) define('MODELS_PATH', ROOT_PATH . '/app/models');
