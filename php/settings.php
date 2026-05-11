<?php
session_start();
include 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['user'];
$success_msg = '';
$error_msg = '';

// Create user_settings table if not exists (FIXED!)
$conn->query("CREATE TABLE IF NOT EXISTS user_settings (
    username VARCHAR(50) PRIMARY KEY,
    email_notifications TINYINT DEFAULT 1,
    order_updates TINYINT DEFAULT 1,
    promotions TINYINT DEFAULT 1,
    profile_visibility TINYINT DEFAULT 1,
    activity_status TINYINT DEFAULT 1,
    FOREIGN KEY (username) REFERENCES signinfo(username) ON DELETE CASCADE
)");

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM signinfo WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Handle Notification Settings
if (isset($_POST['save_notifications'])) {
    $email_notif = isset($_POST['email_notifications']) ? 1 : 0;
    $order_updates = isset($_POST['order_updates']) ? 1 : 0;
    $promotions = isset($_POST['promotions']) ? 1 : 0;
    
    $update = $conn->prepare("INSERT INTO user_settings 
        (username, email_notifications, order_updates, promotions) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        email_notifications = VALUES(email_notifications),
        order_updates = VALUES(order_updates),
        promotions = VALUES(promotions)");
    $update->bind_param("siii", $username, $email_notif, $order_updates, $promotions);
    
    if ($update->execute()) {
        $success_msg = "Notification preferences saved!";
    } else {
        $error_msg = "Failed to save settings!";
    }
}

// Handle Privacy Settings
if (isset($_POST['save_privacy'])) {
    $profile_visible = isset($_POST['profile_visibility']) ? 1 : 0;
    $activity_status = isset($_POST['activity_status']) ? 1 : 0;
    
    $update = $conn->prepare("INSERT INTO user_settings 
        (username, profile_visibility, activity_status) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        profile_visibility = VALUES(profile_visibility),
        activity_status = VALUES(activity_status)");
    $update->bind_param("sii", $username, $profile_visible, $activity_status);
    
    if ($update->execute()) {
        $success_msg = "Privacy settings saved!";
    } else {
        $error_msg = "Failed to save settings!";
    }
}

// Handle Delete Account
if (isset($_POST['delete_account'])) {
    $password = $_POST['confirm_delete_password'];
    
    if (password_verify($password, $user_data['password'])) {
        // Delete user
        $delete = $conn->prepare("DELETE FROM signinfo WHERE username = ?");
        $delete->bind_param("s", $username);
        
        if ($delete->execute()) {
            session_destroy();
            header("Location: login.php?msg=account_deleted");
            exit();
        }
    } else {
        $error_msg = "Incorrect password! Account not deleted.";
    }
}

// Fetch notification settings (FIXED - no error if empty)
$notif_settings = [
    'email_notifications' => 1, 
    'order_updates' => 1, 
    'promotions' => 1,
    'profile_visibility' => 1,
    'activity_status' => 1
];

$notif_stmt = $conn->prepare("SELECT * FROM user_settings WHERE username = ?");
$notif_stmt->bind_param("s", $username);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
if ($notif_result && $notif_result->num_rows > 0) {
    $notif_settings = array_merge($notif_settings, $notif_result->fetch_assoc());
}

// Calculate cart count
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'];
    }
}

