<?php
/**
 * Activate Modern Theme
 * This script makes a backup of your existing index.php and replaces it with the new modern theme
 */

// Define root path
define('ROOT_PATH', __DIR__);

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Terral Modern Theme Activation</h1>";

// Check if modern theme files exist
if (!file_exists(ROOT_PATH . '/assets/css/modern-theme.css')) {
    die("<p style='color: red;'>Error: Modern theme CSS file not found. Please ensure you've created the modern-theme.css file.</p>");
}

if (!file_exists(ROOT_PATH . '/modern-index.php')) {
    die("<p style='color: red;'>Error: Modern index file not found. Please ensure you've created the modern-index.php file.</p>");
}

// Create a backup of current index.php
$original_index = ROOT_PATH . '/index.php';
$backup_index = ROOT_PATH . '/index.php.backup-' . date('Y-m-d-H-i-s');

if (file_exists($original_index)) {
    // Make a backup
    if (copy($original_index, $backup_index)) {
        echo "<p style='color: green;'>✅ Created backup of original index.php at: " . basename($backup_index) . "</p>";
    } else {
        die("<p style='color: red;'>Error: Failed to create backup of index.php. Please check permissions.</p>");
    }
} else {
    echo "<p style='color: orange;'>⚠️ Warning: Original index.php not found. Will create a new one.</p>";
}

// Ensure assets directory exists
if (!file_exists(ROOT_PATH . '/assets')) {
    if (!mkdir(ROOT_PATH . '/assets', 0755, true)) {
        die("<p style='color: red;'>Error: Failed to create assets directory. Please check permissions.</p>");
    }
}

// Ensure assets/css directory exists
if (!file_exists(ROOT_PATH . '/assets/css')) {
    if (!mkdir(ROOT_PATH . '/assets/css', 0755, true)) {
        die("<p style='color: red;'>Error: Failed to create assets/css directory. Please check permissions.</p>");
    }
}

// Ensure assets/js directory exists
if (!file_exists(ROOT_PATH . '/assets/js')) {
    if (!mkdir(ROOT_PATH . '/assets/js', 0755, true)) {
        die("<p style='color: red;'>Error: Failed to create assets/js directory. Please check permissions.</p>");
    }
}

// Ensure assets/images directory exists
if (!file_exists(ROOT_PATH . '/assets/images')) {
    if (!mkdir(ROOT_PATH . '/assets/images', 0755, true)) {
        die("<p style='color: red;'>Error: Failed to create assets/images directory. Please check permissions.</p>");
    }
}

// Copy modern index to main index.php
if (copy(ROOT_PATH . '/modern-index.php', $original_index)) {
    echo "<p style='color: green;'>✅ Successfully activated modern theme index page!</p>";
} else {
    die("<p style='color: red;'>Error: Failed to copy modern theme index. Please check permissions.</p>");
}

// Copy fix-product-image.js to the assets/js directory if it doesn't exist
if (!file_exists(ROOT_PATH . '/assets/js/fix-product-image.js') && file_exists(ROOT_PATH . '/assets/js/fix-product-image.js')) {
    if (copy(ROOT_PATH . '/assets/js/fix-product-image.js', ROOT_PATH . '/assets/js/fix-product-image.js')) {
        echo "<p style='color: green;'>✅ Copied product image fix JavaScript.</p>";
    }
}

// Create a simple favicon.ico if it doesn't exist
if (!file_exists(ROOT_PATH . '/assets/images/favicon.ico')) {
    file_put_contents(ROOT_PATH . '/assets/images/favicon.ico', '');
    echo "<p style='color: green;'>✅ Created placeholder favicon.ico</p>";
}

// Create a placeholder.jpg if it doesn't exist
if (!file_exists(ROOT_PATH . '/assets/images/placeholder.jpg')) {
    // Try to create a simple placeholder
    if (function_exists('imagecreate')) {
        $image = imagecreate(600, 600);
        $bg = imagecolorallocate($image, 52, 152, 219); // #3498db
        $text_color = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 5, 250, 290, "No Image", $text_color);
        imagejpeg($image, ROOT_PATH . '/assets/images/placeholder.jpg', 90);
        imagedestroy($image);
        echo "<p style='color: green;'>✅ Created placeholder image using GD library</p>";
    } else {
        // Create an empty file as placeholder
        file_put_contents(ROOT_PATH . '/assets/images/placeholder.jpg', '');
        echo "<p style='color: orange;'>⚠️ Created empty placeholder image (GD library not available)</p>";
    }
}

echo "<h2>Modern Theme Activation Complete! 🎉</h2>";
echo "<p>Your website now has a modern, responsive design with improved UI/UX.</p>";

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><a href='index.php'>Visit your homepage</a> to see the new modern design.</li>";
echo "<li>You can customize the theme by editing <code>assets/css/modern-theme.css</code>.</li>";
echo "<li>If you need to revert back to the original design, rename <code>" . basename($backup_index) . "</code> to <code>index.php</code>.</li>";
echo "</ol>";

echo "<div style='margin-top: 30px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;'>";
echo "<p style='font-weight: bold; margin-bottom: 10px;'>Additional Recommendations:</p>";
echo "<ul>";
echo "<li>Create modern versions of other important pages (all-products.php, product-detail.php, etc.)</li>";
echo "<li>Update your product images with high-quality photos</li>";
echo "<li>Add a logo image to replace the font icon in the header</li>";
echo "</ul>";
echo "</div>";
?> 