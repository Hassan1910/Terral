<?php
class Report {
    // Database connection
    private $conn;
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Get sales summary
    public function getSalesSummary($period = 'month') {
        $query = "";
        $groupBy = "";
        $dateFormat = "";
        
        // Set time period for query
        switch($period) {
            case 'day':
                $dateFormat = "%Y-%m-%d";
                $groupBy = "DATE(o.created_at)";
                break;
            case 'week':
                $dateFormat = "%x-W%v"; // Year-Week format
                $groupBy = "YEARWEEK(o.created_at)";
                break;
            case 'month':
                $dateFormat = "%Y-%m";
                $groupBy = "YEAR(o.created_at), MONTH(o.created_at)";
                break;
            case 'year':
                $dateFormat = "%Y";
                $groupBy = "YEAR(o.created_at)";
                break;
            default:
                $dateFormat = "%Y-%m";
                $groupBy = "YEAR(o.created_at), MONTH(o.created_at)";
        }
        
        // Query to get sales summary
        $query = "SELECT 
                DATE_FORMAT(o.created_at, '{$dateFormat}') as period,
                COUNT(o.id) as order_count,
                SUM(o.total_price) as total_sales,
                AVG(o.total_price) as average_order_value
            FROM 
                orders o
            WHERE 
                o.status != 'canceled' 
                AND o.payment_status = 'completed'
            GROUP BY 
                {$groupBy}
            ORDER BY 
                o.created_at DESC";
                
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get top selling products
    public function getTopSellingProducts($limit = 10, $period = null) {
        $dateCondition = "";
        
        // Add date condition if period is specified
        if($period) {
            $dateCondition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 {$period})";
        }
        
        // Query to get top selling products
        $query = "SELECT 
                p.id,
                p.name,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.price * oi.quantity) as total_revenue
            FROM 
                order_items oi
            JOIN 
                orders o ON oi.order_id = o.id
            JOIN 
                products p ON oi.product_id = p.id
            WHERE 
                o.status != 'canceled' 
                AND o.payment_status = 'completed'
                {$dateCondition}
            GROUP BY 
                p.id
            ORDER BY 
                total_quantity DESC
            LIMIT :limit";
                
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get customer insights
    public function getCustomerInsights($limit = 10) {
        // Query to get customer insights
        $query = "SELECT 
                u.id,
                CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                COUNT(o.id) as order_count,
                SUM(o.total_price) as total_spent,
                MAX(o.created_at) as last_order_date
            FROM 
                users u
            LEFT JOIN 
                orders o ON u.id = o.user_id
            WHERE 
                u.role = 'customer'
                AND o.status != 'canceled' 
                AND o.payment_status = 'completed'
            GROUP BY 
                u.id
            ORDER BY 
                total_spent DESC
            LIMIT :limit";
                
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get revenue by category
    public function getRevenueByCategory($period = null) {
        $dateCondition = "";
        
        // Add date condition if period is specified
        if($period) {
            $dateCondition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 {$period})";
        }
        
        // Query to get revenue by category
        $query = "SELECT 
                c.id,
                c.name as category_name,
                COUNT(DISTINCT o.id) as order_count,
                SUM(oi.price * oi.quantity) as total_revenue
            FROM 
                order_items oi
            JOIN 
                orders o ON oi.order_id = o.id
            JOIN 
                products p ON oi.product_id = p.id
            JOIN 
                product_categories pc ON p.id = pc.product_id
            JOIN 
                categories c ON pc.category_id = c.id
            WHERE 
                o.status != 'canceled' 
                AND o.payment_status = 'completed'
                {$dateCondition}
            GROUP BY 
                c.id
            ORDER BY 
                total_revenue DESC";
                
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get order status distribution
    public function getOrderStatusDistribution() {
        // Query to get order status distribution
        $query = "SELECT 
                status,
                COUNT(*) as count
            FROM 
                orders
            GROUP BY 
                status
            ORDER BY 
                count DESC";
                
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get daily sales for the last 30 days
    public function getDailySales($days = 30) {
        // Query to get daily sales
        $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as order_count,
                SUM(total_price) as total_sales
            FROM 
                orders
            WHERE 
                status != 'canceled' 
                AND payment_status = 'completed'
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY 
                DATE(created_at)
            ORDER BY 
                date ASC";
                
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
}
?> 