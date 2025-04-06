<?php
// Include models
include_once ROOT_PATH . '/models/Order.php';

class PaymentController {
    private $conn;
    private $order;
    
    // Payment Simulation Settings
    private $simulation_mode = true; // Set to true to enable payment simulation
    private $simulate_success_rate = 90; // Percentage of successful payments in simulation (0-100)
    private $available_payment_methods = ['mpesa', 'card', 'bank_transfer', 'cash_on_delivery'];
    
    // Constructor
    public function __construct() {
        // Get database connection
        $database = new Database();
        $this->conn = $database->getConnection();
        
        // Initialize order model
        $this->order = new Order($this->conn);
        
        // Create logs directory if it doesn't exist
        if (!file_exists(ROOT_PATH . '/logs')) {
            mkdir(ROOT_PATH . '/logs', 0755, true);
        }
    }
    
    // Get available payment methods
    public function getPaymentMethods() {
        // Set response code - 200 OK
        http_response_code(200);
        
        // Response data
        echo json_encode([
            "payment_methods" => $this->available_payment_methods,
            "simulation_mode" => $this->simulation_mode,
            "message" => "Note: All payment methods are currently simulated for testing purposes."
        ]);
    }
    
    // Initiate M-Pesa payment
    public function initiateMpesaPayment() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if required data is provided
        if(
            !empty($data->order_id) &&
            !empty($data->phone_number) &&
            !empty($data->amount)
        ) {
            try {
                // Format phone number (remove + and ensure starts with 254)
                $phone = preg_replace('/[^0-9]/', '', $data->phone_number);
                if(substr($phone, 0, 1) == '0') {
                    $phone = '254' . substr($phone, 1);
                } else if(substr($phone, 0, 3) != '254') {
                    $phone = '254' . $phone;
                }
                
                // Check if order exists
                $this->order->id = $data->order_id;
                if(!$this->order->readOne()) {
                    // Set response code - 404 not found
                    http_response_code(404);
                    
                    // Tell the user order not found
                    echo json_encode(array("message" => "Order not found."));
                    return;
                }
                
                if ($this->simulation_mode) {
                    // Simulate M-Pesa payment
                    $checkout_request_id = 'SIM_' . uniqid();
                    $timestamp = date('YmdHis');
                    
                    // Log the simulated payment request
                    $this->logInfo("Simulated M-Pesa payment initiated: Order #{$data->order_id}, Amount: {$data->amount}, Phone: {$phone}");
                    
                    // Update order payment status and ID
                    $this->order->payment_status = "processing";
                    $this->order->payment_method = "mpesa";
                    $this->order->payment_id = $checkout_request_id;
                    $this->order->updatePaymentStatus();
                    
                    // Set response code - 200 OK
                    http_response_code(200);
                    
                    // Response data
                    echo json_encode([
                        "message" => "Payment initiated successfully (SIMULATION MODE).",
                        "checkout_request_id" => $checkout_request_id,
                        "simulation" => true,
                        "note" => "This is a simulated payment. In a real environment, the customer would receive an STK push on their phone.",
                        "next_step" => "To simulate payment completion, call the /payments/simulate-completion endpoint with checkout_request_id."
                    ]);
                    
                    // Optional: Auto-simulate callback after a few seconds for testing
                    // This would normally happen when the customer completes payment on their phone
                    // Uncomment if you want automatic simulation
                    /*
                    if (function_exists('fastcgi_finish_request')) {
                        fastcgi_finish_request(); // Send response to client immediately
                    }
                    sleep(5); // Wait 5 seconds
                    $this->simulatePaymentCallback($checkout_request_id, 'mpesa');
                    */
                } else {
                    // Real M-Pesa implementation would go here
                    // This code is left for reference when you get real API credentials
                    
                    // Since the user doesn't have actual credentials, return error
                    http_response_code(503);
                    echo json_encode(array(
                        "message" => "Real M-Pesa API is not configured. Please enable simulation mode.",
                    ));
                }
            } catch(Exception $e) {
                // Set response code - 503 service unavailable
                http_response_code(503);
                
                // Tell the user
                echo json_encode(array("message" => "Error: " . $e->getMessage()));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user data is incomplete
            echo json_encode(array("message" => "Unable to initiate payment. Data is incomplete."));
        }
    }
    
