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

// Get basic stats for summary cards
$total_users = $user->getCount();
$total_products = $product->getCount();
$total_orders = $order->getCount() ?? 0;

// Get total revenue
try {
    $revenueQuery = "SELECT COALESCE(SUM(total_price), 0) as total_revenue 
                     FROM orders 
                     WHERE (status IN ('delivered', 'shipped', 'processing') 
                            OR payment_status = 'completed')
                     AND status != 'canceled'";
    $stmt = $db->prepare($revenueQuery);
    $stmt->execute();
    $revenueData = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalRevenue = $revenueData['total_revenue'];
    
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

// Get top selling products
$topProducts = [];
try {
    $query = "SELECT p.id, p.name, p.image, SUM(oi.quantity) as quantity_sold, SUM(oi.quantity * oi.price) as total_sales 
              FROM order_items oi 
              JOIN orders o ON oi.order_id = o.id 
              JOIN products p ON oi.product_id = p.id 
              WHERE o.status != 'canceled' 
              GROUP BY p.id, p.name, p.image 
              ORDER BY quantity_sold DESC 
              LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add image URLs to products
    $base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    $base_url .= $_SERVER['HTTP_HOST'];
    
    foreach ($topProducts as &$product) {
        $product['image_url'] = !empty($product['image']) 
            ? $base_url . '/Terral/api/uploads/products/' . $product['image'] 
            : $base_url . '/Terral/assets/images/placeholder.jpg';
    }
} catch (PDOException $e) {
    $errorMessage = 'Database error: ' . $e->getMessage();
}

// Get sales data for the last 6 months for chart
$salesData = [];
$labels = [];
$values = [];

try {
    $query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
              SUM(total_price) as total_sales 
              FROM orders 
              WHERE status != 'canceled' 
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
              GROUP BY month 
              ORDER BY month ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($salesData as $data) {
        $date = new DateTime($data['month'] . '-01');
        $labels[] = $date->format('M Y');
        $values[] = (float)$data['total_sales'];
    }
} catch (PDOException $e) {
    $errorMessage = 'Database error: ' . $e->getMessage();
}

// Get order status distribution for pie chart
$orderStatusData = [];
$statusLabels = [];
$statusValues = [];
$statusColors = [
    'pending' => '#FEF9C3',
    'processing' => '#DBEAFE',
    'shipped' => '#DCFCE7',
    'delivered' => '#2ecc71',
    'canceled' => '#e74c3c'
];

try {
    $query = "SELECT status, COUNT(*) as count 
              FROM orders 
              GROUP BY status";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $orderStatusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($orderStatusData as $data) {
        $statusLabels[] = ucfirst($data['status']);
        $statusValues[] = (int)$data['count'];
    }
} catch (PDOException $e) {
    $errorMessage = 'Database error: ' . $e->getMessage();
}

// Get top customers
$topCustomers = [];
try {
    $query = "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email, 
              COUNT(o.id) as order_count, SUM(o.total_price) as total_spent 
              FROM users u 
              JOIN orders o ON u.id = o.user_id 
              WHERE o.status != 'canceled' 
              GROUP BY u.id, customer_name, u.email 
              ORDER BY total_spent DESC 
              LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = 'Database error: ' . $e->getMessage();
}

// Get new customers by month
$newCustomersData = [];
$customerLabels = [];
$customerValues = [];

