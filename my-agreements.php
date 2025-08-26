<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's agreements with vehicle details
$query = "SELECT va.*, v.name as vehicle_name, v.model as vehicle_model, v.image_url, v.rate_per_day 
          FROM vehicle_agreements va 
          JOIN vehicles v ON va.vehicle_id = v.id 
          WHERE va.user_id = ? 
          ORDER BY va.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$agreements = [];
while ($row = mysqli_fetch_assoc($result)) {
    $agreements[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Vehicle Agreements - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .agreement-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .agreement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
        }
        
        .vehicle-image {
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .agreement-details {
            font-size: 0.9rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">
                        <i class="fas fa-file-contract me-2"></i>My Vehicle Agreements
                    </h2>
                    <a href="vehicles.php" class="btn btn-primary">
                        <i class="fas fa-car me-2"></i>Browse Vehicles
                    </a>
                </div>

                <?php if (empty($agreements)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-contract"></i>
                        <h4 class="text-muted">No Agreements Found</h4>
                        <p class="text-muted">You haven't submitted any vehicle agreements yet.</p>
                        <a href="vehicles.php" class="btn btn-primary">
                            <i class="fas fa-car me-2"></i>Browse Available Vehicles
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($agreements as $agreement): ?>
                            <div class="col-lg-6 mb-4">
                                <div class="card agreement-card h-100">
                                    <div class="card-header bg-white border-0 pb-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($agreement['vehicle_name']); ?></h5>
                                                <p class="text-muted mb-0 small"><?php echo htmlspecialchars($agreement['vehicle_model']); ?></p>
                                            </div>
                                            <?php 
                                            $status_class = '';
                                            $status_text = '';
                                            switch($agreement['agreement_status']) {
                                                case 'pending':
                                                    $status_class = 'bg-warning';
                                                    $status_text = 'Pending Review';
                                                    break;
                                                case 'approved':
                                                    $status_class = 'bg-success';
                                                    $status_text = 'Approved';
                                                    break;
                                                case 'rejected':
                                                    $status_class = 'bg-danger';
                                                    $status_text = 'Rejected';
                                                    break;
                                                case 'completed':
                                                    $status_class = 'bg-info';
                                                    $status_text = 'Completed';
                                                    break;
                                                default:
                                                    $status_class = 'bg-secondary';
                                                    $status_text = ucfirst($agreement['agreement_status']);
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?> status-badge"><?php echo $status_text; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4 text-center mb-3">
                                                <img src="<?php echo htmlspecialchars($agreement['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($agreement['vehicle_name']); ?>" 
                                                     class="vehicle-image w-100">
                                            </div>
                                            <div class="col-md-8">
                                                <div class="agreement-details">
                                                    <div class="row mb-2">
                                                        <div class="col-6">
                                                            <small class="text-muted">Organization:</small>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($agreement['organization_company']); ?></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Contact:</small>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($agreement['contact_name']); ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mb-2">
                                                        <div class="col-6">
                                                            <small class="text-muted">Pickup Date:</small>
                                                            <div class="fw-bold"><?php echo date('M d, Y', strtotime($agreement['pickup_date'])); ?></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Return Date:</small>
                                                            <div class="fw-bold"><?php echo date('M d, Y', strtotime($agreement['return_date'])); ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mb-2">
                                                        <div class="col-6">
                                                            <small class="text-muted">Duration:</small>
                                                            <div class="fw-bold"><?php echo $agreement['number_of_days']; ?> days</div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Rate:</small>
                                                            <div class="fw-bold"><?php echo formatCurrency($agreement['rate_per_day']); ?>/day</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mb-2">
                                                        <div class="col-6">
                                                            <small class="text-muted">Pickup Time:</small>
                                                            <div class="fw-bold"><?php echo date('H:i', strtotime($agreement['pickup_time'])); ?></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Drop-off Time:</small>
                                                            <div class="fw-bold"><?php echo date('H:i', strtotime($agreement['dropoff_time'])); ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mb-2">
                                                        <div class="col-12">
                                                            <small class="text-muted">Business Address:</small>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($agreement['business_address']); ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($agreement['agreement_status'] === 'rejected' && !empty($agreement['admin_notes'])): ?>
                                                        <div class="alert alert-danger mt-3 mb-0">
                                                            <small class="fw-bold">Admin Notes:</small><br>
                                                            <?php echo htmlspecialchars($agreement['admin_notes']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($agreement['agreement_status'] === 'approved' && !empty($agreement['admin_notes'])): ?>
                                                        <div class="alert alert-success mt-3 mb-0">
                                                            <small class="fw-bold">Admin Notes:</small><br>
                                                            <?php echo htmlspecialchars($agreement['admin_notes']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-footer bg-white border-0 pt-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                Submitted: <?php echo date('M d, Y H:i', strtotime($agreement['created_at'])); ?>
                                            </small>
                                            
                                            <?php if ($agreement['agreement_status'] === 'pending'): ?>
                                                <span class="text-warning">
                                                    <i class="fas fa-clock me-1"></i>Under Review
                                                </span>
                                            <?php elseif ($agreement['agreement_status'] === 'approved'): ?>
                                                <span class="text-success">
                                                    <i class="fas fa-check-circle me-1"></i>Ready for Pickup
                                                </span>
                                            <?php elseif ($agreement['agreement_status'] === 'rejected'): ?>
                                                <span class="text-danger">
                                                    <i class="fas fa-times-circle me-1"></i>Not Approved
                                                </span>
                                            <?php endif; ?>
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
