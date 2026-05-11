<?php
session_start();
include 'connection.php';

if (isset($_SESSION['user'])) {
    $username = $_SESSION['user'];
    
    if (isset($_GET['action']) && $_GET['action'] === 'offline') {
        // Mark session as offline
        $stmt = $conn->prepare("UPDATE user_sessions SET is_online = 0, last_activity = CURRENT_TIMESTAMP WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        
        // Record logout time in user_logs
        $log_stmt = $conn->prepare("
            UPDATE user_logs 
            SET logout_time = CURRENT_TIMESTAMP 
            WHERE username = ? 
            AND action = 'login' 
            AND logout_time IS NULL 
            ORDER BY login_time DESC 
            LIMIT 1
        ");
        $log_stmt->bind_param("s", $username);
        $log_stmt->execute();
        
        echo "Logged out";
    } else {
        // Regular ping - mark online
        $stmt = $conn->prepare("UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP, is_online = 1 WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        
        echo "Pong";
    }
}
?>