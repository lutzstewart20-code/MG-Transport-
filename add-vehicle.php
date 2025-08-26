<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $model = trim($_POST['model']);
    $year = (int)$_POST['year'];
    $vehicle_type = $_POST['vehicle_type'];
    $registration_number = trim($_POST['registration_number']);
    $rate_per_day = (float)$_POST['rate_per_day'];
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    $next_service_date = $_POST['next_service_date'] ?: null;
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Vehicle name is required.";
    }
    
    if (empty($model)) {
        $errors[] = "Vehicle model is required.";
    }
    
    if ($year < 1900 || $year > date('Y') + 1) {
        $errors[] = "Please enter a valid year.";
    }
    
    if (empty($registration_number)) {
        $errors[] = "Registration number is required.";
    }
    
    if ($rate_per_day <= 0) {
        $errors[] = "Rate per day must be greater than 0.";
    }
    
    // Check if registration number already exists
    $check_query = "SELECT id FROM vehicles WHERE registration_number = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "s", $registration_number);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        $errors[] = "A vehicle with this registration number already exists.";
    }
    
    // Handle image upload
    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = "Please upload a valid image file (JPEG, PNG, GIF, or WebP).";
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = "Image file size must be less than 5MB.";
        } else {
            $upload_dir = '../assets/images/vehicles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'vehicle_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image_url = 'assets/images/vehicles/' . $filename;
            } else {
                $errors[] = "Failed to upload image. Please try again.";
            }
        }
    }
    
    // Get specification values
    $fuel_type = trim($_POST['fuel_type'] ?? '');
    $body_color = trim($_POST['body_color'] ?? '');
    $transmission = trim($_POST['transmission'] ?? '');
    $seat_capacity = trim($_POST['seat_capacity'] ?? '');
    
    // If no errors, insert vehicle
    if (empty($errors)) {
        $query = "INSERT INTO vehicles (name, model, year, vehicle_type, registration_number, 
                                      rate_per_day, description, status, next_service_date, image_url,
                                      fuel_type, body_color, transmission, seat_capacity, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssisssdsssssss", $name, $model, $year, $vehicle_type, 
                              $registration_number, $rate_per_day, $description, $status, 
                              $next_service_date, $image_url, $fuel_type, $body_color, $transmission,
                              $seat_capacity);
        
        if (mysqli_stmt_execute($stmt)) {
            redirectWithMessage('vehicles.php', 'Vehicle added successfully.', 'success');
        } else {
            $errors[] = "Error adding vehicle. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Vehicle - MG Transport Services Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
                    <h1 class="h3 mb-0">Add New Vehicle</h1>
                    <a href="vehicles.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Vehicles
                    </a>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">Vehicle Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Vehicle Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                           required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="model" class="form-label">Model *</label>
                                    <input type="text" class="form-control" id="model" name="model" 
                                           value="<?php echo isset($_POST['model']) ? htmlspecialchars($_POST['model']) : ''; ?>" 
                                           required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="year" class="form-label">Year *</label>
                                    <input type="number" class="form-control" id="year" name="year" 
                                           value="<?php echo isset($_POST['year']) ? htmlspecialchars($_POST['year']) : date('Y'); ?>" 
                                           min="1900" max="<?php echo date('Y') + 1; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="vehicle_type" class="form-label">Vehicle Type *</label>
                                    <select class="form-select" id="vehicle_type" name="vehicle_type" required>
                                        <option value="">Select Type</option>
                                        <optgroup label="Land Cruiser Models">
                                            <option value="land_cruiser_78_series" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'land_cruiser_78_series') ? 'selected' : ''; ?>>Land Cruiser 78 Series</option>
                                            <option value="land_cruiser_79_series" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'land_cruiser_79_series') ? 'selected' : ''; ?>>Land Cruiser 79 Series</option>
                                            <option value="land_cruiser_10_seater" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'land_cruiser_10_seater') ? 'selected' : ''; ?>>Land Cruiser 10-Seater</option>
                                            <option value="land_cruiser_open_back" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'land_cruiser_open_back') ? 'selected' : ''; ?>>Land Cruiser Open Back</option>
                                            <option value="land_cruiser_4_door" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'land_cruiser_4_door') ? 'selected' : ''; ?>>Land Cruiser 4-Door</option>
                                            <option value="land_cruiser_5_door" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'land_cruiser_5_door') ? 'selected' : ''; ?>>Land Cruiser 5-Door</option>
                                        </optgroup>
                                        <optgroup label="Other Vehicles">
                                            <option value="hilux" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'hilux') ? 'selected' : ''; ?>>Hilux</option>
                                            <option value="harrier" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'harrier') ? 'selected' : ''; ?>>Harrier</option>
                                            <option value="bus" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'bus') ? 'selected' : ''; ?>>Bus</option>
                                            <option value="sedan" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'sedan') ? 'selected' : ''; ?>>Sedan</option>
                                            <option value="suv" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'suv') ? 'selected' : ''; ?>>SUV</option>
                                            <option value="truck" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'truck') ? 'selected' : ''; ?>>Truck</option>
                                            <option value="van" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === 'van') ? 'selected' : ''; ?>>Van</option>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="registration_number" class="form-label">Registration Number *</label>
                                    <input type="text" class="form-control" id="registration_number" name="registration_number" 
                                           value="<?php echo isset($_POST['registration_number']) ? htmlspecialchars($_POST['registration_number']) : ''; ?>" 
                                           required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="rate_per_day" class="form-label">Rate Per Day *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">PGK</span>
                                        <input type="number" class="form-control" id="rate_per_day" name="rate_per_day" 
                                               value="<?php echo isset($_POST['rate_per_day']) ? htmlspecialchars($_POST['rate_per_day']) : ''; ?>" 
                                               step="0.01" min="0" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="available" <?php echo (isset($_POST['status']) && $_POST['status'] === 'available') ? 'selected' : ''; ?>>Available</option>
                                        <option value="maintenance" <?php echo (isset($_POST['status']) && $_POST['status'] === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                        <option value="booked" <?php echo (isset($_POST['status']) && $_POST['status'] === 'booked') ? 'selected' : ''; ?>>Booked</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="Enter vehicle description, features, etc."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="next_service_date" class="form-label">Next Service Date</label>
                                    <input type="date" class="form-control" id="next_service_date" name="next_service_date" 
                                           value="<?php echo isset($_POST['next_service_date']) ? htmlspecialchars($_POST['next_service_date']) : ''; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="image" class="form-label">Vehicle Image</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    <small class="text-muted">Max size: 5MB. Supported formats: JPEG, PNG, GIF, WebP</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="gps_device_id" class="form-label">GPS Device ID</label>
                                    <input type="text" class="form-control" id="gps_device_id" name="gps_device_id" 
                                           value="<?php echo isset($_POST['gps_device_id']) ? htmlspecialchars($_POST['gps_device_id']) : ''; ?>" 
                                           placeholder="e.g., TRK001">
                                    <small class="text-muted">Enter GPS device ID for tracking</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_tracked" name="is_tracked" value="1" 
                                               <?php echo (isset($_POST['is_tracked']) && $_POST['is_tracked']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_tracked">
                                            Enable GPS Tracking
                                        </label>
                                    </div>
                                    <small class="text-muted">Check this to enable real-time GPS tracking for this vehicle</small>
                                </div>
                            </div>

                            <!-- Vehicle Specifications Section -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Vehicle Specifications</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="fuel_type" class="form-label">Fuel Type</label>
                                            <select class="form-select" id="fuel_type" name="fuel_type">
                                                <option value="">Select Fuel Type</option>
                                                <?php 
                                                $fuel_types = getSpecificationsByType($conn, 'fuel_type');
                                                foreach ($fuel_types as $fuel): 
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($fuel['value']); ?>" 
                                                            <?php echo (isset($_POST['fuel_type']) && $_POST['fuel_type'] === $fuel['value']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($fuel['value']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="body_color" class="form-label">Body Color</label>
                                            <select class="form-select" id="body_color" name="body_color">
                                                <option value="">Select Body Color</option>
                                                <?php 
                                                $body_colors = getSpecificationsByType($conn, 'body_color');
                                                foreach ($body_colors as $color): 
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($color['value']); ?>" 
                                                            <?php echo (isset($_POST['body_color']) && $_POST['body_color'] === $color['value']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($color['value']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="transmission" class="form-label">Transmission</label>
                                            <select class="form-select" id="transmission" name="transmission">
                                                <option value="">Select Transmission</option>
                                                <?php 
                                                $transmissions = getSpecificationsByType($conn, 'transmission');
                                                foreach ($transmissions as $transmission): 
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($transmission['value']); ?>" 
                                                            <?php echo (isset($_POST['transmission']) && $_POST['transmission'] === $transmission['value']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($transmission['value']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="seat_capacity" class="form-label">Seat Capacity</label>
                                            <select class="form-select" id="seat_capacity" name="seat_capacity">
                                                <option value="">Select Seat Capacity</option>
                                                <?php 
                                                $seat_capacities = getSpecificationsByType($conn, 'seat_capacity');
                                                foreach ($seat_capacities as $capacity): 
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($capacity['value']); ?>" 
                                                            <?php echo (isset($_POST['seat_capacity']) && $_POST['seat_capacity'] === $capacity['value']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($capacity['value']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="vehicles.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Add Vehicle
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-info-circle text-primary"></i>
                                <strong>Vehicle Name:</strong> Use a descriptive name (e.g., "Toyota Camry 2023")
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-info-circle text-primary"></i>
                                <strong>Registration:</strong> Must be unique across all vehicles
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-info-circle text-primary"></i>
                                <strong>Rate:</strong> Set competitive daily rates
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-info-circle text-primary"></i>
                                <strong>Image:</strong> High-quality photos attract more bookings
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-info-circle text-primary"></i>
                                <strong>Service Date:</strong> Keep track of maintenance schedules
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 