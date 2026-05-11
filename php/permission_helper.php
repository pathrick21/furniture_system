<?php
// Permission Helper Functions
// Include this file in all admin pages after connection.php

function loadPermissions($conn, $username) {
    $stmt = $conn->prepare("SELECT * FROM admin_permissions WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $perms = $result->fetch_assoc();
        foreach ($perms as $key => $value) {
            if (strpos($key, 'can_') === 0) {
                $perms[$key] = (bool)$value;
            }
        }
        return $perms;
    }
    
    // Return default limited permissions if no record exists
    return [
        'can_clear_session_logs' => false,
        'can_view_online_users' => true,
        'can_view_login_history' => true,
        'can_view_admin_activity' => false,
        'can_add_product' => false,
        'can_edit_product' => false,
        'can_delete_product' => false,
        'can_view_product_activity' => true,
        'can_view_users' => true,
        'can_edit_user' => false,
        'can_ban_user' => false,
        'can_delete_user' => false,
        'can_approve_reject_user' => false,
        'can_force_logout_user' => false,
        'can_reset_user_password' => false,
        'can_create_user' => false,        // <-- ADD THIS LINE
        'can_create_admin' => false,
        'can_edit_admin' => false,
        'can_ban_admin' => false,
        'can_delete_admin' => false,
        'can_reset_admin_password' => false,
        'can_view_product_logs' => true,
        'can_delete_product_logs' => false
    ];
}

function hasPermission($permissions, $permission_name) {
    return isset($permissions[$permission_name]) && $permissions[$permission_name] === true;
}

function requirePermission($permissions, $permission_name, $redirect_url = 'login.php') {
    if (!hasPermission($permissions, $permission_name)) {
        header("Location: $redirect_url?error=access_denied");
        exit();
    }
}
?>