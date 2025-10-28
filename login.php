<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = clean($_POST['username']);
    $password = $_POST['password'];
    
    if ($username && $password) {
        $db = db();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR faculty_id = ?) AND status = 'active'");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
        die("DEBUG: No user found with username or faculty ID '$username'");
        }

        if (!verifyPass($password, $user['password'])) {
        die("DEBUG: Password mismatch. Entered: $password<br>Stored hash: {$user['password']}");
        }
        
        if ($user && verifyPass($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['faculty_id'] = $user['faculty_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            
            logActivity($user['id'], 'Login', 'User logged in');
            
            if ($user['force_password_change']) {
                header('Location: change_password.php?first_login=1');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}

// Handle forgot password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $email = clean($_POST['email']);
    
    if ($email) {
        $db = db();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user) {
            $otp = strtoupper(substr(md5(time()), 0, 6));
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_time'] = time();
            
            $message = "Your password reset OTP is: $otp. Valid for 1 hour.";
            sendEmail($email, 'Password Reset OTP', $message);
            
            $success = 'OTP sent to your email';
        } else {
            $error = 'Email not found';
        }
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $otp = strtoupper(clean($_POST['otp']));
    $newPass = $_POST['new_password'];
    $confirmPass = $_POST['confirm_password'];
    
    if (isset($_SESSION['reset_otp']) && $otp === $_SESSION['reset_otp']) {
        if ((time() - $_SESSION['reset_time']) < 3600) { // 1 hour
            if ($newPass === $confirmPass) {
                if (strlen($newPass) >= 8) {
                    $db = db();
                    $hashedPass = hashPass($newPass);
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashedPass, $_SESSION['reset_user_id']);
                    $stmt->execute();
                    
                    unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_time']);
                    $success = 'Password reset successfully. You can now login.';
                } else {
                    $error = 'Password must be at least 8 characters';
                }
            } else {
                $error = 'Passwords do not match';
            }
        } else {
            $error = 'OTP expired';
        }
    } else {
        $error = 'Invalid OTP';
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BPC Attendance</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-circle">
                    <svg class="fingerprint-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 11v.01M12 14v.01M12 17v.01M15 8.5c0-1.933-1.567-3.5-3.5-3.5S8 6.567 8 8.5V12c0 1.933 1.567 3.5 3.5 3.5S15 13.933 15 12V8.5z"/>
                    </svg>
                </div>
                <h1>BPC Attendance</h1>
                <p>Staff Attendance Monitoring System</p>
            </div>

            <div class="login-body">
                <h2>Welcome Back</h2>
                <p class="subtitle">Please login to your account</p>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= $error ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Username or Faculty ID</label>
                        <input type="text" name="username" placeholder="Username" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="password-input">
                            <input type="password" name="password" placeholder="Password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
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

                    <button type="submit" name="login" class="btn btn-primary btn-block">Login</button>
                </form>

                <div class="login-footer">
                    <p>Bulacan Polytechnic College<br>© 2025</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotModal" class="modal">
        <div class="modal-content">
            <h3>Reset Password</h3>
            <p id="modalSubtitle">Enter your email to receive OTP</p>

            <form method="POST">
                <div id="emailStep">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <button type="submit" name="send_otp" class="btn btn-primary btn-block">Send OTP</button>
                </div>

                <div id="otpStep" style="display: none;">
                    <div class="form-group">
                        <label>Enter OTP</label>
                        <input type="text" name="otp" class="form-control" maxlength="6">
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control">
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary btn-block">Reset Password</button>
                </div>

                <button type="button" class="btn btn-secondary btn-block" onclick="closeForgotModal()">Back to Login</button>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            const pass = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pass.type === 'password') {
                pass.type = 'text';
                icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                pass.type = 'password';
                icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        }

        function showForgotModal() {
            document.getElementById('forgotModal').classList.add('active');
        }

        function closeForgotModal() {
            document.getElementById('forgotModal').classList.remove('active');
        }

        window.onclick = function(e) {
            const modal = document.getElementById('forgotModal');
            if (e.target === modal) closeForgotModal();
        }

        <?php if (isset($_POST['send_otp']) && !$error): ?>
        document.getElementById('emailStep').style.display = 'none';
        document.getElementById('otpStep').style.display = 'block';
        document.getElementById('modalSubtitle').textContent = 'Check your email for OTP';
        showForgotModal();
        <?php endif; ?>
    </script>
</body>
</html>