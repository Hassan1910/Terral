<?php
/**
 * Product Form Template
 * 
 * This is a template for adding or editing products with improved image upload capabilities.
 * Include this file in both add-product.php and edit-product.php.
 */

// Security check
if (!defined('IS_ADMIN') || !isset($pageTitle)) {
    header('Location: index.php');
    exit;
}
?>
<!-- Product Form with Enhanced Image Upload -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><?php echo $pageTitle; ?></h6>
        <a href="products.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
    </div>
    <div class="card-body">
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger">
                <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>
        
        <form id="product-form" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
            <?php if (isset($productId)): ?>
                <input type="hidden" name="id" value="<?php echo $productId; ?>">
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-8">
                    <!-- Basic Product Information -->
                    <div class="form-group">
                        <label for="name">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $product['name'] ?? ''; ?>" required>
                        <div class="invalid-feedback">Please enter a product name.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category <span class="text-danger">*</span></label>
                        <select class="form-control" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo (isset($product['category_id']) && $product['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a category.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="price">Price (KSh) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?php echo $product['price'] ?? ''; ?>" required>
                                <div class="invalid-feedback">Please enter a valid price.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="stock">Stock Quantity <span class="text-danger">*</span></label>
                                <input type="number" min="0" class="form-control" id="stock" name="stock" value="<?php echo $product['stock'] ?? ''; ?>" required>
                                <div class="invalid-feedback">Please enter stock quantity.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?php echo $product['description'] ?? ''; ?></textarea>
                        <div class="invalid-feedback">Please enter a product description.</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="active" <?php echo (isset($product['status']) && $product['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (isset($product['status']) && $product['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="featured" <?php echo (isset($product['status']) && $product['status'] == 'featured') ? 'selected' : ''; ?>>Featured</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customizable</label>
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="is_customizable" name="is_customizable" value="1" <?php echo (isset($product['is_customizable']) && $product['is_customizable']) ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="is_customizable">Allow customers to customize this product</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Enhanced Image Upload Section -->
                    <div class="card border-left-primary">
                        <div class="card-body">
                            <h5 class="card-title">Product Image</h5>
                            <p class="text-muted small">Supported formats: JPG, PNG, GIF, WebP<br>Max size: 5MB</p>
                            
                            <!-- Hidden file input -->
                            <input type="file" id="product-image-upload" class="image-upload-input" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                            
                            <!-- Image preview area -->
                            <div id="product-image-preview" class="image-preview">
                                <!-- Preview container will be created by JS -->
                            </div>
                            
                            <?php if (isset($product['image']) && !empty($product['image'])): ?>
                                <div class="current-image mt-3">
                                    <p class="mb-1">Current Image:</p>
                                    <img src="<?php echo '/Terral2/api/uploads/products/' . $product['image']; ?>" alt="Current Image" class="img-thumbnail" style="max-height: 100px;">
                                    <div class="custom-control custom-checkbox mt-2">
                                        <input type="checkbox" class="custom-control-input" id="delete_image" name="delete_image" value="1">
                                        <label class="custom-control-label" for="delete_image">Delete current image</label>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Additional Options -->
                    <div class="card border-left-secondary mt-3">
                        <div class="card-body">
                            <h5 class="card-title">Additional Options</h5>
                            
                            <div class="form-group">
                                <label for="sku">SKU</label>
                                <input type="text" class="form-control" id="sku" name="sku" value="<?php echo $product['sku'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="weight">Weight (kg)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="weight" name="weight" value="<?php echo $product['weight'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="dimensions">Dimensions (LxWxH cm)</label>
                                <input type="text" class="form-control" id="dimensions" name="dimensions" value="<?php echo $product['dimensions'] ?? ''; ?>" placeholder="e.g. 25x15x10">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="form-group text-center">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-save mr-2"></i> <?php echo (isset($productId)) ? 'Update Product' : 'Add Product'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Include the image upload JS and CSS -->
<link rel="stylesheet" href="/Terral2/assets/css/admin-image-upload.css">
<script src="/Terral2/assets/js/admin-image-upload.js"></script>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Fetch all forms with needs-validation class
    var forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Initialize the rich text editor for description if available
    if (typeof CKEDITOR !== 'undefined' && document.getElementById('description')) {
        CKEDITOR.replace('description');
    }
});
</script> 