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

// ONLY SUPER ADMINS CAN VIEW DELETION PROOFS AND RESTORE
if (!$is_super_admin) {
    header("Location: super_admin_dashboard.php?error=access_denied");
    exit();
}

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

// ========== HANDLE RESTORE ==========
if (isset($_GET['restore']) && !empty($_GET['restore'])) {
    $archive_id = $_GET['restore'];
    $restore_message = '';
    $restore_error = '';
    
    // Get archived admin data
    $archive_stmt = $conn->prepare("SELECT * FROM deleted_admins_archive WHERE id = ? AND restored_at IS NULL");
    $archive_stmt->bind_param("i", $archive_id);
    $archive_stmt->execute();
    $archive_data = $archive_stmt->get_result()->fetch_assoc();
    
    if (!$archive_data) {
        $restore_error = "Archived admin not found or already restored.";
    } else {
        // Check if username or email already exists in signinfo
        $check_exists = $conn->prepare("SELECT id_main FROM signinfo WHERE username = ? OR email = ?");
        $check_exists->bind_param("ss", $archive_data['username'], $archive_data['email']);
        $check_exists->execute();
        
        if ($check_exists->get_result()->num_rows > 0) {
            $restore_error = "Cannot restore: Username or email already exists in the system.";
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // CREATE VARIABLES FOR ALL FIELDS - HANDLE NULLS WITH EMPTY STRINGS
                $id_main = (string)$archive_data['id_main'];
                $fname = (string)$archive_data['fname'];
                $lname = (string)$archive_data['lname'];
                $mi = isset($archive_data['mi']) && $archive_data['mi'] !== null ? (string)$archive_data['mi'] : '';
                $Ename = isset($archive_data['Ename']) && $archive_data['Ename'] !== null ? (string)$archive_data['Ename'] : '';
                $username = (string)$archive_data['username'];
                $email = (string)$archive_data['email'];
                $password = (string)$archive_data['password'];
                $Purok = isset($archive_data['Purok']) && $archive_data['Purok'] !== null ? (string)$archive_data['Purok'] : '';
                $Barranggay = isset($archive_data['Barranggay']) && $archive_data['Barranggay'] !== null ? (string)$archive_data['Barranggay'] : '';
                $CM = isset($archive_data['CM']) && $archive_data['CM'] !== null ? (string)$archive_data['CM'] : '';
                $Province = isset($archive_data['Province']) && $archive_data['Province'] !== null ? (string)$archive_data['Province'] : '';
                $Country = isset($archive_data['Country']) && $archive_data['Country'] !== null ? (string)$archive_data['Country'] : 'Philippines';
                $zip = isset($archive_data['zip']) && $archive_data['zip'] !== null ? (string)$archive_data['zip'] : '';
                $sex = (string)$archive_data['sex'];
                $bd = (string)$archive_data['bd'];
                $profile_pic = isset($archive_data['profile_pic']) && $archive_data['profile_pic'] !== null ? (string)$archive_data['profile_pic'] : '';
                $account_type = (string)$archive_data['account_type'];
                $control_level = isset($archive_data['control_level']) && $archive_data['control_level'] !== null ? (string)$archive_data['control_level'] : 'limited';
                $phone = isset($archive_data['phone']) && $archive_data['phone'] !== null ? (string)$archive_data['phone'] : '';
                $address = isset($archive_data['address']) && $archive_data['address'] !== null ? (string)$archive_data['address'] : '';
                $created_at = (string)$archive_data['created_at'];
                
                // Prepare the INSERT statement
                $restore_sql = "INSERT INTO signinfo (
                    id_main, fname, lname, mi, Ename, username, email, password,
                    Purok, Barranggay, CM, Province, Country, zip, sex, bd,
                    profile_pic, account_type, control_level, phone, address, created_at,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
                
                $restore_stmt = $conn->prepare($restore_sql);
                
                // Bind parameters - using variables only
                $restore_stmt->bind_param(
                    "ssssssssssssssssssssss",
                    $id_main,
                    $fname,
                    $lname,
                    $mi,
                    $Ename,
                    $username,
                    $email,
                    $password,
                    $Purok,
                    $Barranggay,
                    $CM,
                    $Province,
                    $Country,
                    $zip,
                    $sex,
                    $bd,
                    $profile_pic,
                    $account_type,
                    $control_level,
                    $phone,
                    $address,
                    $created_at
                );
                
                if (!$restore_stmt->execute()) {
                    throw new Exception("Failed to restore account: " . $restore_stmt->error);
                }
                
                // Update archive with restoration info
                $update_archive = $conn->prepare("UPDATE deleted_admins_archive SET 
                    restored_at = NOW(), 
                    restored_by = ? 
                    WHERE id = ?");
                $update_archive->bind_param("si", $current_user, $archive_id);
                
                if (!$update_archive->execute()) {
                    throw new Exception("Failed to update archive: " . $update_archive->error);
                }
                
                // Log the restoration
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $target_name = $archive_data['fname'] . ' ' . $archive_data['lname'];
                
                $log_sql = "INSERT INTO user_logs (
                    username, action, target_user, target_name, ip_address, 
                    device_info, browser, os, login_time
                ) VALUES (?, 'restored_admin', ?, ?, ?, 'Admin Panel', 'System', 'System', NOW())";
                
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bind_param("ssss", 
                    $current_user,
                    $archive_data['username'],
                    $target_name,
                    $ip
                );
                
                if (!$log_stmt->execute()) {
                    throw new Exception("Failed to log restoration: " . $log_stmt->error);
                }
                
                $conn->commit();
                
                // Redirect with success message
                header("Location: view_deletion_proofs.php?restore_success=1&name=" . urlencode($archive_data['username']));
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $restore_error = "Restore failed: " . $e->getMessage();
            }
        }
    }
    
    // If there's an error, show it
    if (!empty($restore_error)) {
        echo '<div style="background: #FFEBEE; color: #C62828; padding: 2rem; margin: 2rem auto; max-width: 600px; border-radius: 12px; text-align: center; border: 2px solid #EF9A9A;">';
        echo '<i class="fas fa-exclamation-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i><br>';
        echo '<h3 style="margin-bottom: 1rem;">Restore Failed</h3>';
        echo '<p style="margin-bottom: 1.5rem;">' . htmlspecialchars($restore_error) . '</p>';
        echo '<a href="view_deletion_proofs.php" style="background: #C62828; color: white; padding: 0.75rem 2rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">';
        echo '<i class="fas fa-arrow-left"></i> Go back to Archive';
        echo '</a>';
        echo '</div>';
    }
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Build query - Get all deletion proofs with archive data
$sql = "SELECT da.*, 
               a.fname as admin_fname, 
               a.lname as admin_lname,
               a.profile_pic as admin_pic
        FROM deleted_admins_archive da
        LEFT JOIN signinfo a ON da.deleted_by = a.username
        WHERE 1=1";

