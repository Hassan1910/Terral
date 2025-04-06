<?php
/**
 * Settings Table Schema Update Script
 * This script will create the settings table and add default settings
 */

// Include database connection
require_once 'api/config/Database.php';
$database = new Database();
$conn = $database->getConnection();

// Function to check if settings table exists
function tableExists($conn, $table) {
    try {
        $result = $conn->query("SHOW TABLES LIKE '{$table}'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Function to check if column exists
function columnExists($conn, $table, $column) {
    try {
        $result = $conn->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Create settings table if it doesn't exist
if (!tableExists($conn, 'settings')) {
    echo "Creating settings table...<br>";
    
    // Load SQL from file
    $sql = file_get_contents('api/database/settings_table.sql');
    
    // Split SQL script into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)), 'strlen');
    
    // Execute each statement
    try {
        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
        
        foreach ($statements as $statement) {
            $stmt = $conn->prepare($statement);
            $stmt->execute();
            echo "Executed: " . substr($statement, 0, 50) . "...<br>";
        }
        
        echo "<strong>Settings table created successfully!</strong><br>";
    } catch (PDOException $e) {
        echo "<strong>Error creating settings table:</strong> " . $e->getMessage() . "<br>";
        die();
    }
} else {
    echo "Settings table already exists.<br>";
    
    // Check if sort_order column exists and add it if missing
    if (!columnExists($conn, 'settings', 'sort_order')) {
        echo "Adding missing sort_order column...<br>";
        try {
            $conn->exec("ALTER TABLE settings ADD COLUMN sort_order INT NOT NULL DEFAULT 0");
            echo "<strong>Added sort_order column successfully!</strong><br>";
        } catch (PDOException $e) {
            echo "<strong>Error adding sort_order column:</strong> " . $e->getMessage() . "<br>";
        }
    } else {
        echo "sort_order column already exists.<br>";
    }
}

// Create uploads directory for settings if it doesn't exist
$uploads_dir = 'uploads/settings';
if (!file_exists($uploads_dir)) {
    if (mkdir($uploads_dir, 0755, true)) {
        echo "Created directory for settings uploads: {$uploads_dir}<br>";
    } else {
        echo "Failed to create directory: {$uploads_dir}<br>";
    }
} else {
    echo "Uploads directory for settings already exists.<br>";
}

echo "<br>Schema update completed.<br>";
echo "<a href='admin/settings.php'>Go to Settings Page</a>";
?> 