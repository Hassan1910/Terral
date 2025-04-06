<?php
/**
 * Fix Admin Product Images
 * This script checks all product images in the database and fixes any issues
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

// Initialize counters
$missing_images = 0;
$fixed_images = 0;
$total_products = 0;

echo "<h1>Fix Product Images Tool</h1>";

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Initialize product helper
    $productHelper = new ProductHelper($conn);
    
    // Define uploads directory
    $uploads_dir = ROOT_PATH . '/api/uploads/products/';
    
    // Ensure the placeholder image exists
    $productHelper->ensurePlaceholderExists();
    echo "<p>✅ Checked placeholder image</p>";
    
    // Get all products from database
    $query = "SELECT id, name, image FROM products";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_products = count($products);
    
    echo "<h2>Scanning {$total_products} products for image issues...</h2>";
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Product ID</th>";
    echo "<th>Product Name</th>";
    echo "<th>Current Image</th>";
    echo "<th>Image Path</th>";
    echo "<th>Action</th>";
    echo "</tr>";
    
    // Loop through all products
    foreach ($products as $product) {
        $image_filename = $product['image'];
        $product_id = $product['id'];
        $product_name = htmlspecialchars($product['name']);
        
        // Create product image name based on product ID and name
        $new_image_name = 'product_' . $product_id . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $product_name)) . '.jpg';
        
        echo "<tr>";
        echo "<td>{$product_id}</td>";
        echo "<td>{$product_name}</td>";
        
        // Check if image exists
        $image_path = $uploads_dir . $image_filename;
        $image_exists = !empty($image_filename) && file_exists($image_path);
        
        // Generate public URL
        $image_url = $productHelper->getProductImageUrl($image_filename);
        
        // Display current image
        echo "<td><img src='{$image_url}' alt='{$product_name}' style='max-width: 100px; max-height: 100px;'></td>";
        echo "<td>" . ($image_exists ? $image_path : "<span style='color: red;'>Missing: {$image_path}</span>") . "</td>";
        
        // Check if image needs to be fixed
        if (!$image_exists) {
            $missing_images++;
            
            // Check if the renamed file might exist
            $alternative_path = $uploads_dir . $new_image_name;
            $alternative_exists = file_exists($alternative_path);
            
            if ($alternative_exists) {
                // Update the database with the new image name
                $update_query = "UPDATE products SET image = :image WHERE id = :id";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(':image', $new_image_name);
                $update_stmt->bindParam(':id', $product_id);
                $update_stmt->execute();
                
                echo "<td style='background-color: #d4edda;'>Fixed! Updated database to use existing file: {$new_image_name}</td>";
                $fixed_images++;
            } else {
                // Try to download a placeholder from placehold.co
                $placeholder_url = 'https://placehold.co/600x600/e2e2e2/888888?text=' . urlencode($product_name);
                $placeholder_content = @file_get_contents($placeholder_url);
                
                if ($placeholder_content && file_put_contents($uploads_dir . $new_image_name, $placeholder_content)) {
                    // Update the database with the new image name
                    $update_query = "UPDATE products SET image = :image WHERE id = :id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bindParam(':image', $new_image_name);
                    $update_stmt->bindParam(':id', $product_id);
                    $update_stmt->execute();
                    
                    echo "<td style='background-color: #d4edda;'>Created placeholder image and updated database: {$new_image_name}</td>";
                    $fixed_images++;
                } else {
                    echo "<td style='background-color: #f8d7da;'>Could not fix. <a href='download_product_images.php' target='_blank'>Download a placeholder image</a>.</td>";
                }
            }
        } else {
            echo "<td style='background-color: #d4edda;'>Image OK: {$image_filename}</td>";
        }
        
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Summary
    echo "<h2>Summary</h2>";
    echo "<ul>";
    echo "<li>Total products: {$total_products}</li>";
    echo "<li>Missing images: {$missing_images}</li>";
    echo "<li>Fixed images: {$fixed_images}</li>";
    echo "</ul>";
    
    echo "<h2>Next Steps</h2>";
    echo "<ol>";
    echo "<li>Go to your <a href='index.php'>homepage</a> to check if product images are now displaying correctly</li>";
    echo "<li>If there are still missing images, use the <a href='download_product_images.php'>Download Product Images</a> tool</li>";
    echo "<li>For best results, upload actual product photos through the admin panel</li>";
    echo "</ol>";
    
} catch (PDOException $e) {
    echo "<div style='color: red; font-weight: bold;'>";
    echo "Database error: " . $e->getMessage();
    echo "</div>";
} catch (Exception $e) {
    echo "<div style='color: red; font-weight: bold;'>";
    echo "Error: " . $e->getMessage();
    echo "</div>";
}
?> 