<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <div class="container-fluid py-5" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 text-white fw-bold mb-4">About MG Transport Services</h1>
                    <p class="lead text-white mb-4">Your trusted partner for reliable and professional transportation solutions across Papua New Guinea.</p>
                    <div class="d-flex align-items-center text-white">
                        <div class="me-4">
                            <i class="fas fa-star text-warning fs-4"></i>
                            <span class="ms-2 fw-bold">4.8/5</span>
                        </div>
                        <div class="me-4">
                            <i class="fas fa-users text-info fs-4"></i>
                            <span class="ms-2 fw-bold">500+</span>
                            <span class="ms-1">Happy Customers</span>
                        </div>
                        <div>
                            <i class="fas fa-car text-success fs-4"></i>
                            <span class="ms-2 fw-bold">50+</span>
                            <span class="ms-1">Vehicles</span>
                        </div>
                    </div>
                </div>
                                  <div class="col-lg-6 text-center">
                     <img src="assets/images/MG Logo.jpg" alt="MG Transport Services" class="img-fluid" style="max-height: 300px;">
                   </div>
            </div>
        </div>
    </div>

    <!-- Company Story -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="mb-4">Our Story</h2>
                    <p class="lead text-muted mb-5">
                        Founded with a vision to provide exceptional transportation services, MG Transport Services has been serving the people of Papua New Guinea since our establishment. We understand the unique challenges of transportation in our beautiful country and have built our services around reliability, safety, and customer satisfaction.
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-shield-alt text-primary fs-2"></i>
                            </div>
                            <h5 class="card-title">Safety First</h5>
                            <p class="card-text text-muted">We prioritize the safety of our customers and drivers above all else. All our vehicles undergo regular maintenance and safety checks.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-clock text-success fs-2"></i>
                            </div>
                            <h5 class="card-title">24/7 Service</h5>
                            <p class="card-text text-muted">We understand that transportation needs don't follow a schedule. Our team is available round the clock to serve you.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-handshake text-warning fs-2"></i>
                            </div>
                            <h5 class="card-title">Trusted Partner</h5>
                            <p class="card-text text-muted">Building long-term relationships with our customers through transparent pricing, reliable service, and exceptional support.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="mb-4">Our Services</h2>
                    <p class="lead text-muted mb-5">
                        We offer a comprehensive range of transportation services to meet all your needs, from personal travel to corporate transportation.
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary bg-opacity-10 rounded p-3 me-3">
                                    <i class="fas fa-car text-primary fs-3"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Vehicle Rental</h5>
                                    <p class="text-muted mb-0">Flexible rental periods with competitive rates</p>
                                </div>
                            </div>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Daily, weekly, and monthly rentals</li>
                                <li><i class="fas fa-check text-success me-2"></i>Wide range of vehicle types</li>
                                <li><i class="fas fa-check text-success me-2"></i>Comprehensive insurance coverage</li>
                                <li><i class="fas fa-check text-success me-2"></i>24/7 roadside assistance</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success bg-opacity-10 rounded p-3 me-3">
                                    <i class="fas fa-route text-success fs-3"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Chauffeur Services</h5>
                                    <p class="text-muted mb-0">Professional drivers for your convenience</p>
                                </div>
                            </div>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Experienced and licensed drivers</li>
                                <li><i class="fas fa-check text-success me-2"></i>Airport transfers and corporate travel</li>
                                <li><i class="fas fa-check text-success me-2"></i>Event transportation</li>
                                <li><i class="fas fa-check text-success me-2"></i>Tourist and sightseeing trips</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-info bg-opacity-10 rounded p-3 me-3">
                                    <i class="fas fa-building text-info fs-3"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Corporate Solutions</h5>
                                    <p class="text-muted mb-0">Tailored transportation for businesses</p>
                                </div>
                            </div>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Fleet management services</li>
                                <li><i class="fas fa-check text-success me-2"></i>Employee transportation</li>
                                <li><i class="fas fa-check text-success me-2"></i>Client pickup and delivery</li>
                                <li><i class="fas fa-check text-success me-2"></i>Customized billing solutions</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning bg-opacity-10 rounded p-3 me-3">
                                    <i class="fas fa-tools text-warning fs-3"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Maintenance & Support</h5>
                                    <p class="text-muted mb-0">Keeping our fleet in top condition</p>
                                </div>
                            </div>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Regular maintenance schedules</li>
                                <li><i class="fas fa-check text-success me-2"></i>24/7 technical support</li>
                                <li><i class="fas fa-check text-success me-2"></i>Emergency roadside assistance</li>
                                <li><i class="fas fa-check text-success me-2"></i>Quality assurance programs</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="mb-4">Why Choose MG Transport Services?</h2>
                    <p class="lead text-muted mb-5">
                        We stand out from the competition through our commitment to excellence and customer satisfaction.
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="text-center">
                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px;">
                            <i class="fas fa-medal text-primary fs-1"></i>
                        </div>
                        <h5>Quality Assurance</h5>
                        <p class="text-muted">Rigorous quality standards ensure every journey is safe and comfortable.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="text-center">
                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px;">
                            <i class="fas fa-money-bill-wave text-success fs-1"></i>
                        </div>
                        <h5>Competitive Pricing</h5>
                        <p class="text-muted">Transparent pricing with no hidden fees or surprise charges.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="text-center">
                        <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px;">
                            <i class="fas fa-headset text-info fs-1"></i>
                        </div>
                        <h5>24/7 Support</h5>
                        <p class="text-muted">Our customer support team is always ready to assist you.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="text-center">
                        <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px;">
                            <i class="fas fa-map-marked-alt text-warning fs-1"></i>
                        </div>
                        <h5>Nationwide Coverage</h5>
                        <p class="text-muted">Serving customers across Papua New Guinea with reliable transportation.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Information -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="mb-4">Get in Touch</h2>
                    <p class="lead text-muted mb-5">
                        Ready to experience the best transportation services? Contact us today!
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-map-marker-alt text-primary fs-2"></i>
                            </div>
                            <h5>Visit Us</h5>
                            <p class="text-muted mb-0">
                                123 Transport Street<br>
                                Port Moresby, NCD<br>
                                Papua New Guinea
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-phone text-success fs-2"></i>
                            </div>
                            <h5>Call Us</h5>
                            <p class="text-muted mb-0">
                                <strong>Main Office:</strong><br>
                                +675 1234 5678<br>
                                <strong>24/7 Support:</strong><br>
                                +675 1234 5679
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center p-4">
                            <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-envelope text-info fs-2"></i>
                            </div>
                            <h5>Email Us</h5>
                            <p class="text-muted mb-0">
                                <strong>General Inquiries:</strong><br>
                                info@mgtransport.com<br>
                                <strong>Support:</strong><br>
                                support@mgtransport.com
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-5">
                <div class="col-lg-8 mx-auto text-center">
                    <h4 class="mb-4">Ready to Book Your Vehicle?</h4>
                    <p class="text-muted mb-4">Experience the difference with MG Transport Services</p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="vehicles.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-car me-2"></i>Browse Vehicles
                        </a>
                        <a href="booking.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-calendar-check me-2"></i>Book Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 