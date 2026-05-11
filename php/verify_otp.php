<?php
session_start();
include 'connection.php';

// Initialize variable
$otpError = "";

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot.php");
    exit();
}
$email = $_SESSION['reset_email'];

$stmt = $conn->prepare("SELECT reset_otp, otp_expiry FROM signinfo WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("Email not found.");
}

if (empty($row['reset_otp'])) {
    die("No OTP found. Please request a new one.");
}

$dbExpiry = strtotime($row['otp_expiry']);
if (!isset($_SESSION['otp_expires']) || $_SESSION['otp_expires'] != $dbExpiry) {
    $_SESSION['otp_expires'] = $dbExpiry;
}
$remainingSeconds = max(0, $_SESSION['otp_expires'] - time());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredOtp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    
     if (!password_verify($enteredOtp, $row['reset_otp'])) {
        $otpError = "Incorrect OTP. Please try again.";
    } elseif ($_SESSION['otp_expires'] < time()) {
        $otpError = "OTP has expired. Please request a new one.";
    } else {
        $_SESSION['reset_email'] = $email;
        $_SESSION['otp_verified'] = true;
        header("Location: security_questions.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace - Verify OTP</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: 
                linear-gradient(rgba(93, 78, 55, 0.85), rgba(139, 105, 20, 0.85)),
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect fill="%23faf8f5" width="100" height="100"/><path fill="%23f0ebe3" d="M0 0h100v100H0z" opacity=".5"/><path fill="none" stroke="%23e0d5c7" stroke-width="0.5" d="M0 0l100 100M100 0L0 100" opacity=".3"/></svg>'),
                linear-gradient(135deg, #d4c4b0 0%, #a89880 100%);
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
        }

        .container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            box-shadow: 
                0 20px 60px rgba(93, 78, 55, 0.3),
                0 0 0 1px rgba(139, 105, 20, 0.1);
            padding: 25px;
            width: 100%;
            max-width: 420px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            text-align: center;
        }

        /* Wood grain top border */
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, 
                #8b6914 0%, 
                #d4af37 25%, 
                #8b6914 50%, 
                #d4af37 75%, 
                #8b6914 100%
            );
            border-radius: 16px 16px 0 0;
        }

        /* Step Indicator */
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-top: 10px;
        }

        .step {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f0ebe3;
            border: 2px solid #d4c4b0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #a89880;
            font-weight: 600;
            transition: all 0.3s;
            font-family: 'Playfair Display', serif;
        }

        .step.active {
            background: #8b6914;
            border-color: #8b6914;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(139, 105, 20, 0.3);
        }

        .step.completed {
            background: #5d4e37;
            border-color: #5d4e37;
            color: white;
        }

        /* Logo Section */
        .logo-icon {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, #5d4e37 0%, #8b7355 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 
                0 6px 20px rgba(93, 78, 55, 0.3),
                inset 0 2px 4px rgba(255,255,255,0.3);
            border: 3px solid #f5f2ed;
        }

        .logo-icon i {
            font-size: 30px;
            color: white;
        }

        h2 {
            color: #5d4e37;
            font-size: 26px;
            font-weight: 700;
            font-family: 'Playfair Display', serif;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }

        .subtitle {
            color: #8b7355;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .email-display {
            background: #f5f2ed;
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 13px;
            color: #5d4e37;
            font-weight: 500;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #e0d5c7;
        }

        .email-display i {
            color: #8b6914;
        }

        /* Timer Box */
        .timer-box {
            background: linear-gradient(135deg, #fff8e6 0%, #fff3cd 100%);
            border: 2px solid #d4af37;
            color: #8b6914;
            padding: 12px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 18px;
            font-family: 'Courier New', monospace;
            box-shadow: 0 4px 10px rgba(139, 105, 20, 0.1);
        }

        .timer-box.expired {
            background: #fee;
            border-color: #c0392b;
            color: #c0392b;
        }

        .timer-box i {
            color: #d4af37;
            font-size: 20px;
        }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fee;
            color: #c44;
            border-left: 3px solid #c44;
            text-align: left;
        }

        /* OTP Input */
        .otp-input {
            width: 100%;
            padding: 18px;
            font-size: 28px;
            letter-spacing: 12px;
            text-align: center;
            border: 2px solid #e0d5c7;
            border-radius: 12px;
            background: #faf8f5;
            font-weight: 700;
            color: #5d4e37;
            margin-bottom: 18px;
            font-family: 'Courier New', monospace;
            transition: all 0.3s;
        }

        .otp-input:focus {
            outline: none;
            border-color: #8b6914;
            background: white;
            box-shadow: 0 0 0 4px rgba(139, 105, 20, 0.1);
        }

        /* Buttons */
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 12px;
            font-family: 'Poppins', sans-serif;
        }

        .btn-verify {
            background: linear-gradient(135deg, #5d4e37 0%, #8b7355 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(93, 78, 55, 0.3);
        }

        .btn-verify:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(93, 78, 55, 0.4);
        }

        .btn-resend {
            background: transparent;
            color: #8b6914;
            border: 2px solid #d4af37;
        }

        .btn-resend:hover:not(:disabled) {
            background: #d4af37;
            color: white;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
            display: none;
        }

        .btn.loading .spinner { display: inline-block; }
        .btn.loading span { display: none; }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .back-link {
            margin-top: 18px;
            font-size: 13px;
        }

        .back-link a {
            color: #8b7355;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .back-link a:hover {
            color: #5d4e37;
        }

        .trust-badges {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 18px;
            font-size: 11px;
            color: #a89880;
        }

        .badge {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .badge i {
            color: #8b6914;
            font-size: 12px;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Step Indicator -->
    <div class="step-indicator">
        <div class="step completed"><i class="fas fa-check"></i></div>
        <div class="step active">2</div>
        <div class="step">3</div>
        <div class="step">4</div>
    </div>

    <div class="logo-icon">
        <i class="fas fa-shield-alt"></i>
    </div>
    
    <h2>Verify Code</h2>
    <p class="subtitle">Enter the 6-digit verification code</p>

    <div class="email-display">
        <i class="fas fa-envelope"></i>
        <?php echo htmlspecialchars($email); ?>
    </div>

    <?php if (!empty($otpError)): ?>
        <div class="alert">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $otpError; ?>
        </div>
    <?php endif; ?>

    <div class="timer-box" id="timerBox">
        <i class="fas fa-clock"></i>
        <span id="timeLeft">05:00</span>
    </div>

    <form method="post" id="otpForm">
        <input 
            type="text" 
            name="otp" 
            id="otpInput"
            class="otp-input" 
            placeholder="000000" 
            maxlength="6" 
            pattern="\d{6}" 
            inputmode="numeric"
            autocomplete="off"
            required
        >

        <button type="button" class="btn btn-resend" id="resendBtn">
            <i class="fas fa-redo"></i>
            <span>Resend Code</span>
        </button>
    </form>

    <div class="back-link">
        <a href="forgot.php">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="trust-badges">
        <div class="badge"><i class="fas fa-shield-alt"></i> Secure</div>
        <div class="badge"><i class="fas fa-lock"></i> Protected</div>
        <div class="badge"><i class="fas fa-home"></i> Trusted</div>
    </div>
</div>

<script>
    let remaining = <?php echo $remainingSeconds; ?>;
    const timerBox = document.getElementById('timerBox');
    const timeLeft = document.getElementById('timeLeft');
    const otpInput = document.getElementById('otpInput');
    const verifyBtn = document.getElementById('verifyBtn');
    const resendBtn = document.getElementById('resendBtn');

    function updateTimer() {
        if (remaining <= 0) {
            clearInterval(timerInterval);
            timeLeft.textContent = "00:00";
            otpInput.disabled = true;
            verifyBtn.disabled = true;
            timerBox.classList.add('expired');
            return;
        }

        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        timeLeft.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        remaining--;
    }

    const timerInterval = setInterval(updateTimer, 1000);
    updateTimer();

    otpInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length === 6) {
            setTimeout(() => document.getElementById('otpForm').submit(), 200);
        }
    });

    window.addEventListener('load', () => otpInput.focus());

    document.getElementById('otpForm').addEventListener('submit', function(e) {
        if (otpInput.value.length !== 6) {
            e.preventDefault();
            return;
        }
        verifyBtn.classList.add('loading');
        verifyBtn.disabled = true;
    });

    resendBtn.addEventListener('click', function() {
        if (!confirm('Request new code?')) return;
        resendBtn.disabled = true;
        resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        fetch('resend_otp.php?email=' + encodeURIComponent('<?php echo $email; ?>'))
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('New code sent!');
                    location.reload();
                } else {
                    alert(data.error || 'Failed');
                    resendBtn.disabled = false;
                    resendBtn.innerHTML = '<i class="fas fa-redo"></i><span>Resend</span>';
                }
            })
            .catch(() => {
                alert('Error');
                resendBtn.disabled = false;
                resendBtn.innerHTML = '<i class="fas fa-redo"></i><span>Resend</span>';
            });
    });
</script>

</body>
</html>