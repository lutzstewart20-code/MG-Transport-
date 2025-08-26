<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($payment_method) {
    $where_conditions[] = "b.payment_method = ?";
    $params[] = $payment_method;
    $param_types .= 's';
}

if ($status_filter) {
    $where_conditions[] = "b.payment_status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_conditions[] = "b.created_at BETWEEN ? AND ?";
$params[] = $start_date;
$params[] = $end_date;
$param_types .= 'ss';

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get payment statements
$query = "
SELECT b.*, u.first_name, u.last_name, u.email, u.phone,
       v.name as vehicle_name, v.registration_number,
       pr.receipt_file, pr.bank_name, pr.account_number, pr.reference_number
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN vehicles v ON b.vehicle_id = v.id
LEFT JOIN payment_receipts pr ON b.id = pr.booking_id
$where_clause
ORDER BY b.created_at DESC
";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$payments = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get payment statistics
$stats_query = "
SELECT 
    COUNT(*) as total_payments,
    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_count,
    SUM(total_amount) as total_revenue,
    SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as confirmed_revenue
FROM bookings
WHERE created_at BETWEEN ? AND ?
";

$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get payment method distribution
$method_stats_query = "
SELECT 
    payment_method,
    COUNT(*) as count,
    SUM(total_amount) as total_amount
FROM bookings 
WHERE created_at BETWEEN ? AND ? AND payment_method IS NOT NULL
GROUP BY payment_method
ORDER BY count DESC
";

$method_stmt = mysqli_prepare($conn, $method_stats_query);
mysqli_stmt_bind_param($method_stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($method_stmt);
$method_stats_result = mysqli_stmt_get_result($method_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Statements - MG Transport Services Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-compact.css" rel="stylesheet">
    <style>
        .payment-card {
            border-left: 4px solid #17a2b8;
            transition: all 0.3s ease;
        }
        
        .payment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container-fluid admin-container">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Payment Statements & Financial Summary
                        </h2>
                        <p class="text-muted mb-0">View detailed payment statements and financial reports</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-modern btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <?php displayMessage(); ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                        <div class="stat-card h-100">
                            <div class="stats-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['total_payments']); ?></h3>
                            <p class="text-muted mb-0 fw-semibold">Total Payments</p>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                        <div class="stat-card success h-100">
                            <div class="stats-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['paid_count']); ?></h3>
                            <p class="text-muted mb-0 fw-semibold">Confirmed Payments</p>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                        <div class="stat-card warning h-100">
                            <div class="stats-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['pending_count']); ?></h3>
                            <p class="text-muted mb-0 fw-semibold">Pending Payments</p>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                        <div class="stat-card info h-100">
                            <div class="stats-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <h3 class="fw-bold mb-2 stat-number"><?php echo formatCurrency($stats['confirmed_revenue']); ?></h3>
                            <p class="text-muted mb-0 fw-semibold">Confirmed Revenue</p>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select class="form-select" id="payment_method" name="payment_method">
                                <option value="">All Methods</option>
                                <option value="credit_card" <?php echo $payment_method === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                <option value="debit_card" <?php echo $payment_method === 'debit_card' ? 'selected' : ''; ?>>Debit Card</option>
                                <option value="bank_transfer" <?php echo $payment_method === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="sms_transfer" <?php echo $payment_method === 'sms_transfer' ? 'selected' : ''; ?>>SMS Transfer</option>
                                <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Payment Statements Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Payment Statements 
                            <span class="badge bg-primary"><?php echo count($payments); ?> found</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payments)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No Payment Statements Found</h4>
                                <p class="text-muted">No payments match your current filters.</p>
                                <a href="payment-statements.php" class="btn btn-primary">Clear Filters</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Booking ID</th>
                                            <th>Customer</th>
                                            <th>Vehicle</th>
                                            <th>Payment Method</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td>
                                                    <strong>#<?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($payment['email']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($payment['vehicle_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($payment['registration_number']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo formatCurrency($payment['total_amount']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_color = 'secondary';
                                                    $status_text = ucfirst($payment['payment_status']);
                                                    
                                                    if ($payment['payment_method'] === 'sms_transfer' && $payment['payment_receipt_path']) {
                                                        $status_text = 'Payment Made - Awaiting Approval';
                                                        $status_color = 'info';
                                                    } elseif ($payment['payment_status'] === 'paid') {
                                                        $status_color = 'success';
                                                    } elseif ($payment['payment_status'] === 'pending') {
                                                        $status_color = 'warning';
                                                    } elseif ($payment['payment_status'] === 'failed') {
                                                        $status_color = 'danger';
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_color; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo formatDate($payment['created_at']); ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view-booking.php?id=<?php echo $payment['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="generate_invoice.php?booking_id=<?php echo $payment['id']; ?>" 
                                                           class="btn btn-sm btn-outline-info" target="_blank" title="Generate Invoice">
                                                            <i class="fas fa-file-invoice"></i>
                                                        </a>
                                                        <?php if ($payment['payment_status'] === 'pending' && $payment['payment_receipt_path']): ?>
                                                            <a href="sms-payments.php" class="btn btn-sm btn-outline-success" title="Verify Payment">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Method Distribution -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Payment Method Distribution</h5>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($method_stats_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Method</th>
                                                    <th>Count</th>
                                                    <th>Total Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($method = mysqli_fetch_assoc($method_stats_result)): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-info">
                                                                <?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $method['count']; ?></td>
                                                        <td><?php echo formatCurrency($method['total_amount']); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No payment method data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-download me-2"></i>Export Options</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="export-reports.php?type=payments&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                                       class="btn btn-outline-success">
                                        <i class="fas fa-file-csv me-2"></i>Export to CSV
                                    </a>
                                    <a href="revenue-analytics.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                                       class="btn btn-outline-info">
                                        <i class="fas fa-chart-line me-2"></i>Revenue Analytics
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/admin_footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit form when dates change
        document.getElementById('start_date').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('end_date').addEventListener('change', function() {
            this.form.submit();
        });
        
        // Auto-submit form when payment method or status changes
        document.getElementById('payment_method').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>
