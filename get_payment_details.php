<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied. Admin privileges required.</div>';
    exit();
}

// Get payment ID from request
$payment_id = intval($_GET['payment_id'] ?? 0);

if (!$payment_id) {
    echo '<div class="alert alert-danger">Invalid payment ID.</div>';
    exit();
}

// Fetch payment details with related information
$query = "SELECT p.*, 
          b.start_date, b.end_date, b.total_days, b.pickup_location, b.dropoff_location, b.special_requests,
          u.first_name, u.last_name, u.email, u.phone,
          v.name as vehicle_name, v.model, v.registration_number, v.vehicle_type, v.seats, v.rate_per_day,
          admin.first_name as admin_first_name, admin.last_name as admin_last_name
          FROM payments p
          JOIN bookings b ON p.booking_id = b.id
          JOIN users u ON b.user_id = u.id
          JOIN vehicles v ON b.vehicle_id = v.id
          LEFT JOIN users admin ON p.processed_by = admin.id
          WHERE p.id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $payment_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$payment = mysqli_fetch_assoc($result);

if (!$payment) {
    echo '<div class="alert alert-danger">Payment not found.</div>';
    exit();
}

// Format payment method display
$payment_methods = [
    'bank_transfer' => 'Bank Transfer',
    'sms_payment' => 'SMS Payment',
    'online_payment' => 'Online Payment',
    'cash' => 'Cash'
];

$payment_method_display = $payment_methods[$payment['payment_method']] ?? ucwords(str_replace('_', ' ', $payment['payment_method']));

// Format status display
$status_classes = [
    'pending' => 'bg-warning',
    'completed' => 'bg-success',
    'failed' => 'bg-danger',
    'cancelled' => 'bg-secondary'
];
$status_class = $status_classes[$payment['status']] ?? 'bg-secondary';
?>

<div class="row">
    <!-- Payment Information -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Information</h6>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-5"><strong>Payment ID:</strong></div>
                    <div class="col-7">#<?php echo $payment['id']; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-5"><strong>Status:</strong></div>
                    <div class="col-7">
                        <span class="badge <?php echo $status_class; ?>">
                            <?php echo ucfirst($payment['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-5"><strong>Method:</strong></div>
                    <div class="col-7"><?php echo $payment_method_display; ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-5"><strong>Amount:</strong></div>
                    <div class="col-7">
                        <span class="h5 text-success mb-0">
                            <?php echo formatCurrency($payment['amount']); ?>
                        </span>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-5"><strong>Reference:</strong></div>
                    <div class="col-7">
                        <code><?php echo htmlspecialchars($payment['reference_number']); ?></code>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-5"><strong>Payment Date:</strong></div>
                    <div class="col-7"><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-5"><strong>Created:</strong></div>
                    <div class="col-7"><?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?></div>
                </div>
                <?php if ($payment['processed_by']): ?>
                <div class="row mb-2">
                    <div class="col-5"><strong>Processed By:</strong></div>
                    <div class="col-7"><?php echo htmlspecialchars($payment['admin_first_name'] . ' ' . $payment['admin_last_name']); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-5"><strong>Processed At:</strong></div>
                    <div class="col-7"><?php echo date('M j, Y g:i A', strtotime($payment['processed_at'])); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($payment['admin_notes']): ?>
                <div class="row mb-2">
                    <div class="col-5"><strong>Admin Notes:</strong></div>
                    <div class="col-7"><?php echo nl2br(htmlspecialchars($payment['admin_notes'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Customer Information -->
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-user me-2"></i>Customer Information</h6>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-5"><strong>Name:</strong></div>
                    <div class="col-7"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-5"><strong>Email:</strong></div>
                    <div class="col-7"><?php echo htmlspecialchars($payment['email']); ?></div>
                </div>
                <?php if ($payment['phone']): ?>
                <div class="row mb-2">
                    <div class="col-5"><strong>Phone:</strong></div>
                    <div class="col-7"><?php echo htmlspecialchars($payment['phone']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Vehicle Information -->
        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-car me-2"></i>Vehicle Information</h6>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-5"><strong>Vehicle:</strong></div>
                    <div class="col-7"><?php echo htmlspecialchars($payment['vehicle_name']); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-5"><strong>Model:</strong></div>
                    <div class="col-7"><?php echo htmlspecialchars($payment['model']); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-5"><strong>Registration:</strong></div>
                    <div class="col-7"><?php echo htmlspecialchars($payment['registration_number']); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-5"><strong>Type:</strong></div>
                    <div class="col-7"><?php echo htmlspecialchars($payment['vehicle_type']); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-5"><strong>Rate/Day:</strong></div>
                                            <div class="col-7"><?php echo formatCurrency($payment['rate_per_day']); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Booking Details -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0"><i class="fas fa-calendar me-2"></i>Booking Details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="h4 text-primary mb-1"><?php echo $payment['total_days']; ?></div>
                            <small class="text-muted">Total Days</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="h6 mb-1">Start Date</div>
                            <small class="text-muted"><?php echo date('M j, Y', strtotime($payment['start_date'])); ?></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="h6 mb-1">End Date</div>
                            <small class="text-muted"><?php echo date('M j, Y', strtotime($payment['end_date'])); ?></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="h6 mb-1">Total Amount</div>
                            <small class="text-success"><?php echo formatCurrency($payment['rate_per_day'] * $payment['total_days']); ?></small>
                        </div>
                    </div>
                </div>
                
                <?php if ($payment['pickup_location'] || $payment['dropoff_location']): ?>
                <hr>
                <div class="row">
                    <?php if ($payment['pickup_location']): ?>
                    <div class="col-md-6">
                        <strong>Pickup Location:</strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($payment['pickup_location']); ?></small>
                    </div>
                    <?php endif; ?>
                    <?php if ($payment['dropoff_location']): ?>
                    <div class="col-md-6">
                        <strong>Dropoff Location:</strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($payment['dropoff_location']); ?></small>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($payment['special_requests']): ?>
                <hr>
                <div class="row">
                    <div class="col-12">
                        <strong>Special Requests:</strong><br>
                        <small class="text-muted"><?php echo nl2br(htmlspecialchars($payment['special_requests'])); ?></small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Receipt File -->
<?php if ($payment['receipt_file']): ?>
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Payment Receipt</h6>
            </div>
            <div class="card-body text-center">
                <?php
                $file_extension = strtolower(pathinfo($payment['receipt_file'], PATHINFO_EXTENSION));
                if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                    <img src="../<?php echo htmlspecialchars($payment['receipt_file']); ?>" 
                         alt="Payment Receipt" class="img-fluid" style="max-height: 300px;">
                <?php else: ?>
                    <div class="p-4">
                        <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                        <p class="mb-2">PDF Receipt File</p>
                        <a href="../<?php echo htmlspecialchars($payment['receipt_file']); ?>" 
                           target="_blank" class="btn btn-primary btn-sm">
                            <i class="fas fa-download me-1"></i>Download Receipt
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
