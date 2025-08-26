# Vehicle Agreement System - MG Transport Services

## Overview
The Vehicle Agreement System allows clients to submit formal vehicle rental agreements through a structured form, similar to the printed agreement form used by MG Transport Services. This system streamlines the vehicle rental process and provides administrators with a comprehensive way to manage and review client requests.

## Features

### For Clients
- **Agreement Form**: Complete vehicle rental agreement form with all required fields
- **Vehicle Selection**: Choose from available vehicles in the system
- **Auto-fill**: Form automatically populates with user and vehicle information
- **Status Tracking**: View the status of submitted agreements
- **History**: Access to all previous agreement submissions

### For Administrators
- **Agreement Management**: Review, approve, reject, or complete agreements
- **Status Updates**: Change agreement status with optional admin notes
- **Search & Filter**: Find agreements by status, organization, or contact details
- **Customer Information**: View complete customer and vehicle details
- **Audit Trail**: Track who processed each agreement and when

## System Flow

1. **Client submits agreement** → Status: Pending
2. **Admin reviews agreement** → Can approve, reject, or request changes
3. **If approved** → Status: Approved (ready for vehicle pickup)
4. **If rejected** → Status: Rejected (with admin notes)
5. **After vehicle return** → Status: Completed

## Database Structure

### vehicle_agreements Table
- `id`: Unique identifier
- `user_id`: Reference to user who submitted agreement
- `vehicle_id`: Reference to requested vehicle
- `organization_company`: Company/organization name
- `business_address`: Business address
- `contact_name`: Primary contact person
- `telephone_email`: Contact information
- `position`: Contact person's position
- `division_branch_section`: Department/division
- `vehicle_registration`: Vehicle registration number
- `vehicle_make_type`: Vehicle make and type
- `vehicle_model`: Vehicle model
- `vehicle_colour`: Vehicle color
- `vehicle_mileage`: Current mileage
- `pickup_date`: Requested pickup date
- `return_date`: Requested return date
- `pickup_time`: Pickup time
- `dropoff_time`: Drop-off time
- `number_of_days`: Calculated rental duration
- `agreement_status`: Current status (pending/approved/rejected/completed)
- `admin_notes`: Notes from administrators
- `admin_approved_by`: Admin who processed the agreement
- `admin_approved_at`: When the agreement was processed
- `created_at`: When the agreement was submitted
- `updated_at`: Last update timestamp

## File Structure

```
├── vehicle-agreement.php          # Client agreement form
├── my-agreements.php             # Client agreement history
├── admin/
│   └── vehicle-agreements.php    # Admin agreement management
├── config/
│   └── database.php              # Database schema (updated)
└── includes/
    └── header.php                # Navigation (updated)
```

## Usage Instructions

### For Clients

1. **Browse Vehicles**: Go to `vehicles.php` to see available vehicles
2. **Submit Agreement**: Click "Agreement Form" button on any available vehicle
3. **Fill Form**: Complete all required fields in the agreement form
4. **Submit**: Click "Submit Agreement" to send for review
5. **Track Status**: Check "My Agreements" in your user menu to monitor progress

### For Administrators

1. **Access Management**: Go to Admin Dashboard → Vehicle Agreements
2. **Review Agreements**: View all submitted agreements with filtering options
3. **Process Requests**: Approve, reject, or add notes to agreements
4. **Status Updates**: Change agreement status as needed
5. **Customer Communication**: Use admin notes to communicate with clients

## Form Fields

### Organization & Contact Information
- Organization/Company
- Business Address
- Contact Name
- Telephone/Email Address
- Position
- Division/Branch/Section

### Vehicle Details
- Vehicle Registration
- Make/Type
- Model
- Colour
- Vehicle Mileage (KM/Hour)

### Rental Period
- Pickup Date
- Return Date
- Pickup Time
- Drop-off Time

### Terms and Conditions
- Liability acknowledgment
- Rental commencement terms
- User responsibility agreement

## Security Features

- **Session-based authentication**: Only logged-in users can submit agreements
- **Input validation**: All form fields are validated and sanitized
- **SQL injection protection**: Prepared statements for all database queries
- **XSS protection**: HTML special characters are escaped
- **Admin access control**: Only admin users can manage agreements

## Integration Points

- **User System**: Integrates with existing user authentication
- **Vehicle System**: Links to vehicle inventory and availability
- **Notification System**: Can be extended to send email/SMS notifications
- **Booking System**: Complements existing booking functionality
- **Admin Dashboard**: Integrated into admin management interface

## Future Enhancements

- **Email Notifications**: Automatic emails for status changes
- **SMS Alerts**: Text message notifications for urgent updates
- **Document Generation**: PDF generation of approved agreements
- **Digital Signatures**: Electronic signature capture
- **Payment Integration**: Link agreements to payment processing
- **Mobile App**: Native mobile application for agreement submission

## Troubleshooting

### Common Issues

1. **Form not submitting**: Check if all required fields are filled
2. **Vehicle not found**: Ensure vehicle ID is valid in URL
3. **Permission denied**: Verify user is logged in and has appropriate access
4. **Database errors**: Check database connection and table structure

### Support

For technical support or questions about the Vehicle Agreement System, contact the system administrator or refer to the main MG Transport Services documentation.

---

**Version**: 1.0  
**Last Updated**: December 2024  
**System**: MG Transport Services Vehicle Management Platform
