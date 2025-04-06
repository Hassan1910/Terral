<?php
/**
 * M-Pesa Setup Script
 * This script helps configure the M-Pesa integration for your Terral Online Store
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Process form submission
$success_message = '';
$error_message = '';
$credentials = [
    'mpesa_consumer_key' => '',
    'mpesa_consumer_secret' => '',
    'mpesa_passkey' => '',
    'mpesa_business_shortcode' => '174379', // Default Safaricom test shortcode
    'mpesa_callback_url' => '',
    'mpesa_env' => 'sandbox' // 'sandbox' or 'production'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['setup_mpesa'])) {
        try {
            // Create database connection
            $database = new Database();
            $conn = $database->getConnection();
            
            // Check if settings table exists
            $tableExists = false;
            try {
                $stmt = $conn->prepare("SHOW TABLES LIKE 'settings'");
                $stmt->execute();
                $tableExists = ($stmt->rowCount() > 0);
            } catch (PDOException $e) {
                $error_message = "Error checking settings table: " . $e->getMessage();
            }
            
            // Create settings table if it doesn't exist
            if (!$tableExists) {
                try {
                    $sql = "CREATE TABLE IF NOT EXISTS `settings` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `key` VARCHAR(255) NOT NULL UNIQUE,
                        `value` TEXT,
                        `type` VARCHAR(50) DEFAULT 'text',
                        `label` VARCHAR(255),
                        `description` TEXT,
                        `group` VARCHAR(100),
                        `is_public` TINYINT(1) DEFAULT 0,
                        `ordering` INT DEFAULT 0,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    
                    $conn->exec($sql);
                    $success_message .= "Settings table created successfully. ";
                } catch (PDOException $e) {
                    $error_message = "Error creating settings table: " . $e->getMessage();
                    throw $e;
                }
            }
            
            // Get submitted values
            $credentials['mpesa_consumer_key'] = $_POST['consumer_key'];
            $credentials['mpesa_consumer_secret'] = $_POST['consumer_secret'];
            $credentials['mpesa_passkey'] = $_POST['passkey'];
            $credentials['mpesa_business_shortcode'] = $_POST['business_shortcode'];
            $credentials['mpesa_callback_url'] = $_POST['callback_url'];
            $credentials['mpesa_env'] = $_POST['environment'];
            
            // Get current domain if callback not specified
            if (empty($credentials['mpesa_callback_url'])) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $domain = $_SERVER['HTTP_HOST'];
                $credentials['mpesa_callback_url'] = "$protocol://$domain/api/payments/mpesa-callback";
            }
            
            // Save M-Pesa settings
            foreach ($credentials as $key => $value) {
                try {
                    // Check if setting exists
                    $stmt = $conn->prepare("SELECT id FROM settings WHERE `key` = :key");
                    $stmt->bindParam(':key', $key);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        // Update existing setting
                        $stmt = $conn->prepare("UPDATE settings SET `value` = :value WHERE `key` = :key");
                    } else {
                        // Insert new setting
                        $stmt = $conn->prepare("INSERT INTO settings (`key`, `value`, `type`, `label`, `group`) 
                                              VALUES (:key, :value, 'text', :label, 'payment')");
                        
                        // Set label based on key
                        $label = ucwords(str_replace('_', ' ', $key));
                        $stmt->bindParam(':label', $label);
                    }
                    
                    $stmt->bindParam(':key', $key);
                    $stmt->bindParam(':value', $value);
                    $stmt->execute();
                } catch (PDOException $e) {
                    $error_message = "Error saving setting '$key': " . $e->getMessage();
                    throw $e;
                }
            }
            
            // Create/Update PaymentController simulation settings
            $controllerPath = ROOT_PATH . '/api/controllers/PaymentController.php';
            if (file_exists($controllerPath)) {
                $controllerContent = file_get_contents($controllerPath);
                
                // Enable simulation mode based on environment
                $simulationMode = ($credentials['mpesa_env'] === 'sandbox') ? 'true' : 'false';
                
                // Update simulation_mode property if found
                if (preg_match('/private \$simulation_mode = (true|false);/', $controllerContent)) {
                    $controllerContent = preg_replace(
                        '/private \$simulation_mode = (true|false);/', 
                        'private $simulation_mode = ' . $simulationMode . ';', 
                        $controllerContent
                    );
                    
                    // Save updated file
                    file_put_contents($controllerPath, $controllerContent);
                    $success_message .= "PaymentController updated with simulation mode: " . $simulationMode . ". ";
                }
            }
            
            $success_message .= "M-Pesa settings saved successfully!";
            
        } catch (Exception $e) {
            $error_message = "An error occurred: " . $e->getMessage();
        }
    }
}

// Load current settings if available
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Check if settings table exists
    try {
        $stmt = $conn->prepare("SHOW TABLES LIKE 'settings'");
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            // Get M-Pesa settings
            $stmt = $conn->prepare("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'mpesa_%'");
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $credentials[$row['key']] = $row['value'];
            }
        }
    } catch (PDOException $e) {
        // Table might not exist, will use default values
    }
} catch (Exception $e) {
    $error_message = "Error loading settings: " . $e->getMessage();
}

// Check PaymentController for simulation mode
$simulationMode = true;
try {
    $controllerPath = ROOT_PATH . '/api/controllers/PaymentController.php';
    if (file_exists($controllerPath)) {
        $controllerContent = file_get_contents($controllerPath);
        
        // Check simulation_mode value
        if (preg_match('/private \$simulation_mode = (true|false);/', $controllerContent, $matches)) {
            $simulationMode = ($matches[1] === 'true');
        }
    }
} catch (Exception $e) {
    // Ignore error, use default value
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-Pesa Setup - Terral Online Store</title>
    <link rel="stylesheet" href="/Terral2/assets/css/modern-theme.css">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .setup-box {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: var(--space-4);
            margin-bottom: var(--space-4);
        }
        
        .setup-box h3 {
            margin-bottom: var(--space-3);
            color: var(--primary);
            border-bottom: 1px solid var(--light-3);
            padding-bottom: var(--space-2);
        }
        
        .setup-steps {
            margin-bottom: var(--space-4);
        }
        
        .setup-step {
            margin-bottom: var(--space-3);
            padding: var(--space-3);
            background-color: var(--light);
            border-radius: var(--border-radius);
        }
        
        .setup-step h4 {
            color: var(--primary);
            margin-bottom: var(--space-2);
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: var(--space-2);
        }
        
        .badge-sandbox {
            background-color: var(--warning);
            color: #856404;
        }
        
        .badge-production {
            background-color: var(--success);
            color: white;
        }
        
        .msg {
            padding: var(--space-3);
            margin-bottom: var(--space-3);
            border-radius: var(--border-radius);
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-3);
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .note {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: var(--space-1);
        }
        
        .security-note {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: var(--space-3);
            margin-bottom: var(--space-3);
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <i class="fas fa-paint-brush"></i> Terral
            </a>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">Home</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">Admin</a>
                </li>
            </ul>
        </div>
    </header>
    
    <main class="main-content">
        <div class="setup-container">
            <h1>M-Pesa Integration Setup</h1>
            
            <?php if (!empty($success_message)): ?>
            <div class="msg success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
            <div class="msg error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <div class="setup-box">
                <h3>How M-Pesa Integration Works</h3>
                <div class="setup-steps">
                    <div class="setup-step">
                        <h4>1. Customer Selects M-Pesa at Checkout</h4>
                        <p>When a customer selects M-Pesa as their payment method during checkout, they provide their M-Pesa registered phone number.</p>
                    </div>
                    
                    <div class="setup-step">
                        <h4>2. STK Push Notification</h4>
                        <p>After placing an order, the Safaricom M-Pesa API sends an STK Push notification to the customer's phone requesting payment authorization.</p>
                    </div>
                    
                    <div class="setup-step">
                        <h4>3. Customer Authorizes Payment</h4>
                        <p>The customer enters their M-Pesa PIN on their phone to authorize the payment.</p>
                    </div>
                    
                    <div class="setup-step">
                        <h4>4. Payment Confirmation</h4>
                        <p>Once the payment is processed, Safaricom sends a notification to your callback URL, and the order status is updated.</p>
                    </div>
                </div>
                
                <div class="security-note">
                    <strong>Security Note:</strong> M-Pesa API credentials are sensitive information. Store them securely and never share them publicly.
                </div>
            </div>
            
            <div class="setup-box">
                <h3>M-Pesa API Configuration</h3>
                <p>Enter your Safaricom M-Pesa API credentials below. You can get these by registering at <a href="https://developer.safaricom.co.ke/" target="_blank">Safaricom Developer Portal</a>.</p>
                
                <form method="post" action="">
                    <div class="form-group">
                        <label for="environment">Environment</label>
                        <select id="environment" name="environment" class="form-control">
                            <option value="sandbox" <?php echo $credentials['mpesa_env'] === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                            <option value="production" <?php echo $credentials['mpesa_env'] === 'production' ? 'selected' : ''; ?>>Production (Live)</option>
                        </select>
                        <div class="note">Select Sandbox for testing. Use Production only when you're ready to process real payments.</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="consumer_key">Consumer Key</label>
                            <input type="text" id="consumer_key" name="consumer_key" class="form-control" value="<?php echo htmlspecialchars($credentials['mpesa_consumer_key']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="consumer_secret">Consumer Secret</label>
                            <input type="text" id="consumer_secret" name="consumer_secret" class="form-control" value="<?php echo htmlspecialchars($credentials['mpesa_consumer_secret']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="business_shortcode">Business Shortcode</label>
                            <input type="text" id="business_shortcode" name="business_shortcode" class="form-control" value="<?php echo htmlspecialchars($credentials['mpesa_business_shortcode']); ?>" required>
                            <div class="note">For sandbox testing, use 174379</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="passkey">Passkey</label>
                            <input type="text" id="passkey" name="passkey" class="form-control" value="<?php echo htmlspecialchars($credentials['mpesa_passkey']); ?>" required>
                            <div class="note">The passkey provided in your Safaricom Developer account</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="callback_url">Callback URL</label>
                        <input type="text" id="callback_url" name="callback_url" class="form-control" value="<?php echo htmlspecialchars($credentials['mpesa_callback_url']); ?>" placeholder="https://your-domain.com/api/payments/mpesa-callback">
                        <div class="note">The URL where M-Pesa will send payment notifications. Leave blank to use your current domain.</div>
                    </div>
                    
                    <div style="margin-top: var(--space-4);">
                        <button type="submit" name="setup_mpesa" class="btn btn-primary">Save M-Pesa Configuration</button>
                    </div>
                </form>
            </div>
            
            <div class="setup-box">
                <h3>Current Status</h3>
                
                <div style="margin-bottom: var(--space-3);">
                    <span class="badge <?php echo $credentials['mpesa_env'] === 'sandbox' ? 'badge-sandbox' : 'badge-production'; ?>">
                        <?php echo ucfirst($credentials['mpesa_env']); ?> Mode
                    </span>
                    
                    <span class="badge <?php echo $simulationMode ? 'badge-sandbox' : 'badge-production'; ?>">
                        Simulation: <?php echo $simulationMode ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </div>
                
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td><strong>Consumer Key:</strong></td>
                        <td><?php echo !empty($credentials['mpesa_consumer_key']) ? '✅ Configured' : '❌ Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Consumer Secret:</strong></td>
                        <td><?php echo !empty($credentials['mpesa_consumer_secret']) ? '✅ Configured' : '❌ Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Business Shortcode:</strong></td>
                        <td><?php echo !empty($credentials['mpesa_business_shortcode']) ? '✅ Configured (' . $credentials['mpesa_business_shortcode'] . ')' : '❌ Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Passkey:</strong></td>
                        <td><?php echo !empty($credentials['mpesa_passkey']) ? '✅ Configured' : '❌ Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Callback URL:</strong></td>
                        <td><?php echo !empty($credentials['mpesa_callback_url']) ? '✅ ' . $credentials['mpesa_callback_url'] : '❌ Not configured'; ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="setup-box">
                <h3>Next Steps</h3>
                
                <ul>
                    <li><a href="mpesa-integration-test.php">Run the M-Pesa Integration Test</a> to verify everything is working correctly.</li>
                    <li><a href="checkout.php">Try the checkout process</a> with M-Pesa payment to test the full flow.</li>
                    <li>For troubleshooting, check the logs in the <code>/logs</code> directory of your application.</li>
                </ul>
                
                <p><strong>Note:</strong> In sandbox mode, payments will be simulated and won't require actual M-Pesa transactions.</p>
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Terral Online Store. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html> 