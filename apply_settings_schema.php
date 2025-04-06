<?php
/**
 * Apply settings schema to the database
 * This script will ensure the settings table exists and has the correct structure
 */

// Include database connection
require_once 'api/config/Database.php';
$database = new Database();
$conn = $database->getConnection();

// Create settings table if it doesn't exist
$conn->exec("
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT,
  setting_type ENUM('text', 'textarea', 'image', 'boolean', 'number', 'select', 'color', 'json') NOT NULL DEFAULT 'text',
  setting_label VARCHAR(255) NOT NULL,
  setting_description TEXT,
  setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
  setting_options TEXT NULL COMMENT 'JSON array of options for select type',
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_setting_key (setting_key),
  INDEX idx_setting_group (setting_group),
  INDEX idx_is_public (is_public)
) ENGINE=InnoDB");

echo "Settings table created or verified.<br>";

// Create uploads directory for settings if it doesn't exist
$uploads_dir = 'uploads/settings';
if (!file_exists($uploads_dir)) {
    if (mkdir($uploads_dir, 0755, true)) {
        echo "Created directory for settings uploads: {$uploads_dir}<br>";
    } else {
        echo "Failed to create directory: {$uploads_dir}<br>";
    }
} else {
    echo "Uploads directory for settings already exists.<br>";
}

