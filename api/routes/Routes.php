<?php
// Include controller files
include_once ROOT_PATH . '/controllers/UserController.php';
include_once ROOT_PATH . '/controllers/ProductController.php';
include_once ROOT_PATH . '/controllers/OrderController.php';
include_once ROOT_PATH . '/controllers/PaymentController.php';
include_once ROOT_PATH . '/controllers/InventoryController.php';
include_once ROOT_PATH . '/controllers/ReportController.php';
include_once ROOT_PATH . '/controllers/SettingController.php';
include_once ROOT_PATH . '/helpers/AuthHelper.php';

class Routes {
    private $userController;
    private $productController;
    private $orderController;
    private $paymentController;
    private $inventoryController;
    private $reportController;
    private $settingController;
    private $authHelper;

    public function __construct() {
        $this->userController = new UserController();
        $this->productController = new ProductController();
        $this->orderController = new OrderController();
        $this->paymentController = new PaymentController();
        $this->inventoryController = new InventoryController();
        $this->reportController = new ReportController();
        $this->settingController = new SettingController();
        $this->authHelper = new AuthHelper();
    }

    public function processRequest($route, $method) {
        // Parse URL to get the endpoint and parameters
        $url_parts = parse_url($route);
        $path = isset($url_parts['path']) ? $url_parts['path'] : '';
        $path = trim($path, '/');
        $path_parts = explode('/', $path);
        $endpoint = isset($path_parts[0]) ? $path_parts[0] : '';
        $id = isset($path_parts[1]) ? $path_parts[1] : null;
        $action = isset($path_parts[2]) ? $path_parts[2] : null;

        // Parse query parameters
        $query = isset($url_parts['query']) ? $url_parts['query'] : '';
        parse_str($query, $query_params);

        // Routing based on endpoint
        switch ($endpoint) {
            case 'users':
                $this->handleUserRoutes($method, $id, $action, $query_params);
                break;
            case 'auth':
                $this->handleAuthRoutes($method, $action);
                break;
            case 'products':
                $this->handleProductRoutes($method, $id, $action, $query_params);
                break;
            case 'orders':
                $this->handleOrderRoutes($method, $id, $action, $query_params);
                break;
            case 'payments':
                $this->handlePaymentRoutes($method, $id, $action);
                break;
            case 'inventory':
                $this->handleInventoryRoutes($method, $id, $action, $query_params);
                break;
            case 'reports':
                $this->handleReportRoutes($method, $id, $action, $query_params);
                break;
            case 'settings':
                $this->handleSettingRoutes($method, $id, $action);
                break;
            default:
                // Handle 404 Not Found
                $this->sendResponse(['message' => 'Endpoint not found'], 404);
                break;
        }
    }

