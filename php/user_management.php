<?php
session_start();
include 'connection.php';
include 'device_helper.php';
include 'permission_helper.php';  // ADD THIS

// Check if user is still valid (not deleted or banned)
if (isset($_SESSION['user'])) {
    $check_user = $conn->prepare("SELECT status FROM signinfo WHERE username = ?");
    $check_user->bind_param("s", $_SESSION['user']);
    $check_user->execute();
    $result = $check_user->get_result();
    
    if ($result->num_rows == 0) {
        session_destroy();
        header("Location: login.php?error=account_deleted");
        exit();
    }
    
    $user = $result->fetch_assoc();
    if ($user['status'] === 'banned' || $user['status'] === 'deleted') {
        session_destroy();
        header("Location: login.php?error=account_inactive");
        exit();
    }
}


// Allow both super_admin and admin
if (!isset($_SESSION['user']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['user'];
$account_type = $_SESSION['account_type'];
$is_super_admin = ($account_type === 'super_admin');
$is_admin = ($account_type === 'admin');

// ===== FORCE REFRESH control_level FROM DATABASE =====
$refresh_stmt = $conn->prepare("SELECT control_level FROM signinfo WHERE username = ?");
$refresh_stmt->bind_param("s", $current_user);
$refresh_stmt->execute();
$refresh_result = $refresh_stmt->get_result()->fetch_assoc();
$control_level = $refresh_result['control_level'] ?? 'limited';
$_SESSION['control_level'] = $control_level;

// Define mode variables
$is_god_mode = ($account_type === 'super_admin' && $control_level === 'full');
$is_manager_mode = ($account_type === 'admin' && $control_level === 'full');
$is_viewer_mode = ($account_type === 'admin' && $control_level === 'limited');
$is_super_admin_limited = ($account_type === 'super_admin' && $control_level === 'limited');

// Load permissions
$permissions = loadPermissions($conn, $current_user);

$can_edit_user = hasPermission($permissions, 'can_edit_user');
$can_ban_user = hasPermission($permissions, 'can_ban_user');
$can_delete_user = hasPermission($permissions, 'can_delete_user');
$can_approve_user = hasPermission($permissions, 'can_approve_reject_user');
$can_force_logout = hasPermission($permissions, 'can_force_logout_user');
$can_reset_password = hasPermission($permissions, 'can_reset_user_password');

$can_create_admin = hasPermission($permissions, 'can_create_admin');
$can_edit_admin = hasPermission($permissions, 'can_edit_admin');
$can_ban_admin = hasPermission($permissions, 'can_ban_admin');
$can_delete_admin = hasPermission($permissions, 'can_delete_admin');
$can_reset_admin_password = hasPermission($permissions, 'can_reset_admin_password');

$can_create_user = hasPermission($permissions, 'can_create_user') || $is_god_mode || $is_manager_mode;

$has_any_permission = ($can_edit_user || $can_ban_user || $can_delete_user || 
                      $can_approve_user || $can_force_logout || $can_reset_password || 
                      $can_create_user);

if (!$is_super_admin && !$is_admin) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'updated') {
        $message = "User updated successfully!";
    } elseif ($_GET['success'] === 'password_changed') {
        $message = "🔑 Password changed successfully!";
    } elseif ($_GET['success'] === 'created') {
        $message = "👤 New user created successfully! Default password is <strong>password123</strong> — the user should change it after first login.";
    }
}

