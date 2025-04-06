<?php
// Include model
include_once ROOT_PATH . '/models/Order.php';

class OrderController {
    private $order;
    private $conn;
    
    // Constructor
    public function __construct() {
        // Get database connection
        $database = new Database();
        $this->conn = $database->getConnection();
        
        // Initialize order model
        $this->order = new Order($this->conn);
    }
    
    // Get all orders (admin only)
    public function getOrders($query_params) {
        // Initialize parameters
        $limit = isset($query_params['limit']) ? (int)$query_params['limit'] : 10;
        $offset = isset($query_params['offset']) ? (int)$query_params['offset'] : 0;
        $status = isset($query_params['status']) ? $query_params['status'] : null;
        
        // Read all orders
        $stmt = $this->order->read($limit, $offset, $status);
        
        // Get row count
        $num = $stmt->rowCount();
        
        // Check if any orders found
        if($num > 0) {
            // Orders array
            $orders_arr = array();
            $orders_arr["orders"] = array();
            $orders_arr["total_count"] = $this->order->getCount($status);
            $orders_arr["limit"] = $limit;
            $orders_arr["offset"] = $offset;
            
            // Retrieve table contents
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Extract row
                extract($row);
                
                $order_item = array(
                    "id" => $id,
                    "user_id" => $user_id,
                    "customer_name" => $customer_name,
                    "customer_email" => $customer_email,
                    "total_price" => $total_price,
                    "status" => $status,
                    "payment_status" => $payment_status,
                    "payment_method" => $payment_method,
                    "created_at" => $created_at,
                    "updated_at" => $updated_at
                );
                
                // Push to "orders" array
                array_push($orders_arr["orders"], $order_item);
            }
            
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode($orders_arr);
        } else {
            // Set response code - 404 Not found
            http_response_code(404);
            
            // Tell the user no orders found
            echo json_encode(array("message" => "No orders found."));
        }
    }
    
    // Get orders for a specific user
    public function getUserOrders($user_id, $query_params) {
        // Initialize parameters
        $limit = isset($query_params['limit']) ? (int)$query_params['limit'] : 10;
        $offset = isset($query_params['offset']) ? (int)$query_params['offset'] : 0;
        $status = isset($query_params['status']) ? $query_params['status'] : null;
        
        // Read user orders
        $stmt = $this->order->read($limit, $offset, $status, $user_id);
        
        // Get row count
        $num = $stmt->rowCount();
        
        // Check if any orders found
        if($num > 0) {
            // Orders array
            $orders_arr = array();
            $orders_arr["orders"] = array();
            $orders_arr["total_count"] = $this->order->getCount($status, $user_id);
            $orders_arr["limit"] = $limit;
            $orders_arr["offset"] = $offset;
            
            // Retrieve table contents
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Extract row
                extract($row);
                
                $order_item = array(
                    "id" => $id,
                    "total_price" => $total_price,
                    "status" => $status,
                    "payment_status" => $payment_status,
                    "payment_method" => $payment_method,
                    "created_at" => $created_at,
                    "updated_at" => $updated_at
                );
                
                // Push to "orders" array
                array_push($orders_arr["orders"], $order_item);
            }
            
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode($orders_arr);
        } else {
            // Set response code - 404 Not found
            http_response_code(404);
            
            // Tell the user no orders found
            echo json_encode(array("message" => "No orders found."));
        }
    }
    
    // Get one order
    public function getOrder($id) {
        // Set order id
        $this->order->id = $id;
        
        // Get order details
        if($this->order->readOne()) {
            // Create array
            $order_arr = array(
                "id" => $this->order->id,
                "user_id" => $this->order->user_id,
                "total_price" => $this->order->total_price,
                "status" => $this->order->status,
                "payment_status" => $this->order->payment_status,
                "payment_method" => $this->order->payment_method,
                "payment_id" => $this->order->payment_id,
                "shipping_address" => $this->order->shipping_address,
                "shipping_city" => $this->order->shipping_city,
                "shipping_state" => $this->order->shipping_state,
                "shipping_postal_code" => $this->order->shipping_postal_code,
                "shipping_country" => $this->order->shipping_country,
                "shipping_phone" => $this->order->shipping_phone,
                "notes" => $this->order->notes,
                "created_at" => $this->order->created_at,
                "updated_at" => $this->order->updated_at,
                "items" => $this->order->items
            );
            
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode($order_arr);
        } else {
            // Set response code - 404 Not found
            http_response_code(404);
            
            // Tell the user order not found
            echo json_encode(array("message" => "Order not found."));
        }
    }
    
    // Create order
    public function createOrder() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if data is not empty
        if(
            !empty($data->user_id) &&
            !empty($data->items) &&
            !empty($data->shipping_address) &&
            !empty($data->shipping_city) &&
            !empty($data->shipping_country) &&
            !empty($data->shipping_phone)
        ) {
            // Set order properties
            $this->order->user_id = $data->user_id;
            $this->order->total_price = isset($data->total_price) ? $data->total_price : 0;
            $this->order->status = isset($data->status) ? $data->status : 'pending';
            $this->order->payment_status = isset($data->payment_status) ? $data->payment_status : 'pending';
            $this->order->payment_method = isset($data->payment_method) ? $data->payment_method : '';
            $this->order->payment_id = isset($data->payment_id) ? $data->payment_id : '';
            $this->order->shipping_address = $data->shipping_address;
            $this->order->shipping_city = $data->shipping_city;
            $this->order->shipping_state = isset($data->shipping_state) ? $data->shipping_state : '';
            $this->order->shipping_postal_code = isset($data->shipping_postal_code) ? $data->shipping_postal_code : '';
            $this->order->shipping_country = $data->shipping_country;
            $this->order->shipping_phone = $data->shipping_phone;
            $this->order->notes = isset($data->notes) ? $data->notes : '';
            
            // Set order items
            $this->order->items = $data->items;
            
            // Calculate total price if not provided
            if(empty($this->order->total_price)) {
                $total = 0;
                foreach($this->order->items as $item) {
                    $total += $item->price * $item->quantity;
                }
                $this->order->total_price = $total;
            }
            
            // Attempt to create order
            if($this->order->create()) {
                // Set response code - 201 created
                http_response_code(201);
                
                // Response data
                echo json_encode(array(
                    "message" => "Order was created successfully.",
                    "id" => $this->order->id
                ));
            } else {
                // Set response code - 503 service unavailable
                http_response_code(503);
                
                // Tell the user
                echo json_encode(array("message" => "Unable to create order."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user data is incomplete
            echo json_encode(array("message" => "Unable to create order. Data is incomplete."));
        }
    }
    
    // Update order
    public function updateOrder($id) {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Set order id
        $this->order->id = $id;
        
        // Check if order exists
        if(!$this->order->readOne()) {
            // Set response code - 404 not found
            http_response_code(404);
            
            // Tell the user order not found
            echo json_encode(array("message" => "Order not found."));
            return;
        }
        
        // Set order properties from request data
        if(isset($data->status)) $this->order->status = $data->status;
        if(isset($data->payment_status)) $this->order->payment_status = $data->payment_status;
        if(isset($data->payment_method)) $this->order->payment_method = $data->payment_method;
        if(isset($data->payment_id)) $this->order->payment_id = $data->payment_id;
        if(isset($data->shipping_address)) $this->order->shipping_address = $data->shipping_address;
        if(isset($data->shipping_city)) $this->order->shipping_city = $data->shipping_city;
        if(isset($data->shipping_state)) $this->order->shipping_state = $data->shipping_state;
        if(isset($data->shipping_postal_code)) $this->order->shipping_postal_code = $data->shipping_postal_code;
        if(isset($data->shipping_country)) $this->order->shipping_country = $data->shipping_country;
        if(isset($data->shipping_phone)) $this->order->shipping_phone = $data->shipping_phone;
        if(isset($data->notes)) $this->order->notes = $data->notes;
        
        // Update order
        if($this->order->update()) {
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode(array(
                "message" => "Order was updated successfully.",
                "id" => $this->order->id
            ));
        } else {
            // Set response code - 503 service unavailable
            http_response_code(503);
            
            // Tell the user
            echo json_encode(array("message" => "Unable to update order."));
        }
    }
    
    // Update order status
    public function updateOrderStatus($id) {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if status is provided
        if(!empty($data->status)) {
            // Set order id
            $this->order->id = $id;
            
            // Check if order exists
            if(!$this->order->readOne()) {
                // Set response code - 404 not found
                http_response_code(404);
                
                // Tell the user order not found
                echo json_encode(array("message" => "Order not found."));
                return;
            }
            
            // Validate status
            $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'canceled'];
            if(!in_array($data->status, $valid_statuses)) {
                // Set response code - 400 bad request
                http_response_code(400);
                
                // Tell the user
                echo json_encode(array("message" => "Invalid status. Valid statuses are: " . implode(", ", $valid_statuses)));
                return;
            }
            
            // Set order status
            $this->order->status = $data->status;
            
            // Update order status
            if($this->order->updateStatus()) {
                // Set response code - 200 OK
                http_response_code(200);
                
                // Response data
                echo json_encode(array(
                    "message" => "Order status was updated successfully.",
                    "id" => $this->order->id,
                    "status" => $this->order->status
                ));
            } else {
                // Set response code - 503 service unavailable
                http_response_code(503);
                
                // Tell the user
                echo json_encode(array("message" => "Unable to update order status."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user data is incomplete
            echo json_encode(array("message" => "Unable to update order status. Status is required."));
        }
    }
    
    // Cancel order
    public function cancelOrder($id) {
        // Set order id
        $this->order->id = $id;
        
        // Check if order exists
        if(!$this->order->readOne()) {
            // Set response code - 404 not found
            http_response_code(404);
            
            // Tell the user order not found
            echo json_encode(array("message" => "Order not found."));
            return;
        }
        
        // Cancel order
        if($this->order->cancel()) {
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode(array(
                "message" => "Order was canceled successfully.",
                "id" => $this->order->id
            ));
        } else {
            // Set response code - 503 service unavailable
            http_response_code(503);
            
            // Tell the user
            echo json_encode(array("message" => "Unable to cancel order."));
        }
    }
    
    // Delete order
    public function deleteOrder($id) {
        // Set order id
        $this->order->id = $id;
        
        // Check if order exists
        if(!$this->order->readOne()) {
            // Set response code - 404 not found
            http_response_code(404);
            
            // Tell the user order not found
            echo json_encode(array("message" => "Order not found."));
            return;
        }
        
        // Delete order
        if($this->order->delete()) {
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode(array("message" => "Order was deleted successfully."));
        } else {
            // Set response code - 503 service unavailable
            http_response_code(503);
            
            // Tell the user
            echo json_encode(array("message" => "Unable to delete order."));
        }
    }
}
?> 