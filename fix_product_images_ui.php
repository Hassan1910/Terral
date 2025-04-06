<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/helpers/ProductHelper.php';

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize ProductHelper
$productHelper = new ProductHelper($conn);

// Check if form was submitted
$ran_fix = isset($_POST['run_fix']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Images Fix Tool</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 800px; margin: 0 auto; }
        h1, h2 { color: #3d5afe; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn { display: inline-block; background: #3d5afe; color: white; padding: 10px 15px; border: none; text-decoration: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #2a3eb1; }
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert-info { background-color: #e8f4fd; border-left: 4px solid #3498db; }
        .alert-success { background-color: #e8f8f5; border-left: 4px solid #2ecc71; }
        img { max-width: 100%; border: 1px solid #eee; }
        .img-preview { max-width: 200px; max-height: 200px; object-fit: contain; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Product Images Fix Tool</h1>
        <p>This tool will fix product images that don't appear correctly on the frontend. It will:</p>
        <ol>
            <li>Ensure the product images upload directory exists and is writable</li>
            <li>Create a placeholder image for products without images</li>
            <li>Generate sample images for products with missing image files</li>
            <li>Update the database to link products to their images</li>
        </ol>
        
        <?php if (!$ran_fix): ?>
            <div class="alert alert-info">
                <p><strong>Note:</strong> Running this tool will create images for products that don't have them. It won't affect products that already have valid images.</p>
            </div>
            
            <form method="post" action="">
                <input type="hidden" name="run_fix" value="1">
                <button type="submit" class="btn">Run Fix Now</button>
            </form>
        <?php endif; ?>
    </div>
    
    <?php if ($ran_fix): ?>
        <div class="card">
            <h2>Fix Results</h2>
            <div class="alert alert-success">
                <p><strong>Fix process started!</strong> Please check the results below.</p>
            </div>
            
            <div style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin-top: 15px;">
                <?php include 'fix_product_images.php'; ?>
            </div>
            
            <p style="margin-top: 20px;">
                <a href="/Terral2/index.php" class="btn">Go to Homepage</a>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" style="margin-left: 10px;">Run Again</a>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h2>Test Image Display</h2>
        <p>Below is a test to see if product images are displaying correctly:</p>
        
        <?php
        // Display a sample product image
        try {
            $query = "SELECT id, name, image FROM products WHERE image IS NOT NULL AND image != '' ORDER BY RAND() LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                $imageUrl = $productHelper->getProductImageUrl($product['image']);
                
                echo '<div style="margin-top: 15px;">';
                echo '<h3>' . htmlspecialchars($product['name']) . '</h3>';
                echo '<p>Product ID: ' . $product['id'] . '</p>';
                echo '<p>Image filename: ' . htmlspecialchars($product['image']) . '</p>';
                echo '<p>Image URL: ' . $imageUrl . '</p>';
                echo '<img src="' . $imageUrl . '" alt="' . htmlspecialchars($product['name']) . '" class="img-preview">';
                echo '</div>';
            } else {
                echo '<p>No products with images found in the database.</p>';
            }
        } catch (PDOException $e) {
            echo '<p>Database error: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
</body>
</html> 