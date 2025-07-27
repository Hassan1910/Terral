<?php
/**
 * Payment Validation Helper
 * Ensures customers must pay before goods are delivered
 */

class PaymentValidationHelper {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    /**
     * Validate if order can be processed based on payment status
     * @param int $order_id
     * @return array
     */
    public function validateOrderForProcessing($order_id) {
        try {
            // Get order details with payment information
            $query = "SELECT o.*, p.status as payment_status, p.transaction_id, p.payment_date
                      FROM orders o 
                      LEFT JOIN payments p ON o.id = p.order_id 
                      WHERE o.id = :order_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                return [
                    'valid' => false,
                    'message' => 'Order not found',
                    'code' => 'ORDER_NOT_FOUND'
                ];
            }
            
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check payment requirements based on payment method
            return $this->checkPaymentRequirements($order);
            
        } catch (Exception $e) {
            return [
                'valid' => false,
                'message' => 'Error validating order: ' . $e->getMessage(),
                'code' => 'VALIDATION_ERROR'
            ];
        }
    }
    
    /**
     * Check payment requirements based on payment method
     * @param array $order
     * @return array
     */
    private function checkPaymentRequirements($order) {
        $payment_method = $order['payment_method'];
        $payment_status = $order['payment_status'] ?? 'pending';
        $order_status = $order['status'];
        
        switch ($payment_method) {
            case 'mpesa':
            case 'card':
            case 'bank_transfer':
                // These methods require immediate payment confirmation
                if ($payment_status !== 'completed') {
                    return [
                        'valid' => false,
                        'message' => 'Payment must be completed before order can be processed',
                        'code' => 'PAYMENT_NOT_COMPLETED',
                        'required_action' => 'complete_payment'
                    ];
                }
                break;
                
            case 'cash_on_delivery':
                // COD orders can be processed but not delivered until payment
                if ($order_status === 'delivered' && $payment_status !== 'completed') {
                    return [
                        'valid' => false,
                        'message' => 'Cash payment must be collected before marking order as delivered',
                        'code' => 'COD_PAYMENT_NOT_COLLECTED',
                        'required_action' => 'collect_payment'
                    ];
                }
                break;
                
            default:
                return [
                    'valid' => false,
                    'message' => 'Invalid payment method',
                    'code' => 'INVALID_PAYMENT_METHOD'
                ];
        }
        
        return [
            'valid' => true,
            'message' => 'Order payment validation passed',
            'code' => 'VALIDATION_PASSED'
        ];
    }
    
    /**
     * Check if order status can be updated
     * @param int $order_id
     * @param string $new_status
     * @return array
     */
    public function canUpdateOrderStatus($order_id, $new_status) {
        $validation = $this->validateOrderForProcessing($order_id);
        
        if (!$validation['valid'] && $validation['code'] === 'PAYMENT_NOT_COMPLETED') {
            // Prevent certain status updates without payment
            $restricted_statuses = ['processing', 'shipped', 'delivered'];
            
            if (in_array($new_status, $restricted_statuses)) {
                return [
                    'allowed' => false,
                    'message' => 'Cannot update order to "' . $new_status . '" without payment confirmation',
                    'code' => 'PAYMENT_REQUIRED'
                ];
            }
        }
        
        // Special validation for delivery status
        if ($new_status === 'delivered') {
            return $this->validateDelivery($order_id);
        }
        
        return [
            'allowed' => true,
            'message' => 'Status update allowed',
            'code' => 'UPDATE_ALLOWED'
        ];
    }
    
    /**
     * Validate if order can be marked as delivered
     * @param int $order_id
     * @return array
     */
    public function validateDelivery($order_id) {
        try {
            $query = "SELECT o.payment_method, o.status, p.status as payment_status, p.transaction_id
                      FROM orders o 
                      LEFT JOIN payments p ON o.id = p.order_id 
                      WHERE o.id = :order_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                return [
                    'allowed' => false,
                    'message' => 'Order not found',
                    'code' => 'ORDER_NOT_FOUND'
                ];
            }
            
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $payment_method = $order['payment_method'];
            $payment_status = $order['payment_status'] ?? 'pending';
            
            // All payment methods require payment confirmation before delivery
            if ($payment_status !== 'completed') {
                $message = 'Payment must be completed before marking order as delivered';
                
                if ($payment_method === 'cash_on_delivery') {
                    $message = 'Cash payment must be collected before marking order as delivered';
                }
                
                return [
                    'allowed' => false,
                    'message' => $message,
                    'code' => 'PAYMENT_REQUIRED_FOR_DELIVERY'
                ];
            }
            
            return [
                'allowed' => true,
                'message' => 'Order can be marked as delivered',
                'code' => 'DELIVERY_ALLOWED'
            ];
            
        } catch (Exception $e) {
            return [
                'allowed' => false,
                'message' => 'Error validating delivery: ' . $e->getMessage(),
                'code' => 'VALIDATION_ERROR'
            ];
        }
    }
    
    /**
     * Get order payment summary
     * @param int $order_id
     * @return array
     */
    public function getOrderPaymentSummary($order_id) {
        try {
            $query = "SELECT o.id, o.total_price, o.payment_method, o.status,
                             p.status as payment_status, p.transaction_id, p.payment_date, p.amount
                      FROM orders o 
                      LEFT JOIN payments p ON o.id = p.order_id 
                      WHERE o.id = :order_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                return [
                    'found' => false,
                    'message' => 'Order not found'
                ];
            }
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $validation = $this->validateOrderForProcessing($order_id);
            
            return [
                'found' => true,
                'order_id' => $data['id'],
                'total_amount' => $data['total_price'],
                'payment_method' => $data['payment_method'],
                'order_status' => $data['status'],
                'payment_status' => $data['payment_status'] ?? 'pending',
                'transaction_id' => $data['transaction_id'],
                'payment_date' => $data['payment_date'],
                'paid_amount' => $data['amount'],
                'is_paid' => ($data['payment_status'] ?? 'pending') === 'completed',
                'validation' => $validation
            ];
            
        } catch (Exception $e) {
            return [
                'found' => false,
                'message' => 'Error retrieving payment summary: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Log payment validation events
     * @param string $event
     * @param array $data
     */
    public function logValidationEvent($event, $data) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        // Log to file
        $log_file = dirname(dirname(__DIR__)) . '/logs/payment_validation.log';
        if (!file_exists(dirname($log_file))) {
            mkdir(dirname($log_file), 0755, true);
        }
        
        file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
}
