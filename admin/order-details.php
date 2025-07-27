<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Include necessary files
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/api/config/Database.php';

// Get order ID from URL
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($orderId <= 0) {
    header('Location: orders.php');
    exit;
}

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Get order details
try {
    // Get order information with customer and payment details
    $orderQuery = "SELECT o.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                   u.email as customer_email,
                   u.phone as customer_phone,
                   u.address as customer_address,
                   p.payment_method,
                   p.status as payment_status,
                   p.transaction_id,
                   p.payment_date
                   FROM orders o
                   LEFT JOIN users u ON o.user_id = u.id
                   LEFT JOIN payments p ON o.id = p.order_id
                   WHERE o.id = :order_id";
    
    $stmt = $db->prepare($orderQuery);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        header('Location: orders.php');
        exit;
    }
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get order items
    $itemsQuery = "SELECT oi.*, p.name as product_name, p.image as product_image
                   FROM order_items oi
                   LEFT JOIN products p ON oi.product_id = p.id
                   WHERE oi.order_id = :order_id";
    
    $stmt = $db->prepare($itemsQuery);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];
    $updateQuery = "UPDATE orders SET status = :status WHERE id = :order_id";
    $stmt = $db->prepare($updateQuery);
    $stmt->bindParam(':status', $newStatus);
    $stmt->bindParam(':order_id', $orderId);
    
    if ($stmt->execute()) {
        $order['status'] = $newStatus;
        $successMessage = 'Order status updated successfully!';
    } else {
        $errorMessage = 'Failed to update order status.';
    }
}

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_status'])) {
    $newPaymentStatus = $_POST['payment_status'];
    
    // Check if payment record exists
    $checkPaymentQuery = "SELECT id FROM payments WHERE order_id = :order_id";
    $stmt = $db->prepare($checkPaymentQuery);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Update existing payment record
        $updatePaymentQuery = "UPDATE payments SET status = :status WHERE order_id = :order_id";
        $stmt = $db->prepare($updatePaymentQuery);
        $stmt->bindParam(':status', $newPaymentStatus);
        $stmt->bindParam(':order_id', $orderId);
        
        if ($stmt->execute()) {
            $order['payment_status'] = $newPaymentStatus;
            $successMessage = 'Payment status updated successfully!';
        } else {
            $errorMessage = 'Failed to update payment status.';
        }
    } else {
        // Create new payment record if it doesn't exist
        $insertPaymentQuery = "INSERT INTO payments (order_id, status, payment_method, created_at) VALUES (:order_id, :status, 'manual', NOW())";
        $stmt = $db->prepare($insertPaymentQuery);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->bindParam(':status', $newPaymentStatus);
        
        if ($stmt->execute()) {
            $order['payment_status'] = $newPaymentStatus;
            $successMessage = 'Payment status updated successfully!';
        } else {
            $errorMessage = 'Failed to create payment record.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo $orderId; ?> - Terral Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --secondary: #e74c3c;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --background: #f8f9fa;
            --white: #ffffff;
            --gray-light: #ecf0f1;
            --gray: #bdc3c7;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --border-radius: 8px;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--text-dark);
            color: var(--white);
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--white);
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            transition: var(--transition);
            display: flex;
            align-items: center;
        }
        
        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
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
            margin-left: 250px;
            padding: 30px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info .user-name {
            margin-right: 15px;
        }
        
        .user-info .logout-btn {
            background-color: var(--danger);
            color: var(--white);
            padding: 8px 15px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .user-info .logout-btn:hover {
            background-color: #c0392b;
        }
        
        /* Back button */
        .back-btn {
            background-color: var(--gray);
            color: var(--text-dark);
            padding: 10px 20px;
            border-radius: var(--border-radius);
            transition: var(--transition);
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .back-btn:hover {
            background-color: #95a5a6;
        }
        
        /* Alert messages */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Order details grid */
        .order-details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
        }
        
        .card-header {
            border-bottom: 1px solid var(--gray-light);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        /* Order info */
        .order-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-weight: 500;
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        /* Status badges */
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: capitalize;
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
            background-color: var(--success);
            color: var(--white);
        }
        
        .status-cancelled {
            background-color: var(--danger);
            color: var(--white);
        }
        
        .status-completed {
            background-color: var(--success);
            color: var(--white);
        }
        
        .status-failed {
            background-color: var(--danger);
            color: var(--white);
        }
        
        .status-refunded {
            background-color: var(--warning);
            color: var(--white);
        }
        
        /* Status update form */
        .status-update {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-light);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 14px;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        /* Order items */
        .order-items {
            grid-column: 1 / -1;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .items-table th,
        .items-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .items-table th {
            background-color: var(--gray-light);
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        .product-details h4 {
            margin: 0;
            font-size: 1rem;
            color: var(--text-dark);
        }
        
        .product-details p {
            margin: 5px 0 0 0;
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .customization {
            margin-top: 10px;
            padding: 10px;
            background-color: var(--gray-light);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
        }
        
        .customization strong {
            color: var(--text-dark);
        }
        
        .customization-image {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-top: 10px;
        }
        
        /* Order summary */
        .order-summary {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--gray-light);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .summary-row.total {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--text-dark);
            border-top: 1px solid var(--gray-light);
            padding-top: 10px;
            margin-top: 10px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .order-details-grid {
                grid-template-columns: 1fr;
            }
            
            .order-info {
                grid-template-columns: 1fr;
            }
            
            .product-info {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-logo">
                TERRAL ADMIN
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="products.php">
                        <i class="fas fa-box"></i> <span>Products</span>
                    </a>
                </li>
                <li>
                    <a href="orders.php" class="active">
                        <i class="fas fa-shopping-cart"></i> <span>Orders</span>
                    </a>
                </li>
                <li>
                    <a href="customers.php">
                        <i class="fas fa-users"></i> <span>Customers</span>
                    </a>
                </li>
                <li>
                    <a href="categories.php">
                        <i class="fas fa-tags"></i> <span>Categories</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php">
                        <i class="fas fa-chart-bar"></i> <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php">
                        <i class="fas fa-cog"></i> <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <a href="orders.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
            
            <div class="page-header">
                <h1 class="page-title">Order #<?php echo $orderId; ?></h1>
                <div class="user-info">
                    <span class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Admin'); ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errorMessage)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php else: ?>
                <div class="order-details-grid">
                    <!-- Order Information -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Order Information</h2>
                        </div>
                        
                        <div class="order-info">
                            <div class="info-item">
                                <span class="info-label">Order ID</span>
                                <span class="info-value">#<?php echo $order['id']; ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Order Date</span>
                                <span class="info-value"><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="info-value">
                                    <span class="status status-<?php echo strtolower($order['status']); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Total Amount</span>
                                <span class="info-value">KSh <?php echo number_format($order['total_price'], 2); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Payment Method</span>
                                <span class="info-value"><?php echo ucfirst($order['payment_method'] ?? 'N/A'); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Payment Status</span>
                                <span class="info-value">
                                    <span class="status status-<?php echo strtolower($order['payment_status'] ?? 'pending'); ?>">
                                        <?php echo ucfirst($order['payment_status'] ?? 'Pending'); ?>
                                    </span>
                                </span>
                            </div>
                            
                            <?php if ($order['transaction_id']): ?>
                                <div class="info-item">
                                    <span class="info-label">Transaction ID</span>
                                    <span class="info-value"><?php echo htmlspecialchars($order['transaction_id']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['payment_date']): ?>
                                <div class="info-item">
                                    <span class="info-label">Payment Date</span>
                                    <span class="info-value"><?php echo date('M d, Y H:i', strtotime($order['payment_date'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Customer Information -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Customer Information</h2>
                        </div>
                        
                        <div class="order-info">
                            <div class="info-item">
                                <span class="info-label">Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['customer_email'] ?? 'N/A'); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Phone</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="info-label">Address</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['customer_address'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                        
                        <!-- Status Update Form - Only show when payment is completed -->
                        <?php if (($order['payment_status'] ?? '') === 'completed'): ?>
                        <div class="status-update">
                            <h3>Update Order Status</h3>
                            <form method="POST">
                                <div class="form-group">
                                    <label for="status">New Status</label>
                                    <select id="status" name="status" class="form-control" required>
                                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_status" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Status
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <div class="status-update">
                            <div class="alert alert-info" style="background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 10px; border-radius: 5px; margin-top: 15px;">
                                <i class="fas fa-info-circle"></i> Order status can only be updated after payment is completed.
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Payment Status Update Form -->
                        <div class="status-update">
                            <h3>Update Payment Status</h3>
                            <form method="POST">
                                <div class="form-group">
                                    <label for="payment_status">New Payment Status</label>
                                    <select id="payment_status" name="payment_status" class="form-control" required>
                                        <option value="pending" <?php echo ($order['payment_status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo ($order['payment_status'] ?? '') === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="completed" <?php echo ($order['payment_status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="failed" <?php echo ($order['payment_status'] ?? '') === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                        <option value="refunded" <?php echo ($order['payment_status'] ?? '') === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                        <option value="cancelled" <?php echo ($order['payment_status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_payment_status" class="btn btn-primary">
                                    <i class="fas fa-credit-card"></i> Update Payment Status
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="card order-items">
                        <div class="card-header">
                            <h2 class="card-title">Order Items</h2>
                        </div>
                        
                        <?php if (empty($orderItems)): ?>
                            <p>No items found for this order.</p>
                        <?php else: ?>
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $subtotal = 0;
                                    foreach ($orderItems as $item): 
                                        $itemTotal = $item['price'] * $item['quantity'];
                                        $subtotal += $itemTotal;
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="product-info">
                                                    <?php if ($item['product_image']): ?>
                                                        <img src="../api/uploads/products/<?php echo htmlspecialchars($item['product_image']); ?>" 
                                                             alt="Product Image" class="product-image">
                                                    <?php else: ?>
                                                        <img src="../assets/images/placeholder.jpg" 
                                                             alt="No Image" class="product-image">
                                                    <?php endif; ?>
                                                    
                                                    <div class="product-details">
                                                        <h4><?php echo htmlspecialchars($item['product_name'] ?? 'Product #' . $item['product_id']); ?></h4>
                                                        
                                                        <?php if ($item['customization_text'] || $item['customization_image'] || $item['customization_color'] || $item['customization_size']): ?>
                                            <div class="customization">
                                                <strong>Customization:</strong><br>
                                                <?php if ($item['customization_color']): ?>
                                                    <p>Color: <?php echo htmlspecialchars($item['customization_color']); ?></p>
                                                <?php endif; ?>
                                                <?php if ($item['customization_size']): ?>
                                                    <p>Size: <?php echo htmlspecialchars($item['customization_size']); ?></p>
                                                <?php endif; ?>
                                                <?php if ($item['customization_text']): ?>
                                                    <p>Text: <?php echo htmlspecialchars($item['customization_text']); ?></p>
                                                <?php endif; ?>
                                                <?php if ($item['customization_image']): ?>
                                                    <img src="../uploads/customizations/<?php echo htmlspecialchars($item['customization_image']); ?>" 
                                                         alt="Customization" class="customization-image">
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>KSh <?php echo number_format($item['price'], 2); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>KSh <?php echo number_format($itemTotal, 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div class="order-summary">
                                <div class="summary-row">
                                    <span>Subtotal:</span>
                                    <span>KSh <?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total:</span>
                                    <span>KSh <?php echo number_format($order['total_price'], 2); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>