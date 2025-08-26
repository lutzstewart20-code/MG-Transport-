<?php
require_once '../config/database.php';
require_once 'includes/security-middleware.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $receipt_id = (int)$_POST['receipt_id'];
    $booking_id = (int)$_POST['booking_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $update_receipt = "UPDATE payment_receipts SET status = 'verified', verified_at = NOW() WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_receipt);
        mysqli_stmt_bind_param($stmt, "i", $receipt_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $update_booking = "UPDATE bookings SET status = 'confirmed', payment_status = 'paid' WHERE id = ?";
            $stmt2 = mysqli_prepare($conn, $update_booking);
            mysqli_stmt_bind_param($stmt2, "i", $booking_id);
            mysqli_stmt_execute($stmt2);
            
            redirectWithMessage('sms-payments.php', 'Receipt verified and booking confirmed!', 'success');
        } else {
            redirectWithMessage('sms-payments.php', 'Error verifying receipt.', 'error');
        }
    } elseif ($action === 'reject') {
        $update_receipt = "UPDATE payment_receipts SET status = 'rejected', verified_at = NOW() WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_receipt);
        mysqli_stmt_bind_param($stmt, "i", $receipt_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $update_booking = "UPDATE bookings SET status = 'cancelled', payment_status = 'failed' WHERE id = ?";
            $stmt2 = mysqli_prepare($conn, $update_booking);
            mysqli_stmt_bind_param($stmt2, "i", $booking_id);
            mysqli_stmt_execute($stmt2);
            
            redirectWithMessage('sms-payments.php', 'Receipt rejected and booking cancelled.', 'warning');
        } else {
            redirectWithMessage('sms-payments.php', 'Error rejecting receipt.', 'error');
        }
    }
}

// Get SMS payment receipts
$receipts_query = "SELECT pr.*, b.id as booking_id, b.total_amount, b.payment_method, b.status as booking_status,
                   v.name as vehicle_name, u.first_name, u.last_name, u.email, u.phone
                   FROM payment_receipts pr
                   JOIN bookings b ON pr.booking_id = b.id
                   JOIN vehicles v ON b.vehicle_id = v.id
                   JOIN users u ON b.user_id = u.id
                   ORDER BY pr.created_at DESC";
$receipts_result = mysqli_query($conn, $receipts_query);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total_receipts,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
                FROM payment_receipts";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

include 'includes/header.php';
?>

