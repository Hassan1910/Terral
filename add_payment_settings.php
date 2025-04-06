<?php
// Include database connection
require_once 'api/config/Database.php';
require_once 'api/models/Setting.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Initialize Setting model
$setting = new Setting($db);

// All payment settings
$payment_settings = [
    // Payment Methods
    [
        'setting_key' => 'payment_methods',
        'setting_value' => json_encode([
            [
                'id' => 'mpesa',
                'name' => 'M-Pesa',
                'enabled' => true
            ],
            [
                'id' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'enabled' => true
            ],
            [
                'id' => 'cash_on_delivery',
                'name' => 'Cash on Delivery',
                'enabled' => false
            ]
        ]),
        'setting_type' => 'json',
        'setting_label' => 'Payment Methods',
        'setting_description' => 'Configure available payment methods and their status',
        'setting_group' => 'payment',
        'is_public' => 1,
        'sort_order' => 10
    ],
    
    // M-Pesa Settings
    [
        'setting_key' => 'mpesa_business_shortcode',
        'setting_value' => '174379',
        'setting_type' => 'text',
        'setting_label' => 'M-Pesa Business Shortcode',
        'setting_description' => 'Your M-Pesa business shortcode',
        'setting_group' => 'payment',
        'is_public' => 0,
        'sort_order' => 20
    ],
    [
        'setting_key' => 'mpesa_passkey',
        'setting_value' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
        'setting_type' => 'text',
        'setting_label' => 'M-Pesa Passkey',
        'setting_description' => 'Your M-Pesa API passkey',
        'setting_group' => 'payment',
        'is_public' => 0,
        'sort_order' => 30
    ],
    [
        'setting_key' => 'mpesa_consumer_key',
        'setting_value' => 'YOUR_CONSUMER_KEY',
        'setting_type' => 'text',
        'setting_label' => 'M-Pesa Consumer Key',
        'setting_description' => 'Your M-Pesa API consumer key',
        'setting_group' => 'payment',
        'is_public' => 0,
        'sort_order' => 40
    ],
    [
        'setting_key' => 'mpesa_consumer_secret',
        'setting_value' => 'YOUR_CONSUMER_SECRET',
        'setting_type' => 'text',
        'setting_label' => 'M-Pesa Consumer Secret',
        'setting_description' => 'Your M-Pesa API consumer secret',
        'setting_group' => 'payment',
        'is_public' => 0,
        'sort_order' => 50
    ],
    
    // Currency Settings
    [
        'setting_key' => 'currency_code',
        'setting_value' => 'KES',
        'setting_type' => 'text',
        'setting_label' => 'Default Currency Code',
        'setting_description' => 'Default currency code (e.g., KES, USD)',
        'setting_group' => 'payment',
        'is_public' => 1,
        'sort_order' => 60
    ],
    [
        'setting_key' => 'currency_symbol',
        'setting_value' => 'KSh',
        'setting_type' => 'text',
        'setting_label' => 'Currency Symbol',
        'setting_description' => 'Currency symbol to display (e.g., KSh, $)',
        'setting_group' => 'payment',
        'is_public' => 1,
        'sort_order' => 70
    ],
    [
        'setting_key' => 'minimum_order_amount',
        'setting_value' => '500',
        'setting_type' => 'number',
        'setting_label' => 'Minimum Order Amount',
        'setting_description' => 'Minimum amount required for checkout',
        'setting_group' => 'payment',
        'is_public' => 1,
        'sort_order' => 80
    ]
];

// Begin transaction
$db->beginTransaction();

try {
    foreach ($payment_settings as $setting_data) {
        // Check if setting already exists
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_key = ?");
        $stmt->execute([$setting_data['setting_key']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            // Update existing setting
            $stmt = $db->prepare("UPDATE settings SET 
                setting_value = :value,
                setting_type = :type,
                setting_label = :label,
                setting_description = :description,
                setting_group = :group,
                is_public = :is_public,
                sort_order = :sort_order,
                updated_at = NOW()
                WHERE setting_key = :key");
        } else {
            // Insert new setting
            $stmt = $db->prepare("INSERT INTO settings (
                setting_key,
                setting_value,
                setting_type,
                setting_label,
                setting_description,
                setting_group,
                is_public,
                sort_order,
                created_at,
                updated_at
            ) VALUES (
                :key,
                :value,
                :type,
                :label,
                :description,
                :group,
                :is_public,
                :sort_order,
                NOW(),
                NOW()
            )");
        }
        
        // Bind parameters
        $stmt->bindParam(':key', $setting_data['setting_key']);
        $stmt->bindParam(':value', $setting_data['setting_value']);
        $stmt->bindParam(':type', $setting_data['setting_type']);
        $stmt->bindParam(':label', $setting_data['setting_label']);
        $stmt->bindParam(':description', $setting_data['setting_description']);
        $stmt->bindParam(':group', $setting_data['setting_group']);
        $stmt->bindParam(':is_public', $setting_data['is_public']);
        $stmt->bindParam(':sort_order', $setting_data['sort_order']);
        
        // Execute query
        if (!$stmt->execute()) {
            throw new Exception("Failed to save setting: " . $setting_data['setting_key']);
        }
    }
    
    // Commit transaction
    $db->commit();
    echo "All payment settings have been added/updated successfully.";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    echo "Error: " . $e->getMessage();
} 