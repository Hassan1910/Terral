<?php
// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/models/User.php';
require_once ROOT_PATH . '/api/helpers/NotificationHelper.php';

// Start session
session_start();

// Initialize variables
$errors = [];
$success_message = '';
$step = 1; // Step 1: Email verification, Step 2: Password reset
$email = '';
$verified_email = '';

// Check if we're in step 2 (password reset)
if (isset($_SESSION['reset_email']) && !empty($_SESSION['reset_email'])) {
    $step = 2;
    $verified_email = $_SESSION['reset_email'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // Step 1: Email verification
        $email = trim($_POST['email'] ?? '');
        
        // Validate email
        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        }
        
        // If no validation errors, check if email exists
        if (empty($errors)) {
            try {
                // Create database connection
                $database = new Database();
                $db = $database->getConnection();
                
                // Check if email exists in database
                $query = "SELECT id, first_name, last_name, email FROM users WHERE email = :email LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // Email exists, proceed to step 2
                    $_SESSION['reset_email'] = $email;
                    $step = 2;
                    $verified_email = $email;
                    $success_message = 'Email verified! Please enter your new password below.';
                } else {
                    $errors['email'] = 'No account found with this email address';
                }
            } catch (PDOException $e) {
                $errors['general'] = 'Database error occurred. Please try again.';
            }
        }
    } elseif ($step === 2) {
        // Step 2: Password reset
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $verified_email = $_SESSION['reset_email'];
        
        // Validate passwords
        if (empty($new_password)) {
            $errors['new_password'] = 'New password is required';
        } elseif (strlen($new_password) < 6) {
            $errors['new_password'] = 'Password must be at least 6 characters long';
        }
        
        if (empty($confirm_password)) {
            $errors['confirm_password'] = 'Please confirm your password';
        } elseif ($new_password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
        
        // If no validation errors, update password
        if (empty($errors)) {
            try {
                // Create database connection
                $database = new Database();
                $db = $database->getConnection();
                
                // Create user object and update password
                $user = new User($db);

                // Get user details first
                if ($user->emailExists($verified_email)) {
                    // Set user properties for password update
                    $user->email = $verified_email;
                    $user->password = $new_password;

                    // Get user ID for password update
                    $query = "SELECT id FROM users WHERE email = :email LIMIT 1";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':email', $verified_email);
                    $stmt->execute();
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $user->id = $row['id'];

                    if ($user->updatePassword()) {
                        // Password updated successfully
                        unset($_SESSION['reset_email']); // Clear session
                        
                        // Optional: Send email notification
                        try {
                            $notificationHelper = new NotificationHelper();
                            $email_subject = "Password Reset Successful - Terral";
                            $email_content = "
                                <h2>Password Reset Successful</h2>
                                <p>Your password has been successfully reset for your Terral account.</p>
                                <p>If you did not request this change, please contact our support team immediately.</p>
                                <p>Best regards,<br>Terral Team</p>
                            ";
                            $notificationHelper->sendEmail($verified_email, $email_subject, $email_content);
                        } catch (Exception $e) {
                            // Email sending failed, but password was still updated
                        }
                        
                        $_SESSION['password_reset_success'] = 'Your password has been successfully reset. You can now login with your new password.';
                        header('Location: login.php');
                        exit;
                    } else {
                        $errors['general'] = 'Failed to update password. Please try again.';
                    }
                } else {
                    $errors['general'] = 'User account not found.';
                }
            } catch (PDOException $e) {
                $errors['general'] = 'Database error occurred. Please try again.';
            }
        }
    }
}

