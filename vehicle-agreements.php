<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'includes/security-middleware.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit();
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $agreement_id = $_POST['agreement_id'];
    $action = $_POST['action'];
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    if ($action === 'approve') {
        $status = 'approved';
    } elseif ($action === 'reject') {
        $status = 'rejected';
    } elseif ($action === 'complete') {
        $status = 'completed';
    } else {
        $status = 'pending';
    }
    
    $update_query = "UPDATE vehicle_agreements SET 
                     agreement_status = ?, 
                     admin_notes = ?, 
                     admin_approved_by = ?, 
                     admin_approved_at = CURRENT_TIMESTAMP 
                     WHERE id = ?";
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "ssii", $status, $admin_notes, $_SESSION['user_id'], $agreement_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        $success_message = "Agreement status updated successfully!";
    } else {
        $error_message = "Error updating agreement status.";
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build the query
$query = "SELECT va.*, v.name as vehicle_name, v.model as vehicle_model, v.image_url, 
          u.first_name, u.last_name, u.email, u.phone,
          admin.first_name as admin_first_name, admin.last_name as admin_last_name,
          b.start_date, b.end_date, b.total_amount, b.status as booking_status
          FROM vehicle_agreements va 
          JOIN vehicles v ON va.vehicle_id = v.id 
          JOIN users u ON va.user_id = u.id 
          LEFT JOIN users admin ON va.admin_approved_by = admin.id
          LEFT JOIN bookings b ON va.booking_id = b.id";

$params = [];
$types = '';

if ($status_filter !== 'all') {
    $query .= " WHERE va.agreement_status = ?";
    $params[] = $status_filter;
    $types .= 's';
} else {
    $query .= " WHERE 1=1";
}

if ($search) {
    $query .= " AND (va.organization_company LIKE ? OR va.contact_name LIKE ? OR v.name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$query .= " ORDER BY va.created_at DESC";

// Prepare and execute the query
$stmt = mysqli_prepare($conn, $query);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
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
    <title>Vehicle Agreements Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin-compact.css" rel="stylesheet">
    <style>
        .agreement-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .agreement-card:hover {
            transform: translateY(-2px);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
        }
        
        .vehicle-image {
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .agreement-details {
            font-size: 0.9rem;
        }
        
        .action-buttons .btn {
            font-size: 0.8rem;
            padding: 0.375rem 0.75rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0">
                            <i class="fas fa-file-contract me-2"></i>Vehicle Agreements Management
                        </h2>
                        <div class="d-flex gap-2">
                            <a href="../admin/dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-4">
                                    <label for="status" class="form-label">Status Filter</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" name="search" id="search" class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search by organization, contact name, vehicle, or email">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-2"></i>Filter
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Agreements List -->
                    <?php if (empty($agreements)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-contract fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No agreements found</h4>
                            <p class="text-muted">Try adjusting your filters or search criteria.</p>
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
                                                    <img src="../<?php echo htmlspecialchars($agreement['image_url']); ?>" 
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
                                                                <small class="text-muted">Customer:</small>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($agreement['first_name'] . ' ' . $agreement['last_name']); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($agreement['email']); ?></small>
                                                            </div>
                                                            <div class="col-6">
                                                                <small class="text-muted">Phone:</small>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($agreement['phone']); ?></div>
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
                                                                <small class="text-muted">Submitted:</small>
                                                                <div class="fw-bold"><?php echo date('M d, Y H:i', strtotime($agreement['created_at'])); ?></div>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($agreement['admin_notes']): ?>
                                                            <div class="alert alert-info mt-3 mb-0">
                                                                <small class="fw-bold">Admin Notes:</small><br>
                                                                <?php echo htmlspecialchars($agreement['admin_notes']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($agreement['admin_approved_by']): ?>
                                                            <div class="alert alert-success mt-3 mb-0">
                                                                <small class="fw-bold">Processed by:</small><br>
                                                                <?php echo htmlspecialchars($agreement['admin_first_name'] . ' ' . $agreement['admin_last_name']); ?> 
                                                                on <?php echo date('M d, Y H:i', strtotime($agreement['admin_approved_at'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card-footer bg-white border-0 pt-0">
                                            <?php if ($agreement['agreement_status'] === 'pending'): ?>
                                                <div class="action-buttons d-flex gap-2">
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#actionModal" 
                                                            data-agreement-id="<?php echo $agreement['id']; ?>" 
                                                            data-action="approve"
                                                            data-agreement-title="Approve Agreement">
                                                        <i class="fas fa-check me-1"></i>Approve
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#actionModal" 
                                                            data-agreement-id="<?php echo $agreement['id']; ?>" 
                                                            data-action="reject"
                                                            data-agreement-title="Reject Agreement">
                                                        <i class="fas fa-times me-1"></i>Reject
                                                    </button>
                                                </div>
                                            <?php elseif ($agreement['agreement_status'] === 'approved'): ?>
                                                <div class="action-buttons d-flex gap-2">
                                                    <button type="button" class="btn btn-info btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#actionModal" 
                                                            data-agreement-id="<?php echo $agreement['id']; ?>" 
                                                            data-action="complete"
                                                            data-agreement-title="Mark as Completed">
                                                        <i class="fas fa-flag-checkered me-1"></i>Mark Complete
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="actionModalLabel">Update Agreement Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="agreement_id" id="modalAgreementId">
                        <input type="hidden" name="action" id="modalAction">
                        
                        <div class="mb-3">
                            <label for="admin_notes" class="form-label">Admin Notes (Optional)</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                                      placeholder="Add any notes or comments about this decision..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="actionDescription"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Confirm Action</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/admin_footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle modal data
        document.getElementById('actionModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const agreementId = button.getAttribute('data-agreement-id');
            const action = button.getAttribute('data-action');
            const title = button.getAttribute('data-agreement-title');
            
            document.getElementById('modalAgreementId').value = agreementId;
            document.getElementById('modalAction').value = action;
            document.getElementById('actionModalLabel').textContent = title;
            
            let description = '';
            if (action === 'approve') {
                description = 'This will approve the vehicle agreement and notify the customer that their request has been accepted.';
            } else if (action === 'reject') {
                description = 'This will reject the vehicle agreement. Please provide a reason in the notes above.';
            } else if (action === 'complete') {
                description = 'This will mark the agreement as completed, indicating the vehicle has been returned.';
            }
            
            document.getElementById('actionDescription').textContent = description;
        });
    </script>
</body>
</html>
