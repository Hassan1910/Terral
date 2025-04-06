<?php
/**
 * Quick Fix Product Images
 * This script directly updates the database with URLs to placeholder images
 */

// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🚀 Quick Fix: Product Images</h1>";

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Define product categories with colors
    $categories = [
        'Accessories' => '#1abc9c',
        'Apparel' => '#e74c3c',
        'Home Decor' => '#2ecc71',
        'Office Supplies' => '#34495e',
        'Promotional' => '#9b59b6',
        'Stationery' => '#f39c12',
        'Electronics' => '#3498db',
        'Custom' => '#95a5a6'
    ];
    
    // Get all products
    $query = "SELECT p.id, p.name, p.image, c.name as category_name FROM products p
              LEFT JOIN categories c ON p.category_id = c.id";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalProducts = count($products);
    
    echo "<p>Found {$totalProducts} products in the database</p>";
    echo "<h2>Updating product images:</h2>";
    echo "<ul>";
    
    foreach ($products as $product) {
        // Determine category
        $category = !empty($product['category_name']) ? $product['category_name'] : 'Custom';
        
        // Find the closest category match
        $matched_category = 'Custom';
        foreach (array_keys($categories) as $cat) {
            if (stripos($category, $cat) !== false || stripos($cat, $category) !== false) {
                $matched_category = $cat;
                break;
            }
        }
        
        // Get color for this category
        $color = $categories[$matched_category];
        $color_hex = substr($color, 1); // Remove #
        
        // Create a formatted product name for the image URL
        $product_text = urlencode($product['name']);
        
        // Create the placeholder URL - this is a direct web URL to an image
        $image_url = "https://placehold.co/600x600/{$color_hex}/ffffff?text={$product_text}";
        
        // Update the database with the image URL
        $query = "UPDATE products SET image = :image WHERE id = :id";
        $update_stmt = $conn->prepare($query);
        $update_stmt->bindParam(':image', $image_url);
        $update_stmt->bindParam(':id', $product['id']);
        $update_stmt->execute();
        
        echo "<li>✅ Updated image for Product #{$product['id']}: {$product['name']} ({$matched_category}) <div style='display:inline-block;width:20px;height:20px;background:{$color};vertical-align:middle;margin-left:5px;'></div></li>";
    }
    
    echo "</ul>";
    echo "<hr>";
    echo "<h2>Summary:</h2>";
    echo "<p>Updated images for {$totalProducts} products</p>";
    
    echo "<hr>";
    echo "<h2>Next Steps:</h2>";
    echo "<ol>";
    echo "<li>Go to <a href='index.php'>the homepage</a> to see your products with images</li>";
    echo "<li>The images are now loaded directly from placehold.co</li>";
    echo "<li>For better performance, you should download the images and upload them to your server later</li>";
    echo "<li>You can use our <a href='download_product_images.php'>Product Image Generator</a> to download all the images at once</li>";
    echo "</ol>";
    
    echo "<div style='margin-top: 20px; padding: 15px; border-left: 4px solid #27ae60; background: #eafaf1;'>";
    echo "<strong>Success!</strong> Your product images should now work properly on your website. ";
    echo "This is a temporary solution using a third-party image service. For a permanent solution, ";
    echo "download the images and host them on your own server.";
    echo "</div>";
    
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