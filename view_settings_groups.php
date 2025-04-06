<?php
// Include database connection
require_once 'api/config/Database.php';
$database = new Database();
$conn = $database->getConnection();

// Query to get all setting groups
$stmt = $conn->query("SELECT DISTINCT setting_group FROM settings");

// Display results
echo "<h2>Settings Groups in Database</h2>";
echo "<ul>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<li>" . $row['setting_group'] . "</li>";
}
echo "</ul>";

// Query to get all settings
$stmt = $conn->query("SELECT setting_key, setting_value, setting_type, setting_label, setting_group FROM settings ORDER BY setting_group");

// Display results
echo "<h2>Settings in Database</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Group</th><th>Key</th><th>Label</th><th>Type</th><th>Value</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['setting_group'] . "</td>";
    echo "<td>" . $row['setting_key'] . "</td>";
    echo "<td>" . $row['setting_label'] . "</td>";
    echo "<td>" . $row['setting_type'] . "</td>";
    echo "<td>" . (strlen($row['setting_value']) > 50 ? substr($row['setting_value'], 0, 50) . "..." : $row['setting_value']) . "</td>";
    echo "</tr>";
}
echo "</table>";
?> 