<?php
session_start();
include 'connection.php';
include 'device_helper.php';

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

// Allow BOTH Admin and Super Admin to view
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
include 'permission_helper.php';
$permissions = loadPermissions($conn, $current_user);

// NEW: Check specific permission for deleting logs
$can_delete_logs = hasPermission($permissions, 'can_delete_product_logs');

$is_super_admin = ($account_type === 'super_admin');
$is_admin = ($account_type === 'admin');

// KEEP old control_level for backwards compatibility
if (!isset($_SESSION['control_level'])) {
    $control_stmt = $conn->prepare("SELECT control_level FROM signinfo WHERE username = ?");
    $control_stmt->bind_param("s", $current_user);
    $control_stmt->execute();
    $control_result = $control_stmt->get_result()->fetch_assoc();
    $_SESSION['control_level'] = $control_result['control_level'] ?? 'limited';
}
$control_level = $_SESSION['control_level'];

// ADD THESE for UI compatibility:
$is_god_mode = ($account_type === 'super_admin' && $control_level === 'full');
$is_manager_mode = ($account_type === 'admin' && $control_level === 'full');
$is_viewer_mode = ($account_type === 'admin' && $control_level === 'limited');
$is_super_admin_limited = ($account_type === 'super_admin' && $control_level === 'limited');

// Who can delete logs? (God Mode + Admin Full)
$can_delete_logs = hasPermission($permissions, 'can_delete_product_logs');

// If neither admin nor super_admin, kick out
if (!$is_super_admin && !$is_admin) {
    header("Location: login.php");
    exit();
}

// Delete Log Functionality
$delete_message = '';
$delete_error = '';

$log_id = isset($_GET['delete_log']) ? intval($_GET['delete_log']) : 0;

