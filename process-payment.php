<?php
// Define root path
define('ROOT_PATH', __DIR__);

// Start session for user authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Store current page as redirect destination after login
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header("Location: login.php");
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Include database connection
require_once ROOT_PATH . '/api/config/Database.php';

// Get posted data
$order_id = $_POST['order_id'] ?? '';
$amount = $_POST['amount'] ?? 0;
$payment_method = $_POST['payment_method'] ?? '';
$first_name = $_POST['first_name'] ?? '';
$last_name = $_POST['last_name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$address = $_POST['address'] ?? '';
$city = $_POST['city'] ?? '';
$state = $_POST['state'] ?? '';
$postal_code = $_POST['postal_code'] ?? '';
$country = $_POST['country'] ?? '';

// Store order details temporarily in session
$_SESSION['order'] = [
    'order_id' => $order_id,
    'amount' => $amount,
    'payment_method' => $payment_method,
    'first_name' => $first_name,
    'last_name' => $last_name,
    'email' => $email,
    'phone' => $phone,
    'address' => $address,
    'city' => $city,
    'state' => $state,
    'postal_code' => $postal_code,
    'country' => $country,
    'timestamp' => date('Y-m-d H:i:s')
];

// Process based on payment method
if ($payment_method === 'mpesa') {
    // Additional M-Pesa specific data
    $mpesa_phone = $_POST['mpesa_phone'] ?? '';
    $_SESSION['order']['mpesa_phone'] = $mpesa_phone;
    
    // Redirect to M-Pesa payment simulation
    header("Location: payment-simulation.php?method=mpesa");
    exit;
} elseif ($payment_method === 'card') {
    // Additional card specific data
    $card_number = $_POST['card_number'] ?? '';
    $card_expiry = $_POST['card_expiry'] ?? '';
    $card_cvv = $_POST['card_cvv'] ?? '';
    $card_name = $_POST['card_name'] ?? '';
    
    // Store masked card number for security
    $masked_card = '************' . substr(str_replace(' ', '', $card_number), -4);
    $_SESSION['order']['card_details'] = [
        'card_number' => $masked_card,
        'card_expiry' => $card_expiry,
        'card_holder' => $card_name
    ];
    
    // Redirect to card payment simulation
    header("Location: payment-simulation.php?method=card");
    exit;
} elseif ($payment_method === 'bank_transfer') {
    // Bank transfer doesn't need real-time processing
    header("Location: payment-simulation.php?method=bank_transfer");
    exit;
} elseif ($payment_method === 'cash_on_delivery') {
    // Additional COD specific data
    $cod_time = $_POST['cod_time'] ?? 'any';
    $_SESSION['order']['cod_time'] = $cod_time;
    
    // Redirect to COD confirmation
    header("Location: payment-simulation.php?method=cash_on_delivery");
    exit;
} else {
    // Invalid payment method
    header("Location: checkout.php?error=invalid_payment");
    exit;
}
?> 