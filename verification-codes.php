<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Handle verification code actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $code_id = (int)$_POST['code_id'];
    $action = $_POST['action'];
    
    switch ($action) {
        case 'invalidate':
            $update_query = "UPDATE verification_codes SET is_used = TRUE WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "i", $code_id);
            
            if (mysqli_stmt_execute($stmt)) {
                redirectWithMessage('verification-codes.php', 'Verification code invalidated successfully.', 'success');
            } else {
                redirectWithMessage('verification-codes.php', 'Error invalidating verification code.', 'error');
            }
            break;
            
        case 'delete_expired':
            $delete_query = "DELETE FROM verification_codes WHERE expires_at < NOW() AND is_used = FALSE";
            if (mysqli_query($conn, $delete_query)) {
                redirectWithMessage('verification-codes.php', 'Expired verification codes cleaned up successfully.', 'success');
            } else {
                redirectWithMessage('verification-codes.php', 'Error cleaning up expired codes.', 'error');
            }
            break;
            
        default:
            redirectWithMessage('verification-codes.php', 'Invalid action.', 'error');
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$purpose_filter = isset($_GET['purpose']) ? $_GET['purpose'] : '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($status_filter) {
    if ($status_filter === 'active') {
        $where_conditions[] = "vc.expires_at > NOW() AND vc.is_used = FALSE";
    } elseif ($status_filter === 'expired') {
        $where_conditions[] = "vc.expires_at < NOW() AND vc.is_used = FALSE";
    } elseif ($status_filter === 'used') {
        $where_conditions[] = "vc.is_used = TRUE";
    }
}

