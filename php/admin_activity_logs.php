<?php
session_start();
include 'connection.php';
include 'permission_helper.php';

// Check if user is still valid
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

// Authentication check
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

// Control level
if (!isset($_SESSION['control_level'])) {
    $control_stmt = $conn->prepare("SELECT control_level FROM signinfo WHERE username = ?");
    $control_stmt->bind_param("s", $current_user);
    $control_stmt->execute();
    $control_result = $control_stmt->get_result()->fetch_assoc();
    $_SESSION['control_level'] = $control_result['control_level'] ?? 'limited';
}
$control_level = $_SESSION['control_level'];

$is_god_mode = ($account_type === 'super_admin' && $control_level === 'full');

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_admin = isset($_GET['admin']) ? trim($_GET['admin']) : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Build query - ONLY ADMINISTRATIVE ACTIONS (NO login/logout, NO product actions)
$sql = "SELECT ul.*, 
               a.fname as admin_fname, 
               a.lname as admin_lname, 
               a.profile_pic as admin_pic, 
               a.account_type as admin_type
        FROM user_logs ul
        LEFT JOIN signinfo a ON ul.username = a.username
        WHERE ul.action IN (
            -- Admin Management Actions
            'created_admin', 'edited_admin', 'deleted_admin', 'banned_admin', 'unbanned_admin',
            'created_super_admin', 'restored_admin',
            
            -- User Management Actions
            'created_user', 'edited_user', 'deleted_user', 'banned_user', 'unbanned_user',
            'approved_user', 'rejected_user', 'force_logout_user', 'reset_user_password',
            
            -- System Actions
            'changed_settings', 'cleared_session_logs'
        )";

$params = array();
$types = "";

// Action filter
if (!empty($filter_action)) {
    $sql .= " AND ul.action = ?";
    $params[] = $filter_action;
    $types .= "s";
}

// Admin filter (who performed the action)
if (!empty($filter_admin)) {
    $sql .= " AND ul.username LIKE ?";
    $params[] = "%$filter_admin%";
    $types .= "s";
}

// Date filter
if (!empty($filter_date)) {
    $sql .= " AND DATE(ul.login_time) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM user_logs ul 
              WHERE ul.action IN (
                  'created_admin', 'edited_admin', 'deleted_admin', 'banned_admin', 'unbanned_admin',
                  'created_super_admin', 'restored_admin',
                  'created_user', 'edited_user', 'deleted_user', 'banned_user', 'unbanned_user',
                  'approved_user', 'rejected_user', 'force_logout_user', 'reset_user_password',
                  'changed_settings', 'cleared_session_logs'
              )";

$count_params = [];
$count_types = "";

if (!empty($filter_action)) {
    $count_sql .= " AND ul.action = ?";
    $count_params[] = $filter_action;
    $count_types .= "s";
}
if (!empty($filter_admin)) {
    $count_sql .= " AND ul.username LIKE ?";
    $count_params[] = "%$filter_admin%";
    $count_types .= "s";
}
if (!empty($filter_date)) {
    $count_sql .= " AND DATE(ul.login_time) = ?";
    $count_params[] = $filter_date;
    $count_types .= "s";
}

$count_stmt = $conn->prepare($count_sql);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$total_activities = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_activities / $limit);

// Add pagination to main query
$sql .= " ORDER BY ul.login_time DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$activities = $stmt->get_result();

// Get unique actions for filter dropdown
$actions_sql = "SELECT DISTINCT action, COUNT(*) as count 
                FROM user_logs 
                WHERE action IN (
                    'created_admin', 'edited_admin', 'deleted_admin', 'banned_admin', 'unbanned_admin',
                    'created_super_admin', 'restored_admin',
                    'created_user', 'edited_user', 'deleted_user', 'banned_user', 'unbanned_user',
                    'approved_user', 'rejected_user', 'force_logout_user', 'reset_user_password',
                    'changed_settings', 'cleared_session_logs'
                )
                GROUP BY action
                ORDER BY count DESC, action";

