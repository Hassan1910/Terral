<?php
/**
 * Admin Customer Details Page
 * 
 * This page allows administrators to view and manage details for a specific customer.
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
    $_SESSION['redirect_after_login'] = '/Terral2/admin/customers.php';
    header('Location: /Terral2/login.php');
    exit;
}

// Initialize variables
$errorMessage = '';
$successMessage = '';
$customer = null;
$orders = [];

// Check if customer ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: customers.php');
    exit;
}

$customerId = intval($_GET['id']);

// Create database connection
$database = new Database();
$conn = $database->getConnection();

try {
    // Fetch customer details
    $customerQuery = "SELECT * FROM users WHERE id = :id AND role = 'customer' LIMIT 1";
    $customerStmt = $conn->prepare($customerQuery);
    $customerStmt->bindParam(':id', $customerId);
    $customerStmt->execute();
    
    if ($customerStmt->rowCount() === 0) {
        // Customer not found or not a customer
        header('Location: customers.php');
        exit;
    }
    
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch customer's orders
    $ordersQuery = "SELECT o.*, 
                   IFNULL(p.status, 'pending') as payment_status 
                   FROM orders o
                   LEFT JOIN payments p ON o.id = p.order_id
                   WHERE o.user_id = :user_id 
                   ORDER BY o.created_at DESC
                   LIMIT 10";
    $ordersStmt = $conn->prepare($ordersQuery);
    $ordersStmt->bindParam(':user_id', $customerId);
    $ordersStmt->execute();
    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle status update if form submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $newStatus = $_POST['status'];
        $validStatuses = ['active', 'inactive', 'suspended'];
        
        if (in_array($newStatus, $validStatuses)) {
            $updateStmt = $conn->prepare("UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id");
            $updateStmt->bindParam(':status', $newStatus);
            $updateStmt->bindParam(':id', $customerId);
            
            if ($updateStmt->execute()) {
                $successMessage = "Customer status has been updated successfully.";
                // Update the customer variable with new status
                $customer['status'] = $newStatus;
            } else {
                $errorMessage = "Failed to update customer status.";
            }
        } else {
            $errorMessage = "Invalid status value provided.";
        }
    }
    
} catch (PDOException $e) {
    $errorMessage = 'Database error: ' . $e->getMessage();
}

// Helper function for formatting currency
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return 'KSh ' . number_format($amount, 2);
    }
}

// Get status badge class helper function
if (!function_exists('getStatusBadgeClass')) {
    function getStatusBadgeClass($status) {
        switch ($status) {
            case 'active':
                return 'badge-success';
            case 'inactive':
                return 'badge-warning';
            case 'suspended':
                return 'badge-danger';
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Details - Terral Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
    <link href="/Terral/assets/css/admin.css" rel="stylesheet">
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --secondary: #e74c3c;
            --success: #2ecc71;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --background: #f8f9fa;
            --border-radius: 0.25rem;
            --box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--gray-800);
            background-color: var(--gray-100);
            line-height: 1.6;
            font-size: 0.875rem;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        .container-fluid {
            padding: 0;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--dark);
            color: var(--white);
            position: fixed;
            height: 100vh;
            z-index: 1000;
            transition: var(--transition);
            overflow-y: auto;
        }
        
        .sidebar-brand {
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .sidebar-brand h2 {
            color: var(--white);
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }
        
        .sidebar-menu {
            padding: 0;
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 0.25rem;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.8);
            padding: 0.875rem 1.5rem;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            color: var(--white);
            background-color: rgba(52, 152, 219, 0.7);
            border-left-color: var(--white);
        }
        
        .sidebar-menu i {
            margin-right: 0.75rem;
            font-size: 0.875rem;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 1.5rem;
            transition: var(--transition);
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-300);
        }
        
        .page-title {
            color: var(--gray-800);
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .page-title i {
            margin-right: 0.75rem;
            color: var(--primary);
        }
        
        .user-dropdown {
            position: relative;
        }
        
        .user-dropdown .dropdown-toggle {
            display: flex;
            align-items: center;
            background: none;
            border: none;
            color: var(--gray-700);
            font-weight: 500;
            padding: 0.5rem 0.75rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .user-dropdown .dropdown-toggle:hover {
            background-color: var(--gray-200);
        }
        
        .user-dropdown .dropdown-toggle img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-right: 0.75rem;
            object-fit: cover;
            border: 2px solid var(--gray-300);
        }
        
        .user-dropdown .dropdown-toggle i {
            margin-left: 0.5rem;
            font-size: 0.75rem;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            background-color: transparent;
            padding: 0;
            margin-bottom: 1.5rem;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: var(--gray-600);
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb-item a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb-item.active {
            color: var(--gray-700);
        }
        
        /* Action buttons */
        .action-buttons {
            margin-bottom: 1.5rem;
        }
        
        .btn {
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            font-weight: 500;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            transition: var(--transition);
        }
        
        .btn i {
            margin-right: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            color: var(--white);
        }
        
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: var(--white);
        }
        
        .btn-secondary {
            background-color: var(--gray-600);
            border-color: var(--gray-600);
        }
        
        .btn-secondary:hover {
            background-color: var(--gray-700);
            border-color: var(--gray-700);
        }
        
        /* Cards */
        .card {
            border: none;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--white);
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--gray-300);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-header h5 {
            margin: 0;
            color: var(--gray-800);
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
        }
        
        .card-header h5 i {
            color: var(--primary);
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        /* Customer Profile */
        .customer-profile {
            padding: 1.5rem;
            text-align: center;
        }
        
        .profile-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1.25rem;
            background-color: var(--gray-100);
            border: 2px solid var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--primary);
            overflow: hidden;
        }
        
        .profile-img i {
            font-size: 3rem;
        }
        
        .customer-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .customer-email {
            color: var(--gray-600);
            margin-bottom: 1rem;
        }
        
        .customer-stats {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin: 1.25rem 0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: var(--gray-600);
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        /* Badges */
        .badge {
            padding: 0.35rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 50px;
        }
        
        .badge-success {
            background-color: rgba(46, 204, 113, 0.2);
            color: var(--success);
        }
        
        .badge-warning {
            background-color: rgba(243, 156, 18, 0.2);
            color: #9a7d0a;
        }
        
        .badge-danger {
            background-color: rgba(231, 76, 60, 0.2);
            color: var(--danger);
        }
        
        .badge-info {
            background-color: rgba(52, 152, 219, 0.2);
            color: var(--info);
        }
        
        .badge-primary {
            background-color: rgba(52, 152, 219, 0.2);
            color: var(--primary);
        }
        
        .badge-secondary {
            background-color: rgba(108, 117, 125, 0.2);
            color: var(--gray-600);
        }
        
        /* Customer Info List */
        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .info-item {
            padding: 0.75rem 0;
            display: flex;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            min-width: 150px;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .info-value {
            flex: 1;
            color: var(--gray-800);
        }
        
        /* Orders List */
        .order-card {
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 1rem;
            transition: var(--transition);
            margin-bottom: 1rem;
            background-color: var(--white);
        }
        
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .order-id {
            font-weight: 600;
            color: var(--primary);
        }
        
        .order-date {
            color: var(--gray-600);
            font-size: 0.813rem;
        }
        
        .order-details {
            display: flex;
            justify-content: space-between;
            margin-top: 0.75rem;
        }
        
        .order-status-price {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        /* Form Controls */
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--gray-700);
            background-color: var(--white);
            background-clip: padding-box;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                position: fixed;
                left: -250px;
            }
            
            .sidebar.active {
                width: 250px;
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .toggle-sidebar {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <h2><i class="fas fa-paint-brush"></i> TERRAL</h2>
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="products.php">
                        <i class="fas fa-box"></i>
                        <span>Products</span>
                    </a>
                </li>
                <li>
                    <a href="orders.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                </li>
                <li>
                    <a href="customers.php" class="active">
                        <i class="fas fa-users"></i>
                        <span>Customers</span>
                    </a>
                </li>
                <li>
                    <a href="categories.php">
                        <i class="fas fa-tags"></i>
                        <span>Categories</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navbar -->
            <div class="page-header">
                <h1 class="page-title">
                    <button id="sidebarToggle" class="btn btn-sm btn-icon d-lg-none mr-2">
                        <i class="fas fa-bars"></i>
                    </button>
                    <i class="fas fa-user-circle"></i>
                    Customer Details
                    <?php if ($customer): ?>
                    <span class="ml-2 badge badge-pill badge-light">#<?php echo $customer['id']; ?></span>
                    <?php endif; ?>
                </h1>
                <div class="user-dropdown">
                    <button class="dropdown-toggle" type="button" id="userDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <div class="d-flex align-items-center">
                            <div class="avatar-placeholder mr-2 bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <span><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Admin'); ?></span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                        <a class="dropdown-item" href="profile.php">
                            <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                            Profile
                        </a>
                        <a class="dropdown-item" href="settings.php">
                            <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                            Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="../logout.php">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="customers.php">Customers</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Customer Details</li>
                </ol>
            </nav>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="customers.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Customers
                </a>
                <?php if ($customer): ?>
                <a href="edit-customer.php?id=<?php echo $customer['id']; ?>" class="btn btn-primary ml-2">
                    <i class="fas fa-edit"></i> Edit Customer
                </a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <div><?php echo $errorMessage; ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success d-flex align-items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <div><?php echo $successMessage; ?></div>
                </div>
            <?php endif; ?>
            
            <?php if ($customer): ?>
                <div class="row">
                    <div class="col-xl-4 col-lg-5">
                        <!-- Customer Profile Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="m-0"><i class="fas fa-id-card"></i> Customer Profile</h5>
                            </div>
                            <div class="card-body customer-profile">
                                <div class="profile-img">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h3 class="customer-name"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h3>
                                <p class="customer-email"><?php echo htmlspecialchars($customer['email']); ?></p>
                                <span class="badge <?php echo getStatusBadgeClass($customer['status'] ?? 'active'); ?>">
                                    <?php echo ucfirst($customer['status'] ?? 'active'); ?>
                                </span>
                                
                                <!-- Customer Statistics -->
                                <div class="customer-stats">
                                    <?php
                                    // Get order count for customer
                                    $orderCountQuery = "SELECT COUNT(*) as order_count, SUM(total_price) as total_spent FROM orders WHERE user_id = :user_id";
                                    $orderCountStmt = $conn->prepare($orderCountQuery);
                                    $orderCountStmt->bindParam(':user_id', $customerId);
                                    $orderCountStmt->execute();
                                    $orderStats = $orderCountStmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    $orderCount = $orderStats['order_count'] ?? 0;
                                    $totalSpent = $orderStats['total_spent'] ?? 0;
                                    
                                    // Calculate days as customer
                                    $joinDate = new DateTime($customer['created_at']);
                                    $today = new DateTime();
                                    $daysAsCustomer = $joinDate->diff($today)->days;
                                    ?>
                                    
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $orderCount; ?></div>
                                        <div class="stat-label">Orders</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo formatCurrency($totalSpent); ?></div>
                                        <div class="stat-label">Total Spent</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $daysAsCustomer; ?></div>
                                        <div class="stat-label">Days</div>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-envelope"></i> Send Email
                                    </a>
                                    <?php if (!empty($customer['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" class="btn btn-outline-secondary btn-sm ml-2">
                                        <i class="fas fa-phone"></i> Call
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Update Status Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="m-0"><i class="fas fa-toggle-on"></i> Update Status</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="form-group">
                                        <label for="status" class="form-label">Customer Status</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="active" <?php echo ($customer['status'] === 'active' || empty($customer['status'])) ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($customer['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="suspended" <?php echo ($customer['status'] === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                        </select>
                                        <small class="form-text text-muted">
                                            <i class="fas fa-info-circle"></i> Status affects user's ability to login and place orders.
                                        </small>
                                    </div>
                                    
                                    <button type="submit" name="update_status" class="btn btn-primary btn-block">
                                        <i class="fas fa-save"></i> Update Status
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-8 col-lg-7">
                        <!-- Customer Information Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="m-0"><i class="fas fa-info-circle"></i> Customer Information</h5>
                            </div>
                            <div class="card-body">
                                <ul class="info-list">
                                    <li class="info-item">
                                        <span class="info-label"><i class="fas fa-user mr-2 text-primary"></i>Full Name:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <span class="info-label"><i class="fas fa-envelope mr-2 text-primary"></i>Email:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($customer['email']); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <span class="info-label"><i class="fas fa-phone mr-2 text-primary"></i>Phone:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <span class="info-label"><i class="fas fa-map-marker-alt mr-2 text-primary"></i>Address:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($customer['address'] ?? 'N/A'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <span class="info-label"><i class="fas fa-city mr-2 text-primary"></i>City:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($customer['city'] ?? 'N/A'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <span class="info-label"><i class="fas fa-mailbox mr-2 text-primary"></i>Postal Code:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($customer['postal_code'] ?? 'N/A'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <span class="info-label"><i class="fas fa-globe mr-2 text-primary"></i>Country:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($customer['country'] ?? 'N/A'); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <span class="info-label"><i class="fas fa-shield-alt mr-2 text-primary"></i>Status:</span>
                                        <span class="info-value">
                                            <span class="badge <?php echo getStatusBadgeClass($customer['status'] ?? 'active'); ?>">
                                                <?php echo ucfirst($customer['status'] ?? 'active'); ?>
                                            </span>
                                        </span>
                                    </li>
                                    <li class="info-item">
                                        <span class="info-label"><i class="fas fa-calendar-plus mr-2 text-primary"></i>Registered:</span>
                                        <span class="info-value"><?php echo date('F j, Y, g:i a', strtotime($customer['created_at'])); ?></span>
                                    </li>
                                    <li class="info-item">
                                        <span class="info-label"><i class="fas fa-edit mr-2 text-primary"></i>Last Updated:</span>
                                        <span class="info-value"><?php echo !empty($customer['updated_at']) ? date('F j, Y, g:i a', strtotime($customer['updated_at'])) : 'N/A'; ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Recent Orders Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="m-0"><i class="fas fa-shopping-bag"></i> Recent Orders</h5>
                                <a href="orders.php?search=<?php echo urlencode($customer['email']); ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-list"></i> View All Orders
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (count($orders) > 0): ?>
                                    <div class="order-list">
                                        <?php foreach ($orders as $order): ?>
                                            <div class="order-card" onclick="window.location.href='order-details.php?id=<?php echo $order['id']; ?>'" style="cursor: pointer;">
                                                <div class="order-header">
                                                    <span class="order-id">#<?php echo $order['order_number'] ?? $order['id']; ?></span>
                                                    <span class="order-date"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                                                </div>
                                                
                                                <div class="order-details">
                                                    <div class="order-status-price">
                                                        <span class="badge <?php echo getStatusBadgeClass($order['status']); ?>">
                                                            <?php echo ucfirst($order['status']); ?>
                                                        </span>
                                                        <span class="badge <?php echo getStatusBadgeClass($order['payment_status']); ?>">
                                                            <?php echo ucfirst($order['payment_status']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="order-total font-weight-bold">
                                                        <?php echo formatCurrency($order['total_price'] ?? 0); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <div class="empty-state mb-3">
                                            <i class="fas fa-shopping-cart fa-3x text-muted"></i>
                                        </div>
                                        <p class="text-muted">No orders found for this customer.</p>
                                        <a href="#" class="btn btn-sm btn-outline-primary mt-2" data-toggle="modal" data-target="#createOrderModal">
                                            <i class="fas fa-plus"></i> Create New Order
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Customer Notes Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="m-0"><i class="fas fa-sticky-note"></i> Customer Notes</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Check if customer notes exist
                                $hasNotes = !empty($customer['notes']);
                                ?>
                                
                                <?php if ($hasNotes): ?>
                                    <div class="customer-notes mb-3">
                                        <?php echo nl2br(htmlspecialchars($customer['notes'])); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <p class="text-muted">No notes available for this customer.</p>
                                    </div>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#addNoteModal">
                                    <i class="fas fa-plus"></i> <?php echo $hasNotes ? 'Edit Notes' : 'Add Note'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Add Note Modal -->
                <div class="modal fade" id="addNoteModal" tabindex="-1" role="dialog" aria-labelledby="addNoteModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addNoteModalLabel">Customer Notes</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form action="" method="post">
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label for="customerNotes">Notes</label>
                                        <textarea class="form-control" id="customerNotes" name="notes" rows="4"><?php echo htmlspecialchars($customer['notes'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" name="update_notes" class="btn btn-primary">Save Notes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Create Order Modal -->
                <div class="modal fade" id="createOrderModal" tabindex="-1" role="dialog" aria-labelledby="createOrderModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="createOrderModalLabel">Create New Order</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <p>Create a new order for this customer?</p>
                                <p>This will redirect you to the new order creation page with this customer pre-selected.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <a href="create-order.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-primary">Create Order</a>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="alert alert-warning d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <div>Customer not found. This customer may have been deleted or doesn't exist.</div>
                </div>
                <div class="text-center mt-4">
                    <a href="customers.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Customers List
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bootstrap JS, jQuery, Popper.js -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        $(document).ready(function() {
            // Toggle sidebar for mobile
            $('#sidebarToggle').on('click', function() {
                $('.sidebar').toggleClass('active');
            });
            
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Initialize popovers
            $('[data-toggle="popover"]').popover();
            
            // Add animation to notifications
            $('.order-card').hover(
                function() { $(this).addClass('shadow-sm'); },
                function() { $(this).removeClass('shadow-sm'); }
            );
        });
    </script>
</body>
</html>
