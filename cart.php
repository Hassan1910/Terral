<?php
// Start session for user authentication
session_start();

// Set content type
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Terral Online Production System</title>
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
        
        .nav-icons a.active {
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
        
        /* Page Header */
        .page-header {
            background: linear-gradient(rgba(44, 62, 80, 0.7), rgba(44, 62, 80, 0.7)), url('assets/images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            color: var(--white);
            text-align: center;
            padding: 60px 0;
            margin-bottom: 50px;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        /* Cart Section */
        .cart-section {
            margin-bottom: 80px;
        }
        
        .cart-container {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 30px;
        }
        
        .cart-header {
            display: grid;
            grid-template-columns: 100px 2fr 1fr 1fr 1fr 50px;
            padding: 15px 0;
            border-bottom: 1px solid var(--gray-light);
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .cart-header div:nth-child(3),
        .cart-header div:nth-child(4),
        .cart-header div:nth-child(5) {
            text-align: center;
        }
        
        .cart-item {
            display: grid;
            grid-template-columns: 100px 2fr 1fr 1fr 1fr 50px;
            padding: 20px 0;
            border-bottom: 1px solid var(--gray-light);
            align-items: center;
        }
        
        .cart-item-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        .cart-item-details {
            padding-right: 20px;
        }
        
        .cart-item-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .cart-item-customization {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .cart-item-price {
            text-align: center;
            font-weight: 600;
        }
        
        .cart-item-quantity {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            background-color: var(--gray-light);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .quantity-btn:hover {
            background-color: var(--gray);
        }
        
        .quantity-input {
            width: 40px;
            height: 30px;
            border: 1px solid var(--gray-light);
            text-align: center;
            margin: 0 5px;
            font-family: inherit;
            font-size: 0.9rem;
        }
        
        .cart-item-total {
            text-align: center;
            font-weight: 600;
            color: var(--primary);
        }
        
        .cart-item-remove {
            display: flex;
            justify-content: center;
        }
        
        .remove-btn {
            background: none;
            border: none;
            color: var(--secondary);
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .remove-btn:hover {
            color: var(--text-dark);
        }
        
        .cart-footer {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .cart-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
            font-size: 1rem;
            border: none;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .cart-summary {
            background-color: var(--gray-light);
            border-radius: var(--border-radius);
            padding: 20px;
            min-width: 300px;
        }
        
        .summary-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray);
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .summary-label {
            color: var(--text-light);
        }
        
        .summary-value {
            font-weight: 600;
        }
        
        .summary-total {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-dark);
            padding-top: 15px;
            margin-top: 15px;
            border-top: 1px solid var(--gray);
        }
        
        .checkout-btn {
            width: 100%;
            margin-top: 20px;
        }
        
        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 50px 0;
        }
        
        .empty-cart i {
            font-size: 5rem;
            color: var(--gray);
            margin-bottom: 20px;
        }
        
        .empty-cart h2 {
            font-size: 2rem;
            margin-bottom: 20px;
        }
        
        .empty-cart p {
            font-size: 1.2rem;
            color: var(--text-light);
            margin-bottom: 30px;
        }
        
        /* Footer */
        .footer {
            background-color: var(--text-dark);
            color: var(--white);
            padding: 30px 0;
            margin-top: 80px;
        }
        
        .footer-bottom {
            text-align: center;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .cart-header {
                display: none;
            }
            
            .cart-item {
                grid-template-columns: 80px 1fr;
                grid-template-rows: auto auto auto;
                gap: 10px;
                padding: 20px 0;
                position: relative;
            }
            
            .cart-item-img {
                grid-row: span 3;
            }
            
            .cart-item-details {
                grid-column: 2;
                padding-right: 40px;
            }
            
            .cart-item-price {
                text-align: left;
                grid-column: 2;
            }
            
            .cart-item-quantity {
                justify-content: flex-start;
                grid-column: 2;
            }
            
            .cart-item-total {
                display: none;
            }
            
            .cart-item-remove {
                position: absolute;
                top: 20px;
                right: 0;
            }
            
            .cart-footer {
                flex-direction: column;
            }
            
            .cart-actions, .cart-summary {
                width: 100%;
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
                    <li><a href="all-products.php">Products</a></li>
                    <li><a href="index.php#how-it-works">How It Works</a></li>
                </ul>
                
                <div class="nav-icons">
                    <a href="#" id="search-toggle"><i class="fas fa-search"></i></a>
                    <a href="cart.php" id="cart-toggle" class="cart-icon active">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count">0</span>
                    </a>
                    <a href="<?php echo isset($_SESSION['user_id']) ? 'account.php' : 'login.php'; ?>">
                        <i class="fas fa-user"></i>
                    </a>
                </div>
            </nav>
        </div>
    </header>
    
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Shopping Cart</h1>
        </div>
    </section>
    
    <!-- Cart Section -->
    <section class="cart-section">
        <div class="container">
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert" style="background-color: #f8d7da; color: #721c24; padding: 10px 15px; margin-bottom: 20px; border-radius: var(--border-radius); border-left: 4px solid #dc3545;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
            <?php endif; ?>
            
            <div id="cart-container" class="cart-container">
                <!-- Cart content will be loaded dynamically with JavaScript -->
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Terral Online Production System. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <script>
        // Load cart contents when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadCart();
        });
        
        // Load cart from localStorage
        function loadCart() {
            const cartContainer = document.getElementById('cart-container');
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            
            updateCartCount();
            
            if (cart.length === 0) {
                // Display empty cart message
                cartContainer.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h2>Your cart is empty</h2>
                        <p>Looks like you haven't added any products to your cart yet.</p>
                        <a href="all-products.php" class="btn btn-primary">Continue Shopping</a>
                    </div>
                `;
                return;
            }
            
            // Calculate cart totals
            const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
            const shipping = subtotal > 0 ? 10 : 0; // Example shipping calculation
            const tax = subtotal * 0.16; // Example tax calculation (16%)
            const total = subtotal + shipping + tax;
            
            // Build cart HTML
            let cartHTML = `
                <div class="cart-header">
                    <div>Product</div>
                    <div>Details</div>
                    <div>Price</div>
                    <div>Quantity</div>
                    <div>Total</div>
                    <div></div>
                </div>
            `;
            
            // Add cart items
            cart.forEach((item, index) => {
                const itemTotal = item.price * item.quantity;
                
                cartHTML += `
                    <div class="cart-item" data-id="${item.id}">
                        <div>
                            <img src="${item.image}" alt="${item.name}" class="cart-item-img">
                        </div>
                        <div class="cart-item-details">
                            <h3 class="cart-item-name">${item.name}</h3>
                            ${item.customization ? `
                                <div class="cart-item-customization">
                                    <p>Customized with text: ${item.customization.text || 'None'}</p>
                                    ${item.customization.image ? `<p>Custom image added</p>` : ''}
                                </div>
                            ` : ''}
                        </div>
                        <div class="cart-item-price">KSh ${item.price.toFixed(2)}</div>
                        <div class="cart-item-quantity">
                            <button class="quantity-btn decrease-btn" onclick="updateQuantity(${index}, -1)">-</button>
                            <input type="number" class="quantity-input" value="${item.quantity}" min="1" onchange="updateItemQuantity(${index}, this.value)">
                            <button class="quantity-btn increase-btn" onclick="updateQuantity(${index}, 1)">+</button>
                        </div>
                        <div class="cart-item-total">KSh ${itemTotal.toFixed(2)}</div>
                        <div class="cart-item-remove">
                            <button class="remove-btn" onclick="removeItem(${index})">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            // Add cart footer with actions and summary
            cartHTML += `
                <div class="cart-footer">
                    <div class="cart-actions">
                        <a href="all-products.php" class="btn btn-outline">Continue Shopping</a>
                        <button class="btn btn-outline" onclick="clearCart()">Clear Cart</button>
                    </div>
                    
                    <div class="cart-summary">
                        <h3 class="summary-title">Order Summary</h3>
                        <div class="summary-item">
                            <span class="summary-label">Subtotal:</span>
                            <span class="summary-value">KSh ${subtotal.toFixed(2)}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Shipping:</span>
                            <span class="summary-value">KSh ${shipping.toFixed(2)}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Tax (16%):</span>
                            <span class="summary-value">KSh ${tax.toFixed(2)}</span>
                        </div>
                        <div class="summary-item summary-total">
                            <span>Total:</span>
                            <span class="summary-value">KSh ${total.toFixed(2)}</span>
                        </div>
                        <button onclick="proceedToCheckout()" class="btn btn-primary checkout-btn">Proceed to Checkout</button>
                    </div>
                </div>
            `;
            
            cartContainer.innerHTML = cartHTML;
        }
        
        // Update cart item quantity
        function updateQuantity(index, change) {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            
            if (index >= 0 && index < cart.length) {
                cart[index].quantity = Math.max(1, cart[index].quantity + change);
                localStorage.setItem('cart', JSON.stringify(cart));
                loadCart();
            }
        }
        
        // Update item quantity directly
        function updateItemQuantity(index, quantity) {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            
            if (index >= 0 && index < cart.length) {
                cart[index].quantity = Math.max(1, parseInt(quantity) || 1);
                localStorage.setItem('cart', JSON.stringify(cart));
                loadCart();
            }
        }
        
        // Remove item from cart
        function removeItem(index) {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            
            if (index >= 0 && index < cart.length) {
                cart.splice(index, 1);
                localStorage.setItem('cart', JSON.stringify(cart));
                loadCart();
            }
        }
        
        // Clear the entire cart
        function clearCart() {
            if (confirm('Are you sure you want to clear your cart?')) {
                localStorage.setItem('cart', JSON.stringify([]));
                loadCart();
            }
        }
        
        // Update cart count in header
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            const totalItems = cart.reduce((count, item) => count + item.quantity, 0);
            document.querySelector('.cart-count').textContent = totalItems;
        }
        
        // Function to proceed to checkout
        function proceedToCheckout() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            
            if (cart.length === 0) {
                alert('Your cart is empty. Please add items before checkout.');
                return;
            }
            
            // Create a form to submit cart data to the server
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'process-cart.php';
            form.style.display = 'none';
            
            // Add cart data as a hidden input
            const cartInput = document.createElement('input');
            cartInput.type = 'hidden';
            cartInput.name = 'cart_data';
            cartInput.value = JSON.stringify(cart);
            form.appendChild(cartInput);
            
            // Add the form to the document and submit it
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html> 