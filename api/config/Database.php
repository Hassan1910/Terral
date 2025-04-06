<?php
/**
 * Terral Online Production System
 * Database Connection Class
 */

class Database {
    // Database credentials
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    // Constructor
    public function __construct() {
        // Set credentials from environment variables if available
        if (class_exists('\\Dotenv\\Dotenv') && file_exists(dirname(dirname(__DIR__)) . '/.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(dirname(dirname(__DIR__)));
            $dotenv->load();
            
            $this->host = isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'localhost';
            $this->db_name = isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : 'terral_db';
            $this->username = isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : 'root';
            $this->password = isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : '';
        } else {
            // Default credentials
            $this->host = 'localhost';
            $this->db_name = 'terral_db';
            $this->username = 'root';
            $this->password = '';
        }
    }

    /**
     * Get the database connection
     * @return PDO The database connection object
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8mb4");
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}
?> 