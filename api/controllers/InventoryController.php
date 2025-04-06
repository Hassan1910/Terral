<?php
// Include models
include_once ROOT_PATH . '/models/Product.php';

class InventoryController {
    private $conn;
    private $product;
    
    // Constructor
    public function __construct() {
        // Get database connection
        $database = new Database();
        $this->conn = $database->getConnection();
        
        // Initialize product model
        $this->product = new Product($this->conn);
    }
    
    // Get inventory
    public function getInventory($query_params) {
        // Forward to ProductController's getProducts method
        $productController = new ProductController();
        $productController->getProducts($query_params);
    }
    
    // Get low stock products
    public function getLowStockProducts($query_params) {
        // Initialize parameters
        $limit = isset($query_params['limit']) ? (int)$query_params['limit'] : 10;
        $offset = isset($query_params['offset']) ? (int)$query_params['offset'] : 0;
        $threshold = isset($query_params['threshold']) ? (int)$query_params['threshold'] : 10;
        
        // Get low stock products
        $stmt = $this->product->getLowStockProducts($threshold, $limit, $offset);
        
        // Get row count
        $num = $stmt->rowCount();
        
        // Check if any products found
        if($num > 0) {
            // Products array
            $products_arr = array();
            $products_arr["products"] = array();
            $products_arr["total_count"] = $this->product->getLowStockCount($threshold);
            $products_arr["threshold"] = $threshold;
            $products_arr["limit"] = $limit;
            $products_arr["offset"] = $offset;
            
            // Retrieve table contents
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Extract row
                extract($row);
                
                $product_item = array(
                    "id" => $id,
                    "name" => $name,
                    "description" => $description,
                    "price" => $price,
                    "stock" => $stock,
                    "image" => $image,
                    "is_customizable" => $is_customizable,
                    "status" => $status,
                    "created_at" => $created_at,
                    "updated_at" => $updated_at
                );
                
                // Add categories if present
                if(isset($row['categories']) && !empty($row['categories'])) {
                    $product_item["categories"] = explode(",", $row['categories']);
                } else {
                    $product_item["categories"] = [];
                }
                
                // Push to "products" array
                array_push($products_arr["products"], $product_item);
            }
            
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode($products_arr);
        } else {
            // Set response code - 404 Not found
            http_response_code(404);
            
            // Tell the user no products found
            echo json_encode(array("message" => "No low stock products found."));
        }
    }
    
    // Update stock
    public function updateStock($id) {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if stock is provided
        if(isset($data->stock) && $data->stock >= 0) {
            // Set product id
            $this->product->id = $id;
            
            // Check if product exists
            if(!$this->product->readOne()) {
                // Set response code - 404 not found
                http_response_code(404);
                
                // Tell the user product not found
                echo json_encode(array("message" => "Product not found."));
                return;
            }
            
            // Set stock
            $this->product->stock = $data->stock;
            
            // Update stock
            if($this->product->updateStock()) {
                // Set response code - 200 OK
                http_response_code(200);
                
                // Response data
                echo json_encode(array(
                    "message" => "Stock updated successfully.",
                    "id" => $this->product->id,
                    "stock" => $this->product->stock
                ));
            } else {
                // Set response code - 503 service unavailable
                http_response_code(503);
                
                // Tell the user
                echo json_encode(array("message" => "Unable to update stock."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user
            echo json_encode(array("message" => "Unable to update stock. Valid stock value is required."));
        }
    }
    
    // Bulk import products from CSV
    public function bulkImport() {
        // Check if file is uploaded
        if(isset($_FILES['csv_file'])) {
            $file = $_FILES['csv_file'];
            
            // Check for upload errors
            if($file['error'] !== UPLOAD_ERR_OK) {
                // Set response code - 400 bad request
                http_response_code(400);
                
                // Tell the user
                echo json_encode(array(
                    "message" => "File upload error: " . $this->getUploadErrorMessage($file['error'])
                ));
                return;
            }
            
            // Check file type
            $file_info = pathinfo($file['name']);
            $extension = strtolower($file_info['extension']);
            
            if($extension != 'csv') {
                // Set response code - 400 bad request
                http_response_code(400);
                
                // Tell the user
                echo json_encode(array("message" => "Only CSV files are allowed."));
                return;
            }
            
            // Open file
            $handle = fopen($file['tmp_name'], 'r');
            
            if($handle !== FALSE) {
                $csv_data = [];
                
                // Read CSV data
                while(($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $csv_data[] = $data;
                }
                
                fclose($handle);
                
                // Validate CSV structure
                if(count($csv_data) < 2) {
                    // Set response code - 400 bad request
                    http_response_code(400);
                    
                    // Tell the user
                    echo json_encode(array("message" => "CSV file must contain header row and at least one data row."));
                    return;
                }
                
                // Validate header row
                $required_headers = ['name', 'description', 'price', 'stock'];
                $header_row = array_map('strtolower', $csv_data[0]);
                
                foreach($required_headers as $required) {
                    if(!in_array($required, $header_row)) {
                        // Set response code - 400 bad request
                        http_response_code(400);
                        
                        // Tell the user
                        echo json_encode(array(
                            "message" => "CSV file must contain the following headers: " . implode(', ', $required_headers)
                        ));
                        return;
                    }
                }
                
                // Import products
                $result = $this->product->bulkImport($csv_data);
                
                if($result['success']) {
                    // Set response code - 200 OK
                    http_response_code(200);
                    
                    // Response data
                    echo json_encode(array(
                        "message" => "Products imported successfully.",
                        "success_count" => $result['success_count'],
                        "failed_count" => $result['failed_count'],
                        "errors" => $result['errors']
                    ));
                } else {
                    // Set response code - 503 service unavailable
                    http_response_code(503);
                    
                    // Tell the user
                    echo json_encode(array(
                        "message" => "Failed to import products: " . $result['message'],
                    ));
                }
            } else {
                // Set response code - 503 service unavailable
                http_response_code(503);
                
                // Tell the user
                echo json_encode(array("message" => "Unable to read CSV file."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user
            echo json_encode(array("message" => "No CSV file provided."));
        }
    }
    
    // Helper function to get upload error message
    private function getUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
            case UPLOAD_ERR_FORM_SIZE:
                return "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
            case UPLOAD_ERR_PARTIAL:
                return "The uploaded file was only partially uploaded";
            case UPLOAD_ERR_NO_FILE:
                return "No file was uploaded";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Missing a temporary folder";
            case UPLOAD_ERR_CANT_WRITE:
                return "Failed to write file to disk";
            case UPLOAD_ERR_EXTENSION:
                return "A PHP extension stopped the file upload";
            default:
                return "Unknown upload error";
        }
    }
}
?> 