<?php
// Include model and helper classes
include_once ROOT_PATH . '/models/Setting.php';

class SettingController {
    private $setting;
    private $conn;
    private $authHelper;
    
    // Constructor
    public function __construct() {
        // Get database connection
        $database = new Database();
        $this->conn = $database->getConnection();
        
        // Initialize setting model
        $this->setting = new Setting($this->conn);
        
        // Initialize auth helper
        $this->authHelper = new AuthHelper();
    }
    
    /**
     * Get all settings (admin only)
     * @return void
     */
    public function getAll() {
        // Check if user is admin
        $user = $this->authHelper->validateAdmin();
        if(!$user) {
            return;
        }
        
        // Get settings
        $stmt = $this->setting->getAll();
        $num = $stmt->rowCount();
        
        // Check if any settings found
        if($num > 0) {
            // Settings array
            $settings_arr = array();
            $settings_arr["settings"] = array();
            
            // Retrieve table contents
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Extract row
                extract($row);
                
                $setting_item = array(
                    "id" => $id,
                    "setting_key" => $setting_key,
                    "setting_value" => $setting_value,
                    "setting_type" => $setting_type,
                    "setting_label" => $setting_label,
                    "setting_description" => $setting_description,
                    "setting_group" => $setting_group,
                    "setting_options" => $setting_options,
                    "is_public" => $is_public,
                    "sort_order" => $sort_order,
                    "created_at" => $created_at,
                    "updated_at" => $updated_at
                );
                
                // Push to "settings" array
                array_push($settings_arr["settings"], $setting_item);
            }
            
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode($settings_arr);
        } else {
            // Set response code - 404 Not found
            http_response_code(404);
            
            // Tell the user no settings found
            echo json_encode(array("message" => "No settings found."));
        }
    }
    
    /**
     * Get settings by group (admin only)
     * @param string $group Group name
     * @return void
     */
    public function getByGroup($group) {
        // Check if user is admin
        $user = $this->authHelper->validateAdmin();
        if(!$user) {
            return;
        }
        
        // Get settings by group
        $stmt = $this->setting->getByGroup($group);
        $num = $stmt->rowCount();
        
        // Check if any settings found
        if($num > 0) {
            // Settings array
            $settings_arr = array();
            $settings_arr["settings"] = array();
            $settings_arr["group"] = $group;
            
            // Retrieve table contents
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Extract row
                extract($row);
                
                $setting_item = array(
                    "id" => $id,
                    "setting_key" => $setting_key,
                    "setting_value" => $setting_value,
                    "setting_type" => $setting_type,
                    "setting_label" => $setting_label,
                    "setting_description" => $setting_description,
                    "setting_group" => $setting_group,
                    "setting_options" => $setting_options,
                    "is_public" => $is_public,
                    "sort_order" => $sort_order,
                    "created_at" => $created_at,
                    "updated_at" => $updated_at
                );
                
                // Push to "settings" array
                array_push($settings_arr["settings"], $setting_item);
            }
            
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode($settings_arr);
        } else {
            // Set response code - 404 Not found
            http_response_code(404);
            
            // Tell the user no settings found
            echo json_encode(array("message" => "No settings found for group: " . $group));
        }
    }
    
    /**
     * Get all available setting groups (admin only)
     * @return void
     */
    public function getGroups() {
        // Check if user is admin
        $user = $this->authHelper->validateAdmin();
        if(!$user) {
            return;
        }
        
        // Get groups
        $groups = $this->setting->getGroups();
        
        // Check if any groups found
        if(count($groups) > 0) {
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode(array("groups" => $groups));
        } else {
            // Set response code - 404 Not found
            http_response_code(404);
            
            // Tell the user no groups found
            echo json_encode(array("message" => "No setting groups found."));
        }
    }
    
    /**
     * Get public settings (accessible without authentication)
     * @return void
     */
    public function getPublicSettings() {
        // Get public settings
        $stmt = $this->setting->getPublicSettings();
        $num = $stmt->rowCount();
        
        // Check if any settings found
        if($num > 0) {
            // Settings array
            $settings_arr = array();
            
            // Retrieve table contents
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Add to settings array with key as index
                $settings_arr[$row['setting_key']] = $row['setting_value'];
            }
            
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode($settings_arr);
        } else {
            // Set response code - 404 Not found
            http_response_code(404);
            
            // Tell the user no settings found
            echo json_encode(array("message" => "No public settings found."));
        }
    }
    
    /**
     * Get setting by key
     * @param string $key Setting key
     * @return void
     */
    public function getByKey($key) {
        // Check if setting exists
        if($this->setting->getByKey($key)) {
            // Determine if setting is public or user is admin
            if($this->setting->is_public == 1 || $this->authHelper->validateAdmin()) {
                // Create array
                $setting_arr = array(
                    "id" => $this->setting->id,
                    "setting_key" => $this->setting->setting_key,
                    "setting_value" => $this->setting->setting_value,
                    "setting_type" => $this->setting->setting_type,
                    "setting_label" => $this->setting->setting_label,
                    "setting_description" => $this->setting->setting_description,
                    "setting_group" => $this->setting->setting_group,
                    "setting_options" => $this->setting->setting_options,
                    "is_public" => $this->setting->is_public,
                    "sort_order" => $this->setting->sort_order,
                    "created_at" => $this->setting->created_at,
                    "updated_at" => $this->setting->updated_at
                );
                
                // Set response code - 200 OK
                http_response_code(200);
                
                // Response data
                echo json_encode($setting_arr);
            } else {
                // Set response code - 403 Forbidden
                http_response_code(403);
                
                // Tell the user they don't have permission
                echo json_encode(array("message" => "You don't have permission to access this setting."));
            }
        } else {
            // Set response code - 404 Not found
            http_response_code(404);
            
            // Tell the user setting not found
            echo json_encode(array("message" => "Setting not found."));
        }
    }
    
    /**
     * Update setting value (admin only)
     * @param string $key Setting key
     * @return void
     */
    public function updateValue($key) {
        // Check if user is admin
        $user = $this->authHelper->validateAdmin();
        if(!$user) {
            return;
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if data is not empty
        if(isset($data->value)) {
            // Check if setting exists
            if($this->setting->getByKey($key)) {
                // Update setting value
                if($this->setting->updateValue($key, $data->value)) {
                    // Set response code - 200 OK
                    http_response_code(200);
                    
                    // Response data
                    echo json_encode(array(
                        "message" => "Setting was updated successfully.",
                        "key" => $key,
                        "value" => $data->value
                    ));
                } else {
                    // Set response code - 503 service unavailable
                    http_response_code(503);
                    
                    // Tell the user
                    echo json_encode(array("message" => "Unable to update setting."));
                }
            } else {
                // Set response code - 404 Not found
                http_response_code(404);
                
                // Tell the user setting not found
                echo json_encode(array("message" => "Setting not found."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user data is incomplete
            echo json_encode(array("message" => "Unable to update setting. Value is required."));
        }
    }
    
    /**
     * Batch update multiple settings (admin only)
     * @return void
     */
    public function batchUpdate() {
        // Check if user is admin
        $user = $this->authHelper->validateAdmin();
        if(!$user) {
            return;
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if data is not empty
        if(isset($data->settings) && is_object($data->settings)) {
            // Convert object to array
            $settings = get_object_vars($data->settings);
            
            // Update settings
            if($this->setting->batchUpdate($settings)) {
                // Set response code - 200 OK
                http_response_code(200);
                
                // Response data
                echo json_encode(array(
                    "message" => "Settings were updated successfully.",
                    "updated_count" => count($settings)
                ));
            } else {
                // Set response code - 503 service unavailable
                http_response_code(503);
                
                // Tell the user
                echo json_encode(array("message" => "Unable to update settings."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user data is incomplete
            echo json_encode(array("message" => "Unable to update settings. Settings object is required."));
        }
    }
    
    /**
     * Create new setting (admin only)
     * @return void
     */
    public function create() {
        // Check if user is admin
        $user = $this->authHelper->validateAdmin();
        if(!$user) {
            return;
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if data is not empty
        if(
            !empty($data->setting_key) &&
            !empty($data->setting_type) &&
            !empty($data->setting_label) &&
            !empty($data->setting_group)
        ) {
            // Check if setting key already exists
            if($this->setting->getByKey($data->setting_key)) {
                // Set response code - 400 bad request
                http_response_code(400);
                
                // Tell the user
                echo json_encode(array("message" => "Setting key already exists."));
                return;
            }
            
            // Set setting properties
            $this->setting->setting_key = $data->setting_key;
            $this->setting->setting_value = isset($data->setting_value) ? $data->setting_value : '';
            $this->setting->setting_type = $data->setting_type;
            $this->setting->setting_label = $data->setting_label;
            $this->setting->setting_description = isset($data->setting_description) ? $data->setting_description : '';
            $this->setting->setting_group = $data->setting_group;
            $this->setting->setting_options = isset($data->setting_options) ? $data->setting_options : '';
            $this->setting->is_public = isset($data->is_public) ? (int)$data->is_public : 0;
            $this->setting->sort_order = isset($data->sort_order) ? (int)$data->sort_order : 0;
            
            // Create setting
            if($this->setting->create()) {
                // Set response code - 201 created
                http_response_code(201);
                
                // Response data
                echo json_encode(array(
                    "message" => "Setting was created successfully.",
                    "setting_key" => $this->setting->setting_key
                ));
            } else {
                // Set response code - 503 service unavailable
                http_response_code(503);
                
                // Tell the user
                echo json_encode(array("message" => "Unable to create setting."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user data is incomplete
            echo json_encode(array("message" => "Unable to create setting. Data is incomplete."));
        }
    }
    
    /**
     * Update setting (admin only)
     * @param string $key Setting key
     * @return void
     */
    public function update($key) {
        // Check if user is admin
        $user = $this->authHelper->validateAdmin();
        if(!$user) {
            return;
        }
        
        // Check if setting exists
        if(!$this->setting->getByKey($key)) {
            // Set response code - 404 not found
            http_response_code(404);
            
            // Tell the user setting not found
            echo json_encode(array("message" => "Setting not found."));
            return;
        }
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Set setting properties
        if(isset($data->setting_value)) $this->setting->setting_value = $data->setting_value;
        if(isset($data->setting_type)) $this->setting->setting_type = $data->setting_type;
        if(isset($data->setting_label)) $this->setting->setting_label = $data->setting_label;
        if(isset($data->setting_description)) $this->setting->setting_description = $data->setting_description;
        if(isset($data->setting_group)) $this->setting->setting_group = $data->setting_group;
        if(isset($data->setting_options)) $this->setting->setting_options = $data->setting_options;
        if(isset($data->is_public)) $this->setting->is_public = (int)$data->is_public;
        if(isset($data->sort_order)) $this->setting->sort_order = (int)$data->sort_order;
        
        // Update setting
        if($this->setting->update()) {
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode(array(
                "message" => "Setting was updated successfully.",
                "setting_key" => $key
            ));
        } else {
            // Set response code - 503 service unavailable
            http_response_code(503);
            
            // Tell the user
            echo json_encode(array("message" => "Unable to update setting."));
        }
    }
    
    /**
     * Delete setting (admin only)
     * @param string $key Setting key
     * @return void
     */
    public function delete($key) {
        // Check if user is admin
        $user = $this->authHelper->validateAdmin();
        if(!$user) {
            return;
        }
        
        // Check if setting exists
        if(!$this->setting->getByKey($key)) {
            // Set response code - 404 not found
            http_response_code(404);
            
            // Tell the user setting not found
            echo json_encode(array("message" => "Setting not found."));
            return;
        }
        
        // Delete setting
        if($this->setting->delete($key)) {
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode(array("message" => "Setting was deleted successfully."));
        } else {
            // Set response code - 503 service unavailable
            http_response_code(503);
            
            // Tell the user
            echo json_encode(array("message" => "Unable to delete setting."));
        }
    }
    
    /**
     * Handle image upload for settings (admin only)
     * @param string $key Setting key
     * @return void
     */
    public function uploadImage($key) {
        // Check if user is admin
        $user = $this->authHelper->validateAdmin();
        if(!$user) {
            return;
        }
        
        // Check if file was uploaded
        if(isset($_FILES['image'])) {
            // Upload image
            $result = $this->setting->uploadImage($key, $_FILES['image']);
            
            if($result) {
                // Set response code - 200 OK
                http_response_code(200);
                
                // Response data
                echo json_encode(array(
                    "message" => "Image was uploaded successfully.",
                    "file_path" => $result
                ));
            } else {
                // Set response code - 400 bad request
                http_response_code(400);
                
                // Tell the user
                echo json_encode(array("message" => "Unable to upload image. Invalid file or setting type."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user
            echo json_encode(array("message" => "No image file was uploaded."));
        }
    }
}
?> 