    // Handle User routes
    private function handleUserRoutes($method, $id, $action, $query_params) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    if ($this->authHelper->validateToken()) {
                        $this->userController->getUser($id);
                    }
                } else {
                    if ($this->authHelper->validateToken() && $this->authHelper->isAdmin()) {
                        $this->userController->getUsers($query_params);
                    }
                }
                break;
            case 'POST':
                if ($action == 'register') {
                    $this->userController->register();
                } else if ($action == 'reset-password') {
                    $this->userController->resetPassword();
                } else {
                    if ($this->authHelper->validateToken() && $this->authHelper->isAdmin()) {
                        $this->userController->createUser();
                    }
                }
                break;
            case 'PUT':
                if ($id) {
                    if ($this->authHelper->validateToken() && ($this->authHelper->isAdmin() || $this->authHelper->isCurrentUser($id))) {
                        $this->userController->updateUser($id);
                    }
                }
                break;
            case 'DELETE':
                if ($id) {
                    if ($this->authHelper->validateToken() && $this->authHelper->isAdmin()) {
                        $this->userController->deleteUser($id);
                    }
                }
                break;
            default:
                $this->sendResponse(['message' => 'Method not allowed'], 405);
                break;
        }
    }

    // Handle Auth routes
    private function handleAuthRoutes($method, $action) {
        switch ($method) {
            case 'POST':
                if ($action == 'login') {
                    $this->userController->login();
                } else if ($action == 'verify') {
                    $this->authHelper->validateToken(true);
                } else {
                    $this->sendResponse(['message' => 'Action not found'], 404);
                }
                break;
            default:
                $this->sendResponse(['message' => 'Method not allowed'], 405);
                break;
        }
    }

    // Handle Product routes
    private function handleProductRoutes($method, $id, $action, $query_params) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->productController->getProduct($id);
                } else {
                    $this->productController->getProducts($query_params);
                }
                break;
            case 'POST':
                if ($this->authHelper->validateToken() && $this->authHelper->isAdmin()) {
                    if ($action == 'upload') {
                        $this->productController->uploadImage($id);
                    } else if ($action == 'customize' && $id) {
                        if ($this->authHelper->validateToken()) {
                            $this->productController->customizeProduct($id);
                        }
                    } else {
                        $this->productController->createProduct();
                    }
                }
                break;
            case 'PUT':
                if ($id && $this->authHelper->validateToken() && $this->authHelper->isAdmin()) {
                    $this->productController->updateProduct($id);
                }
                break;
            case 'DELETE':
                if ($id && $this->authHelper->validateToken() && $this->authHelper->isAdmin()) {
                    $this->productController->deleteProduct($id);
                }
                break;
            default:
                $this->sendResponse(['message' => 'Method not allowed'], 405);
                break;
        }
    }

    // Handle Order routes
    private function handleOrderRoutes($method, $id, $action, $query_params) {
        switch ($method) {
            case 'GET':
                if ($this->authHelper->validateToken()) {
                    if ($id) {
                        $this->orderController->getOrder($id);
                    } else {
                        if ($this->authHelper->isAdmin()) {
                            $this->orderController->getOrders($query_params);
                        } else {
                            $this->orderController->getUserOrders($this->authHelper->getCurrentUserId(), $query_params);
                        }
                    }
                }
                break;
            case 'POST':
                if ($this->authHelper->validateToken()) {
                    $this->orderController->createOrder();
                }
                break;
            case 'PUT':
                if ($id && $this->authHelper->validateToken()) {
                    if ($action == 'status' && $this->authHelper->isAdmin()) {
                        $this->orderController->updateOrderStatus($id);
                    } else if ($action == 'cancel') {
                        $this->orderController->cancelOrder($id);
                    } else {
                        $this->orderController->updateOrder($id);
                    }
                }
                break;
            case 'DELETE':
                if ($id && $this->authHelper->validateToken() && $this->authHelper->isAdmin()) {
                    $this->orderController->deleteOrder($id);
                }
                break;
            default:
                $this->sendResponse(['message' => 'Method not allowed'], 405);
                break;
        }
    }

    // Handle Payment routes
    private function handlePaymentRoutes($method, $id, $action) {
        switch ($method) {
            case 'POST':
                if ($this->authHelper->validateToken()) {
                    if ($action == 'mpesa-init') {
                        $this->paymentController->initiateMpesaPayment();
                    } else if ($action == 'card-init') {
                        $this->paymentController->initiateCardPayment();
                    } else if ($action == 'bank-init') {
                        $this->paymentController->initiateBankTransfer();
                    } else if ($action == 'cod-init') {
                        $this->paymentController->initiateCashOnDelivery();
                    } else if ($action == 'simulate-completion') {
                        $this->paymentController->simulatePaymentCompletion();
                    } else if ($action == 'callback') {
                        // This would be public for M-Pesa API callback
                        $this->paymentController->mpesaCallback();
                    } else if ($action == 'invoice' && $id) {
                        $this->paymentController->generateInvoice($id);
                    }
                }
                break;
            case 'GET':
                if ($action == 'methods') {
                    $this->paymentController->getPaymentMethods();
                } else if ($id && $this->authHelper->validateToken()) {
                    $this->paymentController->getPayment($id);
                }
                break;
            default:
                $this->sendResponse(['message' => 'Method not allowed'], 405);
                break;
        }
    }

    // Handle Inventory routes
    private function handleInventoryRoutes($method, $id, $action, $query_params) {
        switch ($method) {
            case 'GET':
                if ($this->authHelper->validateToken() && $this->authHelper->isAdmin()) {
                    if ($action == 'low-stock') {
                        $this->inventoryController->getLowStockProducts($query_params);
                    } else {
                        $this->inventoryController->getInventory($query_params);
                    }
                }
                break;
            case 'POST':
                if ($this->authHelper->validateToken() && $this->authHelper->isAdmin()) {
                    if ($action == 'bulk-import') {
                        $this->inventoryController->bulkImport();
                    } else if ($id) {
                        $this->inventoryController->updateStock($id);
                    }
                }
                break;
            default:
                $this->sendResponse(['message' => 'Method not allowed'], 405);
                break;
        }
    }

    // Handle Report routes
    private function handleReportRoutes($method, $id, $action, $query_params) {
        switch ($method) {
            case 'GET':
                if ($this->authHelper->validateToken() && $this->authHelper->isAdmin()) {
                    if ($action == 'sales') {
                        $this->reportController->getSalesReport($query_params);
                    } else if ($action == 'orders') {
                        $this->reportController->getOrdersReport($query_params);
                    } else if ($action == 'customers') {
                        $this->reportController->getCustomerReport($query_params);
                    } else {
                        $this->reportController->getDashboardStats();
                    }
                }
                break;
            default:
                $this->sendResponse(['message' => 'Method not allowed'], 405);
                break;
        }
    }

    // Handle Setting routes
    private function handleSettingRoutes($method, $id, $action) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    // Get setting by key
                    $this->settingController->getByKey($id);
                } else if ($action == 'public') {
                    // Get public settings
                    $this->settingController->getPublicSettings();
                } else if ($action == 'groups') {
                    // Get all setting groups (admin only)
                    $this->settingController->getGroups();
                } else if ($action && $action != 'public' && $action != 'groups') {
                    // Get settings by group (admin only)
                    $this->settingController->getByGroup($action);
                } else {
                    // Get all settings (admin only)
                    $this->settingController->getAll();
                }
                break;
            case 'POST':
                if ($this->authHelper->validateToken() && $this->authHelper->isAdmin()) {
                    if ($action == 'batch') {
                        // Batch update multiple settings
                        $this->settingController->batchUpdate();
                    } else if ($id && $action == 'upload') {
                        // Upload image for a setting
                        $this->settingController->uploadImage($id);
                    } else {
                        // Create new setting
                        $this->settingController->create();
                    }
                }
                break;
            case 'PUT':
                if ($this->authHelper->validateToken() && $this->authHelper->isAdmin()) {
                    if ($id && $action == 'value') {
                        // Update only the value of a setting
                        $this->settingController->updateValue($id);
                    } else if ($id) {
                        // Update a setting completely
                        $this->settingController->update($id);
                    }
                }
                break;
            case 'DELETE':
                if ($id && $this->authHelper->validateToken() && $this->authHelper->isAdmin()) {
                    // Delete a setting
                    $this->settingController->delete($id);
                }
                break;
            default:
                $this->sendResponse(['message' => 'Method not allowed'], 405);
                break;
        }
    }

    // Helper function to send API responses
    public function sendResponse($data, $status_code = 200) {
        http_response_code($status_code);
        echo json_encode($data);
        exit;
    }
}
?> 