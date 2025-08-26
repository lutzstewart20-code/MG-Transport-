<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get date range for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Fetch revenue data
$revenue_query = "SELECT 
    DATE(created_at) as date,
    SUM(total_amount) as daily_revenue,
    COUNT(*) as total_bookings,
    SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as confirmed_revenue,
    SUM(CASE WHEN payment_status = 'pending' THEN total_amount ELSE 0 END) as pending_revenue
    FROM bookings 
    WHERE created_at BETWEEN ? AND ? 
    GROUP BY DATE(created_at)
    ORDER BY date";

$stmt = $conn->prepare($revenue_query);
if ($stmt) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $revenue_result = $stmt->get_result();
} else {
    $revenue_result = null;
    error_log("Failed to prepare revenue query: " . $conn->error);
}

// Fetch monthly revenue data for charts
$monthly_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    SUM(total_amount) as monthly_revenue,
    COUNT(*) as total_bookings
    FROM bookings 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month";

$monthly_result = $conn->query($monthly_query);
if (!$monthly_result) {
    error_log("Failed to execute monthly query: " . $conn->error);
    $monthly_result = null;
}

// Fetch payment method statistics
$payment_methods_query = "SELECT 
    payment_method,
    COUNT(*) as count,
    SUM(total_amount) as total_amount
    FROM bookings 
    WHERE created_at BETWEEN ? AND ? AND payment_status = 'paid'
    GROUP BY payment_method";

$stmt = $conn->prepare($payment_methods_query);
if ($stmt) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $payment_methods_result = $stmt->get_result();
} else {
    $payment_methods_result = null;
    error_log("Failed to prepare payment methods query: " . $conn->error);
}

// Calculate totals
$total_revenue = 0;
$total_bookings = 0;
$confirmed_revenue = 0;
$pending_revenue = 0;

$revenue_data = [];
if ($revenue_result) {
    while ($row = $revenue_result->fetch_assoc()) {
        $revenue_data[] = $row;
        $total_revenue += $row['daily_revenue'];
        $total_bookings += $row['total_bookings'];
        $confirmed_revenue += $row['confirmed_revenue'];
        $pending_revenue += $row['pending_revenue'];
    }
}

// Prepare chart data
$chart_labels = [];
$chart_revenue = [];
$chart_bookings = [];

if ($monthly_result) {
    while ($row = $monthly_result->fetch_assoc()) {
        $chart_labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $chart_revenue[] = $row['monthly_revenue'];
        $chart_bookings[] = $row['total_bookings'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Analytics - MG Transport Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stats-card h3 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stats-card p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
            color: #fff;
        }
        .sidebar .nav-link.active {
            background: #3498db;
            color: #fff;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-truck"></i> MG Transport
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bookings.php">
                                <i class="fas fa-calendar-check"></i> Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="vehicles.php">
                                <i class="fas fa-truck"></i> Vehicles
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="revenue-analytics.php">
                                <i class="fas fa-chart-line"></i> Revenue Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-file-alt"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-chart-line text-primary"></i> Revenue Analytics
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Date Filter -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo formatCurrency($total_revenue); ?></h3>
                            <p><i class="fas fa-money-bill-wave"></i> Total Revenue</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo number_format($total_bookings); ?></h3>
                            <p><i class="fas fa-calendar-check"></i> Total Bookings</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo formatCurrency($confirmed_revenue); ?></h3>
                            <p><i class="fas fa-check-circle"></i> Confirmed Revenue</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo formatCurrency($pending_revenue); ?></h3>
                            <p><i class="fas fa-clock"></i> Pending Revenue</p>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="chart-container">
                            <h5><i class="fas fa-chart-area"></i> Monthly Revenue Trend</h5>
                            <canvas id="revenueChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="chart-container">
                            <h5><i class="fas fa-chart-pie"></i> Payment Methods</h5>
                            <canvas id="paymentChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Revenue Table -->
                <div class="table-container">
                    <h5><i class="fas fa-table"></i> Daily Revenue Breakdown</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Revenue</th>
                                    <th>Bookings</th>
                                    <th>Confirmed Revenue</th>
                                    <th>Pending Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($revenue_data as $row): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                    <td class="text-success fw-bold"><?php echo formatCurrency($row['daily_revenue']); ?></td>
                                    <td><?php echo number_format($row['total_bookings']); ?></td>
                                    <td class="text-primary"><?php echo formatCurrency($row['confirmed_revenue']); ?></td>
                                    <td class="text-warning"><?php echo formatCurrency($row['pending_revenue']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const chartLabels = <?php echo json_encode($chart_labels); ?>;
        const chartRevenue = <?php echo json_encode($chart_revenue); ?>;
        
        if (chartLabels.length > 0 && chartRevenue.length > 0) {
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Monthly Revenue (PGK)',
                        data: chartRevenue,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'PGK ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        } else {
            // Display message when no data
            revenueCtx.font = '16px Arial';
            revenueCtx.fillStyle = '#666';
            revenueCtx.textAlign = 'center';
            revenueCtx.fillText('No revenue data available', 400, 100);
        }

        // Payment Methods Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        const paymentData = <?php echo $payment_methods_result ? json_encode($payment_methods_result->fetch_all(MYSQLI_ASSOC)) : '[]'; ?>;
        
        if (paymentData.length > 0) {
            new Chart(paymentCtx, {
                type: 'doughnut',
                data: {
                    labels: paymentData.map(item => item.payment_method),
                    datasets: [{
                        data: paymentData.map(item => item.total_amount),
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        } else {
            // Display message when no data
            paymentCtx.font = '16px Arial';
            paymentCtx.fillStyle = '#666';
            paymentCtx.textAlign = 'center';
            paymentCtx.fillText('No payment data available', 200, 100);
        }

        function exportData() {
            // Create CSV content
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Date,Revenue,Bookings,Confirmed Revenue,Pending Revenue\n";
            
            <?php foreach ($revenue_data as $row): ?>
            csvContent += "<?php echo date('M d, Y', strtotime($row['date'])); ?>,<?php echo $row['daily_revenue']; ?>,<?php echo $row['total_bookings']; ?>,<?php echo $row['confirmed_revenue']; ?>,<?php echo $row['pending_revenue']; ?>\n";
            <?php endforeach; ?>
            
            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "revenue_analytics_<?php echo date('Y-m-d'); ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
