<?php
session_start();
include 'connection.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize variables
$error = "";
$id_main = "";
$masked_email = "";
$otp_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_main = trim($_POST['id_main']);

    if (empty($id_main)) {
        $error = "Please enter your ID number.";
    } else {
        $stmt = $conn->prepare("SELECT id_main, fname, email, status FROM signinfo WHERE id_main = ?");
        $stmt->bind_param("s", $id_main);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = "No account found with that ID number.";
        } else {
            $user = $result->fetch_assoc();
            $email = $user['email'];
            $fname = $user['fname'];

            if ($user['status'] === 'pending') {
                $error = "Your account is still pending approval. Password reset is not available yet.";
            } elseif ($user['status'] === 'banned') {
                $error = "Your account has been banned. Please contact support for assistance.";
            } elseif ($user['status'] === 'rejected') {
                $error = "Your account registration was rejected. Please contact support.";
            } else {
                // Mask email for display (e.g., p***@gmail.com)
                $email_parts = explode('@', $email);
                $name = $email_parts[0];
                $domain = $email_parts[1];
                $masked_name = substr($name, 0, 1) . str_repeat('*', strlen($name) - 1);
                $masked_email = $masked_name . '@' . $domain;

                // Generate OTP
                $otp = rand(100000, 999999);
                $hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
                $expiry = date("Y-m-d H:i:s", time() + 300);

                // Save OTP to database
                $stmt = $conn->prepare("UPDATE signinfo SET reset_otp=?, otp_expiry=? WHERE id_main=?");
                $stmt->bind_param("sss", $hashedOtp, $expiry, $id_main);

                if ($stmt->execute()) {
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'pathrick20@gmail.com';
                        $mail->Password = 'kyjlgqiwbmglumnx';
                        $mail->SMTPSecure = 'tls';
                        $mail->Port = 587;
                        $mail->setFrom('pathrick20@gmail.com', 'Furniplace');
                        $mail->addAddress($email);
                        $mail->isHTML(true);
                        $mail->Subject = '🔐 Furniplace Password Reset';
                        $mail->Body = "
                        <div style='font-family: Georgia, serif; max-width: 600px; margin: 0 auto; background: #faf8f5; padding: 40px; border-radius: 8px; border: 1px solid #e0d5c7;'>
                            <div style='text-align: center; margin-bottom: 30px;'>
                                <div style='font-size: 48px; margin-bottom: 10px;'>🪑</div>
                                <h2 style='color: #5d4e37; margin: 0; font-family: Georgia, serif;'>Furniplace</h2>
                                <p style='color: #8b7355; margin: 5px 0; font-size: 14px;'>Fine Furniture & Home Decor</p>
                            </div>
                            
                            <div style='background: white; padding: 30px; border-radius: 6px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);'>
                                <h3 style='color: #5d4e37; margin-top: 0;'>Password Reset Request</h3>
                                <p style='color: #666; line-height: 1.6;'>Hello <strong>{$fname}</strong>,</p>
                                <p style='color: #666; line-height: 1.6;'>We received a request to reset your Furniplace account password.</p>
                                
                                <div style='background: #f5f2ed; border-left: 4px solid #8b6914; padding: 20px; margin: 25px 0; text-align: center; border-radius: 4px;'>
                                    <p style='margin: 0 0 10px 0; color: #5d4e37; font-size: 14px;'>Your verification code:</p>
                                    <div style='font-size: 36px; font-weight: bold; color: #8b6914; letter-spacing: 8px; font-family: Courier New, monospace;'>$otp</div>
                                    <p style='margin: 10px 0 0 0; color: #999; font-size: 12px;'>⏰ Expires in 5 minutes</p>
                                </div>
                                
                                <p style='color: #999; font-size: 13px; margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;'>
                                    Didn't request this? Please ignore this email or contact our support team.
                                </p>
                            </div>
                            
                            <div style='text-align: center; margin-top: 20px; color: #aaa; font-size: 12px;'>
                                <p>🏠 Furniplace - Making Houses Homes Since 2024</p>
                            </div>
                        </div>";

                        $mail->send();

                        $_SESSION['reset_email'] = $email;
                        $_SESSION['reset_id'] = $id_main;

                        $otp_sent = true;

                    } catch (Exception $e) {
                        $error = "Failed to send email. Please try again.";
                    }
                } else {
                    $error = "Database error. Please try again.";
                }
            } // end status else
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace - Forgot Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/forget.css">
</head>
<body>

<div class="container">
    <!-- Step Indicator -->
    <div class="step-indicator">
        <div class="step active">1</div>
        <div class="step">2</div>
        <div class="step">3</div>
        <div class="step">4</div>
    </div>

    <div class="logo-section">
        <div class="logo-icon">
            <i class="fas fa-chair"></i>
        </div>
        <h2>Furniplace</h2>
        <p class="subtitle">Password Recovery</p>
    </div>

    <?php if ($otp_sent): ?>
        <!-- Success State -->
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <div class="email-sent-box">
                <i class="fas fa-envelope-open-text"></i>
                <p style="color: #5d4e37; margin-bottom: 8px;">We sent a verification code to:</p>
                <div class="masked-email"><?php echo htmlspecialchars($masked_email); ?></div>
                <p style="color: #8b7355; font-size: 12px; margin-top: 8px;">
                    Check your inbox and enter the 6-digit code
                </p>
            </div>
            
            <a href="verify_otp.php" class="continue-btn">
                <span>Continue</span>
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
    <?php else: ?>
        <!-- Input Form -->
        <?php if (!empty($error)): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="post" id="forgotForm">
            <div class="form-group">
                <label for="id_main">ID Number</label>
                <div class="input-wrapper">
                    <i class="fas fa-id-card input-icon"></i>
                    <input 
                        type="text" 
                        id="id_main" 
                        name="id_main" 
                        value="<?php echo htmlspecialchars($id_main); ?>"
                        placeholder="Enter your ID number" 
                        required 
                        autocomplete="off"
                        autofocus
                    >
                </div>
                <div class="hint">
                    <i class="fas fa-info-circle"></i>
                    <span>Enter the ID number associated with your account</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn">
                <div class="spinner"></div>
                <span>Verify ID & Send Code</span>
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>

        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    <?php endif; ?>

    <div class="trust-badges">
        <div class="badge"><i class="fas fa-shield-alt"></i> Secure</div>
        <div class="badge"><i class="fas fa-lock"></i> Protected</div>
        <div class="badge"><i class="fas fa-home"></i> Trusted</div>
    </div>
</div>

<script>
    <?php if (!$otp_sent): ?>
    document.getElementById('forgotForm').addEventListener('submit', function(e) {
        const id_main = document.getElementById('id_main').value.trim();
        const btn = document.getElementById('submitBtn');
        
        if (!id_main) {
            e.preventDefault();
            return;
        }
        
        btn.classList.add('loading');
        btn.disabled = true;
    });

    window.addEventListener('load', function() {
        document.getElementById('id_main').focus();
    });
    <?php endif; ?>
</script>

</body>
</html>