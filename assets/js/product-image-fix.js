/**
 * Product Image Display Fix
 * This script fixes product image display issues
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Product image fix script loaded');
    // Fix product images with "Product Image" text
    const productImgContainers = document.querySelectorAll('.product-img-container');
    console.log('Found product containers:', productImgContainers.length);
    
    // Get base URL and correct path - Based on the debug results, these formats work
    const baseUrl = window.location.protocol + '//' + window.location.host;
    // Primary placeholder path (relative path works with the base tag)
    const placeholderPath = 'api/uploads/products/placeholder.jpg';
    // Fallback placeholder with full path
    const fallbackPlaceholderPath = baseUrl + '/Terral2/api/uploads/products/placeholder.jpg';
    // Third fallback in assets folder
    const thirdFallbackPath = 'assets/images/placeholder.jpg'; 
    
    console.log('Using placeholder paths:', { 
        primary: placeholderPath,
        fallback: fallbackPlaceholderPath,
        third: thirdFallbackPath
    });
    
    productImgContainers.forEach((container, index) => {
        // Remove any text nodes directly under the container
        container.childNodes.forEach(node => {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim() === 'Product Image') {
                node.textContent = '';
            }
        });
        
        // Check if the image is actually displaying
        const img = container.querySelector('.product-img');
        console.log(`Container ${index} has image:`, img ? 'Yes' : 'No');
        if (img) {
            console.log(`Image ${index} src:`, img.src);
            
            // Only add the error handler if it doesn't already have one
            if (!img.hasAttribute('data-error-handler-added')) {
                img.setAttribute('data-error-handler-added', 'true');
                
                img.addEventListener('error', function() {
                    // Prevent infinite loop with multiple fallbacks
                    if (this.hasAttribute('data-replaced-twice')) {
                        console.log(`Image ${index} already tried multiple placeholders, stopping loop`);
                        return;
                    }
                    
                    // Primary placeholder - use relative path
                    if (!this.hasAttribute('data-replaced')) {
                        console.log(`Image ${index} failed to load, trying primary placeholder`);
                        this.setAttribute('data-replaced', 'true');
                        this.src = placeholderPath;
                        container.style.backgroundColor = '#f8f9fa';
                    } 
                    // Fallback placeholder - use absolute path with Terral2
                    else if (!this.hasAttribute('data-replaced-twice')) {
                        console.log(`Image ${index} primary placeholder failed, trying fallback`);
                        this.setAttribute('data-replaced-twice', 'true');
                        this.src = fallbackPlaceholderPath;
                    }
                    // Final fallback - try assets folder
                    else {
                        console.log(`Image ${index} trying final fallback`);
                        this.src = thirdFallbackPath;
                        this.setAttribute('data-all-fallbacks-tried', 'true');
                    }
                });
            }
            
            // Force image reload only if not already replaced
            if (!img.hasAttribute('data-replaced') && !img.complete) {
                const currentSrc = img.src;
                if (currentSrc) {
                    img.src = '';
                    setTimeout(() => {
                        img.src = currentSrc;
                        console.log(`Image ${index} reloaded with src:`, currentSrc);
                    }, 10);
                }
            }
        }
    });
});