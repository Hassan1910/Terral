<?php
class Order {
    private $conn;
    private $table_name = "orders";
    private $order_items_table = "order_items";
    private $users_table = "users";
    private $products_table = "products";
    
    // Order properties
    public $id;
    public $user_id;
    public $total_price;
    public $status;
    public $payment_status;
    public $payment_method;
    public $payment_id;
    public $shipping_address;
    public $shipping_city;
    public $shipping_state;
    public $shipping_postal_code;
    public $shipping_country;
    public $shipping_phone;
    public $notes;
    public $created_at;
    public $updated_at;
    public $items = [];
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Read all orders
    public function read($limit = 10, $offset = 0, $status = null, $user_id = null) {
        // Base query
        $query = "SELECT o.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                    u.email as customer_email  
                  FROM " . $this->table_name . " o
                  LEFT JOIN " . $this->users_table . " u ON o.user_id = u.id
                  WHERE 1=1 ";
        
        // Add filters if provided
        if($status) {
            $query .= "AND o.status = :status ";
        }
        
        if($user_id) {
            $query .= "AND o.user_id = :user_id ";
        }
        
        // Add ordering and limits
        $query .= "ORDER BY o.created_at DESC 
                   LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        if($status) {
            $stmt->bindParam(':status', $status);
        }
        
        if($user_id) {
            $stmt->bindParam(':user_id', $user_id);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get total count of orders
    public function getCount($status = null, $user_id = null) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE 1=1 ";
        
        // Add filters if provided
        if($status) {
            $query .= "AND status = :status ";
        }
        
        if($user_id) {
            $query .= "AND user_id = :user_id ";
        }
        
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        if($status) {
            $stmt->bindParam(':status', $status);
        }
        
        if($user_id) {
            $stmt->bindParam(':user_id', $user_id);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
    
    // Read single order with items
    public function readOne() {
        // First get order details
        $query = "SELECT o.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                    u.email as customer_email  
                  FROM " . $this->table_name . " o
                  LEFT JOIN " . $this->users_table . " u ON o.user_id = u.id
                  WHERE o.id = :id 
                  LIMIT 0,1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind ID parameter
        $stmt->bindParam(':id', $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Fetch row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if order exists
        if($row) {
            // Set properties
            $this->user_id = $row['user_id'];
            $this->total_price = $row['total_price'];
            $this->status = $row['status'];
            $this->payment_status = $row['payment_status'];
            $this->payment_method = $row['payment_method'];
            $this->payment_id = $row['payment_id'];
            $this->shipping_address = $row['shipping_address'];
            $this->shipping_city = $row['shipping_city'];
            $this->shipping_state = $row['shipping_state'];
            $this->shipping_postal_code = $row['shipping_postal_code'];
            $this->shipping_country = $row['shipping_country'];
            $this->shipping_phone = $row['shipping_phone'];
            $this->notes = $row['notes'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            // Get order items
            $this->items = $this->getOrderItems();
            
            return true;
        }
        
        return false;
    }
    
    // Get order items
    private function getOrderItems() {
        $query = "SELECT oi.*, p.name as product_name, p.image as product_image 
                  FROM " . $this->order_items_table . " oi
                  LEFT JOIN " . $this->products_table . " p ON oi.product_id = p.id
                  WHERE oi.order_id = :order_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $this->id);
        $stmt->execute();
        
        $items = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = [
                'id' => $row['id'],
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'product_image' => $row['product_image'],
                'quantity' => $row['quantity'],
                'price' => $row['price'],
                'customization_image' => $row['customization_image'],
                'customization_text' => $row['customization_text']
            ];
        }
        
        return $items;
    }
    
    // Create order
    public function create() {
        // Start transaction
        $this->conn->beginTransaction();
        
        try {
            // Insert query
            $query = "INSERT INTO " . $this->table_name . " 
                      SET 
                        user_id = :user_id, 
                        total_price = :total_price, 
                        status = :status, 
                        payment_status = :payment_status, 
                        payment_method = :payment_method, 
                        payment_id = :payment_id, 
                        shipping_address = :shipping_address, 
                        shipping_city = :shipping_city, 
                        shipping_state = :shipping_state, 
                        shipping_postal_code = :shipping_postal_code, 
                        shipping_country = :shipping_country, 
                        shipping_phone = :shipping_phone, 
                        notes = :notes, 
                        created_at = NOW(), 
                        updated_at = NOW()";
            
            // Prepare statement
            $stmt = $this->conn->prepare($query);
            
            // Sanitize inputs
            $this->user_id = htmlspecialchars(strip_tags($this->user_id));
            $this->total_price = htmlspecialchars(strip_tags($this->total_price));
            $this->status = htmlspecialchars(strip_tags($this->status));
            $this->payment_status = htmlspecialchars(strip_tags($this->payment_status));
            $this->payment_method = htmlspecialchars(strip_tags($this->payment_method));
            $this->payment_id = htmlspecialchars(strip_tags($this->payment_id));
            $this->shipping_address = htmlspecialchars(strip_tags($this->shipping_address));
            $this->shipping_city = htmlspecialchars(strip_tags($this->shipping_city));
            $this->shipping_state = htmlspecialchars(strip_tags($this->shipping_state));
            $this->shipping_postal_code = htmlspecialchars(strip_tags($this->shipping_postal_code));
            $this->shipping_country = htmlspecialchars(strip_tags($this->shipping_country));
            $this->shipping_phone = htmlspecialchars(strip_tags($this->shipping_phone));
            $this->notes = htmlspecialchars(strip_tags($this->notes));
            
            // Bind parameters
            $stmt->bindParam(':user_id', $this->user_id);
            $stmt->bindParam(':total_price', $this->total_price);
            $stmt->bindParam(':status', $this->status);
            $stmt->bindParam(':payment_status', $this->payment_status);
            $stmt->bindParam(':payment_method', $this->payment_method);
            $stmt->bindParam(':payment_id', $this->payment_id);
            $stmt->bindParam(':shipping_address', $this->shipping_address);
            $stmt->bindParam(':shipping_city', $this->shipping_city);
            $stmt->bindParam(':shipping_state', $this->shipping_state);
            $stmt->bindParam(':shipping_postal_code', $this->shipping_postal_code);
            $stmt->bindParam(':shipping_country', $this->shipping_country);
            $stmt->bindParam(':shipping_phone', $this->shipping_phone);
            $stmt->bindParam(':notes', $this->notes);
            
            // Execute query
            if(!$stmt->execute()) {
                throw new Exception("Failed to create order.");
            }
            
            // Get order ID
            $this->id = $this->conn->lastInsertId();
            
            // Insert order items
            if(!empty($this->items)) {
                foreach($this->items as $item) {
                    // Check if product exists and has enough stock
                    $product_query = "SELECT stock FROM " . $this->products_table . " WHERE id = :id";
                    $product_stmt = $this->conn->prepare($product_query);
                    $product_stmt->bindParam(':id', $item['product_id']);
                    $product_stmt->execute();
                    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if(!$product) {
                        throw new Exception("Product with ID " . $item['product_id'] . " not found.");
                    }
                    
                    if($product['stock'] < $item['quantity']) {
                        throw new Exception("Not enough stock for product ID " . $item['product_id'] . ".");
                    }
                    
                    // Update product stock
                    $new_stock = $product['stock'] - $item['quantity'];
                    $update_stock_query = "UPDATE " . $this->products_table . " SET stock = :stock WHERE id = :id";
                    $update_stock_stmt = $this->conn->prepare($update_stock_query);
                    $update_stock_stmt->bindParam(':stock', $new_stock);
                    $update_stock_stmt->bindParam(':id', $item['product_id']);
                    
                    if(!$update_stock_stmt->execute()) {
                        throw new Exception("Failed to update product stock.");
                    }
                    
                    // Insert order item
                    $item_query = "INSERT INTO " . $this->order_items_table . " 
                                  SET 
                                    order_id = :order_id, 
                                    product_id = :product_id, 
                                    quantity = :quantity, 
                                    price = :price, 
                                    customization_image = :customization_image, 
                                    customization_text = :customization_text";
                    
                    $item_stmt = $this->conn->prepare($item_query);
                    
                    // Sanitize inputs
                    $item['product_id'] = htmlspecialchars(strip_tags($item['product_id']));
                    $item['quantity'] = htmlspecialchars(strip_tags($item['quantity']));
                    $item['price'] = htmlspecialchars(strip_tags($item['price']));
                    $item['customization_image'] = isset($item['customization_image']) ? htmlspecialchars(strip_tags($item['customization_image'])) : '';
                    $item['customization_text'] = isset($item['customization_text']) ? htmlspecialchars(strip_tags($item['customization_text'])) : '';
                    
                    // Bind parameters
                    $item_stmt->bindParam(':order_id', $this->id);
                    $item_stmt->bindParam(':product_id', $item['product_id']);
                    $item_stmt->bindParam(':quantity', $item['quantity']);
                    $item_stmt->bindParam(':price', $item['price']);
                    $item_stmt->bindParam(':customization_image', $item['customization_image']);
                    $item_stmt->bindParam(':customization_text', $item['customization_text']);
                    
                    if(!$item_stmt->execute()) {
                        throw new Exception("Failed to add order item.");
                    }
                }
            }
            
            // Commit transaction
            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            printf("Error: %s.\n", $e->getMessage());
            return false;
        }
    }
    
    // Update order
    public function update() {
        // Update query
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                    status = :status, 
                    payment_status = :payment_status, 
                    payment_method = :payment_method, 
                    payment_id = :payment_id, 
                    shipping_address = :shipping_address, 
                    shipping_city = :shipping_city, 
                    shipping_state = :shipping_state, 
                    shipping_postal_code = :shipping_postal_code, 
                    shipping_country = :shipping_country, 
                    shipping_phone = :shipping_phone, 
                    notes = :notes, 
                    updated_at = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->payment_status = htmlspecialchars(strip_tags($this->payment_status));
        $this->payment_method = htmlspecialchars(strip_tags($this->payment_method));
        $this->payment_id = htmlspecialchars(strip_tags($this->payment_id));
        $this->shipping_address = htmlspecialchars(strip_tags($this->shipping_address));
        $this->shipping_city = htmlspecialchars(strip_tags($this->shipping_city));
        $this->shipping_state = htmlspecialchars(strip_tags($this->shipping_state));
        $this->shipping_postal_code = htmlspecialchars(strip_tags($this->shipping_postal_code));
        $this->shipping_country = htmlspecialchars(strip_tags($this->shipping_country));
        $this->shipping_phone = htmlspecialchars(strip_tags($this->shipping_phone));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        
        // Bind parameters
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':payment_status', $this->payment_status);
        $stmt->bindParam(':payment_method', $this->payment_method);
        $stmt->bindParam(':payment_id', $this->payment_id);
        $stmt->bindParam(':shipping_address', $this->shipping_address);
        $stmt->bindParam(':shipping_city', $this->shipping_city);
        $stmt->bindParam(':shipping_state', $this->shipping_state);
        $stmt->bindParam(':shipping_postal_code', $this->shipping_postal_code);
        $stmt->bindParam(':shipping_country', $this->shipping_country);
        $stmt->bindParam(':shipping_phone', $this->shipping_phone);
        $stmt->bindParam(':notes', $this->notes);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        // Print error if something goes wrong
        printf("Error: %s.\n", $stmt->error);
        
        return false;
    }
    
    // Update order status
    public function updateStatus() {
        // Update query
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                    status = :status, 
                    updated_at = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->status = htmlspecialchars(strip_tags($this->status));
        
        // Bind parameters
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':status', $this->status);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        // Print error if something goes wrong
        printf("Error: %s.\n", $stmt->error);
        
        return false;
    }
    
    // Update payment status
    public function updatePaymentStatus() {
        // Update query
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                    payment_status = :payment_status, 
                    payment_id = :payment_id, 
                    updated_at = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->payment_status = htmlspecialchars(strip_tags($this->payment_status));
        $this->payment_id = htmlspecialchars(strip_tags($this->payment_id));
        
        // Bind parameters
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':payment_status', $this->payment_status);
        $stmt->bindParam(':payment_id', $this->payment_id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        // Print error if something goes wrong
        printf("Error: %s.\n", $stmt->error);
        
        return false;
    }
    
    // Cancel order
    public function cancel() {
        // Start transaction
        $this->conn->beginTransaction();
        
        try {
            // First check if order can be canceled
            if($this->status !== 'pending' && $this->status !== 'processing') {
                throw new Exception("Only orders with 'pending' or 'processing' status can be canceled.");
            }
            
            // Get order items to restore stock
            $query = "SELECT product_id, quantity FROM " . $this->order_items_table . " WHERE order_id = :order_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':order_id', $this->id);
            $stmt->execute();
            
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Restore stock for each product
                $product_id = $row['product_id'];
                $quantity = $row['quantity'];
                
                $update_stock_query = "UPDATE " . $this->products_table . " 
                                       SET stock = stock + :quantity 
                                       WHERE id = :product_id";
                $update_stock_stmt = $this->conn->prepare($update_stock_query);
                $update_stock_stmt->bindParam(':quantity', $quantity);
                $update_stock_stmt->bindParam(':product_id', $product_id);
                
                if(!$update_stock_stmt->execute()) {
                    throw new Exception("Failed to restore product stock.");
                }
            }
            
            // Update order status to canceled
            $this->status = 'canceled';
            
            if(!$this->updateStatus()) {
                throw new Exception("Failed to update order status.");
            }
            
            // Commit transaction
            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            printf("Error: %s.\n", $e->getMessage());
            return false;
        }
    }
    
