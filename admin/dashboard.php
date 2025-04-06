<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page if not logged in or not an admin
    header('Location: ../login.php');
    exit;
}

// Include necessary files
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/models/User.php';
require_once ROOT_PATH . '/api/models/Product.php';
require_once ROOT_PATH . '/api/models/Order.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Initialize models
$user = new User($db);
$product = new Product($db);
$order = new Order($db);

// Get stats
$total_users = $user->getCount();
$total_products = $product->getCount();
$total_orders = $order->getCount() ?? 0;

// Fetch total revenue from the database
try {
    // Try a more comprehensive revenue query that includes delivered, shipped and processing orders with the correct payment status
    $revenueQuery = "SELECT COALESCE(SUM(total_price), 0) as total_revenue 
                     FROM orders 
                     WHERE (status IN ('delivered', 'shipped', 'processing') 
                            OR payment_status = 'completed')
                     AND status != 'canceled'";
    $stmt = $db->prepare($revenueQuery);
    $stmt->execute();
    $revenueData = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalRevenue = $revenueData['total_revenue'];
    
    // If we still have zero, let's just sum up all orders that are not canceled
    if ($totalRevenue <= 0) {
        $fallbackQuery = "SELECT COALESCE(SUM(total_price), 0) as total_revenue FROM orders WHERE status != 'canceled'";
        $stmt = $db->prepare($fallbackQuery);
        $stmt->execute();
        $fallbackData = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalRevenue = $fallbackData['total_revenue'];
    }
} catch (PDOException $e) {
    $totalRevenue = 0;
    $errorMessage = 'Database error: ' . $e->getMessage();
}

// Get recent orders directly
$recent_orders = [];
$recent_orders_query = "SELECT o.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                    u.email as customer_email  
                  FROM orders o
                  LEFT JOIN users u ON o.user_id = u.id
                  ORDER BY o.created_at DESC 
                  LIMIT 5";
