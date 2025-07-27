<?php
/**
 * AJAX Order Details Handler
 * This script returns order details in JSON format for AJAX requests
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Start session for user authentication
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Initialize response
$response = [
    'success' => false,
    'data' => null,
    'error' => null
];

// Get order ID from request
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($orderId <= 0) {
    $response['error'] = 'Invalid order ID';
    echo json_encode($response);
    exit;
}

// Create database connection
$database = new Database();
$conn = $database->getConnection();

try {
    // Get order information
    $orderSql = "SELECT o.*, o.total_price as total, p.payment_method, IFNULL(p.status, 'pending') as payment_status, 
                p.transaction_id, p.payment_date as paid_at
                FROM orders o
                LEFT JOIN payments p ON o.id = p.order_id
                WHERE o.id = :order_id";
    
    $stmt = $conn->prepare($orderSql);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate total from order items
        $itemsQuery = "SELECT SUM(quantity * price) as items_total FROM order_items WHERE order_id = :order_id";
        $stmtItems = $conn->prepare($itemsQuery);
        $stmtItems->bindParam(':order_id', $orderId);
        $stmtItems->execute();
        
        if ($stmtItems->rowCount() > 0) {
            $itemsRow = $stmtItems->fetch(PDO::FETCH_ASSOC);
            $itemsTotal = $itemsRow['items_total'];
            $order['calculated_total'] = $itemsTotal;
        }
        
        // Get customer information
        $customerSql = "SELECT * FROM users WHERE id = :user_id";
        $stmt = $conn->prepare($customerSql);
        $stmt->bindParam(':user_id', $order['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            $order['customer'] = $customer;
        }
        
        // Get order items
        $itemsSql = "SELECT oi.*, p.name, p.image, oi.customization_image, oi.customization_text
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = :order_id";
        
        $stmt = $conn->prepare($itemsSql);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add image URLs to order items
            foreach ($orderItems as &$item) {
                if (!empty($item['customization_image'])) {
                    $item['image_url'] = '../uploads/customizations/' . $item['customization_image'];
                } else {
                    $item['image_url'] = !empty($item['image']) 
                        ? '../uploads/products/' . $item['image'] 
                        : '../uploads/products/placeholder.jpg';
                }
            }
            
            $order['items'] = $orderItems;
        } else {
            $order['items'] = [];
        }
        
        $response['success'] = true;
        $response['data'] = $order;
    } else {
        $response['error'] = 'Order not found';
    }
} catch (PDOException $e) {
    $response['error'] = 'Database error: ' . $e->getMessage();
}

// Return response as JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;