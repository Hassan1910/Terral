<?php
/**
 * Generate Category-Specific Placeholder Images
 * This script creates category-specific placeholder images for products
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

// Simple CSS-based image approach using data URLs instead of GD
$css_placeholders = [
    'Apparel' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800"><defs><linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#e74c3c"/><stop offset="100%" stop-color="#c0392b"/></linearGradient></defs><rect width="800" height="800" fill="url(#gradient)"/><text x="400" y="360" font-family="Arial" font-size="80" text-anchor="middle" fill="white">👕</text><text x="400" y="460" font-family="Arial" font-size="40" font-weight="bold" text-anchor="middle" fill="white">Apparel</text><text x="400" y="520" font-family="Arial" font-size="30" text-anchor="middle" fill="white" opacity="0.8">Terral Online Store</text></svg>'),
    
    'Office Supplies' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800"><defs><linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#34495e"/><stop offset="100%" stop-color="#2c3e50"/></linearGradient></defs><rect width="800" height="800" fill="url(#gradient)"/><text x="400" y="360" font-family="Arial" font-size="80" text-anchor="middle" fill="white">💼</text><text x="400" y="460" font-family="Arial" font-size="40" font-weight="bold" text-anchor="middle" fill="white">Office Supplies</text><text x="400" y="520" font-family="Arial" font-size="30" text-anchor="middle" fill="white" opacity="0.8">Terral Online Store</text></svg>'),
    
    'Promotional' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800"><defs><linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#9b59b6"/><stop offset="100%" stop-color="#8e44ad"/></linearGradient></defs><rect width="800" height="800" fill="url(#gradient)"/><text x="400" y="360" font-family="Arial" font-size="80" text-anchor="middle" fill="white">🎁</text><text x="400" y="460" font-family="Arial" font-size="40" font-weight="bold" text-anchor="middle" fill="white">Promotional</text><text x="400" y="520" font-family="Arial" font-size="30" text-anchor="middle" fill="white" opacity="0.8">Terral Online Store</text></svg>'),
    
    'Stationery' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800"><defs><linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f39c12"/><stop offset="100%" stop-color="#e67e22"/></linearGradient></defs><rect width="800" height="800" fill="url(#gradient)"/><text x="400" y="360" font-family="Arial" font-size="80" text-anchor="middle" fill="white">🖋️</text><text x="400" y="460" font-family="Arial" font-size="40" font-weight="bold" text-anchor="middle" fill="white">Stationery</text><text x="400" y="520" font-family="Arial" font-size="30" text-anchor="middle" fill="white" opacity="0.8">Terral Online Store</text></svg>'),
    
    'Accessories' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800"><defs><linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#1abc9c"/><stop offset="100%" stop-color="#16a085"/></linearGradient></defs><rect width="800" height="800" fill="url(#gradient)"/><text x="400" y="360" font-family="Arial" font-size="80" text-anchor="middle" fill="white">👜</text><text x="400" y="460" font-family="Arial" font-size="40" font-weight="bold" text-anchor="middle" fill="white">Accessories</text><text x="400" y="520" font-family="Arial" font-size="30" text-anchor="middle" fill="white" opacity="0.8">Terral Online Store</text></svg>'),
    
    'Home Décor' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800"><defs><linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#2ecc71"/><stop offset="100%" stop-color="#27ae60"/></linearGradient></defs><rect width="800" height="800" fill="url(#gradient)"/><text x="400" y="360" font-family="Arial" font-size="80" text-anchor="middle" fill="white">🏠</text><text x="400" y="460" font-family="Arial" font-size="40" font-weight="bold" text-anchor="middle" fill="white">Home Décor</text><text x="400" y="520" font-family="Arial" font-size="30" text-anchor="middle" fill="white" opacity="0.8">Terral Online Store</text></svg>'),
    
    'Electronics' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800"><defs><linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#3498db"/><stop offset="100%" stop-color="#2980b9"/></linearGradient></defs><rect width="800" height="800" fill="url(#gradient)"/><text x="400" y="360" font-family="Arial" font-size="80" text-anchor="middle" fill="white">📱</text><text x="400" y="460" font-family="Arial" font-size="40" font-weight="bold" text-anchor="middle" fill="white">Electronics</text><text x="400" y="520" font-family="Arial" font-size="30" text-anchor="middle" fill="white" opacity="0.8">Terral Online Store</text></svg>'),
    
    'Custom' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800"><defs><linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#95a5a6"/><stop offset="100%" stop-color="#7f8c8d"/></linearGradient></defs><rect width="800" height="800" fill="url(#gradient)"/><text x="400" y="360" font-family="Arial" font-size="80" text-anchor="middle" fill="white">🎨</text><text x="400" y="460" font-family="Arial" font-size="40" font-weight="bold" text-anchor="middle" fill="white">Custom</text><text x="400" y="520" font-family="Arial" font-size="30" text-anchor="middle" fill="white" opacity="0.8">Terral Online Store</text></svg>')
];

echo "<h1>Category Placeholder Generator</h1>";

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
    
    // First, save all category placeholder images
    echo "<h2>Generating category placeholder images:</h2>";
    echo "<ul>";
    
    foreach ($css_placeholders as $category => $data_url) {
        $filename = 'category_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $category)) . '.jpg';
        $file_path = $uploads_dir . $filename;
        
        // Extract the base64 image data
        $data = explode(',', $data_url)[1];
        $data = base64_decode($data);
        
        // Save the file
        file_put_contents($file_path, $data);
        
        echo "<li>✅ Created placeholder for {$category} category: {$filename}</li>";
    }
    
    echo "</ul>";
    
    // Now get all products with categories
    $query = "SELECT p.id, p.name, p.image, c.name as category_name 
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.id";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalProducts = count($products);
    
    echo "<p>Found {$totalProducts} products in the database</p>";
    echo "<h2>Assigning category-specific images to products:</h2>";
    echo "<ul>";
    
    // Create a catalog of category-specific placeholders
    $category_placeholder_map = [];
    foreach ($css_placeholders as $category => $data_url) {
        $placeholder_filename = 'category_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $category)) . '.jpg';
        $category_placeholder_map[$category] = $placeholder_filename;
    }
    
    foreach ($products as $product) {
        // Get category name or use 'Custom' if not found
        $category = !empty($product['category_name']) ? $product['category_name'] : 'Custom';
        
        // Find the closest category match
        $assigned_category = 'Custom';
        foreach (array_keys($css_placeholders) as $placeholder_category) {
            if (stripos($category, $placeholder_category) !== false || 
                stripos($placeholder_category, $category) !== false) {
                $assigned_category = $placeholder_category;
                break;
            }
        }
        
        // Get placeholder filename for this category
        $placeholder_filename = $category_placeholder_map[$assigned_category] ?? $category_placeholder_map['Custom'];
        
        // Create a product-specific copy of the placeholder
        $product_image_name = 'product_' . $product['id'] . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $product['name'])) . '.jpg';
        
        // Copy the placeholder to the product-specific filename
        copy($uploads_dir . $placeholder_filename, $uploads_dir . $product_image_name);
        
        // Update the database
        $query = "UPDATE products SET image = :image WHERE id = :id";
        $update_stmt = $conn->prepare($query);
        $update_stmt->bindParam(':image', $product_image_name);
        $update_stmt->bindParam(':id', $product['id']);
        $update_stmt->execute();
        
        echo "<li>✅ Assigned {$assigned_category} image to Product #{$product['id']}: {$product['name']}</li>";
    }
    
    echo "</ul>";
    echo "<hr>";
    echo "<h2>Summary:</h2>";
    echo "<p>Created " . count($css_placeholders) . " category placeholder images</p>";
    echo "<p>Assigned images to {$totalProducts} products</p>";
    
    echo "<hr>";
    echo "<h2>What to do next:</h2>";
    echo "<ol>";
    echo "<li>All products now have category-specific images</li>";
    echo "<li>To upload real images for these products, go to the product edit page in the admin panel</li>";
    echo "<li><a href='index.php'>Return to homepage</a> to view your products with new images</li>";
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