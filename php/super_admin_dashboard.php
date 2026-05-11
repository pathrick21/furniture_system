<?php
session_start();
include 'connection.php';
include 'device_helper.php';
include 'permission_helper.php';

// Check if user is still valid (not deleted or banned)
if (isset($_SESSION['user'])) {
    $check_user = $conn->prepare("SELECT status FROM signinfo WHERE username = ?");
    $check_user->bind_param("s", $_SESSION['user']);
    $check_user->execute();
    $result = $check_user->get_result();
    if ($result->num_rows == 0) { session_destroy(); header("Location: login.php?error=account_deleted"); exit(); }
    $user = $result->fetch_assoc();
    if ($user['status'] === 'banned' || $user['status'] === 'deleted') { session_destroy(); header("Location: login.php?error=account_inactive"); exit(); }
}

if (!isset($_SESSION['user']) || !isset($_SESSION['account_type'])) { header("Location: login.php"); exit(); }

$current_user = $_SESSION['user'];
$account_type = $_SESSION['account_type'];
$is_super_admin = ($account_type === 'super_admin');
$is_admin = ($account_type === 'admin');

if (!$is_super_admin && !$is_admin) { header("Location: login.php"); exit(); }

$refresh_stmt = $conn->prepare("SELECT control_level FROM signinfo WHERE username = ?");
$refresh_stmt->bind_param("s", $current_user);
$refresh_stmt->execute();
$refresh_result = $refresh_stmt->get_result()->fetch_assoc();
$control_level = $refresh_result['control_level'] ?? 'limited';
$_SESSION['control_level'] = $control_level;

$is_god_mode = ($account_type === 'super_admin' && $control_level === 'full');
$is_manager_mode = ($account_type === 'admin' && $control_level === 'full');
$is_viewer_mode = ($account_type === 'admin' && $control_level === 'limited');
$is_super_admin_limited = ($account_type === 'super_admin' && $control_level === 'limited');

$permissions = loadPermissions($conn, $current_user);
$can_clear_session = hasPermission($permissions, 'can_clear_session_logs');
$can_view_admin_activity = hasPermission($permissions, 'can_view_admin_activity');

if (isset($_GET['clear_session']) && $can_clear_session) {
    $username_to_clear = $_GET['clear_session'];
    if ($username_to_clear !== $current_user) {
        $clear_stmt = $conn->prepare("DELETE FROM user_sessions WHERE username = ?");
        $clear_stmt->bind_param("s", $username_to_clear);
        if ($clear_stmt->execute()) {
            $clear_logs = $conn->prepare("DELETE FROM user_logs WHERE username = ? AND login_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $clear_logs->bind_param("s", $username_to_clear);
            $clear_logs->execute();
            header("Location: super_admin_dashboard.php?cleared=1"); exit();
        } else { $error_msg = "Failed to clear session: " . $conn->error; }
    } else { $error_msg = "You cannot clear your own session record."; }
} elseif (isset($_GET['clear_session']) && !$can_clear_session) {
    $error_msg = "Access Denied: You don't have permission to clear sessions.";
}

$stmt = $conn->prepare("UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP, is_online = 1 WHERE username = ?");
$stmt->bind_param("s", $current_user);
$stmt->execute();

$total_users = $conn->query("SELECT COUNT(*) as count FROM signinfo WHERE account_type = 'user'")->fetch_assoc()['count'] ?? 0;
$total_admins = $conn->query("SELECT COUNT(*) as count FROM signinfo WHERE account_type IN ('admin', 'super_admin')")->fetch_assoc()['count'] ?? 0;

if ($is_super_admin) {
    $online_stmt = $conn->prepare("SELECT s.*, si.fname, si.lname, si.profile_pic, si.account_type FROM user_sessions s LEFT JOIN signinfo si ON s.username = si.username WHERE s.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND s.is_online = 1 AND si.username != ? ORDER BY s.last_activity DESC");
    $online_stmt->bind_param("s", $current_user);
    $online_stmt->execute();
    $online_users = $online_stmt->get_result();
} else {
    $online_users = $conn->query("SELECT s.*, si.fname, si.lname, si.profile_pic, si.account_type FROM user_sessions s LEFT JOIN signinfo si ON s.username = si.username WHERE s.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND s.is_online = 1 AND si.account_type = 'user' ORDER BY s.last_activity DESC");
}

// ── All filter variables (defined first before any queries) ──
$current_filter = $_GET['role_filter'] ?? 'all';
$search         = trim($_GET['search'] ?? '');
$date_from      = $_GET['date_from']  ?? '';
$date_to        = $_GET['date_to']    ?? '';
$year_range_start = 2023;
$year_range_end   = (int)date('Y');

// ── All users query ──
$all_users_sql = "SELECT si.id_main, si.fname, si.lname, si.username, si.email, si.account_type, si.profile_pic, us.last_activity, us.is_online, us.device_info, us.browser, us.os, us.ip_address FROM signinfo si LEFT JOIN user_sessions us ON si.username = us.username WHERE 1=1";
if (!$is_super_admin) { $all_users_sql .= " AND si.account_type IN ('user', 'admin')"; }

if ($current_filter === 'super_admin' && !$is_super_admin) { $current_filter = 'all'; }
if ($current_filter === 'super_admin')  { $all_users_sql .= " AND si.account_type = 'super_admin'"; }
elseif ($current_filter === 'admin')    { $all_users_sql .= " AND si.account_type = 'admin'"; }
elseif ($current_filter === 'user')     { $all_users_sql .= " AND si.account_type = 'user'"; }

if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $all_users_sql .= " AND (si.fname LIKE '%$s%' OR si.lname LIKE '%$s%' OR si.username LIKE '%$s%' OR si.email LIKE '%$s%' OR CONCAT(si.fname,' ',si.lname) LIKE '%$s%')";
}