// Check permission before deleting
if ($log_id > 0 && $can_delete_logs) {
    $check_stmt = $conn->prepare("SELECT product_name, action FROM product_logs WHERE id = ?");
    $check_stmt->bind_param("i", $log_id);
    $check_stmt->execute();
    $log_info = $check_stmt->get_result()->fetch_assoc();
    
    if ($log_info) {
        $delete_stmt = $conn->prepare("DELETE FROM product_logs WHERE id = ?");
        $delete_stmt->bind_param("i", $log_id);
        
        if ($delete_stmt->execute()) {
            $delete_message = "Log entry for '{$log_info['product_name']}' ({$log_info['action']}) deleted successfully!";
        } else {
            $delete_error = "Failed to delete log entry.";
        }
    } else {
        $delete_error = "Log entry not found.";
    }
} elseif ($log_id > 0 && !$can_delete_logs) {
    $delete_error = "Access Denied: Only Super Admin (Full) or Admin (Full) can delete activity logs.";
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filters
$filter_action = isset($_GET['action_type']) ? $_GET['action_type'] : '';
$filter_admin = isset($_GET['admin']) ? trim($_GET['admin']) : '';
$filter_product = isset($_GET['product']) ? trim($_GET['product']) : '';

// Build query
$sql = "SELECT 
            pl.id,
            pl.username as admin_name,
            pl.action,
            pl.ip_address,
            pl.created_at as activity_time,
            pl.product_id,
            pl.product_name,
            pl.details
        FROM product_logs pl
        WHERE 1=1";

$params = array();
$types = "";

if (!empty($filter_action)) {
    $action_map = [
        'created_product' => 'created',
        'updated_product' => 'updated',
        'deleted_product' => 'deleted'
    ];
    $db_action = isset($action_map[$filter_action]) ? $action_map[$filter_action] : $filter_action;
    
    $sql .= " AND pl.action = ?";
    $params[] = $db_action;
    $types .= "s";
}

if (!empty($filter_admin)) {
    $sql .= " AND pl.username LIKE ?";
    $params[] = "%$filter_admin%";
    $types .= "s";
}

if (!empty($filter_product)) {
    $sql .= " AND pl.product_name LIKE ?";
    $params[] = "%$filter_product%";
    $types .= "s";
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM ($sql) as subquery";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_params = $params;
    $count_types = $types;
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$total_activities = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_activities / $limit);

// Add ordering and pagination
$sql .= " ORDER BY pl.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$activities = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_actions,
    SUM(CASE WHEN action = 'created' THEN 1 ELSE 0 END) as total_created,
    SUM(CASE WHEN action = 'updated' THEN 1 ELSE 0 END) as total_updated,
    SUM(CASE WHEN action = 'deleted' THEN 1 ELSE 0 END) as total_deleted,
    COUNT(DISTINCT username) as unique_admins
FROM product_logs";
$stats = $conn->query($stats_sql)->fetch_assoc();

// Get unique admins for filter dropdown
$admins_result = $conn->query("SELECT DISTINCT username FROM product_logs ORDER BY username");
$admin_list = [];
while($row = $admins_result->fetch_assoc()) {
    $admin_list[] = $row['username'];
}

// Get profile data for sidebar
$profile_stmt = $conn->prepare("SELECT fname, lname, profile_pic, email FROM signinfo WHERE username = ?");
$profile_stmt->bind_param("s", $current_user);
$profile_stmt->execute();
$profile_data = $profile_stmt->get_result()->fetch_assoc();

// Helper function for time ago
function time_elapsed_string($datetime, $full = false) {
    date_default_timezone_set('Asia/Manila');
    
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hr',
        'i' => 'min',
        's' => 'sec',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace — Product Activity Log</title>
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

        /* ─── PERMISSION NOTICE ─── */
        .perm-notice { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.25rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.85rem; border-left: 3px solid; }
        .perm-notice.has-perms { background: rgba(62,207,142,0.06); border-color: var(--accent-green); color: var(--accent-green); }
        .perm-notice.view-only { background: rgba(78,124,255,0.06); border-color: var(--accent-blue); color: var(--accent-blue); }

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
        .stat-icon.red { background: rgba(255,71,87,0.12); color: var(--accent-red); }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: var(--text-primary); line-height: 1; font-family: 'Playfair Display', serif; }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }

        /* ─── FILTER SECTION ─── */
        .filter-section { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); padding: 1rem 1.25rem; margin-bottom: 1.75rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
        .search-box { position: relative; flex: 2; min-width: 220px; }
        .search-box input, .search-box select { width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; transition: all 0.2s; }
        .search-box select { padding-left: 0.875rem; cursor: pointer; }
        .search-box input:focus, .search-box select:focus { outline: none; border-color: var(--gold-dark); background: var(--dark-5); }
        .search-box input::placeholder { color: var(--text-muted); }
        .search-box i { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.8rem; }
        .filter-select { padding: 0.6rem 1rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; min-width: 150px; cursor: pointer; }
        .filter-select:focus { outline: none; border-color: var(--gold-dark); }
        .btn-clear { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-secondary); text-decoration: none; font-size: 0.875rem; transition: all 0.2s; }
        .btn-clear:hover { background: var(--dark-5); color: var(--text-primary); }

        /* ─── BUTTONS ─── */
        .btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.25rem; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); color: var(--dark); border: none; border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.25s; text-decoration: none; white-space: nowrap; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(201,168,76,0.35); }
        .btn-secondary { padding: 0.6rem 1.25rem; background: transparent; border: 1px solid var(--border-subtle); color: var(--text-secondary); border-radius: var(--radius-sm); cursor: pointer; font-size: 0.875rem; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-secondary:hover { background: var(--dark-5); color: var(--text-primary); }
        .btn-delete-log { padding: 0.4rem 0.875rem; background: rgba(255,71,87,0.1); border: 1px solid rgba(255,71,87,0.2); border-radius: var(--radius-sm); color: var(--accent-red); font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; }
        .btn-delete-log:hover { background: var(--accent-red); color: white; }

        /* ─── ACTIVITY CONTAINER ─── */
        .activity-container { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); overflow: hidden; margin-bottom: 1.5rem; }
        .activity-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; background: var(--dark-4); }
        .activity-header h2 { font-size: 1rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; }
        .activity-list { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }

        /* ─── ACTIVITY ITEM ─── */
        .activity-item { background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius); padding: 1.25rem; display: flex; gap: 1.25rem; transition: all 0.2s; }
        .activity-item:hover { border-color: var(--gold); }

        .activity-icon-wrapper { position: relative; flex-shrink: 0; }
        .activity-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border: 2px solid; }
        .activity-icon.created { background: rgba(62,207,142,0.1); color: var(--accent-green); border-color: rgba(62,207,142,0.3); }
        .activity-icon.updated { background: rgba(78,124,255,0.1); color: var(--accent-blue); border-color: rgba(78,124,255,0.3); }
        .activity-icon.deleted { background: rgba(255,71,87,0.1); color: var(--accent-red); border-color: rgba(255,71,87,0.3); }
        .activity-time-badge { position: absolute; bottom: -5px; right: -5px; background: var(--dark-5); color: var(--text-secondary); padding: 0.2rem 0.5rem; border-radius: 20px; font-size: 0.6rem; font-weight: 700; border: 1px solid var(--border-subtle); font-family: 'JetBrains Mono', monospace; white-space: nowrap; }

        .activity-content { flex: 1; }
        .activity-header-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; }
        .activity-title { font-weight: 600; color: var(--text-primary); font-size: 1rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
        .activity-badge { padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-created { background: rgba(62,207,142,0.15); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.3); }
        .badge-updated { background: rgba(78,124,255,0.15); color: var(--accent-blue); border: 1px solid rgba(78,124,255,0.3); }
        .badge-deleted { background: rgba(255,71,87,0.15); color: var(--accent-red); border: 1px solid rgba(255,71,87,0.3); text-decoration: line-through; }

        .activity-meta { display: flex; gap: 1.5rem; color: var(--text-muted); font-size: 0.75rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
        .activity-meta span { display: flex; align-items: center; gap: 0.4rem; }
        .activity-meta i { color: var(--gold); width: 14px; }

        .activity-timestamp { font-size: 0.7rem; color: var(--text-muted); font-family: 'JetBrains Mono', monospace; display: flex; align-items: center; gap: 0.3rem; }

        .product-preview { display: flex; gap: 1rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 0.875rem; margin-top: 0.5rem; }
        .product-preview.deleted { opacity: 0.7; background: rgba(255,71,87,0.05); border-color: rgba(255,71,87,0.2); }
        .product-image { width: 60px; height: 60px; border-radius: 6px; background: var(--dark-4); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--text-muted); overflow: hidden; flex-shrink: 0; }
        .product-image img { width: 100%; height: 100%; object-fit: cover; }
        .product-details { flex: 1; }
        .product-name { font-family: 'Playfair Display', serif; color: var(--text-primary); font-size: 0.95rem; margin-bottom: 0.3rem; }
        .product-name.deleted { color: var(--accent-red); text-decoration: line-through; }
        .product-specs { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .spec-item { display: flex; align-items: center; gap: 0.25rem; font-size: 0.7rem; color: var(--text-secondary); background: var(--dark-4); padding: 0.2rem 0.6rem; border-radius: 4px; border: 1px solid var(--border-subtle); }
        .spec-item i { color: var(--gold); font-size: 0.65rem; }

        /* ─── EMPTY STATE ─── */
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.15; display: block; }
        .empty-state h3 { font-family: 'Playfair Display', serif; color: var(--text-primary); margin-bottom: 0.5rem; font-size: 1.2rem; }
        .empty-state p { font-size: 0.9rem; }

        /* ─── PAGINATION ─── */
        .pagination { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-top: 1px solid var(--border-subtle); }
        .page-info { color: var(--text-muted); font-size: 0.8rem; }
        .page-btns { display: flex; gap: 0.4rem; }
        .page-btn { padding: 0.4rem 0.8rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-secondary); text-decoration: none; font-size: 0.8rem; transition: all 0.2s; }
        .page-btn:hover, .page-btn.active { background: rgba(201,168,76,0.1); border-color: rgba(201,168,76,0.3); color: var(--gold-light); }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .activity-item { flex-direction: column; }
            .activity-header-row { flex-direction: column; gap: 0.5rem; }
            .product-preview { flex-direction: column; }
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
        <a href="product_activity_log.php" class="nav-item active"><i class="fas fa-clipboard-list"></i> Product Activity</a>
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
            <h1><i class="fas fa-clipboard-list" style="color: var(--gold);"></i> Product Activity Log</h1>
            <p>Monitor all product management activities by administrators</p>
        </div>
        <div class="topbar-right">
            <span class="badge-pill <?php echo $is_super_admin ? 'super' : 'admin'; ?>">
                <i class="fas fa-<?php echo $is_super_admin ? 'crown' : 'shield-alt'; ?>" style="font-size:0.65rem;"></i>
                <?php echo strtoupper(str_replace('_', ' ', $account_type)); ?> &nbsp;·&nbsp;
                <?php if ($control_level === 'full') echo 'FULL ACCESS'; elseif ($control_level === 'limited') echo 'LIMITED'; else echo strtoupper($control_level); ?>
            </span>
            <a href="manage_products.php" class="btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>
    </div>

    <div class="page-content">

        <?php if (!empty($delete_message)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $delete_message; ?></div>
        <?php endif; ?>
        <?php if (!empty($delete_error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $delete_error; ?></div>
        <?php endif; ?>

        <!-- Permission Notice -->
        <?php
        $log_permissions = [];
        if ($can_delete_logs) $log_permissions[] = 'delete logs';
        $has_perms = !empty($log_permissions);
        $perm_text = $has_perms ? 'You can delete activity logs' : 'You have view-only access to activity logs';
        ?>
        <div class="perm-notice <?php echo $has_perms ? 'has-perms' : 'view-only'; ?>">
            <i class="fas fa-<?php echo $has_perms ? 'check-circle' : 'info-circle'; ?>"></i>
            <span><strong>Activity Log Access:</strong> <?php echo $perm_text; ?>.</span>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" style="--card-accent: var(--accent-blue);">
                <div class="stat-icon blue"><i class="fas fa-list-alt"></i></div>
                <div><div class="stat-value"><?php echo $stats['total_actions']; ?></div><div class="stat-label">Total Activities</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-green);">
                <div class="stat-icon green"><i class="fas fa-plus-circle"></i></div>
                <div><div class="stat-value"><?php echo $stats['total_created']; ?></div><div class="stat-label">Products Created</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-orange);">
                <div class="stat-icon orange"><i class="fas fa-edit"></i></div>
                <div><div class="stat-value"><?php echo $stats['total_updated']; ?></div><div class="stat-label">Products Updated</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-red);">
                <div class="stat-icon red"><i class="fas fa-trash-alt"></i></div>
                <div><div class="stat-value"><?php echo $stats['total_deleted']; ?></div><div class="stat-label">Products Deleted</div></div>
            </div>
        </div>

        <!-- Filters -->
        <form class="filter-section" method="GET">
            <div class="search-box" style="flex: 2;">
                <i class="fas fa-search"></i>
                <input type="text" name="product" placeholder="Search product name..." value="<?php echo htmlspecialchars($filter_product); ?>">
            </div>
            <div class="search-box" style="flex: 1;">
                <i class="fas fa-user"></i>
                <select name="admin" style="padding-left: 2.5rem;">
                    <option value="">All Admins</option>
                    <?php foreach($admin_list as $admin): ?>
                        <option value="<?php echo htmlspecialchars($admin); ?>" <?php echo $filter_admin === $admin ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($admin); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-box" style="flex: 1;">
                <i class="fas fa-tag"></i>
                <select name="action_type" style="padding-left: 2.5rem;">
                    <option value="">All Actions</option>
                    <option value="created" <?php echo $filter_action === 'created' ? 'selected' : ''; ?>>Created</option>
                    <option value="updated" <?php echo $filter_action === 'updated' ? 'selected' : ''; ?>>Updated</option>
                    <option value="deleted" <?php echo $filter_action === 'deleted' ? 'selected' : ''; ?>>Deleted</option>
                </select>
            </div>
            <?php if ($filter_action || $filter_admin || $filter_product): ?>
                <a href="product_activity_log.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
            <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Filter</button>
        </form>

        <!-- Activity Timeline -->
        <div class="activity-container">
            <div class="activity-header">
                <h2><i class="fas fa-history" style="color: var(--gold);"></i> Activity Timeline</h2>
                <span class="admins-count"><?php echo $activities->num_rows; ?> of <?php echo $total_activities; ?> activities</span>
            </div>
            
            <div class="activity-list">
                <?php if ($activities->num_rows > 0): ?>
                    <?php while($activity = $activities->fetch_assoc()): 
                        $action_type = $activity['action'];
                        $icon_class = $action_type;
                        $badge_class = 'badge-' . $action_type;
                        $time_ago = time_elapsed_string($activity['activity_time']);
                        
                        $product_name = $activity['product_name'] ?? 'Unknown Product';
                        $product_id = $activity['product_id'] ?? null;
                        
                        // Parse details JSON
                        $details = [];
                        $category = 'Uncategorized';
                        $price = 0;
                        $stock = 0;
                        $device_name = 'Unknown Device';
                        
                        if (!empty($activity['details'])) {
                            $decoded = json_decode($activity['details'], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $details = $decoded;
                                $category = $details['category'] ?? 'Uncategorized';
                                $price = isset($details['price']) ? floatval($details['price']) : 0;
                                $stock = isset($details['stock']) ? intval($details['stock']) : 0;
                                $device_name = $details['source'] ?? 'Admin Panel';
                            } else {
                                $device_name = $activity['details'];
                            }
                        }
                        
                        // Get product image
                        $product_image = '';
                        if ($product_id && is_numeric($product_id)) {
                            $prod_stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
                            $prod_stmt->bind_param("i", $product_id);
                            $prod_stmt->execute();
                            $prod_result = $prod_stmt->get_result()->fetch_assoc();
                            if ($prod_result) {
                                $product_image = $prod_result['image'];
                            }
                        }
                    ?>
                        <div class="activity-item">
                            <div class="activity-icon-wrapper">
                                <div class="activity-icon <?php echo $icon_class; ?>">
                                    <i class="fas fa-<?php echo $action_type === 'created' ? 'plus' : ($action_type === 'updated' ? 'edit' : 'trash'); ?>"></i>
                                </div>
                                <span class="activity-time-badge"><?php echo $time_ago; ?></span>
                            </div>
                            
                            <div class="activity-content">
                                <div class="activity-header-row">
                                    <div class="activity-title">
                                        <?php echo htmlspecialchars($activity['admin_name']); ?>
                                        <span class="activity-badge <?php echo $badge_class; ?>">
                                            <?php echo $action_type; ?>
                                        </span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <span class="activity-timestamp">
                                            <i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($activity['activity_time'])); ?>
                                        </span>
                                        <?php if ($can_delete_logs): ?>
                                            <a href="?delete_log=<?php echo $activity['id']; ?>&page=<?php echo $page; ?>&action_type=<?php echo urlencode($filter_action); ?>&admin=<?php echo urlencode($filter_admin); ?>&product=<?php echo urlencode($filter_product); ?>" 
                                               class="btn-delete-log"
                                               onclick="return confirm('Delete this log entry?\n\nProduct: <?php echo htmlspecialchars($product_name); ?>\nAction: <?php echo $action_type; ?>');">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="activity-meta">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($activity['admin_name']); ?></span>
                                    <span><i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($activity['ip_address']); ?></span>
                                    <span><i class="fas fa-desktop"></i> <?php echo htmlspecialchars($device_name); ?></span>
                                </div>
                                
                                <div class="product-preview <?php echo $action_type === 'deleted' ? 'deleted' : ''; ?>">
                                    <div class="product-image">
                                        <?php if (!empty($product_image) && file_exists($product_image)): ?>
                                            <img src="<?php echo htmlspecialchars($product_image); ?>" alt="">
                                        <?php else: ?>
                                            <i class="fas fa-couch"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-details">
                                        <div class="product-name <?php echo $action_type === 'deleted' ? 'deleted' : ''; ?>">
                                            <?php echo htmlspecialchars($product_name); ?>
                                            <?php if ($action_type === 'deleted'): ?>
                                                <span style="color: var(--accent-red); font-size: 0.7rem; margin-left: 0.5rem;">(DELETED)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="product-specs">
                                            <span class="spec-item"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($category); ?></span>
                                            <span class="spec-item"><i class="fas fa-peso-sign"></i> <?php echo number_format($price, 2); ?></span>
                                            <?php if ($action_type !== 'deleted'): ?>
                                            <span class="spec-item"><i class="fas fa-box"></i> Stock: <?php echo $stock; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-check"></i>
                        <h3>No Activity Found</h3>
                        <p>No product management activities match your current filters.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></div>
                <div class="page-btns">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&action_type=<?php echo urlencode($filter_action); ?>&admin=<?php echo urlencode($filter_admin); ?>&product=<?php echo urlencode($filter_product); ?>" class="page-btn"><i class="fas fa-chevron-left"></i> Prev</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&action_type=<?php echo urlencode($filter_action); ?>&admin=<?php echo urlencode($filter_admin); ?>&product=<?php echo urlencode($filter_product); ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&action_type=<?php echo urlencode($filter_action); ?>&admin=<?php echo urlencode($filter_admin); ?>&product=<?php echo urlencode($filter_product); ?>" class="page-btn">Next <i class="fas fa-chevron-right"></i></a>
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