<?php
/**
 * Fix Hardcoded "Product Image" Text in Templates
 * This script searches for hardcoded "Product Image" text in templates and removes it
 */

// Define root path
define('ROOT_PATH', __DIR__);

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Fix Hardcoded 'Product Image' Text</h1>";

// Files to check
$templates = [
    'index.php',
    'all-products.php',
    'product-detail.php',
    'templates/product-card.php',
    'includes/product-card.php',
    'partials/product-card.php'
];

$found_templates = [];
$modified_files = [];

// Search for templates
foreach ($templates as $template) {
    if (file_exists(ROOT_PATH . '/' . $template)) {
        $found_templates[] = $template;
    }
}

echo "<p>Found " . count($found_templates) . " template files to check.</p>";

// Function to fix product image containers
function fix_product_image_containers($content) {
    // Pattern 1: Direct text "Product Image" inside product-img-container div
    $pattern1 = '/<div[^>]*class="[^"]*product-img-container[^"]*"[^>]*>\s*Product Image\s*<img/';
    $replacement1 = '<div class="product-img-container"><img';
    $content = preg_replace($pattern1, $replacement1, $content);
    
    // Pattern 2: Direct text "Product Image" with no image tag
    $pattern2 = '/<div[^>]*class="[^"]*product-img-container[^"]*"[^>]*>\s*Product Image\s*<\/div>/';
    $replacement2 = '<div class="product-img-container"><img src="/Terral2/api/uploads/products/placeholder.jpg" alt="Product" class="product-img"></div>';
    $content = preg_replace($pattern2, $replacement2, $content);
    
    // Pattern 3: Text node before image
    $pattern3 = '/<div[^>]*class="[^"]*product-img-container[^"]*"[^>]*>\s*Product Image\s*</';
    $replacement3 = '<div class="product-img-container"><';
    $content = preg_replace($pattern3, $replacement3, $content);
    
    // Pattern 4: Find any remaining product-img-container divs without img tags and add one
    $pattern4 = '/<div[^>]*class="[^"]*product-img-container[^"]*"[^>]*>(?!.*?<img).*?<\/div>/s';
    $replacement4 = '<div class="product-img-container"><img src="/Terral2/api/uploads/products/placeholder.jpg" alt="Product" class="product-img"></div>';
    $content = preg_replace($pattern4, $replacement4, $content);
    
    return $content;
}

// Process each template file
foreach ($found_templates as $template) {
    $file_path = ROOT_PATH . '/' . $template;
    $content = file_get_contents($file_path);
    
    // Check if the file contains the text "Product Image"
    if (strpos($content, 'Product Image') !== false) {
        echo "<p>Found 'Product Image' text in {$template}</p>";
        
        // Make a backup of the file
        $backup_file = $file_path . '.bak_' . date('YmdHis');
        copy($file_path, $backup_file);
        
        // Fix the content
        $fixed_content = fix_product_image_containers($content);
        
        // Only update if changes were made
        if ($fixed_content !== $content) {
            file_put_contents($file_path, $fixed_content);
            $modified_files[] = $template;
            echo "<p>✅ Fixed {$template} and created backup at " . basename($backup_file) . "</p>";
        } else {
            echo "<p>⚠️ No changes made to {$template} (could not find pattern to replace)</p>";
        }
    } else {
        echo "<p>✓ No 'Product Image' text found in {$template}</p>";
    }
}

// If no templates were found or modified, check for component libraries
if (empty($modified_files)) {
    echo "<h2>Checking for component libraries...</h2>";
    
    // Check for React/Vue/Angular components
    $component_paths = [
        'components',
        'src/components',
        'app/components',
        'resources/js/components'
    ];
    
    foreach ($component_paths as $comp_path) {
        $full_path = ROOT_PATH . '/' . $comp_path;
        if (is_dir($full_path)) {
            echo "<p>Found components directory at {$comp_path}</p>";
            
            // Find all component files
            $component_files = glob($full_path . '/**/*.{js,jsx,vue,php,html}', GLOB_BRACE);
            
            foreach ($component_files as $comp_file) {
                $rel_path = str_replace(ROOT_PATH . '/', '', $comp_file);
                $content = file_get_contents($comp_file);
                
                if (strpos($content, 'Product Image') !== false) {
                    echo "<p>Found 'Product Image' text in {$rel_path}</p>";
                    
                    // Make a backup
                    $backup_file = $comp_file . '.bak_' . date('YmdHis');
                    copy($comp_file, $backup_file);
                    
                    // Replace hardcoded text in components
                    $fixed_content = fix_product_image_containers($content);
                    
                    // For JS/JSX/Vue components, use different patterns
                    if (preg_match('/\.(js|jsx|vue)$/', $comp_file)) {
                        // Replace in JSX/Vue templates
                        $fixed_content = str_replace(
                            ['className="product-img-container">Product Image<', 'class="product-img-container">Product Image<'],
                            ['className="product-img-container"><', 'class="product-img-container"><'],
                            $fixed_content
                        );
                    }
                    
                    if ($fixed_content !== $content) {
                        file_put_contents($comp_file, $fixed_content);
                        $modified_files[] = $rel_path;
                        echo "<p>✅ Fixed {$rel_path} and created backup</p>";
                    } else {
                        echo "<p>⚠️ No changes made to {$rel_path} (could not find pattern to replace)</p>";
                    }
                }
            }
        }
    }
}

// Create a CSS fix as a fallback
$css_fix = <<<CSS
/* Hide "Product Image" text in product containers */
.product-img-container {
    position: relative;
    height: 220px;
    overflow: hidden;
    background-color: #f5f5f5;
}

/* Make sure images display properly */
.product-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

/* This is the most important part - hide all direct text nodes */
.product-img-container::before {
    content: '';
    display: block;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: #3498db;
    z-index: 0;
}

