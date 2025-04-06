<?php
/**
 * Permission and Configuration Checker
 * 
 * This script helps diagnose issues with file access and PHP configuration
 * that might cause blank pages or errors with the fix_product_images.php script.
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', __DIR__);

echo "<h1>PHP Environment & Permissions Checker</h1>";

// PHP Configuration
echo "<h2>PHP Configuration</h2>";
echo "<ul>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>Output Buffering: " . (ini_get('output_buffering') ? 'Enabled' : 'Disabled') . "</li>";
echo "<li>Memory Limit: " . ini_get('memory_limit') . "</li>";
echo "<li>Max Execution Time: " . ini_get('max_execution_time') . " seconds</li>";
echo "<li>Display Errors: " . (ini_get('display_errors') ? 'Enabled' : 'Disabled') . "</li>";
echo "<li>Error Reporting Level: " . ini_get('error_reporting') . "</li>";
echo "<li>Error Log: " . ini_get('error_log') . "</li>";
echo "</ul>";

// Test file locks
echo "<h2>File Lock Test</h2>";
$test_file = ROOT_PATH . '/file_lock_test.txt';
echo "<p>Testing file locks with: {$test_file}</p>";

// Try to create and write to a test file
if (file_put_contents($test_file, 'Test content: ' . date('Y-m-d H:i:s'))) {
    echo "<p style='color:green'>Successfully created test file</p>";
    
    // Try to read the file
    $content = file_get_contents($test_file);
    if ($content) {
        echo "<p style='color:green'>Successfully read test file: " . htmlspecialchars($content) . "</p>";
    } else {
        echo "<p style='color:red'>Failed to read test file</p>";
    }
    
    // Try to delete the file
    if (unlink($test_file)) {
        echo "<p style='color:green'>Successfully deleted test file</p>";
    } else {
        echo "<p style='color:red'>Failed to delete test file</p>";
    }
} else {
    echo "<p style='color:red'>Failed to create test file - this indicates a permission issue</p>";
}

// Directory Permissions
echo "<h2>Directory Permissions</h2>";
echo "<ul>";
echo "<li>Current Directory: " . getcwd() . "</li>";
echo "<li>Current Directory Writable: " . (is_writable(getcwd()) ? 'Yes' : 'No') . "</li>";

// Check key directories
$directories = [
    '.' => 'Current',
    './api' => 'API',
    './api/uploads' => 'API Uploads',
    './api/uploads/products' => 'Product Images',
    './uploads' => 'Old Uploads',
    './uploads/products' => 'Old Product Images'
];

foreach ($directories as $dir => $name) {
    if (file_exists($dir)) {
        echo "<li>{$name} Directory ({$dir}):<ul>";
        echo "<li>Exists: Yes</li>";
        echo "<li>Readable: " . (is_readable($dir) ? 'Yes' : 'No') . "</li>";
        echo "<li>Writable: " . (is_writable($dir) ? 'Yes' : 'No') . "</li>";
        echo "<li>Permissions: " . substr(sprintf('%o', fileperms($dir)), -4) . "</li>";
        echo "</ul></li>";
    } else {
        echo "<li>{$name} Directory ({$dir}): Does not exist</li>";
    }
}
echo "</ul>";

// Check if the fix_product_images.php file is accessible
echo "<h2>Script File Check</h2>";
$script_paths = [
    './fix_product_images.php' => 'Original Fix Script',
    './fix_product_images_new.php' => 'New Fix Script'
];

foreach ($script_paths as $script_path => $script_name) {
    echo "<h3>{$script_name}</h3>";
    if (file_exists($script_path)) {
        echo "<ul>";
        echo "<li>File exists: Yes</li>";
        echo "<li>File size: " . filesize($script_path) . " bytes</li>";
        echo "<li>Last modified: " . date("Y-m-d H:i:s", filemtime($script_path)) . "</li>";
        echo "<li>Readable: " . (is_readable($script_path) ? 'Yes' : 'No') . "</li>";
        echo "<li>Writable: " . (is_writable($script_path) ? 'Yes' : 'No') . "</li>";
        
        // Try to read a small portion of the file to check if it's locked
        $fh = @fopen($script_path, 'r');
        if ($fh) {
            echo "<li>Could open file for reading: Yes</li>";
            fclose($fh);
        } else {
            echo "<li style='color:red'>Could open file for reading: No (file may be locked)</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>File does not exist</p>";
    }
}

echo "<h2>Recommended Actions</h2>";
echo "<ol>";
echo "<li>Try using the <a href='fix_product_images_new.php'>fix_product_images_new.php</a> script instead</li>";
echo "<li>Restart your web server (Apache/XAMPP) to release any file locks</li>";
echo "<li>Make sure antivirus isn't scanning/locking the file</li>";
echo "<li>Check the PHP error logs for detailed error messages</li>";
echo "</ol>";
?> 