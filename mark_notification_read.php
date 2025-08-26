<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get notification ID
$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

if (!$notification_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Notification ID is required']);
    exit();
}

// Verify the notification belongs to the current user
$user_id = $_SESSION['user_id'];
$verify_query = "SELECT id FROM notifications WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Notification not found or access denied']);
    exit();
}

// Mark notification as read
$update_query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to mark notification as read']);
}
?> 