<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    header('Location: ../login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_fuel_type':
                $fuel_type = sanitizeInput($_POST['fuel_type']);
                $query = "INSERT INTO vehicle_specifications (type, value) VALUES ('fuel_type', ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "s", $fuel_type);
                mysqli_stmt_execute($stmt);
                break;
                
            case 'add_body_color':
                $body_color = sanitizeInput($_POST['body_color']);
                $query = "INSERT INTO vehicle_specifications (type, value) VALUES ('body_color', ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "s", $body_color);
                mysqli_stmt_execute($stmt);
                break;
                
            case 'add_transmission':
                $transmission = sanitizeInput($_POST['transmission']);
                $query = "INSERT INTO vehicle_specifications (type, value) VALUES ('transmission', ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "s", $transmission);
                mysqli_stmt_execute($stmt);
                break;
                
            case 'add_seat_capacity':
                $seat_capacity = sanitizeInput($_POST['seat_capacity']);
                $query = "INSERT INTO vehicle_specifications (type, value) VALUES ('seat_capacity', ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "s", $seat_capacity);
                mysqli_stmt_execute($stmt);
                break;
                
            case 'delete_spec':
                $id = intval($_POST['id']);
                $query = "DELETE FROM vehicle_specifications WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                break;
        }
        
        // Redirect to refresh the page
        header('Location: vehicle-specifications.php');
        exit();
    }
}

// Get existing specifications
function getSpecificationsByType($conn, $type) {
    $query = "SELECT * FROM vehicle_specifications WHERE type = ? ORDER BY value";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $type);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$fuel_types = getSpecificationsByType($conn, 'fuel_type');
$body_colors = getSpecificationsByType($conn, 'body_color');
$transmissions = getSpecificationsByType($conn, 'transmission');
$seat_capacities = getSpecificationsByType($conn, 'seat_capacity');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Specifications - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-color);">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <img src="../assets/images/MG Logo.jpg" alt="MG Transport Services">
                <span class="ms-2">MG Transport Services Admin</span>
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
                        <a class="nav-link" href="vehicle-specifications.php">Specifications</a>
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
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">Settings</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-cogs me-2"></i>Vehicle Specifications Management
                    </h1>
                </div>

                <!-- Fuel Types -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-gas-pump me-2"></i>Fuel Types</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="mb-3">
                                    <input type="hidden" name="action" value="add_fuel_type">
                                    <div class="input-group">
                                        <input type="text" name="fuel_type" class="form-control" placeholder="Add fuel type..." required>
                                        <button type="submit" class="btn btn-primary">Add</button>
                                    </div>
                                </form>
                                <div class="list-group">
                                    <?php foreach ($fuel_types as $fuel): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($fuel['value']); ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_spec">
                                                <input type="hidden" name="id" value="<?php echo $fuel['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Body Colors -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-palette me-2"></i>Body Colors</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="mb-3">
                                    <input type="hidden" name="action" value="add_body_color">
                                    <div class="input-group">
                                        <input type="text" name="body_color" class="form-control" placeholder="Add body color..." required>
                                        <button type="submit" class="btn btn-primary">Add</button>
                                    </div>
                                </form>
                                <div class="list-group">
                                    <?php foreach ($body_colors as $color): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($color['value']); ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_spec">
                                                <input type="hidden" name="id" value="<?php echo $color['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transmissions -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Transmissions</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="mb-3">
                                    <input type="hidden" name="action" value="add_transmission">
                                    <div class="input-group">
                                        <input type="text" name="transmission" class="form-control" placeholder="Add transmission..." required>
                                        <button type="submit" class="btn btn-primary">Add</button>
                                    </div>
                                </form>
                                <div class="list-group">
                                    <?php foreach ($transmissions as $transmission): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($transmission['value']); ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_spec">
                                                <input type="hidden" name="id" value="<?php echo $transmission['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seat Capacity -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Seat Capacity</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="mb-3">
                                    <input type="hidden" name="action" value="add_seat_capacity">
                                    <div class="input-group">
                                        <input type="text" name="seat_capacity" class="form-control" placeholder="Add seat capacity..." required>
                                        <button type="submit" class="btn btn-primary">Add</button>
                                    </div>
                                </form>
                                <div class="list-group">
                                    <?php foreach ($seat_capacities as $capacity): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($capacity['value']); ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_spec">
                                                <input type="hidden" name="id" value="<?php echo $capacity['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
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