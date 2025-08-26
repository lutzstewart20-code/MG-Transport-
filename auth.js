// Authentication Management
class AuthManager {
    constructor() {
        this.auth = firebaseServices.auth;
        this.db = firebaseServices.db;
        this.currentUser = null;
        this.init();
    }

    init() {
        // Listen for auth state changes
        this.auth.onAuthStateChanged((user) => {
            if (user) {
                this.currentUser = user;
                this.onUserLogin(user);
            } else {
                this.currentUser = null;
                this.onUserLogout();
            }
        });

        // Setup form event listeners
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Login form
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // Register form
        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', (e) => this.handleRegister(e));
        }

        // Logout button
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => this.handleLogout(e));
        }
    }

    async handleLogin(e) {
        e.preventDefault();
        
        const email = document.getElementById('loginEmail').value;
        const password = document.getElementById('loginPassword').value;
        const submitBtn = e.target.querySelector('button[type="submit"]');
        
        try {
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';
            
            // Sign in with Firebase
            const userCredential = await this.auth.signInWithEmailAndPassword(email, password);
            
            // Close modal
            const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
            if (loginModal) {
                loginModal.hide();
            }
            
            // Clear form
            e.target.reset();
            
            // Show success message
            this.showAlert('Login successful!', 'success');
            
        } catch (error) {
            console.error('Login error:', error);
            let errorMessage = 'Login failed. Please try again.';
            
            switch (error.code) {
                case 'auth/user-not-found':
                    errorMessage = 'No account found with this email address.';
                    break;
                case 'auth/wrong-password':
                    errorMessage = 'Incorrect password.';
                    break;
                case 'auth/invalid-email':
                    errorMessage = 'Invalid email address.';
                    break;
                case 'auth/too-many-requests':
                    errorMessage = 'Too many failed attempts. Please try again later.';
                    break;
            }
            
            this.showAlert(errorMessage, 'danger');
        } finally {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Login';
        }
    }

    async handleRegister(e) {
        e.preventDefault();
        
        const firstName = document.getElementById('firstName').value;
        const lastName = document.getElementById('lastName').value;
        const email = document.getElementById('registerEmail').value;
        const phone = document.getElementById('phone').value;
        const password = document.getElementById('registerPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const submitBtn = e.target.querySelector('button[type="submit"]');
        
        // Validation
        if (password !== confirmPassword) {
            this.showAlert('Passwords do not match.', 'danger');
            return;
        }
        
        if (password.length < 6) {
            this.showAlert('Password must be at least 6 characters long.', 'danger');
            return;
        }
        
        try {
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating Account...';
            
            // Create user with Firebase Auth
            const userCredential = await this.auth.createUserWithEmailAndPassword(email, password);
            const user = userCredential.user;
            
            // Create user profile in Firestore
            await this.db.collection('users').doc(user.uid).set({
                first_name: firstName,
                last_name: lastName,
                email: email,
                phone: phone,
                role: 'customer',
                status: 'active',
                created_at: firebase.firestore.FieldValue.serverTimestamp(),
                updated_at: firebase.firestore.FieldValue.serverTimestamp()
            });
            
            // Close modal
            const registerModal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
            if (registerModal) {
                registerModal.hide();
            }
            
            // Clear form
            e.target.reset();
            
            // Show success message
            this.showAlert('Account created successfully! You are now logged in.', 'success');
            
        } catch (error) {
            console.error('Registration error:', error);
            let errorMessage = 'Registration failed. Please try again.';
            
            switch (error.code) {
                case 'auth/email-already-in-use':
                    errorMessage = 'An account with this email already exists.';
                    break;
                case 'auth/invalid-email':
                    errorMessage = 'Invalid email address.';
                    break;
                case 'auth/weak-password':
                    errorMessage = 'Password is too weak.';
                    break;
            }
            
            this.showAlert(errorMessage, 'danger');
        } finally {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Account';
        }
    }

    async handleLogout(e) {
        e.preventDefault();
        
        try {
            await this.auth.signOut();
            this.showAlert('Logged out successfully.', 'info');
        } catch (error) {
            console.error('Logout error:', error);
            this.showAlert('Logout failed. Please try again.', 'danger');
        }
    }

    onUserLogin(user) {
        // Update UI for logged-in user
        document.getElementById('loginNav').classList.add('d-none');
        document.getElementById('registerNav').classList.add('d-none');
        document.getElementById('userNav').classList.remove('d-none');
        
        // Get user display name
        this.getUserProfile(user.uid).then(profile => {
            if (profile) {
                const displayName = profile.first_name || user.email;
                document.getElementById('userDisplayName').textContent = displayName;
            }
        });
        
        // Load user-specific content
        this.loadUserContent();
    }

    onUserLogout() {
        // Update UI for logged-out user
        document.getElementById('loginNav').classList.remove('d-none');
        document.getElementById('registerNav').classList.remove('d-none');
        document.getElementById('userNav').classList.add('d-none');
        
        // Clear user-specific content
        this.clearUserContent();
    }

    async getUserProfile(userId) {
        try {
            const doc = await this.db.collection('users').doc(userId).get();
            if (doc.exists) {
                return doc.data();
            }
            return null;
        } catch (error) {
            console.error('Error getting user profile:', error);
            return null;
        }
    }

    loadUserContent() {
        // Load user dashboard, bookings, etc.
        // This will be implemented in main.js
        if (window.loadUserContent) {
            window.loadUserContent();
        }
    }

    clearUserContent() {
        // Clear user-specific content
        if (window.clearUserContent) {
            window.clearUserContent();
        }
    }

    showAlert(message, type = 'info') {
        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Add to page
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Check if user is admin
    async isAdmin() {
        if (!this.currentUser) return false;
        
        try {
            const userDoc = await this.db.collection('users').doc(this.currentUser.uid).get();
            if (userDoc.exists) {
                const userData = userDoc.data();
                return userData.role === 'admin' || userData.role === 'super_admin';
            }
            return false;
        } catch (error) {
            console.error('Error checking admin status:', error);
            return false;
        }
    }

    // Get current user ID
    getCurrentUserId() {
        return this.currentUser ? this.currentUser.uid : null;
    }

    // Get current user email
    getCurrentUserEmail() {
        return this.currentUser ? this.currentUser.email : null;
    }
}

// Initialize authentication manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.authManager = new AuthManager();
});
