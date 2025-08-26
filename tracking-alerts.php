<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Handle alert resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'resolve_alert') {
        $alert_id = (int)$_POST['alert_id'];
        $resolution_notes = $_POST['resolution_notes'];
        
        $resolve_query = "UPDATE tracking_alerts SET 
                         is_resolved = TRUE, resolved_by = ?, resolved_at = CURRENT_TIMESTAMP 
                         WHERE id = ?";
        $stmt = mysqli_prepare($conn, $resolve_query);
        mysqli_stmt_bind_param($stmt, "ii", $_SESSION['user_id'], $alert_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Alert resolved successfully!";
        } else {
            $_SESSION['error_message'] = "Error resolving alert: " . mysqli_error($conn);
        }
    }
}

// Get all tracking alerts
$alerts_query = "SELECT ta.*, v.name as vehicle_name, v.registration_number,
                 u.first_name, u.last_name, u.email,
                 r.first_name as resolver_first_name, r.last_name as resolver_last_name
                 FROM tracking_alerts ta
                 JOIN vehicles v ON ta.vehicle_id = v.id
                 JOIN users u ON v.current_driver_id = u.id
                 LEFT JOIN users r ON ta.resolved_by = r.id
                 ORDER BY ta.created_at DESC";
$alerts_result = mysqli_query($conn, $alerts_query);

// Get alert statistics
$stats_query = "SELECT 
    COUNT(*) as total_alerts,
    COUNT(CASE WHEN is_resolved = FALSE THEN 1 END) as active_alerts,
    COUNT(CASE WHEN severity = 'critical' THEN 1 END) as critical_alerts,
    COUNT(CASE WHEN severity = 'high' THEN 1 END) as high_alerts,
    COUNT(CASE WHEN severity = 'medium' THEN 1 END) as medium_alerts,
    COUNT(CASE WHEN severity = 'low' THEN 1 END) as low_alerts
FROM tracking_alerts";
$stats_result = mysqli_query($conn, $stats_query);
$alert_stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Alerts - MG Transport Services Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-compact.css" rel="stylesheet">
    <style>
        .alert-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .alert-card:hover {
            transform: translateY(-2px);
        }
        
        .alert-critical { border-left: 4px solid #dc3545; }
        .alert-high { border-left: 4px solid #fd7e14; }
        .alert-medium { border-left: 4px solid #ffc107; }
        .alert-low { border-left: 4px solid #17a2b8; }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .stats-card.danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .stats-card.warning {
            background: linear-gradient(135deg, #fd7e14 0%, #e55a00 100%);
        }
        
        .stats-card.info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <div class="d-flex align-items-center">
                    <img src="../assets/images/MG Logo.jpg" alt="MG Transport Services" class="me-3" style="width: 50px; height: 50px; border-radius: 50%; background: #fbbf24;">
                    <div class="d-none d-md-block">
                        <div class="text-xl fw-bold text-white">MG TRANSPORT SERVICES</div>
                        <div class="text-sm text-warning">ADMIN PANEL</div>
                    </div>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="vehicles.php">
                            <i class="fas fa-car me-2"></i>Vehicles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bookings.php">
                            <i class="fas fa-calendar-check me-2"></i>Bookings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tracking-dashboard.php">
                            <i class="fas fa-satellite-dish me-2"></i>Tracking
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="delivery-tracking.php">
                            <i class="fas fa-truck me-2"></i>Delivery
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="tracking-alerts.php">
                            <i class="fas fa-exclamation-triangle me-2"></i>Alerts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-2"></i>Users
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>Admin
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid admin-container">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Tracking Alerts Management</h2>
                    <div>
                        <a href="tracking-dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-satellite-dish me-2"></i>Vehicle Tracking
                        </a>
                    </div>
                </div>

                <!-- Alert Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card stats-card text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?php echo $alert_stats['total_alerts']; ?></h3>
                                <small>Total Alerts</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card danger text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?php echo $alert_stats['active_alerts']; ?></h3>
                                <small>Active Alerts</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card danger text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?php echo $alert_stats['critical_alerts']; ?></h3>
                                <small>Critical</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card warning text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?php echo $alert_stats['high_alerts']; ?></h3>
                                <small>High</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card info text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?php echo $alert_stats['medium_alerts']; ?></h3>
                                <small>Medium</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stats-card text-white" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%);">
                            <div class="card-body text-center">
                                <h3 class="mb-0"><?php echo $alert_stats['low_alerts']; ?></h3>
                                <small>Low</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alerts List -->
                <div class="card alert-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Tracking Alerts</h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($alerts_result) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Vehicle</th>
                                            <th>Alert Type</th>
                                            <th>Severity</th>
                                            <th>Message</th>
                                            <th>Driver</th>
                                            <th>Created</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($alert = mysqli_fetch_assoc($alerts_result)): ?>
                                            <tr class="alert-<?php echo $alert['severity']; ?>">
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($alert['vehicle_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($alert['registration_number']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo ucfirst(str_replace('_', ' ', $alert['alert_type'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $alert['severity'] === 'critical' ? 'danger' : 
                                                             ($alert['severity'] === 'high' ? 'warning' : 
                                                             ($alert['severity'] === 'medium' ? 'info' : 'secondary')); 
                                                    ?>">
                                                        <?php echo ucfirst($alert['severity']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <?php echo htmlspecialchars($alert['message']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($alert['first_name']): ?>
                                                        <?php echo htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?php echo formatDate($alert['created_at']); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($alert['is_resolved']): ?>
                                                        <span class="badge bg-success">Resolved</span>
                                                        <?php if ($alert['resolver_first_name']): ?>
                                                            <br><small class="text-muted">by <?php echo htmlspecialchars($alert['resolver_first_name'] . ' ' . $alert['resolver_last_name']); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Active</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h4 class="text-success">No Alerts Found</h4>
                                <p class="text-muted">All systems are running smoothly with no active alerts.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