    // Initiate card payment
    public function initiateCardPayment() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if required data is provided
        if(
            !empty($data->order_id) &&
            !empty($data->amount) &&
            !empty($data->card_details)
        ) {
            try {
                // Check if order exists
                $this->order->id = $data->order_id;
                if(!$this->order->readOne()) {
                    http_response_code(404);
                    echo json_encode(array("message" => "Order not found."));
                    return;
                }
                
                if ($this->simulation_mode) {
                    // Simulate card payment
                    $transaction_id = 'CARD_' . uniqid();
                    
                    // Log the simulated payment request
                    $this->logInfo("Simulated card payment initiated: Order #{$data->order_id}, Amount: {$data->amount}");
                    
                    // Basic validation of card details
                    if (isset($data->card_details->number)) {
                        // Mask card number for privacy
                        $masked_number = '**** **** **** ' . substr($data->card_details->number, -4);
                        
                        // Update order payment status and ID
                        $this->order->payment_status = "processing";
                        $this->order->payment_method = "card";
                        $this->order->payment_id = $transaction_id;
                        $this->order->updatePaymentStatus();
                        
                        // Set response code - 200 OK
                        http_response_code(200);
                        
                        // Response data
                        echo json_encode([
                            "message" => "Card payment initiated successfully (SIMULATION MODE).",
                            "transaction_id" => $transaction_id,
                            "card" => $masked_number,
                            "simulation" => true,
                            "next_step" => "To simulate payment completion, call the /payments/simulate-completion endpoint with transaction_id."
                        ]);
                        
                        // Auto-process after a short delay
                        if (function_exists('fastcgi_finish_request')) {
                            fastcgi_finish_request(); // Send response to client immediately
                        }
                        sleep(2); // Shorter wait time for card payments
                        $this->simulatePaymentCallback($transaction_id, 'card');
                    } else {
                        http_response_code(400);
                        echo json_encode(array("message" => "Invalid card details."));
                    }
                } else {
                    // Real card payment implementation would go here
                    http_response_code(503);
                    echo json_encode(array(
                        "message" => "Real card payment API is not configured. Please enable simulation mode.",
                    ));
                }
            } catch(Exception $e) {
                http_response_code(503);
                echo json_encode(array("message" => "Error: " . $e->getMessage()));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Unable to initiate payment. Data is incomplete."));
        }
    }
    
    // Initiate bank transfer payment
    public function initiateBankTransfer() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if required data is provided
        if(
            !empty($data->order_id) &&
            !empty($data->amount) &&
            !empty($data->bank_details)
        ) {
            try {
                // Check if order exists
                $this->order->id = $data->order_id;
                if(!$this->order->readOne()) {
                    http_response_code(404);
                    echo json_encode(array("message" => "Order not found."));
                    return;
                }
                
                if ($this->simulation_mode) {
                    // Simulate bank transfer
                    $transaction_id = 'BANK_' . uniqid();
                    
                    // Log the simulated payment request
                    $this->logInfo("Simulated bank transfer initiated: Order #{$data->order_id}, Amount: {$data->amount}");
                    
                    // Update order payment status and ID
                    $this->order->payment_status = "processing";
                    $this->order->payment_method = "bank_transfer";
                    $this->order->payment_id = $transaction_id;
                    $this->order->updatePaymentStatus();
                    
                    // Generate virtual account details for the transfer
                    $account_number = '1000' . rand(100000, 999999);
                    $reference = 'TER' . $data->order_id;
                    
                    // Set response code - 200 OK
                    http_response_code(200);
                    
                    // Response data
                    echo json_encode([
                        "message" => "Bank transfer instructions created successfully (SIMULATION MODE).",
                        "transaction_id" => $transaction_id,
                        "bank_details" => [
                            "bank_name" => "Terral Simulated Bank",
                            "account_number" => $account_number,
                            "reference" => $reference
                        ],
                        "amount" => $data->amount,
                        "simulation" => true,
                        "next_step" => "To simulate payment completion, call the /payments/simulate-completion endpoint with transaction_id."
                    ]);
                } else {
                    // Real bank transfer implementation would go here
                    http_response_code(503);
                    echo json_encode(array(
                        "message" => "Real bank transfer API is not configured. Please enable simulation mode.",
                    ));
                }
            } catch(Exception $e) {
                http_response_code(503);
                echo json_encode(array("message" => "Error: " . $e->getMessage()));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Unable to initiate bank transfer. Data is incomplete."));
        }
    }
    
