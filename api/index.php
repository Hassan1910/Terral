<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set error reporting based on environment
// Load .env file if available
if (file_exists(dirname(__DIR__) . '/.env')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
    
    // Set error reporting based on DEBUG setting
    if (isset($_ENV['DEBUG']) && $_ENV['DEBUG'] === 'true') {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(0);
    }
} else {
    // Default to debug mode in development
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Define paths
define('ROOT_PATH', dirname(__FILE__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('PRODUCTS_UPLOAD_PATH', UPLOAD_PATH . '/products');
define('CUSTOM_UPLOAD_PATH', UPLOAD_PATH . '/customizations');
define('INVOICES_UPLOAD_PATH', UPLOAD_PATH . '/invoices');

// Create upload directories if they don't exist
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}
if (!file_exists(PRODUCTS_UPLOAD_PATH)) {
    mkdir(PRODUCTS_UPLOAD_PATH, 0777, true);
}
if (!file_exists(CUSTOM_UPLOAD_PATH)) {
    mkdir(CUSTOM_UPLOAD_PATH, 0777, true);
}
if (!file_exists(INVOICES_UPLOAD_PATH)) {
    mkdir(INVOICES_UPLOAD_PATH, 0777, true);
}

// Include database and config files
include_once ROOT_PATH . '/config/Database.php';
include_once ROOT_PATH . '/config/JwtConfig.php';

// Include helper classes
include_once ROOT_PATH . '/helpers/AuthHelper.php';
include_once ROOT_PATH . '/helpers/ValidationHelper.php';
include_once ROOT_PATH . '/helpers/NotificationHelper.php';
include_once ROOT_PATH . '/helpers/LoggerHelper.php';

// Initialize logger
$logger = new LoggerHelper();

// Record start time for performance logging
$api_start_time = microtime(true);

// Try to handle the request, catch any exceptions
try {
    // Get request URI
    $request_uri = $_SERVER['REQUEST_URI'];
    $base_path = isset($_ENV['API_URL']) ? parse_url($_ENV['API_URL'], PHP_URL_PATH) : '/Terral2/api';
    $route = str_replace($base_path, '', $request_uri);
    
    // Log API request
    LoggerHelper::logApiRequest($_SERVER['REQUEST_METHOD'], $route, $_REQUEST);
    
    // Route the request
    include_once ROOT_PATH . '/routes/Routes.php';
    $routes = new Routes();
    $routes->processRequest($route, $_SERVER['REQUEST_METHOD']);
    
    // Calculate execution time
    $execution_time = round((microtime(true) - $api_start_time) * 1000, 2);
    LoggerHelper::logApiResponse(http_response_code(), null, $execution_time);
} catch (Exception $e) {
    // Log error
    LoggerHelper::error('API Error: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Determine status code
    $status_code = ($e instanceof PDOException) ? 503 : 500;
    
    // Send error response
    http_response_code($status_code);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    // Calculate execution time
    $execution_time = round((microtime(true) - $api_start_time) * 1000, 2);
    LoggerHelper::logApiResponse($status_code, [
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ], $execution_time);
}
?> 