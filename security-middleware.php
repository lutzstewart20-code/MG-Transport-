<?php
/**
 * Security Middleware for Admin Pages
 * Ensures proper authentication and security for all admin functions
 */

define('SECURE_ACCESS', true);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

$security = Security::getInstance();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $security->logSecurityEvent('Unauthorized Access Attempt', 'Admin page accessed without login', 'WARNING');
    header('Location: ../secure-login.php');
    exit;
}

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $security->logSecurityEvent('Unauthorized Access Attempt', 
        "User ID: {$_SESSION['user_id']}, Role: {$_SESSION['user_role']}", 'WARNING');
    header('Location: ../secure-login.php?error=access_denied');
    exit;
}

// Check session timeout (30 minutes)
$session_timeout = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $session_timeout) {
    $security->logSecurityEvent('Session Timeout', 
        "User ID: {$_SESSION['user_id']}, Email: {$_SESSION['user_email']}", 'INFO');
    session_destroy();
    header('Location: ../secure-login.php?error=timeout');
    exit;
}

// Update last activity
$_SESSION['last_activity'] = time();

// Check for suspicious activity
if ($security->detectSuspiciousActivity()) {
    $security->logSecurityEvent('Suspicious Activity - Admin Access', 
        "User ID: {$_SESSION['user_id']}, IP: {$_SERVER['REMOTE_ADDR']}", 'CRITICAL');
    session_destroy();
    header('Location: ../secure-login.php?error=security');
    exit;
}

// Log admin page access
$current_page = basename($_SERVER['PHP_SELF']);
$security->logSecurityEvent('Admin Page Access', 
    "User: {$_SESSION['user_email']}, Page: {$current_page}", 'INFO');

// Set secure headers for admin pages
header("X-Admin-Access: true");
header("X-User-Role: admin");

// Function to verify CSRF token for forms
function verifyAdminCSRF() {
    global $security;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
            $security->logSecurityEvent('CSRF Token Mismatch - Admin', 
                "User: {$_SESSION['user_email']}, Page: " . basename($_SERVER['PHP_SELF']), 'WARNING');
            return false;
        }
    }
    
    return true;
}

// Function to get CSRF token for forms
function getAdminCSRFToken() {
    global $security;
    return $security->getCSRFToken();
}

// Function to sanitize admin input
function sanitizeAdminInput($data, $type = 'string') {
    global $security;
    return $security->sanitizeInput($data, $type);
}

// Function to check admin permissions
function checkAdminPermission($permission) {
    // Add permission checking logic here
    // For now, all admins have full access
    return true;
}

// Function to log admin actions
function logAdminAction($action, $details = '') {
    global $security;
    $security->logSecurityEvent("Admin Action: {$action}", 
        "User: {$_SESSION['user_email']}, Details: {$details}", 'INFO');
}

// Function to validate admin file uploads
function validateAdminFileUpload($file, $allowed_types = ['image', 'document']) {
    require_once '../includes/secure-upload.php';
    
    $uploader = new SecureUpload('uploads/admin/', 10485760); // 10MB limit
    return $uploader->uploadFile($file, $allowed_types[0]);
}

// Function to check if admin account is compromised
function checkAdminAccountSecurity() {
    global $conn, $security;
    
    // Check for multiple failed login attempts
    $stmt = mysqli_prepare($conn, "SELECT failed_attempts, last_login_attempt FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        if ($user['failed_attempts'] > 10) {
            $security->logSecurityEvent('Admin Account Compromised', 
                "User ID: {$_SESSION['user_id']}, Failed attempts: {$user['failed_attempts']}", 'CRITICAL');
            return false;
        }
    }
    
    return true;
}

// Check account security on each page load
if (!checkAdminAccountSecurity()) {
    session_destroy();
    header('Location: ../secure-login.php?error=account_locked');
    exit;
}

// Function to generate secure admin tokens
function generateAdminToken($purpose = 'general', $expiry = 3600) {
    $token_data = [
        'user_id' => $_SESSION['user_id'],
        'purpose' => $purpose,
        'expiry' => time() + $expiry,
        'random' => bin2hex(random_bytes(16))
    ];
    
    $token = base64_encode(json_encode($token_data));
    $signature = hash_hmac('sha256', $token, $_SESSION['csrf_token']);
    
    return $token . '.' . $signature;
}

// Function to verify admin token
function verifyAdminToken($token, $purpose = 'general') {
    if (strpos($token, '.') === false) {
        return false;
    }
    
    list($token_data, $signature) = explode('.', $token, 2);
    
    // Verify signature
    $expected_signature = hash_hmac('sha256', $token_data, $_SESSION['csrf_token']);
    if (!hash_equals($expected_signature, $signature)) {
        return false;
    }
    
    // Decode token data
    $data = json_decode(base64_decode($token_data), true);
    if (!$data) {
        return false;
    }
    
    // Check expiry
    if ($data['expiry'] < time()) {
        return false;
    }
    
    // Check user ID
    if ($data['user_id'] != $_SESSION['user_id']) {
        return false;
    }
    
    // Check purpose
    if ($data['purpose'] !== $purpose) {
        return false;
    }
    
    return true;
}

// Function to clean up old admin sessions
function cleanupOldAdminSessions() {
    global $conn;
    
    $stmt = mysqli_prepare($conn, "DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    mysqli_stmt_execute($stmt);
}

// Clean up old sessions periodically (once per hour)
if (!isset($_SESSION['last_cleanup']) || (time() - $_SESSION['last_cleanup']) > 3600) {
    cleanupOldAdminSessions();
    $_SESSION['last_cleanup'] = time();
}

// Set admin-specific security headers
header("X-Admin-User: " . $_SESSION['user_email']);
header("X-Admin-Session: " . session_id());

// Log successful admin authentication
if (!isset($_SESSION['admin_authenticated'])) {
    $security->logSecurityEvent('Admin Authentication Success', 
        "User: {$_SESSION['user_email']}, IP: {$_SERVER['REMOTE_ADDR']}", 'INFO');
    $_SESSION['admin_authenticated'] = true;
}
?>
