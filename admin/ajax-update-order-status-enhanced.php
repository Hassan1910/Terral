<?php
/**
 * Enhanced Order Status Update with Payment Validation
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
    $new_status = $_POST['status'] ?? null;
    
    if (!$order_id || !$new_status) {
        throw new Exception('Order ID and status are required');
    }
    
    // Validate status
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'canceled'];
    if (!in_array($new_status, $valid_statuses)) {
        throw new Exception('Invalid status value');
    }
    
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Initialize payment validator
    $validator = new PaymentValidationHelper($conn);
    
    // Check if status update is allowed
    $validation = $validator->canUpdateOrderStatus($order_id, $new_status);
    
    if (!$validation['allowed']) {
        echo json_encode([
            'success' => false,
            'error' => $validation['message'],
            'code' => $validation['code'],
            'payment_required' => true
        ]);
        exit;
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Update order status
    $query = "UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :order_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':status', $new_status);
    $stmt->bindParam(':order_id', $order_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update order status');
    }
    
    // Log the status update
    $validator->logValidationEvent('admin_status_update', [
        'order_id' => $order_id,
        'new_status' => $new_status,
        'admin_id' => $_SESSION['user_id'],
        'validation_passed' => true
    ]);
    
    // Commit transaction
    $conn->commit();
    
    // Get updated order details for response
    $orderQuery = "SELECT o.*, p.status as payment_status 
                   FROM orders o 
                   LEFT JOIN payments p ON o.id = p.order_id 
                   WHERE o.id = :order_id";
    $stmt = $conn->prepare($orderQuery);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Order status updated successfully',
        'order' => [
            'id' => $order['id'],
            'status' => $order['status'],
            'payment_status' => $order['payment_status'] ?? 'pending'
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
