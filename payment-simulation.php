<?php
// Define root path
define('ROOT_PATH', __DIR__);

// Start session for user authentication and order details
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if order details exist in session
if (!isset($_SESSION['order'])) {
    header("Location: checkout.php");
    exit;
}

// Get payment method from URL
$payment_method = $_GET['method'] ?? '';

// Validate payment method
if (!in_array($payment_method, ['mpesa', 'card', 'bank_transfer', 'cash_on_delivery'])) {
    header("Location: checkout.php");
    exit;
}

// Get order details from session
$order = $_SESSION['order'];
$order_id = $order['order_id'];
$amount = $order['amount'];

// Set variables for payment simulation based on method
$page_title = '';
$icon_class = '';
$simulation_message = '';
$processing_time = 3000; // milliseconds
$show_spinner = true;
$completion_message = '';
$transaction_id = '';

switch ($payment_method) {
    case 'mpesa':
        $page_title = 'M-Pesa Payment';
        $icon_class = 'fa-mobile-alt';
        $simulation_message = "We've sent an STK push to {$order['mpesa_phone']}. Please check your phone and enter your M-Pesa PIN to complete the payment.";
        $processing_time = 10000; // 10 seconds for M-Pesa
        $transaction_id = 'MPESA' . date('YmdHis') . rand(1000, 9999);
        $completion_message = "Your M-Pesa payment has been processed successfully. A confirmation SMS has been sent to your phone.";
        break;
        
    case 'card':
        $page_title = 'Card Payment';
        $icon_class = 'fa-credit-card';
        $simulation_message = "We're processing your card payment with ending in {$order['card_details']['card_number']}. Please do not refresh this page.";
        $processing_time = 5000; // 5 seconds for card
        $transaction_id = 'CARD' . date('YmdHis') . rand(1000, 9999);
        $completion_message = "Your card payment has been approved and processed successfully.";
        break;
        
    case 'bank_transfer':
        $page_title = 'Bank Transfer Instructions';
        $icon_class = 'fa-university';
        $show_spinner = false;
        $simulation_message = "Please complete your bank transfer using the following details:";
        $transaction_id = 'BANK' . date('YmdHis') . rand(1000, 9999);
        break;
        
    case 'cash_on_delivery':
        $page_title = 'Cash on Delivery Confirmation';
        $icon_class = 'fa-money-bill-wave';
        $show_spinner = false;
        $simulation_message = "Your order has been confirmed for Cash on Delivery.";
        $transaction_id = 'COD' . date('YmdHis') . rand(1000, 9999);
        $delivery_time = '';
        switch($order['cod_time']) {
            case 'morning':
                $delivery_time = 'Morning (9 AM - 12 PM)';
                break;
            case 'afternoon':
                $delivery_time = 'Afternoon (12 PM - 3 PM)';
                break;
            case 'evening':
                $delivery_time = 'Evening (3 PM - 6 PM)';
                break;
            default:
                $delivery_time = 'Any Time (9 AM - 6 PM)';
        }
        break;
}

// Set order completion status
$order_completed = ($payment_method === 'bank_transfer') ? false : true;

// Update order details with transaction ID
$_SESSION['order']['transaction_id'] = $transaction_id;

