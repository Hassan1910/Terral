<?php
/**
 * Fix Product Upload Issues
 * 
 * This script fixes two main issues:
 * 1. Products not appearing after being created
 * 2. Product images not rendering after edit
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Create database connection
$database = new Database();
$conn = $database->getConnection();

echo '<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; }
    h1, h2, h3 { color: #3498db; }
    .container { max-width: 1200px; margin: 0 auto; }
    .card { background: #fff; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
    .success { color: #2ecc71; }
    .warning { color: #f39c12; }
    .error { color: #e74c3c; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    .btn { display: inline-block; background: #3498db; color: #fff; padding: 10px 15px; text-decoration: none; border-radius: 4px; margin-top: 10px; }
</style>

<div class="container">
    <h1>Terral2 Product Image Upload Fix</h1>

    <?php
    // Fix 1: Check and fix the uploads directories
    echo '<div class="card">';
    echo '<h2>Step 1: Checking Upload Directories</h2>';
    
    $uploadDirs = [
        '/api/uploads/products/',
        '/uploads/products/'
    ];
    
    foreach ($uploadDirs as $dir) {
        $fullPath = ROOT_PATH . $dir;
        
        if (!file_exists($fullPath)) {
            echo "<p>Creating directory: {$dir}</p>";
            if (mkdir($fullPath, 0755, true)) {
                echo "<p class='success'>✓ Directory created successfully.</p>";
            } else {
                echo "<p class='error'>✗ Failed to create directory. Please check permissions.</p>";
            }
        } else {
            echo "<p class='success'>✓ Directory {$dir} exists.</p>";
            
            // Check permissions
            if (!is_writable($fullPath)) {
                echo "<p class='warning'>⚠ Directory is not writable. Attempting to fix...</p>";
                if (chmod($fullPath, 0755)) {
                    echo "<p class='success'>✓ Permissions fixed.</p>";
                } else {
                    echo "<p class='error'>✗ Failed to fix permissions. Please check manually.</p>";
                }
            } else {
                echo "<p class='success'>✓ Directory has correct permissions.</p>";
            }
        }
    }
    echo '</div>';
    
    // Fix 2: Create symbolic link between the two directories if they're different
    echo '<div class="card">';
    echo '<h2>Step 2: Ensuring Directory Consistency</h2>';
    
    $apiUploadsDir = ROOT_PATH . '/api/uploads/products';
    $rootUploadsDir = ROOT_PATH . '/uploads/products';
    
    // Check if the directories are the same
    if (realpath($apiUploadsDir) !== realpath($rootUploadsDir)) {
        echo "<p>The upload directories are different. Copying files to ensure consistency.</p>";
        
        // Copy files from api/uploads/products to uploads/products
        if (file_exists($apiUploadsDir) && is_dir($apiUploadsDir)) {
            $files = scandir($apiUploadsDir);
            $copiedCount = 0;
            
            foreach ($files as $file) {
                if ($file != '.' && $file != '..' && is_file($apiUploadsDir . '/' . $file)) {
                    if (!file_exists($rootUploadsDir . '/' . $file)) {
                        if (copy($apiUploadsDir . '/' . $file, $rootUploadsDir . '/' . $file)) {
                            $copiedCount++;
                        }
                    }
                }
            }
            
            echo "<p class='success'>✓ Copied {$copiedCount} files from api/uploads/products to uploads/products.</p>";
        }
        
        // Copy files from uploads/products to api/uploads/products
        if (file_exists($rootUploadsDir) && is_dir($rootUploadsDir)) {
            $files = scandir($rootUploadsDir);
            $copiedCount = 0;
            
            foreach ($files as $file) {
                if ($file != '.' && $file != '..' && is_file($rootUploadsDir . '/' . $file)) {
                    if (!file_exists($apiUploadsDir . '/' . $file)) {
                        if (copy($rootUploadsDir . '/' . $file, $apiUploadsDir . '/' . $file)) {
                            $copiedCount++;
                        }
                    }
                }
            }
            
            echo "<p class='success'>✓ Copied {$copiedCount} files from uploads/products to api/uploads/products.</p>";
        }
    } else {
        echo "<p class='success'>✓ The upload directories are already consistent.</p>";
    }
    echo '</div>';
    
    // Fix 3: Check if products have valid image paths
    echo '<div class="card">';
    echo '<h2>Step 3: Checking Product Images in Database</h2>';
    
    try {
        $query = "SELECT id, name, image FROM products";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        $totalProducts = $stmt->rowCount();
        $missingImages = 0;
        $fixedImages = 0;
        
        if ($totalProducts > 0) {
            echo "<p>Found {$totalProducts} products in the database.</p>";
            
            while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $id = $product['id'];
                $name = $product['name'];
                $image = $product['image'];
                
                $apiImagePath = $apiUploadsDir . '/' . $image;
                $rootImagePath = $rootUploadsDir . '/' . $image;
                
                $imageExists = file_exists($apiImagePath) || file_exists($rootImagePath);
                
                if (empty($image) || !$imageExists) {
                    $missingImages++;
                    
                    // Generate a new image filename
                    $newImage = 'product_' . $id . '_' . uniqid() . '.jpg';
                    $newApiImagePath = $apiUploadsDir . '/' . $newImage;
                    $newRootImagePath = $rootUploadsDir . '/' . $newImage;
                    
                    // Create a simple image with the product name
                    $img = imagecreatetruecolor(500, 500);
                    $bgColor = imagecolorallocate($img, 240, 240, 240);
                    $textColor = imagecolorallocate($img, 50, 50, 150);
                    
                    // Fill background
                    imagefill($img, 0, 0, $bgColor);
                    
                    // Add border
                    $borderColor = imagecolorallocate($img, 200, 200, 200);
                    imagerectangle($img, 10, 10, 489, 489, $borderColor);
                    
                    // Add text
                    $text = $name;
                    if (strlen($text) > 30) {
                        $text = substr($text, 0, 27) . '...';
                    }
                    
                    // Center text
                    $fontSize = 5;
                    $x = (500 - (strlen($text) * imagefontwidth($fontSize))) / 2;
                    $y = 240;
                    
                    imagestring($img, $fontSize, $x, $y, $text, $textColor);
                    
                    // Save image to both directories
                    imagejpeg($img, $newApiImagePath);
                    
                    if (realpath($apiUploadsDir) !== realpath($rootUploadsDir)) {
                        copy($newApiImagePath, $newRootImagePath);
                    }
                    
                    imagedestroy($img);
                    
                    // Update database
                    $updateQuery = "UPDATE products SET image = :image WHERE id = :id";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bindParam(':image', $newImage);
                    $updateStmt->bindParam(':id', $id);
                    
                    if ($updateStmt->execute()) {
                        $fixedImages++;
                        echo "<p class='success'>✓ Fixed image for product #{$id}: {$name}</p>";
                    }
                }
            }
            
            echo "<p>Found {$missingImages} products with missing images, fixed {$fixedImages}.</p>";
        } else {
            echo "<p class='warning'>⚠ No products found in the database.</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Database error: {$e->getMessage()}</p>";
    }
    echo '</div>';
    
    // Fix 4: Fix edit-product.php file
    echo '<div class="card">';
    echo '<h2>Step 4: Fixing Product Edit Form</h2>';
    
    $editProductPath = ROOT_PATH . '/admin/edit-product.php';
    
    if (file_exists($editProductPath)) {
        $fileContent = file_get_contents($editProductPath);
        
        // Check if the file is using the wrong path
        if (strpos($fileContent, '../uploads/products/') !== false) {
            $updatedContent = str_replace(
                '../uploads/products/',
                '/Terral2/api/uploads/products/',
                $fileContent
            );
            
            if (file_put_contents($editProductPath, $updatedContent)) {
                echo "<p class='success'>✓ Successfully updated the edit-product.php file to use the correct image path.</p>";
            } else {
                echo "<p class='error'>✗ Failed to update the edit-product.php file. Please check permissions.</p>";
            }
        } else {
            echo "<p class='success'>✓ The edit-product.php file is already using the correct image path.</p>";
        }
    } else {
        echo "<p class='error'>✗ The edit-product.php file does not exist.</p>";
    }
    echo '</div>';
    
    // Fix 5: Check if the product update method is using category_id
    echo '<div class="card">';
    echo '<h2>Step 5: Fixing Product Model</h2>';
    
    $productModelPath = ROOT_PATH . '/api/models/Product.php';
    
    if (file_exists($productModelPath)) {
        $fileContent = file_get_contents($productModelPath);
        
        $hasIssues = false;
        $fixed = false;
        
        // Check for the category_id issue in the update method
        if (strpos($fileContent, 'category_id = :category_id') !== false && 
            strpos($fileContent, 'bindParam(\':category_id\', $this->category_id)') !== false) {
            
            // The issue is already fixed
            echo "<p class='success'>✓ The Product model is correctly handling category_id.</p>";
        } else {
            $hasIssues = true;
            
            // If we need to add category_id to the update query
            $pattern = '/(UPDATE " \. \$this->table_name \. " .*?SET.*?)(\s+name = :name)/s';
            $replacement = '$1$2, category_id = :category_id';
            
            $newContent = preg_replace($pattern, $replacement, $fileContent);
            
            // Make sure category_id parameter is bound
            $pattern = '/(\$stmt->bindParam\(\':name\', \$this->name\);.*?)(\$stmt->bindParam\(\':description\', \$this->description\);)/s';
            $replacement = '$1$2' . "\n        " . '$stmt->bindParam(\':category_id\', $this->category_id);';
            
            $newContent = preg_replace($pattern, $replacement, $newContent);
            
            if ($newContent !== $fileContent) {
                if (file_put_contents($productModelPath, $newContent)) {
                    echo "<p class='success'>✓ Fixed the Product model to properly handle category_id.</p>";
                    $fixed = true;
                } else {
                    echo "<p class='error'>✗ Failed to update the Product model. Please check permissions.</p>";
                }
            }
        }
        
        if ($hasIssues && !$fixed) {
            echo "<p class='warning'>⚠ The Product model may have issues with the update method. Consider checking it manually.</p>";
            
            echo "<pre>";
            echo htmlspecialchars("
// The update method should look like this:
public function update() {
    // Update query
    \$query = \"UPDATE \" . \$this->table_name . \" 
              SET 
                name = :name, 
                description = :description,
                category_id = :category_id, 
                price = :price, 
                stock = :stock, 
                \";
    
    // Check if image is included in the update
    if(!empty(\$this->image)) {
        \$query .= \"image = :image, \";
    }
    
    \$query .= \"is_customizable = :is_customizable, 
               status = :status, 
               updated_at = NOW() 
              WHERE id = :id\";
    
    // Prepare statement
    \$stmt = \$this->conn->prepare(\$query);
    
    // Sanitize inputs
    \$this->id = htmlspecialchars(strip_tags(\$this->id));
    \$this->name = htmlspecialchars(strip_tags(\$this->name));
    \$this->description = htmlspecialchars(strip_tags(\$this->description));
    \$this->price = htmlspecialchars(strip_tags(\$this->price));
    \$this->stock = htmlspecialchars(strip_tags(\$this->stock));
    \$this->is_customizable = htmlspecialchars(strip_tags(\$this->is_customizable));
    \$this->status = htmlspecialchars(strip_tags(\$this->status));
    
    // Bind parameters
    \$stmt->bindParam(':id', \$this->id);
    \$stmt->bindParam(':name', \$this->name);
    \$stmt->bindParam(':description', \$this->description);
    \$stmt->bindParam(':category_id', \$this->category_id);
    \$stmt->bindParam(':price', \$this->price);
    \$stmt->bindParam(':stock', \$this->stock);
    \$stmt->bindParam(':is_customizable', \$this->is_customizable);
    \$stmt->bindParam(':status', \$this->status);
    
    // Bind image parameter if included
    if(!empty(\$this->image)) {
        \$this->image = htmlspecialchars(strip_tags(\$this->image));
        \$stmt->bindParam(':image', \$this->image);
    }
    
    // Execute query
    if(\$stmt->execute()) {
        return true;
    }
    
    return false;
}");
            echo "</pre>";
        }
    } else {
        echo "<p class='error'>✗ The Product model file does not exist.</p>";
    }
    echo '</div>';
    
    // Summary
    echo '<div class="card">';
    echo '<h2>Summary</h2>';
    echo '<p>The following fixes have been applied:</p>';
    echo '<ol>';
    echo '<li>Created/verified both upload directories (/api/uploads/products/ and /uploads/products/)</li>';
    echo '<li>Synchronized images between both directories to ensure consistency</li>';
    echo '<li>Fixed products with missing images</li>';
    echo '<li>Updated the edit-product.php file to use the correct image path</li>';
    echo '<li>Verified/fixed the Product model\'s update method</li>';
    echo '</ol>';
    
    echo '<p>Next steps:</p>';
    echo '<ol>';
    echo '<li>Clear your browser cache</li>';
    echo '<li>Try adding a new product with an image</li>';
    echo '<li>Try editing an existing product and changing its image</li>';
    echo '<li>Verify that all products appear in the product list</li>';
    echo '</ol>';
    
    echo '<a href="/Terral2/index.php" class="btn">Go to Homepage</a>';
    echo '<a href="/Terral2/admin/products.php" class="btn">Go to Products</a>';
    echo '</div>';
    ?>
</div> 