$params = array();
$types = "";

// Search filter
if (!empty($search)) {
    $sql .= " AND (da.fname LIKE ? OR da.lname LIKE ? OR da.username LIKE ? OR da.deleted_by LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= "ssss";
}

// Date filter
if (!empty($filter_date)) {
    $sql .= " AND DATE(da.deleted_at) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

// Only show un-restored admins
$sql .= " AND da.restored_at IS NULL";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM deleted_admins_archive WHERE restored_at IS NULL";
$count_params = [];
$count_types = "";

if (!empty($search)) {
    $count_sql .= " AND (fname LIKE ? OR lname LIKE ? OR username LIKE ? OR deleted_by LIKE ?)";
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term]);
    $count_types .= "ssss";
}
if (!empty($filter_date)) {
    $count_sql .= " AND DATE(deleted_at) = ?";
    $count_params[] = $filter_date;
    $count_types .= "s";
}

$count_stmt = $conn->prepare($count_sql);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$total_archived = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_archived / $limit);

// Add pagination to main query
$sql .= " ORDER BY da.deleted_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$archived_admins = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_deletions,
    COUNT(DISTINCT deleted_by) as unique_admins,
    COUNT(DISTINCT username) as unique_deleted,
    SUM(CASE WHEN DATE(deleted_at) = CURDATE() THEN 1 ELSE 0 END) as today_deletions,
    SUM(CASE WHEN YEARWEEK(deleted_at) = YEARWEEK(CURDATE()) THEN 1 ELSE 0 END) as week_deletions
