<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Get date range for reports
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get booking statistics
$stats_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_bookings,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as avg_booking_value
    FROM bookings 
    WHERE created_at BETWEEN ? AND ?";

$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get top vehicles by bookings
$top_vehicles_query = "SELECT v.name, v.registration_number, COUNT(b.id) as booking_count, SUM(b.total_amount) as revenue
    FROM vehicles v
    LEFT JOIN bookings b ON v.id = b.vehicle_id 
    AND b.created_at BETWEEN ? AND ?
    GROUP BY v.id
    ORDER BY booking_count DESC
    LIMIT 5";

$top_vehicles_stmt = mysqli_prepare($conn, $top_vehicles_query);
mysqli_stmt_bind_param($top_vehicles_stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($top_vehicles_stmt);
$top_vehicles_result = mysqli_stmt_get_result($top_vehicles_stmt);

// Get monthly revenue data for chart
$monthly_revenue_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    SUM(total_amount) as revenue,
    COUNT(*) as bookings
    FROM bookings 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month";

$monthly_revenue_result = mysqli_query($conn, $monthly_revenue_query);

// Get recent bookings
$recent_bookings_query = "SELECT b.*, v.name as vehicle_name, u.first_name, u.last_name
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.id
    JOIN users u ON b.user_id = u.id
    WHERE b.created_at BETWEEN ? AND ?
    ORDER BY b.created_at DESC
    LIMIT 10";

$recent_bookings_stmt = mysqli_prepare($conn, $recent_bookings_query);
mysqli_stmt_bind_param($recent_bookings_stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($recent_bookings_stmt);
$recent_bookings_result = mysqli_stmt_get_result($recent_bookings_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - MG Transport Services Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-compact.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <img src="../assets/images/MG Logo.jpg" alt="MG Transport Services" class="me-2">
                <span class="d-none d-md-inline">MG Transport Services Admin</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="vehicles.php">Vehicles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bookings.php">Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="maintenance.php">Maintenance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">Settings</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Reports & Analytics</h1>
                    <div class="btn-group" role="group">
                        <button class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                        <a href="export-reports.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-outline-success">
                            <i class="fas fa-download"></i> Export Data
                        </a>
                    </div>
                </div>
                <?php displayMessage(); ?>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Apply Filter
                                    </button>
                                    <a href="reports.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Bookings</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_bookings']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Total Revenue</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($stats['total_revenue']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Average Booking Value</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($stats['avg_booking_value']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Completion Rate</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $stats['total_bookings'] > 0 ? round(($stats['completed_bookings'] / $stats['total_bookings']) * 100, 1) : 0; ?>%
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-percentage fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Revenue Chart -->
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Monthly Revenue Trend</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart" width="400" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Vehicles -->
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Top Vehicles by Bookings</h6>
                    </div>
                    <div class="card-body">
                        <?php while ($vehicle = mysqli_fetch_assoc($top_vehicles_result)): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo htmlspecialchars($vehicle['name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($vehicle['registration_number']); ?></small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?php echo $vehicle['booking_count']; ?> bookings</div>
                                <small class="text-muted"><?php echo formatCurrency($vehicle['revenue']); ?></small>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Bookings</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Booking ID</th>
                                        <th>Customer</th>
                                        <th>Vehicle</th>
                                        <th>Dates</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = mysqli_fetch_assoc($recent_bookings_result)): ?>
                                    <tr>
                                        <td>#<?php echo $booking['id']; ?></td>
                                        <td><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['vehicle_name']); ?></td>
                                        <td><?php echo formatDate($booking['start_date']) . ' - ' . formatDate($booking['end_date']); ?></td>
                                        <td><?php echo formatCurrency($booking['total_amount']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $booking['status'] === 'active' ? 'success' : 
                                                    ($booking['status'] === 'confirmed' ? 'primary' : 
                                                    ($booking['status'] === 'completed' ? 'info' : 
                                                    ($booking['status'] === 'cancelled' ? 'danger' : 'secondary'))); 
                                            ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($booking['created_at']); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    mysqli_data_seek($monthly_revenue_result, 0);
                    $labels = [];
                    $data = [];
                    while ($row = mysqli_fetch_assoc($monthly_revenue_result)) {
                        $labels[] = "'" . date('M Y', strtotime($row['month'] . '-01')) . "'";
                        $data[] = $row['revenue'];
                    }
                    echo implode(', ', $labels);
                    ?>
                ],
                datasets: [{
                    label: 'Monthly Revenue',
                    data: [<?php echo implode(', ', $data); ?>],
                    borderColor: '#FFD700',
                    backgroundColor: 'rgba(255, 215, 0, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html> 