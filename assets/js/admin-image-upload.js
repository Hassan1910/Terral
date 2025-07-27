/**
 * Admin Image Upload Script
 * 
 * This script handles client-side image uploads, validations, previews, and AJAX submissions.
 * It works with the server-side ImageUploadHelper.php.
 */

class ImageUploader {
    constructor(options) {
        // Default options
        this.options = {
            inputSelector: '.image-upload-input',
            previewSelector: '.image-preview',
            formSelector: 'form',
            uploadUrl: '/Terral/admin/image-upload-handler.php',
            maxFileSize: 5 * 1024 * 1024, // 5MB
            allowedTypes: ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'],
            uploadType: 'products', // Default upload type
            customName: null,
            isRequired: false, // Is image upload required
            showFileName: true,
            ...options
        };
        
        // Initialize
        this.init();
    }
    
    init() {
        // Get DOM elements
        this.input = document.querySelector(this.options.inputSelector);
        this.preview = document.querySelector(this.options.previewSelector);
        this.form = document.querySelector(this.options.formSelector);
        
        if (!this.input || !this.preview || !this.form) {
            console.error('Required elements not found.');
            return;
        }
        
        // Create preview container if it doesn't exist
        if (!this.preview.querySelector('.preview-container')) {
            const container = document.createElement('div');
            container.className = 'preview-container';
            this.preview.appendChild(container);
        }
        
        // Create error message container
        if (!this.preview.querySelector('.error-message')) {
            const errorContainer = document.createElement('div');
            errorContainer.className = 'error-message';
            this.preview.appendChild(errorContainer);
        }
        
        // Create file name container if showFileName is true
        if (this.options.showFileName && !this.preview.querySelector('.file-name')) {
            const fileNameContainer = document.createElement('div');
            fileNameContainer.className = 'file-name';
            this.preview.appendChild(fileNameContainer);
        }
        
        // Initialize events
        this.initEvents();
    }
    
