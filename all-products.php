<?php
/**
 * Terral Online Production System
 * All Products Page
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Start session for user authentication
session_start();

// Set content type
header('Content-Type: text/html; charset=UTF-8');

// Initialize variables
$products = [];
$categories = [];
$error_message = '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : null;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 12;
$offset = ($page - 1) * $items_per_page;
$total_products = 0;

// Get products and categories from database
try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Build query based on filters
    $query_count = "SELECT COUNT(*) as total FROM products p";
    $query = "SELECT p.*, c.name as category_name 
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.id";
    
    // Add WHERE clause for status and category filter
    if ($category_filter) {
        $query .= " WHERE c.name = :category_name AND (p.status = 'active' OR p.status = 'featured')";
        $query_count .= " LEFT JOIN categories c ON p.category_id = c.id WHERE c.name = :category_name AND (p.status = 'active' OR p.status = 'featured')";
    } else {
        $query .= " WHERE p.status = 'active' OR p.status = 'featured'";
        $query_count .= " WHERE p.status = 'active' OR p.status = 'featured'";
    }
    
    $query .= " GROUP BY p.id";
    
    // Add sorting
    switch ($sort_by) {
        case 'price_low':
            $query .= " ORDER BY p.price ASC";
            break;
        case 'price_high':
            $query .= " ORDER BY p.price DESC";
            break;
        case 'name':
            $query .= " ORDER BY p.name ASC";
            break;
        case 'newest':
        default:
            $query .= " ORDER BY p.id DESC";
            break;
    }
    
    $query .= " LIMIT :limit OFFSET :offset";
    
    // Get total product count for pagination
    $stmt_count = $conn->prepare($query_count);
    if ($category_filter) {
        $stmt_count->bindParam(':category_name', $category_filter);
    }
    $stmt_count->execute();
    $row_count = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $total_products = $row_count['total'];
    $total_pages = ceil($total_products / $items_per_page);
    
    // Get products
    $stmt = $conn->prepare($query);
    if ($category_filter) {
        $stmt->bindParam(':category_name', $category_filter);
    }
    $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add image URLs to products
        foreach ($products as &$product) {
            // Get the base URL dynamically
            $base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
            $base_url .= $_SERVER['HTTP_HOST'];
            
            $product['image_url'] = !empty($product['image']) 
                ? $base_url . '/Terral2/api/uploads/products/' . $product['image'] 
                : $base_url . '/Terral2/api/uploads/products/placeholder.jpg';
            // Add debug console log to see the actual image URL
            echo "<!-- Debug: Product ID " . $product['id'] . " image path: " . $product['image_url'] . " -->\n";
        }
    }
    
    // Get all categories
    $query = "SELECT * FROM categories ORDER BY name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <base href="<?php echo isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://'; ?><?php echo $_SERVER['HTTP_HOST']; ?>/Terral2/">
    <title>All Products - Terral Online Production System</title>
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
        
        .nav-links a:hover, .nav-links a.active {
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
        
        /* Page Header */
        .page-header {
            background: linear-gradient(rgba(44, 62, 80, 0.7), rgba(44, 62, 80, 0.7)), url('assets/images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            color: var(--white);
            text-align: center;
            padding: 60px 0;
            margin-bottom: 50px;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .breadcrumb {
            display: flex;
            justify-content: center;
            margin-top: 10px;
        }
        
        .breadcrumb-item {
            margin: 0 10px;
            position: relative;
        }
        
        .breadcrumb-item:not(:last-child)::after {
            content: '/';
            margin-left: 20px;
            color: var(--gray-light);
        }
        
        .breadcrumb-item a {
            color: var(--gray-light);
            transition: var(--transition);
        }
        
        .breadcrumb-item a:hover {
            color: var(--white);
        }
        
        .breadcrumb-item.active {
            color: var(--white);
            font-weight: 500;
        }
        
        /* Filter Section */
        .filter-section {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 40px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
        }
        
        .category-filter {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            flex: 2;
        }
        
        .category-filter label {
            margin-right: 10px;
            font-weight: 500;
        }
        
        .category-filter select {
            padding: 8px 15px;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            margin-right: 15px;
            background-color: var(--white);
            font-family: inherit;
        }
        
        .sort-filter {
            display: flex;
            align-items: center;
            flex: 1;
            justify-content: flex-end;
        }
        
        .sort-filter label {
            margin-right: 10px;
            font-weight: 500;
        }
        
        .sort-filter select {
            padding: 8px 15px;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            background-color: var(--white);
            font-family: inherit;
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
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
        
        .product-badge {
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
        
        .product-desc {
            color: var(--text-light);
            margin-bottom: 20px;
            flex-grow: 1;
        }
        
        .product-actions {
            display: flex;
            justify-content: space-between;
        }
        
        .add-to-cart {
            flex-grow: 1;
            margin-right: 10px;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            padding: 10px;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .add-to-cart:hover {
            background-color: var(--primary-dark);
        }
        
        .quick-view {
            background-color: var(--gray-light);
            color: var(--text-dark);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .quick-view:hover {
            background-color: var(--gray);
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-bottom: 50px;
        }
        
        .pagination a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            margin: 0 5px;
            border-radius: var(--border-radius);
            background-color: var(--white);
            color: var(--text-dark);
            font-weight: 500;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .pagination a:hover {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .pagination a.active {
            background-color: var(--primary);
            color: var(--white);
        }
        
        /* No Products */
        .no-products {
            text-align: center;
            padding: 50px 0;
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .no-products i {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 20px;
        }
        
        .no-products h3 {
            font-size: 1.5rem;
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        
        .no-products p {
            color: var(--text-light);
            margin-bottom: 20px;
        }
        
        /* Footer */
        .footer {
            background-color: var(--text-dark);
            color: var(--white);
            padding: 60px 0 30px;
            margin-top: 80px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-column h3 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-column h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 2px;
            background-color: var(--primary);
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: var(--gray);
            transition: var(--transition);
        }
        
        .footer-links a:hover {
            color: var(--primary);
            padding-left: 5px;
        }
        
        .footer-contact {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            color: var(--gray);
        }
        
        .footer-contact i {
            color: var(--primary);
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .social-links {
            display: flex;
            margin-top: 20px;
        }
        
        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            margin-right: 10px;
            color: var(--white);
            transition: var(--transition);
        }
        
        .social-links a:hover {
            background-color: var(--primary);
            transform: translateY(-3px);
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            text-align: center;
            font-size: 0.9rem;
            color: var(--gray);
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
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .filter-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .category-filter, .sort-filter {
                width: 100%;
                margin-bottom: 15px;
                justify-content: flex-start;
            }
            
            .sort-filter {
                justify-content: flex-start;
            }
            
            .pagination a {
                width: 35px;
                height: 35px;
                margin: 0 3px;
            }
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
                    <li><a href="all-products.php" class="active">Products</a></li>
                    <li><a href="index.php#how-it-works">How It Works</a></li>
                </ul>
                
                <div class="nav-icons">
                    <a href="#" id="search-toggle"><i class="fas fa-search"></i></a>
                    <a href="#" id="cart-toggle" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count">0</span>
                    </a>
                    <a href="<?php echo isset($_SESSION['user_id']) ? 'account.php' : 'login.php'; ?>">
                        <i class="fas fa-user"></i>
                    </a>
                </div>
            </nav>
        </div>
    </header>
    
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>All Products</h1>
            <div class="breadcrumb">
                <div class="breadcrumb-item"><a href="index.php">Home</a></div>
                <div class="breadcrumb-item active">Products</div>
            </div>
        </div>
    </section>
    
    <main class="container">
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php else: ?>
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="category-filter">
                    <label for="category">Category:</label>
                    <select id="category" name="category" onchange="location = this.value;">
                        <option value="all-products.php" <?php echo $category_filter === null ? 'selected' : ''; ?>>All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="all-products.php?category=<?php echo urlencode($category['name']); ?>" <?php echo $category_filter === $category['name'] ? 'selected' : ''; ?>>
                                <?php echo $category['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="sort-filter">
                    <label for="sort">Sort By:</label>
                    <select id="sort" name="sort" onchange="updateSort(this.value);">
                        <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest</option>
                        <option value="price_low" <?php echo $sort_by === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort_by === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name: A to Z</option>
                    </select>
                </div>
            </div>
            
            <?php if (empty($products)): ?>
                <!-- No Products Found -->
                <div class="no-products">
                    <i class="fas fa-shopping-basket"></i>
                    <h3>No Products Found</h3>
                    <p>We couldn't find any products matching your criteria.</p>
                    <a href="all-products.php" class="btn btn-primary">View All Products</a>
                </div>
            <?php else: ?>
                <!-- Products Grid -->
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <div class="product-img-container">
                                <a href="product-detail.php?id=<?php echo $product['id']; ?>">
                                    <img src="<?php echo $product['image_url']; ?>" alt="<?php echo $product['name']; ?>" class="product-img">
                                </a>
                                <?php if (isset($product['is_customizable']) && $product['is_customizable']): ?>
                                    <span class="product-badge">Customizable</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <h3 class="product-name">
                                    <a href="product-detail.php?id=<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a>
                                </h3>
                                <?php if (!empty($product['category_name'])): ?>
                                <div class="product-categories">
                                    <span class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="product-price">KSh <?php echo number_format($product['price'], 2); ?></div>
                                <p class="product-desc"><?php echo substr($product['description'], 0, 100) . '...'; ?></p>
                                <div class="product-actions">
                                    <button class="add-to-cart" data-id="<?php echo $product['id']; ?>" data-name="<?php echo $product['name']; ?>" data-price="<?php echo $product['price']; ?>" data-image="<?php echo $product['image_url']; ?>">
                                        <i class="fas fa-shopping-cart"></i> Add to Cart
                                    </button>
                                    <a href="product-detail.php?id=<?php echo $product['id']; ?>" class="quick-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo buildPaginationUrl($page - 1); ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<a href="' . buildPaginationUrl(1) . '">1</a>';
                        if ($start_page > 2) {
                            echo '<a>...</a>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active_class = $i === $page ? 'active' : '';
                        echo '<a href="' . buildPaginationUrl($i) . '" class="' . $active_class . '">' . $i . '</a>';
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<a>...</a>';
                        }
                        echo '<a href="' . buildPaginationUrl($total_pages) . '">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo buildPaginationUrl($page + 1); ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>Terral</h3>
                    <p>We provide high-quality customizable products for businesses and individuals. Make your brand stand out with our premium branded items.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="all-products.php">Products</a></li>
                        <li><a href="index.php#how-it-works">How It Works</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <div class="footer-contact">
                        <i class="fas fa-map-marker-alt"></i>
                        <p>123 Business Street, Meru, Kenya</p>
                    </div>
                    <div class="footer-contact">
                        <i class="fas fa-phone"></i>
                        <p>+254 712 345 678</p>
                    </div>
                    <div class="footer-contact">
                        <i class="fas fa-envelope"></i>
                        <p>info@terral.com</p>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Newsletter</h3>
                    <p>Subscribe to our newsletter for updates and special offers.</p>
                    <form class="newsletter-form">
                        <input type="email" placeholder="Your Email" class="form-control" style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: var(--border-radius); border: none;">
                        <button type="submit" class="btn btn-primary" style="width: 100%; background-color: var(--primary); color: var(--white); border: none; padding: 10px; border-radius: var(--border-radius); cursor: pointer;">Subscribe</button>
                    </form>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Terral Online Production System. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Notification -->
    <div class="notification" id="notification">
        <span id="notification-message"></span>
    </div>
    
    <script>
        // Helper function to build pagination URL
        <?php
        function buildPaginationUrl($page_number) {
            $params = $_GET;
            $params['page'] = $page_number;
            return 'all-products.php?' . http_build_query($params);
        }
        ?>
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
            
            // Add to cart functionality
            const addToCartButtons = document.querySelectorAll('.add-to-cart');
            
            addToCartButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const productData = {
                        id: this.dataset.id,
                        name: this.dataset.name,
                        price: parseFloat(this.dataset.price),
                        image: this.dataset.image,
                        quantity: 1
                    };
                    
                    addToCart(productData);
                });
            });
        });
        
        // Update sort function
        function updateSort(sortValue) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('sort', sortValue);
            window.location.href = 'all-products.php?' + urlParams.toString();
        }
        
        // Add to cart function
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
            showNotification(`${item.name} added to cart!`);
        }
        
        // Update cart count
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            const totalItems = cart.reduce((count, item) => count + item.quantity, 0);
            document.querySelector('.cart-count').textContent = totalItems;
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
    <?php include 'includes/product-image-fix.php'; ?>
    <?php include 'includes/fix-product-image.php'; ?>
    <script src="debug-product-images.js"></script>
</body>
</html> 