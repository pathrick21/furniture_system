<?php
header('Content-Type: application/json');
session_start();

include 'connection.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_GET['email'])) {
    echo json_encode(['success' => false, 'error' => 'No email provided']);
    exit;
}

$email = $_GET['email'];

// ✅ Check if email exists (NO id column)
$stmt = $conn->prepare("SELECT 1 FROM signinfo WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Email not found']);
    exit;
}

// Generate OTP
$otp = rand(100000, 999999);
$hashedOtp = password_hash($otp, PASSWORD_DEFAULT);
$expiry = date("Y-m-d H:i:s", time() + 300);

// Save OTP
$stmt = $conn->prepare(
    "UPDATE signinfo SET reset_otp = ?, otp_expiry = ? WHERE email = ?"
);
$stmt->bind_param("sss", $hashedOtp, $expiry, $email);
$stmt->execute();

// Send email
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
    $mail->Subject = 'Password Reset OTP';
    $mail->Body = "Your OTP is <b>$otp</b>. It will expire in 5 minutes.";

    $mail->send();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $mail->ErrorInfo
    ]);
}
