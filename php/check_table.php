<?php
include 'connection.php';

echo "<h2>Structure of user_logs table:</h2>";
$result = $conn->query("DESCRIBE user_logs");
echo "<pre>";
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "</pre>";
?>