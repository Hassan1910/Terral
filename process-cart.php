<?php
/**
 * Terral Online Production System
 * Process Cart Data
 * 
 * This script processes cart data sent from client-side localStorage
 * and transfers it to PHP session before redirecting to checkout.
 */

// Start the session
session_start();

// Set content type
header('Content-Type: text/html; charset=UTF-8');

// Check if cart data was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_data'])) {
    // Get cart data from POST
    $cartData = json_decode($_POST['cart_data'], true);
    
    // Validate cart data
    if (is_array($cartData) && !empty($cartData)) {
        // Store cart data in session
        $_SESSION['cart'] = $cartData;
        
        // Redirect to checkout page
        header('Location: checkout.php');
        exit;
    } else {
        // Invalid cart data
        $_SESSION['error_message'] = 'Invalid cart data. Please try again.';
        header('Location: cart.php');
        exit;
    }
} else {
    // No cart data submitted
    header('Location: cart.php');
    exit;
} 