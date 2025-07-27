<?php
/**
 * AJAX Status Update Validation Endpoint
 */

// Start session and check if admin
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Include necessary files
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/helpers/PaymentValidationHelper.php';

// Set content type
header('Content-Type: application/json');

try {
    // Get posted data
    $order_id = $_POST['order_id'] ?? null;
    $new_status = $_POST['new_status'] ?? null;
    
    if (!$order_id || !$new_status) {
        throw new Exception('Order ID and new status are required');
    }
    
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Initialize payment validator
    $validator = new PaymentValidationHelper($conn);
    
    // Check if status update is allowed
    $validation = $validator->canUpdateOrderStatus($order_id, $new_status);
    
    echo json_encode($validation);
    
} catch (Exception $e) {
    echo json_encode([
        'allowed' => false,
        'message' => $e->getMessage(),
        'code' => 'VALIDATION_ERROR'
    ]);
}
?>
