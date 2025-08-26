<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle marking notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'mark_read' && isset($_POST['notification_id'])) {
        $notification_id = (int)$_POST['notification_id'];
        markNotificationAsReadById($conn, $notification_id, $_SESSION['user_id']);
    } elseif ($action === 'mark_all_read') {
        markAllNotificationsAsReadForUser($conn, $_SESSION['user_id']);
    }
    
    // Redirect to prevent form resubmission
    header('Location: notifications.php');
    exit();
}

// Get all notifications for the user
$all_notifications = getAllNotificationsList($conn, $_SESSION['user_id'], 50);
$unread_count = getNotificationCountForUser($conn, $_SESSION['user_id'], true);
$total_count = getNotificationCountForUser($conn, $_SESSION['user_id'], false);

// Get user details
$user = getUserDetails($_SESSION['user_id'], $conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .notification-card {
            border-left: 4px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .notification-card.unread {
            border-left-color: #007bff;
            background-color: #f8f9fa;
        }
        
        .notification-card.success {
            border-left-color: #28a745;
        }
        
        .notification-card.warning {
            border-left-color: #ffc107;
        }
        
        .notification-card.error {
            border-left-color: #dc3545;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .notification-icon.info {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .notification-icon.success {
            background-color: #e8f5e8;
            color: #388e3c;
        }
        
        .notification-icon.warning {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
        .notification-icon.error {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        .notification-time {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .notification-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .notification-card:hover .notification-actions {
            opacity: 1;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">
                            <i class="fas fa-bell me-2 text-primary"></i>
                            Notifications
                        </h1>
                        <p class="text-muted mb-0">
                            <?php echo $unread_count; ?> unread • <?php echo $total_count; ?> total
                        </p>
                    </div>
                    
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-check-double me-2"></i>
                                Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Notifications List -->
                <?php if (empty($all_notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h4>No notifications yet</h4>
                        <p class="mb-0">You'll see notifications here when there are updates about your bookings, payments, or other activities.</p>
                    </div>
                <?php else: ?>
                    <div class="notifications-list">
                        <?php foreach ($all_notifications as $notification): ?>
                            <div class="card notification-card mb-3 <?php echo $notification['is_read'] ? '' : 'unread'; ?> <?php echo $notification['type']; ?>">
                                <div class="card-body">
                                    <div class="row align-items-start">
                                        <div class="col-auto">
                                            <div class="notification-icon <?php echo $notification['type']; ?>">
                                                <?php
                                                $icon_class = 'fas fa-info-circle';
                                                switch ($notification['type']) {
                                                    case 'success':
                                                        $icon_class = 'fas fa-check-circle';
                                                        break;
                                                    case 'warning':
                                                        $icon_class = 'fas fa-exclamation-triangle';
                                                        break;
                                                    case 'error':
                                                        $icon_class = 'fas fa-times-circle';
                                                        break;
                                                }
                                                ?>
                                                <i class="<?php echo $icon_class; ?>"></i>
                                            </div>
                                        </div>
                                        
                                        <div class="col">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="card-title mb-1">
                                                        <?php echo htmlspecialchars($notification['title']); ?>
                                                        <?php if (!$notification['is_read']): ?>
                                                            <span class="badge bg-primary ms-2">New</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <p class="card-text mb-2">
                                                        <?php echo htmlspecialchars($notification['message']); ?>
                                                    </p>
                                                    <div class="notification-time">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date('M j, Y \a\t g:i A', strtotime($notification['created_at'])); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="notification-actions ms-3">
                                                    <?php if (!$notification['is_read']): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="mark_read">
                                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Mark as read">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($notification['related_id'] && $notification['related_type']): ?>
                                                        <a href="<?php echo getRelatedLink($notification['related_type'], $notification['related_id']); ?>" 
                                                           class="btn btn-sm btn-outline-primary ms-1" title="View details">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>

<?php
/**
 * Helper function to get related links for notifications
 */
function getRelatedLink($type, $id) {
    switch ($type) {
        case 'booking':
            return "my-bookings.php";
        case 'payment':
            return "my-bookings.php";
        case 'agreement':
            return "vehicle-agreement.php";
        default:
            return "#";
    }
}
?> 