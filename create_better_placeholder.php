<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Image Generator</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        h1 {
            color: #3498db;
            margin-bottom: 30px;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .product-card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .placeholder {
            position: relative;
            width: 100%;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .placeholder:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        .placeholder-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
            z-index: 2;
        }
        .product-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }
        .placeholder-text {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .placeholder-subtext {
            font-size: 0.9em;
            opacity: 0.8;
        }
        
        /* Different styled placeholders */
        .style1 {
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
        }
        .style2 {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
        }
        .style3 {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
            color: white;
        }
        .style4 {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: white;
        }
        .style5 {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
            color: white;
        }
        .style6 {
            background: linear-gradient(45deg, #1abc9c, #16a085);
            color: white;
        }
        .style7 {
            background: linear-gradient(45deg, #34495e, #2c3e50);
            color: white;
        }
        .style8 {
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .product-info {
            padding: 15px;
        }
        .product-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .product-category {
            font-size: 0.9em;
            color: #666;
        }
        
        .instructions {
            margin-top: 40px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .instructions h2 {
            color: #3498db;
            margin-top: 0;
        }
        .instructions ol {
            padding-left: 20px;
        }
        .instructions li {
            margin-bottom: 10px;
        }
        .note {
            padding: 10px;
            background-color: #ffffdd;
            border-left: 4px solid #f39c12;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>Product Placeholder Images</h1>
    <p>Below are examples of placeholder images you can use for your products. Take a screenshot of any design you like and save it to the products folder.</p>
    
    <div class="product-grid">
        <!-- Style 1: Blue -->
        <div class="product-card">
            <div class="placeholder style1">
                <div class="placeholder-content">
                    <div class="product-icon">📱</div>
                    <div class="placeholder-text">Product Image</div>
                    <div class="placeholder-subtext">Terral Online Store</div>
                </div>
            </div>
            <div class="product-info">
                <div class="product-name">Blue Gradient</div>
                <div class="product-category">Electronics Category</div>
            </div>
        </div>
        
        <!-- Style 2: Red -->
        <div class="product-card">
            <div class="placeholder style2">
                <div class="placeholder-content">
                    <div class="product-icon">👕</div>
                    <div class="placeholder-text">Product Image</div>
                    <div class="placeholder-subtext">Terral Online Store</div>
                </div>
            </div>
            <div class="product-info">
                <div class="product-name">Red Gradient</div>
                <div class="product-category">Apparel Category</div>
            </div>
        </div>
        
        <!-- Style 3: Green -->
        <div class="product-card">
            <div class="placeholder style3">
                <div class="placeholder-content">
                    <div class="product-icon">🏠</div>
                    <div class="placeholder-text">Product Image</div>
                    <div class="placeholder-subtext">Terral Online Store</div>
                </div>
            </div>
            <div class="product-info">
                <div class="product-name">Green Gradient</div>
                <div class="product-category">Home Décor Category</div>
            </div>
        </div>
        
        <!-- Style 4: Orange -->
        <div class="product-card">
            <div class="placeholder style4">
                <div class="placeholder-content">
                    <div class="product-icon">🖋️</div>
                    <div class="placeholder-text">Product Image</div>
                    <div class="placeholder-subtext">Terral Online Store</div>
                </div>
            </div>
            <div class="product-info">
                <div class="product-name">Orange Gradient</div>
                <div class="product-category">Stationery Category</div>
            </div>
        </div>
        
        <!-- Style 5: Purple -->
        <div class="product-card">
            <div class="placeholder style5">
                <div class="placeholder-content">
                    <div class="product-icon">🎁</div>
                    <div class="placeholder-text">Product Image</div>
                    <div class="placeholder-subtext">Terral Online Store</div>
                </div>
            </div>
            <div class="product-info">
                <div class="product-name">Purple Gradient</div>
                <div class="product-category">Promotional Category</div>
            </div>
        </div>
        
        <!-- Style 6: Teal -->
        <div class="product-card">
            <div class="placeholder style6">
                <div class="placeholder-content">
                    <div class="product-icon">👜</div>
                    <div class="placeholder-text">Product Image</div>
                    <div class="placeholder-subtext">Terral Online Store</div>
                </div>
            </div>
            <div class="product-info">
                <div class="product-name">Teal Gradient</div>
                <div class="product-category">Accessories Category</div>
            </div>
        </div>
        
        <!-- Style 7: Navy -->
        <div class="product-card">
            <div class="placeholder style7">
                <div class="placeholder-content">
                    <div class="product-icon">💼</div>
                    <div class="placeholder-text">Product Image</div>
                    <div class="placeholder-subtext">Terral Online Store</div>
                </div>
            </div>
            <div class="product-info">
                <div class="product-name">Navy Gradient</div>
                <div class="product-category">Office Supplies Category</div>
            </div>
        </div>
        
        <!-- Style 8: Gray -->
        <div class="product-card">
            <div class="placeholder style8">
                <div class="placeholder-content">
                    <div class="product-icon">🎨</div>
                    <div class="placeholder-text">Product Image</div>
                    <div class="placeholder-subtext">Terral Online Store</div>
                </div>
            </div>
            <div class="product-info">
                <div class="product-name">Gray Gradient</div>
                <div class="product-category">Custom Category</div>
            </div>
        </div>
    </div>
    
    <div class="instructions">
        <h2>How to Use These Images</h2>
        <ol>
            <li>Take a screenshot of the placeholder design you like (use a screen capture tool)</li>
            <li>Crop the image to include just the colored square with the icon and text</li>
            <li>Save the image as JPG or PNG format</li>
            <li>Upload the image to your product in the admin panel, or</li>
            <li>Place the image in the <code>/api/uploads/products/</code> directory and update the product database record</li>
        </ol>
        
        <div class="note">
            <strong>Note:</strong> For best results, create images that are square (1:1 aspect ratio) and at least 800×800 pixels.
        </div>
    </div>
</body>
</html> 