$actions_result = $conn->query($actions_sql);

// Get unique admins for filter dropdown
$admins_sql = "SELECT DISTINCT username, COUNT(*) as count 
               FROM user_logs 
               WHERE action IN (
                   'created_admin', 'edited_admin', 'deleted_admin', 'banned_admin', 'unbanned_admin',
                   'created_super_admin', 'restored_admin',
                   'created_user', 'edited_user', 'deleted_user', 'banned_user', 'unbanned_user',
                   'approved_user', 'rejected_user', 'force_logout_user', 'reset_user_password',
                   'changed_settings', 'cleared_session_logs'
               )
               GROUP BY username
               ORDER BY count DESC, username";

$admins_result = $conn->query($admins_sql);

// Stats for admin actions only
$stats_sql = "SELECT 
    COUNT(*) as total_actions,
    COUNT(DISTINCT username) as unique_admins,
    
    -- Admin Management Stats
    SUM(CASE WHEN action = 'created_admin' OR action = 'created_super_admin' THEN 1 ELSE 0 END) as created_admins,
    SUM(CASE WHEN action = 'deleted_admin' THEN 1 ELSE 0 END) as deleted_admins,
    SUM(CASE WHEN action = 'restored_admin' THEN 1 ELSE 0 END) as restored_admins,
    SUM(CASE WHEN action = 'banned_admin' THEN 1 ELSE 0 END) as banned_admins,
    SUM(CASE WHEN action = 'unbanned_admin' THEN 1 ELSE 0 END) as unbanned_admins,
    SUM(CASE WHEN action = 'edited_admin' THEN 1 ELSE 0 END) as edited_admins,
    
    -- User Management Stats
    SUM(CASE WHEN action = 'created_user' THEN 1 ELSE 0 END) as created_users,
    SUM(CASE WHEN action = 'deleted_user' THEN 1 ELSE 0 END) as deleted_users,
    SUM(CASE WHEN action = 'banned_user' THEN 1 ELSE 0 END) as banned_users,
    SUM(CASE WHEN action = 'unbanned_user' THEN 1 ELSE 0 END) as unbanned_users,
    SUM(CASE WHEN action = 'edited_user' THEN 1 ELSE 0 END) as edited_users,
    SUM(CASE WHEN action = 'approved_user' THEN 1 ELSE 0 END) as approved_users,
    SUM(CASE WHEN action = 'rejected_user' THEN 1 ELSE 0 END) as rejected_users,
    SUM(CASE WHEN action = 'force_logout_user' THEN 1 ELSE 0 END) as force_logouts,
    SUM(CASE WHEN action = 'reset_user_password' THEN 1 ELSE 0 END) as password_resets,
    
    -- Time-based stats
    SUM(CASE WHEN DATE(login_time) = CURDATE() THEN 1 ELSE 0 END) as today_actions,
    SUM(CASE WHEN YEARWEEK(login_time) = YEARWEEK(CURDATE()) THEN 1 ELSE 0 END) as week_actions
FROM user_logs 
WHERE action IN (
    'created_admin', 'edited_admin', 'deleted_admin', 'banned_admin', 'unbanned_admin',
    'created_super_admin', 'restored_admin',
    'created_user', 'edited_user', 'deleted_user', 'banned_user', 'unbanned_user',
    'approved_user', 'rejected_user', 'force_logout_user', 'reset_user_password',
    'changed_settings', 'cleared_session_logs'
)";

$stats = $conn->query($stats_sql)->fetch_assoc();

// Get profile data for sidebar
$profile_stmt = $conn->prepare("SELECT fname, lname, profile_pic, email FROM signinfo WHERE username = ?");
$profile_stmt->bind_param("s", $current_user);
$profile_stmt->execute();
$profile_data = $profile_stmt->get_result()->fetch_assoc();

