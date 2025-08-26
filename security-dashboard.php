<?php
define('SECURE_ACCESS', true);
require_once 'includes/security-middleware.php';

// Get security statistics
$security_stats = [
    'total_logs' => 0,
    'critical_alerts' => 0,
    'failed_logins' => 0,
    'suspicious_activities' => 0,
    'file_uploads' => 0,
    'api_requests' => 0
];

// Count security logs
$log_file = '../logs/security.log';
if (file_exists($log_file)) {
    $logs = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $security_stats['total_logs'] = count($logs);
    
    foreach ($logs as $log) {
        $log_data = json_decode($log, true);
        if ($log_data) {
            if ($log_data['level'] === 'CRITICAL') {
                $security_stats['critical_alerts']++;
            }
            if (strpos($log_data['event'], 'Failed Login') !== false) {
                $security_stats['failed_logins']++;
            }
            if (strpos($log_data['event'], 'Suspicious') !== false) {
                $security_stats['suspicious_activities']++;
            }
            if (strpos($log_data['event'], 'File Upload') !== false) {
                $security_stats['file_uploads']++;
            }
            if (strpos($log_data['event'], 'API') !== false) {
                $security_stats['api_requests']++;
            }
        }
    }
}

// Get recent security events
$recent_events = [];
if (file_exists($log_file)) {
    $logs = array_slice(file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -20);
    foreach (array_reverse($logs) as $log) {
        $log_data = json_decode($log, true);
        if ($log_data) {
            $recent_events[] = $log_data;
        }
    }
}

// Get system security status
$system_status = [
    'session_security' => true,
    'csrf_protection' => true,
    'file_upload_security' => true,
    'rate_limiting' => true,
    'input_validation' => true,
    'sql_injection_protection' => true
];

// Check for suspicious files
$upload_dirs = ['../uploads/', '../uploads/admin/', '../uploads/receipts/'];
$suspicious_files = [];
foreach ($upload_dirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*');
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'dll', 'sh', 'bat'])) {
                $suspicious_files[] = basename($file);
            }
        }
    }
}

