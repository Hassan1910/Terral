<?php
/**
 * Add Product Page
 * 
 * This page allows administrators to add new products with support for multiple image formats.
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', dirname(__DIR__));
define('IS_ADMIN', true);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/helpers/ImageUploadHelper.php';

// Start session for user authentication
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /Terral2/login.php');
    exit;
}

// Initialize variables
$pageTitle = 'Add New Product';
$successMessage = '';
$errorMessage = '';
$categories = [];
$product = [
    'name' => '',
    'description' => '',
    'price' => '',
    'stock' => '',
    'category_id' => '',
    'status' => 'active',
    'is_customizable' => 0
];

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Get categories from database for dropdown
try {
    $query = "SELECT id, name FROM categories ORDER BY name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = 'Error loading categories: ' . $e->getMessage();
}

// Initialize image upload helper
$imageUploader = new ImageUploadHelper();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $requiredFields = ['name', 'description', 'price', 'stock', 'category_id'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill all required fields.");
            }
            
            // Update product array with submitted values
            $product[$field] = $_POST[$field];
        }
        
        // Validate numeric fields
        if (!is_numeric($_POST['price']) || $_POST['price'] < 0) {
            throw new Exception("Price must be a valid positive number.");
        }
        
        if (!is_numeric($_POST['stock']) || $_POST['stock'] < 0) {
            throw new Exception("Stock quantity must be a valid positive number.");
        }
        
        // Validate category
        $categoryExists = false;
        foreach ($categories as $category) {
            if ($category['id'] == $_POST['category_id']) {
                $categoryExists = true;
                break;
            }
        }
        
        if (!$categoryExists) {
            throw new Exception("Selected category is invalid.");
        }
        
        // Get optional fields
        $product['status'] = $_POST['status'] ?? 'active';
        $product['is_customizable'] = isset($_POST['is_customizable']) ? 1 : 0;
        $product['sku'] = $_POST['sku'] ?? null;
        $product['weight'] = !empty($_POST['weight']) ? $_POST['weight'] : null;
        $product['dimensions'] = $_POST['dimensions'] ?? null;
        
        // Check if image was uploaded via our AJAX uploader
        $imageFilename = isset($_POST['image_filename']) ? $_POST['image_filename'] : null;
        
        // Start transaction
        $conn->beginTransaction();
        
        // Insert product into database
        $sql = "INSERT INTO products (name, description, price, stock, category_id, status, is_customizable, image, sku, weight, dimensions, created_at) 
                VALUES (:name, :description, :price, :stock, :category_id, :status, :is_customizable, :image, :sku, :weight, :dimensions, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':name', $product['name']);
        $stmt->bindParam(':description', $product['description']);
        $stmt->bindParam(':price', $product['price']);
        $stmt->bindParam(':stock', $product['stock']);
        $stmt->bindParam(':category_id', $product['category_id']);
        $stmt->bindParam(':status', $product['status']);
        $stmt->bindParam(':is_customizable', $product['is_customizable']);
        $stmt->bindParam(':image', $imageFilename);
        $stmt->bindParam(':sku', $product['sku']);
        $stmt->bindParam(':weight', $product['weight']);
        $stmt->bindParam(':dimensions', $product['dimensions']);
        
        $stmt->execute();
        
        // Get the new product ID
        $productId = $conn->lastInsertId();
        
        // Commit transaction
        $conn->commit();
        
        // Success!
        $successMessage = "Product added successfully!";
        
        // Reset product form
        $product = [
            'name' => '',
            'description' => '',
            'price' => '',
            'stock' => '',
            'category_id' => '',
            'status' => 'active',
            'is_customizable' => 0
        ];
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        $errorMessage = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Terral Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <!-- Admin Styles -->
    <link href="/Terral2/assets/css/admin.css" rel="stylesheet">
    <!-- Image Upload Styles -->
    <link href="/Terral2/assets/css/admin-image-upload.css" rel="stylesheet">
    <style>
        .image-preview {
            margin-bottom: 20px;
        }
        .preview-container {
            min-height: 200px;
        }
    </style>
</head>
<body>
    <!-- Admin Header Placeholder -->
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Admin Sidebar Placeholder -->
            <div class="col-lg-9 col-md-8 ml-auto">
                <div class="row">
                    <div class="col-12">
                        <h2 class="mb-4">
                            <i class="fas fa-box-open mr-2"></i> Add New Product
                        </h2>
                        
                        <?php include 'product-template.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS, jQuery, Popper.js -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <!-- Admin Image Upload JS -->
    <script src="/Terral2/assets/js/admin-image-upload.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize image uploader for product
            const productImageUploader = new ImageUploader({
                inputSelector: '#product-image-upload',
                previewSelector: '#product-image-preview',
                formSelector: '#product-form',
                uploadType: 'products'
            });
        });
    </script>
</body>
</html> 