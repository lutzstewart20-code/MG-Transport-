<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'delete' && isset($_POST['notification_id'])) {
        $notification_id = (int)$_POST['notification_id'];
        $delete_query = "DELETE FROM notifications WHERE id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $notification_id);
        mysqli_stmt_execute($stmt);
    } elseif ($action === 'cleanup') {
        cleanupOldNotifications($conn, 30); // Clean up notifications older than 30 days
    }
    
    // Redirect to prevent form resubmission
    header('Location: notifications.php');
    exit();
}

// Get all notifications with user details
$notifications_query = "
    SELECT n.*, u.username, u.email, u.first_name, u.last_name 
    FROM notifications n 
    JOIN users u ON n.user_id = u.id 
    ORDER BY n.created_at DESC
";
$notifications_result = mysqli_query($conn, $notifications_query);

// Get statistics
$total_notifications = mysqli_num_rows($notifications_result);
$unread_count = 0;
$type_counts = ['info' => 0, 'success' => 0, 'warning' => 0, 'error' => 0];

if ($notifications_result) {
    $notifications = [];
    while ($row = mysqli_fetch_assoc($notifications_result)) {
        $notifications[] = $row;
        if (!$row['is_read']) $unread_count++;
        $type_counts[$row['type']]++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Notifications - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
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
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stats-card h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .stats-card p {
            margin: 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-bell me-2 text-primary"></i>
                        Manage Notifications
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <form method="POST" class="d-inline me-2">
                            <input type="hidden" name="action" value="cleanup">
                            <button type="submit" class="btn btn-outline-warning" onclick="return confirm('This will delete notifications older than 30 days. Continue?')">
                                <i class="fas fa-broom me-2"></i>Cleanup Old
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo $total_notifications; ?></h3>
                            <p>Total Notifications</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h3><?php echo $unread_count; ?></h3>
                            <p>Unread</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h3><?php echo $type_counts['info']; ?></h3>
                            <p>Info</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <h3><?php echo $type_counts['success']; ?></h3>
                            <p>Success</p>
                        </div>
                    </div>
                </div>

                <!-- Notifications List -->
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No notifications found</h4>
                        <p class="text-muted">The system doesn't have any notifications yet.</p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">All Notifications</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-card p-3 border-bottom <?php echo $notification['is_read'] ? '' : 'unread'; ?> <?php echo $notification['type']; ?>">
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
                                                    <h6 class="mb-1">
                                                        <?php echo htmlspecialchars($notification['title']); ?>
                                                        <?php if (!$notification['is_read']): ?>
                                                            <span class="badge bg-primary ms-2">New</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <p class="mb-2 text-muted">
                                                        <?php echo htmlspecialchars($notification['message']); ?>
                                                    </p>
                                                    <div class="d-flex align-items-center text-muted small">
                                                        <span class="me-3">
                                                            <i class="fas fa-user me-1"></i>
                                                            <?php echo htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']); ?>
                                                            (<?php echo htmlspecialchars($notification['username']); ?>)
                                                        </span>
                                                        <span class="me-3">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                                        </span>
                                                        <?php if ($notification['related_id'] && $notification['related_type']): ?>
                                                            <span class="me-3">
                                                                <i class="fas fa-link me-1"></i>
                                                                <?php echo ucfirst($notification['related_type']); ?> #<?php echo $notification['related_id']; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="ms-3">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                                onclick="return confirm('Are you sure you want to delete this notification?')"
                                                                title="Delete notification">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