try {
    $query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
              FROM users 
              WHERE role = 'customer' 
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
              GROUP BY month 
              ORDER BY month ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $newCustomersData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($newCustomersData as $data) {
        $date = new DateTime($data['month'] . '-01');
        $customerLabels[] = $date->format('M Y');
        $customerValues[] = (int)$data['count'];
    }
} catch (PDOException $e) {
    $errorMessage = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - Terral Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
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
            z-index: 10;
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
            display: flex;
            align-items: center;
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
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
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
        
        /* Chart Containers */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1200px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .chart-container {
            background-color: var(--white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            max-height: 450px;
            overflow: hidden;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .chart-actions {
            display: flex;
            gap: 10px;
        }
        
        .chart-filter {
            padding: 8px 12px;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            background-color: var(--white);
            font-family: inherit;
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .chart-canvas {
            width: 100%;
            height: 300px;
            max-height: 300px;
        }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .data-table th {
            background-color: var(--gray-light);
            font-weight: 500;
        }
        
        .data-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .data-table .product-image {
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius);
            object-fit: cover;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                padding: 10px 0;
            }
            
            .sidebar-logo {
                font-size: 1.2rem;
                padding: 15px 5px;
            }
            
            .sidebar-menu a {
                padding: 12px;
                justify-content: center;
            }
            
            .sidebar-menu i {
                margin-right: 0;
            }
            
            .sidebar-menu span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .dashboard-stats {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
        
        /* Date Range Picker */
        .date-range {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .date-input {
            padding: 8px 12px;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-family: inherit;
        }
        
        .apply-btn {
            background-color: var(--primary);
            color: var(--white);
            border: none;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .apply-btn:hover {
            background-color: var(--primary-dark);
        }
        
        /* Export Button */
        .export-btn {
            background-color: var(--success);
            color: var(--white);
            border: none;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .export-btn:hover {
            background-color: #27ae60;
        }
        
        /* Tabs */
        .report-tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-light);
            margin-bottom: 30px;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .tab-btn.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Loader */
        .loader-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
        }
        
        .loader {
            border: 5px solid var(--gray-light);
            border-top: 5px solid var(--primary);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Export Modal Styles */
        .export-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }
        
        .export-modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
        }
        
        .export-modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .export-modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .export-modal-close {
            font-size: 24px;
            cursor: pointer;
            color: white;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        
        .export-modal-close:hover {
            opacity: 1;
        }
        
        .export-modal-body {
            padding: 25px;
        }
        
        .export-options h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .export-format-options,
        .export-type-options {
            margin-bottom: 25px;
        }
        
        .export-option {
            display: block;
            margin-bottom: 12px;
            cursor: pointer;
            position: relative;
        }
        
        .export-option input[type="radio"] {
            display: none;
        }
        
        .export-option-label {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .export-option-label i {
            margin-right: 10px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        .export-option input[type="radio"]:checked + .export-option-label {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .export-option:hover .export-option-label {
            border-color: #667eea;
            transform: translateY(-1px);
        }
        
        .date-range-export {
            margin-top: 20px;
        }
        
        .date-inputs {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .date-inputs label {
            display: flex;
            flex-direction: column;
            font-weight: 500;
            color: #555;
        }
        
        .date-inputs input[type="date"] {
            margin-top: 5px;
            padding: 8px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
        }
        
        .date-inputs input[type="date"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .export-modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
        }
        
        .btn-cancel,
        .btn-export {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }
        
        .btn-export {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-export:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-export:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        @media (max-width: 768px) {
            .export-modal-content {
                width: 95%;
                margin: 20px;
            }
            
            .date-inputs {
                flex-direction: column;
            }
            
            .export-modal-footer {
                flex-direction: column;
            }
            
            .btn-cancel,
            .btn-export {
                width: 100%;
            }
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
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="products.php">
                        <i class="fas fa-box"></i> <span>Products</span>
                    </a>
                </li>
                <li>
                    <a href="orders.php">
                        <i class="fas fa-shopping-cart"></i> <span>Orders</span>
                    </a>
                </li>
                <li>
                    <a href="customers.php">
                        <i class="fas fa-users"></i> <span>Customers</span>
                    </a>
                </li>
                <li>
                    <a href="categories.php">
                        <i class="fas fa-tags"></i> <span>Categories</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="active">
                        <i class="fas fa-chart-bar"></i> <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php">
                        <i class="fas fa-cog"></i> <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Analytics & Reports</h1>
                <div class="user-info">
                    <span class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Admin'); ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Report Tabs -->
            <div class="report-tabs">
                <button class="tab-btn active" data-tab="overview">Overview</button>
                <button class="tab-btn" data-tab="sales">Sales Analytics</button>
                <button class="tab-btn" data-tab="products">Product Performance</button>
                <button class="tab-btn" data-tab="customers">Customer Insights</button>
            </div>
            
            <!-- Date Range Picker -->
            <div class="date-range">
                <label for="start-date">From:</label>
                <input type="date" id="start-date" class="date-input" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                
                <label for="end-date">To:</label>
                <input type="date" id="end-date" class="date-input" value="<?php echo date('Y-m-d'); ?>">
                
                <button class="apply-btn" id="apply-date-range">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
                
                <button class="export-btn" id="export-report">
                    <i class="fas fa-download"></i> Export Report
                </button>
            </div>
            
            <!-- Overview Tab -->
            <div class="tab-content active" id="overview-tab">
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
                
                <!-- Charts Grid -->
                <div class="chart-grid">
                    <!-- Sales Trend Chart -->
                    <div class="chart-container">
                        <div class="chart-header">
                            <h2 class="chart-title">Sales Trend</h2>
                            <div class="chart-actions">
                                <select class="chart-filter" id="sales-period">
                                    <option value="6">Last 6 Months</option>
                                    <option value="3">Last 3 Months</option>
                                    <option value="12">Last 12 Months</option>
                                </select>
                            </div>
                        </div>
                        <canvas id="salesChart" class="chart-canvas"></canvas>
                    </div>
                    
                    <!-- Order Status Chart -->
                    <div class="chart-container">
                        <div class="chart-header">
                            <h2 class="chart-title">Order Status Distribution</h2>
                        </div>
                        <canvas id="orderStatusChart" class="chart-canvas"></canvas>
                    </div>
                </div>
                
                <!-- Top Products -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h2 class="chart-title">Top Selling Products</h2>
                    </div>
                    <?php if (empty($topProducts)): ?>
                        <p>No product data available.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity Sold</th>
                                    <th>Total Sales</th>
                                    <th>Average Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topProducts as $product): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center;">
                                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                                <span style="margin-left: 10px;"><?php echo htmlspecialchars($product['name']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo $product['quantity_sold']; ?></td>
                                        <td>KSh <?php echo number_format($product['total_sales'], 2); ?></td>
                                        <td>KSh <?php echo number_format($product['total_sales'] / $product['quantity_sold'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Customer Growth Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h2 class="chart-title">New Customer Growth</h2>
                    </div>
                    <canvas id="customerGrowthChart" class="chart-canvas"></canvas>
                </div>
            </div>
            
            <!-- Sales Analytics Tab -->
            <div class="tab-content" id="sales-tab">
                <div class="chart-container">
                    <div class="chart-header">
                        <h2 class="chart-title">Detailed Sales Analysis</h2>
                    </div>
                    <div id="detailed-sales-chart-container">
                        <canvas id="detailedSalesChart" class="chart-canvas"></canvas>
                    </div>
                </div>
                
                <div class="chart-grid">
                    <!-- Revenue by Category -->
                    <div class="chart-container">
                        <div class="chart-header">
                            <h2 class="chart-title">Revenue by Category</h2>
                        </div>
                        <div id="category-revenue-container">
                            <div class="loader-container">
                                <div class="loader"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Methods -->
                    <div class="chart-container">
                        <div class="chart-header">
                            <h2 class="chart-title">Payment Methods</h2>
                        </div>
                        <div id="payment-methods-container">
                            <div class="loader-container">
                                <div class="loader"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Product Performance Tab -->
            <div class="tab-content" id="products-tab">
                <div class="chart-container">
                    <div class="chart-header">
                        <h2 class="chart-title">Product Performance Analysis</h2>
                    </div>
                    <div id="product-performance-container">
                        <div class="loader-container">
                            <div class="loader"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Low Stock Products -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h2 class="chart-title">Low Stock Products</h2>
                    </div>
                    <div id="low-stock-container">
                        <div class="loader-container">
                            <div class="loader"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Insights Tab -->
            <div class="tab-content" id="customers-tab">
                <!-- Top Customers -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h2 class="chart-title">Top Customers</h2>
                    </div>
                    <?php if (empty($topCustomers)): ?>
                        <p>No customer data available.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th>Orders</th>
                                    <th>Total Spent</th>
                                    <th>Avg. Order Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topCustomers as $customer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td><?php echo $customer['order_count']; ?></td>
                                        <td>KSh <?php echo number_format($customer['total_spent'], 2); ?></td>
                                        <td>KSh <?php echo number_format($customer['total_spent'] / $customer['order_count'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Customer Retention -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h2 class="chart-title">Customer Retention</h2>
                    </div>
                    <div id="customer-retention-container">
                        <div class="loader-container">
                            <div class="loader"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked button and corresponding content
                    button.classList.add('active');
                    const tabId = button.getAttribute('data-tab');
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                });
            });
            
            // Sales Chart
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            const salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        label: 'Sales (KSh)',
                        data: <?php echo json_encode($values); ?>,
                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        pointBackgroundColor: 'rgba(52, 152, 219, 1)',
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'KSh ' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'KSh ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Order Status Chart
            const statusCtx = document.getElementById('orderStatusChart').getContext('2d');
            const orderStatusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($statusLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($statusValues); ?>,
                        backgroundColor: [
                            '#FEF9C3', // pending
                            '#DBEAFE', // processing
                            '#DCFCE7', // shipped
                            '#2ecc71', // delivered
                            '#e74c3c'  // canceled
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Customer Growth Chart
            const customerCtx = document.getElementById('customerGrowthChart').getContext('2d');
            const customerGrowthChart = new Chart(customerCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($customerLabels); ?>,
                    datasets: [{
                        label: 'New Customers',
                        data: <?php echo json_encode($customerValues); ?>,
                        backgroundColor: 'rgba(46, 204, 113, 0.2)',
                        borderColor: 'rgba(46, 204, 113, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
            
            // Date range filter functionality
            document.getElementById('apply-date-range').addEventListener('click', function() {
                const startDate = document.getElementById('start-date').value;
                const endDate = document.getElementById('end-date').value;
                
                // Show loading state
                document.querySelectorAll('.chart-canvas').forEach(canvas => {
                    canvas.style.opacity = '0.5';
                });
                
                // In a real implementation, you would fetch new data based on the date range
                // and update the charts. For now, we'll just simulate a loading state.
                setTimeout(() => {
                    document.querySelectorAll('.chart-canvas').forEach(canvas => {
                        canvas.style.opacity = '1';
                    });
                    alert('Date range filter applied: ' + startDate + ' to ' + endDate);
                }, 1000);
            });
            
            // Export report functionality
            document.getElementById('export-report').addEventListener('click', function() {
                window.showExportModal();
            });
            
            // Export modal functionality - Make functions globally accessible
            window.showExportModal = function() {
                const modal = document.createElement('div');
                modal.className = 'export-modal';
                modal.innerHTML = `
                    <div class="export-modal-content">
                        <div class="export-modal-header">
                            <h3>Export Report</h3>
                            <span class="export-modal-close">&times;</span>
                        </div>
                        <div class="export-modal-body">
                            <div class="export-options">
                                <h4>Select Export Format:</h4>
                                <div class="export-format-options">
                                    <label class="export-option">
                                        <input type="radio" name="export-format" value="csv" checked>
                                        <span class="export-option-label">
                                            <i class="fas fa-file-csv"></i>
                                            CSV (Excel Compatible)
                                        </span>
                                    </label>
                                    <label class="export-option">
                                        <input type="radio" name="export-format" value="pdf">
                                        <span class="export-option-label">
                                            <i class="fas fa-file-pdf"></i>
                                            PDF Report
                                        </span>
                                    </label>
                                </div>
                                
                                <h4>Select Report Type:</h4>
                                <div class="export-type-options">
                                    <label class="export-option">
                                        <input type="radio" name="export-type" value="overview" checked>
                                        <span class="export-option-label">
                                            <i class="fas fa-chart-line"></i>
                                            Overview Report
                                        </span>
                                    </label>
                                    <label class="export-option">
                                        <input type="radio" name="export-type" value="sales">
                                        <span class="export-option-label">
                                            <i class="fas fa-dollar-sign"></i>
                                            Sales Report
                                        </span>
                                    </label>
                                    <label class="export-option">
                                        <input type="radio" name="export-type" value="products">
                                        <span class="export-option-label">
                                            <i class="fas fa-box"></i>
                                            Products Report
                                        </span>
                                    </label>
                                    <label class="export-option">
                                        <input type="radio" name="export-type" value="customers">
                                        <span class="export-option-label">
                                            <i class="fas fa-users"></i>
                                            Customers Report
                                        </span>
                                    </label>
                                </div>
                                
                                <div class="date-range-export">
                                    <h4>Date Range:</h4>
                                    <div class="date-inputs">
                                        <label>From: <input type="date" id="export-start-date" value="${document.getElementById('start-date').value}"></label>
                                        <label>To: <input type="date" id="export-end-date" value="${document.getElementById('end-date').value}"></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="export-modal-footer">
                            <button class="btn-cancel" onclick="closeExportModal()">Cancel</button>
                            <button class="btn-export" onclick="processExport()">Export Report</button>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                
                // Close modal when clicking outside
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeExportModal();
                    }
                });
                
                // Close modal when clicking X
                modal.querySelector('.export-modal-close').addEventListener('click', closeExportModal);
            };
            
            window.closeExportModal = function() {
                const modal = document.querySelector('.export-modal');
                if (modal) {
                    modal.remove();
                }
            };
            
            window.processExport = function() {
                const format = document.querySelector('input[name="export-format"]:checked').value;
                const type = document.querySelector('input[name="export-type"]:checked').value;
                const startDate = document.getElementById('export-start-date').value;
                const endDate = document.getElementById('export-end-date').value;
                
                // Show loading state
                const exportBtn = document.querySelector('.btn-export');
                const originalText = exportBtn.textContent;
                exportBtn.textContent = 'Exporting...';
                exportBtn.disabled = true;
                
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'export-report.php';
                form.target = '_blank';
                
                const inputs = [
                    { name: 'export_format', value: format },
                    { name: 'export_type', value: type },
                    { name: 'start_date', value: startDate },
                    { name: 'end_date', value: endDate }
                ];
                
                inputs.forEach(input => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = input.name;
                    hiddenInput.value = input.value;
                    form.appendChild(hiddenInput);
                });
                
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
                
                // Reset button state
                setTimeout(() => {
                    exportBtn.textContent = originalText;
                    exportBtn.disabled = false;
                    closeExportModal();
                }, 1000);
            };
            
            // Load data for other tabs when they become active
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const tabId = button.getAttribute('data-tab');
                    
                    if (tabId === 'sales' && !window.salesDataLoaded) {
                        // Simulate loading sales data
                        setTimeout(() => {
                            // Initialize Detailed Sales Analysis chart
                            const detailedSalesCtx = document.getElementById('detailedSalesChart').getContext('2d');
                            new Chart(detailedSalesCtx, {
                                type: 'bar',
                                data: {
                                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                                    datasets: [{
                                        label: 'Revenue',
                                        data: [12500, 19200, 15000, 21500, 18300, 24100],
                                        backgroundColor: 'rgba(52, 152, 219, 0.5)',
                                        borderColor: 'rgba(52, 152, 219, 1)',
                                        borderWidth: 1
                                    }, {
                                        label: 'Profit',
                                        data: [5200, 7800, 6100, 9200, 7500, 10300],
                                        backgroundColor: 'rgba(46, 204, 113, 0.5)',
                                        borderColor: 'rgba(46, 204, 113, 1)',
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: true,
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                callback: function(value) {
                                                    return 'KSh ' + value.toLocaleString();
                                                }
                                            }
                                        }
                                    },
                                    plugins: {
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    return context.dataset.label + ': KSh ' + context.raw.toLocaleString();
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                            
                            document.querySelector('#category-revenue-container').innerHTML = '<canvas id="categoryRevenueChart" class="chart-canvas"></canvas>';
                            document.querySelector('#payment-methods-container').innerHTML = '<canvas id="paymentMethodsChart" class="chart-canvas"></canvas>';
                            
                            // Create placeholder charts
                            createCategoryRevenueChart();
                            createPaymentMethodsChart();
                            
                            window.salesDataLoaded = true;
                        }, 1000);
                    }
                    
                    if (tabId === 'products' && !window.productsDataLoaded) {
                        // Simulate loading product data
                        setTimeout(() => {
                            document.querySelector('#product-performance-container').innerHTML = '<canvas id="productPerformanceChart" class="chart-canvas"></canvas>';
                            document.querySelector('#low-stock-container').innerHTML = createLowStockTable();
                            
                            createProductPerformanceChart();
                            
                            window.productsDataLoaded = true;
                        }, 1000);
                    }
                    
                    if (tabId === 'customers' && !window.customersDataLoaded) {
                        // Simulate loading customer data
                        setTimeout(() => {
                            document.querySelector('#customer-retention-container').innerHTML = '<canvas id="customerRetentionChart" class="chart-canvas"></canvas>';
                            
                            createCustomerRetentionChart();
                            
                            window.customersDataLoaded = true;
                        }, 1000);
                    }
                });
            });
            
            // Helper functions to create placeholder charts for other tabs
            function createCategoryRevenueChart() {
                const ctx = document.getElementById('categoryRevenueChart').getContext('2d');
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: ['Electronics', 'Clothing', 'Home & Garden', 'Books', 'Other'],
                        datasets: [{
                            data: [12000, 8000, 5000, 3000, 2000],
                            backgroundColor: [
                                '#3498db',
                                '#2ecc71',
                                '#f39c12',
                                '#9b59b6',
                                '#e74c3c'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'right'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        return `${label}: KSh ${value.toLocaleString()}`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            function createPaymentMethodsChart() {
                const ctx = document.getElementById('paymentMethodsChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['M-Pesa', 'Credit Card', 'Bank Transfer', 'Cash on Delivery'],
                        datasets: [{
                            label: 'Number of Transactions',
                            data: [120, 50, 30, 20],
                            backgroundColor: 'rgba(52, 152, 219, 0.5)',
                            borderColor: 'rgba(52, 152, 219, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }
            
            function createProductPerformanceChart() {
                const ctx = document.getElementById('productPerformanceChart').getContext('2d');
                new Chart(ctx, {
                    type: 'horizontalBar',
                    data: {
                        labels: ['Product A', 'Product B', 'Product C', 'Product D', 'Product E'],
                        datasets: [{
                            label: 'Units Sold',
                            data: [150, 120, 90, 70, 50],
                            backgroundColor: 'rgba(46, 204, 113, 0.5)',
                            borderColor: 'rgba(46, 204, 113, 1)',
                            borderWidth: 1
                        }, {
                            label: 'Revenue (KSh)',
                            data: [15000, 12000, 9000, 7000, 5000],
                            backgroundColor: 'rgba(52, 152, 219, 0.5)',
                            borderColor: 'rgba(52, 152, 219, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: true,
                        scales: {
                            x: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            function createLowStockTable() {
                return `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Current Stock</th>
                                <th>Reorder Level</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Product A</td>
                                <td>5</td>
                                <td>10</td>
                                <td><span class="status status-pending">Low Stock</span></td>
                                <td><a href="#" class="action-btn btn-view">Restock</a></td>
                            </tr>
                            <tr>
                                <td>Product B</td>
                                <td>3</td>
                                <td>15</td>
                                <td><span class="status status-cancelled">Critical</span></td>
                                <td><a href="#" class="action-btn btn-view">Restock</a></td>
                            </tr>
                            <tr>
                                <td>Product C</td>
                                <td>8</td>
                                <td>10</td>
                                <td><span class="status status-pending">Low Stock</span></td>
                                <td><a href="#" class="action-btn btn-view">Restock</a></td>
                            </tr>
                        </tbody>
                    </table>
                `;
            }
            
            function createCustomerRetentionChart() {
                const ctx = document.getElementById('customerRetentionChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'New Customers',
                            data: [50, 60, 45, 70, 65, 80],
                            backgroundColor: 'rgba(52, 152, 219, 0.2)',
                            borderColor: 'rgba(52, 152, 219, 1)',
                            borderWidth: 2,
                            tension: 0.4
                        }, {
                            label: 'Returning Customers',
                            data: [30, 40, 45, 55, 60, 70],
                            backgroundColor: 'rgba(46, 204, 113, 0.2)',
                            borderColor: 'rgba(46, 204, 113, 1)',
                            borderWidth: 2,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>