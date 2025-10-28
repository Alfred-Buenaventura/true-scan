<?php
// Always start the session at the beginning of the script.
require_once 'config.php';

// If the user is already logged in, redirect them to the dashboard.
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Initialize variables to hold messages for the user.
$error = '';
$success = '';
$showOtpForm = false; // This will control which part of the modal is shown.

// =================================================================
// HANDLE POST REQUESTS (Login, Forgot Password, Reset Password)
// =================================================================

// Check if the form was submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Handle User Login ---
    if (isset($_POST['login'])) {
        $username = clean($_POST['username']);
        $password = $_POST['password'];

        if (!empty($username) && !empty($password)) {
            $db = db();
            // Prepare a query to find the user by username or faculty_id.
            $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR faculty_id = ?) AND status = 'active'");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            // Verify user exists and the password is correct.
            if ($user && verifyPass($password, $user['password'])) {
                // Set session variables to log the user in.
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['faculty_id'] = $user['faculty_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];

                logActivity($user['id'], 'Login', 'User logged in successfully.');

                // Redirect user based on whether they need to change their password.
                if ($user['force_password_change']) {
                    header('Location: change_password.php?first_login=1');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                $error = 'Invalid username or password. Please try again.';
            }
        } else {
            $error = 'Please enter both username and password.';
        }
    }

    // --- Handle Forgot Password: Send OTP ---
    if (isset($_POST['send_otp'])) {
        $email = clean($_POST['email']);

        if (!empty($email)) {
            $db = db();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user) {
                // Generate a 6-character OTP and store it in the session.
                $otp = strtoupper(substr(md5(time()), 0, 6));
                $_SESSION['reset_otp'] = $otp;
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_time'] = time(); // OTP valid for 1 hour.

                // In a real application, you would send an email here.
                // sendEmail($email, 'Password Reset OTP', "Your password reset OTP is: $otp.");

                $success = "An OTP has been sent to your email: $otp"; // Display OTP for simplicity
                $showOtpForm = true; // Tell the modal to show the OTP step.
            } else {
                $error = 'No account found with that email address.';
                $showOtpForm = false;
            }
        }
    }

    // --- Handle Password Reset with OTP ---
    if (isset($_POST['reset_password'])) {
        $otp = strtoupper(clean($_POST['otp']));
        $newPass = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'];
        $showOtpForm = true; // Keep the OTP form visible.

        // Check if OTP is valid and not expired.
        if (isset($_SESSION['reset_otp']) && $otp === $_SESSION['reset_otp']) {
            if ((time() - $_SESSION['reset_time']) < 3600) { // 3600 seconds = 1 hour
                if ($newPass === $confirmPass) {
                    if (strlen($newPass) >= 8) {
                        $db = db();
                        $hashedPass = hashPass($newPass);
                        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->bind_param("si", $hashedPass, $_SESSION['reset_user_id']);
                        $stmt->execute();

                        // Clear session variables after successful reset.
                        unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time']);
                        $success = 'Password has been reset successfully. You can now login.';
                        $showOtpForm = false; // Hide modal form.
                    } else {
                        $error = 'Password must be at least 8 characters long.';
                    }
                } else {
                    $error = 'The new passwords do not match.';
                }
            } else {
                $error = 'The OTP has expired. Please request a new one.';
            }
        } else {
            $error = 'The OTP you entered is invalid.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BPC Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=1.1">
</head>
<body class="login-page">
    
    <div class="card login-card-modern">
        
        <div class="card-header login-header-modern">
            <div class="logo-circle">
                <svg class="fingerprint-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M12 11v.01M12 14v.01M12 17v.01M15 8.5c0-1.933-1.567-3.5-3.5-3.5S8 6.567 8 8.5V12c0 1.933 1.567 3.5 3.5 3.5S15 13.933 15 12V8.5z"/>
                </svg>
            </div>
            <h1>BPC Attendance</h1>
            <p>Staff Attendance Monitoring System</p>
        </div>

        <div class="card-body login-body-modern">
            <h2>Welcome Back</h2>
            <p class="subtitle">Please login to your account</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Username or Faculty ID</label>
                    <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="password-input">
                        <input type="password" name="password" id="passwordField" class="form-control" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">
                            <i id="eyeIcon" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" onclick="showForgotModal()" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" name="login" class="btn btn-primary btn-full-width">Login</button>
            </form>

            <div class="login-footer-modern">
                <p>Bulacan Polytechnic College<br>© 2025</p>
            </div>
        </div>
    </div>

    <div id="forgotModal" class="modal">
        <div class="modal-content modal-small">
            <form method="POST">
                <div class="modal-header">
                    <h3>Reset Password</h3>
                    <button type="button" class="modal-close" onclick="closeForgotModal()">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div id="emailStep">
                        <p>Enter your email to receive an OTP</p>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>

                    <div id="otpStep" style="display: none;">
                        <p>Check your email for the OTP.</p>
                        <div class="form-group">
                            <label>Enter OTP</label>
                            <input type="text" name="otp" class="form-control" maxlength="6" placeholder="6-character code">
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeForgotModal()">Cancel</button>
                    <button type="submit" name="send_otp" id="sendOtpBtn" class="btn btn-primary">Send OTP</button>
                    <button type="submit" name="reset_password" id="resetPassBtn" class="btn btn-primary" style="display: none;">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passField = document.getElementById('passwordField');
            const eyeIcon = document.getElementById('eyeIcon');
            const isPassword = passField.type === 'password';
            
            passField.type = isPassword ? 'text' : 'password';
            eyeIcon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        }

        const modal = document.getElementById('forgotModal');
        const emailStep = document.getElementById('emailStep');
        const otpStep = document.getElementById('otpStep');
        const sendOtpBtn = document.getElementById('sendOtpBtn');
        const resetPassBtn = document.getElementById('resetPassBtn');

        function showForgotModal() {
            if (modal) modal.style.display = 'flex';
        }

        function closeForgotModal() {
            if (modal) modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target === modal) {
                closeForgotModal();
            }
        }

        <?php if ($showOtpForm): ?>
            emailStep.style.display = 'none';
            otpStep.style.display = 'block';
            sendOtpBtn.style.display = 'none';
            resetPassBtn.style.display = 'inline-flex';
            showForgotModal();
        <?php endif; ?>
    </script>
</body>
</html>