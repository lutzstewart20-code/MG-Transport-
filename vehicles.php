<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get filter parameters
$vehicle_type = $_GET['vehicle_type'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';

// Build the query
$query = "SELECT * FROM vehicles";
$params = [];
$types = '';

// Add status filter
if ($status_filter !== 'all') {
    $query .= " WHERE status = ?";
    $params[] = $status_filter;
    $types .= 's';
} else {
    $query .= " WHERE 1=1";
}

if ($vehicle_type) {
    $query .= " AND vehicle_type = ?";
    $params[] = $vehicle_type;
    $types .= 's';
}

if ($min_price) {
    $query .= " AND rate_per_day >= ?";
    $params[] = $min_price;
    $types .= 'd';
}

if ($max_price) {
    $query .= " AND rate_per_day <= ?";
    $params[] = $max_price;
    $types .= 'd';
}

if ($search) {
    $query .= " AND (name LIKE ? OR model LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$query .= " ORDER BY name";

// Prepare and execute the query
$stmt = mysqli_prepare($conn, $query);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$vehicles = [];
while ($row = mysqli_fetch_assoc($result)) {
    $vehicles[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicles - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        /* Additional compact styles for vehicles page */
        .vehicle-card {
            font-size: 0.875rem;
        }
        
        .vehicle-card .card-title {
            font-size: 1rem;
        }
        
        .vehicle-card .card-text {
            font-size: 0.8rem;
        }
        
        .vehicle-filters .form-control {
            font-size: 0.8rem;
            padding: 0.375rem 0.5rem;
        }
        
        .vehicle-filters .btn {
            font-size: 0.8rem;
            padding: 0.375rem 0.75rem;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <!-- Status Filter -->
                            <div class="mb-3">
                                <label for="status" class="form-label">Vehicle Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Vehicles</option>
                                    <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="booked" <?php echo $status_filter === 'booked' ? 'selected' : ''; ?>>Booked</option>
                                    <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                                </select>
                            </div>

                            <!-- Vehicle Type Filter -->
                            <div class="mb-3">
                                <label for="vehicle_type" class="form-label">Vehicle Type</label>
                                <select name="vehicle_type" id="vehicle_type" class="form-select">
                                    <option value="">All Types</option>
                                    <optgroup label="Land Cruiser Models">
                                        <option value="land_cruiser_78_series" <?php echo $vehicle_type === 'land_cruiser_78_series' ? 'selected' : ''; ?>>Land Cruiser 78 Series</option>
                                        <option value="land_cruiser_79_series" <?php echo $vehicle_type === 'land_cruiser_79_series' ? 'selected' : ''; ?>>Land Cruiser 79 Series</option>
                                        <option value="land_cruiser_10_seater" <?php echo $vehicle_type === 'land_cruiser_10_seater' ? 'selected' : ''; ?>>Land Cruiser 10-Seater</option>
                                        <option value="land_cruiser_open_back" <?php echo $vehicle_type === 'land_cruiser_open_back' ? 'selected' : ''; ?>>Land Cruiser Open Back</option>
                                        <option value="land_cruiser_4_door" <?php echo $vehicle_type === 'land_cruiser_4_door' ? 'selected' : ''; ?>>Land Cruiser 4-Door</option>
                                        <option value="land_cruiser_5_door" <?php echo $vehicle_type === 'land_cruiser_5_door' ? 'selected' : ''; ?>>Land Cruiser 5-Door</option>
                                    </optgroup>
                                    <optgroup label="Other Vehicles">
                                        <option value="hilux" <?php echo $vehicle_type === 'hilux' ? 'selected' : ''; ?>>Hilux</option>
                                        <option value="harrier" <?php echo $vehicle_type === 'harrier' ? 'selected' : ''; ?>>Harrier</option>
                                        <option value="bus" <?php echo $vehicle_type === 'bus' ? 'selected' : ''; ?>>Bus</option>
                                        <option value="sedan" <?php echo $vehicle_type === 'sedan' ? 'selected' : ''; ?>>Sedan</option>
                                        <option value="suv" <?php echo $vehicle_type === 'suv' ? 'selected' : ''; ?>>SUV</option>
                                        <option value="truck" <?php echo $vehicle_type === 'truck' ? 'selected' : ''; ?>>Truck</option>
                                        <option value="van" <?php echo $vehicle_type === 'van' ? 'selected' : ''; ?>>Van</option>
                                    </optgroup>
                                </select>
                            </div>

                            <!-- Price Range Filter -->
                            <div class="mb-3">
                                <label for="min_price" class="form-label">Min Price (PGK)</label>
                                <input type="number" name="min_price" id="min_price" class="form-control" value="<?php echo htmlspecialchars($min_price); ?>" placeholder="0">
                            </div>

                            <div class="mb-3">
                                <label for="max_price" class="form-label">Max Price (PGK)</label>
                                <input type="number" name="max_price" id="max_price" class="form-control" value="<?php echo htmlspecialchars($max_price); ?>" placeholder="1000">
                            </div>

                            <!-- Search Filter -->
                            <div class="mb-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" name="search" id="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search vehicles...">
                            </div>

                            <!-- Filter Buttons -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Apply Filters
                                </button>
                                <a href="vehicles.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Vehicles Grid -->
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">
                        <i class="fas fa-car me-2"></i>
                        <?php 
                        switch($status_filter) {
                            case 'available':
                                echo 'Available Vehicles';
                                break;
                            case 'booked':
                                echo 'Booked Vehicles';
                                break;
                            case 'maintenance':
                                echo 'Vehicles Under Maintenance';
                                break;
                            default:
                                echo 'All Vehicles';
                        }
                        ?>
                    </h2>
                    <span class="badge bg-primary fs-6"><?php echo count($vehicles); ?> vehicles found</span>
                </div>

                <?php if (empty($vehicles)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-car fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No vehicles found</h4>
                        <p class="text-muted">Try adjusting your filters or search criteria.</p>
                        <a href="vehicles.php" class="btn btn-primary">Clear Filters</a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($vehicles as $vehicle): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 border-0 shadow-sm hover-shadow">
                                    <div class="position-relative">
                                        <img src="<?php echo htmlspecialchars($vehicle['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($vehicle['name']); ?>"  
                                             class="card-img-top" style="height: 200px; object-fit: cover;">
                                        <div class="position-absolute top-0 end-0 m-2">
                                            <?php 
                                            $status_badge_class = '';
                                            $status_text = '';
                                            switch($vehicle['status']) {
                                                case 'available':
                                                    $status_badge_class = 'bg-success';
                                                    $status_text = 'Available';
                                                    break;
                                                case 'booked':
                                                    $status_badge_class = 'bg-warning';
                                                    $status_text = 'Booked';
                                                    break;
                                                case 'maintenance':
                                                    $status_badge_class = 'bg-danger';
                                                    $status_text = 'Under Maintenance';
                                                    break;
                                                default:
                                                    $status_badge_class = 'bg-secondary';
                                                    $status_text = ucfirst($vehicle['status']);
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_badge_class; ?>"><?php echo $status_text; ?></span>
                                        </div>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?php echo htmlspecialchars($vehicle['name']); ?></h5>
                                        <p class="card-text text-muted small">
                                            <i class="fas fa-car me-1"></i><?php echo htmlspecialchars(formatVehicleType($vehicle['vehicle_type'])); ?> • 
                                            <i class="fas fa-users me-1"></i><?php echo htmlspecialchars($vehicle['seats']); ?> seats
                                        </p>
                                        <p class="card-text"><?php echo htmlspecialchars(substr($vehicle['description'], 0, 100)); ?>...</p>
                                        
                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="badge bg-primary"><?php echo htmlspecialchars(formatVehicleType($vehicle['vehicle_type'])); ?></span>
                                                <span class="fw-bold text-primary"><?php echo formatCurrency($vehicle['rate_per_day']); ?>/day</span>
                                            </div>
                                            
                                            <!-- Vehicle Specifications Dropdown -->
                                            <div class="mb-3">
                                                <button class="btn btn-outline-info btn-sm w-100" type="button" data-bs-toggle="collapse" data-bs-target="#specs-<?php echo $vehicle['id']; ?>" aria-expanded="false" aria-controls="specs-<?php echo $vehicle['id']; ?>">
                                                    <i class="fas fa-info-circle me-2"></i>View Specifications
                                                </button>
                                                <div class="collapse" id="specs-<?php echo $vehicle['id']; ?>">
                                                    <div class="card card-body mt-2 bg-light">
                                                        <?php 
                                                        $specs = getVehicleSpecificationsFromDB($vehicle['id'], $conn);
                                                        ?>
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <small class="text-muted"><i class="fas fa-gas-pump me-1"></i>Fuel Type</small>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($specs['fuel_type']); ?></div>
                                                            </div>
                                                            <div class="col-6">
                                                                <small class="text-muted"><i class="fas fa-palette me-1"></i>Body Color</small>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($specs['body_color']); ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="row mt-2">
                                                            <div class="col-6">
                                                                <small class="text-muted"><i class="fas fa-cog me-1"></i>Transmission</small>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($specs['transmission']); ?></div>
                                                            </div>
                                                            <div class="col-6">
                                                                <small class="text-muted"><i class="fas fa-users me-1"></i>Seat Capacity</small>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($specs['seat_capacity']); ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid gap-2">
                                                <?php if ($vehicle['status'] === 'available'): ?>
                                                    <a href="booking.php?vehicle_id=<?php echo $vehicle['id']; ?>" class="btn btn-primary">
                                                        <i class="fas fa-calendar-plus me-2"></i>Book Now
                                                    </a>
                                                <?php elseif ($vehicle['status'] === 'booked'): ?>
                                                    <?php
                                                    // Check if user has a confirmed booking for this vehicle
                                                    $user_has_confirmed_booking = false;
                                                    if (isset($_SESSION['user_id'])) {
                                                        $booking_check_query = "SELECT id FROM bookings WHERE user_id = ? AND vehicle_id = ? AND status = 'confirmed'";
                                                        $booking_check_stmt = mysqli_prepare($conn, $booking_check_query);
                                                        mysqli_stmt_bind_param($booking_check_stmt, "ii", $_SESSION['user_id'], $vehicle['id']);
                                                        mysqli_stmt_execute($booking_check_stmt);
                                                        $booking_check_result = mysqli_stmt_get_result($booking_check_stmt);
                                                        $user_has_confirmed_booking = mysqli_num_rows($booking_check_result) > 0;
                                                    }
                                                    ?>
                                                    
                                                    <?php if ($user_has_confirmed_booking): ?>
                                                        <a href="vehicle-agreement.php?vehicle_id=<?php echo $vehicle['id']; ?>" class="btn btn-success">
                                                            <i class="fas fa-file-contract me-2"></i>Fill Agreement Form
                                                        </a>
                                                        <small class="text-muted text-center">Your booking is confirmed! Fill out the agreement form to get your vehicle.</small>
                                                    <?php else: ?>
                                                        <button class="btn btn-warning" disabled>
                                                            <i class="fas fa-calendar-times me-2"></i>Currently Booked
                                                        </button>
                                                        <small class="text-muted text-center">This vehicle is currently booked by another user.</small>
                                                    <?php endif; ?>
                                                <?php elseif ($vehicle['status'] === 'maintenance'): ?>
                                                    <button class="btn btn-danger" disabled>
                                                        <i class="fas fa-tools me-2"></i>Under Maintenance
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html> 