$stmt = $db->prepare($recent_orders_query);
$stmt->execute();
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $recent_orders[] = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'customer_name' => $row['customer_name'],
        'total_amount' => $row['total_price'],
        'status' => $row['status'],
        'created_at' => $row['created_at']
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Terral Online Production System</title>
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
            --warning: #f39c12;
            --danger: #e74c3c;
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
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--text-dark);
            color: var(--white);
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--white);
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            transition: var(--transition);
        }
        
        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-menu a.active {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info .user-name {
            margin-right: 15px;
        }
        
        .user-info .logout-btn {
            background-color: var(--danger);
            color: var(--white);
            padding: 8px 15px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .user-info .logout-btn:hover {
            background-color: #c0392b;
        }
        
        /* Dashboard Cards */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: var(--white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-right: 20px;
            color: var(--primary);
        }
        
        .stat-card .stat-info {
            flex: 1;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-title {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        /* Recent Orders */
        .recent-orders {
            background-color: var(--white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .orders-list {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-list th,
        .orders-list td {
            padding: 12px 15px;
            text-align: left;
        }
        
        .orders-list th {
            background-color: var(--gray-light);
            font-weight: 500;
        }
        
        .orders-list tr:nth-child(even) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #FEF9C3;
            color: #854D0E;
        }
        
        .status-processing {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        
        .status-shipped {
            background-color: #DCFCE7;
            color: #166534;
        }
        
        .status-delivered {
            background-color: var(--success);
            color: var(--white);
        }
        
        .status-cancelled {
            background-color: var(--danger);
            color: var(--white);
        }
        
        .view-all {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--primary);
            font-weight: 500;
            transition: var(--transition);
        }
        
        .view-all:hover {
            color: var(--primary-dark);
        }
        
        /* Action Buttons */
        .action-btn {
            padding: 6px 12px;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .btn-view {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-view:hover {
            background-color: var(--primary-dark);
        }
        
        /* Quick Links */
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .quick-link-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
            text-align: center;
            transition: var(--transition);
        }
        
        .quick-link-card:hover {
            transform: translateY(-5px);
        }
        
        .quick-link-card i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .quick-link-card h3 {
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        
        .quick-link-card p {
            font-size: 0.9rem;
            color: var(--text-light);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-logo">
                TERRAL ADMIN
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php" class="active">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="products.php">
                        <i class="fas fa-box"></i> Products
                    </a>
                </li>
                <li>
                    <a href="orders.php">
                        <i class="fas fa-shopping-cart"></i> Orders
                    </a>
                </li>
                <li>
                    <a href="customers.php">
                        <i class="fas fa-users"></i> Customers
                    </a>
                </li>
                <li>
                    <a href="categories.php">
                        <i class="fas fa-tags"></i> Categories
                    </a>
                </li>
                <li>
                    <a href="reports.php">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </li>
                <li>
                    <a href="settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li>
                    <a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
                <div class="user-info">
                    <span class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $total_users; ?></div>
                        <div class="stat-title">Total Customers</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-box"></i>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $total_products; ?></div>
                        <div class="stat-title">Total Products</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-shopping-cart"></i>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $total_orders; ?></div>
                        <div class="stat-title">Total Orders</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="stat-info">
                        <div class="stat-number">KSh <?php echo number_format($totalRevenue, 2); ?></div>
                        <div class="stat-title">Total Revenue</div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="recent-orders">
                <h2 class="section-title">Recent Orders</h2>
                <?php if (empty($recent_orders)): ?>
                    <p>No orders found.</p>
                <?php else: ?>
                    <table class="orders-list">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="status status-<?php echo strtolower($order['status']); ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="order-details.php?id=<?php echo $order['id']; ?>" class="action-btn btn-view">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="orders.php" class="view-all">View All Orders</a>
                <?php endif; ?>
            </div>
            
            <!-- Quick Links -->
            <div class="quick-links">
                <div class="quick-link-card">
                    <i class="fas fa-plus"></i>
                    <h3>Add Product</h3>
                    <p>Add new products to your inventory</p>
                    <a href="add-product.php" class="action-btn btn-view">Add New</a>
                </div>
                
                <div class="quick-link-card">
                    <i class="fas fa-tag"></i>
                    <h3>Add Category</h3>
                    <p>Create new product categories</p>
                    <a href="add-category.php" class="action-btn btn-view">Add New</a>
                </div>
                
                <div class="quick-link-card">
                    <i class="fas fa-file-alt"></i>
                    <h3>Sales Report</h3>
                    <p>View and download sales reports</p>
                    <a href="reports.php" class="action-btn btn-view">View</a>
                </div>
                
                <div class="quick-link-card">
                    <i class="fas fa-cog"></i>
                    <h3>Settings</h3>
                    <p>Configure store settings</p>
                    <a href="settings.php" class="action-btn btn-view">Configure</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Order Details <span id="orderNumber"></span></h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div id="orderDetails" class="order-details-container">
                    <div class="loader">Loading...</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- CSS for the modal -->
    <style>
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border: 1px solid #888;
            width: 90%;
            max-width: 1200px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 5px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #333;
        }
        
        .loader {
            text-align: center;
            padding: 30px;
            font-size: 18px;
            color: #666;
        }
        
        /* Order details styles */
        .order-summary-section,
        .payment-section,
        .customer-section,
        .items-section,
        .actions-section {
            margin-bottom: 25px;
            background-color: white;
            border-radius: 5px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .section-header {
            font-size: 18px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        
        .order-details-container {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 20px;
        }
        
        .main-column {
            display: flex;
            flex-direction: column;
        }
        
        .side-column {
            display: flex;
            flex-direction: column;
        }
        
        .order-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .items-table th, 
        .items-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .items-table th {
            background-color: #f8f9fa;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .item-details {
            display: flex;
            align-items: center;
        }
        
        .item-details img {
            margin-right: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background-color: #007bff;
            color: white;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-processing {
            background-color: #17a2b8;
            color: white;
        }
        
        .badge-shipped {
            background-color: #007bff;
            color: white;
        }
        
        .badge-delivered {
            background-color: #28a745;
            color: white;
        }
        
        .badge-canceled {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }
    </style>
    
    <!-- JavaScript for handling the modal -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal elements
            const modal = document.getElementById('orderModal');
            const orderNumber = document.getElementById('orderNumber');
            const orderDetails = document.getElementById('orderDetails');
            const closeBtn = document.getElementsByClassName('close')[0];
            
            // Get all "View" buttons
            const viewButtons = document.querySelectorAll('.view-order-btn');
            
            // Add click event to all view buttons
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-id');
                    openOrderModal(orderId);
                });
            });
            
            // Close modal when clicking the X
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            // Close modal when clicking outside the modal
            window.addEventListener('click', function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
            
            // Function to open modal and load order details
            function openOrderModal(orderId) {
                // Show modal
                modal.style.display = 'block';
                
                // Set loading state
                orderNumber.textContent = '#' + orderId;
                orderDetails.innerHTML = '<div class="loader">Loading order details...</div>';
                
                // Fetch order details using AJAX
                fetch('ajax-order-details.php?id=' + orderId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayOrderDetails(data.data);
                        } else {
                            orderDetails.innerHTML = '<div class="error">Error: ' + (data.error || 'Could not load order details') + '</div>';
                        }
                    })
                    .catch(error => {
                        orderDetails.innerHTML = '<div class="error">Error loading order details. Please try again.</div>';
                        console.error('Error:', error);
                    });
            }
            
            // Function to display order details in the modal
            function displayOrderDetails(order) {
                // Calculate total
                const calculateTotal = () => {
                    let total = 0;
                    if (order.items && order.items.length > 0) {
                        total = order.items.reduce((sum, item) => sum + (parseFloat(item.price) * parseInt(item.quantity)), 0);
                    }
                    return total.toFixed(2);
                };
                
                // Format currency
                const formatCurrency = (amount) => {
                    return 'KSh ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                };
                
                // Get status badge class
                const getStatusBadgeClass = (status) => {
                    switch (status) {
                        case 'pending': return 'badge-pending';
                        case 'processing': return 'badge-processing';
                        case 'shipped': return 'badge-shipped';
                        case 'delivered': return 'badge-delivered';
                        case 'canceled': return 'badge-canceled';
                        default: return 'badge-secondary';
                    }
                };
                
                // Get payment status badge class
                const getPaymentStatusBadgeClass = (status) => {
                    switch (status) {
                        case 'paid': return 'badge-success';
                        case 'pending': return 'badge-warning';
                        case 'failed': return 'badge-danger';
                        case 'refunded': return 'badge-info';
                        case 'canceled': return 'badge-secondary';
                        default: return 'badge-secondary';
                    }
                };
                
                // Format date
                const formatDate = (dateString) => {
                    if (!dateString) return 'N/A';
                    const date = new Date(dateString);
                    return date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                };
                
                // Build HTML for order details
                let html = `
                    <div class="main-column">
                        <!-- Order Summary -->
                        <div class="order-summary-section">
                            <h3 class="section-header">Order Summary</h3>
                            <div class="order-summary">
                                <div>
                                    <p><strong>Order ID:</strong> #${order.id}</p>
                                    <p><strong>Date:</strong> ${formatDate(order.created_at)}</p>
                                    <p>
                                        <strong>Status:</strong> 
                                        <span class="status-badge ${getStatusBadgeClass(order.status)}">
                                            ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                                        </span>
                                    </p>
                                </div>
                                <div>
                                    <p><strong>Subtotal:</strong> ${formatCurrency(order.subtotal || calculateTotal())}</p>
                                    <p><strong>Shipping:</strong> ${formatCurrency(order.shipping_cost || 0)}</p>
                                    <p><strong>Total:</strong> <span class="total-price">${formatCurrency(order.calculated_total || order.total_price || calculateTotal())}</span></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Information -->
                        <div class="payment-section">
                            <h3 class="section-header">Payment Information</h3>
                            <div class="order-summary">
                                <div>
                                    <p><strong>Payment Method:</strong> ${order.payment_method || 'N/A'}</p>
                                    <p>
                                        <strong>Payment Status:</strong>
                                        <span class="status-badge ${getPaymentStatusBadgeClass(order.payment_status)}">
                                            ${order.payment_status.charAt(0).toUpperCase() + order.payment_status.slice(1)}
                                        </span>
                                    </p>
                                </div>
                                <div>
                                    <p><strong>Transaction ID:</strong> ${order.transaction_id || 'N/A'}</p>
                                    <p><strong>Paid Date:</strong> ${order.paid_at ? formatDate(order.paid_at) : 'N/A'}</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Items -->
                        <div class="items-section">
                            <h3 class="section-header">Ordered Items</h3>
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                // Add order items
                if (order.items && order.items.length > 0) {
                    order.items.forEach(item => {
                        html += `
                            <tr>
                                <td>
                                    <div class="item-details">
                                        <img src="${item.image_url}" alt="${item.name}" class="item-image">
                                        <div>
                                            <p class="item-name">${item.name}</p>
                                            ${item.product_code ? `<small class="item-code">Code: ${item.product_code}</small>` : ''}
                                        </div>
                                    </div>
                                </td>
                                <td>${item.quantity}</td>
                                <td>${formatCurrency(item.price)}</td>
                                <td>${formatCurrency(item.price * item.quantity)}</td>
                            </tr>
                        `;
                    });
                } else {
                    html += `
                        <tr>
                            <td colspan="4" class="text-center">No items found for this order.</td>
                        </tr>
                    `;
                }
                
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="side-column">
                        <!-- Update Order Status -->
                        <div class="actions-section">
                            <h3 class="section-header">Update Status</h3>
                            <form id="updateStatusForm">
                                <input type="hidden" name="order_id" value="${order.id}">
                                <div class="form-group">
                                    <label for="status">Order Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                                        <option value="processing" ${order.status === 'processing' ? 'selected' : ''}>Processing</option>
                                        <option value="shipped" ${order.status === 'shipped' ? 'selected' : ''}>Shipped</option>
                                        <option value="delivered" ${order.status === 'delivered' ? 'selected' : ''}>Delivered</option>
                                        <option value="canceled" ${order.status === 'canceled' ? 'selected' : ''}>Canceled</option>
                                    </select>
                                </div>
                                
                                <button type="button" class="btn btn-primary btn-block" id="updateStatusBtn">
                                    Update Status
                                </button>
                            </form>
                        </div>
                        
                        <!-- Update Payment Status -->
                        <div class="actions-section">
                            <h3 class="section-header">Update Payment Status</h3>
                            <form id="updatePaymentForm">
                                <input type="hidden" name="order_id" value="${order.id}">
                                <div class="form-group">
                                    <label for="payment_status">Payment Status</label>
                                    <select class="form-control" id="payment_status" name="payment_status">
                                        <option value="pending" ${order.payment_status === 'pending' ? 'selected' : ''}>Pending</option>
                                        <option value="paid" ${order.payment_status === 'paid' ? 'selected' : ''}>Paid</option>
                                        <option value="failed" ${order.payment_status === 'failed' ? 'selected' : ''}>Failed</option>
                                        <option value="refunded" ${order.payment_status === 'refunded' ? 'selected' : ''}>Refunded</option>
                                        <option value="canceled" ${order.payment_status === 'canceled' ? 'selected' : ''}>Canceled</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="transaction_id">Transaction ID (Optional)</label>
                                    <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                           value="${order.transaction_id || ''}" 
                                           placeholder="Enter transaction ID or leave empty to generate">
                                    <small class="form-text text-muted">If left empty, a reference will be generated automatically when payment is marked as paid.</small>
                                </div>
                                
                                <button type="button" class="btn btn-success btn-block" id="updatePaymentBtn">
                                    Update Payment Status
                                </button>
                            </form>
                        </div>
                        
                        <!-- Customer Information -->
                        <div class="customer-section">
                            <h3 class="section-header">Customer Information</h3>
                            ${order.customer ? `
                                <p><strong>Name:</strong> ${order.customer.first_name} ${order.customer.last_name}</p>
                                <p><strong>Email:</strong> ${order.customer.email}</p>
                                <p><strong>Phone:</strong> ${order.customer.phone || 'N/A'}</p>
                            ` : '<p>Customer information not available.</p>'}
                        </div>
                        
                        <!-- Shipping Information -->
                        <div class="actions-section">
                            <h3 class="section-header">Shipping Information</h3>
                            <p><strong>Shipping Address:</strong></p>
                            <address>
                                ${order.shipping_address || 'N/A'}
                            </address>
                            <p><strong>Shipping Method:</strong> ${order.shipping_method || 'Standard Shipping'}</p>
                        </div>
                        
                        <!-- Actions -->
                        <div class="actions-section">
                            <h3 class="section-header">Actions</h3>
                            <a href="invoice.php?order_id=${order.id}" class="btn btn-block" style="background-color: #6c757d; margin-bottom: 10px;" target="_blank">
                                Generate Invoice
                            </a>
                            ${order.customer ? `
                                <a href="mailto:${order.customer.email}" class="btn btn-block" style="background-color: #17a2b8;">
                                    Email Customer
                                </a>
                            ` : ''}
                        </div>
                    </div>
                `;
                
                // Update the modal content
                orderDetails.innerHTML = html;
                
                // Add event listeners for the update buttons
                document.getElementById('updateStatusBtn').addEventListener('click', function() {
                    updateOrderStatus(order.id);
                });
                
                document.getElementById('updatePaymentBtn').addEventListener('click', function() {
                    updatePaymentStatus(order.id);
                });
            }
            
            // Function to update order status
            function updateOrderStatus(orderId) {
                const form = document.getElementById('updateStatusForm');
                const formData = new FormData(form);
                
                // Show loading state
                const button = document.getElementById('updateStatusBtn');
                const originalText = button.textContent;
                button.textContent = 'Updating...';
                button.disabled = true;
                
                // Send AJAX request
                fetch('ajax-update-order-status.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Refresh the order details
                        alert('Order status updated successfully!');
                        openOrderModal(orderId);
                    } else {
                        alert('Error: ' + (data.error || 'Could not update order status'));
                    }
                })
                .catch(error => {
                    alert('Error updating order status. Please try again.');
                    console.error('Error:', error);
                })
                .finally(() => {
                    // Reset button state
                    button.textContent = originalText;
                    button.disabled = false;
                });
            }
            
            // Function to update payment status
            function updatePaymentStatus(orderId) {
                const form = document.getElementById('updatePaymentForm');
                const formData = new FormData(form);
                
                // Show loading state
                const button = document.getElementById('updatePaymentBtn');
                const originalText = button.textContent;
                button.textContent = 'Updating...';
                button.disabled = true;
                
                // Send AJAX request
                fetch('ajax-update-payment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Refresh the order details
                        alert('Payment status updated successfully!');
                        openOrderModal(orderId);
                    } else {
                        alert('Error: ' + (data.error || 'Could not update payment status'));
                    }
                })
                .catch(error => {
                    alert('Error updating payment status. Please try again.');
                    console.error('Error:', error);
                })
                .finally(() => {
                    // Reset button state
                    button.textContent = originalText;
                    button.disabled = false;
                });
            }
        });
    </script>
</body>
</html> 