    // Initiate cash on delivery
    public function initiateCashOnDelivery() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if required data is provided
        if(!empty($data->order_id)) {
            try {
                // Check if order exists
                $this->order->id = $data->order_id;
                if(!$this->order->readOne()) {
                    http_response_code(404);
                    echo json_encode(array("message" => "Order not found."));
                    return;
                }
                
                // Cash on delivery is always simulated/processed as it's paid upon delivery
                $transaction_id = 'COD_' . uniqid();
                
                // Log the COD request
                $this->logInfo("Cash on delivery set up: Order #{$data->order_id}, Amount: {$this->order->total_price}");
                
                // Update order payment status and ID
                $this->order->payment_status = "pending";  // Will be collected on delivery
                $this->order->payment_method = "cash_on_delivery";
                $this->order->payment_id = $transaction_id;
                $this->order->updatePaymentStatus();
                
                // Move order to processing state
                $this->order->status = "processing";
                $this->order->updateStatus();
                
                // Set response code - 200 OK
                http_response_code(200);
                
                // Response data
                echo json_encode([
                    "message" => "Cash on delivery set up successfully.",
                    "transaction_id" => $transaction_id,
                    "payment_method" => "cash_on_delivery",
                    "amount" => $this->order->total_price,
                    "note" => "Payment will be collected upon delivery."
                ]);
                
            } catch(Exception $e) {
                http_response_code(503);
                echo json_encode(array("message" => "Error: " . $e->getMessage()));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Unable to set up cash on delivery. Order ID is required."));
        }
    }
    
    // Simulate payment completion (for testing)
    public function simulatePaymentCompletion() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        if(!empty($data->transaction_id)) {
            $transaction_id = $data->transaction_id;
            $success = isset($data->success) ? $data->success : $this->shouldPaymentSucceed();
            
            // Get payment method from transaction ID prefix
            $payment_method = 'other';
            if(strpos($transaction_id, 'SIM_') === 0) {
                $payment_method = 'mpesa';
            } else if(strpos($transaction_id, 'CARD_') === 0) {
                $payment_method = 'card';
            } else if(strpos($transaction_id, 'BANK_') === 0) {
                $payment_method = 'bank_transfer';
            } else if(strpos($transaction_id, 'COD_') === 0) {
                $payment_method = 'cash_on_delivery';
            }
            
            // Simulate callback
            $result = $this->simulatePaymentCallback($transaction_id, $payment_method, $success);
            
            if($result) {
                http_response_code(200);
                echo json_encode([
                    "message" => "Payment simulation completed successfully.",
                    "transaction_id" => $transaction_id,
                    "status" => $success ? "completed" : "failed",
                    "payment_method" => $payment_method
                ]);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "No order found with this transaction ID."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Transaction ID is required."]);
        }
    }
    
