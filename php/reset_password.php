<?php
session_start();
include 'connection.php';

// Initialize variables to prevent undefined errors
$resetError = "";
$successMsg = "";

// Make sure the user came from security questions verification
if (!isset($_SESSION['reset_email'])) {
    die("Unauthorized access. Please verify your email first.");
}
if (!isset($_SESSION['security_verified']) || $_SESSION['security_verified'] !== true) {
    die("Unauthorized access. Please complete security verification.");
}

$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Basic validation
    if (strlen($password) < 6) {
        $resetError = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirmPassword) {
        $resetError = "Passwords do not match.";
    } else {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Update password in database
        $stmt = $conn->prepare("UPDATE signinfo SET password=?, reset_otp=NULL, otp_expiry=NULL WHERE email=?");
        $stmt->bind_param("ss", $hashedPassword, $email);

        if ($stmt->execute()) {
            $successMsg = "Password successfully updated! You can now <a href='login.php'>login</a>.";
            // Remove ALL session variables
            unset($_SESSION['reset_email']);
            unset($_SESSION['security_verified']);
            unset($_SESSION['otp_expires']);
        } else {
            $resetError = "Failed to reset password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace - Reset Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/reset_password.css">
</head>
<body>

<div class="container">
    <!-- Step Indicator -->
    <div class="step-indicator">
        <div class="step completed"><i class="fas fa-check"></i></div>
        <div class="step completed"><i class="fas fa-check"></i></div>
        <div class="step completed"><i class="fas fa-check"></i></div>
        <div class="step completed"><i class="fas fa-check"></i></div>
    </div>

    <div class="logo-icon">
        <i class="fas fa-lock"></i>
    </div>
    
    <h2>Reset Password</h2>
    <p class="subtitle">Create your new secure password</p>

    <?php if (!empty($resetError)): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $resetError; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMsg)): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <div><?php echo $successMsg; ?></div>
        </div>
    <?php else: ?>

    <form method="post" id="resetForm">
        <div class="requirements">
            <div class="requirements-title">
                <i class="fas fa-shield-alt"></i>
                Password Requirements
            </div>
            <ul>
                <li id="req-length" class="invalid">
                    <i class="fas fa-circle"></i>
                    At least 6 characters
                </li>
                <li id="req-match" class="invalid">
                    <i class="fas fa-circle"></i>
                    Passwords match
                </li>
            </ul>
        </div>

        <div class="input-group">
            <label for="password">New Password</label>
            <div class="input-wrapper">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" id="password" name="password" placeholder="Enter new password" required>
                <i class="fas fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
            </div>
            <div class="password-strength" id="strength-meter" style="display: none;">
                <div class="strength-bar" id="strength-bar"></div>
            </div>
            <div class="strength-text" id="strength-text"></div>
        </div>

        <div class="input-group">
            <label for="confirm_password">Confirm Password</label>
            <div class="input-wrapper">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
            </div>
        </div>

        <button type="submit" id="submitBtn">
            <div class="spinner"></div>
            <span>Reset Password</span>
            <i class="fas fa-arrow-right"></i>
        </button>

        <div class="login-link">
            Remember your password? <a href="login.php">Log in</a>
        </div>
    </form>

    <?php endif; ?>
</div>

<script>
    function togglePassword(fieldId, icon) {
        const field = document.getElementById(fieldId);
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const strengthMeter = document.getElementById('strength-meter');
    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');
    const reqLength = document.getElementById('req-length');
    const reqMatch = document.getElementById('req-match');
    const submitBtn = document.getElementById('submitBtn');

    password.addEventListener('input', function() {
        const val = this.value;
        
        if (val.length > 0) {
            strengthMeter.style.display = 'block';
        } else {
            strengthMeter.style.display = 'none';
        }

        if (val.length >= 6) {
            reqLength.classList.remove('invalid');
            reqLength.classList.add('valid');
            reqLength.innerHTML = '<i class="fas fa-check-circle"></i> At least 6 characters';
        } else {
            reqLength.classList.remove('valid');
            reqLength.classList.add('invalid');
            reqLength.innerHTML = '<i class="fas fa-circle"></i> At least 6 characters';
        }

        let strength = 0;
        if (val.length >= 6) strength++;
        if (val.length >= 10) strength++;
        if (/[A-Z]/.test(val)) strength++;
        if (/[0-9]/.test(val)) strength++;
        if (/[^A-Za-z0-9]/.test(val)) strength++;

        strengthBar.className = 'strength-bar';
        if (strength <= 2) {
            strengthBar.classList.add('strength-weak');
            strengthText.textContent = 'Weak password';
            strengthText.style.color = '#e74c3c';
        } else if (strength <= 4) {
            strengthBar.classList.add('strength-medium');
            strengthText.textContent = 'Medium strength';
            strengthText.style.color = '#f39c12';
        } else {
            strengthBar.classList.add('strength-strong');
            strengthText.textContent = 'Strong password';
            strengthText.style.color = '#27ae60';
        }

        checkMatch();
    });

    confirmPassword.addEventListener('input', checkMatch);

    function checkMatch() {
        const pass = password.value;
        const confirm = confirmPassword.value;

        if (confirm.length > 0) {
            if (pass === confirm && pass.length >= 6) {
                reqMatch.classList.remove('invalid');
                reqMatch.classList.add('valid');
                reqMatch.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
                return true;
            } else {
                reqMatch.classList.remove('valid');
                reqMatch.classList.add('invalid');
                reqMatch.innerHTML = '<i class="fas fa-circle"></i> Passwords match';
                return false;
            }
        }
        return false;
    }

    document.getElementById('resetForm').addEventListener('submit', function(e) {
        const pass = password.value;
        const confirm = confirmPassword.value;

        if (pass.length < 6 || pass !== confirm) {
            e.preventDefault();
            alert('Please ensure passwords meet all requirements.');
            return;
        }

        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
    });
</script>

</body>
</html>