if ($purpose_filter) {
    $where_conditions[] = "vc.purpose = ?";
    $params[] = $purpose_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get verification codes with user and booking details
$codes_query = "SELECT vc.*, u.first_name, u.last_name, u.email, u.phone,
                       b.id as booking_id, v.name as vehicle_name
                FROM verification_codes vc
                JOIN users u ON vc.user_id = u.id
                JOIN bookings b ON vc.booking_id = b.id
                JOIN vehicles v ON b.vehicle_id = v.id
                $where_clause
                ORDER BY vc.created_at DESC";

$stmt = mysqli_prepare($conn, $codes_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$codes_result = mysqli_stmt_get_result($stmt);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_codes,
    SUM(CASE WHEN expires_at > NOW() AND is_used = FALSE THEN 1 ELSE 0 END) as active_codes,
    SUM(CASE WHEN expires_at < NOW() AND is_used = FALSE THEN 1 ELSE 0 END) as expired_codes,
    SUM(CASE WHEN is_used = TRUE THEN 1 ELSE 0 END) as used_codes
    FROM verification_codes";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Codes - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .code-active { border-left: 4px solid #28a745; }
        .code-expired { border-left: 4px solid #dc3545; }
        .code-used { border-left: 4px solid #6c757d; }
        .verification-code {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: bold;
            letter-spacing: 0.2rem;
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-shield-alt me-2"></i>Verification Codes Management</h2>
                    <div>
                        <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#smsStatsModal">
                            <i class="fas fa-chart-bar me-2"></i>SMS Statistics
                        </button>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="action" value="delete_expired" class="btn btn-warning" 
                                    onclick="return confirm('Delete all expired verification codes?')">
                                <i class="fas fa-trash me-2"></i>Clean Expired Codes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo $stats['total_codes']; ?></h4>
                                <small>Total Codes</small>
                            </div>
                            <i class="fas fa-key fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo $stats['active_codes']; ?></h4>
                                <small>Active Codes</small>
                            </div>
                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo $stats['expired_codes']; ?></h4>
                                <small>Expired Codes</small>
                            </div>
                            <i class="fas fa-clock fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo $stats['used_codes']; ?></h4>
                                <small>Used Codes</small>
                            </div>
                            <i class="fas fa-check-double fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="status_filter" class="form-label">Status</label>
                                <select class="form-select" id="status_filter" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                    <option value="used" <?php echo $status_filter === 'used' ? 'selected' : ''; ?>>Used</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="purpose_filter" class="form-label">Purpose</label>
                                <select class="form-select" id="purpose_filter" name="purpose">
                                    <option value="">All Purposes</option>
                                    <option value="bank_transfer" <?php echo $purpose_filter === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                    <option value="payment_confirmation" <?php echo $purpose_filter === 'payment_confirmation' ? 'selected' : ''; ?>>Payment Confirmation</option>
                                    <option value="account_verification" <?php echo $purpose_filter === 'account_verification' ? 'selected' : ''; ?>>Account Verification</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter me-2"></i>Filter
                                </button>
                                <a href="verification-codes.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification Codes Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Verification Codes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($codes_result) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>User</th>
                                        <th>Phone</th>
                                        <th>Purpose</th>
                                        <th>Booking</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Expires</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($code = mysqli_fetch_assoc($codes_result)): ?>
                                    <?php 
                                        $status_class = '';
                                        $status_text = '';
                                        if ($code['is_used']) {
                                            $status_class = 'code-used';
                                            $status_text = '<span class="badge bg-secondary">Used</span>';
                                        } elseif (strtotime($code['expires_at']) < time()) {
                                            $status_class = 'code-expired';
                                            $status_text = '<span class="badge bg-danger">Expired</span>';
                                        } else {
                                            $status_class = 'code-active';
                                            $status_text = '<span class="badge bg-success">Active</span>';
                                        }
                                    ?>
                                    <tr class="<?php echo $status_class; ?>">
                                        <td>
                                            <span class="verification-code"><?php echo htmlspecialchars($code['code']); ?></span>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($code['first_name'] . ' ' . $code['last_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($code['email']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($code['phone']); ?></code>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst(str_replace('_', ' ', $code['purpose'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div>
                                                <strong>#<?php echo $code['booking_id']; ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($code['vehicle_name']); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo $status_text; ?></td>
                                        <td>
                                            <small><?php echo formatDate($code['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo formatDate($code['expires_at']); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!$code['is_used'] && strtotime($code['expires_at']) > time()): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="code_id" value="<?php echo $code['id']; ?>">
                                                <button type="submit" name="action" value="invalidate" 
                                                        class="btn btn-sm btn-warning" 
                                                        onclick="return confirm('Invalidate this verification code?')">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-shield-alt fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No verification codes found</h5>
                            <p class="text-muted">No verification codes match the current filters.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SMS Statistics Modal -->
    <div class="modal fade" id="smsStatsModal" tabindex="-1" aria-labelledby="smsStatsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="smsStatsModalLabel">
                        <i class="fas fa-chart-bar me-2"></i>SMS Statistics
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Overall Statistics</h6>
                            <div class="list-group">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Total SMS Sent
                                    <span class="badge bg-primary rounded-pill"><?php echo $stats['total_codes']; ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Active Codes
                                    <span class="badge bg-success rounded-pill"><?php echo $stats['active_codes']; ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Expired Codes
                                    <span class="badge bg-danger rounded-pill"><?php echo $stats['expired_codes']; ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    Used Codes
                                    <span class="badge bg-secondary rounded-pill"><?php echo $stats['used_codes']; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Recent Activity</h6>
                            <div class="list-group">
                                <div class="list-group-item">
                                    <small class="text-muted">Last 24 Hours</small>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>New Codes Generated</span>
                                        <span class="badge bg-info"><?php echo $stats['active_codes'] + $stats['expired_codes']; ?></span>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <small class="text-muted">Success Rate</small>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Verification Success</span>
                                        <span class="badge bg-success">
                                            <?php 
                                            $total_used = $stats['used_codes'];
                                            $total_generated = $stats['total_codes'];
                                            $success_rate = $total_generated > 0 ? round(($total_used / $total_generated) * 100, 1) : 0;
                                            echo $success_rate . '%';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh statistics every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
        
        // Enhanced table interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
            
            // Add click to copy verification code
            const codeElements = document.querySelectorAll('.verification-code');
            codeElements.forEach(code => {
                code.style.cursor = 'pointer';
                code.title = 'Click to copy';
                code.addEventListener('click', function() {
                    const text = this.textContent;
                    navigator.clipboard.writeText(text).then(() => {
                        // Show temporary success message
                        const originalText = this.textContent;
                        this.textContent = 'Copied!';
                        this.style.backgroundColor = '#28a745';
                        this.style.color = 'white';
                        
                        setTimeout(() => {
                            this.textContent = originalText;
                            this.style.backgroundColor = '';
                            this.style.color = '';
                        }, 1000);
                    });
                });
            });
        });
    </script>
</body>
</html>
