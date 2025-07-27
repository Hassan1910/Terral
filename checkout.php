<?php
/**
 * Terral Online Production System
 * Modern Checkout Page
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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // User is not logged in, redirect to login page with a return URL
    $_SESSION['checkout_pending'] = true; // Flag to indicate pending checkout
    header('Location: login.php?redirect=checkout');
    exit;
}

// Set content type
header('Content-Type: text/html; charset=UTF-8');

// Check if cart is empty and redirect if needed
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
if (empty($cart) && !isset($_GET['demo'])) {
    // If cart is empty, redirect to products page
    header('Location: all-products.php');
    exit;
}

// Initialize variables
$site_name = 'Terral';
$pageTitle = 'Checkout';
$errorMessage = '';
$successMessage = '';
$user = null;
$total = 0;
$subtotal = 0;
$shipping = 350; // Standard shipping fee in KSh
$tax_rate = 0.16; // 16% VAT in Kenya
$tax = 0;

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize ProductHelper
$productHelper = new ProductHelper($conn);

// Get user data from database if logged in
if (isset($_SESSION['user_id'])) {
    try {
        $userId = $_SESSION['user_id'];
        $query = "SELECT * FROM users WHERE id = :id LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            // Store complete user data in session for future use
            $_SESSION['user'] = $user;
        }
    } catch (PDOException $e) {
        $errorMessage = 'Error retrieving user data: ' . $e->getMessage();
    }
}

// Get cart items from session or use demo items
if (isset($_GET['demo'])) {
    // Demo cart for preview purposes
    $cart = [
        [
            'id' => 1,
            'name' => 'Custom T-Shirt',
            'price' => 1999.99,
            'quantity' => 2,
            'image' => 'tshirt.jpg'
        ],
        [
            'id' => 2,
            'name' => 'Branded Notebook',
            'price' => 699.99,
            'quantity' => 1,
            'image' => 'notebook.jpg'
        ],
        [
            'id' => 3,
            'name' => 'Custom Mug',
            'price' => 899.99,
            'quantity' => 1,
            'image' => 'mug.jpg'
        ]
    ];
    
    // Add image URLs to demo cart
    foreach ($cart as &$item) {
        $item['image_url'] = $productHelper->getProductImageUrl($item['image']);
    }
} else {
    // Real cart from session
    foreach ($cart as &$item) {
        // Extract the actual product ID (remove custom suffix if present)
        $actual_product_id = $item['product_id'] ?? $item['id'];
        if (strpos($actual_product_id, '_custom_') !== false) {
            $actual_product_id = explode('_custom_', $actual_product_id)[0];
        }
        
        // Validate product exists and get current price
        try {
            $query = "SELECT id, name, price, image FROM products WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $actual_product_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Update item with current product data
                $item['name'] = $product['name'];
                $item['price'] = $product['price'];
                $item['image'] = $product['image'];
                $item['image_url'] = $productHelper->getProductImageUrl($product['image']);
                
                // Store the actual product ID for later use
                $item['product_id'] = $actual_product_id;
            }
        } catch (PDOException $e) {
            $errorMessage = 'Error loading product: ' . $e->getMessage();
        }
    }
}

// Calculate totals
foreach ($cart as $item) {
    $itemTotal = $item['price'] * $item['quantity'];
    $subtotal += $itemTotal;
}

$tax = $subtotal * $tax_rate;
$total = $subtotal + $tax + $shipping;

// Process checkout form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    // Validate form fields
    $requiredFields = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'country'];
    $isValid = true;
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $errorMessage = 'Please fill in all required fields.';
            $isValid = false;
            break;
        }
    }
    
    if ($isValid) {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Get user ID (create account if guest checkout)
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            if (!$userId && isset($_POST['create_account']) && $_POST['create_account'] == 1) {
                // Create new user account
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                $query = "INSERT INTO users (first_name, last_name, email, password, phone, address, city, state, postal_code, country, role)
                          VALUES (:first_name, :last_name, :email, :password, :phone, :address, :city, :state, :postal_code, :country, 'customer')";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':first_name', $_POST['first_name']);
                $stmt->bindParam(':last_name', $_POST['last_name']);
                $stmt->bindParam(':email', $_POST['email']);
                $stmt->bindParam(':password', $password);
                $stmt->bindParam(':phone', $_POST['phone']);
                $stmt->bindParam(':address', $_POST['address']);
                $stmt->bindParam(':city', $_POST['city']);
                $stmt->bindParam(':state', $_POST['state']);
                $stmt->bindParam(':postal_code', $_POST['postal_code']);
                $stmt->bindParam(':country', $_POST['country']);
                $stmt->execute();
                
                $userId = $conn->lastInsertId();
                
                // Log user in
                $_SESSION['user_id'] = $userId;
                $_SESSION['user'] = [
                    'first_name' => $_POST['first_name'],
                    'last_name' => $_POST['last_name'],
                    'email' => $_POST['email'],
                    'role' => 'customer'
                ];
            }
            
            // If still no user ID, use guest ID
            if (!$userId) {
                // Check if guest user exists with this email
                $query = "SELECT id FROM users WHERE email = :email AND role = 'guest'";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':email', $_POST['email']);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $userId = $stmt->fetchColumn();
                } else {
                    // Create guest user
                    $query = "INSERT INTO users (first_name, last_name, email, phone, address, city, state, postal_code, country, role)
                              VALUES (:first_name, :last_name, :email, :phone, :address, :city, :state, :postal_code, :country, 'guest')";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':first_name', $_POST['first_name']);
                    $stmt->bindParam(':last_name', $_POST['last_name']);
                    $stmt->bindParam(':email', $_POST['email']);
                    $stmt->bindParam(':phone', $_POST['phone']);
                    $stmt->bindParam(':address', $_POST['address']);
                    $stmt->bindParam(':city', $_POST['city']);
                    $stmt->bindParam(':state', $_POST['state']);
                    $stmt->bindParam(':postal_code', $_POST['postal_code']);
                    $stmt->bindParam(':country', $_POST['country']);
                    $stmt->execute();
                    
                    $userId = $conn->lastInsertId();
                }
            }
            
            // Create order
            $query = "INSERT INTO orders (user_id, total_price, status, payment_status, payment_method, 
                      shipping_address, shipping_city, shipping_state, shipping_postal_code, shipping_country, shipping_phone, notes)
                      VALUES (:user_id, :total_price, 'pending', 'pending', :payment_method, 
                      :shipping_address, :shipping_city, :shipping_state, :shipping_postal_code, :shipping_country, :shipping_phone, :notes)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':total_price', $total);
            $stmt->bindParam(':payment_method', $_POST['payment_method']);
            $stmt->bindParam(':shipping_address', $_POST['address']);
            $stmt->bindParam(':shipping_city', $_POST['city']);
            $stmt->bindParam(':shipping_state', $_POST['state']);
            $stmt->bindParam(':shipping_postal_code', $_POST['postal_code']);
            $stmt->bindParam(':shipping_country', $_POST['country']);
            $stmt->bindParam(':shipping_phone', $_POST['phone']);
            $stmt->bindParam(':notes', $_POST['notes']);
            $stmt->execute();
            
            $orderId = $conn->lastInsertId();
            
            // Add order items
            foreach ($cart as $item) {
                // Handle customization data
                $customization_text = null;
                $customization_image = null;
                $customization_color = null;
                $customization_size = null;
                
                if (isset($item['customization'])) {
                    $customization_text = isset($item['customization']['text']) ? $item['customization']['text'] : null;
                    $customization_color = isset($item['customization']['color']) ? $item['customization']['color'] : null;
                    $customization_size = isset($item['customization']['size']) ? $item['customization']['size'] : null;
                    
                    // Handle customization image upload
                    if (isset($item['customization']['image']) && !empty($item['customization']['image'])) {
                        $imageData = $item['customization']['image'];
                        
                        // Check if it's base64 encoded image data
                        if (strpos($imageData, 'data:image') === 0) {
                            // Extract image data from base64
                            list($type, $imageData) = explode(';', $imageData);
                            list(, $imageData) = explode(',', $imageData);
                            $imageData = base64_decode($imageData);
                            
                            // Generate unique filename
                            $imageName = 'custom_' . uniqid() . '_' . time() . '.png';
                            $uploadDir = ROOT_PATH . '/uploads/customizations/';
                            
                            // Create directory if it doesn't exist
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0755, true);
                            }
                            
                            $uploadPath = $uploadDir . $imageName;
                            
                            // Save the image file
                            if (file_put_contents($uploadPath, $imageData)) {
                                $customization_image = $imageName;
                            }
                        } else {
                            // If it's already a filename, use it directly
                            $customization_image = $imageData;
                        }
                    }
                }
                
                // Extract the actual product ID from the cart item ID (remove custom suffix if present)
                $actual_product_id = $item['product_id'] ?? $item['id'];
                if (strpos($actual_product_id, '_custom_') !== false) {
                    $actual_product_id = explode('_custom_', $actual_product_id)[0];
                }
                
                $query = "INSERT INTO order_items (order_id, product_id, product_name, quantity, price, customization_color, customization_size, customization_text, customization_image)
                          VALUES (:order_id, :product_id, :product_name, :quantity, :price, :customization_color, :customization_size, :customization_text, :customization_image)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':order_id', $orderId);
                $stmt->bindParam(':product_id', $actual_product_id);
                $stmt->bindParam(':product_name', $item['name']);
                $stmt->bindParam(':quantity', $item['quantity']);
                $stmt->bindParam(':price', $item['price']);
                $stmt->bindParam(':customization_color', $customization_color);
                $stmt->bindParam(':customization_size', $customization_size);
                $stmt->bindParam(':customization_text', $customization_text);
                $stmt->bindParam(':customization_image', $customization_image);
                $stmt->execute();
                
                // Update product stock (if not demo)
                if (!isset($_GET['demo'])) {
                    $query = "UPDATE products SET stock = stock - :quantity WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':quantity', $item['quantity']);
                    $stmt->bindParam(':id', $actual_product_id);
                    $stmt->execute();
                }
            }
            
            // If payment method is M-Pesa, create payment record
            if ($_POST['payment_method'] === 'mpesa') {
                // Get the M-Pesa phone number
                $mpesaPhone = isset($_POST['mpesa_phone']) ? $_POST['mpesa_phone'] : $_POST['phone'];
                
                // Sanitize the phone number (remove spaces, dashes, etc.)
                $mpesaPhone = preg_replace('/[^0-9]/', '', $mpesaPhone);
                
                // Format the phone number to ensure it's in the correct format (254XXXXXXXXX)
                if (strlen($mpesaPhone) === 10 && substr($mpesaPhone, 0, 1) === '0') {
                    // Convert 07XXXXXXXX to 2547XXXXXXXX
                    $mpesaPhone = '254' . substr($mpesaPhone, 1);
                } elseif (strlen($mpesaPhone) === 9) {
                    // Convert 7XXXXXXXX to 2547XXXXXXXX
                    $mpesaPhone = '254' . $mpesaPhone;
                } elseif (strlen($mpesaPhone) < 12 && substr($mpesaPhone, 0, 3) !== '254') {
                    // Add country code if missing
                    $mpesaPhone = '254' . $mpesaPhone;
                }
                
                // For demo, we'll just create a pending payment
                $query = "INSERT INTO payments (order_id, amount, payment_method, status)
                          VALUES (:order_id, :amount, 'mpesa', 'pending')";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':order_id', $orderId);
                $stmt->bindParam(':amount', $total);
                $stmt->execute();
                
                $paymentId = $conn->lastInsertId();
                
                // Store the M-Pesa phone number with the payment
                $query = "UPDATE orders SET shipping_phone = :phone WHERE id = :order_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':phone', $mpesaPhone);
                $stmt->bindParam(':order_id', $orderId);
                $stmt->execute();
                
                // In a real implementation, you would integrate with M-Pesa API here
                // For example:
                // $mpesaApi = new MpesaApi();
                // $mpesaResult = $mpesaApi->initiateSTKPush($mpesaPhone, $total, $orderId);
                // ... process the result
            }
            
            // Commit transaction
            $conn->commit();
            
            // Clear cart
            unset($_SESSION['cart']);
            
            // Redirect to thank you page
            header('Location: order-confirmation.php?order_id=' . $orderId);
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            
            $errorMessage = 'Error processing order: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($site_name); ?></title>
    
    <!-- Meta tags for SEO -->
    <meta name="description" content="Checkout and complete your order of custom branded products.">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Modern Theme CSS -->
    <link rel="stylesheet" href="/Terral/assets/css/modern-theme.css">
    
    <style>
        /* Checkout specific styles */
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-4);
        }
        
        @media (max-width: 992px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
        }
        
        .checkout-section {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: var(--space-4);
            margin-bottom: var(--space-4);
        }
        
        .checkout-section h3 {
            margin-bottom: var(--space-3);
            padding-bottom: var(--space-2);
            border-bottom: 1px solid var(--light-3);
        }
        
        .form-group {
            margin-bottom: var(--space-3);
        }
        
        .form-group label {
            display: block;
            margin-bottom: var(--space-1);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-3);
            border-radius: var(--border-radius);
            font-family: var(--font-family);
            font-size: 1rem;
            transition: border-color var(--transition-fast);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(61, 90, 254, 0.2);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-3);
        }
        
        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .cart-item {
            display: flex;
            margin-bottom: var(--space-3);
            padding-bottom: var(--space-3);
            border-bottom: 1px solid var(--light-3);
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item-image {
            width: 80px;
            height: 80px;
            overflow: hidden;
            border-radius: var(--border-radius);
            margin-right: var(--space-3);
        }
        
        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cart-item-details {
            flex-grow: 1;
        }
        
        .cart-item-name {
            font-weight: 600;
            margin-bottom: var(--space-1);
        }
        
        .cart-item-price {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .cart-item-quantity {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .cart-item-total {
            font-weight: 600;
            margin-left: auto;
            align-self: center;
        }
        
        .order-summary {
            margin-top: var(--space-3);
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
        
        .payment-methods {
            margin-top: var(--space-3);
        }
        
        .payment-method {
            display: block;
            padding: var(--space-2);
            margin-bottom: var(--space-2);
            border: 1px solid var(--light-3);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .payment-method:hover {
            border-color: var(--primary-light);
        }
        
        .payment-method.active {
            border-color: var(--primary);
            background-color: rgba(61, 90, 254, 0.05);
        }
        
        .payment-method input {
            margin-right: var(--space-2);
        }
        
        .payment-icon {
            margin-left: var(--space-2);
            font-size: 1.2rem;
        }
        
        .mpesa {
            color: #4CAF50;
        }
        
        .card {
            color: #FF9800;
        }
        
        .checkout-btn {
            margin-top: var(--space-3);
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
        }
        
        .secure-checkout {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: var(--space-3);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .secure-checkout i {
            margin-right: var(--space-1);
            color: var(--success);
        }
        
        .delivery-options {
            margin-top: var(--space-3);
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
                    <span class="cart-count"><?php echo count($cart); ?></span>
                </a>
                <a href="<?php echo isset($_SESSION['user_id']) ? 'account.php' : 'login.php'; ?>" class="navbar-icon">
                    <i class="fas fa-user"></i>
                </a>
            </div>
        </div>
    </header>
    
    <main class="main-content">
        <div class="container">
            <h1 class="mb-4">Checkout</h1>
            
            <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; padding: 1rem; border-radius: var(--border-radius); margin-bottom: var(--space-3);">
                <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" style="background-color: #d4edda; color: #155724; padding: 1rem; border-radius: var(--border-radius); margin-bottom: var(--space-3);">
                <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
            </div>
            <?php endif; ?>
            
            <div class="checkout-container">
                <!-- Order Summary Section -->
                <div class="checkout-column">
                    <div class="checkout-section">
                        <h3>Order Summary</h3>
                        
                        <?php if (empty($cart)): ?>
                        <p>Your cart is empty. <a href="all-products.php">Browse products</a></p>
                        <?php else: ?>
                        
                        <div class="cart-items">
                            <?php foreach ($cart as $item): ?>
                            <div class="cart-item">
                                <div class="cart-item-image">
                                    <img src="<?php echo $item['image_url']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                
                                <div class="cart-item-details">
                                    <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="cart-item-price">KSh <?php echo number_format($item['price'], 2); ?></div>
                                    <div class="cart-item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                                </div>
                                
                                <div class="cart-item-total">
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
                        <?php endif; ?>
                    </div>
                    
                    <div class="checkout-section">
                        <h3>Have a Coupon?</h3>
                        
                        <div class="form-group">
                            <div style="display: flex;">
                                <input type="text" class="form-control" placeholder="Enter coupon code" style="border-top-right-radius: 0; border-bottom-right-radius: 0;">
                                <button class="btn btn-primary" style="border-top-left-radius: 0; border-bottom-left-radius: 0;">Apply</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Checkout Form -->
                <div class="checkout-column">
                    <form method="post" id="checkout-form">
                        <div class="checkout-section">
                            <h3>Your Information</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name">First Name *</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" required 
                                           value="<?php echo ($user && isset($user['first_name'])) ? htmlspecialchars($user['first_name']) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">Last Name *</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" required 
                                           value="<?php echo ($user && isset($user['last_name'])) ? htmlspecialchars($user['last_name']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" class="form-control" required 
                                       value="<?php echo ($user && isset($user['email'])) ? htmlspecialchars($user['email']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" id="phone" name="phone" class="form-control" required placeholder="e.g. 0712345678" 
                                       value="<?php echo ($user && isset($user['phone'])) ? htmlspecialchars($user['phone']) : ''; ?>">
                            </div>
                            
                            <?php if (!isset($_SESSION['user_id'])): ?>
                            <div class="form-group">
                                <div style="display: flex; align-items: center;">
                                    <input type="checkbox" id="create_account" name="create_account" value="1" style="margin-right: 10px;">
                                    <label for="create_account" style="margin-bottom: 0;">Create an account for faster checkout next time</label>
                                </div>
                            </div>
                            
                            <div id="password-fields" style="display: none;">
                                <div class="form-group">
                                    <label for="password">Password *</label>
                                    <input type="password" id="password" name="password" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirm Password *</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="checkout-section">
                            <h3>Shipping Address</h3>
                            
                            <div class="form-group">
                                <label for="address">Street Address *</label>
                                <input type="text" id="address" name="address" class="form-control" required 
                                       value="<?php echo ($user && isset($user['address'])) ? htmlspecialchars($user['address']) : ''; ?>">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">City/Town *</label>
                                    <input type="text" id="city" name="city" class="form-control" required 
                                           value="<?php echo ($user && isset($user['city'])) ? htmlspecialchars($user['city']) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="state">County/State</label>
                                    <input type="text" id="state" name="state" class="form-control" 
                                           value="<?php echo ($user && isset($user['state'])) ? htmlspecialchars($user['state']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="postal_code">Postal Code</label>
                                    <input type="text" id="postal_code" name="postal_code" class="form-control" 
                                           value="<?php echo ($user && isset($user['postal_code'])) ? htmlspecialchars($user['postal_code']) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="country">Country *</label>
                                    <select id="country" name="country" class="form-control" required>
                                        <option value="Kenya" <?php echo ($user && isset($user['country']) && $user['country'] == 'Kenya') ? 'selected' : ''; ?>>Kenya</option>
                                        <option value="Uganda" <?php echo ($user && isset($user['country']) && $user['country'] == 'Uganda') ? 'selected' : ''; ?>>Uganda</option>
                                        <option value="Tanzania" <?php echo ($user && isset($user['country']) && $user['country'] == 'Tanzania') ? 'selected' : ''; ?>>Tanzania</option>
                                        <option value="Rwanda" <?php echo ($user && isset($user['country']) && $user['country'] == 'Rwanda') ? 'selected' : ''; ?>>Rwanda</option>
                                        <option value="Ethiopia" <?php echo ($user && isset($user['country']) && $user['country'] == 'Ethiopia') ? 'selected' : ''; ?>>Ethiopia</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="notes">Order Notes (Optional)</label>
                                <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Special delivery instructions or notes about your order"></textarea>
                            </div>
                        </div>
                        
                        <div class="checkout-section">
                            <h3>Delivery Options</h3>
                            
                            <div class="delivery-options">
                                <div class="form-group">
                                    <div class="payment-method active">
                                        <input type="radio" id="delivery_standard" name="delivery_option" value="standard" checked>
                                        <label for="delivery_standard">Standard Delivery (2-3 business days) - KSh 350</label>
                                    </div>
                                    
                                    <div class="payment-method">
                                        <input type="radio" id="delivery_express" name="delivery_option" value="express">
                                        <label for="delivery_express">Express Delivery (1 business day) - KSh 550</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkout-section">
                            <h3>Payment Method</h3>
                            
                            <div class="payment-methods">
                                <div class="payment-method active">
                                    <input type="radio" id="payment_mpesa" name="payment_method" value="mpesa" checked>
                                    <label for="payment_mpesa">M-Pesa <i class="fas fa-mobile-alt payment-icon mpesa"></i></label>
                                </div>
                                
                                <div class="payment-method">
                                    <input type="radio" id="payment_card" name="payment_method" value="card">
                                    <label for="payment_card">Credit/Debit Card <i class="fas fa-credit-card payment-icon card"></i></label>
                                </div>
                                
                                <div class="payment-method">
                                    <input type="radio" id="payment_cash" name="payment_method" value="cash">
                                    <label for="payment_cash">Cash on Delivery <i class="fas fa-money-bill-wave payment-icon"></i></label>
                                </div>
                            </div>
                            
                            <div id="mpesa-form" class="payment-form" style="margin-top: var(--space-3);">
                                <p>You will receive an M-Pesa payment prompt on your phone number after placing the order.</p>
                                <div class="form-group" style="margin-top: var(--space-3);">
                                    <label for="mpesa_phone">M-Pesa Phone Number *</label>
                                    <input type="tel" id="mpesa_phone" name="mpesa_phone" class="form-control" 
                                           placeholder="e.g. 07XX XXX XXX or 254XXXXXXXXX"
                                           value="<?php echo ($user && isset($user['phone'])) ? htmlspecialchars($user['phone']) : ''; ?>">
                                    <small style="display: block; margin-top: 5px; color: var(--text-secondary);">Enter the phone number registered with M-Pesa that will receive the payment prompt.</small>
                                </div>
                            </div>
                            
                            <div id="card-form" class="payment-form" style="display: none; margin-top: var(--space-3);">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="card_number">Card Number</label>
                                        <input type="text" id="card_number" class="form-control" placeholder="1234 5678 9012 3456">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="card_name">Name on Card</label>
                                        <input type="text" id="card_name" class="form-control">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="card_expiry">Expiry Date</label>
                                        <input type="text" id="card_expiry" class="form-control" placeholder="MM/YY">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="card_cvv">CVV</label>
                                        <input type="text" id="card_cvv" class="form-control" placeholder="123">
                                    </div>
                                </div>
                            </div>
                            
                            <div id="cash-form" class="payment-form" style="display: none; margin-top: var(--space-3);">
                                <p>You will pay the full amount in cash when your order is delivered.</p>
                            </div>
                            
                            <button type="submit" name="checkout" class="btn btn-secondary checkout-btn">
                                Complete Order <i class="fas fa-arrow-right"></i>
                            </button>
                            
                            <div class="secure-checkout">
                                <i class="fas fa-lock"></i> Secure Checkout - Your data is protected
                            </div>
                        </div>
                    </form>
                </div>
            </div>
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
            
            // Toggle password fields
            const createAccountCheckbox = document.getElementById('create_account');
            const passwordFields = document.getElementById('password-fields');
            
            if (createAccountCheckbox && passwordFields) {
                createAccountCheckbox.addEventListener('change', function() {
                    passwordFields.style.display = this.checked ? 'block' : 'none';
                });
            }
            
            // Payment method selection
            const paymentMethods = document.querySelectorAll('.payment-method');
            const mpesaForm = document.getElementById('mpesa-form');
            const cardForm = document.getElementById('card-form');
            const cashForm = document.getElementById('cash-form');
            
            paymentMethods.forEach(method => {
                method.addEventListener('click', function() {
                    // Remove active class from all methods
                    paymentMethods.forEach(m => m.classList.remove('active'));
                    
                    // Add active class to clicked method
                    this.classList.add('active');
                    
                    // Check the radio button
                    const radioBtn = this.querySelector('input[type="radio"]');
                    radioBtn.checked = true;
                    
                    // Show appropriate form
                    mpesaForm.style.display = 'none';
                    cardForm.style.display = 'none';
                    cashForm.style.display = 'none';
                    
                    if (radioBtn.value === 'mpesa') {
                        mpesaForm.style.display = 'block';
                    } else if (radioBtn.value === 'card') {
                        cardForm.style.display = 'block';
                    } else if (radioBtn.value === 'cash') {
                        cashForm.style.display = 'block';
                    }
                });
            });
        });
    </script>
</body>
</html>
