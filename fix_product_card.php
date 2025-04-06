<?php
/**
 * Fix Product Card Display
 * This script checks and fixes product image display issues in the frontend
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

echo "<h1>Product Card Display Fixer</h1>";

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Initialize product helper
    $productHelper = new ProductHelper($conn);
    
    // Define the product styles we need to check
    $styleFiles = [
        'assets/css/style.css',
        'style.css',
        'assets/css/main.css'
    ];

    $foundStyle = false;
    $styleContent = '';
    foreach ($styleFiles as $styleFile) {
        if (file_exists(ROOT_PATH . '/' . $styleFile)) {
            $foundStyle = true;
            $styleContent = file_get_contents(ROOT_PATH . '/' . $styleFile);
            echo "<p>✅ Found CSS style file: {$styleFile}</p>";
            break;
        }
    }

    // Check for custom CSS in index.php or product display files
    if (!$foundStyle) {
        $indexContent = file_exists(ROOT_PATH . '/index.php') ? file_get_contents(ROOT_PATH . '/index.php') : '';
        
        if (strpos($indexContent, '<style') !== false) {
            $foundStyle = true;
            preg_match('/<style>(.*?)<\/style>/s', $indexContent, $matches);
            if (!empty($matches[1])) {
                $styleContent = $matches[1];
                echo "<p>✅ Found inline CSS in index.php</p>";
            }
        }
    }

    // Check if we found CSS rules for product images
    $productImgRules = [];
    if (!empty($styleContent)) {
        // Look for product-img selector
        if (preg_match('/\.product-img(-container)?\s*\{([^}]+)\}/s', $styleContent, $matches)) {
            echo "<p>✅ Found product image CSS rules</p>";
            $productImgRules[] = $matches[0];
        }
        
        // Look for product-card selector
        if (preg_match('/\.product-card\s*\{([^}]+)\}/s', $styleContent, $matches)) {
            echo "<p>✅ Found product card CSS rules</p>";
            $productImgRules[] = $matches[0];
        }
    }

    // Check for inline styles
    $filesWithProductCards = [
        'index.php',
        'all-products.php',
        'product-detail.php'
    ];
    
    foreach ($filesWithProductCards as $file) {
        if (file_exists(ROOT_PATH . '/' . $file)) {
            $content = file_get_contents(ROOT_PATH . '/' . $file);
            
            // Check if there's a product card div with inline style
            if (preg_match('/<div.*?class="product-img-container".*?style="([^"]+)".*?>/s', $content, $matches)) {
                echo "<p>⚠️ Found product image container with inline styles in {$file}: {$matches[1]}</p>";
            }
            
            // Check how product images are being rendered
            if (preg_match_all('/<img.*?class="product-img".*?src="([^"]+)".*?>/s', $content, $matches)) {
                echo "<p>Found " . count($matches[1]) . " product image tags in {$file}</p>";
                
                // Check a few image sources
                for ($i = 0; $i < min(3, count($matches[1])); $i++) {
                    echo "<p>Sample image path: {$matches[1][$i]}</p>";
                }
                
                // Check if some paths use getProductImageUrl and others don't
                if (strpos($content, 'getProductImageUrl') !== false || strpos($content, 'product_image_url') !== false) {
                    echo "<p>⚠️ File uses getProductImageUrl function for some images</p>";
                }
            }
        }
    }
    
    // Create a test CSS fix file
    $fixCssContent = <<<CSS
/**
 * Product Image Display Fix
 * This CSS fixes product image display issues
 */

/* Make sure product-img-container has proper styling */
.product-img-container {
    position: relative;
    height: 220px;
    overflow: hidden;
    background-color: #f5f5f5;
}

/* Ensure product-img displays correctly */
.product-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

/* Add a fallback for missing images */
.product-img::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: #f5f5f5;
    z-index: -1;
}

/* Remove any unwanted text overlays */
.product-img-container::before {
    display: none !important;
}

/* CSS fix for Product Image text */
.product-img-container::after {
    content: '' !important;
}

