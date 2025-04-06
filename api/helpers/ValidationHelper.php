<?php
class ValidationHelper {
    // Error messages
    private $errors = [];
    
    // Validate email
    public function validateEmail($email, $field_name = 'email', $required = true) {
        // Check if required
        if ($required && empty($email)) {
            $this->addError($field_name, 'Email is required.');
            return false;
        }
        
        // If not required and empty, skip validation
        if (!$required && empty($email)) {
            return true;
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field_name, 'Invalid email format.');
            return false;
        }
        
        return true;
    }
    
    // Validate password
    public function validatePassword($password, $field_name = 'password', $required = true, $min_length = 8) {
        // Check if required
        if ($required && empty($password)) {
            $this->addError($field_name, 'Password is required.');
            return false;
        }
        
        // If not required and empty, skip validation
        if (!$required && empty($password)) {
            return true;
        }
        
        // Check minimum length
        if (strlen($password) < $min_length) {
            $this->addError($field_name, "Password must be at least {$min_length} characters long.");
            return false;
        }
        
        return true;
    }
    
    // Validate text field
    public function validateText($text, $field_name, $required = true, $min_length = 0, $max_length = 0) {
        // Check if required
        if ($required && empty($text)) {
            $this->addError($field_name, ucfirst($field_name) . ' is required.');
            return false;
        }
        
        // If not required and empty, skip validation
        if (!$required && empty($text)) {
            return true;
        }
        
        // Check minimum length
        if ($min_length > 0 && strlen($text) < $min_length) {
            $this->addError($field_name, ucfirst($field_name) . " must be at least {$min_length} characters long.");
            return false;
        }
        
        // Check maximum length
        if ($max_length > 0 && strlen($text) > $max_length) {
            $this->addError($field_name, ucfirst($field_name) . " must not exceed {$max_length} characters.");
            return false;
        }
        
        return true;
    }
    
    // Validate numeric value
    public function validateNumeric($value, $field_name, $required = true, $min = null, $max = null) {
        // Check if required
        if ($required && ($value === null || $value === '')) {
            $this->addError($field_name, ucfirst($field_name) . ' is required.');
            return false;
        }
        
        // If not required and empty, skip validation
        if (!$required && ($value === null || $value === '')) {
            return true;
        }
        
        // Check if numeric
        if (!is_numeric($value)) {
            $this->addError($field_name, ucfirst($field_name) . ' must be a number.');
            return false;
        }
        
        // Check minimum value
        if ($min !== null && $value < $min) {
            $this->addError($field_name, ucfirst($field_name) . " must be at least {$min}.");
            return false;
        }
        
        // Check maximum value
        if ($max !== null && $value > $max) {
            $this->addError($field_name, ucfirst($field_name) . " must not exceed {$max}.");
            return false;
        }
        
        return true;
    }
    
    // Validate phone number
    public function validatePhone($phone, $field_name = 'phone', $required = true) {
        // Check if required
        if ($required && empty($phone)) {
            $this->addError($field_name, 'Phone number is required.');
            return false;
        }
        
        // If not required and empty, skip validation
        if (!$required && empty($phone)) {
            return true;
        }
        
        // Remove non-numeric characters
        $phone_numeric = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if phone has at least 10 digits (basic validation)
        if (strlen($phone_numeric) < 10) {
            $this->addError($field_name, 'Phone number must have at least 10 digits.');
            return false;
        }
        
        return true;
    }
    
    // Validate date
    public function validateDate($date, $field_name = 'date', $required = true, $format = 'Y-m-d') {
        // Check if required
        if ($required && empty($date)) {
            $this->addError($field_name, ucfirst($field_name) . ' is required.');
            return false;
        }
        
        // If not required and empty, skip validation
        if (!$required && empty($date)) {
            return true;
        }
        
        // Create DateTime object from format
        $d = DateTime::createFromFormat($format, $date);
        
        // Check if date is valid
        if ($d && $d->format($format) === $date) {
            return true;
        }
        
        $this->addError($field_name, 'Invalid date format. Expected format: ' . $format);
        return false;
    }
    
    // Validate URL
    public function validateUrl($url, $field_name = 'url', $required = true) {
        // Check if required
        if ($required && empty($url)) {
            $this->addError($field_name, ucfirst($field_name) . ' is required.');
            return false;
        }
        
        // If not required and empty, skip validation
        if (!$required && empty($url)) {
            return true;
        }
        
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->addError($field_name, 'Invalid URL format.');
            return false;
        }
        
        return true;
    }
    
    // Validate file upload
    public function validateFile($file, $field_name = 'file', $required = true, $allowed_types = [], $max_size = 0) {
        // Check if required
        if ($required && (empty($file) || !isset($file['tmp_name']) || empty($file['tmp_name']))) {
            $this->addError($field_name, 'File is required.');
            return false;
        }
        
        // If not required and empty, skip validation
        if (!$required && (empty($file) || !isset($file['tmp_name']) || empty($file['tmp_name']))) {
            return true;
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $this->addError($field_name, 'File size exceeds the maximum allowed size.');
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $this->addError($field_name, 'File was only partially uploaded.');
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $this->addError($field_name, 'No file was uploaded.');
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $this->addError($field_name, 'Missing a temporary folder.');
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $this->addError($field_name, 'Failed to write file to disk.');
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $this->addError($field_name, 'A PHP extension stopped the file upload.');
                    break;
                default:
                    $this->addError($field_name, 'Unknown upload error.');
            }
            return false;
        }
        
        // Check file size
        if ($max_size > 0 && $file['size'] > $max_size) {
            $this->addError($field_name, 'File size exceeds the maximum allowed size of ' . $this->formatBytes($max_size) . '.');
            return false;
        }
        
        // Check file type
        if (!empty($allowed_types)) {
            $file_type = mime_content_type($file['tmp_name']);
            if (!in_array($file_type, $allowed_types)) {
                $this->addError($field_name, 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types));
                return false;
            }
        }
        
        return true;
    }
    
    // Validate array
    public function validateArray($array, $field_name = 'items', $required = true, $min_items = 0, $max_items = 0) {
        // Check if required
        if ($required && (empty($array) || !is_array($array))) {
            $this->addError($field_name, ucfirst($field_name) . ' is required and must be an array.');
            return false;
        }
        
        // If not required and empty, skip validation
        if (!$required && (empty($array) || !is_array($array))) {
            return true;
        }
        
        // Check minimum items
        if ($min_items > 0 && count($array) < $min_items) {
            $this->addError($field_name, ucfirst($field_name) . " must have at least {$min_items} items.");
            return false;
        }
        
        // Check maximum items
        if ($max_items > 0 && count($array) > $max_items) {
            $this->addError($field_name, ucfirst($field_name) . " must not exceed {$max_items} items.");
            return false;
        }
        
        return true;
    }
    
    // Validate enum value
    public function validateEnum($value, $allowed_values, $field_name, $required = true) {
        // Check if required
        if ($required && ($value === null || $value === '')) {
            $this->addError($field_name, ucfirst($field_name) . ' is required.');
            return false;
        }
        
        // If not required and empty, skip validation
        if (!$required && ($value === null || $value === '')) {
            return true;
        }
        
        // Check if value is in allowed values
        if (!in_array($value, $allowed_values)) {
            $this->addError($field_name, ucfirst($field_name) . ' must be one of: ' . implode(', ', $allowed_values));
            return false;
        }
        
        return true;
    }
    
    // Validate JSON data
    public function validateJson($data, $field_name = 'json', $required = true) {
        // Check if required
        if ($required && empty($data)) {
            $this->addError($field_name, 'JSON data is required.');
            return false;
        }
        
        // If not required and empty, skip validation
        if (!$required && empty($data)) {
            return true;
        }
        
        // Check if valid JSON
        json_decode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError($field_name, 'Invalid JSON data: ' . json_last_error_msg());
            return false;
        }
        
        return true;
    }
    
    // Add error message
    public function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }
    
    // Get all errors
    public function getErrors() {
        return $this->errors;
    }
    
    // Check if there are any validation errors
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    // Get formatted error message
    public function getErrorMessage() {
        $messages = [];
        
        foreach ($this->errors as $field => $field_errors) {
            foreach ($field_errors as $error) {
                $messages[] = $error;
            }
        }
        
        return implode(' ', $messages);
    }
    
    // Get error response array
    public function getErrorResponse() {
        return [
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $this->errors
        ];
    }
    
    // Format bytes to human-readable format
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    // Sanitize input data
    public function sanitize($data) {
        // If data is an array, sanitize each element
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $sanitized[$key] = $this->sanitize($value);
            }
            return $sanitized;
        }
        
        // If data is a string, sanitize it
        if (is_string($data)) {
            return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
        }
        
        // Return data as is for other types
        return $data;
    }
}
?> 