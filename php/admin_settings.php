<?php
session_start();
include 'connection.php';
include 'permission_helper.php';

// Authentication check
if (!isset($_SESSION['user']) || !isset($_SESSION['account_type'])) {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['user'];
$account_type = $_SESSION['account_type'];
$is_super_admin = ($account_type === 'super_admin');
$is_admin = ($account_type === 'admin');

// ✅ ADD THIS DEFAULT PASSWORD DETECTION CODE RIGHT HERE:
// Check if user needs to change default password
$needs_password_change = false;

// First check if session variable exists
if (isset($_SESSION['default_password_change_required'])) {
    $needs_password_change = true;
} else {
    // Check if password is still the default 'admin123'
    $check_default = $conn->prepare("SELECT password FROM signinfo WHERE username = ?");
    $check_default->bind_param("s", $current_user);
    $check_default->execute();
    $result = $check_default->get_result();
    
    if ($result->num_rows > 0) {
        $hashed_password = $result->fetch_assoc()['password'];
        if (password_verify('admin123', $hashed_password)) {
            $needs_password_change = true;
            $_SESSION['default_password_change_required'] = true;
        }
    }
}


// If neither admin nor super_admin, kick out
if (!$is_super_admin && !$is_admin) {
    header("Location: login.php");
    exit();
}

// Load permissions
$permissions = loadPermissions($conn, $current_user);

// Handle profile picture upload
$upload_message = '';
$upload_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $target_dir = "uploads/profile_pics/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
    $new_filename = $current_user . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    $uploadOk = 1;
    
    // Check if image file is actual image
    $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
    if ($check === false) {
        $upload_error = "File is not an image.";
        $uploadOk = 0;
    }
    
    // Check file size (max 5MB)
    if ($_FILES["profile_picture"]["size"] > 5000000) {
        $upload_error = "File is too large. Maximum size is 5MB.";
        $uploadOk = 0;
    }
    
    // Allow certain file formats
    if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $upload_error = "Only JPG, JPEG, PNG & GIF files are allowed.";
        $uploadOk = 0;
    }
    
    if ($uploadOk == 1) {
        // Get old profile picture to delete
        $old_pic_query = $conn->prepare("SELECT profile_pic FROM signinfo WHERE username = ?");
        $old_pic_query->bind_param("s", $current_user);
        $old_pic_query->execute();
        $old_pic_result = $old_pic_query->get_result();
        $old_pic = $old_pic_result->fetch_assoc()['profile_pic'];
        
        // Upload new file
        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
            // Update database
            $update_stmt = $conn->prepare("UPDATE signinfo SET profile_pic = ? WHERE username = ?");
            $update_stmt->bind_param("ss", $target_file, $current_user);
            
            if ($update_stmt->execute()) {
                $upload_message = "Profile picture uploaded successfully.";
                
                // Delete old profile picture if exists and not default
                if ($old_pic && file_exists($old_pic) && strpos($old_pic, 'default') === false) {
                    unlink($old_pic);
                }
            } else {
                $upload_error = "Database update failed.";
            }
        } else {
            $upload_error = "Error uploading file.";
        }
    }
}

// Handle profile information update
$profile_message = '';
$profile_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $mi = $_POST['mi'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $purok = $_POST['purok'];
    $barangay = $_POST['Barranggay'];
    $cm = $_POST['CM'];
    $province = $_POST['Province'];
    $country = $_POST['Country'];
    $zip = $_POST['zip'];
    
    $update_stmt = $conn->prepare("UPDATE signinfo SET fname = ?, lname = ?, mi = ?, email = ?, phone = ?, address = ?, Purok = ?, Barranggay = ?, CM = ?, Province = ?, Country = ?, zip = ? WHERE username = ?");
    $update_stmt->bind_param("sssssssssssss", $fname, $lname, $mi, $email, $phone, $address, $purok, $barangay, $cm, $province, $country, $zip, $current_user);
    
    if ($update_stmt->execute()) {
        $profile_message = "Profile updated successfully.";
    } else {
        $profile_error = "Error updating profile: " . $conn->error;
    }
}

