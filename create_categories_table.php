<?php
// Define root path
define('ROOT_PATH', __DIR__);

// Include database configuration
require_once ROOT_PATH . '/api/config/Database.php';

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// SQL to create categories table
$categoriesTableSQL = "CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// SQL to create a category_id column in products table if it doesn't exist
$addCategoryColumnSQL = "
SELECT COUNT(*) AS column_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'products' 
AND column_name = 'category_id'";

// Execute categories table creation query
try {
    $conn->exec($categoriesTableSQL);
    echo "Categories table created successfully!<br>";
    
    // Check if product table exists and if category_id column exists
    $stmt = $conn->query("SHOW TABLES LIKE 'products'");
    if ($stmt->rowCount() > 0) {
        // Products table exists, check for category_id column
        $stmt = $conn->query($addCategoryColumnSQL);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['column_exists'] == 0) {
            // Column doesn't exist, add it
            $conn->exec("ALTER TABLE products ADD COLUMN category_id INT AFTER description");
            $conn->exec("ALTER TABLE products ADD CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL");
            echo "Added category_id column to products table.<br>";
        } else {
            echo "Category_id column already exists in products table.<br>";
        }
    } else {
        echo "Note: Products table doesn't exist yet.<br>";
    }
    
    // Add some default categories if the table is empty
    $stmt = $conn->query("SELECT COUNT(*) as count FROM categories");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row['count'] == 0) {
        $defaultCategories = [
            ["T-Shirts", "Customizable t-shirts in various styles and colors"],
            ["Mugs", "Personalized mugs for coffee and tea lovers"],
            ["Posters", "High-quality printed posters for decoration"],
            ["Accessories", "Customizable accessories like phone cases and keychains"],
            ["Stationery", "Personalized notebooks, pens, and other stationery items"]
        ];
        
        $insertSQL = "INSERT INTO categories (name, description) VALUES (?, ?)";
        $stmt = $conn->prepare($insertSQL);
        
        foreach ($defaultCategories as $category) {
            $stmt->execute($category);
        }
        
        echo "Added default categories.<br>";
    }
    
    echo "<br>Setup complete! <a href='admin/products.php'>Go to Products Admin</a>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
?> 