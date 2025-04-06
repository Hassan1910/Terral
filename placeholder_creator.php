<?php
// Create directory if it doesn't exist
function createDirIfNotExists($dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Create a simple placeholder HTML file
function createHtmlPlaceholder($filename, $width = 300, $height = 300, $bg_color = '#cccccc', $text = "Placeholder") {
    $html = '<!DOCTYPE html>
<html>
<head>
    <title>Placeholder</title>
    <style>
        body { margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .placeholder { width: ' . $width . 'px; height: ' . $height . 'px; background-color: ' . $bg_color . '; display: flex; justify-content: center; align-items: center; border: 1px solid #999; font-family: Arial, sans-serif; color: #333; }
    </style>
</head>
<body>
    <div class="placeholder">' . $text . '</div>
</body>
</html>';
    
    file_put_contents($filename, $html);
    echo "Created placeholder: $filename\n";
}

// Define directories for sample products
$products_dir = __DIR__ . '/api/uploads/products';
$categories_dir = __DIR__ . '/api/uploads/categories';

// Create directories if they don't exist
createDirIfNotExists($products_dir);
createDirIfNotExists($categories_dir);

// Create sample product images using a simple approach (copy a placeholder image)
$placeholder_content = file_get_contents('https://via.placeholder.com/400x400.jpg?text=Product+Image');
if ($placeholder_content) {
    for ($i = 1; $i <= 5; $i++) {
        file_put_contents($products_dir . '/product' . $i . '.jpg', $placeholder_content);
        echo "Created product placeholder: " . $products_dir . '/product' . $i . '.jpg' . "\n";
    }
    
    // Create placeholder images in assets/images
    createDirIfNotExists(__DIR__ . '/assets/images');
    file_put_contents(__DIR__ . '/assets/images/placeholder.jpg', $placeholder_content);
    echo "Created main placeholder: " . __DIR__ . '/assets/images/placeholder.jpg' . "\n";
    
    // Create category placeholders
    $category_placeholder = file_get_contents('https://via.placeholder.com/600x300.jpg?text=Category+Image');
    if ($category_placeholder) {
        for ($i = 1; $i <= 3; $i++) {
            file_put_contents($categories_dir . '/category' . $i . '.jpg', $category_placeholder);
            echo "Created category placeholder: " . $categories_dir . '/category' . $i . '.jpg' . "\n";
        }
        
        file_put_contents(__DIR__ . '/assets/images/category-placeholder.jpg', $category_placeholder);
        echo "Created category placeholder: " . __DIR__ . '/assets/images/category-placeholder.jpg' . "\n";
    }
    
    // Create hero image
    $hero_placeholder = file_get_contents('https://via.placeholder.com/1200x600.jpg?text=Terral+Hero+Image');
    if ($hero_placeholder) {
        file_put_contents(__DIR__ . '/assets/images/hero-bg.jpg', $hero_placeholder);
        echo "Created hero placeholder: " . __DIR__ . '/assets/images/hero-bg.jpg' . "\n";
    }
} else {
    echo "Failed to fetch placeholder images. Using HTML placeholders instead.\n";
    
    // Create HTML placeholders if image fetching fails
    for ($i = 1; $i <= 5; $i++) {
        createHtmlPlaceholder($products_dir . '/product' . $i . '.html', 400, 400, '#e6e6e6', "Product " . $i);
    }
    
    for ($i = 1; $i <= 3; $i++) {
        createHtmlPlaceholder($categories_dir . '/category' . $i . '.html', 600, 300, '#dceeff', "Category " . $i);
    }
    
    createHtmlPlaceholder(__DIR__ . '/assets/images/placeholder.html', 400, 400, '#e6e6e6', "Product Placeholder");
    createHtmlPlaceholder(__DIR__ . '/assets/images/category-placeholder.html', 600, 300, '#dceeff', "Category Placeholder");
    createHtmlPlaceholder(__DIR__ . '/assets/images/hero-bg.html', 1200, 600, '#6496c8', "Terral Hero");
    
    echo "Please update your image extensions from .jpg to .html in your code.\n";
}

echo "All placeholder images created successfully!\n";
?> 