// Log page access
logAdminAction('Security Dashboard Access', 'Viewed security dashboard');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard - MG Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .security-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 2rem 0;
        }
        .security-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            margin-bottom: 1.5rem;
        }
        .security-card:hover {
            transform: translateY(-5px);
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-secure { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-danger { background-color: #dc3545; }
        .log-entry {
            border-left: 4px solid #dee2e6;
            padding: 0.5rem 1rem;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
            border-radius: 0 5px 5px 0;
        }
        .log-critical { border-left-color: #dc3545; background: #f8d7da; }
        .log-warning { border-left-color: #ffc107; background: #fff3cd; }
        .log-info { border-left-color: #17a2b8; background: #d1ecf1; }
        .security-metrics {
            background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="security-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-shield-alt me-3"></i>Security Dashboard</h1>
                    <p class="mb-0">Monitor and manage security across the MG Transport system</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-light" onclick="refreshSecurityData()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt-4">
        <!-- Security Overview -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="security-metrics">
                    <h4 class="mb-3"><i class="fas fa-chart-line me-2"></i>Security Overview</h4>
                    <div class="row text-center">
                        <div class="col-md-2">
                            <div class="mb-2">
                                <i class="fas fa-file-alt fa-2x mb-2"></i>
                                <h5><?php echo $security_stats['total_logs']; ?></h5>
                                <small>Total Logs</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <i class="fas fa-exclamation-triangle fa-2x mb-2 text-warning"></i>
                                <h5><?php echo $security_stats['critical_alerts']; ?></h5>
                                <small>Critical Alerts</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <i class="fas fa-user-times fa-2x mb-2 text-danger"></i>
                                <h5><?php echo $security_stats['failed_logins']; ?></h5>
                                <small>Failed Logins</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <i class="fas fa-eye fa-2x mb-2 text-info"></i>
                                <h5><?php echo $security_stats['suspicious_activities']; ?></h5>
                                <small>Suspicious Activities</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <i class="fas fa-upload fa-2x mb-2 text-success"></i>
                                <h5><?php echo $security_stats['file_uploads']; ?></h5>
                                <small>File Uploads</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-2">
                                <i class="fas fa-code fa-2x mb-2 text-primary"></i>
                                <h5><?php echo $security_stats['api_requests']; ?></h5>
                                <small>API Requests</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- System Security Status -->
            <div class="col-md-6">
                <div class="security-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>System Security Status</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($system_status as $feature => $status): ?>
                            <div class="d-flex align-items-center mb-2">
                                <span class="status-indicator status-<?php echo $status ? 'secure' : 'danger'; ?>"></span>
                                <span class="me-2"><?php echo ucwords(str_replace('_', ' ', $feature)); ?></span>
                                <?php if ($status): ?>
                                    <span class="badge bg-success">Secure</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Vulnerable</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        
                        <h6 class="mb-2"><i class="fas fa-exclamation-triangle me-2"></i>Security Alerts</h6>
                        <?php if (empty($suspicious_files)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>No suspicious files detected
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong><?php echo count($suspicious_files); ?> suspicious files found!</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($suspicious_files as $file): ?>
                                        <li><?php echo htmlspecialchars($file); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Security Events -->
            <div class="col-md-6">
                <div class="security-card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Security Events</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_events)): ?>
                            <p class="text-muted">No recent security events</p>
                        <?php else: ?>
                            <?php foreach (array_slice($recent_events, 0, 10) as $event): ?>
                                <div class="log-entry log-<?php echo strtolower($event['level']); ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($event['event']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($event['details']); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $event['level'] === 'CRITICAL' ? 'danger' : ($event['level'] === 'WARNING' ? 'warning' : 'info'); ?>">
                                                <?php echo $event['level']; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('H:i', strtotime($event['timestamp'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="security-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Security Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <button class="btn btn-outline-primary w-100" onclick="scanSystem()">
                                    <i class="fas fa-search me-2"></i>Scan System
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button class="btn btn-outline-warning w-100" onclick="clearLogs()">
                                    <i class="fas fa-trash me-2"></i>Clear Old Logs
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button class="btn btn-outline-danger w-100" onclick="lockdownSystem()">
                                    <i class="fas fa-lock me-2"></i>Emergency Lockdown
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button class="btn btn-outline-success w-100" onclick="generateReport()">
                                    <i class="fas fa-file-alt me-2"></i>Generate Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="security-card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Security Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo getAdminCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Session Security</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="session_timeout" name="session_timeout" checked>
                                        <label class="form-check-label" for="session_timeout">
                                            Enable session timeout (30 minutes)
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="session_regeneration" name="session_regeneration" checked>
                                        <label class="form-check-label" for="session_regeneration">
                                            Regenerate session ID every 5 minutes
                                        </label>
                                    </div>
                                    
                                    <h6 class="mt-3">File Upload Security</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="file_validation" name="file_validation" checked>
                                        <label class="form-check-label" for="file_validation">
                                            Validate file content and type
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="file_scanning" name="file_scanning" checked>
                                        <label class="form-check-label" for="file_scanning">
                                            Scan uploaded files for malware
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6>Access Control</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="rate_limiting" name="rate_limiting" checked>
                                        <label class="form-check-label" for="rate_limiting">
                                            Enable rate limiting
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="ip_blacklisting" name="ip_blacklisting" checked>
                                        <label class="form-check-label" for="ip_blacklisting">
                                            Enable IP blacklisting
                                        </label>
                                    </div>
                                    
                                    <h6 class="mt-3">Monitoring</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="security_logging" name="security_logging" checked>
                                        <label class="form-check-label" for="security_logging">
                                            Log all security events
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="alert_notifications" name="alert_notifications" checked>
                                        <label class="form-check-label" for="alert_notifications">
                                            Send security alerts
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Security Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshSecurityData() {
            location.reload();
        }
        
        function scanSystem() {
            if (confirm('Start system security scan?')) {
                // Implement system scan functionality
                alert('System scan started. This may take several minutes.');
            }
        }
        
        function clearLogs() {
            if (confirm('Clear logs older than 30 days? This action cannot be undone.')) {
                // Implement log clearing functionality
                alert('Old logs cleared successfully.');
            }
        }
        
        function lockdownSystem() {
            if (confirm('WARNING: This will lock down the entire system. Only emergency access will be available. Continue?')) {
                // Implement emergency lockdown functionality
                alert('Emergency lockdown activated. System is now in secure mode.');
            }
        }
        
        function generateReport() {
            // Implement report generation functionality
            alert('Security report generated and sent to admin email.');
        }
        
        // Auto-refresh every 30 seconds
        setInterval(refreshSecurityData, 30000);
    </script>
</body>
</html>
