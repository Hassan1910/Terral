<?php
/**
 * Terral Online Production System
 * User Model Class
 */

class User {
    // Database connection and table name
    private $conn;
    private $table_name = "users";
    
    // Object properties
    public $id;
    public $first_name;
    public $last_name;
    public $email;
    public $phone;
    public $password;
    public $address;
    public $city;
    public $postal_code;
    public $country;
    public $role;
    public $status;
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
     * Create new user record
     * @return bool True if created successfully, false otherwise
     */
    public function create() {
        // Query to insert record
        $query = "INSERT INTO " . $this->table_name . "
                  SET
                  first_name = :first_name,
                  last_name = :last_name,
                  email = :email,
                  phone = :phone,
                  password = :password,
                  address = :address,
                  city = :city,
                  postal_code = :postal_code,
                  country = :country,
                  role = :role,
                  created_at = :created_at";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->city = htmlspecialchars(strip_tags($this->city));
        $this->postal_code = htmlspecialchars(strip_tags($this->postal_code));
        $this->country = htmlspecialchars(strip_tags($this->country));
        $this->role = htmlspecialchars(strip_tags($this->role));
        
        // Bind values
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":city", $this->city);
        $stmt->bindParam(":postal_code", $this->postal_code);
        $stmt->bindParam(":country", $this->country);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":created_at", $this->created_at);
        
        // Execute query
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if email already exists
     * @param string $email Email to check
     * @return bool True if email exists, false otherwise
     */
    public function emailExists($email) {
        // Query to check if email exists
        $query = "SELECT id, first_name, last_name, email, password, role
                  FROM " . $this->table_name . "
                  WHERE email = ?
                  LIMIT 0,1";
        
        // Prepare the query
        $stmt = $this->conn->prepare($query);
        
        // Bind the email
        $stmt->bindParam(1, $email);
        
        // Execute the query
        $stmt->execute();
        
        // Check if email exists
        if ($stmt->rowCount() > 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get user by ID
     * @param int $id User ID
     * @return bool True if user found, false otherwise
     */
    public function readOne($id) {
        // Query to read single record
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Bind ID of product to be read
        $stmt->bindParam(1, $id);
        
        // Execute query
        $stmt->execute();
        
        // Get retrieved row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if record exists
        if ($row) {
            // Set values to object properties
            $this->id = $row['id'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->address = $row['address'];
            $this->city = $row['city'];
            $this->postal_code = $row['postal_code'];
            $this->country = $row['country'];
            $this->role = $row['role'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    // Read all users
    public function read($limit = 10, $offset = 0) {
        // Select query
        $query = "SELECT * FROM " . $this->table_name . " 
                  ORDER BY created_at DESC 
                  LIMIT :limit OFFSET :offset";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get total count of users
    public function getCount() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
    
    // Update user
    public function update() {
        // Update query
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                    first_name = :first_name, 
                    last_name = :last_name, 
                    email = :email, 
                    ";
        
        // Check if password is included in the update
        if(!empty($this->password)) {
            $query .= "password = :password, ";
        }
        
        $query .= "role = :role, 
                   phone = :phone, 
                   address = :address, 
                   city = :city, 
                   state = :state, 
                   postal_code = :postal_code, 
                   country = :country, 
                   updated_at = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->city = htmlspecialchars(strip_tags($this->city));
        $this->state = htmlspecialchars(strip_tags($this->state));
        $this->postal_code = htmlspecialchars(strip_tags($this->postal_code));
        $this->country = htmlspecialchars(strip_tags($this->country));
        
        // Bind parameters
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':first_name', $this->first_name);
        $stmt->bindParam(':last_name', $this->last_name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':address', $this->address);
        $stmt->bindParam(':city', $this->city);
        $stmt->bindParam(':state', $this->state);
        $stmt->bindParam(':postal_code', $this->postal_code);
        $stmt->bindParam(':country', $this->country);
        
        // Hash password if it's included
        if(!empty($this->password)) {
            $password_hash = password_hash($this->password, PASSWORD_BCRYPT);
            $stmt->bindParam(':password', $password_hash);
        }
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        // Print error if something goes wrong
        printf("Error: %s.\n", $stmt->error);
        
        return false;
    }
    
    // Delete user
    public function delete() {
        // Delete query
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize id
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Bind parameter
        $stmt->bindParam(':id', $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        // Print error if something goes wrong
        printf("Error: %s.\n", $stmt->error);
        
        return false;
    }
    
    // Update password
    public function updatePassword() {
        // Update query
        $query = "UPDATE " . $this->table_name . " 
                  SET 
                    password = :password, 
                    updated_at = NOW() 
                  WHERE id = :id";
        
        // Prepare statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Hash password
        $password_hash = password_hash($this->password, PASSWORD_BCRYPT);
        
        // Bind parameters
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':password', $password_hash);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        // Print error if something goes wrong
        printf("Error: %s.\n", $stmt->error);
        
        return false;
    }
    
    // Search users
    public function search($search_term, $limit = 10, $offset = 0) {
        // Select query
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE 
                    first_name LIKE :search_term OR 
                    last_name LIKE :search_term OR 
                    email LIKE :search_term OR 
                    phone LIKE :search_term 
                  ORDER BY created_at DESC 
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
}
?> 