// Function to get action details with descriptive text
function getActionDetails($action) {
    $actions = [
        // Admin Management
        'created_admin' => [
            'icon' => 'fa-user-plus', 
            'color' => '#D4AF37', 
            'bg' => 'rgba(212, 175, 55, 0.15)', 
            'label' => 'Created Admin',
            'description' => 'created a new administrator account'
        ],
        'created_super_admin' => [
            'icon' => 'fa-crown', 
            'color' => '#D4AF37', 
            'bg' => 'rgba(212, 175, 55, 0.15)', 
            'label' => 'Created Super Admin',
            'description' => 'created a new super administrator account'
        ],
        'restored_admin' => [
            'icon' => 'fa-undo-alt', 
            'color' => '#2E7D32', 
            'bg' => 'rgba(62, 207, 142, 0.15)', 
            'label' => 'Restored Admin',
            'description' => 'restored a deleted administrator account'
        ],
        'edited_admin' => [
            'icon' => 'fa-user-edit', 
            'color' => '#1976D2', 
            'bg' => 'rgba(78, 124, 255, 0.15)', 
            'label' => 'Edited Admin',
            'description' => 'edited an administrator\'s details'
        ],
        'deleted_admin' => [
            'icon' => 'fa-user-minus', 
            'color' => '#C62828', 
            'bg' => 'rgba(255, 71, 87, 0.15)', 
            'label' => 'Deleted Admin',
            'description' => 'deleted an administrator account'
        ],
        'banned_admin' => [
            'icon' => 'fa-ban', 
            'color' => '#C62828', 
            'bg' => 'rgba(255, 71, 87, 0.15)', 
            'label' => 'Banned Admin',
            'description' => 'banned an administrator'
        ],
        'unbanned_admin' => [
            'icon' => 'fa-check-circle', 
            'color' => '#2E7D32', 
            'bg' => 'rgba(62, 207, 142, 0.15)', 
            'label' => 'Unbanned Admin',
            'description' => 'unbanned an administrator'
        ],
        
        // User Management
        'created_user' => [
            'icon' => 'fa-user-plus', 
            'color' => '#2E7D32', 
            'bg' => 'rgba(62, 207, 142, 0.15)', 
            'label' => 'Created User',
            'description' => 'created a new user account'
        ],
        'edited_user' => [
            'icon' => 'fa-user-edit', 
            'color' => '#1976D2', 
            'bg' => 'rgba(78, 124, 255, 0.15)', 
            'label' => 'Edited User',
            'description' => 'edited a user\'s details'
        ],
        'deleted_user' => [
            'icon' => 'fa-user-minus', 
            'color' => '#C62828', 
            'bg' => 'rgba(255, 71, 87, 0.15)', 
            'label' => 'Deleted User',
            'description' => 'deleted a user account'
        ],
        'banned_user' => [
            'icon' => 'fa-ban', 
            'color' => '#C62828', 
            'bg' => 'rgba(255, 71, 87, 0.15)', 
            'label' => 'Banned User',
            'description' => 'banned a user'
        ],
        'unbanned_user' => [
            'icon' => 'fa-check-circle', 
            'color' => '#2E7D32', 
            'bg' => 'rgba(62, 207, 142, 0.15)', 
            'label' => 'Unbanned User',
            'description' => 'unbanned a user'
        ],
        'approved_user' => [
            'icon' => 'fa-check', 
            'color' => '#2E7D32', 
            'bg' => 'rgba(62, 207, 142, 0.15)', 
            'label' => 'Approved User',
            'description' => 'approved a user registration'
        ],
        'rejected_user' => [
            'icon' => 'fa-times', 
            'color' => '#C62828', 
            'bg' => 'rgba(255, 71, 87, 0.15)', 
            'label' => 'Rejected User',
            'description' => 'rejected a user registration'
        ],
        'force_logout_user' => [
            'icon' => 'fa-sign-out-alt', 
            'color' => '#F57C00', 
            'bg' => 'rgba(255, 140, 66, 0.15)', 
            'label' => 'Force Logout',
            'description' => 'forced a user to logout'
        ],
        'reset_user_password' => [
            'icon' => 'fa-key', 
            'color' => '#7B1FA2', 
            'bg' => 'rgba(155, 89, 182, 0.15)', 
            'label' => 'Password Reset',
            'description' => 'reset a user\'s password'
        ],
        
        // System Actions
        'changed_settings' => [
            'icon' => 'fa-cog', 
            'color' => '#546E7A', 
            'bg' => 'rgba(108, 117, 125, 0.15)', 
            'label' => 'Changed Settings',
            'description' => 'changed system settings'
        ],
        'cleared_session_logs' => [
            'icon' => 'fa-broom', 
            'color' => '#546E7A', 
            'bg' => 'rgba(108, 117, 125, 0.15)', 
            'label' => 'Cleared Session Logs',
            'description' => 'cleared user session logs'
        ],
    ];
    
    return $actions[$action] ?? [
        'icon' => 'fa-history', 
        'color' => '#757575', 
        'bg' => 'rgba(155, 155, 155, 0.15)', 
        'label' => ucfirst(str_replace('_', ' ', $action)),
        'description' => 'performed an administrative action'
    ];
}