/* Make sure images display on top of the background */
.product-img-container img {
    position: relative;
    z-index: 1;
}

/* Hide "Product Image" text by making it transparent */
.product-img-container {
    color: transparent;
}
CSS;

$css_path = ROOT_PATH . '/assets/css/fix-product-image.css';
if (!file_exists(dirname($css_path))) {
    mkdir(dirname($css_path), 0755, true);
}
file_put_contents($css_path, $css_fix);
echo "<p>✅ Created CSS fix at assets/css/fix-product-image.css</p>";

// Create a script to apply the CSS fix to all pages
$js_fix = <<<JS
// Add the CSS fix to the page
document.addEventListener('DOMContentLoaded', function() {
    // Add the CSS file if it's not already added
    if (!document.querySelector('link[href*="fix-product-image.css"]')) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '/Terral2/assets/css/fix-product-image.css';
        document.head.appendChild(link);
    }
    
    // Direct fix: Remove "Product Image" text from product-img-container divs
    document.querySelectorAll('.product-img-container').forEach(function(container) {
        // Check for direct text nodes that contain "Product Image"
        Array.from(container.childNodes).forEach(function(node) {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim() === 'Product Image') {
                node.textContent = '';
            }
        });
        
        // Make sure there's an image tag
        if (!container.querySelector('img')) {
            // Create a placeholder image
            var img = document.createElement('img');
            img.src = '/Terral2/api/uploads/products/placeholder.jpg';
            img.alt = 'Product';
            img.className = 'product-img';
            container.appendChild(img);
        }
    });
});
JS;

$js_path = ROOT_PATH . '/assets/js/fix-product-image.js';
if (!file_exists(dirname($js_path))) {
    mkdir(dirname($js_path), 0755, true);
}
file_put_contents($js_path, $js_fix);
echo "<p>✅ Created JavaScript fix at assets/js/fix-product-image.js</p>";

// Create an include file to add our fixes to templates
$include_content = <<<PHP
<!-- Product Image Display Fix -->
<link rel="stylesheet" href="/Terral2/assets/css/fix-product-image.css">
<script src="/Terral2/assets/js/fix-product-image.js"></script>
PHP;

$include_path = ROOT_PATH . '/includes/fix-product-image.php';
if (!file_exists(dirname($include_path))) {
    mkdir(dirname($include_path), 0755, true);
}
file_put_contents($include_path, $include_content);
echo "<p>✅ Created include file at includes/fix-product-image.php</p>";

// Add the include to the main template files (if they exist and weren't already modified)
$main_templates = ['index.php', 'all-products.php', 'product-detail.php'];
foreach ($main_templates as $template) {
    if (file_exists(ROOT_PATH . '/' . $template) && !in_array($template, $modified_files)) {
        $content = file_get_contents(ROOT_PATH . '/' . $template);
        
        // Check if the include is already there
        if (strpos($content, 'fix-product-image.php') === false) {
            // Find the closing </body> tag and add our include before it
            $content = str_replace('</body>', "<?php include 'includes/fix-product-image.php'; ?>\n</body>", $content);
            file_put_contents(ROOT_PATH . '/' . $template, $content);
            echo "<p>✅ Added include to {$template}</p>";
        }
    }
}

// Direct fix for all HTML files (in case the templates are statically rendered)
echo "<h2>Direct HTML Fix</h2>";
echo "<p>Checking for hardcoded HTML files with 'Product Image' text...</p>";

$html_files = glob(ROOT_PATH . '/*.{html,htm}', GLOB_BRACE);
foreach ($html_files as $html_file) {
    $rel_path = str_replace(ROOT_PATH . '/', '', $html_file);
    $content = file_get_contents($html_file);
    
    if (strpos($content, 'Product Image') !== false) {
        echo "<p>Found 'Product Image' text in {$rel_path}</p>";
        
        // Make a backup
        $backup_file = $html_file . '.bak_' . date('YmdHis');
        copy($html_file, $backup_file);
        
        // Fix the content
        $fixed_content = fix_product_image_containers($content);
        
        // Add our CSS and JS fixes
        $head_fix = '<link rel="stylesheet" href="/Terral2/assets/css/fix-product-image.css">';
        $body_fix = '<script src="/Terral2/assets/js/fix-product-image.js"></script>';
        
        // Add to head if not already there
        if (strpos($fixed_content, 'fix-product-image.css') === false) {
            $fixed_content = str_replace('</head>', "{$head_fix}\n</head>", $fixed_content);
        }
        
        // Add to body if not already there
        if (strpos($fixed_content, 'fix-product-image.js') === false) {
            $fixed_content = str_replace('</body>', "{$body_fix}\n</body>", $fixed_content);
        }
        
        // Only update if changes were made
        if ($fixed_content !== $content) {
            file_put_contents($html_file, $fixed_content);
            echo "<p>✅ Fixed {$rel_path} and created backup</p>";
        } else {
            echo "<p>⚠️ No changes made to {$rel_path} (could not find pattern to replace)</p>";
        }
    }
}

// Summary
echo "<h2>Fix Complete!</h2>";

if (count($modified_files) > 0) {
    echo "<p>Modified " . count($modified_files) . " files:</p>";
    echo "<ul>";
    foreach ($modified_files as $file) {
        echo "<li>{$file}</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No template files needed modification for 'Product Image' text.</p>";
}

echo "<p>Created CSS and JavaScript fixes as a fallback solution.</p>";

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Refresh your website to see if the 'Product Image' text has been removed</li>";
echo "<li>If you still see 'Product Image' text, try clearing your browser cache (Ctrl+F5 or Cmd+Shift+R)</li>";
echo "<li>If issues persist, check the browser console for any errors</li>";
echo "</ol>";
?> 