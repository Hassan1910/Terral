<?php
/**
 * Product Image Display Fix
 * This file includes the JavaScript that fixes product image display issues
 */

// Define the base URL for assets
$base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$base_url .= $_SERVER['HTTP_HOST'];

// Output the script tag for the fix-product-image.js file
echo '<script src="' . $base_url . '/Terral/assets/js/fix-product-image.js"></script>';
?>