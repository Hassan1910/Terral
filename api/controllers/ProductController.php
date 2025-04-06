<?php
// Include model
include_once ROOT_PATH . '/models/Product.php';

class ProductController {
    private $product;
    private $conn;
    
    // Constructor
    public function __construct() {
        // Get database connection
        $database = new Database();
        $this->conn = $database->getConnection();
        
        // Initialize product model
        $this->product = new Product($this->conn);
    }
    
    // Get all products
    public function getProducts($query_params) {
        // Initialize parameters
        $limit = isset($query_params['limit']) ? (int)$query_params['limit'] : 10;
        $offset = isset($query_params['offset']) ? (int)$query_params['offset'] : 0;
        $category_id = isset($query_params['category_id']) ? (int)$query_params['category_id'] : null;
        $search = isset($query_params['search']) ? $query_params['search'] : '';
        
        // If search term is provided, search products
        if(!empty($search)) {
            $stmt = $this->product->search($search, $limit, $offset);
        } else {
            // Read all products
            $stmt = $this->product->read($limit, $offset, $category_id);
        }
        
        // Get row count
        $num = $stmt->rowCount();
        
        // Check if any products found
        if($num > 0) {
            // Products array
            $products_arr = array();
            $products_arr["products"] = array();
            $products_arr["total_count"] = $this->product->getCount($category_id);
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
            echo json_encode(array("message" => "No products found."));
        }
    }
    
    // Get one product
    public function getProduct($id) {
        // Set product id
        $this->product->id = $id;
        
        // Get product details
        if($this->product->readOne()) {
            // Create array
            $product_arr = array(
                "id" => $this->product->id,
                "name" => $this->product->name,
                "description" => $this->product->description,
                "price" => $this->product->price,
                "stock" => $this->product->stock,
                "image" => $this->product->image,
                "is_customizable" => $this->product->is_customizable,
                "status" => $this->product->status,
                "categories" => $this->product->categories,
                "created_at" => $this->product->created_at,
                "updated_at" => $this->product->updated_at
            );
            
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode($product_arr);
        } else {
            // Set response code - 404 Not found
            http_response_code(404);
            
            // Tell the user product not found
            echo json_encode(array("message" => "Product not found."));
        }
    }
    
