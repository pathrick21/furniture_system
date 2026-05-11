<?php
include "connection.php";

if (isset($_POST['email'])) {

    $email = $_POST['email'];

    // CHECK IF EMAIL EXISTS
    $stmt = $conn->prepare("SELECT id_main FROM signinfo WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        // GENERATE OTP
        $otp = rand(100000, 999999);
        $expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

        // UPDATE OTP
        $update = $conn->prepare(
            "UPDATE signinfo SET otp_code=?, otp_expiry=? WHERE email=?"
        );
        $update->bind_param("sss", $otp, $expiry, $email);
        $update->execute();

        // SEND EMAIL
        $subject = "Password Reset OTP";
        $message = "Your OTP code is: $otp\n\nThis code expires in 5 minutes.";
        $headers = "From: noreply@yourproject.com";

        mail($email, $subject, $message, $headers);

        header("Location: verify_otp.php?email=" . urlencode($email));
        exit();

    } else {
        echo "Email not found.";
    }
}
?>
<form method="post">
    <input type="text" name="otp" placeholder="Enter OTP" required>
    <button type="submit">Verify</button>
</form>
