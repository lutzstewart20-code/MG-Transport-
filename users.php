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
            case 'add_user':
                $username = sanitizeInput($_POST['username']);
                $email = sanitizeInput($_POST['email']);
                $password = sanitizeInput($_POST['password']);
                $role = sanitizeInput($_POST['role']);
                $first_name = sanitizeInput($_POST['first_name']);
                $last_name = sanitizeInput($_POST['last_name']);
                
                if (empty($username) || empty($email) || empty($password) || empty($role)) {
                    $message = 'Please fill in all required fields.';
                    $message_type = 'danger';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $query = "INSERT INTO users (username, email, password, role, first_name, last_name) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "ssssss", $username, $email, $hashed_password, $role, $first_name, $last_name);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'User added successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error adding user.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'delete_user':
                $user_id = sanitizeInput($_POST['user_id']);
                
                if ($user_id != $_SESSION['user_id']) {
                    $query = "DELETE FROM users WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'User deleted successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error deleting user.';
                        $message_type = 'danger';
                    }
                } else {
                    $message = 'You cannot delete your own account.';
                    $message_type = 'danger';
                }
                break;
        }
    }
}

// Get users with booking statistics
$users_query = "SELECT u.*, 
                    COUNT(b.id) as total_bookings,
                    SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END) as total_spent
                FROM users u
                LEFT JOIN bookings b ON u.id = b.user_id
                GROUP BY u.id
                ORDER BY u.created_at DESC";
$users_result = mysqli_query($conn, $users_query);

// Get user statistics
$stats_query = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customers,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                    SUM(CASE WHEN role = 'super_admin' THEN 1 ELSE 0 END) as super_admins
                FROM users";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - MG Transport Services Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-compact.css" rel="stylesheet">
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
                        <a class="nav-link" href="maintenance.php">Maintenance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="users.php">Users</a>
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
            <h2><i class="fas fa-users text-primary"></i> User Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus"></i> Add User
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
            <div class="col-md-3">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_users']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Customers</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['customers']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Admins</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['admins']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Super Admins</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['super_admins']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-crown fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">User Accounts</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="usersTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Bookings</th>
                                <th>Total Spent</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong><br>
                                        <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['role'] === 'super_admin' ? 'danger' : ($user['role'] === 'admin' ? 'warning' : 'success'); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($user['role']))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['total_bookings']; ?></td>
                                    <td>PGK <?php echo number_format($user['total_spent'], 2); ?></td>
                                    <td><?php echo formatDate($user['created_at']); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewUserModal" 
                                                    data-user='<?php echo json_encode($user); ?>'>
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
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

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="customer">Customer</option>
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetails">
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
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" id="delete_user_id">
    </form>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View user modal
        document.querySelectorAll('[data-bs-target="#viewUserModal"]').forEach(button => {
            button.addEventListener('click', function() {
                const user = JSON.parse(this.getAttribute('data-user'));
                const userPhotoHtml = user.user_photo ? 
                    `<div class="mb-3">
                        <strong>User Photo:</strong><br>
                        <img src="../${user.user_photo}" alt="User Photo" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                    </div>` : 
                    `<div class="mb-3"><strong>User Photo:</strong> Not uploaded</div>`;
                
                const licensePhotoHtml = user.driver_license_photo ? 
                    `<div class="mb-3">
                        <strong>Driver's License:</strong><br>
                        <img src="../${user.driver_license_photo}" alt="Driver's License" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                    </div>` : 
                    `<div class="mb-3"><strong>Driver's License:</strong> Not uploaded</div>`;
                
                const detailsHtml = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Personal Information</h6>
                            <p><strong>Name:</strong> ${user.first_name} ${user.last_name}</p>
                            <p><strong>Username:</strong> @${user.username}</p>
                            <p><strong>Email:</strong> ${user.email}</p>
                            <p><strong>Phone:</strong> ${user.phone || 'Not provided'}</p>
                            <p><strong>Address:</strong> ${user.address || 'Not provided'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Account Information</h6>
                            <p><strong>Role:</strong> <span class="badge bg-${user.role === 'super_admin' ? 'danger' : (user.role === 'admin' ? 'warning' : 'success')}">${user.role.replace('_', ' ').charAt(0).toUpperCase() + user.role.replace('_', ' ').slice(1)}</span></p>
                            <p><strong>Joined:</strong> ${new Date(user.created_at).toLocaleDateString()}</p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            ${userPhotoHtml}
                        </div>
                        <div class="col-md-6">
                            ${licensePhotoHtml}
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Booking Statistics</h6>
                            <p><strong>Total Bookings:</strong> ${user.total_bookings}</p>
                            <p><strong>Total Spent:</strong> PGK ${parseFloat(user.total_spent || 0).toFixed(2)}</p>
                        </div>
                    </div>
                `;
                document.getElementById('userDetails').innerHTML = detailsHtml;
            });
        });

        // Delete user function
        function deleteUser(userId, username) {
            if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
                document.getElementById('delete_user_id').value = userId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html> 