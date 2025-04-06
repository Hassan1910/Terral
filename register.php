<?php
// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/models/User.php';

// Start session for flash messages
session_start();

// Initialize variables
$errors = [];
$success_message = '';
$form_data = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'city' => '',
    'postal_code' => '',
    'country' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $form_data = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'country' => trim($_POST['country'] ?? '')
    ];
    
    // Validate form data
    if (empty($form_data['first_name'])) {
        $errors['first_name'] = 'First name is required';
    }
    
    if (empty($form_data['last_name'])) {
        $errors['last_name'] = 'Last name is required';
    }
    
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    if (empty($form_data['phone'])) {
        $errors['phone'] = 'Phone number is required';
    }
    
    if (empty($form_data['password'])) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($form_data['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long';
    }
    
    if ($form_data['password'] !== $form_data['confirm_password']) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    if (empty($form_data['address'])) {
        $errors['address'] = 'Address is required';
    }
    
    if (empty($form_data['city'])) {
        $errors['city'] = 'City is required';
    }
    
    if (empty($form_data['postal_code'])) {
        $errors['postal_code'] = 'Postal code is required';
    }
    
    if (empty($form_data['country'])) {
        $errors['country'] = 'Country is required';
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            // Create database connection
            $database = new Database();
            $db = $database->getConnection();
            
            // Check if email already exists
            $user = new User($db);
            if ($user->emailExists($form_data['email'])) {
                $errors['email'] = 'Email already exists. Please use a different email address.';
            } else {
                // Create new user
                $user->first_name = $form_data['first_name'];
                $user->last_name = $form_data['last_name'];
                $user->email = $form_data['email'];
                $user->phone = $form_data['phone'];
                $user->password = password_hash($form_data['password'], PASSWORD_BCRYPT);
                $user->address = $form_data['address'];
                $user->city = $form_data['city'];
                $user->postal_code = $form_data['postal_code'];
                $user->country = $form_data['country'];
                $user->role = 'customer'; // Default role
                $user->created_at = date('Y-m-d H:i:s');
                
                if ($user->create()) {
                    // Redirect to login page with success message
                    $_SESSION['registration_success'] = 'Registration successful! You can now login with your new account.';
                    header('Location: login.php');
                    exit;
                } else {
                    $errors['general'] = 'Failed to create account. Please try again.';
                }
            }
        } catch (Exception $e) {
            $errors['general'] = 'An error occurred: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Terral Online Production System</title>
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
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 40px;
            font-size: 2.5rem;
            color: var(--text-dark);
        }
        
        /* Registration Form */
        .registration-form-container {
            max-width: 800px;
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
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
            min-width: 250px;
            margin-right: 20px;
            margin-bottom: 20px;
        }
        
        .form-group:last-child {
            margin-right: 0;
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
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: var(--text-light);
        }
        
        .login-link a {
            color: var(--primary);
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        /* Footer */
        .footer {
            background-color: var(--text-dark);
            color: var(--white);
            padding: 60px 0 30px;
            margin-top: 80px;
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 40px;
            padding-top: 20px;
            text-align: center;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .form-group {
                margin-right: 0;
                min-width: 100%;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .registration-form-container {
                padding: 30px 20px;
            }
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
                    <li><a href="index.php#products">Products</a></li>
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
            <h1 class="page-title">Create an Account</h1>
            
            <div class="registration-form-container">
                <?php if (!empty($success_message)): ?>
                    <div class="success-message">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errors['general'])): ?>
                    <div class="general-error">
                        <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>
                
                <h2 class="form-title">Your Information</h2>
                
                <form action="register.php" method="POST" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($form_data['first_name']); ?>" required>
                            <?php if (isset($errors['first_name'])): ?>
                                <span class="error-message"><?php echo $errors['first_name']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($form_data['last_name']); ?>" required>
                            <?php if (isset($errors['last_name'])): ?>
                                <span class="error-message"><?php echo $errors['last_name']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <span class="error-message"><?php echo $errors['email']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($form_data['phone']); ?>" required>
                            <?php if (isset($errors['phone'])): ?>
                                <span class="error-message"><?php echo $errors['phone']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                            <?php if (isset($errors['password'])): ?>
                                <span class="error-message"><?php echo $errors['password']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <span class="error-message"><?php echo $errors['confirm_password']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h2 class="form-title">Shipping Information</h2>
                    
                    <div class="form-row">
                        <div class="form-group" style="flex: 100%;">
                            <label for="address" class="form-label">Address *</label>
                            <input type="text" id="address" name="address" class="form-control" value="<?php echo htmlspecialchars($form_data['address']); ?>" required>
                            <?php if (isset($errors['address'])): ?>
                                <span class="error-message"><?php echo $errors['address']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city" class="form-label">City *</label>
                            <input type="text" id="city" name="city" class="form-control" value="<?php echo htmlspecialchars($form_data['city']); ?>" required>
                            <?php if (isset($errors['city'])): ?>
                                <span class="error-message"><?php echo $errors['city']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="postal_code" class="form-label">Postal Code *</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-control" value="<?php echo htmlspecialchars($form_data['postal_code']); ?>" required>
                            <?php if (isset($errors['postal_code'])): ?>
                                <span class="error-message"><?php echo $errors['postal_code']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" style="flex: 100%;">
                            <label for="country" class="form-label">Country *</label>
                            <select id="country" name="country" class="form-control" required>
                                <option value="">Select a country</option>
                                <option value="Kenya" <?php echo $form_data['country'] === 'Kenya' ? 'selected' : ''; ?>>Kenya</option>
                                <option value="Uganda" <?php echo $form_data['country'] === 'Uganda' ? 'selected' : ''; ?>>Uganda</option>
                                <option value="Tanzania" <?php echo $form_data['country'] === 'Tanzania' ? 'selected' : ''; ?>>Tanzania</option>
                                <option value="Rwanda" <?php echo $form_data['country'] === 'Rwanda' ? 'selected' : ''; ?>>Rwanda</option>
                                <option value="Burundi" <?php echo $form_data['country'] === 'Burundi' ? 'selected' : ''; ?>>Burundi</option>
                                <option value="South Sudan" <?php echo $form_data['country'] === 'South Sudan' ? 'selected' : ''; ?>>South Sudan</option>
                                <option value="Ethiopia" <?php echo $form_data['country'] === 'Ethiopia' ? 'selected' : ''; ?>>Ethiopia</option>
                                <option value="Somalia" <?php echo $form_data['country'] === 'Somalia' ? 'selected' : ''; ?>>Somalia</option>
                                <option value="Congo" <?php echo $form_data['country'] === 'Congo' ? 'selected' : ''; ?>>Congo</option>
                                <option value="Nigeria" <?php echo $form_data['country'] === 'Nigeria' ? 'selected' : ''; ?>>Nigeria</option>
                                <option value="Ghana" <?php echo $form_data['country'] === 'Ghana' ? 'selected' : ''; ?>>Ghana</option>
                                <option value="South Africa" <?php echo $form_data['country'] === 'South Africa' ? 'selected' : ''; ?>>South Africa</option>
                            </select>
                            <?php if (isset($errors['country'])): ?>
                                <span class="error-message"><?php echo $errors['country']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <button type="submit" class="form-submit">Create Account</button>
                    
                    <div class="login-link">
                        Already have an account? <a href="login.php">Login here</a>
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
            
            // Form validation
            const form = document.querySelector('form');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            form.addEventListener('submit', function(event) {
                let isValid = true;
                
                // Simple client-side validation (in addition to server-side)
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                    isValid = false;
                } else {
                    confirmPassword.setCustomValidity('');
                }
                
                if (!isValid) {
                    event.preventDefault();
                }
            });
            
            // Update password validation when typing
            confirmPassword.addEventListener('input', function() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            });
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