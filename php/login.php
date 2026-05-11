<?php
session_start();
include 'connection.php';

// Only include device helper if it exists
if (file_exists('device_helper.php')) {
    include 'device_helper.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $user = $_POST['username'] ?? '';
    $pass = $_POST['pass'] ?? '';

   $stmt = $conn->prepare("SELECT id_main, username, password, account_type, status FROM signinfo WHERE username = ?");
$stmt->bind_param("s", $user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Username does not exist', 'error_type' => 'username']);
    exit;
}

$row = $result->fetch_assoc();

// CHECK IF USER IS BANNED
if (isset($row['status']) && $row['status'] === 'banned') {
    echo json_encode(['success' => false, 'error' => 'Your account has been banned. Contact admin for assistance.', 'error_type' => 'banned']);
    exit;
}

// CHECK IF USER IS PENDING APPROVAL - ADD THIS HERE
if (isset($row['status']) && $row['status'] === 'pending') {
    echo json_encode(['success' => false, 'error' => 'Your account is pending approval. Please wait for administrator approval before logging in.', 'error_type' => 'pending']);
    exit;
}

// CHECK IF USER IS REJECTED - ADD THIS HERE
if (isset($row['status']) && $row['status'] === 'rejected') {
    echo json_encode(['success' => false, 'error' => 'Your registration has been rejected. Please contact support for assistance.', 'error_type' => 'rejected']);
    exit;
}

if (!password_verify($pass, $row['password'])) {
    echo json_encode(['success' => false, 'error' => 'Incorrect password', 'error_type' => 'password']);
    exit;
}   

    // Set session
    $_SESSION['user'] = $row['username'];
    $_SESSION['id_main'] = $row['id_main'];
    $_SESSION['account_type'] = $row['account_type'] ?? 'user';
    
    // Safe device tracking (only if functions exist)
    if (function_exists('getDeviceInfo') && function_exists('getClientIP')) {
        $device = getDeviceInfo();
$ip = getClientIP();

if (is_array($device)) {
    // NEW: Get actual device name like "iPhone 14 Pro" or "Samsung Galaxy S23"
    $device_name = $device['device_name'] ?? 'Unknown Device';
    $browser = $device['browser'] ?? 'Unknown';
    $os = $device['os'] ?? 'Unknown';
    
    // Save device_name into device_info column
    $sess_stmt = $conn->prepare("
        INSERT INTO user_sessions 
        (username, ip_address, device_info, browser, os, is_online, login_time, last_activity) 
        VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
        ip_address = VALUES(ip_address), 
        device_info = VALUES(device_info), 
        browser = VALUES(browser), 
        os = VALUES(os), 
        last_activity = CURRENT_TIMESTAMP, 
        is_online = 1
    ");
    // Bind $device_name instead of $device_type
    $sess_stmt->bind_param("sssss", $row['username'], $ip, $device_name, $browser, $os);
    $sess_stmt->execute();
    
    // Also update user_logs
    $log_stmt = $conn->prepare("
        INSERT INTO user_logs 
        (username, action, ip_address, device_info, browser, os, login_time) 
        VALUES (?, 'login', ?, ?, ?, ?, NOW())
    ");
    $log_stmt->bind_param("sssss", $row['username'], $ip, $device_name, $browser, $os);
    $log_stmt->execute();
}
    }

  if ($row['account_type'] === 'super_admin') {
    $redirect = '../php/super_admin_dashboard.php';
} elseif ($row['account_type'] === 'admin') {
    $redirect = '../php/super_admin_dashboard.php';
} else {
    $redirect = '../php/home.php';
}
    
    echo json_encode(['success' => true, 'redirect' => $redirect]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FurniPlace - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/login.css">
    
    <!-- Add this style block for the countdown timer -->
    <style>
        /* Lockout Countdown Timer - Above Username */
        .lockout-countdown {
            display: none; /* Hidden by default */
            background: linear-gradient(135deg, #FFF3E0 0%, #FFE0B2 100%);
            border: 2px solid #FF9800;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s ease;
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.15);
        }

        .lockout-countdown.active {
            display: flex;
        }

        .lockout-icon {
            width: 40px;
            height: 40px;
            background: #FF9800;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }

        .lockout-content {
            flex: 1;
        }

        .lockout-title {
            color: #E65100;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 4px;
            font-family: 'Playfair Display', serif;
        }

        .lockout-text {
            color: #BF360C;
            font-size: 13px;
            margin: 0;
        }

        #time-left {
            font-weight: 700;
            color: #E65100;
            font-size: 18px;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }

        .lockout-progress {
            width: 100%;
            height: 4px;
            background: rgba(255, 152, 0, 0.2);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }

        .lockout-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #FF9800, #F57C00);
            width: 100%;
            transform-origin: left;
            animation: countdown-progress 15s linear;
        }

        @keyframes countdown-progress {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Visual feedback when inputs are disabled during lockout */
        input:disabled {
            background-color: #f5f5f5 !important;
            cursor: not-allowed;
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-brand">
            <i class="fas fa-couch"></i>
            <span>FurniPlace</span>
        </div>
        <div class="nav-links">
            <a href="../php/home_web.php" id="register-link3" class="nav-link">Home</a>
            <a href="register.php" id="register-link" class="nav-link nav-link-primary">Register</a>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="container">
        
        <!-- Left Side: Branding -->
        <div class="brand-section">
            <div class="brand-content">
                <h1 class="brand-title">FurniPlace</h1>
                <p class="brand-tagline">Premium Furniture for Your Home</p>
                
                <div class="quote-box">
                    <i class="fas fa-quote-left quote-icon"></i>
                    <p class="quote-text">"Furniture will fill a space full of memories and a heart full of love."</p>
                    <i class="fas fa-quote-right quote-icon"></i>
                </div>

                <div class="features">
                    <div class="feature">
                        <i class="fas fa-truck"></i>
                        <span>Free Delivery</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-shield-alt"></i>
                        <span>5 Year Warranty</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-undo"></i>
                        <span>30 Days Return</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="login-section">
            <div class="login-card">
                <div class="login-header">
                    <h2>Welcome Back</h2>
                    <p>Sign in to your account</p>
                </div>

                <!-- FORCE LOGOUT MESSAGE (from admin) -->
<?php if (isset($_GET['force_logout'])): ?>
    <div style="background: #FFF3E0; color: #F57C00; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border-left: 4px solid #F57C00; display: flex; align-items: center; gap: 1rem; box-shadow: 0 4px 12px rgba(245, 124, 0, 0.15);">
        <div style="width: 40px; height: 40px; background: #F57C00; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div style="flex: 1;">
            <strong style="color: #E65100; font-size: 1rem; display: block; margin-bottom: 0.25rem;">Session Terminated</strong>
            <p style="margin: 0; color: #BF360C; font-size: 0.9rem;">Your session was ended by an administrator. Please log in again.</p>
        </div>
        <button onclick="this.parentElement.parentElement.remove()" style="background: transparent; border: none; color: #F57C00; cursor: pointer; font-size: 1.2rem;">
            <i class="fas fa-times"></i>
        </button>
    </div>
<?php endif; ?>

<!-- BANNED MESSAGE -->
<?php if (isset($_GET['banned'])): ?>
    <div style="background: #FFEBEE; color: #C62828; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border-left: 4px solid #C62828; display: flex; align-items: center; gap: 1rem; box-shadow: 0 4px 12px rgba(198, 40, 40, 0.15);">
        <div style="width: 40px; height: 40px; background: #C62828; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem;">
            <i class="fas fa-ban"></i>
        </div>
        <div style="flex: 1;">
            <strong style="color: #C62828; font-size: 1rem; display: block; margin-bottom: 0.25rem;">Account Banned</strong>
            <p style="margin: 0; color: #B71C1C; font-size: 0.9rem;">Your account has been banned. Please contact support for assistance.</p>
        </div>
        <button onclick="this.parentElement.parentElement.remove()" style="background: transparent; border: none; color: #C62828; cursor: pointer; font-size: 1.2rem;">
            <i class="fas fa-times"></i>
        </button>
    </div>
<?php endif; ?>

<!-- MULTIPLE ATTEMPTS ALERT -->
<div id="attempts-alert-container"></div>

<!-- REGULAR ERROR MESSAGE -->
<div id="login-error" class="error-message" style="display: none;"></div>

                <form class="LoginForm" action="" method="post" novalidate>
                    
                    <!-- LOCKOUT COUNTDOWN TIMER - ABOVE USERNAME -->
                    <div id="countdown" class="lockout-countdown">
                        <div class="lockout-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="lockout-content">
                            <div class="lockout-title">Account Temporarily Locked</div>
                            <p class="lockout-text">
                                Too many failed attempts. Try again in <span id="time-left">15</span>s
                            </p>
                            <div class="lockout-progress">
                                <div class="lockout-progress-bar" id="progress-bar"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                placeholder="Enter your username" 
                                required
                                autocomplete="username"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="password" 
                                name="pass" 
                                placeholder="Enter your password" 
                                required
                                autocomplete="current-password"
                            >
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="showPassword" onclick="togglePassword()">
                            <span class="checkmark"></span>
                            <span class="label-text">Show Password</span>
                        </label>
                        
                        <!-- Forgot Password Link -->
                        <div id="forgot-password" class="forgot-link" style="display: none;">
                            <a href="forgot.php">Forgot Password?</a>
                        </div>
                    </div>

                    <button type="submit" id="login-btn" class="submit-btn">
                        <span>Sign In</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>

                </form>

                <div class="social-login">
                    <div class="divider">
                        <span>Or continue with</span>
                    </div>
                    <div class="social-buttons">
                        <a href="https://gmail.com" target="_blank" class="social-btn google">
                            <i class="fab fa-google"></i>
                        </a>
                        <a href="https://facebook.com" target="_blank" class="social-btn facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://instagram.com" target="_blank" class="social-btn instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>

                <div class="register-prompt">
                    <p>Don't have an account? <a href="register.php">Create one</a></p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
            <p>&copy; 2024 FurniPlace. All rights reserved.</p>
    </footer>

        <script src="../script/login.js"></script>
<script>
// Ping immediately when page loads
fetch('ping.php');

// Then ping every 30 seconds to stay online
setInterval(function() {
    fetch('ping.php');
}, 30000);

// Mark offline when leaving
window.addEventListener('beforeunload', function() {
    navigator.sendBeacon('ping.php?action=offline');
});

// Check for force logout or banned parameter on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('force_logout')) {
        // Auto-focus on username input
        document.getElementById('username').focus();
        console.log('Force logout detected - focusing username field');
    }
    if (urlParams.has('banned')) {
        // Auto-focus on username input
        document.getElementById('username').focus();
        console.log('Banned account detected - focusing username field');
    }
});
</script>
</body>
</html>