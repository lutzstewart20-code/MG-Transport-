<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <div class="d-flex align-items-center">
                <img src="assets/images/MG Logo.jpg" alt="MG Transport Services" class="me-3" style="width: 50px; height: 50px; border-radius: 50%; background: #fbbf24;">
                <div class="d-none d-md-block">
                    <div class="text-xl fw-bold text-white">MG TRANSPORT SERVICES</div>
                    <div class="text-sm text-warning">VEHICLE HIRE</div>
                </div>
            </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="vehicles.php">Vehicles</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="booking.php">Book Now</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact.php">Contact</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="tracking.php">Track Vehicle</a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Notification Bell -->
                    <li class="nav-item me-3">
                        <?php include 'notification-bell.php'; ?>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="my-bookings.php">My Bookings</a></li>
                            <li><a class="dropdown-item" href="my-agreements.php">My Agreements</a></li>
                            <li><a class="dropdown-item" href="notifications.php">
                                <i class="fas fa-bell me-2"></i>All Notifications
                            </a></li>
                            <li><a class="dropdown-item" href="messages.php">Messages</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 