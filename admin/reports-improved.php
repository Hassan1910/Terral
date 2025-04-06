<?php
/**
 * Admin Reports Page
 * 
 * This page provides analytics and reporting features for administrators.
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Start session for user authentication
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Set a session variable to indicate where to redirect after login
    $_SESSION['redirect_after_login'] = '/Terral2/admin/reports.php';
    
    header('Location: /Terral2/login.php');
    exit;
}

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Get custom date range if provided
$customDateRange = false;
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
    $customDateRange = true;
    $period = 'custom';
} else {
    // Set default time period
    $period = isset($_GET['period']) ? $_GET['period'] : 'month';
    $validPeriods = ['week', 'month', 'quarter', 'year', 'custom'];
    if (!in_array($period, $validPeriods)) {
        $period = 'month';
    }

    // Get date range based on period
    $endDate = date('Y-m-d');
    switch($period) {
        case 'week':
            $startDate = date('Y-m-d', strtotime('-1 week'));
            break;
        case 'month':
            $startDate = date('Y-m-d', strtotime('-1 month'));
            break;
        case 'quarter':
            $startDate = date('Y-m-d', strtotime('-3 months'));
            break;
        case 'year':
            $startDate = date('Y-m-d', strtotime('-1 year'));
            break;
        default:
            $startDate = date('Y-m-d', strtotime('-1 month'));
    }
}

// Initialize report data
$totalSales = 0;
$totalOrders = 0;
$totalCustomers = 0;
$averageOrderValue = 0;
$salesByStatus = [];
$topProducts = [];
$salesByDate = [];
$newCustomers = 0;

// Calculate previous period for comparison
$previousPeriodStart = date('Y-m-d', strtotime($startDate . ' -' . (strtotime($endDate) - strtotime($startDate)) . ' seconds'));
$previousPeriodEnd = date('Y-m-d', strtotime($endDate . ' -' . (strtotime($endDate) - strtotime($startDate)) . ' seconds'));

// Initialize previous period data
$previousTotalSales = 0;
$previousTotalOrders = 0;
$previousNewCustomers = 0;
$previousAverageOrderValue = 0;

try {
    // Get total sales and orders for the period
    $salesQuery = "SELECT 
                        COUNT(*) as order_count, 
                        COALESCE(SUM(total_price), 0) as total_sales 
                    FROM orders 
                    WHERE created_at BETWEEN :start_date AND :end_date";
    $stmt = $conn->prepare($salesQuery);
    $stmt->bindParam(':start_date', $startDate);
    $endDateTime = $endDate . ' 23:59:59';
    $stmt->bindParam(':end_date', $endDateTime);
    $stmt->execute();
    $salesData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalSales = $salesData['total_sales'];
    $totalOrders = $salesData['order_count'];
    
    // Calculate average order value
    $averageOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;
    
    // Get previous period data for comparison
    $prevSalesQuery = "SELECT 
                        COUNT(*) as order_count, 
                        COALESCE(SUM(total_price), 0) as total_sales 
                    FROM orders 
                    WHERE created_at BETWEEN :start_date AND :end_date";
    $stmt = $conn->prepare($prevSalesQuery);
    $stmt->bindParam(':start_date', $previousPeriodStart);
    $prevEndDateTime = $previousPeriodEnd . ' 23:59:59';
    $stmt->bindParam(':end_date', $prevEndDateTime);
    $stmt->execute();
    $prevSalesData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $previousTotalSales = $prevSalesData['total_sales'];
    $previousTotalOrders = $prevSalesData['order_count'];
    $previousAverageOrderValue = $previousTotalOrders > 0 ? $previousTotalSales / $previousTotalOrders : 0;
    
    // Get total customers count
    $customerQuery = "SELECT COUNT(*) as total FROM users WHERE role = 'customer'";
    $stmt = $conn->prepare($customerQuery);
    $stmt->execute();
    $customerData = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalCustomers = $customerData['total'];
    
    // Get new customers count for the period
    $newCustomerQuery = "SELECT COUNT(*) as total FROM users 
                        WHERE role = 'customer' AND created_at BETWEEN :start_date AND :end_date";
    $stmt = $conn->prepare($newCustomerQuery);
    $stmt->bindParam(':start_date', $startDate);
    $endDateTime = $endDate . ' 23:59:59';
    $stmt->bindParam(':end_date', $endDateTime);
    $stmt->execute();
    $newCustomerData = $stmt->fetch(PDO::FETCH_ASSOC);
    $newCustomers = $newCustomerData['total'];
    
    // Get previous period new customers
    $prevNewCustomerQuery = "SELECT COUNT(*) as total FROM users 
                        WHERE role = 'customer' AND created_at BETWEEN :start_date AND :end_date";
    $stmt = $conn->prepare($prevNewCustomerQuery);
    $stmt->bindParam(':start_date', $previousPeriodStart);
    $prevEndDateTime = $previousPeriodEnd . ' 23:59:59';
    $stmt->bindParam(':end_date', $prevEndDateTime);
    $stmt->execute();
    $prevNewCustomerData = $stmt->fetch(PDO::FETCH_ASSOC);
    $previousNewCustomers = $prevNewCustomerData['total'];
    
    // Get orders by status
    $statusQuery = "SELECT status, COUNT(*) as count, SUM(total_price) as total 
                    FROM orders 
                    WHERE created_at BETWEEN :start_date AND :end_date 
                    GROUP BY status";
    $stmt = $conn->prepare($statusQuery);
    $stmt->bindParam(':start_date', $startDate);
    $endDateTime = $endDate . ' 23:59:59';
    $stmt->bindParam(':end_date', $endDateTime);
    $stmt->execute();
    $salesByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get top selling products
    $topProductsQuery = "SELECT p.id, p.name, p.price, p.image_url, SUM(oi.quantity) as total_quantity, 
                        SUM(oi.quantity * oi.price) as total_sales
                        FROM order_items oi
                        JOIN products p ON oi.product_id = p.id
                        JOIN orders o ON oi.order_id = o.id
                        WHERE o.created_at BETWEEN :start_date AND :end_date
                        GROUP BY p.id
                        ORDER BY total_sales DESC
                        LIMIT 5";
    $stmt = $conn->prepare($topProductsQuery);
    $stmt->bindParam(':start_date', $startDate);
    $endDateTime = $endDate . ' 23:59:59';
    $stmt->bindParam(':end_date', $endDateTime);
    $stmt->execute();
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sales by date for the chart
    $dateFormat = ($period == 'year') ? '%Y-%m' : '%Y-%m-%d';
    $salesByDateQuery = "SELECT 
                            DATE_FORMAT(created_at, '$dateFormat') as date, 
                            SUM(total_price) as total,
                            COUNT(*) as orders
                        FROM orders
                        WHERE created_at BETWEEN :start_date AND :end_date
                        GROUP BY DATE_FORMAT(created_at, '$dateFormat')
                        ORDER BY date ASC";
    $stmt = $conn->prepare($salesByDateQuery);
    $stmt->bindParam(':start_date', $startDate);
    $endDateTime = $endDate . ' 23:59:59';
    $stmt->bindParam(':end_date', $endDateTime);
    $stmt->execute();
    $salesByDate = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment methods distribution
    $paymentMethodsQuery = "SELECT payment_method, COUNT(*) as count, SUM(total_price) as total
                           FROM orders
                           WHERE created_at BETWEEN :start_date AND :end_date
                           GROUP BY payment_method";
    $stmt = $conn->prepare($paymentMethodsQuery);
    $stmt->bindParam(':start_date', $startDate);
    $endDateTime = $endDate . ' 23:59:59';
    $stmt->bindParam(':end_date', $endDateTime);
    $stmt->execute();
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
}

// Format sales by date for chart
$labels = [];
$salesData = [];
$ordersData = [];
foreach ($salesByDate as $data) {
    $labels[] = $data['date'];
    $salesData[] = round($data['total'], 2);
    $ordersData[] = $data['orders'];
}

// Helper function for formatting currency
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return 'KSh ' . number_format($amount, 2);
    }
}

// Helper function to calculate growth percentage
function calculateGrowth($current, $previous) {
    if ($previous == 0) return 100;
    return round((($current - $previous) / $previous) * 100, 2);
}

// Calculate growth percentages
$salesGrowth = calculateGrowth($totalSales, $previousTotalSales);
$ordersGrowth = calculateGrowth($totalOrders, $previousTotalOrders);
$customersGrowth = calculateGrowth($newCustomers, $previousNewCustomers);
$aovGrowth = calculateGrowth($averageOrderValue, $previousAverageOrderValue);

// Set period labels for UI
$periodLabels = [
    'week' => 'Last 7 Days',
    'month' => 'Last 30 Days',
    'quarter' => 'Last 3 Months',
    'year' => 'Last 12 Months',
    'custom' => 'Custom Range: ' . date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate))
];

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'pending':
            return 'badge-warning';
        case 'processing':
            return 'badge-info';
        case 'shipped':
            return 'badge-primary';
        case 'delivered':
            return 'badge-success';
        case 'canceled':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}

// Helper function to get payment method icon
function getPaymentMethodIcon($method) {
    switch (strtolower($method)) {
        case 'credit card':
            return 'fa-credit-card';
        case 'paypal':
            return 'fa-paypal';
        case 'mpesa':
            return 'fa-mobile-alt';
        case 'bank transfer':
            return 'fa-university';
        case 'cash on delivery':
            return 'fa-money-bill-wave';
        default:
            return 'fa-money-bill';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Terral Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
            --info: #3498db;
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
            background-color: var(--dark);
            color: var(--white);
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
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
        
        .logout-btn {
            background-color: var(--danger);
            color: var(--white);
            padding: 8px 15px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .logout-btn:hover {
            background-color: #c0392b;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-outline-primary {
            border: 1px solid var(--primary);
            color: var(--primary);
            background-color: transparent;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-outline-secondary {
            border: 1px solid var(--gray);
            color: var(--text-dark);
            background-color: transparent;
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--gray);
        }
        
        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h6 {
            margin: 0;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .stats-card {
            padding: 20px;
            text-align: center;
            height: 100%;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stats-card.sales {
            border-left-color: var(--primary);
        }
        
        .stats-card.orders {
            border-left-color: var(--warning);
        }
        
        .stats-card.customers {
            border-left-color: var(--success);
        }
        
        .stats-card.aov {
            border-left-color: var(--info);
        }
        
        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary);
            background-color: rgba(52, 152, 219, 0.1);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        
        .stats-card.sales .stats-icon {
            color: var(--primary);
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .stats-card.orders .stats-icon {
            color: var(--warning);
            background-color: rgba(243, 156, 18, 0.1);
        }
        
        .stats-card.customers .stats-icon {
            color: var(--success);
            background-color: rgba(46, 204, 113, 0.1);
        }
        
        .stats-card.aov .stats-icon {
            color: var(--info);
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .stats-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: var(--text-light);
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        
        .stats-trend {
            font-size: 0.85rem;
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
        }
        
        .stats-trend i {
            margin-right: 4px;
        }
        
        .trend-up {
            background-color: rgba(46, 204, 113, 0.2);
            color: var(--success);
        }
        
        .trend-down {
            background-color: rgba(231, 76, 60, 0.2);
            color: var(--danger);
        }
        
        .chart-container {
            position: relative;
            margin: auto;
            height: 300px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .table th {
            font-weight: 600;
            text-align: left;
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .period-selector {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .period-selector .btn-group {
            display: flex;
        }
        
        .period-selector .btn-group .btn {
            border-radius: 0;
            margin: 0;
            border: 1px solid var(--primary);
        }
        
        .period-selector .btn-group .btn:first-child {
            border-top-left-radius: var(--border-radius);
            border-bottom-left-radius: var(--border-radius);
        }
        
        .period-selector .btn-group .btn:last-child {
            border-top-right-radius: var(--border-radius);
            border-bottom-right-radius: var(--border-radius);
        }
        
        .period-selector .btn-group .btn.active {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .date-range-picker {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .date-input {
            padding: 8px 12px;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
        }
        
        .badge-status {
            padding: 6px 10px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
            display: inline-block;
        }
        
        .product-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 10px;
        }
        
        .product-name {
            font-weight: 500;
        }
        
        .dropdown-menu {
            min-width: 10rem;
            padding: 0.5rem 0;
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: var(--border-radius);
        }
        
        .dropdown-item {
            padding: 0.5rem 1.5rem;
            transition: var(--transition);
        }
        
        .dropdown-item:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .payment-method-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .payment-method-item:last-child {
            border-bottom: none;
        }
        
        .payment-method-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(52, 152, 219, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--primary);
        }
        
        .payment-method-details {
            flex: 1;
        }
        
        .payment-method-name {
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .payment-method-count {
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        .payment-method-amount {
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .nav-tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-light);
            margin-bottom: 20px;
        }
        
        .nav-tabs .nav-item {
            margin-right: 5px;
        }
        
        .nav-tabs .nav-link {
            padding: 10px 15px;
            border-bottom: 2px solid transparent;
            transition: var(--transition);
            font-weight: 500;
            color: var(--text-light);
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        .progress {
            height: 8px;
            background-color: var(--gray-light);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-bar {
            height: 100%;
            background-color: var(--primary);
        }
        
        @media print {
            .sidebar, .period-selector, .btn, .no-print {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
                break-inside: avoid;
            }
            
            body {
                background-color: white;
            }
        }
        
        @media (max-width: 992px) {
            .period-selector {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .period-selector .actions {
                margin-top: 15px;
                width: 100%;
            }
            
            .date-range-picker {
                flex-wrap: wrap;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
                margin-bottom: 20px;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-info {
                margin-top: 15px;
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
                    <a href="reports.php" class="active">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </li>
                <li>
                    <a href="settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-chart-line mr-2"></i> Reports & Analytics
                </h1>
                <div class="user-info">
                    <span class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Period Selector -->
            <div class="period-selector">
                <div>
                    <div class="btn-group" role="group">
                        <a href="?period=week" class="btn <?php echo $period === 'week' ? 'btn-primary' : 'btn-outline-primary'; ?>">Last 7 Days</a>
                        <a href="?period=month" class="btn <?php echo $period === 'month' ? 'btn-primary' : 'btn-outline-primary'; ?>">Last 30 Days</a>
                        <a href="?period=quarter" class="btn <?php echo $period === 'quarter' ? 'btn-primary' : 'btn-outline-primary'; ?>">Last 3 Months</a>
                        <a href="?period=year" class="btn <?php echo $period === 'year' ? 'btn-primary' : 'btn-outline-primary'; ?>">Last 12 Months</a>
                    </div>
                    
                    <div class="date-range-picker mt-3">
                        <form action="" method="GET" class="d-flex align-items-center">
                            <input type="text" id="start-date" name="start_date" class="date-input" placeholder="Start Date" value="<?php echo $customDateRange ? $startDate : ''; ?>" required>
                            <span class="mx-2">to</span>
                            <input type="text" id="end-date" name="end_date" class="date-input" placeholder="End Date" value="<?php echo $customDateRange ? $endDate : ''; ?>" required>
                            <button type="submit" class="btn btn-primary ml-2">Apply</button>
                        </form>
                    </div>
                </div>
                
                <div class="actions">
                    <button class="btn btn-outline-secondary" id="printReport">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <div class="btn-group ml-2">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="#" id="exportCSV"><i class="far fa-file-excel mr-2"></i>Export as CSV</a>
                            <a class="dropdown-item" href="#" id="exportPDF"><i class="far fa-file-pdf mr-2"></i>Export as PDF</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Key Metrics -->
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card stats-card sales">
                        <div class="stats-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stats-value"><?php echo formatCurrency($totalSales); ?></div>
                        <div class="stats-label">Total Sales</div>
                        <div class="stats-trend <?php echo $salesGrowth >= 0 ? 'trend-up' : 'trend-down'; ?>">
                            <i class="fas fa-arrow-<?php echo $salesGrowth >= 0 ? 'up' : 'down'; ?>"></i> 
                            <?php echo abs($salesGrowth); ?>%
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stats-card orders">
                        <div class="stats-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stats-value"><?php echo $totalOrders; ?></div>
                        <div class="stats-label">Total Orders</div>
                        <div class="stats-trend <?php echo $ordersGrowth >= 0 ? 'trend-up' : 'trend-down'; ?>">
                            <i class="fas fa-arrow-<?php echo $ordersGrowth >= 0 ? 'up' : 'down'; ?>"></i> 
                            <?php echo abs($ordersGrowth); ?>%
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stats-card customers">
                        <div class="stats-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-value"><?php echo $newCustomers; ?></div>
                        <div class="stats-label">New Customers</div>
                        <div class="stats-trend <?php echo $customersGrowth >= 0 ? 'trend-up' : 'trend-down'; ?>">
                            <i class="fas fa-arrow-<?php echo $customersGrowth >= 0 ? 'up' : 'down'; ?>"></i> 
                            <?php echo abs($customersGrowth); ?>%
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stats-card aov">
                        <div class="stats-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="stats-value"><?php echo formatCurrency($averageOrderValue); ?></div>
                        <div class="stats-label">Avg. Order Value</div>
                        <div class="stats-trend <?php echo $aovGrowth >= 0 ? 'trend-up' : 'trend-down'; ?>">
                            <i class="fas fa-arrow-<?php echo $aovGrowth >= 0 ? 'up' : 'down'; ?>"></i> 
                            <?php echo abs($aovGrowth); ?>%
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sales Overview -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Sales Overview - <?php echo $periodLabels[$period]; ?></h6>
                            <div class="card-tools">
                                <ul class="nav nav-tabs card-header-tabs" id="salesChartTabs">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="sales-tab" data-toggle="tab" href="#sales-chart">Sales</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="orders-tab" data-toggle="tab" href="#orders-chart">Orders</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="sales-chart">
                                    <div class="chart-container">
                                        <canvas id="salesChart"></canvas>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="orders-chart">
                                    <div class="chart-container">
                                        <canvas id="ordersChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Orders by Status</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 200px;">
                                <canvas id="statusChart"></canvas>
                            </div>
                            <div class="mt-4">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th class="text-right">Orders</th>
                                            <th class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($salesByStatus) > 0): ?>
                                            <?php foreach ($salesByStatus as $status): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge badge-status <?php echo getStatusBadgeClass($status['status']); ?>">
                                                        <?php echo ucfirst($status['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-right"><?php echo $status['count']; ?></td>
                                                <td class="text-right"><?php echo formatCurrency($status['total']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center">No orders found for the selected period.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Products & Payment Methods -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Top Selling Products</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th class="text-right">Price</th>
                                            <th class="text-right">Qty</th>
                                            <th class="text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($topProducts) > 0): ?>
                                            <?php foreach ($topProducts as $product): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?php echo !empty($product['image_url']) ? $product['image_url'] : '/Terral2/assets/images/placeholder.jpg'; ?>" class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                                    </div>
                                                </td>
                                                <td class="text-right"><?php echo formatCurrency($product['price']); ?></td>
                                                <td class="text-right"><?php echo $product['total_quantity']; ?></td>
                                                <td class="text-right"><?php echo formatCurrency($product['total_sales']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No products found for the selected period.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Payment Methods</h6>
                        </div>
                        <div class="card-body">
                            <?php if (isset($paymentMethods) && count($paymentMethods) > 0): ?>
                                <?php foreach ($paymentMethods as $method): ?>
                                <div class="payment-method-item">
                                    <div class="d-flex align-items-center">
                                        <div class="payment-method-icon">
                                            <i class="fas <?php echo getPaymentMethodIcon($method['payment_method']); ?>"></i>
                                        </div>
                                        <div class="payment-method-details">
                                            <div class="payment-method-name"><?php echo htmlspecialchars($method['payment_method']); ?></div>
                                            <div class="payment-method-count"><?php echo $method['count']; ?> orders</div>
                                        </div>
                                    </div>
                                    <div class="payment-method-amount">
                                        <?php echo formatCurrency($method['total']); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">No payment data available for the selected period.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Overview -->
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Customer Overview</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="chart-container" style="height: 200px;">
                                        <canvas id="customerChart"></canvas>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <h5 class="card-title">Total Customers</h5>
                                                    <h2 class="mb-0"><?php echo $totalCustomers; ?></h2>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <h5 class="card-title">New Customers</h5>
                                                    <h2 class="mb-0"><?php echo $newCustomers; ?></h2>
                                                    <small class="text-muted">During selected period</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Customer Growth</h5>
                                            <div class="stats-trend <?php echo $customersGrowth >= 0 ? 'trend-up' : 'trend-down'; ?> mb-2">
                                                <i class="fas fa-arrow-<?php echo $customersGrowth >= 0 ? 'up' : 'down'; ?>"></i> 
                                                <?php echo abs($customersGrowth); ?>% compared to previous period
                                            </div>
                                            <p>The customer base has <?php echo $customersGrowth >= 0 ? 'grown' : 'decreased'; ?> by <?php echo abs($customersGrowth); ?>% compared to the previous period.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS, jQuery, Popper.js -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
    <!-- Flatpickr -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize date pickers
            flatpickr("#start-date", {
                dateFormat: "Y-m-d",
                maxDate: "today"
            });
            
            flatpickr("#end-date", {
                dateFormat: "Y-m-d",
                maxDate: "today"
            });
            
            // Tab switching
            $('#salesChartTabs a').on('click', function (e) {
                e.preventDefault();
                $(this).tab('show');
            });
            
            // Sales Chart
            var salesCtx = document.getElementById('salesChart').getContext('2d');
            var salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        label: 'Sales (KSh)',
                        data: <?php echo json_encode($salesData); ?>,
                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(52, 152, 219, 1)',
                        pointBorderColor: '#fff',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                callback: function(value) {
                                    return 'KSh ' + value.toLocaleString();
                                }
                            }
                        }]
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var value = tooltipItem.yLabel;
                                return 'Sales: KSh ' + value.toLocaleString();
                            }
                        }
                    }
                }
            });
            
            // Orders Chart
            var ordersCtx = document.getElementById('ordersChart').getContext('2d');
            var ordersChart = new Chart(ordersCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        label: 'Orders',
                        data: <?php echo json_encode($ordersData); ?>,
                        backgroundColor: 'rgba(243, 156, 18, 0.7)',
                        borderColor: 'rgba(243, 156, 18, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                stepSize: 1
                            }
                        }]
                    }
                }
            });
            
            // Status Chart
            var statusCtx = document.getElementById('statusChart').getContext('2d');
            var statusLabels = [];
            var statusData = [];
            var statusColors = [];
            
            <?php foreach ($salesByStatus as $status): ?>
                statusLabels.push("<?php echo ucfirst($status['status']); ?>");
                statusData.push(<?php echo $status['count']; ?>);
                
                <?php if (strtolower($status['status']) == 'pending'): ?>
                    statusColors.push('#ffc107');
                <?php elseif (strtolower($status['status']) == 'processing'): ?>
                    statusColors.push('#17a2b8');
                <?php elseif (strtolower($status['status']) == 'shipped'): ?>
                    statusColors.push('#007bff');
                <?php elseif (strtolower($status['status']) == 'delivered'): ?>
                    statusColors.push('#28a745');
                <?php elseif (strtolower($status['status']) == 'canceled'): ?>
                    statusColors.push('#dc3545');
                <?php else: ?>
                    statusColors.push('#6c757d');
                <?php endif; ?>
            <?php endforeach; ?>
            
            var statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusData,
                        backgroundColor: statusColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12
                        }
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var dataset = data.datasets[tooltipItem.datasetIndex];
                                var total = dataset.data.reduce(function(previousValue, currentValue) {
                                    return previousValue + currentValue;
                                });
                                var currentValue = dataset.data[tooltipItem.index];
                                var percentage = Math.round((currentValue/total) * 100);
                                return data.labels[tooltipItem.index] + ': ' + currentValue + ' orders (' + percentage + '%)';
                            }
                        }
                    }
                }
            });
            
            // Customer Chart
            var customerCtx = document.getElementById('customerChart').getContext('2d');
            var customerData = {
                labels: ['New Customers', 'Existing Customers'],
                datasets: [{
                    data: [<?php echo $newCustomers; ?>, <?php echo $totalCustomers - $newCustomers; ?>],
                    backgroundColor: ['rgba(46, 204, 113, 0.8)', 'rgba(149, 165, 166, 0.8)'],
                    borderWidth: 0
                }]
            };
            
            var customerChart = new Chart(customerCtx, {
                type: 'pie',
                data: customerData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        position: 'bottom'
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var dataset = data.datasets[tooltipItem.datasetIndex];
                                var total = dataset.data.reduce(function(previousValue, currentValue) {
                                    return previousValue + currentValue;
                                });
                                var currentValue = dataset.data[tooltipItem.index];
                                var percentage = Math.round((currentValue/total) * 100);
                                return data.labels[tooltipItem.index] + ': ' + currentValue + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            });
            
            // Print report
            $('#printReport').click(function() {
                window.print();
            });
            
            // Export as CSV
            $('#exportCSV').click(function(e) {
                e.preventDefault();
                alert('Export as CSV functionality will be implemented here.');
            });
            
            // Export as PDF
            $('#exportPDF').click(function(e) {
                e.preventDefault();
                alert('Export as PDF functionality will be implemented here.');
            });
        });
    </script>
</body>
</html>