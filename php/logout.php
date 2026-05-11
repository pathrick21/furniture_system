<?php
session_start();
include 'connection.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_SESSION['user'])) {
    $username = $_SESSION['user'];
    $session_id = session_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    try {
        // 1. Insert logout record (optional - tracks that logout occurred)
        $logout_stmt = $conn->prepare("INSERT INTO user_logs (username, action, ip_address, session_id) VALUES (?, 'logout', ?, ?)");
        $logout_stmt->bind_param("sss", $username, $ip, $session_id);
        $logout_stmt->execute();
        
        // 2. UPDATE the most recent login record that doesn't have a logout time yet
        $update_stmt = $conn->prepare("
            UPDATE user_logs 
            SET logout_time = CURRENT_TIMESTAMP 
            WHERE username = ? 
            AND action = 'login' 
            AND logout_time IS NULL 
            ORDER BY login_time DESC 
            LIMIT 1
        ");
        $update_stmt->bind_param("s", $username);
        $update_stmt->execute();
        
        // Debug: Check if update worked
        $affected_rows = $update_stmt->affected_rows;
        if ($affected_rows === 0) {
            // Log to error log why it failed
            error_log("Logout update failed for user: $username - No matching login row found");
            
            // Alternative: Force update the most recent login regardless
            $force_update = $conn->prepare("
                UPDATE user_logs 
                SET logout_time = CURRENT_TIMESTAMP 
                WHERE username = ? 
                AND action = 'login' 
                ORDER BY login_time DESC 
                LIMIT 1
            ");
            $force_update->bind_param("s", $username);
            $force_update->execute();
        }
        
        // 3. Mark session as offline
        $sess_stmt = $conn->prepare("UPDATE user_sessions SET is_online = 0, last_activity = CURRENT_TIMESTAMP WHERE username = ?");
        $sess_stmt->bind_param("s", $username);
        $sess_stmt->execute();
        
        // 4. Delete from user_sessions to ensure complete logout
        $del_stmt = $conn->prepare("DELETE FROM user_sessions WHERE username = ?");
        $del_stmt->bind_param("s", $username);
        $del_stmt->execute();
        
    } catch (Exception $e) {
        error_log("Logout error: " . $e->getMessage());
    }
}

// Store message parameter before destroying session
$redirect_url = "login.php";
if (isset($_GET['force_logout'])) {
    $redirect_url = "login.php?force_logout=1";
} elseif (isset($_GET['banned'])) {
    $redirect_url = "login.php?banned=1";
}

session_destroy();

// Redirect with appropriate message
header("Location: " . $redirect_url);
exit();
?>