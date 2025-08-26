<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse" style="transition: all 0.3s ease;">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'bookings.php' ? 'active' : ''; ?>" href="bookings.php">
                    <i class="fas fa-calendar-check"></i>
                    Manage Bookings
                    <?php
                    // Get count of new pending bookings
                    $new_bookings_query = "SELECT COUNT(*) as pending_count FROM bookings WHERE status = 'pending'";
                    $new_bookings_result = mysqli_query($conn, $new_bookings_query);
                    $new_bookings_count = mysqli_fetch_assoc($new_bookings_result)['pending_count'] ?? 0;
                    if ($new_bookings_count > 0): ?>
                        <span class="badge bg-info ms-auto"><?php echo $new_bookings_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'vehicle-agreements.php' ? 'active' : ''; ?>" href="vehicle-agreements.php">
                    <i class="fas fa-file-contract"></i>
                    Vehicle Agreements
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'vehicles.php' ? 'active' : ''; ?>" href="vehicles.php">
                    <i class="fas fa-car"></i>
                    Manage Vehicles
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users"></i>
                    Manage Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'maintenance.php' ? 'active' : ''; ?>" href="maintenance.php">
                    <i class="fas fa-tools"></i>
                    Maintenance
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tracking-management.php' ? 'active' : ''; ?>" href="tracking-management.php">
                    <i class="fas fa-satellite-dish"></i>
                    Tracking Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tracking-history.php' ? 'active' : ''; ?>" href="tracking-history.php">
                    <i class="fas fa-history"></i>
                    Tracking History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                    <i class="fas fa-comments"></i>
                    Messages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : ''; ?>" href="invoices.php">
                    <i class="fas fa-file-pdf"></i>
                    Invoices
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>" href="payments.php">
                    <i class="fas fa-credit-card"></i>
                    Payments
                    <?php
                    // Get count of pending payments
                    $pending_payments_query = "SELECT COUNT(*) as pending_count FROM payments WHERE status = 'pending'";
                    $pending_payments_result = mysqli_query($conn, $pending_payments_query);
                    $pending_payments_count = mysqli_fetch_assoc($pending_payments_result)['pending_count'] ?? 0;
                    if ($pending_payments_count > 0): ?>
                        <span class="badge bg-warning ms-auto"><?php echo $pending_payments_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>" href="notifications.php">
                    <i class="fas fa-bell"></i>
                    Notifications
                    <?php
                    // Get count of unread notifications for admin
                    $unread_notifications_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE";
                    $unread_stmt = mysqli_prepare($conn, $unread_notifications_query);
                    mysqli_stmt_bind_param($unread_stmt, "i", $_SESSION['user_id']);
                    mysqli_stmt_execute($unread_stmt);
                    $unread_result = mysqli_stmt_get_result($unread_stmt);
                    $unread_count = mysqli_fetch_assoc($unread_result)['unread_count'] ?? 0;
                    if ($unread_count > 0): ?>
                        <span class="badge bg-danger ms-auto"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Quick Actions</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" href="add-vehicle.php">
                    <i class="fas fa-plus"></i>
                    Add Vehicle
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="export-reports.php">
                    <i class="fas fa-download"></i>
                    Export Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profile.php">
                    <i class="fas fa-user-circle"></i>
                    My Profile
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>System Info</span>
        </h6>
        <div class="px-3">
            <small class="text-muted">
                <i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A'); ?><br>
                <i class="fas fa-user"></i> Admin Panel<br>
                <i class="fas fa-shield-alt"></i> Secure Access
            </small>
        </div>
    </div>
</nav> 