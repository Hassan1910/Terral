<?php
// Simple diagnostic script for product images

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define paths
define('ROOT_PATH', __DIR__);
$uploads_dir = ROOT_PATH . '/api/uploads/products/';

echo "<h1>Simple Product Image Debug</h1>";

// Check if products directory exists
echo "<p>Products directory: {$uploads_dir}</p>";
echo "<p>Directory exists: " . (file_exists($uploads_dir) ? "YES" : "NO") . "</p>";
echo "<p>Directory is writable: " . (is_writable($uploads_dir) ? "YES" : "NO") . "</p>";

// Create products directory if it doesn't exist
if (!file_exists($uploads_dir)) {
    echo "<p>Creating products directory...</p>";
    $result = mkdir($uploads_dir, 0755, true);
    echo "<p>Directory creation result: " . ($result ? "SUCCESS" : "FAILED") . "</p>";
}

// List all files in the products directory
echo "<h2>Files in products directory:</h2>";
if (file_exists($uploads_dir)) {
    $files = scandir($uploads_dir);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>{$file} (" . filesize($uploads_dir . $file) . " bytes)</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p>Cannot list files - directory does not exist</p>";
}

// Create a test image
echo "<h2>Creating a test image:</h2>";
$test_image = 'test_' . time() . '.jpg';
$test_path = $uploads_dir . $test_image;

try {
    $image = imagecreate(200, 200);
    $bg = imagecolorallocate($image, 0, 0, 255);
    $text_color = imagecolorallocate($image, 255, 255, 255);
    imagestring($image, 5, 50, 90, "Test Image", $text_color);
    imagejpeg($image, $test_path);
    imagedestroy($image);
    
    echo "<p>Test image creation: " . (file_exists($test_path) ? "SUCCESS" : "FAILED") . "</p>";
    echo "<p>Image path: {$test_path}</p>";
    echo "<p>Image URL: /Terral2/api/uploads/products/{$test_image}</p>";
    
    echo "<h3>Test image preview:</h3>";
    echo "<img src='/Terral2/api/uploads/products/{$test_image}' style='max-width: 200px; border: 1px solid #ccc;'>";
} catch (Exception $e) {
    echo "<p>Error creating test image: " . $e->getMessage() . "</p>";
}

// Connect to database
echo "<h2>Database check:</h2>";
try {
    require_once ROOT_PATH . '/api/config/Database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<p>Database connection: SUCCESS</p>";
    
    // Query for products
    $query = "SELECT id, name, image FROM products ORDER BY id DESC LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $count = $stmt->rowCount();
    echo "<p>Found {$count} products in database</p>";
    
    if ($count > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Product Name</th><th>Image Name</th><th>Image Path</th><th>Exists?</th></tr>";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $img_path = $uploads_dir . $row['image'];
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['name'] . "</td>";
            echo "<td>" . $row['image'] . "</td>";
            echo "<td>" . $img_path . "</td>";
            echo "<td>" . (file_exists($img_path) ? "YES" : "NO") . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Add the test image to a product
    echo "<h3>Test update a product with the new image:</h3>";
    
    // Get the first product
    $query = "SELECT id, name FROM products ORDER BY id ASC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update this product with the test image
        $update_query = "UPDATE products SET image = :image WHERE id = :id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':image', $test_image);
        $update_stmt->bindParam(':id', $product['id']);
        
        $result = $update_stmt->execute();
        
        echo "<p>Updated product #{$product['id']} ({$product['name']}) with test image: " . 
             ($result ? "SUCCESS" : "FAILED") . "</p>";
        
        echo "<p><a href='index.php'>Go to homepage</a> to see if the product image appears correctly</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?> 