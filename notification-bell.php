<?php
// Include notifications helper if not already included
if (!function_exists('getNotificationCountForUser')) {
    require_once 'notifications.php';
}

// Get notification count for current user
$notification_count = 0;
$unread_notifications = [];

if (isset($_SESSION['user_id'])) {
    $notification_count = getNotificationCountForUser($conn, $_SESSION['user_id'], true);
    $unread_notifications = getUnreadNotificationsList($conn, $_SESSION['user_id'], 5);
}
?>

<!-- Notification Bell Component -->
<div class="dropdown notification-dropdown">
    <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell fa-lg"></i>
        <?php if ($notification_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?php echo $notification_count > 99 ? '99+' : $notification_count; ?>
            </span>
        <?php endif; ?>
    </a>
    
    <div class="dropdown-menu dropdown-menu-end notification-dropdown-menu" style="width: 350px; max-height: 400px; overflow-y: auto;">
        <div class="dropdown-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Notifications</h6>
            <?php if ($notification_count > 0): ?>
                <button class="btn btn-sm btn-outline-primary" onclick="markAllNotificationsAsRead()">
                    Mark all read
                </button>
            <?php endif; ?>
        </div>
        
        <div class="dropdown-divider"></div>
        
        <?php if (empty($unread_notifications)): ?>
            <div class="text-center py-3">
                <i class="fas fa-bell-slash fa-2x text-muted mb-2"></i>
                <p class="text-muted mb-0">No new notifications</p>
            </div>
        <?php else: ?>
            <?php foreach ($unread_notifications as $notification): ?>
                <div class="dropdown-item notification-item" data-notification-id="<?php echo $notification['id']; ?>">
                    <div class="d-flex align-items-start">
                        <div class="notification-icon me-3">
                            <?php
                            $icon_class = 'fas fa-info-circle';
                            $icon_color = 'text-info';
                            
                            switch ($notification['type']) {
                                case 'success':
                                    $icon_class = 'fas fa-check-circle';
                                    $icon_color = 'text-success';
                                    break;
                                case 'warning':
                                    $icon_class = 'fas fa-exclamation-triangle';
                                    $icon_color = 'text-warning';
                                    break;
                                case 'error':
                                    $icon_class = 'fas fa-times-circle';
                                    $icon_color = 'text-danger';
                                    break;
                            }
                            ?>
                            <i class="<?php echo $icon_class . ' ' . $icon_color; ?>"></i>
                        </div>
                        
                        <div class="notification-content flex-grow-1">
                            <h6 class="notification-title mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                            <p class="notification-message mb-1 small"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <small class="text-muted">
                                <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                            </small>
                        </div>
                        
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="markNotificationAsRead(<?php echo $notification['id']; ?>)">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="dropdown-divider"></div>
            <div class="text-center">
                <a href="notifications.php" class="btn btn-outline-primary btn-sm">
                    View All Notifications
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.notification-dropdown-menu {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.notification-item {
    padding: 0.75rem;
    border-bottom: 1px solid #f8f9fa;
    transition: background-color 0.2s;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: #495057;
}

.notification-message {
    color: #6c757d;
    line-height: 1.4;
}

.notification-icon {
    margin-top: 0.125rem;
}

.notification-dropdown .nav-link {
    color: #6c757d;
    transition: color 0.2s;
}

.notification-dropdown .nav-link:hover {
    color: #495057;
}
</style>

<script>
function markNotificationAsRead(notificationId) {
    fetch('ajax/mark-notification-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the notification from the dropdown
            const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notificationItem) {
                notificationItem.remove();
            }
            
            // Update notification count
            updateNotificationCount();
            
            // If no more notifications, show empty state
            const notificationItems = document.querySelectorAll('.notification-item');
            if (notificationItems.length === 0) {
                location.reload(); // Simple refresh for now
            }
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

function markAllNotificationsAsRead() {
    fetch('ajax/mark-all-notifications-read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload(); // Refresh to update notification count
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
    });
}

function updateNotificationCount() {
    // This function can be enhanced to update the badge count without page refresh
    // For now, we'll just reload the page
    location.reload();
}
</script>