// Clear cart after successful order
if ($order_completed || $payment_method === 'bank_transfer') {
    // In a real application, you would store the order in the database here
    // For now, we'll just keep it in the session for the simulation
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Terral Online Production System</title>
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        /* Header & Navigation */
        header {
            background-color: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
        }
        
        /* Main Content */
        .main-content {
            flex-grow: 1;
            padding: 50px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .payment-container {
            max-width: 600px;
            width: 100%;
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 40px;
            text-align: center;
        }
        
        .payment-icon {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .payment-title {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: var(--text-dark);
        }
        
        .payment-message {
            margin-bottom: 30px;
            color: var(--text-dark);
        }
        
        .spinner {
            border: 5px solid var(--gray-light);
            border-top: 5px solid var(--primary);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 30px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .bank-details {
            background-color: var(--gray-light);
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .bank-details p {
            margin-bottom: 10px;
        }
        
        .bank-details p:last-child {
            margin-bottom: 0;
        }
        
        .order-details {
            margin: 30px 0;
            padding-top: 20px;
            border-top: 1px solid var(--gray-light);
        }
        
        .order-details h3 {
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-light);
        }
        
        .detail-value {
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .success-icon {
            font-size: 4rem;
            color: var(--success);
            margin-bottom: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 20px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
            border: none;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        /* Footer */
        .footer {
            background-color: var(--text-dark);
            color: var(--white);
            padding: 20px 0;
            margin-top: auto;
        }
        
        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            text-align: center;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        @media (max-width: 768px) {
            .payment-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header & Navigation -->
    <header>
        <div class="navbar">
            <a href="index.php" class="logo">
                <i class="fas fa-paint-brush"></i> Terral
            </a>
        </div>
    </header>
    
    <main class="main-content">
        <div class="payment-container" id="payment-container">
            <?php if ($show_spinner): ?>
            <div class="spinner" id="payment-spinner"></div>
            <?php else: ?>
            <div class="payment-icon">
                <i class="fas <?php echo $icon_class; ?>"></i>
            </div>
            <?php endif; ?>
            
            <h1 class="payment-title"><?php echo $page_title; ?></h1>
            <p class="payment-message"><?php echo $simulation_message; ?></p>
            
            <?php if ($payment_method === 'bank_transfer'): ?>
            <div class="bank-details">
                <p><strong>Bank Name:</strong> Terral Bank</p>
                <p><strong>Account Number:</strong> 1234567890</p>
                <p><strong>Beneficiary:</strong> Terral Online Production</p>
                <p><strong>Reference:</strong> <?php echo $order_id; ?></p>
                <p><strong>Amount:</strong> $<?php echo $amount; ?></p>
            </div>
            <p>Please use the reference number when making your transfer to ensure your payment is properly assigned to your order.</p>
            <p>Your order will be processed once we receive your payment.</p>
            <?php endif; ?>
            
            <?php if ($payment_method === 'cash_on_delivery'): ?>
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <p>Your order has been confirmed for Cash on Delivery.</p>
            <p>Our delivery personnel will collect the payment of $<?php echo $amount; ?> when your order is delivered.</p>
            <p>Preferred delivery time: <strong><?php echo $delivery_time; ?></strong></p>
            <?php endif; ?>
            
            <div class="order-details">
                <h3>Order Summary</h3>
                <div class="detail-row">
                    <span class="detail-label">Order Number:</span>
                    <span class="detail-value"><?php echo $order_id; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value">$<?php echo $amount; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $payment_method)); ?></span>
                </div>
                <?php if ($payment_method !== 'bank_transfer'): ?>
                <div class="detail-row">
                    <span class="detail-label">Transaction ID:</span>
                    <span class="detail-value"><?php echo $transaction_id; ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value"><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
            </div>
            
            <a href="index.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    </main>
    
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> Terral Online Production System. All Rights Reserved.</p>
        </div>
    </footer>
    
    <?php if ($show_spinner): ?>
    <script>
        // Simulate payment processing
        setTimeout(function() {
            // Replace spinner with success icon
            document.getElementById('payment-spinner').outerHTML = '<div class="success-icon"><i class="fas fa-check-circle"></i></div>';
            
            // Update payment message
            document.getElementById('payment-container').innerHTML = `
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="payment-title">Payment Successful!</h1>
                <p class="payment-message"><?php echo $completion_message; ?></p>
                
                <div class="order-details">
                    <h3>Payment Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">Order Number:</span>
                        <span class="detail-value"><?php echo $order_id; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Amount:</span>
                        <span class="detail-value">$<?php echo $amount; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Method:</span>
                        <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $payment_method)); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Transaction ID:</span>
                        <span class="detail-value"><?php echo $transaction_id; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value"><?php echo date('Y-m-d H:i:s'); ?></span>
                    </div>
                </div>
                
                <p>We'll process your order right away. Thank you for shopping with Terral!</p>
                <a href="index.php" class="btn btn-primary">Continue Shopping</a>
            `;
            
            // Clear cart
            localStorage.removeItem('cart');
        }, <?php echo $processing_time; ?>);
    </script>
    <?php else: ?>
    <script>
        // Just clear cart for non-processing payments
        localStorage.removeItem('cart');
    </script>
    <?php endif; ?>
</body>
</html> 