    // Delete order
    public function delete() {
        // Start transaction
        $this->conn->beginTransaction();
        
        try {
            // First delete order items
            $query = "DELETE FROM " . $this->order_items_table . " WHERE order_id = :order_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':order_id', $this->id);
            
            if(!$stmt->execute()) {
                throw new Exception("Failed to delete order items.");
            }
            
            // Then delete order
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id);
            
            if(!$stmt->execute()) {
                throw new Exception("Failed to delete order.");
            }
            
            // Commit transaction
            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            printf("Error: %s.\n", $e->getMessage());
            return false;
        }
    }
    
    // Get orders by date range
    public function getOrdersByDateRange($start_date, $end_date, $limit = 10, $offset = 0) {
        $query = "SELECT o.*, 
                    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                    u.email as customer_email  
                  FROM " . $this->table_name . " o
                  LEFT JOIN " . $this->users_table . " u ON o.user_id = u.id
                  WHERE o.created_at BETWEEN :start_date AND :end_date
                  ORDER BY o.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get count of orders by date range
    public function getCountByDateRange($start_date, $end_date) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " 
                  WHERE created_at BETWEEN :start_date AND :end_date";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'];
    }
    
    // Get total sales by date range
    public function getTotalSalesByDateRange($start_date, $end_date) {
        $query = "SELECT SUM(total_price) as total FROM " . $this->table_name . " 
                  WHERE created_at BETWEEN :start_date AND :end_date
                  AND status != 'canceled'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'] ? $row['total'] : 0;
    }
    
    // Get order count by status
    public function getCountByStatus() {
        $query = "SELECT status, COUNT(*) as count 
                  FROM " . $this->table_name . " 
                  GROUP BY status";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $result = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['status']] = $row['count'];
        }
        
        return $result;
    }
}
?> 