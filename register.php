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
    } elseif (!preg_match('/^[a-zA-Z\s\'.-]+$/', $form_data['first_name'])) {
        $errors['first_name'] = 'First name can only contain letters, spaces, apostrophes, and hyphens';
    }
    
    if (empty($form_data['last_name'])) {
        $errors['last_name'] = 'Last name is required';
    } elseif (!preg_match('/^[a-zA-Z\s\'.-]+$/', $form_data['last_name'])) {
        $errors['last_name'] = 'Last name can only contain letters, spaces, apostrophes, and hyphens';
    }
    
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    if (empty($form_data['phone'])) {
        $errors['phone'] = 'Phone number is required';
    } elseif (!preg_match('/^[\d\s\-\+\(\)\.]+$/', $form_data['phone'])) {
        $errors['phone'] = 'Phone number can only contain numbers, spaces, hyphens, parentheses, and plus sign';
    } elseif (strlen(preg_replace('/[^\d]/', '', $form_data['phone'])) < 10) {
        $errors['phone'] = 'Phone number must contain at least 10 digits';
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
    } elseif (!preg_match('/^[a-zA-Z\s\'.-]+$/', $form_data['city'])) {
        $errors['city'] = 'City name can only contain letters, spaces, apostrophes, and hyphens';
    }
    
    if (empty($form_data['postal_code'])) {
        $errors['postal_code'] = 'Postal code is required';
    } elseif (!preg_match('/^[a-zA-Z0-9\s\-]+$/', $form_data['postal_code'])) {
        $errors['postal_code'] = 'Postal code can only contain letters, numbers, spaces, and hyphens';
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
        
        .form-control:invalid {
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(231, 76, 60, 0.1);
        }
        
        .form-control:invalid:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(231, 76, 60, 0.2);
        }
        
        .form-control:valid {
            border-color: var(--success);
        }
        
        .form-control:valid:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 2px rgba(46, 204, 113, 0.2);
        }
        
        .validation-error {
            color: var(--secondary) !important;
            font-size: 0.9rem !important;
            margin-top: 5px !important;
            font-weight: 500 !important;
            display: block !important;
        }
        
        .form-group.has-error .form-control {
            border-color: var(--secondary) !important;
            box-shadow: 0 0 0 2px rgba(231, 76, 60, 0.1) !important;
        }
        
        .form-group.has-success .form-control {
            border-color: var(--success) !important;
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
                            <input type="text" id="first_name" name="first_name" class="form-control" 
                                   pattern="[a-zA-Z\s'.-]+" 
                                   title="First name can only contain letters, spaces, apostrophes, and hyphens"
                                   value="<?php echo htmlspecialchars($form_data['first_name']); ?>" required>
                            <?php if (isset($errors['first_name'])): ?>
                                <span class="error-message"><?php echo $errors['first_name']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" 
                                   pattern="[a-zA-Z\s'.-]+" 
                                   title="Last name can only contain letters, spaces, apostrophes, and hyphens"
                                   value="<?php echo htmlspecialchars($form_data['last_name']); ?>" required>
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
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   pattern="[\d\s\-\+\(\)\.]+" 
                                   title="Phone number can only contain numbers, spaces, hyphens, parentheses, and plus sign"
                                   value="<?php echo htmlspecialchars($form_data['phone']); ?>" required>
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
                            <input type="text" id="city" name="city" class="form-control" 
                                   pattern="[a-zA-Z\s'.-]+" 
                                   title="City name can only contain letters, spaces, apostrophes, and hyphens"
                                   value="<?php echo htmlspecialchars($form_data['city']); ?>" required>
                            <?php if (isset($errors['city'])): ?>
                                <span class="error-message"><?php echo $errors['city']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="postal_code" class="form-label">Postal Code *</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-control" 
                                   pattern="[a-zA-Z0-9\s\-]+" 
                                   title="Postal code can only contain letters, numbers, spaces, and hyphens"
                                   value="<?php echo htmlspecialchars($form_data['postal_code']); ?>" required>
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
            
            // Get form elements
            const form = document.querySelector('form');
            const firstName = document.getElementById('first_name');
            const lastName = document.getElementById('last_name');
            const phone = document.getElementById('phone');
            const city = document.getElementById('city');
            const postalCode = document.getElementById('postal_code');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            // Validation patterns
            const namePattern = /^[a-zA-Z\s'.-]+$/;
            const phonePattern = /^[\d\s\-\+\(\)\.]+$/;
            const postalPattern = /^[a-zA-Z0-9\s\-]+$/;
            
            // Function to show error message
            function showFieldError(field, message) {
                const formGroup = field.closest('.form-group');
                
                // Remove existing error message
                const existingError = formGroup.querySelector('.validation-error');
                if (existingError) {
                    existingError.remove();
                }
                
                // Remove existing classes
                formGroup.classList.remove('has-error', 'has-success');
                
                // Add new error message
                if (message) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'validation-error';
                    errorDiv.textContent = message;
                    formGroup.appendChild(errorDiv);
                    formGroup.classList.add('has-error');
                } else if (field.value !== '') {
                    formGroup.classList.add('has-success');
                }
            }
            
            // Function to validate name fields
            function validateNameField(field, message) {
                if (field.value === '') {
                    field.setCustomValidity('This field is required');
                    showFieldError(field, 'This field is required');
                } else if (!namePattern.test(field.value)) {
                    field.setCustomValidity(message);
                    showFieldError(field, message);
                } else {
                    field.setCustomValidity('');
                    showFieldError(field, '');
                }
            }
            
            // Function to validate phone field
            function validatePhoneField(field) {
                if (field.value === '') {
                    field.setCustomValidity('Phone number is required');
                    showFieldError(field, 'Phone number is required');
                } else if (!phonePattern.test(field.value)) {
                    field.setCustomValidity('Phone number can only contain numbers, spaces, hyphens, parentheses, and plus sign');
                    showFieldError(field, 'Phone number can only contain numbers, spaces, hyphens, parentheses, and plus sign');
                } else if (field.value.replace(/[^\d]/g, '').length < 10) {
                    field.setCustomValidity('Phone number must contain at least 10 digits');
                    showFieldError(field, 'Phone number must contain at least 10 digits');
                } else {
                    field.setCustomValidity('');
                    showFieldError(field, '');
                }
            }
            
            // Function to validate postal code
            function validatePostalCode(field) {
                if (field.value === '') {
                    field.setCustomValidity('Postal code is required');
                    showFieldError(field, 'Postal code is required');
                } else if (!postalPattern.test(field.value)) {
                    field.setCustomValidity('Postal code can only contain letters, numbers, spaces, and hyphens');
                    showFieldError(field, 'Postal code can only contain letters, numbers, spaces, and hyphens');
                } else {
                    field.setCustomValidity('');
                    showFieldError(field, '');
                }
            }
            
            // Real-time validation for first name
            firstName.addEventListener('input', function() {
                validateNameField(this, 'First name can only contain letters, spaces, apostrophes, and hyphens');
            });
            
            firstName.addEventListener('blur', function() {
                validateNameField(this, 'First name can only contain letters, spaces, apostrophes, and hyphens');
            });
            
            // Real-time validation for last name
            lastName.addEventListener('input', function() {
                validateNameField(this, 'Last name can only contain letters, spaces, apostrophes, and hyphens');
            });
            
            lastName.addEventListener('blur', function() {
                validateNameField(this, 'Last name can only contain letters, spaces, apostrophes, and hyphens');
            });
            
            // Real-time validation for phone
            phone.addEventListener('input', function() {
                validatePhoneField(this);
            });
            
            phone.addEventListener('blur', function() {
                validatePhoneField(this);
            });
            
            // Real-time validation for city
            city.addEventListener('input', function() {
                validateNameField(this, 'City name can only contain letters, spaces, apostrophes, and hyphens');
            });
            
            city.addEventListener('blur', function() {
                validateNameField(this, 'City name can only contain letters, spaces, apostrophes, and hyphens');
            });
            
            // Real-time validation for postal code
            postalCode.addEventListener('input', function() {
                validatePostalCode(this);
            });
            
            postalCode.addEventListener('blur', function() {
                validatePostalCode(this);
            });
            
            // Email validation
            document.getElementById('email').addEventListener('input', validateEmail);
            document.getElementById('email').addEventListener('blur', validateEmail);
            
            // Password validation
            password.addEventListener('input', validatePassword);
            password.addEventListener('blur', validatePassword);
            
            // Confirm password validation
            confirmPassword.addEventListener('input', validateConfirmPassword);
            confirmPassword.addEventListener('blur', validateConfirmPassword);
            
            // Address validation
            document.getElementById('address').addEventListener('input', validateAddress);
            document.getElementById('address').addEventListener('blur', validateAddress);
            
            // Country validation
            document.getElementById('country').addEventListener('change', validateCountry);
            
            // Form validation
            form.addEventListener('submit', function(event) {
                let isValid = true;
                let firstInvalidField = null;
                
                // Validate all fields
                const fields = [
                    { field: firstName, validator: () => validateNameField(firstName, 'First name can only contain letters, spaces, apostrophes, and hyphens') },
                    { field: lastName, validator: () => validateNameField(lastName, 'Last name can only contain letters, spaces, apostrophes, and hyphens') },
                    { field: document.getElementById('email'), validator: () => validateEmail() },
                    { field: phone, validator: () => validatePhoneField(phone) },
                    { field: password, validator: () => validatePassword() },
                    { field: confirmPassword, validator: () => validateConfirmPassword() },
                    { field: document.getElementById('address'), validator: () => validateAddress() },
                    { field: city, validator: () => validateNameField(city, 'City name can only contain letters, spaces, apostrophes, and hyphens') },
                    { field: postalCode, validator: () => validatePostalCode(postalCode) },
                    { field: document.getElementById('country'), validator: () => validateCountry() }
                ];
                
                fields.forEach(({ field, validator }) => {
                    validator();
                    if (!field.checkValidity()) {
                        isValid = false;
                        if (!firstInvalidField) {
                            firstInvalidField = field;
                        }
                    }
                });
                
                if (!isValid) {
                    event.preventDefault();
                    
                    // Scroll to first invalid field
                    if (firstInvalidField) {
                        firstInvalidField.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        firstInvalidField.focus();
                    }
                    
                    // Show general error message
                    showGeneralError('Please correct the highlighted errors below and try again.');
                }
            });
            
            // Additional validation functions
            function validateEmail() {
                const email = document.getElementById('email');
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email.value === '') {
                    email.setCustomValidity('Email is required');
                    showFieldError(email, 'Email is required');
                } else if (!emailPattern.test(email.value)) {
                    email.setCustomValidity('Please enter a valid email address');
                    showFieldError(email, 'Please enter a valid email address');
                } else {
                    email.setCustomValidity('');
                    showFieldError(email, '');
                }
            }
            
            function validatePassword() {
                if (password.value === '') {
                    password.setCustomValidity('Password is required');
                    showFieldError(password, 'Password is required');
                } else if (password.value.length < 8) {
                    password.setCustomValidity('Password must be at least 8 characters long');
                    showFieldError(password, 'Password must be at least 8 characters long');
                } else {
                    password.setCustomValidity('');
                    showFieldError(password, '');
                }
            }
            
            function validateConfirmPassword() {
                if (confirmPassword.value === '') {
                    confirmPassword.setCustomValidity('Please confirm your password');
                    showFieldError(confirmPassword, 'Please confirm your password');
                } else if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                    showFieldError(confirmPassword, 'Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                    showFieldError(confirmPassword, '');
                }
            }
            
            function validateAddress() {
                const address = document.getElementById('address');
                if (address.value === '') {
                    address.setCustomValidity('Address is required');
                    showFieldError(address, 'Address is required');
                } else {
                    address.setCustomValidity('');
                    showFieldError(address, '');
                }
            }
            
            function validateCountry() {
                const country = document.getElementById('country');
                if (country.value === '') {
                    country.setCustomValidity('Please select a country');
                    showFieldError(country, 'Please select a country');
                } else {
                    country.setCustomValidity('');
                    showFieldError(country, '');
                }
            }
            
            function showGeneralError(message) {
                // Remove existing general error
                const existingError = document.querySelector('.general-validation-error');
                if (existingError) {
                    existingError.remove();
                }
                
                // Add new general error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'general-validation-error';
                errorDiv.style.cssText = `
                    background-color: var(--secondary);
                    color: white;
                    padding: 15px 20px;
                    border-radius: var(--border-radius);
                    margin-bottom: 20px;
                    text-align: center;
                    font-weight: 500;
                `;
                errorDiv.textContent = message;
                
                const formContainer = document.querySelector('.registration-form-container');
                const formTitle = formContainer.querySelector('.form-title');
                formTitle.insertAdjacentElement('afterend', errorDiv);
            }
            
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