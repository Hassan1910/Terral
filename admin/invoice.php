<?php
/**
 * Admin Invoice Generator
 * 
 * This page generates printable/downloadable invoices for orders.
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Start session for user authentication
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /Terral2/login.php');
    exit;
}

// Initialize variables
$order = null;
$orderItems = [];
$customer = null;
$errorMessage = '';

// Get order ID from URL parameter
$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($orderId <= 0) {
    $errorMessage = 'Invalid order ID.';
}

// Create database connection
$database = new Database();
$conn = $database->getConnection();

// Fetch order details
try {
    if ($orderId > 0) {
        // Get order information
        $orderSql = "SELECT o.*, o.total_price as total, p.payment_method, IFNULL(p.status, 'pending') as payment_status, p.transaction_id 
                    FROM orders o
                    LEFT JOIN payments p ON o.id = p.order_id
                    WHERE o.id = :order_id";
        
        $stmt = $conn->prepare($orderSql);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get customer information
            $customerSql = "SELECT * FROM users WHERE id = :user_id";
            $stmt = $conn->prepare($customerSql);
            $stmt->bindParam(':user_id', $order['user_id']);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Get order items
            $itemsSql = "SELECT oi.*, p.name, p.description
                        FROM order_items oi
                        LEFT JOIN products p ON oi.product_id = p.id
                        WHERE oi.order_id = :order_id";
            
            $stmt = $conn->prepare($itemsSql);
            $stmt->bindParam(':order_id', $orderId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $errorMessage = 'Order not found.';
        }
    }
} catch (PDOException $e) {
    $errorMessage = 'Database error: ' . $e->getMessage();
}

// Helper function for formatting currency
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        // Add null check to handle empty values
        if ($amount === null) {
            $amount = 0;
        }
        return 'KSh ' . number_format($amount, 2);
    }
}

// Get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'badge-warning';
        case 'processing':
            return 'badge-info';
        case 'shipped':
            return 'badge-primary';
        case 'delivered':
            return 'badge-success';
        case 'canceled':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}

// Company information
$companyName = 'Terral Online Production System';
$companyAddress = '123 Business Avenue, Meru, Kenya';
$companyEmail = 'info@terral.com';
$companyPhone = '+254 123 456 789';
$companyWebsite = 'www.terral.com';
$companyLogo = '/Terral2/assets/images/logo.png';

// Generate invoice number
$invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);

// Set content type as PDF if download requested
if (isset($_GET['download'])) {
    // Include mPDF library (you would need to install this via Composer)
    // For now, we'll just set the content type as text/html
    header('Content-Type: text/html');
    // In a real-world scenario, you would generate a PDF here
    // and send it to the browser for download
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $invoiceNumber; ?> - Terral</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        .invoice-container {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
            margin: 20px auto;
            max-width: 800px;
        }
        @media print {
            body {
                background-color: white;
            }
            .invoice-container {
                box-shadow: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
        .invoice-header {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .invoice-company-info {
            text-align: left;
        }
        .invoice-logo {
            max-height: 80px;
            margin-bottom: 10px;
        }
        .invoice-title {
            font-size: 28px;
            font-weight: 700;
            color: #3498db;
        }
        .invoice-details {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 30px;
        }
        .customer-details {
            margin-bottom: 30px;
        }
        .customer-details h3, .order-items h3 {
            font-size: 18px;
            font-weight: 600;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .order-items table {
            width: 100%;
        }
        .order-items th {
            background-color: #f8f9fa;
            padding: 10px;
            text-align: left;
        }
        .order-items td {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        .order-totals {
            margin-top: 30px;
            text-align: right;
        }
        .order-totals .total-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 5px;
        }
        .order-totals .total-label {
            width: 150px;
            text-align: right;
            margin-right: 15px;
            font-weight: 600;
        }
        .order-totals .total-value {
            width: 120px;
            text-align: right;
        }
        .grand-total {
            font-size: 18px;
            font-weight: 700;
            color: #3498db;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #f0f0f0;
        }
        .invoice-footer {
            margin-top: 50px;
            text-align: center;
            font-size: 0.9rem;
            color: #777;
        }
        .invoice-notes {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        .print-buttons {
            text-align: center;
            margin: 20px auto;
        }
    </style>
</head>
<body>
    <?php if (!empty($errorMessage)): ?>
        <div class="container mt-5">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
                <a href="orders.php" class="btn btn-sm btn-outline-secondary ml-3">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </a>
            </div>
        </div>
    <?php elseif ($order): ?>
        <div class="container">
            <div class="print-buttons no-print">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print mr-1"></i> Print Invoice
                </button>
                <a href="?order_id=<?php echo $orderId; ?>&download=pdf" class="btn btn-secondary ml-2">
                    <i class="fas fa-file-download mr-1"></i> Download PDF
                </a>
                <a href="order-details.php?id=<?php echo $orderId; ?>" class="btn btn-outline-secondary ml-2">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Order
                </a>
            </div>
            
            <div class="invoice-container">
                <!-- Invoice Header -->
                <div class="invoice-header row">
                    <div class="col-md-6 invoice-company-info">
                        <img src="<?php echo $companyLogo; ?>" alt="Company Logo" class="invoice-logo" onerror="this.style.display='none'">
                        <h1 class="invoice-title"><?php echo $companyName; ?></h1>
                        <p><?php echo $companyAddress; ?></p>
                        <p>
                            <strong>Email:</strong> <?php echo $companyEmail; ?><br>
                            <strong>Phone:</strong> <?php echo $companyPhone; ?><br>
                            <strong>Website:</strong> <?php echo $companyWebsite; ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-right">
                        <h2>INVOICE</h2>
                        <div class="invoice-details">
                            <p>
                                <strong>Invoice Number:</strong> <?php echo $invoiceNumber; ?><br>
                                <strong>Order Number:</strong> #<?php echo $order['order_number'] ?? $order['id']; ?><br>
                                <strong>Invoice Date:</strong> <?php echo date('F j, Y'); ?><br>
                                <strong>Order Date:</strong> <?php echo date('F j, Y', strtotime($order['created_at'])); ?><br>
                                <strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'Not specified')); ?><br>
                                <strong>Order Status:</strong> 
                                <span class="badge <?php echo getStatusBadgeClass($order['status']); ?>" style="padding: 5px 10px; border-radius: 4px;">
                                    <?php echo ucfirst($order['status'] ?? 'Pending'); ?>
                                </span><br>
                                <strong>Payment Status:</strong> 
                                <span class="badge <?php echo ($order['payment_status'] === 'paid') ? 'badge-success' : 'badge-warning'; ?>" style="padding: 5px 10px; border-radius: 4px;">
                                    <?php echo ucfirst($order['payment_status'] ?? 'Pending'); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Information -->
                <div class="row customer-details">
                    <div class="col-md-6">
                        <h3>Bill To</h3>
                        <p>
                            <strong><?php echo $customer['first_name'] . ' ' . $customer['last_name']; ?></strong><br>
                            <?php echo $customer['email']; ?><br>
                            <?php echo $customer['phone'] ?? ''; ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h3>Ship To</h3>
                        <p>
                            <?php echo $order['shipping_address'] ?? 'Same as billing address'; ?>
                        </p>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="order-items">
                    <h3>Order Items</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($orderItems)): ?>
                                <?php $itemNumber = 1; ?>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?php echo $itemNumber++; ?></td>
                                        <td>
                                            <strong><?php echo $item['name']; ?></strong>
                                            <?php if (!empty($item['product_code'])): ?>
                                                <div><small class="text-muted">Product Code: <?php echo $item['product_code']; ?></small></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatCurrency($item['price']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td class="text-right"><?php echo formatCurrency($item['price'] * $item['quantity']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No items found for this order.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Order Totals -->
                <div class="order-totals">
                    <div class="total-row">
                        <div class="total-label">Subtotal:</div>
                        <div class="total-value"><?php echo formatCurrency($order['subtotal']); ?></div>
                    </div>
                    <div class="total-row">
                        <div class="total-label">Shipping:</div>
                        <div class="total-value"><?php echo formatCurrency($order['shipping_cost']); ?></div>
                    </div>
                    <?php if (!empty($order['tax'])): ?>
                    <div class="total-row">
                        <div class="total-label">Tax:</div>
                        <div class="total-value"><?php echo formatCurrency($order['tax']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="total-row grand-total">
                        <div class="total-label">Total:</div>
                        <div class="total-value"><?php echo formatCurrency($order['total_price'] ?? $order['total'] ?? 0); ?></div>
                    </div>
                </div>
                
                <!-- Payment Information -->
                <div class="invoice-notes">
                    <h4>Payment Information</h4>
                    <p>
                        <strong>Order Status:</strong> <?php echo ucfirst($order['status'] ?? 'Pending'); ?><br>
                        <strong>Payment Method:</strong> <span style="font-weight: 600; color: #3498db;"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'Not specified')); ?></span><br>
                        <?php if (!empty($order['transaction_id'])): ?>
                            <strong>Transaction ID:</strong> <?php echo $order['transaction_id']; ?><br>
                        <?php endif; ?>
                        <strong>Payment Status:</strong> <?php echo ucfirst($order['payment_status'] ?? 'Pending'); ?>
                    </p>
                    
                    <h4>Notes</h4>
                    <p>Thank you for your business! If you have any questions about this invoice, please contact our customer support.</p>
                </div>
                
                <!-- Invoice Footer -->
                <div class="invoice-footer">
                    <p><?php echo $companyName; ?> &copy; <?php echo date('Y'); ?> - All rights reserved.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- No need for JS frameworks for the invoice page, keeping it simple -->
</body>
</html> 