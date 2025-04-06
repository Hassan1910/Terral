<?php
/**
 * Terral Online Production System
 * Category Model Class
 */

class Category {
    // Database connection and table name
    private $conn;
    private $table_name = "categories";
    
    // Object properties
    public $id;
    public $name;
    public $description;
    public $image;
    public $created_at;
    public $updated_at;
    
    /**
     * Constructor with DB connection
     * @param PDO $db Database connection object
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Read all categories
     * @return PDOStatement
     */
    public function read() {
        // Query to read all categories
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY name ASC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Read single category
     * @return bool True if data found, false otherwise
     */
    public function readOne() {
        // Query to read single record
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind ID parameter
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Get retrieved row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Set properties
        if($row) {
            $this->name = $row['name'];
            $this->description = $row['description'];
            $this->image = $row['image'] ?? null;
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Create new category
     * @return bool True if created, false otherwise
     */
    public function create() {
        // Check if categories table has image column, add it if not
        try {
            $checkImageColumn = "SHOW COLUMNS FROM " . $this->table_name . " LIKE 'image'";
            $stmt = $this->conn->prepare($checkImageColumn);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                // Add image column if it doesn't exist
                $addColumnSQL = "ALTER TABLE " . $this->table_name . " ADD COLUMN image VARCHAR(255) NULL AFTER description";
                $this->conn->exec($addColumnSQL);
            }
        } catch (PDOException $e) {
            // Silently continue even if we can't add the column
        }
        
        // Query to insert record
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    name = :name,
                    description = :description,
                    image = :image,
                    created_at = :created_at";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        if ($this->image) {
            $this->image = htmlspecialchars(strip_tags($this->image));
        }
        
        // Bind values
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":image", $this->image);
        $stmt->bindParam(":created_at", $this->created_at);
        
        // Execute query
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    /**
     * Update category
     * @return bool True if updated, false otherwise
     */
    public function update() {
        // Query to update record
        $query = "UPDATE " . $this->table_name . "
                SET
                    name = :name,
                    description = :description,
                    image = :image,
                    updated_at = :updated_at
                WHERE
                    id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        if ($this->image) {
            $this->image = htmlspecialchars(strip_tags($this->image));
        }
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Bind values
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":image", $this->image);
        $stmt->bindParam(":updated_at", $this->updated_at);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete category
     * @return bool True if deleted, false otherwise
     */
    public function delete() {
        // Query to delete record
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Bind id
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if category has associated products
     * @return bool True if has products, false otherwise
     */
    public function hasProducts() {
        // Query to check if category has products
        $query = "SELECT COUNT(*) as product_count FROM products WHERE category_id = ?";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Fetch result
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return true if product count > 0
        return $row['product_count'] > 0;
    }
    
    /**
     * Get total number of categories
     * @return int Total number of categories
     */
    public function getCount() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
    
    /**
     * Search categories
     * @param string $keywords Keywords to search for
     * @return PDOStatement
     */
    public function search($keywords) {
        // Query to search categories
        $query = "SELECT * FROM " . $this->table_name . " 
                WHERE name LIKE ? OR description LIKE ?
                ORDER BY name ASC";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize
        $keywords = htmlspecialchars(strip_tags($keywords));
        $keywords = "%{$keywords}%";
        
        // Bind
        $stmt->bindParam(1, $keywords);
        $stmt->bindParam(2, $keywords);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
} 