// Handle Edit User
if (isset($_POST['edit_user'])) {
    if (!$can_edit_user) {
        $error = "Access Denied: You don't have permission to edit users.";
    } else {
        $user_id = $_POST['user_id'];
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $mi = trim($_POST['mi'] ?? '');
        $Ename = trim($_POST['Ename'] ?? '');
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $sex = $_POST['sex'];
        $bd = $_POST['bd'];
        $Purok = trim($_POST['Purok'] ?? '');
        $Barranggay = trim($_POST['Barranggay'] ?? '');
        $zip = trim($_POST['zip'] ?? '');
        $CM = trim($_POST['CM'] ?? '');
        $Province = trim($_POST['Province'] ?? '');
        $Country = trim($_POST['Country'] ?? 'Philippines');
        
        $old_data_stmt = $conn->prepare("SELECT fname, lname, mi, Ename, username, email, sex, bd, 
                                                Purok, Barranggay, zip, CM, Province, Country 
                                         FROM signinfo WHERE id_main = ?");
        $old_data_stmt->bind_param("s", $user_id);
        $old_data_stmt->execute();
        $old_data = $old_data_stmt->get_result()->fetch_assoc();
        
        $check_user = $conn->prepare("SELECT id_main FROM signinfo WHERE username = ? AND id_main != ?");
        $check_user->bind_param("ss", $username, $user_id);
        $check_user->execute();
        if ($check_user->get_result()->num_rows > 0) {
            $error = "Username already taken!";
        } else {
            $check_email = $conn->prepare("SELECT id_main FROM signinfo WHERE email = ? AND id_main != ?");
            $check_email->bind_param("ss", $email, $user_id);
            $check_email->execute();
            if ($check_email->get_result()->num_rows > 0) {
                $error = "Email already registered!";
            } else {
                $stmt = $conn->prepare("UPDATE signinfo SET 
                    fname=?, lname=?, mi=?, Ename=?, username=?, email=?, 
                    sex=?, bd=?, Purok=?, Barranggay=?, zip=?, CM=?, 
                    Province=?, Country=?
                    WHERE id_main=?");
                    
                $stmt->bind_param("sssssssssssssss", 
                    $fname, $lname, $mi, $Ename, $username, $email,
                    $sex, $bd, $Purok, $Barranggay, $zip, $CM,
                    $Province, $Country, $user_id
                );
                
                if ($stmt->execute()) {
                    $changes = [];
                    $changed_fields = [];
                    
                    if ($old_data['fname'] !== $fname) { $changes['first_name'] = ['old' => $old_data['fname'], 'new' => $fname]; $changed_fields[] = 'first name'; }
                    if ($old_data['lname'] !== $lname) { $changes['last_name'] = ['old' => $old_data['lname'], 'new' => $lname]; $changed_fields[] = 'last name'; }
                    if (($old_data['mi'] ?? '') !== ($mi ?? '')) { $changes['middle_initial'] = ['old' => $old_data['mi'] ?? '', 'new' => $mi ?? '']; $changed_fields[] = 'middle initial'; }
                    if (($old_data['Ename'] ?? '') !== ($Ename ?? '')) { $changes['extension'] = ['old' => $old_data['Ename'] ?? '', 'new' => $Ename ?? '']; $changed_fields[] = 'name extension'; }
                    if ($old_data['username'] !== $username) { $changes['username'] = ['old' => $old_data['username'], 'new' => $username]; $changed_fields[] = 'username'; }
                    if ($old_data['email'] !== $email) { $changes['email'] = ['old' => $old_data['email'], 'new' => $email]; $changed_fields[] = 'email'; }
                    if ($old_data['sex'] !== $sex) { $changes['sex'] = ['old' => $old_data['sex'], 'new' => $sex]; $changed_fields[] = 'sex'; }
                    if ($old_data['bd'] !== $bd) { $changes['birth_date'] = ['old' => $old_data['bd'], 'new' => $bd]; $changed_fields[] = 'birth date'; }
                    if (($old_data['Purok'] ?? '') !== ($Purok ?? '')) { $changes['purok'] = ['old' => $old_data['Purok'] ?? '', 'new' => $Purok ?? '']; $changed_fields[] = 'purok/street'; }
                    if (($old_data['Barranggay'] ?? '') !== ($Barranggay ?? '')) { $changes['barangay'] = ['old' => $old_data['Barranggay'] ?? '', 'new' => $Barranggay ?? '']; $changed_fields[] = 'barangay'; }
                    if (($old_data['zip'] ?? '') !== ($zip ?? '')) { $changes['zip'] = ['old' => $old_data['zip'] ?? '', 'new' => $zip ?? '']; $changed_fields[] = 'zip code'; }
                    if (($old_data['CM'] ?? '') !== ($CM ?? '')) { $changes['city'] = ['old' => $old_data['CM'] ?? '', 'new' => $CM ?? '']; $changed_fields[] = 'city/municipality'; }
                    if (($old_data['Province'] ?? '') !== ($Province ?? '')) { $changes['province'] = ['old' => $old_data['Province'] ?? '', 'new' => $Province ?? '']; $changed_fields[] = 'province'; }
                    if (($old_data['Country'] ?? '') !== ($Country ?? '')) { $changes['country'] = ['old' => $old_data['Country'] ?? '', 'new' => $Country ?? '']; $changed_fields[] = 'country'; }
                    
                    $user_fullname = $fname . ' ' . $lname;
                    
                    if (!empty($changes)) {
                        $changes_json = json_encode($changes);
                        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                        
                        $log_sql = "INSERT INTO user_logs (username, action, target_user, target_name, changes, ip_address, device_info, browser, os, login_time) VALUES (?, 'edited_user', ?, ?, ?, ?, 'Admin Panel', 'System', 'System', NOW())";
                        $log_stmt = $conn->prepare($log_sql);
                        if ($log_stmt) {
                            $log_stmt->bind_param("sssss", $current_user, $username, $user_fullname, $changes_json, $ip);
                            $log_stmt->execute();
                        }
                    }
                    
                    $message = "User updated successfully!";
                    header("Location: user_management.php?success=updated");
                    exit();
                } else {
                    $error = "Failed to update user: " . $conn->error;
                }
            }
        }
    }
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reset Password
    if (isset($_POST['reset_password'])) {
        if (!$can_reset_password) {
            $error = "Access Denied: You don't have permission to reset passwords.";
        } else {
            $user_id = $_POST['user_id'];
            $new_pass = password_hash('password123', PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE signinfo SET password=? WHERE id_main=?");
            $stmt->bind_param("ss", $new_pass, $user_id);
            
            if ($stmt->execute()) {
                $message = "Password reset to 'password123'";
            }
        }
    }
    
    // Handle Change Password
    if (isset($_POST['change_password'])) {
        if (!$can_reset_password) {
            $error = "Access Denied: You don't have permission to change passwords.";
        } else {
            $user_id = $_POST['user_id'];
            $new_password = $_POST['new_password'];
            
            if (strlen($new_password) < 8) {
                $error = "Password must be at least 8 characters long.";
            } else {
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE signinfo SET password=? WHERE id_main=?");
                $stmt->bind_param("ss", $hashedPassword, $user_id);
                
                if ($stmt->execute()) {
                    header("Location: user_management.php?success=password_changed");
                    exit();
                } else {
                    $error = "Failed to change password.";
                }
            }
        }
    }
}

// Handle Create User
// =====================================================================
// SECURITY QUESTIONS ARE NO LONGER SET BY ADMIN.
// Default password is always "password123".
// The user sets their own security questions from their profile page.
// =====================================================================
if (isset($_POST['create_user_modal'])) {
    if (!$can_create_user) {
        $error = "Access Denied: You don't have permission to create users.";
    } else {
        $id_main   = trim($_POST['id_main']);
        $fname     = trim($_POST['fname']);
        $lname     = trim($_POST['lname']);
        $mi        = trim($_POST['mi'] ?? '');
        $Ename     = trim($_POST['Ename'] ?? '');
        $sex       = $_POST['sex'];
        $bd        = $_POST['bd'];
        $username  = trim($_POST['username']);
        $email     = trim($_POST['email']);
        $Purok     = trim($_POST['Purok'] ?? '');
        $Barranggay= trim($_POST['Barranggay'] ?? '');
        $zip       = trim($_POST['zip'] ?? '');
        $CM        = trim($_POST['CM'] ?? '');
        $Province  = trim($_POST['Province'] ?? '');
        $Country   = trim($_POST['Country'] ?? 'Philippines');

        // Default password — user must change on first login
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);

        // Check if username or email already exists
        $check = $conn->prepare("SELECT id_main FROM signinfo WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error = "Username or email already exists!";
        } else {
            // Insert user WITHOUT security questions — user sets them on their own profile
            $stmt = $conn->prepare("INSERT INTO signinfo (
                id_main, fname, lname, mi, Ename, sex, bd, username, email, password, 
                account_type, status, Purok, Barranggay, zip, CM, Province, Country, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', 'pending', ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->bind_param("ssssssssssssssss", 
                $id_main, $fname, $lname, $mi, $Ename, $sex, $bd, $username, $email, $hashedPassword,
                $Purok, $Barranggay, $zip, $CM, $Province, $Country
            );
            
            if ($stmt->execute()) {
                // Log the create action
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $user_fullname = $fname . ' ' . $lname;
                
                $log_sql = "INSERT INTO user_logs (username, action, target_user, target_name, ip_address, login_time) 
                            VALUES (?, 'created_user', ?, ?, ?, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bind_param("ssss", $current_user, $username, $user_fullname, $ip);
                $log_stmt->execute();
                
                header("Location: user_management.php?success=created");
                exit();
            } else {
                $error = "Failed to create user: " . $conn->error;
            }
        }
    }
}


// Handle Get Actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    // Ban User
    if ($_GET['action'] === 'ban') {
        if (!$can_ban_user) {
            $error = "Access Denied: You don't have permission to ban users.";
        } else {
            $user_stmt = $conn->prepare("SELECT username, fname, lname FROM signinfo WHERE id_main=?");
            $user_stmt->bind_param("s", $user_id);
            $user_stmt->execute();
            $result = $user_stmt->get_result()->fetch_assoc();
            
            if ($result) {
                $username = $result['username'];
                $user_fullname = $result['fname'] . ' ' . $result['lname'];
                
                $stmt = $conn->prepare("UPDATE signinfo SET status='banned' WHERE id_main=?");
                $stmt->bind_param("s", $user_id);
                
                if ($stmt->execute()) {
                    $sess_stmt = $conn->prepare("DELETE FROM user_sessions WHERE username = ?");
                    $sess_stmt->bind_param("s", $username);
                    $sess_stmt->execute();
                    
                    $log_stmt = $conn->prepare("UPDATE user_logs SET logout_time = CURRENT_TIMESTAMP WHERE username = ? AND action = 'login' AND logout_time IS NULL");
                    $log_stmt->bind_param("s", $username);
                    $log_stmt->execute();
                    
                    $message = "User '$username' has been banned and logged out!";
                    
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                    $log_sql = "INSERT INTO user_logs (username, action, target_user, target_name, ip_address, login_time) VALUES (?, 'banned_user', ?, ?, ?, NOW())";
                    $log_stmt2 = $conn->prepare($log_sql);
                    if ($log_stmt2) {
                        $log_stmt2->bind_param("ssss", $current_user, $username, $user_fullname, $ip);
                        $log_stmt2->execute();
                    }
                } else {
                    $error = "Failed to ban user.";
                }
            } else {
                $error = "User not found.";
            }
        }
    }
        
    // Unban User
    if ($_GET['action'] === 'unban') {
        if (!$can_ban_user) {
            $error = "Access Denied: You don't have permission to unban users.";
        } else {
            $user_stmt = $conn->prepare("SELECT username, fname, lname FROM signinfo WHERE id_main=?");
            $user_stmt->bind_param("s", $user_id);
            $user_stmt->execute();
            $result = $user_stmt->get_result()->fetch_assoc();
            
            if ($result) {
                $username = $result['username'];
                $user_fullname = $result['fname'] . ' ' . $result['lname'];
                
                $stmt = $conn->prepare("UPDATE signinfo SET status='active' WHERE id_main=?");
                $stmt->bind_param("s", $user_id);
                
                if ($stmt->execute()) {
                    $message = "User '$username' has been unbanned!";
                    
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                    $log_sql = "INSERT INTO user_logs (username, action, target_user, target_name, ip_address, login_time) VALUES (?, 'unbanned_user', ?, ?, ?, NOW())";
                    $log_stmt = $conn->prepare($log_sql);
                    if ($log_stmt) {
                        $log_stmt->bind_param("ssss", $current_user, $username, $user_fullname, $ip);
                        $log_stmt->execute();
                    }
                } else {
                    $error = "Failed to unban user.";
                }
            } else {
                $error = "User not found.";
            }
        }
    }

    // Force Logout
    if ($_GET['action'] === 'logout') {
        if (!$can_force_logout) {
            $error = "Access Denied: You don't have permission to force logout users.";
        } else {
            $user_stmt = $conn->prepare("SELECT username, fname, lname FROM signinfo WHERE id_main=?");
            $user_stmt->bind_param("s", $user_id);
            $user_stmt->execute();
            $result = $user_stmt->get_result()->fetch_assoc();
            
            if ($result) {
                $username = $result['username'];
                $user_fullname = $result['fname'] . ' ' . $result['lname'];
                
                $del_stmt = $conn->prepare("DELETE FROM user_sessions WHERE username = ?");
                $del_stmt->bind_param("s", $username);
                $del_stmt->execute();
                
                $update_log = $conn->prepare("UPDATE user_logs SET logout_time = NOW() WHERE username = ? AND action = 'login' AND logout_time IS NULL ORDER BY login_time DESC LIMIT 1");
                $update_log->bind_param("s", $username);
                $update_log->execute();
                
                $message = "User '$username' has been forced to logout!";
                
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $log_sql = "INSERT INTO user_logs (username, action, target_user, target_name, ip_address, login_time) VALUES (?, 'force_logout_user', ?, ?, ?, NOW())";
                $admin_log = $conn->prepare($log_sql);
                if ($admin_log) {
                    $admin_log->bind_param("ssss", $current_user, $username, $user_fullname, $ip);
                    $admin_log->execute();
                }
            } else {
                $error = "User not found.";
            }
        }
    }

    // Delete User
    if ($_GET['action'] === 'delete') {
        if (!$can_delete_user) {
            $error = "Access Denied: You don't have permission to delete users.";
        } else {
            $user_stmt = $conn->prepare("SELECT username, fname, lname FROM signinfo WHERE id_main=?");
            $user_stmt->bind_param("s", $user_id);
            $user_stmt->execute();
            $result = $user_stmt->get_result()->fetch_assoc();
            
            if ($result) {
                $username = $result['username'];
                $user_fullname = $result['fname'] . ' ' . $result['lname'];
                
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $log_sql = "INSERT INTO user_logs (username, action, target_user, target_name, ip_address, login_time) VALUES (?, 'deleted_user', ?, ?, ?, NOW())";
                $admin_log = $conn->prepare($log_sql);
                $admin_log->bind_param("ssss", $current_user, $username, $user_fullname, $ip);
                $admin_log->execute();
                
                $del_orders = $conn->prepare("DELETE FROM orders WHERE username = ?");
                $del_orders->bind_param("s", $username);
                $del_orders->execute();
                
                $sess_stmt = $conn->prepare("DELETE FROM user_sessions WHERE username = ?");
                $sess_stmt->bind_param("s", $username);
                $sess_stmt->execute();
                
                $log_stmt = $conn->prepare("DELETE FROM user_logs WHERE username = ?");
                $log_stmt->bind_param("s", $username);
                $log_stmt->execute();
                
                $stmt = $conn->prepare("DELETE FROM signinfo WHERE id_main=?");
                $stmt->bind_param("s", $user_id);
                
                if ($stmt->execute()) {
                    $message = "User '$username' and all their data deleted permanently!";
                } else {
                    $error = "Failed to delete user.";
                }
            } else {
                $error = "User not found.";
            }
        }
    }

    // Approve User
    if ($_GET['action'] === 'approve') {
        if (!$can_approve_user) {
            $error = "Access Denied: You don't have permission to approve users.";
        } else {
            $user_stmt = $conn->prepare("SELECT username, fname, lname FROM signinfo WHERE id_main=?");
            $user_stmt->bind_param("s", $user_id);
            $user_stmt->execute();
            $result = $user_stmt->get_result()->fetch_assoc();
            
            if ($result) {
                $username = $result['username'];
                $user_fullname = $result['fname'] . ' ' . $result['lname'];
                
                $stmt = $conn->prepare("UPDATE signinfo SET status='active' WHERE id_main=?");
                $stmt->bind_param("s", $user_id);
                
                if ($stmt->execute()) {
                    $message = "User '$username' has been approved and can now log in!";
                    
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                    $log_sql = "INSERT INTO user_logs (username, action, target_user, target_name, ip_address, login_time) VALUES (?, 'approved_user', ?, ?, ?, NOW())";
                    $log_stmt = $conn->prepare($log_sql);
                    if ($log_stmt) {
                        $log_stmt->bind_param("ssss", $current_user, $username, $user_fullname, $ip);
                        $log_stmt->execute();
                    }
                } else {
                    $error = "Failed to approve user.";
                }
            } else {
                $error = "User not found.";
            }
        }
    }
    
    // Reject User
    if ($_GET['action'] === 'reject') {
        if (!$can_approve_user) {
            $error = "Access Denied: You don't have permission to reject users.";
        } else {
            $user_stmt = $conn->prepare("SELECT username, fname, lname FROM signinfo WHERE id_main=?");
            $user_stmt->bind_param("s", $user_id);
            $user_stmt->execute();
            $result = $user_stmt->get_result()->fetch_assoc();
            
            if ($result) {
                $username = $result['username'];
                $user_fullname = $result['fname'] . ' ' . $result['lname'];
                
                $stmt = $conn->prepare("UPDATE signinfo SET status='rejected' WHERE id_main=?");
                $stmt->bind_param("s", $user_id);
                
                if ($stmt->execute()) {
                    $message = "User '$username' registration has been rejected!";
                    
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                    $log_sql = "INSERT INTO user_logs (username, action, target_user, target_name, ip_address, login_time) VALUES (?, 'rejected_user', ?, ?, ?, NOW())";
                    $log_stmt = $conn->prepare($log_sql);
                    if ($log_stmt) {
                        $log_stmt->bind_param("ssss", $current_user, $username, $user_fullname, $ip);
                        $log_stmt->execute();
                    }
                } else {
                    $error = "Failed to reject user.";
                }
            } else {
                $error = "User not found.";
            }
        }
    }
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build Query
$sql = "SELECT s.*, us.last_activity, us.is_online, us.ip_address 
        FROM signinfo s 
        LEFT JOIN user_sessions us ON s.username = us.username 
        WHERE s.account_type = 'user'";

$params = array();
$types = "";

if (!empty($search)) {
    $sql .= " AND (s.fname LIKE ? OR s.lname LIKE ? OR s.email LIKE ? OR s.username LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, array($search_term, $search_term, $search_term, $search_term));
    $types .= "ssss";
}

if (!empty($role_filter)) {
    $sql .= " AND s.account_type = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $sql .= " AND s.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$count_sql = str_replace("s.*, us.last_activity, us.is_online, us.ip_address", "COUNT(*) as total", $sql);
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_users / $limit);

$sql .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result();

// Stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_today
    FROM signinfo 
    WHERE account_type = 'user'
")->fetch_assoc();

// Get user for viewing
$view_user = null;
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $view_id = $_GET['view'];
    $view_stmt = $conn->prepare("SELECT * FROM signinfo WHERE id_main = ?");
    $view_stmt->bind_param("s", $view_id);
    $view_stmt->execute();
    $view_user = $view_stmt->get_result()->fetch_assoc();
    
    if ($view_user) {
        $activity_stmt = $conn->prepare("SELECT * FROM user_logs WHERE username = ? ORDER BY login_time DESC LIMIT 20");
        $activity_stmt->bind_param("s", $view_user['username']);
        $activity_stmt->execute();
        $activities = $activity_stmt->get_result();
    }
}

// Get user for editing
$edit_user = null;
if ($can_edit_user && isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_stmt = $conn->prepare("SELECT * FROM signinfo WHERE id_main = ?");
    $edit_stmt->bind_param("s", $edit_id);
    $edit_stmt->execute();
    $edit_user = $edit_stmt->get_result()->fetch_assoc();
}

// Get profile data for sidebar
$profile_stmt = $conn->prepare("SELECT fname, lname, profile_pic, email FROM signinfo WHERE username = ?");
$profile_stmt->bind_param("s", $current_user);
$profile_stmt->execute();
$profile_data = $profile_stmt->get_result()->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace — User Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #C9A84C;
            --gold-light: #E8C97A;
            --gold-dark: #A07830;
            --dark: #0F0F1A;
            --dark-2: #161625;
            --dark-3: #1E1E30;
            --dark-4: #252538;
            --dark-5: #2E2E45;
            --text-primary: #F0EDE8;
            --text-secondary: #9B97A8;
            --text-muted: #6B6878;
            --accent-blue: #4E7CFF;
            --accent-green: #3ECF8E;
            --accent-orange: #FF8C42;
            --accent-red: #FF4757;
            --accent-purple: #9B59B6;
            --accent-yellow: #F1C40F;
            --border: rgba(201,168,76,0.12);
            --border-subtle: rgba(255,255,255,0.06);
            --sidebar-w: 270px;
            --radius: 14px;
            --radius-sm: 8px;
            --shadow: 0 8px 32px rgba(0,0,0,0.4);
            --shadow-sm: 0 4px 16px rgba(0,0,0,0.25);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; background: var(--dark); color: var(--text-primary); min-height: 100vh; display: flex; overflow-x: hidden; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--dark-2); }
        ::-webkit-scrollbar-thumb { background: var(--dark-5); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--gold-dark); }

        /* ─── SIDEBAR ─── */
        .sidebar { width: var(--sidebar-w); background: var(--dark-2); border-right: 1px solid var(--border); position: fixed; height: 100vh; display: flex; flex-direction: column; z-index: 100; overflow-y: auto; }
        .sidebar-brand { padding: 1.75rem 1.5rem 1.25rem; border-bottom: 1px solid var(--border-subtle); }
        .sidebar-brand-logo { display: flex; align-items: center; gap: 0.75rem; }
        .brand-icon { width: 38px; height: 38px; background: linear-gradient(135deg, var(--gold), var(--gold-dark)); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: var(--dark); flex-shrink: 0; box-shadow: 0 4px 12px rgba(201,168,76,0.3); }
        .brand-text h2 { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--gold-light); }
        .brand-text small { font-size: 0.7rem; color: var(--text-muted); letter-spacing: 0.5px; }
        .sidebar-profile { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; gap: 0.875rem; }
        .profile-avatar { width: 44px; height: 44px; border-radius: 50%; border: 2px solid var(--gold); overflow: hidden; flex-shrink: 0; position: relative; box-shadow: 0 0 0 3px rgba(201,168,76,0.15); }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-avatar-placeholder { width: 100%; height: 100%; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 700; color: var(--dark); }
        .profile-status { position: absolute; bottom: 1px; right: 1px; width: 10px; height: 10px; background: var(--accent-green); border-radius: 50%; border: 2px solid var(--dark-2); }
        .profile-info { flex: 1; min-width: 0; }
        .profile-name { font-size: 0.875rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .profile-sub { font-size: 0.7rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.2rem; margin-top: 0.1rem; }
        .profile-email { font-size: 0.68rem; color: var(--text-muted); display: flex; align-items: flex-start; gap: 0.2rem; margin-top: 0.1rem; word-break: break-all; line-height: 1.3; }
        .role-pill { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.6rem; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; margin-top: 0.3rem; }
        .role-pill.super { background: rgba(201,168,76,0.15); color: var(--gold-light); border: 1px solid rgba(201,168,76,0.3); }
        .role-pill.admin { background: rgba(255,140,66,0.15); color: var(--accent-orange); border: 1px solid rgba(255,140,66,0.3); }
        .nav-section { padding: 1rem 0; flex: 1; }
        .nav-label { padding: 0.6rem 1.5rem 0.3rem; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); font-weight: 600; }
        .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.7rem 1.5rem; color: var(--text-secondary); text-decoration: none; font-size: 0.875rem; transition: all 0.2s; border-left: 2px solid transparent; }
        .nav-item:hover { background: rgba(255,255,255,0.04); color: var(--text-primary); border-left-color: var(--border); }
        .nav-item.active { background: rgba(201,168,76,0.08); color: var(--gold-light); border-left-color: var(--gold); font-weight: 500; }
        .nav-item i { width: 18px; text-align: center; font-size: 0.875rem; }
        .nav-item.danger { color: var(--accent-red); }

        /* ─── MAIN ─── */
        .main { flex: 1; margin-left: var(--sidebar-w); min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: var(--dark-2); border-bottom: 1px solid var(--border-subtle); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50; }
        .topbar-left h1 { font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--text-primary); }
        .topbar-left p { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem; }
        .topbar-right { display: flex; align-items: center; gap: 0.75rem; }
        .badge-pill { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.875rem; border-radius: 99px; font-size: 0.75rem; font-weight: 600; }
        .badge-pill.super { background: rgba(201,168,76,0.12); color: var(--gold-light); border: 1px solid rgba(201,168,76,0.25); }
        .badge-pill.admin { background: rgba(255,140,66,0.12); color: var(--accent-orange); border: 1px solid rgba(255,140,66,0.25); }
        .page-content { padding: 2rem; flex: 1; }

        /* ─── ALERTS ─── */
        .alert { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.25rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.875rem; font-weight: 500; }
        .alert-success { background: rgba(62,207,142,0.1); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.25); }
        .alert-error { background: rgba(255,71,87,0.1); color: var(--accent-red); border: 1px solid rgba(255,71,87,0.25); }

        /* ─── PERMISSION NOTICE ─── */
        .perm-notice { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.25rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.85rem; border-left: 3px solid; }
        .perm-notice.has-perms { background: rgba(62,207,142,0.06); border-color: var(--accent-green); color: var(--accent-green); }
        .perm-notice.view-only { background: rgba(78,124,255,0.06); border-color: var(--accent-blue); color: var(--accent-blue); }

        /* ─── STATS GRID ─── */
        .stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem; margin-bottom: 1.75rem; }
        @media (max-width: 1400px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        .stat-card { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); padding: 1.25rem; display: flex; align-items: center; gap: 1rem; transition: all 0.25s; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--card-accent, var(--gold)); opacity: 0.6; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); border-color: var(--border); }
        .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .stat-icon.blue { background: rgba(78,124,255,0.12); color: var(--accent-blue); }
        .stat-icon.green { background: rgba(62,207,142,0.12); color: var(--accent-green); }
        .stat-icon.red { background: rgba(255,71,87,0.12); color: var(--accent-red); }
        .stat-icon.orange { background: rgba(255,140,66,0.12); color: var(--accent-orange); }
        .stat-icon.yellow { background: rgba(241,196,15,0.12); color: var(--accent-yellow); }
        .stat-icon.purple { background: rgba(155,89,182,0.12); color: var(--accent-purple); }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: var(--text-primary); line-height: 1; font-family: 'Playfair Display', serif; }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }

        /* ─── FILTER SECTION ─── */
        .filter-section { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); padding: 1rem 1.25rem; margin-bottom: 1.75rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
        .search-box { position: relative; flex: 1; min-width: 220px; max-width: 380px; }
        .search-box input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; transition: all 0.2s; }
        .search-box input:focus { outline: none; border-color: var(--gold-dark); background: var(--dark-5); }
        .search-box input::placeholder { color: var(--text-muted); }
        .search-box i { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.8rem; }
        .filter-select { padding: 0.6rem 1rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; min-width: 150px; }
        .filter-select:focus { outline: none; border-color: var(--gold-dark); }
        .btn-clear { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-secondary); text-decoration: none; font-size: 0.875rem; transition: all 0.2s; }
        .btn-clear:hover { background: var(--dark-5); color: var(--text-primary); }

        /* ─── TABLE ─── */
        .table-container { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); overflow: hidden; margin-bottom: 1.5rem; }
        .table-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; background: var(--dark-4); }
        .table-header-title { font-size: 0.875rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; }
        .users-count { background: var(--dark-5); color: var(--text-secondary); padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.72rem; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: var(--dark-4); padding: 1rem; text-align: left; color: var(--text-secondary); font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-subtle); }
        .data-table td { padding: 1rem; border-bottom: 1px solid var(--border-subtle); color: var(--text-primary); font-size: 0.85rem; }
        .data-table tr:hover { background: rgba(255,255,255,0.02); }

        .user-cell { display: flex; align-items: center; gap: 0.75rem; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); display: flex; align-items: center; justify-content: center; color: var(--dark); font-size: 0.9rem; font-weight: 700; overflow: hidden; border: 2px solid rgba(201,168,76,0.3); }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; color: var(--text-primary); }
        .user-email { font-size: 0.7rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.2rem; }

        .status-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.3rem 0.75rem; border-radius: 99px; font-size: 0.7rem; font-weight: 600; }
        .status-active { background: rgba(62,207,142,0.12); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.2); }
        .status-banned { background: rgba(255,71,87,0.12); color: var(--accent-red); border: 1px solid rgba(255,71,87,0.2); }
        .status-pending { background: rgba(241,196,15,0.12); color: var(--accent-yellow); border: 1px solid rgba(241,196,15,0.2); }
        .status-rejected { background: rgba(155,89,182,0.12); color: var(--accent-purple); border: 1px solid rgba(155,89,182,0.2); }
        .status-offline { background: rgba(155,155,155,0.12); color: var(--text-secondary); border: 1px solid rgba(155,155,155,0.2); }
        .status-online { background: rgba(62,207,142,0.12); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.2); }
        .type-badge { display: inline-flex; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; }
        .type-user { background: rgba(201,168,76,0.12); color: var(--gold-light); border: 1px solid rgba(201,168,76,0.2); }

        .action-btns { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .btn-icon { width: 30px; height: 30px; border-radius: 6px; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; transition: all 0.2s; text-decoration: none; font-size: 0.75rem; }
        .btn-view { background: rgba(78,124,255,0.1); color: var(--accent-blue); border: 1px solid rgba(78,124,255,0.2); }
        .btn-view:hover { background: var(--accent-blue); color: white; }
        .btn-edit { background: rgba(255,140,66,0.1); color: var(--accent-orange); border: 1px solid rgba(255,140,66,0.2); }
        .btn-edit:hover { background: var(--accent-orange); color: white; }
        .btn-ban { background: rgba(255,71,87,0.1); color: var(--accent-red); border: 1px solid rgba(255,71,87,0.2); }
        .btn-ban:hover { background: var(--accent-red); color: white; }
        .btn-unban { background: rgba(62,207,142,0.1); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.2); }
        .btn-unban:hover { background: var(--accent-green); color: white; }
        .btn-logout { background: rgba(155,155,155,0.1); color: var(--text-secondary); border: 1px solid rgba(155,155,155,0.2); }
        .btn-logout:hover { background: var(--text-secondary); color: var(--dark); }
        .btn-delete { background: rgba(255,71,87,0.1); color: var(--accent-red); border: 1px solid rgba(255,71,87,0.2); }
        .btn-delete:hover { background: var(--accent-red); color: white; }
        .btn-approve { background: rgba(62,207,142,0.1); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.2); }
        .btn-approve:hover { background: var(--accent-green); color: white; }
        .btn-reject { background: rgba(155,89,182,0.1); color: var(--accent-purple); border: 1px solid rgba(155,89,182,0.2); }
        .btn-reject:hover { background: var(--accent-purple); color: white; }

        .login-time { font-size: 0.75rem; color: var(--text-secondary); }
        .login-time i { margin-right: 0.2rem; font-size: 0.65rem; }

        .pagination { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-top: 1px solid var(--border-subtle); }
        .page-info { color: var(--text-muted); font-size: 0.8rem; }
        .page-btns { display: flex; gap: 0.4rem; }
        .page-btn { padding: 0.4rem 0.8rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-secondary); text-decoration: none; font-size: 0.8rem; transition: all 0.2s; }
        .page-btn:hover, .page-btn.active { background: rgba(201,168,76,0.1); border-color: rgba(201,168,76,0.3); color: var(--gold-light); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.15; display: block; }
        .empty-state p { font-size: 0.9rem; }

        /* ─── MODAL BASE ─── */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 2000; display: none; align-items: center; justify-content: center; padding: 1.5rem; backdrop-filter: blur(6px); overflow-y: auto; }
        .modal-overlay.active { display: flex !important; }
        .modal { background: var(--dark-3); border: 1px solid var(--border); border-radius: 20px; width: 100%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 30px 70px rgba(0,0,0,0.6); animation: slideUp 0.3s ease; }
        .modal-large { max-width: 1000px; }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { background: linear-gradient(135deg, var(--dark-4), var(--dark-5)); padding: 1.5rem 1.75rem; border-bottom: 2px solid var(--gold-dark); display: flex; justify-content: space-between; align-items: center; border-radius: 20px 20px 0 0; position: sticky; top: 0; z-index: 10; }
        .modal-header-left { display: flex; align-items: center; gap: 0.875rem; }
        .modal-header-icon { width: 42px; height: 42px; background: rgba(201,168,76,0.15); border: 2px solid rgba(201,168,76,0.3); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--gold-light); font-size: 1.1rem; }
        .modal-header h2 { font-family: 'Playfair Display', serif; color: var(--gold-light); font-size: 1.3rem; margin: 0; }
        .modal-header p { color: var(--text-muted); font-size: 0.78rem; margin: 0.2rem 0 0 0; }
        .modal-close { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.07); border: 1px solid var(--border-subtle); color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; transition: all 0.2s; }
        .modal-close:hover { background: rgba(255,71,87,0.15); color: var(--accent-red); transform: rotate(90deg); }
        .modal-body { padding: 1.75rem; }
        .modal-footer { padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-subtle); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--dark-4); border-radius: 0 0 20px 20px; }

        .form-section { margin-bottom: 1.5rem; }
        .form-section-title { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); font-weight: 600; margin-bottom: 0.875rem; display: flex; align-items: center; gap: 0.5rem; }
        .form-section-title::after { content: ''; flex: 1; height: 1px; background: var(--border-subtle); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; color: var(--text-secondary); font-size: 0.8rem; font-weight: 500; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.7rem 0.875rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; transition: all 0.2s; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--gold-dark); background: var(--dark-5); }
        .form-group input::placeholder, .form-group textarea::placeholder { color: var(--text-muted); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        /* Default password notice */
        .default-pass-notice { background: rgba(201,168,76,0.08); border: 1px solid rgba(201,168,76,0.25); border-radius: var(--radius-sm); padding: 0.875rem 1rem; display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; }
        .default-pass-notice i { color: var(--gold); margin-top: 0.1rem; flex-shrink: 0; }
        .default-pass-notice p { font-size: 0.8rem; color: var(--text-secondary); line-height: 1.5; margin: 0; }
        .default-pass-notice strong { color: var(--gold-light); }

        /* View Modal */
        .view-profile-header { display: flex; align-items: center; gap: 2rem; padding: 2rem; background: linear-gradient(135deg, var(--dark-4), var(--dark-5)); border-bottom: 2px solid var(--gold-dark); }
        .view-avatar-large { width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--dark); border: 4px solid rgba(201,168,76,0.3); }
        .view-avatar-large img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .view-badge-container { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
        .view-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem; border-radius: 99px; font-size: 0.75rem; font-weight: 600; }
        .view-info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; padding: 1.5rem; }
        .view-info-card { background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 1rem; }
        .view-info-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.3rem; }
        .view-info-value { font-size: 0.9rem; color: var(--text-primary); font-weight: 500; word-break: break-word; }
        .view-timeline { max-height: 300px; overflow-y: auto; padding: 0 1.5rem 1.5rem; }
        .view-timeline-item { display: flex; gap: 1rem; padding: 0.8rem; border-bottom: 1px solid var(--border-subtle); }
        .view-timeline-item:last-child { border-bottom: none; }
        .view-timeline-icon { width: 32px; height: 32px; border-radius: 8px; background: rgba(78,124,255,0.1); display: flex; align-items: center; justify-content: center; color: var(--accent-blue); flex-shrink: 0; }
        .view-timeline-icon.login { background: rgba(62,207,142,0.1); color: var(--accent-green); }
        .view-timeline-icon.logout { background: rgba(255,71,87,0.1); color: var(--accent-red); }

        .password-section { background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 1rem; margin-top: 1rem; }
        .password-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; padding: 0.5rem; }
        .password-header span { font-weight: 500; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem; }
        .password-content { display: none; padding: 1rem 0.5rem 0.5rem; border-top: 1px solid var(--border-subtle); margin-top: 0.5rem; }
        .password-content.show { display: block; }
        .strength-indicator { font-size: 0.7rem; margin-top: 0.3rem; min-height: 1.2rem; }

        .btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.25rem; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); color: var(--dark); border: none; border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.25s; text-decoration: none; white-space: nowrap; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(201,168,76,0.35); }
        .btn-secondary { padding: 0.65rem 1.25rem; background: transparent; border: 1px solid var(--border-subtle); color: var(--text-secondary); border-radius: var(--radius-sm); cursor: pointer; font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 500; transition: all 0.2s; }
        .btn-secondary:hover { background: var(--dark-5); color: var(--text-primary); }

        @media (max-width: 768px) {
            .view-info-grid { grid-template-columns: 1fr; }
            .view-profile-header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-logo">
            <div class="brand-icon"><i class="fas fa-<?php echo $is_super_admin ? 'shield-alt' : 'user-shield'; ?>"></i></div>
            <div class="brand-text">
                <h2>Furniplace</h2>
                <small><?php echo $is_super_admin ? 'Super Admin Console' : 'Admin Panel'; ?></small>
            </div>
        </div>
    </div>

    <div class="sidebar-profile">
        <div class="profile-avatar">
            <?php if (!empty($profile_data['profile_pic']) && file_exists($profile_data['profile_pic'])): ?>
                <img src="<?php echo htmlspecialchars($profile_data['profile_pic']); ?>" alt="">
            <?php else: ?>
                <div class="profile-avatar-placeholder"><?php echo strtoupper(substr($profile_data['fname'] ?? $current_user, 0, 1)); ?></div>
            <?php endif; ?>
            <div class="profile-status"></div>
        </div>
        <div class="profile-info">
            <div class="profile-name"><?php echo htmlspecialchars(($profile_data['fname'] ?? '') . ' ' . ($profile_data['lname'] ?? '')); ?></div>
            <div class="profile-sub"><i class="fas fa-at" style="font-size:0.6rem;"></i> <?php echo htmlspecialchars($current_user); ?></div>
            <div class="profile-email"><i class="fas fa-envelope" style="font-size:0.6rem; flex-shrink:0; margin-top:1px;"></i> <?php echo htmlspecialchars($profile_data['email'] ?? ''); ?></div>
            <div class="role-pill <?php echo $is_super_admin ? 'super' : 'admin'; ?>">
                <i class="fas fa-<?php echo $is_super_admin ? 'crown' : 'shield-alt'; ?>" style="font-size:0.55rem;"></i>
                <?php echo $is_super_admin ? 'Super Admin' : 'Admin'; ?>
            </div>
        </div>
    </div>

    <nav class="nav-section">
        <div class="nav-label">Dashboard</div>
        <a href="super_admin_dashboard.php" class="nav-item"><i class="fas fa-chart-line"></i> Activity Monitor</a>
        <a href="manage_products.php" class="nav-item"><i class="fas fa-box"></i> Manage Products</a>
        <a href="user_management.php" class="nav-item active"><i class="fas fa-users"></i> User Management</a>
        <a href="admin_management.php" class="nav-item"><i class="fas fa-user-shield"></i> Admin Management</a>
        <a href="product_activity_log.php" class="nav-item"><i class="fas fa-clipboard-list"></i> Product Activity</a>
        <a href="admin_activity_logs.php" class="nav-item"><i class="fas fa-history"></i> Admin Activity Logs</a>
        <?php if ($is_super_admin): ?>
        <a href="view_deletion_proofs.php" class="nav-item"><i class="fas fa-camera"></i> Deletion Proofs</a>
        <?php endif; ?>
        <div class="nav-label" style="margin-top:0.5rem;">System</div>
        <a href="home.php" class="nav-item"><i class="fas fa-store"></i> View Store</a>
        <a href="admin_settings.php" class="nav-item"><i class="fas fa-cog"></i> Settings</a>
        <a href="logout.php" class="nav-item danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>

<!-- ═══ MAIN ═══ -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h1>User Management</h1>
            <p>
                <?php 
                if ($is_god_mode) echo 'Full system user control — manage, ban, approve & delete';
                elseif ($is_manager_mode) echo 'Manager access — manage users with limited controls';
                elseif ($has_any_permission) echo 'Custom permissions — selected user management actions';
                else echo 'View-only access to user information';
                ?>
            </p>
        </div>
        <div class="topbar-right">
            <span class="badge-pill <?php echo $is_super_admin ? 'super' : 'admin'; ?>">
                <i class="fas fa-<?php echo $is_super_admin ? 'crown' : 'shield-alt'; ?>" style="font-size:0.65rem;"></i>
                <?php echo strtoupper(str_replace('_', ' ', $account_type)); ?> &nbsp;·&nbsp;
                <?php if ($control_level === 'full') echo 'FULL ACCESS'; elseif ($control_level === 'limited') echo 'LIMITED'; else echo strtoupper($control_level); ?>
            </span>
            <?php if ($can_create_user): ?>
            <button class="btn-primary" onclick="openCreateModal()">
                <i class="fas fa-user-plus"></i> Create User
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="page-content">

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php
        $user_permissions = [];
        if ($can_edit_user) $user_permissions[] = 'edit';
        if ($can_ban_user) $user_permissions[] = 'ban/unban';
        if ($can_delete_user) $user_permissions[] = 'delete';
        if ($can_approve_user) $user_permissions[] = 'approve/reject';
        if ($can_reset_password) $user_permissions[] = 'reset passwords';
        if ($can_force_logout) $user_permissions[] = 'force logout';
        if ($can_create_user) $user_permissions[] = 'create users';
        $has_perms = !empty($user_permissions);
        $last = $has_perms ? array_pop($user_permissions) : '';
        $perm_text = $has_perms ? ('You can ' . (count($user_permissions) ? implode(', ', $user_permissions) . ' and ' : '') . $last . ' users') : 'View-only access to users';
        ?>
        <div class="perm-notice <?php echo $has_perms ? 'has-perms' : 'view-only'; ?>">
            <i class="fas fa-<?php echo $has_perms ? 'check-circle' : 'info-circle'; ?>"></i>
            <span><strong>User Management Access:</strong> <?php echo $perm_text; ?>.</span>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" style="--card-accent: var(--accent-blue);">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div><div class="stat-value"><?php echo $stats['total']; ?></div><div class="stat-label">Total Users</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-green);">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div><div class="stat-value"><?php echo $stats['active']; ?></div><div class="stat-label">Active</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-yellow);">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div><div class="stat-value"><?php echo $stats['pending']; ?></div><div class="stat-label">Pending</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-purple);">
                <div class="stat-icon purple"><i class="fas fa-times-circle"></i></div>
                <div><div class="stat-value"><?php echo $stats['rejected']; ?></div><div class="stat-label">Rejected</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-red);">
                <div class="stat-icon red"><i class="fas fa-ban"></i></div>
                <div><div class="stat-value"><?php echo $stats['banned']; ?></div><div class="stat-label">Banned</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-orange);">
                <div class="stat-icon orange"><i class="fas fa-user-plus"></i></div>
                <div><div class="stat-value"><?php echo $stats['new_today']; ?></div><div class="stat-label">New Today</div></div>
            </div>
        </div>

        <!-- Filters -->
        <form class="filter-section" method="GET">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search users by name, email, username..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Banned</option>
            </select>
            <?php if ($search || $status_filter): ?>
                <a href="user_management.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>

        <!-- Users Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-header-title">
                    <i class="fas fa-users" style="color: var(--gold); font-size:0.85rem;"></i>
                    Registered Users
                    <span class="users-count"><?php echo $total_users; ?> total</span>
                </div>
                <div style="font-size:0.75rem; color:var(--text-muted); display:flex; align-items:center; gap:0.4rem;">
                    <i class="fas fa-sort-amount-down" style="font-size:0.7rem;"></i> Newest first
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users->num_rows > 0): ?>
                        <?php while($user = $users->fetch_assoc()): 
                            $is_online = ($user['is_online'] == 1) && (strtotime($user['last_activity']) > time() - 300);
                            if ($user['status'] === 'banned') { $status_class = 'status-banned'; $status_text = 'Banned'; }
                            elseif ($user['status'] === 'pending') { $status_class = 'status-pending'; $status_text = 'Pending'; }
                            elseif ($user['status'] === 'rejected') { $status_class = 'status-rejected'; $status_text = 'Rejected'; }
                            elseif ($is_online) { $status_class = 'status-online'; $status_text = 'Online'; }
                            else { $status_class = 'status-offline'; $status_text = 'Offline'; }
                        ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">
                                        <?php if (!empty($user['profile_pic']) && file_exists($user['profile_pic'])): ?>
                                            <img src="<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($user['fname'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-info">
                                        <span class="user-name"><?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></span>
                                        <span class="user-email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="type-badge type-user"><?php echo ucfirst($user['account_type']); ?></span></td>
                            <td>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $login_stmt = $conn->prepare("SELECT login_time, logout_time FROM user_logs WHERE username = ? AND action = 'login' ORDER BY login_time DESC LIMIT 1");
                                $login_stmt->bind_param("s", $user['username']);
                                $login_stmt->execute();
                                $login_result = $login_stmt->get_result()->fetch_assoc();
                                if ($login_result) {
                                    if (!empty($login_result['logout_time'])) {
                                        echo '<div class="login-time"><i class="fas fa-sign-out-alt" style="color:var(--accent-red);"></i> ' . date('M d, Y', strtotime($login_result['logout_time'])) . '</div>';
                                        echo '<small style="color:var(--text-muted); font-size:0.65rem;">' . date('h:i A', strtotime($login_result['logout_time'])) . '</small>';
                                    } else {
                                        echo '<div class="login-time"><i class="fas fa-sign-in-alt" style="color:var(--accent-green);"></i> ' . date('M d, Y', strtotime($login_result['login_time'])) . '</div>';
                                        echo '<small style="color:var(--text-muted); font-size:0.65rem;">' . date('h:i A', strtotime($login_result['login_time'])) . '</small>';
                                    }
                                } else {
                                    echo '<span class="login-time" style="color:var(--text-muted);">Never</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-btns">
                                    <a href="?view=<?php echo $user['id_main']; ?>" class="btn-icon btn-view" title="View Details"><i class="fas fa-eye"></i></a>
                                    <?php if ($user['status'] === 'pending' && $can_approve_user): ?>
                                        <a href="?action=approve&id=<?php echo $user['id_main']; ?>" class="btn-icon btn-approve" title="Approve User" onclick="return confirm('Approve <?php echo htmlspecialchars($user['username']); ?>?')"><i class="fas fa-check"></i></a>
                                        <a href="?action=reject&id=<?php echo $user['id_main']; ?>" class="btn-icon btn-reject" title="Reject User" onclick="return confirm('Reject <?php echo htmlspecialchars($user['username']); ?>?')"><i class="fas fa-times"></i></a>
                                    <?php endif; ?>
                                    <?php if ($user['status'] !== 'pending' && $can_edit_user): ?>
                                        <a href="?edit=<?php echo $user['id_main']; ?>" class="btn-icon btn-edit" title="Edit User"><i class="fas fa-edit"></i></a>
                                    <?php endif; ?>
                                    <?php if ($is_online && $user['status'] !== 'pending' && $user['status'] !== 'rejected' && $can_force_logout): ?>
                                        <a href="?action=logout&id=<?php echo $user['id_main']; ?>" class="btn-icon btn-logout" title="Force Logout" onclick="return confirm('Force logout <?php echo htmlspecialchars($user['username']); ?>?')"><i class="fas fa-sign-out-alt"></i></a>
                                    <?php endif; ?>
                                    <?php if ($user['status'] !== 'banned' && $user['status'] !== 'pending' && $user['status'] !== 'rejected' && $can_ban_user): ?>
                                        <a href="?action=ban&id=<?php echo $user['id_main']; ?>" class="btn-icon btn-ban" title="Ban User" onclick="return confirm('Ban <?php echo htmlspecialchars($user['username']); ?>?')"><i class="fas fa-ban"></i></a>
                                    <?php elseif ($user['status'] === 'banned' && $can_ban_user): ?>
                                        <a href="?action=unban&id=<?php echo $user['id_main']; ?>" class="btn-icon btn-unban" title="Unban User" onclick="return confirm('Unban <?php echo htmlspecialchars($user['username']); ?>?')"><i class="fas fa-check"></i></a>
                                    <?php endif; ?>
                                    <?php if ($user['status'] !== 'pending' && $can_delete_user): ?>
                                        <a href="?action=delete&id=<?php echo $user['id_main']; ?>" class="btn-icon btn-delete" title="Delete User" onclick="return confirm('PERMANENTLY DELETE <?php echo htmlspecialchars($user['username']); ?>?\n\nThis action cannot be undone!')"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="fas fa-users"></i><p>No users found matching your criteria</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="page-info">Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $limit, $total_users); ?> of <?php echo $total_users; ?> users</div>
                <div class="page-btns">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== CREATE USER MODAL ===== -->
