// Main Application Logic
class MGTransportApp {
    constructor() {
        this.db = firebaseServices.db;
        this.storage = firebaseServices.storage;
        this.currentSection = 'home';
        this.init();
    }

    init() {
        // Setup navigation
        this.setupNavigation();
        
        // Load initial content
        this.loadVehicles();
        
        // Setup contact form
        this.setupContactForm();
        
        // Setup smooth scrolling
        this.setupSmoothScrolling();
    }

    setupNavigation() {
        // Handle navigation clicks
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link[href^="#"]');
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = link.getAttribute('href').substring(1);
                this.navigateToSection(targetId);
            });
        });

        // Handle user menu navigation
        const userMenuLinks = document.querySelectorAll('#userNav .dropdown-item[href^="#"]');
        userMenuLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = link.getAttribute('href').substring(1);
                this.navigateToSection(targetId);
            });
        });
    }

    navigateToSection(sectionId) {
        // Hide all sections
        const sections = document.querySelectorAll('main > section');
        sections.forEach(section => {
            section.style.display = 'none';
        });

        // Show target section
        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            targetSection.style.display = 'block';
            this.currentSection = sectionId;
            
            // Load section-specific content
            this.loadSectionContent(sectionId);
        }
    }

    async loadSectionContent(sectionId) {
        switch (sectionId) {
            case 'dashboard':
                await this.loadDashboard();
                break;
            case 'my-bookings':
                await this.loadMyBookings();
                break;
            case 'profile':
                await this.loadProfile();
                break;
            case 'vehicles':
                await this.loadVehicles();
                break;
        }
    }

    async loadVehicles() {
        try {
            const vehiclesContainer = document.getElementById('vehiclesContainer');
            if (!vehiclesContainer) return;

            // Show loading state
            vehiclesContainer.innerHTML = `
                <div class="col-12 text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;

            // Get vehicles from Firestore
            const snapshot = await this.db.collection('vehicles')
                .where('status', '==', 'available')
                .get();

            if (snapshot.empty) {
                vehiclesContainer.innerHTML = `
                    <div class="col-12 text-center">
                        <p class="text-muted">No vehicles available at the moment.</p>
                    </div>
                `;
                return;
            }

            // Build vehicles HTML
            let vehiclesHTML = '';
            snapshot.forEach(doc => {
                const vehicle = doc.data();
                vehiclesHTML += this.createVehicleCard(vehicle, doc.id);
            });

            vehiclesContainer.innerHTML = vehiclesHTML;

            // Add event listeners to book buttons
            this.setupVehicleBooking();

        } catch (error) {
            console.error('Error loading vehicles:', error);
            const vehiclesContainer = document.getElementById('vehiclesContainer');
            if (vehiclesContainer) {
                vehiclesContainer.innerHTML = `
                    <div class="col-12 text-center">
                        <p class="text-danger">Error loading vehicles. Please try again.</p>
                    </div>
                `;
            }
        }
    }

    createVehicleCard(vehicle, vehicleId) {
        const statusClass = `status-${vehicle.status}`;
        const statusText = vehicle.status.charAt(0).toUpperCase() + vehicle.status.slice(1).replace('_', ' ');
        
        return `
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card vehicle-card h-100">
                    <div class="position-relative">
                        <img src="${vehicle.image_url || 'images/default-vehicle.jpg'}" 
                             class="card-img-top" alt="${vehicle.name}">
                        <span class="badge ${statusClass} status-badge">${statusText}</span>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">${vehicle.name}</h5>
                        <p class="card-text">${vehicle.description || 'Professional vehicle for your transportation needs.'}</p>
                        <div class="mt-auto">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Type:</small><br>
                                    <strong>${vehicle.vehicle_type ? vehicle.vehicle_type.replace(/_/g, ' ').toUpperCase() : 'N/A'}</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Seats:</small><br>
                                    <strong>${vehicle.seats || 'N/A'}</strong>
                                </div>
                            </div>
                            <div class="price mb-3">
                                <small class="text-muted">Rate per day:</small><br>
                                <strong>$${vehicle.rate_per_day || 0}</strong>
                            </div>
                            ${vehicle.status === 'available' ? 
                                `<button class="btn btn-primary w-100 book-vehicle-btn" data-vehicle-id="${vehicleId}">
                                    <i class="fas fa-calendar-plus me-2"></i>Book Now
                                </button>` : 
                                `<button class="btn btn-secondary w-100" disabled>
                                    <i class="fas fa-ban me-2"></i>Not Available
                                </button>`
                            }
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    setupVehicleBooking() {
        const bookButtons = document.querySelectorAll('.book-vehicle-btn');
        bookButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const vehicleId = e.target.dataset.vehicleId;
                this.showBookingModal(vehicleId);
            });
        });
    }

    showBookingModal(vehicleId) {
        // Check if user is logged in
        if (!window.authManager || !window.authManager.currentUser) {
            window.authManager.showAlert('Please login to book a vehicle.', 'warning');
            return;
        }

        // Create and show booking modal
        const modalHTML = `
            <div class="modal fade" id="bookingModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Book Vehicle</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="bookingForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Pickup Date</label>
                                        <input type="date" class="form-control" id="pickupDate" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Return Date</label>
                                        <input type="date" class="form-control" id="returnDate" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Pickup Location</label>
                                        <input type="text" class="form-control" id="pickupLocation" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Dropoff Location</label>
                                        <input type="text" class="form-control" id="dropoffLocation" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Special Requests</label>
                                    <textarea class="form-control" id="specialRequests" rows="3"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Driver License</label>
                                    <input type="file" class="form-control" id="driverLicense" accept="image/*,.pdf" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-calendar-check me-2"></i>Submit Booking
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
        modal.show();

        // Setup form submission
        this.setupBookingForm(vehicleId);

        // Remove modal when hidden
        document.getElementById('bookingModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    setupBookingForm(vehicleId) {
        const bookingForm = document.getElementById('bookingForm');
        if (!bookingForm) return;

        // Set minimum dates
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('pickupDate').min = today;
        document.getElementById('returnDate').min = today;

        // Update return date minimum when pickup date changes
        document.getElementById('pickupDate').addEventListener('change', (e) => {
            document.getElementById('returnDate').min = e.target.value;
        });

        // Handle form submission
        bookingForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.submitBooking(vehicleId, e.target);
        });
    }

    async submitBooking(vehicleId, form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        try {
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

            // Get form data
            const formData = new FormData(form);
            const bookingData = {
                user_id: window.authManager.getCurrentUserId(),
                vehicle_id: vehicleId,
                pickup_date: formData.get('pickupDate'),
                return_date: formData.get('returnDate'),
                pickup_location: formData.get('pickupLocation'),
                dropoff_location: formData.get('dropoffLocation'),
                special_requests: formData.get('specialRequests'),
                status: 'pending',
                payment_status: 'pending',
                created_at: firebase.firestore.FieldValue.serverTimestamp(),
                updated_at: firebase.firestore.FieldValue.serverTimestamp()
            };

            // Calculate dates and total
            const pickupDate = new Date(bookingData.pickup_date);
            const returnDate = new Date(bookingData.return_date);
            const totalDays = Math.ceil((returnDate - pickupDate) / (1000 * 60 * 60 * 24));

            // Get vehicle details for pricing
            const vehicleDoc = await this.db.collection('vehicles').doc(vehicleId).get();
            if (!vehicleDoc.exists) {
                throw new Error('Vehicle not found');
            }

            const vehicle = vehicleDoc.data();
            const ratePerDay = vehicle.rate_per_day || 0;
            const subtotal = ratePerDay * totalDays;
            const gstAmount = subtotal * 0.1; // 10% GST
            const totalAmount = subtotal + gstAmount;

            // Add calculated fields
            bookingData.total_days = totalDays;
            bookingData.rate_per_day = ratePerDay;
            bookingData.subtotal = subtotal;
            bookingData.gst_amount = gstAmount;
            bookingData.total_amount = totalAmount;

            // Upload driver license if provided
            const driverLicenseFile = formData.get('driverLicense');
            if (driverLicenseFile && driverLicenseFile.size > 0) {
                const licensePath = `licenses/${window.authManager.getCurrentUserId()}_${Date.now()}_${driverLicenseFile.name}`;
                const licenseRef = this.storage.ref().child(licensePath);
                await licenseRef.put(driverLicenseFile);
                const licenseUrl = await licenseRef.getDownloadURL();
                bookingData.driver_license_path = licenseUrl;
            }

            // Save booking to Firestore
            const bookingRef = await this.db.collection('bookings').add(bookingData);

            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('bookingModal'));
            modal.hide();

            // Show success message
            window.authManager.showAlert('Booking submitted successfully! Waiting for admin approval.', 'success');

            // Navigate to my bookings
            this.navigateToSection('my-bookings');

        } catch (error) {
            console.error('Error submitting booking:', error);
            window.authManager.showAlert('Error submitting booking. Please try again.', 'danger');
        } finally {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    setupContactForm() {
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', (e) => {
                e.preventDefault();
                // Handle contact form submission
                window.authManager.showAlert('Thank you for your message. We will get back to you soon!', 'success');
                contactForm.reset();
            });
        }
    }

    setupSmoothScrolling() {
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // User content management
    async loadDashboard() {
        // Implementation for user dashboard
        console.log('Loading dashboard...');
    }

    async loadMyBookings() {
        // Implementation for user bookings
        console.log('Loading my bookings...');
    }

    async loadProfile() {
        // Implementation for user profile
        console.log('Loading profile...');
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.mgTransportApp = new MGTransportApp();
});

// Global functions for auth manager
window.loadUserContent = function() {
    if (window.mgTransportApp) {
        window.mgTransportApp.loadDashboard();
    }
};

window.clearUserContent = function() {
    // Clear user-specific content when logged out
    console.log('Clearing user content...');
};