// Handle password change (NO CURRENT PASSWORD REQUIRED)
$password_message = '';
$password_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Check if passwords match
    if ($new_password !== $confirm_password) {
        $password_error = "Passwords do not match.";
    } 
    // Check minimum length
    elseif (strlen($new_password) < 8) {
        $password_error = "Password must be at least 8 characters long.";
    } 
    else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password in database
        $update_stmt = $conn->prepare("UPDATE signinfo SET password = ? WHERE username = ?");
        $update_stmt->bind_param("ss", $hashed_password, $current_user);
        
        if ($update_stmt->execute()) {
            $password_message = "Password changed successfully!";
            
            // ✅ Remove the default password warning session if it exists
            if (isset($_SESSION['default_password_change_required'])) {
                unset($_SESSION['default_password_change_required']);
            }
            
            // Optional: Log the password change
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $log_stmt = $conn->prepare("INSERT INTO user_logs (username, action, ip_address, login_time) VALUES (?, 'password_changed', ?, NOW())");
            $log_stmt->bind_param("ss", $current_user, $ip);
            $log_stmt->execute();
        } else {
            $password_error = "Error updating password. Please try again.";
        }
    }
}

// Handle security questions update
$security_message = '';
$security_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_security'])) {
    $sec_q1 = $_POST['sec_q1'];
    $sec_a1 = password_hash($_POST['sec_a1'], PASSWORD_DEFAULT);
    $sec_q2 = $_POST['sec_q2'];
    $sec_a2 = password_hash($_POST['sec_a2'], PASSWORD_DEFAULT);
    $sec_q3 = $_POST['sec_q3'];
    $sec_a3 = password_hash($_POST['sec_a3'], PASSWORD_DEFAULT);
    
    $update_stmt = $conn->prepare("UPDATE signinfo SET sec_q1 = ?, sec_a1 = ?, sec_q2 = ?, sec_a2 = ?, sec_q3 = ?, sec_a3 = ? WHERE username = ?");
    $update_stmt->bind_param("sssssss", $sec_q1, $sec_a1, $sec_q2, $sec_a2, $sec_q3, $sec_a3, $current_user);
    
    if ($update_stmt->execute()) {
        $security_message = "Security questions updated successfully.";
    } else {
        $security_error = "Error updating security questions: " . $conn->error;
    }
}

// Handle control level update (Super Admin only)
$control_message = '';
if ($is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_control_level'])) {
    $target_username = $_POST['target_username'];
    $new_control_level = $_POST['control_level'];
    
    $update_stmt = $conn->prepare("UPDATE signinfo SET control_level = ? WHERE username = ? AND account_type = 'admin'");
    $update_stmt->bind_param("ss", $new_control_level, $target_username);
    
    if ($update_stmt->execute()) {
        $control_message = "Control level updated for " . $target_username;
    } else {
        $control_error = "Error updating control level.";
    }
}

// Handle account status update (Super Admin only)
$status_message = '';
if ($is_super_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $target_username = $_POST['target_username'];
    $new_status = $_POST['status'];
    
    $update_stmt = $conn->prepare("UPDATE signinfo SET status = ? WHERE username = ?");
    $update_stmt->bind_param("ss", $new_status, $target_username);
    
    if ($update_stmt->execute()) {
        $status_message = "Status updated for " . $target_username;
    } else {
        $status_error = "Error updating status.";
    }
}

// Get current user data
$user_stmt = $conn->prepare("SELECT * FROM signinfo WHERE username = ?");
$user_stmt->bind_param("s", $current_user);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();

