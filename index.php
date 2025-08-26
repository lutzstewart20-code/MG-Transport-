<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Get vehicle images for slideshow
$vehicle_images = [
    [
        'src' => 'assets/images/vehicles/4-door.webp',
        'alt' => 'Land Cruiser 79 Series Single Cab GX',
        'title' => 'Toyota Land Cruiser 79 Series Single Cab GX',
        'subtitle' => 'Premium 4WD Transport'
    ],
    [
        'src' => 'assets/images/vehicles/openback.webp',
        'alt' => 'Land Cruiser Open Back Side View',
        'title' => 'Land Cruiser Open Back',
        'subtitle' => 'Versatile Transport'
    ],
    [
        'src' => 'assets/images/vehicles/10seater.webp',
        'alt' => 'Land Cruiser 10-Seater',
        'title' => 'Land Cruiser 10-Seater',
        'subtitle' => 'Group Transport'
    ],
    [
        'src' => 'assets/images/vehicles/5door.webp',
        'alt' => 'Land Cruiser 5-Door',
        'title' => 'Land Cruiser 5-Door',
        'subtitle' => 'Luxury & Comfort'
    ],
    [
        'src' => 'assets/images/vehicles/10.webp',
        'alt' => 'Land Cruiser 10-Seater Interior',
        'title' => '10-Seater Interior',
        'subtitle' => 'Spacious & Comfortable'
    ],
    [
        'src' => 'assets/images/vehicles/lc70-back-1200x675.webp',
        'alt' => 'Land Cruiser 70 Series Back View',
        'title' => 'Land Cruiser 70 Series',
        'subtitle' => 'Reliable & Safe'
    ],
    [
        'src' => 'assets/images/vehicles/toyota-land-cruiser-4-door.webp',
        'alt' => 'Toyota Land Cruiser 4-Door',
        'title' => 'Toyota Land Cruiser 4-Door',
        'subtitle' => 'Luxury & Performance'
    ],
    [
        'src' => 'assets/images/vehicles/Hilux.webp',
        'alt' => 'Toyota Hilux',
        'title' => 'Toyota Hilux',
        'subtitle' => 'Durable & Versatile'
    ],
    [
        'src' => 'assets/images/vehicles/HiluxRed.webp',
        'alt' => 'Toyota Hilux Red',
        'title' => 'Toyota Hilux Red',
        'subtitle' => 'Style & Function'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MG Transport Services - Car Hire Booking Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        /* Hero Section with Live Background */
        .hero-section {
            position: relative;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            overflow: hidden;
            min-height: 80vh; /* Reduced from 100vh */
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            transition: opacity 0.5s ease-in-out;
        }
        
        .hero-section {
            --bg-image: url('assets/images/vehicles/4-door.webp');
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg-image) center center/cover no-repeat;
            opacity: 0.15;
            animation: backgroundMove 25s ease-in-out infinite alternate;
            transition: background-image 0.5s ease-in-out;
        }
        
        @keyframes backgroundMove {
            0% {
                transform: scale(1) translate(0, 0);
            }
            50% {
                transform: scale(1.15) translate(-15px, -15px);
            }
            100% {
                transform: scale(1.1) translate(15px, 15px);
            }
        }
        
        /* Floating Elements Animation */
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }
        
        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }
        
        .floating-element:nth-child(1) {
            width: 80px; /* Reduced from 100px */
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-element:nth-child(2) {
            width: 120px; /* Reduced from 150px */
            height: 120px;
            top: 60%;
            right: 15%;
            animation-delay: 3s;
        }
        
        .floating-element:nth-child(3) {
            width: 60px; /* Reduced from 80px */
            height: 60px;
            top: 40%;
            left: 70%;
            animation-delay: 6s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
                opacity: 0.6;
            }
            50% {
                transform: translateY(-30px) rotate(180deg);
                opacity: 1;
            }
        }
        
        /* Hero Content */
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        /* .hero-image {
            position: relative;
            overflow: hidden;
            border-radius: 1.5rem;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
            animation: imageFloat 10s ease-in-out infinite;
            transform: scale(1.1);
            border: 3px solid rgba(255, 255, 255, 0.2);
        }
        
        @keyframes imageFloat {
            0%, 100% {
                transform: translateY(0px) rotateY(0deg) scale(1.1);
            }
            50% {
                transform: translateY(-15px) rotateY(8deg) scale(1.15);
            }
        }
        
        .hero-image img {
            transition: all 1s ease-in-out;
            width: 100%;
            height: auto;
            display: block;
            border-radius: 1rem;
        }
        
        .hero-image:hover img {
            transform: scale(1.1);
        } */
        
        /* .image-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.9));
            padding: 3rem 2.5rem 2.5rem;
            color: white;
            animation: overlayFade 4s ease-in-out infinite alternate;
            backdrop-filter: blur(10px);
        }
        
        @keyframes overlayFade {
            0% {
                background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
            }
            100% {
                background: linear-gradient(transparent, rgba(0, 0, 0, 0.9));
            }
        }
        
        .overlay-text h3 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 4px 4px 8px rgba(0, 0, 0, 0.9);
            animation: textGlow 3s ease-in-out infinite alternate;
            transition: all 0.5s ease-in-out;
            font-weight: 800;
            letter-spacing: 1px;
        }
        
        @keyframes textGlow {
            0% {
                text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.8);
            }
            100% {
                text-shadow: 3px 3px 12px rgba(255, 255, 255, 0.4);
            }
        }
        
        .overlay-text p {
            font-size: 1.4rem;
            opacity: 0.95;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.9);
            transition: all 0.5s ease-in-out;
            font-weight: 600;
            letter-spacing: 0.5px;
        } */
        
        /* Slideshow Controls */
        /* .slideshow-controls {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            display: flex;
            gap: 10px;
        }
        
        .slideshow-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .slideshow-dot.active {
            background: rgba(255, 255, 255, 1);
            transform: scale(1.2);
        } */
        
        /* Button Animations */
        .btn-animated {
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
        }
        
        .btn-animated::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s;
        }
        
        .btn-animated:hover::before {
            left: 100%;
        }
        
        .btn-animated:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .overlay-text h3 {
                font-size: 1.5rem;
            }
            
            .overlay-text p {
                font-size: 1rem;
            }
            
            .floating-element {
                display: none;
            }
            
            .hero-image {
                transform: scale(1);
            }
            
            @keyframes imageFloat {
                0%, 100% {
                    transform: translateY(0px) rotateY(0deg) scale(1);
                }
                50% {
                    transform: translateY(-10px) rotateY(5deg) scale(1.05);
                }
            }
        }
        
        /* Fade In Up Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Pulse Animation for Buttons */
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }
        
        .btn-animated:hover {
            animation: pulse 0.8s ease-in-out;
        }
        
        /* Card hover effects */
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3) !important;
        }
        
        /* Feature cards hover */
        .card .mb-4:hover {
            transform: scale(1.1);
            transition: all 0.3s ease;
        }
        
        /* Button hover effects */
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        /* Compact styles for homepage */
        .hero-content h1 {
            font-size: 2.5rem; /* Reduced from default */
        }
        
        .hero-content p {
            font-size: 1rem; /* Reduced from default */
        }
        
        .feature-card {
            font-size: 0.875rem;
            padding: 1.5rem; /* Reduced from default */
        }
        
        .feature-card h3 {
            font-size: 1.25rem; /* Reduced from default */
        }
        
        .feature-card p {
            font-size: 0.875rem;
        }
        
        .section-title {
            font-size: 2rem; /* Reduced from default */
        }
        
        .section-subtitle {
            font-size: 1rem; /* Reduced from default */
        }
        
        /* Compact vehicle cards */
        .vehicle-showcase .card {
            font-size: 0.875rem;
        }
        
        .vehicle-showcase .card-title {
            font-size: 1.1rem;
        }
        
        .vehicle-showcase .card-text {
            font-size: 0.8rem;
        }
        
        /* Compact buttons */
        .hero-content .btn {
            font-size: 0.9rem;
            padding: 0.5rem 1.25rem;
        }
        
        /* Compact testimonials */
        .testimonial-card {
            font-size: 0.875rem;
            padding: 1.25rem;
        }
        
        .testimonial-card .card-text {
            font-size: 0.8rem;
        }
        
        /* Compact contact section */
        .contact-info {
            font-size: 0.875rem;
        }
        
        .contact-info h5 {
            font-size: 1rem;
        }
        
        .contact-info p {
            font-size: 0.8rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .hero-content p {
                font-size: 0.9rem;
            }
            
            .section-title {
                font-size: 1.75rem;
            }
            
            .feature-card {
                padding: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .hero-content h1 {
                font-size: 1.75rem;
            }
            
            .hero-content p {
                font-size: 0.85rem;
            }
            
            .section-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
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
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                                <li><a class="dropdown-item" href="my-bookings.php">My Bookings</a></li>
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

    <!-- Hero Section -->
    <div class="hero-section">
        <!-- Floating Elements -->
        <div class="floating-elements">
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
        </div>
        
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6 hero-content">
                    <h1 class="display-3 fw-bold text-white mb-4" style="animation: fadeInUp 1.2s ease-out; text-shadow: 3px 3px 6px rgba(0,0,0,0.9); letter-spacing: 1px;">
                        Book Your Perfect Ride with MG Transport
                    </h1>
                    <p class="lead text-warning mb-4" style="animation: fadeInUp 1.2s ease-out 0.4s both; font-weight: 600; text-shadow: 2px 2px 4px rgba(0,0,0,0.9); font-size: 1.3rem; line-height: 1.8;">
                        Reliable vehicle hire services in Madang. Quality Toyota vehicles with competitive rates and professional service for all your transport needs. Choose from our wide selection of vehicles and book your ride with ease.
                    </p>
                    <div class="d-flex gap-3" style="animation: fadeInUp 1.2s ease-out 0.8s both;">
                        <a href="booking.php" class="btn btn-lg btn-animated" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border: none; color: #1e3a8a; font-weight: 700; padding: 15px 30px; box-shadow: 0 8px 25px rgba(251, 191, 36, 0.4);">
                            <i class="fas fa-calendar-check me-2"></i> Book Now
                        </a>
                        <a href="vehicles.php" class="btn btn-outline-light btn-lg btn-animated" style="border: 2px solid #fbbf24; color: #fbbf24; font-weight: 600; padding: 15px 30px;">
                            <i class="fas fa-car me-2"></i> View Vehicles
                        </a>
                    </div>
                </div>
                <!-- <div class="col-lg-6 hero-content">
                    <div class="hero-image">
                        <img id="hero-slideshow" src="<?php echo $vehicle_images[0]['src']; ?>" alt="<?php echo $vehicle_images[0]['alt']; ?>" class="img-fluid rounded shadow-lg">
                        <div class="image-overlay">
                            <div class="overlay-text">
                                <h3 id="hero-title" class="text-dark fw-bold mb-2" style="text-shadow: 2px 2px 4px rgba(255,255,255,0.9);"><?php echo $vehicle_images[0]['title']; ?></h3>
                                <p id="hero-subtitle" class="text-warning mb-3" style="font-weight: 600; text-shadow: 1px 1px 2px rgba(0,0,0,0.8);"><?php echo $vehicle_images[0]['subtitle']; ?></p>
                            </div>
                        </div>
                    </div>
                </div> -->
            </div>
        </div>
        
        <!-- Slideshow Controls -->
        <!-- <div class="slideshow-controls">
            <?php for ($i = 0; $i < count($vehicle_images); $i++): ?>
                <div class="slideshow-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></div>
            <?php endfor; ?>
        </div> -->
    </div>

    <!-- Features Section -->
    <section class="py-5" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);">
        <div class="container">
            <h2 class="text-center mb-5" style="color: #1e3a8a; font-weight: 800; font-size: 2.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.1);">Why Choose MG Transport?</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-lg" style="border-radius: 20px; transition: all 0.3s ease; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
                        <div class="card-body text-center p-4">
                            <div class="mb-4" style="width: 80px; height: 80px; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-clock fa-2x" style="color: #1e3a8a;"></i>
                            </div>
                            <h5 class="card-title" style="color: #1e3a8a; font-weight: 700; font-size: 1.3rem;">24/7 Availability</h5>
                            <p class="card-text" style="color: #64748b; line-height: 1.6;">Book your vehicle anytime, anywhere with our online platform.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-lg" style="border-radius: 20px; transition: all 0.3s ease; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
                        <div class="card-body text-center p-4">
                            <div class="mb-4" style="width: 80px; height: 80px; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-shield-alt fa-2x" style="color: #1e3a8a;"></i>
                            </div>
                            <h5 class="card-title" style="color: #1e3a8a; font-weight: 700; font-size: 1.3rem;">Safe & Reliable</h5>
                            <p class="card-text" style="color: #64748b; line-height: 1.6;">All our vehicles are regularly maintained and safety checked.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-lg" style="border-radius: 20px; transition: all 0.3s ease; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
                        <div class="card-body text-center p-4">
                            <div class="mb-4" style="width: 80px; height: 80px; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-dollar-sign fa-2x" style="color: #1e3a8a;"></i>
                            </div>
                            <h5 class="card-title" style="color: #1e3a8a; font-weight: 700; font-size: 1.3rem;">Competitive Rates</h5>
                            <p class="card-text" style="color: #64748b; line-height: 1.6;">Get the best value for your money with our competitive pricing.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Available Vehicles Section -->
    <section class="py-5" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
        <div class="container">
            <h2 class="text-center mb-5" style="color: white; font-weight: 800; font-size: 2.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">Available Vehicles</h2>
            <div class="row g-4">
                <?php
                $query = "SELECT * FROM vehicles WHERE status = 'available' LIMIT 6";
                $result = mysqli_query($conn, $query);
                while ($vehicle = mysqli_fetch_assoc($result)):
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-lg" style="border-radius: 20px; transition: all 0.3s ease; background: white; overflow: hidden;">
                        <img src="<?php echo htmlspecialchars($vehicle['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($vehicle['name']); ?>" style="height: 200px; object-fit: cover;">
                        <div class="card-body p-4">
                            <h5 class="card-title" style="color: #1e3a8a; font-weight: 700; font-size: 1.2rem;"><?php echo htmlspecialchars($vehicle['name']); ?></h5>
                            <p class="card-text" style="color: #64748b; line-height: 1.6;"><?php echo htmlspecialchars($vehicle['description']); ?></p>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #1e3a8a; font-weight: 600;">Available</span>
                                <span class="fw-bold" style="color: #1e3a8a; font-size: 1.1rem;"><?php echo formatCurrency($vehicle['rate_per_day']); ?>/day</span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent p-4">
                            <a href="booking.php?vehicle_id=<?php echo $vehicle['id']; ?>" class="btn w-100" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border: none; color: #1e3a8a; font-weight: 700; padding: 12px;">
                                Book Now
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <div class="text-center mt-5">
                <a href="vehicles.php" class="btn btn-lg" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border: none; color: #1e3a8a; font-weight: 700; padding: 15px 40px; box-shadow: 0 8px 25px rgba(251, 191, 36, 0.4);">
                    <i class="fas fa-car me-2"></i>View All Vehicles
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/hero-animations.js"></script>
    
    <script>
        // Background slideshow functionality only
        const vehicleImages = <?php echo json_encode($vehicle_images); ?>;
        let currentSlide = 0;
        let slideshowInterval;
        
        // Function to change background slide with smooth transition
        function changeBackgroundSlide(index) {
            const heroSection = document.querySelector('.hero-section');
            
            // Add fade out effect
            heroSection.style.opacity = '0.8';
            
            setTimeout(() => {
                // Update background image using CSS custom property
                heroSection.style.setProperty('--bg-image', `url('${vehicleImages[index].src}')`);
                
                // Fade back in
                heroSection.style.opacity = '1';
                
                currentSlide = index;
            }, 500); // Half second transition
        }
        
        // Function to start automatic background slideshow
        function startBackgroundSlideshow() {
            slideshowInterval = setInterval(() => {
                const nextSlide = (currentSlide + 1) % vehicleImages.length;
                changeBackgroundSlide(nextSlide);
            }, 4000); // 4 seconds (3.5s display + 0.5s transition)
        }
        
        // Event listeners for background slideshow
        document.addEventListener('DOMContentLoaded', function() {
            // Start automatic background slideshow
            startBackgroundSlideshow();
        });
    </script>
</body>
</html> 