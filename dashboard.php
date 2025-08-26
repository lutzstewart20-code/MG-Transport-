<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Get essential statistics
$stats = getBookingStats($conn);
$maintenance_due = getMaintenanceDueVehicles($conn);
$insurance_due = getInsuranceDueVehicles($conn);
$registration_due = getRegistrationDueVehicles($conn);

// Ensure stats array has all required keys
if (!isset($stats['total_bookings'])) $stats['total_bookings'] = 0;
if (!isset($stats['active_bookings'])) $stats['active_bookings'] = 0;
if (!isset($stats['total_revenue'])) $stats['total_revenue'] = 0;
if (!isset($stats['total_users'])) $stats['total_users'] = 0;

// Ensure arrays are initialized
if (!is_array($maintenance_due)) $maintenance_due = [];
if (!is_array($insurance_due)) $insurance_due = [];
if (!is_array($registration_due)) $registration_due = [];

// Get recent bookings
$recent_bookings_query = "SELECT b.*, v.name as vehicle_name, u.first_name, u.last_name 
                         FROM bookings b 
                         JOIN vehicles v ON b.vehicle_id = v.id 
                         JOIN users u ON b.user_id = u.id 
                         ORDER BY b.created_at DESC LIMIT 8";
$recent_bookings = mysqli_query($conn, $recent_bookings_query);

// Check for query errors
if (!$recent_bookings) {
    error_log("MySQL Error: " . mysqli_error($conn));
    $recent_bookings = false;
}

// Get monthly revenue data for chart
$monthly_revenue_query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
                         SUM(total_amount) as revenue 
                         FROM bookings 
                         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
                         GROUP BY month 
                         ORDER BY month";
$monthly_revenue = mysqli_query($conn, $monthly_revenue_query);

// Check for query errors
if (!$monthly_revenue) {
    error_log("MySQL Error: " . mysqli_error($conn));
    $monthly_revenue = false;
}

$chart_labels = [];
$chart_data = [];
if ($monthly_revenue) {
    while ($row = mysqli_fetch_assoc($monthly_revenue)) {
        $chart_labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $chart_data[] = $row['revenue'];
    }
}

// Ensure we have at least some data for the chart
if (empty($chart_labels)) {
    $chart_labels = ['Jan 2024', 'Feb 2024', 'Mar 2024', 'Apr 2024', 'May 2024', 'Jun 2024'];
    $chart_data = [0, 0, 0, 0, 0, 0];
}

// Get pending payments for admin notification
$pending_payments_query = "SELECT COUNT(*) as pending_count FROM payments WHERE status = 'pending'";
$pending_payments_result = mysqli_query($conn, $pending_payments_query);
$pending_payments = mysqli_fetch_assoc($pending_payments_result)['pending_count'] ?? 0;

// Get recent pending payments
$recent_pending_query = "SELECT p.*, u.first_name, u.last_name, v.name as vehicle_name 
                        FROM payments p 
                        JOIN bookings b ON p.booking_id = b.id 
                        JOIN users u ON b.user_id = u.id 
                        JOIN vehicles v ON b.vehicle_id = v.id 
                        WHERE p.status = 'pending' 
                        ORDER BY p.created_at DESC LIMIT 5";
$recent_pending_result = mysqli_query($conn, $recent_pending_query);
$recent_pending_payments = [];
if ($recent_pending_result) {
    while ($row = mysqli_fetch_assoc($recent_pending_result)) {
        $recent_pending_payments[] = $row;
    }
}

// Get new workflow statistics
$pending_agreements_query = "SELECT COUNT(*) as pending_count FROM vehicle_agreements WHERE agreement_status = 'pending'";
$pending_agreements_result = mysqli_query($conn, $pending_agreements_query);
$pending_agreements = mysqli_fetch_assoc($pending_agreements_result)['pending_count'] ?? 0;

$confirmed_no_payment_query = "SELECT COUNT(*) as confirmed_count FROM bookings WHERE status = 'confirmed' AND payment_status = 'pending'";
$confirmed_no_payment_result = mysqli_query($conn, $confirmed_no_payment_query);
$confirmed_no_payment = mysqli_fetch_assoc($confirmed_no_payment_result)['confirmed_count'] ?? 0;

