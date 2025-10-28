<?php
require_once 'config.php';
requireLogin();

$db = db();
$error = '';
$success = '';
$firstLogin = isset($_GET['first_login']);

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPass = $_POST['current_password'];
    $newPass = $_POST['new_password'];
    $confirmPass = $_POST['confirm_password'];
    
    // Get current user
    $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!verifyPass($currentPass, $user['password'])) {
        $error = 'Current password is incorrect';
    } elseif ($newPass !== $confirmPass) {
        $error = 'New passwords do not match';
    } elseif (strlen($newPass) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        $hashedPass = hashPass($newPass);
        $stmt = $db->prepare("UPDATE users SET password=?, force_password_change=0 WHERE id=?");
        $stmt->bind_param("si", $hashedPass, $_SESSION['user_id']);
        $stmt->execute();
        
        logActivity($_SESSION['user_id'], 'Password Changed', 'User changed their password');
        $success = 'Password changed successfully!';
        
        if ($firstLogin) {
            header('Location: index.php');
            exit;
        }
    }
}

$pageTitle = 'Change Password';
$pageSubtitle = $firstLogin ? 'Please change your default password' : 'Update your account password';
include 'includes/header.php';
?>

<div class="main-body">
    <?php if ($firstLogin): ?>
        <div class="alert" style="background: #DBEAFE; border: 1px solid #93C5FD; color: #1E40AF;">
            <strong>First Time Login:</strong> For security reasons, you must change your password before continuing.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header">
            <h3>Change Your Password</h3>
            <p>Enter your current password and choose a new one</p>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                    <p style="font-size: 12px; color: var(--gray-600); margin-top: 4px;">
                        Must be at least 8 characters long
                    </p>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">Change Password</button>
                    <?php if (!$firstLogin): ?>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="max-width: 600px; margin: 24px auto 0;">
        <div class="card-body">
            <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 12px;">Password Requirements:</h4>
            <ul style="list-style: none; padding: 0; color: var(--gray-600); font-size: 14px;">
                <li style="margin-bottom: 8px;">✓ At least 8 characters long</li>
                <li style="margin-bottom: 8px;">✓ Mix of letters and numbers recommended</li>
                <li style="margin-bottom: 8px;">✓ Avoid using common words or personal information</li>
                <li>✓ Don't reuse passwords from other accounts</li>
            </ul>
        </div>
    </div>
</div>

<script src="js/main.js"></script>
<?php include 'includes/footer.php'; ?>
