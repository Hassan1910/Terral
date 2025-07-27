<?php
// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Start session for user authentication
session_start();

// Set content type
header('Content-Type: text/html; charset=UTF-8');

// Get product ID from URL parameter
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no product ID is provided, redirect to products page
if ($product_id <= 0) {
    header('Location: index.php');
    exit;
}

// Initialize variables
$product = null;
$related_products = [];
$error_message = '';

// Get product details from database
try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Prepare query to get product details
    $query = "SELECT p.*, c.name as category_name, c.id as category_id
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE p.id = :id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $product_id);
    $stmt->execute();
    
    // Check if product exists
    if ($stmt->rowCount() > 0) {
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get product image URL
        $base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
        $base_url .= $_SERVER['HTTP_HOST'];
        
        $product['image_url'] = !empty($product['image']) 
            ? $base_url . '/Terral/api/uploads/products/' . $product['image'] 
            : $base_url . '/Terral/api/uploads/products/placeholder.jpg';
        
        // Get related products (products in the same category)
        if (!empty($product['category_id'])) {
            $category_id = $product['category_id'];
            
            $query = "SELECT p.* 
                      FROM products p
                      WHERE p.category_id = :category_id 
                      AND p.id != :product_id 
                      LIMIT 4";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
            
            $related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add image URLs to related products
            foreach ($related_products as &$related_product) {
                $related_product['image_url'] = !empty($related_product['image']) 
                    ? $base_url . '/Terral/api/uploads/products/' . $related_product['image'] 
                    : $base_url . '/Terral/api/uploads/products/placeholder.jpg';
            }
        }
    } else {
        // Product not found
        $error_message = 'Product not found';
    }
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://'; ?><?php echo $_SERVER['HTTP_HOST']; ?>/Terral/">
    <title><?php echo $product ? $product['name'] : 'Product Not Found'; ?> - Terral Online Production System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --secondary: #e74c3c;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --background: #f8f9fa;
            --white: #ffffff;
            --gray-light: #ecf0f1;
            --gray: #bdc3c7;
            --success: #2ecc71;
            --border-radius: 8px;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-dark);
            background-color: var(--background);
            line-height: 1.6;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        .container {
            width: 90%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header & Navigation */
        header {
            background-color: var(--white);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
        }
        
        .nav-links li {
            margin-left: 30px;
        }
        
        .nav-links a {
            font-weight: 500;
            transition: var(--transition);
        }
        
        .nav-links a:hover {
            color: var(--primary);
        }
        
        .nav-icons {
            display: flex;
            align-items: center;
        }
        
        .nav-icons a {
            margin-left: 20px;
            font-size: 1.2rem;
            transition: var(--transition);
        }
        
        .nav-icons a:hover {
            color: var(--primary);
        }
        
        /* Cart icon with counter */
        .cart-icon {
            position: relative;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--secondary);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* Main Content */
        .main-content {
            padding: 50px 0;
        }
        
        .breadcrumb {
            display: flex;
            margin-bottom: 30px;
            font-size: 0.9rem;
        }
        
        .breadcrumb a {
            color: var(--text-light);
        }
        
        .breadcrumb a:hover {
            color: var(--primary);
        }
        
        .breadcrumb-separator {
            margin: 0 10px;
            color: var(--text-light);
        }
        
        /* Product Details */
        .product-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-bottom: 50px;
        }
        
        @media (max-width: 992px) {
            .product-detail {
                grid-template-columns: 1fr;
            }
        }
        
        .product-gallery {
            position: relative;
        }
        
        .main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .thumbnail-container {
            display: flex;
            gap: 10px;
        }
        
        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: var(--border-radius);
            cursor: pointer;
            border: 2px solid transparent;
            transition: var(--transition);
        }
        
        .thumbnail:hover, .thumbnail.active {
            border-color: var(--primary);
        }
        
        .customize-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--primary);
            color: var(--white);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .product-info h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .product-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .product-category {
            background-color: var(--gray-light);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        .product-price {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
        }
        
        .product-description {
            margin-bottom: 30px;
            color: var(--text-light);
        }
        
        .product-meta {
            margin-bottom: 30px;
        }
        
        .product-meta-item {
            display: flex;
            margin-bottom: 10px;
        }
        
        .meta-label {
            font-weight: 600;
            min-width: 120px;
        }
        
        .stock-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .in-stock {
            background-color: var(--success);
            color: var(--white);
        }
        
        .low-stock {
            background-color: #f39c12;
            color: var(--white);
        }
        
        .out-of-stock {
            background-color: var(--secondary);
            color: var(--white);
        }
        
        /* Product Actions */
        .product-actions {
            margin-bottom: 30px;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            background-color: var(--gray-light);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: var(--transition);
        }
        
        .quantity-btn:hover {
            background-color: var(--gray);
        }
        
        .quantity-input {
            width: 60px;
            height: 40px;
            border: 1px solid var(--gray-light);
            text-align: center;
            margin: 0 10px;
            font-family: inherit;
            font-size: 1rem;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            margin-right: 10px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
            border: none;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .btn-outline {
            border: 2px solid var(--primary);
            color: var(--primary);
            background-color: transparent;
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: var(--white);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        /* Customization Section */
        .customization-section {
            margin-top: 40px;
            padding-top: 40px;
            border-top: 1px solid var(--gray-light);
        }
        
        .customization-section h2 {
            font-size: 1.5rem;
            margin-bottom: 20px;
        }
        
        .customization-options {
            margin-bottom: 20px;
        }
        
        .customization-option {
            margin-bottom: 20px;
        }
        
        .customization-option label {
            display: block;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .customization-text {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
            margin-bottom: 20px;
        }
        
        .upload-label {
            display: block;
            padding: 12px;
            background-color: var(--gray-light);
            color: var(--text-dark);
            border-radius: var(--border-radius);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: 10px;
        }
        
        .upload-label:hover {
            background-color: var(--gray);
        }
        
        .upload-preview {
            display: none;
            max-width: 100%;
            height: 200px;
            object-fit: contain;
            margin-top: 10px;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
        }
        
        /* Tabs Section */
        .tabs {
            margin-top: 50px;
        }
        
        .tab-links {
            display: flex;
            border-bottom: 1px solid var(--gray-light);
            margin-bottom: 20px;
        }
        
        .tab-link {
            padding: 10px 20px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 2px solid transparent;
        }
        
        .tab-link.active, .tab-link:hover {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        .tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Related Products */
        .section-title {
            text-align: center;
            margin-bottom: 40px;
            font-size: 2rem;
            position: relative;
        }
        
        .section-title:after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background-color: var(--primary);
            margin: 10px auto 0;
        }
        
        .related-products {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .product-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        
        .product-img-container {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .product-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .product-card:hover .product-img {
            transform: scale(1.1);
        }
        
        .product-info {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        
        .product-name {
            font-size: 1.2rem;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .product-price {
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--text-dark);
            margin-bottom: 15px;
        }
        
        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: var(--success);
            color: white;
            padding: 15px 20px;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            z-index: 1002;
            transform: translateX(150%);
            transition: transform 0.3s ease;
        }
        
        .notification.active {
            transform: translateX(0);
        }
        
        /* Error page */
        .error-container {
            text-align: center;
            padding: 50px 0;
        }
        
        .error-container i {
            font-size: 5rem;
            color: var(--secondary);
            margin-bottom: 20px;
        }
        
        .error-container h2 {
            font-size: 2rem;
            margin-bottom: 20px;
        }

        /* Footer */
        .footer {
            background-color: var(--text-dark);
            color: var(--white);
            padding: 60px 0 30px;
            margin-top: 80px;
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 40px;
            padding-top: 20px;
            text-align: center;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        /* Add these new styles to the existing styles */
        .customization-select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
            margin-bottom: 20px;
            background-color: var(--white);
        }
        
        #cart-sidebar {
            position: fixed;
            right: -400px;
            top: 0;
            width: 400px;
            height: 100vh;
            background-color: white;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
            z-index: 1001;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        #cart-sidebar.active {
            right: 0;
        }
        
        .cart-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cart-items {
            flex-grow: 1;
            overflow-y: auto;
            padding: 1.5rem;
        }
        
        .cart-item {
            display: flex;
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .cart-item-image {
            width: 60px;
            height: 60px;
            margin-right: 1rem;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cart-item-details {
            flex-grow: 1;
        }
        
        .cart-item-name {
            margin: 0 0 0.25rem;
            font-size: 1rem;
        }
        
        .cart-item-price {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cart-item-quantity {
            display: flex;
            align-items: center;
            margin-top: 0.5rem;
        }
        
        .cart-item-quantity button {
            width: 25px;
            height: 25px;
            border: 1px solid var(--gray-light);
            background: none;
            cursor: pointer;
            border-radius: 4px;
        }
        
        .cart-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--gray-light);
        }
        
        .cart-total {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        #overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        #overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .total-price-display {
            font-size: 1.2rem;
            font-weight: bold;
            margin-top: 10px;
            color: var(--primary-dark);
        }
    </style>
</head>
<body>
    <!-- Header & Navigation -->
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">
                    <i class="fas fa-paint-brush"></i> Terral
                </a>
                
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="index.php#products">Products</a></li>
                    <li><a href="index.php#how-it-works">How It Works</a></li>
                </ul>
                
                <div class="nav-icons">
                    <a href="#" id="search-toggle"><i class="fas fa-search"></i></a>
                    <a href="#" id="cart-toggle" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count">0</span>
                    </a>
                    <a href="login.php"><i class="fas fa-user"></i></a>
                </div>
            </nav>
        </div>
    </header>
    
    <main class="main-content">
        <div class="container">
            <?php if (!empty($error_message)): ?>
                <div class="error-container">
                    <i class="fas fa-exclamation-circle"></i>
                    <h2><?php echo $error_message; ?></h2>
                    <p>The product you're looking for might have been removed or is currently unavailable.</p>
                    <a href="index.php" class="btn btn-primary">Return to Home</a>
                </div>
            <?php elseif ($product): ?>
                <!-- Breadcrumb navigation -->
                <div class="breadcrumb">
                    <a href="index.php">Home</a>
                    <span class="breadcrumb-separator">/</span>
                    <a href="index.php#products">Products</a>
                    <span class="breadcrumb-separator">/</span>
                    <span><?php echo $product['name']; ?></span>
                </div>
                
                <!-- Product Detail -->
                <div class="product-detail">
                    <!-- Product Gallery -->
                    <div class="product-gallery">
                        <img src="<?php echo $product['image_url']; ?>" alt="<?php echo $product['name']; ?>" class="main-image" id="main-product-image">
                        
                        <?php if ($product['is_customizable']): ?>
                            <span class="customize-badge">Customizable</span>
                        <?php endif; ?>
                        
                        <!-- Thumbnails would go here in a real implementation -->
                        <div class="thumbnail-container">
                            <img src="<?php echo $product['image_url']; ?>" alt="Thumbnail 1" class="thumbnail active" onclick="changeMainImage(this.src)">
                            <img src="assets/images/product-thumbnail2.jpg" alt="Thumbnail 2" class="thumbnail" onclick="changeMainImage(this.src)">
                            <img src="assets/images/product-thumbnail3.jpg" alt="Thumbnail 3" class="thumbnail" onclick="changeMainImage(this.src)">
                        </div>
                    </div>
                    
                    <!-- Product Information -->
                    <div class="product-info">
                        <h1><?php echo $product['name']; ?></h1>
                        
                        <!-- Product Categories -->
                        <?php if (!empty($product['category_name'])): ?>
                        <div class="product-categories">
                            <h4>Category:</h4>
                            <div class="category-tags">
                                <span class="category-tag"><?php echo htmlspecialchars($product['category_name']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="product-price">KSh <?php echo number_format($product['price'], 2); ?></div>
                        
                        <div class="product-description">
                            <?php echo $product['description']; ?>
                        </div>
                        
                        <div class="product-meta">
                            <div class="product-meta-item">
                                <span class="meta-label">Availability:</span>
                                <?php if ($product['stock'] > 10): ?>
                                    <span class="stock-status in-stock">In Stock</span>
                                <?php elseif ($product['stock'] > 0): ?>
                                    <span class="stock-status low-stock">Low Stock (<?php echo $product['stock']; ?> left)</span>
                                <?php else: ?>
                                    <span class="stock-status out-of-stock">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Product Actions -->
                        <div class="product-actions">
                            <div class="quantity-control">
                                <button class="quantity-btn" id="decrease-qty">-</button>
                                <input type="number" class="quantity-input" id="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>">
                                <button class="quantity-btn" id="increase-qty">+</button>
                            </div>
                            
                            <button class="btn btn-primary" id="add-to-cart-btn" data-id="<?php echo $product['id']; ?>" data-name="<?php echo $product['name']; ?>" data-price="<?php echo $product['price']; ?>" data-image="<?php echo $product['image_url']; ?>">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                            
                            <?php if ($product['is_customizable']): ?>
                                <button class="btn btn-outline" id="customize-btn">Customize Now</button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($product['is_customizable']): ?>
                            <!-- Customization Section (initially hidden) -->
                            <div class="customization-section" id="customization-section" style="display: none;">
                                <h2>Customize Your Product</h2>
                                
                                <div class="customization-options">
                                    <!-- Add color selection -->
                                    <div class="customization-option">
                                        <label for="custom-color">Choose Color:</label>
                                        <select id="custom-color" class="customization-select">
                                            <option value="black">Black</option>
                                            <option value="white">White</option>
                                            <option value="red">Red</option>
                                            <option value="blue">Blue</option>
                                            <option value="green">Green</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Add size selection -->
                                    <div class="customization-option">
                                        <label for="custom-size">Choose Size:</label>
                                        <select id="custom-size" class="customization-select">
                                            <option value="small">Small</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="large">Large</option>
                                            <option value="xl">Extra Large</option>
                                        </select>
                                    </div>
                                    
                                    <div class="customization-option">
                                        <label for="custom-text">Add Custom Text:</label>
                                        <input type="text" id="custom-text" class="customization-text" placeholder="Enter your text here...">
                                    </div>
                                    
                                    <div class="customization-option">
                                        <label for="custom-image">Upload Your Image:</label>
                                        <label for="custom-image" class="upload-label">
                                            <i class="fas fa-upload"></i> Choose File
                                        </label>
                                        <input type="file" id="custom-image" accept="image/*" style="display: none;">
                                        <img id="image-preview" class="upload-preview" alt="Preview">
                                    </div>
                                </div>
                                
                                <button class="btn btn-primary" id="add-customized-to-cart">
                                    <i class="fas fa-shopping-cart"></i> Add Customized Product to Cart
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Product Tabs -->
                <div class="tabs">
                    <div class="tab-links">
                        <div class="tab-link active" data-tab="description">Description</div>
                        <div class="tab-link" data-tab="specifications">Specifications</div>
                        <div class="tab-link" data-tab="shipping">Shipping & Returns</div>
                    </div>
                    
                    <div class="tab-content active" id="description-tab">
                        <p><?php echo $product['description']; ?></p>
                    </div>
                    
                    <div class="tab-content" id="specifications-tab">
                        <h3>Product Specifications</h3>
                        <ul>
                            <li>Material: Premium Quality</li>
                            <li>Size: Standard</li>
                            <li>Weight: 0.5 kg</li>
                            <li>Color: Multiple options available</li>
                        </ul>
                    </div>
                    
                    <div class="tab-content" id="shipping-tab">
                        <h3>Shipping Information</h3>
                        <p>We ship to all major destinations worldwide. Standard shipping takes 3-5 business days.</p>
                        
                        <h3>Returns Policy</h3>
                        <p>If you're not completely satisfied with your purchase, you can return it within 30 days for a full refund.</p>
                    </div>
                </div>
                
                <!-- Related Products -->
                <?php if (!empty($related_products)): ?>
                    <div class="related-products-section">
                        <h2 class="section-title">Related Products</h2>
                        
                        <div class="related-products">
                            <?php foreach ($related_products as $related_product): ?>
                                <div class="product-card" data-id="<?php echo $related_product['id']; ?>" data-name="<?php echo $related_product['name']; ?>" data-price="<?php echo $related_product['price']; ?>" data-image="<?php echo $related_product['image_url']; ?>">
                                    <div class="product-img-container">
                                        <img src="<?php echo $related_product['image_url']; ?>" alt="<?php echo $related_product['name']; ?>" class="product-img">
                                        <?php if ($related_product['is_customizable']): ?>
                                            <span class="customize-badge">Customizable</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-info">
                                        <h3 class="product-name">
                                            <a href="product-detail.php?id=<?php echo $related_product['id']; ?>"><?php echo $related_product['name']; ?></a>
                                        </h3>
                                        <div class="product-price">KSh <?php echo number_format($related_product['price'], 2); ?></div>
                                        <button class="btn btn-primary add-to-cart" data-id="<?php echo $related_product['id']; ?>">
                                            Add to Cart
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Notification -->
    <div class="notification" id="notification">
        <span id="notification-message"></span>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Terral Online Production System. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Cart Sidebar -->
    <div id="cart-sidebar">
        <div class="cart-header">
            <h3>Your Cart</h3>
            <button id="close-cart" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="cart-items" class="cart-items"></div>
        
        <div class="cart-footer">
            <div class="cart-total">
                <span style="font-weight: 600;">Total:</span>
                <span id="cart-total" style="font-weight: 700; font-size: 1.25rem;">KSh 0.00</span>
            </div>
            <button id="checkout-btn" class="btn btn-primary" style="width: 100%; text-align: center;">Proceed to Checkout</button>
        </div>
    </div>
    
    <!-- Overlay for Cart -->
    <div id="overlay"></div>
    
    <script>
        // Initialize quantities
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
            updateCartDisplay();
            
            // Quantity controls
            const quantityInput = document.getElementById('quantity');
            const decreaseBtn = document.getElementById('decrease-qty');
            const increaseBtn = document.getElementById('increase-qty');
            
            if (decreaseBtn && increaseBtn && quantityInput) {
                decreaseBtn.addEventListener('click', function() {
                    let value = parseInt(quantityInput.value);
                    if (value > 1) {
                        quantityInput.value = value - 1;
                        updateTotalPrice();
                    }
                });
                
                increaseBtn.addEventListener('click', function() {
                    let value = parseInt(quantityInput.value);
                    let max = parseInt(quantityInput.getAttribute('max'));
                    if (value < max) {
                        quantityInput.value = value + 1;
                        updateTotalPrice();
                    }
                });
                
                // Update when quantity input changes directly
                quantityInput.addEventListener('change', function() {
                    updateTotalPrice();
                });
                
                // Initial total price calculation
                updateTotalPrice();
            }
            
            // Function to update the total price based on quantity
            function updateTotalPrice() {
                const priceElement = document.querySelector('.product-price');
                const basePrice = <?php echo $product['price']; ?>;
                const quantity = parseInt(quantityInput.value);
                const totalPrice = basePrice * quantity;
                
                // Create or update the total price display
                let totalPriceDisplay = document.querySelector('.total-price-display');
                if (!totalPriceDisplay) {
                    totalPriceDisplay = document.createElement('div');
                    totalPriceDisplay.className = 'total-price-display';
                    priceElement.insertAdjacentElement('afterend', totalPriceDisplay);
                }
                
                // Update the text
                if (quantity > 1) {
                    totalPriceDisplay.innerHTML = `Total: KSh ${totalPrice.toFixed(2)} <small>(${quantity} items @ KSh ${basePrice.toFixed(2)})</small>`;
                    totalPriceDisplay.style.display = 'block';
                } else {
                    totalPriceDisplay.style.display = 'none';
                }
            }
            
            // Tabs functionality
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabLinks.forEach(link => {
                link.addEventListener('click', function() {
                    tabLinks.forEach(l => l.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    this.classList.add('active');
                    document.getElementById(`${this.dataset.tab}-tab`).classList.add('active');
                });
            });
            
            // Customization toggle
            const customizeBtn = document.getElementById('customize-btn');
            const customizationSection = document.getElementById('customization-section');
            
            if (customizeBtn && customizationSection) {
                customizeBtn.addEventListener('click', function() {
                    if (customizationSection.style.display === 'none') {
                        customizationSection.style.display = 'block';
                        this.textContent = 'Hide Customization';
                    } else {
                        customizationSection.style.display = 'none';
                        this.textContent = 'Customize Now';
                    }
                });
            }
            
            // Image preview
            const customImage = document.getElementById('custom-image');
            const imagePreview = document.getElementById('image-preview');
            
            if (customImage && imagePreview) {
                customImage.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            imagePreview.src = e.target.result;
                            imagePreview.style.display = 'block';
                        };
                        
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
            
            // Add to cart functionality
            const addToCartBtn = document.getElementById('add-to-cart-btn');
            const addCustomizedToCartBtn = document.getElementById('add-customized-to-cart');
            const relatedProductBtns = document.querySelectorAll('.add-to-cart');
            
            if (addToCartBtn) {
                addToCartBtn.addEventListener('click', function() {
                    addToCart({
                        id: this.dataset.id,
                        name: this.dataset.name,
                        price: parseFloat(this.dataset.price),
                        image: this.dataset.image,
                        quantity: parseInt(document.getElementById('quantity').value)
                    });
                });
            }
            
            if (addCustomizedToCartBtn) {
                addCustomizedToCartBtn.addEventListener('click', function() {
                    const customText = document.getElementById('custom-text').value;
                    const imagePreview = document.getElementById('image-preview');
                    const customColor = document.getElementById('custom-color').value;
                    const customSize = document.getElementById('custom-size').value;
                    const productId = document.getElementById('add-to-cart-btn').dataset.id;
                    const productName = document.getElementById('add-to-cart-btn').dataset.name;
                    const productPrice = parseFloat(document.getElementById('add-to-cart-btn').dataset.price);
                    const productImage = document.getElementById('add-to-cart-btn').dataset.image;
                    const quantity = parseInt(document.getElementById('quantity').value);
                    
                    // Get custom image data (base64) if an image was uploaded
                    let customImage = null;
                    if (imagePreview && imagePreview.src && imagePreview.style.display !== 'none' && !imagePreview.src.includes('placeholder')) {
                        customImage = imagePreview.src;
                    }
                    
                    // Create a description of the customizations
                    let customDesc = ` (${customSize}, ${customColor}`;
                    if (customText) customDesc += `, Text: "${customText}"`;
                    if (customImage) customDesc += `, Custom Image`;
                    customDesc += ')';
                    
                    addToCart({
                        id: productId + '_custom_' + Date.now(),
                        product_id: productId,
                        name: productName + customDesc,
                        price: productPrice,
                        image: productImage,
                        quantity: quantity,
                        customization: {
                            text: customText,
                            image: customImage,
                            color: customColor,
                            size: customSize
                        }
                    });
                });
            }
            
            relatedProductBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const productCard = this.closest('.product-card');
                    addToCart({
                        id: productCard.dataset.id,
                        name: productCard.dataset.name,
                        price: parseFloat(productCard.dataset.price),
                        image: productCard.dataset.image,
                        quantity: 1
                    });
                });
            });
            
            // Cart Sidebar Toggle
            const cartToggle = document.getElementById('cart-toggle');
            const cartSidebar = document.getElementById('cart-sidebar');
            const overlay = document.getElementById('overlay');
            const closeCart = document.getElementById('close-cart');
            
            cartToggle.addEventListener('click', function(e) {
                e.preventDefault();
                cartSidebar.classList.add('active');
                overlay.classList.add('active');
                updateCartDisplay();
            });
            
            closeCart.addEventListener('click', function() {
                cartSidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
            
            overlay.addEventListener('click', function() {
                cartSidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
            
            // Add checkout button functionality
            const checkoutBtn = document.getElementById('checkout-btn');
            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', function() {
                    const cart = JSON.parse(localStorage.getItem('cart')) || [];
                    if (cart.length === 0) {
                        showNotification('Your cart is empty!');
                        return;
                    }
                    
                    // Send cart data to server via AJAX
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'save-cart-to-session.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            // Redirect to checkout page
                            window.location.href = 'checkout.php';
                        } else {
                            showNotification('Failed to process your cart. Please try again.');
                        }
                    };
                    
                    xhr.onerror = function() {
                        showNotification('Failed to communicate with the server. Please try again.');
                    };
                    
                    xhr.send(JSON.stringify(cart));
                });
            }
        });
        
        // Change main product image
        function changeMainImage(src) {
            document.getElementById('main-product-image').src = src;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                if (thumb.src === src) {
                    thumb.classList.add('active');
                } else {
                    thumb.classList.remove('active');
                }
            });
        }
        
        // Add to cart
        function addToCart(item) {
            let cart = JSON.parse(localStorage.getItem('cart')) || [];
            
            const existingItem = cart.find(i => i.id === item.id);
            
            if (existingItem) {
                existingItem.quantity += item.quantity;
            } else {
                cart.push(item);
            }
            
            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartCount();
            updateCartDisplay();
            showNotification(`${item.name} added to cart!`);
        }
        
        // Update cart count
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            const totalItems = cart.reduce((count, item) => count + item.quantity, 0);
            document.querySelector('.cart-count').textContent = totalItems;
        }
        
        // Update cart display in sidebar
        function updateCartDisplay() {
            const cartItems = document.getElementById('cart-items');
            const cartTotal = document.getElementById('cart-total');
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            
            if (cartItems) {
                // Clear current items
                cartItems.innerHTML = '';
                
                if (cart.length === 0) {
                    cartItems.innerHTML = '<p style="text-align: center; padding: 2rem;">Your cart is empty</p>';
                    cartTotal.textContent = 'KSh 0.00';
                    return;
                }
                
                let total = 0;
                
                cart.forEach((item, index) => {
                    const itemTotal = item.price * item.quantity;
                    total += itemTotal;
                    
                    const cartItem = document.createElement('div');
                    cartItem.className = 'cart-item';
                    
                    cartItem.innerHTML = `
                        <div class="cart-item-image">
                            <img src="${item.image}" alt="${item.name}">
                        </div>
                        <div class="cart-item-details">
                            <h4 class="cart-item-name">${item.name}</h4>
                            <div class="cart-item-price">
                                <span>KSh ${item.price.toFixed(2)} × ${item.quantity}</span>
                                <span style="font-weight: 600;">KSh ${itemTotal.toFixed(2)}</span>
                            </div>
                            <div class="cart-item-quantity">
                                <button class="cart-btn-decrease" data-index="${index}">-</button>
                                <span style="margin: 0 0.5rem;">${item.quantity}</span>
                                <button class="cart-btn-increase" data-index="${index}">+</button>
                                <button class="cart-btn-remove" data-index="${index}" style="margin-left: auto; background: none; border: none; color: var(--danger); cursor: pointer;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    
                    cartItems.appendChild(cartItem);
                });
                
                // Update total
                cartTotal.textContent = 'KSh ' + total.toFixed(2);
                
                // Add event listeners for quantity buttons
                document.querySelectorAll('.cart-btn-decrease').forEach(button => {
                    button.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        if (cart[index].quantity > 1) {
                            cart[index].quantity -= 1;
                        } else {
                            cart.splice(index, 1);
                        }
                        localStorage.setItem('cart', JSON.stringify(cart));
                        updateCartCount();
                        updateCartDisplay();
                    });
                });
                
                document.querySelectorAll('.cart-btn-increase').forEach(button => {
                    button.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        cart[index].quantity += 1;
                        localStorage.setItem('cart', JSON.stringify(cart));
                        updateCartCount();
                        updateCartDisplay();
                    });
                });
                
                document.querySelectorAll('.cart-btn-remove').forEach(button => {
                    button.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        cart.splice(index, 1);
                        localStorage.setItem('cart', JSON.stringify(cart));
                        updateCartCount();
                        updateCartDisplay();
                    });
                });
            }
        }
        
        // Show notification
        function showNotification(message) {
            const notification = document.getElementById('notification');
            const notificationMessage = document.getElementById('notification-message');
            
            notificationMessage.textContent = message;
            notification.classList.add('active');
            
            setTimeout(() => {
                notification.classList.remove('active');
            }, 3000);
        }
    </script>
<?php include 'includes/fix-product-image.php'; ?>
</body>
</html>