<?php
/**
 * Direct Product Image Fix
 * This script creates direct image links from the database for all products
 */

// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Direct Product Image Fix</h1>";

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Ensure the uploads directory exists
    $uploads_dir = ROOT_PATH . '/api/uploads/products/';
    if (!file_exists($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
        echo "<p>Created uploads directory</p>";
    }
    
    // Ensure we have a placeholder image
    $placeholder_path = $uploads_dir . 'placeholder.jpg';
    if (!file_exists($placeholder_path)) {
        // Try to create a simple placeholder
        if (function_exists('imagecreate')) {
            $image = imagecreate(600, 600);
            $bg = imagecolorallocate($image, 52, 152, 219); // #3498db
            $text_color = imagecolorallocate($image, 255, 255, 255);
            imagestring($image, 5, 250, 290, "No Image", $text_color);
            imagejpeg($image, $placeholder_path, 90);
            imagedestroy($image);
            echo "<p>Created placeholder image using GD library</p>";
        } else {
            // Copy from assets if GD not available
            $asset_placeholder = ROOT_PATH . '/assets/images/placeholder.jpg';
            if (file_exists($asset_placeholder)) {
                copy($asset_placeholder, $placeholder_path);
                echo "<p>Copied placeholder from assets</p>";
            } else {
                // Just create an empty file
                file_put_contents($placeholder_path, "PLACEHOLDER");
                echo "<p>Created empty placeholder file</p>";
            }
        }
    }
    
    // Get all products
    $query = "SELECT id, name, image FROM products";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($products);
    
    echo "<p>Found {$total} products in database</p>";
    
    // Track our progress
    $updated = 0;
    $has_images = 0;
    
    // Create a table to show results
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse'>";
    echo "<tr><th>ID</th><th>Name</th><th>Image</th><th>Action</th></tr>";
    
    foreach ($products as $product) {
        $id = $product['id'];
        $name = htmlspecialchars($product['name']);
        $image = $product['image'];
        $image_path = !empty($image) ? ($uploads_dir . $image) : '';
        
        // Check if image exists
        $has_image = !empty($image) && file_exists($image_path);
        if ($has_image) {
            $has_images++;
        }
        
        echo "<tr>";
        echo "<td>{$id}</td>";
        echo "<td>{$name}</td>";
        
        if ($has_image) {
            // If has image, just show it
            echo "<td><img src='/Terral2/api/uploads/products/{$image}' height='50'></td>";
            echo "<td>Image OK</td>";
        } else {
            // Create a new filename based on product ID and name
            $new_image = 'product_' . $id . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name)) . '.jpg';
            $new_path = $uploads_dir . $new_image;
            
            // Copy the placeholder
            copy($placeholder_path, $new_path);
            
            // Update the database
            $update = "UPDATE products SET image = :image WHERE id = :id";
            $update_stmt = $conn->prepare($update);
            $update_stmt->bindParam(':image', $new_image);
            $update_stmt->bindParam(':id', $id);
            $update_stmt->execute();
            
            echo "<td><img src='/Terral2/api/uploads/products/{$new_image}' height='50'></td>";
            echo "<td>Created new image: {$new_image}</td>";
            $updated++;
        }
        
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<h2>Summary</h2>";
    echo "<p>Total products: {$total}</p>";
    echo "<p>Products with existing images: {$has_images}</p>";
    echo "<p>Products updated with new images: {$updated}</p>";
    
    echo "<h2>Next Steps</h2>";
    echo "<ol>";
    echo "<li>Return to your <a href='index.php'>homepage</a> to see your products with images</li>";
    echo "<li>All products now have direct image file links instead of 'Product Image' text</li>";
    echo "<li>For better visuals, upload actual product images through the admin panel</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<div style='color: red; font-weight: bold;'>";
    echo "Error: " . $e->getMessage();
    echo "</div>";
}
?> 