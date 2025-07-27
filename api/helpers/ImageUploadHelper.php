<?php
/**
 * Image Upload Helper
 * 
 * This class handles all image upload functionality with support for multiple
 * image formats (JPG, PNG, GIF, WebP), image validation, and proper error handling.
 */
class ImageUploadHelper {
    // Allowed mime types with their extensions
    private $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png', 
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    // Default upload directories
    private $uploadDirs = [
        'products' => '/uploads/products/',
        'categories' => '/uploads/categories/',
        'customizations' => '/uploads/customizations/',
        'logos' => '/uploads/logos/',
        'avatars' => '/uploads/avatars/'
    ];
    
    // Max file size in bytes (5MB)
    private $maxFileSize = 5242880;
    
    /**
     * Upload an image file
     * 
     * @param array $file The $_FILES array element
     * @param string $type The type of upload (products, categories, etc.)
     * @param string $customName Custom filename (optional)
     * @return array Result with status and data
     */
    public function uploadImage($file, $type, $customName = null) {
        // Validate upload directory type
        if (!isset($this->uploadDirs[$type])) {
            return [
                'success' => false,
                'message' => 'Invalid upload type specified'
            ];
        }
        
        // Check if file was uploaded properly
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = $this->getUploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE);
            return [
                'success' => false,
                'message' => $errorMessage
            ];
        }
        
        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'success' => false,
                'message' => 'File is too large. Maximum file size is ' . ($this->maxFileSize / 1048576) . 'MB'
            ];
        }
        
        // Get mime type and validate
        $mimeType = $this->getMimeType($file['tmp_name']);
        if (!array_key_exists($mimeType, $this->allowedTypes)) {
            return [
                'success' => false,
                'message' => 'Invalid file type. Allowed types: JPG, PNG, GIF, WebP'
            ];
        }
        
        // Create upload directory if it doesn't exist
        $uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/terral' . $this->uploadDirs[$type];
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }
        
        // Also create the secondary upload path for consistency
        $secondaryUploadPath = $_SERVER['DOCUMENT_ROOT'] . '/terral/api' . $this->uploadDirs[$type];
        if (!file_exists($secondaryUploadPath)) {
            mkdir($secondaryUploadPath, 0777, true);
        }
        
        // Generate filename
        $extension = $this->allowedTypes[$mimeType];
        $filename = $customName ?? uniqid('img_') . '_' . time();
        $filename = $this->sanitizeFilename($filename) . '.' . $extension;
        $targetFile = $uploadPath . $filename;
        $secondaryTargetFile = $secondaryUploadPath . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            // Also copy to secondary location for admin dashboard compatibility
            copy($targetFile, $secondaryTargetFile);
            
            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'filename' => $filename,
                'filepath' => $this->uploadDirs[$type] . $filename,
                'fullpath' => $targetFile,
                'mime_type' => $mimeType,
                'extension' => $extension
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to upload file. Please try again.'
            ];
        }
    }
    
    /**
     * Validate an image before upload (without moving it)
     * 
     * @param array $file The $_FILES array element
     * @return array Result with status and data
     */
    public function validateImage($file) {
        // Check if file was uploaded properly
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = $this->getUploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE);
            return [
                'success' => false,
                'message' => $errorMessage
            ];
        }
        
        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'success' => false,
                'message' => 'File is too large. Maximum file size is ' . ($this->maxFileSize / 1048576) . 'MB'
            ];
        }
        
        // Get mime type and validate
        $mimeType = $this->getMimeType($file['tmp_name']);
        if (!array_key_exists($mimeType, $this->allowedTypes)) {
            return [
                'success' => false,
                'message' => 'Invalid file type. Allowed types: JPG, PNG, GIF, WebP'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'File is valid',
            'mime_type' => $mimeType,
            'extension' => $this->allowedTypes[$mimeType],
            'size' => $file['size']
        ];
    }
    
    /**
     * Delete an image file
     * 
     * @param string $filename The filename to delete
     * @param string $type The type of upload (products, categories, etc.)
     * @return array Result with status and message
     */
    public function deleteImage($filename, $type) {
        if (!isset($this->uploadDirs[$type])) {
            return [
                'success' => false,
                'message' => 'Invalid upload type specified'
            ];
        }
        
        $filepath = $_SERVER['DOCUMENT_ROOT'] . '/terral' . $this->uploadDirs[$type] . $filename;
        
        if (file_exists($filepath)) {
            if (unlink($filepath)) {
                return [
                    'success' => true,
                    'message' => 'File deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to delete file'
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'File does not exist'
            ];
        }
    }
    
    /**
     * Get error message for upload error code
     * 
     * @param int $errorCode Upload error code
     * @return string Error message
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error';
        }
    }
    
    /**
     * Get MIME type of file
     * 
     * @param string $filePath Path to file
     * @return string MIME type
     */
    private function getMimeType($filePath) {
        // Use finfo for more accurate MIME detection
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mimeType;
        }
        
        // Fallback to exif_imagetype
        if (function_exists('exif_imagetype')) {
            $imageType = exif_imagetype($filePath);
            if ($imageType !== false) {
                return image_type_to_mime_type($imageType);
            }
        }
        
        // Last resort - use the original file's reported mime type
        return mime_content_type($filePath);
    }
    
    /**
     * Sanitize filename for security
     * 
     * @param string $filename Filename to sanitize
     * @return string Sanitized filename
     */
    private function sanitizeFilename($filename) {
        // Remove any character that is not alphanumeric, underscore, dash or dot
        $filename = preg_replace('/[^\w\-\.]/', '_', $filename);
        
        // Remove multiple dots and make sure there's no executable extension
        $filename = preg_replace('/\.{2,}/', '.', $filename);
        
        return $filename;
    }
}
?>