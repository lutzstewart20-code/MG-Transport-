<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Mark all notifications as read
$success = markAllNotificationsAsReadForUser($conn, $_SESSION['user_id']);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to mark notifications as read']);
}
?>
