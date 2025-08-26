<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $subject = sanitizeInput($_POST['subject']);
    $message = sanitizeInput($_POST['message']);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Send email notification (you can customize this)
        $to = 'info@mgtransport.com';
        $email_subject = "Contact Form: $subject";
        $email_message = "
        <h2>New Contact Form Submission</h2>
        <p><strong>Name:</strong> $name</p>
        <p><strong>Email:</strong> $email</p>
        <p><strong>Phone:</strong> $phone</p>
        <p><strong>Subject:</strong> $subject</p>
        <p><strong>Message:</strong></p>
        <p>" . nl2br($message) . "</p>
        ";
        
        // You can implement actual email sending here
        // For now, we'll just show a success message
        $success_message = 'Thank you for your message! We will get back to you soon.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - MG Transport Services</title>
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
                    <h1 class="display-4 text-white fw-bold mb-4">Contact Us</h1>
                    <p class="lead text-white mb-4">Get in touch with our team. We're here to help with all your transportation needs.</p>
                    <div class="d-flex align-items-center text-white">
                        <div class="me-4">
                            <i class="fas fa-clock text-warning fs-4"></i>
                            <span class="ms-2 fw-bold">24/7</span>
                            <span class="ms-1">Support</span>
                        </div>
                        <div class="me-4">
                            <i class="fas fa-phone text-success fs-4"></i>
                            <span class="ms-2 fw-bold">+675 74291747</span>
                        </div>
                        <div>
                            <i class="fas fa-envelope text-info fs-4"></i>
                            <span class="ms-2 fw-bold">info@mgtransport.com</span>
                        </div>
                    </div>
                </div>
                                  <div class="col-lg-6 text-center">
                     <img src="assets/images/MG Logo.jpg" alt="MG Transport Services" class="img-fluid" style="max-height: 300px;">
                   </div>
            </div>
        </div>
    </div>

    <!-- Contact Form and Information -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <!-- Contact Form -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Send us a Message</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger"><?php echo $error_message; ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($success_message)): ?>
                                <div class="alert alert-success"><?php echo $success_message; ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" id="contactForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="subject" class="form-label">Subject *</label>
                                        <select class="form-select" id="subject" name="subject" required>
                                            <option value="">Select a subject...</option>
                                            <option value="General Inquiry">General Inquiry</option>
                                            <option value="Vehicle Rental">Vehicle Rental</option>
                                            <option value="Booking Support">Booking Support</option>
                                            <option value="Corporate Services">Corporate Services</option>
                                            <option value="Technical Support">Technical Support</option>
                                            <option value="Feedback">Feedback</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="message" class="form-label">Message *</label>
                                    <textarea class="form-control" id="message" name="message" rows="5" required placeholder="Please describe your inquiry or request..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Visit Us</h5>
                        </div>
                        <div class="card-body p-4">
                                                         <h6>Main Office</h6>
                             <p class="text-muted mb-3">
                                 Torokina Estate<br>
                                 Madang Province<br>
                                 North Coast, Papua New Guinea
                             </p>
                            <h6>Business Hours</h6>
                            <p class="text-muted mb-0">
                                <strong>Monday - Friday:</strong> 8:00 AM - 6:00 PM<br>
                                <strong>Saturday:</strong> 9:00 AM - 4:00 PM<br>
                                <strong>Sunday:</strong> Closed
                            </p>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-phone me-2"></i>Call Us</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <h6>Main Office</h6>
                                <p class="text-muted mb-1">+675 865765</p>
                                <small class="text-muted">Monday - Sunday, 8:00 AM - 12:00 PM</small>
                            </div>
                            <div class="mb-3">
                                <h6>24/7 Support</h6>
                                <p class="text-muted mb-1">+675 70000000</p>
                                <small class="text-muted">Emergency and after-hours support</small>
                            </div>
                            <div>
                                <h6>WhatsApp</h6>
                                <p class="text-muted mb-0">+675 1234 5680</p>
                                <small class="text-muted">Quick messaging for urgent requests</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-warning text-white">
                            <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Email Us</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <h6>General Inquiries</h6>
                                <p class="text-muted mb-1">info@mgtransport.com</p>
                                <small class="text-muted">For general questions and information</small>
                            </div>
                            <div class="mb-3">
                                <h6>Support</h6>
                                <p class="text-muted mb-1">support@mgtransport.com</p>
                                <small class="text-muted">Technical support and booking assistance</small>
                            </div>
                            <div>
                                <h6>Corporate</h6>
                                <p class="text-muted mb-0">corporate@mgtransport.com</p>
                                <small class="text-muted">Business partnerships and corporate services</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="mb-4">Frequently Asked Questions</h2>
                    <p class="lead text-muted mb-5">Find quick answers to common questions about our services.</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq1">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                    How do I book a vehicle?
                                </button>
                            </h2>
                            <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    You can book a vehicle through our website by browsing available vehicles, selecting your dates, and completing the booking form. You can also call us directly for assistance.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                    What documents do I need to rent a vehicle?
                                </button>
                            </h2>
                            <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    You'll need a valid driver's license, proof of identity, and a credit card for the security deposit. International drivers may need an International Driving Permit.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                    Do you offer insurance coverage?
                                </button>
                            </h2>
                            <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes, all our vehicles come with comprehensive insurance coverage. Additional coverage options are available for extra protection.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq4">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                                    What happens if I have an emergency?
                                </button>
                            </h2>
                            <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We provide 24/7 roadside assistance. Call our emergency number +675 1234 5679 for immediate help with breakdowns, accidents, or other emergencies.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq5">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5">
                                    Can I cancel or modify my booking?
                                </button>
                            </h2>
                            <div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes, you can modify or cancel your booking up to 24 hours before the rental start time. Contact us directly for any changes to your reservation.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                                         <h2 class="mb-4">Find Us</h2>
                     <p class="lead text-muted mb-5">Visit our main office in Torokina Estate, Madang Province for in-person assistance.</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <!-- Placeholder for map - you can replace this with actual Google Maps embed -->
                            <div class="bg-light d-flex align-items-center justify-content-center" style="height: 400px;">
                                <div class="text-center">
                                    <i class="fas fa-map-marked-alt text-primary fs-1 mb-3"></i>
                                                                         <h5>Interactive Map</h5>
                                     <p class="text-muted">Torokina Estate, Madang Province, North Coast, Papua New Guinea</p>
                                    <a href="https://maps.google.com" target="_blank" class="btn btn-primary">
                                        <i class="fas fa-external-link-alt me-2"></i>Open in Google Maps
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value.trim();
            
            if (!name || !email || !subject || !message) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            if (!email.includes('@')) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
        });
    </script>
</body>
</html> 