<?php
// api/get_user.php - Simple API to get user data
require_once '../config.php';
requireAdmin();

if (!isset($_GET['id'])) {
    jsonResponse(false, 'User ID required');
}

$userId = (int)$_GET['id'];
$db = db();

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    jsonResponse(false, 'User not found');
}

// Don't send password
unset($user['password']);

jsonResponse(true, 'User retrieved', $user);
?>