    // Handle M-Pesa callback
    public function mpesaCallback() {
        try {
            // In simulation mode, this endpoint won't be called from M-Pesa
            // but we'll keep it for future real implementation
            if ($this->simulation_mode) {
                // Log the simulation attempt
                $this->logInfo("Real M-Pesa callback attempted while in simulation mode.");
                echo json_encode(["ResultCode" => 0, "ResultDesc" => "Simulation mode active - real callbacks ignored"]);
                return;
            }
            
            // Get callback data
            $callbackData = json_decode(file_get_contents("php://input"));
            
            // Log callback data for debugging
            $this->logCallback($callbackData);
            
            // Check if callback is valid
            if(
                isset($callbackData->Body->stkCallback->ResultCode) &&
                isset($callbackData->Body->stkCallback->CheckoutRequestID)
            ) {
                $resultCode = $callbackData->Body->stkCallback->ResultCode;
                $checkoutRequestId = $callbackData->Body->stkCallback->CheckoutRequestID;
                
                // Find order by payment_id (CheckoutRequestID)
                $query = "SELECT id FROM orders WHERE payment_id = :payment_id LIMIT 0,1";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':payment_id', $checkoutRequestId);
                $stmt->execute();
                
                if($stmt->rowCount() > 0) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $order_id = $row['id'];
                    
                    // Get order
                    $this->order->id = $order_id;
                    $this->order->readOne();
                    
                    // Update payment status based on result code
                    if($resultCode == 0) {
                        // Payment successful
                        // Extract payment details
                        $mpesaReceiptNumber = '';
                        $transactionDate = '';
                        $phoneNumber = '';
                        
                        if(isset($callbackData->Body->stkCallback->CallbackMetadata->Item)) {
                            foreach($callbackData->Body->stkCallback->CallbackMetadata->Item as $item) {
                                if($item->Name == "MpesaReceiptNumber") {
                                    $mpesaReceiptNumber = $item->Value;
                                } else if($item->Name == "TransactionDate") {
                                    $transactionDate = $item->Value;
                                } else if($item->Name == "PhoneNumber") {
                                    $phoneNumber = $item->Value;
                                }
                            }
                        }
                        
                        // Update order payment status
                        $this->order->payment_status = "completed";
                        $this->order->payment_id = $mpesaReceiptNumber;
                        $this->order->updatePaymentStatus();
                        
                        // Update order status to processing
                        if($this->order->status == "pending") {
                            $this->order->status = "processing";
                            $this->order->updateStatus();
                        }
                        
                        // Generate invoice
                        $this->generateInvoice($order_id);
                        
                        // Send confirmation email and SMS
                        $this->sendPaymentConfirmation($order_id, $mpesaReceiptNumber);
                    } else {
                        // Payment failed
                        $this->order->payment_status = "failed";
                        $this->order->updatePaymentStatus();
                    }
                    
                    // Return success response
                    echo json_encode(array("ResultCode" => 0, "ResultDesc" => "Callback received successfully"));
                } else {
                    // Order not found
                    $this->logError("Order not found for CheckoutRequestID: " . $checkoutRequestId);
                    echo json_encode(array("ResultCode" => 1, "ResultDesc" => "Order not found"));
                }
            } else {
                // Invalid callback data
                $this->logError("Invalid callback data");
                echo json_encode(array("ResultCode" => 1, "ResultDesc" => "Invalid callback data"));
            }
        } catch(Exception $e) {
            // Log error
            $this->logError("Callback error: " . $e->getMessage());
            
            // Return error response
            echo json_encode(array("ResultCode" => 1, "ResultDesc" => "Error: " . $e->getMessage()));
        }
    }
    
    // Simulate payment callback (internal method for simulation)
    private function simulatePaymentCallback($transaction_id, $payment_method = 'mpesa', $success = null) {
        // If success is not specified, determine randomly based on simulation success rate
        if ($success === null) {
            $success = $this->shouldPaymentSucceed();
        }
        
        try {
            // Find order by payment_id
            $query = "SELECT id FROM orders WHERE payment_id = :payment_id LIMIT 0,1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':payment_id', $transaction_id);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $order_id = $row['id'];
                
                // Get order
                $this->order->id = $order_id;
                $this->order->readOne();
                
                // Generate a receipt number
                $receipt_number = strtoupper(substr($payment_method, 0, 3)) . date('YmdHis') . rand(1000, 9999);
                
                if($success) {
                    // Payment successful
                    $this->logInfo("Simulated {$payment_method} payment successful: Order #{$order_id}, Transaction: {$transaction_id}");
                    
                    // Update order payment status
                    $this->order->payment_status = "completed";
                    $this->order->payment_id = $receipt_number; // Update to receipt number
                    $this->order->updatePaymentStatus();
                    
                    // Update order status to processing if it's pending
                    if($this->order->status == "pending") {
                        $this->order->status = "processing";
                        $this->order->updateStatus();
                    }
                    
                    // Generate invoice
                    $this->generateInvoice($order_id);
                    
                    // Send confirmation notification
                    $this->sendPaymentConfirmation($order_id, $receipt_number);
                } else {
                    // Payment failed
                    $this->logInfo("Simulated {$payment_method} payment failed: Order #{$order_id}, Transaction: {$transaction_id}");
                    
                    $this->order->payment_status = "failed";
                    $this->order->updatePaymentStatus();
                }
                
                return true;
            } else {
                $this->logError("Order not found for Transaction ID: " . $transaction_id);
                return false;
            }
        } catch(Exception $e) {
            $this->logError("Simulation error: " . $e->getMessage());
            return false;
        }
    }
    
    // Determine if a simulated payment should succeed based on the success rate
    private function shouldPaymentSucceed() {
        return (rand(1, 100) <= $this->simulate_success_rate);
    }
    
    // Generate invoice
    public function generateInvoice($order_id) {
        // Set order id
        $this->order->id = $order_id;
        
        // Check if order exists
        if(!$this->order->readOne()) {
            // Set response code - 404 not found
            http_response_code(404);
            
            // Tell the user order not found
            echo json_encode(array("message" => "Order not found."));
            return;
        }
        
        try {
            // In simulation mode, just create a simple text-based invoice
            if ($this->simulation_mode) {
                // Create a simple text invoice
                $invoice = "==================================\n";
                $invoice .= "           INVOICE\n";
                $invoice .= "==================================\n";
                $invoice .= "Invoice #: INV-" . sprintf('%06d', $order_id) . "\n";
                $invoice .= "Date: " . date('Y-m-d') . "\n";
                $invoice .= "Order #: " . $order_id . "\n";
                $invoice .= "Payment Method: " . $this->order->payment_method . "\n";
                $invoice .= "Payment ID: " . $this->order->payment_id . "\n";
                $invoice .= "----------------------------------\n";
                $invoice .= "ITEMS:\n";
                
                $subtotal = 0;
                foreach($this->order->items as $item) {
                    $item_total = $item['price'] * $item['quantity'];
                    $subtotal += $item_total;
                    $invoice .= sprintf("%-30s x%d  %8.2f\n", 
                        substr($item['product_name'], 0, 30), 
                        $item['quantity'], 
                        $item_total);
                }
                
                $tax_rate = 0.15;
                $tax = $subtotal * $tax_rate;
                $shipping = 10.00;
                $total = $subtotal + $tax + $shipping;
                
                $invoice .= "----------------------------------\n";
                $invoice .= sprintf("%-30s %8.2f\n", "Subtotal:", $subtotal);
                $invoice .= sprintf("%-30s %8.2f\n", "Tax (15%):", $tax);
                $invoice .= sprintf("%-30s %8.2f\n", "Shipping:", $shipping);
                $invoice .= sprintf("%-30s %8.2f\n", "TOTAL:", $total);
                $invoice .= "==================================\n";
                $invoice .= "Thank you for your business!\n";
                $invoice .= "SIMULATION MODE - NOT A REAL INVOICE\n";
                
                // Save the invoice to a file
                $invoice_dir = ROOT_PATH . '/uploads/invoices';
                if (!file_exists($invoice_dir)) {
                    mkdir($invoice_dir, 0755, true);
                }
                
                $pdf_file = 'invoice_' . $order_id . '.txt';
                $pdf_path = $invoice_dir . '/' . $pdf_file;
                file_put_contents($pdf_path, $invoice);
                
                // If this is a direct API call, not a callback
                if($_SERVER['REQUEST_METHOD'] === 'GET') {
                    // Set headers for text download
                    header('Content-Type: text/plain');
                    header('Content-Disposition: attachment; filename="' . $pdf_file . '"');
                    header('Cache-Control: max-age=0');
                    
                    // Output file
                    readfile($pdf_path);
                    exit;
                }
            } else {
                // Require TCPDF library for actual PDF generation
                if (file_exists(ROOT_PATH . '/vendor/tcpdf/tcpdf.php')) {
                    require_once ROOT_PATH . '/vendor/tcpdf/tcpdf.php';
                    
                    // Create new PDF document
                    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                    
                    // Set document information
                    $pdf->SetCreator('Terral Online Production System');
                    $pdf->SetAuthor('Terral');
                    $pdf->SetTitle('Invoice - Order #' . $order_id);
                    $pdf->SetSubject('Invoice');
                    
                    // Set margins
                    $pdf->SetMargins(15, 15, 15);
                    $pdf->SetHeaderMargin(10);
                    $pdf->SetFooterMargin(10);
                    
                    // Set auto page breaks
                    $pdf->SetAutoPageBreak(true, 15);
                    
                    // Add a page
                    $pdf->AddPage();
                    
                    // Get current date
                    $invoice_date = date('Y-m-d');
                    
                    // Get customer details
                    $query = "SELECT CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email, u.phone, u.address, u.city, u.state, u.postal_code, u.country
                              FROM users u
                              WHERE u.id = :user_id
                              LIMIT 0,1";
                    $stmt = $this->conn->prepare($query);
                    $stmt->bindParam(':user_id', $this->order->user_id);
                    $stmt->execute();
                    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Prepare invoice content
                    $html = '
                    <h1>INVOICE</h1>
                    <table border="0" cellspacing="0" cellpadding="4">
                        <tr>
                            <td width="50%"><strong>Terral Online Production System</strong><br />
                                123 Company Street<br />
                                City, State<br />
                                Country<br />
                                Email: info@terral.com
                            </td>
                            <td width="50%">
                                <table border="0" cellspacing="0" cellpadding="4">
                                    <tr>
                                        <td><strong>Invoice #:</strong></td>
                                        <td>INV-' . sprintf('%06d', $order_id) . '</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Date:</strong></td>
                                        <td>' . $invoice_date . '</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Order #:</strong></td>
                                        <td>' . $order_id . '</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Payment Method:</strong></td>
                                        <td>' . $this->order->payment_method . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                    <br /><br />
                    <h2>Bill To:</h2>
                    <table border="0" cellspacing="0" cellpadding="4">
                        <tr>
                            <td width="100%">
                                <strong>' . $customer['customer_name'] . '</strong><br />
                                ' . $this->order->shipping_address . '<br />
                                ' . $this->order->shipping_city . ', ' . $this->order->shipping_state . ' ' . $this->order->shipping_postal_code . '<br />
                                ' . $this->order->shipping_country . '<br />
                                Phone: ' . $this->order->shipping_phone . '<br />
                                Email: ' . $customer['email'] . '
                            </td>
                        </tr>
                    </table>
                    <br /><br />
                    <h2>Order Details:</h2>
                    <table border="1" cellspacing="0" cellpadding="4">
                        <tr style="background-color:#EEEEEE;">
                            <th width="10%">Item #</th>
                            <th width="40%">Product</th>
                            <th width="15%">Price</th>
                            <th width="10%">Quantity</th>
                            <th width="25%">Total</th>
                        </tr>';
                    
                    $item_count = 1;
                    $subtotal = 0;
                    
                    foreach($this->order->items as $item) {
                        $item_total = $item['price'] * $item['quantity'];
                        $subtotal += $item_total;
                        
                        $html .= '
                        <tr>
                            <td>' . $item_count . '</td>
                            <td>' . $item['product_name'] . '</td>
                            <td align="right">' . number_format($item['price'], 2) . '</td>
                            <td align="center">' . $item['quantity'] . '</td>
                            <td align="right">' . number_format($item_total, 2) . '</td>
                        </tr>';
                        
                        $item_count++;
                    }
                    
                    // Calculate tax (assuming 15% tax)
                    $tax_rate = 0.15;
                    $tax = $subtotal * $tax_rate;
                    
                    // Calculate shipping (fixed fee)
                    $shipping = 10.00;
                    
                    // Calculate total
                    $total = $subtotal + $tax + $shipping;
                    
                    $html .= '
                        <tr>
                            <td colspan="4" align="right"><strong>Subtotal:</strong></td>
                            <td align="right">' . number_format($subtotal, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="4" align="right"><strong>Tax (15%):</strong></td>
                            <td align="right">' . number_format($tax, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="4" align="right"><strong>Shipping:</strong></td>
                            <td align="right">' . number_format($shipping, 2) . '</td>
                        </tr>
                        <tr>
                            <td colspan="4" align="right"><strong>Total:</strong></td>
                            <td align="right"><strong>' . number_format($total, 2) . '</strong></td>
                        </tr>
                    </table>
                    <br /><br />
                    <p><strong>Notes:</strong> ' . ($this->order->notes ? $this->order->notes : 'None') . '</p>
                    <p><em>Thank you for your business!</em></p>';
                    
                    // Output HTML content
                    $pdf->writeHTML($html, true, false, true, false, '');
                    
                    // Close and output PDF document
                    $invoice_dir = ROOT_PATH . '/uploads/invoices';
                    if (!file_exists($invoice_dir)) {
                        mkdir($invoice_dir, 0755, true);
                    }
                    
                    $pdf_file = 'invoice_' . $order_id . '.pdf';
                    $pdf_path = $invoice_dir . '/' . $pdf_file;
                    $pdf->Output($pdf_path, 'F');
                    
                    // If this is a direct API call, not a callback
                    if($_SERVER['REQUEST_METHOD'] === 'GET') {
                        // Set headers for PDF download
                        header('Content-Type: application/pdf');
                        header('Content-Disposition: attachment; filename="' . $pdf_file . '"');
                        header('Cache-Control: max-age=0');
                        
                        // Output PDF
                        readfile($pdf_path);
                        exit;
                    }
                } else {
                    // TCPDF not available, use text invoice as fallback
                    $this->logError("TCPDF library not found, using text invoice as fallback");
                    throw new Exception("PDF library not found");
                }
            }
            
            return true;
        } catch(Exception $e) {
            if($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Set response code - 503 service unavailable
                http_response_code(503);
                
                // Tell the user
                echo json_encode(array("message" => "Unable to generate invoice. Error: " . $e->getMessage()));
            }
            
            $this->logError("Invoice generation error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get payment details
    public function getPayment($id) {
        // Set order id
        $this->order->id = $id;
        
        // Check if order exists
        if(!$this->order->readOne()) {
            // Set response code - 404 not found
            http_response_code(404);
            
            // Tell the user order not found
            echo json_encode(array("message" => "Order not found."));
            return;
        }
        
        // Create payment array
        $payment_arr = array(
            "order_id" => $this->order->id,
            "payment_status" => $this->order->payment_status,
            "payment_method" => $this->order->payment_method,
            "payment_id" => $this->order->payment_id,
            "total_amount" => $this->order->total_price,
            "created_at" => $this->order->created_at,
            "simulation_mode" => $this->simulation_mode
        );
        
        // Set response code - 200 OK
        http_response_code(200);
        
        // Response data
        echo json_encode($payment_arr);
    }
    
    // Send payment confirmation
    private function sendPaymentConfirmation($order_id, $receipt_number) {
        // In a real application, send email and SMS notifications
        // For now, just log the action
        $log_message = "Payment confirmation sent for Order #" . $order_id . " with receipt #" . $receipt_number;
        $this->logInfo($log_message);
        
        return true;
    }
    
    // Log callback data
    private function logCallback($data) {
        $log_file = ROOT_PATH . '/logs/mpesa_callback.log';
        $log_message = date('Y-m-d H:i:s') . ': ' . json_encode($data) . "\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
    
    // Log error
    private function logError($message) {
        $log_file = ROOT_PATH . '/logs/error.log';
        $log_message = date('Y-m-d H:i:s') . ' ERROR: ' . $message . "\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
    
    // Log info
    private function logInfo($message) {
        $log_file = ROOT_PATH . '/logs/info.log';
        $log_message = date('Y-m-d H:i:s') . ' INFO: ' . $message . "\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}
?> 