<?php
/**
 * Terral Online Production System
 * Modern Redesigned Homepage
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

// Start session for user authentication
session_start();

// Set content type
header('Content-Type: text/html; charset=UTF-8');

// Initialize variables
$site_name = 'Terral';
$site_description = 'Custom Branded Products in Kenya';
$featured_products = [];
$categories = [];
$store_info = [
    'tagline' => 'Making your brand stand out with premium quality',
    'phone' => '+254 712 345 678',
    'email' => 'info@terral.co.ke',
    'address' => 'Nairobi, Kenya'
];

// Get featured products and categories from database
try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Initialize ProductHelper
    $productHelper = new ProductHelper($conn);
    
    // Get featured products (limit to 8)
    $query = "SELECT p.*, c.name as category_name 
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE p.status = 'active' OR p.status = 'featured'
              ORDER BY p.created_at DESC
              LIMIT 8";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add image URLs to products
        foreach ($featured_products as &$product) {
            $product['image_url'] = $productHelper->getProductImageUrl($product['image']);
        }
    }
    
    // Get product categories
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
    <title><?php echo htmlspecialchars($site_name); ?> - Custom Branded Products in Kenya</title>
    
    <!-- Meta tags for SEO -->
    <meta name="description" content="<?php echo htmlspecialchars($store_info['tagline']); ?> - Premium quality customizable products for your business.">
    <meta name="keywords" content="customizable products, branded merchandise, promotional items, custom printing, Kenya">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($site_name); ?> - Custom Branded Products in Kenya">
    <meta property="og:description" content="<?php echo htmlspecialchars($store_info['tagline']); ?> - High-quality customizable products for your business.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']; ?>">
    
    <!-- Favicon -->
    <link rel="icon" href="/Terral2/assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Modern Theme CSS -->
    <link rel="stylesheet" href="/Terral2/assets/css/modern-theme.css">
    
    <!-- Structured Data for SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "<?php echo htmlspecialchars($site_name); ?>",
        "url": "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']; ?>",
        "logo": "/Terral2/assets/images/logo.png",
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "<?php echo htmlspecialchars($store_info['phone']); ?>",
            "contactType": "customer service"
        },
        "address": {
            "@type": "PostalAddress",
            "addressLocality": "<?php echo htmlspecialchars($store_info['address']); ?>"
        }
    }
    </script>
    
    <!-- Products Structured Data -->
    <?php if (!empty($featured_products)): ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ItemList",
        "itemListElement": [
            <?php $counter = 1; foreach ($featured_products as $product): ?>
            {
                "@type": "ListItem",
                "position": <?php echo $counter++; ?>,
                "item": {
                    "@type": "Product",
                    "name": "<?php echo htmlspecialchars($product['name']); ?>",
                    "description": "<?php echo htmlspecialchars(substr($product['description'], 0, 150)); ?>...",
                    "image": "<?php echo htmlspecialchars($product['image_url']); ?>",
                    "sku": "<?php echo htmlspecialchars($product['id']); ?>",
                    "offers": {
                        "@type": "Offer",
                        "price": "<?php echo number_format($product['price'], 2); ?>",
                        "priceCurrency": "KES",
                        "availability": "<?php echo $product['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'; ?>",
                        "url": "<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']; ?>/product-detail.php?id=<?php echo $product['id']; ?>"
                    }
                }
            }<?php if($counter <= count($featured_products)): ?>,<?php endif; ?>
            <?php endforeach; ?>
        ]
    }
    </script>
    <?php endif; ?>
</head>
<body>
    <!-- Promo Banner -->
    <div class="promo-banner">
        <div class="container">
            <div class="promo-content">
                <span class="promo-text"><i class="fas fa-gift"></i> Special Offer: Use code <strong>TERRAL20</strong> for 20% off your first order!</span>
                <div class="promo-countdown" id="promo-countdown">
                    <span class="countdown-text">Ends in:</span>
                    <span class="countdown-timer" id="countdown-timer">23:59:59</span>
                </div>
            </div>
            <button class="promo-close" id="promo-close"><i class="fas fa-times"></i></button>
        </div>
    </div>
    
    <!-- Navigation -->
    <header class="navbar">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <i class="fas fa-paint-brush"></i> <?php echo htmlspecialchars($site_name); ?>
            </a>
            
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a href="index.php" class="nav-link active">Home</a>
                </li>
                <li class="nav-item">
                    <a href="all-products.php" class="nav-link">Products</a>
                </li>
                <li class="nav-item">
                    <a href="#how-it-works" class="nav-link">How It Works</a>
                </li>
                <li class="nav-item">
                    <a href="#contact" class="nav-link">Contact</a>
                </li>
            </ul>
            
            <div class="navbar-icons">
                <a href="#" class="navbar-icon" id="search-toggle">
                    <i class="fas fa-search"></i>
                </a>
                <a href="#" class="navbar-icon cart-icon" id="cart-toggle">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count">0</span>
                </a>
                <a href="<?php echo isset($_SESSION['user_id']) ? 'account.php' : 'login.php'; ?>" class="navbar-icon">
                    <i class="fas fa-user"></i>
                </a>
            </div>
        </div>
    </header>
    
    <main class="main-content">
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>Custom Branded Products for Your Business</h1>
                    <p><?php echo htmlspecialchars($store_info['tagline']); ?> - Premium quality products with your logo or design.</p>
                    <div class="hero-buttons">
                        <a href="all-products.php" class="btn btn-secondary">Shop All Products</a>
                        <a href="#how-it-works" class="btn btn-outline">How It Works</a>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Top 3 Products Section -->
        <section class="section">
            <div class="container">
                <div class="section-header">
                    <h2>Top Products</h2>
                    <p>Our most popular customizable products</p>
                </div>
                
                <div class="top-products">
                    <?php
                    // Get top 3 products based on some criteria (newest, best selling, etc)
                    $top_products_query = "SELECT p.*, c.name as category_name 
                                          FROM products p
                                          LEFT JOIN categories c ON p.category_id = c.id
                                          WHERE p.status = 'active' OR p.status = 'featured'
                                          ORDER BY p.id DESC
                                          LIMIT 3";
                    $stmt = $conn->prepare($top_products_query);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Add image URLs to products
                        foreach ($top_products as &$product) {
                            $product['image_url'] = $productHelper->getProductImageUrl($product['image']);
                        }
                        ?>
                        <div class="top-products-grid">
                            <?php foreach ($top_products as $index => $product): ?>
                            <div class="top-product-card">
                                <div class="top-product-image">
                                    <a href="product-detail.php?id=<?php echo $product['id']; ?>">
                                        <img 
                                            src="<?php echo $product['image_url']; ?>"
                                            alt="<?php echo htmlspecialchars($product['name']); ?>"
                                        >
                                    </a>
                                    <div class="top-product-number"><?php echo $index + 1; ?></div>
                                    <?php if ($product['is_customizable']): ?>
                                    <span class="top-product-badge">Customizable</span>
                                    <?php endif; ?>
                                </div>
                                <div class="top-product-info">
                                    <?php if (!empty($product['category_name'])): ?>
                                    <div class="top-product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                                    <?php endif; ?>
                                    
                                    <h3 class="top-product-name">
                                        <a href="product-detail.php?id=<?php echo $product['id']; ?>">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h3>
                                    
                                    <div class="top-product-price">KSh <?php echo number_format($product['price'], 2); ?></div>
                                    
                                    <button class="btn btn-primary btn-sm add-to-cart" 
                                            data-id="<?php echo $product['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                            data-price="<?php echo $product['price']; ?>"
                                            data-image="<?php echo $product['image_url']; ?>">
                                        <i class="fas fa-shopping-cart"></i> Add to Cart
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </section>
        
        <!-- Categories Section -->
        <section class="section">
            <div class="container">
                <div class="section-header">
                    <h2>Browse Categories</h2>
                    <p>Find the perfect customizable products for your business needs</p>
                </div>
                
                <div class="categories-grid">
                    <?php foreach ($categories as $category): ?>
                    <a href="all-products.php?category=<?php echo urlencode($category['name']); ?>" class="category-card">
                        <img 
                            src="<?php echo !empty($category['image']) ? $category['image'] : 'api/uploads/categories/placeholder.jpg'; ?>"
                            alt="<?php echo htmlspecialchars($category['name']); ?>"
                            class="category-image"
                        >
                        <div class="category-overlay">
                            <h3 class="category-name"><?php echo htmlspecialchars($category['name']); ?></h3>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        
        <!-- Featured Products Section -->
        <?php if (!empty($featured_products)): ?>
        <section class="section" style="background-color: var(--light-2);">
            <div class="container">
                <div class="section-header">
                    <h2>Featured Products</h2>
                    <p>Discover our best-selling customizable products for your business</p>
                </div>
                
                <div class="products-grid">
                    <?php foreach ($featured_products as $product): ?>
                    <article class="product-card">
                        <div class="product-img-container">
                            <a href="product-detail.php?id=<?php echo $product['id']; ?>">
                                <img 
                                    src="<?php echo $product['image_url']; ?>"
                                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                                    class="product-img"
                                >
                            </a>
                            <?php if ($product['is_customizable']): ?>
                            <span class="product-badge">Customizable</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-info">
                            <?php if (!empty($product['category_name'])): ?>
                            <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                            <?php endif; ?>
                            
                            <h3 class="product-name">
                                <a href="product-detail.php?id=<?php echo $product['id']; ?>">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h3>
                            
                            <div class="product-price">KSh <?php echo number_format($product['price'], 2); ?></div>
                            
                            <p class="product-desc"><?php echo htmlspecialchars(substr($product['description'], 0, 100) . '...'); ?></p>
                            
                            <div class="product-actions">
                                <button class="btn btn-primary add-to-cart" 
                                        data-id="<?php echo $product['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                        data-price="<?php echo $product['price']; ?>"
                                        data-image="<?php echo $product['image_url']; ?>">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                                
                                <a href="product-detail.php?id=<?php echo $product['id']; ?>" class="quick-view">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- How It Works Section -->
        <section class="section" id="how-it-works">
            <div class="container">
                <div class="section-header">
                    <h2>How It Works</h2>
                    <p>Our simple process for creating customized products</p>
                </div>
                
                <div class="steps-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                    <div class="step-card" style="text-align: center; padding: 2rem; background-color: white; border-radius: var(--border-radius); box-shadow: var(--shadow);">
                        <div class="step-icon" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <h3>1. Choose a Product</h3>
                        <p>Browse our wide selection of premium quality products available for customization.</p>
                    </div>
                    
                    <div class="step-card" style="text-align: center; padding: 2rem; background-color: white; border-radius: var(--border-radius); box-shadow: var(--shadow);">
                        <div class="step-icon" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;">
                            <i class="fas fa-paint-brush"></i>
                        </div>
                        <h3>2. Customize It</h3>
                        <p>Add your logo, text, or design to make the product uniquely yours.</p>
                    </div>
                    
                    <div class="step-card" style="text-align: center; padding: 2rem; background-color: white; border-radius: var(--border-radius); box-shadow: var(--shadow);">
                        <div class="step-icon" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <h3>3. Place Your Order</h3>
                        <p>Review your customized product, add to cart, and complete the secure checkout.</p>
                    </div>
                    
                    <div class="step-card" style="text-align: center; padding: 2rem; background-color: white; border-radius: var(--border-radius); box-shadow: var(--shadow);">
                        <div class="step-icon" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h3>4. Receive Your Order</h3>
                        <p>We'll produce and deliver your customized products right to your doorstep.</p>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Testimonials Section -->
        <section class="section" style="background-color: var(--light-2);">
            <div class="container">
                <div class="section-header">
                    <h2>What Our Customers Say</h2>
                    <p>Hear from businesses that have used our products</p>
                </div>
                
                <div class="testimonials-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <div class="testimonial-card" style="background-color: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--shadow);">
                        <div class="testimonial-content" style="font-style: italic; margin-bottom: 1.5rem;">
                            <i class="fas fa-quote-left" style="color: var(--primary); margin-right: 0.5rem;"></i>
                            I was amazed by how easy it was to customize our company mugs. The ordering process was smooth, and the products arrived faster than expected.
                        </div>
                        <div class="testimonial-author" style="display: flex; align-items: center;">
                            <div class="author-avatar" style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; margin-right: 1rem;">
                                <img src="assets/images/placeholder.jpg" alt="John Smith" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div class="author-info">
                                <h4 style="margin: 0; font-size: 1rem;">John Smith</h4>
                                <p style="margin: 0; font-size: 0.875rem; color: var(--text-secondary);">Marketing Director, ABC Company</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="testimonial-card" style="background-color: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--shadow);">
                        <div class="testimonial-content" style="font-style: italic; margin-bottom: 1.5rem;">
                            <i class="fas fa-quote-left" style="color: var(--primary); margin-right: 0.5rem;"></i>
                            The customer service was exceptional. They helped me with design adjustments to ensure our logo looked perfect on the products. Highly recommended!
                        </div>
                        <div class="testimonial-author" style="display: flex; align-items: center;">
                            <div class="author-avatar" style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; margin-right: 1rem;">
                                <img src="assets/images/placeholder.jpg" alt="Sarah Johnson" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div class="author-info">
                                <h4 style="margin: 0; font-size: 1rem;">Sarah Johnson</h4>
                                <p style="margin: 0; font-size: 0.875rem; color: var(--text-secondary);">Small Business Owner</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="testimonial-card" style="background-color: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--shadow);">
                        <div class="testimonial-content" style="font-style: italic; margin-bottom: 1.5rem;">
                            <i class="fas fa-quote-left" style="color: var(--primary); margin-right: 0.5rem;"></i>
                            We ordered branded t-shirts for our company event, and the quality was outstanding. Everyone loved them, and we've already placed our second order!
                        </div>
                        <div class="testimonial-author" style="display: flex; align-items: center;">
                            <div class="author-avatar" style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; margin-right: 1rem;">
                                <img src="assets/images/placeholder.jpg" alt="David Mwangi" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                            <div class="author-info">
                                <h4 style="margin: 0; font-size: 1rem;">David Mwangi</h4>
                                <p style="margin: 0; font-size: 0.875rem; color: var(--text-secondary);">Events Manager, XYZ Corporation</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- CTA Section -->
        <section class="cta-section">
            <div class="container">
                <h2>Ready to Brand Your Products?</h2>
                <p>Create a free account today and start customizing premium quality products for your business or event.</p>
                <a href="register.php" class="btn btn-secondary">Create an Account</a>
            </div>
        </section>
    </main>
    
    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3>About <?php echo htmlspecialchars($site_name); ?></h3>
                    <p style="margin-bottom: 1rem;">We provide high-quality customizable products for businesses and individuals. Make your brand stand out with our premium branded items.</p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="all-products.php">Products</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Categories</h3>
                    <ul class="footer-links">
                        <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                        <li><a href="all-products.php?category=<?php echo urlencode($category['name']); ?>"><?php echo htmlspecialchars($category['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contact Us</h3>
                    <div class="footer-contact">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($store_info['address']); ?></span>
                    </div>
                    <div class="footer-contact">
                        <i class="fas fa-phone"></i>
                        <span><?php echo htmlspecialchars($store_info['phone']); ?></span>
                    </div>
                    <div class="footer-contact">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($store_info['email']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Shopping Cart Sidebar -->
    <div id="cart-sidebar" style="position: fixed; right: -400px; top: 0; width: 400px; height: 100vh; background-color: white; box-shadow: -2px 0 10px rgba(0,0,0,0.1); z-index: 1001; transition: var(--transition-normal); display: flex; flex-direction: column;">
        <div style="padding: 1.5rem; border-bottom: 1px solid var(--light-3); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">Your Cart</h3>
            <button id="close-cart" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="cart-items" style="flex-grow: 1; overflow-y: auto; padding: 1.5rem;"></div>
        
        <div style="padding: 1.5rem; border-top: 1px solid var(--light-3);">
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                <span style="font-weight: 600;">Total:</span>
                <span id="cart-total" style="font-weight: 700; font-size: 1.25rem;">KSh 0.00</span>
            </div>
            <button id="checkout-btn" class="btn btn-primary" style="width: 100%;">Proceed to Checkout</button>
        </div>
    </div>
    
    <!-- Overlay for Cart -->
    <div id="overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; opacity: 0; visibility: hidden; transition: var(--transition-normal);"></div>
    
    <!-- Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Shopping Cart Functionality
            const cartSidebar = document.getElementById('cart-sidebar');
            const overlay = document.getElementById('overlay');
            const cartToggle = document.getElementById('cart-toggle');
            const closeCart = document.getElementById('close-cart');
            const cartItems = document.getElementById('cart-items');
            const cartTotal = document.getElementById('cart-total');
            const cartCount = document.querySelector('.cart-count');
            const checkoutBtn = document.getElementById('checkout-btn');
            
            // Initialize cart from localStorage
            let cart = JSON.parse(localStorage.getItem('cart')) || [];
            updateCart();
            
            // Cart Toggle
            cartToggle.addEventListener('click', function(e) {
                e.preventDefault();
                cartSidebar.style.right = '0';
                overlay.style.opacity = '1';
                overlay.style.visibility = 'visible';
            });
            
            // Close Cart
            closeCart.addEventListener('click', function() {
                cartSidebar.style.right = '-400px';
                overlay.style.opacity = '0';
                overlay.style.visibility = 'hidden';
            });
            
            // Close Cart on Overlay Click
            overlay.addEventListener('click', function() {
                cartSidebar.style.right = '-400px';
                overlay.style.opacity = '0';
                overlay.style.visibility = 'hidden';
            });
            
            // Add to Cart Buttons
            document.querySelectorAll('.add-to-cart').forEach(button => {
                button.addEventListener('click', function() {
                    const productData = {
                        id: this.dataset.id,
                        name: this.dataset.name,
                        price: parseFloat(this.dataset.price),
                        image: this.dataset.image,
                        quantity: 1
                    };
                    
                    addToCart(productData);
                    
                    // Show cart sidebar
                    cartSidebar.style.right = '0';
                    overlay.style.opacity = '1';
                    overlay.style.visibility = 'visible';
                });
            });
            
            // Checkout Button
            checkoutBtn.addEventListener('click', function() {
                if (cart.length > 0) {
                    window.location.href = 'checkout.php';
                } else {
                    alert('Your cart is empty. Add some products before checking out.');
                }
            });
            
            // Add to Cart Function
            function addToCart(product) {
                // Check if product already in cart
                const existingItem = cart.find(item => item.id === product.id);
                
                if (existingItem) {
                    existingItem.quantity += 1;
                } else {
                    cart.push(product);
                }
                
                updateCart();
                showNotification(product.name + ' added to cart');
            }
            
            // Update Cart Function
            function updateCart() {
                // Save to localStorage
                localStorage.setItem('cart', JSON.stringify(cart));
                
                // Update cart count
                cartCount.textContent = cart.reduce((total, item) => total + item.quantity, 0);
                
                // Update cart items display
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
                    cartItem.style.display = 'flex';
                    cartItem.style.marginBottom = '1rem';
                    cartItem.style.padding = '0.5rem';
                    cartItem.style.borderBottom = '1px solid var(--light-3)';
                    
                    cartItem.innerHTML = `
                        <div style="width: 60px; height: 60px; margin-right: 1rem; border-radius: var(--border-radius); overflow: hidden;">
                            <img src="${item.image}" alt="${item.name}" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div style="flex-grow: 1;">
                            <h4 style="margin: 0 0 0.25rem; font-size: 1rem;">${item.name}</h4>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>KSh ${item.price.toFixed(2)} × ${item.quantity}</span>
                                <span style="font-weight: 600;">KSh ${itemTotal.toFixed(2)}</span>
                            </div>
                            <div style="display: flex; align-items: center; margin-top: 0.5rem;">
                                <button class="cart-btn-decrease" data-index="${index}" style="width: 25px; height: 25px; border: 1px solid var(--light-3); background: none; cursor: pointer; border-radius: 4px;">-</button>
                                <span style="margin: 0 0.5rem;">${item.quantity}</span>
                                <button class="cart-btn-increase" data-index="${index}" style="width: 25px; height: 25px; border: 1px solid var(--light-3); background: none; cursor: pointer; border-radius: 4px;">+</button>
                                <button class="cart-btn-remove" data-index="${index}" style="margin-left: auto; background: none; border: none; color: var(--danger); cursor: pointer;"><i class="fas fa-trash"></i></button>
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
                        updateCart();
                    });
                });
                
                document.querySelectorAll('.cart-btn-increase').forEach(button => {
                    button.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        cart[index].quantity += 1;
                        updateCart();
                    });
                });
                
                document.querySelectorAll('.cart-btn-remove').forEach(button => {
                    button.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        cart.splice(index, 1);
                        updateCart();
                    });
                });
            }
            
            // Show Notification
            function showNotification(message) {
                const notification = document.createElement('div');
                notification.className = 'notification';
                notification.style.position = 'fixed';
                notification.style.top = '20px';
                notification.style.right = '20px';
                notification.style.backgroundColor = 'var(--success)';
                notification.style.color = 'white';
                notification.style.padding = '10px 20px';
                notification.style.borderRadius = 'var(--border-radius)';
                notification.style.boxShadow = 'var(--shadow)';
                notification.style.zIndex = '2000';
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(50px)';
                notification.style.transition = 'opacity 0.3s, transform 0.3s';
                notification.textContent = message;
                
                document.body.appendChild(notification);
                
                // Show notification
                setTimeout(() => {
                    notification.style.opacity = '1';
                    notification.style.transform = 'translateX(0)';
                }, 10);
                
                // Remove notification after 3 seconds
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateX(50px)';
                    setTimeout(() => {
                        document.body.removeChild(notification);
                    }, 300);
                }, 3000);
            }
            
            // Promo Banner Countdown
            function startCountdown() {
                // Set the date we're counting down to (24 hours from now)
                const countDownDate = new Date();
                countDownDate.setDate(countDownDate.getDate() + 1);
                
                // Update the countdown every 1 second
                const countdownTimer = document.getElementById('countdown-timer');
                const countdownInterval = setInterval(function() {
                    // Get today's date and time
                    const now = new Date().getTime();
                    
                    // Find the distance between now and the countdown date
                    const distance = countDownDate - now;
                    
                    // Time calculations for hours, minutes and seconds
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    
                    // Display the result
                    countdownTimer.innerHTML = 
                        (hours < 10 ? '0' + hours : hours) + ":" +
                        (minutes < 10 ? '0' + minutes : minutes) + ":" +
                        (seconds < 10 ? '0' + seconds : seconds);
                    
                    // If the countdown is finished, clear interval
                    if (distance < 0) {
                        clearInterval(countdownInterval);
                        countdownTimer.innerHTML = "EXPIRED";
                    }
                }, 1000);
            }
            
            // Close Promo Banner
            const promoBanner = document.querySelector('.promo-banner');
            const promoCloseBtn = document.getElementById('promo-close');
            if (promoCloseBtn) {
                promoCloseBtn.addEventListener('click', function() {
                    promoBanner.style.height = '0';
                    setTimeout(function() {
                        promoBanner.style.display = 'none';
                        // Adjust main content margin
                        document.querySelector('.main-content').style.marginTop = 'var(--navbar-height)';
                    }, 300);
                    
                    // Set cookie to remember closed state
                    localStorage.setItem('promo_banner_closed', 'true');
                });
            }
            
            // Check if banner was previously closed
            if (localStorage.getItem('promo_banner_closed') !== 'true') {
                startCountdown();
            } else {
                // Banner was closed before, hide it
                promoBanner.style.display = 'none';
                // Adjust main content margin
                document.querySelector('.main-content').style.marginTop = 'var(--navbar-height)';
            }
        });
    </script>
    
    <!-- Product Image Fix Script -->
    <script src="/Terral2/assets/js/fix-product-image.js"></script>
</body>
</html> 