    initEvents() {
        // Listen for file input changes
        this.input.addEventListener('change', (e) => {
            this.handleFileSelect(e);
        });
        
        // Modify form submit event
        this.form.addEventListener('submit', (e) => {
            if (this.validateForm()) {
                // Form is valid, check if we have a file to upload
                if (this.currentFile) {
                    e.preventDefault(); // Prevent default form submission
                    this.uploadFile().then(response => {
                        if (response.success) {
                            // Create a hidden input with the filename
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'image_filename';
                            hiddenInput.value = response.filename;
                            this.form.appendChild(hiddenInput);
                            
                            // Submit the form now
                            this.form.submit();
                        } else {
                            this.showError(response.message);
                        }
                    }).catch(error => {
                        this.showError('Upload failed: ' + error.message);
                    });
                }
                // If no file, let the form submit normally
            } else {
                e.preventDefault(); // Prevent submission if validation failed
            }
        });
        
        // Add drag and drop support
        const previewContainer = this.preview.querySelector('.preview-container');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            previewContainer.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });
        
        // Add drag enter/over styling
        ['dragenter', 'dragover'].forEach(eventName => {
            previewContainer.addEventListener(eventName, () => {
                previewContainer.classList.add('highlight');
            }, false);
        });
        
        // Remove drag enter/over styling
        ['dragleave', 'drop'].forEach(eventName => {
            previewContainer.addEventListener(eventName, () => {
                previewContainer.classList.remove('highlight');
            }, false);
        });
        
        // Handle dropped files
        previewContainer.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length) {
                this.input.files = files;
                this.handleFileSelect({ target: this.input });
            }
        }, false);
        
        // Clicking on preview opens file dialog
        previewContainer.addEventListener('click', () => {
            this.input.click();
        });
    }
    
    handleFileSelect(e) {
        const files = e.target.files;
        
        if (!files.length) return;
        
        const file = files[0];
        this.currentFile = file;
        
        // Clear previous errors
        this.clearError();
        
        // Validate file type
        if (!this.validateFileType(file)) {
            this.showError('Invalid file type. Allowed types: JPG, PNG, GIF, WebP');
            this.clearPreview();
            return;
        }
        
        // Validate file size
        if (!this.validateFileSize(file)) {
            this.showError(`File too large. Maximum size is ${this.options.maxFileSize / (1024 * 1024)}MB`);
            this.clearPreview();
            return;
        }
        
        // Show file name if option is enabled
        if (this.options.showFileName) {
            const fileNameContainer = this.preview.querySelector('.file-name');
            if (fileNameContainer) {
                fileNameContainer.textContent = file.name;
                fileNameContainer.style.display = 'block';
            }
        }
        
        // Create preview
        this.createPreview(file);
    }
    
    validateFileType(file) {
        return this.options.allowedTypes.includes(file.type);
    }
    
    validateFileSize(file) {
        return file.size <= this.options.maxFileSize;
    }
    
    createPreview(file) {
        const reader = new FileReader();
        const previewContainer = this.preview.querySelector('.preview-container');
        
        reader.onload = (e) => {
            // Clear previous preview
            previewContainer.innerHTML = '';
            
            // Create image element
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'preview-image';
            previewContainer.appendChild(img);
            
            // Add delete button
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'delete-btn';
            deleteBtn.innerHTML = '×';
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent triggering click on container
                this.clearAll();
            });
            previewContainer.appendChild(deleteBtn);
        };
        
        reader.readAsDataURL(file);
    }
    
    clearPreview() {
        const previewContainer = this.preview.querySelector('.preview-container');
        previewContainer.innerHTML = '';
        
        // Add default content to show it's droppable
        const defaultContent = document.createElement('div');
        defaultContent.className = 'default-content';
        defaultContent.innerHTML = '<i class="fas fa-cloud-upload-alt"></i><p>Click or drop image here</p>';
        previewContainer.appendChild(defaultContent);
        
        // Clear file name
        const fileNameContainer = this.preview.querySelector('.file-name');
        if (fileNameContainer) {
            fileNameContainer.textContent = '';
            fileNameContainer.style.display = 'none';
        }
    }
    
    clearError() {
        const errorContainer = this.preview.querySelector('.error-message');
        if (errorContainer) {
            errorContainer.textContent = '';
            errorContainer.style.display = 'none';
        }
    }
    
    clearAll() {
        this.clearPreview();
        this.clearError();
        this.input.value = '';
        this.currentFile = null;
    }
    
    showError(message) {
        const errorContainer = this.preview.querySelector('.error-message');
        if (errorContainer) {
            errorContainer.textContent = message;
            errorContainer.style.display = 'block';
        }
    }
    
    validateForm() {
        // If image is required and no file is selected, show error
        if (this.options.isRequired && !this.currentFile) {
            this.showError('Please select an image');
            return false;
        }
        
        return true;
    }
    
    uploadFile() {
        return new Promise((resolve, reject) => {
            if (!this.currentFile) {
                reject(new Error('No file selected'));
                return;
            }
            
            const formData = new FormData();
            formData.append('image', this.currentFile);
            formData.append('action', 'upload');
            formData.append('type', this.options.uploadType);
            
            if (this.options.customName) {
                formData.append('custom_name', this.options.customName);
            }
            
            fetch(this.options.uploadUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                resolve(data);
            })
            .catch(error => {
                reject(error);
            });
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Example usage:
    // For product images
    if (document.querySelector('#product-image-upload')) {
        const productImageUploader = new ImageUploader({
            inputSelector: '#product-image-upload',
            previewSelector: '#product-image-preview',
            formSelector: '#product-form',
            uploadType: 'products'
        });
    }
    
    // For category images
    if (document.querySelector('#category-image-upload')) {
        const categoryImageUploader = new ImageUploader({
            inputSelector: '#category-image-upload',
            previewSelector: '#category-image-preview',
            formSelector: '#category-form',
            uploadType: 'categories'
        });
    }
}); 