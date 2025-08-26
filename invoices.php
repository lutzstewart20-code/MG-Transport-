<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin($conn)) {
    header('Location: ../login.php');
    exit();
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment_status'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($status_filter) {
    $where_conditions[] = "b.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($payment_filter) {
    $where_conditions[] = "b.payment_status = ?";
    $params[] = $payment_filter;
    $param_types .= 's';
}

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR v.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(b.created_at) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "
SELECT b.*, u.username, u.email, u.first_name, u.last_name, u.phone,
       v.name as vehicle_name, v.registration_number, v.rate_per_day
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN vehicles v ON b.vehicle_id = v.id
$where_clause
ORDER BY b.created_at DESC
";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$bookings = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get statistics
$stats_query = "
SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_bookings,
    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
    SUM(total_amount) as total_revenue,
    SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as paid_revenue
FROM bookings
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Management - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-file-pdf"></i> Invoice Management
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="export-invoices.php" class="btn btn-outline-success">
                                <i class="fas fa-download"></i> Export All
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-file-pdf fa-2x text-primary mb-2"></i>
                                <h5 class="card-title">Total Invoices</h5>
                                <h3 class="text-primary"><?php echo $stats['total_bookings']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <h5 class="card-title">Paid Invoices</h5>
                                <h3 class="text-success"><?php echo $stats['paid_bookings']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                <h5 class="card-title">Pending Invoices</h5>
                                <h3 class="text-warning"><?php echo $stats['pending_bookings']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-money-bill-wave fa-2x text-info mb-2"></i>
                                <h5 class="card-title">Total Revenue</h5>
                                <h3 class="text-info"><?php echo formatCurrency($stats['total_revenue']); ?></h3>
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
                                    <div class="col-md-2">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="">All Statuses</option>
                                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="payment_status" class="form-label">Payment</label>
                                        <select class="form-select" id="payment_status" name="payment_status">
                                            <option value="">All Payments</option>
                                            <option value="pending" <?php echo $payment_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="paid" <?php echo $payment_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                            <option value="failed" <?php echo $payment_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="date_filter" class="form-label">Date</label>
                                        <select class="form-select" id="date_filter" name="date_filter">
                                            <option value="">All Dates</option>
                                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                                            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                            <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="search" class="form-label">Search</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               placeholder="Customer name, email, or vehicle" value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">&nbsp;</label>
                                        <div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-filter"></i> Apply Filters
                                            </button>
                                            <a href="invoices.php" class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Clear
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoices Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">All Invoices (<?php echo count($bookings); ?> found)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($bookings)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Invoice #</th>
                                                <th>Customer</th>
                                                <th>Vehicle</th>
                                                <th>Dates</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Payment</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bookings as $booking): ?>
                                            <tr>
                                                <td>
                                                    <strong>INV-<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($booking['email']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($booking['vehicle_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($booking['registration_number']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <small><?php echo formatDate($booking['start_date']); ?></small>
                                                        <br><small class="text-muted">to <?php echo formatDate($booking['end_date']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?php echo formatCurrency($booking['total_amount']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusColor($booking['status']); ?>">
                                                        <?php echo ucfirst($booking['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $booking['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($booking['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo formatDate($booking['created_at']); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="generate_invoice.php?booking_id=<?php echo $booking['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" target="_blank" title="View Invoice">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="generate_invoice.php?booking_id=<?php echo $booking['id']; ?>&download=1" 
                                                           class="btn btn-sm btn-outline-success" title="Download Invoice">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                                data-bs-toggle="modal" data-bs-target="#invoiceModal<?php echo $booking['id']; ?>" 
                                                                title="Invoice Details">
                                                            <i class="fas fa-info-circle"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            
                                            <!-- Invoice Details Modal -->
                                            <div class="modal fade" id="invoiceModal<?php echo $booking['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Invoice Details #<?php echo $booking['id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h6>Customer Information</h6>
                                                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></p>
                                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['email']); ?></p>
                                                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($booking['phone']); ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h6>Vehicle Information</h6>
                                                                    <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($booking['vehicle_name']); ?></p>
                                                                    <p><strong>Registration:</strong> <?php echo htmlspecialchars($booking['registration_number']); ?></p>
                                                                    <p><strong>Rate:</strong> <?php echo formatCurrency($booking['rate_per_day']); ?>/day</p>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h6>Booking Details</h6>
                                                                    <p><strong>Start Date:</strong> <?php echo formatDate($booking['start_date']); ?></p>
                                                                    <p><strong>End Date:</strong> <?php echo formatDate($booking['end_date']); ?></p>
                                                                    <p><strong>Status:</strong> <?php echo ucfirst($booking['status']); ?></p>
                                                                    <p><strong>Payment Status:</strong> <?php echo ucfirst($booking['payment_status']); ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h6>Payment Information</h6>
                                                                    <p><strong>Total Amount:</strong> <?php echo formatCurrency($booking['total_amount']); ?></p>
                                                                    <p><strong>Payment Method:</strong> <?php echo ucfirst($booking['payment_method']); ?></p>
                                                                    <p><strong>Created:</strong> <?php echo date('F d, Y g:i A', strtotime($booking['created_at'])); ?></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <a href="generate_invoice.php?booking_id=<?php echo $booking['id']; ?>" 
                                                               class="btn btn-primary" target="_blank">
                                                                <i class="fas fa-file-pdf"></i> View Invoice
                                                            </a>
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-file-pdf fa-3x text-muted mb-3"></i>
                                    <h4 class="text-muted">No Invoices Found</h4>
                                    <p class="text-muted">No invoices match your current filters.</p>
                                    <a href="invoices.php" class="btn btn-primary">Clear Filters</a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 