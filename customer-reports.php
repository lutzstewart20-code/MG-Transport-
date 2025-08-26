<?php
define('SECURE_ACCESS', true);
require_once 'includes/security-middleware.php';

// Get customer statistics
$stats_query = "SELECT 
                    COUNT(*) as total_customers,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count,
                    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_count
                FROM users WHERE role = 'customer'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get top customers by spending
$top_customers_query = "SELECT u.first_name, u.last_name, u.email, 
                        COUNT(b.id) as total_bookings,
                        SUM(b.total_amount) as total_spent
                        FROM users u
                        LEFT JOIN bookings b ON u.id = b.user_id
                        WHERE u.role = 'customer'
                        GROUP BY u.id
                        ORDER BY total_spent DESC
                        LIMIT 10";
$top_customers_result = mysqli_query($conn, $top_customers_query);

// Get customer registration trends
$trends_query = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as new_customers
                    FROM users 
                    WHERE role = 'customer' 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC";
$trends_result = mysqli_query($conn, $trends_query);

include 'includes/header.php';
?>

<div class="container-fluid admin-container">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-chart-bar me-2 text-primary"></i>Customer Reports & Analytics
                    </h2>
                    <p class="text-muted mb-0">Comprehensive customer insights and analytics</p>
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
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['total_customers']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Total Customers</p>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card success h-100">
                        <div class="stats-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['active_count']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Active Customers</p>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card warning h-100">
                        <div class="stats-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['inactive_count']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Inactive Customers</p>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card danger h-100">
                        <div class="stats-icon">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['suspended_count']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold">Suspended Customers</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Top Customers -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-trophy me-2"></i>Top Customers by Spending
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($top_customers_result && mysqli_num_rows($top_customers_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Bookings</th>
                                                <th>Total Spent</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($customer = mysqli_fetch_assoc($top_customers_result)): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($customer['email']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $customer['total_bookings']; ?></span>
                                                </td>
                                                <td>
                                                    <strong class="text-success"><?php echo formatCurrency($customer['total_spent'] ?? 0); ?></strong>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No customer data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Registration Trends -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-chart-line me-2"></i>Customer Registration Trends (Last 30 Days)
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($trends_result && mysqli_num_rows($trends_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>New Customers</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($trend = mysqli_fetch_assoc($trends_result)): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo formatDate($trend['date']); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo $trend['new_customers']; ?></span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No registration trends data available</p>
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

.stat-card.danger {
    border-left-color: #ef4444;
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

.stat-card.danger .stats-icon {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.stat-number {
    font-size: 2.2rem;
    font-weight: 800;
    color: #1e3a8a;
    line-height: 1;
}
</style>
