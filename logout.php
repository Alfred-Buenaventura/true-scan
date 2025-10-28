<?php
// logout.php - Simple logout script
require_once 'config.php';

if (isLoggedIn()) {
    $db = db();
    logActivity($_SESSION['user_id'], 'Logout', 'User logged out');
}

// Clear session
session_unset();
session_destroy();

// Redirect to login
header('Location: login.php?logout=1');
exit;
?>
