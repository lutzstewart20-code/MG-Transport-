<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Handle search
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

if ($status_filter) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get customers with booking counts
$customers_query = "SELECT u.*, 
                    COUNT(b.id) as total_bookings,
                    SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                    SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
                    SUM(b.total_amount) as total_spent
                    FROM users u
                    LEFT JOIN bookings b ON u.id = b.user_id
                    $where_clause
                    GROUP BY u.id
                    ORDER BY u.created_at DESC";

$stmt = mysqli_prepare($conn, $customers_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$customers_result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Search - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-compact.css" rel="stylesheet">
    <style>
        .customer-card {
            border-left: 4px solid #17a2b8;
            transition: all 0.3s ease;
        }
        
        .customer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .customer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }
        
        .search-box {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <div class="d-flex align-items-center">
                    <img src="../assets/images/MG Logo.jpg" alt="MG Transport Services" class="me-3" style="width: 50px; height: 50px; border-radius: 50%; background: #fbbf24;">
                    <div class="d-none d-md-block">
                        <div class="text-xl fw-bold text-white">MG TRANSPORT SERVICES</div>
                        <div class="text-sm text-warning">ADMIN PANEL</div>
                    </div>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="vehicles.php">
                            <i class="fas fa-car me-2"></i>Vehicles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bookings.php">
                            <i class="fas fa-calendar-check me-2"></i>Bookings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="users.php">
                            <i class="fas fa-users me-2"></i>Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>Admin
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid admin-container">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-search me-2 text-primary"></i>Customer Search</h2>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <!-- Search Form -->
                <div class="card search-box mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search Customers</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by name, email, or phone...">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status Filter</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Search
                                    </button>
                                    <a href="customer-search.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-users fa-2x text-primary mb-2"></i>
                            <h4 class="mb-1">
                                <?php 
                                $total_customers = 0;
                                if ($customers_result) {
                                    $total_customers = mysqli_num_rows($customers_result);
                                }
                                echo $total_customers;
                                ?>
                            </h4>
                            <small class="text-muted">Total Customers</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-user-check fa-2x text-success mb-2"></i>
                            <h4 class="mb-1">
                                <?php 
                                $active_customers = 0;
                                if ($customers_result) {
                                    mysqli_data_seek($customers_result, 0);
                                    while ($customer = mysqli_fetch_assoc($customers_result)) {
                                        if (isset($customer['status']) && $customer['status'] === 'active') {
                                            $active_customers++;
                                        } elseif (!isset($customer['status'])) {
                                            // If status column doesn't exist, assume active
                                            $active_customers++;
                                        }
                                    }
                                }
                                echo $active_customers;
                                ?>
                            </h4>
                            <small class="text-muted">Active Customers</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-calendar-check fa-2x text-info mb-2"></i>
                            <h4 class="mb-1">
                                <?php 
                                $total_bookings = 0;
                                if ($customers_result) {
                                    mysqli_data_seek($customers_result, 0);
                                    while ($customer = mysqli_fetch_assoc($customers_result)) {
                                        $total_bookings += $customer['total_bookings'];
                                    }
                                }
                                echo $total_bookings;
                                ?>
                            </h4>
                            <small class="text-muted">Total Bookings</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-dollar-sign fa-2x text-warning mb-2"></i>
                            <h4 class="mb-1">
                                <?php 
                                $total_revenue = 0;
                                if ($customers_result) {
                                    mysqli_data_seek($customers_result, 0);
                                    while ($customer = mysqli_fetch_assoc($customers_result)) {
                                        $total_revenue += $customer['total_spent'] ?? 0;
                                    }
                                }
                                echo formatCurrency($total_revenue);
                                ?>
                            </h4>
                            <small class="text-muted">Total Revenue</small>
                        </div>
                    </div>
                </div>

                <!-- Customers List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Customer List</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($customers_result && mysqli_num_rows($customers_result) > 0): ?>
                            <div class="row">
                                <?php 
                                mysqli_data_seek($customers_result, 0);
                                while ($customer = mysqli_fetch_assoc($customers_result)): 
                                ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="card customer-card">
                                        <div class="card-header">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">Customer #<?php echo $customer['id']; ?></h6>
                                                <span class="status-badge bg-<?php 
                                                    $status = isset($customer['status']) ? $customer['status'] : 'active';
                                                    echo $status === 'active' ? 'success' : 
                                                        ($status === 'inactive' ? 'secondary' : 'danger'); 
                                                ?> text-white">
                                                    <?php echo ucfirst(isset($customer['status']) ? $customer['status'] : 'Active'); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <!-- Customer Info -->
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="customer-avatar me-3">
                                                    <?php echo strtoupper(substr($customer['first_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($customer['email']); ?></small><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($customer['phone']); ?></small>
                                                </div>
                                            </div>

                                            <!-- Customer Stats -->
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <div class="text-center">
                                                        <h6 class="fw-bold text-primary"><?php echo $customer['total_bookings']; ?></h6>
                                                        <small class="text-muted">Total Bookings</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="text-center">
                                                        <h6 class="fw-bold text-success"><?php echo $customer['confirmed_bookings']; ?></h6>
                                                        <small class="text-muted">Confirmed</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <div class="text-center">
                                                        <h6 class="fw-bold text-warning"><?php echo $customer['pending_bookings']; ?></h6>
                                                        <small class="text-muted">Pending</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="text-center">
                                                        <h6 class="fw-bold text-info"><?php echo formatCurrency($customer['total_spent'] ?? 0); ?></h6>
                                                        <small class="text-muted">Total Spent</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Registration Info -->
                                            <div class="mb-3">
                                                <small class="text-muted">Registered:</small><br>
                                                <strong><?php echo formatDate($customer['created_at']); ?></strong>
                                            </div>

                                            <?php if (isset($customer['last_login']) && $customer['last_login']): ?>
                                            <div class="mb-3">
                                                <small class="text-muted">Last Login:</small><br>
                                                <strong><?php echo formatDate($customer['last_login']); ?></strong>
                                            </div>
                                            <?php elseif (!isset($customer['last_login'])): ?>
                                            <div class="mb-3">
                                                <small class="text-muted">Last Login:</small><br>
                                                <strong class="text-muted">Not tracked</strong>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Action Buttons -->
                                            <div class="d-flex gap-2">
                                                <a href="view-user.php?id=<?php echo $customer['id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>View Details
                                                </a>
                                                <a href="customer-bookings.php?user_id=<?php echo $customer['id']; ?>" 
                                                   class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-calendar me-1"></i>View Bookings
                                                </a>
                                                <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" 
                                                   class="btn btn-outline-success btn-sm">
                                                    <i class="fas fa-envelope me-1"></i>Email
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Customers Found</h5>
                                <p class="text-muted">
                                    <?php if ($search || $status_filter): ?>
                                        Try adjusting your search criteria.
                                    <?php else: ?>
                                        No customers have registered yet.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 