/* Ensure images are properly loaded */
img.product-img[src$="placeholder.jpg"] {
    object-fit: contain;
    padding: 20px;
}
CSS;

    $fixCssPath = ROOT_PATH . '/assets/css/product-image-fix.css';
    if (!file_exists(dirname($fixCssPath))) {
        mkdir(dirname($fixCssPath), 0755, true);
    }
    file_put_contents($fixCssPath, $fixCssContent);
    echo "<p>✅ Created CSS fix file at: {$fixCssPath}</p>";

    // Create a JavaScript fix
    $fixJsContent = <<<JS
/**
 * Product Image Display Fix
 * This script fixes product image display issues
 */
document.addEventListener('DOMContentLoaded', function() {
    // Fix product images with "Product Image" text
    const productImgContainers = document.querySelectorAll('.product-img-container');
    
    productImgContainers.forEach(container => {
        // Remove any text nodes directly under the container
        container.childNodes.forEach(node => {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim() === 'Product Image') {
                node.textContent = '';
            }
        });
        
        // Check if the image is actually displaying
        const img = container.querySelector('.product-img');
        if (img) {
            img.addEventListener('error', function() {
                // If image fails to load, replace it with placeholder
                this.src = '/Terral2/api/uploads/products/placeholder.jpg';
                container.style.backgroundColor = '#f8f9fa';
            });
            
            // Force image reload
            const currentSrc = img.src;
            img.src = '';
            setTimeout(() => {
                img.src = currentSrc;
            }, 10);
        }
    });
});
JS;

    $fixJsPath = ROOT_PATH . '/assets/js/product-image-fix.js';
    if (!file_exists(dirname($fixJsPath))) {
        mkdir(dirname($fixJsPath), 0755, true);
    }
    file_put_contents($fixJsPath, $fixJsContent);
    echo "<p>✅ Created JavaScript fix file at: {$fixJsPath}</p>";

    // Create a PHP include file to add our fixes
    $includeFixContent = <<<PHP
<?php
/**
 * Product Image Display Fix
 * Include this at the end of your file before </body> to fix product image display
 */

// Add CSS fix
echo '<link rel="stylesheet" href="/Terral2/assets/css/product-image-fix.css">';

// Add JavaScript fix
echo '<script src="/Terral2/assets/js/product-image-fix.js"></script>';
?>
PHP;

    $includeFixPath = ROOT_PATH . '/includes/product-image-fix.php';
    if (!file_exists(dirname($includeFixPath))) {
        mkdir(dirname($includeFixPath), 0755, true);
    }
    file_put_contents($includeFixPath, $includeFixContent);
    echo "<p>✅ Created PHP include file at: {$includeFixPath}</p>";

    // Modify index.php to include our fix
    if (file_exists(ROOT_PATH . '/index.php')) {
        $indexContent = file_get_contents(ROOT_PATH . '/index.php');
        if (strpos($indexContent, 'product-image-fix.php') === false) {
            $indexContent = str_replace('</body>', "    <?php include 'includes/product-image-fix.php'; ?>\n</body>", $indexContent);
            file_put_contents(ROOT_PATH . '/index.php', $indexContent);
            echo "<p>✅ Updated index.php to include the fix</p>";
        }
    }

    // Modify all-products.php to include our fix
    if (file_exists(ROOT_PATH . '/all-products.php')) {
        $allProductsContent = file_get_contents(ROOT_PATH . '/all-products.php');
        if (strpos($allProductsContent, 'product-image-fix.php') === false) {
            $allProductsContent = str_replace('</body>', "    <?php include 'includes/product-image-fix.php'; ?>\n</body>", $allProductsContent);
            file_put_contents(ROOT_PATH . '/all-products.php', $allProductsContent);
            echo "<p>✅ Updated all-products.php to include the fix</p>";
        }
    }

    echo "<h2>Fix Complete!</h2>";
    echo "<p>The product image display issues should now be fixed. Please check your website to verify.</p>";
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Visit your <a href='index.php'>homepage</a> to verify images are displaying correctly</li>";
    echo "<li>Check <a href='all-products.php'>all products page</a> to confirm the fix</li>";
    echo "<li>If issues persist, you may need to clear your browser cache or inspect the HTML to see what's happening</li>";
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