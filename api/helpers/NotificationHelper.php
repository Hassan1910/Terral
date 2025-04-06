<?php
// Include required libraries
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Twilio\Rest\Client;

class NotificationHelper {
    // Email configuration
    private $smtp_host = "smtp.example.com"; // Change in production
    private $smtp_port = 587;
    private $smtp_username = "no-reply@terral.com"; // Change in production
    private $smtp_password = "your_password"; // Change in production
    private $smtp_from_email = "no-reply@terral.com"; // Change in production
    private $smtp_from_name = "Terral Online Production System"; // Change in production
    
    // SMS configuration (Twilio)
    private $twilio_account_sid = "YOUR_TWILIO_ACCOUNT_SID"; // Change in production
    private $twilio_auth_token = "YOUR_TWILIO_AUTH_TOKEN"; // Change in production
    private $twilio_from_number = "YOUR_TWILIO_NUMBER"; // Change in production
    
    // Constructor
    public function __construct() {
        // Load configuration from .env if available
        if (class_exists('\\Dotenv\\Dotenv') && file_exists(ROOT_PATH . '/.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(ROOT_PATH);
            $dotenv->load();
            
            // Set email configuration from environment variables
            $this->smtp_host = isset($_ENV['SMTP_HOST']) ? $_ENV['SMTP_HOST'] : $this->smtp_host;
            $this->smtp_port = isset($_ENV['SMTP_PORT']) ? $_ENV['SMTP_PORT'] : $this->smtp_port;
            $this->smtp_username = isset($_ENV['SMTP_USERNAME']) ? $_ENV['SMTP_USERNAME'] : $this->smtp_username;
            $this->smtp_password = isset($_ENV['SMTP_PASSWORD']) ? $_ENV['SMTP_PASSWORD'] : $this->smtp_password;
            $this->smtp_from_email = isset($_ENV['SMTP_FROM_EMAIL']) ? $_ENV['SMTP_FROM_EMAIL'] : $this->smtp_from_email;
            $this->smtp_from_name = isset($_ENV['SMTP_FROM_NAME']) ? $_ENV['SMTP_FROM_NAME'] : $this->smtp_from_name;
            
            // Set SMS configuration from environment variables
            $this->twilio_account_sid = isset($_ENV['TWILIO_ACCOUNT_SID']) ? $_ENV['TWILIO_ACCOUNT_SID'] : $this->twilio_account_sid;
            $this->twilio_auth_token = isset($_ENV['TWILIO_AUTH_TOKEN']) ? $_ENV['TWILIO_AUTH_TOKEN'] : $this->twilio_auth_token;
            $this->twilio_from_number = isset($_ENV['TWILIO_FROM_NUMBER']) ? $_ENV['TWILIO_FROM_NUMBER'] : $this->twilio_from_number;
        }
    }
    
    // Send email notification
    public function sendEmail($to, $subject, $message, $attachments = []) {
        // Check if PHPMailer is available
        if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            return [
                'success' => false,
                'message' => 'PHPMailer library not available'
            ];
        }
        
        try {
            // Create a new PHPMailer instance
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_port;
            
            // Recipients
            $mail->setFrom($this->smtp_from_email, $this->smtp_from_name);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // Add attachments if any
            if (!empty($attachments) && is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    if (isset($attachment['path']) && file_exists($attachment['path'])) {
                        $name = isset($attachment['name']) ? $attachment['name'] : basename($attachment['path']);
                        $mail->addAttachment($attachment['path'], $name);
                    }
                }
            }
            
            // Send email
            $mail->send();
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Email could not be sent. Error: ' . $mail->ErrorInfo
            ];
        }
    }
    
    // Send SMS notification
    public function sendSMS($to, $message) {
        // Check if Twilio SDK is available
        if (!class_exists('\\Twilio\\Rest\\Client')) {
            return [
                'success' => false,
                'message' => 'Twilio SDK not available'
            ];
        }
        
        try {
            // Create a new Twilio client
            $client = new Client($this->twilio_account_sid, $this->twilio_auth_token);
            
            // Send SMS
            $result = $client->messages->create(
                $to,
                [
                    'from' => $this->twilio_from_number,
                    'body' => $message
                ]
            );
            
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'sid' => $result->sid
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SMS could not be sent. Error: ' . $e->getMessage()
            ];
        }
    }
    
    // Send order confirmation notification
    public function sendOrderConfirmation($order_id, $user_email, $user_phone) {
        // Database connection
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get order details
        $query = "SELECT o.*, CONCAT(u.first_name, ' ', u.last_name) as customer_name 
                  FROM orders o 
                  JOIN users u ON o.user_id = u.id 
                  WHERE o.id = :order_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            return [
                'success' => false,
                'message' => 'Order not found'
            ];
        }
        
        // Get order items
        $query = "SELECT oi.*, p.name as product_name 
                  FROM order_items oi 
                  JOIN products p ON oi.product_id = p.id 
                  WHERE oi.order_id = :order_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate order confirmation email content
        $email_subject = "Order Confirmation - Order #" . $order_id;
        
        $email_content = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #f8f9fa; padding: 15px; text-align: center; }
                    .order-details { margin: 20px 0; }
                    .item { margin-bottom: 10px; }
                    .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; }
                    .total { font-weight: bold; margin-top: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                    th { background-color: #f2f2f2; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Thank You for Your Order!</h2>
                        <p>Order #" . $order_id . "</p>
                    </div>
                    
                    <div class='order-details'>
                        <p>Hello " . $order['customer_name'] . ",</p>
                        <p>Your order has been received and is being processed. Here's a summary of your order:</p>
                        
                        <table>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>";
                            
        foreach ($items as $item) {
            $email_content .= "
                            <tr>
                                <td>" . $item['product_name'] . "</td>
                                <td>" . $item['quantity'] . "</td>
                                <td>$" . number_format($item['price'], 2) . "</td>
                                <td>$" . number_format($item['price'] * $item['quantity'], 2) . "</td>
                            </tr>";
        }
        
        $email_content .= "
                            <tr class='total'>
                                <td colspan='3' align='right'>Total:</td>
                                <td>$" . number_format($order['total_price'], 2) . "</td>
                            </tr>
                        </table>
                        
                        <div class='shipping-info'>
                            <h3>Shipping Information</h3>
                            <p>
                                Address: " . $order['shipping_address'] . "<br>
                                City: " . $order['shipping_city'] . "<br>
                                State: " . $order['shipping_state'] . "<br>
                                Postal Code: " . $order['shipping_postal_code'] . "<br>
                                Country: " . $order['shipping_country'] . "
                            </p>
                        </div>
                    </div>
                    
                    <div class='footer'>
                        <p>If you have any questions, please contact our customer service at support@terral.com</p>
                        <p>&copy; " . date('Y') . " Terral Online Production System. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Send email
        $email_result = $this->sendEmail($user_email, $email_subject, $email_content);
        
        // SMS content
        $sms_content = "Thank you for your order #" . $order_id . " with Terral. Your order is being processed and we'll notify you once it ships. Total: $" . number_format($order['total_price'], 2);
        
        // Send SMS if phone number is provided
        $sms_result = null;
        if (!empty($user_phone)) {
            $sms_result = $this->sendSMS($user_phone, $sms_content);
        }
        
        return [
            'success' => true,
            'email_result' => $email_result,
            'sms_result' => $sms_result
        ];
    }
    
    // Send payment confirmation notification
    public function sendPaymentConfirmation($order_id, $user_email, $user_phone, $invoice_path = null) {
        // Database connection
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get payment details
        $query = "SELECT p.*, o.total_price, CONCAT(u.first_name, ' ', u.last_name) as customer_name 
                  FROM payments p 
                  JOIN orders o ON p.order_id = o.id 
                  JOIN users u ON o.user_id = u.id 
                  WHERE p.order_id = :order_id 
                  ORDER BY p.created_at DESC 
                  LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            return [
                'success' => false,
                'message' => 'Payment not found'
            ];
        }
        
        // Generate payment confirmation email content
        $email_subject = "Payment Confirmation - Order #" . $order_id;
        
        $email_content = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #f8f9fa; padding: 15px; text-align: center; }
                    .payment-details { margin: 20px 0; }
                    .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Payment Confirmation</h2>
                        <p>Order #" . $order_id . "</p>
                    </div>
                    
                    <div class='payment-details'>
                        <p>Hello " . $payment['customer_name'] . ",</p>
                        <p>Your payment for order #" . $order_id . " has been received and confirmed. Here's a summary of your payment:</p>
                        
                        <ul>
                            <li><strong>Order ID:</strong> " . $order_id . "</li>
                            <li><strong>Payment Method:</strong> " . ucfirst($payment['payment_method']) . "</li>
                            <li><strong>Transaction ID:</strong> " . $payment['transaction_id'] . "</li>
                            <li><strong>Amount:</strong> $" . number_format($payment['amount'], 2) . "</li>
                            <li><strong>Payment Date:</strong> " . date('F d, Y H:i:s', strtotime($payment['payment_date'])) . "</li>
                        </ul>
                        
                        <p>Your order is now being processed. We'll notify you once it ships.</p>
                    </div>
                    
                    <div class='footer'>
                        <p>If you have any questions, please contact our customer service at support@terral.com</p>
                        <p>&copy; " . date('Y') . " Terral Online Production System. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Prepare attachments if invoice is available
        $attachments = [];
        if ($invoice_path && file_exists($invoice_path)) {
            $attachments[] = [
                'path' => $invoice_path,
                'name' => 'Invoice_Order_' . $order_id . '.pdf'
            ];
        }
        
        // Send email
        $email_result = $this->sendEmail($user_email, $email_subject, $email_content, $attachments);
        
        // SMS content
        $sms_content = "Your payment of $" . number_format($payment['amount'], 2) . " for Terral order #" . $order_id . " has been confirmed. Thank you for your purchase!";
        
        // Send SMS if phone number is provided
        $sms_result = null;
        if (!empty($user_phone)) {
            $sms_result = $this->sendSMS($user_phone, $sms_content);
        }
        
        return [
            'success' => true,
            'email_result' => $email_result,
            'sms_result' => $sms_result
        ];
    }
    
    // Send order status update notification
    public function sendOrderStatusUpdate($order_id, $status, $user_email, $user_phone) {
        // Status messages
        $status_messages = [
            'processing' => 'Your order is now being processed and prepared for shipping.',
            'shipped' => 'Your order has been shipped! You can track your package with the provided tracking information.',
            'delivered' => 'Your order has been delivered. We hope you enjoy your products!',
            'canceled' => 'Your order has been canceled as requested.'
        ];
        
        // Check if status message exists
        if (!isset($status_messages[$status])) {
            return [
                'success' => false,
                'message' => 'Invalid status'
            ];
        }
        
        // Status specific subject
        $status_subjects = [
            'processing' => 'Your Order is Being Processed',
            'shipped' => 'Your Order Has Been Shipped',
            'delivered' => 'Your Order Has Been Delivered',
            'canceled' => 'Your Order Has Been Canceled'
        ];
        
        // Generate email content
        $email_subject = $status_subjects[$status] . " - Order #" . $order_id;
        
        $email_content = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #f8f9fa; padding: 15px; text-align: center; }
                    .status-message { margin: 20px 0; }
                    .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Order Status Update</h2>
                        <p>Order #" . $order_id . "</p>
                    </div>
                    
                    <div class='status-message'>
                        <p>Hello,</p>
                        <p>" . $status_messages[$status] . "</p>
                        <p>If you have any questions about your order, please contact our customer service.</p>
                    </div>
                    
                    <div class='footer'>
                        <p>If you have any questions, please contact our customer service at support@terral.com</p>
                        <p>&copy; " . date('Y') . " Terral Online Production System. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Send email
        $email_result = $this->sendEmail($user_email, $email_subject, $email_content);
        
        // SMS content
        $sms_content = "Terral Order #" . $order_id . " Update: " . $status_messages[$status];
        
        // Send SMS if phone number is provided
        $sms_result = null;
        if (!empty($user_phone)) {
            $sms_result = $this->sendSMS($user_phone, $sms_content);
        }
        
        return [
            'success' => true,
            'email_result' => $email_result,
            'sms_result' => $sms_result
        ];
    }
}
?> 