$profile_pic = !empty($user_data['profile_pic']) && file_exists($user_data['profile_pic']) 
    ? $user_data['profile_pic'] 
    : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace - Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #faf8f5; min-height: 100vh; }

        /* Header & Menu (Same as shop.php) */
        .main-header {
            background: white; padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between;
            align-items: center; position: sticky; top: 0; z-index: 100;
        }
        .header-left { display: flex; align-items: center; gap: 1rem; }
        .hamburger-btn {
            width: 40px; height: 40px; background: none; border: none;
            cursor: pointer; display: flex; flex-direction: column;
            gap: 5px; padding: 5px;
        }
        .hamburger-btn span { width: 25px; height: 3px; background: #3E2723; border-radius: 3px; transition: all 0.3s; }
        .logo-icon { color: #8B5A2B; font-size: 1.75rem; }
        .header-left h1 { font-family: 'Playfair Display', serif; color: #3E2723; font-size: 1.5rem; }
        .header-right { display: flex; align-items: center; gap: 1.5rem; }
        .cart-icon { position: relative; font-size: 1.25rem; color: #3E2723; text-decoration: none; }
        .cart-count {
            position: absolute; top: -8px; right: -8px; background: #FF9800;
            color: white; font-size: 0.75rem; font-weight: 600;
            width: 20px; height: 20px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
        .header-profile { display: flex; align-items: center; gap: 0.75rem; }
        .profile-avatar {
            width: 40px; height: 40px; border-radius: 50%; overflow: hidden;
            border: 2px solid #8B5A2B; display: flex; align-items: center;
            justify-content: center; background: #f5f2ed;
        }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .welcome-text { color: #5d4e37; font-weight: 500; font-size: 0.9rem; }

        /* Side Menu */
        .menu-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 998;
            opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .menu-overlay.active { opacity: 1; visibility: visible; }
        .side-menu {
            position: fixed; top: 0; left: -320px; width: 320px; height: 100vh;
            background: linear-gradient(135deg, #3E2723 0%, #5d4e37 100%);
            color: white; z-index: 999; transition: left 0.3s ease;
            overflow-y: auto; box-shadow: 2px 0 10px rgba(0,0,0,0.3);
        }
        .side-menu.active { left: 0; }
        .menu-header {
            padding: 2rem; border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex; justify-content: space-between; align-items: center;
        }
        .user-info { display: flex; align-items: center; gap: 1rem; }
        .user-avatar {
            width: 60px; height: 60px; border-radius: 50%; overflow: hidden;
            border: 3px solid rgba(139, 90, 43, 0.3); display: flex;
            align-items: center; justify-content: center;
            background: rgba(255,255,255,0.2); font-size: 1.75rem; color: white;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-details h3 { font-family: 'Playfair Display', serif; font-size: 1.25rem; margin-bottom: 0.25rem; }
        .user-details span { opacity: 0.8; font-size: 0.875rem; }
        .close-menu {
            background: none; border: none; color: white; font-size: 1.5rem;
            cursor: pointer; width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%; transition: background 0.3s;
        }
        .close-menu:hover { background: rgba(255,255,255,0.1); }
        .menu-items { padding: 1rem 0; }
        .menu-item {
            display: flex; align-items: center; padding: 1rem 2rem;
            color: white; text-decoration: none; transition: all 0.3s;
            gap: 1rem; position: relative;
        }
        .menu-item:hover { background: rgba(255,255,255,0.1); padding-left: 2.5rem; }
        .menu-item i:first-child { width: 24px; font-size: 1.25rem; color: #D4AF37; }
        .menu-item span { flex: 1; font-weight: 500; }
        .menu-item .arrow { font-size: 0.875rem; opacity: 0.7; }
        .menu-item.highlight { background: rgba(212, 175, 55, 0.15); border-left: 3px solid #D4AF37; }
        .menu-item.highlight i:first-child { color: #FFD700; }
        .badge { background: #FF9800; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .new-tag { background: #4CAF50; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.625rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .menu-footer { padding: 1rem 0; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 2rem; }
        .menu-item.small { padding: 0.75rem 2rem; font-size: 0.875rem; }
        .menu-item.logout { color: #FF9800; }
        .menu-item.logout i { color: #FF9800; }
        body.menu-open { overflow: hidden; }

        /* Settings Page Styles */
        .settings-container { max-width: 800px; margin: 2rem auto; padding: 0 2rem; }
        
        .settings-header {
            background: linear-gradient(135deg, #3E2723 0%, #8B5A2B 100%);
            color: white; padding: 2.5rem; border-radius: 20px;
            margin-bottom: 2rem; text-align: center;
        }
        .settings-header h1 { font-family: 'Playfair Display', serif; font-size: 2rem; margin-bottom: 0.5rem; }
        .settings-header p { opacity: 0.9; }

        .settings-card {
            background: white; border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 2rem; margin-bottom: 1.5rem;
        }

        .section-title {
            font-family: 'Playfair Display', serif; color: #3E2723;
            font-size: 1.25rem; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 0.75rem;
        }
        .section-title i { color: #8B5A2B; }

        /* Quick Links to Profile */
        .quick-links {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem; margin-bottom: 1.5rem;
        }
        .quick-link {
            display: flex; align-items: center; gap: 1rem;
            padding: 1.25rem; background: #faf8f5;
            border-radius: 12px; text-decoration: none;
            color: #5d4e37; transition: all 0.3s;
            border: 2px solid transparent;
        }
        .quick-link:hover {
            border-color: #8B5A2B; background: #fff8e7;
            transform: translateY(-2px);
        }
        .quick-link i { font-size: 1.5rem; color: #8B5A2B; }
        .quick-link h4 { margin-bottom: 0.25rem; }
        .quick-link p { font-size: 0.85rem; color: #8b7355; }

        /* Toggle Switch */
        .toggle-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1.25rem; background: #faf8f5; border-radius: 10px;
            margin-bottom: 1rem;
        }
        .toggle-info h4 { color: #3E2723; margin-bottom: 0.25rem; }
        .toggle-info p { color: #8b7355; font-size: 0.875rem; }
        
        .toggle-switch {
            position: relative; width: 50px; height: 26px;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background: #e0d5c7; transition: 0.3s; border-radius: 34px;
        }
        .toggle-slider:before {
            position: absolute; content: ""; height: 20px; width: 20px;
            left: 3px; bottom: 3px; background: white; transition: 0.3s; border-radius: 50%;
        }
        .toggle-switch input:checked + .toggle-slider { background: #8B5A2B; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(24px); }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem; border-radius: 10px; border: none;
            cursor: pointer; font-weight: 600; transition: all 0.3s;
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #8B5A2B 0%, #3E2723 100%);
            color: white;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(139, 90, 43, 0.3); }

        /* Danger Zone */
        .danger-zone {
            border: 2px solid #e74c3c; border-radius: 15px;
            padding: 1.5rem; margin-top: 2rem;
        }
        .danger-zone h3 { color: #e74c3c; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .btn-danger {
            background: #e74c3c; color: white;
        }
        .btn-danger:hover { background: #c0392b; }

        .delete-form { display: none; margin-top: 1rem; }
        .delete-form.active { display: block; }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem; border-radius: 10px; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 0.75rem;
        }
        .alert-success { background: #E8F5E9; color: #2E7D32; border-left: 4px solid #2E7D32; }
        .alert-error { background: #FFEBEE; color: #C62828; border-left: 4px solid #C62828; }

        @media (max-width: 768px) {
            .quick-links { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Menu Overlay -->
    <div class="menu-overlay" id="menuOverlay" onclick="toggleMenu()"></div>

    <!-- Side Navigation -->
    <nav class="side-menu" id="sideMenu">
        <div class="menu-header">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if ($profile_pic): ?>
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($user_data['fname'] . ' ' . $user_data['lname']); ?></h3>
                    <span><?php echo htmlspecialchars($username); ?></span>
                </div>
            </div>
            <button class="close-menu" onclick="toggleMenu()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="menu-items">
            <a href="home.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
                <i class="fas fa-chevron-right arrow"></i>
            </a>
            <a href="profile.php" class="menu-item">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
                <i class="fas fa-chevron-right arrow"></i>
            </a>
            <a href="shop.php" class="menu-item">
                <i class="fas fa-store"></i>
                <span>Shop</span>
                <i class="fas fa-chevron-right arrow"></i>
            </a>
            <a href="cart.php" class="menu-item">
                <i class="fas fa-shopping-cart"></i>
                <span>My Cart</span>
                <span class="badge"><?php echo $cart_count; ?></span>
            </a>
            <a href="settings.php" class="menu-item highlight">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
                <i class="fas fa-chevron-right arrow"></i>
            </a>
        </div>

        <div class="menu-footer">
            <a href="logout.php" class="menu-item small logout" onclick="recordLogout(event)">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <button class="hamburger-btn" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <i class="fas fa-chair logo-icon"></i>
            <h1>Furniplace</h1>
        </div>
        <div class="header-right">
            <a href="cart.php" class="cart-icon">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count"><?php echo $cart_count; ?></span>
            </a>
            <div class="header-profile">
                <div class="profile-avatar">
                    <?php if ($profile_pic): ?>
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($username); ?>!</span>
            </div>
        </div>
    </header>

    <!-- Settings Content -->
    <div class="settings-container">
        <div class="settings-header">
            <h1><i class="fas fa-cog"></i> Settings</h1>
            <p>Manage your preferences and account</p>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_msg): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <!-- Quick Links to Profile -->
        <div class="quick-links">
            <a href="profile.php" class="quick-link">
                <i class="fas fa-user-edit"></i>
                <div>
                    <h4>Edit Profile</h4>
                    <p>Update personal info & password</p>
                </div>
            </a>
            <a href="cart.php" class="quick-link">
                <i class="fas fa-shopping-bag"></i>
                <div>
                    <h4>My Orders</h4>
                    <p>View order history</p>
                </div>
            </a>
        </div>

        <!-- Notifications -->
        <div class="settings-card">
            <h2 class="section-title">
                <i class="fas fa-bell"></i> Notifications
            </h2>
            
            <form method="POST">
                <div class="toggle-item">
                    <div class="toggle-info">
                        <h4>Email Notifications</h4>
                        <p>Receive order updates and promotions via email</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="email_notifications" <?php echo $notif_settings['email_notifications'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="toggle-item">
                    <div class="toggle-info">
                        <h4>Order Updates</h4>
                        <p>Get notified when your order status changes</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="order_updates" <?php echo $notif_settings['order_updates'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="toggle-item">
                    <div class="toggle-info">
                        <h4>Promotions & Deals</h4>
                        <p>Receive special offers and discount codes</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="promotions" <?php echo $notif_settings['promotions'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <button type="submit" name="save_notifications" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-save"></i> Save Preferences
                </button>
            </form>
        </div>

        <!-- Privacy Settings -->
        <div class="settings-card">
            <h2 class="section-title">
                <i class="fas fa-lock"></i> Privacy
            </h2>
            
            <form method="POST">
                <div class="toggle-item">
                    <div class="toggle-info">
                        <h4>Profile Visibility</h4>
                        <p>Make your profile visible to other users</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="profile_visibility" <?php echo $notif_settings['profile_visibility'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="toggle-item">
                    <div class="toggle-info">
                        <h4>Activity Status</h4>
                        <p>Show when you're online</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="activity_status" <?php echo $notif_settings['activity_status'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <button type="submit" name="save_privacy" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-save"></i> Save Privacy Settings
                </button>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="settings-card danger-zone">
            <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
            <p style="margin-bottom: 1rem; color: #5d4e37;">Once you delete your account, there is no going back. Please be certain.</p>
            <button class="btn btn-danger" onclick="toggleDeleteForm()">
                <i class="fas fa-trash-alt"></i> Delete Account
            </button>
            
            <form method="POST" class="delete-form" id="deleteForm">
                <p style="margin-bottom: 1rem; color: #5d4e37;">Enter your password to confirm:</p>
                <input type="password" name="confirm_delete_password" placeholder="Your password" required style="padding: 0.75rem; border: 2px solid #e0d5c7; border-radius: 8px; margin-bottom: 1rem; width: 100%;">
                <div style="display: flex; gap: 1rem;">
                    <button type="button" class="btn btn-secondary" onclick="toggleDeleteForm()">Cancel</button>
                    <button type="submit" name="delete_account" class="btn btn-danger">Confirm Delete</button>
                </div>
            </form>
        </div>

    </div>

    <!-- JavaScript -->
    <script>
        function toggleMenu() {
            const sideMenu = document.getElementById('sideMenu');
            const menuOverlay = document.getElementById('menuOverlay');
            const body = document.body;
            
            sideMenu.classList.toggle('active');
            menuOverlay.classList.toggle('active');
            body.classList.toggle('menu-open');
        }

        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleMenu();
                }
            });
        });

        function toggleDeleteForm() {
            document.getElementById('deleteForm').classList.toggle('active');
        }

        function recordLogout(e) {
            e.preventDefault();
            navigator.sendBeacon('ping.php?action=offline');
            window.location.href = 'logout.php';
        }

        fetch('ping.php').catch(err => console.log('Ping failed'));
        setInterval(function() {
            fetch('ping.php').catch(err => console.log('Ping failed'));
        }, 30000);
        window.addEventListener('beforeunload', function() {
            navigator.sendBeacon('ping.php?action=offline');
        });
    </script>

</body>
</html>