<div class="modal-overlay" id="createModal" <?php echo (!empty($error) && isset($_POST['create_user_modal'])) ? 'style="display:flex;"' : ''; ?>>
    <div class="modal modal-large">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-header-icon"><i class="fas fa-user-plus"></i></div>
                <div>
                    <h2>Create New User</h2>
                    <p>Register a new user account — default password will be set automatically</p>
                </div>
            </div>
            <button class="modal-close" onclick="closeCreateModal()"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="create_user_modal" value="1">

                <!-- Default password notice -->
                <div class="default-pass-notice">
                    <i class="fas fa-info-circle"></i>
                    <p>
                        A default password of <strong>password123</strong> will be assigned to this account.
                        The user will be prompted to set their own security questions and can change their password after logging in for the first time.
                    </p>
                </div>
                
                <!-- Personal Information -->
                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-user" style="color:var(--gold);"></i> Personal Information</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>ID Number <span style="color:var(--accent-red);">*</span></label>
                            <input type="text" name="id_main" required placeholder="ID-XXXX" value="<?php echo isset($_POST['id_main']) ? htmlspecialchars($_POST['id_main']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>First Name <span style="color:var(--accent-red);">*</span></label>
                            <input type="text" name="fname" required placeholder="First name" value="<?php echo isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : ''; ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Last Name <span style="color:var(--accent-red);">*</span></label>
                            <input type="text" name="lname" required placeholder="Last name" value="<?php echo isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Middle Initial</label>
                            <input type="text" name="mi" placeholder="M.I." maxlength="3" value="<?php echo isset($_POST['mi']) ? htmlspecialchars($_POST['mi']) : ''; ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Extension</label>
                            <input type="text" name="Ename" placeholder="Jr., Sr., III" maxlength="4" value="<?php echo isset($_POST['Ename']) ? htmlspecialchars($_POST['Ename']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Sex <span style="color:var(--accent-red);">*</span></label>
                            <select name="sex" required>
                                <option value="">Select</option>
                                <option value="Male" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Birth Date <span style="color:var(--accent-red);">*</span></label>
                        <input type="date" name="bd" required value="<?php echo isset($_POST['bd']) ? $_POST['bd'] : ''; ?>">
                    </div>
                </div>

                <!-- Account Details (no password fields - auto-set) -->
                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-lock" style="color:var(--gold);"></i> Account Details</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Username <span style="color:var(--accent-red);">*</span></label>
                            <input type="text" name="username" required placeholder="Choose username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Email <span style="color:var(--accent-red);">*</span></label>
                            <input type="email" name="email" required placeholder="user@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-map-marker-alt" style="color:var(--gold);"></i> Address Information</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Purok/Street <span style="color:var(--accent-red);">*</span></label>
                            <input type="text" name="Purok" required placeholder="Purok or Street" value="<?php echo isset($_POST['Purok']) ? htmlspecialchars($_POST['Purok']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Barangay <span style="color:var(--accent-red);">*</span></label>
                            <input type="text" name="Barranggay" required placeholder="Barangay" value="<?php echo isset($_POST['Barranggay']) ? htmlspecialchars($_POST['Barranggay'] ) : ''; ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Zip Code <span style="color:var(--accent-red);">*</span></label>
                            <input type="text" name="zip" required placeholder="Zip Code" value="<?php echo isset($_POST['zip']) ? htmlspecialchars($_POST['zip']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>City/Municipality <span style="color:var(--accent-red);">*</span></label>
                            <input type="text" name="CM" required placeholder="City or Municipality" value="<?php echo isset($_POST['CM']) ? htmlspecialchars($_POST['CM']) : ''; ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Province <span style="color:var(--accent-red);">*</span></label>
                            <input type="text" name="Province" required placeholder="Province" value="<?php echo isset($_POST['Province']) ? htmlspecialchars($_POST['Province']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>Country <span style="color:var(--accent-red);">*</span></label>
                            <input type="text" name="Country" value="<?php echo isset($_POST['Country']) ? htmlspecialchars($_POST['Country']) : 'Philippines'; ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Security questions intentionally removed -->
                <!-- Users set their own security questions on their profile page -->

            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeCreateModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-user-plus"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== VIEW USER MODAL ===== -->
