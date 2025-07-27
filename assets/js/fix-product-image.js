/**
 * Product Image Display Fix
 * This script fixes product image display issues
 */
document.addEventListener('DOMContentLoaded', function() {
    // Flag to prevent infinite loops
    window._imageFixApplied = window._imageFixApplied || false;
    
    // Only run once
    if (window._imageFixApplied) return;
    window._imageFixApplied = true;
    
    console.log('Applying product image fix...');
    
    // Get base URL and define placeholder paths using patterns that work based on debug output
    const baseUrl = window.location.protocol + '//' + window.location.host;
    // Primary placeholder - relative path
    const placeholderPath = 'api/uploads/products/placeholder.jpg';
    // Fallback - full URL with Terral
    const fallbackPlaceholderPath = baseUrl + '/Terral/api/uploads/products/placeholder.jpg';
    // Third fallback in assets
    const thirdFallbackPath = 'assets/images/placeholder.jpg';
    
    console.log('Using placeholder paths:', { 
        primary: placeholderPath,
        fallback: fallbackPlaceholderPath,
        third: thirdFallbackPath
    });
    
    // Direct fix: Remove "Product Image" text from product-img-container divs
    document.querySelectorAll('.product-img-container').forEach(function(container) {
        // Check for direct text nodes that contain "Product Image"
        Array.from(container.childNodes).forEach(function(node) {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim() === 'Product Image') {
                node.textContent = '';
            }
        });
        
        // Make sure there's an image tag
        const img = container.querySelector('img');
        if (!img) {
            // Create a placeholder image if none exists
            var newImg = document.createElement('img');
            newImg.src = placeholderPath;
            newImg.alt = 'Product';
            newImg.className = 'product-img';
            
            // Add fallback for the new image
            newImg.onerror = function() {
                console.log('Primary placeholder failed, using fallback');
                if (!this.hasAttribute('data-fallback-tried')) {
                    this.setAttribute('data-fallback-tried', 'true');
                    this.src = fallbackPlaceholderPath;
                } else {
                    console.log('Trying final fallback path');
                    this.src = thirdFallbackPath;
                }
            };
            
            container.appendChild(newImg);
        } else {
            // If image already exists but failed to load, set a clean error handler
            // Only add the handler if not already added
            if (!img.hasAttribute('data-error-handler-added')) {
                img.setAttribute('data-error-handler-added', 'true');
                
                img.onerror = function(e) {
                    // Track fallback attempts to prevent loops
                    if (this.hasAttribute('data-all-fallbacks-tried')) {
                        console.log('Already tried all placeholders, stopping loop');
                        return;
                    }
                    
                    // Try first fallback
                    if (!this.hasAttribute('data-replaced')) {
                        console.log('Image failed to load, trying primary placeholder');
                        this.setAttribute('data-replaced', 'true');
                        this.src = placeholderPath;
                    } 
                    // Try second fallback
                    else if (!this.hasAttribute('data-replaced-twice')) {
                        console.log('Primary placeholder failed, trying fallback with Terral2');
                        this.setAttribute('data-replaced-twice', 'true');
                        this.src = fallbackPlaceholderPath;
                    }
                    // Try final fallback
                    else {
                        console.log('Second placeholder failed, trying assets path');
                        this.setAttribute('data-all-fallbacks-tried', 'true');
                        this.src = thirdFallbackPath;
                    }
                }
            }
        }
    });
});