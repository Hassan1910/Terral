<?php
// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Check connection
if (!$conn) {
    die("Connection failed");
}

try {
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h2>Tables in database:</h2>";
    echo "<pre>" . print_r($tables, true) . "</pre>";
    
    // Check payments table structure
    if (in_array('payments', $tables)) {
        $columns = $conn->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_ASSOC);
        echo "<h2>Columns in payments table:</h2>";
        echo "<pre>" . print_r($columns, true) . "</pre>";
        
        // Get all payment records
        $payments = $conn->query("SELECT * FROM payments LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        echo "<h2>Payment records:</h2>";
        echo "<pre>" . print_r($payments, true) . "</pre>";
        
        // Get a sample order with payments
        echo "<h2>Sample order with payment details:</h2>";
        $order_query = "SELECT o.id as order_id, o.status as order_status, 
                       p.id as payment_id, p.payment_method, p.status as payment_status, p.transaction_id
                       FROM orders o
                       LEFT JOIN payments p ON o.id = p.order_id
                       LIMIT 1";
        $sample_order = $conn->query($order_query)->fetch(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($sample_order, true) . "</pre>";
    } else {
        echo "<p>Payments table does not exist.</p>";
    }
    
    // Check orders table structure 
    if (in_array('orders', $tables)) {
        $columns = $conn->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_ASSOC);
        echo "<h2>Columns in orders table:</h2>";
        echo "<pre>" . print_r($columns, true) . "</pre>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 