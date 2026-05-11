<?php
session_start();
include 'connection.php';
include 'permission_helper.php';

// Define account type at the TOP
$account_type = $_SESSION['account_type'] ?? '';
$current_user = $_SESSION['user'] ?? '';

// Function to check current Super Admin count
function getSuperAdminCount($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM signinfo WHERE account_type = 'super_admin'");
    return $result->fetch_assoc()['count'];
}

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

// ===== EDIT ADMIN MODAL - SINGLE CLEAN IMPLEMENTATION =====
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    
    // Load permissions first
    $permissions = loadPermissions($conn, $_SESSION['user'] ?? '');
    $can_edit_admin = hasPermission($permissions, 'can_edit_admin');
    $can_reset_admin_password = hasPermission($permissions, 'can_reset_admin_password');
    
    if (!$can_edit_admin) {
        header("Location: admin_management.php?error=access_denied");
        exit();
    }
    
    $stmt = $conn->prepare("SELECT * FROM signinfo WHERE id_main = ? AND (account_type = 'admin' OR account_type = 'super_admin')");
    $stmt->bind_param("s", $edit_id);
    $stmt->execute();
    $edit_admin = $stmt->get_result()->fetch_assoc();
    
    if ($edit_admin) {
        // Get current permissions
        $perm_stmt = $conn->prepare("SELECT * FROM admin_permissions WHERE username = ?");
        $perm_stmt->bind_param("s", $edit_admin['username']);
        $perm_stmt->execute();
        $edit_permissions = $perm_stmt->get_result()->fetch_assoc();
        
        // Check Super Admin count for edit form
        $super_count_check = $conn->query("SELECT COUNT(*) as count FROM signinfo WHERE account_type = 'super_admin'");
        $total_super = $super_count_check->fetch_assoc()['count'];
        $is_currently_super = ($edit_admin['account_type'] === 'super_admin');
        $is_current_user_super = ($account_type === 'super_admin');
        $super_disabled = (!$is_currently_super && $total_super >= 2);

        // Check if current user has promote privilege
$promote_perm_stmt = $conn->prepare("SELECT can_promote_to_super_admin FROM admin_permissions WHERE username = ?");
$promote_perm_stmt->bind_param("s", $current_user);
$promote_perm_stmt->execute();
$promote_perm = $promote_perm_stmt->get_result()->fetch_assoc();
$can_promote_super = ($is_current_user_super || ($promote_perm['can_promote_to_super_admin'] ?? 0));
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Edit Administrator - <?php echo htmlspecialchars($edit_admin['fname'] . ' ' . $edit_admin['lname']); ?></title>
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
                    --radius: 14px;
                    --radius-sm: 8px;
                    --shadow: 0 8px 32px rgba(0,0,0,0.4);
                    --shadow-sm: 0 4px 16px rgba(0,0,0,0.25);
                }
                
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'DM Sans', sans-serif; background: var(--dark); color: var(--text-primary); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
                ::-webkit-scrollbar { width: 5px; height: 5px; }
                ::-webkit-scrollbar-track { background: var(--dark-2); }
                ::-webkit-scrollbar-thumb { background: var(--dark-5); border-radius: 99px; }
                ::-webkit-scrollbar-thumb:hover { background: var(--gold-dark); }

                .edit-modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.85);
                    z-index: 999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    backdrop-filter: blur(8px);
                    padding: 1rem;
                    animation: fadeIn 0.2s ease;
                }

                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }

                .edit-modal {
                    background: var(--dark-3);
                    width: 1000px;
                    max-width: 95%;
                    max-height: 90vh;
                    overflow-y: auto;
                    overflow-x: hidden;
                    border-radius: 24px;
                    box-shadow: var(--shadow);
                    animation: slideUp 0.3s ease;
                    position: relative;
                    display: flex;
                    flex-direction: column;
                    border: 1px solid var(--border);
                }

                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(30px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                .modal-header {
                    background: linear-gradient(135deg, var(--dark-4), var(--dark-5));
                    padding: 1.5rem 2rem;
                    border-radius: 24px 24px 0 0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    border-bottom: 3px solid var(--gold-dark);
                    position: sticky;
                    top: 0;
                    z-index: 10;
                }

                .header-content {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                }

                .header-icon {
                    width: 50px;
                    height: 50px;
                    background: rgba(201,168,76,0.2);
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 2px solid var(--gold-dark);
                }

                .header-icon i {
                    color: var(--gold-light);
                    font-size: 1.5rem;
                }

                .header-text h2 {
                    color: var(--gold-light);
                    font-family: 'Playfair Display', serif;
                    margin: 0;
                    font-size: 1.5rem;
                }

                .header-text p {
                    color: var(--text-muted);
                    margin: 0.25rem 0 0 0;
                    font-size: 0.875rem;
                }

                .close-btn {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    background: rgba(255,255,255,0.07);
                    border: 1px solid var(--border-subtle);
                    color: var(--text-secondary);
                    font-size: 1.5rem;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    text-decoration: none;
                    transition: all 0.3s;
                }

                .close-btn:hover {
                    background: rgba(255,71,87,0.15);
                    color: var(--accent-red);
                    transform: rotate(90deg);
                }

                .modal-body {
                    padding: 2rem;
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 2rem;
                    flex: 1;
                    overflow-y: auto;
                    overflow-x: hidden;
                    width: 100%;
                    background: var(--dark-3);
                }

                .form-section {
                    background: var(--dark-4);
                    border: 1px solid var(--border-subtle);
                    border-radius: 16px;
                    padding: 1.5rem;
                    box-shadow: var(--shadow-sm);
                    width: 100%;
                }

                .section-header {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    margin-bottom: 1.25rem;
                    padding-bottom: 0.75rem;
                    border-bottom: 2px solid var(--border-subtle);
                }

                .section-icon {
                    width: 36px;
                    height: 36px;
                    background: linear-gradient(135deg, var(--gold-dark), var(--gold));
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .section-icon i {
                    color: var(--dark);
                    font-size: 1rem;
                }

                .section-title {
                    font-family: 'Playfair Display', serif;
                    color: var(--text-primary);
                    margin: 0;
                    font-size: 1.1rem;
                }

                .form-group {
                    margin-bottom: 1rem;
                }

                .form-group label {
                    display: block;
                    margin-bottom: 0.375rem;
                    color: var(--text-secondary);
                    font-weight: 500;
                    font-size: 0.8rem;
                }

                .form-group input,
                .form-group select {
                    width: 100%;
                    padding: 0.625rem 0.875rem;
                    border: 1px solid var(--border-subtle);
                    border-radius: var(--radius-sm);
                    font-family: 'DM Sans', sans-serif;
                    font-size: 0.9rem;
                    background: var(--dark-5);
                    color: var(--text-primary);
                    transition: all 0.2s;
                }

                .form-group input:focus,
                .form-group select:focus {
                    outline: none;
                    border-color: var(--gold-dark);
                    background: var(--dark-5);
                }

                .form-group input:read-only {
                    background: var(--dark-4);
                    opacity: 0.8;
                    cursor: not-allowed;
                }

                .input-wrapper {
                    position: relative;
                    width: 100%;
                }

                .input-wrapper i {
                    position: absolute;
                    left: 0.875rem;
                    top: 50%;
                    transform: translateY(-50%);
                    color: var(--text-muted);
                    font-size: 0.875rem;
                }

                .input-wrapper input,
                .input-wrapper select {
                    padding-left: 2.5rem;
                }

                .form-row {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 0.75rem;
                    margin-bottom: 1rem;
                }

                .form-row .form-group {
                    margin-bottom: 0;
                }

                .control-options {
                    display: grid;
                    grid-template-columns: 1fr 1fr 1fr;
                    gap: 0.75rem;
                    margin-bottom: 1.5rem;
                }

                .control-option {
                    cursor: pointer;
                    padding: 1rem;
                    border: 1px solid var(--border-subtle);
                    border-radius: 12px;
                    text-align: center;
                    transition: all 0.3s;
                    background: var(--dark-5);
                    position: relative;
                }

                .control-option:hover {
                    transform: translateY(-2px);
                    box-shadow: var(--shadow-sm);
                    border-color: var(--gold);
                }

                .control-option.selected {
                    border-color: var(--gold) !important;
                    background: rgba(201,168,76,0.08) !important;
                }

                .control-option.selected i {
                    color: var(--gold-light) !important;
                }

                .control-option i {
                    font-size: 1.5rem;
                    color: var(--text-secondary);
                    margin-bottom: 0.5rem;
                    display: block;
                }

                .control-option h4 {
                    margin: 0 0 0.25rem 0;
                    color: var(--text-primary);
                    font-size: 0.875rem;
                }

                .control-option p {
                    margin: 0;
                    color: var(--text-muted);
                    font-size: 0.75rem;
                }

                .control-option input[type="radio"] {
                    position: absolute;
                    opacity: 0;
                }

                .description-box {
                    background: linear-gradient(135deg, rgba(201,168,76,0.08), rgba(201,168,76,0.02));
                    border: 1px solid rgba(201,168,76,0.2);
                    border-radius: 12px;
                    padding: 1.25rem;
                    margin-bottom: 1rem;
                }

                .description-box.limited {
                    border-color: rgba(78,124,255,0.3);
                    background: linear-gradient(135deg, rgba(78,124,255,0.08), rgba(78,124,255,0.02));
                }

                .permission-group {
                    background: var(--dark-5);
                    border-radius: 8px;
                    padding: 1rem;
                    margin-bottom: 0.75rem;
                    border-left: 3px solid var(--gold);
                }

                .permission-group h4 {
                    margin: 0 0 0.5rem 0;
                    color: var(--text-primary);
                    font-size: 0.8rem;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }

                .permission-group h4 i {
                    color: var(--gold-light);
                }

                .permission-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr 1fr;
                    gap: 0.5rem;
                }

                .permission-grid.two-col {
                    grid-template-columns: 1fr 1fr;
                }

                .permission-grid label {
                    display: flex;
                    align-items: center;
                    gap: 0.375rem;
                    font-size: 0.8rem;
                    color: var(--text-secondary);
                    cursor: pointer;
                }

                .permission-grid input[type="checkbox"] {
                    accent-color: var(--gold);
                }

                .modal-footer {
                    padding: 1.5rem 2rem;
                    border-top: 1px solid var(--border-subtle);
                    display: flex;
                    justify-content: flex-end;
                    gap: 1rem;
                    background: var(--dark-4);
                    border-radius: 0 0 24px 24px;
                }

                .btn-secondary {
                    padding: 0.875rem 1.5rem;
                    background: transparent;
                    border: 1px solid var(--border-subtle);
                    color: var(--text-secondary);
                    border-radius: var(--radius-sm);
                    font-weight: 600;
                    cursor: pointer;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    transition: all 0.3s;
                    font-size: 0.95rem;
                    font-family: 'DM Sans', sans-serif;
                }

                .btn-secondary:hover {
                    background: var(--dark-5);
                    color: var(--text-primary);
                }

                .btn-primary {
                    background: linear-gradient(135deg, var(--gold-dark), var(--gold));
                    color: var(--dark);
                    border: none;
                    padding: 0.875rem 1.5rem;
                    border-radius: var(--radius-sm);
                    font-weight: 600;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    transition: all 0.3s;
                    font-size: 0.95rem;
                    font-family: 'DM Sans', sans-serif;
                }

                .btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(201,168,76,0.3);
                }

                .password-section {
                    margin-top: 1.5rem;
                    background: var(--dark-5);
                    border-radius: 12px;
                    padding: 1rem;
                    border: 1px solid var(--border-subtle);
                }

                .password-toggle {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    cursor: pointer;
                }

                .password-content {
                    display: none;
                    padding: 1rem 0 0;
                    border-top: 1px solid var(--border-subtle);
                    margin-top: 1rem;
                }

                .password-content.active {
                    display: block;
                }

                .status-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.25rem;
                    padding: 0.25rem 0.75rem;
                    border-radius: 20px;
                    font-size: 0.7rem;
                    font-weight: 600;
                }

                .status-badge.super {
                    background: rgba(201,168,76,0.15);
                    color: var(--gold-light);
                    border: 1px solid rgba(201,168,76,0.3);
                }

                .status-badge.admin {
                    background: rgba(255,140,66,0.15);
                    color: var(--accent-orange);
                    border: 1px solid rgba(255,140,66,0.3);
                }

                .lock-message {
                    margin-top: 0.5rem;
                    padding: 0.4rem 0.75rem;
                    background: rgba(255,140,66,0.1);
                    border-radius: 6px;
                    font-size: 0.7rem;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    border-left: 3px solid var(--accent-orange);
                    color: var(--text-secondary);
                }

                .limit-warning {
                    margin-top: 0.5rem;
                    padding: 0.5rem;
                    background: rgba(255,71,87,0.1);
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    border-left: 3px solid var(--accent-red);
                    color: var(--text-secondary);
                }

                .strength-indicator {
                    font-size: 0.7rem;
                    margin-top: 0.3rem;
                    min-height: 1.2rem;
                    color: var(--text-muted);
                }

                @media (max-width: 768px) {
                    .modal-body {
                        grid-template-columns: 1fr;
                    }
                    .control-options {
                        grid-template-columns: 1fr;
                    }
                    .permission-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>
        </head>
        <body>
            
            <div class="edit-modal-overlay" onclick="if(event.target === this) window.location.href='admin_management.php';">
                <div class="edit-modal">
                    <!-- Header -->
                    <div class="modal-header">
                        <div class="header-content">
                            <div class="header-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="header-text">
                                <h2>Edit Administrator</h2>
                                <p>Modify administrator account details and permissions</p>
                            </div>
                        </div>
                        <a href="admin_management.php" class="close-btn"><i class="fas fa-times"></i></a>
                    </div>

                    <form method="POST" action="admin_management.php">
                        <!-- Body -->
                        <div class="modal-body">
                            <!-- LEFT COLUMN -->
                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                
                                <!-- Personal Information -->
                                <div class="form-section">
                                    <div class="section-header">
                                        <div class="section-icon"><i class="fas fa-user"></i></div>
                                        <h3 class="section-title">Personal Information</h3>
                                    </div>

                                    <input type="hidden" name="admin_id" value="<?php echo $edit_admin['id_main']; ?>">
                                    <input type="hidden" name="status" value="<?php echo $edit_admin['status']; ?>">

                                    <!-- ID Number -->
                                    <div class="form-group">
                                        <label>ID Number <span style="color: var(--accent-red);">*</span></label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-id-card"></i>
                                            <input type="text" value="<?php echo htmlspecialchars($edit_admin['id_main']); ?>" readonly>
                                        </div>
                                    </div>

                                    <!-- First & Last Name -->
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>First Name <span style="color: var(--accent-red);">*</span></label>
                                            <input type="text" name="fname" value="<?php echo htmlspecialchars($edit_admin['fname']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Last Name <span style="color: var(--accent-red);">*</span></label>
                                            <input type="text" name="lname" value="<?php echo htmlspecialchars($edit_admin['lname']); ?>" required>
                                        </div>
                                    </div>

                                    <!-- M.I. & Extension -->
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>M.I. <span style="color: var(--text-muted); font-weight: 400;">(Optional)</span></label>
                                            <input type="text" name="mi" value="<?php echo htmlspecialchars($edit_admin['mi'] ?? ''); ?>" maxlength="3">
                                        </div>
                                        <div class="form-group">
                                            <label>Extension <span style="color: var(--text-muted); font-weight: 400;">(Optional)</span></label>
                                            <input type="text" name="Ename" value="<?php echo htmlspecialchars($edit_admin['Ename'] ?? ''); ?>" maxlength="4" placeholder="Jr., Sr., III">
                                        </div>
                                    </div>

                                    <!-- Sex & Birth Date -->
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Sex <span style="color: var(--accent-red);">*</span></label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-venus-mars"></i>
                                                <select name="sex" required>
                                                    <option value="">Select</option>
                                                    <option value="Male" <?php echo ($edit_admin['sex'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                                    <option value="Female" <?php echo ($edit_admin['sex'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Birth Date <span style="color: var(--accent-red);">*</span></label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-calendar-alt"></i>
                                                <input type="date" name="bd" value="<?php echo htmlspecialchars($edit_admin['bd']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Account Details -->
                                <div class="form-section">
                                    <div class="section-header">
                                        <div class="section-icon"><i class="fas fa-lock"></i></div>
                                        <h3 class="section-title">Account Details</h3>
                                    </div>

                                    <!-- Username & Email -->
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Username <span style="color: var(--accent-red);">*</span></label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-user-circle"></i>
                                                <input type="text" name="username" value="<?php echo htmlspecialchars($edit_admin['username']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Email <span style="color: var(--accent-red);">*</span></label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-envelope"></i>
                                                <input type="email" name="email" value="<?php echo htmlspecialchars($edit_admin['email']); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Phone -->
                                    <div class="form-group">
                                        <label>Phone</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-phone"></i>
                                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($edit_admin['phone'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <!-- Account Type -->
                                    <div class="form-group">
                                        <label>Account Type <span style="color: var(--accent-red);">*</span></label>
                                        <div class="input-wrapper">
                                            <?php if (!$can_promote_super): ?>
    <!-- No Permission - Cannot change type -->
    <select name="account_type" required disabled style="opacity: 0.8;">
        <option value="admin" <?php echo $edit_admin['account_type'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
        <option value="super_admin" <?php echo $edit_admin['account_type'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
    </select>
    <input type="hidden" name="account_type" value="<?php echo $edit_admin['account_type']; ?>">
    <div class="lock-message">
        <i class="fas fa-lock"></i>
        <span>You don't have permission to change account types.</span>
    </div>
<?php else: ?>
    <!-- Has Permission - Can change type -->
    <select name="account_type" required>
        <option value="">Select Type</option>
        <option value="admin" <?php echo $edit_admin['account_type'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
        <option value="super_admin" 
            <?php echo $edit_admin['account_type'] === 'super_admin' ? 'selected' : ''; ?>
            <?php echo $super_disabled ? 'disabled' : ''; ?>
            style="<?php echo $super_disabled ? 'opacity: 0.5;' : ''; ?>">
            Super Admin 
            <?php if ($is_currently_super): ?>
                (Current)
            <?php elseif ($total_super >= 2): ?>
                (Limit Reached - Max 2)
            <?php elseif ($total_super == 1): ?>
                (1/2 - 1 slot left)
            <?php endif; ?>
        </option>
    </select>
    
    <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <span class="status-badge super">
            <i class="fas fa-crown"></i> Super Admins: <?php echo $total_super; ?>/2
        </span>
        <?php if ($is_currently_super): ?>
            <span class="status-badge super">
                <i class="fas fa-crown"></i> Current: Super Admin
            </span>
        <?php endif; ?>
    </div>
    
    <?php if ($super_disabled): ?>
        <div class="limit-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Maximum Super Admins (2/2) reached. Cannot upgrade.</span>
        </div>
    <?php endif; ?>
<?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Password Change Section -->
                                    <?php if ($can_reset_admin_password): ?>
                                    <div class="password-section">
                                        <div class="password-toggle" onclick="togglePasswordSection()">
                                            <span style="font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                                                <i class="fas fa-key" style="color: var(--gold);"></i>
                                                Change Password
                                            </span>
                                            <i class="fas fa-chevron-down" id="passwordToggleIcon" style="color: var(--text-muted); transition: transform 0.3s;"></i>
                                        </div>
                                        
                                        <div class="password-content" id="passwordContent">
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <div class="input-wrapper" style="margin-bottom: 0.3rem;">
                                                        <i class="fas fa-lock"></i>
                                                        <input type="password" id="newPassword" placeholder="New password" minlength="8">
                                                    </div>
                                                    <div class="strength-indicator" id="newPasswordStrength"></div>
                                                </div>
                                                <div class="form-group">
                                                    <div class="input-wrapper" style="margin-bottom: 0.3rem;">
                                                        <i class="fas fa-lock"></i>
                                                        <input type="password" id="confirmPassword" placeholder="Confirm password">
                                                    </div>
                                                    <div class="strength-indicator" id="confirmPasswordMatch"></div>
                                                </div>
                                            </div>
                                            
                                            <div style="margin: 0.5rem 0;">
                                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.8rem; color: var(--text-secondary);">
                                                    <input type="checkbox" id="showPassword" onclick="togglePasswordVisibility()" style="accent-color: var(--gold);">
                                                    Show Password
                                                </label>
                                            </div>
                                            
                                            <button type="button" onclick="changeAdminPassword('<?php echo $edit_admin['id_main']; ?>')" 
                                                    class="btn-primary" style="width: 100%;">
                                                <i class="fas fa-sync-alt"></i> Update Password
                                            </button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Address Information -->
                                <div class="form-section">
                                    <div class="section-header">
                                        <div class="section-icon"><i class="fas fa-map-marker-alt"></i></div>
                                        <h3 class="section-title">Address Information</h3>
                                    </div>

                                    <!-- Purok/Street & Barangay -->
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Purok/Street <span style="color: var(--accent-red);">*</span></label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-road"></i>
                                                <input type="text" name="Purok" value="<?php echo htmlspecialchars($edit_admin['Purok'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Barangay <span style="color: var(--accent-red);">*</span></label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-map-pin"></i>
                                                <input type="text" name="Barranggay" value="<?php echo htmlspecialchars($edit_admin['Barranggay'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- City/Municipality & Province -->
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>City/Municipality <span style="color: var(--accent-red);">*</span></label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-city"></i>
                                                <input type="text" name="CM" value="<?php echo htmlspecialchars($edit_admin['CM'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Province <span style="color: var(--accent-red);">*</span></label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-map"></i>
                                                <input type="text" name="Province" value="<?php echo htmlspecialchars($edit_admin['Province'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Zip Code & Country -->
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Zip Code <span style="color: var(--accent-red);">*</span></label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-mail-bulk"></i>
                                                <input type="text" name="zip" value="<?php echo htmlspecialchars($edit_admin['zip'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Country <span style="color: var(--accent-red);">*</span></label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-globe"></i>
                                                <input type="text" name="Country" value="<?php echo htmlspecialchars($edit_admin['Country'] ?? 'Philippines'); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN - Access Control -->
                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                
                                <div class="form-section">
                                    <div class="section-header">
                                        <div class="section-icon"><i class="fas fa-cogs"></i></div>
                                        <h3 class="section-title">Access Control</h3>
                                    </div>

                                    <!-- Control Level Cards -->
                                    <div class="control-options">
                                        <div class="control-option <?php echo ($edit_admin['control_level'] ?? 'limited') === 'full' ? 'selected' : ''; ?>" 
                                             onclick="selectControl('full')">
                                            <input type="radio" name="control_level" value="full" id="control_full" 
                                                   <?php echo ($edit_admin['control_level'] ?? '') === 'full' ? 'checked' : ''; ?>>
                                            <i class="fas fa-crown"></i>
                                            <h4>Full</h4>
                                            <p>All permissions</p>
                                        </div>
                                        
                                        <div class="control-option <?php echo ($edit_admin['control_level'] ?? 'limited') === 'limited' ? 'selected' : ''; ?>" 
                                             onclick="selectControl('limited')">
                                            <input type="radio" name="control_level" value="limited" id="control_limited" 
                                                   <?php echo ($edit_admin['control_level'] ?? 'limited') === 'limited' ? 'checked' : ''; ?>>
                                            <i class="fas fa-pen"></i>
                                            <h4>Limited</h4>
                                            <p>Can create & edit (no delete)</p>
                                        </div>
                                        
                                        <div class="control-option <?php echo ($edit_admin['control_level'] ?? '') === 'manual' ? 'selected' : ''; ?>" 
                                             onclick="selectControl('manual')">
                                            <input type="radio" name="control_level" value="manual" id="control_manual" 
                                                   <?php echo ($edit_admin['control_level'] ?? '') === 'manual' ? 'checked' : ''; ?>>
                                            <i class="fas fa-sliders-h"></i>
                                            <h4>Manual</h4>
                                            <p>Custom permissions</p>
                                        </div>
                                    </div>

                                    <!-- Description Box -->
                                    <div id="controlDescription" style="display: <?php echo ($edit_admin['control_level'] ?? 'limited') !== 'manual' ? 'block' : 'none'; ?>">
                                        <?php if (($edit_admin['control_level'] ?? 'limited') === 'full'): ?>
                                        <div class="description-box">
                                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                                <i class="fas fa-crown" style="color: var(--gold-light); font-size: 1.25rem;"></i>
                                                <h4 style="margin: 0; color: var(--text-primary); font-family: 'Playfair Display', serif; font-size: 1.1rem;">Full Access Granted</h4>
                                            </div>
                                            <p style="margin: 0 0 0.75rem 0; color: var(--text-secondary); font-size: 0.875rem;">This administrator has <strong style="color: var(--gold-light);">complete unrestricted access</strong> to all system features.</p>
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem;">
                                                <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create/Edit/Delete Products</div>
                                                <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create/Edit/Delete Users</div>
                                                <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create/Edit/Delete Admins</div>
                                                <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Manage All Permissions</div>
                                            </div>
                                        </div>
                                        <?php elseif (($edit_admin['control_level'] ?? 'limited') === 'limited'): ?>
                                        <div class="description-box limited">
                                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                                <i class="fas fa-pen" style="color: var(--accent-blue); font-size: 1.25rem;"></i>
                                                <h4 style="margin: 0; color: var(--text-primary); font-family: 'Playfair Display', serif; font-size: 1.1rem;">Limited Access</h4>
                                            </div>
                                            <p style="margin: 0 0 0.75rem 0; color: var(--text-secondary); font-size: 0.875rem;">This administrator can <strong style="color: var(--accent-green);">create and edit</strong> but <strong style="color: var(--accent-red);">cannot delete</strong> anything.</p>
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem;">
                                                <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Add/Edit Products</div>
                                                <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create/Edit Users</div>
                                                <div><i class="fas fa-times-circle" style="color: var(--accent-red);"></i> Delete Products</div>
                                                <div><i class="fas fa-times-circle" style="color: var(--accent-red);"></i> Delete Users</div>
                                                <div><i class="fas fa-times-circle" style="color: var(--accent-red);"></i> Delete Admins</div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Manual Permissions -->
                                    <div id="manualPermissions" style="display: <?php echo ($edit_admin['control_level'] ?? '') === 'manual' ? 'block' : 'none'; ?>; max-height: 350px; overflow-y: auto; padding-right: 0.5rem;">
                                        <!-- Product Management -->
                                        <div class="permission-group">
                                            <h4><i class="fas fa-box"></i> Products</h4>
                                            <div class="permission-grid">
                                                <label><input type="checkbox" name="perm_add_product" value="1" <?php echo ($edit_permissions['can_add_product'] ?? 0) ? 'checked' : ''; ?>> Add</label>
                                                <label><input type="checkbox" name="perm_edit_product" value="1" <?php echo ($edit_permissions['can_edit_product'] ?? 0) ? 'checked' : ''; ?>> Edit</label>
                                                <label><input type="checkbox" name="perm_delete_product" value="1" <?php echo ($edit_permissions['can_delete_product'] ?? 0) ? 'checked' : ''; ?>> Delete</label>
                                            </div>
                                        </div>

                                        <!-- User Management -->
                                        <div class="permission-group" style="border-left-color: var(--gold);">
                                            <h4><i class="fas fa-users" style="color: var(--gold);"></i> Users</h4>
                                            <div class="permission-grid two-col">
                                                <label><input type="checkbox" name="perm_edit_user" value="1" <?php echo ($edit_permissions['can_edit_user'] ?? 0) ? 'checked' : ''; ?>> Edit</label>
                                                <label><input type="checkbox" name="perm_ban_user" value="1" <?php echo ($edit_permissions['can_ban_user'] ?? 0) ? 'checked' : ''; ?>> Ban</label>
                                                <label><input type="checkbox" name="perm_delete_user" value="1" <?php echo ($edit_permissions['can_delete_user'] ?? 0) ? 'checked' : ''; ?>> Delete</label>
                                                <label><input type="checkbox" name="perm_approve_user" value="1" <?php echo ($edit_permissions['can_approve_reject_user'] ?? 0) ? 'checked' : ''; ?>> Approve</label>
                                                <label><input type="checkbox" name="perm_reset_password" value="1" <?php echo ($edit_permissions['can_reset_user_password'] ?? 0) ? 'checked' : ''; ?>> Reset Pass</label>
                                                <label style="grid-column: span 2; background: rgba(201,168,76,0.08); padding: 0.5rem; border-radius: 4px;">
                                                    <input type="checkbox" name="perm_create_user" value="1" <?php echo ($edit_permissions['can_create_user'] ?? 0) ? 'checked' : ''; ?>> 
                                                    <strong>Create User</strong>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Admin Management -->
                                       <div class="permission-group">
    <h4><i class="fas fa-user-shield"></i> Admins</h4>
    <div class="permission-grid two-col">
        <label><input type="checkbox" name="perm_create_admin" value="1" <?php echo ($edit_permissions['can_create_admin'] ?? 0) ? 'checked' : ''; ?>> Create</label>
        <label><input type="checkbox" name="perm_edit_admin" value="1" <?php echo ($edit_permissions['can_edit_admin'] ?? 0) ? 'checked' : ''; ?>> Edit</label>
        <label><input type="checkbox" name="perm_ban_admin" value="1" <?php echo ($edit_permissions['can_ban_admin'] ?? 0) ? 'checked' : ''; ?>> Ban</label>
        <label><input type="checkbox" name="perm_delete_admin" value="1" <?php echo ($edit_permissions['can_delete_admin'] ?? 0) ? 'checked' : ''; ?>> Delete</label>
        <label style="grid-column: span 2;">
            <input type="checkbox" name="perm_reset_admin_pass" value="1" <?php echo ($edit_permissions['can_reset_admin_password'] ?? 0) ? 'checked' : ''; ?>> Reset Passwords
        </label>

        <?php if (($_SESSION['account_type'] ?? '') === 'super_admin'): ?>
        <!-- SUPER ADMIN ONLY PRIVILEGE -->
        <label style="
            grid-column: span 2;
            margin-top: 0.5rem;
            background: linear-gradient(135deg, rgba(201,168,76,0.12), rgba(201,168,76,0.04));
            border: 1px solid rgba(201,168,76,0.3);
            border-radius: 8px;
            padding: 0.6rem 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        ">
            <input type="checkbox" name="perm_promote_to_super_admin" value="1" 
                   <?php echo ($edit_permissions['can_promote_to_super_admin'] ?? 0) ? 'checked' : ''; ?>
                   style="accent-color: var(--gold);">
            <span style="display: flex; flex-direction: column; gap: 0.15rem;">
                <span style="color: var(--gold-light); font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 0.4rem;">
                    <i class="fas fa-crown" style="font-size: 0.75rem;"></i>
                    Can promote Admin to Super Admin
                </span>
                <span style="color: var(--text-muted); font-size: 0.7rem;">
                    Allows this admin to change another admin's role to Super Admin
                </span>
            </span>
        </label>
        <?php endif; ?>
    </div>
</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="modal-footer">
                            <a href="admin_management.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" name="edit_admin" class="btn-primary">
                                <i class="fas fa-user-shield"></i> Update Administrator
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
                function togglePasswordSection() {
                    const content = document.getElementById('passwordContent');
                    const icon = document.getElementById('passwordToggleIcon');
                    
                    content.classList.toggle('active');
                    icon.style.transform = content.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                
                function togglePasswordVisibility() {
                    const newPass = document.getElementById('newPassword');
                    const confirmPass = document.getElementById('confirmPassword');
                    const checkbox = document.getElementById('showPassword');
                    
                    const type = checkbox.checked ? 'text' : 'password';
                    newPass.type = type;
                    confirmPass.type = type;
                }
                
                function selectControl(level) {
                    document.querySelectorAll('.control-option').forEach(opt => opt.classList.remove('selected'));
                    document.querySelector(`[onclick="selectControl('${level}')"]`).classList.add('selected');
                    document.getElementById(`control_${level}`).checked = true;
                    
                    const manualPerms = document.getElementById('manualPermissions');
                    const description = document.getElementById('controlDescription');
                    
                    if (level === 'manual') {
                        manualPerms.style.display = 'block';
                        description.style.display = 'none';
                    } else {
                        manualPerms.style.display = 'none';
                        description.style.display = 'block';
                        
                        if (level === 'full') {
    description.innerHTML = `
        <div class="description-box" style="background: linear-gradient(135deg, rgba(212,175,55,0.08), rgba(212,175,55,0.02)); border: 1px solid rgba(201,168,76,0.2); border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <i class="fas fa-crown" style="color: var(--gold-light); font-size: 1.25rem;"></i>
                <h4 style="margin: 0; color: var(--text-primary); font-family: 'Playfair Display', serif; font-size: 1.1rem;">Full Access Granted</h4>
            </div>
            <p style="margin: 0 0 0.75rem 0; color: var(--text-secondary); font-size: 0.875rem;">This administrator has <strong style="color: var(--gold-light);">complete unrestricted access</strong> to all system features.</p>
            
            <div style="margin-bottom: 0.75rem;">
                <h5 style="margin: 0 0 0.5rem 0; color: var(--gold-light); font-size: 0.85rem;"><i class="fas fa-box"></i> Products:</h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; font-size: 0.8rem;">
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Add Products</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Edit Products</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Delete Products</div>
                </div>
            </div>
            
            <div style="margin-bottom: 0.75rem;">
                <h5 style="margin: 0 0 0.5rem 0; color: var(--gold-light); font-size: 0.85rem;"><i class="fas fa-users"></i> Users:</h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; font-size: 0.8rem;">
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create Users</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Edit Users</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Delete Users</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Ban Users</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Approve Users</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Reset User Passwords</div>
                </div>
            </div>
            
            <div style="margin-bottom: 0.75rem;">
                <h5 style="margin: 0 0 0.5rem 0; color: var(--gold-light); font-size: 0.85rem;"><i class="fas fa-user-shield"></i> Admins:</h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; font-size: 0.8rem;">
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create Admins</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Edit Admins</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Delete Admins</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Ban Admins</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Reset Admin Passwords</div>
                </div>
            </div>
            
            <div>
                <h5 style="margin: 0 0 0.5rem 0; color: var(--gold-light); font-size: 0.85rem;"><i class="fas fa-clipboard-list"></i> Logs & Activity:</h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; font-size: 0.8rem;">
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> View Online Users</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> View Login History</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> View Product Activity</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> View Product Logs</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Delete Activity Logs</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Force Logout Users</div>
                </div>
            </div>
        </div>
    `;
} else {
    description.innerHTML = `
        <div class="description-box limited" style="background: linear-gradient(135deg, rgba(78,124,255,0.08), rgba(78,124,255,0.02)); border: 1px solid rgba(78,124,255,0.3); border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <i class="fas fa-pen" style="color: var(--accent-blue); font-size: 1.25rem;"></i>
                <h4 style="margin: 0; color: var(--text-primary); font-family: 'Playfair Display', serif; font-size: 1.1rem;">Limited Access</h4>
            </div>
            <p style="margin: 0 0 0.75rem 0; color: var(--text-secondary); font-size: 0.875rem;">This administrator can <strong style="color: var(--accent-green);">create and edit</strong> but <strong style="color: var(--accent-red);">cannot delete</strong> anything.</p>
            
            <div style="margin-bottom: 0.75rem;">
                <h5 style="margin: 0 0 0.5rem 0; color: var(--accent-green); font-size: 0.85rem;"><i class="fas fa-check-circle"></i> Can Do:</h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; font-size: 0.8rem;">
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Add Products</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Edit Products</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create Users</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Edit Users</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Ban Users</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Approve Users</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Reset User Passwords</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create Admins</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Edit Admins</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Ban Admins</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> View Online Users</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> View Login History</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> View Product Activity</div>
                    <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> View Product Logs</div>
                </div>
            </div>
            
            <div>
                <h5 style="margin: 0 0 0.5rem 0; color: var(--accent-red); font-size: 0.85rem;"><i class="fas fa-times-circle"></i> Cannot Do:</h5>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; font-size: 0.8rem;">
                    <div><i class="fas fa-times-circle" style="color: var(--accent-red);"></i> Delete Products</div>
                    <div><i class="fas fa-times-circle" style="color: var(--accent-red);"></i> Delete Users</div>
                    <div><i class="fas fa-times-circle" style="color: var(--accent-red);"></i> Delete Admins</div>
                </div>
            </div>
        </div>
    `;
}
                    }
                }
                
                function checkPasswordStrength() {
                    const password = document.getElementById('newPassword').value;
                    const strengthDiv = document.getElementById('newPasswordStrength');
                    
                    let strength = 0;
                    if (password.match(/[a-z]+/)) strength++;
                    if (password.match(/[A-Z]+/)) strength++;
                    if (password.match(/[0-9]+/)) strength++;
                    if (password.match(/[$@#&!]+/)) strength++;
                    if (password.length >= 8) strength++;
                    
                    let text = '', color = '';
                    if (strength <= 2) { text = 'Weak'; color = 'var(--accent-red)'; }
                    else if (strength <= 4) { text = 'Medium'; color = 'var(--accent-orange)'; }
                    else { text = 'Strong'; color = 'var(--accent-green)'; }
                    
                    strengthDiv.innerHTML = password ? `<span style="color: ${color};">Password Strength: ${text}</span>` : '';
                }
                
                document.getElementById('newPassword')?.addEventListener('keyup', checkPasswordStrength);
                
                document.getElementById('confirmPassword')?.addEventListener('keyup', function() {
                    const password = document.getElementById('newPassword').value;
                    const confirm = this.value;
                    const matchDiv = document.getElementById('confirmPasswordMatch');
                    
                    if (confirm.length === 0) matchDiv.innerHTML = '';
                    else if (password === confirm) matchDiv.innerHTML = '<span style="color: var(--accent-green);"><i class="fas fa-check-circle"></i> Passwords match</span>';
                    else matchDiv.innerHTML = '<span style="color: var(--accent-red);"><i class="fas fa-exclamation-circle"></i> Passwords do not match</span>';
                });
                
                function changeAdminPassword(adminId) {
                    const newPass = document.getElementById('newPassword').value;
                    const confirmPass = document.getElementById('confirmPassword').value;
                    
                    if (!newPass) { alert('Please enter a new password'); return; }
                    if (newPass.length < 8) { alert('Password must be at least 8 characters'); return; }
                    if (newPass !== confirmPass) { alert('Passwords do not match'); return; }
                    
                    if (confirm('Change this administrator\'s password?')) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'admin_management.php';
                        
                        const adminIdInput = document.createElement('input');
                        adminIdInput.type = 'hidden';
                        adminIdInput.name = 'admin_id';
                        adminIdInput.value = adminId;
                        
                        const passwordInput = document.createElement('input');
                        passwordInput.type = 'hidden';
                        passwordInput.name = 'new_password';
                        passwordInput.value = newPass;
                        
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'change_admin_password';
                        actionInput.value = '1';
                        
                        form.appendChild(adminIdInput);
                        form.appendChild(passwordInput);
                        form.appendChild(actionInput);
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
                
                document.addEventListener('DOMContentLoaded', function() {
                    const currentLevel = document.querySelector('input[name="control_level"]:checked')?.value || 'limited';
                    selectControl(currentLevel);
                });
            </script>
        </body>
        </html>
        <?php
        exit();
    }
}

// CHECK FOR VIEW PARAMETER FIRST - BEFORE ANY HTML OUTPUT
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $view_id = $_GET['view'];
    $stmt = $conn->prepare("SELECT * FROM signinfo WHERE id_main = ? AND (account_type = 'admin' OR account_type = 'super_admin')");
    $stmt->bind_param("s", $view_id);
    $stmt->execute();
    $admin_data = $stmt->get_result()->fetch_assoc();
    
    if ($admin_data) {
        // Get activities
        $act_stmt = $conn->prepare("SELECT * FROM user_logs WHERE username = ? ORDER BY login_time DESC LIMIT 20");
        $act_stmt->bind_param("s", $admin_data['username']);
        $act_stmt->execute();
        $activities = $act_stmt->get_result();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Profile - <?php echo htmlspecialchars($admin_data['fname'] . ' ' . $admin_data['lname']); ?></title>
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
                    --radius: 14px;
                    --radius-sm: 8px;
                    --shadow: 0 8px 32px rgba(0,0,0,0.4);
                    --shadow-sm: 0 4px 16px rgba(0,0,0,0.25);
                }
                
                * { margin: 0; padding: 0; box-sizing: border-box; }
                
                body {
                    font-family: 'DM Sans', sans-serif;
                    background: var(--dark);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    padding: 20px;
                }

                .view-modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.85);
                    z-index: 999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    backdrop-filter: blur(8px);
                    padding: 1rem;
                    animation: fadeIn 0.2s ease;
                }

                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }

                .view-modal {
                    background: var(--dark-3);
                    width: 1000px;
                    max-width: 95%;
                    max-height: 90vh;
                    overflow-y: auto;
                    border-radius: 32px;
                    box-shadow: var(--shadow);
                    animation: slideIn 0.3s ease;
                    position: relative;
                    border: 1px solid var(--border);
                }

                @keyframes slideIn {
                    from { opacity: 0; transform: translateY(30px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                .view-modal-header {
                    background: linear-gradient(135deg, var(--dark-4), var(--dark-5));
                    padding: 1.8rem 2.5rem;
                    border-radius: 32px 32px 0 0;
                    position: relative;
                    border-bottom: 4px solid var(--gold-dark);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .view-modal-header h2 {
                    font-family: 'Playfair Display', serif;
                    color: var(--gold-light);
                    margin: 0;
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    font-size: 1.8rem;
                }

                .view-modal-header h2 i {
                    font-size: 2rem;
                    color: var(--gold-light);
                }

                .view-modal-close {
                    width: 44px;
                    height: 44px;
                    border-radius: 50%;
                    background: rgba(255,255,255,0.07);
                    border: 1px solid var(--border-subtle);
                    color: var(--text-secondary);
                    font-size: 1.3rem;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    text-decoration: none;
                    transition: all 0.3s;
                }

                .view-modal-close:hover {
                    background: rgba(255,71,87,0.15);
                    color: var(--accent-red);
                    transform: rotate(90deg);
                }

                .view-profile-section {
                    background: var(--dark-4);
                    padding: 2rem 2.5rem;
                    display: flex;
                    align-items: center;
                    gap: 2rem;
                    border-bottom: 1px solid var(--border-subtle);
                }

                .view-avatar-large {
                    width: 120px;
                    height: 120px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, var(--gold-dark), var(--gold));
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 3rem;
                    color: var(--dark);
                    border: 4px solid rgba(201,168,76,0.3);
                    overflow: hidden;
                    flex-shrink: 0;
                }

                .view-avatar-large img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }

                .view-profile-info {
                    flex: 1;
                }

                .view-profile-info h1 {
                    font-family: 'Playfair Display', serif;
                    font-size: 2.2rem;
                    color: var(--text-primary);
                    margin: 0 0 0.5rem 0;
                }

                .view-badge-container {
                    display: flex;
                    gap: 0.75rem;
                    flex-wrap: wrap;
                    margin-bottom: 0.75rem;
                }

                .view-badge {
                    padding: 0.4rem 1.2rem;
                    border-radius: 30px;
                    font-size: 0.85rem;
                    font-weight: 600;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                }

                .view-badge-role {
                    background: rgba(201,168,76,0.15);
                    color: var(--gold-light);
                    border: 1px solid rgba(201,168,76,0.3);
                }

                .view-badge-status {
                    background: rgba(62,207,142,0.15);
                    color: var(--accent-green);
                    border: 1px solid rgba(62,207,142,0.3);
                }

                .view-badge-control {
                    background: rgba(78,124,255,0.15);
                    color: var(--accent-blue);
                    border: 1px solid rgba(78,124,255,0.3);
                }

                .view-contact-info {
                    display: flex;
                    gap: 1.5rem;
                    color: var(--text-muted);
                    font-size: 0.95rem;
                    flex-wrap: wrap;
                }

                .view-contact-info span {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }

                .view-contact-info i {
                    color: var(--gold);
                    width: 16px;
                }

                .view-stats-bar {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 1px;
                    background: var(--border-subtle);
                    border-bottom: 1px solid var(--border-subtle);
                }

                .view-stat-item {
                    background: var(--dark-4);
                    padding: 1.2rem;
                    text-align: center;
                }

                .view-stat-label {
                    font-size: 0.75rem;
                    color: var(--text-muted);
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 0.25rem;
                }

                .view-stat-value {
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: var(--text-primary);
                }

                .view-content {
                    padding: 2rem 2.5rem;
                }

                .view-section {
                    background: var(--dark-4);
                    border: 1px solid var(--border-subtle);
                    border-radius: 20px;
                    padding: 1.5rem;
                    margin-bottom: 1.5rem;
                    box-shadow: var(--shadow-sm);
                }

                .view-section:last-child {
                    margin-bottom: 0;
                }

                .view-section-header {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    margin-bottom: 1.5rem;
                    padding-bottom: 0.75rem;
                    border-bottom: 1px solid var(--border-subtle);
                }

                .view-section-icon {
                    width: 40px;
                    height: 40px;
                    background: linear-gradient(135deg, var(--gold-dark), var(--gold));
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: var(--dark);
                    font-size: 1.1rem;
                }

                .view-section-header h3 {
                    font-family: 'Playfair Display', serif;
                    color: var(--text-primary);
                    margin: 0;
                    font-size: 1.2rem;
                }

                .view-info-grid {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 1.2rem;
                }

                @media (max-width: 768px) {
                    .view-info-grid {
                        grid-template-columns: repeat(2, 1fr);
                    }
                }

                @media (max-width: 480px) {
                    .view-info-grid {
                        grid-template-columns: 1fr;
                    }
                }

                .view-info-card {
                    background: var(--dark-5);
                    padding: 1.2rem;
                    border-radius: 12px;
                    border-left: 3px solid var(--gold);
                }

                .view-info-label {
                    font-size: 0.7rem;
                    color: var(--text-muted);
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                    margin-bottom: 0.3rem;
                    display: flex;
                    align-items: center;
                    gap: 0.3rem;
                }

                .view-info-label i {
                    color: var(--gold);
                    font-size: 0.8rem;
                }

                .view-info-value {
                    font-size: 1rem;
                    font-weight: 600;
                    color: var(--text-primary);
                    word-break: break-word;
                }

                .view-address-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 1rem;
                }

                @media (max-width: 480px) {
                    .view-address-grid {
                        grid-template-columns: 1fr;
                    }
                }

                .view-timeline {
                    max-height: 300px;
                    overflow-y: auto;
                    padding-right: 0.5rem;
                }

                .view-timeline-item {
                    display: flex;
                    gap: 1rem;
                    padding: 0.8rem;
                    border-bottom: 1px solid var(--border-subtle);
                }

                .view-timeline-item:last-child {
                    border-bottom: none;
                }

                .view-timeline-icon {
                    width: 36px;
                    height: 36px;
                    border-radius: 10px;
                    background: rgba(78,124,255,0.1);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: var(--accent-blue);
                    flex-shrink: 0;
                    font-size: 0.9rem;
                }

                .view-timeline-icon.logout { background: rgba(255,71,87,0.1); color: var(--accent-red); }
                .view-timeline-icon.login { background: rgba(62,207,142,0.1); color: var(--accent-green); }

                .view-timeline-content {
                    flex: 1;
                }

                .view-timeline-title {
                    font-weight: 600;
                    color: var(--text-primary);
                    font-size: 0.9rem;
                    margin-bottom: 0.2rem;
                }

                .view-timeline-details {
                    font-size: 0.75rem;
                    color: var(--text-muted);
                    display: flex;
                    gap: 1rem;
                    flex-wrap: wrap;
                }

                .view-timeline-details i {
                    margin-right: 0.2rem;
                    font-size: 0.7rem;
                }

                .view-empty-state {
                    text-align: center;
                    padding: 2rem;
                    color: var(--text-muted);
                    background: var(--dark-5);
                    border-radius: 12px;
                }

                .view-empty-state i {
                    font-size: 2.5rem;
                    margin-bottom: 0.5rem;
                    opacity: 0.3;
                }

                .view-only-notice {
                    background: rgba(78,124,255,0.1);
                    border: 1px solid rgba(78,124,255,0.3);
                    border-radius: 12px;
                    padding: 0.8rem 1.2rem;
                    margin-bottom: 1.5rem;
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    color: var(--accent-blue);
                    font-size: 0.9rem;
                }

                .view-only-notice i {
                    font-size: 1rem;
                }

                .view-close-bottom {
                    display: none;
                    padding: 1rem 2.5rem 2rem;
                }

                .view-close-bottom .view-btn-close {
                    width: 100%;
                    padding: 1rem;
                    background: var(--dark-4);
                    border: 1px solid var(--border-subtle);
                    border-radius: 12px;
                    color: var(--text-secondary);
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                    cursor: pointer;
                    text-decoration: none;
                    transition: all 0.3s;
                }

                .view-close-bottom .view-btn-close:hover {
                    background: var(--dark-5);
                    color: var(--text-primary);
                }

                @media (max-width: 768px) {
                    .view-profile-section {
                        flex-direction: column;
                        text-align: center;
                        padding: 1.5rem;
                    }
                    
                    .view-contact-info {
                        justify-content: center;
                    }
                    
                    .view-stats-bar {
                        grid-template-columns: repeat(2, 1fr);
                    }
                    
                    .view-modal-close {
                        display: none;
                    }
                    
                    .view-close-bottom {
                        display: block;
                    }
                }
            </style>
        </head>
        <body>
            <div class="view-modal-overlay" onclick="if(event.target === this) window.location.href='admin_management.php';">
                <div class="view-modal">
                    <div class="view-modal-header">
                        <h2>
                            <i class="fas fa-user-shield"></i>
                            Admin Profile
                        </h2>
                        <a href="admin_management.php" class="view-modal-close">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>

                    <div class="view-profile-section">
                        <div class="view-avatar-large">
                            <?php if (!empty($admin_data['profile_pic']) && file_exists($admin_data['profile_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($admin_data['profile_pic']); ?>" alt="Profile">
                            <?php else: ?>
                                <i class="fas fa-user-shield"></i>
                            <?php endif; ?>
                        </div>

                        <div class="view-profile-info">
                            <div class="view-badge-container">
                                <span class="view-badge view-badge-role">
                                    <i class="fas fa-<?php echo $admin_data['account_type'] === 'super_admin' ? 'crown' : 'shield-alt'; ?>"></i>
                                    <?php echo strtoupper(str_replace('_', ' ', $admin_data['account_type'])); ?>
                                </span>
                                
                                <span class="view-badge view-badge-status">
                                    <i class="fas fa-<?php 
                                        echo $admin_data['status'] === 'active' ? 'check-circle' : 
                                            ($admin_data['status'] === 'banned' ? 'ban' : 'circle'); 
                                    ?>"></i>
                                    <?php echo ucfirst($admin_data['status'] ?? 'Active'); ?>
                                </span>

                                <span class="view-badge view-badge-control">
                                    <i class="fas fa-<?php 
                                        echo ($admin_data['control_level'] ?? 'limited') === 'full' ? 'unlock' : 
                                            (($admin_data['control_level'] ?? '') === 'manual' ? 'sliders-h' : 'eye'); 
                                    ?>"></i>
                                    <?php echo ucfirst($admin_data['control_level'] ?? 'Limited'); ?> Access
                                </span>
                            </div>

                            <h1><?php echo htmlspecialchars($admin_data['fname'] . ' ' . $admin_data['lname']); ?></h1>
                            
                            <div class="view-contact-info">
                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($admin_data['email']); ?></span>
                                <?php if (!empty($admin_data['phone'])): ?>
                                <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($admin_data['phone']); ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-calendar-alt"></i> Joined <?php echo date('M Y', strtotime($admin_data['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="view-stats-bar">
                        <div class="view-stat-item">
                            <div class="view-stat-label">ID Number</div>
                            <div class="view-stat-value"><?php echo htmlspecialchars(substr($admin_data['id_main'], -8)); ?></div>
                        </div>
                        <div class="view-stat-item">
                            <div class="view-stat-label">Username</div>
                            <div class="view-stat-value">@<?php echo htmlspecialchars($admin_data['username']); ?></div>
                        </div>
                        <div class="view-stat-item">
                            <div class="view-stat-label">Age</div>
                            <div class="view-stat-value">
                                <?php 
                                if (!empty($admin_data['bd'])) {
                                    $birthdate = new DateTime($admin_data['bd']);
                                    $today = new DateTime();
                                    echo $today->diff($birthdate)->y . ' years';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="view-stat-item">
                            <div class="view-stat-label">Birthday</div>
                            <div class="view-stat-value"><?php echo !empty($admin_data['bd']) ? date('M d', strtotime($admin_data['bd'])) : 'N/A'; ?></div>
                        </div>
                    </div>

                    <div class="view-content">
                        <div class="view-only-notice">
                            <i class="fas fa-info-circle"></i>
                            <span>You are viewing administrator details in read-only mode.</span>
                        </div>

                        <div class="view-section">
                            <div class="view-section-header">
                                <div class="view-section-icon">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <h3>Personal Information</h3>
                            </div>

                            <div class="view-info-grid">
                                <div class="view-info-card">
                                    <div class="view-info-label">
                                        <i class="fas fa-id-card"></i> Full Name
                                    </div>
                                    <div class="view-info-value">
                                        <?php 
                                        $fullName = $admin_data['fname'];
                                        if (!empty($admin_data['mi'])) $fullName .= ' ' . $admin_data['mi'] . '.';
                                        $fullName .= ' ' . $admin_data['lname'];
                                        if (!empty($admin_data['Ename'])) $fullName .= ' ' . $admin_data['Ename'];
                                        echo htmlspecialchars($fullName);
                                        ?>
                                    </div>
                                </div>

                                <div class="view-info-card">
                                    <div class="view-info-label">
                                        <i class="fas fa-venus-mars"></i> Sex
                                    </div>
                                    <div class="view-info-value"><?php echo htmlspecialchars($admin_data['sex'] ?? 'Not specified'); ?></div>
                                </div>

                                <div class="view-info-card">
                                    <div class="view-info-label">
                                        <i class="fas fa-calendar"></i> Birth Date
                                    </div>
                                    <div class="view-info-value"><?php echo !empty($admin_data['bd']) ? date('F d, Y', strtotime($admin_data['bd'])) : 'Not specified'; ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="view-section">
                            <div class="view-section-header">
                                <div class="view-section-icon">
                                    <i class="fas fa-map-marked-alt"></i>
                                </div>
                                <h3>Address Information</h3>
                            </div>

                            <div class="view-address-grid">
                                <div class="view-info-card">
                                    <div class="view-info-label">
                                        <i class="fas fa-road"></i> Purok/Street
                                    </div>
                                    <div class="view-info-value"><?php echo htmlspecialchars($admin_data['Purok'] ?? 'Not specified'); ?></div>
                                </div>

                                <div class="view-info-card">
                                    <div class="view-info-label">
                                        <i class="fas fa-map-pin"></i> Barangay
                                    </div>
                                    <div class="view-info-value"><?php echo htmlspecialchars($admin_data['Barranggay'] ?? 'Not specified'); ?></div>
                                </div>

                                <div class="view-info-card">
                                    <div class="view-info-label">
                                        <i class="fas fa-mail-bulk"></i> Zip Code
                                    </div>
                                    <div class="view-info-value"><?php echo htmlspecialchars($admin_data['zip'] ?? 'Not specified'); ?></div>
                                </div>

                                <div class="view-info-card">
                                    <div class="view-info-label">
                                        <i class="fas fa-city"></i> City/Municipality
                                    </div>
                                    <div class="view-info-value"><?php echo htmlspecialchars($admin_data['CM'] ?? 'Not specified'); ?></div>
                                </div>

                                <div class="view-info-card">
                                    <div class="view-info-label">
                                        <i class="fas fa-map"></i> Province
                                    </div>
                                    <div class="view-info-value"><?php echo htmlspecialchars($admin_data['Province'] ?? 'Not specified'); ?></div>
                                </div>

                                <div class="view-info-card">
                                    <div class="view-info-label">
                                        <i class="fas fa-globe"></i> Country
                                    </div>
                                    <div class="view-info-value"><?php echo htmlspecialchars($admin_data['Country'] ?? 'Philippines'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="view-section">
                            <div class="view-section-header">
                                <div class="view-section-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <h3>Recent Activity</h3>
                            </div>

                            <div class="view-timeline">
                                <?php if ($activities && $activities->num_rows > 0): ?>
                                    <?php while($act = $activities->fetch_assoc()): ?>
                                    <div class="view-timeline-item">
                                        <div class="view-timeline-icon <?php 
                                            echo $act['action'] === 'login' ? 'login' : 
                                                ($act['action'] === 'logout' ? 'logout' : ''); 
                                        ?>">
                                            <i class="fas fa-<?php 
                                                echo $act['action'] === 'login' ? 'sign-in-alt' : 
                                                    ($act['action'] === 'logout' ? 'sign-out-alt' : 
                                                    ($act['action'] === 'created_admin' ? 'user-plus' : 
                                                    ($act['action'] === 'deleted_admin' ? 'user-minus' : 
                                                    ($act['action'] === 'banned_admin' ? 'ban' : 'desktop')))); 
                                            ?>"></i>
                                        </div>
                                        <div class="view-timeline-content">
                                            <div class="view-timeline-title">
                                                <?php echo ucfirst(str_replace('_', ' ', $act['action'])); ?>
                                            </div>
                                            <div class="view-timeline-details">
                                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($act['login_time'])); ?></span>
                                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($act['ip_address']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="view-empty-state">
                                        <i class="fas fa-history"></i>
                                        <p>No activity recorded for this administrator</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="view-close-bottom">
                            <a href="admin_management.php" class="view-btn-close">
                                <i class="fas fa-times"></i> Close Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

// If no view parameter, continue with normal page
$account_type = $_SESSION['account_type'] ?? '';
$current_user = $_SESSION['user'] ?? '';

// Load permissions
$permissions = loadPermissions($conn, $current_user);

// Allow BOTH Super Admin and Admin to access the page
if ($account_type !== 'super_admin' && $account_type !== 'admin') {
    header("Location: super_admin_dashboard.php?error=access_denied");
    exit();
}

// Load permissions for the current user
$permissions = loadPermissions($conn, $current_user);

// Define individual permissions for use in the page
$can_create_admin = hasPermission($permissions, 'can_create_admin');
$can_edit_admin = hasPermission($permissions, 'can_edit_admin');
$can_ban_admin = hasPermission($permissions, 'can_ban_admin');
$can_delete_admin = hasPermission($permissions, 'can_delete_admin');
$can_reset_admin_password = hasPermission($permissions, 'can_reset_admin_password');

if (!isset($_SESSION['control_level'])) {
    $control_stmt = $conn->prepare("SELECT control_level FROM signinfo WHERE username = ?");
    $control_stmt->bind_param("s", $current_user);
    $control_stmt->execute();
    $control_result = $control_stmt->get_result()->fetch_assoc();
    $_SESSION['control_level'] = $control_result['control_level'] ?? 'limited';
}
$control_level = $_SESSION['control_level'];

// Define permission modes
$is_super_admin = ($account_type === 'super_admin');
$is_admin = ($account_type === 'admin');
$is_god_mode = ($account_type === 'super_admin' && $control_level === 'full');
$is_manager_mode = ($account_type === 'admin' && $control_level === 'full');
$is_viewer_mode = ($account_type === 'admin' && $control_level === 'limited');
$is_super_admin_limited = ($account_type === 'super_admin' && $control_level === 'limited');

// Allow access based on permissions
if ($is_admin) {
    // If admin, they can only manage other admins (not super_admins)
} elseif (!$is_super_admin) {
    header("Location: super_admin_dashboard.php?error=access_denied");
    exit();
}

$current_user = $_SESSION['user'];
$message = '';
$error = '';

// Check for success message from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'updated') {
        $message = "Admin updated successfully!";
    } elseif ($_GET['success'] === 'created') {
        $message = "Admin created successfully!";
    } elseif ($_GET['success'] === 'deactivated') {
        $message = "✅ Admin deactivated successfully! Proof photo saved.";
    } elseif ($_GET['success'] === 'restored') {
        $message = "🔄 Admin account restored successfully!";
    } elseif ($_GET['success'] === 'deleted_permanently') {
        $message = "✅ Admin account permanently deleted! A backup is saved in archives.";
    }
}

// Handle Edit Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin'])) {
    $admin_id = $_POST['admin_id'];
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $account_type_edit = $_POST['account_type'];
    $status = $_POST['status'];
    $control_level = $_POST['control_level'] ?? 'limited';
    
    // First, get the username and current account type for this admin
    $user_stmt = $conn->prepare("SELECT username, account_type FROM signinfo WHERE id_main = ?");
    $user_stmt->bind_param("s", $admin_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result()->fetch_assoc();
    $username = $user_result['username'];
    $current_account_type = $user_result['account_type'];
    
    // ===== FETCH OLD DATA BEFORE UPDATE FOR CHANGE TRACKING =====
    $old_data_stmt = $conn->prepare("SELECT fname, lname, email, phone, account_type, control_level, 
                                             Purok, Barranggay, zip, CM, Province, Country 
                                      FROM signinfo WHERE id_main = ?");
    $old_data_stmt->bind_param("s", $admin_id);
    $old_data_stmt->execute();
    $old_data = $old_data_stmt->get_result()->fetch_assoc();
    // ===== END FETCH OLD DATA =====
    
   // ===== SECURITY CHECK: Prevent regular admins from changing account type =====
if ($account_type !== 'super_admin') {
    // Check if this admin has the promote privilege
    $promote_check = $conn->prepare("SELECT can_promote_to_super_admin FROM admin_permissions WHERE username = ?");
    $promote_check->bind_param("s", $current_user);
    $promote_check->execute();
    $promote_result = $promote_check->get_result()->fetch_assoc();
    $can_promote = $promote_result['can_promote_to_super_admin'] ?? 0;

    if (!$can_promote) {
        // No promote privilege - force account type to remain the same
        $check_stmt = $conn->prepare("SELECT account_type FROM signinfo WHERE id_main = ?");
        $check_stmt->bind_param("s", $admin_id);
        $check_stmt->execute();
        $original = $check_stmt->get_result()->fetch_assoc();
        $account_type_edit = $original['account_type'];
    } else {
        // Has promote privilege - but still check the 2 super admin limit
        if ($account_type_edit === 'super_admin') {
            $count_super = $conn->query("SELECT COUNT(*) as count FROM signinfo WHERE account_type = 'super_admin'");
            $super_count = $count_super->fetch_assoc()['count'];
            if ($super_count >= 2) {
                $error = "Cannot upgrade to Super Admin: Maximum limit of 2 Super Admins reached.";
                header("Location: admin_management.php?error=super_admin_limit");
                exit();
            }
        }
    }
}
    
    // Address fields
    $Purok = trim($_POST['Purok'] ?? '');
    $Barranggay = trim($_POST['Barranggay'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    $CM = trim($_POST['CM'] ?? '');
    $Province = trim($_POST['Province'] ?? '');
    $Country = trim($_POST['Country'] ?? 'Philippines');
    
    // ========== SUPER ADMIN UPGRADE CHECK ==========
    if ($account_type_edit === 'super_admin' && $current_account_type !== 'super_admin') {
        $count_super = $conn->query("SELECT COUNT(*) as count FROM signinfo WHERE account_type = 'super_admin'");
        $super_count = $count_super->fetch_assoc()['count'];
        
        if ($super_count >= 2) {
            $error = "Cannot upgrade to Super Admin: Maximum limit of 2 Super Admins reached.";
            ?>
            <div class="alert alert-error" style="margin: 1rem 2rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <br>
                <a href="admin_management.php" style="color: var(--accent-red); font-weight: 600; text-decoration: underline; margin-top: 0.5rem; display: inline-block;">
                    <i class="fas fa-arrow-left"></i> Go back
                </a>
            </div>
            <?php
            exit();
        }
    }
    
    if ($account_type_edit !== 'super_admin' && $current_account_type === 'super_admin') {
        $count_super = $conn->query("SELECT COUNT(*) as count FROM signinfo WHERE account_type = 'super_admin'");
        $super_count = $count_super->fetch_assoc()['count'];
        
        if ($super_count <= 1) {
            $error = "Cannot downgrade the last Super Admin. At least one Super Admin must remain in the system.";
            ?>
            <div class="alert alert-error" style="margin: 1rem 2rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <br>
                <a href="admin_management.php" style="color: var(--accent-red); font-weight: 600; text-decoration: underline; margin-top: 0.5rem; display: inline-block;">
                    <i class="fas fa-arrow-left"></i> Go back
                </a>
            </div>
            <?php
            exit();
        }
    }
    // ========== END SUPER ADMIN CHECKS ==========
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Update signinfo table
        $stmt = $conn->prepare("UPDATE signinfo SET 
            fname=?, lname=?, email=?, phone=?, account_type=?, status=?, control_level=?,
            Purok=?, Barranggay=?, zip=?, CM=?, Province=?, Country=?
            WHERE id_main=?");
        $stmt->bind_param("ssssssssssssss", 
            $fname, $lname, $email, $phone, $account_type_edit, $status, $control_level,
            $Purok, $Barranggay, $zip, $CM, $Province, $Country, $admin_id
        );
        $stmt->execute();
        
        // 2. Set permissions based on control level
        $new_permissions = [];
        
        if ($control_level === 'full') {
            // All permissions = 1
            $new_permissions = array_fill_keys([
                'can_clear_session_logs', 'can_view_online_users', 'can_view_login_history',
                'can_view_admin_activity', 'can_add_product', 'can_edit_product', 'can_delete_product',
                'can_view_product_activity', 'can_view_users', 'can_edit_user', 'can_ban_user',
                'can_delete_user', 'can_approve_reject_user', 'can_force_logout_user', 'can_reset_user_password',
                'can_create_admin', 'can_edit_admin', 'can_ban_admin', 'can_delete_admin', 'can_reset_admin_password',
                'can_view_product_logs', 'can_delete_product_logs', 'can_create_user'
            ], 1);
        } elseif ($control_level === 'limited') {
            // Limited Access - ALL admin permissions EXCEPT delete
            $new_permissions = [
                'can_clear_session_logs' => 0,
                'can_view_online_users' => 1,
                'can_view_login_history' => 1,
                'can_view_admin_activity' => 1,
                'can_add_product' => 1,
                'can_edit_product' => 1,
                'can_delete_product' => 0,
                'can_view_product_activity' => 1,
                'can_view_users' => 1,
                'can_edit_user' => 1,
                'can_ban_user' => 1,
                'can_delete_user' => 0,
                'can_approve_reject_user' => 1,
                'can_force_logout_user' => 1,
                'can_reset_user_password' => 1,
                'can_create_admin' => 1,
                'can_edit_admin' => 1,
                'can_ban_admin' => 1,
                'can_delete_admin' => 0,
                'can_reset_admin_password' => 1,
                'can_view_product_logs' => 1,
                'can_delete_product_logs' => 0,
                'can_create_user' => 1
            ];
        } else {
            // Manual permissions from checkboxes
            $new_permissions = [
                'can_clear_session_logs' => isset($_POST['perm_clear_session']) ? 1 : 0,
                'can_view_online_users' => 1,
                'can_view_login_history' => 1,
                'can_view_admin_activity' => isset($_POST['perm_view_admin_activity']) ? 1 : 0,
                'can_add_product' => isset($_POST['perm_add_product']) ? 1 : 0,
                'can_edit_product' => isset($_POST['perm_edit_product']) ? 1 : 0,
                'can_delete_product' => isset($_POST['perm_delete_product']) ? 1 : 0,
                'can_view_product_activity' => 1,
                'can_view_users' => 1,
                'can_edit_user' => isset($_POST['perm_edit_user']) ? 1 : 0,
                'can_ban_user' => isset($_POST['perm_ban_user']) ? 1 : 0,
                'can_delete_user' => isset($_POST['perm_delete_user']) ? 1 : 0,
                'can_approve_reject_user' => isset($_POST['perm_approve_user']) ? 1 : 0,
                'can_reset_user_password' => isset($_POST['perm_reset_password']) ? 1 : 0,
                'can_create_user' => isset($_POST['perm_create_user']) ? 1 : 0,
                'can_create_admin' => isset($_POST['perm_create_admin']) ? 1 : 0,
                'can_edit_admin' => isset($_POST['perm_edit_admin']) ? 1 : 0,
                'can_ban_admin' => isset($_POST['perm_ban_admin']) ? 1 : 0,
                'can_delete_admin' => isset($_POST['perm_delete_admin']) ? 1 : 0,
                'can_reset_admin_password' => isset($_POST['perm_reset_admin_pass']) ? 1 : 0,
                'can_view_product_logs' => 1,
                'can_delete_product_logs' => isset($_POST['perm_delete_logs']) ? 1 : 0,
'can_promote_to_super_admin' => isset($_POST['perm_promote_to_super_admin']) ? 1 : 0,
                
            ];
        }
        
        // 3. Update or insert permissions in admin_permissions table
        $check_perm = $conn->prepare("SELECT id FROM admin_permissions WHERE username = ?");
        $check_perm->bind_param("s", $username);
        $check_perm->execute();
        $perm_exists = $check_perm->get_result()->num_rows > 0;
        
        if ($perm_exists) {
            // Update existing permissions
            $perm_sql = "UPDATE admin_permissions SET 
                can_clear_session_logs = ?,
                can_view_online_users = ?,
                can_view_login_history = ?,
                can_view_admin_activity = ?,
                can_add_product = ?,
                can_edit_product = ?,
                can_delete_product = ?,
                can_view_product_activity = ?,
                can_view_users = ?,
                can_edit_user = ?,
                can_ban_user = ?,
                can_delete_user = ?,
                can_approve_reject_user = ?,
                can_force_logout_user = ?,
                can_reset_user_password = ?,
                can_create_user = ?,
                can_create_admin = ?,
                can_edit_admin = ?,
                can_ban_admin = ?,
                can_delete_admin = ?,
                can_reset_admin_password = ?,
                can_view_product_logs = ?,
                can_delete_product_logs = ?,
                can_promote_to_super_admin = ?,
                updated_at = NOW()
                WHERE username = ?";
                
            $perm_stmt = $conn->prepare($perm_sql);
            $perm_stmt->bind_param("iiiiiiiiiiiiiiiiiiiiiiiis",
                $new_permissions['can_clear_session_logs'],
                $new_permissions['can_view_online_users'],
                $new_permissions['can_view_login_history'],
                $new_permissions['can_view_admin_activity'],
                $new_permissions['can_add_product'],
                $new_permissions['can_edit_product'],
                $new_permissions['can_delete_product'],
                $new_permissions['can_view_product_activity'],
                $new_permissions['can_view_users'],
                $new_permissions['can_edit_user'],
                $new_permissions['can_ban_user'],
                $new_permissions['can_delete_user'],
                $new_permissions['can_approve_reject_user'],
                $new_permissions['can_force_logout_user'],
                $new_permissions['can_reset_user_password'],
                $new_permissions['can_create_user'],
                $new_permissions['can_create_admin'],
                $new_permissions['can_edit_admin'],
                $new_permissions['can_ban_admin'],
                $new_permissions['can_delete_admin'],
                $new_permissions['can_reset_admin_password'],
                $new_permissions['can_view_product_logs'],
                $new_permissions['can_delete_product_logs'],
                 $new_permissions['can_promote_to_super_admin'],
                $username
            );
        } else {
            // Insert new permissions
            $perm_sql = "INSERT INTO admin_permissions (
                username, can_clear_session_logs, can_view_online_users, can_view_login_history,
                can_view_admin_activity, can_add_product, can_edit_product, can_delete_product,
                can_view_product_activity, can_view_users, can_edit_user, can_ban_user,
                can_delete_user, can_approve_reject_user, can_force_logout_user, can_reset_user_password,
                can_create_user, can_create_admin, can_edit_admin, can_ban_admin,
                can_delete_admin, can_reset_admin_password, can_view_product_logs, can_delete_product_logs
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $perm_stmt = $conn->prepare($perm_sql);
            $perm_stmt->bind_param("siiiiiiiiiiiiiiiiiiiiiii",
                $username,
                $new_permissions['can_clear_session_logs'],
                $new_permissions['can_view_online_users'],
                $new_permissions['can_view_login_history'],
                $new_permissions['can_view_admin_activity'],
                $new_permissions['can_add_product'],
                $new_permissions['can_edit_product'],
                $new_permissions['can_delete_product'],
                $new_permissions['can_view_product_activity'],
                $new_permissions['can_view_users'],
                $new_permissions['can_edit_user'],
                $new_permissions['can_ban_user'],
                $new_permissions['can_delete_user'],
                $new_permissions['can_approve_reject_user'],
                $new_permissions['can_force_logout_user'],
                $new_permissions['can_reset_user_password'],
                $new_permissions['can_create_user'],
                $new_permissions['can_create_admin'],
                $new_permissions['can_edit_admin'],
                $new_permissions['can_ban_admin'],
                $new_permissions['can_delete_admin'],
                $new_permissions['can_reset_admin_password'],
                $new_permissions['can_view_product_logs'],
                $new_permissions['can_delete_product_logs']
            );
        }
        
        $perm_stmt->execute();
        
        // ===== DETAILED LOGGING FOR ADMIN EDIT =====
        $changes = [];
        $changed_fields = [];
        
        if ($old_data['fname'] !== $fname) {
            $changes['first_name'] = ['old' => $old_data['fname'], 'new' => $fname];
            $changed_fields[] = 'first name';
        }
        if ($old_data['lname'] !== $lname) {
            $changes['last_name'] = ['old' => $old_data['lname'], 'new' => $lname];
            $changed_fields[] = 'last name';
        }
        if ($old_data['email'] !== $email) {
            $changes['email'] = ['old' => $old_data['email'], 'new' => $email];
            $changed_fields[] = 'email';
        }
        if (($old_data['phone'] ?? '') !== ($phone ?? '')) {
            $changes['phone'] = ['old' => $old_data['phone'] ?? '', 'new' => $phone ?? ''];
            $changed_fields[] = 'phone';
        }
        if ($old_data['account_type'] !== $account_type_edit) {
            $changes['account_type'] = ['old' => $old_data['account_type'], 'new' => $account_type_edit];
            $changed_fields[] = 'account type';
        }
        if ($old_data['control_level'] !== $control_level) {
            $changes['control_level'] = ['old' => $old_data['control_level'], 'new' => $control_level];
            $changed_fields[] = 'access level';
        }
        
        if (($old_data['Purok'] ?? '') !== ($Purok ?? '')) {
            $changes['purok'] = ['old' => $old_data['Purok'] ?? '', 'new' => $Purok ?? ''];
            $changed_fields[] = 'purok/street';
        }
        if (($old_data['Barranggay'] ?? '') !== ($Barranggay ?? '')) {
            $changes['barangay'] = ['old' => $old_data['Barranggay'] ?? '', 'new' => $Barranggay ?? ''];
            $changed_fields[] = 'barangay';
        }
        if (($old_data['zip'] ?? '') !== ($zip ?? '')) {
            $changes['zip'] = ['old' => $old_data['zip'] ?? '', 'new' => $zip ?? ''];
            $changed_fields[] = 'zip code';
        }
        if (($old_data['CM'] ?? '') !== ($CM ?? '')) {
            $changes['city'] = ['old' => $old_data['CM'] ?? '', 'new' => $CM ?? ''];
            $changed_fields[] = 'city/municipality';
        }
        if (($old_data['Province'] ?? '') !== ($Province ?? '')) {
            $changes['province'] = ['old' => $old_data['Province'] ?? '', 'new' => $Province ?? ''];
            $changed_fields[] = 'province';
        }
        if (($old_data['Country'] ?? '') !== ($Country ?? '')) {
            $changes['country'] = ['old' => $old_data['Country'] ?? '', 'new' => $Country ?? ''];
            $changed_fields[] = 'country';
        }
        
        $admin_fullname = $fname . ' ' . $lname;
        
        if (!empty($changes)) {
            $changes_json = json_encode($changes);
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            
            $log_sql = "INSERT INTO user_logs (
                username, 
                action, 
                target_user, 
                target_name, 
                changes,
                ip_address, 
                device_info, 
                browser, 
                os, 
                login_time
            ) VALUES (?, 'edited_admin', ?, ?, ?, ?, 'Admin Panel', 'System', 'System', NOW())";
            
            $log_stmt = $conn->prepare($log_sql);
            if ($log_stmt) {
                $log_stmt->bind_param("sssss", 
                    $current_user, $username, $admin_fullname, $changes_json, $ip
                );
                $log_stmt->execute();
            }
        }
        // ===== END DETAILED LOGGING =====
        
        if ($username === $current_user) {
            $_SESSION['control_level'] = $control_level;
            $_SESSION['account_type'] = $account_type_edit;
            $permissions = loadPermissions($conn, $current_user);
        }
        
        $conn->commit();
        header("Location: admin_management.php?success=updated");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to update admin: " . $e->getMessage();
    }
}

// Ensure permission variable is defined
if (!isset($can_reset_admin_password)) {
    $can_reset_admin_password = isset($permissions) ? hasPermission($permissions, 'can_reset_admin_password') : false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_admin_password'])) {
    if (!$can_reset_admin_password) {
        $error = "Access Denied: You don't have permission to reset admin passwords.";
    } else {
        $admin_id = $_POST['admin_id'];
        $new_password = $_POST['new_password'];
        
        if (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE signinfo SET password=? WHERE id_main=? AND (account_type='admin' OR account_type='super_admin')");
            $stmt->bind_param("ss", $hashedPassword, $admin_id);
            
            if ($stmt->execute()) {
                $user_stmt = $conn->prepare("SELECT username FROM signinfo WHERE id_main=?");
                $user_stmt->bind_param("s", $admin_id);
                $user_stmt->execute();
                $username = $user_stmt->get_result()->fetch_assoc()['username'];
                
                $message = "✅ Password changed successfully for $username!";
                
                $log = $conn->prepare("INSERT INTO user_logs (username, action, ip_address, device_info, browser, os) VALUES (?, 'reset_admin_password', ?, 'System', 'Admin Panel', 'System')");
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $log->bind_param("ss", $current_user, $ip);
                $log->execute();
            } else {
                $error = "Failed to change password.";
            }
        }
    }
}

// Handle Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $admin_id = $_POST['admin_id'];
    $new_pass = password_hash('admin123', PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE signinfo SET password=? WHERE id_main=? AND (account_type='admin' OR account_type='super_admin')");
    $stmt->bind_param("ss", $new_pass, $admin_id);
    
    if ($stmt->execute()) {
        $message = "Password reset to 'admin123'";
    }
}

// Handle Get Actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $admin_id = $_GET['id'];
    
    // Ban Admin
    if ($_GET['action'] === 'ban') {
        $user_stmt = $conn->prepare("SELECT username FROM signinfo WHERE id_main=?");
        $user_stmt->bind_param("s", $admin_id);
        $user_stmt->execute();
        $username = $user_stmt->get_result()->fetch_assoc()['username'];
        
        $stmt = $conn->prepare("UPDATE signinfo SET status='banned' WHERE id_main=?");
        $stmt->bind_param("s", $admin_id);
        $stmt->execute();
        
        $conn->query("DELETE FROM user_sessions WHERE username='".$conn->real_escape_string($username)."'");
        $message = "Admin banned and logged out!";
    }
    
    // Unban Admin
    if ($_GET['action'] === 'unban') {
        $stmt = $conn->prepare("UPDATE signinfo SET status='active' WHERE id_main=?");
        $stmt->bind_param("s", $admin_id);
        $stmt->execute();
        $message = "Admin restored!";
    }

    // Handle Restore Admin
    if ($_GET['action'] === 'restore' && isset($_GET['id'])) {
        $archive_id = $_GET['id'];
        
        $can_restore_admin = $is_super_admin;
        
        if (!$can_restore_admin) {
            $error = "Access Denied: Only Super Admins can restore accounts.";
        } else {
            $archive_stmt = $conn->prepare("SELECT * FROM deleted_admins_archive WHERE id = ?");
            $archive_stmt->bind_param("i", $archive_id);
            $archive_stmt->execute();
            $archive_data = $archive_stmt->get_result()->fetch_assoc();
            
            if (!$archive_data) {
                $error = "Deleted admin record not found in archive.";
            } else {
                $conn->begin_transaction();
                
                try {
                    $restore_sql = "INSERT INTO signinfo (
                        id_main, fname, lname, mi, Ename, username, email, password,
                        Purok, Barranggay, CM, Province, Country, zip, sex, bd,
                        profile_pic, account_type, control_level, phone, address, created_at,
                        status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
                    
                    $restore_stmt = $conn->prepare($restore_sql);
                    $restore_stmt->bind_param(
                        "ssssssssssssssssssssss",
                        $archive_data['id_main'],
                        $archive_data['fname'],
                        $archive_data['lname'],
                        $archive_data['mi'],
                        $archive_data['Ename'],
                        $archive_data['username'],
                        $archive_data['email'],
                        $archive_data['password'],
                        $archive_data['Purok'],
                        $archive_data['Barranggay'],
                        $archive_data['CM'],
                        $archive_data['Province'],
                        $archive_data['Country'],
                        $archive_data['zip'],
                        $archive_data['sex'],
                        $archive_data['bd'],
                        $archive_data['profile_pic'],
                        $archive_data['account_type'],
                        $archive_data['control_level'],
                        $archive_data['phone'],
                        $archive_data['address'],
                        $archive_data['created_at']
                    );
                    $restore_stmt->execute();
                    
                    $update_archive = $conn->prepare("UPDATE deleted_admins_archive SET 
                        restored_at = NOW(), 
                        restored_by = ? 
                        WHERE id = ?");
                    $update_archive->bind_param("si", $current_user, $archive_id);
                    $update_archive->execute();
                    
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                    
                    $log_sql = "INSERT INTO user_logs (
                        username, action, target_user, target_name, ip_address, 
                        device_info, browser, os, login_time
                    ) VALUES (?, 'restored_admin', ?, ?, ?, 'Admin Panel', 'System', 'System', NOW())";
                    
                    $log_stmt = $conn->prepare($log_sql);
                    $log_stmt->bind_param("ssss", 
                        $current_user,
                        $archive_data['username'],
                        $archive_data['fname'] . ' ' . $archive_data['lname'],
                        $ip
                    );
                    $log_stmt->execute();
                    
                    $conn->commit();
                    
                    $message = "✅ Admin account '" . $archive_data['username'] . "' has been successfully restored!";
                    
                    header("Location: admin_management.php?success=restored");
                    exit();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Restore failed: " . $e->getMessage();
                }
            }
        }
    }
        
    // PERMANENT DELETE with backup to archive
    if ($_GET['action'] === 'delete') {
        if (!isset($_POST['submit_proof'])) {
            $admin_id = $_GET['id'];
            
            $user_stmt = $conn->prepare("SELECT username, fname, lname, account_type FROM signinfo WHERE id_main=?");
            $user_stmt->bind_param("s", $admin_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            
            if (!$user_data) {
                $error = "Admin not found.";
            } else {
                $username = $user_data['username'];
                $fullname = $user_data['fname'] . ' ' . $user_data['lname'];
                $user_type = $user_data['account_type'];
                
                if ($username === $current_user) {
                    $error = "You cannot delete your own account!";
                } else {
                    ?>
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Delete Admin - Proof Required</title>
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
                                --border: rgba(201,168,76,0.12);
                                --border-subtle: rgba(255,255,255,0.06);
                                --radius: 14px;
                                --radius-sm: 8px;
                                --shadow: 0 8px 32px rgba(0,0,0,0.4);
                                --shadow-sm: 0 4px 16px rgba(0,0,0,0.25);
                            }
                            
                            * { margin: 0; padding: 0; box-sizing: border-box; }
                            
                            body {
                                font-family: 'DM Sans', sans-serif;
                                background: var(--dark);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                min-height: 100vh;
                                padding: 20px;
                            }
                            
                            .proof-container {
                                background: var(--dark-3);
                                border-radius: 24px;
                                box-shadow: var(--shadow);
                                max-width: 600px;
                                width: 100%;
                                overflow: hidden;
                                animation: slideUp 0.3s ease;
                                border: 1px solid var(--border);
                            }
                            
                            @keyframes slideUp {
                                from { opacity: 0; transform: translateY(30px); }
                                to { opacity: 1; transform: translateY(0); }
                            }
                            
                            .proof-header {
                                background: linear-gradient(135deg, var(--dark-4), var(--dark-5));
                                color: var(--text-primary);
                                padding: 2rem;
                                text-align: center;
                                border-bottom: 3px solid var(--accent-red);
                            }
                            
                            .proof-header i {
                                font-size: 3rem;
                                margin-bottom: 1rem;
                                color: var(--accent-red);
                            }
                            
                            .proof-header h2 {
                                font-family: 'Playfair Display', serif;
                                font-size: 1.8rem;
                                margin-bottom: 0.5rem;
                                color: var(--text-primary);
                            }
                            
                            .proof-header p {
                                color: var(--text-muted);
                            }
                            
                            .proof-body {
                                padding: 2rem;
                            }
                            
                            .admin-info {
                                background: var(--dark-4);
                                padding: 1.5rem;
                                border-radius: 16px;
                                margin-bottom: 2rem;
                                border-left: 4px solid var(--accent-red);
                            }
                            
                            .admin-info-item {
                                display: flex;
                                margin-bottom: 0.75rem;
                                font-size: 1rem;
                            }
                            
                            .admin-info-label {
                                width: 120px;
                                color: var(--text-muted);
                                font-weight: 500;
                            }
                            
                            .admin-info-value {
                                color: var(--text-primary);
                                font-weight: 600;
                            }
                            
                            .warning-box {
                                background: rgba(255,71,87,0.1);
                                border: 1px solid rgba(255,71,87,0.3);
                                border-radius: 12px;
                                padding: 1.2rem;
                                margin-bottom: 2rem;
                                display: flex;
                                align-items: center;
                                gap: 1rem;
                            }
                            
                            .warning-box i {
                                font-size: 2rem;
                                color: var(--accent-red);
                            }
                            
                            .warning-box p {
                                color: var(--text-secondary);
                                font-weight: 500;
                                font-size: 0.9rem;
                            }
                            
                            .archive-note {
                                background: rgba(78,124,255,0.1);
                                border: 1px solid rgba(78,124,255,0.3);
                                border-radius: 12px;
                                padding: 1rem;
                                margin-bottom: 1.5rem;
                                display: flex;
                                align-items: center;
                                gap: 1rem;
                            }
                            
                            .archive-note i {
                                font-size: 2rem;
                                color: var(--accent-blue);
                            }
                            
                            .archive-note p {
                                color: var(--text-secondary);
                                font-weight: 500;
                                font-size: 0.9rem;
                            }
                            
                            .proof-form {
                                margin-top: 1.5rem;
                            }
                            
                            .form-group {
                                margin-bottom: 1.5rem;
                            }
                            
                            .form-group label {
                                display: block;
                                margin-bottom: 0.75rem;
                                color: var(--text-secondary);
                                font-weight: 600;
                                font-size: 0.95rem;
                            }
                            
                            .file-upload-area {
                                border: 2px dashed var(--border-subtle);
                                border-radius: 16px;
                                padding: 2rem;
                                text-align: center;
                                background: var(--dark-4);
                                cursor: pointer;
                                transition: all 0.3s;
                                position: relative;
                            }
                            
                            .file-upload-area:hover {
                                border-color: var(--gold);
                                background: var(--dark-5);
                            }
                            
                            .file-upload-area i {
                                font-size: 3rem;
                                color: var(--text-muted);
                                margin-bottom: 1rem;
                            }
                            
                            .file-upload-area p {
                                color: var(--text-secondary);
                                margin-bottom: 0.5rem;
                            }
                            
                            .file-upload-area small {
                                color: var(--text-muted);
                                font-size: 0.8rem;
                            }
                            
                            .file-upload-area input[type="file"] {
                                position: absolute;
                                top: 0;
                                left: 0;
                                width: 100%;
                                height: 100%;
                                opacity: 0;
                                cursor: pointer;
                            }
                            
                            #imagePreview {
                                max-width: 100%;
                                max-height: 200px;
                                margin-top: 1rem;
                                border-radius: 8px;
                                display: none;
                                border: 2px solid var(--gold);
                            }
                            
                            #imagePreview.active {
                                display: block;
                            }
                            
                            .form-actions {
                                display: flex;
                                gap: 1rem;
                                margin-top: 2rem;
                            }
                            
                            .btn-primary {
                                background: linear-gradient(135deg, var(--accent-red), #8B0000);
                                color: white;
                                border: none;
                                padding: 1rem 2rem;
                                border-radius: var(--radius-sm);
                                font-weight: 600;
                                cursor: pointer;
                                flex: 1;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                gap: 0.5rem;
                                transition: all 0.3s;
                                font-family: 'DM Sans', sans-serif;
                            }
                            
                            .btn-primary:hover:not(:disabled) {
                                transform: translateY(-2px);
                                box-shadow: 0 6px 20px rgba(255,71,87,0.4);
                            }
                            
                            .btn-primary:disabled {
                                opacity: 0.5;
                                cursor: not-allowed;
                            }
                            
                            .btn-secondary {
                                background: transparent;
                                border: 1px solid var(--border-subtle);
                                color: var(--text-secondary);
                                padding: 1rem 2rem;
                                border-radius: var(--radius-sm);
                                font-weight: 600;
                                cursor: pointer;
                                text-decoration: none;
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                gap: 0.5rem;
                                transition: all 0.3s;
                                font-family: 'DM Sans', sans-serif;
                            }
                            
                            .btn-secondary:hover {
                                background: var(--dark-5);
                                color: var(--text-primary);
                            }
                            
                            .error-message {
                                color: var(--accent-red);
                                font-size: 0.85rem;
                                margin-top: 0.5rem;
                                display: none;
                            }
                            
                            .error-message.show {
                                display: block;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="proof-container">
                            <div class="proof-header">
                                <i class="fas fa-shield-alt"></i>
                                <h2>Security Verification Required</h2>
                                <p>Please provide proof for admin account deletion</p>
                            </div>
                            
                            <div class="proof-body">
                                <div class="archive-note">
                                    <i class="fas fa-database"></i>
                                    <p>This account will be permanently removed but a backup will be saved in archives. It can be restored later if needed.</p>
                                </div>
                                
                                <div class="admin-info">
                                    <div class="admin-info-item">
                                        <span class="admin-info-label">Username:</span>
                                        <span class="admin-info-value"><?php echo htmlspecialchars($username); ?></span>
                                    </div>
                                    <div class="admin-info-item">
                                        <span class="admin-info-label">Full Name:</span>
                                        <span class="admin-info-value"><?php echo htmlspecialchars($fullname); ?></span>
                                    </div>
                                    <div class="admin-info-item">
                                        <span class="admin-info-label">Account Type:</span>
                                        <span class="admin-info-value"><?php echo strtoupper($user_type); ?></span>
                                    </div>
                                </div>
                                
                                <div class="warning-box">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <p>This action will permanently delete the account from the system. A backup will be kept in archives. A photo proof is required.</p>
                                </div>
                                
                                <form method="POST" enctype="multipart/form-data" class="proof-form" id="proofForm">
                                    <input type="hidden" name="admin_id" value="<?php echo $admin_id; ?>">
                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                    <input type="hidden" name="fullname" value="<?php echo htmlspecialchars($fullname); ?>">
                                    <input type="hidden" name="user_type" value="<?php echo $user_type; ?>">

                                    <!-- Reason for Deletion (Optional) -->
<div class="form-group">
    <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; color: var(--text-secondary); font-weight: 600; font-size: 0.95rem;">
        <i class="fas fa-comment-alt" style="color: var(--gold);"></i> 
        Reason for Deletion 
        <span style="font-weight: 400; color: var(--text-muted); font-size: 0.85rem;">(Optional)</span>
    </label>
    <textarea 
        name="deletion_reason" 
        placeholder="e.g. Account suspended due to policy violation, duplicate account, admin request..."
        rows="4"
        maxlength="500"
        style="
            width: 100%; 
            padding: 0.875rem 1rem; 
            background: var(--dark-4); 
            border: 1px solid var(--border-subtle); 
            border-radius: 12px; 
            color: var(--text-primary); 
            font-family: 'DM Sans', sans-serif; 
            font-size: 0.9rem; 
            resize: vertical;
            transition: border-color 0.2s;
            line-height: 1.5;
        "
        onfocus="this.style.borderColor='var(--gold-dark)'"
        onblur="this.style.borderColor='var(--border-subtle)'"
        oninput="updateCharCount(this)"
    ></textarea>
    <div style="display: flex; justify-content: space-between; margin-top: 0.4rem;">
        <span style="font-size: 0.75rem; color: var(--text-muted);">
            <i class="fas fa-info-circle"></i> This reason will be saved in the deletion log and archive.
        </span>
        <span id="charCount" style="font-size: 0.75rem; color: var(--text-muted);">0 / 500</span>
    </div>
</div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-camera" style="margin-right: 0.5rem;"></i> Upload Proof Photo *</label>
                                        <div class="file-upload-area" id="fileUploadArea">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <p>Click or drag photo here</p>
                                            <small>Supported: JPG, PNG, GIF (Max 5MB)</small>
                                            <input type="file" name="proof_photo" id="proofPhoto" accept="image/*" required onchange="previewImage(this)">
                                        </div>
                                        <img id="imagePreview" src="#" alt="Preview">
                                        <div class="error-message" id="fileError">Please select a valid image file (max 5MB)</div>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <a href="admin_management.php" class="btn-secondary">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                        <button type="submit" name="submit_proof" class="btn-primary" id="submitBtn" disabled>
                                            <i class="fas fa-trash-alt"></i> Permanently Delete
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <script>
                            function previewImage(input) {
                                const preview = document.getElementById('imagePreview');
                                const submitBtn = document.getElementById('submitBtn');
                                const fileError = document.getElementById('fileError');
                                
                                if (input.files && input.files[0]) {
                                    const file = input.files[0];
                                    
                                    if (file.size > 5 * 1024 * 1024) {
                                        fileError.classList.add('show');
                                        submitBtn.disabled = true;
                                        preview.classList.remove('active');
                                        return;
                                    }
                                    
                                    if (!file.type.match('image.*')) {
                                        fileError.classList.add('show');
                                        submitBtn.disabled = true;
                                        preview.classList.remove('active');
                                        return;
                                    }
                                    
                                    fileError.classList.remove('show');
                                    
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        preview.src = e.target.result;
                                        preview.classList.add('active');
                                        submitBtn.disabled = false;
                                    }
                                    reader.readAsDataURL(file);
                                }
                            }
                            
                            const dropArea = document.getElementById('fileUploadArea');
                            
                            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                                dropArea.addEventListener(eventName, preventDefaults, false);
                            });
                            
                            function preventDefaults(e) {
                                e.preventDefault();
                                e.stopPropagation();
                            }
                            
                            ['dragenter', 'dragover'].forEach(eventName => {
                                dropArea.addEventListener(eventName, highlight, false);
                            });
                            
                            ['dragleave', 'drop'].forEach(eventName => {
                                dropArea.addEventListener(eventName, unhighlight, false);
                            });
                            
                            function highlight() {
                                dropArea.style.borderColor = 'var(--gold)';
                                dropArea.style.background = 'var(--dark-5)';
                            }
                            
                            function unhighlight() {
                                dropArea.style.borderColor = 'var(--border-subtle)';
                                dropArea.style.background = 'var(--dark-4)';
                            }
                            
                            dropArea.addEventListener('drop', handleDrop, false);
                            
                            function handleDrop(e) {
                                const dt = e.dataTransfer;
                                const files = dt.files;
                                document.getElementById('proofPhoto').files = files;
                                previewImage(document.getElementById('proofPhoto'));
                            }
                        </script>
                    </body>
                    </html>
                    <?php
                    exit();
                }
            }
        }
        
        if (isset($_POST['submit_proof'])) {
            $admin_id = $_POST['admin_id'];
            $username = $_POST['username'];
            $fullname = $_POST['fullname'];
            $user_type = $_POST['user_type'];
            
            $proof_photo_path = '';
            $upload_error = '';
            
            if (isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['proof_photo']['tmp_name'];
                $file_name = $_FILES['proof_photo']['name'];
                $file_size = $_FILES['proof_photo']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
                $max_size = 5 * 1024 * 1024;
                
                if (!in_array($file_ext, $allowed_exts)) {
                    $error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                } elseif ($file_size > $max_size) {
                    $error = "File too large. Maximum size is 5MB.";
                } else {
                    $new_filename = 'deletion_proof_' . time() . '_' . uniqid() . '.' . $file_ext;
                    $upload_dir = 'uploads/deletion_proofs/';
                    
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $proof_photo_path = $upload_dir . $new_filename;
                    
                    if (!move_uploaded_file($file_tmp, $proof_photo_path)) {
                        $error = "Failed to upload proof photo.";
                    }
                }
            } else {
                $error = "Proof photo is required.";
            }
            
            if (empty($error)) {
                if ($username === $current_user) {
                    $error = "You cannot delete your own account!";
                } else {
                    $conn->begin_transaction();
                    
                    try {
                        $get_data = $conn->prepare("SELECT * FROM signinfo WHERE id_main = ?");
                        $get_data->bind_param("s", $admin_id);
                        $get_data->execute();
                        $admin_data = $get_data->get_result()->fetch_assoc();
                        
                        if (!$admin_data) {
                            throw new Exception("Admin data not found.");
                        }
                        
                        $archive_sql = "INSERT INTO deleted_admins_archive (
                            id_main, fname, lname, mi, Ename, username, email, password,
                            Purok, Barranggay, CM, Province, Country, zip, sex, bd,
                            profile_pic, account_type, control_level, phone, address, created_at,
                            deleted_by, deletion_proof
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $archive_stmt = $conn->prepare($archive_sql);
                        $archive_stmt->bind_param(
                            "ssssssssssssssssssssssss",
                            $admin_data['id_main'],
                            $admin_data['fname'],
                            $admin_data['lname'],
                            $admin_data['mi'],
                            $admin_data['Ename'],
                            $admin_data['username'],
                            $admin_data['email'],
                            $admin_data['password'],
                            $admin_data['Purok'],
                            $admin_data['Barranggay'],
                            $admin_data['CM'],
                            $admin_data['Province'],
                            $admin_data['Country'],
                            $admin_data['zip'],
                            $admin_data['sex'],
                            $admin_data['bd'],
                            $admin_data['profile_pic'],
                            $admin_data['account_type'],
                            $admin_data['control_level'],
                            $admin_data['phone'],
                            $admin_data['address'],
                            $admin_data['created_at'],
                            $current_user,
                            $proof_photo_path
                        );
                        
                        if (!$archive_stmt->execute()) {
                            throw new Exception("Failed to archive admin: " . $archive_stmt->error);
                        }
                        
                        $conn->query("DELETE FROM user_sessions WHERE username='".$conn->real_escape_string($username)."'");
                        
                        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                        
                        $log_sql = "INSERT INTO user_logs (
                            username, action, target_user, target_name, proof_photo, ip_address, 
                            device_info, browser, os, login_time
                        ) VALUES (?, 'deleted_admin', ?, ?, ?, ?, 'Admin Panel', 'System', 'System', NOW())";
                        
                        $log_stmt = $conn->prepare($log_sql);
                        $log_stmt->bind_param("sssss", 
                            $current_user, $username, $fullname, $proof_photo_path, $ip
                        );
                        
                        if (!$log_stmt->execute()) {
                            throw new Exception("Failed to log deletion: " . $log_stmt->error);
                        }
                        
                        $delete_stmt = $conn->prepare("DELETE FROM signinfo WHERE id_main = ?");
                        $delete_stmt->bind_param("s", $admin_id);
                        
                        if (!$delete_stmt->execute()) {
                            throw new Exception("Failed to delete admin: " . $delete_stmt->error);
                        }
                        
                        $conn->commit();
                        
                        $message = "✅ Admin '$username' has been permanently deleted. A backup is saved in archives.";
                        
                        $_SESSION['success_message'] = $message;
                        
                        header("Location: admin_management.php?success=deleted_permanently");
                        exit();
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Deletion failed: " . $e->getMessage();
                        
                        if (file_exists($proof_photo_path)) {
                            unlink($proof_photo_path);
                        }
                    }
                }
            }
            
            if (!empty($error)) {
                ?>
                <div style="background: rgba(255,71,87,0.1); border: 1px solid rgba(255,71,87,0.3); color: var(--accent-red); padding: 2rem; margin: 2rem auto; max-width: 600px; border-radius: 12px; text-align: center; box-shadow: var(--shadow-sm);">
                    <i class="fas fa-exclamation-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h3 style="margin-bottom: 1rem; color: var(--text-primary);">Deletion Failed</h3>
                    <p style="margin-bottom: 1.5rem; color: var(--text-secondary);"><?php echo htmlspecialchars($error); ?></p>
                    <a href="admin_management.php" class="btn-primary" style="background: var(--accent-red); color: white; padding: 0.75rem 2rem; border-radius: var(--radius-sm); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-arrow-left"></i> Go back to Admin Management
                    </a>
                </div>
                <?php
            }
        }
    }
}

// Handle Create Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    if (!$can_create_admin) {
        $error = "Access Denied: You don't have permission to create admins.";
    } else {
        // Get form data
        $id_main = trim($_POST['id_main']);
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $mi = trim($_POST['mi'] ?? '');
        $Ename = trim($_POST['Ename'] ?? '');
        $sex = $_POST['sex'];
        $bd = $_POST['bd'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        // ✅ USE DEFAULT PASSWORD - REMOVE the password field from form
        $default_password = 'admin123';
        $password = $default_password;  // ← This is the change
        
        $Purok = trim($_POST['Purok'] ?? '');
        $Barranggay = trim($_POST['Barranggay'] ?? '');
        $zip = trim($_POST['zip'] ?? '');
        $CM = trim($_POST['CM'] ?? '');
        $Province = trim($_POST['Province'] ?? '');
        $Country = trim($_POST['Country'] ?? 'Philippines');
        $account_type = $_POST['account_type'];
        $control_level = $_POST['control_level'] ?? 'limited';
        
        // Security questions
        $sec_q1 = $_POST['sec_q1'] ?? '';
        $sec_a1 = $_POST['sec_a1'] ?? '';
        $sec_q2 = $_POST['sec_q2'] ?? '';
        $sec_a2 = $_POST['sec_a2'] ?? '';
        $sec_q3 = $_POST['sec_q3'] ?? '';
        $sec_a3 = $_POST['sec_a3'] ?? '';
        
        // Check if username or email already exists
        $check = $conn->prepare("SELECT id_main FROM signinfo WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error = "Username or email already exists!";
        } else {
            // Check Super Admin limit
            if ($account_type === 'super_admin') {
                $count_super = $conn->query("SELECT COUNT(*) as count FROM signinfo WHERE account_type = 'super_admin'");
                $super_count = $count_super->fetch_assoc()['count'];
                if ($super_count >= 2) {
                    $error = "Cannot create Super Admin: Maximum limit of 2 Super Admins reached.";
                }
            }
            
            if (empty($error)) {
                // ✅ REMOVED password validation - no need since using default
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Hash security answers
                $hashed_sec_a1 = !empty($sec_a1) ? password_hash($sec_a1, PASSWORD_DEFAULT) : '';
                $hashed_sec_a2 = !empty($sec_a2) ? password_hash($sec_a2, PASSWORD_DEFAULT) : '';
                $hashed_sec_a3 = !empty($sec_a3) ? password_hash($sec_a3, PASSWORD_DEFAULT) : '';
                
                // INSERT query
                $stmt = $conn->prepare("INSERT INTO signinfo (
                    id_main, fname, lname, mi, Ename, username, email, password,
                    Purok, Barranggay, CM, Province, Country, zip, sex, bd,
                    sec_q1, sec_a1, sec_q2, sec_a2, sec_q3, sec_a3,
                    account_type, control_level, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
                
                $stmt->bind_param("ssssssssssssssssssssssss", 
                    $id_main, $fname, $lname, $mi, $Ename, $username, $email, $hashedPassword,
                    $Purok, $Barranggay, $CM, $Province, $Country, $zip, $sex, $bd,
                    $sec_q1, $hashed_sec_a1, $sec_q2, $hashed_sec_a2, $sec_q3, $hashed_sec_a3,
                    $account_type, $control_level
                );
                    
                    if ($stmt->execute()) {
                        // Set default permissions based on control level
                        $new_permissions = [];
                        if ($control_level === 'full') {
                            $new_permissions = array_fill_keys([
                                'can_clear_session_logs', 'can_view_online_users', 'can_view_login_history',
                                'can_view_admin_activity', 'can_add_product', 'can_edit_product', 'can_delete_product',
                                'can_view_product_activity', 'can_view_users', 'can_edit_user', 'can_ban_user',
                                'can_delete_user', 'can_approve_reject_user', 'can_force_logout_user', 'can_reset_user_password',
                                'can_create_admin', 'can_edit_admin', 'can_ban_admin', 'can_delete_admin', 'can_reset_admin_password',
                                'can_view_product_logs', 'can_delete_product_logs', 'can_create_user'
                            ], 1);
                        } elseif ($control_level === 'limited') {
                            $new_permissions = [
                                'can_clear_session_logs' => 0,
                                'can_view_online_users' => 1,
                                'can_view_login_history' => 1,
                                'can_view_admin_activity' => 1,
                                'can_add_product' => 1,
                                'can_edit_product' => 1,
                                'can_delete_product' => 0,
                                'can_view_product_activity' => 1,
                                'can_view_users' => 1,
                                'can_edit_user' => 1,
                                'can_ban_user' => 1,
                                'can_delete_user' => 0,
                                'can_approve_reject_user' => 1,
                                'can_force_logout_user' => 1,
                                'can_reset_user_password' => 1,
                                'can_create_admin' => 1,
                                'can_edit_admin' => 1,
                                'can_ban_admin' => 1,
                                'can_delete_admin' => 0,
                                'can_reset_admin_password' => 1,
                                'can_view_product_logs' => 1,
                                'can_delete_product_logs' => 0,
                                'can_create_user' => 1
                            ];
                        } else {
                            // Manual permissions from checkboxes
                            $new_permissions = [
                                'can_clear_session_logs' => isset($_POST['perm_clear_session']) ? 1 : 0,
                                'can_view_online_users' => 1,
                                'can_view_login_history' => 1,
                                'can_view_admin_activity' => isset($_POST['perm_view_admin_activity']) ? 1 : 0,
                                'can_add_product' => isset($_POST['perm_add_product']) ? 1 : 0,
                                'can_edit_product' => isset($_POST['perm_edit_product']) ? 1 : 0,
                                'can_delete_product' => isset($_POST['perm_delete_product']) ? 1 : 0,
                                'can_view_product_activity' => 1,
                                'can_view_users' => 1,
                                'can_edit_user' => isset($_POST['perm_edit_user']) ? 1 : 0,
                                'can_ban_user' => isset($_POST['perm_ban_user']) ? 1 : 0,
                                'can_delete_user' => isset($_POST['perm_delete_user']) ? 1 : 0,
                                'can_approve_reject_user' => isset($_POST['perm_approve_user']) ? 1 : 0,
                                'can_reset_user_password' => isset($_POST['perm_reset_password']) ? 1 : 0,
                                'can_create_user' => isset($_POST['perm_create_user']) ? 1 : 0,
                                'can_create_admin' => isset($_POST['perm_create_admin']) ? 1 : 0,
                                'can_edit_admin' => isset($_POST['perm_edit_admin']) ? 1 : 0,
                                'can_ban_admin' => isset($_POST['perm_ban_admin']) ? 1 : 0,
                                'can_delete_admin' => isset($_POST['perm_delete_admin']) ? 1 : 0,
                                'can_reset_admin_password' => isset($_POST['perm_reset_admin_pass']) ? 1 : 0,
                                'can_view_product_logs' => 1,
                                'can_delete_product_logs' => isset($_POST['perm_delete_logs']) ? 1 : 0,
'can_promote_to_super_admin' => isset($_POST['perm_promote_to_super_admin']) ? 1 : 0,
                            ];
                        }
                        
                        // Insert permissions
                       $perm_sql = "INSERT INTO admin_permissions (
    username, can_clear_session_logs, can_view_online_users, can_view_login_history,
    can_view_admin_activity, can_add_product, can_edit_product, can_delete_product,
    can_view_product_activity, can_view_users, can_edit_user, can_ban_user,
    can_delete_user, can_approve_reject_user, can_force_logout_user, can_reset_user_password,
    can_create_user, can_create_admin, can_edit_admin, can_ban_admin,
    can_delete_admin, can_reset_admin_password, can_view_product_logs, can_delete_product_logs,
    can_promote_to_super_admin
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $perm_stmt = $conn->prepare($perm_sql);
                       $perm_stmt->bind_param("siiiiiiiiiiiiiiiiiiiiiiii",
    $username,
    $new_permissions['can_clear_session_logs'],
    $new_permissions['can_view_online_users'],
    $new_permissions['can_view_login_history'],
    $new_permissions['can_view_admin_activity'],
    $new_permissions['can_add_product'],
    $new_permissions['can_edit_product'],
    $new_permissions['can_delete_product'],
    $new_permissions['can_view_product_activity'],
    $new_permissions['can_view_users'],
    $new_permissions['can_edit_user'],
    $new_permissions['can_ban_user'],
    $new_permissions['can_delete_user'],
    $new_permissions['can_approve_reject_user'],
    $new_permissions['can_force_logout_user'],
    $new_permissions['can_reset_user_password'],
    $new_permissions['can_create_user'],
    $new_permissions['can_create_admin'],
    $new_permissions['can_edit_admin'],
    $new_permissions['can_ban_admin'],
    $new_permissions['can_delete_admin'],
    $new_permissions['can_reset_admin_password'],
    $new_permissions['can_view_product_logs'],
    $new_permissions['can_delete_product_logs'],
    $new_permissions['can_promote_to_super_admin']
);
                        $perm_stmt->execute();
                        
                       // Log the creation
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                    $log_stmt = $conn->prepare("INSERT INTO user_logs (username, action, target_user, target_name, ip_address, login_time) VALUES (?, 'created_admin', ?, ?, ?, NOW())");
                    $admin_fullname = $fname . ' ' . $lname;
                    $log_stmt->bind_param("ssss", $current_user, $username, $admin_fullname, $ip);
                    $log_stmt->execute();
                    
                    $message = "Admin created successfully! Default password: admin123";
                    header("Location: admin_management.php?success=created");
                    exit();
                } else {
                    $error = "Failed to create admin: " . $conn->error;
                }
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
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$role_filter = isset($_GET['view_role']) ? $_GET['view_role'] : 'admin';

$sql = "SELECT s.*, us.last_activity, us.is_online 
        FROM signinfo s 
        LEFT JOIN user_sessions us ON s.username = us.username 
        WHERE 1=1";

// Role filter logic
if ($role_filter === 'super_admin') {
    $sql .= " AND s.account_type = 'super_admin' AND s.status != 'deleted'";
} elseif ($role_filter === 'admin') {
    $sql .= " AND s.account_type = 'admin' AND s.status != 'deleted'";
} elseif ($role_filter === 'all') {
    $sql .= " AND (s.account_type = 'admin' OR s.account_type = 'super_admin') AND s.status != 'deleted'";
} elseif ($role_filter === 'deleted') {
    $sql .= " AND s.status = 'deleted' AND (s.account_type = 'admin' OR s.account_type = 'super_admin')";
} else {
    $sql .= " AND s.account_type = 'admin' AND s.status != 'deleted'";
}

$params = array();
$types = "";

if (!empty($search)) {
    $sql .= " AND (s.fname LIKE ? OR s.lname LIKE ? OR s.email LIKE ? OR s.username LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, array($search_term, $search_term, $search_term, $search_term));
    $types .= "ssss";
}

if (!empty($status_filter)) {
    $sql .= " AND s.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM signinfo s WHERE 1=1";

$count_params = array();
$count_types = "";


if ($role_filter === 'super_admin') {
    $count_sql .= " AND s.account_type = 'super_admin'";
} elseif ($role_filter === 'admin') {
    $count_sql .= " AND s.account_type = 'admin'";
} elseif ($role_filter === 'all') {
    $count_sql .= " AND (s.account_type = 'admin' OR s.account_type = 'super_admin')";
} elseif ($role_filter === 'deleted') {
    $count_sql .= " AND s.status = 'deleted' AND (s.account_type = 'admin' OR s.account_type = 'super_admin')";
} else {
    $count_sql .= " AND s.account_type = 'admin'";
}

if (!empty($search)) {
    $count_sql .= " AND (s.fname LIKE ? OR s.lname LIKE ? OR s.email LIKE ? OR s.username LIKE ?)";
    $count_params = array_merge($count_params, array($search_term, $search_term, $search_term, $search_term));
    $count_types .= "ssss";
}
if (!empty($status_filter)) {
    $count_sql .= " AND s.status = ?";
    $count_params[] = $status_filter;
    $count_types .= "s";
}

$count_stmt = $conn->prepare($count_sql);
if (!empty($count_params) && !empty($count_types)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();

$count_result = $count_stmt->get_result();
$total_admins = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_admins / $limit);

// Add pagination
$sql .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params) && !empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$admins = $stmt->get_result();

// Stats
$stats_condition = "";
if ($role_filter === 'super_admin') {
    $stats_condition = "AND account_type = 'super_admin'";
} elseif ($role_filter === 'admin') {
    $stats_condition = "AND account_type = 'admin'";
} elseif ($role_filter === 'all') {
    $stats_condition = "AND (account_type = 'admin' OR account_type = 'super_admin')";
} elseif ($role_filter === 'deleted') {
    $stats_condition = "AND status = 'deleted' AND (account_type = 'admin' OR account_type = 'super_admin')";
} else {
    $stats_condition = "AND account_type = 'admin'";
}

$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned,
        (SELECT COUNT(*) FROM signinfo WHERE DATE(created_at) = CURDATE() $stats_condition) as new_today
    FROM signinfo 
    WHERE 1=1 $stats_condition
")->fetch_assoc();

// Get admin for editing
$edit_admin = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_stmt = $conn->prepare("SELECT * FROM signinfo WHERE id_main = ? AND (account_type = 'admin' OR account_type = 'super_admin')");
    $edit_stmt->bind_param("s", $edit_id);
    $edit_stmt->execute();
    $result = $edit_stmt->get_result();
    $edit_admin = $result->fetch_assoc();
    
    if ($edit_admin) {
        $perm_stmt = $conn->prepare("SELECT * FROM admin_permissions WHERE username = ?");
        $perm_stmt->bind_param("s", $edit_admin['username']);
        $perm_stmt->execute();
        $edit_permissions = $perm_stmt->get_result()->fetch_assoc();
    }
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
    <title>Furniplace — Admin Management</title>
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
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.75rem; }
        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } }
        .stat-card { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); padding: 1.25rem; display: flex; align-items: center; gap: 1rem; transition: all 0.25s; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--card-accent, var(--gold)); opacity: 0.6; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); border-color: var(--border); }
        .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .stat-icon.blue { background: rgba(78,124,255,0.12); color: var(--accent-blue); }
        .stat-icon.green { background: rgba(62,207,142,0.12); color: var(--accent-green); }
        .stat-icon.red { background: rgba(255,71,87,0.12); color: var(--accent-red); }
        .stat-icon.orange { background: rgba(255,140,66,0.12); color: var(--accent-orange); }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: var(--text-primary); line-height: 1; font-family: 'Playfair Display', serif; }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem; }

        /* ─── FILTER SECTION ─── */
        .filter-section { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); padding: 1rem 1.25rem; margin-bottom: 1.75rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
        .search-box { position: relative; flex: 1; min-width: 220px; max-width: 380px; }
        .search-box input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; transition: all 0.2s; }
        .search-box input:focus { outline: none; border-color: var(--gold-dark); background: var(--dark-5); }
        .search-box input::placeholder { color: var(--text-muted); }
        .search-box i { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.8rem; }
        .filter-select { padding: 0.6rem 1rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; min-width: 150px; cursor: pointer; }
        .filter-select:focus { outline: none; border-color: var(--gold-dark); }
        .btn-clear { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; background: rgba(255,255,255,0.03); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-secondary); text-decoration: none; font-size: 0.875rem; transition: all 0.2s; }
        .btn-clear:hover { background: var(--dark-5); color: var(--text-primary); }

        /* ─── TABLE ─── */
        .table-container { background: var(--dark-3); border: 1px solid var(--border-subtle); border-radius: var(--radius); overflow: hidden; margin-bottom: 1.5rem; }
        .table-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; background: var(--dark-4); }
        .table-header-title { font-size: 0.875rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; }
        .admins-count { background: var(--dark-5); color: var(--text-secondary); padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.72rem; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: var(--dark-4); padding: 1rem; text-align: left; color: var(--text-secondary); font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-subtle); }
        .data-table td { padding: 1rem; border-bottom: 1px solid var(--border-subtle); color: var(--text-primary); font-size: 0.85rem; }
        .data-table tr:hover { background: rgba(255,255,255,0.02); }

        /* Admin cell */
        .admin-cell { display: flex; align-items: center; gap: 0.75rem; }
        .admin-avatar { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); display: flex; align-items: center; justify-content: center; color: var(--dark); font-size: 0.9rem; font-weight: 700; overflow: hidden; border: 2px solid rgba(201,168,76,0.3); }
        .admin-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .admin-info { display: flex; flex-direction: column; }
        .admin-name { font-weight: 600; color: var(--text-primary); }
        .admin-username { font-size: 0.7rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.2rem; }

        /* Badges */
        .status-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.3rem 0.75rem; border-radius: 99px; font-size: 0.7rem; font-weight: 600; }
        .status-active { background: rgba(62,207,142,0.12); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.2); }
        .status-banned { background: rgba(255,71,87,0.12); color: var(--accent-red); border: 1px solid rgba(255,71,87,0.2); }
        .status-deleted { background: rgba(155,155,155,0.12); color: var(--text-secondary); border: 1px solid rgba(155,155,155,0.2); }
        .status-online { background: rgba(62,207,142,0.12); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.2); }
        .status-offline { background: rgba(155,155,155,0.12); color: var(--text-secondary); border: 1px solid rgba(155,155,155,0.2); }

        .role-badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.6rem; font-weight: 700; text-transform: uppercase; background: rgba(201,168,76,0.15); color: var(--gold-light); border: 1px solid rgba(201,168,76,0.2); margin-top: 0.2rem; }

        /* Action buttons */
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
        .btn-delete { background: rgba(255,71,87,0.1); color: var(--accent-red); border: 1px solid rgba(255,71,87,0.2); }
        .btn-delete:hover { background: var(--accent-red); color: white; }
        .btn-restore { background: rgba(62,207,142,0.1); color: var(--accent-green); border: 1px solid rgba(62,207,142,0.2); }
        .btn-restore:hover { background: var(--accent-green); color: white; }
        .btn-privilege { background: rgba(201,168,76,0.1); color: var(--gold-light); border: 1px solid rgba(201,168,76,0.2); }
        .btn-privilege:hover { background: var(--gold); color: var(--dark); }

        /* Login time */
        .activity-time { font-size: 0.75rem; color: var(--text-secondary); }
        .activity-time i { margin-right: 0.2rem; font-size: 0.65rem; }

        /* Pagination */
        .pagination { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-top: 1px solid var(--border-subtle); }
        .page-info { color: var(--text-muted); font-size: 0.8rem; }
        .page-btns { display: flex; gap: 0.4rem; }
        .page-btn { padding: 0.4rem 0.8rem; background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-secondary); text-decoration: none; font-size: 0.8rem; transition: all 0.2s; }
        .page-btn:hover, .page-btn.active { background: rgba(201,168,76,0.1); border-color: rgba(201,168,76,0.3); color: var(--gold-light); }

        /* Empty state */
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.15; display: block; }
        .empty-state p { font-size: 0.9rem; }

        /* Button */
        .btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.25rem; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); color: var(--dark); border: none; border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.25s; text-decoration: none; white-space: nowrap; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(201,168,76,0.35); }
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
        <a href="admin_management.php" class="nav-item active"><i class="fas fa-user-shield"></i> Admin Management</a>
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
            <h1>Admin Management</h1>
            <p>
                <?php 
                if ($is_god_mode) echo 'Full system control — create, edit, and manage administrators';
                elseif ($is_manager_mode) echo 'Manager access — manage administrators with limited controls';
                elseif ($can_create_admin || $can_edit_admin || $can_ban_admin || $can_delete_admin) echo 'Custom permissions — selected admin management actions';
                else echo 'View-only access to administrator information';
                ?>
            </p>
        </div>
        <div class="topbar-right">
            <span class="badge-pill <?php echo $is_super_admin ? 'super' : 'admin'; ?>">
                <i class="fas fa-<?php echo $is_super_admin ? 'crown' : 'shield-alt'; ?>" style="font-size:0.65rem;"></i>
                <?php echo strtoupper(str_replace('_', ' ', $account_type)); ?> &nbsp;·&nbsp;
                <?php if ($control_level === 'full') echo 'FULL ACCESS'; elseif ($control_level === 'limited') echo 'LIMITED'; else echo strtoupper($control_level); ?>
            </span>
            <?php if ($can_create_admin): ?>
            <button class="btn-primary" onclick="openCreateModal()">
                <i class="fas fa-user-plus"></i> Create Admin
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="page-content">

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php
        $admin_permissions = [];
        if ($can_create_admin) $admin_permissions[] = 'create';
        if ($can_edit_admin) $admin_permissions[] = 'edit';
        if ($can_ban_admin) $admin_permissions[] = 'ban/unban';
        if ($can_delete_admin) $admin_permissions[] = 'delete';
        if ($can_reset_admin_password) $admin_permissions[] = 'reset passwords';
        
        $has_perms = !empty($admin_permissions);
        $last = $has_perms ? array_pop($admin_permissions) : '';
        $perm_text = $has_perms ? ('You can ' . (count($admin_permissions) ? implode(', ', $admin_permissions) . ' and ' : '') . $last . ' administrators') : 'View-only access to administrators';
        ?>
        <div class="perm-notice <?php echo $has_perms ? 'has-perms' : 'view-only'; ?>">
            <i class="fas fa-<?php echo $has_perms ? 'check-circle' : 'info-circle'; ?>"></i>
            <span><strong>Admin Management Access:</strong> <?php echo $perm_text; ?>.</span>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" style="--card-accent: var(--accent-blue);">
                <div class="stat-icon blue"><i class="fas fa-user-shield"></i></div>
                <div><div class="stat-value"><?php echo $stats['total']; ?></div><div class="stat-label">Total Admins</div></div>
            </div>
            <div class="stat-card" style="--card-accent: var(--accent-green);">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div><div class="stat-value"><?php echo $stats['active']; ?></div><div class="stat-label">Active</div></div>
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
                <input type="text" name="search" placeholder="Search admins by name, email, username..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Banned</option>
            </select>
            <select name="view_role" class="filter-select" onchange="this.form.submit()">
                <option value="admin" <?php echo ($role_filter ?? 'admin') === 'admin' ? 'selected' : ''; ?>>Active Admins</option>
                <option value="super_admin" <?php echo ($role_filter ?? '') === 'super_admin' ? 'selected' : ''; ?>>Super Admins</option>
                <option value="all" <?php echo ($role_filter ?? '') === 'all' ? 'selected' : ''; ?>>All Active</option>
                <option value="deleted" <?php echo ($role_filter ?? '') === 'deleted' ? 'selected' : ''; ?>>🗑️ Deleted Admins</option>
            </select>
            <?php if ($search || $status_filter || $role_filter !== 'admin'): ?>
                <a href="admin_management.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>

        <!-- Admins Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-header-title">
                    <i class="fas fa-user-shield" style="color: var(--gold); font-size:0.85rem;"></i>
                    Administrators
                    <span class="admins-count"><?php echo $total_admins; ?> total</span>
                </div>
                <div style="font-size:0.75rem; color:var(--text-muted); display:flex; align-items:center; gap:0.4rem;">
                    <i class="fas fa-sort-amount-down" style="font-size:0.7rem;"></i> Newest first
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Administrator</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Last Activity</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($admins) && $admins && $admins->num_rows > 0): ?>
                        <?php while($admin = $admins->fetch_assoc()): 
                            $is_online = ($admin['is_online'] == 1) && (strtotime($admin['last_activity']) > time() - 300);
                            
                            if ($admin['status'] === 'deleted') {
                                $status_class = 'status-deleted';
                                $status_text = 'Deleted';
                            } elseif ($admin['status'] === 'banned') {
                                $status_class = 'status-banned';
                                $status_text = 'Banned';
                            } elseif ($is_online) {
                                $status_class = 'status-online';
                                $status_text = 'Online';
                            } else {
                                $status_class = 'status-offline';
                                $status_text = 'Offline';
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="admin-cell">
                                    <div class="admin-avatar">
                                        <?php if (!empty($admin['profile_pic']) && file_exists($admin['profile_pic'])): ?>
                                            <img src="<?php echo htmlspecialchars($admin['profile_pic']); ?>" alt="">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($admin['fname'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="admin-info">
                                        <span class="admin-name"><?php echo htmlspecialchars($admin['fname'] . ' ' . $admin['lname']); ?></span>
                                        <span class="admin-username"><i class="fas fa-at"></i> <?php echo htmlspecialchars($admin['username']); ?></span>
                                        <?php if ($admin['account_type'] === 'super_admin'): ?>
                                            <span class="role-badge"><i class="fas fa-crown"></i> SUPER</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($admin['email']); ?></div>
                                <small style="color: var(--text-muted);"><?php echo $admin['phone'] ? htmlspecialchars($admin['phone']) : 'No phone'; ?></small>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($admin['last_activity']) {
                                    $diff = time() - strtotime($admin['last_activity']);
                                    if ($diff < 60) echo '<span class="activity-time"><i class="fas fa-clock"></i> Just now</span>';
                                    elseif ($diff < 3600) echo '<span class="activity-time"><i class="fas fa-clock"></i> ' . floor($diff/60) . ' min ago</span>';
                                    else echo '<span class="activity-time"><i class="fas fa-calendar"></i> ' . date('M d, H:i', strtotime($admin['last_activity'])) . '</span>';
                                } else {
                                    echo '<span class="activity-time" style="color: var(--text-muted);">Never</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                            <td>
                                <div class="action-btns">
                                    <?php if ($admin['status'] === 'deleted'): ?>
                                        <?php if ($is_super_admin): ?>
                                            <a href="?action=restore&id=<?php echo $admin['id_main']; ?>" 
                                               class="btn-icon btn-restore" 
                                               title="Restore Admin"
                                               onclick="return confirm('Restore this admin account?\n\nUsername: <?php echo addslashes($admin['username']); ?>\nName: <?php echo addslashes($admin['fname'] . ' ' . $admin['lname']); ?>\n\nThey will be able to log in again.');">
                                                <i class="fas fa-undo-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?view=<?php echo $admin['id_main']; ?>" class="btn-icon btn-view" title="View Details"><i class="fas fa-eye"></i></a>
                                    <?php else: ?>
                                        <?php if ($is_super_admin): ?>
                                            <button class="btn-icon btn-privilege" onclick="viewPrivileges('<?php echo $admin['username']; ?>', '<?php echo htmlspecialchars($admin['fname'] . ' ' . $admin['lname']); ?>')" title="View Permissions">
                                                <i class="fas fa-shield-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <a href="?view=<?php echo $admin['id_main']; ?>" class="btn-icon btn-view" title="View Details"><i class="fas fa-eye"></i></a>
                                        
                                        <?php if ($can_edit_admin): ?>
                                            <a href="?edit=<?php echo $admin['id_main']; ?>" class="btn-icon btn-edit" title="Edit Admin"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        
                                        <?php if ($can_ban_admin): ?>
                                            <?php if ($admin['status'] !== 'banned'): ?>
                                                <a href="?action=ban&id=<?php echo $admin['id_main']; ?>" class="btn-icon btn-ban" title="Ban Admin" onclick="return confirm('Ban this admin? They will be logged out immediately.')"><i class="fas fa-ban"></i></a>
                                            <?php else: ?>
                                                <a href="?action=unban&id=<?php echo $admin['id_main']; ?>" class="btn-icon btn-unban" title="Unban Admin"><i class="fas fa-check"></i></a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($can_delete_admin && $is_super_admin): ?>
                                            <?php if ($admin['username'] === $current_user): ?>
                                                <div style="position: relative; display: inline-block;">
                                                    <a href="#" class="btn-icon btn-delete" style="opacity: 0.6;" 
                                                       title="You cannot delete your own account" 
                                                       onclick="alert('⚠️ SECURITY WARNING: You cannot delete your own account!'); return false;">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <span style="position: absolute; top: -5px; right: -5px; background: var(--accent-red); color: white; border-radius: 50%; width: 16px; height: 16px; font-size: 10px; display: flex; align-items: center; justify-content: center;">!</span>
                                                </div>
                                            <?php else: ?>
                                                <a href="?action=delete&id=<?php echo $admin['id_main']; ?>" class="btn-icon btn-delete" 
                                                   title="Delete Admin" 
                                                   onclick="return confirm('⚠️ WARNING: You are about to deactivate this admin account!\n\nUsername: <?php echo addslashes($admin['username']); ?>\nName: <?php echo addslashes($admin['fname'] . ' ' . $admin['lname']); ?>\n\nYou will need to provide a proof photo. This account can be restored later.');">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-user-shield"></i>
                                    <p>No administrators found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (isset($total_pages) && $total_pages > 1): ?>
            <div class="pagination">
                <div class="page-info">
                    Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $limit, $total_admins); ?> of <?php echo $total_admins; ?> admins
                </div>
                <div class="page-btns">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&view_role=<?php echo $role_filter; ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&view_role=<?php echo $role_filter; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&view_role=<?php echo $role_filter; ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Privilege View Modal -->
<div id="privilegeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999999; align-items:center; justify-content:center; backdrop-filter:blur(5px);">
    <div class="privilege-modal-content" style="background: var(--dark-3); border-radius: 24px; width: 90%; max-width: 600px; max-height: 85vh; overflow: hidden; box-shadow: var(--shadow); border: 1px solid var(--border);">
        <div class="privilege-modal-header" style="background: linear-gradient(135deg, var(--dark-4), var(--dark-5)); padding: 1.5rem 2rem; border-bottom: 3px solid var(--gold-dark); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-family: 'Playfair Display', serif; color: var(--gold-light); display: flex; align-items: center; gap: 0.75rem; font-size: 1.3rem;">
                <i class="fas fa-shield-alt"></i>
                <span id="privilegeAdminName">Admin Privileges</span>
            </h3>
            <button class="privilege-modal-close" onclick="closePrivilegeModal()" style="width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.07); border: 1px solid var(--border-subtle); color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; transition: all 0.2s;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="privilege-modal-body" id="privilegeModalBody" style="padding: 2rem; overflow-y: auto; background: var(--dark-3); color: var(--text-primary);">
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-spinner fa-pulse" style="font-size: 2rem; color: var(--gold);"></i>
                <p style="margin-top: 1rem; color: var(--text-muted);">Loading privileges...</p>
            </div>
        </div>
    </div>
</div>
<!-- Create Admin Modal -->
<div class="modal-overlay" id="createModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(5px);">
    <div class="modal" style="background: var(--dark-3); width: 1000px; max-width: 95%; max-height: 90vh; overflow: hidden; border-radius: 24px; border: 1px solid var(--border); display: flex; flex-direction: column;">
        
        <div class="modal-header" style="background: linear-gradient(135deg, var(--dark-4), var(--dark-5)); padding: 1.5rem 2rem; border-bottom: 3px solid var(--gold-dark); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 50px; height: 50px; background: rgba(201,168,76,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 2px solid var(--gold-dark);">
                    <i class="fas fa-user-shield" style="color: var(--gold-light); font-size: 1.5rem;"></i>
                </div>
                <div>
                    <h2 style="color: var(--gold-light); font-family: 'Playfair Display', serif; margin: 0; font-size: 1.5rem;">Create New Administrator</h2>
                    <p style="color: var(--text-muted); margin: 0.25rem 0 0 0; font-size: 0.875rem;">Fill in the details below to register a new admin account</p>
                </div>
            </div>
            <button class="modal-close" onclick="closeCreateModal()" style="width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.07); border: 1px solid var(--border-subtle); color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; transition: all 0.2s;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="" onsubmit="return validateAdminForm()" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
            <div class="modal-body" style="padding: 2rem; overflow-y: auto; flex: 1;">
                <input type="hidden" name="create_admin" value="1">

                <!-- MAIN LAYOUT: Left column (stacked sections) + Right column (Access Control only) -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: start;">

                    <!-- ===== LEFT COLUMN ===== -->
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">

                        <!-- Personal Information -->
                        <div class="form-section" style="background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-subtle);">
                                <div style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user" style="color: var(--dark); font-size: 1rem;"></i>
                                </div>
                                <h3 style="font-family: 'Playfair Display', serif; color: var(--text-primary); margin: 0; font-size: 1.1rem;">Personal Information</h3>
                            </div>

                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">ID Number <span style="color: var(--accent-red);">*</span></label>
                                <div style="position: relative;">
                                    <i class="fas fa-id-card" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.875rem;"></i>
                                    <input type="text" name="id_main" required placeholder="ID-XXXX" style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-family: 'DM Sans', sans-serif;">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">First Name <span style="color: var(--accent-red);">*</span></label>
                                    <input type="text" name="fname" required placeholder="First name" style="width: 100%; padding: 0.625rem 0.875rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">Last Name <span style="color: var(--accent-red);">*</span></label>
                                    <input type="text" name="lname" required placeholder="Last name" style="width: 100%; padding: 0.625rem 0.875rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">M.I. <span style="color: var(--text-muted);">(Optional)</span></label>
                                    <input type="text" name="mi" placeholder="M.I." maxlength="3" style="width: 100%; padding: 0.625rem 0.875rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">Extension</label>
                                    <input type="text" name="Ename" placeholder="Jr., Sr., III" maxlength="4" style="width: 100%; padding: 0.625rem 0.875rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">Sex <span style="color: var(--accent-red);">*</span></label>
                                    <div style="position: relative;">
                                        <i class="fas fa-venus-mars" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.875rem;"></i>
                                        <select name="sex" required style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                            <option value="">Select</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">Birth Date <span style="color: var(--accent-red);">*</span></label>
                                    <div style="position: relative;">
                                        <i class="fas fa-calendar-alt" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.875rem;"></i>
                                        <input type="date" name="bd" required style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Account Details -->
                        <div class="form-section" style="background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-subtle);">
                                <div style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-lock" style="color: var(--dark); font-size: 1rem;"></i>
                                </div>
                                <h3 style="font-family: 'Playfair Display', serif; color: var(--text-primary); margin: 0; font-size: 1.1rem;">Account Details</h3>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">Username <span style="color: var(--accent-red);">*</span></label>
                                    <div style="position: relative;">
                                        <i class="fas fa-user-circle" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.875rem;"></i>
                                        <input type="text" name="username" required placeholder="Username" style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">Email <span style="color: var(--accent-red);">*</span></label>
                                    <div style="position: relative;">
                                        <i class="fas fa-envelope" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.875rem;"></i>
                                        <input type="email" name="email" required placeholder="email@example.com" style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">Phone</label>
                                <div style="position: relative;">
                                    <i class="fas fa-phone" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.875rem;"></i>
                                    <input type="tel" name="phone" placeholder="Phone number" style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                </div>
                            </div>

                            <div class="form-group">
                                <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">Account Type <span style="color: var(--accent-red);">*</span></label>
                                <div style="position: relative;">
                                    <?php if (!$is_super_admin): ?>
                                        <select name="account_type" required style="width: 100%; padding: 0.625rem 0.875rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                            <option value="admin" selected>Admin</option>
                                        </select>
                                        <div style="margin-top: 0.5rem; padding: 0.4rem 0.75rem; background: rgba(78,124,255,0.1); border-radius: 6px; font-size: 0.75rem; display: flex; align-items: center; gap: 0.5rem; border-left: 3px solid var(--accent-blue); color: var(--text-secondary);">
                                            <i class="fas fa-info-circle" style="color: var(--accent-blue);"></i>
                                            <span>You can only create Admin accounts.</span>
                                        </div>
                                    <?php else: ?>
                                        <?php
                                        $current_super_count = getSuperAdminCount($conn);
                                        $super_disabled = ($current_super_count >= 2);
                                        ?>
                                        <select name="account_type" required style="width: 100%; padding: 0.625rem 0.875rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                            <option value="">Select Type</option>
                                            <option value="admin" selected>Admin</option>
                                            <option value="super_admin" <?php echo $super_disabled ? 'disabled' : ''; ?> style="<?php echo $super_disabled ? 'opacity: 0.5;' : ''; ?>">
                                                Super Admin 
                                                <?php if ($current_super_count >= 2): ?>(Limit Reached - Max 2)<?php endif; ?>
                                            </option>
                                        </select>
                                        <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                            <span class="role-pill super">
                                                <i class="fas fa-crown"></i> Super Admins: <?php echo $current_super_count; ?>/2
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                       <!-- Set Password Section - DEFAULT PASSWORD -->
<div class="form-section" style="background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 1.5rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-subtle);">
        <div style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-key" style="color: var(--dark); font-size: 1rem;"></i>
        </div>
        <h3 style="font-family: 'Playfair Display', serif; color: var(--text-primary); margin: 0; font-size: 1.1rem;">Default Password</h3>
    </div>

    <div style="background: rgba(78,124,255,0.1); border: 1px solid rgba(78,124,255,0.3); border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="fas fa-info-circle" style="color: var(--accent-blue); font-size: 1.2rem;"></i>
            <div>
                <p style="color: var(--text-primary); font-weight: 500; margin-bottom: 0.25rem;">Default Password: <span style="color: var(--gold); font-family: monospace; font-size: 1rem;">admin123</span></p>
                <p style="color: var(--text-muted); font-size: 0.75rem;">The admin must change this password after first login in their Settings page.</p>
            </div>
        </div>
    </div>

    <!-- Security Questions Notice -->
<div class="form-section" style="background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 1.5rem; margin-top: 1rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-subtle);">
        <div style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-shield-alt" style="color: var(--dark); font-size: 1rem;"></i>
        </div>
        <h3 style="font-family: 'Playfair Display', serif; color: var(--text-primary); margin: 0; font-size: 1.1rem;">Security Questions</h3>
    </div>
    
    <div style="background: rgba(201,168,76,0.08); border-left: 3px solid var(--gold); border-radius: 8px; padding: 1rem;">
        <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
            <i class="fas fa-info-circle" style="color: var(--gold); font-size: 1rem; margin-top: 0.1rem;"></i>
            <div>
                <p style="color: var(--text-primary); font-size: 0.85rem; margin-bottom: 0.5rem;">
                    <strong>No security questions are set during account creation.</strong>
                </p>
                <p style="color: var(--text-secondary); font-size: 0.8rem; margin-bottom: 0.75rem;">
                    The administrator can set their own security questions after logging in:
                </p>
                <div style="background: var(--dark-5); border-radius: 6px; padding: 0.5rem 0.75rem; display: inline-block;">
                    <code style="color: var(--gold); font-size: 0.75rem;">
                        <i class="fas fa-cog"></i> Settings → Security Questions
                    </code>
                </div>
                <p style="color: var(--text-muted); font-size: 0.7rem; margin-top: 0.75rem;">
                    <i class="fas fa-question-circle"></i> Security questions help recover the account if the password is forgotten.
                </p>
            </div>
        </div>
    </div>
</div>
    
    <!-- Hidden fields for default password -->
    <input type="hidden" name="default_password" value="admin123">
</div>

                        <!-- Address Information -->
                        <div class="form-section" style="background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-subtle);">
                                <div style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-map-marker-alt" style="color: var(--dark); font-size: 1rem;"></i>
                                </div>
                                <h3 style="font-family: 'Playfair Display', serif; color: var(--text-primary); margin: 0; font-size: 1.1rem;">Address Information</h3>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">Purok/Street <span style="color: var(--accent-red);">*</span></label>
                                    <div style="position: relative;">
                                        <i class="fas fa-road" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.875rem;"></i>
                                        <input type="text" name="Purok" required placeholder="Purok/Street" style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">Barangay <span style="color: var(--accent-red);">*</span></label>
                                    <div style="position: relative;">
                                        <i class="fas fa-map-pin" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.875rem;"></i>
                                        <input type="text" name="Barranggay" required placeholder="Barangay" style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                    </div>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">City/Municipality <span style="color: var(--accent-red);">*</span></label>
                                    <div style="position: relative;">
                                        <i class="fas fa-city" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.875rem;"></i>
                                        <input type="text" name="CM" required placeholder="City/Municipality" style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">Province <span style="color: var(--accent-red);">*</span></label>
                                    <div style="position: relative;">
                                        <i class="fas fa-map" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.875rem;"></i>
                                        <input type="text" name="Province" required placeholder="Province" style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                    </div>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">Zip Code <span style="color: var(--accent-red);">*</span></label>
                                    <div style="position: relative;">
                                        <i class="fas fa-mail-bulk" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.875rem;"></i>
                                        <input type="text" name="zip" required placeholder="Zip Code" style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="display: block; margin-bottom: 0.375rem; color: var(--text-secondary); font-size: 0.8rem;">Country <span style="color: var(--accent-red);">*</span></label>
                                    <div style="position: relative;">
                                        <i class="fas fa-globe" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.875rem;"></i>
                                        <input type="text" name="Country" required value="Philippines" style="width: 100%; padding: 0.625rem 0.875rem 0.625rem 2.5rem; background: var(--dark-5); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary);">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <!-- ===== END LEFT COLUMN ===== -->

                    <!-- ===== RIGHT COLUMN - Access Control ONLY ===== -->
                    <div style="position: sticky; top: 0; align-self: start;">
                        <div class="form-section" style="background: var(--dark-4); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-subtle);">
                                <div style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-cogs" style="color: var(--dark); font-size: 1rem;"></i>
                                </div>
                                <h3 style="font-family: 'Playfair Display', serif; color: var(--text-primary); margin: 0; font-size: 1.1rem;">Access Control</h3>
                            </div>

                            <!-- Control Cards -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
                                <!-- FULL -->
                                <div class="control-option" onclick="selectCreateControl('full')" style="cursor: pointer; padding: 1.25rem 1rem; border: 2px solid var(--border-subtle); border-radius: 14px; text-align: center; background: var(--dark-5); transition: all 0.3s; position: relative;">
                                    <input type="radio" name="control_level" value="full" id="create_control_full" style="position: absolute; opacity: 0;">
                                    <div style="width: 42px; height: 42px; background: rgba(201,168,76,0.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem;">
                                        <i class="fas fa-crown" style="font-size: 1.2rem; color: var(--gold-light);"></i>
                                    </div>
                                    <h4 style="margin: 0 0 0.3rem 0; color: var(--text-primary); font-size: 0.9rem; font-weight: 600;">Full</h4>
                                    <p style="margin: 0; color: var(--text-muted); font-size: 0.72rem; line-height: 1.4;">All permissions granted</p>
                                </div>
                                <!-- LIMITED -->
                                <div class="control-option selected" onclick="selectCreateControl('limited')" style="cursor: pointer; padding: 1.25rem 1rem; border: 2px solid var(--gold); border-radius: 14px; text-align: center; background: rgba(201,168,76,0.06); transition: all 0.3s; position: relative;">
                                    <input type="radio" name="control_level" value="limited" id="create_control_limited" style="position: absolute; opacity: 0;" checked>
                                    <div style="width: 42px; height: 42px; background: rgba(78,124,255,0.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem;">
                                        <i class="fas fa-pen" style="font-size: 1.2rem; color: var(--accent-blue);"></i>
                                    </div>
                                    <h4 style="margin: 0 0 0.3rem 0; color: var(--text-primary); font-size: 0.9rem; font-weight: 600;">Limited</h4>
                                    <p style="margin: 0; color: var(--text-muted); font-size: 0.72rem; line-height: 1.4;">Create & edit, no delete</p>
                                </div>
                                <!-- MANUAL -->
                                <div class="control-option" onclick="selectCreateControl('manual')" style="cursor: pointer; padding: 1.25rem 1rem; border: 2px solid var(--border-subtle); border-radius: 14px; text-align: center; background: var(--dark-5); transition: all 0.3s; position: relative;">
                                    <input type="radio" name="control_level" value="manual" id="create_control_manual" style="position: absolute; opacity: 0;">
                                    <div style="width: 42px; height: 42px; background: rgba(155,89,182,0.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem;">
                                        <i class="fas fa-sliders-h" style="font-size: 1.2rem; color: var(--accent-purple);"></i>
                                    </div>
                                    <h4 style="margin: 0 0 0.3rem 0; color: var(--text-primary); font-size: 0.9rem; font-weight: 600;">Manual</h4>
                                    <p style="margin: 0; color: var(--text-muted); font-size: 0.72rem; line-height: 1.4;">Custom permissions</p>
                                </div>
                            </div>

                            <!-- Description Box -->
                            <div id="createControlDescription">
                                <div style="background: linear-gradient(135deg, rgba(78,124,255,0.08), rgba(78,124,255,0.02)); border: 1px solid rgba(78,124,255,0.3); border-radius: 12px; padding: 1.25rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                        <i class="fas fa-pen" style="color: var(--accent-blue); font-size: 1.1rem;"></i>
                                        <h4 style="margin: 0; color: var(--text-primary); font-family: 'Playfair Display', serif; font-size: 1rem;">Limited Access</h4>
                                    </div>
                                    <p style="margin: 0 0 0.75rem 0; color: var(--text-secondary); font-size: 0.8rem;">Can <strong style="color: var(--accent-green);">create and edit</strong> but <strong style="color: var(--accent-red);">cannot delete</strong> anything.</p>
                                    <div style="margin-bottom: 0.6rem;">
                                        <div style="font-size: 0.75rem; color: var(--accent-green); font-weight: 600; margin-bottom: 0.3rem;"><i class="fas fa-check-circle"></i> Can Do:</div>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.3rem; font-size: 0.75rem; color: var(--text-secondary);">
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Add Products</div>
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Edit Products</div>
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create Users</div>
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Edit Users</div>
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Ban Users</div>
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Approve Users</div>
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Reset User Passwords</div>
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create Admins</div>
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Edit Admins</div>
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Ban Admins</div>
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> View Online Users</div>
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> View Login History</div>
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> View Product Activity</div>
                                            <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> View Product Logs</div>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.75rem; color: var(--accent-red); font-weight: 600; margin-bottom: 0.3rem;"><i class="fas fa-times-circle"></i> Cannot Do:</div>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.3rem; font-size: 0.75rem; color: var(--text-secondary);">
                                            <div><i class="fas fa-times-circle" style="color: var(--accent-red);"></i> Delete Products</div>
                                            <div><i class="fas fa-times-circle" style="color: var(--accent-red);"></i> Delete Users</div>
                                            <div><i class="fas fa-times-circle" style="color: var(--accent-red);"></i> Delete Admins</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Manual Permissions -->
                            <div id="createManualPermissions" style="display: none; max-height: 400px; overflow-y: auto; padding-right: 0.5rem; margin-top: 1rem;">
                                <div class="permission-group" style="background: var(--dark-5); border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem; border-left: 3px solid var(--gold);">
                                    <h4 style="margin: 0 0 0.5rem 0; color: var(--text-primary); font-size: 0.8rem;"><i class="fas fa-box" style="color: var(--gold);"></i> Products</h4>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem;">
                                        <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8rem; color: var(--text-secondary); cursor: pointer;"><input type="checkbox" name="perm_add_product" value="1" style="accent-color: var(--gold);"> Add</label>
                                        <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8rem; color: var(--text-secondary); cursor: pointer;"><input type="checkbox" name="perm_edit_product" value="1" style="accent-color: var(--gold);"> Edit</label>
                                        <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8rem; color: var(--text-secondary); cursor: pointer;"><input type="checkbox" name="perm_delete_product" value="1" style="accent-color: var(--gold);"> Delete</label>
                                    </div>
                                </div>
                                <div class="permission-group" style="background: var(--dark-5); border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem; border-left: 3px solid var(--gold);">
                                    <h4 style="margin: 0 0 0.5rem 0; color: var(--text-primary); font-size: 0.8rem;"><i class="fas fa-users" style="color: var(--gold);"></i> Users</h4>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                        <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8rem; color: var(--text-secondary);"><input type="checkbox" name="perm_edit_user" value="1" style="accent-color: var(--gold);"> Edit</label>
                                        <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8rem; color: var(--text-secondary);"><input type="checkbox" name="perm_ban_user" value="1" style="accent-color: var(--gold);"> Ban</label>
                                        <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8rem; color: var(--text-secondary);"><input type="checkbox" name="perm_delete_user" value="1" style="accent-color: var(--gold);"> Delete</label>
                                        <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8rem; color: var(--text-secondary);"><input type="checkbox" name="perm_approve_user" value="1" style="accent-color: var(--gold);"> Approve</label>
                                        <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8rem; color: var(--text-secondary);"><input type="checkbox" name="perm_reset_password" value="1" style="accent-color: var(--gold);"> Reset Pass</label>
                                        <label style="grid-column: span 2; background: rgba(201,168,76,0.08); padding: 0.5rem; border-radius: 4px;"><input type="checkbox" name="perm_create_user" value="1" style="accent-color: var(--gold);"> <strong>Create User</strong></label>
                                    </div>
                                </div>
                                <div class="permission-group" style="background: var(--dark-5); border-radius: 8px; padding: 1rem; border-left: 3px solid var(--gold);">
    <h4 style="margin: 0 0 0.5rem 0; color: var(--text-primary); font-size: 0.8rem;">
        <i class="fas fa-user-shield" style="color: var(--gold);"></i> Admins
    </h4>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
        <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8rem; color: var(--text-secondary);"><input type="checkbox" name="perm_create_admin" value="1" style="accent-color: var(--gold);"> Create</label>
        <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8rem; color: var(--text-secondary);"><input type="checkbox" name="perm_edit_admin" value="1" style="accent-color: var(--gold);"> Edit</label>
        <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8rem; color: var(--text-secondary);"><input type="checkbox" name="perm_ban_admin" value="1" style="accent-color: var(--gold);"> Ban</label>
        <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.8rem; color: var(--text-secondary);"><input type="checkbox" name="perm_delete_admin" value="1" style="accent-color: var(--gold);"> Delete</label>
        <label style="grid-column: span 2;"><input type="checkbox" name="perm_reset_admin_pass" value="1" style="accent-color: var(--gold);"> Reset Passwords</label>

        <?php if ($is_super_admin): ?>
        <!-- SUPER ADMIN ONLY PRIVILEGE -->
        <label style="
            grid-column: span 2; 
            margin-top: 0.5rem;
            background: linear-gradient(135deg, rgba(201,168,76,0.12), rgba(201,168,76,0.04));
            border: 1px solid rgba(201,168,76,0.3);
            border-radius: 8px;
            padding: 0.6rem 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        ">
            <input type="checkbox" name="perm_promote_to_super_admin" value="1" style="accent-color: var(--gold);">
            <span style="display: flex; flex-direction: column; gap: 0.15rem;">
                <span style="color: var(--gold-light); font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 0.4rem;">
                    <i class="fas fa-crown" style="font-size: 0.75rem;"></i>
                    Can promote Admin to Super Admin
                </span>
                <span style="color: var(--text-muted); font-size: 0.7rem;">
                    Allows this admin to change another admin's role to Super Admin
                </span>
            </span>
        </label>
        <?php endif; ?>
    </div>
</div>
                            </div>
                        </div>
                    </div>
                    <!-- ===== END RIGHT COLUMN ===== -->

                </div>
            </div>

            <div class="modal-footer" style="padding: 1.5rem 2rem; border-top: 1px solid var(--border-subtle); display: flex; justify-content: flex-end; gap: 1rem; background: var(--dark-4); flex-shrink: 0;">
                <button type="button" onclick="closeCreateModal()" class="btn-secondary" style="padding: 0.75rem 1.5rem; background: transparent; border: 1px solid var(--border-subtle); color: var(--text-secondary); border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="create_admin" class="btn-primary" style="background: linear-gradient(135deg, var(--gold-dark), var(--gold)); color: var(--dark); border: none; padding: 0.75rem 1.5rem; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-user-shield"></i> Create Administrator
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal functions
function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeCreateModal() {
    document.getElementById('createModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    if (window.location.search.includes('create=')) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

// Password functions
function toggleCreatePasswordVisibility() {
    const pass = document.getElementById('createNewPassword');
    const repass = document.getElementById('createConfirmPassword');
    const show = document.getElementById('showCreatePassword');
    const type = show.checked ? 'text' : 'password';
    pass.type = type;
    repass.type = type;
}

function checkCreatePasswordStrength() {
    const password = document.getElementById('createNewPassword').value;
    const strengthDiv = document.getElementById('createPasswordStrength');
    const errorDiv = document.getElementById('createPasswordError');
    
    // Clear error when user starts typing
    if (errorDiv && password.length > 0) {
        errorDiv.style.display = 'none';
    }
    
    // Check length first
    if (password.length > 15) {
        strengthDiv.innerHTML = '<span style="color: var(--accent-red);">Password too long! Maximum 15 characters.</span>';
        return;
    }
    
    let strength = 0;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    if (password.length >= 12) strength++;
    
    let text = '', color = '';
    if (strength <= 2) { text = 'Weak'; color = 'var(--accent-red)'; }
    else if (strength <= 4) { text = 'Medium'; color = 'var(--accent-orange)'; }
    else { text = 'Strong'; color = 'var(--accent-green)'; }
    
    if (password.length < 12 && password.length > 0) {
        strengthDiv.innerHTML = `<span style="color: var(--accent-orange);">⚠️ Password must be at least 12 characters (currently ${password.length}/12)</span>`;
    } else if (password.length >= 12 && password.length <= 15) {
        strengthDiv.innerHTML = password ? `Password Strength: <span style="color: ${color};">${text}</span>` : '';
    } else if (password.length === 0) {
        strengthDiv.innerHTML = '';
    }
}

function checkCreatePasswordMatch() {
    const pass = document.getElementById('createNewPassword').value;
    const repass = document.getElementById('createConfirmPassword').value;
    const matchDiv = document.getElementById('createPasswordMatch');
    const errorDiv = document.getElementById('createPasswordError');
    
    if (!repass) {
        matchDiv.innerHTML = '';
    } else if (pass === repass) {
        matchDiv.innerHTML = '<span style="color: var(--accent-green);"><i class="fas fa-check-circle"></i> Passwords match</span>';
        // Clear error if passwords match
        if (errorDiv) {
            errorDiv.style.display = 'none';
        }
    } else {
        matchDiv.innerHTML = '<span style="color: var(--accent-red);"><i class="fas fa-exclamation-circle"></i> Passwords do not match</span>';
    }
}

function validateAdminForm() {
    const pass = document.getElementById('createNewPassword').value;
    const repass = document.getElementById('createConfirmPassword').value;
    const errorDiv = document.getElementById('createPasswordError');
    
    if (pass !== repass) {
        if (errorDiv) {
            errorDiv.innerHTML = '<span style="color: var(--accent-red);"><i class="fas fa-exclamation-circle"></i> Passwords do not match!</span>';
            errorDiv.style.display = 'block';
        }
        return false;
    }
    if (pass.length < 12) {
        if (errorDiv) {
            errorDiv.innerHTML = '<span style="color: var(--accent-red);"><i class="fas fa-exclamation-circle"></i> Password must be at least 12 characters long!</span>';
            errorDiv.style.display = 'block';
        }
        return false;
    }
    if (pass.length > 15) {
        if (errorDiv) {
            errorDiv.innerHTML = '<span style="color: var(--accent-red);"><i class="fas fa-exclamation-circle"></i> Password cannot exceed 15 characters!</span>';
            errorDiv.style.display = 'block';
        }
        return false;
    }
    
    if (errorDiv) {
        errorDiv.style.display = 'none';
    }
    return true;
}

// Control level selection
function selectCreateControl(level) {
    document.querySelectorAll('#createModal .control-option').forEach(opt => opt.classList.remove('selected'));
    document.querySelector(`#createModal .control-option[onclick*="${level}"]`).classList.add('selected');
    document.getElementById(`create_control_${level}`).checked = true;
    
    const manualPerms = document.getElementById('createManualPermissions');
    const description = document.getElementById('createControlDescription');
    
    if (level === 'manual') {
        manualPerms.style.display = 'block';
        description.style.display = 'none';
    } else {
        manualPerms.style.display = 'none';
        description.style.display = 'block';
        
        if (level === 'full') {
            description.innerHTML = `
                <div class="description-box" style="border-color: var(--gold);">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <i class="fas fa-crown" style="color: var(--gold-light); font-size: 1.25rem;"></i>
                        <h4 style="margin: 0; color: var(--text-primary); font-family: 'Playfair Display', serif; font-size: 1.1rem;">Full Access Granted</h4>
                    </div>
                    <p style="margin: 0 0 0.75rem 0; color: var(--text-secondary); font-size: 0.875rem;">This administrator has <strong style="color: var(--gold-light);">complete unrestricted access</strong> to all system features.</p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem;">
                        <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create/Edit/Delete Products</div>
                        <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create/Edit/Delete Users</div>
                        <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create/Edit/Delete Admins</div>
                        <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Manage All Permissions</div>
                    </div>
                </div>
            `;
        } else {
            description.innerHTML = `
                <div class="description-box limited" style="border-color: var(--accent-blue);">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <i class="fas fa-pen" style="color: var(--accent-blue); font-size: 1.25rem;"></i>
                        <h4 style="margin: 0; color: var(--text-primary); font-family: 'Playfair Display', serif; font-size: 1.1rem;">Limited Access</h4>
                    </div>
                    <p style="margin: 0 0 0.75rem 0; color: var(--text-secondary); font-size: 0.875rem;">This administrator can <strong style="color: var(--accent-green);">create and edit</strong> but <strong style="color: var(--accent-red);">cannot delete</strong> anything.</p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem;">
                        <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Add/Edit Products</div>
                        <div><i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Create/Edit Users</div>
                        <div><i class="fas fa-times-circle" style="color: var(--accent-red);"></i> Delete Products</div>
                        <div><i class="fas fa-times-circle" style="color: var(--accent-red);"></i> Delete Users</div>
                        <div><i class="fas fa-times-circle" style="color: var(--accent-red);"></i> Delete Admins</div>
                    </div>
                </div>
            `;
        }
    }
}



// Privilege modal functions
function viewPrivileges(username, fullname) {
    console.log('viewPrivileges called with:', username, fullname);
    
    // Show the modal
    document.getElementById('privilegeAdminName').textContent = fullname + "'s Privileges";
    document.getElementById('privilegeModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Show loading spinner
    document.getElementById('privilegeModalBody').innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-pulse" style="font-size: 2rem; color: #D4AF37;"></i><p style="margin-top: 1rem; color: #666;">Loading privileges...</p></div>';
    
    // Use the correct path (without double php)
    fetch('/ITE107/php/get_admin_privileges.php?username=' + encodeURIComponent(username))
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            if (data.success) {
                const html = buildPrivilegesHTML(data);
                document.getElementById('privilegeModalBody').innerHTML = html;
            } else {
                throw new Error(data.error || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            document.getElementById('privilegeModalBody').innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #C62828;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p><strong>Error:</strong> ${error.message}</p>
                    <p style="font-size: 0.8rem; margin-top: 0.5rem;">Attempted URL: /ITE107/php/get_admin_privileges.php?username=${username}</p>
                    <button onclick="closePrivilegeModal()" style="margin-top: 1rem; padding: 0.5rem 1.5rem; background: #8B5A2B; color: white; border: none; border-radius: 8px; cursor: pointer;">
                        Close
                    </button>
                </div>
            `;
        });
}

// Make sure the close function works
function closePrivilegeModal() {
    document.getElementById('privilegeModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}


// Close modals on outside click
document.getElementById('createModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCreateModal();
});

document.getElementById('privilegeModal')?.addEventListener('click', function(e) {
    if (e.target === this) closePrivilegeModal();
});

// Ping to stay online
fetch('ping.php');
setInterval(() => fetch('ping.php'), 30000);

</script>
<script src="../script/admin_management.js"></script>

</body>
</html>
<?php
// End of file
?>