// Define function to insert or update settings
function addOrUpdateSetting($conn, $key, $value, $type, $label, $description, $group, $options, $is_public, $sort_order) {
    // Check if setting exists
    $stmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    
    if ($stmt->rowCount() > 0) {
        // Update existing setting
        $stmt = $conn->prepare("
            UPDATE settings 
            SET setting_value = ?, setting_type = ?, setting_label = ?, 
                setting_description = ?, setting_group = ?, setting_options = ?,
                is_public = ?, sort_order = ?
            WHERE setting_key = ?
        ");
        $stmt->execute([$value, $type, $label, $description, $group, $options, $is_public, $sort_order, $key]);
        echo "Updated setting: {$key}<br>";
    } else {
        // Insert new setting
        $stmt = $conn->prepare("
            INSERT INTO settings 
            (setting_key, setting_value, setting_type, setting_label, setting_description, setting_group, setting_options, is_public, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$key, $value, $type, $label, $description, $group, $options, $is_public, $sort_order]);
        echo "Added setting: {$key}<br>";
    }
}

// Define default settings
$defaultSettings = [
    // General Settings
    ['site_name', 'Terral Online Production System', 'text', 'Site Name', 'Name of your website', 'general', '', 1, 1],
    ['site_description', 'Customize and order printed/branded products online', 'textarea', 'Site Description', 'Short description about your website', 'general', '', 1, 2],
    ['admin_email', 'admin@terral.com', 'text', 'Admin Email', 'Email address for admin notifications', 'general', '', 0, 3],
    ['contact_email', 'contact@terral.com', 'text', 'Contact Email', 'Email address displayed on the contact page', 'general', '', 1, 4],
    ['contact_phone', '+254700000000', 'text', 'Contact Phone', 'Phone number displayed on the website', 'general', '', 1, 5],
    ['contact_address', '123 Business Street, Nairobi, Kenya', 'textarea', 'Contact Address', 'Physical address displayed on the website', 'general', '', 1, 6],
    
    // Appearance Settings
    ['logo', 'assets/img/logo.png', 'image', 'Site Logo', 'Main logo of your website', 'appearance', '', 1, 1],
    ['primary_color', '#3498db', 'color', 'Primary Color', 'Main color of your website theme', 'appearance', '', 1, 3],
    ['secondary_color', '#2ecc71', 'color', 'Secondary Color', 'Secondary color of your website theme', 'appearance', '', 1, 4],
    
    // Footer Settings
    ['footer_text', '© 2023 Terral Online Production System. All rights reserved.', 'textarea', 'Footer Text', 'Text displayed in the footer section', 'footer', '', 1, 1],
    ['display_payment_icons', '1', 'boolean', 'Display Payment Icons', 'Show payment method icons in the footer', 'footer', '', 1, 2],
    
    // Social Media Settings
    ['facebook_url', 'https://facebook.com/terral', 'text', 'Facebook URL', 'Link to your Facebook page', 'social', '', 1, 1],
    ['twitter_url', 'https://twitter.com/terral', 'text', 'Twitter URL', 'Link to your Twitter profile', 'social', '', 1, 2],
    ['instagram_url', 'https://instagram.com/terral', 'text', 'Instagram URL', 'Link to your Instagram profile', 'social', '', 1, 3],
    
    // Email Settings
    ['smtp_host', 'smtp.example.com', 'text', 'SMTP Host', 'SMTP server for sending emails', 'email', '', 0, 1],
    ['smtp_port', '587', 'number', 'SMTP Port', 'Port for the SMTP server', 'email', '', 0, 2],
    ['smtp_username', 'your_username', 'text', 'SMTP Username', 'Username for SMTP authentication', 'email', '', 0, 3],
    ['smtp_password', 'your_password', 'text', 'SMTP Password', 'Password for SMTP authentication', 'email', '', 0, 4],
    ['smtp_encryption', 'tls', 'select', 'SMTP Encryption', 'Encryption type for SMTP', 'email', '[{"value":"none","label":"None"},{"value":"ssl","label":"SSL"},{"value":"tls","label":"TLS"}]', 0, 5],
    ['email_from_name', 'Terral Support', 'text', 'From Name', 'Name to use in the From field', 'email', '', 0, 6],
    ['email_from_address', 'support@terral.com', 'text', 'From Email', 'Email address to use in the From field', 'email', '', 0, 7],
    ['enable_email_notifications', '1', 'boolean', 'Enable Email Notifications', 'Send email notifications for orders and account activities', 'email', '', 0, 8],
    
    // Payment Settings
    ['payment_methods', '[{"id":"mpesa","name":"M-Pesa","enabled":true},{"id":"bank_transfer","name":"Bank Transfer","enabled":true},{"id":"cash_on_delivery","name":"Cash on Delivery","enabled":false}]', 'json', 'Payment Methods', 'Available payment methods', 'payment', '', 0, 1],
    ['mpesa_business_shortcode', '174379', 'text', 'M-Pesa Business Shortcode', 'Your M-Pesa business shortcode', 'payment', '', 0, 2],
    ['mpesa_passkey', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919', 'text', 'M-Pesa Passkey', 'Your M-Pesa API passkey', 'payment', '', 0, 3],
    ['mpesa_consumer_key', 'YOUR_CONSUMER_KEY', 'text', 'M-Pesa Consumer Key', 'Your M-Pesa API consumer key', 'payment', '', 0, 4],
    ['mpesa_consumer_secret', 'YOUR_CONSUMER_SECRET', 'text', 'M-Pesa Consumer Secret', 'Your M-Pesa API consumer secret', 'payment', '', 0, 5],
    ['currency_code', 'KES', 'text', 'Default Currency Code', 'Default currency code (e.g., KES, USD)', 'payment', '', 0, 6],
    ['currency_symbol', 'KSh', 'text', 'Currency Symbol', 'Currency symbol to display (e.g., KSh, $)', 'payment', '', 0, 7],
    ['min_order_amount', '500', 'number', 'Minimum Order Amount', 'Minimum amount required for checkout', 'payment', '', 0, 8],
    ['bank_account_name', 'Terral Ltd', 'text', 'Bank Account Name', 'Name on the bank account', 'payment', '', 0, 9],
    ['bank_account_number', '1234567890', 'text', 'Bank Account Number', 'Bank account number for transfers', 'payment', '', 0, 10],
    ['bank_name', 'Example Bank', 'text', 'Bank Name', 'Name of the bank', 'payment', '', 0, 11],
    ['bank_branch', 'Main Branch', 'text', 'Bank Branch', 'Branch name', 'payment', '', 0, 12],
    
    // Store Info Settings
    ['store_name', 'Terral Store', 'text', 'Store Name', 'Name of your store', 'store_info', '', 1, 1],
    ['store_tagline', 'Custom Printing & Branding', 'text', 'Store Tagline', 'Short description or slogan', 'store_info', '', 1, 2],
    ['store_phone', '+254700000000', 'text', 'Store Phone', 'Primary contact number', 'store_info', '', 1, 3],
    ['store_email', 'info@terral.com', 'text', 'Store Email', 'Primary contact email', 'store_info', '', 1, 4],
    ['store_address', '123 Business Street', 'text', 'Street Address', 'Street address', 'store_info', '', 1, 5],
    ['store_city', 'Nairobi', 'text', 'City', 'City', 'store_info', '', 1, 6],
    ['store_state', 'Nairobi County', 'text', 'State/County', 'State or county', 'store_info', '', 1, 7],
    ['store_zip', '00100', 'text', 'Postal Code', 'Postal or ZIP code', 'store_info', '', 1, 8],
    ['store_country', 'Kenya', 'text', 'Country', 'Country', 'store_info', '', 1, 9],
    ['business_hours', 'Mon-Fri: 9am-5pm, Sat: 10am-2pm, Sun: Closed', 'text', 'Business Hours', 'Your business operating hours', 'store_info', '', 1, 10]
];

// Add or update default settings
echo "<h2>Adding/updating default settings:</h2>";
foreach ($defaultSettings as $setting) {
    addOrUpdateSetting(
        $conn, 
        $setting[0], // key
        $setting[1], // value
        $setting[2], // type
        $setting[3], // label
        $setting[4], // description
        $setting[5], // group
        $setting[6], // options
        $setting[7], // is_public
        $setting[8]  // sort_order
    );
}

echo "<hr>";
echo "<h2>Settings application completed successfully!</h2>";
echo "<p>Your site settings have been initialized. You can now:</p>";
echo "<ul>";
echo "<li><a href='admin/settings.php'>Go to Settings Admin Page</a> to customize your settings.</li>";
echo "<li><a href='index.php'>View your Home Page</a> to see the settings in action.</li>";
echo "</ul>";
?> 