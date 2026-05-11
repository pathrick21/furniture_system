<?php
include 'connection.php';

echo "<h2>Last 10 logs in user_logs table:</h2>";
$result = $conn->query("SELECT * FROM user_logs ORDER BY login_time DESC LIMIT 10");
echo "<pre>";
while($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . "\n";
    echo "Admin: " . $row['username'] . "\n";
    echo "Action: " . $row['action'] . "\n";
    echo "Target User: " . ($row['target_user'] ?? 'NULL') . "\n";
    echo "Target Name: " . ($row['target_name'] ?? 'NULL') . "\n";
    echo "IP: " . $row['ip_address'] . "\n";
    echo "Time: " . $row['login_time'] . "\n";
    echo "------------------------\n";
}
echo "</pre>";
?>