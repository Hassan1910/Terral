<?php
// Include models
include_once ROOT_PATH . '/models/Order.php';
include_once ROOT_PATH . '/models/Product.php';
include_once ROOT_PATH . '/models/User.php';

class ReportController {
    private $conn;
    private $order;
    private $product;
    private $user;
    
    // Constructor
    public function __construct() {
        // Get database connection
        $database = new Database();
        $this->conn = $database->getConnection();
        
        // Initialize models
        $this->order = new Order($this->conn);
        $this->product = new Product($this->conn);
        $this->user = new User($this->conn);
    }
    
    // Get dashboard statistics
    public function getDashboardStats() {
        // Get current date and time
        $now = new \DateTime();
        $now_str = $now->format('Y-m-d H:i:s');
        
        // Calculate 30 days ago
        $thirty_days_ago = clone $now;
        $thirty_days_ago->modify('-30 days');
        $thirty_days_ago_str = $thirty_days_ago->format('Y-m-d H:i:s');
        
        // Calculate 7 days ago
        $seven_days_ago = clone $now;
        $seven_days_ago->modify('-7 days');
        $seven_days_ago_str = $seven_days_ago->format('Y-m-d H:i:s');
        
        // Get order statistics
        $order_count = $this->getOrderCount();
        $order_count_last_30_days = $this->getOrderCountByDateRange($thirty_days_ago_str, $now_str);
        $order_count_last_7_days = $this->getOrderCountByDateRange($seven_days_ago_str, $now_str);
        $order_status_counts = $this->getOrderStatusCounts();
        
        // Get sales statistics
        $total_sales = $this->getTotalSales();
        $total_sales_last_30_days = $this->getTotalSalesByDateRange($thirty_days_ago_str, $now_str);
        $total_sales_last_7_days = $this->getTotalSalesByDateRange($seven_days_ago_str, $now_str);
        
        // Get product statistics
        $product_count = $this->getProductCount();
        $low_stock_count = $this->getLowStockCount();
        
        // Get customer statistics
        $customer_count = $this->getCustomerCount();
        $new_customers_last_30_days = $this->getNewCustomerCountByDateRange($thirty_days_ago_str, $now_str);
        
        // Prepare response
        $response = [
            "orders" => [
                "total" => $order_count,
                "last_30_days" => $order_count_last_30_days,
                "last_7_days" => $order_count_last_7_days,
                "by_status" => $order_status_counts
            ],
            "sales" => [
                "total" => $total_sales,
                "last_30_days" => $total_sales_last_30_days,
                "last_7_days" => $total_sales_last_7_days
            ],
            "products" => [
                "total" => $product_count,
                "low_stock" => $low_stock_count
            ],
            "customers" => [
                "total" => $customer_count,
                "new_last_30_days" => $new_customers_last_30_days
            ]
        ];
        
        // Set response code - 200 OK
        http_response_code(200);
        
        // Response data
        echo json_encode($response);
    }
    