$payment_verification_query = "SELECT COUNT(*) as verification_count FROM bookings WHERE payment_status = 'pending_verification'";
$payment_verification_result = mysqli_query($conn, $payment_verification_query);
$payment_verification = mysqli_fetch_assoc($payment_verification_result)['verification_count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#1e3a8a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Admin Dashboard - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-compact.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #3b82f6;
            --accent-color: #fbbf24;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --border-color: #e5e7eb;
        }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            scroll-behavior: smooth;
        }

        /* Smooth transitions for all elements */
        * {
            transition: all 0.2s ease;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
        }

        /* Admin Dashboard Container */
        .admin-container {
            padding: 2rem 0;
        }

        /* Enhanced Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            border-radius: 20px;
            padding: 1.75rem;
            margin-bottom: 2rem;
            box-shadow: 0 15px 40px rgba(30, 58, 138, 0.3);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(251, 191, 36, 0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        .welcome-section::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, transparent 70%);
            animation: float 8s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .text-white-75 {
            color: rgba(255, 255, 255, 0.75) !important;
        }

        .text-white-50 {
            color: rgba(255, 255, 255, 0.5) !important;
        }

        /* Enhanced Cards */
        .dashboard-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            transition: all 0.3s ease;
            overflow: hidden;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
        }

        .dashboard-card .card-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 5px solid #fbbf24;
            position: relative;
            overflow: hidden;
            border: none;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #fbbf24 0%, #f59e0b 100%);
            border-radius: 20px 20px 0 0;
        }

        .stat-card.success::before {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        }

        .stat-card.warning::before {
            background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-card.info::before {
            background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-card.success {
            border-left-color: var(--success-color);
        }

        .stat-card.warning {
            border-left-color: var(--warning-color);
        }

        .stat-card.info {
            border-left-color: var(--secondary-color);
        }

        .stat-card.danger {
            border-left-color: var(--danger-color);
        }

        /* Notification Badges */
        .badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
        }

        .badge.bg-info {
            background-color: #0dcaf0 !important;
        }

        .badge.bg-warning {
            background-color: #ffc107 !important;
            color: #000 !important;
        }

        .badge.bg-danger {
            background-color: #dc3545 !important;
        }

        /* Statistics */
        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--dark-color);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-card p {
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        .stat-description {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        /* Stats Icon */
        .stats-icon {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1rem;
        }

        .stat-card .stats-icon {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #1e3a8a;
        }

        .stat-card.success .stats-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .stat-card.warning .stats-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .stat-card.info .stats-icon {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .stat-card.danger .stats-icon {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        /* Enhanced Tables */
        .table-modern {
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-modern th {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table-modern td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .table-modern tbody tr:hover {
            background: rgba(251, 191, 36, 0.05);
        }

        /* Badges */
        .badge-modern {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Buttons */
        .btn-modern {
            border-radius: 12px;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-modern:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #1e3a8a;
            box-shadow: 0 8px 25px rgba(251, 191, 36, 0.4);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #1e3a8a;
            box-shadow: 0 15px 30px rgba(251, 191, 36, 0.5);
        }

        .btn-outline-primary {
            border: 2px solid #fbbf24;
            color: #fbbf24;
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: #fbbf24;
            color: #1e3a8a;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(251, 191, 36, 0.3);
        }

        /* Alerts */
        .alert-modern {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            padding: 1rem;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .action-btn {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border: none;
            color: #1e3a8a;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(251, 191, 36, 0.4);
            text-decoration: none;
            display: inline-block;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(251, 191, 36, 0.5);
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #1e3a8a;
        }

        /* Enhanced Navigation Styles */
        .navbar-nav .nav-link {
            padding: 1rem 1.25rem;
            margin: 0 0.25rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .navbar {
            min-height: 80px;
        }

        .navbar-brand {
            padding: 0.5rem 0;
        }

        .navbar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }

        .navbar-nav .nav-link.active {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24 !important;
            font-weight: 700;
        }

        .navbar-nav .nav-link.active::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 3px;
            background: #fbbf24;
            border-radius: 2px;
        }

        .dropdown-menu {
            margin-top: 0.5rem;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .dropdown-item {
            padding: 0.75rem 1.5rem;
            transition: all 0.2s ease;
            border-radius: 8px;
            margin: 0.25rem;
        }

        .dropdown-item:hover {
            background: rgba(30, 58, 138, 0.1);
            transform: translateX(5px);
        }

        /* Enhanced Responsive Design */
        @media (max-width: 1200px) {
            .navbar-nav .nav-link {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .welcome-section h1 {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 992px) {
            .navbar-nav .nav-link {
                padding: 0.75rem 1rem;
                font-size: 0.85rem;
            }
            
            .stat-number {
                font-size: 1.8rem;
            }
            
            .welcome-section {
                padding: 1.5rem 1.25rem;
            }
            
            .welcome-section h1 {
                font-size: 1.6rem;
            }
            
            .stat-card {
                padding: 1.25rem;
            }
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 1rem 0;
            }
            
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .navbar-nav .nav-link {
                margin: 0.25rem 0;
                text-align: center;
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }

            .navbar-collapse {
                background: rgba(30, 58, 138, 0.95);
                border-radius: 12px;
                margin-top: 1rem;
                padding: 1rem;
                backdrop-filter: blur(10px);
            }
            
            .stat-number {
                font-size: 1.6rem;
            }
            
            .welcome-section {
                padding: 1.25rem 1rem;
                margin-bottom: 1.5rem;
            }
            
            .welcome-section h1 {
                font-size: 1.5rem;
                text-align: center;
            }
            
            .welcome-section .lead {
                text-align: center;
            }
            
            .stat-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .dashboard-card {
                margin-bottom: 1rem;
            }
            
            .quick-actions .row > div {
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 576px) {
            .admin-container {
                padding: 0.5rem 0;
            }
            
            .container {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            
            .welcome-section {
                padding: 1rem 0.75rem;
                border-radius: 15px;
            }
            
            .welcome-section h1 {
                font-size: 1.3rem;
            }
            
            .stat-number {
                font-size: 1.4rem;
            }
            
            .stat-card {
                padding: 0.875rem;
                border-radius: 15px;
            }
            
            .dashboard-card {
                border-radius: 15px;
            }
            
            .navbar-brand .d-none.d-md-block {
                display: none !important;
            }
            
            .navbar-brand img {
                width: 40px !important;
                height: 40px !important;
            }
        }

        /* Touch-friendly interactions for mobile */
        @media (hover: none) and (pointer: coarse) {
            .nav-link, .btn, .dropdown-item {
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .stat-card:hover {
                transform: none;
            }
            
            .dashboard-card:hover {
                transform: none;
            }
        }

        /* Mobile-specific enhancements */
        @media (max-width: 576px) {
            .stats-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .stat-card {
                text-align: center;
            }
            
            .welcome-section::before,
            .welcome-section::after {
                display: none;
            }
            
            .btn-modern {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
        }

        /* Tablet-specific optimizations */
        @media (min-width: 577px) and (max-width: 991px) {
            .stat-card {
                text-align: center;
            }
            
            .stats-icon {
                margin: 0 auto 1rem auto;
            }
            
            .welcome-section h1 {
                font-size: 1.6rem;
            }
            
            .stat-number {
                font-size: 1.8rem;
            }
        }

        /* High-DPI displays */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .stats-icon {
                border: 2px solid;
            }
            
            .navbar-brand img {
                border: 2px solid rgba(251, 191, 36, 0.3);
            }
        }

        /* Landscape orientation for mobile */
        @media (max-width: 768px) and (orientation: landscape) {
            .welcome-section {
                padding: 1rem;
            }
            
            .welcome-section h1 {
                font-size: 1.6rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
        }

        /* Loading Animation */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }
    </style>
</head>
<body>
    <!-- Enhanced Mobile-Responsive Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 1rem 0;">
        <div class="container-fluid px-3 px-md-4">
            <a class="navbar-brand" href="dashboard.php">
                <div class="d-flex align-items-center">
                    <img src="../assets/images/MG Logo.jpg" alt="MG Transport Services" class="me-2 me-md-3" style="width: 45px; height: 45px; border-radius: 50%; background: #fbbf24; box-shadow: 0 4px 15px rgba(251, 191, 36, 0.3);">
                    <div class="d-none d-md-block">
                        <div class="text-xl fw-bold text-white" style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);">MG TRANSPORT SERVICES</div>
                        <div class="text-sm text-warning fw-bold" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">ADMIN PANEL</div>
                    </div>
                    <div class="d-md-none">
                        <div class="text-sm fw-bold text-white">MG TRANSPORT</div>
                        <div class="text-xs text-warning">ADMIN</div>
                    </div>
                </div>
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation" style="padding: 0.5rem;">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto flex-wrap justify-content-center justify-content-md-start">
                    <li class="nav-item">
                        <a class="nav-link active fw-semibold" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i><span class="d-none d-sm-inline">Dashboard</span><span class="d-sm-none">Home</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="vehicles.php">
                            <i class="fas fa-car me-2"></i><span class="d-none d-sm-inline">Vehicles</span><span class="d-sm-none">Cars</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="bookings.php">
                            <i class="fas fa-calendar-check me-2"></i><span class="d-none d-sm-inline">Bookings</span><span class="d-sm-none">Book</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="tracking-dashboard.php">
                            <i class="fas fa-satellite-dish me-2"></i><span class="d-none d-sm-inline">GPS Tracking</span><span class="d-sm-none">GPS</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="tracking-alerts.php">
                            <i class="fas fa-bell me-2"></i><span class="d-none d-sm-inline">Alerts</span><span class="d-sm-none">Alert</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="users.php">
                            <i class="fas fa-users me-2"></i><span class="d-none d-sm-inline">Users</span><span class="d-sm-none">Users</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i><span class="d-none d-sm-inline">Reports</span><span class="d-sm-none">Stats</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="maintenance.php">
                            <i class="fas fa-tools me-2"></i><span class="d-none d-sm-inline">Maintenance</span><span class="d-sm-none">Tools</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold" href="verification-codes.php">
                            <i class="fas fa-shield-alt me-2"></i><span class="d-none d-sm-inline">Verification</span><span class="d-sm-none">Code</span>
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fw-semibold" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-shield me-2"></i> <span class="d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span><span class="d-sm-none">Admin</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border: none; min-width: 200px;">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../index.php"><i class="fas fa-home me-2"></i>View Site</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="admin-container">
        <div class="container">
            <?php displayMessage(); ?>
            
            <!-- Enhanced Mobile-Responsive Welcome Section -->
            <div class="welcome-section">
                <div class="row align-items-center">
                    <div class="col-lg-8 col-md-7 col-12 mb-3 mb-md-0">
                        <h1 class="h2 fw-bold mb-2 text-white text-center text-md-start">
                            Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>! 👋
                        </h1>
                        <p class="mb-0 text-white-75 text-center text-md-start">Here's what's happening with your transport business today.</p>
                        <div class="mt-3 text-center text-md-start">
                            <span class="badge bg-warning text-dark me-2 mb-2 mb-md-0 d-inline-block">
                                <i class="fas fa-clock me-1"></i><?php echo date('H:i'); ?>
                            </span>
                            <span class="badge bg-info text-white d-inline-block">
                                <i class="fas fa-calendar-alt me-1"></i><?php echo date('l, F j, Y'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-5 col-12">
                        <div class="d-flex flex-column align-items-center align-items-md-end">
                            <div class="text-white-75 mb-2 text-center text-md-end">
                                <i class="fas fa-map-marker-alt me-2 text-warning"></i>
                                <span class="fw-semibold">PNG Operations</span>
                            </div>
                            <div class="text-white-50 small text-center text-md-end mb-3">
                                <i class="fas fa-satellite me-2 text-info"></i>
                                GPS Tracking Active
                            </div>
                            <div class="d-flex flex-column flex-sm-row gap-2 w-100 justify-content-center justify-content-md-end">
                                <a href="add-vehicle.php" class="btn btn-modern btn-primary btn-sm flex-fill flex-sm-grow-0">
                                    <i class="fas fa-plus me-2"></i><span class="d-none d-sm-inline">Add Vehicle</span><span class="d-sm-none">Add Car</span>
                                </a>
                                <a href="bookings.php" class="btn btn-modern btn-outline-primary btn-sm flex-fill flex-sm-grow-0">
                                    <i class="fas fa-calendar me-2"></i><span class="d-none d-sm-inline">View Bookings</span><span class="d-sm-none">Bookings</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Mobile-Responsive Statistics Cards -->
            <div class="row mb-3">
                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card h-100 position-relative overflow-hidden">
                        <div class="stats-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['total_bookings']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold text-center text-md-start">Total Bookings</p>
                        <div class="position-absolute top-0 end-0 p-3 opacity-25 d-none d-md-block">
                            <i class="fas fa-calendar-check fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card success h-100 position-relative overflow-hidden">
                        <div class="stats-icon">
                            <i class="fas fa-car"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['active_bookings']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold text-center text-md-start">Active Bookings</p>
                        <div class="position-absolute top-0 end-0 p-3 opacity-25 d-none d-md-block">
                            <i class="fas fa-car fa-2x text-success"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card info h-100 position-relative overflow-hidden">
                        <div class="stats-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo formatCurrency($stats['total_revenue']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold text-center text-md-start">Total Revenue</p>
                        <div class="position-absolute top-0 end-0 p-3 opacity-25 d-none d-md-block">
                            <i class="fas fa-money-bill-wave fa-2x text-info"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card warning h-100 position-relative overflow-hidden">
                        <div class="stats-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($stats['total_users']); ?></h3>
                        <p class="text-muted mb-0 fw-semibold text-center text-md-start">Registered Users</p>
                        <div class="position-absolute top-0 end-0 p-3 opacity-25 d-none d-md-block">
                            <i class="fas fa-users fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Workflow Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card warning h-100 position-relative overflow-hidden">
                        <div class="stats-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($confirmed_no_payment); ?></h3>
                        <p class="text-muted mb-0 fw-semibold text-center text-md-start">Confirmed - Payment Required</p>
                        <div class="position-absolute top-0 end-0 p-3 opacity-25 d-none d-md-block">
                            <i class="fas fa-clock fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card info h-100 position-relative overflow-hidden">
                        <div class="stats-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($payment_verification); ?></h3>
                        <p class="text-muted mb-0 fw-semibold text-center text-md-start">Payment Verification</p>
                        <div class="position-absolute top-0 end-0 p-3 opacity-25 d-none d-md-block">
                            <i class="fas fa-search fa-2x text-info"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card secondary h-100 position-relative overflow-hidden">
                        <div class="stats-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($pending_agreements); ?></h3>
                        <p class="text-muted mb-0 fw-semibold text-center text-md-start">Pending Agreements</p>
                        <div class="position-absolute top-0 end-0 p-3 opacity-25 d-none d-md-block">
                            <i class="fas fa-file-contract fa-2x text-secondary"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
                    <div class="stat-card success h-100 position-relative overflow-hidden">
                        <div class="stats-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="fw-bold mb-2 stat-number"><?php echo number_format($pending_payments); ?></h3>
                        <p class="text-muted mb-0 fw-semibold text-center text-md-start">Pending Payments</p>
                        <div class="position-absolute top-0 end-0 p-3 opacity-25 d-none d-md-block">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Notifications -->
            <?php if ($pending_payments > 0): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="card-header bg-transparent border-0 p-4">
                            <h5 class="mb-0 text-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Payment Verification Required (<?php echo $pending_payments; ?>)
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="alert alert-warning mb-3">
                                <i class="fas fa-clock me-2"></i>
                                <strong><?php echo $pending_payments; ?> payment(s)</strong> are waiting for admin verification.
                                <a href="payments.php" class="btn btn-warning btn-sm ms-3">
                                    <i class="fas fa-credit-card me-1"></i>Review Payments
                                </a>
                            </div>
                            
                            <?php if (!empty($recent_pending_payments)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Customer</th>
                                            <th>Vehicle</th>
                                            <th>Method</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_pending_payments as $payment): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($payment['vehicle_name']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?>
                                                </span>
                                            </td>
                                            <td><strong><?php echo formatCurrency($payment['amount']); ?></strong></td>
                                            <td><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></td>
                                            <td>
                                                <a href="payments.php" class="btn btn-outline-warning btn-sm">
                                                    <i class="fas fa-eye me-1"></i>Review
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- New Booking Notifications -->
            <?php 
            // Get count of new pending bookings
            $new_bookings_query = "SELECT COUNT(*) as pending_count FROM bookings WHERE status = 'pending'";
            $new_bookings_result = mysqli_query($conn, $new_bookings_query);
            $new_bookings_count = mysqli_fetch_assoc($new_bookings_result)['pending_count'] ?? 0;
            
            // Get recent new bookings
            $recent_new_bookings_query = "SELECT b.*, u.first_name, u.last_name, u.email, v.name as vehicle_name, v.registration_number 
                                        FROM bookings b 
                                        JOIN users u ON b.user_id = u.id 
                                        JOIN vehicles v ON b.vehicle_id = v.id 
                                        WHERE b.status = 'pending' 
                                        ORDER BY b.created_at DESC LIMIT 5";
            $recent_new_bookings_result = mysqli_query($conn, $recent_new_bookings_query);
            $recent_new_bookings = [];
            if ($recent_new_bookings_result) {
                while ($row = mysqli_fetch_assoc($recent_new_bookings_result)) {
                    $recent_new_bookings[] = $row;
                }
            }
            ?>
            
            <?php if ($new_bookings_count > 0): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="card-header bg-transparent border-0 p-4">
                            <h5 class="mb-0 text-info">
                                <i class="fas fa-calendar-plus me-2"></i>
                                New Booking Requests (<?php echo $new_bookings_count; ?>)
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-clock me-2"></i>
                                <strong><?php echo $new_bookings_count; ?> new booking request(s)</strong> are waiting for admin review and approval.
                                <a href="bookings.php" class="btn btn-info btn-sm ms-3">
                                    <i class="fas fa-calendar me-1"></i>Review Bookings
                                </a>
                            </div>
                            
                            <?php if (!empty($recent_new_bookings)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Customer</th>
                                            <th>Vehicle</th>
                                            <th>Dates</th>
                                            <th>Total Amount</th>
                                            <th>Requested</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_new_bookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($booking['email']); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($booking['vehicle_name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($booking['registration_number']); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo date('M j', strtotime($booking['start_date'])); ?> - <?php echo date('M j', strtotime($booking['end_date'])); ?></strong>
                                                <br><small class="text-muted"><?php echo $booking['total_days']; ?> days</small>
                                            </td>
                                            <td><strong><?php echo formatCurrency($booking['total_amount']); ?></strong></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></td>
                                            <td>
                                                <a href="bookings.php" class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-eye me-1"></i>Review
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="card-header bg-transparent border-0 p-4">
                            <h5 class="mb-0 text-dark">
                                <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row">
                                <!-- Customer Bookings Management -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="quick-actions">
                                        <div class="text-center mb-3">
                                            <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                                            <h6 class="fw-bold">Customer Bookings</h6>
                                            <p class="text-muted small">View and manage all customer bookings</p>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <a href="bookings.php" class="action-btn">
                                                <i class="fas fa-list me-2"></i>View All Bookings
                                            </a>
                                            <a href="pending-bookings.php" class="action-btn">
                                                <i class="fas fa-clock me-2"></i>Pending Bookings
                                            </a>
                                            <a href="payments.php" class="action-btn">
                                                <i class="fas fa-credit-card me-2"></i>All Payments
                                            </a>
                                            <a href="sms-payments.php" class="action-btn">
                                                <i class="fas fa-mobile-alt me-2"></i>SMS Payments
                                            </a>
                                            <a href="payment-statements.php" class="action-btn">
                                                <i class="fas fa-file-invoice-dollar me-2"></i>View Statement Payment
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Customer Management -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="quick-actions">
                                        <div class="text-center mb-3">
                                            <i class="fas fa-users fa-2x text-success mb-2"></i>
                                            <h6 class="fw-bold">Customer Management</h6>
                                            <p class="text-muted small">Manage customer accounts and details</p>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <a href="users.php" class="action-btn">
                                                <i class="fas fa-user-friends me-2"></i>View All Customers
                                            </a>
                                            <a href="customer-search.php" class="action-btn">
                                                <i class="fas fa-search me-2"></i>Search Customers
                                            </a>
                                            <a href="customer-reports.php" class="action-btn">
                                                <i class="fas fa-chart-bar me-2"></i>Customer Reports
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Vehicle Management -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="quick-actions">
                                        <div class="text-center mb-3">
                                            <i class="fas fa-car fa-2x text-info mb-2"></i>
                                            <h6 class="fw-bold">Vehicle Management</h6>
                                            <p class="text-muted small">Manage fleet and vehicle details</p>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <a href="vehicles.php" class="action-btn">
                                                <i class="fas fa-car-side me-2"></i>View All Vehicles
                                            </a>
                                            <a href="add-vehicle.php" class="action-btn">
                                                <i class="fas fa-plus me-2"></i>Add New Vehicle
                                            </a>
                                            <a href="maintenance.php" class="action-btn">
                                                <i class="fas fa-tools me-2"></i>Maintenance Schedule
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reports & Analytics -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="quick-actions">
                                        <div class="text-center mb-3">
                                            <i class="fas fa-chart-line fa-2x text-warning mb-2"></i>
                                            <h6 class="fw-bold">Reports & Analytics</h6>
                                            <p class="text-muted small">View business insights and reports</p>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <a href="reports.php" class="action-btn">
                                                <i class="fas fa-file-alt me-2"></i>Business Reports
                                            </a>
                                            <a href="revenue-analytics.php" class="action-btn">
                                                <i class="fas fa-dollar-sign me-2"></i>Revenue Analytics
                                            </a>
                                            <a href="booking-analytics.php" class="action-btn">
                                                <i class="fas fa-chart-pie me-2"></i>Booking Analytics
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Workflow Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="card-header bg-transparent border-0 p-4">
                            <h5 class="mb-0 text-dark">
                                <i class="fas fa-sync-alt me-2 text-primary"></i>New Workflow Management
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row">
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="quick-actions">
                                        <div class="text-center mb-3">
                                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                            <h6 class="fw-bold">Payment Required</h6>
                                            <p class="text-muted small">Confirmed bookings waiting for payment</p>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <a href="bookings.php?filter=confirmed_no_payment" class="action-btn">
                                                <i class="fas fa-credit-card me-2"></i>View Bookings
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="quick-actions">
                                        <div class="text-center mb-3">
                                            <i class="fas fa-search fa-2x text-info mb-2"></i>
                                            <h6 class="fw-bold">Payment Verification</h6>
                                            <p class="text-muted small">Review submitted payments</p>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <a href="payments.php" class="action-btn">
                                                <i class="fas fa-eye me-2"></i>Review Payments
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="quick-actions">
                                        <div class="text-center mb-3">
                                            <i class="fas fa-file-contract fa-2x text-secondary mb-2"></i>
                                            <h6 class="fw-bold">Vehicle Agreements</h6>
                                            <p class="text-muted small">Review submitted agreements</p>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <a href="vehicle-agreements.php" class="action-btn">
                                                <i class="fas fa-check me-2"></i>Review Agreements
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="quick-actions">
                                        <div class="text-center mb-3">
                                            <i class="fas fa-play fa-2x text-success mb-2"></i>
                                            <h6 class="fw-bold">Activate Bookings</h6>
                                            <p class="text-muted small">Activate completed agreements</p>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <a href="bookings.php?filter=ready_to_activate" class="action-btn">
                                                <i class="fas fa-rocket me-2"></i>Activate
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tracking Overview Section -->
            <div class="row mb-4">
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-satellite-dish me-2"></i>GPS Tracking Overview</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get tracking statistics
                            $tracking_stats_query = "SELECT 
                                COUNT(*) as total_vehicles,
                                SUM(CASE WHEN vt.status = 'moving' THEN 1 ELSE 0 END) as moving_vehicles,
                                SUM(CASE WHEN vt.status = 'stopped' THEN 1 ELSE 0 END) as stopped_vehicles,
                                SUM(CASE WHEN vt.status = 'offline' THEN 1 ELSE 0 END) as offline_vehicles,
                                SUM(CASE WHEN vt.gps_signal_strength IN ('excellent', 'good') THEN 1 ELSE 0 END) as good_gps_signal
                                FROM vehicles v 
                                LEFT JOIN vehicle_tracking vt ON v.id = vt.vehicle_id";
                            $tracking_stats_result = mysqli_query($conn, $tracking_stats_query);
                            $tracking_stats = mysqli_fetch_assoc($tracking_stats_result);
                            ?>
                            
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <i class="fas fa-route text-success fa-lg"></i>
                                        <div class="h5 mb-0"><?php echo $tracking_stats['moving_vehicles'] ?? 0; ?></div>
                                        <div class="small text-muted">Moving</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <i class="fas fa-pause-circle text-warning fa-lg"></i>
                                        <div class="h5 mb-0"><?php echo $tracking_stats['stopped_vehicles'] ?? 0; ?></div>
                                        <div class="small text-muted">Stopped</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <i class="fas fa-satellite text-info fa-lg"></i>
                                        <div class="h5 mb-0"><?php echo $tracking_stats['good_gps_signal'] ?? 0; ?></div>
                                        <div class="small text-muted">Good GPS</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="tracking-dashboard.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-map-marked-alt me-2"></i>View Live GPS Map
                                </a>
                                <a href="tracking-alerts.php" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-exclamation-triangle me-2"></i>View Tracking Alerts
                                </a>
                                <a href="gps-integration-guide.php" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-book me-2"></i>GPS Integration Guide
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="dashboard-card">
                        <div class="card-header bg-transparent border-0 p-4">
                            <h5 class="mb-0 text-dark">
                                <i class="fas fa-bell me-2 text-warning"></i>Alerts & Notifications
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (!empty($maintenance_due)): ?>
                            <div class="alert-modern alert-warning">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle me-3"></i>
                                    <div>
                                        <strong>Maintenance Due</strong>
                                        <div class="text-muted"><?php echo count($maintenance_due); ?> vehicle(s) need service</div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($insurance_due)): ?>
                            <div class="alert-modern alert-danger">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-shield-alt me-3"></i>
                                    <div>
                                        <strong>Insurance Due</strong>
                                        <div class="text-muted"><?php echo count($insurance_due); ?> vehicle(s) need renewal</div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($registration_due)): ?>
                            <div class="alert-modern alert-info">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-id-card me-3"></i>
                                    <div>
                                        <strong>Registration Due</strong>
                                        <div class="text-muted"><?php echo count($registration_due); ?> vehicle(s) need renewal</div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (empty($maintenance_due) && empty($insurance_due) && empty($registration_due)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
                                <h6 class="text-success">All Good!</h6>
                                <p class="text-muted mb-0">No urgent alerts at the moment</p>
                            </div>
                            <?php endif; ?>

                            <div class="mt-4">
                                <a href="maintenance.php" class="btn btn-modern btn-outline-warning w-100 mb-2">
                                    <i class="fas fa-tools me-2"></i>View Maintenance
                                </a>
                                <a href="vehicles.php" class="btn btn-modern btn-outline-info w-100">
                                    <i class="fas fa-car me-2"></i>Manage Vehicles
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="row">
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="card-header bg-transparent border-0 p-4">
                            <h5 class="mb-0 text-dark">
                                <i class="fas fa-chart-line me-2 text-primary"></i>Revenue Overview
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile interaction enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Touch-friendly navigation
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.95)';
                });
                link.addEventListener('touchend', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Responsive chart sizing
            function resizeChart() {
                const chartContainer = document.querySelector('.chart-container');
                if (window.innerWidth < 768) {
                    chartContainer.style.height = '250px';
                } else {
                    chartContainer.style.height = '300px';
                }
            }

            // Initial resize and on window resize
            resizeChart();
            window.addEventListener('resize', resizeChart);

            // Mobile menu improvements
            const navbarToggler = document.querySelector('.navbar-toggler');
            const navbarCollapse = document.querySelector('.navbar-collapse');
            
            if (navbarToggler && navbarCollapse) {
                navbarToggler.addEventListener('click', function() {
                    // Add smooth animation
                    navbarCollapse.style.transition = 'all 0.3s ease';
                });
            }
        });

        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Monthly Revenue',
                    data: <?php echo json_encode($chart_data); ?>,
                    borderColor: '#1e3a8a',
                    backgroundColor: 'rgba(30, 58, 138, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#1e3a8a',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'PGK ' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                elements: {
                    point: {
                        hoverBackgroundColor: '#1e3a8a'
                    }
                }
            }
        });

        // Add loading states
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach(card => {
                card.addEventListener('click', function() {
                    this.classList.add('loading');
                    setTimeout(() => {
                        this.classList.remove('loading');
                    }, 1000);
                });
            });
        });
    </script>
</body>
</html> 