FROM deleted_admins_archive 
WHERE restored_at IS NULL";

$stats = $conn->query($stats_sql)->fetch_assoc();

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
    <title>Furniplace — Deletion Proofs Archive</title>
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

        /* ─── SUCCESS MESSAGE ─── */
        .success-message {
            background: rgba(62,207,142,0.1);
            border: 1px solid rgba(62,207,142,0.3);
            border-radius: var(--radius);
            padding: 1rem 1.5rem;
            margin: 1rem 0 2rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--accent-green);
        }
        .success-message i { font-size: 2rem; }
        .success-message-content { flex: 1; }
        .success-message-content strong { font-size: 1.1rem; display: block; margin-bottom: 0.2rem; }
        .success-message-content p { margin: 0; color: var(--text-secondary); }
        .success-close { color: var(--accent-green); font-size: 1.2rem; cursor: pointer; transition: all 0.2s; }
        .success-close:hover { transform: rotate(90deg); }

        /* ─── STATS GRID ─── */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.75rem; }
        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } }
        .stat-card { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); padding: 1.25rem; display: flex; align-items: center; gap: 1rem; transition: all 0.25s; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--card-accent, var(--gold)); opacity: 0.6; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); border-color: var(--border); }
        .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .stat-icon.red { background: rgba(255,71,87,0.12); color: var(--accent-red); }
        .stat-icon.blue { background: rgba(78,124,255,0.12); color: var(--accent-blue); }
        .stat-icon.orange { background: rgba(255,140,66,0.12); color: var(--accent-orange); }
        .stat-icon.green { background: rgba(62,207,142,0.12); color: var(--accent-green); }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: var(--text-primary); line-height: 1; font-family: 'Playfair Display', serif; }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }

        /* ─── FILTER SECTION ─── */
        .filter-section { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); padding: 1rem 1.25rem; margin-bottom: 1.75rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
        .search-box { position: relative; flex: 1; min-width: 250px; }
        .search-box input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; transition: all 0.2s; }
        .search-box input:focus { outline: none; border-color: var(--gold-dark); background: var(--dark-5); }
        .search-box input::placeholder { color: var(--text-muted); }
        .search-box i { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.8rem; }
        .filter-input { padding: 0.6rem 1rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; min-width: 180px; }
        .filter-input:focus { outline: none; border-color: var(--gold-dark); }
        .btn-clear { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-secondary); text-decoration: none; font-size: 0.875rem; transition: all 0.2s; }
        .btn-clear:hover { background: var(--dark-5); color: var(--text-primary); }

        /* ─── BUTTONS ─── */
        .btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.25rem; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); color: var(--dark); border: none; border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.25s; text-decoration: none; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(201,168,76,0.35); }
        .btn-danger { background: linear-gradient(135deg, var(--accent-red), #8B0000); color: white; }
        .btn-danger:hover { box-shadow: 0 6px 20px rgba(255,71,87,0.4); }
        .btn-secondary { background: transparent; border: 1px solid var(--border-subtle); color: var(--text-secondary); }
        .btn-secondary:hover { background: var(--dark-5); color: var(--text-primary); }

        /* ─── ARCHIVE GRID ─── */
        .archive-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .archive-card { background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius); overflow: hidden; transition: all 0.3s; position: relative; }
        .archive-card:hover { transform: translateY(-4px); border-color: var(--gold); box-shadow: var(--shadow); }

        .proof-image { width: 100%; height: 180px; overflow: hidden; background: var(--dark-5); position: relative; cursor: pointer; }
        .proof-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .archive-card:hover .proof-image img { transform: scale(1.05); }
        .proof-image-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(to top, rgba(0,0,0,0.9), transparent); color: var(--text-primary); padding: 1rem; transform: translateY(100%); transition: transform 0.3s; }
        .archive-card:hover .proof-image-overlay { transform: translateY(0); }
        .proof-image-overlay span { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; }

        .archive-content { padding: 1.5rem; }
        .archive-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
        .deleted-user { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--text-primary); margin: 0; }
        .deleted-user small { font-size: 0.8rem; color: var(--text-muted); display: block; margin-top: 0.25rem; }

        .status-badge { background: var(--accent-red); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; }

        .archive-details { background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 1rem; margin: 1rem 0; }
        .detail-row { display: flex; margin-bottom: 0.5rem; font-size: 0.85rem; }
        .detail-label { width: 100px; color: var(--text-muted); font-weight: 500; }
        .detail-value { color: var(--text-primary); font-weight: 600; }

        .deleted-by { display: flex; align-items: center; gap: 0.75rem; margin: 1rem 0; padding: 0.75rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); }
        .deleted-by-avatar { width: 35px; height: 35px; border-radius: 50%; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); display: flex; align-items: center; justify-content: center; color: var(--dark); font-size: 0.9rem; overflow: hidden; }
        .deleted-by-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .deleted-by-info { flex: 1; }
        .deleted-by-name { font-weight: 600; color: var(--text-primary); font-size: 0.9rem; }
        .deleted-by-time { font-size: 0.7rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem; }

        .restore-btn { background: linear-gradient(135deg, var(--accent-green), #1B5E20); color: white; border: none; padding: 0.75rem; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.3s; text-decoration: none; font-size: 0.9rem; margin-top: 1rem; }
        .restore-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(62,207,142,0.4); }

        .ip-address { font-family: 'JetBrains Mono', monospace; background: var(--dark-5); border: 1px solid var(--border-subtle); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem; color: var(--text-secondary); }

        /* ─── ZOOM MODAL ─── */
        .zoom-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 1000000; align-items: center; justify-content: center; padding: 2rem; backdrop-filter: blur(8px); }
        .zoom-modal.active { display: flex; }
        .zoom-content { max-width: 90%; max-height: 90vh; position: relative; }
        .zoom-content img { width: 100%; height: 100%; object-fit: contain; border-radius: 8px; box-shadow: var(--shadow); border: 2px solid var(--gold); }
        .zoom-close { position: absolute; top: -40px; right: 0; color: var(--text-primary); font-size: 2rem; cursor: pointer; background: rgba(255,255,255,0.1); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s; border: 1px solid var(--border-subtle); }
        .zoom-close:hover { background: var(--accent-red); transform: rotate(90deg); }
        .zoom-download { position: absolute; bottom: -40px; right: 0; color: var(--dark); background: var(--gold); padding: 0.5rem 1rem; border-radius: var(--radius-sm); text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; transition: all 0.3s; }
        .zoom-download:hover { background: var(--gold-dark); transform: translateY(-2px); }

        /* ─── EMPTY STATE ─── */
        .empty-state { grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; color: var(--text-muted); background: var(--dark-4); border-radius: var(--radius); border: 1px solid var(--border-subtle); }
        .empty-state i { font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.15; color: var(--gold); }
        .empty-state h3 { font-family: 'Playfair Display', serif; color: var(--text-primary); margin-bottom: 0.5rem; font-size: 1.3rem; }
        .empty-state p { font-size: 0.9rem; }

        /* ─── PAGINATION ─── */
        .pagination { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); margin-top: 2rem; flex-wrap: wrap; gap: 1rem; }
        .page-info { color: var(--text-muted); font-size: 0.8rem; }
        .page-btns { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .page-btn { padding: 0.4rem 0.8rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-secondary); text-decoration: none; font-size: 0.8rem; transition: all 0.2s; }
        .page-btn:hover, .page-btn.active { background: rgba(201,168,76,0.1); border-color: rgba(201,168,76,0.3); color: var(--gold-light); }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .archive-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-logo">
            <div class="brand-icon"><i class="fas fa-shield-alt"></i></div>
            <div class="brand-text">
                <h2>Furniplace</h2>
                <small>Super Admin Console</small>
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
            <div class="role-pill super">
                <i class="fas fa-crown"></i> Super Admin
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
        <a href="admin_activity_logs.php" class="nav-item"><i class="fas fa-history"></i> Admin Activity Logs</a>
        <a href="view_deletion_proofs.php" class="nav-item active"><i class="fas fa-camera"></i> Deletion Proofs</a>
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
            <h1><i class="fas fa-archive" style="color: var(--gold);"></i> Deleted Admins Archive</h1>
            <p>View and restore permanently deleted administrator accounts</p>
        </div>
        <div class="topbar-right">
            <span class="badge-pill super">
                <i class="fas fa-crown"></i> SUPER ADMIN • <?php echo $is_god_mode ? 'GOD MODE' : 'FULL ACCESS'; ?>
            </span>
        </div>
    </div>

    <div class="page-content">

        <!-- Success Message -->
        <?php if (isset($_GET['restore_success'])): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <div class="success-message-content">
                <strong>✅ Restore Successful!</strong>
                <p>Admin account <strong>"<?php echo htmlspecialchars($_GET['name'] ?? 'Admin'); ?>"</strong> has been successfully restored.</p>
            </div>
            <a href="view_deletion_proofs.php" class="success-close"><i class="fas fa-times"></i></a>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card" style="--card-accent: var(--accent-red);">
                <div class="stat-icon red"><i class="fas fa-trash-alt"></i></div>
                <div><div class="stat-value"><?php echo $stats['total_deletions'] ?? 0; ?></div><div class="stat-label">Deleted Admins</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-blue);">
                <div class="stat-icon blue"><i class="fas fa-user-shield"></i></div>
                <div><div class="stat-value"><?php echo $stats['unique_admins'] ?? 0; ?></div><div class="stat-label">Admins Who Deleted</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-orange);">
                <div class="stat-icon orange"><i class="fas fa-user-slash"></i></div>
                <div><div class="stat-value"><?php echo $stats['unique_deleted'] ?? 0; ?></div><div class="stat-label">Unique Deleted</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-green);">
                <div class="stat-icon green"><i class="fas fa-calendar-day"></i></div>
                <div><div class="stat-value"><?php echo $stats['today_deletions'] ?? 0; ?></div><div class="stat-label">Today's Deletions</div></div>
            </div>
        </div>

        <!-- Filters -->
        <form class="filter-section" method="GET">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by name, username, or who deleted..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <input type="date" name="date" class="filter-input" value="<?php echo $filter_date; ?>">
            
            <button type="submit" class="btn-primary btn-danger">
                <i class="fas fa-filter"></i> Filter
            </button>
            
            <?php if ($search || $filter_date): ?>
            <a href="view_deletion_proofs.php" class="btn-clear">
                <i class="fas fa-times"></i> Clear
            </a>
            <?php endif; ?>
        </form>

        <!-- Archive Grid -->
        <?php if ($archived_admins && $archived_admins->num_rows > 0): ?>
            <div class="archive-container">
                <?php while($admin = $archived_admins->fetch_assoc()): 
                    $proof_path = $admin['deletion_proof'];
                    $proof_exists = file_exists($proof_path);
                    $fullname = $admin['fname'] . ' ' . $admin['lname'];
                ?>
                <div class="archive-card">
                    <?php if ($proof_exists): ?>
                    <div class="proof-image" onclick="openZoom('<?php echo $proof_path; ?>')">
                        <img src="<?php echo htmlspecialchars($proof_path); ?>" alt="Deletion Proof">
                        <div class="proof-image-overlay">
                            <span><i class="fas fa-search-plus"></i> Click to view proof</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="proof-image" style="background: var(--dark-5); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-image-slash" style="font-size: 3rem; color: var(--text-muted);"></i>
                    </div>
                    <?php endif; ?>
                    
                    <div class="archive-content">
                        <div class="archive-header">
                            <div>
                                <h3 class="deleted-user">
                                    <?php echo htmlspecialchars($fullname); ?>
                                    <small>@<?php echo htmlspecialchars($admin['username']); ?></small>
                                </h3>
                            </div>
                            <span class="status-badge">DELETED</span>
                        </div>
                        
                        <div class="archive-details">
                            <div class="detail-row">
                                <span class="detail-label">Account Type:</span>
                                <span class="detail-value"><?php echo strtoupper($admin['account_type']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($admin['email']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Deleted:</span>
                                <span class="detail-value"><?php echo date('M d, Y', strtotime($admin['deleted_at'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="deleted-by">
                            <div class="deleted-by-avatar">
                                <?php if (!empty($admin['admin_pic']) && file_exists($admin['admin_pic'])): ?>
                                    <img src="<?php echo htmlspecialchars($admin['admin_pic']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-user-shield"></i>
                                <?php endif; ?>
                            </div>
                            <div class="deleted-by-info">
                                <div class="deleted-by-name">Deleted by: <?php echo htmlspecialchars($admin['deleted_by']); ?></div>
                                <div class="deleted-by-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('M d, Y h:i A', strtotime($admin['deleted_at'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <a href="?restore=<?php echo $admin['id']; ?>" 
                           class="restore-btn"
                           onclick="return confirm('Restore this admin account?\n\nUsername: <?php echo addslashes($admin['username']); ?>\nName: <?php echo addslashes($fullname); ?>\n\nThey will be able to log in again with their original credentials.');">
                            <i class="fas fa-undo-alt"></i> Restore Account
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-archive"></i>
                <h3>No Deleted Admins Found</h3>
                <p>No permanently deleted admin accounts in the archive.</p>
                <?php if ($search || $filter_date): ?>
                <a href="view_deletion_proofs.php" class="btn-primary" style="margin-top: 1rem; display: inline-flex;">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <div class="page-info">
                Page <?php echo $page; ?> of <?php echo $total_pages; ?> • 
                Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $limit, $total_archived); ?> of <?php echo $total_archived; ?> deleted admins
            </div>
            <div class="page-btns">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo urlencode($filter_date); ?>" class="page-btn">
                        <i class="fas fa-chevron-left"></i> Prev
                    </a>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++): 
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo urlencode($filter_date); ?>" 
                       class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo urlencode($filter_date); ?>" class="page-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Zoom Modal -->
        <div class="zoom-modal" id="zoomModal" onclick="if(event.target === this) closeZoom()">
            <div class="zoom-content">
                <img id="zoomImage" src="" alt="Zoomed Proof">
                <div class="zoom-close" onclick="closeZoom()">
                    <i class="fas fa-times"></i>
                </div>
                <a id="downloadLink" href="#" download class="zoom-download">
                    <i class="fas fa-download"></i> Download Proof
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Zoom functions
function openZoom(imagePath) {
    document.getElementById('zoomImage').src = imagePath;
    document.getElementById('downloadLink').href = imagePath;
    document.getElementById('zoomModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeZoom() {
    document.getElementById('zoomModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Close zoom with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeZoom();
    }
});

// Ping to stay online
fetch('ping.php');
setInterval(() => fetch('ping.php'), 30000);
</script>

</body>
</html>