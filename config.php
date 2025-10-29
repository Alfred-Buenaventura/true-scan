<?php
// Start or resume a session
session_start();

// Database connection constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bpc_attendance');

// Set the default timezone for date/time functions
date_default_timezone_set('Asia/Manila');

// Function to get the database connection (uses a static variable to prevent multiple connections)
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

// Function to clean user input to prevent XSS attacks
function clean($data) {
    return htmlspecialchars(trim($data));
}

// Function to check if a user is currently logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if the logged-in user is an Admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
}

// Function to require a user to be logged in to access a page
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Function to require a user to be an Admin to access a page
function requireAdmin() {
    requireLogin(); // First, ensure they are logged in
    if (!isAdmin()) {
        die('Access denied'); // Stop the script if they are not an admin
    }
}

// Function to hash a password using the default, secure PHP method
function hashPass($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Function to verify a submitted password against a saved hash
function verifyPass($password, $hash) {
    return password_verify($password, $hash);
}

// Function to log an activity to the `activity_logs` table
function logActivity($userId, $action, $details = '') {
    $db = db();
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $action, $details, $ip);
    $stmt->execute();
}

// Function to get a single user's details by their ID
function getUser($userId) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Function to send an email (placeholder for a real email service)
function sendEmail($to, $subject, $message) {
    $headers = "From: noreply@bpc.edu.ph\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $message, $headers);
}

// Function to return a standardized JSON response for API calls
function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    if ($data) $response['data'] = $data;
    echo json_encode($response);
    exit;
}
?>