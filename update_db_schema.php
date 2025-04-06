<?php
// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Initialize variables
$success = false;
$error_message = '';

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();

    // SQL queries to update database schema
    $queries = array(
        // First check if order_number column exists and add it if missing
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS order_number VARCHAR(50) NULL AFTER user_id",
        
        // Add first_name, last_name, email columns if they don't exist
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS first_name VARCHAR(50) NULL AFTER order_number", 
         
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS last_name VARCHAR(50) NULL AFTER first_name",
         
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS email VARCHAR(100) NULL AFTER last_name",
         
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL AFTER email",
        
        // Add other missing columns
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10, 2) NULL DEFAULT 0 AFTER payment_method",
         
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(10, 2) NULL DEFAULT 0 AFTER subtotal",
         
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS tax DECIMAL(10, 2) NULL DEFAULT 0 AFTER shipping_cost",
         
        // Add total_amount column if it doesn't exist
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS total_amount DECIMAL(10, 2) NULL DEFAULT 0",
         
        // Add product_name column to order_items if it doesn't exist
        "ALTER TABLE order_items
         ADD COLUMN IF NOT EXISTS product_name VARCHAR(255) NULL AFTER product_id",
         
        // Add created_at column to order_items if it doesn't exist
        "ALTER TABLE order_items
         ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    );
    
    // Execute each query separately
    foreach ($queries as $query) {
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute();
            echo "Executed: $query<br>";
        } catch (PDOException $e) {
            echo "Error executing: $query<br>";
            echo "Error message: " . $e->getMessage() . "<br><br>";
        }
    }
    
    // Check if total_price column exists
    $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'total_price'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Total_price exists, rename it to total_amount if needed
        try {
            $stmt = $conn->prepare("ALTER TABLE orders CHANGE COLUMN total_price total_amount DECIMAL(10, 2)");
            $stmt->execute();
            echo "Renamed total_price to total_amount<br>";
        } catch (PDOException $e) {
            echo "Error renaming total_price: " . $e->getMessage() . "<br>";
        }
    }
    
    $success = true;
    echo "<br>Database schema update completed!";
    
} catch (PDOException $e) {
    $error_message = 'Database connection error: ' . $e->getMessage();
    echo $error_message;
}
?> 