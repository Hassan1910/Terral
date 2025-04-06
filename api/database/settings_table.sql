-- Create settings table for Terral Online Production System
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
) ENGINE=InnoDB;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, setting_label, setting_description, setting_group, is_public, sort_order) VALUES 
-- General Settings
('site_name', 'Terral Online Production System', 'text', 'Site Name', 'Name of your website', 'general', 1, 1),
('site_description', 'Customize and order printed/branded products online', 'textarea', 'Site Description', 'Short description about your website', 'general', 1, 2),
('admin_email', 'admin@terral.com', 'text', 'Admin Email', 'Email address for admin notifications', 'general', 0, 3),
('contact_email', 'contact@terral.com', 'text', 'Contact Email', 'Email address displayed on the contact page', 'general', 1, 4),
('contact_phone', '+254700000000', 'text', 'Contact Phone', 'Phone number displayed on the website', 'general', 1, 5),
('contact_address', '123 Business Street, Nairobi, Kenya', 'textarea', 'Contact Address', 'Physical address displayed on the website', 'general', 1, 6),
('default_currency', 'KES', 'select', 'Default Currency', 'Default currency for product prices', 'general', 1, 7),
('currency_options', '[{"code":"KES","name":"Kenyan Shilling","symbol":"KSh"},{"code":"USD","name":"US Dollar","symbol":"$"},{"code":"EUR","name":"Euro","symbol":"€"},{"code":"GBP","name":"British Pound","symbol":"£"}]', 'json', 'Available Currencies', 'List of available currencies', 'general', 0, 8),
('date_format', 'd/m/Y', 'select', 'Date Format', 'Format for displaying dates', 'general', 1, 9),
('time_format', 'H:i', 'select', 'Time Format', 'Format for displaying time', 'general', 1, 10),

-- Appearance Settings
('logo', 'assets/img/logo.png', 'image', 'Site Logo', 'Main logo of your website', 'appearance', 1, 1),
('favicon', 'assets/img/favicon.ico', 'image', 'Favicon', 'Small icon displayed in browser tabs', 'appearance', 1, 2),
('primary_color', '#3498db', 'color', 'Primary Color', 'Main color of your website theme', 'appearance', 1, 3),
('secondary_color', '#2ecc71', 'color', 'Secondary Color', 'Secondary color of your website theme', 'appearance', 1, 4),
('accent_color', '#e74c3c', 'color', 'Accent Color', 'Accent color for buttons and highlights', 'appearance', 1, 5),
('enable_dark_mode', '0', 'boolean', 'Enable Dark Mode', 'Option to allow users to switch to dark mode', 'appearance', 1, 6),
('default_theme', 'light', 'select', 'Default Theme', 'Default theme for the website', 'appearance', 1, 7),
('hero_image', 'assets/img/hero.jpg', 'image', 'Hero Image', 'Main banner image displayed on the homepage', 'appearance', 1, 8),
('custom_css', '', 'textarea', 'Custom CSS', 'Add custom CSS styles to your website', 'appearance', 0, 9),

-- Footer Settings
('footer_text', '© 2023 Terral Online Production System. All rights reserved.', 'textarea', 'Footer Text', 'Text displayed in the footer section', 'footer', 1, 1),
('display_payment_icons', '1', 'boolean', 'Display Payment Icons', 'Show payment method icons in the footer', 'footer', 1, 2),
('footer_columns', '3', 'select', 'Footer Columns', 'Number of columns in the footer', 'footer', 0, 3),
('footer_about_text', 'Terral is a leading online production system for customized printed products.', 'textarea', 'About Text', 'Short text about your company in the footer', 'footer', 1, 4),

