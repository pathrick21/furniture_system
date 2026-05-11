<?php
include 'connection.php';

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=furniplace;charset=utf8",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>FurniPlace - Register</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../php/script.php?dir=css&file=register.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Lato:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Only keep closeError here since it's for the PHP error box -->
    <script>
        function closeError() {
            var errorMessage = document.querySelector('.error-message');
            if (errorMessage) {
                errorMessage.classList.remove('show-error');
            }
        }
    </script>
</head>
<body>

    <!-- Header -->
    <header id="main-header">
        <h1><i class="fas fa-chair"></i> FURNIPLACE</h1>
        <nav class="nav-links">
            <a href="login.php" class="nav-btn"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a>
            <a href="home_web.php" class="nav-btn"><i class="fas fa-home"></i> <span>Home</span></a>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="Content">
        <div class="form-wrapper">
            
            <!-- Form with your JS validation functions -->
            <form class="SignUpForm" method="post" action="" onsubmit="return validateForm() && validateAge()">
                
                <!-- Section 1: Personal Information -->
                <div class="form-section" id="section-1">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="section-title">
                            <h2>Personal Information</h2>
                            <p>Tell us about yourself</p>
                        </div>
                    </div>

                    <div class="form-grid three-col">
                        <div class="form-group">
                            <label for="id_main">ID Number <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-id-card"></i>
                                <input type="text" id="id_main" name="id_main" placeholder="XXXX-XXXX" 
                                       value="<?php echo isset($_POST['id_main']) ? htmlspecialchars($_POST['id_main']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="fname">First Name <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" id="fname" name="fname" placeholder="First name" 
                                       value="<?php echo isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="lname">Last Name <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" id="lname" name="lname" placeholder="Last name" 
                                       value="<?php echo isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="mi">Middle Initial <span class="optional">Optional</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-font"></i>
                                <input type="text" id="mi" name="mi" placeholder="M.I." maxlength="3"
                                       value="<?php echo isset($_POST['mi']) ? htmlspecialchars($_POST['mi']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Ename">Name Extension <span class="optional">Optional</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-tag"></i>
                                <input type="text" id="Ename" name="Ename" placeholder="Jr., Sr., III" maxlength="4"
                                       onblur="validateRomanNumerals(this)" 
                                       value="<?php echo isset($_POST['Ename']) ? htmlspecialchars($_POST['Ename']) : ''; ?>">
                            </div>
                        </div>

                        <!-- Sex and Birth Date in Personal Info -->
                        <div class="form-row two-col">
                            <div class="form-group">
                                <label for="sex">Sex <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-venus-mars"></i>
                                    <select id="sex" name="sex" required>
                                        <option value="">Select</option>
                                        <option value="Male" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="bd">Birth Date <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fas fa-calendar-alt"></i>
                                    <input type="date" id="bd" name="bd" required 
                                           value="<?php echo isset($_POST['bd']) ? $_POST['bd'] : ''; ?>">
                                </div>
                                <div id="age-error" class="error-text"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Account Details -->
                <div class="form-section" id="section-2">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="section-title">
                            <h2>Account Details</h2>
                            <p>Create your login credentials</p>
                        </div>
                    </div>

                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label for="user">Username <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-user-circle"></i>
                                <input type="text" id="user" name="user" placeholder="Choose username" 
                                       value="<?php echo isset($_POST['user']) ? htmlspecialchars($_POST['user']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" placeholder="your@email.com" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pass">Password <span class="required">*</span></label>
                            <div class="input-wrapper password-wrap">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="pass" name="pass" placeholder="Create password" 
                                       onkeyup="checkPasswordStrength()" required>
                                <button type="button" class="toggle-pass" onclick="togglePassword()">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="password-strength"></div>
                        </div>

                        <div class="form-group">
                            <label for="repass">Confirm Password <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="repass" name="repass" placeholder="Re-enter password" required>
                            </div>
                            <div id="is-valid-confirmpassword"></div>
                        </div>
                    </div>

                    <!-- Show Password Checkbox in Account Details -->
                    <div class="checkbox-wrapper">
                        <label class="custom-checkbox">
                            <input type="checkbox" id="showPassword" onclick="togglePassword()">
                            <span class="checkmark"></span>
                            <span class="label-text">Show Password</span>
                        </label>
                    </div>
                </div>

                <!-- Section 3: Address Information -->
                <div class="form-section" id="section-3">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="section-title">
                            <h2>Address Information</h2>
                            <p>Where can we reach you?</p>
                        </div>
                    </div>

                    <div class="form-grid three-col">
                        <div class="form-group">
                            <label for="Purok">Purok/Street <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-road"></i>
                                <input type="text" id="Purok" name="Purok" placeholder="Purok or Street" 
                                       value="<?php echo isset($_POST['Purok']) ? htmlspecialchars($_POST['Purok']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Barranggay">Barangay <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-map-pin"></i>
                                <input type="text" id="Barranggay" name="Barranggay" placeholder="Barangay" 
                                       value="<?php echo isset($_POST['Barranggay']) ? htmlspecialchars($_POST['Barranggay']) : ''; ?>" required>
                            </div>
                        </div>

                        <!-- Zip Code in Address -->
                        <div class="form-group">
                            <label for="zip">Zip Code <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-mail-bulk"></i>
                                <input type="text" id="zip" name="zip" placeholder="Zip Code" 
                                       value="<?php echo isset($_POST['zip']) ? htmlspecialchars($_POST['zip']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="CM">City/Municipality <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-city"></i>
                                <input type="text" id="CM" name="CM" placeholder="City or Municipality" 
                                       value="<?php echo isset($_POST['CM']) ? htmlspecialchars($_POST['CM']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Province">Province <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-map"></i>
                                <input type="text" id="Province" name="Province" placeholder="Province" 
                                       value="<?php echo isset($_POST['Province']) ? htmlspecialchars($_POST['Province']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Country">Country <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-globe"></i>
                                <input type="text" id="Country" name="Country" placeholder="Country" 
                                       value="<?php echo isset($_POST['Country']) ? htmlspecialchars($_POST['Country']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Security Questions (LAST) -->
                <div class="form-section" id="section-4">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="section-title">
                            <h2>Security Questions</h2>
                            <p>Protect your account</p>
                        </div>
                    </div>

                    <div class="security-container">
                        <div class="security-card">
                            <div class="security-content">
                                <label>Question 1 <span class="required">*</span></label>
                                <select name="sec_q1" required>
                                    <option value="">Select a security question</option>
                                    <option value="What is your mother's maiden name?" <?php echo (isset($_POST['sec_q1']) && $_POST['sec_q1'] == "What is your mother's maiden name?") ? 'selected' : ''; ?>>What is your mother's maiden name?</option>
                                    <option value="What was the name of your first pet?" <?php echo (isset($_POST['sec_q1']) && $_POST['sec_q1'] == "What was the name of your first pet?") ? 'selected' : ''; ?>>What was the name of your first pet?</option>
                                    <option value="What was the make of your first car?" <?php echo (isset($_POST['sec_q1']) && $_POST['sec_q1'] == "What was the make of your first car?") ? 'selected' : ''; ?>>What was the make of your first car?</option>
                                </select>
                                <div class="input-wrapper password-wrap">
                                        <i class="fas fa-key"></i>
                                        <input type="password" name="sec_a1" id="sec_a1" class="security-answer" placeholder="Your answer" required 
                                            value="<?php echo isset($_POST['sec_a1']) ? htmlspecialchars($_POST['sec_a1']) : ''; ?>">
                                        <button type="button" class="toggle-pass" onclick="toggleSecurityAnswer('sec_a1')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                            </div>
                        </div>

                        <div class="security-card">
                            <div class="security-content">
                                <label>Question 2 <span class="required">*</span></label>
                                <select name="sec_q2" required>
                                    <option value="">Select a security question</option>
                                    <option value="What city were you born in?" <?php echo (isset($_POST['sec_q2']) && $_POST['sec_q2'] == "What city were you born in?") ? 'selected' : ''; ?>>What city were you born in?</option>
                                    <option value="What was your childhood nickname?" <?php echo (isset($_POST['sec_q2']) && $_POST['sec_q2'] == "What was your childhood nickname?") ? 'selected' : ''; ?>>What was your childhood nickname?</option>
                                    <option value="What school did you attend in grade 1?" <?php echo (isset($_POST['sec_q2']) && $_POST['sec_q2'] == "What school did you attend in grade 1?") ? 'selected' : ''; ?>>What school did you attend in grade 1?</option>
                                </select>
                                <div class="input-wrapper password-wrap">
                                        <i class="fas fa-key"></i>
                                        <input type="password" name="sec_a2" id="sec_a2" class="security-answer" placeholder="Your answer" required 
                                            value="<?php echo isset($_POST['sec_a2']) ? htmlspecialchars($_POST['sec_a2']) : ''; ?>">
                                        <button type="button" class="toggle-pass" onclick="toggleSecurityAnswer('sec_a2')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                            </div>
                        </div>

                        <div class="security-card">
                            <div class="security-content">
                                <label>Question 3 <span class="required">*</span></label>
                                <select name="sec_q3" required>
                                    <option value="">Select a security question</option>
                                    <option value="What is your favorite color?" <?php echo (isset($_POST['sec_q3']) && $_POST['sec_q3'] == "What is your favorite color?") ? 'selected' : ''; ?>>What is your favorite color?</option>
                                    <option value="What is your favorite food?" <?php echo (isset($_POST['sec_q3']) && $_POST['sec_q3'] == "What is your favorite food?") ? 'selected' : ''; ?>>What is your favorite food?</option>
                                    <option value="What is the name of your favorite teacher?" <?php echo (isset($_POST['sec_q3']) && $_POST['sec_q3'] == "What is the name of your favorite teacher?") ? 'selected' : ''; ?>>What is the name of your favorite teacher?</option>
                                </select>
                                <div class="input-wrapper password-wrap">
                                    <i class="fas fa-key"></i>
                                    <input type="password" name="sec_a3" id="sec_a3" class="security-answer" placeholder="Your answer" required 
                                        value="<?php echo isset($_POST['sec_a3']) ? htmlspecialchars($_POST['sec_a3']) : ''; ?>">
                                    <button type="button" class="toggle-pass" onclick="toggleSecurityAnswer('sec_a3')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Section -->
                <div class="form-section submit-section">
                    <div class="submit-wrapper">
                        <button type="submit" name="sub" class="submit-btn">
                            <span class="btn-text">
                                <i class="fas fa-user-plus"></i>
                                Create Account
                            </span>
                            <span class="btn-shine"></span>
                        </button>
                        <p class="submit-note">By registering, you agree to our Terms & Conditions</p>
                    </div>

                    <div class="login-redirect">
                        <p>Already have an account? <a href="login.php"><i class="fas fa-arrow-right"></i> Sign in here</a></p>
                    </div>
                </div>

            </form>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; 2024 FurniPlace. All rights reserved.</p>
            <p class="tagline">Crafting comfort for your home <i class="fas fa-heart"></i></p>
        </div>
    </footer>

    <?php
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['sub'])) {
        $id_main = trim($_POST['id_main']);
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $mi = trim($_POST['mi']);
        $Ename = trim($_POST['Ename']);
        $username = trim($_POST['user']);
        $email = trim($_POST['email']);
        $pass = $_POST['pass'];
        $zip = trim($_POST['zip']);
        $Purok = trim($_POST['Purok']);
        $Barranggay = trim($_POST['Barranggay']);
        $CM = trim($_POST['CM']);
        $Province = trim($_POST['Province']);
        $Country = trim($_POST['Country']);
        $sex = $_POST['sex'];
        $bd = $_POST['bd'];
        $sec_q1 = trim($_POST['sec_q1']);
        $sec_a1 = trim($_POST['sec_a1']);
        $sec_q2 = trim($_POST['sec_q2']);
        $sec_a2 = trim($_POST['sec_a2']);
        $sec_q3 = trim($_POST['sec_q3']);
        $sec_a3 = trim($_POST['sec_a3']);

        $hashed_a1 = password_hash(strtolower($sec_a1), PASSWORD_DEFAULT);
        $hashed_a2 = password_hash(strtolower($sec_a2), PASSWORD_DEFAULT);
        $hashed_a3 = password_hash(strtolower($sec_a3), PASSWORD_DEFAULT);

        if (empty($id_main) || empty($username) || empty($email) || empty($pass)) {
            echo errorBox("Please fill in all required fields");
            exit;
        }

        $stmt = $pdo->prepare("SELECT 1 FROM signinfo WHERE id_main = :id");
        $stmt->execute([':id' => $id_main]);
        if ($stmt->fetch()) {
            echo errorBox("ID Number already registered");
            exit;
        }

        $stmt = $pdo->prepare("SELECT 1 FROM signinfo WHERE username = :u");
        $stmt->execute([':u' => $username]);
        if ($stmt->fetch()) {
            echo errorBox("Username already taken");
            exit;
        }

        $stmt = $pdo->prepare("SELECT 1 FROM signinfo WHERE email = :e");
        $stmt->execute([':e' => $email]);
        if ($stmt->fetch()) {
            echo errorBox("Email already registered");
            exit;
        }

        $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);

        $sql = "INSERT INTO signinfo (
            id_main, fname, lname, mi, Ename, username, password, email,
            zip, Purok, Barranggay, CM, Province, Country, sex, bd,
            sec_q1, sec_a1, sec_q2, sec_a2, sec_q3, sec_a3, account_type, status
        ) VALUES (
            :id_main, :fname, :lname, :mi, :Ename, :username, :password, :email,
            :zip, :Purok, :Barranggay, :CM, :Province, :Country, :sex, :bd,
            :sec_q1, :sec_a1, :sec_q2, :sec_a2, :sec_q3, :sec_a3, 'user', 'pending'
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_main' => $id_main, ':fname' => $fname, ':lname' => $lname,
            ':mi' => $mi, ':Ename' => $Ename, ':username' => $username,
            ':password' => $hashedPassword, ':email' => $email, ':zip' => $zip,
            ':Purok' => $Purok, ':Barranggay' => $Barranggay, ':CM' => $CM,
            ':Province' => $Province, ':Country' => $Country, ':sex' => $sex,
            ':bd' => $bd, ':sec_q1' => $sec_q1, ':sec_a1' => $hashed_a1,
            ':sec_q2' => $sec_q2, ':sec_a2' => $hashed_a2,
            ':sec_q3' => $sec_q3, ':sec_a3' => $hashed_a3
        ]);

        echo "<script>alert('Registration successful! Your account is pending approval from the administrator. You will be notified once approved.'); window.location='login.php';</script>";
    }

    function errorBox($msg) {
        return '<div class="error-message show-error">
            <div class="error-content">
                <div class="error-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="error-text">
                    <strong>Registration Error</strong>
                    <p>'.$msg.'</p>
                </div>
                <button class="close-btn" onclick="closeError()"><i class="fas fa-times"></i></button>
            </div>
        </div>';
    }
    ?>

</body>
<script src="../script/script.js"></script>
</html>