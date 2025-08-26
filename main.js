// MG Transport - Main JavaScript File

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Date picker enhancements
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        input.setAttribute('min', today);
        
        // Add change event for date validation
        input.addEventListener('change', function() {
            validateDateRange();
        });
    });

    // Price range slider (if exists)
    const priceRange = document.getElementById('price-range');
    if (priceRange) {
        priceRange.addEventListener('input', function() {
            document.getElementById('price-value').textContent = this.value;
        });
    }

    // Vehicle image lazy loading
    const vehicleImages = document.querySelectorAll('.vehicle-card img');
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        vehicleImages.forEach(img => {
            if (img.classList.contains('lazy')) {
                imageObserver.observe(img);
            }
        });
    }

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

    // Loading states for buttons
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="loading"></span> Processing...';
            }
        });
    });

    // Notification counter update
    updateNotificationCount();
});

// Date range validation
function validateDateRange() {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    if (startDate && endDate) {
        if (startDate.value && endDate.value) {
            if (new Date(startDate.value) > new Date(endDate.value)) {
                endDate.setCustomValidity('End date must be after start date');
            } else {
                endDate.setCustomValidity('');
            }
        }
    }
}

// Update notification count
function updateNotificationCount() {
    // This would typically make an AJAX call to get the count
    // For now, we'll just update the display
    const notificationBadge = document.querySelector('.notification-badge');
    if (notificationBadge) {
        // Simulate real-time updates
        setInterval(() => {
            // In a real application, this would fetch from the server
            // const count = fetchNotificationCount();
            // notificationBadge.textContent = count;
        }, 30000); // Check every 30 seconds
    }
}

// Currency formatter
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PG', {
        style: 'currency',
        currency: 'PGK'
    }).format(amount);
}

// GST Calculator
function calculateGST(subtotal, gstRate = 10) {
    const gstAmount = (subtotal * gstRate) / 100;
    const total = subtotal + gstAmount;
    return {
        subtotal: subtotal,
        gstAmount: gstAmount,
        total: total
    };
}

// Booking calculator
function calculateBooking(ratePerDay, startDate, endDate) {
    const start = new Date(startDate);
    const end = new Date(endDate);
    const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
    
    if (days <= 0) {
        return null;
    }
    
    const subtotal = ratePerDay * days;
    const calculation = calculateGST(subtotal);
    
    return {
        days: days,
        ratePerDay: ratePerDay,
        subtotal: subtotal,
        gstAmount: calculation.gstAmount,
        total: calculation.total
    };
}

// Vehicle availability checker
function checkVehicleAvailability(vehicleId, startDate, endDate) {
    // This would typically make an AJAX call to check availability
    return new Promise((resolve) => {
        // Simulate API call
        setTimeout(() => {
            // Mock response - in real app, this would be a server call
            const available = Math.random() > 0.3; // 70% chance of being available
            resolve(available);
        }, 500);
    });
}

// Form data serializer
function serializeForm(form) {
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    return data;
}

// AJAX helper function
function ajaxRequest(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject(new Error('Request failed'));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network error'));
        };
        
        if (data) {
            xhr.send(JSON.stringify(data));
        } else {
            xhr.send();
        }
    });
}

// Toast notification system
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Remove toast after it's hidden
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '1055';
    document.body.appendChild(container);
    return container;
}

// Export functions for global use
window.MGTransport = {
    formatCurrency,
    calculateGST,
    calculateBooking,
    checkVehicleAvailability,
    serializeForm,
    ajaxRequest,
    showToast
}; 