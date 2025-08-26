<?php
define('SECURE_ACCESS', true);
require_once 'includes/security-middleware.php';

// Get overall booking statistics
$overall_stats_query = "SELECT 
                            COUNT(*) as total_bookings,
                            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
                            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
                            SUM(total_amount) as total_revenue
                        FROM bookings";
$overall_stats_result = mysqli_query($conn, $overall_stats_query);
$overall_stats = mysqli_fetch_assoc($overall_stats_result);

// Get monthly booking trends
$monthly_trends_query = "SELECT 
                            DATE_FORMAT(created_at, '%Y-%m') as month,
                            COUNT(*) as total_bookings,
                            SUM(total_amount) as monthly_revenue
                        FROM bookings 
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                        ORDER BY month DESC";
$monthly_trends_result = mysqli_query($conn, $monthly_trends_query);

// Get top performing vehicles
$top_vehicles_query = "SELECT 
                            v.name as vehicle_name,
                            v.model,
                            COUNT(b.id) as total_bookings,
                            SUM(b.total_amount) as total_revenue
                        FROM vehicles v
                        LEFT JOIN bookings b ON v.id = b.vehicle_id
                        GROUP BY v.id
                        ORDER BY total_revenue DESC
                        LIMIT 10";
$top_vehicles_result = mysqli_query($conn, $top_vehicles_query);

// Get payment method distribution
$payment_methods_query = "SELECT 
                            payment_method,
                            COUNT(*) as count,
                            SUM(total_amount) as total_amount
                        FROM bookings 
                        WHERE payment_method IS NOT NULL
                        GROUP BY payment_method
                        ORDER BY count DESC";
$payment_methods_result = mysqli_query($conn, $payment_methods_query);

include 'includes/header.php';
?>

<div class="container-fluid admin-container">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-chart-pie me-2 text-primary"></i>Booking Analytics & Insights
                    </h2>
                    <p class="text-muted mb-0">Comprehensive booking performance and trends analysis</p>
                </div>
                <a href="dashboard.php" class="btn btn-modern btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>

            <?php displayMessage(); ?>

            <!-- Overall Statistics -->
            <div class="row mb-4">
                <div class="col-xl-2 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card h-100">
                        <div class="stats-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($overall_stats['total_bookings']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Total Bookings</p>
                    </div>
                </div>
                
                <div class="col-xl-2 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card success h-100">
                        <div class="stats-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($overall_stats['confirmed_count']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Confirmed</p>
                    </div>
                </div>
                
                <div class="col-xl-2 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card warning h-100">
                        <div class="stats-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($overall_stats['pending_count']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Pending</p>
                    </div>
                </div>
                
                <div class="col-xl-2 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card info h-100">
                        <div class="stats-icon">
                            <i class="fas fa-flag-checkered"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($overall_stats['completed_count']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Completed</p>
                    </div>
                </div>
                
                <div class="col-xl-2 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card danger h-100">
                        <div class="stats-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($overall_stats['cancelled_count']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Cancelled</p>
                    </div>
                </div>
                
                <div class="col-xl-2 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card revenue h-100">
                        <div class="stats-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo formatCurrency($overall_stats['total_revenue']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Total Revenue</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Monthly Trends -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-chart-line me-2"></i>Monthly Booking Trends
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($monthly_trends_result && mysqli_num_rows($monthly_trends_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th>Bookings</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($trend = mysqli_fetch_assoc($monthly_trends_result)): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo date('M Y', strtotime($trend['month'] . '-01')); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $trend['total_bookings']; ?></span>
                                                </td>
                                                <td>
                                                    <strong class="text-success"><?php echo formatCurrency($trend['monthly_revenue']); ?></strong>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No monthly trends data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Top Performing Vehicles -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-car me-2"></i>Top Performing Vehicles
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($top_vehicles_result && mysqli_num_rows($top_vehicles_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Vehicle</th>
                                                <th>Bookings</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($vehicle = mysqli_fetch_assoc($top_vehicles_result)): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($vehicle['vehicle_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($vehicle['model']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $vehicle['total_bookings']; ?></span>
                                                </td>
                                                <td>
                                                    <strong class="text-success"><?php echo formatCurrency($vehicle['total_revenue'] ?? 0); ?></strong>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-car fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No vehicle performance data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Method Distribution -->
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-credit-card me-2"></i>Payment Method Distribution
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($payment_methods_result && mysqli_num_rows($payment_methods_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Payment Method</th>
                                                <th>Number of Bookings</th>
                                                <th>Total Amount</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_bookings = $overall_stats['total_bookings'];
                                            while ($method = mysqli_fetch_assoc($payment_methods_result)): 
                                                $percentage = $total_bookings > 0 ? round(($method['count'] / $total_bookings) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $method['count']; ?></span>
                                                </td>
                                                <td>
                                                    <strong class="text-success"><?php echo formatCurrency($method['total_amount']); ?></strong>
                                                </td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                             style="width: <?php echo $percentage; ?>%" 
                                                             aria-valuenow="<?php echo $percentage; ?>" 
                                                             aria-valuemin="0" aria-valuemax="100">
                                                            <?php echo $percentage; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No payment method data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
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

.stat-card.danger {
    border-left-color: #ef4444;
}

.stat-card.revenue {
    border-left-color: #8b5cf6;
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

.stat-card.danger .stats-icon {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.stat-card.revenue .stats-icon {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 800;
    color: #1e3a8a;
    line-height: 1;
}

.progress {
    background-color: #e5e7eb;
    border-radius: 10px;
}

.progress-bar {
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
}
</style>
