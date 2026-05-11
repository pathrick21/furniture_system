<?php
session_start();
include 'connection.php';

// Initialize variable
$error = "";

// Check if user verified OTP first
if (!isset($_SESSION['reset_email'])) {
    die("Unauthorized access. Please verify your email first.");
}

$email = $_SESSION['reset_email'];

// Fetch security questions from DB
$stmt = $conn->prepare("SELECT sec_q1, sec_q2, sec_q3, sec_a1, sec_a2, sec_a3 FROM signinfo WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answer1 = trim($_POST['answer1']);
    $answer2 = trim($_POST['answer2']);
    $answer3 = trim($_POST['answer3']);
    
    // Check if stored value is hashed (starts with $2y$)
    $isHashed1 = substr($user['sec_a1'], 0, 4) === '$2y$';
    $isHashed2 = substr($user['sec_a2'], 0, 4) === '$2y$';
    $isHashed3 = substr($user['sec_a3'], 0, 4) === '$2y$';
    
    // Verify based on whether it's hashed or plain text
    if ($isHashed1) {
        $check1 = password_verify(strtolower($answer1), $user['sec_a1']);
    } else {
        $check1 = strtolower($answer1) === strtolower($user['sec_a1']);
    }
    
    if ($isHashed2) {
        $check2 = password_verify(strtolower($answer2), $user['sec_a2']);
    } else {
        $check2 = strtolower($answer2) === strtolower($user['sec_a2']);
    }
    
    if ($isHashed3) {
        $check3 = password_verify(strtolower($answer3), $user['sec_a3']);
    } else {
        $check3 = strtolower($answer3) === strtolower($user['sec_a3']);
    }
    
    if ($check1 && $check2 && $check3) {
        $_SESSION['security_verified'] = true;
        header("Location: reset_password.php");
        exit();
    } else {
        $error = "One or more answers are incorrect. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace - Security Verification</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/security_questions.css">
    <style>
        /* Additional styles for password input fields */
        .input-wrapper {
            position: relative;
            margin-bottom: 10px;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }
        
        .input-wrapper input:focus {
            border-color: #c4a484;
            outline: none;
            box-shadow: 0 0 0 3px rgba(196, 164, 132, 0.1);
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
            transition: color 0.3s ease;
        }
        
        .toggle-password:hover {
            color: #c4a484;
        }
        
        .input-wrapper input[type="text"] {
            letter-spacing: 2px;
        }
        
        .input-wrapper input[type="password"] {
            letter-spacing: 2px;
            font-size: 18px;
        }
        
        /* Style for the eye icon */
        .toggle-password i {
            font-size: 18px;
        }
        
        /* Optional: Add a small hint text */
        .password-hint {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .password-hint i {
            font-size: 12px;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Step Indicator -->
    <div class="step-indicator">
        <div class="step completed"><i class="fas fa-check"></i></div>
        <div class="step completed"><i class="fas fa-check"></i></div>
        <div class="step active">3</div>
        <div class="step">4</div>
    </div>

    <div class="logo-icon">
        <i class="fas fa-user-shield"></i>
    </div>
    
    <h2>Security Check</h2>
    <p class="subtitle">Verify your identity to continue</p>

    <div class="security-note">
        <i class="fas fa-lock"></i>
        Please answer all three security questions you set during registration
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="post" id="securityForm">
        <div class="question-group">
            <label class="question-label">Question 1</label>
            <div class="question-text"><?php echo htmlspecialchars($user['sec_q1']); ?></div>
            <div class="input-wrapper">
                <input type="password" name="answer1" id="answer1" placeholder="••••••" required autocomplete="off">
                <span class="toggle-password" onclick="togglePassword('answer1', this)">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
        </div>

        <div class="question-group">
            <label class="question-label">Question 2</label>
            <div class="question-text"><?php echo htmlspecialchars($user['sec_q2']); ?></div>
            <div class="input-wrapper">
                <input type="password" name="answer2" id="answer2" placeholder="••••••" required autocomplete="off">
                <span class="toggle-password" onclick="togglePassword('answer2', this)">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
        </div>

        <div class="question-group">
            <label class="question-label">Question 3</label>
            <div class="question-text"><?php echo htmlspecialchars($user['sec_q3']); ?></div>
            <div class="input-wrapper">
                <input type="password" name="answer3" id="answer3" placeholder="••••••" required autocomplete="off">
                <span class="toggle-password" onclick="togglePassword('answer3', this)">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
        </div>

        <div class="password-hint">
            <i class="fas fa-info-circle"></i>
            <span>Click the eye icon to show/hide your answers</span>
        </div>

        <button type="submit" class="btn-verify" id="submitBtn">
            <div class="spinner"></div>
            <span>Verify Answers</span>
            <i class="fas fa-arrow-right"></i>
        </button>
    </form>

    <div class="back-link">
        <a href="verify_otp.php?email=<?php echo urlencode($email); ?>">
            <i class="fas fa-arrow-left"></i> Back to OTP Verification
        </a>
    </div>

    <div class="trust-badges">
        <div class="badge"><i class="fas fa-shield-alt"></i> Secure</div>
        <div class="badge"><i class="fas fa-lock"></i> Protected</div>
        <div class="badge"><i class="fas fa-home"></i> Trusted</div>
    </div>
</div>

<script>
    document.getElementById('securityForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.classList.add('loading');
        btn.disabled = true;
    });

    // Auto-focus first input
    window.addEventListener('load', function() {
        document.querySelector('input[name="answer1"]').focus();
    });

    // Function to toggle password visibility
    function togglePassword(inputId, element) {
        const input = document.getElementById(inputId);
        const icon = element.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Optional: Add keyboard shortcut (Ctrl+Shift+E) to toggle all fields
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key === 'E') {
            e.preventDefault();
            const inputs = ['answer1', 'answer2', 'answer3'];
            const allHidden = inputs.every(id => document.getElementById(id).type === 'password');
            
            inputs.forEach(id => {
                const input = document.getElementById(id);
                const wrapper = input.parentElement;
                const icon = wrapper.querySelector('.toggle-password i');
                
                if (allHidden) {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }
    });
</script>

</body>
</html>