<?php if ($view_user): ?>
<div class="modal-overlay active" onclick="if(event.target === this) window.location.href='user_management.php';">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-header-icon"><i class="fas fa-user-circle"></i></div>
                <div><h2>User Profile</h2><p>View user details</p></div>
            </div>
            <a href="user_management.php" class="modal-close"><i class="fas fa-times"></i></a>
        </div>

        <div class="view-profile-header">
            <div class="view-avatar-large">
                <?php if (!empty($view_user['profile_pic']) && file_exists($view_user['profile_pic'])): ?>
                    <img src="<?php echo htmlspecialchars($view_user['profile_pic']); ?>" alt="">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div>
                <div class="view-badge-container">
                    <span class="view-badge" style="background:rgba(201,168,76,0.15); color:var(--gold-light); border:1px solid rgba(201,168,76,0.3);">
                        <i class="fas fa-user"></i> <?php echo ucfirst($view_user['account_type']); ?>
                    </span>
                    <span class="view-badge" style="background:<?php echo $view_user['status'] === 'active' ? 'rgba(62,207,142,0.15); color:var(--accent-green);' : ($view_user['status'] === 'banned' ? 'rgba(255,71,87,0.15); color:var(--accent-red);' : ($view_user['status'] === 'pending' ? 'rgba(241,196,15,0.15); color:var(--accent-yellow);' : 'rgba(155,89,182,0.15); color:var(--accent-purple);')); ?> border:1px solid rgba(255,255,255,0.1);">
                        <i class="fas fa-circle"></i> <?php echo ucfirst($view_user['status']); ?>
                    </span>
                </div>
                <h2 style="font-family:'Playfair Display', serif; color:var(--text-primary); margin:0;"><?php echo htmlspecialchars($view_user['fname'] . ' ' . $view_user['lname']); ?></h2>
                <div style="display:flex; gap:1rem; margin-top:0.5rem; color:var(--text-muted);">
                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($view_user['email']); ?></span>
                    <span><i class="fas fa-at"></i> <?php echo htmlspecialchars($view_user['username']); ?></span>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:1px; background:var(--border-subtle);">
            <div style="background:var(--dark-4); padding:1rem; text-align:center;">
                <div style="font-size:0.7rem; color:var(--text-muted);">ID Number</div>
                <div style="font-weight:600; font-family:'JetBrains Mono', monospace;"><?php echo htmlspecialchars(substr($view_user['id_main'], -8)); ?></div>
            </div>
            <div style="background:var(--dark-4); padding:1rem; text-align:center;">
                <div style="font-size:0.7rem; color:var(--text-muted);">Age</div>
                <div style="font-weight:600;"><?php $birthdate = new DateTime($view_user['bd']); $today = new DateTime(); echo $today->diff($birthdate)->y . ' years'; ?></div>
            </div>
            <div style="background:var(--dark-4); padding:1rem; text-align:center;">
                <div style="font-size:0.7rem; color:var(--text-muted);">Birth Date</div>
                <div style="font-weight:600;"><?php echo date('M d, Y', strtotime($view_user['bd'])); ?></div>
            </div>
            <div style="background:var(--dark-4); padding:1rem; text-align:center;">
                <div style="font-size:0.7rem; color:var(--text-muted);">Joined</div>
                <div style="font-weight:600;"><?php echo date('M Y', strtotime($view_user['created_at'])); ?></div>
            </div>
        </div>

        <div class="modal-body">
            <div class="form-section">
                <div class="form-section-title"><i class="fas fa-id-card" style="color:var(--gold);"></i> Personal Information</div>
                <div class="view-info-grid">
                    <div class="view-info-card">
                        <div class="view-info-label"><i class="fas fa-user"></i> Full Name</div>
                        <div class="view-info-value"><?php echo htmlspecialchars($view_user['fname'] . ' ' . (!empty($view_user['mi']) ? $view_user['mi'] . '. ' : '') . $view_user['lname'] . (!empty($view_user['Ename']) ? ' ' . $view_user['Ename'] : '')); ?></div>
                    </div>
                    <div class="view-info-card">
                        <div class="view-info-label"><i class="fas fa-venus-mars"></i> Sex</div>
                        <div class="view-info-value"><?php echo htmlspecialchars($view_user['sex'] ?? 'Not specified'); ?></div>
                    </div>
                    <div class="view-info-card">
                        <div class="view-info-label"><i class="fas fa-calendar"></i> Birth Date</div>
                        <div class="view-info-value"><?php echo date('F d, Y', strtotime($view_user['bd'])); ?></div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title"><i class="fas fa-map-marker-alt" style="color:var(--gold);"></i> Address Information</div>
                <div class="view-info-grid">
                    <div class="view-info-card"><div class="view-info-label"><i class="fas fa-road"></i> Purok/Street</div><div class="view-info-value"><?php echo htmlspecialchars($view_user['Purok'] ?? 'Not specified'); ?></div></div>
                    <div class="view-info-card"><div class="view-info-label"><i class="fas fa-map-pin"></i> Barangay</div><div class="view-info-value"><?php echo htmlspecialchars($view_user['Barranggay'] ?? 'Not specified'); ?></div></div>
                    <div class="view-info-card"><div class="view-info-label"><i class="fas fa-mail-bulk"></i> Zip Code</div><div class="view-info-value"><?php echo htmlspecialchars($view_user['zip'] ?? 'Not specified'); ?></div></div>
                    <div class="view-info-card"><div class="view-info-label"><i class="fas fa-city"></i> City/Municipality</div><div class="view-info-value"><?php echo htmlspecialchars($view_user['CM'] ?? 'Not specified'); ?></div></div>
                    <div class="view-info-card"><div class="view-info-label"><i class="fas fa-map"></i> Province</div><div class="view-info-value"><?php echo htmlspecialchars($view_user['Province'] ?? 'Not specified'); ?></div></div>
                    <div class="view-info-card"><div class="view-info-label"><i class="fas fa-globe"></i> Country</div><div class="view-info-value"><?php echo htmlspecialchars($view_user['Country'] ?? 'Philippines'); ?></div></div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title"><i class="fas fa-history" style="color:var(--gold);"></i> Recent Activity</div>
                <div class="view-timeline">
                    <?php if ($activities && $activities->num_rows > 0): ?>
                        <?php while($act = $activities->fetch_assoc()): ?>
                        <div class="view-timeline-item">
                            <div class="view-timeline-icon <?php echo $act['action'] === 'login' ? 'login' : ($act['action'] === 'logout' ? 'logout' : ''); ?>">
                                <i class="fas fa-<?php echo $act['action'] === 'login' ? 'sign-in-alt' : ($act['action'] === 'logout' ? 'sign-out-alt' : ($act['action'] === 'approved_user' ? 'check' : ($act['action'] === 'rejected_user' ? 'times' : 'desktop'))); ?>"></i>
                            </div>
                            <div style="flex:1;">
                                <div style="font-weight:500; color:var(--text-primary);"><?php echo ucfirst(str_replace('_', ' ', $act['action'])); ?></div>
                                <div style="font-size:0.7rem; color:var(--text-muted); display:flex; gap:1rem;">
                                    <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($act['login_time'])); ?></span>
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($act['ip_address']); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding:2rem;"><i class="fas fa-history"></i><p>No activity recorded</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <a href="user_management.php" class="btn-secondary"><i class="fas fa-times"></i> Close</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== EDIT USER MODAL ===== -->
<?php if ($can_edit_user && $edit_user): ?>
<div class="modal-overlay active" onclick="if(event.target === this) window.location.href='user_management.php';">
    <div class="modal modal-large">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-header-icon"><i class="fas fa-user-edit"></i></div>
                <div><h2>Edit User Profile</h2><p>Update user information</p></div>
            </div>
            <a href="user_management.php" class="modal-close"><i class="fas fa-times"></i></a>
        </div>

        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="user_id" value="<?php echo $edit_user['id_main']; ?>">
                
                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-user" style="color:var(--gold);"></i> Personal Information</div>
                    <div class="form-row">
                        <div class="form-group"><label>ID Number</label><input type="text" value="<?php echo htmlspecialchars($edit_user['id_main']); ?>" readonly style="background:var(--dark-5);"></div>
                        <div class="form-group"><label>First Name <span style="color:var(--accent-red);">*</span></label><input type="text" name="fname" value="<?php echo htmlspecialchars($edit_user['fname']); ?>" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Last Name <span style="color:var(--accent-red);">*</span></label><input type="text" name="lname" value="<?php echo htmlspecialchars($edit_user['lname']); ?>" required></div>
                        <div class="form-group"><label>Middle Initial</label><input type="text" name="mi" value="<?php echo htmlspecialchars($edit_user['mi'] ?? ''); ?>" maxlength="3"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Extension</label><input type="text" name="Ename" value="<?php echo htmlspecialchars($edit_user['Ename'] ?? ''); ?>" maxlength="4"></div>
                        <div class="form-group"><label>Sex <span style="color:var(--accent-red);">*</span></label>
                            <select name="sex" required>
                                <option value="Male" <?php echo ($edit_user['sex'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($edit_user['sex'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label>Birth Date <span style="color:var(--accent-red);">*</span></label><input type="date" name="bd" value="<?php echo htmlspecialchars($edit_user['bd']); ?>" required></div>
                </div>

                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-lock" style="color:var(--gold);"></i> Account Details</div>
                    <div class="form-row">
                        <div class="form-group"><label>Username <span style="color:var(--accent-red);">*</span></label><input type="text" name="username" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required></div>
                        <div class="form-group"><label>Email <span style="color:var(--accent-red);">*</span></label><input type="email" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required></div>
                    </div>

                    <div class="password-section">
                        <div class="password-header" onclick="togglePasswordSection()">
                            <span><i class="fas fa-key"></i> Change Password</span>
                            <i class="fas fa-chevron-down" id="passwordToggleIcon"></i>
                        </div>
                        <div class="password-content" id="passwordContent">
                            <div class="form-row">
                                <div class="form-group"><label>New Password</label><input type="password" id="editNewPassword" placeholder="Enter new password" minlength="8"><div class="strength-indicator" id="editPasswordStrength"></div></div>
                                <div class="form-group"><label>Confirm Password</label><input type="password" id="editConfirmPassword" placeholder="Confirm new password"><div class="strength-indicator" id="editPasswordMatch"></div></div>
                            </div>
                            <button type="button" onclick="changeUserPassword()" class="btn-primary" style="width:100%;"><i class="fas fa-sync-alt"></i> Update Password</button>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-map-marker-alt" style="color:var(--gold);"></i> Address Information</div>
                    <div class="form-row">
                        <div class="form-group"><label>Purok/Street <span style="color:var(--accent-red);">*</span></label><input type="text" name="Purok" value="<?php echo htmlspecialchars($edit_user['Purok'] ?? ''); ?>" required></div>
                        <div class="form-group"><label>Barangay <span style="color:var(--accent-red);">*</span></label><input type="text" name="Barranggay" value="<?php echo htmlspecialchars($edit_user['Barranggay'] ?? ''); ?>" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Zip Code <span style="color:var(--accent-red);">*</span></label><input type="text" name="zip" value="<?php echo htmlspecialchars($edit_user['zip'] ?? ''); ?>" required></div>
                        <div class="form-group"><label>City/Municipality <span style="color:var(--accent-red);">*</span></label><input type="text" name="CM" value="<?php echo htmlspecialchars($edit_user['CM'] ?? ''); ?>" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Province <span style="color:var(--accent-red);">*</span></label><input type="text" name="Province" value="<?php echo htmlspecialchars($edit_user['Province'] ?? ''); ?>" required></div>
                        <div class="form-group"><label>Country <span style="color:var(--accent-red);">*</span></label><input type="text" name="Country" value="<?php echo htmlspecialchars($edit_user['Country'] ?? 'Philippines'); ?>" required></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <a href="user_management.php" class="btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                <button type="submit" name="edit_user" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<form id="passwordChangeForm" method="POST" style="display:none;">
    <input type="hidden" name="user_id" value="<?php echo $edit_user['id_main']; ?>">
    <input type="hidden" name="change_password" value="1">
    <input type="hidden" name="new_password" id="newPasswordInput">
</form>
<?php endif; ?>

<script>
function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeCreateModal() {
    document.getElementById('createModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function togglePasswordSection() {
    const content = document.getElementById('passwordContent');
    const icon = document.getElementById('passwordToggleIcon');
    content.classList.toggle('show');
    icon.classList.toggle('fa-chevron-down');
    icon.classList.toggle('fa-chevron-up');
}

document.getElementById('editNewPassword')?.addEventListener('keyup', function() {
    const pass = this.value;
    const strengthDiv = document.getElementById('editPasswordStrength');
    let strength = 0;
    if (pass.match(/[a-z]+/)) strength++;
    if (pass.match(/[A-Z]+/)) strength++;
    if (pass.match(/[0-9]+/)) strength++;
    if (pass.match(/[$@#&!]+/)) strength++;
    if (pass.length >= 8) strength++;
    let text = '', color = '';
    if (strength <= 2) { text = 'Weak'; color = 'var(--accent-red)'; }
    else if (strength <= 4) { text = 'Medium'; color = 'var(--accent-orange)'; }
    else { text = 'Strong'; color = 'var(--accent-green)'; }
    strengthDiv.innerHTML = pass ? `Strength: <span style="color:${color};">${text}</span>` : '';
});

document.getElementById('editConfirmPassword')?.addEventListener('keyup', function() {
    const pass = document.getElementById('editNewPassword').value;
    const repass = this.value;
    const matchDiv = document.getElementById('editPasswordMatch');
    if (!repass) matchDiv.innerHTML = '';
    else if (pass === repass) matchDiv.innerHTML = '<span style="color:var(--accent-green);"><i class="fas fa-check-circle"></i> Match</span>';
    else matchDiv.innerHTML = '<span style="color:var(--accent-red);"><i class="fas fa-exclamation-circle"></i> No match</span>';
});

function changeUserPassword() {
    const newPass = document.getElementById('editNewPassword').value;
    const confirmPass = document.getElementById('editConfirmPassword').value;
    if (!newPass) { alert('Please enter a new password'); return; }
    if (newPass.length < 8) { alert('Password must be at least 8 characters'); return; }
    if (newPass !== confirmPass) { alert('Passwords do not match'); return; }
    if (confirm('Change this user\'s password?')) {
        document.getElementById('newPasswordInput').value = newPass;
        document.getElementById('passwordChangeForm').submit();
    }
}

document.getElementById('createModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCreateModal();
});

fetch('ping.php');
setInterval(() => fetch('ping.php'), 30000);
</script>
</body>
</html>