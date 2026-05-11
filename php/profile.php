<?php
session_start();
include 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['user'];
$update_msg = "";
$error_msg = "";

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM signinfo WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: login.php");
    exit();
}

// Handle Profile Picture Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture'])) {
    $target_dir = "../uploads/profile_pics/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $file = $_FILES['profile_pic'];
    $file_name = basename($file['name']);
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    if ($file_error === 0) {
        if (in_array($file_ext, $allowed)) {
            if ($file_size <= 2097152) {
                $new_file_name = uniqid('profile_', true) . '.' . $file_ext;
                $target_file = $target_dir . $new_file_name;
                if (move_uploaded_file($file_tmp, $target_file)) {
                    if (!empty($user['profile_pic']) && file_exists($user['profile_pic'])) {
                        unlink($user['profile_pic']);
                    }
                    $update_pic = $conn->prepare("UPDATE signinfo SET profile_pic = ? WHERE username = ?");
                    $update_pic->bind_param("ss", $target_file, $username);
                    if ($update_pic->execute()) {
                        $update_msg = "Profile picture updated successfully!";
                        $stmt->execute();
                        $user = $stmt->get_result()->fetch_assoc();
                    } else {
                        $error_msg = "Failed to save picture to database";
                    }
                } else {
                    $error_msg = "Failed to upload file";
                }
            } else {
                $error_msg = "File too large (max 2MB)";
            }
        } else {
            $error_msg = "Invalid file type (JPG, PNG, GIF only)";
        }
    } else {
        $error_msg = "Error uploading file";
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $mi = trim($_POST['mi']);
    $Ename = trim($_POST['Ename']);
    $email = trim($_POST['email']);
    $Purok = trim($_POST['Purok']);
    $Barranggay = trim($_POST['Barranggay']);
    $CM = trim($_POST['CM']);
    $Province = trim($_POST['Province']);
    $Country = trim($_POST['Country']);
    $zip = trim($_POST['zip']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Invalid email format";
    } else {
        $check_stmt = $conn->prepare("SELECT id_main FROM signinfo WHERE email = ? AND username != ?");
        $check_stmt->bind_param("ss", $email, $username);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_msg = "Email is already used by another account";
        } else {
            $update_stmt = $conn->prepare("UPDATE signinfo SET fname=?, lname=?, mi=?, Ename=?, email=?, Purok=?, Barranggay=?, CM=?, Province=?, Country=?, zip=? WHERE username=?");
            $update_stmt->bind_param("ssssssssssss", $fname, $lname, $mi, $Ename, $email, $Purok, $Barranggay, $CM, $Province, $Country, $zip, $username);
            if ($update_stmt->execute()) {
                $update_msg = "Profile updated successfully!";
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            } else {
                $error_msg = "Failed to update profile";
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    if (!password_verify($current_pass, $user['password'])) {
        $error_msg = "Current password is incorrect";
    } elseif (strlen($new_pass) < 6) {
        $error_msg = "New password must be at least 6 characters";
    } elseif ($new_pass !== $confirm_pass) {
        $error_msg = "New passwords do not match";
    } else {
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        $pass_stmt = $conn->prepare("UPDATE signinfo SET password=? WHERE username=?");
        $pass_stmt->bind_param("ss", $hashed_pass, $username);
        if ($pass_stmt->execute()) {
            $update_msg = "Password changed successfully!";
        } else {
            $error_msg = "Failed to change password";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_security_questions'])) {
    $sec_q1 = $_POST['sec_q1'];
    $sec_a1 = trim($_POST['sec_a1']);
    $sec_q2 = $_POST['sec_q2'];
    $sec_a2 = trim($_POST['sec_a2']);
    $sec_q3 = $_POST['sec_q3'];
    $sec_a3 = trim($_POST['sec_a3']);

    if (empty($sec_q1) || empty($sec_a1) || empty($sec_q2) || empty($sec_a2) || empty($sec_q3) || empty($sec_a3)) {
        $error_msg = "Please fill in all three security questions and answers.";
    } elseif ($sec_q1 === $sec_q2 || $sec_q1 === $sec_q3 || $sec_q2 === $sec_q3) {
        $error_msg = "Please choose three different security questions.";
    } else {
        // Hash the answers before saving
       $hashed_a1 = !empty($sec_a1) ? password_hash(strtolower($sec_a1), PASSWORD_DEFAULT) : '';
        $hashed_a2 = !empty($sec_a2) ? password_hash(strtolower($sec_a2), PASSWORD_DEFAULT) : '';
        $hashed_a3 = !empty($sec_a3) ? password_hash(strtolower($sec_a3), PASSWORD_DEFAULT) : '';

        $sec_stmt = $conn->prepare("UPDATE signinfo SET sec_q1=?, sec_a1=?, sec_q2=?, sec_a2=?, sec_q3=?, sec_a3=? WHERE username=?");
        $sec_stmt->bind_param("sssssss", $sec_q1, $hashed_a1, $sec_q2, $hashed_a2, $sec_q3, $hashed_a3, $username);
        if ($sec_stmt->execute()) {
            $update_msg = "Security questions updated successfully!";
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } else {
            $error_msg = "Failed to update security questions.";
        }
    }
}

// Check if security questions are set
$has_security_questions = !empty($user['sec_q1']) && !empty($user['sec_a1'])
                       && !empty($user['sec_q2']) && !empty($user['sec_a2'])
                       && !empty($user['sec_q3']) && !empty($user['sec_a3']);

// Get profile picture display
$profile_pic = !empty($user['profile_pic']) && file_exists($user['profile_pic']) 
    ? $user['profile_pic'] 
    : null;

$security_questions_1 = [
    "What is your mother's maiden name?",
    "What was the name of your first pet?",
    "What was the make of your first car?",
];

$security_questions_2 = [
    "What city were you born in?",
    "What was your childhood nickname?",
    "What school did you attend in grade 1?",
];

$security_questions_3 = [
    "What is your favorite color?",
    "What is your favorite food?",
    "What is the name of your favorite teacher?",
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniplace - My Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/profile.css">
    <style>
        /* ── Security Questions section ── */
        .security-section {
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
        }

        /* Prompt banner shown when questions are not yet set */
        .sec-q-banner {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            background: #fff8e7;
            border: 1.5px solid #f0c040;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        .sec-q-banner i { color: #d4a017; font-size: 1.3rem; margin-top: 0.1rem; flex-shrink: 0; }
        .sec-q-banner p { margin: 0; color: #7a5c00; font-size: 0.9rem; line-height: 1.6; }
        .sec-q-banner strong { color: #5a3e00; }

        /* Success banner when questions are already set */
        .sec-q-set-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #e8f8f0;
            border: 1px solid #3ecf8e;
            color: #1a7a4a;
            border-radius: 99px;
            padding: 0.35rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
        }

        .sec-q-form .form-group { margin-bottom: 1.25rem; }
        .sec-q-form label { display: block; font-weight: 500; color: #5d4e37; margin-bottom: 0.35rem; font-size: 0.9rem; }
        .sec-q-form select,
        .sec-q-form input[type="text"] {
            width: 100%;
            padding: 0.65rem 0.9rem;
            border: 1.5px solid #e0d5c7;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.875rem;
            color: #3d2f20;
            background: #faf8f5;
            transition: border-color 0.2s;
        }
        .sec-q-form select:focus,
        .sec-q-form input[type="text"]:focus {
            outline: none;
            border-color: #8B5A2B;
            background: #fff;
        }
        .sec-q-pair {
            background: #faf8f5;
            border: 1px solid #e8e0d5;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }
        .sec-q-pair-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #9b8b78;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        .sec-q-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        @media (max-width: 600px) { .sec-q-row { grid-template-columns: 1fr; } }

        .btn-save-sec {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.75rem;
            background: linear-gradient(135deg, #8B5A2B 0%, #6b3f1a 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s;
            margin-top: 0.5rem;
        }
        .btn-save-sec:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(139,90,43,0.3); }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="header-left">
            <button class="back-btn" onclick="window.location.href='home.php'">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="header-brand">
                <i class="fas fa-chair"></i>
                <span>Furniplace</span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </header>

    <div class="container">
        
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-info">
                <div class="avatar-container">
                    <div class="avatar" id="avatarDisplay">
                        <?php if ($profile_pic): ?>
                            <img src="<?php echo htmlspecialchars($profile_pic); ?>?t=<?php echo time(); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <button class="change-pic-btn" onclick="openUploadModal()" title="Change Profile Picture">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
                <div class="profile-text">
                    <h1><?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></h1>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                    <span class="id-badge">
                        <i class="fas fa-id-card"></i> ID: <?php echo htmlspecialchars($user['id_main']); ?>
                    </span>
                    <?php if (!$has_security_questions): ?>
                    <span style="display:inline-flex; align-items:center; gap:0.4rem; background:#fff3cd; border:1px solid #ffc107; color:#856404; border-radius:99px; padding:0.3rem 0.875rem; font-size:0.75rem; font-weight:600; margin-top:0.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> Security questions not set
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Profile Picture Upload Modal -->
        <div class="upload-modal" id="uploadModal">
            <div class="upload-content">
                <div class="upload-header">
                    <h3><i class="fas fa-camera" style="color: #8B5A2B; margin-right: 0.5rem;"></i>Change Profile Picture</h3>
                </div>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload or drag and drop</p>
                        <span class="file-types">JPG, PNG, GIF (Max 2MB)</span>
                        <input type="file" name="profile_pic" id="fileInput" accept="image/*" onchange="previewFile()">
                    </div>
                    <div class="file-preview" id="filePreview">
                        <img id="previewImg" src="" alt="Preview">
                        <div class="file-info">
                            <div class="file-name" id="fileName"></div>
                            <div class="file-size" id="fileSize"></div>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="clearFile()" style="flex: 0; padding: 0.5rem 1rem;"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="upload-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Cancel</button>
                        <button type="submit" name="upload_picture" class="btn btn-primary"><i class="fas fa-save"></i> Save Picture</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($update_msg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $update_msg; ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Personal Information Form -->
        <div class="form-section">
            <h2 class="section-title"><i class="fas fa-user-edit"></i> Personal Information</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group"><label>ID Number</label><input type="text" value="<?php echo htmlspecialchars($user['id_main']); ?>" readonly class="readonly-field"></div>
                    <div class="form-group"><label>Username</label><input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" readonly class="readonly-field"></div>
                    <div class="form-group"><label>First Name *</label><input type="text" name="fname" value="<?php echo htmlspecialchars($user['fname']); ?>" required></div>
                    <div class="form-group"><label>Last Name *</label><input type="text" name="lname" value="<?php echo htmlspecialchars($user['lname']); ?>" required></div>
                    <div class="form-group"><label>Middle Initial</label><input type="text" name="mi" value="<?php echo htmlspecialchars($user['mi']); ?>" maxlength="10"></div>
                    <div class="form-group"><label>Name Extension</label><input type="text" name="Ename" value="<?php echo htmlspecialchars($user['Ename']); ?>" maxlength="20" placeholder="Jr., Sr., III, etc."></div>
                    <div class="form-group"><label>Email Address *</label><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
                    <div class="form-group"><label>Birth Date</label><input type="date" value="<?php echo htmlspecialchars($user['bd']); ?>" readonly class="readonly-field"></div>
                    <div class="form-group"><label>Sex</label><input type="text" value="<?php echo htmlspecialchars($user['sex']); ?>" readonly class="readonly-field"></div>
                </div>
                <h3 style="margin-top: 2rem; margin-bottom: 1rem; color: #5d4e37; font-family: 'Playfair Display', serif;">
                    <i class="fas fa-map-marker-alt" style="color: #8B5A2B; margin-right: 0.5rem;"></i> Address Information
                </h3>
                <div class="form-grid">
                    <div class="form-group"><label>Purok/Street</label><input type="text" name="Purok" value="<?php echo htmlspecialchars($user['Purok']); ?>"></div>
                    <div class="form-group"><label>Barangay</label><input type="text" name="Barranggay" value="<?php echo htmlspecialchars($user['Barranggay']); ?>"></div>
                    <div class="form-group"><label>City/Municipality</label><input type="text" name="CM" value="<?php echo htmlspecialchars($user['CM']); ?>"></div>
                    <div class="form-group"><label>Province</label><input type="text" name="Province" value="<?php echo htmlspecialchars($user['Province']); ?>"></div>
                    <div class="form-group"><label>Country</label><input type="text" name="Country" value="<?php echo htmlspecialchars($user['Country']); ?>"></div>
                    <div class="form-group"><label>Zip Code</label><input type="text" name="zip" value="<?php echo htmlspecialchars($user['zip']); ?>"></div>
                </div>
                <button type="submit" name="update_profile" class="submit-btn"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>

        <!-- Change Password Section -->
        <div class="form-section password-section">
            <h2 class="section-title"><i class="fas fa-lock"></i> Change Password</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group"><label>Current Password</label><input type="password" name="current_password" placeholder="Enter current password" required></div>
                    <div class="form-group"><label>New Password</label><input type="password" name="new_password" placeholder="At least 6 characters" required minlength="6"></div>
                    <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" placeholder="Re-enter new password" required></div>
                </div>
                <button type="submit" name="change_password" class="submit-btn" style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </form>
        </div>

        <!-- ══════════════════════════════════════════════════ -->
        <!-- SECURITY QUESTIONS SECTION                        -->
        <!-- Only the user can set/update their own questions  -->
        <!-- ══════════════════════════════════════════════════ -->
        <div class="form-section security-section">
            <h2 class="section-title" style="display:flex; align-items:center; gap:0.5rem;">
                <i class="fas fa-shield-alt"></i> Security Questions
            </h2>

            <?php if (!$has_security_questions): ?>
            <!-- Warning prompt if not yet set -->
            <div class="sec-q-banner">
                <i class="fas fa-exclamation-triangle"></i>
                <p>
                    <strong>Action required:</strong> Your account does not have security questions set up yet.
                    Security questions are used to verify your identity if you ever need to recover your account.
                    Please fill in all three questions below to secure your account.
                </p>
            </div>
            <?php else: ?>
            <span class="sec-q-set-badge"><i class="fas fa-check-circle"></i> Security questions are set</span>
            <?php endif; ?>

            <form method="POST" class="sec-q-form" onsubmit="return validateSecurityQuestions()">
                <input type="hidden" name="update_security_questions" value="1">

               <!-- Question 1 -->
<div class="sec-q-pair">
    <div class="sec-q-pair-label"><i class="fas fa-question-circle"></i> Question 1</div>
    <div class="sec-q-row">
        <div class="form-group">
            <label>Question <span style="color:#c0392b;">*</span></label>
            <select name="sec_q1" id="sec_q1" required>
                <option value="">— Select a question —</option>
                <option value="What is your mother's maiden name?" <?php echo ($user['sec_q1'] ?? '') === "What is your mother's maiden name?" ? 'selected' : ''; ?>>What is your mother's maiden name?</option>
                <option value="What was the name of your first pet?" <?php echo ($user['sec_q1'] ?? '') === "What was the name of your first pet?" ? 'selected' : ''; ?>>What was the name of your first pet?</option>
                <option value="What was the make of your first car?" <?php echo ($user['sec_q1'] ?? '') === "What was the make of your first car?" ? 'selected' : ''; ?>>What was the make of your first car?</option>
            </select>
        </div>
        <div class="form-group">
            <label>Your Answer <span style="color:#c0392b;">*</span></label>
            <input type="text" name="sec_a1" placeholder="Enter your answer" required>

        </div>
    </div>
</div>

<!-- Question 2 -->
<div class="sec-q-pair">
    <div class="sec-q-pair-label"><i class="fas fa-question-circle"></i> Question 2</div>
    <div class="sec-q-row">
        <div class="form-group">
            <label>Question <span style="color:#c0392b;">*</span></label>
            <select name="sec_q2" id="sec_q2" required>
                <option value="">— Select a question —</option>
                <option value="What city were you born in?" <?php echo ($user['sec_q2'] ?? '') === "What city were you born in?" ? 'selected' : ''; ?>>What city were you born in?</option>
                <option value="What was your childhood nickname?" <?php echo ($user['sec_q2'] ?? '') === "What was your childhood nickname?" ? 'selected' : ''; ?>>What was your childhood nickname?</option>
                <option value="What school did you attend in grade 1?" <?php echo ($user['sec_q2'] ?? '') === "What school did you attend in grade 1?" ? 'selected' : ''; ?>>What school did you attend in grade 1?</option>
            </select>
        </div>
        <div class="form-group">
            <label>Your Answer <span style="color:#c0392b;">*</span></label>
            <input type="text" name="sec_a2" placeholder="Enter your answer" required>
        </div>
    </div>
</div>

<!-- Question 3 -->
<div class="sec-q-pair">
    <div class="sec-q-pair-label"><i class="fas fa-question-circle"></i> Question 3</div>
    <div class="sec-q-row">
        <div class="form-group">
            <label>Question <span style="color:#c0392b;">*</span></label>
            <select name="sec_q3" id="sec_q3" required>
                <option value="">— Select a question —</option>
                <option value="What is your favorite color?" <?php echo ($user['sec_q3'] ?? '') === "What is your favorite color?" ? 'selected' : ''; ?>>What is your favorite color?</option>
                <option value="What is your favorite food?" <?php echo ($user['sec_q3'] ?? '') === "What is your favorite food?" ? 'selected' : ''; ?>>What is your favorite food?</option>
                <option value="What is the name of your favorite teacher?" <?php echo ($user['sec_q3'] ?? '') === "What is the name of your favorite teacher?" ? 'selected' : ''; ?>>What is the name of your favorite teacher?</option>
            </select>
        </div>
        <div class="form-group">
            <label>Your Answer <span style="color:#c0392b;">*</span></label>
            <input type="text" name="sec_a3" placeholder="Enter your answer" required>
        </div>
    </div>
</div>

                <button type="submit" class="btn-save-sec">
                    <i class="fas fa-shield-alt"></i>
                    <?php echo $has_security_questions ? 'Update Security Questions' : 'Save Security Questions'; ?>
                </button>
            </form>
        </div>
        <!-- ══ END SECURITY QUESTIONS ══ -->

    </div>

    <script>
        // Modal Functions
        function openUploadModal() {
            document.getElementById('uploadModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.remove('active');
            document.body.style.overflow = 'auto';
            clearFile();
        }

        function previewFile() {
            const fileInput = document.getElementById('fileInput');
            const file = fileInput.files[0];
            if (file) {
                if (file.size > 2097152) { alert('File too large! Maximum size is 2MB.'); clearFile(); return; }
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('fileName').textContent = file.name;
                    document.getElementById('fileSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
                    document.getElementById('filePreview').classList.add('active');
                }
                reader.readAsDataURL(file);
            }
        }

        function clearFile() {
            document.getElementById('fileInput').value = '';
            document.getElementById('filePreview').classList.remove('active');
            document.getElementById('previewImg').src = '';
        }

        document.getElementById('uploadModal').addEventListener('click', function(e) {
            if (e.target === this) closeUploadModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeUploadModal();
        });

        const uploadArea = document.querySelector('.upload-area');
        uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.style.borderColor = '#8B5A2B'; uploadArea.style.background = '#fff8e7'; });
        uploadArea.addEventListener('dragleave', (e) => { e.preventDefault(); uploadArea.style.borderColor = '#e0d5c7'; uploadArea.style.background = '#faf8f5'; });
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#e0d5c7'; uploadArea.style.background = '#faf8f5';
            const files = e.dataTransfer.files;
            if (files.length > 0) { document.getElementById('fileInput').files = files; previewFile(); }
        });

        // ── Security questions: prevent duplicate selections ──
        function updateQuestionOptions() {
            const selects = [
                document.getElementById('sec_q1'),
                document.getElementById('sec_q2'),
                document.getElementById('sec_q3')
            ];

            const selected = selects.map(s => s.value);

            selects.forEach((select, idx) => {
                const currentVal = select.value;
                Array.from(select.options).forEach(opt => {
                    if (opt.value === '') return; // keep the placeholder
                    const chosenByOther = selected.some((val, i) => i !== idx && val === opt.value);
                    opt.disabled = chosenByOther;
                });
                // Restore current selection if it got re-enabled
                select.value = currentVal;
            });
        }

        function validateSecurityQuestions() {
            const q1 = document.getElementById('sec_q1').value;
            const q2 = document.getElementById('sec_q2').value;
            const q3 = document.getElementById('sec_q3').value;

            if (!q1 || !q2 || !q3) {
                alert('Please select all three security questions.');
                return false;
            }
            if (q1 === q2 || q1 === q3 || q2 === q3) {
                alert('Please choose three different security questions.');
                return false;
            }
            return true;
        }

        // Run on load to disable already-selected options
        updateQuestionOptions();

        // Ping to stay online
        fetch('ping.php');
        setInterval(function() { fetch('ping.php').catch(() => {}); }, 30000);
        window.addEventListener('beforeunload', function() { navigator.sendBeacon('ping.php?action=offline'); });
    </script>
</body>
</html>