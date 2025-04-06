<?php
/**
 * M-Pesa Integration Test Script
 * This script verifies the M-Pesa integration configuration and tests payment processing
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize test results
$tests = [
    'db_connection' => false,
    'payments_table' => false,
    'mpesa_settings' => false,
    'mpesa_credentials' => false,
    'mpesa_simulation' => false
];

$issues = [];
$recommendations = [];

// Styling
echo '<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }
    h1, h2 { color: #3d5afe; }
    .status { padding: 10px; margin-bottom: 10px; border-radius: 5px; }
    .success { background-color: #d4edda; color: #155724; }
    .error { background-color: #f8d7da; color: #721c24; }
    .warning { background-color: #fff3cd; color: #856404; }
    .test-item { margin-bottom: 5px; padding: 8px; border-radius: 5px; }
    .test-pass { background-color: #d4edda; }
    .test-fail { background-color: #f8d7da; }
    .test-item span { display: inline-block; width: 250px; font-weight: bold; }
    .section { margin-bottom: 30px; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    .recommendations { background-color: #e7f3fe; padding: 15px; border-left: 5px solid #3d5afe; }
</style>';

echo '<h1>M-Pesa Integration Test</h1>';

// Test 1: Check database connection
try {
    // Verify connection by getting server info
    $tests['db_connection'] = true;
    echo "<div class='test-item test-pass'><span>Database Connection:</span> Success</div>";
} catch (PDOException $e) {
    $tests['db_connection'] = false;
    echo "<div class='test-item test-fail'><span>Database Connection:</span> Failed - " . $e->getMessage() . "</div>";
    $issues[] = "Database connection failed: " . $e->getMessage();
}

// Test 2: Check payments table
try {
    $stmt = $conn->prepare("SHOW TABLES LIKE 'payments'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $tests['payments_table'] = true;
        echo "<div class='test-item test-pass'><span>Payments Table:</span> Found</div>";
    } else {
        $tests['payments_table'] = false;
        echo "<div class='test-item test-fail'><span>Payments Table:</span> Missing</div>";
        $issues[] = "Payments table is missing in the database";
        $recommendations[] = "Run the database setup script to create the payments table";
    }
} catch (PDOException $e) {
    echo "<div class='test-item test-fail'><span>Payments Table Check:</span> Error - " . $e->getMessage() . "</div>";
    $issues[] = "Error checking payments table: " . $e->getMessage();
}

// Test 3: Check M-Pesa settings in settings table
try {
    $stmt = $conn->prepare("SHOW TABLES LIKE 'settings'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Check if M-Pesa settings exist
        $stmt = $conn->prepare("SELECT * FROM settings WHERE name LIKE 'mpesa_%'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $tests['mpesa_settings'] = true;
            echo "<div class='test-item test-pass'><span>M-Pesa Settings:</span> Found</div>";
            
            // List all M-Pesa settings
            echo "<div style='margin-left: 20px;'>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = $row['value'];
                
                // Check if default values are still being used
                $isDefault = false;
                if (($row['name'] == 'mpesa_consumer_key' && $value == 'YOUR_CONSUMER_KEY') ||
                    ($row['name'] == 'mpesa_consumer_secret' && $value == 'YOUR_CONSUMER_SECRET') || 
                    ($row['name'] == 'mpesa_passkey' && strpos($value, 'YOUR_') === 0) ||
                    ($row['name'] == 'mpesa_callback_url' && strpos($value, 'YOUR_') === 0)) {
                    $isDefault = true;
                }
                
                // Mask values for security
                if ($row['name'] == 'mpesa_consumer_key' || $row['name'] == 'mpesa_consumer_secret' || $row['name'] == 'mpesa_passkey') {
                    $value = substr($value, 0, 4) . '****' . substr($value, -4);
                }
                
                $status = $isDefault ? 'warning' : 'success';
                $message = $isDefault ? ' (Default value, needs to be updated)' : '';
                
                echo "<div class='test-item test-{$status}'><span>{$row['name']}:</span> {$value}{$message}</div>";
                
                if ($isDefault) {
                    $issues[] = "Default value still being used for {$row['name']}";
                    $recommendations[] = "Update {$row['name']} with your actual M-Pesa API credentials";
                }
            }
            echo "</div>";
        } else {
            $tests['mpesa_settings'] = false;
            echo "<div class='test-item test-fail'><span>M-Pesa Settings:</span> Missing</div>";
            $issues[] = "M-Pesa settings are missing in the settings table";
            $recommendations[] = "Run the database setup script to create M-Pesa settings";
        }
    } else {
        echo "<div class='test-item test-fail'><span>Settings Table:</span> Missing</div>";
        $issues[] = "Settings table is missing in the database";
        $recommendations[] = "Run the database setup script to create the settings table";
    }
} catch (PDOException $e) {
    echo "<div class='test-item test-fail'><span>M-Pesa Settings Check:</span> Error - " . $e->getMessage() . "</div>";
    $issues[] = "Error checking M-Pesa settings: " . $e->getMessage();
}

// Test 4: Check M-Pesa credentials in models/Payment.php
try {
    $paymentFilePath = ROOT_PATH . '/api/models/Payment.php';
    if (file_exists($paymentFilePath)) {
        $paymentFile = file_get_contents($paymentFilePath);
        
        // Check for default values in the file
        $defaultValuesFound = (
            strpos($paymentFile, 'YOUR_MPESA_CONSUMER_KEY') !== false ||
            strpos($paymentFile, 'YOUR_MPESA_CONSUMER_SECRET') !== false ||
            strpos($paymentFile, 'YOUR_MPESA_PASSKEY') !== false ||
            strpos($paymentFile, 'YOUR_MPESA_SHORTCODE') !== false ||
            strpos($paymentFile, 'YOUR_CALLBACK_URL') !== false
        );
        
        if ($defaultValuesFound) {
            $tests['mpesa_credentials'] = false;
            echo "<div class='test-item test-warning'><span>M-Pesa API Credentials:</span> Default values found in Payment.php</div>";
            $issues[] = "Default M-Pesa credential values found in Payment.php";
            $recommendations[] = "Update the M-Pesa API credentials in the Payment.php file";
        } else {
            $tests['mpesa_credentials'] = true;
            echo "<div class='test-item test-pass'><span>M-Pesa API Credentials:</span> Custom values found</div>";
        }
        
        // Check if simulation mode is enabled
        if (strpos($paymentFile, 'simulation_mode = true') !== false || 
            strpos($paymentFile, '$this->simulation_mode = true') !== false) {
            $tests['mpesa_simulation'] = true;
            echo "<div class='test-item test-pass'><span>M-Pesa Simulation:</span> Enabled</div>";
        } else {
            $tests['mpesa_simulation'] = false;
            echo "<div class='test-item test-warning'><span>M-Pesa Simulation:</span> Disabled (requires real API credentials)</div>";
            $issues[] = "M-Pesa simulation mode is disabled but you might not have real credentials";
            $recommendations[] = "Enable simulation mode in PaymentController.php until you have valid M-Pesa API credentials";
        }
    } else {
        echo "<div class='test-item test-fail'><span>Payment.php File:</span> Not found</div>";
        $issues[] = "Payment.php file not found";
        $recommendations[] = "Create the Payment.php file with proper M-Pesa integration code";
    }
} catch (Exception $e) {
    echo "<div class='test-item test-fail'><span>M-Pesa Credentials Check:</span> Error - " . $e->getMessage() . "</div>";
    $issues[] = "Error checking M-Pesa credentials: " . $e->getMessage();
}

// Test 5: Check the checkout form for M-Pesa input field (phone number)
try {
    $checkoutFilePath = ROOT_PATH . '/checkout.php';
    if (file_exists($checkoutFilePath)) {
        $checkoutFile = file_get_contents($checkoutFilePath);
        
        // Check if it has M-Pesa integration in the form
        if (strpos($checkoutFile, 'name="payment_method" value="mpesa"') !== false) {
            echo "<div class='test-item test-pass'><span>Checkout Form:</span> M-Pesa payment option found</div>";
            
            // Analyze the implementation
            if (strpos($checkoutFile, 'mpesa_phone') !== false || 
                strpos($checkoutFile, 'phone_number" value="+254') !== false) {
                echo "<div class='test-item test-pass'><span>Phone Number Field:</span> Found for M-Pesa</div>";
            } else {
                echo "<div class='test-item test-warning'><span>Phone Number Field:</span> Using generic phone field</div>";
                $recommendations[] = "Consider adding a dedicated M-Pesa phone number field in the M-Pesa payment section";
            }
            
            if (strpos($checkoutFile, 'if ($_POST[\'payment_method\'] === \'mpesa\')') !== false) {
                echo "<div class='test-item test-pass'><span>M-Pesa Processing:</span> Found in checkout</div>";
            } else {
                echo "<div class='test-item test-warning'><span>M-Pesa Processing:</span> May be missing in checkout</div>";
                $recommendations[] = "Add specific M-Pesa payment processing code in checkout.php";
            }
        } else {
            echo "<div class='test-item test-fail'><span>Checkout Form:</span> M-Pesa payment option not found</div>";
            $issues[] = "M-Pesa payment option not found in checkout form";
            $recommendations[] = "Add M-Pesa as a payment option in checkout.php";
        }
    } else {
        echo "<div class='test-item test-fail'><span>Checkout.php File:</span> Not found</div>";
        $issues[] = "checkout.php file not found";
        $recommendations[] = "Create a checkout.php file with M-Pesa integration";
    }
} catch (Exception $e) {
    echo "<div class='test-item test-fail'><span>Checkout Form Check:</span> Error - " . $e->getMessage() . "</div>";
    $issues[] = "Error checking checkout form: " . $e->getMessage();
}

// Test 6: Check for actual payments with M-Pesa
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM payments WHERE payment_method = 'mpesa'");
    $stmt->execute();
    $mpesaPaymentCount = $stmt->fetchColumn();
    
    if ($mpesaPaymentCount > 0) {
        echo "<div class='test-item test-pass'><span>M-Pesa Payment Records:</span> Found ({$mpesaPaymentCount} payments)</div>";
        
        // Get the latest successful payment
        $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_method = 'mpesa' AND status = 'completed' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<div class='test-item test-pass'><span>Last Successful Payment:</span> Transaction ID: " . $payment['transaction_id'] . " on " . $payment['payment_date'] . "</div>";
        } else {
            echo "<div class='test-item test-warning'><span>Successful Payments:</span> None found</div>";
            $recommendations[] = "Test a complete M-Pesa payment flow to verify it works end-to-end";
        }
    } else {
        echo "<div class='test-item test-warning'><span>M-Pesa Payment Records:</span> None found</div>";
        $recommendations[] = "Make a test M-Pesa payment to verify the full integration";
    }
} catch (PDOException $e) {
    echo "<div class='test-item test-fail'><span>Payment Records Check:</span> Error - " . $e->getMessage() . "</div>";
    $issues[] = "Error checking payment records: " . $e->getMessage();
}

// Test 7: Check M-Pesa callback URL
try {
    $callbackUrl = '';
    $stmt = $conn->prepare("SELECT value FROM settings WHERE name = 'mpesa_callback_url'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $callbackUrl = $stmt->fetchColumn();
        
        $isDefault = (strpos($callbackUrl, 'YOUR_') === 0 || $callbackUrl == 'https://your-domain.com/api/payments/mpesa-callback');
        
        if ($isDefault) {
            echo "<div class='test-item test-warning'><span>M-Pesa Callback URL:</span> Using default value</div>";
            $issues[] = "Default M-Pesa callback URL is being used";
            $recommendations[] = "Update the M-Pesa callback URL to point to your domain";
        } else {
            // Test if URL is reachable
            if (filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
                echo "<div class='test-item test-pass'><span>M-Pesa Callback URL:</span> Valid URL format</div>";
                
                // Check if it points to the current domain
                $currentDomain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                
                if (strpos($callbackUrl, $currentDomain) === 0) {
                    echo "<div class='test-item test-pass'><span>Callback Domain:</span> Matches current domain</div>";
                } else {
                    echo "<div class='test-item test-warning'><span>Callback Domain:</span> Does not match current domain</div>";
                    $recommendations[] = "Update the M-Pesa callback URL to use your current domain";
                }
            } else {
                echo "<div class='test-item test-fail'><span>M-Pesa Callback URL:</span> Invalid URL format</div>";
                $issues[] = "M-Pesa callback URL is not a valid URL";
                $recommendations[] = "Fix the M-Pesa callback URL format";
            }
        }
    }
} catch (PDOException $e) {
    echo "<div class='test-item test-fail'><span>Callback URL Check:</span> Error - " . $e->getMessage() . "</div>";
    $issues[] = "Error checking callback URL: " . $e->getMessage();
}

// Test 8: Check if simulation mode is properly configured
try {
    $paymentControllerPath = ROOT_PATH . '/api/controllers/PaymentController.php';
    if (file_exists($paymentControllerPath)) {
        $paymentController = file_get_contents($paymentControllerPath);
        
        if (strpos($paymentController, 'private $simulation_mode = true') !== false) {
            echo "<div class='test-item test-pass'><span>Simulation Mode:</span> Enabled in PaymentController</div>";
            
            if (strpos($paymentController, 'simulate_success_rate') !== false) {
                preg_match('/private \$simulate_success_rate = (\d+)/', $paymentController, $matches);
                if (isset($matches[1])) {
                    $successRate = $matches[1];
                    echo "<div class='test-item test-pass'><span>Simulation Success Rate:</span> {$successRate}%</div>";
                }
            }
            
            if (strpos($paymentController, 'simulatePaymentCallback') !== false) {
                echo "<div class='test-item test-pass'><span>Payment Simulation:</span> Callback simulation found</div>";
            } else {
                echo "<div class='test-item test-warning'><span>Payment Simulation:</span> Callback simulation might be missing</div>";
                $recommendations[] = "Ensure there's a simulatePaymentCallback method in PaymentController.php";
            }
        } else {
            echo "<div class='test-item test-warning'><span>Simulation Mode:</span> Disabled in PaymentController</div>";
            $issues[] = "M-Pesa simulation mode is disabled in PaymentController.php";
            $recommendations[] = "Enable simulation mode by setting \$simulation_mode = true in PaymentController.php";
        }
    }
} catch (Exception $e) {
    echo "<div class='test-item test-fail'><span>Simulation Mode Check:</span> Error - " . $e->getMessage() . "</div>";
    $issues[] = "Error checking simulation mode: " . $e->getMessage();
}

// Overall assessment
echo "<h2>Overall M-Pesa Integration Assessment</h2>";

$passedTests = count(array_filter($tests));
$totalTests = count($tests);

if ($passedTests === $totalTests) {
    echo "<div class='status success'>Your M-Pesa integration is properly configured and ready to use.</div>";
} elseif ($passedTests >= $totalTests / 2) {
    echo "<div class='status warning'>Your M-Pesa integration is partially configured but needs some adjustments.</div>";
} else {
    echo "<div class='status error'>Your M-Pesa integration has significant issues that need to be addressed.</div>";
}

// Display issues
if (!empty($issues)) {
    echo "<h2>Issues Found (" . count($issues) . ")</h2>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>" . htmlspecialchars($issue) . "</li>";
    }
    echo "</ul>";
}

// Display recommendations
if (!empty($recommendations)) {
    echo "<h2>Recommendations</h2>";
    echo "<div class='recommendations'><ul>";
    foreach (array_unique($recommendations) as $recommendation) {
        echo "<li>" . htmlspecialchars($recommendation) . "</li>";
    }
    echo "</ul></div>";
}

// Provide test examples
echo "<h2>Test Payment Flow</h2>";
echo "<p>To test the M-Pesa integration, follow these steps:</p>";
echo "<ol>";
echo "<li>Go to the <a href='checkout.php'>checkout page</a> and select M-Pesa as the payment method</li>";
echo "<li>Complete the checkout process with a valid Kenyan phone number</li>";
echo "<li>If simulation mode is enabled, the payment will be automatically processed as successful most of the time</li>";
echo "<li>Check the order confirmation page and ensure the payment status is correctly displayed</li>";
echo "</ol>";

// If simulation mode is enabled, provide additional testing options
if ($tests['mpesa_simulation']) {
    echo "<h3>Simulate Different Payment Scenarios</h3>";
    echo "<p>You can manually simulate different payment outcomes:</p>";
    
    echo "<form method='post' action='api/payments/simulate-completion'>";
    echo "<input type='hidden' name='transaction_id' value='SIM_" . uniqid() . "'>";
    echo "<label><input type='radio' name='success' value='1' checked> Successful Payment</label><br>";
    echo "<label><input type='radio' name='success' value='0'> Failed Payment</label><br>";
    echo "<button type='submit' style='margin-top: 10px; padding: 5px 10px; background: #3d5afe; color: white; border: none; border-radius: 4px;'>Simulate Payment</button>";
    echo "</form>";
}

// Instructions for real M-Pesa integration
echo "<h2>Moving to Production</h2>";
echo "<p>When you're ready to use real M-Pesa payments, follow these steps:</p>";
echo "<ol>";
echo "<li>Register for the Safaricom Developer account at <a href='https://developer.safaricom.co.ke/' target='_blank'>https://developer.safaricom.co.ke/</a></li>";
echo "<li>Create an app and get your API credentials (Consumer Key, Consumer Secret, etc.)</li>";
echo "<li>Update the M-Pesa credentials in your application settings</li>";
echo "<li>Set the callback URL to point to your live domain</li>";
echo "<li>Disable simulation mode in PaymentController.php</li>";
echo "<li>Test with real phone numbers and small amounts</li>";
echo "</ol>";
?> 