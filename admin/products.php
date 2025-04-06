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
require_once ROOT_PATH . '/api/models/Product.php';
require_once ROOT_PATH . '/api/models/Category.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Initialize models
$product = new Product($db);
$category = new Category($db);

// Set default pagination values
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get filter values
$category_id = isset($_GET['category_id']) ? $_GET['category_id'] : null;
$search = isset($_GET['search']) ? $_GET['search'] : null;

// Get products
$stmt = $product->readPaginated($records_per_page, $offset, $category_id, $search);
$num = $stmt->rowCount();

// Get total product count for pagination
$total_rows = $product->getCount($category_id, $search);
$total_pages = ceil($total_rows / $records_per_page);

// Get all categories for filter dropdown
$categories = $category->read();

// Check for success/error messages
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle delete product
if (isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    $product_id = $_POST['product_id'];
    
    // Set product ID
    $product->id = $product_id;
    
    // Check if force delete is requested
    $force_delete = isset($_POST['force_delete']) && $_POST['force_delete'] === 'true';
    
    // Attempt to delete
    try {
        if ($product->delete($force_delete)) {
            $_SESSION['success_message'] = $force_delete ? 
                "Product permanently deleted successfully." : 
                "Product deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete product.";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to refresh the page
    header("Location: products.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Terral Admin</title>
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
        
        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        /* Filter Bar */
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background-color: var(--white);
            padding: 15px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .search-bar {
            display: flex;
            align-items: center;
            flex: 1;
            max-width: 500px;
        }
        
        .search-bar input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius) 0 0 var(--border-radius);
            outline: none;
            font-size: 1rem;
        }
        
        .search-bar button {
            padding: 10px 15px;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .search-bar button:hover {
            background-color: var(--primary-dark);
        }
        
        .filter-options {
            display: flex;
            align-items: center;
        }
        
        .filter-options select {
            padding: 10px;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            margin-left: 10px;
            outline: none;
            font-size: 0.9rem;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
        }
        
        .alert-success {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #842029;
        }
        
        /* Products Table */
        .products-table {
            width: 100%;
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .products-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .products-table th,
        .products-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .products-table th {
            background-color: var(--gray-light);
            font-weight: 500;
        }
        
        .products-table tr:last-child td {
            border-bottom: none;
        }
        
        .products-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        .stock-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .in-stock {
            background-color: #DCFCE7;
            color: #166534;
        }
        
        .low-stock {
            background-color: #FEF9C3;
            color: #854D0E;
        }
        
        .out-of-stock {
            background-color: #FEE2E2;
            color: #991B1B;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
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
        
        .btn-edit {
            background-color: var(--warning);
            color: var(--white);
        }
        
        .btn-edit:hover {
            background-color: #d35400;
        }
        
        .btn-delete {
            background-color: var(--danger);
            color: var(--white);
        }
        
        .btn-delete:hover {
            background-color: #c0392b;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        .pagination .page-item {
            list-style: none;
            margin: 0 5px;
        }
        
        .pagination .page-link {
            display: block;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            background-color: var(--white);
            color: var(--text-dark);
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .pagination .page-link:hover {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .pagination .active .page-link {
            background-color: var(--primary);
            color: var(--white);
        }
        
        /* No Products Message */
        .no-products {
            text-align: center;
            padding: 50px;
            color: var(--text-light);
        }
        
        /* Delete Confirmation Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            max-width: 500px;
            width: 100%;
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .modal-close {
            background-color: var(--gray);
            color: var(--text-dark);
        }
        
        .modal-close:hover {
            background-color: var(--gray-light);
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
                    <a href="products.php" class="active">
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
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Products</h1>
                <a href="add-product.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Product
                </a>
            </div>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filter Bar -->
            <form action="products.php" method="GET" class="filter-bar">
                <div class="search-bar">
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    <button type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                
                <div class="filter-options">
                    <label for="category">Category:</label>
                    <select name="category_id" id="category" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php while ($cat = $categories->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
            
            <!-- Products Table -->
            <div class="products-table">
                <?php if ($num > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th width="80">Image</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo !empty($row['image']) ? '../uploads/products/' . $row['image'] : '../assets/img/product-placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" class="product-image">
                                    </td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                    <td>KSh <?php echo number_format($row['price'], 2); ?></td>
                                    <td>
                                        <?php 
                                        $stock_class = '';
                                        if ($row['stock'] <= 0) {
                                            $stock_class = 'out-of-stock';
                                            $stock_text = 'Out of Stock';
                                        } elseif ($row['stock'] <= 5) {
                                            $stock_class = 'low-stock';
                                            $stock_text = 'Low Stock: ' . $row['stock'];
                                        } else {
                                            $stock_class = 'in-stock';
                                            $stock_text = 'In Stock: ' . $row['stock'];
                                        }
                                        ?>
                                        <span class="stock-status <?php echo $stock_class; ?>"><?php echo $stock_text; ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit-product.php?id=<?php echo $row['id']; ?>" class="action-btn btn-edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <div class="btn-group">
                                                <button onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>')" 
                                                        class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                                <button onclick="confirmForceDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>')" 
                                                        class="btn btn-danger btn-sm" style="background-color: #c0392b;">
                                                    <i class="fas fa-skull"></i> Force Delete
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-products">
                        <p>No products found. <a href="add-product.php">Add a new product</a>.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1<?php echo !empty($category_id) ? '&category_id=' . $category_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">First</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($category_id) ? '&category_id=' . $category_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($category_id) ? '&category_id=' . $category_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($category_id) ? '&category_id=' . $category_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($category_id) ? '&category_id=' . $category_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Last</a>
                        </li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Confirm Delete</h2>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the product "<span id="productName"></span>"?</p>
                <p>This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="product_id" id="deleteProductId">
                    <input type="hidden" name="delete_product" value="1">
                    <button type="button" class="btn modal-close" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Hidden delete form -->
    <form id="delete_form" method="POST" style="display: none;">
        <input type="hidden" name="delete_product" value="1">
        <input type="hidden" id="delete_product_id" name="product_id" value="">
        <input type="hidden" id="force_delete" name="force_delete" value="false">
    </form>
    
    <script>
        // Show delete confirmation modal
        function confirmDelete(productId, productName) {
            if (confirm('Are you sure you want to delete "' + productName + '"?\n\nNote: If this product has orders, it will be soft deleted (marked as deleted but retained in the database).\n\nPress OK to proceed with normal delete, or Cancel to abort.')) {
                document.getElementById('delete_product_id').value = productId;
                document.getElementById('force_delete').value = 'false';
                document.getElementById('delete_form').submit();
            }
        }
        
        function confirmForceDelete(productId, productName) {
            if (confirm('WARNING: Are you sure you want to FORCE DELETE "' + productName + '"?\n\nThis will permanently delete the product and all its order history.\nThis action cannot be undone!\n\nPress OK to permanently delete, or Cancel to abort.')) {
                document.getElementById('delete_product_id').value = productId;
                document.getElementById('force_delete').value = 'true';
                document.getElementById('delete_form').submit();
            }
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal if clicked outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>