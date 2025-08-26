# Customer Removal System Guide

## Overview
The MG Transport system now includes a comprehensive customer removal system that allows administrators to safely remove all customer accounts and related data from the system while preserving admin accounts and system functionality.

## What Gets Removed
When you remove all customers, the following data will be permanently deleted:

- **Customer User Accounts**: All users with role 'customer'
- **Customer Bookings**: All booking records associated with customers
- **Customer Notifications**: All notification records for customers
- **Customer Payment Records**: All payment requests and receipts for customer bookings
- **Customer Verification Codes**: All SMS verification codes for customer transactions
- **Customer Invoices**: All invoice records for customer bookings

## What Gets Preserved
The following data will remain intact:

- **Admin Accounts**: All users with role 'admin' or 'super_admin'
- **Vehicles**: All vehicle records and maintenance schedules
- **System Settings**: Company information, GST rates, API keys, etc.
- **Tracking Data**: GPS tracking information and vehicle locations
- **System Logs**: Security logs, operation logs, and error logs

## How to Use

### Option 1: Web Interface (Recommended)
1. **Access the Admin Panel**: Log in to the admin dashboard
2. **Navigate to Customer Removal**: 
   - Use the sidebar link: "Remove All Customers"
   - Or use the quick action button in the GPS Tracking Overview section
3. **Review the Information**: The page will show you:
   - Current customer count
   - Related data counts (bookings, payments, etc.)
   - What will be removed and preserved
4. **Confirm Removal**: 
   - Check the confirmation checkbox
   - Click "Remove All Customers"
   - Confirm the final warning dialog

### Option 2: Command Line Script
1. **Access the Script**: Navigate to the root directory
2. **Run the Script**: Execute `php remove_customers.php`
3. **Confirm Action**: Type 'yes' when prompted
4. **Monitor Progress**: Watch the console output for progress updates

## Safety Features

### Database Transactions
- All operations are wrapped in database transactions
- If any step fails, all changes are automatically rolled back
- No partial deletions occur

### Admin Authentication
- Only users with 'admin' or 'super_admin' roles can access the removal system
- Session validation ensures secure access

### Confirmation Requirements
- Multiple confirmation steps prevent accidental deletions
- Clear warnings about permanent data loss
- Detailed information about what will be affected

### Logging
- All removal operations are logged to `logs/system_operations.log`
- Includes admin username, timestamp, and count of removed customers
- Provides audit trail for compliance

## Before You Begin

### Backup Recommendation
While the system is designed to be safe, consider creating a database backup before proceeding:
```sql
mysqldump -u root -p mg_transport > backup_before_customer_removal.sql
```

### Verify Current State
Use the test script to check the current system state:
```bash
php test_customer_removal.php
```

### Check Dependencies
Ensure no critical business processes depend on customer data that will be removed.

## After Removal

### Verify Results
1. Check that all customer accounts are gone
2. Verify that admin accounts remain intact
3. Confirm that vehicles and system settings are preserved
4. Test that GPS tracking still functions

### System Cleanup
- The system will automatically clean up orphaned records
- All foreign key constraints are properly handled
- Database integrity is maintained

## Troubleshooting

### Common Issues

**"Access Denied" Error**
- Ensure you're logged in as an admin or super admin
- Check that your session is valid

**"No Customers Found" Message**
- The system already has no customer accounts
- No action is needed

**Database Errors**
- Check database connection
- Verify table structures
- Review error logs for specific issues

### Recovery Options
If you need to restore customer data:
1. Use the database backup created before removal
2. Contact system administrator for assistance
3. Check system operation logs for details

## File Locations

- **Web Interface**: `admin/remove-customers.php`
- **Command Line Script**: `remove_customers.php`
- **Test Script**: `test_customer_removal.php`
- **Operation Logs**: `logs/system_operations.log`
- **Error Logs**: `logs/error.log`

## Security Considerations

- Only authorized administrators can access the removal system
- All actions are logged for audit purposes
- Session validation prevents unauthorized access
- CSRF protection is implemented in the web interface

## Support

If you encounter any issues or need assistance:
1. Check the system logs for error details
2. Review this guide for troubleshooting steps
3. Contact the system administrator
4. Refer to the test script output for diagnostic information

---

**Important**: This action is irreversible. Once customers are removed, their data cannot be recovered unless you have a backup. Use this system responsibly and only when absolutely necessary.
