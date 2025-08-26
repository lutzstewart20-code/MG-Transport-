<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin($conn)) {
    header('Location: ../login.php');
    exit();
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_maintenance':
                $vehicle_id = sanitizeInput($_POST['vehicle_id']);
                $service_type = sanitizeInput($_POST['service_type']);
                $scheduled_date = sanitizeInput($_POST['scheduled_date']);
                $description = sanitizeInput($_POST['description']);
                $cost = sanitizeInput($_POST['cost']);
                $mechanic_name = sanitizeInput($_POST['mechanic_name']);
                
                // Validate required fields
                if (empty($vehicle_id) || empty($service_type) || empty($scheduled_date)) {
                    $message = 'Please fill in all required fields.';
                    $message_type = 'danger';
                } else {
                    $query = "INSERT INTO maintenance_schedule (vehicle_id, service_type, scheduled_date, description, cost, mechanic_name) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "isssds", $vehicle_id, $service_type, $scheduled_date, $description, $cost, $mechanic_name);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // Update vehicle status to maintenance
                        $update_query = "UPDATE vehicles SET status = 'maintenance' WHERE id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_query);
                        mysqli_stmt_bind_param($update_stmt, "i", $vehicle_id);
                        mysqli_stmt_execute($update_stmt);
                        
                        $message = 'Maintenance schedule added successfully.';
                        $message_type = 'success';
                        
                        // Create notification
                        createNotification(1, 'Maintenance Scheduled', "New maintenance scheduled for vehicle ID: $vehicle_id", 'info', $conn);
                    } else {
                        $message = 'Error adding maintenance schedule.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'update_status':
                $maintenance_id = sanitizeInput($_POST['maintenance_id']);
                $status = sanitizeInput($_POST['status']);
                $completed_date = null;
                
                if ($status === 'completed') {
                    $completed_date = date('Y-m-d');
                }
                
                $query = "UPDATE maintenance_schedule SET status = ?, completed_date = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "ssi", $status, $completed_date, $maintenance_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    // If completed, update vehicle status back to available
                    if ($status === 'completed') {
                        $maintenance_query = "SELECT vehicle_id FROM maintenance_schedule WHERE id = ?";
                        $maintenance_stmt = mysqli_prepare($conn, $maintenance_query);
                        mysqli_stmt_bind_param($maintenance_stmt, "i", $maintenance_id);
                        mysqli_stmt_execute($maintenance_stmt);
                        $result = mysqli_stmt_get_result($maintenance_stmt);
                        $maintenance = mysqli_fetch_assoc($result);
                        
                        if ($maintenance) {
                            $vehicle_id = $maintenance['vehicle_id'];
                            $update_query = "UPDATE vehicles SET status = 'available' WHERE id = ?";
                            $update_stmt = mysqli_prepare($conn, $update_query);
                            mysqli_stmt_bind_param($update_stmt, "i", $vehicle_id);
                            mysqli_stmt_execute($update_stmt);
                        }
                    }
                    
                    $message = 'Maintenance status updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating maintenance status.';
                    $message_type = 'danger';
                }
                break;
                
            case 'delete_maintenance':
                $maintenance_id = sanitizeInput($_POST['maintenance_id']);
                
                $query = "DELETE FROM maintenance_schedule WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "i", $maintenance_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Maintenance schedule deleted successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Error deleting maintenance schedule.';
                    $message_type = 'danger';
                }
                break;
        }
    }
}

// Get maintenance schedules with vehicle details
$maintenance_query = "SELECT ms.*, v.name as vehicle_name, v.registration_number, v.model, v.year
                     FROM maintenance_schedule ms
                     JOIN vehicles v ON ms.vehicle_id = v.id
                     ORDER BY ms.scheduled_date ASC";
$maintenance_result = mysqli_query($conn, $maintenance_query);

// Get available vehicles for the add form
$vehicles_query = "SELECT id, name, registration_number, model FROM vehicles WHERE status != 'maintenance' ORDER BY name";
$vehicles_result = mysqli_query($conn, $vehicles_query);

// Get maintenance statistics
$stats_query = "SELECT 
                    COUNT(*) as total_maintenance,
                    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(cost) as total_cost
                FROM maintenance_schedule";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get maintenance due vehicles
