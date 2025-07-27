<?php
// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/models/User.php';

// Start session
session_start();

// Check for redirect parameter in URL
if (isset($_GET['redirect']) && $_GET['redirect'] === 'checkout') {
    $_SESSION['redirect_after_login'] = 'checkout.php';
}

// Initialize variables
$errors = [];
$email = '';
$password = '';
$success_message = '';

// Check for registration success message in session
if (isset($_SESSION['registration_success'])) {
    $success_message = $_SESSION['registration_success'];
    // Clear the message from session after displaying it
    unset($_SESSION['registration_success']);
}

// Check for password reset success message in session
if (isset($_SESSION['password_reset_success'])) {
    $success_message = $_SESSION['password_reset_success'];
    // Clear the message from session after displaying it
    unset($_SESSION['password_reset_success']);
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }
    
    // If no validation errors, attempt login
    if (empty($errors)) {
        try {
            // Create database connection
            $database = new Database();
            $db = $database->getConnection();
            
            // Create user object and check if email exists
            $user = new User($db);
            
            // Check if email exists in database
            $query = "SELECT id, first_name, last_name, email, password, role FROM users WHERE email = :email LIMIT 0,1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Debugging - Uncomment these lines to see what's going on
                /*
                echo "<div style='background: #f5f5f5; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc;'>";
                echo "<strong>Debugging Information:</strong><br>";
                echo "Form Password: " . $password . "<br>";
                echo "DB Password: " . $row['password'] . "<br>";
                echo "Verification Result: " . (password_verify($password, $row['password']) ? 'true' : 'false') . "<br>";
                echo "Row Data: <pre>" . print_r($row, true) . "</pre>";
                echo "</div>";
                */
                
                // Verify password with proper verification
                if (password_verify($password, $row['password'])) {
                    // Password is correct, set user session
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['user_name'] = $row['first_name'] . ' ' . $row['last_name'];
                    $_SESSION['user_email'] = $row['email'];
                    $_SESSION['role'] = $row['role'];
                    
                    // Set complete user data in session for checkout and other pages
                    $_SESSION['user'] = $row;
                    
                    // Process cart data if it exists (for checkout redirection)
                    if (isset($_POST['cart_data']) && !empty($_POST['cart_data'])) {
                        $_SESSION['cart'] = json_decode($_POST['cart_data'], true);
                    }
                    
                    // Redirect based on role and if there's a redirect URL saved
                    if (isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
                        $redirect = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']); // Clear the redirect
                        header('Location: ' . $redirect);
                    } else if ($row['role'] === 'admin') {
                        header('Location: admin/dashboard.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit;
                } else {
                    // Password is incorrect
                    $errors['login'] = 'Invalid email or password';
                }
            } else {
                // Email does not exist
                $errors['login'] = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Terral Online Production System</title>
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
        
        /* Main Content */
        .main-content {
            padding: 50px 0;
            min-height: calc(100vh - 200px);
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 40px;
            font-size: 2.5rem;
            color: var(--text-dark);
        }
        
        /* Login Form */
        .login-form-container {
            max-width: 500px;
            margin: 0 auto;
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 40px;
        }
        
        .form-title {
            font-size: 1.8rem;
            margin-bottom: 30px;
            color: var(--text-dark);
            border-bottom: 2px solid var(--primary);
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray);
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
            color: var(--text-dark);
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .error-message {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .success-message {
            background-color: var(--success);
            color: var(--white);
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .general-error {
            background-color: var(--secondary);
            color: var(--white);
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .form-submit {
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius);
            padding: 15px 25px;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: block;
            width: 100%;
            margin-top: 30px;
        }
        
        .form-submit:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: var(--text-light);
        }
        
        .register-link a {
            color: var(--primary);
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: 10px;
        }
        
        .forgot-password a {
            color: var(--text-light);
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .forgot-password a:hover {
            color: var(--primary);
        }
        
        /* Footer */
        .footer {
            background-color: var(--text-dark);
            color: var(--white);
            padding: 30px 0;
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
                    <a href="login.php"><i class="fas fa-user"></i></a>
                </div>
            </nav>
        </div>
    </header>
    
    <main class="main-content">
        <div class="container">
            <h1 class="page-title">Login to Your Account</h1>
            
            <div class="login-form-container">
                <?php if (!empty($success_message)): ?>
                    <div class="success-message">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['checkout_pending']) && $_SESSION['checkout_pending']): ?>
                    <div class="general-info" style="background-color: #d1ecf1; color: #0c5460; padding: 15px 20px; border-radius: var(--border-radius); margin-bottom: 20px; text-align: center;">
                        <i class="fas fa-info-circle"></i> Please login to continue to checkout and complete your purchase.
                    </div>
                    <?php unset($_SESSION['checkout_pending']); ?>
                <?php endif; ?>
                
                <?php if (isset($errors['general'])): ?>
                    <div class="general-error">
                        <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errors['login'])): ?>
                    <div class="general-error">
                        <?php echo $errors['login']; ?>
                    </div>
                <?php endif; ?>
                
                <form action="login.php" method="POST" novalidate>
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <span class="error-message"><?php echo $errors['email']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <?php if (isset($errors['password'])): ?>
                            <span class="error-message"><?php echo $errors['password']; ?></span>
                        <?php endif; ?>
                        
                        <div class="forgot-password">
                            <a href="forgot-password.php">Forgot your password?</a>
                        </div>
                    </div>
                    
                    <button type="submit" class="form-submit">Login</button>
                    
                    <div class="register-link">
                        Don't have an account? <a href="register.php">Register here</a>
                    </div>
                </form>
            </div>
        </div>
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
            
            // Check if we need to redirect to checkout after login
            <?php if (isset($_SESSION['redirect_after_login']) && $_SESSION['redirect_after_login'] === 'checkout.php'): ?>
            // Add a hidden form to submit cart data
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Get cart data from localStorage
                    const cart = JSON.parse(localStorage.getItem('cart')) || [];
                    
                    // Add cart data as a hidden input
                    const cartInput = document.createElement('input');
                    cartInput.type = 'hidden';
                    cartInput.name = 'cart_data';
                    cartInput.value = JSON.stringify(cart);
                    form.appendChild(cartInput);
                });
            }
            <?php endif; ?>
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