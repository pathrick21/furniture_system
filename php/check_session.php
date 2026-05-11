<?php
session_start();
include 'connection.php';

// Check if user should be force logged out
if (isset($_SESSION['user'])) {
    $username = $_SESSION['user'];
    
    $stmt = $conn->prepare("SELECT 1 FROM user_sessions WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        // Session doesn't exist in DB = force logout
        session_destroy();
        echo json_encode(['status' => 'logged_out']);
        exit;
    }
}

echo json_encode(['status' => 'active']);