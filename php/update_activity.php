<?php
session_start();
include 'connection.php';

if (isset($_SESSION['user'])) {
    $username = $_SESSION['user'];
    
    // Update last activity
    $stmt = $conn->prepare("UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP, is_online = 1 WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    
    echo json_encode(['status' => 'updated']);
}
?>