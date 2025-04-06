<?php
class Product {
    private $conn;
    private $table_name = "products";
    private $category_table = "categories";
    private $product_category_table = "product_categories";
    
    // Product properties
    public $id;
    public $name;
    public $description;
    public $price;
    public $stock;
    public $category_id;
    public $image;
    public $is_customizable;
    public $status;
    public $created_at;
    public $updated_at;
    public $categories = [];
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Read all products
    public function read($limit = 10, $offset = 0, $category_id = null) {
        // Base query
        $query = "SELECT p.*, 
                    (SELECT GROUP_CONCAT(c.name) 
                     FROM " . $this->category_table . " c 
                     JOIN " . $this->product_category_table . " pc ON c.id = pc.category_id 
                     WHERE pc.product_id = p.id) as categories 
                  FROM " . $this->table_name . " p ";
        
        // Add category filter if provided
        if($category_id) {
            $query .= "JOIN " . $this->product_category_table . " pc ON p.id = pc.product_id 
                       WHERE pc.category_id = :category_id ";
        }
        
        // Add ordering and limits
        $query .= "ORDER BY p.created_at DESC 
                   LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        if($category_id) {
            $stmt->bindParam(':category_id', $category_id);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Read products with pagination, search and category filter
    public function readPaginated($limit = 10, $offset = 0, $category_id = null, $search = null) {
        // Base query - Join with categories to get category name
        $query = "SELECT p.*, c.name as category_name 
                  FROM " . $this->table_name . " p
                  LEFT JOIN " . $this->category_table . " c ON p.category_id = c.id
                  WHERE 1=1 ";
        
        // Add category filter if provided
        if($category_id) {
            $query .= "AND p.category_id = :category_id ";
        }
        
        // Add search filter if provided
        if($search) {
            $query .= "AND (p.name LIKE :search OR p.description LIKE :search) ";
        }
        
        // Add ordering and limits
        $query .= "ORDER BY p.created_at DESC 
                   LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        if($category_id) {
            $stmt->bindParam(':category_id', $category_id);
        }
        
        if($search) {
            $search_term = "%{$search}%";
            $stmt->bindParam(':search', $search_term);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get total count of products
    public function getCount($category_id = null) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        
        // Add category filter if provided
        if($category_id) {
            $query = "SELECT COUNT(DISTINCT p.id) as total 
                      FROM " . $this->table_name . " p 
                      JOIN " . $this->product_category_table . " pc ON p.id = pc.product_id 
                      WHERE pc.category_id = :category_id";
        }
        
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        if($category_id) {
            $stmt->bindParam(':category_id', $category_id);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
    
    // Read single product
    public function readOne() {
        // Query to read single record with categories
        $query = "SELECT p.*, 
                    (SELECT GROUP_CONCAT(c.name) 
                     FROM " . $this->category_table . " c 
                     JOIN " . $this->product_category_table . " pc ON c.id = pc.category_id 
                     WHERE pc.product_id = p.id) as categories 
                  FROM " . $this->table_name . " p 
                  WHERE p.id = :id 
                  LIMIT 0,1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind ID parameter
        $stmt->bindParam(':id', $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Fetch row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if product exists
        if($row) {
            // Set properties
            $this->name = $row['name'];
            $this->description = $row['description'];
            $this->price = $row['price'];
            $this->stock = $row['stock'];
            $this->image = $row['image'];
            $this->is_customizable = $row['is_customizable'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            // Set categories
            if($row['categories']) {
                $this->categories = explode(',', $row['categories']);
            }
            
            return true;
        }
        
        return false;
    }
    
    // Create product
    public function create() {
        // Insert query
        $query = "INSERT INTO " . $this->table_name . " 
                  SET 
                    name = :name, 
                    description = :description, 
                    price = :price, 
                    stock = :stock, 
                    image = :image, 
                    is_customizable = :is_customizable, 
                    status = :status, 
                    created_at = NOW(), 
                    updated_at = NOW()";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->price = htmlspecialchars(strip_tags($this->price));
        $this->stock = htmlspecialchars(strip_tags($this->stock));
        $this->image = htmlspecialchars(strip_tags($this->image));
        $this->is_customizable = htmlspecialchars(strip_tags($this->is_customizable));
        $this->status = htmlspecialchars(strip_tags($this->status));
        
        // Bind parameters
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':price', $this->price);
        $stmt->bindParam(':stock', $this->stock);
        $stmt->bindParam(':image', $this->image);
        $stmt->bindParam(':is_customizable', $this->is_customizable);
        $stmt->bindParam(':status', $this->status);
        
        // Execute query
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            
            // Add categories if provided
            if(!empty($this->categories)) {
                $this->addCategories($this->categories);
            }
            
            return true;
        }
        
        // Print error if something goes wrong
        printf("Error: %s.\n", $stmt->error);
        
        return false;
    }
    
    // Update product
    public function update() {
        // Update query
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                    name = :name, 
                    description = :description,
                    category_id = :category_id, 
                    price = :price, 
                    stock = :stock, 
                    is_customizable = :is_customizable, 
                    status = :status";
        
        // Check if image is included in the update
        if(!empty($this->image)) {
            $query .= ", image = :image";
        }
        
        $query .= ", updated_at = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->category_id = htmlspecialchars(strip_tags($this->category_id));
        $this->price = htmlspecialchars(strip_tags($this->price));
        $this->stock = htmlspecialchars(strip_tags($this->stock));
        $this->is_customizable = htmlspecialchars(strip_tags($this->is_customizable));
        $this->status = htmlspecialchars(strip_tags($this->status));
        
        // Bind parameters
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':category_id', $this->category_id);
        $stmt->bindParam(':price', $this->price);
        $stmt->bindParam(':stock', $this->stock);
        $stmt->bindParam(':is_customizable', $this->is_customizable);
        $stmt->bindParam(':status', $this->status);
        
        // Bind image parameter if included
        if(!empty($this->image)) {
            $this->image = htmlspecialchars(strip_tags($this->image));
            $stmt->bindParam(':image', $this->image);
        }
        
        // Execute query
        if($stmt->execute()) {
            // Update categories if provided
            if(!empty($this->categories)) {
                // Delete existing categories
                $this->deleteProductCategories();
                // Add new categories
                $this->addCategories($this->categories);
            }
            
            return true;
        }
        
        // Print error if something goes wrong
        printf("Error: %s.\n", $stmt->error);
        
        return false;
    }
    
    // Delete product
    public function delete($force = false) {
        try {
            // Start transaction
            $this->conn->beginTransaction();
            
            // Check if product has any order items
            $check_query = "SELECT COUNT(*) as count FROM order_items WHERE product_id = :id";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(':id', $this->id);
            $check_stmt->execute();
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0 && !$force) {
                // If product has orders and force delete is not enabled, perform soft delete
                $update_query = "UPDATE " . $this->table_name . " 
                               SET status = 'deleted', updated_at = NOW() 
                               WHERE id = :id";
                $update_stmt = $this->conn->prepare($update_query);
                $update_stmt->bindParam(':id', $this->id);
                $success = $update_stmt->execute();
            } else {
                // If no orders exist or force delete is enabled
                // Delete categories associated with product
                $this->deleteProductCategories();
                
                if ($force) {
                    // If force delete, remove order items first
                    $delete_orders = "DELETE FROM order_items WHERE product_id = :id";
                    $delete_orders_stmt = $this->conn->prepare($delete_orders);
                    $delete_orders_stmt->bindParam(':id', $this->id);
                    $delete_orders_stmt->execute();
                }
                
                // Delete the product
                $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $this->id);
                $success = $stmt->execute();
            }
            
            if ($success) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollBack();
                return false;
            }
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    // Update stock
    public function updateStock() {
        // Update query
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                    stock = :stock, 
                    updated_at = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->stock = htmlspecialchars(strip_tags($this->stock));
        
        // Bind parameters
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':stock', $this->stock);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        // Print error if something goes wrong
        printf("Error: %s.\n", $stmt->error);
        
        return false;
    }
    
    // Get low stock products
    public function getLowStockProducts($threshold = 10, $limit = 10, $offset = 0) {
        // Select query
        $query = "SELECT p.*, 
                    (SELECT GROUP_CONCAT(c.name) 
                     FROM " . $this->category_table . " c 
                     JOIN " . $this->product_category_table . " pc ON c.id = pc.category_id 
                     WHERE pc.product_id = p.id) as categories 
                  FROM " . $this->table_name . " p 
                  WHERE p.stock <= :threshold 
                  ORDER BY p.stock ASC 
                  LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':threshold', $threshold, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get count of low stock products
    public function getLowStockCount($threshold = 10) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE stock <= :threshold";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':threshold', $threshold, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
    
    // Search products
    public function search($search_term, $limit = 10, $offset = 0) {
        // Select query
        $query = "SELECT p.*, 
                    (SELECT GROUP_CONCAT(c.name) 
                     FROM " . $this->category_table . " c 
                     JOIN " . $this->product_category_table . " pc ON c.id = pc.category_id 
                     WHERE pc.product_id = p.id) as categories 
                  FROM " . $this->table_name . " p 
                  WHERE 
                    p.name LIKE :search_term OR 
                    p.description LIKE :search_term
                  ORDER BY p.created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $search_term = htmlspecialchars(strip_tags($search_term));
        $search_term = "%{$search_term}%";
        
        // Bind parameters
        $stmt->bindParam(':search_term', $search_term);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Add categories to product
    private function addCategories($categories) {
        // Check if categories is an array
        if(!is_array($categories)) {
            $categories = [$categories];
        }
        
        // Insert query
        $query = "INSERT INTO " . $this->product_category_table . " (product_id, category_id) VALUES ";
        
        $values = [];
        $params = [];
        
        // Generate placeholders for each category
        foreach($categories as $i => $category_id) {
            $param_name = ":category_id" . $i;
            $values[] = "(:product_id, " . $param_name . ")";
            $params[$param_name] = $category_id;
        }
        
        $query .= implode(", ", $values);
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind product_id parameter
        $stmt->bindParam(':product_id', $this->id);
        
        // Bind category_id parameters
        foreach($params as $param_name => $value) {
            $stmt->bindValue($param_name, $value);
        }
        
        // Execute query
        return $stmt->execute();
    }
    
    // Delete all categories associated with a product
    private function deleteProductCategories() {
        $query = "DELETE FROM " . $this->product_category_table . " WHERE product_id = :product_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':product_id', $this->id);
        return $stmt->execute();
    }
    
    // Import products from CSV
    public function bulkImport($csv_data) {
        // Start transaction
        $this->conn->beginTransaction();
        
        try {
            $success_count = 0;
            $failed_count = 0;
            $errors = [];
            
            // Process each line of CSV
            foreach($csv_data as $line => $data) {
                // Skip header row
                if($line === 0 && isset($data[0]) && $data[0] === 'name') {
                    continue;
                }
                
                // Check required fields
                if(empty($data[0]) || !isset($data[1]) || !isset($data[2]) || !isset($data[3])) {
                    $failed_count++;
                    $errors[] = "Line " . ($line + 1) . ": Missing required fields";
                    continue;
                }
                
                // Set product properties
                $this->name = $data[0];
                $this->description = $data[1];
                $this->price = $data[2];
                $this->stock = $data[3];
                $this->image = isset($data[4]) ? $data[4] : '';
                $this->is_customizable = isset($data[5]) ? $data[5] : 0;
                $this->status = isset($data[6]) ? $data[6] : 'active';
                
                // Set categories if available
                $this->categories = [];
                if(isset($data[7]) && !empty($data[7])) {
                    $this->categories = explode(',', $data[7]);
                }
                
                // Create product
                if($this->create()) {
                    $success_count++;
                } else {
                    $failed_count++;
                    $errors[] = "Line " . ($line + 1) . ": Failed to create product";
                }
            }
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'success_count' => $success_count,
                'failed_count' => $failed_count,
                'errors' => $errors
            ];
        } catch(Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>