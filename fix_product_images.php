<?php
/**
 * Product Image Fix Script
 * 
 * This script fixes issues with product images by:
 * 1. Moving images from /uploads/products/ to /api/uploads/products/ if needed
 * 2. Updating database image paths
 */

// Start output buffering to catch errors
ob_start();

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', __DIR__);

echo "<h1>Product Image Fix Tool</h1>";
echo "<p>Initializing...</p>";

// Debug information
echo "<div style='background-color: #f8f8f8; padding: 10px; margin: 10px 0; border: 1px solid #ddd;'>";
echo "<h3>Debug Information</h3>";
echo "<p>Root Path: " . ROOT_PATH . "</p>";
echo "<p>Script Path: " . __FILE__ . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "</div>";

try {
// Include necessary files
    echo "<p>Loading database configuration...</p>";
    if (!file_exists(ROOT_PATH . '/api/config/Database.php')) {
        throw new Exception("Database.php file not found at path: " . ROOT_PATH . '/api/config/Database.php');
    }
    
require_once ROOT_PATH . '/api/config/Database.php';
    echo "<p>Database configuration loaded.</p>";

// Create database connection
    echo "<p>Connecting to database...</p>";
$database = new Database();
$conn = $database->getConnection();
    echo "<p>Database connection established.</p>";

    // Source and destination directories
    $old_dir = ROOT_PATH . '/uploads/products/';
    $new_dir = ROOT_PATH . '/api/uploads/products/';
    
    echo "<p>Old directory: {$old_dir}</p>";
    echo "<p>New directory: {$new_dir}</p>";
    
    echo "<p>Checking if directories exist...</p>";
    echo "<p>Old directory exists: " . (file_exists($old_dir) ? 'Yes' : 'No') . "</p>";
    echo "<p>New directory exists: " . (file_exists($new_dir) ? 'Yes' : 'No') . "</p>";

    // Create the new directory if it doesn't exist
    if (!file_exists($new_dir)) {
        echo "<p>Attempting to create new directory...</p>";
        if (mkdir($new_dir, 0777, true)) {
            echo "<p style='color:green'>Created directory: {$new_dir}</p>";
        } else {
            echo "<p style='color:red'>Failed to create directory: {$new_dir}</p>";
            throw new Exception("Failed to create directory: {$new_dir}. Check permissions.");
        }
    }

    // Function to check if image exists in a path
    function image_exists($path, $filename) {
        if (empty($filename)) return false;
        return file_exists($path . $filename);
    }

    // Get all products with images
    echo "<p>Fetching products with images from database...</p>";
    $query = "SELECT id, image FROM products WHERE image IS NOT NULL AND image != ''";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $count = $stmt->rowCount();
    echo "<p>Found {$count} products with images.</p>";

    // Process each product
    $processed = 0;
    $fixed = 0;
    $errors = 0;

    echo "<h2>Processing Products</h2>";
    echo "<ul>";
    
    while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $processed++;
        $image_filename = $product['image'];
        
        echo "<li>Processing product ID {$product['id']} with image: {$image_filename}";
        
        // Skip if image is null or empty
        if (empty($image_filename)) {
            echo " - <span style='color:orange'>Skipped (no image)</span></li>";
            continue;
        }
        
        $exists_in_old = image_exists($old_dir, $image_filename);
        $exists_in_new = image_exists($new_dir, $image_filename);
        
        // Case 1: Image exists in old location but not in new
        if ($exists_in_old && !$exists_in_new) {
            // Copy file to new location
            echo " - Copying from: " . $old_dir . $image_filename . " to: " . $new_dir . $image_filename;
            if (copy($old_dir . $image_filename, $new_dir . $image_filename)) {
                echo " - <span style='color:green'>Moved image from old location to new</span>";
                $fixed++;
            } else {
                echo " - <span style='color:red'>Failed to move image (Permission error?)</span>";
                $errors++;
            }
        } 
        // Case 2: Image doesn't exist in either location
        else if (!$exists_in_old && !$exists_in_new) {
            echo " - <span style='color:red'>Image file not found in any location</span>";
            $errors++;
        }
        // Case 3: Image already exists in new location
        else if ($exists_in_new) {
            echo " - <span style='color:green'>Image already in correct location</span>";
        }
        
        echo "</li>";
    }

    echo "</ul>";

    // Summary
    echo "<h2>Summary</h2>";
    echo "<p>Total products processed: {$processed}</p>";
    echo "<p>Fixed images: {$fixed}</p>";
    echo "<p>Errors: {$errors}</p>";

    // Add a link back to admin
    echo "<p><a href='admin/products.php'>Return to Admin Products</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; color: #721c24;'>";
    echo "<h3>Error Occurred</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

// Output any errors that might have been suppressed
$output = ob_get_contents();
ob_end_clean();

if (empty($output)) {
    echo "<h1>Error: Empty Output</h1>";
    echo "<p>The script executed but produced no output. This may indicate a PHP error or permission issue.</p>";
    
    // Check for PHP errors
    echo "<h2>Checking for PHP Errors</h2>";
    $error_log = ini_get('error_log');
    echo "<p>PHP error log is located at: " . ($error_log ? $error_log : 'Not configured') . "</p>";
    
    // Check directory permissions
    echo "<h2>Directory Permissions</h2>";
    echo "<p>Current directory: " . getcwd() . "</p>";
    echo "<p>Is writable: " . (is_writable(getcwd()) ? 'Yes' : 'No') . "</p>";
    
    if (file_exists(ROOT_PATH . '/uploads')) {
        echo "<p>'/uploads' directory exists and " . (is_writable(ROOT_PATH . '/uploads') ? 'is' : 'is NOT') . " writable</p>";
    } else {
        echo "<p>'/uploads' directory does not exist</p>";
    }
    
    if (file_exists(ROOT_PATH . '/api/uploads')) {
        echo "<p>'/api/uploads' directory exists and " . (is_writable(ROOT_PATH . '/api/uploads') ? 'is' : 'is NOT') . " writable</p>";
    } else {
        echo "<p>'/api/uploads' directory does not exist</p>";
    }
} else {
    // Output the buffered content
    echo $output;
}
?>