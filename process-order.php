<?php
// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/models/User.php';
require_once ROOT_PATH . '/api/models/Order.php';
require_once ROOT_PATH . '/api/models/OrderItem.php';
require_once ROOT_PATH . '/api/helpers/PaymentValidationHelper.php';

// Start session for user authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit;
}

// Initialize variables
$error_message = '';
$order_id = null;
$order_number = null;

// Process the order
try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get user details
    $query = "SELECT * FROM users WHERE id = :id LIMIT 0,1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        throw new Exception('User not found');
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get data from form
    $first_name = $_POST['first_name'] ?? $user['first_name'];
    $last_name = $_POST['last_name'] ?? $user['last_name'];
    $email = $_POST['email'] ?? $user['email'];
    $phone = $_POST['phone'] ?? $user['phone'];
    $address = $_POST['address'] ?? $user['address'];
    $city = $_POST['city'] ?? $user['city'];
    $postal_code = $_POST['postal_code'] ?? $user['postal_code'];
    $country = $_POST['country'] ?? $user['country'];
    $payment_method = $_POST['payment_method'] ?? 'mpesa';
    
    // Get cart data
    $cart_data = json_decode($_POST['cart_data'], true);
    
    if (empty($cart_data)) {
        throw new Exception('Your cart is empty');
    }
    
    // Get order totals
    $subtotal = floatval($_POST['subtotal']);
    $shipping = floatval($_POST['shipping']);
    $tax = floatval($_POST['tax']);
    $total = floatval($_POST['total']);
    
    // Format shipping address
    $shipping_address = "$address, $city, $postal_code, $country";
    
    // Start transaction
    $conn->beginTransaction();
    
    // Create unique order number
    $order_number = 'TRL-' . strtoupper(substr(uniqid(), -8));
    
    // Create order
    // PAYMENT VALIDATION: All orders start as pending until payment is confirmed
    // This ensures customers must pay first before goods are delivered
    $order_status = 'pending'; // All orders start as pending until payment is confirmed
    $payment_status = 'pending'; // Payment status starts as pending
    
    // Log order creation attempt for audit
    $validator = new PaymentValidationHelper($conn);
    $validator->logValidationEvent('order_creation_attempt', [
        'user_id' => $_SESSION['user_id'],
        'payment_method' => $payment_method,
        'total_amount' => $total,
        'order_status' => $order_status,
        'payment_status' => $payment_status
    ]);
    
    // Insert order into database
    $query = "INSERT INTO orders 
              (user_id, order_number, first_name, last_name, email, phone, shipping_address, 
               payment_method, payment_status, subtotal, shipping_cost, tax, total_amount, status, created_at) 
              VALUES 
              (:user_id, :order_number, :first_name, :last_name, :email, :phone, :shipping_address, 
               :payment_method, :payment_status, :subtotal, :shipping_cost, :tax, :total_amount, :status, NOW())";
    
    $stmt = $conn->prepare($query);
    
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':order_number', $order_number);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':last_name', $last_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':shipping_address', $shipping_address);
    $stmt->bindParam(':payment_method', $payment_method);
    $stmt->bindParam(':payment_status', $payment_status);
    $stmt->bindParam(':subtotal', $subtotal);
    $stmt->bindParam(':shipping_cost', $shipping);
    $stmt->bindParam(':tax', $tax);
    $stmt->bindParam(':total_amount', $total);
    $stmt->bindParam(':status', $order_status);
    
    $stmt->execute();
    
    // Get the order ID
    $order_id = $conn->lastInsertId();
    
    // Initialize OrderItem model
    $orderItem = new OrderItem($conn);
    
    // Insert order items
    foreach ($cart_data as $item) {
        $orderItem->order_id = $order_id;
        $orderItem->product_id = $item['product_id'] ?? $item['id'];
        $orderItem->product_name = $item['name'];
        $orderItem->price = $item['price'];
        $orderItem->quantity = $item['quantity'];
        
        // Handle customization data
        if (isset($item['customization'])) {
            if (isset($item['customization']['color'])) {
                $orderItem->customization_color = $item['customization']['color'];
            }
            
            if (isset($item['customization']['size'])) {
                $orderItem->customization_size = $item['customization']['size'];
            }
            
            if (isset($item['customization']['text'])) {
                $orderItem->customization_text = $item['customization']['text'];
            }
            
            if (isset($item['customization']['image']) && !empty($item['customization']['image'])) {
                // Handle image upload
                $imageData = $item['customization']['image'];
                list($type, $imageData) = explode(';', $imageData);
                list(, $imageData)      = explode(',', $imageData);
                $imageData = base64_decode($imageData);
                
                $imageName = 'custom_' . uniqid() . '.png';
                $uploadPath = ROOT_PATH . '/uploads/customizations/' . $imageName;
                
                if (file_put_contents($uploadPath, $imageData)) {
                    $orderItem->customization_image = $imageName;
                } else {
                    // Handle error - maybe log it or set a default image
                    $orderItem->customization_image = null;
                }
            }
        }
        
        // Create the order item
        if (!$orderItem->create()) {
            throw new Exception('Failed to create order item');
        }
    }
    
    // Add payment details if provided
    if ($payment_method === 'mpesa' && !empty($_POST['mpesa_code'])) {
        $mpesa_number = $_POST['mpesa_number'] ?? $user['phone'];
        $mpesa_code = $_POST['mpesa_code'];
        
        $query = "INSERT INTO payments 
                  (order_id, payment_method, transaction_id, amount, status, created_at) 
                  VALUES 
                  (:order_id, 'mpesa', :transaction_id, :amount, 'completed', NOW())";
        
        $stmt = $conn->prepare($query);
        
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':transaction_id', $mpesa_code);
        $stmt->bindParam(':amount', $total);
        
        $stmt->execute();
        
        // Update order status to processing
        $query = "UPDATE orders SET status = 'processing' WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $order_id);
        $stmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Store order info in session for confirmation page
    $_SESSION['last_order'] = [
        'id' => $order_id,
        'order_number' => $order_number,
        'total' => $total,
        'payment_method' => $payment_method,
        'status' => $order_status
    ];
    
    // Clear the cart from session
    unset($_SESSION['cart']);
    
    // Redirect to confirmation page with order ID
    header('Location: order-confirmation.php?order_id=' . $order_id);
    exit;
    
} catch (Exception $e) {
    // Rollback transaction in case of error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    $error_message = $e->getMessage();
    
    // Redirect back to checkout with error
    $_SESSION['checkout_error'] = $error_message;
    header('Location: checkout.php');
    exit;
}