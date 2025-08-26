<footer class="bg-dark text-white py-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <h5><i class="fas fa-shield-alt me-2"></i>MG Transport Admin</h5>
                <p>Administrative control panel for MG Transport Services. Manage bookings, vehicles, users, and system settings efficiently.</p>
                <div class="social-links">
                    <a href="#" class="text-white me-3" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-white me-3" title="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-white me-3" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-white" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="col-md-4">
                <h5>Admin Quick Links</h5>
                <ul class="list-unstyled">
                    <li><a href="dashboard.php" class="text-white text-decoration-none"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a></li>
                    <li><a href="bookings.php" class="text-white text-decoration-none"><i class="fas fa-calendar-check me-1"></i>Manage Bookings</a></li>
                    <li><a href="vehicles.php" class="text-white text-decoration-none"><i class="fas fa-car me-1"></i>Vehicle Management</a></li>
                    <li><a href="users.php" class="text-white text-decoration-none"><i class="fas fa-users me-1"></i>User Management</a></li>
                    <li><a href="maintenance.php" class="text-white text-decoration-none"><i class="fas fa-tools me-1"></i>Maintenance</a></li>
                    <li><a href="reports.php" class="text-white text-decoration-none"><i class="fas fa-chart-bar me-1"></i>Reports</a></li>
                    <li><a href="settings.php" class="text-white text-decoration-none"><i class="fas fa-cog me-1"></i>System Settings</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h5>System Information</h5>
                <p>
                    <i class="fas fa-map-marker-alt me-2"></i>
                    <?php echo getSystemSetting('company_address', $conn); ?>
                </p>
                <p>
                    <i class="fas fa-phone me-2"></i>
                    <?php echo getSystemSetting('company_phone', $conn); ?>
                </p>
                <p>
                    <i class="fas fa-envelope me-2"></i>
                    <?php echo getSystemSetting('company_email', $conn); ?>
                </p>
                <p>
                    <i class="fas fa-user-shield me-2"></i>
                    Admin Panel v1.0
                </p>
                <p>
                    <i class="fas fa-clock me-2"></i>
                    Last Login: <?php echo isset($_SESSION['last_login']) ? date('M d, Y H:i', strtotime($_SESSION['last_login'])) : 'N/A'; ?>
                </p>
            </div>
        </div>
        <hr class="my-4">
        <div class="row">
            <div class="col-md-6">
                <p>&copy; <?php echo date('Y'); ?> MG Transport Services. All rights reserved.</p>
                <small class="text-muted">Administrative Control Panel</small>
            </div>
            <div class="col-md-6 text-md-end">
                <p>Powered by MG Transport Services Booking System</p>
                <small class="text-muted">
                    <i class="fas fa-code me-1"></i>Developed for MG Transport Services
                </small>
            </div>
        </div>
    </div>
</footer>

<style>
.admin-footer {
    margin-top: auto;
}

.admin-footer .social-links a:hover {
    color: #007bff !important;
    transition: color 0.3s ease;
}

.admin-footer ul li {
    margin-bottom: 0.5rem;
}

.admin-footer ul li a:hover {
    color: #007bff !important;
    text-decoration: none;
    transition: color 0.3s ease;
}

.admin-footer h5 {
    color: #007bff;
    font-weight: 600;
}

.admin-footer .text-muted {
    color: #6c757d !important;
}
</style> 