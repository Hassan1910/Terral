<?php
/**
 * Simple Product Image Fix Script
 * 
 * A simplified version that focuses on just moving images with minimal output
 */

// Basic error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define paths
$root_path = __DIR__;
$old_dir = $root_path . '/uploads/products/';
$new_dir = $root_path . '/api/uploads/products/';

echo "<h1>Simple Product Image Fix</h1>";

// Create the destination directory if needed
if (!file_exists($new_dir)) {
    if (!mkdir($new_dir, 0777, true)) {
        die("Failed to create directory: {$new_dir}");
    }
}

// Connect to database
try {
    require_once $root_path . '/api/config/Database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get products with images
    $query = "SELECT id, image FROM products WHERE image IS NOT NULL AND image != ''";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    // Setup counters
    $processed = 0;
    $fixed = 0;
    $errors = 0;
    
    // Process each product
    echo "<div style='margin: 20px 0; border: 1px solid #ccc; padding: 10px; max-height: 300px; overflow-y: auto;'>";
    while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $processed++;
        $image_filename = $product['image'];
        
        // Skip empty filenames
        if (empty($image_filename)) continue;
        
        // Check where the image exists
        $exists_in_old = file_exists($old_dir . $image_filename);
        $exists_in_new = file_exists($new_dir . $image_filename);
        
        echo "<p>Product {$product['id']}: {$image_filename} - ";
        
        // Copy from old to new location if needed
        if ($exists_in_old && !$exists_in_new) {
            if (copy($old_dir . $image_filename, $new_dir . $image_filename)) {
                echo "<span style='color: green'>Copied to new location</span>";
                $fixed++;
            } else {
                echo "<span style='color: red'>Copy failed</span>";
                $errors++;
            }
        } elseif (!$exists_in_old && !$exists_in_new) {
            echo "<span style='color: orange'>Image not found</span>";
            $errors++;
        } elseif ($exists_in_new) {
            echo "<span style='color: blue'>Already in correct location</span>";
        }
        
        echo "</p>";
    }
    echo "</div>";
    
    // Display summary
    echo "<div style='margin: 20px 0; background-color: #f5f5f5; padding: 10px; border-radius: 5px;'>";
    echo "<h2>Summary</h2>";
    echo "<p>Products processed: {$processed}</p>";
    echo "<p>Images fixed: {$fixed}</p>";
    echo "<p>Errors: {$errors}</p>";
    echo "</div>";
    
    echo "<p><a href='admin/products.php'>Return to Admin Products</a></p>";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
    echo "<h3>Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?> 