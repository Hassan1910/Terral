<?php
/**
 * Debug Image Paths - Diagnose product image issues
 */

// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/helpers/ProductHelper.php';

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Product Image Path Debugger</h1>";

try {
    // Check upload directory paths
    $api_root = ROOT_PATH . '/api';
    $upload_path = $api_root . '/uploads';
    $products_path = $upload_path . '/products';
    
    echo "<h2>Directory Information:</h2>";
    echo "<ul>";
    echo "<li>Root Path: " . ROOT_PATH . " (Exists: " . (file_exists(ROOT_PATH) ? "Yes" : "No") . ", Writable: " . (is_writable(ROOT_PATH) ? "Yes" : "No") . ")</li>";
    echo "<li>API Root: " . $api_root . " (Exists: " . (file_exists($api_root) ? "Yes" : "No") . ", Writable: " . (is_writable($api_root) ? "Yes" : "No") . ")</li>";
    echo "<li>Upload Path: " . $upload_path . " (Exists: " . (file_exists($upload_path) ? "Yes" : "No") . ", Writable: " . (is_writable($upload_path) ? "Yes" : "No") . ")</li>";
    echo "<li>Products Path: " . $products_path . " (Exists: " . (file_exists($products_path) ? "Yes" : "No") . ", Writable: " . (is_writable($products_path) ? "Yes" : "No") . ")</li>";
    echo "</ul>";
    
    // Create a test image if the products directory exists
    if (file_exists($products_path) && is_writable($products_path)) {
        $test_image = 'test_image_' . uniqid() . '.jpg';
        $test_path = $products_path . '/' . $test_image;
        
        // Create a simple image
        $image = imagecreate(500, 500);
        $background = imagecolorallocate($image, 255, 0, 0); // Red background
        $text_color = imagecolorallocate($image, 255, 255, 255); // White text
        
        // Add text
        imagestring($image, 5, 150, 240, "Test Image", $text_color);
        
        // Save image
        imagejpeg($image, $test_path);
        imagedestroy($image);
        
        echo "<h2>Test Image Created:</h2>";
        echo "<p>Created test image at: " . $test_path . "</p>";
        echo "<p>File exists: " . (file_exists($test_path) ? "Yes" : "No") . "</p>";
        echo "<p>File size: " . (file_exists($test_path) ? filesize($test_path) . " bytes" : "N/A") . "</p>";
        
        // Check database connection
        $database = new Database();
        $conn = $database->getConnection();
        
        // Insert test image into database
        $query = "INSERT INTO products (name, description, price, stock, image, status, is_customizable) 
                 VALUES (:name, :description, :price, :stock, :image, :status, :is_customizable)";
        $stmt = $conn->prepare($query);
        
        // Bind parameters
        $name = "Test Product " . uniqid();
        $description = "This is a test product created by the debugging script";
        $price = 99.99;
        $stock = 10;
        $status = "active";
        $is_customizable = 0;
        
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':image', $test_image);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':is_customizable', $is_customizable);
        
        // Execute query
        if ($stmt->execute()) {
            $product_id = $conn->lastInsertId();
            echo "<p>Test product created in database with ID: " . $product_id . "</p>";
            echo "<p>Image filename in database: " . $test_image . "</p>";
            
            echo "<h2>Image URLs:</h2>";
            
            // Create ProductHelper instance
            $productHelper = new ProductHelper($conn);
            $image_url = $productHelper->getProductImageUrl($test_image);
            
            echo "<ul>";
            echo "<li>Image URL from ProductHelper: " . $image_url . "</li>";
            echo "<li>Direct URL to image: /Terral2/api/uploads/products/" . $test_image . "</li>";
            echo "<li>Absolute path to image: " . $test_path . "</li>";
            echo "</ul>";
            
            echo "<h2>Test Image Display:</h2>";
            echo "<img src='" . $image_url . "' alt='Test Image' style='max-width: 300px;'>";
            
            echo "<p><a href='index.php'>Go to homepage</a> to check if the test product appears with the image.</p>";
        } else {
            echo "<p style='color: red;'>Failed to create test product in database.</p>";
        }
    } else {
        echo "<p style='color: red;'>Unable to create test image. Products directory does not exist or is not writable.</p>";
    }
    
    // List existing products and their images
    echo "<h2>Existing Products in Database:</h2>";
    
    $query = "SELECT id, name, image FROM products ORDER BY id DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Image Filename</th><th>Image Exists</th><th>Image Preview</th></tr>";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $image_path = $products_path . '/' . $row['image'];
            $image_exists = file_exists($image_path);
            
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['name'] . "</td>";
            echo "<td>" . $row['image'] . "</td>";
            echo "<td style='color: " . ($image_exists ? "green" : "red") . ";'>" . ($image_exists ? "Yes" : "No") . "</td>";
            echo "<td>";
            if ($image_exists) {
                echo "<img src='/Terral2/api/uploads/products/" . $row['image'] . "' style='max-width: 100px;'>";
            } else {
                echo "Image not found";
            }
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No products found in the database.</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; font-weight: bold;'>";
    echo "Error: " . $e->getMessage();
    echo "<br>File: " . $e->getFile() . " (Line: " . $e->getLine() . ")";
    echo "</div>";
}
?> 