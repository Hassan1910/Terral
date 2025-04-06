<?php
/**
 * Settings Helper Class
 * Provides helper methods for retrieving and using system settings
 */

class SettingsHelper {
    private $conn;
    private static $settings_cache = null;
    
    /**
     * Constructor
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Get a specific setting value by key
     * @param string $key Setting key
     * @param mixed $default Default value if setting is not found
     * @return mixed Setting value or default value
     */
    public function get($key, $default = null) {
        $settings = $this->getAllSettings();
        
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Get all settings
     * @return array Associative array of all settings (key => value)
     */
    public function getAllSettings() {
        // Return cached settings if available
        if (self::$settings_cache !== null) {
            return self::$settings_cache;
        }
        
        // Get all settings from database
        $query = "SELECT setting_key, setting_value FROM settings";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Cache the settings
        self::$settings_cache = $settings;
        
        return $settings;
    }
    
    /**
     * Get settings by group
     * @param string $group Group name
     * @return array Associative array of settings in the specified group
     */
    public function getSettingsByGroup($group) {
        $query = "SELECT setting_key, setting_value FROM settings WHERE setting_group = :group";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':group', $group);
        $stmt->execute();
        
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    }
    
    /**
     * Clear the settings cache
     * Call this after updating settings to ensure fresh data
     */
    public function clearCache() {
        self::$settings_cache = null;
    }
    
    /**
     * Get site logo URL
     * @return string Logo URL or default logo
     */
    public function getLogo() {
        $logo = $this->get('logo', 'assets/img/logo.png');
        return $logo;
    }
    
    /**
     * Get site name
     * @return string Site name
     */
    public function getSiteName() {
        return $this->get('site_name', 'Terral Online Production System');
    }
    
    /**
     * Get site description
     * @return string Site description
     */
    public function getSiteDescription() {
        return $this->get('site_description', 'Customize and order printed/branded products online');
    }
    
    /**
     * Get primary color
     * @return string Primary color hex code
     */
    public function getPrimaryColor() {
        return $this->get('primary_color', '#3498db');
    }
    
    /**
     * Get footer text
     * @return string Footer text
     */
    public function getFooterText() {
        return $this->get('footer_text', '© ' . date('Y') . ' Terral Online Production System. All rights reserved.');
    }
    
    /**
     * Get social media links
     * @return array Social media links
     */
    public function getSocialLinks() {
        return [
            'facebook' => $this->get('facebook_url', ''),
            'twitter' => $this->get('twitter_url', ''),
            'instagram' => $this->get('instagram_url', ''),
            'linkedin' => $this->get('linkedin_url', ''),
            'youtube' => $this->get('youtube_url', '')
        ];
    }
    
    /**
     * Get store info
     * @return array Store information
     */
    public function getStoreInfo() {
        return [
            'name' => $this->get('store_name', 'Terral Store'),
            'tagline' => $this->get('store_tagline', 'Custom Printing & Branding'),
            'phone' => $this->get('store_phone', '+254700000000'),
            'email' => $this->get('store_email', 'info@terral.com'),
            'address' => $this->get('store_address', '123 Business Street'),
            'city' => $this->get('store_city', 'Nairobi'),
            'state' => $this->get('store_state', 'Nairobi County'),
            'zip' => $this->get('store_zip', '00100'),
            'country' => $this->get('store_country', 'Kenya'),
            'business_hours' => $this->get('business_hours', 'Mon-Fri: 9am-5pm, Sat: 10am-2pm, Sun: Closed')
        ];
    }
}
?> 