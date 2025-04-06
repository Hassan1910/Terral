<?php
/**
 * Image Upload Test Script
 * 
 * This script tests the ImageUploadHelper functionality directly
 * to ensure image uploads are working correctly.
 */

// Include the ImageUploadHelper class
require_once 'api/helpers/ImageUploadHelper.php';

// Init
$message = '';
$uploaded_image = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    // Create the ImageUploadHelper instance
    $imageUploader = new ImageUploadHelper();
    
    // Get the upload type from form
    $uploadType = $_POST['type'] ?? 'products';
    
    // Process the upload
    $result = $imageUploader->uploadImage($_FILES['image'], $uploadType);
    
    if ($result['success']) {
        $message = '<div style="color: green;">Image uploaded successfully!</div>';
        $uploaded_image = $result['filepath']; // Store the relative path
    } else {
        $message = '<div style="color: red;">Upload failed: ' . $result['message'] . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Upload Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2 {
            color: #3498db;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        select, input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #2980b9;
        }
        .preview {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .preview img {
            max-width: 100%;
            max-height: 300px;
        }
        .upload-types {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 30px;
        }
        .upload-type {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            flex: 1;
            min-width: 200px;
        }
        .upload-type h3 {
            margin-top: 0;
            color: #3498db;
        }
        .directory-status {
            margin-top: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>Image Upload Test</h1>
    <p>This page tests the image upload functionality to ensure it's working correctly.</p>
    
    <?php echo $message; ?>
    
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="type">Upload Type:</label>
            <select name="type" id="type">
                <option value="products">Products</option>
                <option value="categories">Categories</option>
                <option value="customizations">Customizations</option>
                <option value="logos">Logos</option>
                <option value="avatars">Avatars</option>
            </select>
        </div>
        <div class="form-group">
            <label for="image">Select Image:</label>
            <input type="file" name="image" id="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
        </div>
        <button type="submit">Upload Image</button>
    </form>
    
    <?php if ($uploaded_image): ?>
    <div class="preview">
        <h2>Uploaded Image</h2>
        <img src="<?php echo '/Terral2' . $uploaded_image; ?>" alt="Uploaded Image">
        <p>Path: <?php echo $uploaded_image; ?></p>
    </div>
    <?php endif; ?>
    
    <div class="upload-types">
        <div class="upload-type">
            <h3>Products</h3>
            <p>Path: /api/uploads/products/</p>
            <p>Used for: Product images shown on product listings and detail pages</p>
            <?php 
            $dir = __DIR__ . '/api/uploads/products';
            if (file_exists($dir) && is_writable($dir)) {
                echo '<p style="color: green;">Directory exists and is writable ✓</p>';
                
                // Show example images if any
                $files = glob($dir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
                if (count($files) > 0) {
                    echo '<p>Sample files: ' . count($files) . ' images</p>';
                } else {
                    echo '<p>No sample images yet.</p>';
                }
            } else {
                echo '<p style="color: red;">Directory issues ✗</p>';
            }
            ?>
        </div>
        <div class="upload-type">
            <h3>Categories</h3>
            <p>Path: /api/uploads/categories/</p>
            <p>Used for: Category images shown on homepage and category pages</p>
            <?php 
            $dir = __DIR__ . '/api/uploads/categories';
            if (file_exists($dir) && is_writable($dir)) {
                echo '<p style="color: green;">Directory exists and is writable ✓</p>';
                
                // Show example images if any
                $files = glob($dir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
                if (count($files) > 0) {
                    echo '<p>Sample files: ' . count($files) . ' images</p>';
                } else {
                    echo '<p>No sample images yet.</p>';
                }
            } else {
                echo '<p style="color: red;">Directory issues ✗</p>';
            }
            ?>
        </div>
    </div>
    
    <div class="directory-status">
        <h2>Directory Structure Check</h2>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 8px;">Directory</th>
                <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 8px;">Status</th>
            </tr>
            <?php
            $upload_dirs = [
                'api/uploads/products',
                'api/uploads/categories',
                'api/uploads/customizations',
                'api/uploads/logos',
                'api/uploads/avatars',
                'api/uploads/invoices'
            ];
            
            foreach ($upload_dirs as $dir) {
                echo '<tr>';
                echo '<td style="border-bottom: 1px solid #ddd; padding: 8px;">' . $dir . '</td>';
                
                if (file_exists($dir)) {
                    if (is_writable($dir)) {
                        echo '<td style="border-bottom: 1px solid #ddd; padding: 8px; color: green;">Exists and writable ✓</td>';
                    } else {
                        echo '<td style="border-bottom: 1px solid #ddd; padding: 8px; color: orange;">Exists but not writable ⚠</td>';
                    }
                } else {
                    echo '<td style="border-bottom: 1px solid #ddd; padding: 8px; color: red;">Directory not found ✗</td>';
                }
                
                echo '</tr>';
            }
            ?>
        </table>
    </div>
</body>
</html> 