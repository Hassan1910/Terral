<?php
// Load JWT library
require_once ROOT_PATH . '/vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class AuthHelper {
    private $jwtConfig;
    private $conn;
    private $secretKey;
    private $decoded;
    
    public function __construct() {
        // Get database connection
        $database = new Database();
        $this->conn = $database->getConnection();
        
        // Get JWT config
        $this->jwtConfig = new JwtConfig();
        $config = $this->jwtConfig->getConfig();
        $this->secretKey = $config['key'];
    }
    
    // Generate JWT token
    public function generateToken($userId, $email, $role) {
        $config = $this->jwtConfig->getConfig();
        
        $payload = [
            'iss' => $config['issuer'],
            'aud' => $config['audience'],
            'iat' => $config['issuedAt'],
            'nbf' => $config['notBefore'],
            'exp' => $config['expire'],
            'data' => [
                'id' => $userId,
                'email' => $email,
                'role' => $role
            ]
        ];
        
        // Encode JWT
        $jwt = JWT::encode($payload, $this->secretKey, 'HS256');
        
        return [
            'token' => $jwt,
            'expires' => $config['expire']
        ];
    }
    
    // Validate JWT token
    public function validateToken($return_response = false) {
        // Get HTTP Authorization header
        $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        $token = null;
        
        // Extract token from header
        if (!empty($auth_header)) {
            // Check if token is in bearer format
            if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
                $token = $matches[1];
            }
        }
        
        // Check if token exists
        if (!$token) {
            if ($return_response) {
                $this->sendResponse(['message' => 'Access denied. Token is missing.'], 401);
            }
            return false;
        }
        
        try {
            // Decode token
            $this->decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            
            // Check if token is valid
            if ($this->decoded) {
                if ($return_response) {
                    $this->sendResponse(['message' => 'Token is valid', 'data' => $this->decoded->data], 200);
                }
                return true;
            }
        } catch (Exception $e) {
            if ($return_response) {
                $this->sendResponse(['message' => 'Token validation failed: ' . $e->getMessage()], 401);
            }
            return false;
        }
        
        if ($return_response) {
            $this->sendResponse(['message' => 'Token is invalid'], 401);
        }
        return false;
    }
    
    // Check if current user is admin
    public function isAdmin() {
        if (!$this->decoded) {
            return false;
        }
        
        return $this->decoded->data->role === 'admin';
    }
    
    // Check if token belongs to current user
    public function isCurrentUser($id) {
        if (!$this->decoded) {
            return false;
        }
        
        return $this->decoded->data->id == $id;
    }
    
    // Get current user ID from token
    public function getCurrentUserId() {
        if (!$this->decoded) {
            return null;
        }
        
        return $this->decoded->data->id;
    }
    
    // Get current user role from token
    public function getCurrentUserRole() {
        if (!$this->decoded) {
            return null;
        }
        
        return $this->decoded->data->role;
    }
    
    // Send API response
    private function sendResponse($data, $status_code = 200) {
        http_response_code($status_code);
        echo json_encode($data);
        exit;
    }
}
?> 