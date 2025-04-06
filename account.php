<?php
// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/models/User.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php');
    exit;
}

// Set content type
header('Content-Type: text/html; charset=UTF-8');

// Initialize variables
$user = null;
$orders = [];
$error_message = '';

// Get user information
try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get user details
    $query = "SELECT * FROM users WHERE id = :id LIMIT 0,1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error_message = 'User not found';
    }
    
    // Get user orders
    $query = "SELECT o.*, COUNT(oi.id) as item_count, SUM(oi.quantity) as total_items,
              o.id as order_number, o.total_price as total_amount, o.status
              FROM orders o
              LEFT JOIN order_items oi ON o.id = oi.order_id
              WHERE o.user_id = :user_id
              GROUP BY o.id
              ORDER BY o.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>My Account - Terral Online Production System</title>
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
        
        .nav-icons a.active {
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
        
        .welcome-text {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        /* Account Dashboard */
        .account-dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
            margin-bottom: 50px;
        }
        
        @media (max-width: 768px) {
            .account-dashboard {
                grid-template-columns: 1fr;
            }
        }
        
        /* Account Sidebar */
        .account-sidebar {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
        }
        
        .sidebar-user {
            text-align: center;
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: var(--primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
        }
        
        .user-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .user-email {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .sidebar-nav {
            list-style: none;
        }
        
        .sidebar-nav li {
            margin-bottom: 10px;
        }
        
        .sidebar-nav a {
            display: block;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: 500;
        }
        
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background-color: var(--gray-light);
            color: var(--primary);
        }
        
        .sidebar-nav a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .logout-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: var(--gray-light);
            color: var(--text-dark);
            border: none;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            margin-top: 20px;
        }
        
        .logout-btn:hover {
            background-color: var(--secondary);
            color: var(--white);
        }
        
        /* Account Content */
        .account-content {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 30px;
        }
        
        .content-title {
            font-size: 1.8rem;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        /* User Information */
        .user-info {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .info-card {
            background-color: var(--gray-light);
            border-radius: var(--border-radius);
            padding: 20px;
        }
        
        .info-card h3 {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: var(--primary);
        }
        
        .info-detail {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: 600;
            margin-right: 10px;
        }
        
        .edit-link {
            display: inline-block;
            margin-top: 15px;
            color: var(--primary);
            font-weight: 500;
            transition: var(--transition);
        }
        
        .edit-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        /* Order History */
        .order-history {
            margin-top: 40px;
        }
        
        .order-history h2 {
            font-size: 1.5rem;
            margin-bottom: 20px;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th, .orders-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .orders-table th {
            background-color: var(--gray-light);
            font-weight: 600;
        }
        
        .orders-table tr:hover {
            background-color: var(--gray-light);
        }
        
        .order-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #f39c12;
            color: var(--white);
        }
        
        .status-processing {
            background-color: #3498db;
            color: var(--white);
        }
        
        .status-shipped {
            background-color: #2ecc71;
            color: var(--white);
        }
        
        .status-delivered {
            background-color: #27ae60;
            color: var(--white);
        }
        
        .status-cancelled {
            background-color: #e74c3c;
            color: var(--white);
        }
        
        .view-order-btn {
            padding: 5px 10px;
            background-color: var(--primary);
            color: var(--white);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .view-order-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .no-orders {
            text-align: center;
            padding: 30px;
            background-color: var(--gray-light);
            border-radius: var(--border-radius);
            margin-top: 20px;
        }
        
        .no-orders i {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 15px;
        }
        
        .no-orders h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
        }
        
        .shop-now-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background-color: var(--primary);
            color: var(--white);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .shop-now-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        /* Footer */
        .footer {
            background-color: var(--text-dark);
            color: var(--white);
            padding: 30px 0;
            margin-top: 80px;
        }
        
        .footer-bottom {
            text-align: center;
            font-size: 0.9rem;
            color: var(--gray);
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
                    <li><a href="all-products.php">Products</a></li>
                    <li><a href="index.php#how-it-works">How It Works</a></li>
                </ul>
                
                <div class="nav-icons">
                    <a href="#" id="search-toggle"><i class="fas fa-search"></i></a>
                    <a href="#" id="cart-toggle" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count">0</span>
                    </a>
                    <a href="account.php" class="active"><i class="fas fa-user"></i></a>
                </div>
            </nav>
        </div>
    </header>
    
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>My Account</h1>
            <p class="welcome-text">Welcome back, <?php echo isset($user['first_name']) ? $user['first_name'] : 'Customer'; ?>!</p>
        </div>
    </section>
    
    <main class="container">
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php else: ?>
            <div class="account-dashboard">
                <!-- Account Sidebar -->
                <div class="account-sidebar">
                    <div class="sidebar-user">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <h3 class="user-name"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h3>
                        <p class="user-email"><?php echo $user['email']; ?></p>
                    </div>
                    
                    <ul class="sidebar-nav">
                        <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="#"><i class="fas fa-shopping-bag"></i> Orders</a></li>
                        <li><a href="#"><i class="fas fa-heart"></i> Wishlist</a></li>
                        <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
                    </ul>
                    
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
                
                <!-- Account Content -->
                <div class="account-content">
                    <h2 class="content-title">Account Dashboard</h2>
                    
                    <!-- User Information -->
                    <div class="user-info">
                        <div class="info-card">
                            <h3>Personal Information</h3>
                            <div class="info-detail">
                                <span class="info-label">Name:</span>
                                <span><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></span>
                            </div>
                            <div class="info-detail">
                                <span class="info-label">Email:</span>
                                <span><?php echo $user['email']; ?></span>
                            </div>
                            <div class="info-detail">
                                <span class="info-label">Phone:</span>
                                <span><?php echo $user['phone']; ?></span>
                            </div>
                            <a href="#" class="edit-link">Edit Profile</a>
                        </div>
                        
                        <div class="info-card">
                            <h3>Shipping Address</h3>
                            <div class="info-detail">
                                <span class="info-label">Address:</span>
                                <span><?php echo $user['address']; ?></span>
                            </div>
                            <div class="info-detail">
                                <span class="info-label">City:</span>
                                <span><?php echo $user['city']; ?></span>
                            </div>
                            <div class="info-detail">
                                <span class="info-label">Postal Code:</span>
                                <span><?php echo $user['postal_code']; ?></span>
                            </div>
                            <div class="info-detail">
                                <span class="info-label">Country:</span>
                                <span><?php echo $user['country']; ?></span>
                            </div>
                            <a href="#" class="edit-link">Edit Address</a>
                        </div>
                    </div>
                    
                    <!-- Order History -->
                    <div class="order-history">
                        <h2>Recent Orders</h2>
                        
                        <?php if (empty($orders)): ?>
                            <div class="no-orders">
                                <i class="fas fa-shopping-basket"></i>
                                <h3>No orders yet</h3>
                                <p>Looks like you haven't placed any orders yet.</p>
                                <a href="all-products.php" class="shop-now-btn">Shop Now</a>
                            </div>
                        <?php else: ?>
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><?php echo !empty($order['order_number']) ? $order['order_number'] : 'N/A'; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                            <td><?php echo !empty($order['total_items']) ? $order['total_items'] : 0; ?></td>
                                            <td>KSh <?php echo number_format(!empty($order['total_amount']) ? $order['total_amount'] : 0, 2); ?></td>
                                            <td>
                                                <span class="order-status status-<?php echo strtolower(!empty($order['status']) ? $order['status'] : 'pending'); ?>">
                                                    <?php echo ucfirst(!empty($order['status']) ? $order['status'] : 'pending'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="view-order-btn">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Terral Online Production System. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <script>
        // Update cart count when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
        });
        
        // Update cart count
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            const totalItems = cart.reduce((count, item) => count + item.quantity, 0);
            document.querySelector('.cart-count').textContent = totalItems;
        }
    </script>
</body>
</html> 