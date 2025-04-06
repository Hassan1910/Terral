<?php
/**
 * Admin Customers Page
 * 
 * This page allows administrators to view and manage customer accounts.
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
    
    // Debug session information
    error_log('User not authenticated. Session: ' . print_r($_SESSION, true));
    
    header('Location: /Terral2/login.php');
    exit;
}

// Initialize variables
$pageTitle = 'Manage Customers';
$customers = [];
$errorMessage = '';
$successMessage = '';

// Pagination variables
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$totalCustomers = 0;

// Filter variables
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sorting
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sortOrder = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Validate sort parameters
$validSortColumns = ['id', 'first_name', 'last_name', 'email', 'created_at'];
if (!in_array($sortBy, $validSortColumns)) {
    $sortBy = 'created_at';
}

$validSortOrders = ['ASC', 'DESC'];
if (!in_array(strtoupper($sortOrder), $validSortOrders)) {
    $sortOrder = 'DESC';
}

// Create database connection
$database = new Database();
$conn = $database->getConnection();

try {
    // Build base SQL query
    $sql = "SELECT u.* FROM users u WHERE u.role = 'customer'";
    
    $countSql = "SELECT COUNT(*) as total FROM users u WHERE u.role = 'customer'";
    
    $params = [];
    
    // Apply filters
    if (!empty($status)) {
        $sql .= " AND u.status = :status";
        $countSql .= " AND u.status = :status";
        $params[':status'] = $status;
    }
    
    if (!empty($search)) {
        $sql .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search)";
        $countSql .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    // Apply sorting
    $sql .= " ORDER BY u.$sortBy $sortOrder";
    
    // Apply pagination
    $sql .= " LIMIT :offset, :per_page";
    
    // Get total count
    $stmt = $conn->prepare($countSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalCustomers = $result['total'];
    
    // Get paginated results
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $errorMessage = 'Database error: ' . $e->getMessage();
}

// Calculate pagination details
$totalPages = ceil($totalCustomers / $perPage);

// Get status badge class helper function
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active':
            return 'badge-success';
        case 'inactive':
            return 'badge-warning';
        case 'suspended':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}

// Build query string for pagination links
function buildQueryString($page, $exclude = []) {
    $query = $_GET;
    
    // Set the page
    $query['page'] = $page;
    
    // Remove excluded parameters
    foreach ($exclude as $param) {
        unset($query[$param]);
    }
    
    return http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers - Terral Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <!-- Admin Styles -->
    <link href="/Terral2/assets/css/admin.css" rel="stylesheet">
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --secondary: #e74c3c;
            --bg-dark: #2c3e50;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --background: #f8f9fa;
            --white: #ffffff;
            --gray-light: #ecf0f1;
            --gray: #bdc3c7;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --border-radius: 4px;
            --box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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
            overflow-x: hidden;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        .container-fluid {
            padding: 0;
            margin: 0;
            width: 100%;
            height: 100vh;
            display: flex;
        }
        
        /* Sidebar */
        .sidebar {
            width: 220px;
            background-color: var(--bg-dark);
            color: var(--white);
            position: fixed;
            height: 100vh;
            padding-top: 20px;
            z-index: 1000;
        }
        
        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 600;
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 15px;
            text-align: center;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 10px 15px;
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, 
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
            margin-left: 220px;
            padding: 15px;
            width: calc(100% - 220px);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0 15px;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .user-actions {
            display: flex;
            align-items: center;
        }
        
        .welcome-text {
            margin-right: 15px;
        }
        
        .logout-btn {
            background-color: var(--secondary);
            color: white;
            padding: 6px 12px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-size: 0.85rem;
        }
        
        .logout-btn:hover {
            background-color: #c0392b;
            color: white;
            text-decoration: none;
        }
        
        /* Filters */
        .filters {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 12px;
            margin-bottom: 15px;
            box-shadow: var(--box-shadow);
        }
        
        .filters .form-group {
            margin-bottom: 0;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        
        /* Tables */
        .table-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .table-responsive {
            flex: 1;
            overflow: auto;
        }
        
        .table {
            margin-bottom: 0;
            width: 100%;
        }
        
        .table th {
            background-color: var(--gray-light);
            border-top: none;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table th, .table td {
            vertical-align: middle;
            padding: 0.5rem 0.75rem;
        }
        
        /* Status badges */
        .status {
            padding: 4px 8px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-active {
            background-color: #DCFCE7;
            color: #166534;
        }
        
        .status-inactive {
            background-color: #FEF9C3;
            color: #854D0E;
        }
        
        .status-suspended {
            background-color: #F87171;
            color: white;
        }
        
        /* Buttons */
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-view {
            background-color: var(--primary);
            color: white;
            padding: 4px 10px;
            border-radius: var(--border-radius);
            font-size: 0.85rem;
        }
        
        .btn-view:hover {
            background-color: var(--primary-dark);
            color: white;
            text-decoration: none;
        }
        
        /* Pagination */
        .pagination-container {
            padding: 10px;
            background-color: var(--white);
            border-top: 1px solid var(--gray-light);
        }
        
        .pagination {
            margin: 0;
        }
        
        .page-link {
            color: var(--primary);
        }
        
        .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
                overflow: hidden;
            }
            
            .sidebar-logo {
                padding: 10px 5px;
                font-size: 1.2rem;
            }
            
            .sidebar-menu a span {
                display: none;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-left: 60px;
                width: calc(100% - 60px);
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-logo">
                <i class="fas fa-paint-brush"></i> Terral
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="products.php"><i class="fas fa-box"></i> <span>Products</span></a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> <span>Categories</span></a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a></li>
                <li><a href="customers.php" class="active"><i class="fas fa-users"></i> <span>Customers</span></a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="main-header">
                <h1 class="page-title">Manage Customers</h1>
                <div class="user-actions">
                    <span class="welcome-text">Welcome, <?php echo isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'Admin'; ?></span>
                    <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="filters">
                <form action="" method="GET" class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" id="status" class="form-control form-control-sm">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="search">Search</label>
                            <input type="text" name="search" id="search" class="form-control form-control-sm" placeholder="Name, Email" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="sort_by">Sort By</label>
                            <select name="sort_by" id="sort_by" class="form-control form-control-sm">
                                <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>Registration Date</option>
                                <option value="last_name" <?php echo $sortBy === 'last_name' ? 'selected' : ''; ?>>Name</option>
                                <option value="email" <?php echo $sortBy === 'email' ? 'selected' : ''; ?>>Email</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="form-group">
                            <label for="sort_order">Order</label>
                            <select name="sort_order" id="sort_order" class="form-control form-control-sm">
                                <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>DESC</option>
                                <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>ASC</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="customers.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-sync"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Customers Table -->
            <div class="table-card">
                <div class="table-responsive">
                    <?php if (count($customers) > 0): ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td><?php echo $customer['id']; ?></td>
                                        <td><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td><?php echo !empty($customer['phone']) ? htmlspecialchars($customer['phone']) : 'N/A'; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                        <td>
                                            <span class="status status-<?php echo $customer['status'] ?? 'active'; ?>">
                                                <?php echo ucfirst($customer['status'] ?? 'Active'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="customer-details.php?id=<?php echo $customer['id']; ?>" class="btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info m-3">
                            <i class="fas fa-info-circle"></i> No customers found. Try adjusting your filters.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <div class="d-flex justify-content-center">
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo buildQueryString($page - 1); ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Determine the range of pages to show
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    // Always show first page
                                    if ($startPage > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?' . buildQueryString(1) . '">1</a></li>';
                                        if ($startPage > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }
                                    
                                    // Show page numbers
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?' . buildQueryString($i) . '">' . $i . '</a></li>';
                                    }
                                    
                                    // Always show last page
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?' . buildQueryString($totalPages) . '">' . $totalPages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo buildQueryString($page + 1); ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
