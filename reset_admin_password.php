<?php
// Define root path
define('ROOT_PATH', __DIR__);

// Include necessary files
require_once ROOT_PATH . '/api/config/Database.php';

// Set admin credentials
$admin_email = 'admin@terral.com';
$new_password = 'admin123';
$hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Update admin password
    $query = "UPDATE users SET password = :password WHERE email = :email AND role = 'admin'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':email', $admin_email);
    
    if ($stmt->execute()) {
        echo "Admin password reset successfully to: " . $new_password;
        echo "<br>Hashed password: " . $hashed_password;
        echo "<br><br>You can now login with email: " . $admin_email . " and password: " . $new_password;
        echo "<br><br><a href='login.php'>Go to Login Page</a>";
    } else {
        echo "Failed to reset admin password.";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?> 