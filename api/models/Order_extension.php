<?php
// Include database and Order class
require_once __DIR__ . '/../../api/config/Database.php';
require_once __DIR__ . '/../../api/models/Order.php';

// Add the missing method to the Order class
if (!method_exists('Order', 'getRecentOrders')) {
    // Define the method on the Order class using runkit if available
    // But since we can't do that easily, let's create a temporary solution
    
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    // Create the function in the database to be called directly from dashboard.php
    $query = "CREATE OR REPLACE FUNCTION get_recent_orders(limit_val INT) 
              RETURNS TEXT
              DETERMINISTIC
              BEGIN
                  DECLARE result TEXT;
                  
                  SET result = '';
                  
                  SELECT GROUP_CONCAT(
                      JSON_OBJECT(
                          'id', o.id,
                          'customer_name', CONCAT(u.first_name, ' ', u.last_name),
                          'total_amount', o.total_price,
                          'status', o.status,
                          'created_at', o.created_at
                      )
                  ) 
                  INTO result
                  FROM orders o
                  LEFT JOIN users u ON o.user_id = u.id
                  ORDER BY o.created_at DESC
                  LIMIT limit_val;
                  
                  RETURN result;
              END;";
              
    // Execute the function creation
    try {
        $stmt = $db->prepare($query);
        $stmt->execute();
        echo "Function created successfully!";
    } catch (PDOException $e) {
        echo "Error creating function: " . $e->getMessage();
    }
}

// Now create a simple PHP function to replace the missing method
function get_recent_orders($db, $limit = 5) {
    try {
        $query = "SELECT o.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                    u.email as customer_email  
                  FROM orders o
                  LEFT JOIN users u ON o.user_id = u.id
                  ORDER BY o.created_at DESC 
                  LIMIT :limit";
        
        // Prepare statement
        $stmt = $db->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        $orders = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $orders[] = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'customer_name' => $row['customer_name'],
                'total_amount' => $row['total_price'],
                'status' => $row['status'],
                'payment_status' => $row['payment_status'],
                'created_at' => $row['created_at']
            ];
        }
        
        return $orders;
    } catch (Exception $e) {
        return [];
    }
}

echo "Extension loaded successfully!";
?> 