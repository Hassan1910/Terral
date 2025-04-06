<?php
/**
 * Generate Product Images
 * This script creates sample product images and updates the database with them
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

echo "<h1>Product Image Generator</h1>";

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Initialize product helper
    $productHelper = new ProductHelper($conn);
    
    // Ensure the product uploads directory exists
    $uploads_dir = ROOT_PATH . '/api/uploads/products/';
    if (!file_exists($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
        echo "<p>✅ Created uploads directory at {$uploads_dir}</p>";
    }
    
    // Ensure the placeholder image exists
    $productHelper->ensurePlaceholderExists();
    echo "<p>✅ Ensured placeholder image exists</p>";
    
    // Source of the placeholder image
    $placeholder_src = ROOT_PATH . '/assets/images/placeholder.jpg';
    if (!file_exists($placeholder_src)) {
        echo "<p>⚠️ Placeholder image not found at {$placeholder_src}, using system-generated placeholder</p>";
        $placeholder_src = $uploads_dir . 'placeholder.jpg';
        if (!file_exists($placeholder_src)) {
            die("<p style='color: red; font-weight: bold;'>Error: No placeholder image found. Please run fix_product_images.php first.</p>");
        }
    }
    
    // Get all products
    $query = "SELECT id, name, image FROM products";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalProducts = count($products);
    
    echo "<p>Found {$totalProducts} products in the database</p>";
    echo "<hr>";
    echo "<h2>Generating Images:</h2>";
    echo "<ul>";
    
    // Product categories
    $categories = [
        'Apparel', 'Office Supplies', 'Promotional', 'Stationery', 
        'Accessories', 'Home Décor', 'Electronics', 'Custom'
    ];
    
    foreach ($products as $index => $product) {
        // Get category for this product
        $category = $categories[$index % count($categories)];
        
        // Create a product image name
        $image_name = 'product_' . $product['id'] . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $product['name'])) . '.jpg';
        $image_path = $uploads_dir . $image_name;
        
        // Copy the placeholder image
        copy($placeholder_src, $image_path);
        
        // Update the database with the new image
        $query = "UPDATE products SET image = :image WHERE id = :id";
        $update_stmt = $conn->prepare($query);
        $update_stmt->bindParam(':image', $image_name);
        $update_stmt->bindParam(':id', $product['id']);
        $update_stmt->execute();
        
        echo "<li>✅ Created image for Product #{$product['id']}: {$product['name']} - {$category} category</li>";
    }
    
    echo "</ul>";
    echo "<hr>";
    echo "<h2>Summary:</h2>";
    echo "<p>Total products: {$totalProducts}</p>";
    echo "<p>Images generated: {$totalProducts}</p>";
    
    echo "<hr>";
    echo "<h2>What to do next:</h2>";
    echo "<ol>";
    echo "<li>All products now have sample images in the database</li>";
    echo "<li>To upload real images for these products, go to the product edit page in the admin panel</li>";
    echo "<li><a href='index.php'>Return to homepage</a> to verify the images</li>";
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