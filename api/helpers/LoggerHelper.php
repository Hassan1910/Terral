<?php
// Use Monolog if available
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class LoggerHelper {
    // Log channels
    const CHANNEL_API = 'api';
    const CHANNEL_PAYMENT = 'payment';
    const CHANNEL_ERROR = 'error';
    const CHANNEL_ACCESS = 'access';
    const CHANNEL_DEBUG = 'debug';
    
    // Log levels
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_NOTICE = 'notice';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    const LEVEL_ALERT = 'alert';
    const LEVEL_EMERGENCY = 'emergency';
    
    // Logger instances
    private static $loggers = [];
    
    // Directory for log files
    private static $log_dir;
    
    // Constructor
    public function __construct() {
        // Set log directory
        self::$log_dir = ROOT_PATH . '/logs';
        
        // Create logs directory if it doesn't exist
        if (!file_exists(self::$log_dir)) {
            mkdir(self::$log_dir, 0777, true);
        }
    }
    
    // Get logger for a specific channel
    private static function getLogger($channel) {
        // If logger for this channel already exists, return it
        if (isset(self::$loggers[$channel])) {
            return self::$loggers[$channel];
        }
        
        // If Monolog is available, use it
        if (class_exists('\\Monolog\\Logger')) {
            // Create logger
            $logger = new Logger($channel);
            
            // Create handler
            $handler = new RotatingFileHandler(
                self::$log_dir . '/' . $channel . '.log',
                30, // Keep logs for 30 days
                Logger::DEBUG
            );
            
            // Set formatter
            $formatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                "Y-m-d H:i:s.u"
            );
            $handler->setFormatter($formatter);
            
            // Add handler to logger
            $logger->pushHandler($handler);
            
            // Store logger in static array
            self::$loggers[$channel] = $logger;
            
            return $logger;
        } else {
            // Monolog not available, return null
            return null;
        }
    }
    
    // Log a message
    public static function log($level, $message, $context = [], $channel = self::CHANNEL_API) {
        // Get logger for channel
        $logger = self::getLogger($channel);
        
        // If Monolog is available, use it
        if ($logger) {
            // Log message
            switch ($level) {
                case self::LEVEL_DEBUG:
                    $logger->debug($message, $context);
                    break;
                case self::LEVEL_INFO:
                    $logger->info($message, $context);
                    break;
                case self::LEVEL_NOTICE:
                    $logger->notice($message, $context);
                    break;
                case self::LEVEL_WARNING:
                    $logger->warning($message, $context);
                    break;
                case self::LEVEL_ERROR:
                    $logger->error($message, $context);
                    break;
                case self::LEVEL_CRITICAL:
                    $logger->critical($message, $context);
                    break;
                case self::LEVEL_ALERT:
                    $logger->alert($message, $context);
                    break;
                case self::LEVEL_EMERGENCY:
                    $logger->emergency($message, $context);
                    break;
            }
        } else {
            // Monolog not available, use simple file logging
            self::simpleLog($level, $message, $context, $channel);
        }
    }
    
    // Simple file logging when Monolog is not available
    private static function simpleLog($level, $message, $context = [], $channel = self::CHANNEL_API) {
        // Create log entry
        $log_entry = '[' . date('Y-m-d H:i:s') . '] ' . strtoupper($level) . ': ' . $message;
        
        // Add context if not empty
        if (!empty($context)) {
            $log_entry .= ' ' . json_encode($context);
        }
        
        // Add new line
        $log_entry .= PHP_EOL;
        
        // Append to log file
        file_put_contents(
            self::$log_dir . '/' . $channel . '.log',
            $log_entry,
            FILE_APPEND
        );
    }
    
    // Debug log
    public static function debug($message, $context = [], $channel = self::CHANNEL_API) {
        self::log(self::LEVEL_DEBUG, $message, $context, $channel);
    }
    
    // Info log
    public static function info($message, $context = [], $channel = self::CHANNEL_API) {
        self::log(self::LEVEL_INFO, $message, $context, $channel);
    }
    
    // Notice log
    public static function notice($message, $context = [], $channel = self::CHANNEL_API) {
        self::log(self::LEVEL_NOTICE, $message, $context, $channel);
    }
    
    // Warning log
    public static function warning($message, $context = [], $channel = self::CHANNEL_API) {
        self::log(self::LEVEL_WARNING, $message, $context, $channel);
    }
    
    // Error log
    public static function error($message, $context = [], $channel = self::CHANNEL_ERROR) {
        self::log(self::LEVEL_ERROR, $message, $context, $channel);
    }
    
    // Critical log
    public static function critical($message, $context = [], $channel = self::CHANNEL_ERROR) {
        self::log(self::LEVEL_CRITICAL, $message, $context, $channel);
    }
    
    // Alert log
    public static function alert($message, $context = [], $channel = self::CHANNEL_ERROR) {
        self::log(self::LEVEL_ALERT, $message, $context, $channel);
    }
    
    // Emergency log
    public static function emergency($message, $context = [], $channel = self::CHANNEL_ERROR) {
        self::log(self::LEVEL_EMERGENCY, $message, $context, $channel);
    }
    
    // Log API request
    public static function logApiRequest($method, $endpoint, $params = [], $ip = '') {
        $context = [
            'method' => $method,
            'endpoint' => $endpoint,
            'params' => $params,
            'ip' => $ip ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        self::info('API Request', $context, self::CHANNEL_API);
    }
    
    // Log API response
    public static function logApiResponse($status_code, $response = [], $execution_time = 0) {
        $context = [
            'status_code' => $status_code,
            'response' => $response,
            'execution_time' => $execution_time . 'ms'
        ];
        
        self::info('API Response', $context, self::CHANNEL_API);
    }
    
    // Log payment attempt
    public static function logPaymentAttempt($order_id, $payment_method, $amount, $user_id = null) {
        $context = [
            'order_id' => $order_id,
            'payment_method' => $payment_method,
            'amount' => $amount,
            'user_id' => $user_id,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        self::info('Payment Attempt', $context, self::CHANNEL_PAYMENT);
    }
    
    // Log payment success
    public static function logPaymentSuccess($order_id, $payment_method, $amount, $transaction_id, $user_id = null) {
        $context = [
            'order_id' => $order_id,
            'payment_method' => $payment_method,
            'amount' => $amount,
            'transaction_id' => $transaction_id,
            'user_id' => $user_id
        ];
        
        self::info('Payment Success', $context, self::CHANNEL_PAYMENT);
    }
    
    // Log payment failure
    public static function logPaymentFailure($order_id, $payment_method, $amount, $error, $user_id = null) {
        $context = [
            'order_id' => $order_id,
            'payment_method' => $payment_method,
            'amount' => $amount,
            'error' => $error,
            'user_id' => $user_id
        ];
        
        self::error('Payment Failure', $context, self::CHANNEL_PAYMENT);
    }
    
    // Log user login
    public static function logUserLogin($user_id, $email, $success = true, $ip = '') {
        $context = [
            'user_id' => $user_id,
            'email' => $email,
            'success' => $success,
            'ip' => $ip ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        if ($success) {
            self::info('User Login Success', $context, self::CHANNEL_ACCESS);
        } else {
            self::warning('User Login Failure', $context, self::CHANNEL_ACCESS);
        }
    }
    
    // Log order status change
    public static function logOrderStatusChange($order_id, $old_status, $new_status, $user_id = null) {
        $context = [
            'order_id' => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'user_id' => $user_id
        ];
        
        self::info('Order Status Change', $context, self::CHANNEL_API);
    }
}
?> 