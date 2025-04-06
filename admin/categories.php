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
require_once ROOT_PATH . '/api/models/Category.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Initialize models
$category = new Category($db);

// Set default pagination values
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get search value
$search = isset($_GET['search']) ? $_GET['search'] : null;

// Get categories
$stmt = $category->read();
$num = $stmt->rowCount();

// Get total category count for pagination
$total_rows = $category->getCount();
$total_pages = ceil($total_rows / $records_per_page);

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

// Handle delete category
if (isset($_POST['delete_category']) && isset($_POST['category_id'])) {
    $category_id = $_POST['category_id'];
    
    // Set category ID
    $category->id = $category_id;
    
    // Check if category has products
    if ($category->hasProducts()) {
        $_SESSION['error_message'] = "Cannot delete category. It has associated products.";
    } else {
        // Attempt to delete
        if ($category->delete()) {
            $_SESSION['success_message'] = "Category deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete category.";
        }
    }
    
    // Redirect to refresh the page
    header("Location: categories.php");
    exit;
}

// Handle add/edit category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    // Set category properties
    $category->name = $_POST['name'];
    $category->description = $_POST['description'];
    
    // Handle image upload if provided
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = ROOT_PATH . '/api/uploads/categories/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Get file info
        $file_name = $_FILES['image']['name'];
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_size = $_FILES['image']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Allowed extensions
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Validate file extension
        if (!in_array($file_ext, $allowed_extensions)) {
            $_SESSION['error_message'] = 'Only JPG, JPEG, PNG, GIF, and WEBP files are allowed';
        }
        // Validate file size (max 5MB)
        else if ($file_size > 5242880) {
            $_SESSION['error_message'] = 'File size must be less than 5MB';
        } else {
            // Generate unique filename
            $image_name = uniqid('category_') . '.' . $file_ext;
            $target_file = $upload_dir . $image_name;
            
            // Upload file
            if (move_uploaded_file($file_tmp, $target_file)) {
                // Set the image path for the category
                $category->image = 'api/uploads/categories/' . $image_name;
            } else {
                $_SESSION['error_message'] = 'Failed to upload image. Please try again.';
            }
        }
    }
    
    if (isset($_POST['category_id']) && !empty($_POST['category_id'])) {
        // Update existing category
        $category->id = $_POST['category_id'];
        $category->updated_at = date('Y-m-d H:i:s');
        
        if ($category->update()) {
            $_SESSION['success_message'] = "Category updated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to update category.";
        }
    } else {
        // Create new category
        $category->created_at = date('Y-m-d H:i:s');
        
        if ($category->create()) {
            $_SESSION['success_message'] = "Category created successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to create category.";
        }
    }
    
    // Redirect to refresh the page
    header("Location: categories.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Terral Admin</title>
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
        
        /* Categories Table */
        .categories-table {
            width: 100%;
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .categories-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .categories-table th,
        .categories-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .categories-table th {
            background-color: var(--gray-light);
            font-weight: 500;
        }
        
        .categories-table tr:last-child td {
            border-bottom: none;
        }
        
        .categories-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
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
        
        /* No Categories Message */
        .no-categories {
            text-align: center;
            padding: 50px;
            color: var(--text-light);
        }
        
        /* Category Form */
        .category-form {
            background-color: var(--white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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
        
        /* Image upload styles */
        .image-upload-container {
            margin-bottom: 20px;
        }
        
        .image-preview {
            width: 150px;
            height: 150px;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 10px;
            background-color: var(--gray-light);
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .image-upload-input {
            display: none;
        }
        
        .image-upload-label {
            display: inline-block;
            background-color: var(--primary);
            color: var(--white);
            padding: 8px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .image-upload-label:hover {
            background-color: var(--primary-dark);
        }
        
        .image-filename {
            margin-top: 5px;
            font-size: 0.85rem;
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
                    <a href="categories.php" class="active">
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
                <h1 class="page-title">Categories</h1>
                <button type="button" class="btn btn-primary" onclick="showAddCategoryForm()">
                    <i class="fas fa-plus"></i> Add New Category
                </button>
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
            
            <!-- Category Form -->
            <div id="categoryForm" class="category-form" style="display: none;">
                <h2 id="formTitle">Add New Category</h2>
                <form action="categories.php" method="POST" enctype="multipart/form-data" id="category-form">
                    <input type="hidden" name="category_id" id="categoryId" value="">
                    <div class="form-group">
                        <label for="name">Category Name</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Category Image</label>
                        <div class="image-upload-container">
                            <div class="image-preview" id="category-image-preview">
                                <img src="../assets/images/placeholder.jpg" alt="Preview" id="image-preview-img">
                            </div>
                            <label for="category-image-upload" class="image-upload-label">
                                <i class="fas fa-upload"></i> Choose Image
                            </label>
                            <input type="file" id="category-image-upload" name="image" class="image-upload-input" accept="image/jpeg, image/jpg, image/png, image/gif, image/webp">
                            <div class="image-filename" id="image-filename">No file chosen</div>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn modal-close" onclick="hideForm()">Cancel</button>
                        <button type="submit" name="save_category" class="btn btn-primary">Save Category</button>
                    </div>
                </form>
            </div>
            
            <!-- Filter Bar -->
            <form action="categories.php" method="GET" class="filter-bar">
                <div class="search-bar">
                    <input type="text" name="search" placeholder="Search categories..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    <button type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            
            <!-- Categories Table -->
            <div class="categories-table">
                <?php if ($num > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td>
                                        <?php if (isset($row['image']) && !empty($row['image'])): ?>
                                            <img src="<?php echo '../' . $row['image']; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background-color: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-image" style="color: #999;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="action-btn btn-edit" onclick="editCategory(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>', '<?php echo addslashes($row['description']); ?>', '<?php echo isset($row['image']) ? addslashes($row['image']) : ''; ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="action-btn btn-delete" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-categories">
                        <p>No categories found. <button type="button" onclick="showAddCategoryForm()" class="btn btn-primary">Add a new category</button></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">First</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Last</a>
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
                <p>Are you sure you want to delete the category "<span id="categoryName"></span>"?</p>
                <p>This action cannot be undone. If this category has associated products, it cannot be deleted.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="category_id" id="deleteCategoryId">
                    <input type="hidden" name="delete_category" value="1">
                    <button type="button" class="btn modal-close" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Show add category form
        function showAddCategoryForm() {
            document.getElementById('formTitle').textContent = 'Add New Category';
            document.getElementById('categoryId').value = '';
            document.getElementById('name').value = '';
            document.getElementById('description').value = '';
            document.getElementById('image-preview-img').src = '../assets/images/placeholder.jpg';
            document.getElementById('image-filename').textContent = 'No file chosen';
            document.getElementById('categoryForm').style.display = 'block';
            document.getElementById('name').focus();
        }
        
        // Show edit category form
        function editCategory(id, name, description, image) {
            document.getElementById('formTitle').textContent = 'Edit Category';
            document.getElementById('categoryId').value = id;
            document.getElementById('name').value = name;
            document.getElementById('description').value = description;
            
            // Set image preview if available
            if (image) {
                document.getElementById('image-preview-img').src = '../' + image;
                document.getElementById('image-filename').textContent = image.split('/').pop();
            } else {
                document.getElementById('image-preview-img').src = '../assets/images/placeholder.jpg';
                document.getElementById('image-filename').textContent = 'No file chosen';
            }
            
            document.getElementById('categoryForm').style.display = 'block';
            document.getElementById('name').focus();
        }
        
        // Handle image upload preview
        document.addEventListener('DOMContentLoaded', function() {
            const imageInput = document.getElementById('category-image-upload');
            const imagePreview = document.getElementById('image-preview-img');
            const filenameDisplay = document.getElementById('image-filename');
            
            imageInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    filenameDisplay.textContent = file.name;
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                } else {
                    imagePreview.src = '../assets/images/placeholder.jpg';
                    filenameDisplay.textContent = 'No file chosen';
                }
            });
        });
        
        // Hide form
        function hideForm() {
            document.getElementById('categoryForm').style.display = 'none';
        }
        
        // Show delete confirmation modal
        function confirmDelete(categoryId, categoryName) {
            document.getElementById('deleteCategoryId').value = categoryId;
            document.getElementById('categoryName').textContent = categoryName;
            document.getElementById('deleteModal').style.display = 'flex';
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