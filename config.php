<?php
// Simplified config.php
session_start();

// Database settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bpc_attendance');

// Timezone
date_default_timezone_set('Asia/Manila');

// Database connection
function db() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
    }
    return $conn;
}

// Clean input
function clean($data) {
    return htmlspecialchars(trim($data));
}

// Check if logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Require admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        die('Access denied');
    }
}

// Hash password
function hashPass($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPass($password, $hash) {
    return password_verify($password, $hash);
}

// Log activity
function logActivity($userId, $action, $details = '') {
    $db = db();
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $action, $details, $ip);
    $stmt->execute();
}

// Get user by ID
function getUser($userId) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Send email (simple version)
function sendEmail($to, $subject, $message) {
    $headers = "From: noreply@bpc.edu.ph\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $message, $headers);
}

// JSON response
function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    if ($data) $response['data'] = $data;
    echo json_encode($response);
    exit;
}
?>
