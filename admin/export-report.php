<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reports.php');
    exit;
}

// Include necessary files
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/api/config/Database.php';
require_once ROOT_PATH . '/api/models/User.php';
require_once ROOT_PATH . '/api/models/Product.php';
require_once ROOT_PATH . '/api/models/Order.php';

// Get form data
$export_format = $_POST['export_format'] ?? 'csv';
$export_type = $_POST['export_type'] ?? 'overview';
$start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_POST['end_date'] ?? date('Y-m-d');

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Initialize models
$user = new User($db);
$product = new Product($db);
$order = new Order($db);

// Function to generate CSV export
function exportCSV($data, $filename, $headers) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// Function to generate PDF export
function exportPDF($content, $filename, $title) {
    // Simple HTML to PDF conversion
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .company-name { font-size: 24px; font-weight: bold; color: #333; }
            .report-title { font-size: 18px; color: #666; margin-top: 10px; }
            .date-range { font-size: 14px; color: #888; margin-top: 5px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .summary { background-color: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #888; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="company-name">TERRAL</div>
            <div class="report-title">' . htmlspecialchars($title) . '</div>
            <div class="date-range">Generated on ' . date('F j, Y g:i A') . '</div>
        </div>
        ' . $content . '
        <div class="footer">
            <p>This report was generated automatically by Terral Admin System</p>
        </div>
    </body>
    </html>';
    
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $html;
    exit;
}

try {
    switch ($export_type) {
        case 'overview':
            // Get overview data
            $total_users = $user->getCount();
            $total_products = $product->getCount();
            $total_orders = $order->getCount() ?? 0;
            
            // Get total revenue
            $revenueQuery = "SELECT COALESCE(SUM(total_price), 0) as total_revenue 
                             FROM orders 
                             WHERE (status IN ('delivered', 'shipped', 'processing') 
                                    OR payment_status = 'completed')
                             AND status != 'canceled'
                             AND DATE(created_at) BETWEEN ? AND ?";
            $stmt = $db->prepare($revenueQuery);
            $stmt->execute([$start_date, $end_date]);
            $revenueData = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalRevenue = $revenueData['total_revenue'];
            
            // Get top selling products
            $topProductsQuery = "SELECT p.name, SUM(oi.quantity) as quantity_sold, 
                                 SUM(oi.quantity * oi.price) as total_sales 
                                 FROM order_items oi 
                                 JOIN orders o ON oi.order_id = o.id 
                                 JOIN products p ON oi.product_id = p.id 
                                 WHERE o.status != 'canceled' 
                                 AND DATE(o.created_at) BETWEEN ? AND ?
                                 GROUP BY p.id, p.name 
                                 ORDER BY quantity_sold DESC 
                                 LIMIT 10";
            $stmt = $db->prepare($topProductsQuery);
            $stmt->execute([$start_date, $end_date]);
            $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($export_format === 'csv') {
                $data = [];
                $data[] = ['Metric', 'Value'];
                $data[] = ['Total Customers', $total_users];
                $data[] = ['Total Products', $total_products];
                $data[] = ['Total Orders', $total_orders];
                $data[] = ['Total Revenue (KSh)', number_format($totalRevenue, 2)];
                $data[] = ['', ''];
                $data[] = ['Top Selling Products', ''];
                $data[] = ['Product Name', 'Quantity Sold', 'Total Sales (KSh)'];
                
                foreach ($topProducts as $product) {
                    $data[] = [
                        $product['name'],
                        $product['quantity_sold'],
                        number_format($product['total_sales'], 2)
                    ];
                }
                
                exportCSV($data, 'overview_report_' . date('Y-m-d') . '.csv', []);
            } else {
                $content = '
                <div class="summary">
                    <h3>Overview Summary (' . $start_date . ' to ' . $end_date . ')</h3>
                    <p><strong>Total Customers:</strong> ' . number_format($total_users) . '</p>
                    <p><strong>Total Products:</strong> ' . number_format($total_products) . '</p>
                    <p><strong>Total Orders:</strong> ' . number_format($total_orders) . '</p>
                    <p><strong>Total Revenue:</strong> KSh ' . number_format($totalRevenue, 2) . '</p>
                </div>
                
                <h3>Top Selling Products</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Quantity Sold</th>
                            <th>Total Sales (KSh)</th>
                            <th>Average Price (KSh)</th>
                        </tr>
                    </thead>
                    <tbody>';
                
                foreach ($topProducts as $product) {
                    $avgPrice = $product['quantity_sold'] > 0 ? $product['total_sales'] / $product['quantity_sold'] : 0;
                    $content .= '
                        <tr>
                            <td>' . htmlspecialchars($product['name']) . '</td>
                            <td>' . number_format($product['quantity_sold']) . '</td>
                            <td>' . number_format($product['total_sales'], 2) . '</td>
                            <td>' . number_format($avgPrice, 2) . '</td>
                        </tr>';
                }
                
                $content .= '
                    </tbody>
                </table>';
                
                exportPDF($content, 'overview_report_' . date('Y-m-d') . '.html', 'Overview Report');
            }
            break;
            
        case 'sales':
            // Get sales data
            $salesQuery = "SELECT DATE(created_at) as sale_date, 
                           COUNT(*) as order_count,
                           SUM(total_price) as daily_revenue
                           FROM orders 
                           WHERE status != 'canceled'
                           AND DATE(created_at) BETWEEN ? AND ?
                           GROUP BY DATE(created_at)
                           ORDER BY sale_date DESC";
            $stmt = $db->prepare($salesQuery);
            $stmt->execute([$start_date, $end_date]);
            $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($export_format === 'csv') {
                $data = [];
                $headers = ['Date', 'Orders Count', 'Daily Revenue (KSh)'];
                
                foreach ($salesData as $sale) {
                    $data[] = [
                        $sale['sale_date'],
                        $sale['order_count'],
                        number_format($sale['daily_revenue'], 2)
                    ];
                }
                
                exportCSV($data, 'sales_report_' . date('Y-m-d') . '.csv', $headers);
            } else {
                $content = '
                <h3>Sales Report (' . $start_date . ' to ' . $end_date . ')</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Orders Count</th>
                            <th>Daily Revenue (KSh)</th>
                        </tr>
                    </thead>
                    <tbody>';
                
                $totalRevenue = 0;
                $totalOrders = 0;
                
                foreach ($salesData as $sale) {
                    $totalRevenue += $sale['daily_revenue'];
                    $totalOrders += $sale['order_count'];
                    $content .= '
                        <tr>
                            <td>' . date('M j, Y', strtotime($sale['sale_date'])) . '</td>
                            <td>' . number_format($sale['order_count']) . '</td>
                            <td>' . number_format($sale['daily_revenue'], 2) . '</td>
                        </tr>';
                }
                
                $content .= '
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f0f0f0; font-weight: bold;">
                            <td>TOTAL</td>
                            <td>' . number_format($totalOrders) . '</td>
                            <td>' . number_format($totalRevenue, 2) . '</td>
                        </tr>
                    </tfoot>
                </table>';
                
                exportPDF($content, 'sales_report_' . date('Y-m-d') . '.html', 'Sales Report');
            }
            break;
            
        case 'products':
            // Get product performance data
            $productsQuery = "SELECT p.name, p.price, p.stock_quantity,
                              COALESCE(SUM(oi.quantity), 0) as total_sold,
                              COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue
                              FROM products p
                              LEFT JOIN order_items oi ON p.id = oi.product_id
                              LEFT JOIN orders o ON oi.order_id = o.id
                              WHERE (o.created_at IS NULL OR DATE(o.created_at) BETWEEN ? AND ?)
                              AND (o.status IS NULL OR o.status != 'canceled')
                              GROUP BY p.id, p.name, p.price, p.stock_quantity
                              ORDER BY total_sold DESC";
            $stmt = $db->prepare($productsQuery);
            $stmt->execute([$start_date, $end_date]);
            $productsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($export_format === 'csv') {
                $data = [];
                $headers = ['Product Name', 'Price (KSh)', 'Stock Quantity', 'Units Sold', 'Total Revenue (KSh)'];
                
                foreach ($productsData as $product) {
                    $data[] = [
                        $product['name'],
                        number_format($product['price'], 2),
                        $product['stock_quantity'],
                        $product['total_sold'],
                        number_format($product['total_revenue'], 2)
                    ];
                }
                
                exportCSV($data, 'products_report_' . date('Y-m-d') . '.csv', $headers);
            } else {
                $content = '
                <h3>Product Performance Report (' . $start_date . ' to ' . $end_date . ')</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Price (KSh)</th>
                            <th>Stock Quantity</th>
                            <th>Units Sold</th>
                            <th>Total Revenue (KSh)</th>
                        </tr>
                    </thead>
                    <tbody>';
                
                foreach ($productsData as $product) {
                    $stockStatus = $product['stock_quantity'] < 10 ? 'style="background-color: #ffebee;"' : '';
                    $content .= '
                        <tr ' . $stockStatus . '>
                            <td>' . htmlspecialchars($product['name']) . '</td>
                            <td>' . number_format($product['price'], 2) . '</td>
                            <td>' . $product['stock_quantity'] . '</td>
                            <td>' . $product['total_sold'] . '</td>
                            <td>' . number_format($product['total_revenue'], 2) . '</td>
                        </tr>';
                }
                
                $content .= '
                    </tbody>
                </table>
                <p style="margin-top: 20px; font-size: 12px; color: #666;">
                    <strong>Note:</strong> Products with stock quantity less than 10 are highlighted in red.
                </p>';
                
                exportPDF($content, 'products_report_' . date('Y-m-d') . '.html', 'Product Performance Report');
            }
            break;
            
        case 'customers':
            // Get customer data
            $customersQuery = "SELECT u.first_name, u.last_name, u.email, u.created_at,
                               COUNT(o.id) as total_orders,
                               COALESCE(SUM(o.total_price), 0) as total_spent
                               FROM users u
                               LEFT JOIN orders o ON u.id = o.user_id 
                               WHERE u.role = 'customer'
                               AND (o.created_at IS NULL OR DATE(o.created_at) BETWEEN ? AND ?)
                               AND (o.status IS NULL OR o.status != 'canceled')
                               GROUP BY u.id, u.first_name, u.last_name, u.email, u.created_at
                               ORDER BY total_spent DESC";
            $stmt = $db->prepare($customersQuery);
            $stmt->execute([$start_date, $end_date]);
            $customersData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($export_format === 'csv') {
                $data = [];
                $headers = ['Customer Name', 'Email', 'Registration Date', 'Total Orders', 'Total Spent (KSh)'];
                
                foreach ($customersData as $customer) {
                    $data[] = [
                        $customer['first_name'] . ' ' . $customer['last_name'],
                        $customer['email'],
                        date('Y-m-d', strtotime($customer['created_at'])),
                        $customer['total_orders'],
                        number_format($customer['total_spent'], 2)
                    ];
                }
                
                exportCSV($data, 'customers_report_' . date('Y-m-d') . '.csv', $headers);
            } else {
                $content = '
                <h3>Customer Report (' . $start_date . ' to ' . $end_date . ')</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Customer Name</th>
                            <th>Email</th>
                            <th>Registration Date</th>
                            <th>Total Orders</th>
                            <th>Total Spent (KSh)</th>
                        </tr>
                    </thead>
                    <tbody>';
                
                foreach ($customersData as $customer) {
                    $content .= '
                        <tr>
                            <td>' . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . '</td>
                            <td>' . htmlspecialchars($customer['email']) . '</td>
                            <td>' . date('M j, Y', strtotime($customer['created_at'])) . '</td>
                            <td>' . $customer['total_orders'] . '</td>
                            <td>' . number_format($customer['total_spent'], 2) . '</td>
                        </tr>';
                }
                
                $content .= '
                    </tbody>
                </table>';
                
                exportPDF($content, 'customers_report_' . date('Y-m-d') . '.html', 'Customer Report');
            }
            break;
            
        default:
            throw new Exception('Invalid export type');
    }
    
} catch (Exception $e) {
    // Handle errors
    header('Content-Type: text/html');
    echo '<script>alert("Error generating report: ' . addslashes($e->getMessage()) . '"); window.close();</script>';
    exit;
}
?>