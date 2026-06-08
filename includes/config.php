<?php
/**
 * UniDorm – Project Configuration
 * path: includes/config.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// ====== BASE_URL AUTO DETECTION ======
if (!defined('BASE_URL')) {
    // Lấy thư mục chứa script hiện tại (ví dụ: /UniDorm hoặc /)
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $scriptDir = str_replace('\\', '/', $scriptDir);
    
    // Nếu script nằm ở gốc, $scriptDir sẽ là '/'
    // Nếu nằm trong thư mục UniDorm, sẽ là '/UniDorm'
    $baseUrl = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
    
    // Đảm bảo không có dấu '/' ở cuối
    $baseUrl = rtrim($baseUrl, '/');
    
    define('BASE_URL', $baseUrl);
}

// ====== PATH CONSTANTS ======
if (!defined('ROOT_PATH')) define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));
if (!defined('LAYOUT_PATH')) define('LAYOUT_PATH', ROOT_PATH . '/views/layout');
if (!defined('MODELS_PATH')) define('MODELS_PATH', ROOT_PATH . '/app/models');
