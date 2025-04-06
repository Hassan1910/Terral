<?php
// Include model and helper classes
include_once ROOT_PATH . '/models/User.php';

class UserController {
    private $user;
    private $conn;
    private $authHelper;
    
    // Constructor
    public function __construct() {
        // Get database connection
        $database = new Database();
        $this->conn = $database->getConnection();
        
        // Initialize user model
        $this->user = new User($this->conn);
        
        // Initialize auth helper
        $this->authHelper = new AuthHelper();
    }
    
    // Register new user
    public function register() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if data is not empty
        if(
            !empty($data->first_name) &&
            !empty($data->last_name) &&
            !empty($data->email) &&
            !empty($data->password)
        ) {
            // Set user properties
            $this->user->first_name = $data->first_name;
            $this->user->last_name = $data->last_name;
            $this->user->email = $data->email;
            $this->user->password = $data->password;
            $this->user->role = isset($data->role) ? $data->role : 'customer';
            $this->user->phone = isset($data->phone) ? $data->phone : '';
            $this->user->address = isset($data->address) ? $data->address : '';
            $this->user->city = isset($data->city) ? $data->city : '';
            $this->user->state = isset($data->state) ? $data->state : '';
            $this->user->postal_code = isset($data->postal_code) ? $data->postal_code : '';
            $this->user->country = isset($data->country) ? $data->country : '';
            
            // Check if email already exists
            if($this->user->emailExists()) {
                // Set response code - 400 bad request
                http_response_code(400);
                
                // Tell the user email already exists
                echo json_encode(array("message" => "Email already exists."));
                return;
            }
            
            // Attempt to create user
            if($this->user->create()) {
                // Generate JWT token
                $token_data = $this->authHelper->generateToken($this->user->id, $this->user->email, $this->user->role);
                
                // Set response code - 201 created
                http_response_code(201);
                
                // Response data
                echo json_encode(array(
                    "message" => "User was created successfully.",
                    "id" => $this->user->id,
                    "first_name" => $this->user->first_name,
                    "last_name" => $this->user->last_name,
                    "email" => $this->user->email,
                    "role" => $this->user->role,
                    "token" => $token_data["token"],
                    "expires" => $token_data["expires"]
                ));
            } else {
                // Set response code - 503 service unavailable
                http_response_code(503);
                
                // Tell the user
                echo json_encode(array("message" => "Unable to create user."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user data is incomplete
            echo json_encode(array("message" => "Unable to create user. Data is incomplete."));
        }
    }
    
    // User login
    public function login() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if email and password are provided
        if(!empty($data->email) && !empty($data->password)) {
            // Set user properties
            $this->user->email = $data->email;
            
            // Check if email exists
            if($this->user->emailExists()) {
                // Check if password is correct
                if(password_verify($data->password, $this->user->password)) {
                    // Generate JWT token
                    $token_data = $this->authHelper->generateToken($this->user->id, $this->user->email, $this->user->role);
                    
                    // Set response code - 200 OK
                    http_response_code(200);
                    
                    // Response data
                    echo json_encode(array(
                        "message" => "Login successful.",
                        "id" => $this->user->id,
                        "first_name" => $this->user->first_name,
                        "last_name" => $this->user->last_name,
                        "email" => $this->user->email,
                        "role" => $this->user->role,
                        "token" => $token_data["token"],
                        "expires" => $token_data["expires"]
                    ));
                } else {
                    // Set response code - 401 unauthorized
                    http_response_code(401);
                    
                    // Tell the user login failed
                    echo json_encode(array("message" => "Login failed. Invalid password."));
                }
            } else {
                // Set response code - 401 unauthorized
                http_response_code(401);
                
                // Tell the user login failed
                echo json_encode(array("message" => "Login failed. User not found."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user data is incomplete
            echo json_encode(array("message" => "Login failed. Data is incomplete."));
        }
    }
    
    // Reset password
    public function resetPassword() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if email is provided
        if(!empty($data->email)) {
            // Set user properties
            $this->user->email = $data->email;
            
            // Check if email exists
            if($this->user->emailExists()) {
                // Generate random password
                $new_password = bin2hex(random_bytes(4)); // 8 characters
                
                // Set new password
                $this->user->password = $new_password;
                
                // Update password
                if($this->user->updatePassword()) {
                    // TODO: Send email with new password
                    
                    // Set response code - 200 OK
                    http_response_code(200);
                    
                    // Response data
                    echo json_encode(array(
                        "message" => "Password reset successful. Check your email for the new password.",
                        "password" => $new_password // Remove this in production, for testing only
                    ));
                } else {
                    // Set response code - 503 service unavailable
                    http_response_code(503);
                    
                    // Tell the user password reset failed
                    echo json_encode(array("message" => "Password reset failed."));
                }
            } else {
                // Set response code - 404 not found
                http_response_code(404);
                
                // Tell the user user not found
                echo json_encode(array("message" => "User not found."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user data is incomplete
            echo json_encode(array("message" => "Password reset failed. Email is required."));
        }
    }
    
    // Get all users (admin only)
    public function getUsers($query_params) {
        // Initialize parameters
        $limit = isset($query_params['limit']) ? (int)$query_params['limit'] : 10;
        $offset = isset($query_params['offset']) ? (int)$query_params['offset'] : 0;
        $search = isset($query_params['search']) ? $query_params['search'] : '';
        
        // If search term is provided, search users
        if(!empty($search)) {
            $stmt = $this->user->search($search, $limit, $offset);
        } else {
            // Read all users
            $stmt = $this->user->read($limit, $offset);
        }
        
        // Get row count
        $num = $stmt->rowCount();
        
        // Check if any users found
        if($num > 0) {
            // Users array
            $users_arr = array();
            $users_arr["users"] = array();
            $users_arr["total_count"] = $this->user->getCount();
            $users_arr["limit"] = $limit;
            $users_arr["offset"] = $offset;
            
            // Retrieve table contents
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Extract row
                extract($row);
                
                $user_item = array(
                    "id" => $id,
                    "first_name" => $first_name,
                    "last_name" => $last_name,
                    "email" => $email,
                    "role" => $role,
                    "phone" => $phone,
                    "address" => $address,
                    "city" => $city,
                    "state" => $state,
                    "postal_code" => $postal_code,
                    "country" => $country,
                    "created_at" => $created_at,
                    "updated_at" => $updated_at
                );
                
                // Push to "users" array
                array_push($users_arr["users"], $user_item);
            }
            
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode($users_arr);
        } else {
            // Set response code - 404 Not found
            http_response_code(404);
            
            // Tell the user no users found
            echo json_encode(array("message" => "No users found."));
        }
    }
    
    // Get one user
    public function getUser($id) {
        // Set user id
        $this->user->id = $id;
        
        // Get user details
        if($this->user->readOne()) {
            // Create array
            $user_arr = array(
                "id" => $this->user->id,
                "first_name" => $this->user->first_name,
                "last_name" => $this->user->last_name,
                "email" => $this->user->email,
                "role" => $this->user->role,
                "phone" => $this->user->phone,
                "address" => $this->user->address,
                "city" => $this->user->city,
                "state" => $this->user->state,
                "postal_code" => $this->user->postal_code,
                "country" => $this->user->country,
                "created_at" => $this->user->created_at,
                "updated_at" => $this->user->updated_at
            );
            
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode($user_arr);
        } else {
            // Set response code - 404 Not found
            http_response_code(404);
            
            // Tell the user user not found
            echo json_encode(array("message" => "User not found."));
        }
    }
    
    // Create user (admin only)
    public function createUser() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if data is not empty
        if(
            !empty($data->first_name) &&
            !empty($data->last_name) &&
            !empty($data->email) &&
            !empty($data->password) &&
            !empty($data->role)
        ) {
            // Set user properties
            $this->user->first_name = $data->first_name;
            $this->user->last_name = $data->last_name;
            $this->user->email = $data->email;
            $this->user->password = $data->password;
            $this->user->role = $data->role;
            $this->user->phone = isset($data->phone) ? $data->phone : '';
            $this->user->address = isset($data->address) ? $data->address : '';
            $this->user->city = isset($data->city) ? $data->city : '';
            $this->user->state = isset($data->state) ? $data->state : '';
            $this->user->postal_code = isset($data->postal_code) ? $data->postal_code : '';
            $this->user->country = isset($data->country) ? $data->country : '';
            
            // Check if email already exists
            if($this->user->emailExists()) {
                // Set response code - 400 bad request
                http_response_code(400);
                
                // Tell the user email already exists
                echo json_encode(array("message" => "Email already exists."));
                return;
            }
            
            // Attempt to create user
            if($this->user->create()) {
                // Set response code - 201 created
                http_response_code(201);
                
                // Response data
                echo json_encode(array(
                    "message" => "User was created successfully.",
                    "id" => $this->user->id
                ));
            } else {
                // Set response code - 503 service unavailable
                http_response_code(503);
                
                // Tell the user
                echo json_encode(array("message" => "Unable to create user."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user data is incomplete
            echo json_encode(array("message" => "Unable to create user. Data is incomplete."));
        }
    }
    
    // Update user
    public function updateUser($id) {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Set user id
        $this->user->id = $id;
        
        // Check if user exists
        if(!$this->user->readOne()) {
            // Set response code - 404 not found
            http_response_code(404);
            
            // Tell the user user not found
            echo json_encode(array("message" => "User not found."));
            return;
        }
        
        // Set user properties from request data
        if(isset($data->first_name)) $this->user->first_name = $data->first_name;
        if(isset($data->last_name)) $this->user->last_name = $data->last_name;
        if(isset($data->email)) $this->user->email = $data->email;
        if(isset($data->password)) $this->user->password = $data->password;
        if(isset($data->role)) $this->user->role = $data->role;
        if(isset($data->phone)) $this->user->phone = $data->phone;
        if(isset($data->address)) $this->user->address = $data->address;
        if(isset($data->city)) $this->user->city = $data->city;
        if(isset($data->state)) $this->user->state = $data->state;
        if(isset($data->postal_code)) $this->user->postal_code = $data->postal_code;
        if(isset($data->country)) $this->user->country = $data->country;
        
        // Update user
        if($this->user->update()) {
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode(array(
                "message" => "User was updated successfully.",
                "id" => $this->user->id
            ));
        } else {
            // Set response code - 503 service unavailable
            http_response_code(503);
            
            // Tell the user
            echo json_encode(array("message" => "Unable to update user."));
        }
    }
    
    // Delete user
    public function deleteUser($id) {
        // Set user id
        $this->user->id = $id;
        
        // Check if user exists
        if(!$this->user->readOne()) {
            // Set response code - 404 not found
            http_response_code(404);
            
            // Tell the user user not found
            echo json_encode(array("message" => "User not found."));
            return;
        }
        
        // Delete user
        if($this->user->delete()) {
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode(array("message" => "User was deleted successfully."));
        } else {
            // Set response code - 503 service unavailable
            http_response_code(503);
            
            // Tell the user
            echo json_encode(array("message" => "Unable to delete user."));
        }
    }
}
?> 