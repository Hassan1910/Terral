<?php
/**
 * Enhanced AJAX Payment Status Update Handler with Validation
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/helpers/PaymentValidationHelper.php';

// Start session for user authentication
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'error' => null
];

// Get data from request
$orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$newPaymentStatus = isset($_POST['payment_status']) ? $_POST['payment_status'] : '';
$transactionId = isset($_POST['transaction_id']) ? $_POST['transaction_id'] : '';

// Validate data
if ($orderId <= 0) {
    $response['error'] = 'Invalid order ID';
    echo json_encode($response);
    exit;
}

$validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded', 'canceled'];
if (!in_array($newPaymentStatus, $validPaymentStatuses)) {
    $response['error'] = 'Invalid payment status value';
    echo json_encode($response);
    exit;
}

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize payment validation helper
$validator = new PaymentValidationHelper($conn);

try {
    // Get current order status for validation
    $orderQuery = "SELECT payment_method, status FROM orders WHERE id = :order_id";
    $stmt = $conn->prepare($orderQuery);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $response['error'] = 'Order not found';
        echo json_encode($response);
        exit;
    }
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log payment status change attempt
    $validator->logValidationEvent('payment_status_update_attempt', [
        'order_id' => $orderId,
        'old_status' => 'checking',
        'new_status' => $newPaymentStatus,
        'admin_id' => $_SESSION['user_id'],
        'payment_method' => $order['payment_method']
    ]);

    // Generate transaction ID if needed
    if ($newPaymentStatus === 'paid' && empty($transactionId)) {
        $transactionId = 'MAN' . date('YmdHis') . rand(100, 999);
    }
    
    // Set paid date
    $paidAt = ($newPaymentStatus === 'paid') ? date('Y-m-d H:i:s') : null;
    
    // Calculate order total
    $itemsQuery = "SELECT SUM(quantity * price) as items_total FROM order_items WHERE order_id = :order_id";
    $stmtItems = $conn->prepare($itemsQuery);
    $stmtItems->bindParam(':order_id', $orderId);
    $stmtItems->execute();
    
    $itemsTotal = 0;
    if ($stmtItems->rowCount() > 0) {
        $itemsResult = $stmtItems->fetch(PDO::FETCH_ASSOC);
        $itemsTotal = $itemsResult['items_total'] ?? 0;
    }
    
    // Update order total if needed
    if ($itemsTotal > 0) {
        $updateTotalSql = "UPDATE orders SET total_price = :total WHERE id = :order_id";
        $stmtUpdateTotal = $conn->prepare($updateTotalSql);
        $stmtUpdateTotal->bindParam(':total', $itemsTotal);
        $stmtUpdateTotal->bindParam(':order_id', $orderId);
        $stmtUpdateTotal->execute();
    }
    
    // Check if payment record exists
    $checkSql = "SELECT id FROM payments WHERE order_id = :order_id LIMIT 1";
    $stmt = $conn->prepare($checkSql);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    
    // Begin transaction
    $conn->beginTransaction();
    
    if ($stmt->rowCount() > 0) {
        // Update existing payment record
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        $updateSql = "UPDATE payments SET 
                      status = :status, 
                      transaction_id = :transaction_id,
                      payment_date = :payment_date,
                      amount = :amount,
                      updated_at = NOW() 
                      WHERE id = :payment_id";
        $stmt = $conn->prepare($updateSql);
        $stmt->bindParam(':status', $newPaymentStatus);
        $stmt->bindParam(':transaction_id', $transactionId);
        $stmt->bindParam(':payment_date', $paidAt);
        $stmt->bindParam(':amount', $itemsTotal);
        $stmt->bindParam(':payment_id', $payment['id']);
    } else {
        // Create new payment record
        $updateSql = "INSERT INTO payments (
                      order_id, status, payment_method, transaction_id, 
                      payment_date, amount, created_at, updated_at) 
                      VALUES (
                      :order_id, :status, 'manual', :transaction_id,
                      :payment_date, :amount, NOW(), NOW())";
        $stmt = $conn->prepare($updateSql);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->bindParam(':status', $newPaymentStatus);
        $stmt->bindParam(':transaction_id', $transactionId);
        $stmt->bindParam(':payment_date', $paidAt);
        $stmt->bindParam(':amount', $itemsTotal);
    }
    
    // Execute payment update
    $result1 = $stmt->execute();
    
    // Update order payment status
    $updateOrderSql = "UPDATE orders SET 
                      payment_status = :payment_status, 
                      updated_at = NOW() 
                      WHERE id = :order_id";
    $stmt = $conn->prepare($updateOrderSql);
    $stmt->bindParam(':payment_status', $newPaymentStatus);
    $stmt->bindParam(':order_id', $orderId);
    $result2 = $stmt->execute();
    
    if ($result1 && $result2) {
        $conn->commit();
        
        // Log successful payment update
        $validator->logValidationEvent('payment_status_updated', [
            'order_id' => $orderId,
            'new_payment_status' => $newPaymentStatus,
            'transaction_id' => $transactionId,
            'admin_id' => $_SESSION['user_id'],
            'amount' => $itemsTotal
        ]);
        
        $response['success'] = true;
        $response['message'] = 'Payment status updated successfully';
        
        // Add payment validation summary to response
        $paymentSummary = $validator->getOrderPaymentSummary($orderId);
        $response['payment_summary'] = $paymentSummary;
        
    } else {
        $conn->rollBack();
        $response['error'] = 'Failed to update payment status';
    }
} catch (Exception $e) {
    if ($conn) {
        $conn->rollBack();
    }
    $response['error'] = 'Error: ' . $e->getMessage();
}

// Return response as JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;