-- Social Media Settings
('facebook_url', 'https://facebook.com/terral', 'text', 'Facebook URL', 'Link to your Facebook page', 'social', 1, 1),
('twitter_url', 'https://twitter.com/terral', 'text', 'Twitter URL', 'Link to your Twitter profile', 'social', 1, 2),
('instagram_url', 'https://instagram.com/terral', 'text', 'Instagram URL', 'Link to your Instagram profile', 'social', 1, 3),
('linkedin_url', 'https://linkedin.com/company/terral', 'text', 'LinkedIn URL', 'Link to your LinkedIn page', 'social', 1, 4),
('youtube_url', '', 'text', 'YouTube URL', 'Link to your YouTube channel', 'social', 1, 5),
('display_social_icons', '1', 'boolean', 'Display Social Icons', 'Show social media icons in the website', 'social', 1, 6),

-- SEO Settings
('meta_title', 'Terral Online Production System', 'text', 'Meta Title', 'Default meta title for SEO', 'seo', 0, 1),
('meta_description', 'Order customized printed products online. We offer apparel, accessories, books, and home décor items.', 'textarea', 'Meta Description', 'Default meta description for SEO', 'seo', 0, 2),
('meta_keywords', 'custom printing, branded products, online ordering, customization', 'textarea', 'Meta Keywords', 'Default meta keywords for SEO', 'seo', 0, 3),
('google_analytics_id', '', 'text', 'Google Analytics ID', 'Your Google Analytics tracking ID', 'seo', 0, 4),
('enable_social_meta', '1', 'boolean', 'Enable Social Meta Tags', 'Add OpenGraph and Twitter card meta tags for social sharing', 'seo', 0, 5),

-- Order Settings
('min_order_amount', '500', 'number', 'Minimum Order Amount', 'Minimum amount for checkout', 'orders', 0, 1),
('order_prefix', 'TRL-', 'text', 'Order Prefix', 'Prefix for order numbers', 'orders', 0, 2),
('enable_guest_checkout', '1', 'boolean', 'Enable Guest Checkout', 'Allow users to checkout without registering', 'orders', 0, 3),
('order_confirmation_email', '1', 'boolean', 'Order Confirmation Email', 'Send email to customers after order placement', 'orders', 0, 4),
('admin_order_notification', '1', 'boolean', 'Admin Order Notification', 'Send email to admin after new order', 'orders', 0, 5),
('default_order_status', 'pending', 'select', 'Default Order Status', 'Default status for new orders', 'orders', 0, 6),

-- Payment Settings
('payment_methods', '[{"id":"mpesa","name":"M-Pesa","enabled":true},{"id":"bank_transfer","name":"Bank Transfer","enabled":true},{"id":"cash_on_delivery","name":"Cash on Delivery","enabled":false}]', 'json', 'Payment Methods', 'Available payment methods', 'payment', 0, 1),
('mpesa_business_shortcode', '174379', 'text', 'M-Pesa Business Shortcode', 'Your M-Pesa business shortcode', 'payment', 0, 2),
('mpesa_passkey', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919', 'text', 'M-Pesa Passkey', 'Your M-Pesa API passkey', 'payment', 0, 3),
('mpesa_consumer_key', 'YOUR_CONSUMER_KEY', 'text', 'M-Pesa Consumer Key', 'Your M-Pesa API consumer key', 'payment', 0, 4),
('mpesa_consumer_secret', 'YOUR_CONSUMER_SECRET', 'text', 'M-Pesa Consumer Secret', 'Your M-Pesa API consumer secret', 'payment', 0, 5),
('bank_transfer_instructions', 'Please transfer the amount to:\nBank Name: Example Bank\nAccount Name: Terral Ltd\nAccount Number: 1234567890\nBranch: Main Branch', 'textarea', 'Bank Transfer Instructions', 'Instructions for bank transfer payments', 'payment', 0, 6),

-- Notification Settings
('email_sender_name', 'Terral Support', 'text', 'Email Sender Name', 'Name displayed as email sender', 'notifications', 0, 1),
('email_sender_address', 'support@terral.com', 'text', 'Email Sender Address', 'Email address used for sending emails', 'notifications', 0, 2),
('enable_sms_notifications', '1', 'boolean', 'Enable SMS Notifications', 'Send SMS notifications for orders', 'notifications', 0, 3),
('sms_api_key', 'YOUR_SMS_API_KEY', 'text', 'SMS API Key', 'API key for SMS service', 'notifications', 0, 4); 