<?php
/**
 * Product Images Fix Script
 * 
 * This script diagnoses and fixes issues with product images not appearing on the frontend
 * by ensuring the uploads directory exists, creating a placeholder image, and verifying
 * that product images are correctly stored.
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/helpers/ProductHelper.php';

// Define uploads directory
$uploadsDir = ROOT_PATH . '/api/uploads/products/';

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize ProductHelper
$productHelper = new ProductHelper($conn);

// Output styling
echo '<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1200px; margin: 0 auto; }
    h1, h2 { color: #3d5afe; }
    .card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
    .product-item { border: 1px solid #eee; border-radius: 8px; padding: 10px; text-align: center; }
    .product-image { width: 100%; height: 150px; object-fit: contain; border-radius: 4px; }
    .actions { margin-top: 20px; }
    .btn { display: inline-block; background: #3d5afe; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; margin-right: 10px; }
    .btn-outline { background: transparent; border: 1px solid #3d5afe; color: #3d5afe; }
</style>';

echo '<h1>Product Images Fix Tool</h1>';

// Step 1: Check if uploads directory exists
echo '<div class="card">';
echo '<h2>Step 1: Checking Upload Directory</h2>';

if (!file_exists($uploadsDir)) {
    echo '<p class="warning">Upload directory does not exist. Creating it now...</p>';
    if (mkdir($uploadsDir, 0755, true)) {
        echo '<p class="success">Upload directory created successfully at: ' . $uploadsDir . '</p>';
    } else {
        echo '<p class="error">Failed to create upload directory. Please check permissions.</p>';
        echo '<p>You may need to manually create the directory at: ' . $uploadsDir . '</p>';
    }
} else {
    echo '<p class="success">Upload directory exists at: ' . $uploadsDir . '</p>';
    
    // Check if directory is writable
    if (is_writable($uploadsDir)) {
        echo '<p class="success">Upload directory is writable.</p>';
    } else {
        echo '<p class="error">Upload directory is not writable. Attempting to fix permissions...</p>';
        if (chmod($uploadsDir, 0755)) {
            echo '<p class="success">Permissions fixed successfully.</p>';
        } else {
            echo '<p class="error">Failed to fix permissions. Please manually set proper permissions.</p>';
        }
    }
}
echo '</div>';

// Step 2: Create placeholder image
echo '<div class="card">';
echo '<h2>Step 2: Checking Placeholder Image</h2>';

$placeholderPath = $uploadsDir . 'placeholder.jpg';
if (!file_exists($placeholderPath)) {
    echo '<p class="warning">Placeholder image does not exist. Creating it now...</p>';
    
    // Use ProductHelper to create placeholder
    $productHelper->ensurePlaceholderExists();
    
    if (file_exists($placeholderPath)) {
        echo '<p class="success">Placeholder image created successfully.</p>';
    } else {
        echo '<p class="error">Failed to create placeholder image. Creating a simple one now...</p>';
        
        // Create a simple placeholder image
        $image = imagecreate(500, 500);
        $background = imagecolorallocate($image, 240, 240, 240);
        $textColor = imagecolorallocate($image, 100, 100, 100);
        
        // Fill background
        imagefill($image, 0, 0, $background);
        
        // Add text
        $text = "Product Image";
        $fontSize = 5;
        
        // Center text
        $x = 180;
        $y = 240;
        
        // Draw text
        imagestring($image, $fontSize, $x, $y, $text, $textColor);
        
        // Save image
        imagejpeg($image, $placeholderPath);
        imagedestroy($image);
        
        if (file_exists($placeholderPath)) {
            echo '<p class="success">Simple placeholder image created successfully.</p>';
        } else {
            echo '<p class="error">Failed to create simple placeholder image. Please check PHP GD library.</p>';
        }
    }
} else {
    echo '<p class="success">Placeholder image exists.</p>';
}
echo '</div>';

// Step 3: Check products without images
echo '<div class="card">';
echo '<h2>Step 3: Checking Products Without Images</h2>';

try {
    $query = "SELECT id, name, image FROM products";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $productsWithoutImages = [];
    $totalProducts = 0;
    $missingImages = 0;
    
    while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $totalProducts++;
        
        // Check if product has an image
        if (empty($product['image'])) {
            $productsWithoutImages[] = $product;
            $missingImages++;
            continue;
        }
        
        // Check if the image file exists
        $imagePath = $uploadsDir . $product['image'];
        if (!file_exists($imagePath)) {
            $productsWithoutImages[] = $product;
            $missingImages++;
        }
    }
    
    if ($missingImages > 0) {
        echo '<p class="warning">Found ' . $missingImages . ' out of ' . $totalProducts . ' products with missing images.</p>';
        
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="fix_missing_images" value="1">';
        echo '<button type="submit" class="btn">Fix Missing Images</button>';
        echo '</form>';
        
        echo '<ul>';
        foreach ($productsWithoutImages as $product) {
            echo '<li>Product #' . $product['id'] . ': ' . htmlspecialchars($product['name']) . ' - Image: ' . 
                 (empty($product['image']) ? 'Not set' : htmlspecialchars($product['image']) . ' (File not found)') . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="success">All ' . $totalProducts . ' products have valid images.</p>';
    }
} catch (PDOException $e) {
    echo '<p class="error">Database error: ' . $e->getMessage() . '</p>';
}
echo '</div>';

// Step 4: Fix missing images if requested
if (isset($_POST['fix_missing_images'])) {
    echo '<div class="card">';
    echo '<h2>Step 4: Fixing Missing Images</h2>';
    
    try {
        $query = "SELECT id, name, image FROM products WHERE image IS NULL OR image = ''";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        $updatedCount = 0;
        
        while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Generate a sample image for this product
            $imageName = 'product_' . $product['id'] . '_' . uniqid() . '.jpg';
            $imagePath = $uploadsDir . $imageName;
            
            // Create a sample product image with name text
            $image = imagecreate(500, 500);
            $background = imagecolorallocate($image, 230, 240, 255); // Light blue background
            $textColor = imagecolorallocate($image, 50, 50, 150);    // Dark blue text
            
            // Fill background
            imagefill($image, 0, 0, $background);
            
            // Add border
            $borderColor = imagecolorallocate($image, 200, 210, 240);
            imagerectangle($image, 10, 10, 489, 489, $borderColor);
            
            // Add product name
            $text = $product['name'];
            $fontSize = 5;
            
            // Wrap text if too long
            if (strlen($text) > 25) {
                $text = wordwrap($text, 25, "\n", true);
            }
            
            // Center and add text
            $lines = explode("\n", $text);
            $lineHeight = 20;
            $y = 240 - (count($lines) * $lineHeight / 2);
            
            foreach ($lines as $line) {
                $x = (500 - strlen($line) * 8) / 2;
                imagestring($image, $fontSize, $x, $y, $line, $textColor);
                $y += $lineHeight;
            }
            
            // Save image
            imagejpeg($image, $imagePath);
            imagedestroy($image);
            
            // Update database with new image filename
            $updateQuery = "UPDATE products SET image = :image WHERE id = :id";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bindParam(':image', $imageName);
            $updateStmt->bindParam(':id', $product['id']);
            $updateStmt->execute();
            
            $updatedCount++;
            
            echo '<p class="success">Created image for Product #' . $product['id'] . ': ' . htmlspecialchars($product['name']) . ' - ' . $imageName . '</p>';
        }
        
        echo '<p class="success">Updated ' . $updatedCount . ' products with new images.</p>';
        
    } catch (PDOException $e) {
        echo '<p class="error">Database error: ' . $e->getMessage() . '</p>';
    }
    echo '</div>';
}

// Step 5: Display products and their images
echo '<div class="card">';
echo '<h2>Step 5: Product Image Gallery</h2>';

try {
    $query = "SELECT id, name, image FROM products ORDER BY id DESC LIMIT 20";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo '<p>Showing the latest 20 products and their images:</p>';
        echo '<div class="product-grid">';
        
        while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $imageUrl = $productHelper->getProductImageUrl($product['image']);
            
            echo '<div class="product-item">';
            echo '<img src="' . $imageUrl . '" alt="' . htmlspecialchars($product['name']) . '" class="product-image">';
            echo '<h3>' . htmlspecialchars($product['name']) . '</h3>';
            echo '<p>ID: ' . $product['id'] . '</p>';
            echo '<p>Image: ' . (empty($product['image']) ? 'Not set' : htmlspecialchars($product['image'])) . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
    } else {
        echo '<p>No products found in the database.</p>';
    }
} catch (PDOException $e) {
    echo '<p class="error">Database error: ' . $e->getMessage() . '</p>';
}
echo '</div>';

// Step 6: Final instructions
echo '<div class="card">';
echo '<h2>Step 6: Next Steps</h2>';
echo '<p>Here are some recommendations to ensure product images work correctly:</p>';
echo '<ol>';
echo '<li>Make sure the upload directory has the correct permissions (755 for directories, 644 for files).</li>';
echo '<li>When adding products, ensure images are properly uploaded before saving the product.</li>';
echo '<li>Use the provided image upload controls in the product form rather than manually entering filenames.</li>';
echo '<li>Check that the web server has write permission to the uploads directory.</li>';
echo '<li>If using a CDN or external storage, ensure paths are correctly configured.</li>';
echo '</ol>';

echo '<div class="actions">';
echo '<a href="/Terral2/admin/add-product.php" class="btn">Add New Product</a>';
echo '<a href="/Terral2/admin/products.php" class="btn btn-outline">View All Products</a>';
echo '<a href="/Terral2/index.php" class="btn btn-outline">Go to Homepage</a>';
echo '</div>';
echo '</div>';
?>