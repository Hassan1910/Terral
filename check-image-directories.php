<?php
/**
 * Check Image Directories
 * 
 * This script checks if the required image upload directories exist and have correct permissions.
 * Run this file directly to see the status of your image upload setup.
 */

// Define required directories
$requiredDirs = [
    'api/uploads/products',
    'api/uploads/categories',
    'api/uploads/customizations',
    'api/uploads/logos',
    'api/uploads/avatars',
    'api/uploads/invoices'
];

// Results array
$results = [];

echo "<h1>Image Directory Check</h1>";
echo "<p>Checking image upload directories...</p>";

// Check each directory
foreach ($requiredDirs as $dir) {
    $results[$dir] = [];
    
    // Check if directory exists
    if (file_exists($dir)) {
        $results[$dir]['exists'] = true;
        
        // Check if it's writable
        if (is_writable($dir)) {
            $results[$dir]['writable'] = true;
        } else {
            $results[$dir]['writable'] = false;
        }
        
        // Get permissions
        $perms = fileperms($dir);
        $results[$dir]['permissions'] = substr(sprintf('%o', $perms), -4);
    } else {
        $results[$dir]['exists'] = false;
    }
}

// Display results
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Directory</th><th>Exists</th><th>Writable</th><th>Permissions</th><th>Status</th></tr>";

foreach ($results as $dir => $status) {
    echo "<tr>";
    echo "<td>{$dir}</td>";
    
    // Exists status
    echo "<td style='text-align: center;'>";
    if ($status['exists']) {
        echo "<span style='color: green;'>✓</span>";
    } else {
        echo "<span style='color: red;'>✗</span>";
    }
    echo "</td>";
    
    // Writable status
    echo "<td style='text-align: center;'>";
    if (isset($status['writable'])) {
        if ($status['writable']) {
            echo "<span style='color: green;'>✓</span>";
        } else {
            echo "<span style='color: red;'>✗</span>";
        }
    } else {
        echo "<span style='color: gray;'>-</span>";
    }
    echo "</td>";
    
    // Permissions
    echo "<td style='text-align: center;'>";
    echo isset($status['permissions']) ? $status['permissions'] : "-";
    echo "</td>";
    
    // Overall status
    echo "<td>";
    if (!$status['exists']) {
        echo "<span style='color: red;'>Directory does not exist</span>";
        echo "<br><button onclick=\"createDir('{$dir}')\">Create directory</button>";
    } elseif (!$status['writable']) {
        echo "<span style='color: red;'>Directory not writable</span>";
        echo "<br><button onclick=\"fixPerms('{$dir}')\">Fix permissions</button>";
    } else {
        echo "<span style='color: green;'>OK</span>";
    }
    echo "</td>";
    
    echo "</tr>";
}

echo "</table>";

// Functions to fix issues
echo "<script>
function createDir(dir) {
    if (confirm('Create directory: ' + dir + '?')) {
        window.location.href = '?action=create&dir=' + encodeURIComponent(dir);
    }
}

function fixPerms(dir) {
    if (confirm('Fix permissions for: ' + dir + '?')) {
        window.location.href = '?action=permissions&dir=' + encodeURIComponent(dir);
    }
}
</script>";

// Handle actions
if (isset($_GET['action']) && isset($_GET['dir'])) {
    $dir = $_GET['dir'];
    
    // Security check - make sure we're only processing valid directories
    if (!in_array($dir, $requiredDirs)) {
        die("Invalid directory specified");
    }
    
    switch ($_GET['action']) {
        case 'create':
            // Create directory
            if (!file_exists($dir)) {
                if (mkdir($dir, 0777, true)) {
                    echo "<p style='color: green;'>Directory created successfully: {$dir}</p>";
                    echo "<p><a href='?'>Refresh</a></p>";
                } else {
                    echo "<p style='color: red;'>Failed to create directory: {$dir}</p>";
                    echo "<p>Possible reasons:</p>";
                    echo "<ul>";
                    echo "<li>PHP does not have permission to create directories</li>";
                    echo "<li>Parent directory does not exist</li>";
                    echo "<li>Server configuration prevents directory creation</li>";
                    echo "</ul>";
                }
            }
            break;
            
        case 'permissions':
            // Fix permissions
            if (file_exists($dir)) {
                if (chmod($dir, 0777)) {
                    echo "<p style='color: green;'>Permissions updated successfully for: {$dir}</p>";
                    echo "<p><a href='?'>Refresh</a></p>";
                } else {
                    echo "<p style='color: red;'>Failed to update permissions for: {$dir}</p>";
                    echo "<p>You may need to set permissions manually via FTP or server control panel.</p>";
                }
            }
            break;
    }
}

// Test image creation
echo "<h2>Test Image Creation</h2>";
echo "<p>Testing if PHP can create images in these directories...</p>";

// Check if GD is installed
if (!extension_loaded('gd')) {
    echo "<p style='color: red;'>Warning: PHP GD extension is not available. Image processing may not work correctly.</p>";
}

// Try to create a test image in each directory
foreach ($requiredDirs as $dir) {
    if (file_exists($dir) && is_writable($dir)) {
        $testFile = $dir . '/test_' . time() . '.png';
        
        // Try to create a simple 1x1 PNG
        $img = @imagecreatetruecolor(1, 1);
        if ($img) {
            $result = @imagepng($img, $testFile);
            imagedestroy($img);
            
            if ($result) {
                echo "<p style='color: green;'>Successfully created test image in {$dir}</p>";
                // Clean up the test file
                @unlink($testFile);
            } else {
                echo "<p style='color: red;'>Failed to create test image in {$dir}</p>";
            }
        } else {
            echo "<p style='color: red;'>Failed to create image: GD library may not be working properly</p>";
        }
    }
}

echo "<h2>Next Steps</h2>";
echo "<ul>";
echo "<li>Make sure all directories show as 'OK'</li>";
echo "<li>If any directories are missing, click the 'Create directory' button</li>";
echo "<li>If any directories are not writable, click the 'Fix permissions' button</li>";
echo "<li>Test uploading images through the admin panel</li>";
echo "</ul>";
?> 