<div class="container-fluid admin-container">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-users me-2 text-primary"></i>Customer SMS Payment & Booking Management
                    </h2>
                    <p class="text-muted mb-0">Review customer SMS payments, verify receipts, and confirm vehicle bookings</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="check_database_status.php" class="btn btn-outline-warning btn-sm" target="_blank">
                        <i class="fas fa-database me-2"></i>Check Database
                    </a>
                    <a href="insert_sample_receipt.php" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-plus me-2"></i>Add Sample Receipt
                    </a>
                    <a href="dashboard.php" class="btn btn-modern btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>

            <?php displayMessage(); ?>

            <!-- Alert when no data exists -->
            <?php if (!$receipts_result || mysqli_num_rows($receipts_result) == 0): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Getting Started:</strong> No SMS payment receipts found yet. To see customer details and test the system, 
                    <a href="insert_sample_receipt.php" class="alert-link">click here to add sample data</a> or 
                    <a href="check_database_status.php" class="alert-link" target="_blank">check your database status</a>.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card h-100">
                        <div class="stats-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['pending_count']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Pending Verification</p>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card success h-100">
                        <div class="stats-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['verified_count']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Confirmed Bookings</p>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card warning h-100">
                        <div class="stats-icon">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['rejected_count']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Cancelled Bookings</p>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card info h-100">
                        <div class="stats-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['total_receipts']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Total SMS Bookings</p>
                    </div>
                </div>
            </div>

            <!-- SMS Payment Receipts -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-calendar-check me-2"></i>Customer Bookings with SMS Payments
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($receipts_result && mysqli_num_rows($receipts_result) > 0): ?>
                        <div class="row">
                            <?php while ($receipt = mysqli_fetch_assoc($receipts_result)): ?>
                            <div class="col-lg-12 mb-4">
                                <div class="card receipt-card h-100">
                                    <div class="card-header bg-light">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0 fw-bold">
                                                    <i class="fas fa-calendar me-2"></i>Booking #<?php echo $receipt['booking_id']; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    Receipt #<?php echo $receipt['id']; ?> | 
                                                    <?php echo htmlspecialchars($receipt['payment_method']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?php 
                                                    echo $receipt['status'] === 'pending' ? 'warning' : 
                                                        ($receipt['status'] === 'verified' ? 'success' : 'danger'); 
                                                ?> rounded-pill me-2">
                                                    Receipt: <?php echo ucfirst($receipt['status']); ?>
                                                </span>
                                                <span class="badge bg-<?php 
                                                    echo $receipt['booking_status'] === 'pending' ? 'warning' : 
                                                        ($receipt['booking_status'] === 'confirmed' ? 'success' : 
                                                        ($receipt['booking_status'] === 'active' ? 'info' : 
                                                        ($receipt['booking_status'] === 'completed' ? 'secondary' : 'danger'))); 
                                                ?> rounded-pill">
                                                    Booking: <?php echo ucfirst($receipt['booking_status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body">
                                        <div class="row">
                                            <!-- Left Column: Customer & Booking Details -->
                                            <div class="col-lg-8">
                                                <!-- Customer Information -->
                                                <div class="mb-4">
                                                    <h6 class="fw-bold text-primary border-bottom pb-2">
                                                        <i class="fas fa-user me-2"></i>Customer Information
                                                    </h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <strong>Name:</strong><br>
                                                            <span class="text-primary"><?php echo htmlspecialchars($receipt['first_name'] . ' ' . $receipt['last_name']); ?></span>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Email:</strong><br>
                                                            <span><?php echo htmlspecialchars($receipt['email']); ?></span>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Phone:</strong><br>
                                                            <span><?php echo htmlspecialchars($receipt['phone']); ?></span>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Vehicle:</strong><br>
                                                            <span class="text-info"><?php echo htmlspecialchars($receipt['vehicle_name']); ?></span>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Total Amount:</strong><br>
                                                            <span class="text-success fw-bold"><?php echo formatCurrency($receipt['total_amount']); ?></span>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Payment Method:</strong><br>
                                                            <span class="badge bg-primary"><?php echo ucwords(str_replace('_', ' ', $receipt['payment_method'])); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Right Column: Receipt & Actions -->
                                            <div class="col-lg-4">
                                                <!-- SMS Payment Receipt -->
                                                <div class="mb-4">
                                                    <h6 class="fw-bold text-dark border-bottom pb-2">
                                                        <i class="fas fa-receipt me-2"></i>SMS Payment Receipt
                                                    </h6>
                                                    <?php if (!empty($receipt['receipt_file']) && file_exists('../uploads/payment_receipts/' . $receipt['receipt_file'])): ?>
                                                        <div class="receipt-image-container text-center">
                                                            <img src="../uploads/payment_receipts/<?php echo htmlspecialchars($receipt['receipt_file']); ?>" 
                                                                 alt="Payment Receipt" 
                                                                 class="img-fluid receipt-image rounded border"
                                                                 style="max-height: 200px; width: 100%; object-fit: cover;">
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="alert alert-warning py-2">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                                            Receipt image not found
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Action Buttons -->
                                                <div class="mb-4">
                                                    <h6 class="fw-bold text-dark border-bottom pb-2">
                                                        <i class="fas fa-cogs me-2"></i>Actions
                                                    </h6>
                                                    <?php if ($receipt['status'] === 'pending'): ?>
                                                        <div class="d-grid gap-2 mb-3">
                                                            <form method="POST">
                                                                <input type="hidden" name="receipt_id" value="<?php echo $receipt['id']; ?>">
                                                                <input type="hidden" name="booking_id" value="<?php echo $receipt['booking_id']; ?>">
                                                                <input type="hidden" name="action" value="approve">
                                                                <button type="submit" class="btn btn-success w-100" 
                                                                        onclick="return confirm('Verify receipt and confirm this booking?')">
                                                                    <i class="fas fa-check me-2"></i>Verify & Confirm Booking
                                                                </button>
                                                            </form>
                                                            <form method="POST">
                                                                <input type="hidden" name="receipt_id" value="<?php echo $receipt['id']; ?>">
                                                                <input type="hidden" name="booking_id" value="<?php echo $receipt['booking_id']; ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <button type="submit" class="btn btn-danger w-100"
                                                                        onclick="return confirm('Reject receipt and cancel this booking?')">
                                                                    <i class="fas fa-times me-2"></i>Reject & Cancel
                                                                </button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
                                <h5 class="text-muted mb-3">No SMS Payment Bookings</h5>
                                <p class="text-muted mb-4">No customer bookings with SMS payments have been found yet. Please add some sample data to get started.</p>
                                <div class="empty-state-actions">
                                    <a href="insert_sample_receipt.php" class="btn btn-success me-2">
                                        <i class="fas fa-plus me-2"></i>Add Sample Data
                                    </a>
                                    <a href="dashboard.php" class="btn btn-primary">
                                        <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<style>
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    border-left: 5px solid #fbbf24;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.stat-card.success {
    border-left-color: #10b981;
}

.stat-card.warning {
    border-left-color: #f59e0b;
}

.stat-card.info {
    border-left-color: #3b82f6;
}

.stats-icon {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    color: white;
}

.stat-card.success .stats-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-card.warning .stats-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-card.info .stats-icon {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.stat-number {
    font-size: 2.2rem;
    font-weight: 800;
    color: #1e3a8a;
    line-height: 1;
}

.receipt-card {
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
}

.receipt-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.receipt-image-container {
    position: relative;
}

.receipt-image {
    transition: all 0.3s ease;
}

.receipt-image:hover {
    transform: scale(1.02);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.empty-state {
    padding: 3rem 1rem;
}

.empty-state-actions {
    margin-top: 2rem;
}

.border-bottom {
    border-bottom: 2px solid #e5e7eb !important;
}
</style>