if (!empty($date_from)) {
    $all_users_sql .= " AND EXISTS (SELECT 1 FROM user_logs ul WHERE ul.username = si.username AND ul.action = 'login' AND DATE(ul.login_time) >= '" . $conn->real_escape_string($date_from) . "')";
}
if (!empty($date_to)) {
    $all_users_sql .= " AND EXISTS (SELECT 1 FROM user_logs ul WHERE ul.username = si.username AND ul.action = 'login' AND DATE(ul.login_time) <= '" . $conn->real_escape_string($date_to) . "')";
}

$all_users_sql .= " ORDER BY CASE si.account_type WHEN 'super_admin' THEN 1 WHEN 'admin' THEN 2 WHEN 'user' THEN 3 END, us.last_activity DESC";
$all_users = $conn->query($all_users_sql);

$log_limit  = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
if (!in_array($log_limit, [5, 10, 30, 50, 100])) $log_limit = 50;
$log_page   = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
$log_offset = ($log_page - 1) * $log_limit;

$role_condition = "";
if ($current_filter === 'super_admin')  { $role_condition = " AND si.account_type = 'super_admin'"; }
elseif ($current_filter === 'admin')    { $role_condition = " AND si.account_type = 'admin'"; }
elseif ($current_filter === 'user')     { $role_condition = " AND si.account_type = 'user'"; }

$search_condition = "";
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $search_condition = " AND (si.fname LIKE '%$s%' OR si.lname LIKE '%$s%' OR l.username LIKE '%$s%' OR CONCAT(si.fname,' ',si.lname) LIKE '%$s%')";
}

$log_date_condition = "";
if (!empty($date_from)) $log_date_condition .= " AND DATE(l.login_time) >= '" . $conn->real_escape_string($date_from) . "'";
if (!empty($date_to))   $log_date_condition .= " AND DATE(l.login_time) <= '" . $conn->real_escape_string($date_to) . "'";

