<?php
/**
 * Terral Online Production System
 * Settings Model Class
 */

class Setting {
    // Database connection and table name
    private $conn;
    private $table_name = "settings";
    
    // Object properties
    public $id;
    public $setting_key;
    public $setting_value;
    public $setting_type;
    public $setting_label;
    public $setting_description;
    public $setting_group;
    public $setting_options;
    public $is_public;
    public $sort_order;
    public $created_at;
    public $updated_at;
    
    /**
     * Constructor with DB connection
     * @param PDO $db Database connection object
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Get a setting by key
     * @param string $key Setting key
     * @return bool True if setting found, false otherwise
     */
    public function getByKey($key) {
        // Query to get setting by key
        $query = "SELECT * FROM " . $this->table_name . " WHERE setting_key = ? LIMIT 0,1";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize key
        $key = htmlspecialchars(strip_tags($key));
        
        // Bind key parameter
        $stmt->bindParam(1, $key);
        
        // Execute query
        $stmt->execute();
        
        // Get record count
        $num = $stmt->rowCount();
        
        // If setting exists
        if($num > 0) {
            // Get record details
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set values to object properties
            $this->id = $row['id'];
            $this->setting_key = $row['setting_key'];
            $this->setting_value = $row['setting_value'];
            $this->setting_type = $row['setting_type'];
            $this->setting_label = $row['setting_label'];
            $this->setting_description = $row['setting_description'];
            $this->setting_group = $row['setting_group'];
            $this->setting_options = $row['setting_options'];
            $this->is_public = $row['is_public'];
            $this->sort_order = $row['sort_order'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get multiple settings by group
     * @param string $group Settings group
     * @return PDOStatement The executed query statement
     */
    public function getByGroup($group) {
        try {
            // First try with sort_order
            $query = "SELECT * FROM " . $this->table_name . " 
                      WHERE setting_group = ? 
                      ORDER BY sort_order ASC";
            
            // Prepare query
            $stmt = $this->conn->prepare($query);
            
            // Sanitize group
            $group = htmlspecialchars(strip_tags($group));
            
            // Bind group parameter
            $stmt->bindParam(1, $group);
            
            // Execute query
            $stmt->execute();
            
            return $stmt;
        } catch (PDOException $e) {
            // If sort_order column doesn't exist, try without it
            if (strpos($e->getMessage(), "Unknown column 'sort_order'") !== false) {
                $query = "SELECT * FROM " . $this->table_name . " 
                          WHERE setting_group = ?";
                
                // Prepare query
                $stmt = $this->conn->prepare($query);
                
                // Bind group parameter
                $stmt->bindParam(1, $group);
                
                // Execute query
                $stmt->execute();
                
                return $stmt;
            } else {
                // Re-throw if it's not the expected error
                throw $e;
            }
        }
    }
    
    /**
     * Get all settings
     * @return PDOStatement The executed query statement
     */
    public function getAll() {
        try {
            // First try with sort_order
            $query = "SELECT * FROM " . $this->table_name . " 
                      ORDER BY setting_group, sort_order ASC";
            
            // Prepare query
            $stmt = $this->conn->prepare($query);
            
            // Execute query
            $stmt->execute();
            
            return $stmt;
        } catch (PDOException $e) {
            // If sort_order column doesn't exist, try without it
            if (strpos($e->getMessage(), "Unknown column 'sort_order'") !== false) {
                $query = "SELECT * FROM " . $this->table_name . " 
                          ORDER BY setting_group ASC";
                
                // Prepare query
                $stmt = $this->conn->prepare($query);
                
                // Execute query
                $stmt->execute();
                
                return $stmt;
            } else {
                // Re-throw if it's not the expected error
                throw $e;
            }
        }
    }
    
    /**
     * Get all public settings
     * @return PDOStatement The executed query statement
     */
    public function getPublicSettings() {
        try {
            // First try with sort_order
            $query = "SELECT * FROM " . $this->table_name . " 
                      WHERE is_public = 1 
                      ORDER BY setting_group, sort_order ASC";
            
            // Prepare query
            $stmt = $this->conn->prepare($query);
            
            // Execute query
            $stmt->execute();
            
            return $stmt;
        } catch (PDOException $e) {
            // If sort_order column doesn't exist, try without it
            if (strpos($e->getMessage(), "Unknown column 'sort_order'") !== false) {
                $query = "SELECT * FROM " . $this->table_name . " 
                          WHERE is_public = 1 
                          ORDER BY setting_group ASC";
                
                // Prepare query
                $stmt = $this->conn->prepare($query);
                
                // Execute query
                $stmt->execute();
                
                return $stmt;
            } else {
                // Re-throw if it's not the expected error
                throw $e;
            }
        }
    }
    
    /**
     * Get settings by multiple keys
     * @param array $keys Array of setting keys
     * @return array Associative array of settings
     */
    public function getByKeys($keys) {
        // Generate placeholders for IN clause
        $placeholders = str_repeat('?,', count($keys) - 1) . '?';
        
        // Query to get settings by multiple keys
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE setting_key IN ($placeholders)";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize keys
        for($i = 0; $i < count($keys); $i++) {
            $keys[$i] = htmlspecialchars(strip_tags($keys[$i]));
            $stmt->bindParam($i + 1, $keys[$i]);
        }
        
        // Execute query
        $stmt->execute();
        
        // Result array
        $result = array();
        
        // Fetch settings
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        
        return $result;
    }
    
    /**
     * Update a setting value
     * @param string $key Setting key
     * @param string $value New setting value
     * @return bool True if updated successfully, false otherwise
     */
    public function updateValue($key, $value) {
        // Query to update setting value
        $query = "UPDATE " . $this->table_name . " 
                  SET setting_value = :value 
                  WHERE setting_key = :key";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $key = htmlspecialchars(strip_tags($key));
        
        // For image paths, sanitize differently to preserve path structure
        if($this->getByKey($key) && $this->setting_type == 'image') {
            $value = filter_var($value, FILTER_SANITIZE_URL);
        } else {
            $value = htmlspecialchars(strip_tags($value));
        }
        
        // Bind parameters
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Create a new setting
     * @return bool True if created successfully, false otherwise
     */
    public function create() {
        // Query to insert a new setting
        $query = "INSERT INTO " . $this->table_name . " 
                  SET 
                    setting_key = :setting_key,
                    setting_value = :setting_value,
                    setting_type = :setting_type,
                    setting_label = :setting_label,
                    setting_description = :setting_description,
                    setting_group = :setting_group,
                    setting_options = :setting_options,
                    is_public = :is_public,
                    sort_order = :sort_order";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->setting_key = htmlspecialchars(strip_tags($this->setting_key));
        $this->setting_value = htmlspecialchars(strip_tags($this->setting_value));
        $this->setting_type = htmlspecialchars(strip_tags($this->setting_type));
        $this->setting_label = htmlspecialchars(strip_tags($this->setting_label));
        $this->setting_description = htmlspecialchars(strip_tags($this->setting_description));
        $this->setting_group = htmlspecialchars(strip_tags($this->setting_group));
        $this->setting_options = htmlspecialchars(strip_tags($this->setting_options));
        $this->is_public = (int)$this->is_public;
        $this->sort_order = (int)$this->sort_order;
        
        // Bind parameters
        $stmt->bindParam(':setting_key', $this->setting_key);
        $stmt->bindParam(':setting_value', $this->setting_value);
        $stmt->bindParam(':setting_type', $this->setting_type);
        $stmt->bindParam(':setting_label', $this->setting_label);
        $stmt->bindParam(':setting_description', $this->setting_description);
        $stmt->bindParam(':setting_group', $this->setting_group);
        $stmt->bindParam(':setting_options', $this->setting_options);
        $stmt->bindParam(':is_public', $this->is_public, PDO::PARAM_INT);
        $stmt->bindParam(':sort_order', $this->sort_order, PDO::PARAM_INT);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Update a setting completely
     * @return bool True if updated successfully, false otherwise
     */
    public function update() {
        // Query to update a setting
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                    setting_value = :setting_value,
                    setting_type = :setting_type,
                    setting_label = :setting_label,
                    setting_description = :setting_description,
                    setting_group = :setting_group,
                    setting_options = :setting_options,
                    is_public = :is_public,
                    sort_order = :sort_order 
                  WHERE setting_key = :setting_key";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->setting_key = htmlspecialchars(strip_tags($this->setting_key));
        $this->setting_value = htmlspecialchars(strip_tags($this->setting_value));
        $this->setting_type = htmlspecialchars(strip_tags($this->setting_type));
        $this->setting_label = htmlspecialchars(strip_tags($this->setting_label));
        $this->setting_description = htmlspecialchars(strip_tags($this->setting_description));
        $this->setting_group = htmlspecialchars(strip_tags($this->setting_group));
        $this->setting_options = htmlspecialchars(strip_tags($this->setting_options));
        $this->is_public = (int)$this->is_public;
        $this->sort_order = (int)$this->sort_order;
        
        // Bind parameters
        $stmt->bindParam(':setting_key', $this->setting_key);
        $stmt->bindParam(':setting_value', $this->setting_value);
        $stmt->bindParam(':setting_type', $this->setting_type);
        $stmt->bindParam(':setting_label', $this->setting_label);
        $stmt->bindParam(':setting_description', $this->setting_description);
        $stmt->bindParam(':setting_group', $this->setting_group);
        $stmt->bindParam(':setting_options', $this->setting_options);
        $stmt->bindParam(':is_public', $this->is_public, PDO::PARAM_INT);
        $stmt->bindParam(':sort_order', $this->sort_order, PDO::PARAM_INT);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete a setting
     * @param string $key Setting key
     * @return bool True if deleted successfully, false otherwise
     */
    public function delete($key) {
        // Query to delete a setting
        $query = "DELETE FROM " . $this->table_name . " WHERE setting_key = ?";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize key
        $key = htmlspecialchars(strip_tags($key));
        
        // Bind key parameter
        $stmt->bindParam(1, $key);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all available setting groups
     * @return array Array of setting groups
     */
    public function getGroups() {
        // Query to get all setting groups
        $query = "SELECT DISTINCT setting_group FROM " . $this->table_name . " ORDER BY setting_group ASC";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        // Result array
        $result = array();
        
        // Fetch groups
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($result, $row['setting_group']);
        }
        
        return $result;
    }
    
    /**
     * Handle image upload for settings
     * @param string $key Setting key
     * @param array $file Uploaded file array from $_FILES
     * @return bool|string Path to the saved image or false on failure
     */
    public function uploadImage($key, $file) {
        // Check if setting exists and is of type image
        if($this->getByKey($key) && $this->setting_type == 'image') {
            // Set upload directory
            $upload_dir = ROOT_PATH . '/uploads/settings/';
            
            // Create directory if not exists
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $filename = uniqid() . '_' . basename($file['name']);
            $target_file = $upload_dir . $filename;
            
            // Check if file is an actual image
            $check = getimagesize($file['tmp_name']);
            if($check === false) {
                return false;
            }
            
            // Check file size (limit to 2MB)
            if($file['size'] > 2000000) {
                return false;
            }
            
            // Allow only certain file formats
            $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            if($file_type != "jpg" && $file_type != "png" && $file_type != "jpeg" && $file_type != "gif") {
                return false;
            }
            
            // Try to upload file
            if(move_uploaded_file($file['tmp_name'], $target_file)) {
                // Update setting value with the new path
                $relative_path = 'uploads/settings/' . $filename;
                $this->updateValue($key, $relative_path);
                return $relative_path;
            }
        }
        
        return false;
    }
    
    /**
     * Batch update multiple settings
     * @param array $settings Associative array of settings (key => value)
     * @return bool True if all updated successfully, false otherwise
     */
    public function batchUpdate($settings) {
        $success = true;
        
        // Begin transaction
        $this->conn->beginTransaction();
        
        try {
            foreach($settings as $key => $value) {
                if(!$this->updateValue($key, $value)) {
                    $success = false;
                    break;
                }
            }
            
            if($success) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                return false;
            }
        } catch(Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
?> 