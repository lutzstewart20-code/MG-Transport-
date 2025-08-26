<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Log the logout activity if user is logged in and has valid user_id
if (isLoggedIn() && isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'User Logout', 'User logged out successfully', $conn);
}

// Destroy the session
session_destroy();

// Redirect to home page
header("Location: index.php");
exit();
?> 