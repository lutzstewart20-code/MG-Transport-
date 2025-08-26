<?php
// Notification Display Component
// Include this file in any page where you want to show notifications

function displayNotifications($user_id, $conn) {
    // Get unread notifications
    $query = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

function markNotificationAsRead($notification_id, $conn) {
    $query = "UPDATE notifications SET is_read = 1 WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $notification_id);
    return mysqli_stmt_execute($stmt);
}

function getNotificationIcon($type) {
    switch ($type) {
        case 'success':
            return 'fas fa-check-circle';
        case 'warning':
            return 'fas fa-exclamation-triangle';
        case 'error':
            return 'fas fa-times-circle';
        case 'info':
        default:
            return 'fas fa-info-circle';
    }
}

function getNotificationColor($type) {
    switch ($type) {
        case 'success':
            return 'success';
        case 'warning':
            return 'warning';
        case 'error':
            return 'danger';
        case 'info':
        default:
            return 'info';
    }
}
?>

<!-- Notification Display HTML -->
<div id="notification-container" class="position-fixed" style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
    <?php if (isset($_SESSION['user_id'])): ?>
        <?php 
        $notifications = displayNotifications($_SESSION['user_id'], $conn);
        foreach ($notifications as $notification): 
        ?>
        <div class="alert alert-<?php echo getNotificationColor($notification['type']); ?> alert-dismissible fade show notification-item mb-2" 
             role="alert" 
             data-notification-id="<?php echo $notification['id']; ?>">
            <i class="<?php echo getNotificationIcon($notification['type']); ?> me-2"></i>
            <strong><?php echo htmlspecialchars($notification['title']); ?></strong><br>
            <small><?php echo htmlspecialchars($notification['message']); ?></small>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Notification JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide notifications after 5 seconds
    const notifications = document.querySelectorAll('.notification-item');
    notifications.forEach(function(notification) {
        setTimeout(function() {
            if (notification && notification.parentNode) {
                notification.classList.add('fade');
                setTimeout(function() {
                    if (notification && notification.parentNode) {
                        notification.remove();
                    }
                }, 500);
            }
        }, 5000);
    });
    
    // Mark notifications as read when dismissed
    notifications.forEach(function(notification) {
        notification.addEventListener('closed.bs.alert', function() {
            const notificationId = this.getAttribute('data-notification-id');
            if (notificationId) {
                // Send AJAX request to mark as read
                fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'notification_id=' + notificationId
                });
            }
        });
    });
});
</script>

<style>
.notification-item {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border: none;
    border-radius: 8px;
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification-item.fade {
    animation: slideOutRight 0.5s ease-in forwards;
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}
</style> 