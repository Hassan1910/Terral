<?php
/**
 * Fix ProductHelper Class
 * This script checks if the ProductHelper class has issues handling image paths
 * and creates a new version if needed
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

echo "<h1>ProductHelper Image Path Fixer</h1>";

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Initialize product helper
    $productHelper = new ProductHelper($conn);
    
    // Get path to ProductHelper class
    $reflection = new ReflectionClass('ProductHelper');
    $helper_file = $reflection->getFileName();
    
    echo "<p>ProductHelper file found at: " . htmlspecialchars($helper_file) . "</p>";
    
    // Test the getProductImageUrl function
    echo "<h2>Testing current ProductHelper implementation:</h2>";
    
    $test_images = [
        'test.jpg', 
        '/test.jpg', 
        'placeholder.jpg', 
        'api/uploads/products/test.jpg',
        '/api/uploads/products/test.jpg',
        'https://example.com/image.jpg',
        '',
        null
    ];
    
    echo "<ul>";
    foreach ($test_images as $test_img) {
        echo "<li>Testing path: " . ($test_img === null ? 'NULL' : "'{$test_img}'") . " → Result: " . $productHelper->getProductImageUrl($test_img) . "</li>";
    }
    echo "</ul>";
    
    // Create backup of the original file
    $backup_file = $helper_file . '.backup.' . date('Y-m-d-H-i-s');
    copy($helper_file, $backup_file);
    echo "<p>✅ Created backup of original file at: " . htmlspecialchars($backup_file) . "</p>";
    
    // Read the current file
    $content = file_get_contents($helper_file);
    
    // Check if there are issues with the getProductImageUrl method
    if (preg_match('/function getProductImageUrl\(.*?\)\s*\{.*?\}/s', $content, $matches)) {
        $current_method = $matches[0];
        echo "<h3>Current implementation:</h3>";
        echo "<pre>" . htmlspecialchars($current_method) . "</pre>";
        
        // Create improved version of the method
        $improved_method = <<<'METHOD'
    /**
     * Get product image URL
     * @param string|null $image_filename Image filename
     * @return string Image URL or placeholder URL if image doesn't exist
     */
    public function getProductImageUrl($image_filename) {
        // If image is null or empty, return placeholder
        if (empty($image_filename)) {
            return '/Terral2/api/uploads/products/placeholder.jpg';
        }
        
        // If image is already a full URL, return it as is
        if (filter_var($image_filename, FILTER_VALIDATE_URL)) {
            return $image_filename;
        }
        
        // Handle direct path to file (both with and without leading slash)
        if (strpos($image_filename, '/api/uploads/products/') === 0 || strpos($image_filename, 'api/uploads/products/') === 0) {
            // It's already a web path, just make sure it has the correct root
            $clean_path = ltrim($image_filename, '/');
            return '/Terral2/' . $clean_path;
        }
        
        // Check if the image exists in the uploads directory
        $image_path = $this->uploads_dir . $image_filename;
        
        if (file_exists($image_path)) {
            return '/Terral2/api/uploads/products/' . $image_filename;
        } else {
            // Log the missing image for debugging
            error_log("Product image not found: " . $image_path);
            
            // Try alternative paths
            $alt_paths = [
                $this->uploads_dir . '/' . $image_filename,
                str_replace('//', '/', $this->uploads_dir . $image_filename),
                $this->uploads_dir . basename($image_filename)
            ];
            
            foreach ($alt_paths as $alt_path) {
                if (file_exists($alt_path)) {
                    return '/Terral2/api/uploads/products/' . basename($alt_path);
                }
            }
            
            // Return the placeholder image
            return '/Terral2/api/uploads/products/placeholder.jpg';
        }
    }
METHOD;
        
        // Replace the method in the content
        $updated_content = preg_replace('/function getProductImageUrl\(.*?\)\s*\{.*?\}/s', $improved_method, $content);
        
        // Write the updated content to the file
        if (file_put_contents($helper_file, $updated_content)) {
            echo "<p>✅ Successfully updated the ProductHelper class with improved image handling!</p>";
            echo "<h3>New implementation:</h3>";
            echo "<pre>" . htmlspecialchars($improved_method) . "</pre>";
        } else {
            echo "<p style='color:red;'>❌ Failed to update the ProductHelper class file. Please check permissions.</p>";
        }
    } else {
        echo "<p style='color:red;'>❌ Could not locate the getProductImageUrl method in the ProductHelper class.</p>";
        echo "<p>You may need to update the method manually. Here's an improved version you can use:</p>";
        echo "<pre>" . htmlspecialchars('
    /**
     * Get product image URL
     * @param string|null $image_filename Image filename
     * @return string Image URL or placeholder URL if image doesn\'t exist
     */
    public function getProductImageUrl($image_filename) {
        // If image is null or empty, return placeholder
        if (empty($image_filename)) {
            return \'/Terral2/api/uploads/products/placeholder.jpg\';
        }
        
        // If image is already a full URL, return it as is
        if (filter_var($image_filename, FILTER_VALIDATE_URL)) {
            return $image_filename;
        }
        
        // Handle direct path to file (both with and without leading slash)
        if (strpos($image_filename, \'/api/uploads/products/\') === 0 || strpos($image_filename, \'api/uploads/products/\') === 0) {
            // It\'s already a web path, just make sure it has the correct root
            $clean_path = ltrim($image_filename, \'/\');
            return \'/Terral2/\' . $clean_path;
        }
        
        // Check if the image exists in the uploads directory
        $image_path = $this->uploads_dir . $image_filename;
        
        if (file_exists($image_path)) {
            return \'/Terral2/api/uploads/products/\' . $image_filename;
        } else {
            // Log the missing image for debugging
            error_log("Product image not found: " . $image_path);
            
            // Try alternative paths
            $alt_paths = [
                $this->uploads_dir . \'/\' . $image_filename,
                str_replace(\'//\', \'/\', $this->uploads_dir . $image_filename),
                $this->uploads_dir . basename($image_filename)
            ];
            
            foreach ($alt_paths as $alt_path) {
                if (file_exists($alt_path)) {
                    return \'/Terral2/api/uploads/products/\' . basename($alt_path);
                }
            }
            
            // Return the placeholder image
            return \'/Terral2/api/uploads/products/placeholder.jpg\';
        }
    }') . "</pre>";
    }
    
    echo "<hr>";
    echo "<h2>Next Steps:</h2>";
    echo "<ol>";
    echo "<li>Run <a href='fix_admin_product_images.php'>fix_admin_product_images.php</a> to check and fix product image database entries</li>";
    echo "<li>Go to <a href='index.php'>the homepage</a> to see if images are displaying correctly now</li>";
    echo "<li>If you still have issues, check your web server configuration and file permissions</li>";
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