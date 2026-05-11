<?php
session_start();
include 'connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['banned' => false, 'session_expired' => false, 'logged_in' => false]);
    exit;
}

$username = $_SESSION['user'];

// Check if user is banned
$check_stmt = $conn->prepare("SELECT status FROM signinfo WHERE username = ?");
$check_stmt->bind_param("s", $username);
$check_stmt->execute();
$result = $check_stmt->get_result()->fetch_assoc();

// Check if session still exists in user_sessions (for force logout)
$session_stmt = $conn->prepare("SELECT username FROM user_sessions WHERE username = ?");
$session_stmt->bind_param("s", $username);
$session_stmt->execute();
$session_exists = $session_stmt->get_result()->num_rows > 0;

// If session doesn't exist in user_sessions, it means admin force logged out
if (!$session_exists) {
    // Destroy session
    $_SESSION = array();
    session_destroy();
    
    echo json_encode([
        'banned' => false, 
        'session_expired' => true, 
        'logged_in' => false
    ]);
    exit;
}

// Check for ban
if ($result && $result['status'] === 'banned') {
    // Destroy session
    $_SESSION = array();
    session_destroy();
    
    echo json_encode([
        'banned' => true, 
        'session_expired' => false, 
        'logged_in' => false
    ]);
    exit;
}

// All good - user is logged in, not banned, and session exists
echo json_encode([
    'banned' => false, 
    'session_expired' => false, 
    'logged_in' => true
]);
?>