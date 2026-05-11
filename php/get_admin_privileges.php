<?php
// NO WHITESPACE BEFORE THIS!
ob_start(); // Start output buffering

// Set JSON header
header('Content-Type: application/json');

// Increase limits
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

// Error handling function
function returnJSON($data) {
    ob_clean();
    echo json_encode($data);
    exit();
}

try {
    session_start();
    include 'connection.php';
    
    // Check if user is logged in and is super admin
    if (!isset($_SESSION['user']) || $_SESSION['account_type'] !== 'super_admin') {
        returnJSON(['success' => false, 'error' => 'Unauthorized']);
    }

    if (!isset($_GET['username']) || empty($_GET['username'])) {
        returnJSON(['success' => false, 'error' => 'Username required']);
    }

    $username = $_GET['username'];

    // Get ONLY what we need - account type and control level
    $stmt = $conn->prepare("SELECT account_type, control_level FROM signinfo WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        returnJSON(['success' => false, 'error' => 'Admin not found']);
    }
    
    $admin = $result->fetch_assoc();

    // Get permissions - but only fetch the permission columns we need
    // Instead of SELECT *, list only the permission columns
    $perm_stmt = $conn->prepare("SELECT 
        can_add_product, can_edit_product, can_delete_product,
        can_create_user, can_edit_user, can_ban_user, can_delete_user, 
        can_approve_reject_user, can_reset_user_password,
        can_create_admin, can_edit_admin, can_ban_admin, can_delete_admin,
        can_reset_admin_password, can_view_product_logs, can_delete_product_logs,
        can_clear_session_logs, can_view_product_activity
        FROM admin_permissions WHERE username = ?");
    
    $perm_stmt->bind_param("s", $username);
    $perm_stmt->execute();
    $perm_result = $perm_stmt->get_result();
    
    $permissions = [];
    if ($perm_result->num_rows > 0) {
        $permissions = $perm_result->fetch_assoc();
    } else {
        // Default all to 0
        $permissions = [
            'can_add_product' => 0,
            'can_edit_product' => 0,
            'can_delete_product' => 0,
            'can_create_user' => 0,
            'can_edit_user' => 0,
            'can_ban_user' => 0,
            'can_delete_user' => 0,
            'can_approve_reject_user' => 0,
            'can_reset_user_password' => 0,
            'can_create_admin' => 0,
            'can_edit_admin' => 0,
            'can_ban_admin' => 0,
            'can_delete_admin' => 0,
            'can_reset_admin_password' => 0,
            'can_view_product_logs' => 0,
            'can_delete_product_logs' => 0,
            'can_clear_session_logs' => 0,
            'can_view_product_activity' => 0
        ];
    }

    // Send optimized response
    returnJSON([
        'success' => true,
        'type' => $admin['account_type'],
        'level' => $admin['control_level'],
        'perms' => $permissions
    ]);

} catch (Exception $e) {
    returnJSON(['success' => false, 'error' => 'Server error']);
}
?>