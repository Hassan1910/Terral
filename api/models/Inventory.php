<?php
class Inventory {
    // Database connection and table name
    private $conn;
    private $table_name = "products";
    
    // Object properties
    public $id;
    public $name;
    public $stock;
    public $low_stock_threshold = 10; // Default low stock threshold
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Check for low stock items
    public function getLowStockItems($limit = 10, $offset = 0) {
        // Query to get products with stock below threshold
        $query = "SELECT id, name, stock FROM " . $this->table_name . " 
                  WHERE stock <= :threshold 
                  ORDER BY stock ASC
                  LIMIT :limit OFFSET :offset";
                  
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':threshold', $this->low_stock_threshold, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Update product stock
    public function updateStock($id, $quantity) {
        // Query to update stock
        $query = "UPDATE " . $this->table_name . " 
                  SET stock = stock + :quantity 
                  WHERE id = :id";
                  
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize and bind parameters
        $quantity = htmlspecialchars(strip_tags($quantity));
        $id = htmlspecialchars(strip_tags($id));
        
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Check if product has sufficient stock
    public function hasStock($id, $quantity) {
        // Query to check stock
        $query = "SELECT stock FROM " . $this->table_name . " 
                  WHERE id = :id";
                  
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        // Get the stock value
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return true if sufficient stock
        if($row && $row['stock'] >= $quantity) {
            return true;
        }
        
        return false;
    }
    
    // Import products from CSV
    public function importFromCSV($file_path) {
        // Check if file exists
        if(!file_exists($file_path)) {
            return false;
        }
        
        // Open file
        $file = fopen($file_path, 'r');
        
        // Skip header row
        fgetcsv($file);
        
        // Start transaction
        $this->conn->beginTransaction();
        
        try {
            // Prepare query for product insertion/update
            $query = "INSERT INTO " . $this->table_name . " 
                     (name, description, price, stock, image, is_customizable, status) 
                     VALUES (:name, :description, :price, :stock, :image, :is_customizable, :status)
                     ON DUPLICATE KEY UPDATE 
                     description = VALUES(description),
                     price = VALUES(price),
                     stock = stock + VALUES(stock),
                     image = VALUES(image),
                     is_customizable = VALUES(is_customizable),
                     status = VALUES(status)";
                     
            $stmt = $this->conn->prepare($query);
            
            // Process each row
            while(($row = fgetcsv($file)) !== false) {
                // Ensure the row has enough columns
                if(count($row) >= 7) {
                    // Bind values
                    $stmt->bindValue(':name', htmlspecialchars(strip_tags($row[0])), PDO::PARAM_STR);
                    $stmt->bindValue(':description', htmlspecialchars(strip_tags($row[1])), PDO::PARAM_STR);
                    $stmt->bindValue(':price', htmlspecialchars(strip_tags($row[2])), PDO::PARAM_STR);
                    $stmt->bindValue(':stock', htmlspecialchars(strip_tags($row[3])), PDO::PARAM_INT);
                    $stmt->bindValue(':image', htmlspecialchars(strip_tags($row[4])), PDO::PARAM_STR);
                    $stmt->bindValue(':is_customizable', htmlspecialchars(strip_tags($row[5])), PDO::PARAM_INT);
                    $stmt->bindValue(':status', htmlspecialchars(strip_tags($row[6])), PDO::PARAM_STR);
                    
                    // Execute statement
                    $stmt->execute();
                }
            }
            
            // Commit transaction
            $this->conn->commit();
            fclose($file);
            return true;
        } catch(Exception $e) {
            // Rollback in case of error
            $this->conn->rollBack();
            fclose($file);
            return false;
        }
    }
    
    // Get count of low stock items
    public function getLowStockCount() {
        // Query to count products with stock below threshold
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE stock <= :threshold";
                  
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameter
        $stmt->bindParam(':threshold', $this->low_stock_threshold, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        // Get count
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['count'];
    }
}
?> 