$total_logs_result = $conn->query("
    SELECT COUNT(*) as count 
    FROM user_logs l 
    LEFT JOIN signinfo si ON l.username = si.username 
    WHERE l.action = 'login'
    $role_condition
    $log_date_condition
    $search_condition
");
$total_logs      = $total_logs_result->fetch_assoc()['count'] ?? 0;
$total_log_pages = ceil($total_logs / $log_limit);

$log_stmt = $conn->prepare("
    SELECT 
        l.*, 
        si.fname, si.lname, si.account_type, si.profile_pic,
        CASE 
            WHEN l.logout_time IS NOT NULL 
            THEN TIMESTAMPDIFF(SECOND, l.login_time, l.logout_time)
            ELSE NULL 
        END as duration_seconds
    FROM user_logs l 
    LEFT JOIN signinfo si ON l.username = si.username 
    WHERE l.action = 'login'
    $role_condition
    $log_date_condition
    $search_condition
    ORDER BY l.login_time DESC 
    LIMIT ? OFFSET ?
");
$log_stmt->bind_param("ii", $log_limit, $log_offset);
$log_stmt->execute();
$activity_logs = $log_stmt->get_result();

// ── Counts ──
$total_super_admins   = $conn->query("SELECT COUNT(*) as count FROM signinfo WHERE account_type='super_admin'")->fetch_assoc()['count'] ?? 0;
$total_regular_admins = $conn->query("SELECT COUNT(*) as count FROM signinfo WHERE account_type='admin'")->fetch_assoc()['count'] ?? 0;
$total_regular_users  = $conn->query("SELECT COUNT(*) as count FROM signinfo WHERE account_type='user'")->fetch_assoc()['count'] ?? 0;
$online_count         = $online_users->num_rows;

$profile_stmt = $conn->prepare("SELECT fname, lname, profile_pic, email, username FROM signinfo WHERE username = ?");
$profile_stmt->bind_param("s", $current_user);
$profile_stmt->execute();
$profile_data = $profile_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace — <?php echo $is_super_admin ? 'Super Admin' : 'Admin'; ?> Dashboard</title>
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
            --accent-purple: #9B7FFF;
            --border: rgba(201,168,76,0.12);
            --border-subtle: rgba(255,255,255,0.06);
            --sidebar-w: 270px;
            --radius: 14px;
            --radius-sm: 8px;
            --shadow: 0 8px 32px rgba(0,0,0,0.4);
            --shadow-sm: 0 4px 16px rgba(0,0,0,0.25);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--dark);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* ─── SCROLLBAR ─── */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--dark-2); }
        ::-webkit-scrollbar-thumb { background: var(--dark-5); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--gold-dark); }

        /* ─── SIDEBAR ─── */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--dark-2);
            border-right: 1px solid var(--border);
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-brand {
            padding: 1.75rem 1.5rem 1.25rem;
            border-bottom: 1px solid var(--border-subtle);
        }

        .sidebar-brand-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.25rem;
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: var(--dark);
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(201,168,76,0.3);
        }

        .brand-text h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            color: var(--gold-light);
            line-height: 1.2;
        }

        .brand-text small {
            font-size: 0.7rem;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        /* Profile */
        .sidebar-profile {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            gap: 0.875rem;
        }

        .profile-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid var(--gold);
            overflow: hidden;
            flex-shrink: 0;
            position: relative;
            box-shadow: 0 0 0 3px rgba(201,168,76,0.15);
        }

        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .profile-avatar-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--gold-dark), var(--gold));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
        }

        .profile-status {
            position: absolute;
            bottom: 1px;
            right: 1px;
            width: 10px;
            height: 10px;
            background: var(--accent-green);
            border-radius: 50%;
            border: 2px solid var(--dark-2);
        }

        .profile-info { flex: 1; min-width: 0; }

        .profile-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .profile-username {
            font-size: 0.72rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.2rem;
            margin-top: 0.1rem;
        }

        .profile-email {
            font-size: 0.68rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.2rem;
            margin-top: 0.1rem;
            word-break: break-all;
            line-height: 1.3;
        }

        /* Role pill */
        .role-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.6rem;
            border-radius: 99px;
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-top: 0.3rem;
        }

        .role-pill.super { background: rgba(201,168,76,0.15); color: var(--gold-light); border: 1px solid rgba(201,168,76,0.3); }
        .role-pill.admin { background: rgba(255,140,66,0.15); color: var(--accent-orange); border: 1px solid rgba(255,140,66,0.3); }

        /* Nav */
        .nav-section { padding: 1rem 0; flex: 1; }

        .nav-label {
            padding: 0.6rem 1.5rem 0.3rem;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 400;
            transition: all 0.2s;
            border-left: 2px solid transparent;
            position: relative;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.04);
            color: var(--text-primary);
            border-left-color: var(--border);
        }

        .nav-item.active {
            background: rgba(201,168,76,0.08);
            color: var(--gold-light);
            border-left-color: var(--gold);
            font-weight: 500;
        }

        .nav-item i { width: 18px; text-align: center; font-size: 0.875rem; }

        .nav-badge {
            margin-left: auto;
            background: var(--accent-green);
            color: var(--dark);
            padding: 0.1rem 0.45rem;
            border-radius: 99px;
            font-size: 0.6rem;
            font-weight: 700;
        }

        .sidebar-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-subtle);
        }

        /* ─── MAIN ─── */
        .main {
            flex: 1;
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Topbar */
        .topbar {
            background: var(--dark-2);
            border-bottom: 1px solid var(--border-subtle);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(10px);
        }

        .topbar-left h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .topbar-left p {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
        }

        .topbar-right { display: flex; align-items: center; gap: 0.75rem; }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.875rem;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-pill.role-super { background: rgba(201,168,76,0.12); color: var(--gold-light); border: 1px solid rgba(201,168,76,0.25); }
        .badge-pill.role-admin { background: rgba(255,140,66,0.12); color: var(--accent-orange); border: 1px solid rgba(255,140,66,0.25); }
        .badge-pill.live { background: rgba(62,207,142,0.1); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.25); }

        .live-dot {
            width: 7px; height: 7px;
            background: var(--accent-green);
            border-radius: 50%;
            animation: blink 2s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(62,207,142,0.5); }
            50% { opacity: 0.6; box-shadow: 0 0 0 5px rgba(62,207,142,0); }
        }

        /* Page content */
        .page-content { padding: 2rem; flex: 1; }

        /* ─── ALERTS ─── */
        .alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .alert-success { background: rgba(62,207,142,0.1); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.25); }
        .alert-error { background: rgba(255,71,87,0.1); color: var(--accent-red); border: 1px solid rgba(255,71,87,0.25); }

        /* ─── STATS GRID ─── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .stat-card {
            background: var(--dark-3);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius);
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.25s;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: var(--card-accent, var(--gold));
            opacity: 0.6;
        }

        .stat-card:hover {
            border-color: var(--border);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .stat-icon.blue { background: rgba(78,124,255,0.12); color: var(--accent-blue); }
        .stat-icon.green { background: rgba(62,207,142,0.12); color: var(--accent-green); }
        .stat-icon.orange { background: rgba(255,140,66,0.12); color: var(--accent-orange); }
        .stat-icon.gold { background: rgba(201,168,76,0.12); color: var(--gold-light); }
        .stat-icon.red { background: rgba(255,71,87,0.12); color: var(--accent-red); }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            font-family: 'Playfair Display', serif;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.2rem;
        }

        /* ─── FILTER BAR ─── */
        .filter-bar {
            background: var(--dark-3);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .filter-bar-label {
            font-size: 0.78rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-right: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .filter-divider {
            width: 1px;
            height: 24px;
            background: var(--border-subtle);
            margin: 0 0.25rem;
        }

        .filter-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.875rem;
            border-radius: 99px;
            font-size: 0.78rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid transparent;
            color: var(--text-secondary);
            background: transparent;
        }

        .filter-btn:hover { background: var(--dark-4); color: var(--text-primary); }

        .filter-btn.active-btn, .filter-btn.active-btn:hover {
            font-weight: 600;
        }

        .filter-btn.all { border-color: rgba(201,168,76,0.3); }
        .filter-btn.all.active-btn { background: rgba(201,168,76,0.12); color: var(--gold-light); border-color: var(--gold-dark); }

        .filter-btn.f-super { border-color: rgba(201,168,76,0.2); }
        .filter-btn.f-super.active-btn { background: rgba(201,168,76,0.1); color: var(--gold-light); border-color: rgba(201,168,76,0.4); }

        .filter-btn.f-admin { border-color: rgba(255,140,66,0.2); }
        .filter-btn.f-admin.active-btn { background: rgba(255,140,66,0.1); color: var(--accent-orange); border-color: rgba(255,140,66,0.4); }

        .filter-btn.f-user { border-color: rgba(78,124,255,0.2); }
        .filter-btn.f-user.active-btn { background: rgba(78,124,255,0.1); color: var(--accent-blue); border-color: rgba(78,124,255,0.4); }

        .filter-btn.f-clear { border-color: rgba(255,71,87,0.2); color: var(--accent-red); }
        .filter-btn.f-clear:hover { background: rgba(255,71,87,0.08); }

        .filter-count {
            background: rgba(255,255,255,0.08);
            padding: 0.05rem 0.35rem;
            border-radius: 99px;
            font-size: 0.68rem;
        }

        .filter-status {
            margin-left: auto;
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* ─── TWO PANEL GRID ─── */
        .panels-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.75rem;
        }

        /* ─── PANEL ─── */
        .panel {
            background: var(--dark-3);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .panel-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--dark-4);
        }

        .panel-title {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .panel-title-icon {
            width: 28px; height: 28px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }

        .panel-title-icon.green { background: rgba(62,207,142,0.15); color: var(--accent-green); }
        .panel-title-icon.gold { background: rgba(201,168,76,0.15); color: var(--gold-light); }
        .panel-title-icon.brown { background: rgba(201,168,76,0.12); color: var(--gold); }

        .panel-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.6rem;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .panel-badge.green { background: rgba(62,207,142,0.12); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.2); }
        .panel-badge.gold { background: rgba(201,168,76,0.12); color: var(--gold-light); border: 1px solid rgba(201,168,76,0.2); }

        .panel-body { max-height: 420px; overflow-y: auto; }

        /* Online user item */
        .online-item {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid var(--border-subtle);
            transition: background 0.15s;
        }

        .online-item:last-child { border-bottom: none; }
        .online-item:hover { background: rgba(255,255,255,0.02); }

        .online-avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            position: relative;
        }

        .online-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .online-avatar-placeholder {
            width: 100%; height: 100%;
            background: linear-gradient(135deg, var(--dark-5), var(--dark-4));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .online-dot {
            position: absolute;
            bottom: 0; right: 0;
            width: 9px; height: 9px;
            background: var(--accent-green);
            border-radius: 50%;
            border: 2px solid var(--dark-3);
        }

        .online-info { flex: 1; min-width: 0; }

        .online-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .online-meta {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-type-chip {
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .chip-admin { background: rgba(255,140,66,0.15); color: var(--accent-orange); }
        .chip-super { background: rgba(201,168,76,0.15); color: var(--gold-light); }

        .online-time {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.68rem;
            color: var(--text-muted);
            text-align: right;
            white-space: nowrap;
        }

        /* ─── DATA TABLE ─── */
        .data-table { width: 100%; border-collapse: collapse; }

        .data-table thead th {
            background: var(--dark-4);
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
            position: sticky;
            top: 0;
            z-index: 5;
            border-bottom: 1px solid var(--border-subtle);
        }

        .data-table tbody td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-subtle);
            font-size: 0.82rem;
            color: var(--text-secondary);
            vertical-align: middle;
        }

        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover td { background: rgba(255,255,255,0.02); }

        /* User cell */
        .user-cell { display: flex; align-items: center; gap: 0.7rem; }

        .user-avatar-sm {
            width: 32px; height: 32px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            background: var(--dark-5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .user-avatar-sm img { width: 100%; height: 100%; object-fit: cover; }

        .user-cell-name { font-size: 0.83rem; font-weight: 600; color: var(--text-primary); }
        .user-cell-username { font-size: 0.7rem; color: var(--text-muted); }

        .you-tag {
            display: inline-block;
            background: rgba(201,168,76,0.15);
            color: var(--gold-light);
            padding: 0.05rem 0.35rem;
            border-radius: 4px;
            font-size: 0.58rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-left: 0.25rem;
        }

        /* Type badges */
        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .type-super { background: rgba(201,168,76,0.12); color: var(--gold-light); }
        .type-admin { background: rgba(255,140,66,0.12); color: var(--accent-orange); }
        .type-user { background: rgba(78,124,255,0.1); color: var(--accent-blue); }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.55rem;
            border-radius: 99px;
            font-size: 0.68rem;
            font-weight: 600;
        }

        .status-online { background: rgba(62,207,142,0.1); color: var(--accent-green); }
        .status-offline { background: rgba(255,255,255,0.05); color: var(--text-muted); }
        .status-banned { background: rgba(255,71,87,0.1); color: var(--accent-red); }

        .status-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }

        /* IP */
        .ip-tag {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            background: var(--dark-4);
            color: var(--text-muted);
            padding: 0.15rem 0.45rem;
            border-radius: 5px;
        }

        /* Time */
        .time-val {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            color: var(--accent-green);
        }

        .time-val.logout { color: var(--accent-red); }

        .active-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.7rem;
            color: var(--accent-green);
            font-weight: 600;
        }

        /* ─── SUMMARY ROW ─── */
        .summary-row {
            background: var(--dark-3);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius);
            padding: 1rem 1.5rem;
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 1.75rem;
        }

        .summary-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.83rem;
        }

        .summary-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
        }

        .summary-dot.super { background: var(--gold); }
        .summary-dot.admin { background: var(--accent-orange); }
        .summary-dot.user { background: var(--accent-blue); }

        .summary-label { color: var(--text-muted); }
        .summary-val { font-weight: 700; color: var(--text-primary); margin-left: 0.2rem; }

        /* ─── EMPTY STATE ─── */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            opacity: 0.2;
            display: block;
        }

        .empty-state p { font-size: 0.875rem; }

        /* ─── DEVICE ICON ─── */
        .device-info {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* ─── ACCESS LABEL ─── */
        .access-label {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.7rem;
            border-radius: 99px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .access-full { background: rgba(62,207,142,0.1); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.2); }
        .access-limited { background: rgba(255,140,66,0.1); color: var(--accent-orange); border: 1px solid rgba(255,140,66,0.2); }
        .access-view { background: rgba(78,124,255,0.1); color: var(--accent-blue); border: 1px solid rgba(78,124,255,0.2); }

        @media (max-width: 1400px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 1100px) { .panels-grid { grid-template-columns: 1fr; } }
        @media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>

<!-- ═══════════════════════════════════════════════ SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-logo">
            <div class="brand-icon">
                <i class="fas fa-<?php echo $is_super_admin ? 'shield-alt' : 'user-shield'; ?>"></i>
            </div>
            <div class="brand-text">
                <h2>Furniplace</h2>
                <small><?php echo $is_super_admin ? 'Super Admin Console' : 'Admin Panel'; ?></small>
            </div>
        </div>
    </div>

    <div class="sidebar-profile">
        <div class="profile-avatar">
            <?php if (!empty($profile_data['profile_pic']) && file_exists($profile_data['profile_pic'])): ?>
                <img src="<?php echo htmlspecialchars($profile_data['profile_pic']); ?>" alt="Profile">
            <?php else: ?>
                <div class="profile-avatar-placeholder">
                    <?php echo strtoupper(substr($profile_data['fname'] ?? $current_user, 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="profile-status"></div>
        </div>
        <div class="profile-info">
            <div class="profile-name"><?php echo htmlspecialchars(($profile_data['fname'] ?? '') . ' ' . ($profile_data['lname'] ?? '')); ?></div>
            <div class="profile-username"><i class="fas fa-at" style="font-size:0.6rem;"></i> <?php echo htmlspecialchars($current_user); ?></div>
            <div class="profile-email"><i class="fas fa-envelope" style="font-size:0.6rem; flex-shrink:0; margin-top:1px;"></i> <?php echo htmlspecialchars($profile_data['email'] ?? ''); ?></div>
            <div class="role-pill <?php echo $is_super_admin ? 'super' : 'admin'; ?>">
                <i class="fas fa-<?php echo $is_super_admin ? 'crown' : 'shield-alt'; ?>" style="font-size:0.55rem;"></i>
                <?php echo $is_super_admin ? 'Super Admin' : 'Admin'; ?>
            </div>
        </div>
    </div>

    <nav class="nav-section">
        <div class="nav-label">Dashboard</div>
        <a href="super_admin_dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'super_admin_dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> Activity Monitor
            <?php if ($online_count > 0): ?>
                <span class="nav-badge"><?php echo $online_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="manage_products.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_products.php' ? 'active' : ''; ?>">
            <i class="fas fa-box"></i> Manage Products
        </a>
        <a href="user_management.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'user_management.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> User Management
        </a>
        <a href="admin_management.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_management.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i> Admin Management
        </a>
        <a href="product_activity_log.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'product_activity_log.php' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i> Product Activity
        </a>
        <a href="admin_activity_logs.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_activity_logs.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> Admin Activity Logs
        </a>
        <?php if ($is_super_admin): ?>
        <a href="view_deletion_proofs.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'view_deletion_proofs.php' ? 'active' : ''; ?>">
            <i class="fas fa-camera"></i> Deletion Proofs
        </a>
        <?php endif; ?>

        <div class="nav-label" style="margin-top:0.5rem;">System</div>
        <a href="home.php" class="nav-item"><i class="fas fa-store"></i> View Store</a>
        <a href="admin_settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
        <a href="logout.php" class="nav-item" style="color: var(--accent-red); margin-top:0.25rem;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<!-- ═══════════════════════════════════════════════ MAIN -->
<div class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <h1>Activity Monitor</h1>
            <p>Real-time user tracking &amp; session management</p>
        </div>
        <div class="topbar-right">
            <?php if ($is_manager_mode): ?>
                <span class="access-label access-full"><i class="fas fa-unlock" style="font-size:0.65rem;"></i> Manager Access</span>
            <?php elseif ($is_viewer_mode): ?>
                <span class="access-label access-view"><i class="fas fa-eye" style="font-size:0.65rem;"></i> View Only</span>
            <?php endif; ?>

            <span class="badge-pill <?php echo $is_super_admin ? 'role-super' : 'role-admin'; ?>">
                <i class="fas fa-<?php echo $is_super_admin ? 'crown' : 'shield-alt'; ?>" style="font-size:0.65rem;"></i>
                <?php echo strtoupper(str_replace('_', ' ', $account_type)); ?>
                &nbsp;·&nbsp;
                <?php
                if ($control_level === 'full') echo 'FULL ACCESS';
                elseif ($control_level === 'limited') echo 'LIMITED';
                else echo strtoupper($control_level);
                ?>
            </span>
            <span class="badge-pill live">
                <span class="live-dot"></span> LIVE
            </span>
        </div>
    </div>

    <div class="page-content">

        <?php if (isset($error_msg)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['cleared'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> Session cleared successfully!</div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" style="--card-accent: var(--accent-blue);">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total_regular_users; ?></div>
                    <div class="stat-label">Regular Users</div>
                </div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-orange);">
                <div class="stat-icon orange"><i class="fas fa-user-shield"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total_regular_admins; ?></div>
                    <div class="stat-label">Admins</div>
                </div>
            </div>
            <div class="stat-card" style="--card-accent: var(--gold);">
                <div class="stat-icon gold"><i class="fas fa-crown"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total_super_admins; ?></div>
                    <div class="stat-label">Super Admins</div>
                </div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-green);">
                <div class="stat-icon green"><i class="fas fa-signal"></i></div>
                <div>
                    <div class="stat-value"><?php echo $online_count; ?></div>
                    <div class="stat-label">Currently Online</div>
                </div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-red);">
                <div class="stat-icon red"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-value" id="current-time" style="font-size:1.1rem;">--:--</div>
                    <div class="stat-label">Server Time</div>
                </div>
            </div>
        </div>

       <!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap; width:100%;">

        <!-- Search -->
        <div style="position:relative; flex:1; min-width:200px;">
            <i class="fas fa-search" style="position:absolute; left:0.875rem; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:0.75rem;"></i>
            <input 
                type="text" 
                name="search" 
                value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                placeholder="Search by name, username, email..."
                style="width:100%; background:var(--dark-4); border:1px solid var(--border-subtle); border-radius:99px; padding:0.5rem 1rem 0.5rem 2.25rem; font-size:0.8rem; color:var(--text-primary); outline:none; font-family:'DM Sans',sans-serif;"
            >
        </div>

        <div style="width:1px; height:24px; background:var(--border-subtle);"></div>

        <!-- Date From -->
        <div style="display:flex; align-items:center; gap:0.4rem;">
            <span style="font-size:0.75rem; color:var(--text-muted); white-space:nowrap;">From</span>
            <input 
                type="date" 
                name="date_from" 
                value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>"
                style="background:var(--dark-4); border:1px solid var(--border-subtle); border-radius:99px; padding:0.45rem 0.875rem; font-size:0.75rem; color:var(--text-secondary); outline:none; font-family:'DM Sans',sans-serif; cursor:pointer;"
            >
        </div>

        <!-- Date To -->
        <div style="display:flex; align-items:center; gap:0.4rem;">
            <span style="font-size:0.75rem; color:var(--text-muted); white-space:nowrap;">To</span>
            <input 
                type="date" 
                name="date_to" 
                value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>"
                style="background:var(--dark-4); border:1px solid var(--border-subtle); border-radius:99px; padding:0.45rem 0.875rem; font-size:0.75rem; color:var(--text-secondary); outline:none; font-family:'DM Sans',sans-serif; cursor:pointer;"
            >
        </div>

        <div style="width:1px; height:24px; background:var(--border-subtle);"></div>

        <!-- Role Dropdown -->
<div style="display:flex; align-items:center; gap:0.4rem;">
    <span style="font-size:0.75rem; color:var(--text-muted); white-space:nowrap;">Role</span>
    <select name="role_filter" onchange="this.form.submit()" style="
        background:var(--dark-4); color:var(--text-secondary);
        border:1px solid var(--border-subtle); border-radius:99px;
        padding:0.45rem 0.875rem; font-size:0.75rem; cursor:pointer;
        outline:none; font-family:'DM Sans',sans-serif;">
        <option value="all"         <?php echo ($current_filter === 'all')         ? 'selected' : ''; ?>>All Roles</option>
        <?php if ($is_super_admin): ?>
        <option value="super_admin" <?php echo ($current_filter === 'super_admin') ? 'selected' : ''; ?>>Super Admin</option>
        <?php endif; ?>
        <option value="admin"       <?php echo ($current_filter === 'admin')       ? 'selected' : ''; ?>>Admin</option>
        <option value="user"        <?php echo ($current_filter === 'user')        ? 'selected' : ''; ?>>User</option>
    </select>
</div>

<div style="width:1px; height:24px; background:var(--border-subtle);"></div>

        <!-- Apply & Clear -->
        <button type="submit" style="
            display:inline-flex; align-items:center; gap:0.4rem;
            padding:0.45rem 1rem; border-radius:99px; font-size:0.78rem; font-weight:600;
            background:rgba(201,168,76,0.15); color:var(--gold-light);
            border:1px solid rgba(201,168,76,0.3); cursor:pointer; font-family:'DM Sans',sans-serif;">
            <i class="fas fa-filter" style="font-size:0.65rem;"></i> Apply
            
        </button>

        <?php if (!empty($_GET['search']) || !empty($_GET['date_from']) || !empty($_GET['date_to']) || (isset($_GET['role_filter']) && $_GET['role_filter'] !== 'all')): ?>
        <a href="?" style="
            display:inline-flex; align-items:center; gap:0.4rem;
            padding:0.45rem 1rem; border-radius:99px; font-size:0.78rem; font-weight:600;
            background:rgba(255,71,87,0.1); color:var(--accent-red);
            border:1px solid rgba(255,71,87,0.25); text-decoration:none;">
            <i class="fas fa-times" style="font-size:0.65rem;"></i> Clear
        </a>
        <?php endif; ?>

    </form>
</div>


       <!-- Login History -->
<div class="panel" style="margin-top: 1.5rem;">
    <div class="panel-header" style="padding: 1.25rem 1.5rem;">
        <div class="panel-title" style="gap: 0.75rem;">
            <div class="panel-title-icon gold" style="width:34px; height:34px;">
                <i class="fas fa-history"></i>
            </div>
            <div>
                <div style="font-size: 1rem; font-weight: 600; color: var(--text-primary);">Login History</div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem;">
                    All login & logout records — all roles
                </div>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.3rem 0.75rem; border-radius:99px; font-size:0.72rem; font-weight:600; background:rgba(201,168,76,0.12); color:var(--gold-light); border:1px solid rgba(201,168,76,0.2);">
                <i class="fas fa-list" style="font-size:0.65rem;"></i> <?php echo $total_logs; ?> Total Records
            </span>
            <span style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.3rem 0.75rem; border-radius:99px; font-size:0.72rem; font-weight:600; background:rgba(62,207,142,0.12); color:var(--accent-green); border:1px solid rgba(62,207,142,0.2);">
                Page <?php echo $log_page; ?> of <?php echo max(1, $total_log_pages); ?>
            </span>
        </div>
    </div>

    <div class="panel-body" style="max-height: 650px; overflow-y: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Login Time</th>
                    <th>Logout Time</th>
                    <th>Duration</th>
                    <th>IP Address</th>
                    <th>Device</th>
                    <th>OS</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($activity_logs && $activity_logs->num_rows > 0):
                while ($log = $activity_logs->fetch_assoc()):
                    $initials = strtoupper(substr($log['fname'] ?? '', 0, 1)) . strtoupper(substr($log['lname'] ?? '', 0, 1));
                    if (!$initials) $initials = strtoupper(substr($log['username'], 0, 2));

                    // Duration
                    $duration = '—';
                    $is_active = false;
                    if (!empty($log['logout_time'])) {
                        $diff = $log['duration_seconds'];
                        if ($diff < 60) $duration = $diff . 's';
                        elseif ($diff < 3600) $duration = floor($diff/60) . 'm ' . ($diff%60) . 's';
                        else $duration = floor($diff/3600) . 'h ' . floor(($diff%3600)/60) . 'm';
                    } else {
                        $is_active = true;
                        $diff = time() - strtotime($log['login_time']);
                        if ($diff < 60) $duration = $diff . 's';
                        elseif ($diff < 3600) $duration = floor($diff/60) . 'm';
                        else $duration = floor($diff/3600) . 'h ' . floor(($diff%3600)/60) . 'm';
                    }

                    $is_current = ($log['username'] === $current_user);
            ?>
            <tr>
                <!-- User -->
                <td>
                    <div class="user-cell">
                        <div class="user-avatar-sm">
                            <?php if (!empty($log['profile_pic']) && file_exists($log['profile_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($log['profile_pic']); ?>" alt="">
                            <?php else: ?>
                                <?php echo $initials ?: 'U'; ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="user-cell-name">
                                <?php echo htmlspecialchars(trim(($log['fname'] ?? '') . ' ' . ($log['lname'] ?? ''))); ?>
                                <?php if ($is_current): ?><span class="you-tag">You</span><?php endif; ?>
                            </div>
                            <div class="user-cell-username">@<?php echo htmlspecialchars($log['username']); ?></div>
                        </div>
                    </div>
                </td>

                <!-- Role -->
                <td>
                    <?php if ($log['account_type'] === 'super_admin'): ?>
                        <span class="type-badge type-super"><i class="fas fa-crown" style="font-size:0.6rem;"></i> Super</span>
                    <?php elseif ($log['account_type'] === 'admin'): ?>
                        <span class="type-badge type-admin"><i class="fas fa-user-shield" style="font-size:0.6rem;"></i> Admin</span>
                    <?php else: ?>
                        <span class="type-badge type-user"><i class="fas fa-user" style="font-size:0.6rem;"></i> User</span>
                    <?php endif; ?>
                </td>

                <!-- Login Time -->
                <td>
                    <?php if (!empty($log['login_time'])): ?>
                        <span class="time-val"><?php echo date('h:i A', strtotime($log['login_time'])); ?></span><br>
                        <span style="font-size:0.65rem; color:var(--text-muted);"><?php echo date('M d, Y', strtotime($log['login_time'])); ?></span>
                    <?php else: echo '<span style="color:var(--text-muted)">—</span>'; endif; ?>
                </td>

                <!-- Logout Time -->
                <td>
                    <?php if (!empty($log['logout_time'])): ?>
                        <span class="time-val logout"><?php echo date('h:i A', strtotime($log['logout_time'])); ?></span><br>
                        <span style="font-size:0.65rem; color:var(--text-muted);"><?php echo date('M d, Y', strtotime($log['logout_time'])); ?></span>
                    <?php elseif ($is_active): ?>
                        <span class="active-tag"><span class="status-dot"></span> Active</span>
                    <?php else: echo '<span style="color:var(--text-muted)">—</span>'; endif; ?>
                </td>

                <!-- Duration -->
                <td style="color:<?php echo $is_active ? 'var(--accent-green)' : 'var(--text-secondary)'; ?>; font-size:0.78rem; font-weight:600;">
                    <?php echo $duration; ?>
                </td>

                <!-- IP -->
                <td>
                    <?php if (!empty($log['ip_address'])): ?>
                        <span class="ip-tag"><?php echo htmlspecialchars($log['ip_address']); ?></span>
                    <?php else: echo '<span style="color:var(--text-muted)">—</span>'; endif; ?>
                </td>

                <!-- Device -->
                <td>
                    <?php if (!empty($log['device_info'])): ?>
                        <div class="device-info">
                            <i class="fas fa-<?php echo $log['device_info'] == 'Mobile' ? 'mobile-alt' : ($log['device_info'] == 'Tablet' ? 'tablet-alt' : 'desktop'); ?>"></i>
                            <?php echo htmlspecialchars($log['device_info']); ?>
                        </div>
                    <?php else: echo '<span style="color:var(--text-muted)">—</span>'; endif; ?>
                </td>

                <!-- OS -->
                <td style="font-size:0.78rem; color:var(--text-secondary);">
                    <?php echo htmlspecialchars($log['os'] ?? '—'); ?>
                </td>

            
            </tr>
            <?php endwhile; else: ?>
            <tr>
                <td colspan="9">
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No login history found</p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_log_pages > 1): ?>
   <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; background: var(--dark-4); border-radius: 0 0 var(--radius) var(--radius);">
    <div style="display:flex; align-items:center; gap:0.75rem;">
        <span style="font-size: 0.78rem; color: var(--text-muted);">
            Showing <?php echo ($log_offset + 1); ?>–<?php echo min($log_offset + $log_limit, $total_logs); ?> of <?php echo $total_logs; ?> records
        </span>

        </form>
    </div>
        <div style="display: flex; gap: 0.4rem; flex-wrap: wrap; align-items: center;">
    <!-- Per Page Dropdown beside page numbers -->
    <form method="GET" style="display:flex; align-items:center; gap:0.4rem; margin-right:0.5rem;">
        <?php foreach ($_GET as $k => $v): if ($k === 'per_page' || $k === 'log_page') continue; ?>
            <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
        <?php endforeach; ?>
        <select name="per_page" onchange="this.form.submit()" style="
            background:var(--dark-5); color:var(--text-secondary);
            border:1px solid var(--border-subtle); border-radius:99px;
            padding:0.35rem 0.75rem; font-size:0.75rem; cursor:pointer;
            outline:none; font-family:'DM Sans',sans-serif;">
            <?php foreach ([5, 10, 30, 50, 100] as $opt): ?>
                <option value="<?php echo $opt; ?>" <?php echo ($log_limit == $opt) ? 'selected' : ''; ?>>
                    <?php echo $opt; ?> rows
                </option>
            <?php endforeach; ?>
        </select>
    </form>
            <!-- First -->
            <?php if ($log_page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['log_page' => 1])); ?>" style="padding:0.4rem 0.8rem; background:var(--dark-5); border:1px solid var(--border-subtle); border-radius:var(--radius-sm); color:var(--text-secondary); text-decoration:none; font-size:0.78rem;">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['log_page' => $log_page - 1])); ?>" style="padding:0.4rem 0.8rem; background:var(--dark-5); border:1px solid var(--border-subtle); border-radius:var(--radius-sm); color:var(--text-secondary); text-decoration:none; font-size:0.78rem;">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>

            <!-- Page numbers -->
            <?php
            $start_page = max(1, $log_page - 2);
            $end_page   = min($total_log_pages, $log_page + 2);
            for ($p = $start_page; $p <= $end_page; $p++):
            ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['log_page' => $p])); ?>" style="padding:0.4rem 0.8rem; background:<?php echo $p === $log_page ? 'rgba(201,168,76,0.15)' : 'var(--dark-5)'; ?>; border:1px solid <?php echo $p === $log_page ? 'rgba(201,168,76,0.4)' : 'var(--border-subtle)'; ?>; border-radius:var(--radius-sm); color:<?php echo $p === $log_page ? 'var(--gold-light)' : 'var(--text-secondary)'; ?>; text-decoration:none; font-size:0.78rem; font-weight:<?php echo $p === $log_page ? '600' : '400'; ?>;">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>

            <!-- Last -->
            <?php if ($log_page < $total_log_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['log_page' => $log_page + 1])); ?>" style="padding:0.4rem 0.8rem; background:var(--dark-5); border:1px solid var(--border-subtle); border-radius:var(--radius-sm); color:var(--text-secondary); text-decoration:none; font-size:0.78rem;">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['log_page' => $total_log_pages])); ?>" style="padding:0.4rem 0.8rem; background:var(--dark-5); border:1px solid var(--border-subtle); border-radius:var(--radius-sm); color:var(--text-secondary); text-decoration:none; font-size:0.78rem;">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

    </div><!-- /page-content -->
</div><!-- /main -->

<script>
function updateTime() {
    const now = new Date();
    document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
setInterval(updateTime, 1000);
updateTime();

fetch('ping.php');
setInterval(() => fetch('ping.php'), 30000);
window.addEventListener('beforeunload', () => navigator.sendBeacon('ping.php?action=offline'));
setTimeout(() => location.reload(), 60000);
</script>
</body>
</html>