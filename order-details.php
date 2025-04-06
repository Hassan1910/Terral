<?php
/**
 * Customer Order Details Page
 * 
 * This page allows customers to view details of their specific order.
 */

// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/helpers/ProductHelper.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php?redirect=account');
    exit;
}

// Set content type
header('Content-Type: text/html; charset=UTF-8');

// Initialize variables
$user = null;
$order = null;
$orderItems = [];
$error_message = '';
$subtotal = 0;

// Get order ID from URL parameter
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($orderId <= 0) {
    $error_message = 'Invalid order ID.';
}

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Get user information
try {
    // Get user details
    $query = "SELECT * FROM users WHERE id = :id LIMIT 0,1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error_message = 'User not found';
    }
    
    // Get order information (only if it belongs to the logged-in user)
    $orderSql = "SELECT o.*, p.payment_method, IFNULL(p.status, 'pending') as payment_status, 
                p.transaction_id, p.payment_date as paid_at
                FROM orders o
                LEFT JOIN payments p ON o.id = p.order_id
                WHERE o.id = :order_id AND o.user_id = :user_id";
    
    $stmt = $conn->prepare($orderSql);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Set default values if needed
        $order['shipping_fee'] = isset($order['shipping_fee']) ? $order['shipping_fee'] : 0;
        $order['total'] = isset($order['total_price']) ? $order['total_price'] : 0;
        
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
            
            // Initialize ProductHelper for image URLs
            $productHelper = new ProductHelper($conn);
            
            // Calculate subtotal and add image URLs
            foreach ($orderItems as &$item) {
                $subtotal += $item['price'] * $item['quantity'];
                $item['image_url'] = $productHelper->getProductImageUrl($item['image']);
            }
        }
    } else {
        $error_message = 'Order not found or does not belong to your account.';
    }
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// Helper function for formatting currency
function formatCurrency($amount) {
    return 'KSh ' . number_format($amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Terral</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/Terral2/assets/css/modern-theme.css">
    <style>
        /* Additional styles for order details page */
        
        /* Improved Header Styles */
        header {
            background-color: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
            position: relative;
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            transition: transform 0.3s ease;
        }
        
        .navbar-brand:hover {
            transform: scale(1.05);
        }
        
        .navbar-brand i {
            font-size: 1.8rem;
            margin-right: 0.5rem;
            color: var(--primary);
        }
        
        .navbar-nav {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .nav-item {
            margin: 0 1rem;
            position: relative;
        }
        
        .nav-link {
            color: var(--text-primary);
            font-weight: 500;
            text-decoration: none;
            padding: 0.5rem 0;
            position: relative;
            transition: color 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--primary);
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--primary);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after, .nav-link.active::after {
            width: 100%;
        }
        
        .navbar-icons {
            display: flex;
            align-items: center;
        }
        
        .navbar-icon {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-left: 1.5rem;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .navbar-icon:hover {
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--primary);
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: transform 0.3s ease;
        }
        
        .navbar-icon:hover .cart-count {
            transform: scale(1.1);
        }
        
        .navbar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-primary);
            cursor: pointer;
        }
        
        @media (max-width: 992px) {
            .navbar {
                flex-wrap: wrap;
                padding: 1rem;
            }
            
            .navbar-toggle {
                display: block;
                order: 3;
            }
            
            .navbar-nav {
                flex-basis: 100%;
                flex-direction: column;
                display: none;
                order: 4;
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid var(--light-3);
            }
            
            .navbar-nav.active {
                display: flex;
            }
            
            .nav-item {
                margin: 0.5rem 0;
            }
            
            .navbar-brand {
                order: 1;
            }
            
            .navbar-icons {
                order: 2;
                margin-left: auto;
            }
        }
        
        /* Breadcrumb improvements */
        .breadcrumb {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            margin: 1rem 0 2rem;
            font-size: 0.9rem;
            background-color: var(--light);
            border-radius: var(--border-radius);
            padding: 0.75rem 1.25rem;
        }
        
        .breadcrumb a {
            color: var(--text-secondary);
            transition: var(--transition-fast);
        }
        
        .breadcrumb a:hover {
            color: var(--primary);
        }
        
        .breadcrumb span {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .breadcrumb > *:not(:last-child)::after {
            content: '/';
            margin: 0 8px;
            color: var(--gray);
        }
        
        .order-details-container {
            max-width: 1000px;
            margin: 0 auto 50px;
            background-color: #ffffff;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            padding: 2rem;
            transition: transform 0.3s ease;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--light-3);
        }
        
        .order-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .order-id {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        
        .order-date {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .order-status {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .order-status i {
            margin-right: 0.5rem;
        }
        
        .status-pending {
            background-color: var(--warning);
            color: #856404;
        }
        
        .status-processing {
            background-color: var(--info);
            color: #fff;
        }
        
        .status-shipped {
            background-color: var(--primary-light);
            color: #fff;
        }
        
        .status-delivered {
            background-color: var(--success);
            color: #fff;
        }
        
        .status-canceled {
            background-color: var(--danger);
            color: #fff;
        }
        
        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .detail-box {
            padding: 1.5rem;
            background-color: var(--light);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .detail-box:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }
        
        .detail-box h3 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--light-3);
            color: var(--text-primary);
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }
        
        .detail-label {
            color: var(--text-secondary);
        }
        
        .detail-value {
            font-weight: 500;
            text-align: right;
        }
        
        .address-details p {
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }
        
        /* Order Items Table */
        .order-items {
            margin-top: 2rem;
        }
        
        .order-items-title {
            font-size: 1.3rem;
            margin-bottom: 1.25rem;
            position: relative;
            display: inline-block;
        }
        
        .order-items-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -8px;
            width: 50px;
            height: 3px;
            background-color: var(--primary);
            border-radius: 2px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }
        
        .items-table th, .items-table td {
            padding: 1rem;
            text-align: left;
        }
        
        .items-table th {
            background-color: var(--light-2);
            font-weight: 600;
            color: var(--text-primary);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        
        .items-table tr {
            background-color: #fff;
            transition: background-color 0.2s ease;
        }
        
        .items-table tr:hover {
            background-color: var(--light);
        }
        
        .items-table tr:not(:last-child) td {
            border-bottom: 1px solid var(--light-3);
        }
        
        .product-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s ease;
        }
        
        .product-img:hover {
            transform: scale(1.05);
        }
        
        .product-info {
            display: flex;
            align-items: center;
        }
        
        .product-img-wrapper {
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .product-details h4 {
            margin: 0 0 0.25rem;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .product-options {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }
        
        .order-summary {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }
        
        .summary-row.total {
            font-size: 1.25rem;
            font-weight: 700;
            padding-top: 1rem;
            border-top: 1px solid var(--light-3);
            margin-top: 1rem;
            color: var(--primary-dark);
        }
        
        .action-buttons {
            margin-top: 2rem;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            text-align: center;
            gap: 0.5rem;
        }
        
        .btn i {
            font-size: 0.9rem;
        }
        
        .btn-back {
            background-color: var(--light-2);
            color: var(--text-primary);
        }
        
        .btn-back:hover {
            background-color: var(--light-3);
            transform: translateY(-3px);
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        
        .timeline {
            margin-top: 2.5rem;
            padding-top: 1.5rem;
        }
        
        .timeline-title {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
        }
        
        .timeline-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -8px;
            width: 50px;
            height: 3px;
            background-color: var(--primary);
            border-radius: 2px;
        }
        
        .timeline-list {
            list-style: none;
            padding: 0;
            position: relative;
            margin-left: 15px;
        }
        
        .timeline-list:before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 15px;
            width: 2px;
            background: linear-gradient(to bottom, var(--light-3) 0%, var(--primary-light) 100%);
            border-radius: 2px;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 60px;
            margin-bottom: 1.5rem;
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        
        .timeline-item:nth-child(1) { animation-delay: 0.1s; }
        .timeline-item:nth-child(2) { animation-delay: 0.2s; }
        .timeline-item:nth-child(3) { animation-delay: 0.3s; }
        .timeline-item:nth-child(4) { animation-delay: 0.4s; }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        
        .timeline-dot {
            position: absolute;
            left: 0;
            top: 0;
            width: 32px;
            height: 32px;
            background-color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 0 0 4px rgba(61, 90, 254, 0.2);
            transition: all 0.3s ease;
        }
        
        .timeline-item:hover .timeline-dot {
            transform: scale(1.1);
            box-shadow: 0 0 0 6px rgba(61, 90, 254, 0.3);
        }
        
        .timeline-date {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        
        .timeline-content {
            font-weight: 600;
            color: var(--text-primary);
            background-color: var(--light);
            padding: 0.75rem 1.25rem;
            border-radius: var(--border-radius);
            display: inline-block;
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s ease;
        }
        
        .timeline-item:hover .timeline-content {
            transform: translateX(5px);
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        /* Print styles */
        @media print {
            header, footer, .action-buttons, .breadcrumb {
                display: none;
            }
            
            .order-details-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
            
            body {
                background-color: white;
            }
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .order-status {
                margin-left: 0;
            }
            
            .items-table {
                display: block;
                overflow-x: auto;
            }
            
            .action-buttons {
                justify-content: center;
                flex-direction: column;
                gap: 1rem;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .timeline-item {
                padding-left: 45px;
            }
        }
        
        @media (max-width: 576px) {
            .order-details-container {
                padding: 1rem;
            }
            
            .detail-box {
                padding: 1rem;
            }
            
            .items-table th, .items-table td {
                padding: 0.75rem;
            }
            
            .product-img {
                width: 50px;
                height: 50px;
            }
        }
    </style>
</head>
<body>
    <!-- Header & Navigation -->
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="navbar-brand">
                    <i class="fas fa-paint-brush"></i> <span>Terral</span>
                </a>
                
                <button class="navbar-toggle" id="navbar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <ul class="navbar-nav" id="navbar-nav">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="all-products.php" class="nav-link">Products</a>
                    </li>
                    <li class="nav-item">
                        <a href="how-it-works.php" class="nav-link">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a href="contact.php" class="nav-link">Contact</a>
                    </li>
                </ul>
                
                <div class="navbar-icons">
                    <a href="#" class="navbar-icon" id="search-toggle">
                        <i class="fas fa-search"></i>
                    </a>
                    <a href="cart.php" class="navbar-icon cart-icon" id="cart-toggle">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count">0</span>
                    </a>
                    <a href="<?php echo isset($_SESSION['user_id']) ? 'account.php' : 'login.php'; ?>" class="navbar-icon">
                        <i class="fas fa-user"></i>
                    </a>
                </div>
            </nav>
        </div>
    </header>
    
    <main class="main-content">
        <div class="container">
            <div class="breadcrumb">
                <a href="index.php">Home</a>
                <a href="account.php">My Account</a>
                <span>Order Details</span>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php else: ?>
                <?php if ($order): ?>
                    <div class="order-details-container">
                        <div class="order-header">
                            <div>
                                <h1 class="order-title">Order Details</h1>
                                <div class="order-id">Order #<?php echo $order['id']; ?></div>
                                <div class="order-date">Placed on <?php echo date('F j, Y', strtotime($order['created_at'])); ?></div>
                            </div>
                            <div>
                                <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                    <i class="fas fa-<?php 
                                        echo match(strtolower($order['status'])) {
                                            'pending' => 'clock',
                                            'processing' => 'spinner',
                                            'shipped' => 'truck',
                                            'delivered' => 'check-circle',
                                            'canceled' => 'times-circle',
                                            default => 'info-circle'
                                        };
                                    ?>"></i>
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="order-details-grid">
                            <div class="detail-box">
                                <h3><i class="fas fa-info-circle"></i> Order Information</h3>
                                <div class="detail-row">
                                    <div class="detail-label">Order Number:</div>
                                    <div class="detail-value">#<?php echo $order['id']; ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Date:</div>
                                    <div class="detail-value"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Status:</div>
                                    <div class="detail-value"><?php echo ucfirst($order['status']); ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Payment Method:</div>
                                    <div class="detail-value"><?php echo !empty($order['payment_method']) ? ucfirst($order['payment_method']) : 'M-Pesa'; ?></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Payment Status:</div>
                                    <div class="detail-value"><?php echo ucfirst($order['payment_status'] ?? 'pending'); ?></div>
                                </div>
                                <?php if (!empty($order['transaction_id'])): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Transaction ID:</div>
                                    <div class="detail-value"><?php echo $order['transaction_id']; ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="detail-box">
                                <h3><i class="fas fa-shipping-fast"></i> Shipping Address</h3>
                                <div class="address-details">
                                    <p><strong><?php echo !empty($order['first_name']) ? $order['first_name'] . ' ' . $order['last_name'] : $user['first_name'] . ' ' . $user['last_name']; ?></strong></p>
                                    <p><?php echo !empty($order['address']) ? $order['address'] : $user['address']; ?></p>
                                    <p>
                                        <?php 
                                        $city = !empty($order['city']) ? $order['city'] : $user['city'];
                                        $state = !empty($order['state']) ? $order['state'] : $user['state'];
                                        $zip = !empty($order['zip_code']) ? $order['zip_code'] : $user['postal_code'];
                                        $country = !empty($order['country']) ? $order['country'] : $user['country'];
                                        
                                        echo $city;
                                        if (!empty($state)) echo ', ' . $state;
                                        if (!empty($zip)) echo ' ' . $zip;
                                        ?>
                                    </p>
                                    <p><?php echo $country; ?></p>
                                    <p><i class="fas fa-phone-alt"></i> <?php echo !empty($order['phone']) ? $order['phone'] : $user['phone']; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="order-items">
                            <h2 class="order-items-title">Order Items</h2>
                            
                            <?php if (count($orderItems) > 0): ?>
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
                                        <?php foreach ($orderItems as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="product-info">
                                                        <div class="product-img-wrapper">
                                                            <img src="<?php echo $item['image_url']; ?>" alt="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" class="product-img">
                                                        </div>
                                                        <div class="product-details">
                                                            <h4><?php echo htmlspecialchars($item['name'] ?? ''); ?></h4>
                                                            <?php if (!empty($item['options'])): ?>
                                                                <div class="product-options">
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
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo formatCurrency($item['price']); ?></td>
                                                <td><?php echo $item['quantity']; ?></td>
                                                <td><?php echo formatCurrency($item['price'] * $item['quantity']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <div class="order-summary">
                                    <div class="summary-row">
                                        <div>Subtotal</div>
                                        <div><?php echo formatCurrency($subtotal); ?></div>
                                    </div>
                                    <div class="summary-row">
                                        <div>Shipping</div>
                                        <div><?php echo formatCurrency($order['shipping_fee']); ?></div>
                                    </div>
                                    <div class="summary-row total">
                                        <div>Total</div>
                                        <div><?php echo formatCurrency($order['total']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="timeline">
                                    <h2 class="timeline-title">Order Timeline</h2>
                                    <ul class="timeline-list">
                                        <li class="timeline-item">
                                            <div class="timeline-dot"><i class="fas fa-shopping-cart"></i></div>
                                            <div class="timeline-date"><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></div>
                                            <div class="timeline-content">Order placed</div>
                                        </li>
                                        
                                        <?php if ($order['status'] != 'pending'): ?>
                                        <li class="timeline-item">
                                            <div class="timeline-dot"><i class="fas fa-sync"></i></div>
                                            <div class="timeline-date"><?php echo date('F j, Y', strtotime($order['updated_at'])); ?></div>
                                            <div class="timeline-content">Status updated to <?php echo ucfirst($order['status']); ?></div>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['payment_status'] === 'paid'): ?>
                                        <li class="timeline-item">
                                            <div class="timeline-dot"><i class="fas fa-money-bill"></i></div>
                                            <div class="timeline-date"><?php echo !empty($order['paid_at']) ? date('F j, Y g:i A', strtotime($order['paid_at'])) : date('F j, Y', strtotime($order['updated_at'])); ?></div>
                                            <div class="timeline-content">Payment received</div>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['status'] === 'shipped'): ?>
                                        <li class="timeline-item">
                                            <div class="timeline-dot"><i class="fas fa-truck"></i></div>
                                            <div class="timeline-date"><?php echo date('F j, Y', strtotime($order['updated_at'])); ?></div>
                                            <div class="timeline-content">Order shipped</div>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['status'] === 'delivered'): ?>
                                        <li class="timeline-item">
                                            <div class="timeline-dot"><i class="fas fa-check"></i></div>
                                            <div class="timeline-date"><?php echo date('F j, Y', strtotime($order['updated_at'])); ?></div>
                                            <div class="timeline-content">Order delivered</div>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No items found for this order.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="account.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back to My Account</a>
                            
                            <?php if ($order['status'] === 'delivered'): ?>
                            <a href="#" class="btn btn-primary"><i class="fas fa-star"></i> Leave a Review</a>
                            <?php endif; ?>
                            
                            <button onclick="window.print()" class="btn btn-back"><i class="fas fa-print"></i> Print Order</button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle"></i> Order not found or you don't have permission to view it.
                    </div>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="account.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to My Account</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Terral Online Production System. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <script>
        // Update cart count when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
            animateTimeline();
        });
        
        // Update cart count
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            const totalItems = cart.reduce((count, item) => count + item.quantity, 0);
            document.querySelector('.cart-count').textContent = totalItems;
        }
        
        // Animate timeline elements
        function animateTimeline() {
            const timelineItems = document.querySelectorAll('.timeline-item');
            timelineItems.forEach(item => {
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            });
        }
        
        // Mobile menu toggle
        document.getElementById('navbar-toggle').addEventListener('click', function() {
            document.getElementById('navbar-nav').classList.toggle('active');
        });
        
        // Set active nav link
        document.addEventListener('DOMContentLoaded', function() {
            const currentLocation = window.location.pathname;
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const linkPath = link.getAttribute('href');
                if (currentLocation.includes('account') || currentLocation.includes('order-details')) {
                    if (linkPath.includes('account')) {
                        link.classList.add('active');
                    }
                } else if (linkPath !== 'index.php' && currentLocation.includes(linkPath)) {
                    link.classList.add('active');
                } else if (linkPath === 'index.php' && (currentLocation === '/' || currentLocation.includes('index'))) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html> 