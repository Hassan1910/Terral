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

// Create database connection                                <img src="<?php echo '../uploads/products/' . $form_data['current_image']; ?>" alt="Current Product Image" class="current-image">$database = new Database();
$db = $database->getConnection();

// Initialize models
$product = new Product($db);
$category = new Category($db);

// Get all categories for dropdown
$categories = $category->read();

// Initialize variables
$success_message = '';
$error_message = '';
$form_data = [
    'name' => '',
    'description' => '',
    'price' => '',
    'stock' => '',
    'category_id' => '',
    'current_image' => '',
    'customizable' => '0',
    'featured' => '0'
];

// Check if id is set in URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'Product ID is required';
    header('Location: products.php');
    exit;
}

// Get product ID from URL
$product_id = intval($_GET['id']);
$product->id = $product_id;

// Get product details
if (!$product->readOne()) {
    $_SESSION['error_message'] = 'Product not found';
    header('Location: products.php');
    exit;
}

// Populate form data with product details
$form_data = [
    'name' => $product->name,
    'description' => $product->description,
    'price' => $product->price,
    'stock' => $product->stock,
    'category_id' => $product->category_id ?? '',
    'current_image' => $product->image,
    'customizable' => $product->is_customizable ?? '0',
    'featured' => $product->status == 'featured' ? '1' : '0'
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'price' => floatval($_POST['price'] ?? 0),
        'stock' => intval($_POST['stock'] ?? 0),
        'category_id' => intval($_POST['category_id'] ?? 0),
        'current_image' => $_POST['current_image'] ?? '',
        'customizable' => isset($_POST['customizable']) ? '1' : '0',
        'featured' => isset($_POST['featured']) ? '1' : '0'
    ];
    
    // Validate input
    $errors = [];
    
    if (empty($form_data['name'])) {
        $errors['name'] = 'Product name is required';
    }
    
    if (empty($form_data['description'])) {
        $errors['description'] = 'Product description is required';
    }
    
    if ($form_data['price'] <= 0) {
        $errors['price'] = 'Price must be greater than zero';
    }
    
    if ($form_data['stock'] < 0) {
        $errors['stock'] = 'Stock cannot be negative';
    }
    
    if ($form_data['category_id'] <= 0) {
        $errors['category_id'] = 'Please select a category';
    }
    
    // Handle image upload if provided
    $image_name = $form_data['current_image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = ROOT_PATH . '/api/uploads/products/';
        
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
            $errors['image'] = 'Only JPG, JPEG, PNG, GIF, and WEBP files are allowed';
        }
        
        // Validate file size (max 5MB)
        if ($file_size > 5242880) {
            $errors['image'] = 'File size must be less than 5MB';
        }
        
        // Generate unique filename
        $image_name = uniqid('product_') . '.' . $file_ext;
        $target_file = $upload_dir . $image_name;
        
        // Upload file
        if (empty($errors['image']) && !move_uploaded_file($file_tmp, $target_file)) {
            $errors['image'] = 'Failed to upload image. Please try again.';
            $image_name = $form_data['current_image'];
        } else if (empty($errors['image']) && !empty($form_data['current_image'])) {
            // Delete old image if new one is uploaded
            $old_image_path = $upload_dir . $form_data['current_image'];
            if (file_exists($old_image_path)) {
                @unlink($old_image_path);
            }
        }
    }
    
    // If no errors, update product
    if (empty($errors)) {
        // Set product properties
        $product->id = $product_id;
        $product->name = $form_data['name'];
        $product->description = $form_data['description'];
        $product->price = $form_data['price'];
        $product->stock = $form_data['stock'];
        $product->category_id = $form_data['category_id'];
        $product->image = $image_name;
        $product->is_customizable = $form_data['customizable'];
        $product->status = $form_data['featured'] === '1' ? 'featured' : 'active';
        
        // Set an empty categories array to prevent the addCategories method from running
        $product->categories = [];
        
        // Update product
        if ($product->update()) {
            // Redirect to products page with success message
            $_SESSION['success_message'] = 'Product updated successfully.';
            header('Location: products.php');
            exit;
        } else {
            $error_message = 'Failed to update product. Please try again.';
        }
    } else {
        // Combine errors into a single message
        $error_message = 'Please fix the following errors:<br>';
        foreach ($errors as $error) {
            $error_message .= '- ' . $error . '<br>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Terral Admin</title>
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
        
        .btn-secondary {
            background-color: var(--gray);
            color: var(--text-dark);
        }
        
        .btn-secondary:hover {
            background-color: var(--gray-light);
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
        
        /* Form Styles */
        .form-container {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.25);
        }
        
        .form-text {
            display: block;
            margin-top: 5px;
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .form-check-input {
            margin-right: 10px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-col {
            flex: 1;
        }
        
        .current-image {
            display: block;
            max-width: 200px;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            border: 1px solid var(--gray);
        }
        
        .image-preview {
            margin-top: 15px;
            display: none;
            max-width: 200px;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
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
                <h1 class="page-title">Edit Product: <?php echo htmlspecialchars($form_data['name']); ?></h1>
                <a href="products.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Products
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
            
            <div class="form-container">
                <form action="edit-product.php?id=<?php echo $product_id; ?>" method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="name" class="form-label">Product Name *</label>
                                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($form_data['name']); ?>" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="category_id" class="form-label">Category *</label>
                                <select id="category_id" name="category_id" class="form-control" required>
                                    <option value="">Select a category</option>
                                    <?php 
                                    // Reset the categories result set pointer
                                    $categories->execute();
                                    while ($cat = $categories->fetch(PDO::FETCH_ASSOC)): 
                                    ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $form_data['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description *</label>
                        <textarea id="description" name="description" class="form-control" required><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="price" class="form-label">Price (KSh) *</label>
                                <input type="number" id="price" name="price" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars($form_data['price']); ?>" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="stock" class="form-label">Stock Quantity *</label>
                                <input type="number" id="stock" name="stock" class="form-control" min="0" value="<?php echo htmlspecialchars($form_data['stock']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="image" class="form-label">Product Image</label>
                        <?php if (!empty($form_data['current_image'])): ?>
                            <div>
                                <p>Current image:</p>
                                <img src="<?php echo '../uploads/products/' . $form_data['current_image']; ?>" alt="Current Product Image" class="current-image">
                                <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($form_data['current_image']); ?>">
                            </div>
                        <?php endif; ?>
                        <input type="file" id="image" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                        <small class="form-text">Leave empty to keep current image. Recommended size: 800x800 pixels. Max file size: 5MB.</small>
                        <img id="imagePreview" src="#" alt="Image Preview" class="image-preview">
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" id="customizable" name="customizable" class="form-check-input" value="1" <?php echo $form_data['customizable'] === '1' ? 'checked' : ''; ?>>
                            <label for="customizable" class="form-check-label">Product is customizable</label>
                        </div>
                        <small class="form-text">Enable if customers can upload custom images or text for this product.</small>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" id="featured" name="featured" class="form-check-input" value="1" <?php echo $form_data['featured'] === '1' ? 'checked' : ''; ?>>
                            <label for="featured" class="form-check-label">Feature on Homepage</label>
                        </div>
                        <small class="form-text">Display this product in the featured section on the homepage.</small>
                    </div>
                    
                    <div class="form-actions">
                        <a href="products.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Image preview function
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            preview.style.display = input.files && input.files[0] ? 'block' : 'none';
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>