// Function to format changes nicely
function formatChanges($changes_json) {
    if (empty($changes_json)) {
        return '';
    }
    
    $changes = json_decode($changes_json, true);
    if (empty($changes)) {
        return '';
    }
    
    $html = '<div style="margin-top: 0.75rem; background: var(--dark-5); padding: 1rem; border-radius: 8px; border-left: 3px solid var(--gold);">';
    
    foreach($changes as $field => $values) {
        $field_name = ucwords(str_replace('_', ' ', $field));
        $old_value = htmlspecialchars($values['old'] ?? '');
        $new_value = htmlspecialchars($values['new'] ?? '');
        
        if ($old_value !== $new_value) {
            $html .= '<div style="display: flex; gap: 0.75rem; margin-bottom: 0.5rem; align-items: center; font-size: 0.9rem;">';
            $html .= '<span style="min-width: 120px; color: var(--text-secondary); font-weight: 500;">' . $field_name . ':</span>';
            $html .= '<span style="color: var(--accent-red); text-decoration: line-through; background: rgba(255,71,87,0.1); padding: 0.2rem 0.5rem; border-radius: 4px;">' . ($old_value ?: '(empty)') . '</span>';
            $html .= '<span style="color: var(--text-muted);"><i class="fas fa-arrow-right"></i></span>';
            $html .= '<span style="color: var(--accent-green); font-weight: 500; background: rgba(62,207,142,0.1); padding: 0.2rem 0.5rem; border-radius: 4px;">' . ($new_value ?: '(empty)') . '</span>';
            $html .= '</div>';
        }
    }
    
    $html .= '</div>';
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace — Admin Activity Logs</title>
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
        .topbar-left h1 { font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.75rem; }
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

        /* ─── STATS GRID ─── */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.75rem; }
        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } }
        .stat-card { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); padding: 1.25rem; display: flex; align-items: center; gap: 1rem; transition: all 0.25s; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--card-accent, var(--gold)); opacity: 0.6; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); border-color: var(--border); }
        .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .stat-icon.blue { background: rgba(78,124,255,0.12); color: var(--accent-blue); }
        .stat-icon.green { background: rgba(62,207,142,0.12); color: var(--accent-green); }
        .stat-icon.orange { background: rgba(255,140,66,0.12); color: var(--accent-orange); }
        .stat-icon.purple { background: rgba(155,89,182,0.12); color: var(--accent-purple); }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: var(--text-primary); line-height: 1; font-family: 'Playfair Display', serif; }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }

        /* ─── FILTER SECTION ─── */
        .filter-section { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); padding: 1rem 1.25rem; margin-bottom: 1.75rem; }
        .filter-row { display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
        .filter-group { display: flex; gap: 0.5rem; flex-wrap: wrap; flex: 1; }
        .filter-select, .filter-input { padding: 0.6rem 1rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; min-width: 180px; }
        .filter-select:focus, .filter-input:focus { outline: none; border-color: var(--gold-dark); }
        .btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.25rem; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); color: var(--dark); border: none; border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.25s; text-decoration: none; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(201,168,76,0.35); }
        .btn-secondary { background: transparent; border: 1px solid var(--border-subtle); color: var(--text-secondary); }
        .btn-secondary:hover { background: var(--dark-5); color: var(--text-primary); }

        /* ─── QUICK STATS ─── */
        .quick-stats { display: flex; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-subtle); flex-wrap: wrap; }
        .quick-stat { background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: 20px; padding: 0.4rem 1rem; font-size: 0.75rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.4rem; }
        .quick-stat i { color: var(--gold); }
        .quick-stat strong { color: var(--text-primary); margin-right: 0.2rem; }

        /* ─── ACTIVITY CONTAINER ─── */
        .activity-container { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); overflow: hidden; margin-bottom: 1.5rem; }
        .activity-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; background: var(--dark-4); }
        .activity-header h3 { font-size: 1rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; }
        .activity-count { background: var(--dark-5); color: var(--text-secondary); padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; }
        .activity-list { padding: 1.5rem; }

        /* ─── ACTIVITY ITEM ─── */
        .activity-item { display: flex; gap: 1.25rem; padding: 1.25rem; border-bottom: 1px solid var(--border-subtle); transition: all 0.2s; border-radius: var(--radius-sm); }
        .activity-item:hover { background: var(--dark-4); transform: translateX(5px); }
        .activity-item:last-child { border-bottom: none; }

        .activity-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; transition: all 0.2s; border: 2px solid; }
        .activity-item:hover .activity-icon { transform: scale(1.1); }

        .activity-content { flex: 1; }
        .activity-header-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem; }

        .admin-badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.3rem 0.8rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: 20px; font-size: 0.75rem; font-weight: 600; color: var(--text-primary); }
        .admin-badge i { color: var(--gold); }

        .action-text { font-weight: 500; padding: 0.2rem 0.6rem; border-radius: 4px; background: var(--dark-5); border: 1px solid var(--border-subtle); font-size: 0.75rem; }

        .activity-time { font-size: 0.75rem; color: var(--text-muted); font-family: 'JetBrains Mono', monospace; display: flex; align-items: center; gap: 0.5rem; }
        .activity-time span { color: var(--gold); }

        .activity-meta { display: flex; gap: 1rem; color: var(--text-muted); font-size: 0.7rem; margin-top: 0.5rem; flex-wrap: wrap; }
        .activity-meta span { display: flex; align-items: center; gap: 0.4rem; }
        .activity-meta i { color: var(--gold); width: 14px; }

        .changes-box { margin-top: 0.75rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 1rem; border-left: 3px solid var(--gold); }
        .change-row { display: flex; gap: 0.75rem; margin-bottom: 0.5rem; align-items: center; font-size: 0.8rem; }
        .change-field { min-width: 120px; color: var(--text-secondary); font-weight: 500; }
        .change-old { color: var(--accent-red); text-decoration: line-through; background: rgba(255,71,87,0.1); padding: 0.2rem 0.5rem; border-radius: 4px; }
        .change-new { color: var(--accent-green); font-weight: 500; background: rgba(62,207,142,0.1); padding: 0.2rem 0.5rem; border-radius: 4px; }
        .change-arrow { color: var(--text-muted); }

        /* ─── EMPTY STATE ─── */
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.15; display: block; }
        .empty-state h3 { font-family: 'Playfair Display', serif; color: var(--text-primary); margin-bottom: 0.5rem; font-size: 1.2rem; }
        .empty-state p { font-size: 0.9rem; }

        /* ─── PAGINATION ─── */
        .pagination { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-top: 1px solid var(--border-subtle); flex-wrap: wrap; gap: 1rem; }
        .page-info { color: var(--text-muted); font-size: 0.8rem; }
        .page-btns { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .page-btn { padding: 0.4rem 0.8rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-secondary); text-decoration: none; font-size: 0.8rem; transition: all 0.2s; }
        .page-btn:hover, .page-btn.active { background: rgba(201,168,76,0.1); border-color: rgba(201,168,76,0.3); color: var(--gold-light); }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-group { flex-direction: column; }
            .activity-item { flex-direction: column; }
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
        <a href="user_management.php" class="nav-item"><i class="fas fa-users"></i> User Management</a>
        <a href="admin_management.php" class="nav-item"><i class="fas fa-user-shield"></i> Admin Management</a>
        <a href="product_activity_log.php" class="nav-item"><i class="fas fa-clipboard-list"></i> Product Activity</a>
        <a href="admin_activity_logs.php" class="nav-item active"><i class="fas fa-history"></i> Admin Activity Logs</a>
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
            <h1><i class="fas fa-history" style="color: var(--gold);"></i> Administrative Activity Logs</h1>
            <p>Track all administrative actions: user/admin creation, deletion, banning, editing, and more</p>
        </div>
        <div class="topbar-right">
            <span class="badge-pill <?php echo $is_super_admin ? 'super' : 'admin'; ?>">
                <i class="fas fa-<?php echo $is_super_admin ? 'crown' : 'shield-alt'; ?>" style="font-size:0.65rem;"></i>
                <?php echo strtoupper(str_replace('_', ' ', $account_type)); ?> &nbsp;·&nbsp;
                <?php if ($control_level === 'full') echo 'FULL ACCESS'; elseif ($control_level === 'limited') echo 'LIMITED'; else echo strtoupper($control_level); ?>
            </span>
        </div>
    </div>

    <div class="page-content">

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card" style="--card-accent: var(--accent-blue);">
                <div class="stat-icon blue"><i class="fas fa-tasks"></i></div>
                <div><div class="stat-value"><?php echo number_format($stats['total_actions'] ?? 0); ?></div><div class="stat-label">Total Actions</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-green);">
                <div class="stat-icon green"><i class="fas fa-user-shield"></i></div>
                <div><div class="stat-value"><?php echo $stats['unique_admins'] ?? 0; ?></div><div class="stat-label">Active Admins</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-orange);">
                <div class="stat-icon orange"><i class="fas fa-calendar-day"></i></div>
                <div><div class="stat-value"><?php echo $stats['today_actions'] ?? 0; ?></div><div class="stat-label">Today's Actions</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-purple);">
                <div class="stat-icon purple"><i class="fas fa-calendar-week"></i></div>
                <div><div class="stat-value"><?php echo $stats['week_actions'] ?? 0; ?></div><div class="stat-label">This Week</div></div>
            </div>
        </div>

        <!-- Filters -->
        <form class="filter-section" method="GET">
            <div class="filter-row">
                <div class="filter-group">
                    <select name="action" class="filter-select">
                        <option value="">All Administrative Actions</option>
                        <?php 
                        if ($actions_result && $actions_result->num_rows > 0) {
                            while($action = $actions_result->fetch_assoc()): 
                                $details = getActionDetails($action['action']);
                        ?>
                        <option value="<?php echo $action['action']; ?>" <?php echo $filter_action === $action['action'] ? 'selected' : ''; ?>>
                            <?php echo $details['label']; ?> (<?php echo $action['count']; ?>)
                        </option>
                        <?php 
                            endwhile; 
                        } 
                        ?>
                    </select>

                    <select name="admin" class="filter-select">
                        <option value="">All Administrators</option>
                        <?php 
                        if ($admins_result && $admins_result->num_rows > 0) {
                            while($admin = $admins_result->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $admin['username']; ?>" <?php echo $filter_admin === $admin['username'] ? 'selected' : ''; ?>>
                            <?php echo $admin['username']; ?> (<?php echo $admin['count']; ?> actions)
                        </option>
                        <?php 
                            endwhile;
                        } 
                        ?>
                    </select>

                    <input type="date" name="date" class="filter-input" value="<?php echo $filter_date; ?>">
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                
                <?php if ($filter_action || $filter_admin || $filter_date): ?>
                <a href="admin_activity_logs.php" class="btn-primary btn-secondary">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
                <?php endif; ?>
            </div>

            <!-- Quick Stats Row -->
            <div class="quick-stats">
                <span class="quick-stat"><i class="fas fa-user-plus"></i> <strong><?php echo $stats['created_users'] ?? 0; ?></strong> Users Created</span>
                <span class="quick-stat"><i class="fas fa-user-minus"></i> <strong><?php echo $stats['deleted_users'] ?? 0; ?></strong> Users Deleted</span>
                <span class="quick-stat"><i class="fas fa-ban"></i> <strong><?php echo ($stats['banned_users'] + ($stats['banned_admins'] ?? 0)) ?? 0; ?></strong> Accounts Banned</span>
                <span class="quick-stat"><i class="fas fa-check-circle"></i> <strong><?php echo $stats['approved_users'] ?? 0; ?></strong> Users Approved</span>
                <span class="quick-stat"><i class="fas fa-key"></i> <strong><?php echo $stats['password_resets'] ?? 0; ?></strong> Password Resets</span>
                <span class="quick-stat"><i class="fas fa-sign-out-alt"></i> <strong><?php echo $stats['force_logouts'] ?? 0; ?></strong> Force Logouts</span>
                <span class="quick-stat"><i class="fas fa-crown"></i> <strong><?php echo $stats['created_admins'] ?? 0; ?></strong> Admins Created</span>
                <span class="quick-stat"><i class="fas fa-undo-alt"></i> <strong><?php echo $stats['restored_admins'] ?? 0; ?></strong> Admins Restored</span>
            </div>
        </form>

        <!-- Activity Timeline -->
        <div class="activity-container">
            <div class="activity-header">
                <h3><i class="fas fa-clock" style="color: var(--gold);"></i> Administrative Actions Timeline</h3>
                <span class="activity-count">
                    Showing <?php echo $activities->num_rows; ?> of <?php echo number_format($total_activities); ?> actions
                </span>
            </div>
            
            <div class="activity-list">
                <?php if ($activities && $activities->num_rows > 0): ?>
                    <?php while($activity = $activities->fetch_assoc()): 
                        $details = getActionDetails($activity['action']);
                    ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: <?php echo $details['bg']; ?>; color: <?php echo $details['color']; ?>; border-color: <?php echo $details['color']; ?>;">
                            <i class="fas <?php echo $details['icon']; ?>"></i>
                        </div>
                        
                        <div class="activity-content">
                            <div class="activity-header-row">
                                <div class="activity-title" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <span class="admin-badge">
                                        <i class="fas fa-<?php echo ($activity['admin_type'] === 'super_admin') ? 'crown' : 'shield-alt'; ?>"></i> 
                                        <?php echo htmlspecialchars($activity['admin_fname'] . ' ' . $activity['admin_lname']); ?>
                                        <span style="color: var(--text-muted);">(@<?php echo htmlspecialchars($activity['username']); ?>)</span>
                                    </span>
                                    
                                    <?php if ($activity['admin_type'] === 'super_admin'): ?>
                                        <span style="display: inline-block; padding: 0.15rem 0.5rem; background: rgba(201,168,76,0.15); color: var(--gold-light); border-radius: 4px; font-size: 0.6rem; font-weight: 700; border: 1px solid rgba(201,168,76,0.3);">
                                            <i class="fas fa-crown"></i> SUPER
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 0.15rem 0.5rem; background: rgba(255,140,66,0.15); color: var(--accent-orange); border-radius: 4px; font-size: 0.6rem; font-weight: 700; border: 1px solid rgba(255,140,66,0.3);">
                                            <i class="fas fa-shield-alt"></i> ADMIN
                                        </span>
                                    <?php endif; ?>

                                    <span class="action-text" style="color: <?php echo $details['color']; ?>;">
                                        <?php echo $details['label']; ?>
                                    </span>
                                </div>
                                
                                <div class="activity-time">
                                    <i class="fas fa-clock"></i> 
                                    <?php echo date('M d, Y', strtotime($activity['login_time'])); ?>
                                    <span><?php echo date('h:i A', strtotime($activity['login_time'])); ?></span>
                                </div>
                            </div>
                            
                            <!-- Action Description -->
                            <div style="margin: 0.5rem 0; color: var(--text-secondary); font-size: 0.9rem;">
                                <?php 
                                echo htmlspecialchars($activity['admin_fname'] . ' ' . $activity['admin_lname']); 
                                
                                if (in_array($activity['action'], ['edited_user', 'edited_admin']) && !empty($activity['changes'])) {
                                    echo ' edited ';
                                    echo ($activity['action'] === 'edited_user') ? 'user' : 'administrator';
                                    if (!empty($activity['target_name'])) {
                                        echo ' <strong style="color: var(--text-primary);">' . htmlspecialchars($activity['target_name']) . '</strong>';
                                    }
                                } else {
                                    echo ' ' . $details['description'];
                                    if (!empty($activity['target_name'])) {
                                        echo ' <strong style="color: var(--text-primary);">' . htmlspecialchars($activity['target_name']) . '</strong>';
                                    }
                                }
                                
                                if (!empty($activity['target_user'])): ?>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;"> (@<?php echo htmlspecialchars($activity['target_user']); ?>)</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Detailed Changes for Edited User/Admin -->
                            <?php if (in_array($activity['action'], ['edited_user', 'edited_admin']) && !empty($activity['changes'])): ?>
                                <?php 
                                $changes = json_decode($activity['changes'], true);
                                if (!empty($changes)): 
                                ?>
                                <div class="changes-box">
                                    <?php foreach($changes as $field => $values): ?>
                                        <?php if (isset($values['old']) || isset($values['new'])): ?>
                                        <div class="change-row">
                                            <span class="change-field"><?php echo ucwords(str_replace('_', ' ', $field)); ?>:</span>
                                            <span class="change-old"><?php echo htmlspecialchars($values['old'] ?? '(empty)'); ?></span>
                                            <span class="change-arrow"><i class="fas fa-arrow-right"></i></span>
                                            <span class="change-new"><?php echo htmlspecialchars($values['new'] ?? '(empty)'); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- Meta Information -->
                            <div class="activity-meta">
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($activity['ip_address'] ?? 'Unknown'); ?></span>
                                <span><i class="fas fa-globe"></i> <?php echo htmlspecialchars($activity['browser'] ?? 'Unknown'); ?></span>
                                <span><i class="fas fa-laptop"></i> <?php echo htmlspecialchars($activity['os'] ?? 'Unknown'); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No Administrative Actions Found</h3>
                        <p>No administrative actions match your current filters.</p>
                        <?php if ($filter_action || $filter_admin || $filter_date): ?>
                        <a href="admin_activity_logs.php" class="btn-primary" style="margin-top: 1rem; display: inline-flex;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="page-info">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?> • 
                    Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $limit, $total_activities); ?> of <?php echo $total_activities; ?> actions
                </div>
                <div class="page-btns">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&action=<?php echo urlencode($filter_action); ?>&admin=<?php echo urlencode($filter_admin); ?>&date=<?php echo urlencode($filter_date); ?>" class="page-btn">
                            <i class="fas fa-chevron-left"></i> Prev
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&action=<?php echo urlencode($filter_action); ?>&admin=<?php echo urlencode($filter_admin); ?>&date=<?php echo urlencode($filter_date); ?>" 
                           class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&action=<?php echo urlencode($filter_action); ?>&admin=<?php echo urlencode($filter_admin); ?>&date=<?php echo urlencode($filter_date); ?>" class="page-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Ping to stay online
fetch('ping.php');
setInterval(() => fetch('ping.php'), 30000);
</script>

</body>
</html>