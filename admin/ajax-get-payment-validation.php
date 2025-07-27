<?php
/**
 * AJAX Payment Validation Check Endpoint
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
    
    if (!$order_id) {
        throw new Exception('Order ID is required');
    }
    
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Initialize payment validator
    $validator = new PaymentValidationHelper($conn);
    
    // Get payment validation status
    $validation = $validator->validateOrderForProcessing($order_id);
    
    // Get payment summary
    $paymentSummary = $validator->getOrderPaymentSummary($order_id);
    
    echo json_encode([
        'success' => true,
        'validation' => $validation,
        'payment_summary' => $paymentSummary
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
