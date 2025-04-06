<?php
/**
 * Database Fix Script
 * This script adds missing columns to the products table
 */

// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Fix: Adding Missing Products Columns</h1>";

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get the current table structure
    $stmt = $conn->prepare("DESCRIBE products");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Add missing columns if they don't exist
    $columnsToAdd = [
        'sku' => "ALTER TABLE products ADD COLUMN sku VARCHAR(100) NULL AFTER status",
        'weight' => "ALTER TABLE products ADD COLUMN weight DECIMAL(10, 2) NULL AFTER sku",
        'dimensions' => "ALTER TABLE products ADD COLUMN dimensions VARCHAR(100) NULL AFTER weight",
    ];
    
    $columnsAdded = 0;
    
    foreach ($columnsToAdd as $column => $sql) {
        if (!in_array($column, $columns)) {
            $conn->exec($sql);
            echo "<p style='color: green;'>✅ Added missing column: <strong>{$column}</strong></p>";
            $columnsAdded++;
        } else {
            echo "<p>Column already exists: {$column}</p>";
        }
    }
    
    // Check if category_id already exists
    if (!in_array('category_id', $columns)) {
        // First check if any products have category relationships in product_categories
        $stmt = $conn->prepare("SELECT COUNT(*) FROM product_categories");
        $stmt->execute();
        $hasCategories = ($stmt->fetchColumn() > 0);
        
        // Add the category_id column
        $conn->exec("ALTER TABLE products ADD COLUMN category_id INT NULL AFTER status");
        echo "<p style='color: green;'>✅ Added missing column: <strong>category_id</strong></p>";
        $columnsAdded++;
        
        // If we have existing product-category relationships, migrate them
        if ($hasCategories) {
            $conn->exec("
                UPDATE products p
                JOIN product_categories pc ON p.id = pc.product_id
                SET p.category_id = pc.category_id
            ");
            echo "<p style='color: blue;'>ℹ️ Migrated existing category relationships to category_id column</p>";
        }
        
        // Set default category for products without a category
        $stmt = $conn->prepare("SELECT id FROM categories ORDER BY id LIMIT 1");
        $stmt->execute();
        $defaultCategoryId = $stmt->fetchColumn();
        
        if ($defaultCategoryId) {
            $conn->exec("UPDATE products SET category_id = {$defaultCategoryId} WHERE category_id IS NULL");
            echo "<p style='color: blue;'>ℹ️ Set default category for products without a category</p>";
        }
        
        // Now make category_id NOT NULL
        $conn->exec("ALTER TABLE products MODIFY COLUMN category_id INT NOT NULL");
        
        // Add foreign key if it doesn't exist
        $conn->exec("ALTER TABLE products ADD CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE");
        echo "<p style='color: green;'>✅ Added foreign key constraint for category_id</p>";
    } else {
        echo "<p>Column already exists: category_id</p>";
    }
    
    // Add indexes if needed
    $stmt = $conn->prepare("SHOW INDEX FROM products WHERE Key_name = 'idx_category_id'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $conn->exec("CREATE INDEX idx_category_id ON products(category_id)");
        echo "<p style='color: green;'>✅ Added index for category_id</p>";
    }
    
    if ($columnsAdded == 0) {
        echo "<p style='color: blue;'>All required columns already exist in the products table.</p>";
    } else {
        echo "<h2>Success! Fixed the products table by adding {$columnsAdded} missing columns.</h2>";
    }
    
    echo "<p>You should now be able to add products without the 'Unknown column' error.</p>";
    echo "<p><a href='admin/add-product.php' style='color: #3d5afe; font-weight: bold;'>Go to Add Product Page</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?> 