// Handle cancel/start over
if (isset($_GET['action']) && $_GET['action'] === 'restart') {
    unset($_SESSION['reset_email']);
    $step = 1;
    $verified_email = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Terral Online Production System</title>
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
        
        /* Form Container */
        .form-container {
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
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            display: flex;
            align-items: center;
            margin: 0 10px;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 10px;
        }
        
        .step.active .step-number {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .step.inactive .step-number {
            background-color: var(--gray);
            color: var(--white);
        }
        
        .step.completed .step-number {
            background-color: var(--success);
            color: var(--white);
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
        
        .form-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .form-links a {
            color: var(--primary);
            font-weight: 500;
            margin: 0 10px;
        }
        
        .form-links a:hover {
            text-decoration: underline;
        }
        
        .info-text {
            background-color: var(--gray-light);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            color: var(--text-dark);
            font-size: 0.9rem;
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
            <h1 class="page-title">Reset Your Password</h1>

            <div class="form-container">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $step === 1 ? 'active' : ($step > 1 ? 'completed' : 'inactive'); ?>">
                        <div class="step-number">1</div>
                        <span>Verify Email</span>
                    </div>
                    <div class="step <?php echo $step === 2 ? 'active' : 'inactive'; ?>">
                        <div class="step-number">2</div>
                        <span>Reset Password</span>
                    </div>
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($errors['general'])): ?>
                    <div class="general-error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>

                <?php if ($step === 1): ?>
                    <!-- Step 1: Email Verification -->
                    <h2 class="form-title">Enter Your Email Address</h2>

                    <div class="info-text">
                        <i class="fas fa-info-circle"></i>
                        Enter the email address associated with your account. We'll verify it exists and then allow you to reset your password.
                    </div>

                    <form action="forgot-password.php" method="POST" novalidate>
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($email); ?>"
                                   placeholder="Enter your email address" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i> <?php echo $errors['email']; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="form-submit">
                            <i class="fas fa-search"></i> Verify Email
                        </button>
                    </form>

                <?php elseif ($step === 2): ?>
                    <!-- Step 2: Password Reset -->
                    <h2 class="form-title">Create New Password</h2>

                    <div class="info-text">
                        <i class="fas fa-user-check"></i>
                        Email verified: <strong><?php echo htmlspecialchars($verified_email); ?></strong><br>
                        Please enter your new password below.
                    </div>

                    <form action="forgot-password.php" method="POST" novalidate>
                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control"
                                   placeholder="Enter new password (minimum 6 characters)" required>
                            <?php if (isset($errors['new_password'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i> <?php echo $errors['new_password']; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                                   placeholder="Confirm your new password" required>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i> <?php echo $errors['confirm_password']; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="form-submit">
                            <i class="fas fa-key"></i> Reset Password
                        </button>
                    </form>

                    <div class="form-links">
                        <a href="forgot-password.php?action=restart">
                            <i class="fas fa-arrow-left"></i> Use Different Email
                        </a>
                    </div>

                <?php endif; ?>

                <div class="form-links">
                    <a href="login.php">
                        <i class="fas fa-sign-in-alt"></i> Back to Login
                    </a>
                    |
                    <a href="register.php">
                        <i class="fas fa-user-plus"></i> Create Account
                    </a>
                </div>
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
            // Cart functionality (if needed)
            function updateCartCount() {
                const cart = JSON.parse(localStorage.getItem('cart')) || [];
                const cartCount = cart.reduce((total, item) => total + item.quantity, 0);
                const cartCountElement = document.querySelector('.cart-count');
                if (cartCountElement) {
                    cartCountElement.textContent = cartCount;
                }
            }

            updateCartCount();

            // Password strength indicator (optional enhancement)
            const newPasswordInput = document.getElementById('new_password');
            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    const password = this.value;
                    // You can add password strength validation here
                });
            }

            // Confirm password validation
            const confirmPasswordInput = document.getElementById('confirm_password');
            if (confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', function() {
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = this.value;

                    if (confirmPassword && newPassword !== confirmPassword) {
                        this.style.borderColor = '#e74c3c';
                    } else {
                        this.style.borderColor = '#bdc3c7';
                    }
                });
            }
        });
    </script>
</body>
</html>