// Get all admins for Super Admin view
$admins_list = null;
if ($is_super_admin) {
    $admins_stmt = $conn->prepare("SELECT id_main, fname, lname, username, email, account_type, control_level, status, created_at FROM signinfo WHERE account_type = 'admin' ORDER BY created_at DESC");
    $admins_stmt->execute();
    $admins_list = $admins_stmt->get_result();
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
    <title>Admin Settings - <?php echo $is_super_admin ? 'Super Admin' : 'Admin'; ?> | Furniplace</title>
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

        /* ─── SETTINGS CONTAINER ─── */
        .settings-container { display: flex; flex-direction: column; gap: 1.5rem; }

        /* ─── SETTINGS CARD ─── */
        .settings-card { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); overflow: hidden; }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; gap: 0.75rem; background: var(--dark-4); }
        .card-header i { font-size: 1.2rem; color: var(--gold); }
        .card-header h3 { font-family: 'Playfair Display', serif; color: var(--text-primary); font-size: 1.1rem; margin: 0; }
        .card-body { padding: 1.5rem; }

        /* ─── PROFILE HEADER ─── */
        .profile-header { display: flex; gap: 2rem; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
        .profile-picture { width: 100px; height: 100px; border-radius: 50%; background: var(--dark-5); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 3px solid var(--gold); flex-shrink: 0; }
        .profile-picture img { width: 100%; height: 100%; object-fit: cover; }
        .profile-picture .no-image { font-size: 2.5rem; color: var(--text-muted); }
        .profile-title h2 { font-family: 'Playfair Display', serif; font-size: 1.3rem; color: var(--text-primary); margin-bottom: 0.5rem; }
        .profile-title p { color: var(--text-muted); margin-bottom: 0.25rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; }
        .profile-title p i { color: var(--gold); width: 18px; }

        /* ─── FORMS ─── */
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem; }
        .form-group { margin-bottom: 0; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { display: block; margin-bottom: 0.4rem; color: var(--text-secondary); font-weight: 500; font-size: 0.8rem; }
        .form-control { width: 100%; padding: 0.6rem 0.875rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; transition: all 0.2s; }
        .form-control:focus { outline: none; border-color: var(--gold-dark); background: var(--dark-5); }
        .form-control[readonly] { background: var(--dark-5); opacity: 0.7; cursor: not-allowed; }
        textarea.form-control { resize: vertical; min-height: 80px; }

        /* File Input */
        .file-input { border: 2px dashed var(--border-subtle); padding: 1.25rem; text-align: center; border-radius: var(--radius-sm); cursor: pointer; background: var(--dark-4); transition: all 0.3s; width: 100%; }
        .file-input:hover { border-color: var(--gold); background: var(--dark-5); }

        /* Buttons */
        .btn { padding: 0.65rem 1.25rem; border: none; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; transition: all 0.3s; font-family: 'DM Sans', sans-serif; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: linear-gradient(135deg, var(--gold-dark), var(--gold)); color: var(--dark); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(201,168,76,0.3); }
        .btn-secondary { background: transparent; border: 1px solid var(--border-subtle); color: var(--text-secondary); }
        .btn-secondary:hover { background: var(--dark-5); color: var(--text-primary); }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.75rem; }

        /* Admin Table */
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th { background: var(--dark-4); padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-subtle); }
        .admin-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-subtle); color: var(--text-primary); font-size: 0.85rem; }
        .admin-table tr:hover { background: rgba(255,255,255,0.02); }

        /* Badges */
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem; }
        .status-active { background: rgba(62,207,142,0.15); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.3); }
        .status-suspended { background: rgba(255,140,66,0.15); color: var(--accent-orange); border: 1px solid rgba(255,140,66,0.3); }
        .status-banned { background: rgba(255,71,87,0.15); color: var(--accent-red); border: 1px solid rgba(255,71,87,0.3); }
        .status-pending { background: rgba(241,196,15,0.15); color: #F1C40F; border: 1px solid rgba(241,196,15,0.3); }

        .control-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .control-full { background: rgba(201,168,76,0.15); color: var(--gold-light); border: 1px solid rgba(201,168,76,0.3); }
        .control-limited { background: rgba(78,124,255,0.15); color: var(--accent-blue); border: 1px solid rgba(78,124,255,0.3); }

        /* Section Headers */
        .section-header { margin: 1.5rem 0 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-subtle); }
        .section-header h4 { font-family: 'Playfair Display', serif; color: var(--text-primary); font-size: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .section-header h4 i { color: var(--gold); }

        /* Responsive */
        @media (max-width: 992px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            .profile-header { flex-direction: column; text-align: center; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .admin-table { display: block; overflow-x: auto; }
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
        <a href="admin_activity_logs.php" class="nav-item"><i class="fas fa-history"></i> Admin Activity Logs</a>
        <?php if ($is_super_admin): ?>
        <a href="view_deletion_proofs.php" class="nav-item"><i class="fas fa-camera"></i> Deletion Proofs</a>
        <?php endif; ?>
        <div class="nav-label" style="margin-top:0.5rem;">System</div>
        <a href="home.php" class="nav-item"><i class="fas fa-store"></i> View Store</a>
        <a href="admin_settings.php" class="nav-item active"><i class="fas fa-cog"></i> Settings</a>
        <a href="logout.php" class="nav-item danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>

<!-- ═══ MAIN ═══ -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h1><i class="fas fa-cog" style="color: var(--gold);"></i> Admin Settings</h1>
            <p>Manage your account and system preferences</p>
        </div>
        <div class="topbar-right">
            <span class="badge-pill <?php echo $is_super_admin ? 'super' : 'admin'; ?>">
                <i class="fas fa-<?php echo $is_super_admin ? 'crown' : 'shield-alt'; ?>" style="font-size:0.65rem;"></i>
                <?php echo strtoupper(str_replace('_', ' ', $account_type)); ?> • 
                <?php echo strtoupper($user_data['control_level'] ?? 'LIMITED'); ?>
            </span>
        </div>
    </div>

    <div class="page-content">
        <div class="settings-container">

        <!-- ✅ PUT THE WARNING BANNER RIGHT HERE -->
        <?php if ($needs_password_change): ?>
        <div class="alert" style="background: rgba(255,140,66,0.15); border: 1px solid rgba(255,140,66,0.3); color: var(--accent-orange); margin-bottom: 1.5rem;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Security Alert:</strong> Please change your password below to secure your account.
        </div>
        <?php endif; ?>

            <!-- Profile Settings Card -->
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-user-circle"></i>
                    <h3>Profile Information</h3>
                </div>
                <div class="card-body">
                    <?php if ($profile_message): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $profile_message; ?></div>
                    <?php endif; ?>
                    <?php if ($profile_error): ?>
                        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $profile_error; ?></div>
                    <?php endif; ?>

                    <div class="profile-header">
                        <div class="profile-picture">
                            <?php if (!empty($user_data['profile_pic']) && file_exists($user_data['profile_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($user_data['profile_pic']); ?>" alt="Profile">
                            <?php else: ?>
                                <div class="no-image"><i class="fas fa-user"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="profile-title">
                            <h2><?php echo htmlspecialchars($user_data['fname'] . ' ' . ($user_data['mi'] ? $user_data['mi'] . '. ' : '') . $user_data['lname']); ?></h2>
                            <p><i class="fas fa-at"></i> <?php echo htmlspecialchars($user_data['username']); ?></p>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user_data['email']); ?></p>
                            <p><i class="fas fa-calendar-alt"></i> Member since: <?php echo date('F d, Y', strtotime($user_data['created_at'])); ?></p>
                        </div>
                    </div>

                    <!-- Profile Picture Upload -->
                    <form method="POST" enctype="multipart/form-data" style="margin-bottom: 1.5rem;">
                        <?php if ($upload_message): ?>
                            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $upload_message; ?></div>
                        <?php endif; ?>
                        <?php if ($upload_error): ?>
                            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $upload_error; ?></div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="profile_picture"><i class="fas fa-camera"></i> Update Profile Picture</label>
                            <input type="file" class="file-input" id="profile_picture" name="profile_picture" accept="image/*">
                            <small style="color: var(--text-muted); margin-top: 0.5rem; display: block;">
                                <i class="fas fa-info-circle"></i> Max file size: 5MB. Allowed: JPG, PNG, GIF
                            </small>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Picture</button>
                    </form>

                    <!-- Profile Update Form -->
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="fname">First Name <span style="color: var(--accent-red);">*</span></label>
                                <input type="text" class="form-control" id="fname" name="fname" value="<?php echo htmlspecialchars($user_data['fname']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="mi">Middle Initial</label>
                                <input type="text" class="form-control" id="mi" name="mi" value="<?php echo htmlspecialchars($user_data['mi'] ?? ''); ?>" maxlength="10">
                            </div>
                            <div class="form-group">
                                <label for="lname">Last Name <span style="color: var(--accent-red);">*</span></label>
                                <input type="text" class="form-control" id="lname" name="lname" value="<?php echo htmlspecialchars($user_data['lname']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email <span style="color: var(--accent-red);">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="section-header">
                            <h4><i class="fas fa-map-marker-alt"></i> Address Information</h4>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="address">Street Address</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="purok">Purok</label>
                                <input type="text" class="form-control" id="purok" name="purok" value="<?php echo htmlspecialchars($user_data['Purok'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="Barranggay">Barangay</label>
                                <input type="text" class="form-control" id="Barranggay" name="Barranggay" value="<?php echo htmlspecialchars($user_data['Barranggay'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="CM">City/Municipality</label>
                                <input type="text" class="form-control" id="CM" name="CM" value="<?php echo htmlspecialchars($user_data['CM'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="Province">Province</label>
                                <input type="text" class="form-control" id="Province" name="Province" value="<?php echo htmlspecialchars($user_data['Province'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="Country">Country</label>
                                <input type="text" class="form-control" id="Country" name="Country" value="<?php echo htmlspecialchars($user_data['Country'] ?? 'Philippines'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="zip">ZIP Code</label>
                                <input type="text" class="form-control" id="zip" name="zip" value="<?php echo htmlspecialchars($user_data['zip'] ?? ''); ?>">
                            </div>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Update Profile</button>
                    </form>
                </div>
            </div>

            <!-- Security Settings Card -->
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-lock"></i>
                    <h3>Security Settings</h3>
                </div>
                <div class="card-body">
                    <div class="section-header">
                        <h4><i class="fas fa-key"></i> Change Password</h4>
                    </div>
                    <?php if ($password_message): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $password_message; ?></div>
                    <?php endif; ?>
                    <?php if ($password_error): ?>
                        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $password_error; ?></div>
                    <?php endif; ?>

                    <form method="POST" style="margin-bottom: 1.5rem;">
    <div class="form-grid">
        <div class="form-group">
            <label for="new_password">New Password <span style="color: var(--accent-red);">*</span></label>
            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
            <small style="color: var(--text-muted);">Minimum 8 characters</small>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm New Password <span style="color: var(--accent-red);">*</span></label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
        </div>
    </div>
    <button type="submit" name="change_password" class="btn btn-primary"><i class="fas fa-key"></i> Update Password</button>
</form>

                    <div class="section-header">
    <h4><i class="fas fa-shield-alt"></i> Security Questions</h4>
</div>

<?php if ($security_message): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $security_message; ?></div>
<?php endif; ?>
<?php if ($security_error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $security_error; ?></div>
<?php endif; ?>

<form method="POST">
    <!-- Security Question 1 -->
    <div class="security-card" style="background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 1.25rem; margin-bottom: 1.25rem;">
        <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.85rem;">
            Question 1 <span style="color: var(--accent-red);">*</span>
        </label>
        <select name="sec_q1" class="form-control" style="margin-bottom: 1rem;" required>
            <option value="">Select a security question</option>
            <option value="What is your mother's maiden name?" <?php echo (($user_data['sec_q1'] ?? '') == "What is your mother's maiden name?") ? 'selected' : ''; ?>>
                What is your mother's maiden name?
            </option>
            <option value="What was the name of your first pet?" <?php echo (($user_data['sec_q1'] ?? '') == "What was the name of your first pet?") ? 'selected' : ''; ?>>
                What was the name of your first pet?
            </option>
            <option value="What was the make of your first car?" <?php echo (($user_data['sec_q1'] ?? '') == "What was the make of your first car?") ? 'selected' : ''; ?>>
                What was the make of your first car?
            </option>
            <option value="What city were you born in?" <?php echo (($user_data['sec_q1'] ?? '') == "What city were you born in?") ? 'selected' : ''; ?>>
                What city were you born in?
            </option>
            <option value="What was your childhood nickname?" <?php echo (($user_data['sec_q1'] ?? '') == "What was your childhood nickname?") ? 'selected' : ''; ?>>
                What was your childhood nickname?
            </option>
        </select>
        
        <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.85rem;">
            Answer 1 <span style="color: var(--accent-red);">*</span>
        </label>
        <div class="input-wrapper password-wrap" style="position: relative;">
            <i class="fas fa-key" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); z-index: 1;"></i>
            <input type="password" name="sec_a1" id="sec_a1" class="form-control" 
                   style="padding-left: 2.5rem; padding-right: 2.5rem;" 
                   placeholder="Your answer" required>
            <button type="button" class="toggle-pass" onclick="toggleSecurityAnswer('sec_a1')" 
                    style="position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-muted); cursor: pointer;">
                <i class="fas fa-eye"></i>
            </button>
        </div>
    </div>

    <!-- Security Question 2 -->
    <div class="security-card" style="background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 1.25rem; margin-bottom: 1.25rem;">
        <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.85rem;">
            Question 2 <span style="color: var(--accent-red);">*</span>
        </label>
        <select name="sec_q2" class="form-control" style="margin-bottom: 1rem;" required>
            <option value="">Select a security question</option>
            <option value="What is your favorite color?" <?php echo (($user_data['sec_q2'] ?? '') == "What is your favorite color?") ? 'selected' : ''; ?>>
                What is your favorite color?
            </option>
            <option value="What is your favorite food?" <?php echo (($user_data['sec_q2'] ?? '') == "What is your favorite food?") ? 'selected' : ''; ?>>
                What is your favorite food?
            </option>
            <option value="What is the name of your favorite teacher?" <?php echo (($user_data['sec_q2'] ?? '') == "What is the name of your favorite teacher?") ? 'selected' : ''; ?>>
                What is the name of your favorite teacher?
            </option>
            <option value="What school did you attend in grade 1?" <?php echo (($user_data['sec_q2'] ?? '') == "What school did you attend in grade 1?") ? 'selected' : ''; ?>>
                What school did you attend in grade 1?
            </option>
            <option value="What is your favorite book?" <?php echo (($user_data['sec_q2'] ?? '') == "What is your favorite book?") ? 'selected' : ''; ?>>
                What is your favorite book?
            </option>
        </select>
        
        <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.85rem;">
            Answer 2 <span style="color: var(--accent-red);">*</span>
        </label>
        <div class="input-wrapper password-wrap" style="position: relative;">
            <i class="fas fa-key" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); z-index: 1;"></i>
            <input type="password" name="sec_a2" id="sec_a2" class="form-control" 
                   style="padding-left: 2.5rem; padding-right: 2.5rem;" 
                   placeholder="Your answer" required>
            <button type="button" class="toggle-pass" onclick="toggleSecurityAnswer('sec_a2')" 
                    style="position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-muted); cursor: pointer;">
                <i class="fas fa-eye"></i>
            </button>
        </div>
    </div>

    <!-- Security Question 3 -->
    <div class="security-card" style="background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 1.25rem; margin-bottom: 1.25rem;">
        <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.85rem;">
            Question 3 <span style="color: var(--accent-red);">*</span>
        </label>
        <select name="sec_q3" class="form-control" style="margin-bottom: 1rem;" required>
            <option value="">Select a security question</option>
            <option value="What was your first job?" <?php echo (($user_data['sec_q3'] ?? '') == "What was your first job?") ? 'selected' : ''; ?>>
                What was your first job?
            </option>
            <option value="What is your favorite movie?" <?php echo (($user_data['sec_q3'] ?? '') == "What is your favorite movie?") ? 'selected' : ''; ?>>
                What is your favorite movie?
            </option>
            <option value="What is your dream vacation destination?" <?php echo (($user_data['sec_q3'] ?? '') == "What is your dream vacation destination?") ? 'selected' : ''; ?>>
                What is your dream vacation destination?
            </option>
            <option value="What is your favorite song?" <?php echo (($user_data['sec_q3'] ?? '') == "What is your favorite song?") ? 'selected' : ''; ?>>
                What is your favorite song?
            </option>
            <option value="What is your mother's middle name?" <?php echo (($user_data['sec_q3'] ?? '') == "What is your mother's middle name?") ? 'selected' : ''; ?>>
                What is your mother's middle name?
            </option>
        </select>
        
        <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-weight: 500; font-size: 0.85rem;">
            Answer 3 <span style="color: var(--accent-red);">*</span>
        </label>
        <div class="input-wrapper password-wrap" style="position: relative;">
            <i class="fas fa-key" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); z-index: 1;"></i>
            <input type="password" name="sec_a3" id="sec_a3" class="form-control" 
                   style="padding-left: 2.5rem; padding-right: 2.5rem;" 
                   placeholder="Your answer" required>
            <button type="button" class="toggle-pass" onclick="toggleSecurityAnswer('sec_a3')" 
                    style="position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-muted); cursor: pointer;">
                <i class="fas fa-eye"></i>
            </button>
        </div>
    </div>

    <button type="submit" name="update_security" class="btn btn-primary">
        <i class="fas fa-shield-alt"></i> Update Security Questions
    </button>
</form>

            <!-- SUPER ADMIN ONLY: Admin Management -->
            <?php if ($is_super_admin): ?>
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-user-shield"></i>
                    <h3>Admin Management (Super Admin)</h3>
                </div>
                <div class="card-body">
                    <?php if ($control_message): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $control_message; ?></div>
                    <?php endif; ?>
                    <?php if ($status_message): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $status_message; ?></div>
                    <?php endif; ?>

                    <div style="overflow-x: auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Admin</th>
                                    <th>Username</th>
                                    <th>Control Level</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($admins_list && $admins_list->num_rows > 0): ?>
                                    <?php while($admin = $admins_list->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($admin['fname'] . ' ' . $admin['lname']); ?></td>
                                            <td><i class="fas fa-at" style="color: var(--gold);"></i> <?php echo htmlspecialchars($admin['username']); ?></td>
                                            <td>
                                                <span class="control-badge control-<?php echo $admin['control_level']; ?>">
                                                    <?php echo strtoupper($admin['control_level']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $admin['status']; ?>">
                                                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                                    <?php echo strtoupper($admin['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline-flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                                    <input type="hidden" name="target_username" value="<?php echo $admin['username']; ?>">
                                                    <select name="control_level" class="form-control" style="width: auto; display: inline-block; padding: 0.4rem 0.6rem; font-size: 0.8rem;">
                                                        <option value="full" <?php echo $admin['control_level'] == 'full' ? 'selected' : ''; ?>>Full</option>
                                                        <option value="limited" <?php echo $admin['control_level'] == 'limited' ? 'selected' : ''; ?>>Limited</option>
                                                    </select>
                                                    <button type="submit" name="update_control_level" class="btn btn-secondary btn-sm" title="Update Control Level">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                    <select name="status" class="form-control" style="width: auto; display: inline-block; padding: 0.4rem 0.6rem; font-size: 0.8rem;">
                                                        <option value="active" <?php echo $admin['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="banned" <?php echo $admin['status'] == 'banned' ? 'selected' : ''; ?>>Banned</option>
                                                    </select>
                                                    <button type="submit" name="update_status" class="btn btn-secondary btn-sm" title="Update Status">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                            <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                            No other admins found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Account Information Card -->
            <div class="settings-card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i>
                    <h3>Account Information</h3>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Account Type</label>
                            <div class="form-control" style="background: var(--dark-5);">
                                <i class="fas fa-<?php echo $is_super_admin ? 'crown' : 'shield-alt'; ?>" style="color: var(--gold);"></i>
                                <?php echo strtoupper($user_data['account_type']); ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Control Level</label>
                            <div class="form-control" style="background: var(--dark-5);">
                                <span class="control-badge control-<?php echo $user_data['control_level'] ?? 'limited'; ?>">
                                    <?php echo strtoupper($user_data['control_level'] ?? 'LIMITED'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Account Status</label>
                            <div class="form-control" style="background: var(--dark-5);">
                                <span class="status-badge status-<?php echo $user_data['status']; ?>">
                                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                    <?php echo strtoupper($user_data['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Member Since</label>
                            <div class="form-control" style="background: var(--dark-5);">
                                <i class="fas fa-calendar-alt" style="color: var(--gold);"></i>
                                <?php echo date('F d, Y \a\t h:i A', strtotime($user_data['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Preview uploaded image
document.getElementById('profile_picture')?.addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const profilePic = document.querySelector('.profile-picture');
            if (profilePic) {
                profilePic.innerHTML = `<img src="${e.target.result}" alt="Profile Preview">`;
            }
        }
        reader.readAsDataURL(this.files[0]);
    }
});

// Ping to stay online
fetch('ping.php');
setInterval(() => fetch('ping.php'), 30000);
</script>

</body>
</html>