$maintenance_due = getMaintenanceDueVehicles($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Management - MG Transport Services Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.15rem rgba(251, 191, 36, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.375rem;
            font-size: 0.875rem;
        }
        
        .btn {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
        
        .btn-lg {
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
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
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bookings.php">Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="vehicles.php">Vehicles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="maintenance.php">Maintenance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-tools text-warning"></i> Maintenance Management</h2>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                <i class="fas fa-plus"></i> Schedule Maintenance
            </button>
        </div>

        <!-- Display Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_maintenance']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-tools fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Scheduled</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['scheduled']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">In Progress</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['in_progress']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-spinner fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['completed']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Cancelled</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['cancelled']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Cost</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($stats['total_cost']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance Due Alert -->
        <?php if (!empty($maintenance_due)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Maintenance Due</h6>
                <p class="mb-0">The following vehicles are due for maintenance:</p>
                <ul class="mb-0 mt-2">
                    <?php foreach ($maintenance_due as $vehicle): ?>
                        <li><strong><?php echo htmlspecialchars($vehicle['name']); ?></strong> (<?php echo htmlspecialchars($vehicle['registration_number']); ?>) - Due: <?php echo formatDate($vehicle['next_service_date']); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Maintenance Schedule Table -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Maintenance Schedule</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="maintenanceTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Service Type</th>
                                <th>Scheduled Date</th>
                                <th>Mechanic</th>
                                <th>Cost</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($maintenance = mysqli_fetch_assoc($maintenance_result)): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($maintenance['vehicle_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($maintenance['registration_number']); ?> (<?php echo htmlspecialchars($maintenance['model']); ?> <?php echo htmlspecialchars($maintenance['year']); ?>)</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $maintenance['service_type'] === 'emergency' ? 'danger' : ($maintenance['service_type'] === 'major' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst(htmlspecialchars($maintenance['service_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($maintenance['scheduled_date']); ?></td>
                                    <td><?php echo htmlspecialchars($maintenance['mechanic_name']); ?></td>
                                    <td><?php echo formatCurrency($maintenance['cost']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $maintenance['status'] === 'completed' ? 'success' : ($maintenance['status'] === 'in_progress' ? 'info' : ($maintenance['status'] === 'cancelled' ? 'danger' : 'warning')); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($maintenance['status']))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewMaintenanceModal" 
                                                    data-maintenance='<?php echo json_encode($maintenance); ?>'>
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#updateStatusModal" 
                                                    data-maintenance-id="<?php echo $maintenance['id']; ?>" data-current-status="<?php echo $maintenance['status']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteMaintenance(<?php echo $maintenance['id']; ?>)">
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

    <!-- Add Maintenance Modal -->
    <div class="modal fade" id="addMaintenanceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Maintenance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_maintenance">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="vehicle_id" class="form-label">Vehicle *</label>
                                    <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                                        <option value="">Select Vehicle</option>
                                        <?php while ($vehicle = mysqli_fetch_assoc($vehicles_result)): ?>
                                            <option value="<?php echo $vehicle['id']; ?>">
                                                <?php echo htmlspecialchars($vehicle['name']); ?> (<?php echo htmlspecialchars($vehicle['registration_number']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="service_type" class="form-label">Service Type *</label>
                                    <select class="form-select" id="service_type" name="service_type" required>
                                        <option value="">Select Service Type</option>
                                        <option value="regular">Regular</option>
                                        <option value="major">Major</option>
                                        <option value="emergency">Emergency</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="scheduled_date" class="form-label">Scheduled Date *</label>
                                    <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="cost" class="form-label">Estimated Cost</label>
                                    <input type="number" class="form-control" id="cost" name="cost" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="mechanic_name" class="form-label">Mechanic Name</label>
                            <input type="text" class="form-control" id="mechanic_name" name="mechanic_name">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Describe the maintenance work to be performed..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Schedule Maintenance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Maintenance Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="maintenance_id" id="update_maintenance_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Maintenance Modal -->
    <div class="modal fade" id="viewMaintenanceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Maintenance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="maintenanceDetails">
                    <!-- Details will be populated via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_maintenance">
        <input type="hidden" name="maintenance_id" id="delete_maintenance_id">
    </form>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update status modal
        document.querySelectorAll('[data-bs-target="#updateStatusModal"]').forEach(button => {
            button.addEventListener('click', function() {
                const maintenanceId = this.getAttribute('data-maintenance-id');
                const currentStatus = this.getAttribute('data-current-status');
                
                document.getElementById('update_maintenance_id').value = maintenanceId;
                document.getElementById('status').value = currentStatus;
            });
        });

        // View maintenance modal
        document.querySelectorAll('[data-bs-target="#viewMaintenanceModal"]').forEach(button => {
            button.addEventListener('click', function() {
                const maintenance = JSON.parse(this.getAttribute('data-maintenance'));
                const detailsHtml = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Vehicle Information</h6>
                            <p><strong>Name:</strong> ${maintenance.vehicle_name}</p>
                            <p><strong>Registration:</strong> ${maintenance.registration_number}</p>
                            <p><strong>Model:</strong> ${maintenance.model} ${maintenance.year}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Maintenance Details</h6>
                            <p><strong>Service Type:</strong> <span class="badge bg-${maintenance.service_type === 'emergency' ? 'danger' : (maintenance.service_type === 'major' ? 'warning' : 'info')}">${maintenance.service_type.charAt(0).toUpperCase() + maintenance.service_type.slice(1)}</span></p>
                            <p><strong>Scheduled Date:</strong> ${new Date(maintenance.scheduled_date).toLocaleDateString()}</p>
                            <p><strong>Status:</strong> <span class="badge bg-${maintenance.status === 'completed' ? 'success' : (maintenance.status === 'in_progress' ? 'info' : (maintenance.status === 'cancelled' ? 'danger' : 'warning'))}">${maintenance.status.replace('_', ' ').charAt(0).toUpperCase() + maintenance.status.replace('_', ' ').slice(1)}</span></p>
                            ${maintenance.completed_date ? `<p><strong>Completed Date:</strong> ${new Date(maintenance.completed_date).toLocaleDateString()}</p>` : ''}
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6>Service Information</h6>
                            <p><strong>Mechanic:</strong> ${maintenance.mechanic_name || 'Not assigned'}</p>
                            <p><strong>Cost:</strong> PGK ${parseFloat(maintenance.cost || 0).toFixed(2)}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Description</h6>
                            <p>${maintenance.description || 'No description provided'}</p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Timeline</h6>
                            <p><strong>Created:</strong> ${new Date(maintenance.created_at).toLocaleString()}</p>
                            <p><strong>Last Updated:</strong> ${new Date(maintenance.updated_at).toLocaleString()}</p>
                        </div>
                    </div>
                `;
                document.getElementById('maintenanceDetails').innerHTML = detailsHtml;
            });
        });

        // Delete maintenance function
        function deleteMaintenance(maintenanceId) {
            if (confirm('Are you sure you want to delete this maintenance schedule? This action cannot be undone.')) {
                document.getElementById('delete_maintenance_id').value = maintenanceId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Set minimum date for scheduled date input
        document.getElementById('scheduled_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html> 