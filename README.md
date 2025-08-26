# MG Transport - Car Hire Booking Platform

A comprehensive online car hire booking platform built with PHP and MySQL, designed for XAMPP deployment. This system enables customers to book vehicles, calculate payments with GST, and provides administrators with a robust dashboard for vehicle and booking management.

## 🚀 Features

### Customer Features
- ✅ **Online Booking System** - Real-time vehicle availability and booking
- ✅ **Automated GST Calculation** - Automatic Goods and Services Tax computation
- ✅ **Secure Payment Integration** - Multiple payment methods (Credit/Debit cards, Bank Transfer, Cash)
- ✅ **Invoice Generation** - Automated invoice creation and email notifications
- ✅ **Vehicle Pool Management** - View available vehicles, types, rates, and availability
- ✅ **User Registration & Authentication** - Secure user accounts with role-based access

### Admin Dashboard Features
- ✅ **Vehicle Management** - Add, edit, remove vehicles and manage availability
- ✅ **Booking Management** - View and manage all customer bookings
- ✅ **Maintenance Scheduling** - Schedule and track vehicle maintenance
- ✅ **Financial Reports** - Generate earnings reports and GST collection summaries
- ✅ **Customer Management** - Track customer transactions and booking history
- ✅ **Service Reminders** - Automated notifications for maintenance, insurance, and registration
- ✅ **Multi-level Security** - Different administrative access levels (Admin, Super Admin)
- ✅ **Real-time Notifications** - System alerts and status updates

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL (XAMPP)
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Icons**: Font Awesome 6
- **Charts**: Chart.js
- **Email**: PHP mail() function

## 📋 Requirements

- XAMPP (Apache + MySQL + PHP)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser with JavaScript enabled

## 🚀 Installation & Setup

### 1. XAMPP Setup
1. Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Start Apache and MySQL services in XAMPP Control Panel

### 2. Project Setup
1. Clone or download this project to your XAMPP htdocs folder:
   ```
   C:\xampp\htdocs\MG Transport\
   ```

2. Open your web browser and navigate to:
   ```
   http://localhost/MG%20Transport/
   ```

3. The system will automatically:
   - Create the database `mg_transport`
   - Create all required tables
   - Insert sample data
   - Set up default admin account

### 3. Default Admin Credentials
- **Username**: admin
- **Password**: admin123
- **Email**: admin@mgtransport.com

### 4. Database Configuration
The database configuration is in `config/database.php`. Default settings:
- **Host**: localhost
- **Database**: mg_transport
- **Username**: root
- **Password**: (empty)

## 📁 Project Structure

```
MG Transport/
├── admin/                 # Admin dashboard files
│   ├── dashboard.php     # Main admin dashboard
│   ├── vehicles.php      # Vehicle management
│   ├── bookings.php      # Booking management
│   ├── maintenance.php   # Maintenance scheduling
│   ├── users.php         # User management
│   ├── reports.php       # Financial reports
│   └── settings.php      # System settings
├── assets/               # Static assets
│   ├── css/             # Stylesheets
│   ├── js/              # JavaScript files
│   └── images/          # Vehicle images
├── config/              # Configuration files
│   └── database.php     # Database connection
├── includes/            # PHP includes
│   ├── functions.php    # Helper functions
│   ├── header.php       # Site header
│   └── footer.php       # Site footer
├── index.php            # Homepage
├── login.php            # User login
├── register.php         # User registration
├── booking.php          # Booking system
├── vehicles.php         # Vehicle listing
├── dashboard.php        # Customer dashboard
├── my-bookings.php      # Customer bookings
├── logout.php           # Logout functionality
└── README.md           # This file
```

## 🎯 Key Features Explained

### GST Calculation
The system automatically calculates GST (Goods and Services Tax) at 10% by default. This can be modified in the admin settings.

### Vehicle Management
- Add new vehicles with detailed specifications
- Upload vehicle images
- Set daily rates and availability
- Track maintenance schedules
- Monitor insurance and registration expiry

### Booking System
- Real-time availability checking
- Date range validation
- Automatic price calculation with GST
- Multiple payment method support
- Email confirmation and invoice generation

### Admin Dashboard
- **Statistics Overview**: Total bookings, revenue, active bookings, available vehicles
- **Recent Bookings**: Latest customer bookings with status tracking
- **Maintenance Alerts**: Vehicles due for service, insurance, or registration renewal
- **Quick Actions**: Direct access to common admin tasks

### Security Features
- Password hashing using PHP's `password_hash()`
- SQL injection prevention with prepared statements
- XSS protection with `htmlspecialchars()`
- Role-based access control
- Session management

## 🔧 Configuration

### System Settings
Access admin settings to configure:
- GST Rate (default: 10%)
- Company Information
- Email Settings
- Maintenance Reminder Days
- Currency Format

### Email Configuration
The system uses PHP's `mail()` function. For production, consider:
- Setting up SMTP configuration
- Using PHPMailer or similar library
- Configuring proper email headers

## 📊 Database Schema

### Main Tables
- **users**: User accounts and authentication
- **vehicles**: Vehicle inventory and specifications
- **bookings**: Customer booking records
- **maintenance_schedule**: Vehicle maintenance tracking
- **invoices**: Invoice generation and management
- **notifications**: System notifications
- **system_settings**: Application configuration

## 🚨 Security Considerations

1. **Change Default Admin Password**: Update the admin password after first login
2. **Database Security**: Use strong MySQL passwords in production
3. **File Permissions**: Ensure proper file permissions on web server
4. **HTTPS**: Use SSL certificates for production deployment
5. **Regular Backups**: Implement database backup procedures

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Ensure MySQL is running in XAMPP
   - Check database credentials in `config/database.php`

2. **Page Not Found**
   - Verify Apache is running in XAMPP
   - Check file paths and permissions

3. **Email Not Working**
   - Configure SMTP settings for production
   - Check server email configuration

4. **Image Upload Issues**
   - Ensure `assets/images/` directory is writable
   - Check file upload limits in PHP configuration

## 🔄 Updates and Maintenance

### Regular Maintenance Tasks
1. **Database Backups**: Regular backup of MySQL database
2. **Log Monitoring**: Check error logs for issues
3. **Security Updates**: Keep PHP and MySQL updated
4. **Vehicle Maintenance**: Regular updates to maintenance schedules

### Adding New Features
1. Follow the existing code structure
2. Use prepared statements for database queries
3. Implement proper validation and sanitization
4. Test thoroughly before deployment

## 📞 Support

For technical support or feature requests:
- Email: support@mgtransport.com
- Documentation: Check inline code comments
- Issues: Review error logs in XAMPP

## 📄 License

This project is licensed under the MIT License. See LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

---

**MG Transport Booking System** - Making car hire simple, secure, and efficient.

*Built with ❤️ for XAMPP deployment* 