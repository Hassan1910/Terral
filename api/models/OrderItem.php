<?php
class OrderItem {
    // Database connection and table name
    private $conn;
    private $table_name = "order_items";
    
    // Object properties
    public $id;
    public $order_id;
    public $product_id;
    public $product_name;
    public $price;
    public $quantity;
    public $subtotal;
    public $customization_image;
    public $customization_text;
    public $created_at;
    
    // Constructor with DB
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create order item
    public function create() {
        // Sanitize inputs
        $this->order_id = htmlspecialchars(strip_tags($this->order_id));
        $this->product_id = htmlspecialchars(strip_tags($this->product_id));
        $this->product_name = htmlspecialchars(strip_tags($this->product_name));
        $this->price = htmlspecialchars(strip_tags($this->price));
        $this->quantity = htmlspecialchars(strip_tags($this->quantity));
        $this->customization_text = $this->customization_text ? htmlspecialchars(strip_tags($this->customization_text)) : null;
        $this->customization_image = $this->customization_image ? htmlspecialchars(strip_tags($this->customization_image)) : null;
        
        // Create query
        $query = "INSERT INTO " . $this->table_name . "
                  (order_id, product_id, product_name, quantity, price, customization_image, customization_text, created_at)
                  VALUES
                  (:order_id, :product_id, :product_name, :quantity, :price, :customization_image, :customization_text, NOW())";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(':order_id', $this->order_id);
        $stmt->bindParam(':product_id', $this->product_id);
        $stmt->bindParam(':product_name', $this->product_name);
        $stmt->bindParam(':quantity', $this->quantity);
        $stmt->bindParam(':price', $this->price);
        $stmt->bindParam(':customization_image', $this->customization_image);
        $stmt->bindParam(':customization_text', $this->customization_text);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Read order items by order ID
    public function readByOrderId($order_id) {
        // Create query
        $query = "SELECT oi.*, p.name as product_name 
                  FROM " . $this->table_name . " oi
                  LEFT JOIN products p ON oi.product_id = p.id
                  WHERE oi.order_id = ? 
                  ORDER BY oi.id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind ID
        $stmt->bindParam(1, $order_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get single order item
    public function readOne() {
        // Create query
        $query = "SELECT oi.*, p.name as product_name 
                  FROM " . $this->table_name . " oi
                  LEFT JOIN products p ON oi.product_id = p.id
                  WHERE oi.id = ? 
                  LIMIT 0,1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind ID
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Get record
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            // Set properties
            $this->id = $row['id'];
            $this->order_id = $row['order_id'];
            $this->product_id = $row['product_id'];
            $this->product_name = $row['product_name'];
            $this->price = $row['price'];
            $this->quantity = $row['quantity'];
            $this->subtotal = $row['price'] * $row['quantity'];
            $this->customization_image = $row['customization_image'];
            $this->customization_text = $row['customization_text'];
            
            return true;
        }
        
        return false;
    }
    
    // Update order item
    public function update() {
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->order_id = htmlspecialchars(strip_tags($this->order_id));
        $this->product_id = htmlspecialchars(strip_tags($this->product_id));
        $this->price = htmlspecialchars(strip_tags($this->price));
        $this->quantity = htmlspecialchars(strip_tags($this->quantity));
        $this->customization_text = $this->customization_text ? htmlspecialchars(strip_tags($this->customization_text)) : null;
        $this->customization_image = $this->customization_image ? htmlspecialchars(strip_tags($this->customization_image)) : null;
        
        // Create query
        $query = "UPDATE " . $this->table_name . "
                  SET order_id = :order_id,
                      product_id = :product_id,
                      price = :price,
                      quantity = :quantity,
                      customization_image = :customization_image,
                      customization_text = :customization_text
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':order_id', $this->order_id);
        $stmt->bindParam(':product_id', $this->product_id);
        $stmt->bindParam(':price', $this->price);
        $stmt->bindParam(':quantity', $this->quantity);
        $stmt->bindParam(':customization_image', $this->customization_image);
        $stmt->bindParam(':customization_text', $this->customization_text);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Delete order item
    public function delete() {
        // Create query
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Bind ID
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Delete all items for an order
    public function deleteByOrderId($order_id) {
        // Create query
        $query = "DELETE FROM " . $this->table_name . " WHERE order_id = ?";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $order_id = htmlspecialchars(strip_tags($order_id));
        
        // Bind ID
        $stmt->bindParam(1, $order_id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Helper function to convert customization data from JSON format
    public function setCustomizationFromJson($json_data) {
        if (empty($json_data)) {
            return false;
        }
        
        $customization = json_decode($json_data, true);
        if (!is_array($customization)) {
            return false;
        }
        
        // Set properties from customization data
        if (isset($customization['text'])) {
            $this->customization_text = $customization['text'];
        }
        
        if (isset($customization['image'])) {
            $this->customization_image = $customization['image'];
        }
        
        return true;
    }
    
    // Helper function to get customization as JSON for the API
    public function getCustomizationAsJson() {
        $customization = array();
        
        if (!empty($this->customization_text)) {
            $customization['text'] = $this->customization_text;
        }
        
        if (!empty($this->customization_image)) {
            $customization['image'] = $this->customization_image;
        }
        
        return !empty($customization) ? json_encode($customization) : null;
    }
}
?> 