    // Get sales report
    public function getSalesReport($query_params) {
        // Initialize parameters
        $start_date = isset($query_params['start_date']) ? $query_params['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($query_params['end_date']) ? $query_params['end_date'] : date('Y-m-d');
        $group_by = isset($query_params['group_by']) ? $query_params['group_by'] : 'day';
        
        // Validate group_by parameter
        $valid_groups = ['day', 'week', 'month'];
        if(!in_array($group_by, $valid_groups)) {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user
            echo json_encode(array(
                "message" => "Invalid group_by parameter. Valid values are: " . implode(', ', $valid_groups)
            ));
            return;
        }
        
        // Format dates for database query
        $start_date_obj = new \DateTime($start_date);
        $start_date_str = $start_date_obj->format('Y-m-d 00:00:00');
        
        $end_date_obj = new \DateTime($end_date);
        $end_date_str = $end_date_obj->format('Y-m-d 23:59:59');
        
        // Prepare grouping format based on group_by parameter
        $group_format = '';
        $date_format = '';
        switch($group_by) {
            case 'day':
                $group_format = 'DATE(created_at)';
                $date_format = 'Y-m-d';
                break;
            case 'week':
                $group_format = 'YEARWEEK(created_at, 1)';
                $date_format = 'Y-W';
                break;
            case 'month':
                $group_format = 'DATE_FORMAT(created_at, "%Y-%m")';
                $date_format = 'Y-m';
                break;
        }
        
        // Get sales data
        $query = "SELECT " . $group_format . " as date_group, 
                  COUNT(*) as order_count, 
                  SUM(total_price) as total_sales, 
                  AVG(total_price) as average_sale 
                  FROM orders 
                  WHERE created_at BETWEEN :start_date AND :end_date 
                  AND status != 'canceled' 
                  GROUP BY date_group 
                  ORDER BY date_group ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $start_date_str);
        $stmt->bindParam(':end_date', $end_date_str);
        $stmt->execute();
        
        // Prepare response
        $report_data = [
            "sales" => [],
            "parameters" => [
                "start_date" => $start_date,
                "end_date" => $end_date,
                "group_by" => $group_by
            ],
            "summary" => [
                "total_orders" => 0,
                "total_sales" => 0,
                "average_sale" => 0
            ]
        ];
        
        $total_orders = 0;
        $total_sales = 0;
        
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date_group = $row['date_group'];
            $label = '';
            
            // Format date label based on group_by
            if($group_by == 'day') {
                $label = date('Y-m-d', strtotime($date_group));
            } else if($group_by == 'week') {
                // Extract year and week number
                $year = substr($date_group, 0, 4);
                $week = substr($date_group, 4);
                $label = $year . '-W' . $week;
            } else { // month
                $label = $date_group;
            }
            
            $report_data["sales"][] = [
                "date" => $label,
                "order_count" => (int)$row['order_count'],
                "total_sales" => (float)$row['total_sales'],
                "average_sale" => (float)$row['average_sale']
            ];
            
            $total_orders += (int)$row['order_count'];
            $total_sales += (float)$row['total_sales'];
        }
        
        // Calculate summary
        $report_data["summary"]["total_orders"] = $total_orders;
        $report_data["summary"]["total_sales"] = $total_sales;
        $report_data["summary"]["average_sale"] = $total_orders > 0 ? $total_sales / $total_orders : 0;
        
        // Set response code - 200 OK
        http_response_code(200);
        
