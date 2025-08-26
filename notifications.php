<?php
/**
 * Enhanced Notifications Helper Functions
 * Handles creating, displaying, and managing user notifications
 * Extends the basic notification functions in functions.php
 */

/**
 * Create a new enhanced notification for a user with related data
 */
function createEnhancedNotification($conn, $user_id, $title, $message, $type = 'info', $related_id = null, $related_type = null) {
    // First, ensure the table has the required columns
    $check_columns = mysqli_query($conn, "SHOW COLUMNS FROM notifications LIKE 'related_id'");
    if (mysqli_num_rows($check_columns) == 0) {
        // Add the missing columns
        mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN related_id INT NULL");
        mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN related_type VARCHAR(50) NULL");
    }
    
    $query = "INSERT INTO notifications (user_id, title, message, type, related_id, related_type) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isssss", $user_id, $title, $message, $type, $related_id, $related_type);
        if (mysqli_stmt_execute($stmt)) {
            return true;
        }
    }
    
    // Fallback to basic notification if enhanced fails
    $fallback_query = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
    $fallback_stmt = mysqli_prepare($conn, $fallback_query);
    
    if ($fallback_stmt) {
        mysqli_stmt_bind_param($fallback_stmt, "isss", $user_id, $title, $message, $type);
        return mysqli_stmt_execute($fallback_stmt);
    }
    
    return false;
}

/**
 * Get unread notifications for a user
 */
function getUnreadNotificationsList($conn, $user_id, $limit = 10) {
    $query = "SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC LIMIT ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $notifications = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
        
        return $notifications;
    }
    
    return [];
}

/**
 * Get all notifications for a user
 */
function getAllNotificationsList($conn, $user_id, $limit = 20) {
    $query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $notifications = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
        
        return $notifications;
    }
    
    return [];
}

/**
 * Mark a notification as read
 */
function markNotificationAsReadById($conn, $notification_id, $user_id) {
    $query = "UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);
        return mysqli_stmt_execute($stmt);
    }
    
    return false;
}

/**
 * Mark all notifications as read for a user
 */
function markAllNotificationsAsReadForUser($conn, $user_id) {
    $query = "UPDATE notifications SET is_read = TRUE WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        return mysqli_stmt_execute($stmt);
    }
    
    return false;
}

/**
 * Get notification count for a user
 */
function getNotificationCountForUser($conn, $user_id, $unread_only = true) {
    $query = $unread_only 
        ? "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE"
        : "SELECT COUNT(*) as count FROM notifications WHERE user_id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        return $row['count'] ?? 0;
    }
    
    return 0;
}

/**
 * Delete old notifications (older than specified days)
 */
function cleanupOldNotifications($conn, $days = 30) {
    $query = "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $days);
        return mysqli_stmt_execute($stmt);
    }
    
    return false;
}

/**
 * Create booking-related notifications
 */
function createBookingNotification($conn, $user_id, $booking_id, $action = 'created') {
    $messages = [
        'created' => [
            'title' => 'Booking Request Submitted',
            'message' => 'Your vehicle booking request has been submitted successfully and is waiting for admin confirmation.',
            'type' => 'info'
        ],
        'confirmed' => [
            'title' => 'Booking Confirmed',
            'message' => 'Your vehicle booking has been confirmed by admin. You can now proceed with payment.',
            'type' => 'success'
        ],
        'rejected' => [
            'title' => 'Booking Rejected',
            'message' => 'Your vehicle booking request has been rejected. Please contact admin for details.',
            'type' => 'error'
        ],
        'cancelled' => [
            'title' => 'Booking Cancelled',
            'message' => 'Your vehicle booking has been cancelled.',
            'type' => 'warning'
        ]
    ];
    
    if (isset($messages[$action])) {
        $msg = $messages[$action];
        return createEnhancedNotification($conn, $user_id, $msg['title'], $msg['message'], $msg['type'], $booking_id, 'booking');
    }
    
    return false;
}

/**
 * Create payment-related notifications
 */
function createPaymentNotification($conn, $user_id, $booking_id, $action = 'verified') {
    $messages = [
        'verified' => [
            'title' => 'Payment Verified',
            'message' => 'Your payment has been verified and your booking is now confirmed.',
            'type' => 'success'
        ],
        'pending' => [
            'title' => 'Payment Pending',
            'message' => 'Your payment is pending verification. We will notify you once confirmed.',
            'type' => 'info'
        ],
        'failed' => [
            'title' => 'Payment Failed',
            'message' => 'Your payment could not be processed. Please try again or contact support.',
            'type' => 'error'
        ]
    ];
    
    if (isset($messages[$action])) {
        $msg = $messages[$action];
        return createEnhancedNotification($conn, $user_id, $msg['title'], $msg['message'], $msg['type'], $booking_id, 'payment');
    }
    
    return false;
}

/**
 * Create admin notifications for new bookings
 */
function createAdminBookingNotification($conn, $booking_id, $action = 'new_booking') {
    // Get all admin users
    $admin_query = "SELECT id FROM users WHERE role = 'admin'";
    $admin_result = mysqli_query($conn, $admin_query);
    
    if (!$admin_result) {
        return false;
    }
    
    $messages = [
        'new_booking' => [
            'title' => 'New Booking Request',
            'message' => 'A new vehicle booking request has been submitted and requires your review.',
            'type' => 'info'
        ],
        'booking_confirmed' => [
            'title' => 'Booking Confirmed',
            'message' => 'A vehicle booking has been confirmed by admin.',
            'type' => 'success'
        ],
        'booking_rejected' => [
            'title' => 'Booking Rejected',
            'message' => 'A vehicle booking request has been rejected.',
            'type' => 'warning'
        ]
    ];
    
    if (!isset($messages[$action])) {
        return false;
    }
    
    $msg = $messages[$action];
    $success_count = 0;
    
    // Create notification for each admin
    while ($admin = mysqli_fetch_assoc($admin_result)) {
        if (createEnhancedNotification($conn, $admin['id'], $msg['title'], $msg['message'], $msg['type'], $booking_id, 'admin_booking')) {
            $success_count++;
        }
    }
    
    return $success_count > 0;
}
?>
