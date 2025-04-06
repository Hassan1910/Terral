/**
 * Product Image Debug Script
 * This script will help debug image loading issues
 */
(function() {
    console.log('========= DEBUG PRODUCT IMAGES =========');
    console.log('Current page URL:', window.location.href);
    
    // Check all image elements
    const allImages = document.querySelectorAll('img');
    console.log('Total images on page:', allImages.length);
    
    // Log the src attribute of each image
    allImages.forEach((img, index) => {
        console.log(`Image ${index}:`, img.src);
        
        // Check if image has loaded or has error
        if (img.complete) {
            console.log(`Image ${index} is complete:`, !img.naturalWidth ? 'Error (not loaded)' : 'Loaded');
        } else {
            console.log(`Image ${index} is still loading`);
            
            // Add load and error event listeners
            img.addEventListener('load', function() {
                console.log(`Image ${index} loaded successfully`);
            });
            
            img.addEventListener('error', function() {
                console.log(`Image ${index} failed to load:`, this.src);
            });
        }
    });
    
    // Check product images specifically
    const productImages = document.querySelectorAll('.product-img');
    console.log('Total product images:', productImages.length);
    
    // Get base URL for testing
    const baseUrl = window.location.protocol + '//' + window.location.host;
    
    // Create image elements to test paths
    console.log('Testing image paths:');
    const testPaths = [
        'api/uploads/products/placeholder.jpg',
        '/api/uploads/products/placeholder.jpg',
        '/Terral2/api/uploads/products/placeholder.jpg',
        baseUrl + '/Terral2/api/uploads/products/placeholder.jpg',
        'assets/images/placeholder.jpg'
    ];
    
    testPaths.forEach(path => {
        const img = new Image();
        img.onload = function() {
            console.log(`✅ Path works: ${path}`);
        };
        img.onerror = function() {
            console.log(`❌ Path fails: ${path}`);
        };
        img.src = path;
    });
    
    // Log the current base URL
    const baseElement = document.querySelector('base');
    console.log('Base URL:', baseElement ? baseElement.href : 'No base element found');
    
    console.log('======= END DEBUG PRODUCT IMAGES =======');
})(); 