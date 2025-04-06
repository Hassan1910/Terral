<?php
/**
 * Admin Order Details Page
 * 
 * This page allows administrators to view and manage details for a specific order.
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
    header('Location: /Terral2/login.php');
    exit;
}

// Initialize variables
$pageTitle = 'Order Details';
$order = null;
$orderItems = [];
$customer = null;
$errorMessage = '';
$successMessage = '';

// Get order ID from URL parameter
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check for success message in URL
if (isset($_GET['success']) && $_GET['success'] === 'payment_updated') {
    $successMessage = 'Payment status updated successfully!';
}

if ($orderId <= 0) {
    $errorMessage = 'Invalid order ID.';
}

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Process status update if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    try {
        $newStatus = $_POST['status'];
        $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'canceled'];
        
        if (!in_array($newStatus, $validStatuses)) {
            throw new Exception('Invalid status value.');
        }
        
        $updateSql = "UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :order_id";
        $stmt = $conn->prepare($updateSql);
        $stmt->bindParam(':status', $newStatus);
        $stmt->bindParam(':order_id', $orderId);
        
        if ($stmt->execute()) {
            $successMessage = 'Order status updated successfully!';
            
            // Refresh the page to show updated data
            header("Location: order-details.php?id={$orderId}&success=status_updated");
            exit;
        } else {
            $errorMessage = 'Failed to update order status.';
        }
    } catch (Exception $e) {
        $errorMessage = 'Error: ' . $e->getMessage();
    }
}

// Process payment status update if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_payment') {
    try {
        $newPaymentStatus = $_POST['payment_status'];
        $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded', 'canceled'];
        
        if (!in_array($newPaymentStatus, $validPaymentStatuses)) {
            throw new Exception('Invalid payment status value.');
        }
        
        // Get transaction ID from form or generate one if paid and empty
        $transactionId = isset($_POST['transaction_id']) ? $_POST['transaction_id'] : '';
        if ($newPaymentStatus === 'paid' && empty($transactionId)) {
            $transactionId = 'MAN' . date('YmdHis') . rand(100, 999); // Generate manual transaction ID
        }
        
        // Set paid date to current date if status is paid
        $paidAt = ($newPaymentStatus === 'paid') ? date('Y-m-d H:i:s') : null;
        
        // Get the order amount - directly calculate from order items
        $itemsQuery = "SELECT SUM(quantity * price) as items_total FROM order_items WHERE order_id = :order_id";
        $stmtItems = $conn->prepare($itemsQuery);
        $stmtItems->bindParam(':order_id', $orderId);
        $stmtItems->execute();
        
        // Get the total from order items
        $itemsTotal = 0;
        if ($stmtItems->rowCount() > 0) {
            $itemsResult = $stmtItems->fetch(PDO::FETCH_ASSOC);
            $itemsTotal = $itemsResult['items_total'] ?? 0;
        }
        
        // Use the items total for the payment amount
        $orderAmount = $itemsTotal;
        
        // Update the order total in the database if it's different
        if ($orderAmount > 0) {
            $updateTotalSql = "UPDATE orders SET total_price = :total WHERE id = :order_id";
            $stmtUpdateTotal = $conn->prepare($updateTotalSql);
            $stmtUpdateTotal->bindParam(':total', $orderAmount);
            $stmtUpdateTotal->bindParam(':order_id', $orderId);
            $stmtUpdateTotal->execute();
        }
        
        // Check if a payment record exists
        $checkSql = "SELECT id FROM payments WHERE order_id = :order_id LIMIT 1";
        $stmt = $conn->prepare($checkSql);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        
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
            $stmt->bindParam(':amount', $orderAmount);
            $stmt->bindParam(':payment_id', $payment['id']);
        } else {
            // Create new payment record if it doesn't exist
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
            $stmt->bindParam(':amount', $orderAmount);
        }
        
        // Also update the payment_status in orders table
        if ($stmt->execute()) {
            $updateOrderSql = "UPDATE orders SET 
                              payment_status = :payment_status, 
                              updated_at = NOW() 
                              WHERE id = :order_id";
            $stmt = $conn->prepare($updateOrderSql);
            $stmt->bindParam(':payment_status', $newPaymentStatus);
            $stmt->bindParam(':order_id', $orderId);
            
            if ($stmt->execute()) {
                $successMessage = 'Payment status updated successfully!';
                
                // Refresh the page to show updated information
                header("Location: order-details.php?id={$orderId}&success=payment_updated");
                exit;
            } else {
                $errorMessage = 'Failed to update order payment status.';
            }
        } else {
            $errorMessage = 'Failed to update payment status.';
        }
    } catch (Exception $e) {
        $errorMessage = 'Error: ' . $e->getMessage();
    }
}

// Fetch order details
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
        
        // Always calculate total from order items to ensure accuracy
        $itemsQuery = "SELECT SUM(quantity * price) as items_total FROM order_items WHERE order_id = :order_id";
        $stmtItems = $conn->prepare($itemsQuery);
        $stmtItems->bindParam(':order_id', $orderId);
        $stmtItems->execute();
        
        if ($stmtItems->rowCount() > 0) {
            $itemsRow = $stmtItems->fetch(PDO::FETCH_ASSOC);
            $itemsTotal = $itemsRow['items_total'];
            
            if ($itemsTotal) {
                // Set the calculated total
                $order['calculated_total'] = $itemsTotal;
                
                // Update the order total in the database if it doesn't match
                if (empty($order['total_price']) || floatval($order['total_price']) != floatval($itemsTotal)) {
                    $updateTotalSql = "UPDATE orders SET total_price = :total WHERE id = :order_id";
                    $stmtUpdateTotal = $conn->prepare($updateTotalSql);
                    $stmtUpdateTotal->bindParam(':total', $itemsTotal);
                    $stmtUpdateTotal->bindParam(':order_id', $orderId);
                    $stmtUpdateTotal->execute();
                    
                    // Update the order object with the correct total
                    $order['total_price'] = $itemsTotal;
                    $order['total'] = $itemsTotal;
                }
            }
        }
        
        // Calculate subtotal from order items
        $subtotalQuery = "SELECT SUM(quantity * price) as subtotal FROM order_items WHERE order_id = :order_id";
        $stmtSubtotal = $conn->prepare($subtotalQuery);
        $stmtSubtotal->bindParam(':order_id', $orderId);
        $stmtSubtotal->execute();
        
        if ($stmtSubtotal->rowCount() > 0) {
            $subtotalRow = $stmtSubtotal->fetch(PDO::FETCH_ASSOC);
            $subtotal = $subtotalRow['subtotal'] ?? 0;
        } else {
            $subtotal = 0;
        }
        
        // Initialize shipping_fee if not set
        if (!isset($order['shipping_fee'])) {
            $order['shipping_fee'] = 0;
        }
        
        // Initialize address fields if not set
        $addressFields = ['address_line1', 'address_line2', 'city', 'state', 'zip_code', 'country'];
        foreach ($addressFields as $field) {
            if (!isset($order[$field])) {
                $order[$field] = '';
            }
        }
        
        // Get customer information
        $customerSql = "SELECT * FROM users WHERE id = :user_id";
        $stmt = $conn->prepare($customerSql);
        $stmt->bindParam(':user_id', $order['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Get order items
        $itemsSql = "SELECT oi.*, p.name, p.image 
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
                $item['image_url'] = !empty($item['image']) 
                    ? '/Terral2/api/uploads/products/' . $item['image'] 
                    : '/Terral2/api/uploads/products/placeholder.jpg';
            }
        }
    } else {
        $errorMessage = 'Order not found.';
    }
} catch (PDOException $e) {
    $errorMessage = 'Database error: ' . $e->getMessage();
}

// Helper function for formatting currency
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return 'KSh ' . number_format($amount, 2);
    }
}

// Get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'badge-warning';
        case 'processing':
            return 'badge-info';
        case 'shipped':
            return 'badge-primary';
        case 'delivered':
            return 'badge-success';
        case 'canceled':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Terral Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --secondary: #e74c3c;
            --dark: #2c3e50;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --background: #f8f9fa;
            --white: #ffffff;
            --gray-light: #ecf0f1;
            --gray: #bdc3c7;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --border-radius: 4px;
            --box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-dark);
            background-color: var(--background);
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        .container-fluid {
            padding: 0;
            margin: 0;
            width: 100%;
            height: 100vh;
            display: flex;
        }
        
        /* Sidebar */
        .sidebar {
            width: 220px;
            background-color: var(--dark);
            color: var(--white);
            position: fixed;
            height: 100vh;
            padding-top: 20px;
            z-index: 1000;
        }
        
        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 600;
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 15px;
            text-align: center;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 10px 15px;
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, 
        .sidebar-menu a.active {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 220px;
            padding: 15px;
            width: calc(100% - 220px);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0 15px;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .user-actions {
            display: flex;
            align-items: center;
        }
        
        .welcome-text {
            margin-right: 15px;
        }
        
        .logout-btn {
            background-color: var(--secondary);
            color: white;
            padding: 6px 12px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-size: 0.85rem;
        }
        
        .logout-btn:hover {
            background-color: #c0392b;
            color: white;
            text-decoration: none;
        }
        
        /* Cards */
        .card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 15px;
            overflow: hidden;
            border: none;
        }
        
        .card-header {
            padding: 12px 15px;
            background-color: var(--gray-light);
            border-bottom: 1px solid var(--gray);
            font-weight: 600;
        }
        
        .card-body {
            padding: 15px;
        }
        
        /* Order Details */
        .order-info {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .order-summary, .payment-info {
            flex: 1;
            min-width: 250px;
        }
        
        .info-group {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--text-light);
            font-size: 0.85rem;
            margin-bottom: 2px;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        /* Timeline */
        .timeline {
            list-style: none;
            padding: 0;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 30px;
            margin-bottom: 15px;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--primary);
        }
        
        .timeline-item:after {
            content: '';
            position: absolute;
            left: 5px;
            top: 12px;
            bottom: -15px;
            width: 2px;
            background-color: var(--gray);
        }
        
        .timeline-item:last-child:after {
            display: none;
        }
        
        .timeline-date {
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        .timeline-content {
            font-weight: 500;
        }
        
        /* Tables */
        .table-responsive {
            flex: 1;
            overflow: auto;
        }
        
        .table {
            margin-bottom: 0;
            width: 100%;
        }
        
        .table th {
            background-color: var(--gray-light);
            border-top: none;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table th, .table td {
            vertical-align: middle;
            padding: 0.5rem 0.75rem;
        }
        
        .table img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        /* Status badges */
        .status {
            padding: 4px 8px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-pending {
            background-color: #FEF9C3;
            color: #854D0E;
        }
        
        .status-processing {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        
        .status-shipped {
            background-color: #DCFCE7;
            color: #166534;
        }
        
        .status-delivered {
            background-color: #4ADE80;
            color: white;
        }
        
        .status-canceled {
            background-color: #F87171;
            color: white;
        }
        
        .status-paid {
            background-color: #4ADE80;
            color: white;
        }
        
        .status-pending-payment {
            background-color: #FEF9C3;
            color: #854D0E;
        }
        
        /* Forms */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
            margin-bottom: 10px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 10px;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray);
            font-size: 0.9rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.25);
        }
        
        /* Buttons */
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            cursor: pointer;
            border: none;
            font-size: 0.9rem;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: var(--gray);
            border-color: var(--gray);
            color: var(--text-dark);
            padding: 8px 12px;
            border-radius: var(--border-radius);
            cursor: pointer;
            border: none;
            font-size: 0.9rem;
        }
        
        .btn-secondary:hover {
            background-color: #a6acaf;
            border-color: #a6acaf;
        }
        
        .btn-view {
            background-color: var(--primary);
            color: white;
            padding: 4px 10px;
            border-radius: var(--border-radius);
            font-size: 0.85rem;
        }
        
        .btn-view:hover {
            background-color: var(--primary-dark);
            color: white;
            text-decoration: none;
        }
        
        /* Back button */
        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--text-light);
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        
        .back-link i {
            margin-right: 5px;
        }
        
        .back-link:hover {
            color: var(--primary);
            text-decoration: none;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
                overflow: hidden;
            }
            
            .sidebar-logo {
                padding: 10px 5px;
                font-size: 1.2rem;
            }
            
            .sidebar-menu a span {
                display: none;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-left: 60px;
                width: calc(100% - 60px);
            }
            
            .order-summary, .payment-info {
                flex: 100%;
            }
            
            .info-group {
                margin-bottom: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-logo">
                <i class="fas fa-paint-brush"></i> Terral
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="products.php"><i class="fas fa-box"></i> <span>Products</span></a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> <span>Categories</span></a></li>
                <li><a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a></li>
                <li><a href="customers.php"><i class="fas fa-users"></i> <span>Customers</span></a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="main-header">
                <h1 class="page-title">Order Details</h1>
                <div class="user-actions">
                    <span class="welcome-text">Welcome, <?php echo isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'Admin'; ?></span>
                    <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            
            <a href="orders.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($order): ?>
                <!-- Order Info Cards -->
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Order Details Card -->
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-info-circle"></i> Order Summary
                            </div>
                            <div class="card-body">
                                <div class="order-info">
                                    <div class="order-summary">
                                        <div class="info-group">
                                            <div class="info-label">Order ID</div>
                                            <div class="info-value">#<?php echo $order['id']; ?></div>
                                        </div>
                                        <div class="info-group">
                                            <div class="info-label">Date</div>
                                            <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></div>
                                        </div>
                                        <div class="info-group">
                                            <div class="info-label">Status</div>
                                            <div class="info-value">
                                                <span class="status status-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="info-group">
                                            <div class="info-label">Customer</div>
                                            <div class="info-value">
                                                <?php echo htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')); ?>
                                            </div>
                                        </div>
                                        <div class="info-group">
                                            <div class="info-label">Email</div>
                                            <div class="info-value">
                                                <?php echo htmlspecialchars($order['email'] ?? ''); ?>
                                            </div>
                                        </div>
                                        <div class="info-group">
                                            <div class="info-label">Phone</div>
                                            <div class="info-value">
                                                <?php echo htmlspecialchars($order['phone'] ?? ''); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="payment-info">
                                        <div class="info-group">
                                            <div class="info-label">Subtotal</div>
                                            <div class="info-value"><?php echo formatCurrency($subtotal); ?></div>
                                        </div>
                                        <div class="info-group">
                                            <div class="info-label">Shipping</div>
                                            <div class="info-value"><?php echo formatCurrency($order['shipping_fee']); ?></div>
                                        </div>
                                        <div class="info-group">
                                            <div class="info-label">Total</div>
                                            <div class="info-value"><strong><?php echo formatCurrency($order['total']); ?></strong></div>
                                        </div>
                                        <div class="info-group">
                                            <div class="info-label">Shipping Address</div>
                                            <div class="info-value">
                                                <?php echo htmlspecialchars($order['address_line1'] ?? ''); ?><br>
                                                <?php if (!empty($order['address_line2'])): ?>
                                                    <?php echo htmlspecialchars($order['address_line2'] ?? ''); ?><br>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars(($order['city'] ?? '') . (!empty($order['city']) ? ', ' : '') . ($order['state'] ?? '') . ' ' . ($order['zip_code'] ?? '')); ?><br>
                                                <?php echo !empty($order['country']) ? htmlspecialchars($order['country'] ?? '') : ''; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Timeline -->
                                <h5>Order Timeline</h5>
                                <ul class="timeline">
                                    <li class="timeline-item">
                                        <div class="timeline-date"><?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?></div>
                                        <div class="timeline-content">Order placed</div>
                                    </li>
                                    <?php if ($order['status'] != 'pending'): ?>
                                        <li class="timeline-item">
                                            <div class="timeline-date"><?php echo date('M d, Y', strtotime($order['updated_at'])); ?></div>
                                            <div class="timeline-content">Status updated to <?php echo ucfirst($order['status'] ?? ''); ?></div>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Payment Info Card -->
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-credit-card"></i> Payment Information
                            </div>
                            <div class="card-body">
                                <div class="info-group">
                                    <div class="info-label">Payment Status</div>
                                    <div class="info-value">
                                        <span class="status status-<?php echo $order['payment_status'] === 'paid' ? 'paid' : 'pending-payment'; ?>">
                                            <?php echo ucfirst($order['payment_status'] ?? ''); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="info-group">
                                    <div class="info-label">Payment Method</div>
                                    <div class="info-value"><?php echo ucfirst($order['payment_method'] ?? ''); ?></div>
                                </div>
                                <?php if (!empty($order['transaction_id'])): ?>
                                    <div class="info-group">
                                        <div class="info-label">Transaction ID</div>
                                        <div class="info-value"><?php echo htmlspecialchars($order['transaction_id'] ?? ''); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($order['paid_at'])): ?>
                                    <div class="info-group">
                                        <div class="info-label">Paid On</div>
                                        <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($order['paid_at'])); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Update Status Card -->
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-edit"></i> Update Order Status
                            </div>
                            <div class="card-body">
                                <form action="" method="POST">
                                    <input type="hidden" name="action" value="update_status">
                                    <div class="form-group">
                                        <label for="status" class="form-label">Status</label>
                                        <select name="status" id="status" class="form-control">
                                            <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                            <option value="canceled" <?php echo $order['status'] === 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn-primary">Update Status</button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Update Payment Status Card -->
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-money-bill"></i> Update Payment Status
                            </div>
                            <div class="card-body">
                                <form action="" method="POST">
                                    <input type="hidden" name="action" value="update_payment">
                                    <div class="form-group">
                                        <label for="payment_status" class="form-label">Payment Status</label>
                                        <select name="payment_status" id="payment_status" class="form-control">
                                            <option value="pending" <?php echo $order['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="paid" <?php echo $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="transaction_id" class="form-label">Transaction ID (Optional)</label>
                                        <input type="text" name="transaction_id" id="transaction_id" class="form-control" value="<?php echo (!empty($order['transaction_id'])) ? htmlspecialchars($order['transaction_id']) : ''; ?>">
                                    </div>
                                    <button type="submit" class="btn-primary">Update Payment</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ordered Items Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-box"></i> Ordered Items
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <?php if (count($orderItems) > 0): ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th style="width: 80px;">Image</th>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Price</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orderItems as $item): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($item['image'])): ?>
                                                        <img src="../uploads/products/<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name'] ?? ''); ?>">
                                                    <?php else: ?>
                                                        <div class="no-image">No Image</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><strong><?php echo htmlspecialchars($item['name'] ?? ''); ?></strong></div>
                                                    <?php if (!empty($item['options'])): ?>
                                                        <div class="small text-muted">
                                                            <?php
                                                            $options = json_decode($item['options'], true);
                                                            if ($options && is_array($options)) {
                                                                foreach ($options as $key => $value) {
                                                                    echo '<div>' . htmlspecialchars(ucfirst($key ?? '')) . ': ' . htmlspecialchars($value ?? '') . '</div>';
                                                                }
                                                            }
                                                            ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $item['quantity']; ?></td>
                                                <td><?php echo formatCurrency($item['price']); ?></td>
                                                <td><?php echo formatCurrency($item['price'] * $item['quantity']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="text-right"><strong>Subtotal</strong></td>
                                            <td><?php echo formatCurrency($subtotal); ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-right"><strong>Shipping</strong></td>
                                            <td><?php echo formatCurrency($order['shipping_fee']); ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-right"><strong>Total</strong></td>
                                            <td><strong><?php echo formatCurrency($order['total']); ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No items found for this order.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Order not found.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bootstrap JS, jQuery, Popper.js -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>