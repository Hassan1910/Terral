<?php
/**
 * Save Cart to Session
 * 
 * This script receives cart data from an AJAX request and saves it to the PHP session.
 * Used to bridge the gap between localStorage and server-side cart handling.
 */

// Start the session
session_start();

// Set content type
header('Content-Type: application/json');

try {
    // Get the raw POST data
    $json = file_get_contents('php://input');
    
    // Decode the JSON data
    $cart = json_decode($json, true);
    
    // Validate the cart data
    if (!is_array($cart)) {
        throw new Exception('Invalid cart data received');
    }
    
    // Save the cart data to the session
    $_SESSION['cart'] = $cart;
    
    // Return success response
    echo json_encode(['success' => true, 'message' => 'Cart saved to session']);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 