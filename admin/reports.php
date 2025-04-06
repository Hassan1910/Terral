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
            --primary-light: #ebf5fd;
            --primary-dark: #2980b9;
            --secondary: #e74c3c;
            --dark: #2c3e50;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --background: #f5f7fa;
            --white: #ffffff;
            --gray-light: #ecf0f1;
            --gray: #bdc3c7;
            --success: #2ecc71;
            --success-light: #eafaf1;
            --warning: #f39c12;
            --warning-light: #fef5e7;
            --danger: #e74c3c;
            --danger-light: #fdedeb;
            --info: #3498db;
            --info-light: #ebf5fd;
            --border-radius: 10px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --box-shadow-hover: 0 10px 30px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
            --font-family: 'Poppins', sans-serif;
            --card-padding: 24px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--font-family);
            color: var(--text-dark);
            background-color: var(--background);
            line-height: 1.6;
            font-size: 0.95rem;
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
            width: 280px;
            background-color: var(--dark);
            color: var(--white);
            position: fixed;
            height: 100vh;
            z-index: 1000;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }
        
        .sidebar-logo {
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-logo h2 {
            color: var(--white);
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
        }
        
        .sidebar-logo i {
            margin-right: 10px;
            font-size: 1.6rem;
        }
        
        .sidebar-menu {
            padding: 20px 0;
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.7);
            border-left: 4px solid transparent;
            transition: var(--transition);
        }
        
        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--white);
            border-left-color: var(--primary);
        }
        
        .sidebar-menu a.active {
            background-color: rgba(52, 152, 219, 0.3);
            color: var(--white);
            border-left-color: var(--primary);
        }
        
        .sidebar-menu i {
            min-width: 30px;
            font-size: 1.1rem;
            margin-right: 10px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            background-color: var(--background);
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            color: var(--text-dark);
        }
        
        .page-title i {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background-color: var(--primary-light);
            color: var(--primary);
            border-radius: 12px;
            margin-right: 15px;
            font-size: 1.4rem;
        }
        
        .user-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            background-color: var(--white);
            padding: 10px 15px;
            border-radius: 50px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 10px;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .logout-btn {
            background-color: var(--white);
            color: var(--danger);
            border: none;
            padding: 10px 15px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: var(--transition);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .logout-btn i {
            margin-right: 6px;
        }
        
        .logout-btn:hover {
            background-color: var(--danger);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        /* Dashboard Cards */
        .card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 25px;
            overflow: hidden;
            transition: var(--transition);
            border: none;
        }
        
        .card:hover {
            box-shadow: var(--box-shadow-hover);
            transform: translateY(-4px);
        }
        
        .card-header {
            padding: 20px var(--card-padding);
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            margin: 0;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
        }
        
        .card-title-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background-color: var(--primary-light);
            color: var(--primary);
            border-radius: 10px;
            margin-right: 12px;
            font-size: 1rem;
        }
        
        .card-actions {
            display: flex;
            gap: 10px;
        }
        
        .card-body {
            padding: var(--card-padding);
        }
        
        .card-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        @media (max-width: 1200px) {
            .card-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .card-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Metric Cards */
        .metric-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            height: 100%;
            display: flex;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .metric-card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
        }
        
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background-color: var(--primary);
            border-radius: 6px 0 0 6px;
        }
        
        .metric-card.primary::before { background-color: var(--primary); }
        .metric-card.success::before { background-color: var(--success); }
        .metric-card.warning::before { background-color: var(--warning); }
        .metric-card.info::before { background-color: var(--info); }
        
        .metric-icon {
            position: relative;
            width: 60px;
            height: 60px;
            border-radius: 15px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .metric-card.primary .metric-icon {
            background-color: var(--primary-light);
            color: var(--primary);
        }
        
        .metric-card.success .metric-icon {
            background-color: var(--success-light);
            color: var(--success);
        }
        
        .metric-card.warning .metric-icon {
            background-color: var(--warning-light);
            color: var(--warning);
        }
        
        .metric-card.info .metric-icon {
            background-color: var(--info-light);
            color: var(--info);
        }
        
        .metric-content {
            flex: 1;
            position: relative;
            z-index: 1;
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .metric-label {
            color: var(--text-light);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .trend-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .trend-badge i {
            margin-right: 4px;
            font-size: 0.9rem;
        }
        
        .trend-up {
            background-color: var(--success-light);
            color: var(--success);
        }
        
        .trend-down {
            background-color: var(--danger-light);
            color: var(--danger);
        }
        
        /* Report Period Card */
        .date-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .period-tabs {
            display: flex;
            background-color: var(--gray-light);
            border-radius: 8px;
            padding: 4px;
        }
        
        .period-tab {
            padding: 8px 15px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-light);
        }
        
        .period-tab.active {
            background-color: var(--white);
            color: var(--primary);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .date-range-picker {
            display: flex;
            align-items: center;
            background-color: var(--white);
            border-radius: 8px;
            padding: 4px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .date-input-group {
            position: relative;
        }
        
        .date-input {
            padding: 8px 15px 8px 35px;
            border: 1px solid var(--gray-light);
            border-radius: 6px;
            font-size: 0.9rem;
            color: var(--text-dark);
            min-width: 140px;
            background-color: transparent;
            transition: var(--transition);
        }
        
        .date-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
        }
        
        .date-input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            pointer-events: none;
        }
        
        .date-separator {
            margin: 0 10px;
            color: var(--text-light);
            font-weight: 600;
        }
        
        .date-apply-btn {
            padding: 8px 20px;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-left: 10px;
        }
        
        .date-apply-btn:hover {
            background-color: var(--primary-dark);
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
        }
        
        .current-period-info {
            background-color: var(--primary-light);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        
        .period-label {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .period-dates {
            color: var(--text-dark);
            font-size: 0.9rem;
        }
        
        /* Buttons & Controls */
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            border: none;
        }
        
        .btn i {
            margin-right: 6px;
            font-size: 1rem;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--text-dark);
            border: 1px solid var(--gray);
        }
        
        .btn-outline:hover {
            background-color: var(--gray-light);
        }
        
        .btn-outline-primary {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-light);
        }
        
        .btn-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background-color: var(--white);
            color: var(--text-dark);
            font-size: 1.1rem;
            border: 1px solid var(--gray-light);
            padding: 0;
        }
        
        .btn-icon:hover {
            background-color: var(--gray-light);
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            min-width: 200px;
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            padding: 10px 0;
            z-index: 1000;
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: var(--transition);
        }
        
        .dropdown-menu.show {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: var(--text-dark);
            transition: var(--transition);
        }
        
        .dropdown-item:hover {
            background-color: var(--primary-light);
            color: var(--primary);
        }
        
        .dropdown-item i {
            margin-right: 10px;
            font-size: 1rem;
            color: inherit;
        }
        
        /* Chart Containers */
        .chart-container {
            width: 100%;
            height: 350px;
            position: relative;
        }
        
        /* Tabs */
        .tab-nav {
            display: flex;
            border-bottom: 1px solid var(--gray-light);
            margin-bottom: 20px;
        }
        
        .tab-link {
            padding: 10px 20px;
            font-weight: 600;
            color: var(--text-light);
            border-bottom: 3px solid transparent;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .tab-link.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Tables */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .data-table th {
            background-color: var(--gray-light);
            color: var(--text-dark);
            font-weight: 600;
            padding: 15px;
            text-align: left;
            position: sticky;
            top: 0;
        }
        
        .data-table th:first-child {
            border-top-left-radius: 8px;
        }
        
        .data-table th:last-child {
            border-top-right-radius: 8px;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid var(--gray-light);
            color: var(--text-dark);
            vertical-align: middle;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover td {
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        /* Status Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-primary {
            background-color: var(--primary-light);
            color: var(--primary);
        }
        
        .badge-success {
            background-color: var(--success-light);
            color: var(--success);
        }
        
        .badge-warning {
            background-color: var(--warning-light);
            color: var(--warning);
        }
        
        .badge-danger {
            background-color: var(--danger-light);
            color: var(--danger);
        }
        
        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background-color: rgba(52, 152, 219, 0.03);
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 15px;
            opacity: 0.7;
        }
        
        .empty-state-text {
            font-size: 1.1rem;
            color: var(--text-light);
            margin-bottom: 20px;
        }
        
        /* Payment Method Items */
        .payment-method-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            margin-bottom: 15px;
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }
        
        .payment-method-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .payment-info {
            display: flex;
            align-items: center;
        }
        
        .payment-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-light);
            color: var(--primary);
            border-radius: 10px;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .payment-details h4 {
            margin: 0 0 5px;
            color: var(--text-dark);
            font-weight: 600;
        }
        
        .payment-stats {
            font-size: 0.85rem;
            color: var(--text-light);
        }
        
        .payment-amount {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary);
        }
        
        /* Product List Items */
        .product-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid var(--gray-light);
            transition: var(--transition);
        }
        
        .product-item:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
            margin-right: 15px;
            background-color: var(--gray-light);
        }
        
        .product-details {
            flex: 1;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .product-id {
            color: var(--text-light);
            font-size: 0.85rem;
        }
        
        .product-price {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .product-quantity {
            padding: 0 15px;
        }
        
        .product-revenue {
            font-weight: 700;
            color: var(--primary);
            text-align: right;
            font-size: 1.1rem;
        }
        
        /* Customer Analysis */
        .customer-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .customer-stat-card {
            background-color: var(--white);
            padding: 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }
        
        .customer-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .customer-stat-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-light);
            color: var(--primary);
            border-radius: 12px;
            margin-right: 15px;
            font-size: 1.3rem;
        }
        
        .customer-stat-icon.green {
            background-color: var(--success-light);
            color: var(--success);
        }
        
        .customer-stat-details h3 {
            margin: 0 0 5px;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .customer-stat-details p {
            margin: 0;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .customer-chart-container {
            display: flex;
        }
        
        .customer-chart {
            flex: 1;
            height: 250px;
        }
        
        .customer-percentage {
            width: 200px;
            background-color: var(--white);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-left: 20px;
        }
        
        .percentage-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--success);
            margin: 10px 0;
        }
        
        .customer-insight-box {
            background-color: var(--white);
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .insight-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .insight-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-light);
            color: var(--primary);
            border-radius: 10px;
            margin-right: 15px;
            font-size: 1.1rem;
        }
        
        .insight-title {
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }
        
        .growth-indicator {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .growth-indicator.positive {
            background-color: var(--success-light);
            color: var(--success);
        }
        
        .growth-indicator.negative {
            background-color: var(--danger-light);
            color: var(--danger);
        }
        
        .insight-text {
            color: var(--text-dark);
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .view-all-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 20px;
            background-color: var(--primary-light);
            color: var(--primary);
            border-radius: 8px;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .view-all-btn:hover {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .view-all-btn i {
            margin-left: 8px;
        }
        
        @media (max-width: 992px) {
            .customer-chart-container {
                flex-direction: column;
            }
            
            .customer-percentage {
                width: 100%;
                margin-left: 0;
                margin-top: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                left: -280px;
            }
            
            .sidebar.active {
                width: 280px;
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-controls {
                margin-top: 20px;
                width: 100%;
                justify-content: space-between;
            }
            
            .customer-stats {
                grid-template-columns: 1fr;
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
                    <i class="fas fa-chart-line"></i>
                    Reports & Analytics
                </h1>
                <div class="user-controls">
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Admin'); ?></span>
                    </div>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Report Period Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <div class="card-title-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        Report Period
                    </h3>
                    <div class="card-actions">
                        <button class="btn btn-outline" id="printReport">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle" id="exportDropdown">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <div class="dropdown-menu" id="exportMenu">
                                <a href="#" class="dropdown-item" id="exportCSV">
                                    <i class="far fa-file-excel"></i> Export as CSV
                                </a>
                                <a href="#" class="dropdown-item" id="exportPDF">
                                    <i class="far fa-file-pdf"></i> Export as PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="date-controls">
                                <div class="period-tabs">
                                    <a href="?period=week" class="period-tab <?php echo $period === 'week' ? 'active' : ''; ?>">Last 7 Days</a>
                                    <a href="?period=month" class="period-tab <?php echo $period === 'month' ? 'active' : ''; ?>">Last 30 Days</a>
                                    <a href="?period=quarter" class="period-tab <?php echo $period === 'quarter' ? 'active' : ''; ?>">Last 3 Months</a>
                                    <a href="?period=year" class="period-tab <?php echo $period === 'year' ? 'active' : ''; ?>">Last 12 Months</a>
                                </div>
                                
                                <form action="" method="GET" class="date-range-picker">
                                    <div class="date-input-group">
                                        <i class="fas fa-calendar date-input-icon"></i>
                                        <input type="text" id="start-date" name="start_date" class="date-input" placeholder="Start Date" value="<?php echo $customDateRange ? $startDate : ''; ?>" required>
                                    </div>
                                    <span class="date-separator">to</span>
                                    <div class="date-input-group">
                                        <i class="fas fa-calendar date-input-icon"></i>
                                        <input type="text" id="end-date" name="end_date" class="date-input" placeholder="End Date" value="<?php echo $customDateRange ? $endDate : ''; ?>" required>
                                    </div>
                                    <button type="submit" class="date-apply-btn">Apply</button>
                                </form>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="current-period-info">
                                <h4 class="period-label"><?php echo $periodLabels[$period]; ?></h4>
                                <p class="period-dates"><?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Key Metrics Dashboard -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <div class="card-title-icon">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        Key Performance Metrics
                    </h3>
                </div>
                <div class="card-body">
                    <div class="card-grid">
                        <div class="metric-card primary">
                            <div class="metric-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="metric-content">
                                <h3 class="metric-value"><?php echo formatCurrency($totalSales); ?></h3>
                                <div class="metric-label">Total Sales</div>
                                <div class="trend-badge <?php echo $salesGrowth >= 0 ? 'trend-up' : 'trend-down'; ?>">
                                    <i class="fas fa-arrow-<?php echo $salesGrowth >= 0 ? 'up' : 'down'; ?>"></i> 
                                    <?php echo abs($salesGrowth); ?>%
                                </div>
                            </div>
                        </div>
                        
                        <div class="metric-card warning">
                            <div class="metric-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="metric-content">
                                <h3 class="metric-value"><?php echo $totalOrders; ?></h3>
                                <div class="metric-label">Total Orders</div>
                                <div class="trend-badge <?php echo $ordersGrowth >= 0 ? 'trend-up' : 'trend-down'; ?>">
                                    <i class="fas fa-arrow-<?php echo $ordersGrowth >= 0 ? 'up' : 'down'; ?>"></i> 
                                    <?php echo abs($ordersGrowth); ?>%
                                </div>
                            </div>
                        </div>
                        
                        <div class="metric-card success">
                            <div class="metric-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="metric-content">
                                <h3 class="metric-value"><?php echo $newCustomers; ?></h3>
                                <div class="metric-label">New Customers</div>
                                <div class="trend-badge <?php echo $customersGrowth >= 0 ? 'trend-up' : 'trend-down'; ?>">
                                    <i class="fas fa-arrow-<?php echo $customersGrowth >= 0 ? 'up' : 'down'; ?>"></i> 
                                    <?php echo abs($customersGrowth); ?>%
                                </div>
                            </div>
                        </div>
                        
                        <div class="metric-card info">
                            <div class="metric-icon">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <div class="metric-content">
                                <h3 class="metric-value"><?php echo formatCurrency($averageOrderValue); ?></h3>
                                <div class="metric-label">Avg. Order Value</div>
                                <div class="trend-badge <?php echo $aovGrowth >= 0 ? 'trend-up' : 'trend-down'; ?>">
                                    <i class="fas fa-arrow-<?php echo $aovGrowth >= 0 ? 'up' : 'down'; ?>"></i> 
                                    <?php echo abs($aovGrowth); ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sales Analytics -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <div class="card-title-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        Sales Analytics
                    </h3>
                </div>
                <div class="card-body">
                    <div class="tab-nav" id="salesChartTabs">
                        <div class="tab-link active" data-tab="sales-chart">Revenue</div>
                        <div class="tab-link" data-tab="orders-chart">Orders</div>
                    </div>
                    
                    <div class="tab-content active" id="sales-chart">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="orders-chart">
                        <div class="chart-container">
                            <canvas id="ordersChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Two-column layout for mid-section -->
            <div class="row">
                <!-- Order Status Distribution -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <div class="card-title-icon">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                Order Status Distribution
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                            
                            <?php if (count($salesByStatus) > 0): ?>
                                <div class="table-container mt-4">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Orders</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($salesByStatus as $status): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge <?php echo getStatusBadgeClass($status['status']); ?>">
                                                        <?php echo ucfirst($status['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $status['count']; ?></td>
                                                <td><?php echo formatCurrency($status['total']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-clipboard-list"></i>
                                    </div>
                                    <p class="empty-state-text">No orders found for the selected period.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Methods -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <div class="card-title-icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                Payment Methods
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (isset($paymentMethods) && count($paymentMethods) > 0): ?>
                                <?php foreach ($paymentMethods as $method): ?>
                                <div class="payment-method-item">
                                    <div class="payment-info">
                                        <div class="payment-icon">
                                            <i class="fas <?php echo getPaymentMethodIcon($method['payment_method']); ?>"></i>
                                        </div>
                                        <div class="payment-details">
                                            <h4><?php echo htmlspecialchars($method['payment_method']); ?></h4>
                                            <div class="payment-stats">
                                                <?php echo $method['count']; ?> orders (<?php echo round(($method['count'] / $totalOrders) * 100); ?>%)
                                            </div>
                                            
                                            <div class="progress mt-2" style="height: 5px; width: 100%;">
                                                <div class="progress-bar" style="width: <?php echo ($method['count'] / $totalOrders) * 100; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="payment-amount">
                                        <?php echo formatCurrency($method['total']); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <p class="empty-state-text">No payment data available for the selected period.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Products Section -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <div class="card-title-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        Top Selling Products
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (count($topProducts) > 0): ?>
                        <?php foreach ($topProducts as $product): ?>
                        <div class="product-item">
                            <img src="<?php echo !empty($product['image_url']) ? $product['image_url'] : '/Terral2/assets/images/placeholder.jpg'; ?>" class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            
                            <div class="product-details">
                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-id">ID: <?php echo $product['id']; ?></div>
                            </div>
                            
                            <div class="product-price">
                                <?php echo formatCurrency($product['price']); ?>
                            </div>
                            
                            <div class="product-quantity">
                                <span class="badge badge-primary"><?php echo $product['total_quantity']; ?> units</span>
                            </div>
                            
                            <div class="product-revenue">
                                <?php echo formatCurrency($product['total_sales']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <p class="empty-state-text">No products found for the selected period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Customer Analysis -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <div class="card-title-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        Customer Analysis
                    </h3>
                </div>
                <div class="card-body">
                    <div class="customer-stats">
                        <div class="customer-stat-card">
                            <div class="customer-stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="customer-stat-details">
                                <h3><?php echo $totalCustomers; ?></h3>
                                <p>Total Customers</p>
                            </div>
                        </div>
                        
                        <div class="customer-stat-card">
                            <div class="customer-stat-icon green">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="customer-stat-details">
                                <h3><?php echo $newCustomers; ?></h3>
                                <p>New Customers (this period)</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="customer-chart-container">
                        <div class="customer-chart">
                            <canvas id="customerChart"></canvas>
                        </div>
                        
                        <div class="customer-percentage">
                            <p>New customers represent</p>
                            <div class="percentage-value"><?php echo round(($newCustomers / $totalCustomers) * 100); ?>%</div>
                            <p>of total customer base</p>
                        </div>
                    </div>
                    
                    <div class="customer-insight-box">
                        <div class="insight-header">
                            <div class="insight-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3 class="insight-title">Customer Growth Insights</h3>
                        </div>
                        
                        <div class="growth-indicator <?php echo $customersGrowth >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $customersGrowth >= 0 ? 'up' : 'down'; ?>"></i> 
                            <?php echo abs($customersGrowth); ?>%
                        </div>
                        
                        <p class="insight-text">
                            Customer acquisition has <?php echo $customersGrowth >= 0 ? 'increased' : 'decreased'; ?> by <strong><?php echo abs($customersGrowth); ?>%</strong> compared to the previous period. 
                            <?php echo $customersGrowth >= 0 ? 'This positive trend indicates effective marketing and customer satisfaction.' : 'This indicates a need to review acquisition strategies and customer experience.'; ?>
                        </p>
                        
                        <a href="customers.php" class="view-all-btn">
                            View All Customers <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript to Handle UI Interactions -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize dropdowns
        const exportDropdown = document.getElementById('exportDropdown');
        const exportMenu = document.getElementById('exportMenu');
        
        if (exportDropdown && exportMenu) {
            exportDropdown.addEventListener('click', function() {
                exportMenu.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!exportDropdown.contains(e.target)) {
                    exportMenu.classList.remove('show');
                }
            });
        }
        
        // Tab handling
        const tabLinks = document.querySelectorAll('.tab-link');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabLinks.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remove active class from all tabs and contents
                tabLinks.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Add active class to current tab and content
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Initialize charts with enhanced styling
        // Update chart options to match our new design
        if (window.salesChart) {
            window.salesChart.options.plugins.tooltip.backgroundColor = '#2c3e50';
            window.salesChart.options.plugins.tooltip.titleColor = '#ffffff';
            window.salesChart.options.plugins.tooltip.bodyColor = '#ffffff';
            window.salesChart.update();
        }
        
        if (window.ordersChart) {
            window.ordersChart.options.plugins.tooltip.backgroundColor = '#2c3e50';
            window.ordersChart.options.plugins.tooltip.titleColor = '#ffffff';
            window.ordersChart.options.plugins.tooltip.bodyColor = '#ffffff';
            window.ordersChart.update();
        }
        
        if (window.statusChart) {
            window.statusChart.options.plugins.tooltip.backgroundColor = '#2c3e50';
            window.statusChart.options.plugins.tooltip.titleColor = '#ffffff';
            window.statusChart.options.plugins.tooltip.bodyColor = '#ffffff';
            window.statusChart.update();
        }
        
        if (window.customerChart) {
            window.customerChart.options.plugins.tooltip.backgroundColor = '#2c3e50';
            window.customerChart.options.plugins.tooltip.titleColor = '#ffffff';
            window.customerChart.options.plugins.tooltip.bodyColor = '#ffffff';
            window.customerChart.update();
        }
        
        // Print report functionality
        const printReportBtn = document.getElementById('printReport');
        if (printReportBtn) {
            printReportBtn.addEventListener('click', function() {
                window.print();
            });
        }
        
        // Export functionality
        const exportCSVBtn = document.getElementById('exportCSV');
        const exportPDFBtn = document.getElementById('exportPDF');
        
        if (exportCSVBtn) {
            exportCSVBtn.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Exporting as CSV...');
                // Implement actual export functionality here
            });
        }
        
        if (exportPDFBtn) {
            exportPDFBtn.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Exporting as PDF...');
                // Implement actual export functionality here
            });
        }
    });
    </script>
</body>
</html>