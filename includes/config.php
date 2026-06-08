<?php
/**
 * UniDorm – Project Configuration
 * path: includes/config.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// ====== BASE_URL AUTO DETECTION ======
if (!defined('BASE_URL')) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host     = $_SERVER['HTTP_HOST'];
    
    // Get the directory of UniDorm relative to document root
    // Normalizing slashes for Windows compatibility
    $docRoot    = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $currentDir = str_replace('\\', '/', dirname(__DIR__)); // Points to project root (UniDorm)
    
    $baseUrl    = str_replace($docRoot, '', $currentDir);
    
    // Ensure it starts with / and doesn't end with /
    $baseUrl = '/' . ltrim($baseUrl, '/');
    $baseUrl = rtrim($baseUrl, '/');
    
    // If base URL is just "/" or empty, set it to empty string for root domain
    if ($baseUrl === '/' || $baseUrl === '') {
        $baseUrl = '';
    }
    
    // For local dev, we use relative-root-path (like /UniDorm) or absolute URL
    // Here we use the relative-root-path as it's more portable for XAMPP
    define('BASE_URL', $baseUrl);
}

// ====== PATH CONSTANTS ======
if (!defined('ROOT_PATH')) define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));
if (!defined('LAYOUT_PATH')) define('LAYOUT_PATH', ROOT_PATH . '/views/layout');
if (!defined('MODELS_PATH')) define('MODELS_PATH', ROOT_PATH . '/app/models');
