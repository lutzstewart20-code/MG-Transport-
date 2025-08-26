<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Handle vehicle deletion
if (isset($_POST['delete_vehicle'])) {
    $vehicle_id = (int)$_POST['vehicle_id'];
    $query = "DELETE FROM vehicles WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $vehicle_id);
    
    if (mysqli_stmt_execute($stmt)) {
        redirectWithMessage('vehicles.php', 'Vehicle deleted successfully.', 'success');
    } else {
        redirectWithMessage('vehicles.php', 'Error deleting vehicle.', 'error');
    }
}

// Get all vehicles
$query = "SELECT * FROM vehicles ORDER BY name";
$vehicles = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Management - MG Transport Services Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-compact.css" rel="stylesheet">
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
                        <a class="nav-link active" href="vehicles.php">Vehicles</a>
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
                    <h1 class="h3">Vehicle Management</h1>
                    <a href="add-vehicle.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Vehicle
                    </a>
                </div>
                <?php displayMessage(); ?>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">All Vehicles</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Registration</th>
                                        <th>Rate/Day</th>
                                        <th>Status</th>
                                        <th>Next Service</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($vehicle = mysqli_fetch_assoc($vehicles)): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($vehicle['image_url'])): ?>
                                                <img src="../<?php echo htmlspecialchars($vehicle['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($vehicle['name']); ?>"
                                                     style="width: 60px; height: 40px; object-fit: cover; border-radius: 5px;"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                <div style="display: none; width: 60px; height: 40px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #6c757d;">
                                                    <i class="fas fa-car"></i>
                                                </div>
                                            <?php else: ?>
                                                <div style="width: 60px; height: 40px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #6c757d;">
                                                    <i class="fas fa-car"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($vehicle['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($vehicle['model']); ?> (<?php echo $vehicle['year']; ?>)</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars(formatVehicleType($vehicle['vehicle_type'])); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($vehicle['registration_number']); ?></td>
                                        <td><?php echo formatCurrency($vehicle['rate_per_day']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $vehicle['status'] === 'available' ? 'success' : ($vehicle['status'] === 'booked' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($vehicle['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($vehicle['next_service_date']): ?>
                                                <?php 
                                                $service_date = new DateTime($vehicle['next_service_date']);
                                                $today = new DateTime();
                                                $days_until = $today->diff($service_date)->days;
                                                $badge_class = $days_until <= 7 ? 'danger' : ($days_until <= 30 ? 'warning' : 'success');
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <?php echo formatDate($vehicle['next_service_date']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="edit-vehicle.php?id=<?php echo $vehicle['id']; ?>" 
                                                   class="btn btn-sm btn-info" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="view-vehicle.php?id=<?php echo $vehicle['id']; ?>" 
                                                   class="btn btn-sm btn-secondary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="confirmDelete(<?php echo $vehicle['id']; ?>, '<?php echo htmlspecialchars($vehicle['name']); ?>')" 
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the vehicle "<span id="vehicleName"></span>"?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="vehicle_id" id="vehicleId">
                        <button type="submit" name="delete_vehicle" class="btn btn-danger">Delete Vehicle</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(vehicleId, vehicleName) {
            document.getElementById('vehicleId').value = vehicleId;
            document.getElementById('vehicleName').textContent = vehicleName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html> 