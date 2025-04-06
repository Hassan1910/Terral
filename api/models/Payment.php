<?php
class Payment {
    // Database connection and table name
    private $conn;
    private $table_name = "payments";
    
    // Object properties
    public $id;
    public $order_id;
    public $amount;
    public $payment_method;
    public $transaction_id;
    public $status;
    public $payment_date;
    public $created_at;
    public $updated_at;
    
    // M-Pesa configuration
    private $mpesa_consumer_key = "YOUR_MPESA_CONSUMER_KEY";
    private $mpesa_consumer_secret = "YOUR_MPESA_CONSUMER_SECRET";
    private $mpesa_passkey = "YOUR_MPESA_PASSKEY";
    private $mpesa_shortcode = "YOUR_MPESA_SHORTCODE";
    private $mpesa_callback_url = "YOUR_CALLBACK_URL";
    private $mpesa_env = "sandbox"; // "sandbox" or "production"
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create payment record
    public function create() {
        // Query to insert payment record
        $query = "INSERT INTO " . $this->table_name . " 
                SET 
                    order_id = :order_id,
                    amount = :amount,
                    payment_method = :payment_method,
                    transaction_id = :transaction_id,
                    status = :status,
                    payment_date = :payment_date";
                    
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind parameters
        $this->order_id = htmlspecialchars(strip_tags($this->order_id));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->payment_method = htmlspecialchars(strip_tags($this->payment_method));
        $this->transaction_id = htmlspecialchars(strip_tags($this->transaction_id));
        $this->status = htmlspecialchars(strip_tags($this->status));
        
        $stmt->bindParam(':order_id', $this->order_id, PDO::PARAM_INT);
        $stmt->bindParam(':amount', $this->amount);
        $stmt->bindParam(':payment_method', $this->payment_method);
        $stmt->bindParam(':transaction_id', $this->transaction_id);
        $stmt->bindParam(':status', $this->status);
        
        // If payment date is provided, use it, otherwise use current date
        if(!empty($this->payment_date)) {
            $stmt->bindParam(':payment_date', $this->payment_date);
        } else {
            $payment_date = date('Y-m-d H:i:s');
            $stmt->bindParam(':payment_date', $payment_date);
        }
        
        // Execute query
        if($stmt->execute()) {
            // Get the ID of the newly created payment
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    // Update payment record
    public function update() {
        // Query to update payment record
        $query = "UPDATE " . $this->table_name . "
                SET 
                    amount = :amount,
                    payment_method = :payment_method,
                    transaction_id = :transaction_id,
                    status = :status,
                    payment_date = :payment_date
                WHERE 
                    id = :id";
                    
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind parameters
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->payment_method = htmlspecialchars(strip_tags($this->payment_method));
        $this->transaction_id = htmlspecialchars(strip_tags($this->transaction_id));
        $this->status = htmlspecialchars(strip_tags($this->status));
        
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        $stmt->bindParam(':amount', $this->amount);
        $stmt->bindParam(':payment_method', $this->payment_method);
        $stmt->bindParam(':transaction_id', $this->transaction_id);
        $stmt->bindParam(':status', $this->status);
        
        // If payment date is provided, use it
        if(!empty($this->payment_date)) {
            $stmt->bindParam(':payment_date', $this->payment_date);
        } else {
            $payment_date = date('Y-m-d H:i:s');
            $stmt->bindParam(':payment_date', $payment_date);
        }
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Read single payment
    public function read() {
        // Query to select single payment
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        // Get record
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Set properties
        if($row) {
            $this->id = $row['id'];
            $this->order_id = $row['order_id'];
            $this->amount = $row['amount'];
            $this->payment_method = $row['payment_method'];
            $this->transaction_id = $row['transaction_id'];
            $this->status = $row['status'];
            $this->payment_date = $row['payment_date'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        
        return false;
    }
    
    // Read payments by order ID
    public function readByOrder() {
        // Query to select payments for an order
        $query = "SELECT * FROM " . $this->table_name . " WHERE order_id = :order_id ORDER BY created_at DESC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(':order_id', $this->order_id, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Generate M-Pesa access token
    private function generateMpesaAccessToken() {
        $url = $this->mpesa_env == "sandbox" 
            ? "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials" 
            : "https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";
            
        $credentials = base64_encode($this->mpesa_consumer_key . ":" . $this->mpesa_consumer_secret);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic " . $credentials]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response);
        
        return isset($result->access_token) ? $result->access_token : null;
    }
    
    // Initiate M-Pesa STK Push request
    public function initiateMpesaPayment($phone, $amount, $reference) {
        // Get access token
        $access_token = $this->generateMpesaAccessToken();
        
        if(!$access_token) {
            return [
                'success' => false,
                'message' => 'Failed to generate access token'
            ];
        }
        
        // Prepare phone number (remove + if present and ensure it's in the format 2547XXXXXXXX)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Ensure it's in the right format for Kenya numbers
        if(strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif(strlen($phone) === 9) {
            $phone = '254' . $phone;
        }
        
        // Prepare timestamp
        $timestamp = date('YmdHis');
        
        // Prepare password
        $password = base64_encode($this->mpesa_shortcode . $this->mpesa_passkey . $timestamp);
        
        // STK Push API endpoint
        $url = $this->mpesa_env == "sandbox" 
            ? "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest" 
            : "https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest";
        
        // STK Push payload
        $data = [
            'BusinessShortCode' => $this->mpesa_shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->mpesa_shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->mpesa_callback_url,
            'AccountReference' => $reference,
            'TransactionDesc' => 'Payment for order ' . $reference
        ];
        
        // Initialize cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        // If request was successful
        if($httpCode == 200 && isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
            return [
                'success' => true,
                'message' => 'STK Push sent successfully',
                'data' => $result
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to initiate payment',
                'error' => $result
            ];
        }
    }
    
    // Process M-Pesa callback
    public function processMpesaCallback($callback_data) {
        // Log the callback data for debugging
        file_put_contents(ROOT_PATH . '/logs/mpesa_callback_' . date('YmdHis') . '.log', json_encode($callback_data));
        
        // Check if the callback contains result data
        if(isset($callback_data['Body']['stkCallback'])) {
            $result_code = $callback_data['Body']['stkCallback']['ResultCode'];
            
            if($result_code == 0) {
                // Payment successful
                $callback_metadata = $callback_data['Body']['stkCallback']['CallbackMetadata']['Item'];
                
                $transaction_id = '';
                $amount = 0;
                
                // Extract transaction details
                foreach($callback_metadata as $item) {
                    if($item['Name'] == 'MpesaReceiptNumber') {
                        $transaction_id = $item['Value'];
                    } else if($item['Name'] == 'Amount') {
                        $amount = $item['Value'];
                    }
                }
                
                // Extract order reference from the account reference
                $reference = $callback_data['Body']['stkCallback']['CallbackMetadata']['Item'][3]['Value'];
                
                // Find order by reference
                $query = "SELECT id FROM orders WHERE id = :reference";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':reference', $reference);
                $stmt->execute();
                
                if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $order_id = $row['id'];
                    
                    // Record payment
                    $this->order_id = $order_id;
                    $this->amount = $amount;
                    $this->payment_method = 'mpesa';
                    $this->transaction_id = $transaction_id;
                    $this->status = 'completed';
                    
                    if($this->create()) {
                        // Update order payment status
                        $query = "UPDATE orders SET payment_status = 'completed', payment_id = :payment_id WHERE id = :order_id";
                        $stmt = $this->conn->prepare($query);
                        $stmt->bindParam(':payment_id', $this->id);
                        $stmt->bindParam(':order_id', $order_id);
                        $stmt->execute();
                        
                        return [
                            'success' => true,
                            'message' => 'Payment processed successfully'
                        ];
                    }
                }
            }
        }
        
        return [
            'success' => false,
            'message' => 'Failed to process payment callback'
        ];
    }
    
    // Generate PDF invoice
    public function generateInvoice($order_id) {
        // Fetch order and payment details
        $query = "SELECT o.*, p.transaction_id, p.payment_date, u.first_name, u.last_name, u.email, u.phone 
                  FROM orders o 
                  LEFT JOIN payments p ON o.id = p.order_id 
                  LEFT JOIN users u ON o.user_id = u.id 
                  WHERE o.id = :order_id";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$order) {
            return [
                'success' => false,
                'message' => 'Order not found'
            ];
        }
        
        // Fetch order items
        $query = "SELECT * FROM order_items WHERE order_id = :order_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate invoice content
        $invoice_html = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, .15); }
                    .invoice-box table { width: 100%; line-height: inherit; text-align: left; }
                    .invoice-box table td { padding: 5px; vertical-align: top; }
                    .invoice-box table tr.top table td { padding-bottom: 20px; }
                    .invoice-box table tr.top table td.title { font-size: 45px; line-height: 45px; color: #333; }
                    .invoice-box table tr.information table td { padding-bottom: 40px; }
                    .invoice-box table tr.heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; }
                    .invoice-box table tr.details td { padding-bottom: 20px; }
                    .invoice-box table tr.item td{ border-bottom: 1px solid #eee; }
                    .invoice-box table tr.item.last td { border-bottom: none; }
                    .invoice-box table tr.total td:nth-child(4) { border-top: 2px solid #eee; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class="invoice-box">
                    <table cellpadding="0" cellspacing="0">
                        <tr class="top">
                            <td colspan="4">
                                <table>
                                    <tr>
                                        <td class="title">
                                            Terral Invoice
                                        </td>
                                        <td>
                                            Invoice #: ' . $order_id . '<br>
                                            Created: ' . date('F d, Y', strtotime($order['created_at'])) . '<br>
                                            Payment Date: ' . (isset($order['payment_date']) ? date('F d, Y', strtotime($order['payment_date'])) : 'N/A') . '
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <tr class="information">
                            <td colspan="4">
                                <table>
                                    <tr>
                                        <td>
                                            Terral, Inc.<br>
                                            123 Main St<br>
                                            Meru, Kenya
                                        </td>
                                        <td>
                                            ' . $order['first_name'] . ' ' . $order['last_name'] . '<br>
                                            ' . $order['email'] . '<br>
                                            ' . $order['shipping_address'] . ', ' . $order['shipping_city'] . '<br>
                                            ' . $order['shipping_postal_code'] . ', ' . $order['shipping_country'] . '
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <tr class="heading">
                            <td>Payment Method</td>
                            <td colspan="3">' . ucfirst($order['payment_method']) . '</td>
                        </tr>
                        
                        <tr class="details">
                            <td>Transaction ID</td>
                            <td colspan="3">' . (isset($order['transaction_id']) ? $order['transaction_id'] : 'N/A') . '</td>
                        </tr>
                        
                        <tr class="heading">
                            <td>Item</td>
                            <td>Quantity</td>
                            <td>Price</td>
                            <td>Total</td>
                        </tr>';
                        
        foreach($items as $item) {
            $invoice_html .= '
                <tr class="item">
                    <td>' . $item['product_name'] . '</td>
                    <td>' . $item['quantity'] . '</td>
                    <td>$' . number_format($item['price'], 2) . '</td>
                    <td>$' . number_format($item['price'] * $item['quantity'], 2) . '</td>
                </tr>';
        }
                        
        $invoice_html .= '
                        <tr class="total">
                            <td colspan="3"></td>
                            <td>Total: $' . number_format($order['total_price'], 2) . '</td>
                        </tr>
                    </table>
                </div>
            </body>
            </html>';
            
        // Check if mPDF library is available
        if(class_exists('\\Mpdf\\Mpdf')) {
            try {
                // Generate PDF using mPDF
                $mpdf = new \Mpdf\Mpdf();
                $mpdf->WriteHTML($invoice_html);
                
                // Create invoices directory if it doesn't exist
                $invoices_dir = ROOT_PATH . '/uploads/invoices';
                if(!file_exists($invoices_dir)) {
                    mkdir($invoices_dir, 0777, true);
                }
                
                $file_name = 'invoice_' . $order_id . '_' . date('YmdHis') . '.pdf';
                $file_path = $invoices_dir . '/' . $file_name;
                
                $mpdf->Output($file_path, 'F');
                
                return [
                    'success' => true,
                    'message' => 'Invoice generated successfully',
                    'file_path' => $file_path,
                    'file_name' => $file_name
                ];
            } catch(Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Failed to generate PDF: ' . $e->getMessage()
                ];
            }
        } else {
            // If mPDF is not available, return HTML
            return [
                'success' => true,
                'message' => 'Invoice generated as HTML',
                'html' => $invoice_html
            ];
        }
    }
}
?> 