<?php
/**
 * Fix Missing Product Images
 * Script to repair product images that are missing or have incorrect paths
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

echo "<h1>Fix Missing Product Images</h1>";

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Initialize product helper
    $productHelper = new ProductHelper($conn);
    
    // Ensure the uploads directory exists
    $uploads_dir = ROOT_PATH . '/api/uploads/products/';
    if (!file_exists($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }
    
    echo "<p>Product uploads directory: {$uploads_dir}</p>";
    
    // Create a placeholder image if it doesn't exist
    $placeholder_file = $uploads_dir . 'placeholder.jpg';
    
    if (!file_exists($placeholder_file)) {
        // Create a simple placeholder image
        $image = imagecreate(500, 500);
        $background = imagecolorallocate($image, 240, 240, 240);
        $text_color = imagecolorallocate($image, 100, 100, 100);
        
        // Fill background
        imagefill($image, 0, 0, $background);
        
        // Add text
        imagestring($image, 5, 180, 240, "No Image Available", $text_color);
        
        // Save image
        imagejpeg($image, $placeholder_file);
        imagedestroy($image);
        
        echo "<p>✅ Created placeholder image: {$placeholder_file}</p>";
    } else {
        echo "<p>✅ Placeholder image already exists.</p>";
    }
    
    // Get all products
    $query = "SELECT id, name, image FROM products";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalProducts = count($products);
    $missingImages = 0;
    $fixedProducts = 0;
    
    echo "<p>Found {$totalProducts} products in the database</p>";
    
    echo "<h2>Processing Products:</h2>";
    echo "<ul>";
    
    foreach ($products as $product) {
        $productId = $product['id'];
        $productName = $product['name'];
        $imageName = $product['image'];
        $imagePath = $uploads_dir . $imageName;
        
        echo "<li>Product #{$productId} - {$productName}:<br>";
        
        // Check if product has an image specified
        if (empty($imageName)) {
            echo "⚠️ No image specified - assigning placeholder<br>";
            $missingImages++;
            
            // Update product to use placeholder
            $query = "UPDATE products SET image = 'placeholder.jpg' WHERE id = :id";
            $updateStmt = $conn->prepare($query);
            $updateStmt->bindParam(':id', $productId);
            $updateStmt->execute();
            $fixedProducts++;
            
            echo "✅ Updated to use placeholder image</li>";
            continue;
        }
        
        // Check if image file exists
        if (!file_exists($imagePath)) {
            echo "❌ Image file not found: {$imageName}<br>";
            $missingImages++;
            
            // Create a test image with the product name
            $new_image = 'product_' . uniqid() . '.jpg';
            $new_path = $uploads_dir . $new_image;
            
            // Create the test image
            $image = imagecreate(500, 500);
            $background = imagecolorallocate($image, 50, 150, 255);
            $text_color = imagecolorallocate($image, 255, 255, 255);
            
            // Add text with product name
            imagestring($image, 5, 20, 240, "Product: " . $productName, $text_color);
            
            // Save image
            imagejpeg($image, $new_path);
            imagedestroy($image);
            
            // Update product in database
            $query = "UPDATE products SET image = :image WHERE id = :id";
            $updateStmt = $conn->prepare($query);
            $updateStmt->bindParam(':image', $new_image);
            $updateStmt->bindParam(':id', $productId);
            $updateStmt->execute();
            $fixedProducts++;
            
            echo "✅ Created new image and updated database: {$new_image}</li>";
        } else {
            echo "✅ Image file exists: {$imageName}</li>";
        }
    }
    
    echo "</ul>";
    
    echo "<h2>Summary:</h2>";
    echo "<p>Total products: {$totalProducts}</p>";
    echo "<p>Products with missing images: {$missingImages}</p>";
    echo "<p>Products fixed: {$fixedProducts}</p>";
    
    echo "<h2>Next Steps:</h2>";
    echo "<ol>";
    echo "<li>Go to <a href='index.php'>homepage</a> to verify product images are showing correctly</li>";
    echo "<li>For any products still missing images, upload new images through the admin panel</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<div style='color: red; font-weight: bold;'>";
    echo "Error: " . $e->getMessage();
    echo "<br>File: " . $e->getFile() . " (Line: " . $e->getLine() . ")";
    echo "</div>";
}
?> 