    // Create product
    public function createProduct() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if data is not empty
        if(
            !empty($data->name) &&
            !empty($data->description) &&
            !empty($data->price) &&
            !empty($data->stock)
        ) {
            // Set product properties
            $this->product->name = $data->name;
            $this->product->description = $data->description;
            $this->product->price = $data->price;
            $this->product->stock = $data->stock;
            $this->product->image = isset($data->image) ? $data->image : '';
            $this->product->is_customizable = isset($data->is_customizable) ? $data->is_customizable : 0;
            $this->product->status = isset($data->status) ? $data->status : 'active';
            
            // Set categories if provided
            if(isset($data->categories) && is_array($data->categories)) {
                $this->product->categories = $data->categories;
            }
            
            // Attempt to create product
            if($this->product->create()) {
                // Set response code - 201 created
                http_response_code(201);
                
                // Response data
                echo json_encode(array(
                    "message" => "Product was created successfully.",
                    "id" => $this->product->id
                ));
            } else {
                // Set response code - 503 service unavailable
                http_response_code(503);
                
                // Tell the user
                echo json_encode(array("message" => "Unable to create product."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user data is incomplete
            echo json_encode(array("message" => "Unable to create product. Data is incomplete."));
        }
    }
    
    // Update product
    public function updateProduct($id) {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"));
        
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
        
        // Set product properties from request data
        if(isset($data->name)) $this->product->name = $data->name;
        if(isset($data->description)) $this->product->description = $data->description;
        if(isset($data->price)) $this->product->price = $data->price;
        if(isset($data->stock)) $this->product->stock = $data->stock;
        if(isset($data->image)) $this->product->image = $data->image;
        if(isset($data->is_customizable)) $this->product->is_customizable = $data->is_customizable;
        if(isset($data->status)) $this->product->status = $data->status;
        
        // Set categories if provided
        if(isset($data->categories) && is_array($data->categories)) {
            $this->product->categories = $data->categories;
        }
        
        // Update product
        if($this->product->update()) {
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode(array(
                "message" => "Product was updated successfully.",
                "id" => $this->product->id
            ));
        } else {
            // Set response code - 503 service unavailable
            http_response_code(503);
            
            // Tell the user
            echo json_encode(array("message" => "Unable to update product."));
        }
    }
    
    // Delete product
    public function deleteProduct($id) {
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
        
        // Delete product
        if($this->product->delete()) {
            // Set response code - 200 OK
            http_response_code(200);
            
            // Response data
            echo json_encode(array("message" => "Product was deleted successfully."));
        } else {
            // Set response code - 503 service unavailable
            http_response_code(503);
            
            // Tell the user
            echo json_encode(array("message" => "Unable to delete product."));
        }
    }
    
    // Upload product image
    public function uploadImage($id = null) {
        // Check if image file is uploaded
        if(isset($_FILES['image'])) {
            $file = $_FILES['image'];
            
            // Check for upload errors
            if($file['error'] !== UPLOAD_ERR_OK) {
                // Set response code - 400 bad request
                http_response_code(400);
                
                // Tell the user
                echo json_encode(array("message" => "File upload error: " . $this->getUploadErrorMessage($file['error'])));
                return;
            }
            
            // Check file size (max 5MB)
            $max_size = 5 * 1024 * 1024;
            if($file['size'] > $max_size) {
                // Set response code - 400 bad request
                http_response_code(400);
                
                // Tell the user
                echo json_encode(array("message" => "File too large. Maximum size is 5MB."));
                return;
            }
            
            // Check file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if(!in_array($file['type'], $allowed_types)) {
                // Set response code - 400 bad request
                http_response_code(400);
                
                // Tell the user
                echo json_encode(array("message" => "Invalid file type. Allowed types: JPEG, PNG, GIF."));
                return;
            }
            
            // Create a ProductHelper instance for file uploads
            include_once ROOT_PATH . '/helpers/ProductHelper.php';
            $productHelper = new ProductHelper($this->conn);
            
            // Upload the image using ProductHelper
            $filename = $productHelper->uploadProductImage($file);
            
            if($filename) {
                // If product ID is provided, update product image
                if($id) {
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
                    
                    // Update product image
                    $this->product->image = $filename;
                    if($this->product->update()) {
                        // Set response code - 200 OK
                        http_response_code(200);
                        
                        // Response data
                        echo json_encode(array(
                            "message" => "Image uploaded successfully.",
                            "image" => $filename,
                            "product_id" => $id
                        ));
                    } else {
                        // Set response code - 503 service unavailable
                        http_response_code(503);
                        
                        // Tell the user
                        echo json_encode(array("message" => "Unable to update product image."));
                    }
                } else {
                    // Just return the filename if no product ID provided
                    // Set response code - 200 OK
                    http_response_code(200);
                    
                    // Response data
                    echo json_encode(array(
                        "message" => "Image uploaded successfully.",
                        "image" => $filename
                    ));
                }
            } else {
                // Set response code - 503 service unavailable
                http_response_code(503);
                
                // Tell the user
                echo json_encode(array("message" => "Unable to upload image."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user
            echo json_encode(array("message" => "No image file provided."));
        }
    }
    
    // Customize product
    public function customizeProduct($id) {
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
        
        // Check if product is customizable
        if(!$this->product->is_customizable) {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user
            echo json_encode(array("message" => "This product is not customizable."));
            return;
        }
        
        // Check if image file is uploaded
        if(isset($_FILES['customization'])) {
            $file = $_FILES['customization'];
            
            // Check for upload errors
            if($file['error'] !== UPLOAD_ERR_OK) {
                // Set response code - 400 bad request
                http_response_code(400);
                
                // Tell the user
                echo json_encode(array("message" => "File upload error: " . $this->getUploadErrorMessage($file['error'])));
                return;
            }
            
            // Check file size (max 5MB)
            $max_size = 5 * 1024 * 1024;
            if($file['size'] > $max_size) {
                // Set response code - 400 bad request
                http_response_code(400);
                
                // Tell the user
                echo json_encode(array("message" => "File too large. Maximum size is 5MB."));
                return;
            }
            
            // Check file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if(!in_array($file['type'], $allowed_types)) {
                // Set response code - 400 bad request
                http_response_code(400);
                
                // Tell the user
                echo json_encode(array("message" => "Invalid file type. Allowed types: JPEG, PNG, GIF."));
                return;
            }
            
            // Generate unique filename
            $filename = uniqid() . '_' . basename($file['name']);
            $upload_path = CUSTOM_UPLOAD_PATH . '/' . $filename;
            
            // Move uploaded file
            if(move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Set response code - 200 OK
                http_response_code(200);
                
                // Response data
                echo json_encode(array(
                    "message" => "Customization image uploaded successfully.",
                    "customization_image" => $filename,
                    "product_id" => $id
                ));
            } else {
                // Set response code - 503 service unavailable
                http_response_code(503);
                
                // Tell the user
                echo json_encode(array("message" => "Unable to upload customization image."));
            }
        } else {
            // Set response code - 400 bad request
            http_response_code(400);
            
            // Tell the user
            echo json_encode(array("message" => "No customization image provided."));
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