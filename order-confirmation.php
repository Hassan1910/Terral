<?php
/**
 * Terral Online Production System
 * Order Confirmation Page
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/helpers/ProductHelper.php';

// Start session for user authentication
session_start();

// Set content type
header('Content-Type: text/html; charset=UTF-8');

// Initialize variables
$site_name = 'Terral';
$pageTitle = 'Order Confirmation';
$order = null;
$orderItems = [];
$errorMessage = '';

// Check if we have an order ID
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header('Location: index.php');
    exit;
}

$orderId = (int)$_GET['order_id'];

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize ProductHelper
$productHelper = new ProductHelper($conn);

// Get order details
try {
    // Get order information
    $query = "SELECT o.*, u.first_name, u.last_name, u.email, u.phone 
              FROM orders o
              JOIN users u ON o.user_id = u.id
              WHERE o.id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $orderId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if user is authorized to view this order
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $order['user_id'] && $_SESSION['role'] != 'admin') {
            header('Location: index.php');
            exit;
        }
        
        // Get order items
        $query = "SELECT oi.*, p.image 
                  FROM order_items oi
                  LEFT JOIN products p ON oi.product_id = p.id
                  WHERE oi.order_id = :order_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add image URLs to items
            foreach ($orderItems as &$item) {
                $item['image_url'] = $productHelper->getProductImageUrl($item['image']);
            }
        }
    } else {
        $errorMessage = 'Order not found';
    }
} catch (PDOException $e) {
    $errorMessage = 'Database error: ' . $e->getMessage();
}

// Calculate order totals
$subtotal = 0;
$tax_rate = 0.16; // 16% VAT in Kenya
$shipping = 350; // Standard shipping

foreach ($orderItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$tax = $subtotal * $tax_rate;
$total = $subtotal + $tax + $shipping;

// Get payment status
$paymentStatus = 'pending';
$paymentDate = null;

try {
    $query = "SELECT status, payment_date FROM payments WHERE order_id = :order_id ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        $paymentStatus = $payment['status'];
        $paymentDate = $payment['payment_date'];
    }
} catch (PDOException $e) {
    // Ignore payment errors
}

// Format dates
$orderDate = new DateTime($order['created_at']);
$estimatedDelivery = clone $orderDate;
$estimatedDelivery->add(new DateInterval('P3D')); // Add 3 days for delivery

// Get order status info
$statusInfo = [
    'pending' => [
        'icon' => 'fa-clock',
        'color' => '#f39c12',
        'description' => 'Your order has been received and is being processed.'
    ],
    'processing' => [
        'icon' => 'fa-spinner',
        'color' => '#3498db',
        'description' => 'Your order is being prepared for shipping.'
    ],
    'shipped' => [
        'icon' => 'fa-truck',
        'color' => '#2ecc71',
        'description' => 'Your order has been shipped and is on its way.'
    ],
    'delivered' => [
        'icon' => 'fa-check-circle',
        'color' => '#27ae60',
        'description' => 'Your order has been delivered successfully.'
    ],
    'canceled' => [
        'icon' => 'fa-times-circle',
        'color' => '#e74c3c',
        'description' => 'Your order has been canceled.'
    ]
];

// Get payment status info
$paymentInfo = [
    'pending' => [
        'icon' => 'fa-clock',
        'color' => '#f39c12',
        'description' => 'Payment is pending.'
    ],
    'processing' => [
        'icon' => 'fa-spinner',
        'color' => '#3498db',
        'description' => 'Payment is being processed.'
    ],
    'completed' => [
        'icon' => 'fa-check-circle',
        'color' => '#27ae60',
        'description' => 'Payment has been completed successfully.'
    ],
    'failed' => [
        'icon' => 'fa-times-circle',
        'color' => '#e74c3c',
        'description' => 'Payment has failed. Please try again or contact customer support.'
    ],
    'refunded' => [
        'icon' => 'fa-undo',
        'color' => '#9b59b6',
        'description' => 'Payment has been refunded.'
    ]
];

// Current status
$currentStatus = isset($order['status']) ? $order['status'] : 'pending';
$currentPaymentStatus = isset($order['payment_status']) ? $order['payment_status'] : 'pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($site_name); ?></title>
    
    <!-- Meta tags for SEO -->
    <meta name="description" content="Order confirmation for your purchase at Terral.">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Modern Theme CSS -->
    <link rel="stylesheet" href="/Terral2/assets/css/modern-theme.css">
    
    <style>
        /* Order confirmation specific styles */
        .order-container {
            margin-bottom: var(--space-5);
        }
        
        .order-success {
            text-align: center;
            margin-bottom: var(--space-4);
            padding: var(--space-4);
            background-color: rgba(46, 204, 113, 0.1);
            border-radius: var(--border-radius);
        }
        
        .order-success .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #2ecc71;
            color: white;
            font-size: 2.5rem;
            margin-bottom: var(--space-3);
        }
        
        .order-success h2 {
            margin-bottom: var(--space-2);
            color: #2ecc71;
        }
        
        .order-success p {
            font-size: 1.1rem;
            margin-bottom: var(--space-3);
        }
        
        .order-details {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .order-header {
            background-color: var(--primary);
            color: white;
            padding: var(--space-3);
            text-align: center;
        }
        
        .order-header h2 {
            margin: 0;
            color: white;
        }
        
        .order-body {
            padding: var(--space-4);
        }
        
        .order-info {
            margin-bottom: var(--space-4);
        }
        
        .order-info-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-3);
            margin-bottom: var(--space-4);
        }
        
        .order-info-item {
            background-color: var(--light);
            padding: var(--space-3);
            border-radius: var(--border-radius);
        }
        
        .order-info-item h3 {
            font-size: 1rem;
            margin: 0 0 var(--space-2) 0;
            color: var(--text-secondary);
        }
        
        .order-info-value {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .order-status {
            display: flex;
            align-items: center;
            margin-bottom: var(--space-2);
        }
        
        .status-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            margin-right: var(--space-2);
            color: white;
        }
        
        .status-text {
            font-weight: 600;
        }
        
        .status-description {
            margin-left: 40px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .order-timeline {
            margin-top: var(--space-4);
            margin-bottom: var(--space-4);
            position: relative;
            display: flex;
            justify-content: space-between;
        }
        
        .order-timeline::before {
            content: '';
            position: absolute;
            top: 24px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--light-3);
            z-index: 0;
        }
        
        .timeline-step {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 1;
        }
        
        .timeline-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: white;
            border: 2px solid var(--light-3);
            margin-bottom: var(--space-2);
            font-size: 1.2rem;
            color: var(--text-secondary);
        }
        
        .timeline-icon.active {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .timeline-icon.completed {
            background-color: #2ecc71;
            border-color: #2ecc71;
            color: white;
        }
        
        .timeline-label {
            font-size: 0.8rem;
            font-weight: 500;
            text-align: center;
            max-width: 80px;
        }
        
        .order-items {
            margin-top: var(--space-4);
        }
        
        .order-item {
            display: flex;
            padding: var(--space-3) 0;
            border-bottom: 1px solid var(--light-3);
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item-image {
            width: 80px;
            height: 80px;
            overflow: hidden;
            border-radius: var(--border-radius);
            margin-right: var(--space-3);
        }
        
        .order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .order-item-details {
            flex-grow: 1;
        }
        
        .order-item-name {
            font-weight: 600;
            margin-bottom: var(--space-1);
        }
        
        .order-item-price {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .order-item-quantity {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .order-item-total {
            font-weight: 600;
            margin-left: auto;
            align-self: center;
        }
        
        .order-summary {
            margin-top: var(--space-4);
            background-color: var(--light);
            padding: var(--space-3);
            border-radius: var(--border-radius);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--space-2);
        }
        
        .summary-row.total {
            margin-top: var(--space-3);
            padding-top: var(--space-3);
            border-top: 1px solid var(--light-3);
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .action-buttons {
            display: flex;
            gap: var(--space-3);
            margin-top: var(--space-4);
        }
        
        .action-button {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-3);
            font-weight: 600;
            border-radius: var(--border-radius);
            background-color: var(--light);
            transition: var(--transition-normal);
        }
        
        .action-button:hover {
            background-color: var(--light-2);
        }
        
        .action-button i {
            margin-right: var(--space-2);
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Promo Banner -->
    <div class="promo-banner">
        <div class="container">
            <div class="promo-content">
                <span class="promo-text"><i class="fas fa-gift"></i> Special Offer: Use code <strong>TERRAL20</strong> for 20% off your first order!</span>
                <div class="promo-countdown" id="promo-countdown">
                    <span class="countdown-text">Ends in:</span>
                    <span class="countdown-timer" id="countdown-timer">23:59:59</span>
                </div>
            </div>
            <button class="promo-close" id="promo-close"><i class="fas fa-times"></i></button>
        </div>
    </div>
    
    <!-- Navigation -->
    <header class="navbar">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <i class="fas fa-paint-brush"></i> <?php echo htmlspecialchars($site_name); ?>
            </a>
            
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">Home</a>
                </li>
                <li class="nav-item">
                    <a href="all-products.php" class="nav-link">Products</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">How It Works</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">Contact</a>
                </li>
            </ul>
            
            <div class="navbar-icons">
                <a href="#" class="navbar-icon" id="search-toggle">
                    <i class="fas fa-search"></i>
                </a>
                <a href="#" class="navbar-icon cart-icon" id="cart-toggle">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count">0</span>
                </a>
                <a href="<?php echo isset($_SESSION['user_id']) ? 'account.php' : 'login.php'; ?>" class="navbar-icon">
                    <i class="fas fa-user"></i>
                </a>
            </div>
        </div>
    </header>
    
    <main class="main-content">
        <div class="container">
            <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; padding: 1rem; border-radius: var(--border-radius); margin-bottom: var(--space-3);">
                <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
            </div>
            <?php elseif ($order): ?>
            
            <div class="order-container">
                <div class="order-success">
                    <div class="icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2>Thank You for Your Order!</h2>
                    <p>Your order has been placed successfully and is being processed.</p>
                    <p>Order #<?php echo sprintf("%06d", $orderId); ?></p>
                    <p>A confirmation email has been sent to <?php echo htmlspecialchars($order['email']); ?></p>
                </div>
                
                <div class="order-details">
                    <div class="order-header">
                        <h2>Order Details</h2>
                    </div>
                    
                    <div class="order-body">
                        <div class="order-info">
                            <div class="order-info-row">
                                <div class="order-info-item">
                                    <h3>Order Number</h3>
                                    <div class="order-info-value">#<?php echo sprintf("%06d", $orderId); ?></div>
                                </div>
                                
                                <div class="order-info-item">
                                    <h3>Order Date</h3>
                                    <div class="order-info-value"><?php echo $orderDate->format('M d, Y'); ?></div>
                                </div>
                                
                                <div class="order-info-item">
                                    <h3>Payment Method</h3>
                                    <div class="order-info-value">
                                        <?php 
                                        $paymentIcons = [
                                            'mpesa' => '<i class="fas fa-mobile-alt" style="color: #4CAF50;"></i> M-Pesa',
                                            'card' => '<i class="fas fa-credit-card" style="color: #FF9800;"></i> Credit/Debit Card',
                                            'cash' => '<i class="fas fa-money-bill-wave"></i> Cash on Delivery'
                                        ];
                                        echo isset($paymentIcons[$order['payment_method']]) 
                                             ? $paymentIcons[$order['payment_method']] 
                                             : ucfirst($order['payment_method']);
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="order-info-item">
                                    <h3>Estimated Delivery</h3>
                                    <div class="order-info-value"><?php echo $estimatedDelivery->format('M d, Y'); ?></div>
                                </div>
                            </div>
                            
                            <div class="order-info-item">
                                <h3>Shipping Address</h3>
                                <div class="order-info-value">
                                    <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?><br>
                                    <?php echo htmlspecialchars($order['shipping_address']); ?><br>
                                    <?php echo htmlspecialchars($order['shipping_city'] . ', ' . ($order['shipping_state'] ? $order['shipping_state'] . ', ' : '') . $order['shipping_country']); ?><br>
                                    <?php echo htmlspecialchars($order['shipping_phone']); ?>
                                </div>
                            </div>
                            
                            <h3 style="margin-top: var(--space-4);">Order Status</h3>
                            
                            <div class="order-status">
                                <?php if (isset($statusInfo[$currentStatus])): ?>
                                <div class="status-icon" style="background-color: <?php echo $statusInfo[$currentStatus]['color']; ?>">
                                    <i class="fas <?php echo $statusInfo[$currentStatus]['icon']; ?>"></i>
                                </div>
                                <div class="status-text"><?php echo ucfirst($currentStatus); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isset($statusInfo[$currentStatus])): ?>
                            <div class="status-description"><?php echo $statusInfo[$currentStatus]['description']; ?></div>
                            <?php endif; ?>
                            
                            <div class="order-timeline">
                                <?php
                                $steps = [
                                    'pending' => ['icon' => 'fa-clock', 'label' => 'Order Placed'],
                                    'processing' => ['icon' => 'fa-spinner', 'label' => 'Processing'],
                                    'shipped' => ['icon' => 'fa-truck', 'label' => 'Shipped'],
                                    'delivered' => ['icon' => 'fa-check-circle', 'label' => 'Delivered']
                                ];
                                
                                $currentFound = false;
                                foreach ($steps as $step => $info): 
                                    $isActive = $step === $currentStatus;
                                    $isCompleted = !$currentFound && !$isActive;
                                    
                                    if ($isActive) {
                                        $currentFound = true;
                                    }
                                ?>
                                <div class="timeline-step">
                                    <div class="timeline-icon <?php echo $isActive ? 'active' : ($isCompleted ? 'completed' : ''); ?>">
                                        <i class="fas <?php echo $info['icon']; ?>"></i>
                                    </div>
                                    <div class="timeline-label"><?php echo $info['label']; ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <h3 style="margin-top: var(--space-4);">Payment Status</h3>
                            
                            <div class="order-status">
                                <?php if (isset($paymentInfo[$currentPaymentStatus])): ?>
                                <div class="status-icon" style="background-color: <?php echo $paymentInfo[$currentPaymentStatus]['color']; ?>">
                                    <i class="fas <?php echo $paymentInfo[$currentPaymentStatus]['icon']; ?>"></i>
                                </div>
                                <div class="status-text"><?php echo ucfirst($currentPaymentStatus); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isset($paymentInfo[$currentPaymentStatus])): ?>
                            <div class="status-description"><?php echo $paymentInfo[$currentPaymentStatus]['description']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <h3>Order Items</h3>
                        
                        <div class="order-items">
                            <?php foreach ($orderItems as $item): ?>
                            <div class="order-item">
                                <div class="order-item-image">
                                    <img src="<?php echo $item['image_url']; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                </div>
                                
                                <div class="order-item-details">
                                    <div class="order-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div class="order-item-price">KSh <?php echo number_format($item['price'], 2); ?></div>
                                    <div class="order-item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                                </div>
                                
                                <div class="order-item-total">
                                    KSh <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-summary">
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span>KSh <?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Tax (16% VAT)</span>
                                <span>KSh <?php echo number_format($tax, 2); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Shipping</span>
                                <span>KSh <?php echo number_format($shipping, 2); ?></span>
                            </div>
                            
                            <div class="summary-row total">
                                <span>Total</span>
                                <span>KSh <?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="index.php" class="action-button">
                                <i class="fas fa-home"></i> Continue Shopping
                            </a>
                            
                            <a href="#" class="action-button" onclick="window.print(); return false;">
                                <i class="fas fa-print"></i> Print Order
                            </a>
                            
                            <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="account.php?tab=orders" class="action-button">
                                <i class="fas fa-clipboard-list"></i> View All Orders
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <div class="text-center" style="padding: var(--space-5) 0;">
                <h2>Order Not Found</h2>
                <p>The order you are looking for does not exist or you don't have permission to view it.</p>
                <a href="index.php" class="btn btn-primary mt-3">Return to Home</a>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3>About <?php echo htmlspecialchars($site_name); ?></h3>
                    <p style="margin-bottom: 1rem;">We provide high-quality customizable products for businesses and individuals. Make your brand stand out with our premium branded items.</p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="all-products.php">Products</a></li>
                        <li><a href="#">How It Works</a></li>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Customer Service</h3>
                    <ul class="footer-links">
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Shipping Policy</a></li>
                        <li><a href="#">Return Policy</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms & Conditions</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contact Us</h3>
                    <div class="footer-contact">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Nairobi, Kenya</span>
                    </div>
                    <div class="footer-contact">
                        <i class="fas fa-phone"></i>
                        <span>+254 712 345 678</span>
                    </div>
                    <div class="footer-contact">
                        <i class="fas fa-envelope"></i>
                        <span>info@terral.co.ke</span>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Promo Banner Countdown
            function startCountdown() {
                // Set the date we're counting down to (24 hours from now)
                const countDownDate = new Date();
                countDownDate.setDate(countDownDate.getDate() + 1);
                
                // Update the countdown every 1 second
                const countdownTimer = document.getElementById('countdown-timer');
                const countdownInterval = setInterval(function() {
                    // Get today's date and time
                    const now = new Date().getTime();
                    
                    // Find the distance between now and the countdown date
                    const distance = countDownDate - now;
                    
                    // Time calculations for hours, minutes and seconds
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    
                    // Display the result
                    countdownTimer.innerHTML = 
                        (hours < 10 ? '0' + hours : hours) + ":" +
                        (minutes < 10 ? '0' + minutes : minutes) + ":" +
                        (seconds < 10 ? '0' + seconds : seconds);
                    
                    // If the countdown is finished, clear interval
                    if (distance < 0) {
                        clearInterval(countdownInterval);
                        countdownTimer.innerHTML = "EXPIRED";
                    }
                }, 1000);
            }
            
            // Close Promo Banner
            const promoBanner = document.querySelector('.promo-banner');
            const promoCloseBtn = document.getElementById('promo-close');
            if (promoCloseBtn) {
                promoCloseBtn.addEventListener('click', function() {
                    promoBanner.style.height = '0';
                    setTimeout(function() {
                        promoBanner.style.display = 'none';
                        // Adjust main content margin
                        document.querySelector('.navbar').style.top = '0';
                        document.querySelector('.main-content').style.marginTop = 'var(--navbar-height)';
                    }, 300);
                    
                    // Set cookie to remember closed state
                    localStorage.setItem('promo_banner_closed', 'true');
                });
            }
            
            // Check if banner was previously closed
            if (localStorage.getItem('promo_banner_closed') === 'true') {
                // Banner was closed before, hide it
                promoBanner.style.display = 'none';
                // Adjust main content margin
                document.querySelector('.navbar').style.top = '0';
                document.querySelector('.main-content').style.marginTop = 'var(--navbar-height)';
            } else {
                startCountdown();
            }
        });
    </script>
</body>
</html> 