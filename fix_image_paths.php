<?php
/**
 * Fix Image Paths
 * This script creates actual JPG images for products and updates image paths
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

echo "<h1>Product Image Path Fixer</h1>";

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
    
    // Define product categories with colors
    $categories = [
        'Accessories' => ['color' => [26, 188, 156], 'icon' => '👜'],
        'Apparel' => ['color' => [231, 76, 60], 'icon' => '👕'],
        'Home Decor' => ['color' => [46, 204, 113], 'icon' => '🏠'],
        'Office Supplies' => ['color' => [52, 73, 94], 'icon' => '💼'],
        'Promotional' => ['color' => [155, 89, 182], 'icon' => '🎁'],
        'Stationery' => ['color' => [243, 156, 18], 'icon' => '🖋️'],
        'Electronics' => ['color' => [52, 152, 219], 'icon' => '📱'],
        'Custom' => ['color' => [149, 165, 166], 'icon' => '🎨']
    ];
    
    // Get all products
    $query = "SELECT p.id, p.name, p.image, c.name as category_name FROM products p
              LEFT JOIN categories c ON p.category_id = c.id";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalProducts = count($products);
    
    echo "<p>Found {$totalProducts} products in the database</p>";
    echo "<h2>Generating actual image files:</h2>";
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
        $color = $categories[$matched_category]['color'];
        $icon = $categories[$matched_category]['icon'];
        
        // Create a product image name that's URL-friendly
        $image_name = 'product_' . $product['id'] . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $product['name'])) . '.jpg';
        $image_path = $uploads_dir . $image_name;
        
        // Create a simple colored image (600x600)
        $width = 600;
        $height = 600;
        
        // Use GD library if available, otherwise create a simple HTML file
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor($width, $height);
            
            // Set background color
            $bg_color = imagecolorallocate($img, $color[0], $color[1], $color[2]);
            imagefill($img, 0, 0, $bg_color);
            
            // Add product name text
            $text_color = imagecolorallocate($img, 255, 255, 255);
            $font_size = 5;
            
            // Center text
            $product_name = $product['name'];
            if (strlen($product_name) > 20) {
                $product_name = substr($product_name, 0, 20) . "...";
            }
            
            $text_width = imagefontwidth($font_size) * strlen($product_name);
            $text_x = ($width - $text_width) / 2;
            $text_y = $height / 2;
            
            imagestring($img, $font_size, $text_x, $text_y, $product_name, $text_color);
            
            // Save the image
            imagejpeg($img, $image_path, 90);
            imagedestroy($img);
        } else {
            // Alternative: Create a simple colored HTML that can be screenshot
            $html = "<!DOCTYPE html><html><head><style>body{margin:0;padding:0;background:rgb({$color[0]},{$color[1]},{$color[2]});display:flex;justify-content:center;align-items:center;height:100vh;font-family:Arial;color:white;text-align:center;}</style></head><body><div><div style='font-size:80px;margin-bottom:20px;'>{$icon}</div><div style='font-size:24px;font-weight:bold;'>{$product['name']}</div><div style='margin-top:10px;opacity:0.8;'>Terral Online Store</div></div></body></html>";
            file_put_contents($image_path . '.html', $html);
            
            // Create a blank JPG as a placeholder
            $blank = imagecreatetruecolor(1, 1);
            $white = imagecolorallocate($blank, 255, 255, 255);
            imagefill($blank, 0, 0, $white);
            imagejpeg($blank, $image_path);
            imagedestroy($blank);
            
            echo "<li>⚠️ GD library not available. Created HTML template for {$product['name']} at {$image_path}.html</li>";
        }
        
        // Update the database with the correct image name
        $query = "UPDATE products SET image = :image WHERE id = :id";
        $update_stmt = $conn->prepare($query);
        $update_stmt->bindParam(':image', $image_name);
        $update_stmt->bindParam(':id', $product['id']);
        $update_stmt->execute();
        
        echo "<li>✅ Fixed image for Product #{$product['id']}: {$product['name']} ({$matched_category})</li>";
    }
    
    echo "</ul>";
    
    // Create generic placeholder if it doesn't exist
    $placeholder_path = $uploads_dir . 'placeholder.jpg';
    if (!file_exists($placeholder_path)) {
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(600, 600);
            $bg = imagecolorallocate($img, 200, 200, 200);
            $text_color = imagecolorallocate($img, 255, 255, 255);
            
            imagefill($img, 0, 0, $bg);
            imagestring($img, 5, 220, 280, "No Image", $text_color);
            
            imagejpeg($img, $placeholder_path, 90);
            imagedestroy($img);
        } else {
            // Create a blank placeholder
            $html = "<!DOCTYPE html><html><head><style>body{margin:0;padding:0;background:#ccc;display:flex;justify-content:center;align-items:center;height:100vh;font-family:Arial;color:white;}</style></head><body><div>No Image</div></body></html>";
            file_put_contents($placeholder_path . '.html', $html);
            
            // Create a blank JPG as a placeholder
            $blank = imagecreatetruecolor(1, 1);
            $gray = imagecolorallocate($blank, 200, 200, 200);
            imagefill($blank, 0, 0, $gray);
            imagejpeg($blank, $placeholder_path);
            imagedestroy($blank);
        }
        
        echo "<p>✅ Created placeholder image at {$placeholder_path}</p>";
    }
    
    echo "<hr>";
    echo "<h2>Summary:</h2>";
    echo "<p>Fixed images for {$totalProducts} products</p>";
    
    echo "<hr>";
    echo "<h2>Next Steps:</h2>";
    echo "<ol>";
    echo "<li>Go to <a href='index.php'>the homepage</a> to see if images are displaying correctly now</li>";
    echo "<li>For best results, upload actual product images through the admin panel</li>";
    echo "</ol>";
    
    // Add a note if GD is not available
    if (!function_exists('imagecreatetruecolor')) {
        echo "<div style='margin-top: 20px; padding: 15px; border-left: 4px solid #f39c12; background: #fffde7;'>";
        echo "<strong>Note:</strong> GD library is not available on this server. While image paths have been fixed, ";
        echo "you'll need to manually create images for best results. The HTML templates (*.html files) in the products ";
        echo "folder can be opened in a browser and screenshot to create proper images.";
        echo "</div>";
    }
    
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