        // Response data
        echo json_encode($report_data);
    }
    
    // Get orders report
    public function getOrdersReport($query_params) {
        // Initialize parameters
        $start_date = isset($query_params['start_date']) ? $query_params['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($query_params['end_date']) ? $query_params['end_date'] : date('Y-m-d');
        
        // Format dates for database query
        $start_date_obj = new \DateTime($start_date);
        $start_date_str = $start_date_obj->format('Y-m-d 00:00:00');
        
        $end_date_obj = new \DateTime($end_date);
        $end_date_str = $end_date_obj->format('Y-m-d 23:59:59');
        
        // Get orders by status
        $query = "SELECT status, COUNT(*) as count 
                  FROM orders 
                  WHERE created_at BETWEEN :start_date AND :end_date 
                  GROUP BY status";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $start_date_str);
        $stmt->bindParam(':end_date', $end_date_str);
        $stmt->execute();
        
        $status_counts = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status_counts[$row['status']] = (int)$row['count'];
        }
        
        // Get top selling products
        $query = "SELECT p.id, p.name, SUM(oi.quantity) as quantity_sold, SUM(oi.quantity * oi.price) as total_sales 
                  FROM order_items oi 
                  JOIN orders o ON oi.order_id = o.id 
                  JOIN products p ON oi.product_id = p.id 
                  WHERE o.created_at BETWEEN :start_date AND :end_date 
                  AND o.status != 'canceled' 
                  GROUP BY p.id, p.name 
                  ORDER BY quantity_sold DESC 
                  LIMIT 10";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $start_date_str);
        $stmt->bindParam(':end_date', $end_date_str);
        $stmt->execute();
        
        $top_products = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $top_products[] = [
                "id" => $row['id'],
                "name" => $row['name'],
                "quantity_sold" => (int)$row['quantity_sold'],
                "total_sales" => (float)$row['total_sales']
            ];
        }
        
        // Prepare response
        $report_data = [
            "parameters" => [
                "start_date" => $start_date,
                "end_date" => $end_date
            ],
            "order_status" => $status_counts,
            "top_selling_products" => $top_products
        ];
        
        // Set response code - 200 OK
        http_response_code(200);
        
        // Response data
        echo json_encode($report_data);
    }
    
    // Get customer report
    public function getCustomerReport($query_params) {
        // Initialize parameters
        $start_date = isset($query_params['start_date']) ? $query_params['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($query_params['end_date']) ? $query_params['end_date'] : date('Y-m-d');
        $limit = isset($query_params['limit']) ? (int)$query_params['limit'] : 10;
        
        // Format dates for database query
        $start_date_obj = new \DateTime($start_date);
        $start_date_str = $start_date_obj->format('Y-m-d 00:00:00');
        
        $end_date_obj = new \DateTime($end_date);
        $end_date_str = $end_date_obj->format('Y-m-d 23:59:59');
        
        // Get top customers by order count
        $query = "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email, 
                  COUNT(o.id) as order_count, SUM(o.total_price) as total_spent 
                  FROM users u 
                  JOIN orders o ON u.id = o.user_id 
                  WHERE o.created_at BETWEEN :start_date AND :end_date 
                  AND o.status != 'canceled' 
                  GROUP BY u.id, customer_name, u.email 
                  ORDER BY order_count DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $start_date_str);
        $stmt->bindParam(':end_date', $end_date_str);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $top_customers = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $top_customers[] = [
                "id" => $row['id'],
                "name" => $row['customer_name'],
                "email" => $row['email'],
                "order_count" => (int)$row['order_count'],
                "total_spent" => (float)$row['total_spent'],
                "average_order_value" => (float)$row['total_spent'] / (int)$row['order_count']
            ];
        }
        
        // Get new customers count by month
        $query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
                  FROM users 
                  WHERE role = 'customer' 
                  AND created_at BETWEEN :start_date AND :end_date 
                  GROUP BY month 
                  ORDER BY month ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $start_date_str);
        $stmt->bindParam(':end_date', $end_date_str);
        $stmt->execute();
        
        $new_customers_by_month = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $new_customers_by_month[$row['month']] = (int)$row['count'];
        }
        
        // Prepare response
        $report_data = [
            "parameters" => [
                "start_date" => $start_date,
                "end_date" => $end_date,
                "limit" => $limit
            ],
            "top_customers" => $top_customers,
            "new_customers_by_month" => $new_customers_by_month
        ];
        
        // Set response code - 200 OK
        http_response_code(200);
        
        // Response data
        echo json_encode($report_data);
    }
    
    // Helper methods to get statistics
    
    // Get total order count
    private function getOrderCount() {
        $query = "SELECT COUNT(*) as count FROM orders";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['count'];
    }
    
    // Get order count by date range
    private function getOrderCountByDateRange($start_date, $end_date) {
        $query = "SELECT COUNT(*) as count FROM orders WHERE created_at BETWEEN :start_date AND :end_date";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['count'];
    }
    
    // Get order status counts
    private function getOrderStatusCounts() {
        $query = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $result = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['status']] = (int)$row['count'];
        }
        
        return $result;
    }
    
    // Get total sales
    private function getTotalSales() {
        $query = "SELECT SUM(total_price) as total FROM orders WHERE status != 'canceled'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float)($row['total'] ? $row['total'] : 0);
    }
    
    // Get total sales by date range
    private function getTotalSalesByDateRange($start_date, $end_date) {
        return $this->order->getTotalSalesByDateRange($start_date, $end_date);
    }
    
    // Get product count
    private function getProductCount() {
        $query = "SELECT COUNT(*) as count FROM products";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['count'];
    }
    
    // Get low stock count
    private function getLowStockCount($threshold = 10) {
        return $this->product->getLowStockCount($threshold);
    }
    
    // Get customer count
    private function getCustomerCount() {
        $query = "SELECT COUNT(*) as count FROM users WHERE role = 'customer'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['count'];
    }
    
    // Get new customer count by date range
    private function getNewCustomerCountByDateRange($start_date, $end_date) {
        $query = "SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND created_at BETWEEN :start_date AND :end_date";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['count'];
    }
}
?> 